<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Workspace::query()->each(function (Workspace $workspace): void {
            foreach (EmailTemplate::TYPES as $type) {
                $content = EmailTemplate::defaultContentForType($type);

                EmailTemplate::updateOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'type' => $type,
                    ],
                    [
                        'name' => $this->nameForType($type),
                        'subject' => $content['subject'],
                        'body' => $content['body'],
                        'is_default' => true,
                        'is_active' => true,
                    ],
                );
            }
        });
    }

    private function nameForType(string $type): string
    {
        return match ($type) {
            EmailTemplate::TYPE_DUE_TODAY => 'Invoice Due Today',
            EmailTemplate::TYPE_OVERDUE => 'Payment Overdue Notice',
            default => 'Invoice Reminder',
        };
    }
}
