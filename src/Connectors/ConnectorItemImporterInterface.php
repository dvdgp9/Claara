<?php

declare(strict_types=1);

namespace Connectors;

interface ConnectorItemImporterInterface
{
    /**
     * @param array<string, mixed> $account
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public function import(array $account, array $item, string $contextTarget = 'lex'): array;
}

