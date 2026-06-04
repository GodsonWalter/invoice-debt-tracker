<?php

namespace App\Models;

use App\Services\TemplateRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    public const TYPE_BEFORE_DUE = 'before_due';

    public const TYPE_DUE_TODAY = 'due_today';

    public const TYPE_OVERDUE = 'overdue';

    public const TYPES = [
        self::TYPE_BEFORE_DUE,
        self::TYPE_DUE_TODAY,
        self::TYPE_OVERDUE,
    ];

    public const PLACEHOLDERS = [
        '{{client_name}}',
        '{{client_email}}',
        '{{invoice_number}}',
        '{{invoice_total}}',
        '{{balance_due}}',
        '{{due_date}}',
        '{{business_name}}',
        '{{workspace_name}}',
        '{{reminder_type}}',
        '{{current_date}}',
    ];

    protected $fillable = [
        'workspace_id',
        'name',
        'type',
        'subject',
        'body',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function renderSubject(Invoice $invoice, TemplateRenderer $renderer): string
    {
        return $renderer->render($this->subject, $invoice, [
            'reminder_type' => $this->typeLabel(),
        ]);
    }

    public function renderBody(Invoice $invoice, TemplateRenderer $renderer): string
    {
        return $renderer->render($this->body, $invoice, [
            'reminder_type' => $this->typeLabel(),
        ]);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_BEFORE_DUE => 'Before Due',
            self::TYPE_DUE_TODAY => 'Due Today',
            self::TYPE_OVERDUE => 'Overdue',
            default => str($this->type)->replace('_', ' ')->title()->toString(),
        };
    }

    public static function typeForReminderSchedule(ReminderSchedule $schedule): string
    {
        if ($schedule->direction === ReminderSchedule::DIRECTION_AFTER_DUE) {
            return self::TYPE_OVERDUE;
        }

        return $schedule->days_offset === 0
            ? self::TYPE_DUE_TODAY
            : self::TYPE_BEFORE_DUE;
    }

    /**
     * @return array{subject: string, body: string}
     */
    public static function defaultContentForType(string $type): array
    {
        return match ($type) {
            self::TYPE_DUE_TODAY => [
                'subject' => 'Invoice {{invoice_number}} is Due Today',
                'body' => "Hello {{client_name}},\n\nThis is a reminder that invoice {{invoice_number}} for {{invoice_total}} is due today.\n\nOutstanding balance:\n{{balance_due}}\n\nYou can review the invoice and complete payment at your convenience.\n\nThank you,\n{{business_name}}",
            ],
            self::TYPE_OVERDUE => [
                'subject' => 'Payment Overdue Notice for Invoice {{invoice_number}}',
                'body' => "Hello {{client_name}},\n\nInvoice {{invoice_number}} for {{invoice_total}} was due on {{due_date}} and is now overdue.\n\nOutstanding balance:\n{{balance_due}}\n\nPlease arrange payment as soon as possible. If payment has already been made, please disregard this notice.\n\nThank you,\n{{business_name}}",
            ],
            default => [
                'subject' => 'Invoice {{invoice_number}} is Due Soon',
                'body' => "Hello {{client_name}},\n\nThis is a reminder that invoice {{invoice_number}} for {{invoice_total}} is due on {{due_date}}.\n\nOutstanding balance:\n{{balance_due}}\n\nThank you,\n{{business_name}}",
            ],
        };
    }
}
