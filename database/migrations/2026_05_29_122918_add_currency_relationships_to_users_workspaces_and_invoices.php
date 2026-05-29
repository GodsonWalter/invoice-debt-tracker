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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_currency_id')
                ->nullable()
                ->after('role')
                ->constrained('currencies')
                ->nullOnDelete();
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->foreignId('currency_id')
                ->nullable()
                ->after('invoice_prefix')
                ->constrained('currencies')
                ->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('currency_id')
                ->nullable()
                ->after('client_id')
                ->constrained('currencies')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_currency_id');
        });
    }
};
