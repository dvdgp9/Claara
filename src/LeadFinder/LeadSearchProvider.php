<?php

declare(strict_types=1);

namespace LeadFinder;

interface LeadSearchProvider
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $maxResults): array;

    public function providerKey(): string;
}
