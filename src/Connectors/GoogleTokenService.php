<?php

declare(strict_types=1);

namespace Connectors;

class GoogleTokenService
{
    private const REFRESH_MARGIN_SECONDS = 300;

    private ConnectorTokensRepo $tokensRepo;
    private ConnectorAccountsRepo $accountsRepo;
    private GoogleDriveProvider $provider;

    public function __construct(
        ?ConnectorTokensRepo $tokensRepo = null,
        ?ConnectorAccountsRepo $accountsRepo = null,
        ?GoogleDriveProvider $provider = null
    ) {
        $this->tokensRepo = $tokensRepo ?? new ConnectorTokensRepo();
        $this->accountsRepo = $accountsRepo ?? new ConnectorAccountsRepo();
        $this->provider = $provider ?? new GoogleDriveProvider();
    }

    /**
     * Returns a valid access token for the account, refreshing it first when
     * it is missing or expires within the safety margin.
     *
     * @return array{access_token: string, expires_at: ?string}
     */
    public function freshAccessToken(int $accountId): array
    {
        $tokens = $this->tokensRepo->findDecryptedForAccount($accountId);
        if (!$tokens) {
            throw new \RuntimeException('No stored tokens for connector account ' . $accountId);
        }

        $accessToken = (string)($tokens['access_token'] ?? '');
        $expiresAt = $tokens['expires_at'] ?? null;
        $stillValid = $accessToken !== ''
            && $expiresAt !== null
            && strtotime((string)$expiresAt) > time() + self::REFRESH_MARGIN_SECONDS;

        if ($stillValid) {
            return ['access_token' => $accessToken, 'expires_at' => $expiresAt];
        }

        $refreshToken = (string)($tokens['refresh_token'] ?? '');
        if ($refreshToken === '') {
            $this->accountsRepo->markError($accountId, 'Missing refresh token; the account must be reconnected');
            throw new \RuntimeException('Connector account has no refresh token; reconnect required');
        }

        try {
            $tokenData = $this->provider->refreshToken($refreshToken);
        } catch (GoogleOAuthException $e) {
            if ($e->isInvalidGrant()) {
                // The user revoked access at Google; surface it on the account.
                $this->accountsRepo->markError($accountId, 'Google access was revoked; the account must be reconnected');
            }
            throw $e;
        }

        $this->tokensRepo->saveForAccount($accountId, $tokenData);
        $saved = $this->tokensRepo->findDecryptedForAccount($accountId);

        return [
            'access_token' => (string)$tokenData['access_token'],
            'expires_at' => $saved['expires_at'] ?? null,
        ];
    }
}
