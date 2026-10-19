<?php

return [

    // Page header
    'page_title'    => 'System Settings',
    'page_subtitle' => 'Server-wide defaults and policies. Apply to every account on this panel installation.',

    // Sidebar category labels
    'cat_display'       => 'Display & Branding',
    'cat_domains'       => 'Domains',
    'cat_mail'          => 'Mail',
    'cat_security'      => 'Security',
    'cat_ssl'           => 'SSL',
    'cat_php'           => 'PHP',
    'cat_notifications' => 'Notifications',
    'cat_integrations'  => 'Integrations',
    'cat_system'        => 'System',

    // Common
    'save'             => 'Save changes',
    'saved'            => 'Settings saved.',
    'coming_soon'      => 'Coming soon',
    'coming_soon_text' => 'This category is part of the System Settings roadmap. The first batch ships with the Domains category and grows from there.',

    // ── Domains & DNS category ────────────────────────────────────
    'cat_domains'      => 'Domains & DNS',
    'domains_title'    => 'Domains & DNS Defaults',
    'domains_subtitle' => 'Defaults applied when new domains, DNS zones, and packages are created.',

    'default_php_version_label' => 'Default PHP version for new packages',
    'default_php_version_hint'  => 'When you create a new hosting package, this version is pre-selected as the default. Existing packages and domains are not affected.',

    'nameservers_title'         => 'Default Nameservers',
    'nameservers_hint'          => 'These nameservers are assigned to newly created domains and replace {ns1} and {ns2} variables in DNS templates.',
    'default_ns1_label'         => 'Primary Nameserver (NS1)',
    'default_ns1_subtext'       => 'e.g. ns1.yourdomain.com (or leave blank to use domain-relative ns1.{domain})',
    'default_ns2_label'         => 'Secondary Nameserver (NS2)',
    'default_ns2_subtext'       => 'e.g. ns2.yourdomain.com (or leave blank to use domain-relative ns2.{domain})',
];
