<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ip_blocks')) {
            Schema::create('ip_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('domain_id')->nullable()->constrained('domains')->cascadeOnDelete();
                $table->foreignId('account_id')->nullable()->constrained('accounts')->cascadeOnDelete();
                $table->string('ip_or_subnet', 50);
                $table->string('comment')->nullable();
                $table->timestamps();

                $table->index(['domain_id', 'ip_or_subnet']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_blocks');
    }
};
