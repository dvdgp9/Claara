<?php
namespace Instances;

final class InstanceManifest
{
    private function __construct(private readonly array $data)
    {
    }

    public static function fromFile(string $path): self
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new InstanceConfigurationException('Instance manifest is missing or unreadable');
        }
        $size = filesize($path);
        if ($size === false || $size < 2 || $size > 1_048_576) {
            throw new InstanceConfigurationException('Instance manifest has an invalid size');
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new InstanceConfigurationException('Instance manifest is not valid JSON');
        }
        return self::fromArray($decoded);
    }

    public static function fromArray(array $data): self
    {
        $data = self::validate($data);
        return new self($data);
    }

    public function id(): string { return $this->data['id']; }
    public function slug(): string { return $this->data['slug']; }
    public function status(): string { return $this->data['status']; }
    public function canonicalDomain(): string { return $this->data['canonical_domain']; }
    public function domains(): array { return $this->data['domains']; }
    public function branding(): array { return $this->data['branding']; }
    public function locales(): array { return $this->data['locales']; }
    public function modules(): array { return $this->data['modules']; }
    public function limits(): array { return $this->data['limits']; }
    public function release(): array { return $this->data['release']; }
    public function resources(): array { return $this->data['resources']; }

    private static function validate(array $data): array
    {
        if (($data['schema_version'] ?? null) !== 1) {
            throw new InstanceConfigurationException('Unsupported instance manifest schema');
        }
        foreach (['id', 'slug'] as $field) {
            if (!is_string($data[$field] ?? null) || preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', $data[$field]) !== 1) {
                throw new InstanceConfigurationException("Invalid instance {$field}");
            }
        }
        if (!in_array($data['status'] ?? null, ['active', 'suspended'], true)) {
            throw new InstanceConfigurationException('Invalid instance status');
        }

        $domains = self::stringList($data['domains'] ?? null, 'domains');
        $normalizedDomains = array_map([self::class, 'normalizeDomain'], $domains);
        if (count(array_unique($normalizedDomains)) !== count($normalizedDomains)) {
            throw new InstanceConfigurationException('Instance domains must be unique');
        }
        $canonicalDomain = self::normalizeDomain((string)($data['canonical_domain'] ?? ''));
        if (!in_array($canonicalDomain, $normalizedDomains, true)) {
            throw new InstanceConfigurationException('Canonical domain must be in instance domains');
        }
        $data['domains'] = $normalizedDomains;
        $data['canonical_domain'] = $canonicalDomain;

        $branding = $data['branding'] ?? null;
        if (!is_array($branding)) {
            throw new InstanceConfigurationException('Missing instance branding');
        }
        foreach (['product_name', 'organization_name'] as $field) {
            $value = $branding[$field] ?? null;
            if (!is_string($value) || trim($value) === '' || mb_strlen($value) > 120) {
                throw new InstanceConfigurationException("Invalid branding {$field}");
            }
        }
        foreach (['logo_path', 'login_logo_path'] as $field) {
            $value = $branding[$field] ?? null;
            if (!is_string($value) || preg_match('#^/[A-Za-z0-9_./-]+$#', $value) !== 1 || str_contains($value, '..')) {
                throw new InstanceConfigurationException("Invalid branding {$field}");
            }
        }
        if (preg_match('/^#[A-Fa-f0-9]{6}$/', (string)($branding['accent_color'] ?? '')) !== 1) {
            throw new InstanceConfigurationException('Invalid branding accent color');
        }

        $locales = $data['locales'] ?? null;
        if (!is_array($locales)) {
            throw new InstanceConfigurationException('Missing locale policy');
        }
        $allowedLocales = self::stringList($locales['allowed'] ?? null, 'allowed locales');
        foreach ($allowedLocales as $locale) {
            if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) !== 1) {
                throw new InstanceConfigurationException('Invalid allowed locale');
            }
        }
        if (count(array_unique($allowedLocales)) !== count($allowedLocales) || !in_array($locales['default'] ?? null, $allowedLocales, true)) {
            throw new InstanceConfigurationException('Default locale must be uniquely allowed');
        }

        $modules = self::stringList($data['modules']['enabled'] ?? null, 'enabled modules');
        foreach ($modules as $module) {
            if (preg_match('/^[a-z0-9][a-z0-9.-]{1,79}$/', $module) !== 1) {
                throw new InstanceConfigurationException('Invalid module slug');
            }
        }
        if (count(array_unique($modules)) !== count($modules)) {
            throw new InstanceConfigurationException('Enabled modules must be unique');
        }

        $limits = $data['limits'] ?? null;
        if (!is_array($limits) || $limits === []) {
            throw new InstanceConfigurationException('Missing instance limits');
        }
        foreach ($limits as $key => $value) {
            if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]{1,63}$/', $key) !== 1 || !is_int($value) || $value < 0) {
                throw new InstanceConfigurationException('Invalid instance limit');
            }
        }

        $release = $data['release'] ?? null;
        if (!is_array($release) || !is_string($release['channel'] ?? null) || !is_string($release['id'] ?? null) || trim($release['id']) === '') {
            throw new InstanceConfigurationException('Invalid release metadata');
        }

        $resources = $data['resources'] ?? null;
        if (!is_array($resources)) {
            throw new InstanceConfigurationException('Missing resource references');
        }
        self::validateEnvPrefix($resources['database']['env_prefix'] ?? null, 'database');
        self::validateEnvName($resources['storage']['path_env'] ?? null, 'storage');
        self::validateEnvPrefix($resources['rag']['env_prefix'] ?? null, 'RAG');
        if (preg_match('/^[a-z0-9_]{0,48}$/', (string)($resources['rag']['collection_prefix'] ?? '')) !== 1) {
            throw new InstanceConfigurationException('Invalid RAG collection prefix');
        }
        if (preg_match('/^[a-f0-9]{12}$/', (string)($resources['session']['namespace'] ?? '')) !== 1) {
            throw new InstanceConfigurationException('Invalid session namespace');
        }
        foreach (['secrets', 'backups'] as $resource) {
            if (preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', (string)($resources[$resource]['scope'] ?? '')) !== 1) {
                throw new InstanceConfigurationException("Invalid {$resource} scope");
            }
        }

        return $data;
    }

    private static function stringList(mixed $value, string $label): array
    {
        if (!is_array($value) || $value === []) {
            throw new InstanceConfigurationException("Missing {$label}");
        }
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '') {
                throw new InstanceConfigurationException("Invalid {$label}");
            }
        }
        return array_values($value);
    }

    private static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(rtrim(trim($domain), '.'));
        if ($domain === 'localhost' || filter_var($domain, FILTER_VALIDATE_IP)) {
            return $domain;
        }
        if ($domain === '' || strlen($domain) > 253 || preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/', $domain) !== 1) {
            throw new InstanceConfigurationException('Invalid instance domain');
        }
        foreach (explode('.', $domain) as $label) {
            if ($label === '' || strlen($label) > 63 || $label[0] === '-' || str_ends_with($label, '-')) {
                throw new InstanceConfigurationException('Invalid instance domain label');
            }
        }
        return $domain;
    }

    private static function validateEnvPrefix(mixed $value, string $label): void
    {
        if (!is_string($value) || preg_match('/^[A-Z][A-Z0-9_]{1,31}$/', $value) !== 1) {
            throw new InstanceConfigurationException("Invalid {$label} environment prefix");
        }
    }

    private static function validateEnvName(mixed $value, string $label): void
    {
        if (!is_string($value) || preg_match('/^[A-Z][A-Z0-9_]{1,63}$/', $value) !== 1) {
            throw new InstanceConfigurationException("Invalid {$label} environment name");
        }
    }
}
