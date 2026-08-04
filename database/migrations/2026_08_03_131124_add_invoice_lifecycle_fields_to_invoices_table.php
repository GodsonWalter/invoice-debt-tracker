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
        Schema::table('invoices', function (Blueprint $table): void {
            $table->enum('status', ['draft', 'sent', 'partial', 'paid', 'overdue', 'void'])
                ->default('draft')
                ->change();
            $table->timestamp('voided_at')->nullable()->after('status');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable()->after('voided_by');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['voided_at', 'voided_by', 'void_reason', 'deleted_at']);
            $table->enum('status', ['draft', 'sent', 'partial', 'paid', 'overdue'])
                ->default('draft')
                ->change();
        });
    }
};
