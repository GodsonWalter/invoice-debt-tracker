<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('public_token')->nullable()->unique()->after('invoice_number');
            $table->timestamp('viewed_at')->nullable()->after('notes');
            $table->timestamp('downloaded_at')->nullable()->after('viewed_at');
            $table->timestamp('printed_at')->nullable()->after('downloaded_at');
        });

        DB::table('invoices')
            ->whereNull('public_token')
            ->orderBy('id')
            ->each(function (object $invoice): void {
                DB::table('invoices')
                    ->where('id', $invoice->id)
                    ->update(['public_token' => (string) Str::uuid()]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn([
                'public_token',
                'viewed_at',
                'downloaded_at',
                'printed_at',
            ]);
        });
    }
};
