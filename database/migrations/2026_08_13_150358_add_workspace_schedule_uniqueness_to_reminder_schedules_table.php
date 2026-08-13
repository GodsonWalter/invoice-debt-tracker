<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reminder_schedules', function (Blueprint $table): void {
            $table->unique(
                ['workspace_id', 'direction', 'days_offset'],
                'reminder_schedules_workspace_direction_offset_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reminder_schedules', function (Blueprint $table): void {
            $table->dropUnique('reminder_schedules_workspace_direction_offset_unique');
        });
    }
};
