<?php

namespace App\Services\AI;

use App\Exceptions\AI\AiServiceException;
use Illuminate\Support\Facades\Http;

class NaturalLanguageQueryService
{
    private const MODEL = 'gpt-5.4-mini';

    private const TIMEOUT = 10;

    public function __construct(
        private string $apiKey = '',
    ) {
        $this->apiKey = config('services.openai.api_key') ?: $apiKey;
    }

    public function parse(string $query): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(self::TIMEOUT)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => self::MODEL,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => $query,
                    ],
                ],
                'temperature' => 0,
            ]);

        if ($response->failed()) {
            throw AiServiceException::apiError($response->body());
        }

        $body = $response->json();

        if (! isset($body['choices'][0]['message']['content'])) {
            throw AiServiceException::noResponse();
        }

        $content = $body['choices'][0]['message']['content'];

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw AiServiceException::invalidJson($e->getMessage());
        }
    }

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a dashboard query parser. Your job is to convert natural language queries into structured JSON format.

CRITICAL RULES:
1. You MUST return ONLY valid JSON. No explanations, no text before or after.
2. You MUST NEVER generate SQL.
3. You MUST NEVER access any database.
4. You MUST ONLY use approved fields and operators.

RESPONSE FORMAT:
{
  "entity": "invoice",
  "filters": [],
  "sort": null,
  "limit": null
}

ALLOWED FIELDS:
- status (values: draft, sent, paid, partial, overdue)
- amount (numeric)
- due_date (ISO date format: YYYY-MM-DD)
- days_overdue (numeric)
- created_at (ISO date format: YYYY-MM-DD)

ALLOWED OPERATORS:
- = (equals)
- > (greater than)
- < (less than)
- >= (greater than or equal)
- <= (less than or equal)

COMMON QUERY MAPPINGS:
- "unpaid invoices" → status = "overdue" (or you could also interpret as: status != "paid")
- "paid invoices" → status = "paid"
- "overdue invoices" → status = "overdue"
- "draft invoices" → status = "draft"
- "sent invoices" → status = "sent"
- "partially paid" → status = "partial"

EXAMPLES:

Input: "Which invoices are overdue by more than 30 days?"
Output:
{
  "entity": "invoice",
  "filters": [
    {"field": "status", "operator": "=", "value": "overdue"},
    {"field": "days_overdue", "operator": ">", "value": 30}
  ],
  "sort": null,
  "limit": null
}

Input: "Show me unpaid invoices"
Output:
{
  "entity": "invoice",
  "filters": [
    {"field": "status", "operator": "=", "value": "overdue"}
  ],
  "sort": null,
  "limit": null
}

Input: "Show invoices above 500000"
Output:
{
  "entity": "invoice",
  "filters": [
    {"field": "amount", "operator": ">", "value": 500000}
  ],
  "sort": null,
  "limit": null
}

Input: "Show paid invoices"
Output:
{
  "entity": "invoice",
  "filters": [
    {"field": "status", "operator": "=", "value": "paid"}
  ],
  "sort": null,
  "limit": null
}

INSTRUCTIONS:
1. Parse the user's natural language query
2. Extract intent and constraints
3. Map to allowed fields and operators
4. Return ONLY the JSON object, nothing else
PROMPT;
    }
}
