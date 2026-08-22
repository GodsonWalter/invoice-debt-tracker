<?php

namespace App\Services;

use App\Exceptions\WhatsAppApiException;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WhatsAppCloudApiService
{
    public function sendReminder(
        WhatsAppConnection $connection,
        Invoice $invoice,
        string $recipientPhone,
        string $renderedBody,
        string $reminderType,
    ): array {
        $template = $connection->templates()
            ->where('type', $this->templateType($reminderType))
            ->where('is_active', true)
            ->first();

        if (! $template && ! config('services.whatsapp.allow_text_reminders')) {
            throw new WhatsAppApiException('An active approved WhatsApp reminder template is required for automatic reminders.');
        }

        $payload = $template
            ? $this->templatePayload($template, $invoice, $recipientPhone, $reminderType)
            : [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipientPhone,
                'type' => 'text',
                'text' => [
                    'preview_url' => true,
                    'body' => $renderedBody,
                ],
            ];

        $response = $this->request($connection)->post(
            $this->endpoint($connection->phone_number_id.'/messages'),
            $payload,
        );

        if ($response->failed()) {
            throw new WhatsAppApiException(
                $response->json('error.message', 'WhatsApp Cloud API rejected the message.'),
                $response->json() ?: ['status' => $response->status()],
            );
        }

        return [
            'request' => $payload,
            'response' => $response->json(),
            'message_id' => $response->json('messages.0.id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(WhatsAppConnection $connection): array
    {
        $response = $this->request($connection)->get($this->endpoint($connection->phone_number_id), [
            'fields' => 'display_phone_number,verified_name,quality_rating,status',
        ]);

        if ($response->failed()) {
            throw new WhatsAppApiException(
                $response->json('error.message', 'WhatsApp connection verification failed.'),
                $response->json() ?: ['status' => $response->status()],
            );
        }

        return $response->json();
    }

    public function subscribe(WhatsAppConnection $connection): void
    {
        if (! filled($connection->waba_id)) {
            return;
        }

        $response = $this->request($connection)->post($this->endpoint($connection->waba_id.'/subscribed_apps'));

        if ($response->failed()) {
            throw new WhatsAppApiException(
                $response->json('error.message', 'WhatsApp webhook subscription failed.'),
                $response->json() ?: ['status' => $response->status()],
            );
        }
    }

    /**
     * Exchange an Embedded Signup authorization code and discover an assigned WABA.
     *
     * @return array{access_token:string, business_portfolio_id:?string, waba_id:?string, phone_number_id:?string, display_phone_number:?string, verified_name:?string}
     */
    public function completeEmbeddedSignup(string $code): array
    {
        $configuration = config('services.whatsapp');
        $response = $this->request()->get($this->endpoint('oauth/access_token'), [
            'client_id' => $configuration['app_id'],
            'client_secret' => $configuration['app_secret'],
            'code' => $code,
            'redirect_uri' => $configuration['redirect_uri'],
        ]);

        if ($response->failed() || ! filled($response->json('access_token'))) {
            throw new WhatsAppApiException(
                $response->json('error.message', 'Embedded Signup token exchange failed.'),
                $response->json() ?: ['status' => $response->status()],
            );
        }

        $accessToken = $response->json('access_token');
        $businessId = $configuration['business_portfolio_id'];
        $wabaResponse = $this->request($accessToken)->get($this->endpoint($businessId.'/client_whatsapp_business_accounts'));
        $waba = $wabaResponse->json('data.0');
        $wabaId = $waba['id'] ?? null;
        $phone = null;

        if ($wabaId) {
            $phone = $this->request($accessToken)
                ->get($this->endpoint($wabaId.'/phone_numbers'))
                ->json('data.0');
        }

        return [
            'access_token' => $accessToken,
            'business_portfolio_id' => $businessId,
            'waba_id' => $wabaId,
            'phone_number_id' => $phone['id'] ?? null,
            'display_phone_number' => $phone['display_phone_number'] ?? null,
            'verified_name' => $phone['verified_name'] ?? null,
        ];
    }

    private function request(WhatsAppConnection|string|null $connection = null): PendingRequest
    {
        $token = $connection instanceof WhatsAppConnection
            ? $connection->access_token
            : ($connection ?: config('services.whatsapp.system_user_access_token'));

        return Http::baseUrl(rtrim(config('services.whatsapp.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.whatsapp.request_timeout', 20))
            ->when(filled($token), fn (PendingRequest $request) => $request->withToken($token));
    }

    private function endpoint(string $path): string
    {
        return trim(config('services.whatsapp.api_version'), '/').'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayload(WhatsAppTemplate $template, Invoice $invoice, string $recipientPhone, string $reminderType): array
    {
        $parameters = [];
        $body = $template->body ?: '';

        foreach (EmailTemplate::PLACEHOLDERS as $placeholder) {
            if (str_contains($body, $placeholder)) {
                $parameters[] = app(TemplateRenderer::class)->render($placeholder, $invoice, [
                    'reminder_type' => $reminderType,
                ]);
            }
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => $recipientPhone,
            'type' => 'template',
            'template' => [
                'name' => $template->template_name,
                'language' => ['code' => $template->language_code],
                ...($parameters === [] ? [] : [
                    'components' => [[
                        'type' => 'body',
                        'parameters' => array_map(fn (string $text): array => ['type' => 'text', 'text' => $text], $parameters),
                    ]],
                ]),
            ],
        ];
    }

    private function templateType(string $reminderType): string
    {
        return match ($reminderType) {
            'Due Today' => 'due_today',
            'Overdue' => 'overdue',
            default => 'before_due',
        };
    }
}
