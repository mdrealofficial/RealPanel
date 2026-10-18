<?php

/**
 * RealPanel — WHMCS Server Module
 *
 * This module integrates WHMCS with the RealPanel provisioning API.
 * Place this directory at: /path/to/whmcs/modules/servers/realpanel/
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

require_once __DIR__ . '/lib/RealPanelAPI.php';

use WHMCS\Module\Server\RealPanel\RealPanelAPI;

/**
 * Module metadata.
 */
function realpanel_MetaData(): array
{
    return [
        'DisplayName'       => 'RealPanel',
        'APIVersion'        => '1.1',
        'RequiresServer'    => true,
        'DefaultNonSSLPort' => '80',
        'DefaultSSLPort'    => '443',
    ];
}

/**
 * Product-level configuration options shown in WHMCS product setup.
 */
function realpanel_ConfigOptions(): array
{
    return [
        'Package Name' => [
            'Type'        => 'text',
            'Size'        => 50,
            'Description' => 'The exact package name as configured in RealPanel (e.g. "Starter", "Standard").',
        ],
    ];
}

/**
 * Custom buttons for Client Area (Single Sign-On).
 */
function realpanel_ClientAreaCustomButtonArray(): array
{
    return [
        'Log in to RealPanel' => 'LoginToPanel',
    ];
}

/**
 * Test connection to RealPanel.
 */
