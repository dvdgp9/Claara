<?php

declare(strict_types=1);

namespace Connectors;

use App\DB;
use PDO;

class ConnectorProvidersRepo
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    public function find(string $providerKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM connector_providers WHERE provider_key = ?');
        $stmt->execute([$providerKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listEnabled(): array
    {
        $stmt = $this->pdo->query('
            SELECT *
            FROM connector_providers
            WHERE is_enabled = 1
            ORDER BY sort_order, display_name
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM connector_providers ORDER BY sort_order, display_name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

