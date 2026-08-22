<?php

namespace App\Services;

use App\Exceptions\SmsApiException;
use App\Models\SmsConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SmsProviderService
{
    /**
     * @return array<string, mixed>
     */
    public function verify(SmsConnection $connection): array
    {
        $this->assertCredentials($connection);

        $response = $this->client($connection)->get($this->accountPath($connection));
        $this->throwIfFailed($response);

        return is_array($response->json()) ? $response->json() : [];
    }

    /**
     * @return array{message_id:string|null,status:string|null,request:array<string,mixed>,response:array<string,mixed>}
     */
    public function send(SmsConnection $connection, string $recipientPhone, string $body): array
    {
        $this->assertProvider($connection);

        if (mb_strlen($body) > 1600) {
            throw new SmsApiException('SMS reminder content cannot exceed 1,600 characters.');
        }

        $payload = [
            'To' => '+'.$recipientPhone,
            'Body' => $body,
        ];

        if (filled($connection->messaging_service_id)) {
            $payload['MessagingServiceSid'] = $connection->messaging_service_id;
        } else {
            $payload['From'] = $connection->sender;
        }

        if (filled(config('services.sms.webhook_url'))) {
            $payload['StatusCallback'] = config('services.sms.webhook_url');
        }

        $response = $this->client($connection)
            ->asForm()
            ->post($this->messagesPath($connection), $payload);
        $this->throwIfFailed($response);
        $responseBody = is_array($response->json()) ? $response->json() : [];

        return [
            'message_id' => data_get($responseBody, 'sid'),
            'status' => data_get($responseBody, 'status'),
            'request' => $payload,
            'response' => $responseBody,
        ];
    }

    public function validWebhookSignature(string $url, array $parameters, string $signature, ?string $authToken): bool
    {
        if (! filled($authToken) || ! filled($signature)) {
            return false;
        }

        ksort($parameters);
        $data = $url;

        foreach ($parameters as $key => $value) {
            $data .= $key.(is_scalar($value) ? (string) $value : '');
        }

        return hash_equals(
            base64_encode(hash_hmac('sha1', $data, $authToken, true)),
            $signature,
        );
    }

    private function assertProvider(SmsConnection $connection): void
    {
        $this->assertCredentials($connection);

        if (! $connection->isReady()) {
            throw new SmsApiException('The selected SMS connection is not ready.');
        }
    }

    private function assertCredentials(SmsConnection $connection): void
    {
        if ($connection->provider !== 'twilio') {
            throw new SmsApiException('The configured SMS provider is not supported.');
        }

        if (! filled($connection->provider_account_id) || ! filled($connection->auth_token)) {
            throw new SmsApiException('The SMS provider account credentials are incomplete.');
        }
    }

    private function client(SmsConnection $connection): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.sms.api_base_url'), '/'))
            ->withBasicAuth((string) $connection->provider_account_id, (string) $connection->auth_token)
            ->acceptJson()
            ->timeout((int) config('services.sms.request_timeout', 20));
    }

    private function accountPath(SmsConnection $connection): string
    {
        return '/2010-04-01/Accounts/'.rawurlencode((string) $connection->provider_account_id).'.json';
    }

    private function messagesPath(SmsConnection $connection): string
    {
        return '/2010-04-01/Accounts/'.rawurlencode((string) $connection->provider_account_id).'/Messages.json';
    }

    private function throwIfFailed(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $body = is_array($response->json()) ? $response->json() : [];
        $message = (string) (data_get($body, 'message') ?: 'The SMS provider request failed.');

        throw new SmsApiException($message, $body);
    }
}
