<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reminder_schedules', function (Blueprint $table): void {
            $table->boolean('include_invoice_pdf')->default(false);
        });

        DB::table('reminder_schedules')
            ->where('direction', 'after_due')
            ->update(['include_invoice_pdf' => true]);

        DB::table('reminder_schedules')
            ->where('direction', 'before_due')
            ->where('days_offset', 0)
            ->update(['include_invoice_pdf' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reminder_schedules', function (Blueprint $table): void {
            $table->dropColumn('include_invoice_pdf');
        });
    }
};
