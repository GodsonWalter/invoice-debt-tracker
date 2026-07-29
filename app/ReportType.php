<?php

namespace App;

enum ReportType: string
{
    case REVENUE = 'revenue';
    case PAYMENTS = 'payments';
    case OUTSTANDING = 'outstanding';
    case OVERDUE = 'overdue';
    case CUSTOMERS = 'customers';
    case REMINDERS = 'reminders';
    case INVOICES = 'invoices';

    public function label(): string
    {
        return match ($this) {
            self::REVENUE => 'Revenue Report',
            self::PAYMENTS => 'Payments Report',
            self::OUTSTANDING => 'Outstanding Debt',
            self::OVERDUE => 'Overdue Invoices',
            self::CUSTOMERS => 'Customer Balances',
            self::REMINDERS => 'Reminder Activity',
            self::INVOICES => 'Invoice Report',
        };
    }
}
