<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DnsTemplateSeeder::class);

        $defaultTemplate = \App\Models\DnsTemplate::where('is_default', true)->first();

        $admin = User::create([
            'name' => 'Gabriel Chimilevschi',
            'email' => 'gabriel1978@gmail.com',
            'password' => bcrypt('admin'),
            'role' => 'admin',
        ]);

        Package::create([
            'name' => 'Default',
            'description' => 'Default hosting package',
            'dns_template_id' => $defaultTemplate?->id,
            'php_versions' => config('realpanel.php_versions'),
            'default_php_version' => config('realpanel.default_php_version'),
            'disk_quota' => 0,
            'bandwidth' => 0,
            'max_subdomains' => 0,
            'max_databases' => 0,
            'max_email_accounts' => 0,
            'ssl_enabled' => true,
            'cron_jobs_enabled' => true,
            'is_default' => true,
        ]);
    }
}
