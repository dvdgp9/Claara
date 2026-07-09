<?php

declare(strict_types=1);

namespace Connectors;

use App\Env;

class MicrosoftOneDriveProvider implements ConnectorProviderInterface
{
    private const AUTH_ENDPOINT = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
    private const TOKEN_ENDPOINT = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    private const GRAPH_ME = 'https://graph.microsoft.com/v1.0/me';

    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $this->clientId = trim((string)(Env::get('MS_OAUTH_CLIENT_ID') ?? ''));
        $this->clientSecret = trim((string)(Env::get('MS_OAUTH_CLIENT_SECRET') ?? ''));

        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new \RuntimeException('Missing MS_OAUTH_CLIENT_ID / MS_OAUTH_CLIENT_SECRET');
        }
    }

    public function providerKey(): string
    {
        return 'onedrive';
    }

    public function displayName(): string
    {
        return 'OneDrive';
    }

    public function scopes(): array
    {
        return ['openid', 'profile', 'email', 'offline_access', 'Files.Read'];
    }

    public function redirectUri(): string
    {
        $base = rtrim((string)(Env::get('APP_URL') ?? ''), '/');
        if ($base === '') {
            throw new \RuntimeException('Missing APP_URL for OAuth redirect');
        }
        return $base . '/api/connectors/onedrive/callback.php';
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTH_ENDPOINT . '?' . http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'response_mode' => 'query',
            'scope' => implode(' ', $this->scopes()),
            'state' => $state,
            'prompt' => 'select_account',
        ]);
    }

    public function exchangeCode(string $code): array
    {
        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', $this->scopes()),
        ]);
    }

    public function refreshToken(string $refreshToken): array
    {
        return $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => implode(' ', $this->scopes()),
        ]);
    }

    public function fetchAccountProfile(string $accessToken): array
    {
        $ch = curl_init(self::GRAPH_ME);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $profile = $response !== false ? json_decode((string)$response, true) : null;
        if ($status !== 200 || !is_array($profile) || empty($profile['id'])) {
            throw new \RuntimeException('Could not fetch Microsoft account profile (HTTP ' . $status . ')');
        }

        $email = $profile['mail'] ?? $profile['userPrincipalName'] ?? null;
        return [
            'id' => (string)$profile['id'],
            'email' => $email,
            'name' => $profile['displayName'] ?? null,
            'display_name' => $email ?? ($profile['displayName'] ?? null),
        ];
    }

    private function tokenRequest(array $fields): array
    {
        $ch = curl_init(self::TOKEN_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Microsoft token request failed: ' . $curlError);
        }

        $data = json_decode((string)$response, true);
        if ($status !== 200 || !is_array($data) || empty($data['access_token'])) {
            $errorCode = is_array($data) ? (string)($data['error'] ?? 'unknown_error') : 'invalid_response';
            $errorDescription = is_array($data) ? (string)($data['error_description'] ?? '') : '';
            throw new ConnectorOAuthException($errorCode, trim($errorCode . ' ' . $errorDescription), $status);
        }

        return $data;
    }
}
