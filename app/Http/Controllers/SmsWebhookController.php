<?php

namespace App\Http\Controllers;

use App\Models\SmsConnection;
use App\Models\SmsWebhookEvent;
use App\Services\SmsConnectionService;
use App\Services\SmsMessageService;
use App\Services\SmsProviderService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SmsWebhookController extends Controller
{
    public function handle(
        Request $request,
        SmsConnectionService $connectionService,
        SmsProviderService $provider,
        SmsMessageService $messageService,
    ): Response {
        $parameters = $request->all();
        $accountId = (string) ($parameters['AccountSid'] ?? '');
        $connection = SmsConnection::query()
            ->where('provider', 'twilio')
            ->where('provider_account_id', $accountId)
            ->latest('id')
            ->first();
        $connection ??= $connectionService->shared();
        $authToken = $connection?->auth_token ?? config('services.sms.webhook_auth_token');
        $signature = (string) $request->header('X-Twilio-Signature');
        $url = (string) (config('services.sms.webhook_url') ?: $request->fullUrl());

        if (! $provider->validWebhookSignature($url, $parameters, $signature, $authToken)) {
            return response('Invalid webhook signature.', 403);
        }

        $messageId = (string) ($parameters['MessageSid'] ?? '');
        $status = (string) ($parameters['MessageStatus'] ?? $parameters['SmsStatus'] ?? '');
        $dedupeKey = hash('sha256', $accountId.'|'.$messageId.'|'.$status.'|'.$request->getContent());
        $event = SmsWebhookEvent::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'provider' => 'twilio',
                'provider_account_id' => $accountId ?: $connection?->provider_account_id,
                'provider_message_id' => $messageId ?: null,
                'event_type' => $status ? 'message.status' : 'message.received',
                'payload' => $parameters,
            ],
        );

        if ($event->wasRecentlyCreated) {
            try {
                if (filled($messageId) && filled($status)) {
                    $messageService->updateDeliveryStatus(
                        messageId: $messageId,
                        status: $status,
                        error: $parameters['ErrorMessage'] ?? $parameters['ErrorCode'] ?? null,
                    );
                }

                $event->forceFill(['processed_at' => now(), 'error_message' => null])->save();
            } catch (Throwable $exception) {
                $event->forceFill(['error_message' => (string) str($exception->getMessage())->limit(1000)])->save();
            }
        }

        return response()->json(['received' => true]);
    }
}
