<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->boolean('sms_auto_reminders_enabled')->default(false)->after('whatsapp_architecture');
            $table->string('sms_architecture', 20)->default('shared')->after('sms_auto_reminders_enabled');
        });

        Schema::create('sms_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('connection_type', 20)->index();
            $table->string('provider', 40)->default('twilio');
            $table->string('provider_account_id')->nullable()->index();
            $table->string('sender', 80)->nullable();
            $table->string('messaging_service_id')->nullable();
            $table->text('auth_token')->nullable();
            $table->string('status', 30)->default('not_connected')->index();
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'connection_type']);
            $table->index(['connection_type', 'status', 'is_enabled']);
        });

        Schema::create('sms_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sms_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['sms_connection_id', 'type']);
            $table->index(['workspace_id', 'is_active']);
        });

        Schema::create('sms_message_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sms_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reminder_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_phone', 30);
            $table->string('sender', 80)->nullable();
            $table->string('message_type', 30)->default('reminder');
            $table->string('status', 30)->default('queued')->index();
            $table->string('idempotency_key')->unique();
            $table->string('provider_message_id')->nullable()->index();
            $table->text('rendered_body')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['invoice_id', 'reminder_schedule_id']);
        });

        Schema::create('sms_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('dedupe_key')->unique();
            $table->string('provider', 40);
            $table->string('provider_account_id')->nullable()->index();
            $table->string('provider_message_id')->nullable()->index();
            $table->string('event_type', 60)->nullable()->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_connection_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sms_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80);
            $table->json('changes')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_connection_audits');
        Schema::dropIfExists('sms_webhook_events');
        Schema::dropIfExists('sms_message_logs');
        Schema::dropIfExists('sms_templates');
        Schema::dropIfExists('sms_connections');

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropColumn(['sms_auto_reminders_enabled', 'sms_architecture']);
        });
    }
};
