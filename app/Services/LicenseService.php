<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LicenseService
{
    private string $serverUrl;
    private string $licenseKey;

    public function __construct()
    {
        $this->serverUrl = rtrim(config('opterius.license_server_url'), '/');
        $this->licenseKey = config('opterius.license_key', '');
    }

    /**
     * Verify the license status.
     * Open-source community edition: always valid with unlimited quotas.
     */
    public function verify(): array
    {
        return [
            'valid'                => true,
            'reason'               => 'community',
            'message'              => 'Open-Source Community Edition',
            'max_domains'          => 0, // 0 = unlimited
            'max_accounts'         => 0, // 0 = unlimited
            'max_servers'          => 0, // 0 = unlimited
            'plan'                 => 'community',
            'subscription_status'  => 'active',
            'cancel_at_period_end' => false,
            'current_period_end'   => null,
        ];
    }

    /**
     * Register a trial license (no-op in open-source edition).
     */
    public function registerTrial(): ?array
    {
        return $this->verify();
    }

    /**
     * Check if the license is valid.
     */
    public function isValid(): bool
    {
        return true;
    }

    /**
     * Get the maximum number of domains allowed.
     */
    public function maxDomains(): int
    {
        return PHP_INT_MAX;
    }

    /**
     * Get the maximum number of accounts allowed.
     */
    public function maxAccounts(): int
    {
        return PHP_INT_MAX;
    }

    /**
     * Get the maximum number of servers allowed.
     */
    public function maxServers(): int
    {
        return PHP_INT_MAX;
    }

    /**
     * Subscription status of the panel owner.
     */
    public function subscriptionStatus(): ?string
    {
        return 'active';
    }

    /**
     * True if cancellation is scheduled.
     */
    public function cancelAtPeriodEnd(): bool
    {
        return false;
    }

    /**
     * End of current billing period.
     */
    public function currentPeriodEnd(): ?string
    {
        return null;
    }

    /**
     * Get the current license plan.
     */
    public function plan(): string
    {
        return 'community';
    }

    /**
     * Clear the cached license status.
     */
    public function clearCache(): void
    {
        Cache::forget('license_status');
    }
}

