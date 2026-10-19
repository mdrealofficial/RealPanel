<?php

namespace Database\Seeders;

use App\Models\DnsTemplate;
use Illuminate\Database\Seeder;

class DnsTemplateSeeder extends Seeder
{
    public static function getTemplates(): array
    {
        return [
            [
                'name'       => 'Standard (Web & Mail)',
                'is_default' => true,
                'records'    => [
                    ['name' => '{domain}', 'type' => 'NS', 'content' => '{ns1}.', 'ttl' => 86400, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'NS', 'content' => '{ns2}.', 'ttl' => 86400, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'A', 'content' => '{ip}', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'www.{domain}', 'type' => 'CNAME', 'content' => '{domain}.', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'mail.{domain}', 'type' => 'A', 'content' => '{ip}', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'webmail.{domain}', 'type' => 'CNAME', 'content' => 'mail.{domain}.', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'cpanel.{domain}', 'type' => 'CNAME', 'content' => '{domain}.', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'ftp.{domain}', 'type' => 'A', 'content' => '{ip}', 'ttl' => 3600, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'MX', 'content' => 'mail.{domain}.', 'ttl' => 3600, 'priority' => 10],
                    ['name' => '{domain}', 'type' => 'TXT', 'content' => 'v=spf1 a mx ip4:{ip} ~all', 'ttl' => 3600, 'priority' => 0],
                    ['name' => '_dmarc.{domain}', 'type' => 'TXT', 'content' => 'v=DMARC1; p=quarantine; sp=none; aspf=r; adkim=r;', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'default._domainkey.{domain}', 'type' => 'TXT', 'content' => '{dkim}', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'autoconfig.{domain}', 'type' => 'CNAME', 'content' => 'mail.{domain}.', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'autodiscover.{domain}', 'type' => 'CNAME', 'content' => 'mail.{domain}.', 'ttl' => 3600, 'priority' => 0],
                ],
            ],
            [
                'name'       => 'Google Workspace (Gmail)',
                'is_default' => false,
                'records'    => [
                    ['name' => '{domain}', 'type' => 'NS', 'content' => '{ns1}.', 'ttl' => 86400, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'NS', 'content' => '{ns2}.', 'ttl' => 86400, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'A', 'content' => '{ip}', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'www.{domain}', 'type' => 'CNAME', 'content' => '{domain}.', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'ftp.{domain}', 'type' => 'A', 'content' => '{ip}', 'ttl' => 3600, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'MX', 'content' => 'aspmx.l.google.com.', 'ttl' => 3600, 'priority' => 1],
                    ['name' => '{domain}', 'type' => 'MX', 'content' => 'alt1.aspmx.l.google.com.', 'ttl' => 3600, 'priority' => 5],
                    ['name' => '{domain}', 'type' => 'MX', 'content' => 'alt2.aspmx.l.google.com.', 'ttl' => 3600, 'priority' => 5],
                    ['name' => '{domain}', 'type' => 'MX', 'content' => 'alt3.aspmx.l.google.com.', 'ttl' => 3600, 'priority' => 10],
                    ['name' => '{domain}', 'type' => 'MX', 'content' => 'alt4.aspmx.l.google.com.', 'ttl' => 3600, 'priority' => 10],
                    ['name' => '{domain}', 'type' => 'TXT', 'content' => 'v=spf1 include:_spf.google.com ~all', 'ttl' => 3600, 'priority' => 0],
                    ['name' => '_dmarc.{domain}', 'type' => 'TXT', 'content' => 'v=DMARC1; p=none;', 'ttl' => 3600, 'priority' => 0],
                ],
            ],
            [
                'name'       => 'Microsoft 365 (Office 365)',
                'is_default' => false,
                'records'    => [
                    ['name' => '{domain}', 'type' => 'NS', 'content' => '{ns1}.', 'ttl' => 86400, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'NS', 'content' => '{ns2}.', 'ttl' => 86400, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'A', 'content' => '{ip}', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'www.{domain}', 'type' => 'CNAME', 'content' => '{domain}.', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'ftp.{domain}', 'type' => 'A', 'content' => '{ip}', 'ttl' => 3600, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'MX', 'content' => '{domain}.mail.protection.outlook.com.', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'autodiscover.{domain}', 'type' => 'CNAME', 'content' => 'autodiscover.outlook.com.', 'ttl' => 3600, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'TXT', 'content' => 'v=spf1 include:spf.protection.outlook.com ~all', 'ttl' => 3600, 'priority' => 0],
                    ['name' => '_dmarc.{domain}', 'type' => 'TXT', 'content' => 'v=DMARC1; p=none;', 'ttl' => 3600, 'priority' => 0],
                ],
            ],
            [
                'name'       => 'Cloudflare & CDN Ready',
                'is_default' => false,
                'records'    => [
                    ['name' => '{domain}', 'type' => 'NS', 'content' => '{ns1}.', 'ttl' => 86400, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'NS', 'content' => '{ns2}.', 'ttl' => 86400, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'A', 'content' => '{ip}', 'ttl' => 300, 'priority' => 0],
                    ['name' => 'www.{domain}', 'type' => 'CNAME', 'content' => '{domain}.', 'ttl' => 300, 'priority' => 0],
                    ['name' => 'direct.{domain}', 'type' => 'A', 'content' => '{ip}', 'ttl' => 300, 'priority' => 0],
                    ['name' => 'ftp.{domain}', 'type' => 'CNAME', 'content' => 'direct.{domain}.', 'ttl' => 300, 'priority' => 0],
                    ['name' => 'mail.{domain}', 'type' => 'A', 'content' => '{ip}', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'webmail.{domain}', 'type' => 'CNAME', 'content' => 'mail.{domain}.', 'ttl' => 3600, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'MX', 'content' => 'mail.{domain}.', 'ttl' => 3600, 'priority' => 10],
                    ['name' => '{domain}', 'type' => 'TXT', 'content' => 'v=spf1 a mx ip4:{ip} ~all', 'ttl' => 3600, 'priority' => 0],
                    ['name' => '_dmarc.{domain}', 'type' => 'TXT', 'content' => 'v=DMARC1; p=quarantine;', 'ttl' => 3600, 'priority' => 0],
                ],
            ],
            [
                'name'       => 'Web Only (No Local Mail)',
                'is_default' => false,
                'records'    => [
                    ['name' => '{domain}', 'type' => 'NS', 'content' => '{ns1}.', 'ttl' => 86400, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'NS', 'content' => '{ns2}.', 'ttl' => 86400, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'A', 'content' => '{ip}', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'www.{domain}', 'type' => 'CNAME', 'content' => '{domain}.', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'api.{domain}', 'type' => 'A', 'content' => '{ip}', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'ftp.{domain}', 'type' => 'A', 'content' => '{ip}', 'ttl' => 3600, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'TXT', 'content' => 'v=spf1 -all', 'ttl' => 3600, 'priority' => 0],
                ],
            ],
            [
                'name'       => 'Zoho Mail',
                'is_default' => false,
                'records'    => [
                    ['name' => '{domain}', 'type' => 'NS', 'content' => '{ns1}.', 'ttl' => 86400, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'NS', 'content' => '{ns2}.', 'ttl' => 86400, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'A', 'content' => '{ip}', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'www.{domain}', 'type' => 'CNAME', 'content' => '{domain}.', 'ttl' => 3600, 'priority' => 0],
                    ['name' => 'ftp.{domain}', 'type' => 'A', 'content' => '{ip}', 'ttl' => 3600, 'priority' => 0],
                    ['name' => '{domain}', 'type' => 'MX', 'content' => 'mx.zoho.com.', 'ttl' => 3600, 'priority' => 10],
                    ['name' => '{domain}', 'type' => 'MX', 'content' => 'mx2.zoho.com.', 'ttl' => 3600, 'priority' => 20],
                    ['name' => '{domain}', 'type' => 'MX', 'content' => 'mx3.zoho.com.', 'ttl' => 3600, 'priority' => 50],
                    ['name' => '{domain}', 'type' => 'TXT', 'content' => 'v=spf1 include:zoho.com ~all', 'ttl' => 3600, 'priority' => 0],
                    ['name' => '_dmarc.{domain}', 'type' => 'TXT', 'content' => 'v=DMARC1; p=none;', 'ttl' => 3600, 'priority' => 0],
                ],
            ],
        ];
    }

    public function run(): void
    {
        $hasDefault = DnsTemplate::where('is_default', true)->exists();

        foreach (self::getTemplates() as $templateData) {
            $existing = DnsTemplate::where('name', $templateData['name'])->first();
            if ($existing) {
                continue;
            }

            $isDefault = $templateData['is_default'];
            if ($isDefault && $hasDefault) {
                $isDefault = false;
            }

            DnsTemplate::create([
                'name'       => $templateData['name'],
                'is_default' => $isDefault,
                'records'    => $templateData['records'],
            ]);

            if ($isDefault) {
                $hasDefault = true;
            }
        }
    }
}
