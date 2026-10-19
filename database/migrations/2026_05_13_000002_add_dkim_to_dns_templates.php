<?php

use App\Models\DnsTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $templates = DnsTemplate::all();
        foreach ($templates as $template) {
            $records = $template->records ?? [];
            $hasDkim = false;
            foreach ($records as $r) {
                if (isset($r['name']) && str_contains($r['name'], '_domainkey')) {
                    $hasDkim = true;
                    break;
                }
            }
            if (!$hasDkim && $template->name === 'Standard (Web & Mail)') {
                $records[] = [
                    'name'     => 'default._domainkey.{domain}',
                    'type'     => 'TXT',
                    'content'  => '{dkim}',
                    'ttl'      => 3600,
                    'priority' => 0,
                ];
                $records[] = [
                    'name'     => 'autoconfig.{domain}',
                    'type'     => 'CNAME',
                    'content'  => 'mail.{domain}.',
                    'ttl'      => 3600,
                    'priority' => 0,
                ];
                $records[] = [
                    'name'     => 'autodiscover.{domain}',
                    'type'     => 'CNAME',
                    'content'  => 'mail.{domain}.',
                    'ttl'      => 3600,
                    'priority' => 0,
                ];
                $template->records = $records;
                $template->save();
            }
        }
    }

    public function down(): void
    {
    }
};