function realpanel_TestConnection(array $params): array
{
    try {
        $api = RealPanelAPI::fromParams($params);
        $response = $api->post('test');

        if (($response['result'] ?? '') === 'success') {
            return ['success' => true, 'error' => ''];
        }

        return ['success' => false, 'error' => $response['message'] ?? 'Unknown error'];
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Provision a new hosting account.
 */
function realpanel_CreateAccount(array $params): string
{
    try {
        $api = RealPanelAPI::fromParams($params);

        $response = $api->post('accounts/create', [
            'domain'           => $params['domain'],
            'username'         => $params['username'],
            'password'         => $params['password'],
            'package'          => $params['configoption1'], // Package Name from ConfigOptions
            'client_email'     => $params['clientsdetails']['email'],
            'client_name'      => trim($params['clientsdetails']['firstname'] . ' ' . $params['clientsdetails']['lastname']),
            'whmcs_service_id' => $params['serviceid'] ?? null,
            'whmcs_client_id'  => $params['userid'] ?? null,
        ]);

        if (($response['result'] ?? '') === 'success' || ($response['result'] ?? '') === 'warning') {
            return 'success';
        }

        return $response['message'] ?? 'Unknown error from RealPanel';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Suspend an account.
 */
function realpanel_SuspendAccount(array $params): string
{
    try {
        $api = RealPanelAPI::fromParams($params);

        $response = $api->post('accounts/suspend', [
            'username' => $params['username'],
            'reason'   => $params['suspendreason'] ?? 'Suspended by WHMCS',
        ]);

        if (($response['result'] ?? '') === 'success') {
            return 'success';
        }

        return $response['message'] ?? 'Unknown error';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Unsuspend an account.
 */
function realpanel_UnsuspendAccount(array $params): string
{
    try {
        $api = RealPanelAPI::fromParams($params);

        $response = $api->post('accounts/unsuspend', [
            'username' => $params['username'],
        ]);

        if (($response['result'] ?? '') === 'success') {
            return 'success';
        }

        return $response['message'] ?? 'Unknown error';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Terminate (delete) an account.
 */
function realpanel_TerminateAccount(array $params): string
{
    try {
        $api = RealPanelAPI::fromParams($params);

        $response = $api->post('accounts/terminate', [
            'username' => $params['username'],
        ]);

        if (($response['result'] ?? '') === 'success') {
            return 'success';
        }

        return $response['message'] ?? 'Unknown error';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Change account password.
 */
function realpanel_ChangePassword(array $params): string
{
    try {
        $api = RealPanelAPI::fromParams($params);

        $response = $api->post('accounts/password', [
            'username' => $params['username'],
            'password' => $params['password'],
        ]);

        if (($response['result'] ?? '') === 'success') {
            return 'success';
        }

        return $response['message'] ?? 'Unknown error';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Change account package (upgrade/downgrade).
 */
function realpanel_ChangePackage(array $params): string
{
    try {
        $api = RealPanelAPI::fromParams($params);

        $response = $api->post('accounts/package', [
            'username' => $params['username'],
            'package'  => $params['configoption1'],
        ]);

        if (($response['result'] ?? '') === 'success') {
            return 'success';
        }

        return $response['message'] ?? 'Unknown error';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * One-Click Login to RealPanel (Single Sign-On Action).
 */
function realpanel_LoginToPanel(array $params)
{
    try {
        $api = RealPanelAPI::fromParams($params);

        $response = $api->post('accounts/sso', [
            'username' => $params['username'],
            'domain'   => $params['domain'],
        ]);

        if (($response['result'] ?? '') === 'success' && !empty($response['redirect_url'])) {
            header('Location: ' . $response['redirect_url']);
            exit;
        }

        return $response['message'] ?? 'Unable to generate one-click login session.';
    } catch (\Exception $e) {
        return 'SSO Error: ' . $e->getMessage();
    }
}

/**
 * Client Area output: Direct button with One-Click SSO link.
 */
function realpanel_ClientArea(array $params): string
{
    $protocol = !empty($params['serversecure']) ? 'https' : 'http';
    $host = $params['serverhostname'] ?: $params['serverip'];
    $port = $params['serverport'] ?: ($protocol === 'https' ? '443' : '80');
    $portSuffix = ($port === '80' && $protocol === 'http') || ($port === '443' && $protocol === 'https') ? '' : ":{$port}";
    $panelUrl = "{$protocol}://{$host}{$portSuffix}";

    // Also offer a direct fallback button if form action is preferred
    $serviceId = (int) ($params['serviceid'] ?? 0);
    $ssoActionUrl = "clientarea.php?action=productdetails&id={$serviceId}&modop=custom&a=LoginToPanel";

    return '
    <div style="margin: 10px 0;">
        <a href="' . htmlspecialchars($ssoActionUrl) . '" class="btn btn-primary" target="_blank" style="padding: 8px 16px; font-weight: 600; border-radius: 4px; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fas fa-sign-in-alt"></i> One-Click Login to RealPanel
        </a>
        <a href="' . htmlspecialchars($panelUrl) . '" target="_blank" class="btn btn-default btn-secondary" style="padding: 8px 16px; margin-left: 8px; border-radius: 4px;">
            Open Panel URL
        </a>
    </div>';
}

/**
 * Admin Area Single Sign-On (Direct server link).
 */
function realpanel_AdminSingleSignOn(array $params): array
{
    $protocol = !empty($params['serversecure']) ? 'https' : 'http';
    $host = $params['serverhostname'] ?: $params['serverip'];
    $port = $params['serverport'] ?: ($protocol === 'https' ? '443' : '80');
    $portSuffix = ($port === '80' && $protocol === 'http') || ($port === '443' && $protocol === 'https') ? '' : ":{$port}";

    return [
        'success'    => true,
        'redirectTo' => "{$protocol}://{$host}{$portSuffix}",
    ];
}

/**
 * Usage update — called periodically by WHMCS cron to sync disk/bandwidth.
 */
function realpanel_UsageUpdate(array $params): string
{
    $api = RealPanelAPI::fromParams($params);

    foreach ($params['accounts'] as $account) {
        $response = $api->post('accounts/usage', [
            'username' => $account['username'],
        ]);

        if (($response['result'] ?? '') === 'success' && isset($response['data'])) {
            $data = $response['data'];

            $diskUsed = round(($data['disk_used_mb'] ?? 0) / 1024, 2); // MB to GB
            $diskLimit = $data['disk_limit'] ?? 0;
            $diskLimit = $diskLimit > 0 ? round($diskLimit / 1024, 2) : 0;
            $bwUsed = round(($data['bw_used_mb'] ?? 0) / 1024, 2);

            try {
                \WHMCS\Database\Capsule::table('tblhosting')
                    ->where('id', $account['serviceid'])
                    ->update([
                        'diskusage'  => $diskUsed,
                        'disklimit'  => $diskLimit,
                        'bwusage'    => $bwUsed,
                        'lastupdate' => date('Y-m-d H:i:s'),
                    ]);
            } catch (\Exception $e) {
                logActivity("RealPanel usage update failed for {$account['username']}: " . $e->getMessage());
            }
        }
    }

    return 'success';
}
