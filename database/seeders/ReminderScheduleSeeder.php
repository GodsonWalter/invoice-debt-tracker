<?php

namespace Database\Seeders;

use App\Models\ReminderSchedule;
use Illuminate\Database\Seeder;

class ReminderScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reminderSchedules = [
            [
                'name' => '3 Days Before Due',
                'days_offset' => 3,
                'direction' => ReminderSchedule::DIRECTION_BEFORE_DUE,
            ],
            [
                'name' => 'Due Today',
                'days_offset' => 0,
                'direction' => ReminderSchedule::DIRECTION_BEFORE_DUE,
            ],
            [
                'name' => '7 Days Overdue',
                'days_offset' => 7,
                'direction' => ReminderSchedule::DIRECTION_AFTER_DUE,
            ],
            [
                'name' => '14 Days Overdue',
                'days_offset' => 14,
                'direction' => ReminderSchedule::DIRECTION_AFTER_DUE,
            ],
        ];

        foreach ($reminderSchedules as $reminderSchedule) {
            ReminderSchedule::updateOrCreate(
                [
                    'workspace_id' => 1,
                    'direction' => $reminderSchedule['direction'],
                    'days_offset' => $reminderSchedule['days_offset'],
                ],
                [
                    'name' => $reminderSchedule['name'],
                    'is_active' => true,
                ],
            );
        }
    }
}
