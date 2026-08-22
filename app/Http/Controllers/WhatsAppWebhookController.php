<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsAppMessageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request, WhatsAppMessageService $messageService): Response
    {
        if ($request->isMethod('GET')) {
            return $this->verify($request);
        }

        $rawPayload = $request->getContent();
        $secret = config('services.whatsapp.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $rawPayload, (string) $secret);

        if (! filled($secret) || ! hash_equals($expected, $signature)) {
            return response('Invalid webhook signature.', 403);
        }

        $payload = json_decode($rawPayload, true);

        if (! is_array($payload)) {
            return response('Invalid webhook payload.', 422);
        }

        $value = data_get($payload, 'entry.0.changes.0.value', []);
        $metadata = $value['metadata'] ?? [];
        $status = $value['statuses'][0] ?? null;
        $dedupeKey = hash('sha256', $rawPayload);
        $event = WhatsAppWebhookEvent::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'waba_id' => data_get($payload, 'entry.0.id'),
                'phone_number_id' => $metadata['phone_number_id'] ?? null,
                'event_type' => $status ? 'message.status' : 'message.received',
                'payload' => $payload,
            ],
        );

        if ($event->wasRecentlyCreated) {
            try {
                if (is_array($status) && filled($status['id'] ?? null)) {
                    $messageService->updateDeliveryStatus(
                        messageId: $status['id'],
                        status: (string) ($status['status'] ?? ''),
                        timestamp: isset($status['timestamp']) ? (string) $status['timestamp'] : null,
                        error: data_get($status, 'errors.0.title'),
                    );
                }

                $event->forceFill(['processed_at' => now(), 'error_message' => null])->save();
            } catch (Throwable $exception) {
                $event->forceFill(['error_message' => (string) str($exception->getMessage())->limit(1000)])->save();
            }
        }

        return response()->json(['received' => true]);
    }

    private function verify(Request $request): Response
    {
        $verifyToken = (string) config('services.whatsapp.webhook_verify_token');

        if ($request->string('hub.mode')->toString() === 'subscribe'
            && filled($verifyToken)
            && hash_equals($verifyToken, $request->string('hub.verify_token')->toString())) {
            return response($request->string('hub.challenge')->toString());
        }

        return response('Webhook verification failed.', 403);
    }
}
