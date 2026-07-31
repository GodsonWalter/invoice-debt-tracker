<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const WORKSPACE_MIGRATION = '2026_05_12_092229_create_workspace_table';

    public function up(): void
    {
        $this->repairUsersTable();

        if (Schema::hasTable('workspaces') && ! $this->migrationWasRecorded(self::WORKSPACE_MIGRATION)) {
            $this->repairWorkspacesTable();
            $this->recordMigration(self::WORKSPACE_MIGRATION);
        }
    }

    public function down(): void
    {
        /**
         * This migration repairs an existing installation and intentionally does not remove recovered schema.
         */
    }

    private function repairUsersTable(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('phone')->nullable();
                $table->string('avatar')->nullable();
                $table->string('address')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_login_at')->nullable();
                $table->enum('role', ['owner', 'admin', 'manager', 'staff', 'user'])->default('user');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table): void {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    private function repairWorkspacesTable(): void
    {
        $indexes = collect(Schema::getIndexes('workspaces'));

        if (! $indexes->contains(fn (array $index): bool => $index['name'] === 'workspaces_slug_unique')) {
            Schema::table('workspaces', function (Blueprint $table): void {
                $table->unique('slug', 'workspaces_slug_unique');
            });
        }

        if (! $indexes->contains(fn (array $index): bool => $index['name'] === 'workspaces_subdomain_unique')) {
            Schema::table('workspaces', function (Blueprint $table): void {
                $table->unique('subdomain', 'workspaces_subdomain_unique');
            });
        }

        $foreignKeyExists = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'workspaces')
            ->where('COLUMN_NAME', 'owner_id')
            ->where('REFERENCED_TABLE_NAME', 'users')
            ->exists();

        if (! $foreignKeyExists) {
            Schema::table('workspaces', function (Blueprint $table): void {
                $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    private function migrationWasRecorded(string $migration): bool
    {
        return DB::table('migrations')->where('migration', $migration)->exists();
    }

    private function recordMigration(string $migration): void
    {
        $batch = ((int) DB::table('migrations')->max('batch')) + 1;

        DB::table('migrations')->insert([
            'migration' => $migration,
            'batch' => $batch,
        ]);
    }
};
