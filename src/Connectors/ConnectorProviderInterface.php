<?php

declare(strict_types=1);

namespace Connectors;

interface ConnectorProviderInterface
{
    public function providerKey(): string;

    public function displayName(): string;

    /**
     * @return array<int, string>
     */
    public function scopes(): array;

    public function authorizationUrl(string $state): string;

    /**
     * @return array<string, mixed>
     */
    public function exchangeCode(string $code): array;

    /**
     * @return array<string, mixed>
     */
    public function refreshToken(string $refreshToken): array;

    /**
     * @return array<string, mixed>
     */
    public function fetchAccountProfile(string $accessToken): array;
}

