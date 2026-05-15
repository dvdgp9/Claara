<?php

declare(strict_types=1);

namespace Connectors;

use App\Env;

class ConnectorTokenCrypto
{
    private const CIPHER = 'aes-256-gcm';

    private string $key;

    public function __construct(?string $keyMaterial = null)
    {
        $keyMaterial = $keyMaterial ?? (string)(Env::get('CONNECTOR_TOKEN_ENCRYPTION_KEY') ?? '');
        $keyMaterial = trim($keyMaterial);

        if ($keyMaterial === '') {
            throw new \RuntimeException('Missing CONNECTOR_TOKEN_ENCRYPTION_KEY');
        }

        $decoded = base64_decode($keyMaterial, true);
        $this->key = $decoded !== false && strlen($decoded) >= 32
            ? substr($decoded, 0, 32)
            : hash('sha256', $keyMaterial, true);
    }

    public function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return null;
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false || $tag === '') {
            throw new \RuntimeException('Could not encrypt connector token');
        }

        return base64_encode(json_encode([
            'v' => 1,
            'cipher' => self::CIPHER,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($ciphertext),
        ], JSON_THROW_ON_ERROR));
    }

    public function decrypt(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        $json = base64_decode($payload, true);
        if ($json === false) {
            throw new \RuntimeException('Invalid connector token payload');
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['cipher'] ?? '') !== self::CIPHER) {
            throw new \RuntimeException('Unsupported connector token payload');
        }

        $iv = base64_decode((string)($data['iv'] ?? ''), true);
        $tag = base64_decode((string)($data['tag'] ?? ''), true);
        $ciphertext = base64_decode((string)($data['data'] ?? ''), true);

        if ($iv === false || $tag === false || $ciphertext === false) {
            throw new \RuntimeException('Corrupt connector token payload');
        }

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            throw new \RuntimeException('Could not decrypt connector token');
        }

        return $plaintext;
    }
}

