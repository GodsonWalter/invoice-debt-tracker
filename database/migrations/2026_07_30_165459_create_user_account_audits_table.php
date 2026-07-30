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
        Schema::create('user_account_audits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->string('target_name')->nullable();
            $table->string('target_email');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_global_role')->nullable();
            $table->string('actor_type');
            $table->string('event');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['target_user_id', 'created_at'], 'user_account_audits_target_created_idx');
            $table->index(['actor_user_id', 'created_at'], 'user_account_audits_actor_created_idx');
            $table->index('event', 'user_account_audits_event_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_account_audits');
    }
};
