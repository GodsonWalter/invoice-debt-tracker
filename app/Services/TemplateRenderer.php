<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Carbon;

class TemplateRenderer
{
    /**
     * @param  array<string, string>  $context
     */
    public function render(string $content, Invoice $invoice, array $context = []): string
    {
        $invoice->loadMissing(['client', 'currency', 'workspace.businessProfile', 'workspace.currency', 'payments']);

        return strtr($content, $this->placeholders($invoice, $context));
    }

    /**
     * @param  array<string, string>  $context
     * @return array<string, string>
     */
    private function placeholders(Invoice $invoice, array $context): array
    {
        $workspace = $invoice->workspace;
        $businessProfile = $workspace?->businessProfile;

        return [
            '{{client_name}}' => $invoice->client?->name ?? '',
            '{{client_email}}' => $invoice->client?->email ?? '',
            '{{invoice_number}}' => $invoice->invoice_number,
            '{{invoice_total}}' => $invoice->formatMoney($invoice->total_amount),
            '{{balance_due}}' => $invoice->formatMoney($invoice->remaining_balance),
            '{{due_date}}' => $invoice->due_date?->format('M j, Y') ?? '',
            '{{business_name}}' => $businessProfile?->display_name ?? $workspace?->name ?? config('app.name'),
            '{{workspace_name}}' => $workspace?->name ?? '',
            '{{reminder_type}}' => $context['reminder_type'] ?? str($invoice->reminder_status)->replace('_', ' ')->title()->toString(),
            '{{current_date}}' => Carbon::now()->format('M j, Y'),
        ];
    }
}
