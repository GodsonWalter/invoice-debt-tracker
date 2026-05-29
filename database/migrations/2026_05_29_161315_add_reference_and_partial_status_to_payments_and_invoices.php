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
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'reference')) {
                $table->string('reference')->nullable()->after('payment_method');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE invoices MODIFY status ENUM('draft', 'sent', 'partial', 'paid', 'overdue') NOT NULL DEFAULT 'draft'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('invoices')->where('status', 'partial')->update(['status' => 'sent']);
            DB::statement("ALTER TABLE invoices MODIFY status ENUM('draft', 'sent', 'paid', 'overdue') NOT NULL DEFAULT 'draft'");
        }

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'reference')) {
                $table->dropColumn('reference');
            }
        });
    }
};
