<?php

declare(strict_types=1);

namespace Connectors;

/**
 * Provider-agnostic access-token lifecycle: returns a valid access token for
 * a connector account, refreshing (and re-encrypting) it when it is missing
 * or expires within the safety margin.
 */
class ConnectorTokenService
{
    private const REFRESH_MARGIN_SECONDS = 300;

    private ConnectorProviderInterface $provider;
    private ConnectorTokensRepo $tokensRepo;
    private ConnectorAccountsRepo $accountsRepo;

    public function __construct(
        ConnectorProviderInterface $provider,
        ?ConnectorTokensRepo $tokensRepo = null,
        ?ConnectorAccountsRepo $accountsRepo = null
    ) {
        $this->provider = $provider;
        $this->tokensRepo = $tokensRepo ?? new ConnectorTokensRepo();
        $this->accountsRepo = $accountsRepo ?? new ConnectorAccountsRepo();
    }

    /**
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
        } catch (ConnectorOAuthException $e) {
            if ($e->isInvalidGrant()) {
                // The user revoked access at the provider; surface it on the account.
                $this->accountsRepo->markError($accountId, 'Access was revoked; the account must be reconnected');
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
