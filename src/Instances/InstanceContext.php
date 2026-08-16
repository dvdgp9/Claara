<?php
namespace Instances;

final class InstanceContext
{
    private static ?self $current = null;
    private readonly InstanceResources $resources;

    public function __construct(private readonly InstanceManifest $manifest, private readonly string $host)
    {
        $this->resources = new InstanceResources($manifest->resources());
    }

    public static function activate(self $context): void
    {
        if (self::$current !== null) {
            throw new InstanceConfigurationException('Instance context is already active');
        }
        self::$current = $context;
    }

    public static function current(): self
    {
        if (self::$current === null) {
            throw new InstanceConfigurationException('Instance context has not been initialized');
        }
        return self::$current;
    }

    public static function currentOrNull(): ?self { return self::$current; }
    public function id(): string { return $this->manifest->id(); }
    public function slug(): string { return $this->manifest->slug(); }
    public function host(): string { return $this->host; }
    public function canonicalDomain(): string { return $this->manifest->canonicalDomain(); }
    public function branding(): array { return $this->manifest->branding(); }
    public function defaultLocale(): string { return $this->manifest->locales()['default']; }
    public function allowedLocales(): array { return $this->manifest->locales()['allowed']; }
    public function enabledModules(): array { return $this->manifest->modules()['enabled']; }
    public function isModuleEnabled(string $module): bool { return in_array($module, $this->enabledModules(), true); }
    public function limit(string $key): ?int { return $this->manifest->limits()[$key] ?? null; }
    public function release(): array { return $this->manifest->release(); }
    public function resources(): InstanceResources { return $this->resources; }
}
