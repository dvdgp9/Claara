<?php

declare(strict_types=1);

namespace Connectors;

use App\Env;

class GoogleDriveProvider implements ConnectorProviderInterface
{
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const USERINFO_ENDPOINT = 'https://openidconnect.googleapis.com/v1/userinfo';
    private const REVOKE_ENDPOINT = 'https://oauth2.googleapis.com/revoke';

    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $this->clientId = trim((string)(Env::get('GOOGLE_OAUTH_CLIENT_ID') ?? ''));
        $this->clientSecret = trim((string)(Env::get('GOOGLE_OAUTH_CLIENT_SECRET') ?? ''));

        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new \RuntimeException('Missing GOOGLE_OAUTH_CLIENT_ID / GOOGLE_OAUTH_CLIENT_SECRET');
        }
    }

    public function providerKey(): string
    {
        return 'google_drive';
    }

    public function displayName(): string
    {
        return 'Google Drive';
    }

    public function scopes(): array
    {
        return [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/drive.file',
        ];
    }

    public function redirectUri(): string
    {
        $base = rtrim((string)(Env::get('APP_URL') ?? ''), '/');
        if ($base === '') {
            throw new \RuntimeException('Missing APP_URL for OAuth redirect');
        }
        return $base . '/api/connectors/google/callback.php';
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTH_ENDPOINT . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes()),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
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
        ]);
    }

    public function refreshToken(string $refreshToken): array
    {
        return $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);
    }

    public function fetchAccountProfile(string $accessToken): array
    {
        $response = $this->httpGet(self::USERINFO_ENDPOINT, $accessToken);
        $profile = json_decode($response, true);
        if (!is_array($profile) || empty($profile['sub'])) {
            throw new \RuntimeException('Could not fetch Google account profile');
        }

        return [
            'id' => (string)$profile['sub'],
            'email' => $profile['email'] ?? null,
            'name' => $profile['name'] ?? null,
            'display_name' => $profile['email'] ?? $profile['name'] ?? null,
        ];
    }

    public function revokeToken(string $token): bool
    {
        $ch = curl_init(self::REVOKE_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['token' => $token]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return $status === 200;
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
            throw new \RuntimeException('Google token request failed: ' . $curlError);
        }

        $data = json_decode((string)$response, true);
        if ($status !== 200 || !is_array($data) || empty($data['access_token'])) {
            $errorCode = is_array($data) ? (string)($data['error'] ?? 'unknown_error') : 'invalid_response';
            $errorDescription = is_array($data) ? (string)($data['error_description'] ?? '') : '';
            throw new GoogleOAuthException($errorCode, trim($errorCode . ' ' . $errorDescription), $status);
        }

        return $data;
    }

    private function httpGet(string $url, string $accessToken): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response === false || $status !== 200) {
            throw new \RuntimeException('Google API GET failed with HTTP ' . $status);
        }
        return (string)$response;
    }
}
