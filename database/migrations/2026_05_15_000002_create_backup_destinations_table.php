<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('backup_destinations')) {
            Schema::create('backup_destinations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
                $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
                $table->string('name');
                $table->enum('type', ['s3', 'b2', 'gdrive', 'sftp'])->default('s3');
                $table->text('credentials_encrypted')->nullable();
                $table->string('bucket')->nullable();
                $table->string('remote_path')->nullable();
                $table->integer('retention_count')->default(7);
                $table->string('status')->default('active');
                $table->timestamp('last_tested_at')->nullable();
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_destinations');
    }
};
