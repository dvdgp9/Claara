<?php

declare(strict_types=1);

namespace Connectors;

use App\DB;
use PDO;

class ConnectorTokensRepo
{
    private PDO $pdo;
    private ConnectorTokenCrypto $crypto;

    public function __construct(?PDO $pdo = null, ?ConnectorTokenCrypto $crypto = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
        $this->crypto = $crypto ?? new ConnectorTokenCrypto();
    }

    public function saveForAccount(int $accountId, array $tokenData): bool
    {
        $expiresAt = $this->expiresAt($tokenData);
        $stmt = $this->pdo->prepare('
            INSERT INTO connector_tokens
                (account_id, encrypted_access_token, encrypted_refresh_token, token_type, expires_at, scopes, metadata, created_at, updated_at)
            VALUES
                (:account_id, :access_token, :refresh_token, :token_type, :expires_at, :scopes, :metadata, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                encrypted_access_token = VALUES(encrypted_access_token),
                encrypted_refresh_token = COALESCE(VALUES(encrypted_refresh_token), encrypted_refresh_token),
                token_type = VALUES(token_type),
                expires_at = VALUES(expires_at),
                scopes = VALUES(scopes),
                metadata = VALUES(metadata),
                updated_at = NOW()
        ');

        return $stmt->execute([
            'account_id' => $accountId,
            'access_token' => $this->crypto->encrypt((string)($tokenData['access_token'] ?? '')),
            'refresh_token' => $this->crypto->encrypt($this->nullableToken($tokenData['refresh_token'] ?? null)),
            'token_type' => $this->nullableString($tokenData['token_type'] ?? 'Bearer', 40),
            'expires_at' => $expiresAt,
            'scopes' => $this->scopesToString($tokenData['scope'] ?? $tokenData['scopes'] ?? ''),
            'metadata' => json_encode($tokenData, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function findDecryptedForAccount(int $accountId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM connector_tokens WHERE account_id = ?');
        $stmt->execute([$accountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['access_token'] = $this->crypto->decrypt($row['encrypted_access_token'] ?? null);
        $row['refresh_token'] = $this->crypto->decrypt($row['encrypted_refresh_token'] ?? null);
        unset($row['encrypted_access_token'], $row['encrypted_refresh_token']);
        $row['metadata'] = $row['metadata'] ? json_decode($row['metadata'], true) : null;
        return $row;
    }

    public function deleteForAccount(int $accountId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM connector_tokens WHERE account_id = ?');
        $stmt->execute([$accountId]);
        return $stmt->rowCount() > 0;
    }

    private function expiresAt(array $tokenData): ?string
    {
        if (!empty($tokenData['expires_at'])) {
            return (string)$tokenData['expires_at'];
        }
        if (isset($tokenData['expires_in']) && is_numeric($tokenData['expires_in'])) {
            return date('Y-m-d H:i:s', time() + max(0, (int)$tokenData['expires_in']));
        }
        return null;
    }

    private function scopesToString(mixed $scopes): ?string
    {
        if (is_array($scopes)) {
            $scopes = implode(' ', array_map('strval', $scopes));
        }
        $scopes = trim((string)$scopes);
        return $scopes === '' ? null : $scopes;
    }

    private function nullableToken(mixed $value): ?string
    {
        $value = trim((string)($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        $value = trim((string)($value ?? ''));
        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }
}

