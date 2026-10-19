<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebmailSsoService
{
    /**
     * Request a one-time SSO login URL from the webmail app for the given email.
     *
     * Returns the redirect URL on success, or null if SSO is not configured /
     * the webmail returned an error. Callers should fall back to the plain
     * webmail URL when null is returned.
     */
    public static function loginUrl(string $email): ?string
    {
        $secret     = config('opterius.webmail_sso_secret');
        $webmailUrl = rtrim(config('opterius.webmail_url'), '/');

        if (!$secret || !$webmailUrl || str_contains($webmailUrl, 'SERVER_IP')) {
            return null;
        }

        $timestamp = time();
        $signature = hash_hmac('sha256', "{$email}:{$timestamp}", $secret);

        try {
            $response = Http::timeout(5)
                ->withoutVerifying()
                ->post("{$webmailUrl}/sso/issue", [
                    'email'     => $email,
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                ]);

            if ($response->successful()) {
                $issuedUrl = (string) $response->json('url');
                // If webmail returned an internal URL (e.g. http://127.0.0.1:8090/sso/login?... or localhost:8090),
                // rewrite the host to the client's current request host so their browser can reach it.
                $clientHost = request()->getHost();
                $scheme = request()->getScheme(); // 'http' or 'https'
                if ($clientHost && !in_array($clientHost, ['127.0.0.1', 'localhost'])) {
                    // Match the scheme of the current panel request (e.g. https:// if panel is on https)
                    $issuedUrl = preg_replace('#^https?://(127\.0\.0\.1|localhost)(:8090.*)$#', $scheme . '://' . $clientHost . '$2', $issuedUrl);
                }
                return $issuedUrl;
            }

            Log::warning('Webmail SSO issue failed', [
                'email'  => $email,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Webmail SSO request failed', ['email' => $email, 'error' => $e->getMessage()]);
        }

        return null;
    }
}
