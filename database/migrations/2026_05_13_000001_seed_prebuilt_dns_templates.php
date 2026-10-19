<?php

use Database\Seeders\DnsTemplateSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!class_exists(\Database\Seeders\DnsTemplateSeeder::class)) {
            $seederPath = database_path('seeders/DnsTemplateSeeder.php');
            if (file_exists($seederPath)) {
                require_once $seederPath;
            }
        }

        if (class_exists(\Database\Seeders\DnsTemplateSeeder::class)) {
            (new \Database\Seeders\DnsTemplateSeeder())->run();
        }
    }

    public function down(): void
    {
        // Don't delete templates on rollback as users might have customized them
    }
};
