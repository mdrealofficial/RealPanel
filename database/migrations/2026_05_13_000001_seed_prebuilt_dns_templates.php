<?php

use Database\Seeders\DnsTemplateSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new DnsTemplateSeeder())->run();
    }

    public function down(): void
    {
        // Don't delete templates on rollback as users might have customized them
    }
};
