<?php
namespace Instances;

use App\Env;

final class InstanceResources
{
    public function __construct(private readonly array $resources)
    {
    }

    public function databaseEnvPrefix(): string { return $this->resources['database']['env_prefix']; }
    public function storagePathEnv(): string { return $this->resources['storage']['path_env']; }
    public function ragEnvPrefix(): string { return $this->resources['rag']['env_prefix']; }
    public function ragCollectionPrefix(): string { return $this->resources['rag']['collection_prefix']; }
    public function sessionNamespace(): string { return $this->resources['session']['namespace']; }
    public function secretsScope(): string { return $this->resources['secrets']['scope']; }
    public function backupsScope(): string { return $this->resources['backups']['scope']; }

    public function databaseConfig(): array
    {
        $prefix = $this->databaseEnvPrefix();
        return [
            'host' => $this->env($prefix . '_HOST', 'localhost'),
            'port' => $this->positivePort($this->env($prefix . '_PORT', '3306'), 'database'),
            'name' => $this->requiredEnv($prefix . '_NAME'),
            'user' => $this->requiredEnv($prefix . '_USER'),
            'password' => $this->env($prefix . '_PASS', ''),
        ];
    }

    public function storageRoot(string $projectRoot): string
    {
        $configured = trim($this->env($this->storagePathEnv(), ''));
        $path = $configured !== '' ? $configured : rtrim($projectRoot, '/') . '/storage';
        if ($path === '/' || $path[0] !== '/' || str_contains($path, "\0")) {
            throw new InstanceConfigurationException('Instance storage root must be a safe absolute path');
        }
        return rtrim($path, '/');
    }

    public function ragConfig(): array
    {
        $prefix = $this->ragEnvPrefix();
        return [
            'host' => $this->env($prefix . '_HOST', 'localhost'),
            'port' => $this->positivePort($this->env($prefix . '_PORT', '6333'), 'RAG'),
            'api_key' => $this->env($prefix . '_API_KEY', ''),
            'collection_prefix' => $this->ragCollectionPrefix(),
        ];
    }

    private function requiredEnv(string $key): string
    {
        $value = trim($this->env($key, ''));
        if ($value === '') {
            throw new InstanceConfigurationException("Missing required instance environment value {$key}");
        }
        return $value;
    }

    private function env(string $key, string $default): string
    {
        return (string)(Env::get($key, $default) ?? $default);
    }

    private function positivePort(string $value, string $label): int
    {
        $port = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($port === false) {
            throw new InstanceConfigurationException("Invalid {$label} port");
        }
        return (int)$port;
    }
}
