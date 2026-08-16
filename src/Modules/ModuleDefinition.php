<?php
declare(strict_types=1);

namespace Modules;

final class ModuleDefinition
{
    /**
     * @param list<string> $dependencies
     * @param list<string> $capabilities
     * @param array<string, scalar|array|null> $navigation
     * @param array<string, mixed> $configurationSchema
     */
    public function __construct(
        private readonly string $slug,
        private readonly string $nameKey,
        private readonly string $descriptionKey,
        private readonly bool $defaultEnabled,
        private readonly array $dependencies,
        private readonly array $capabilities,
        private readonly array $navigation,
        private readonly array $configurationSchema,
        private readonly ?string $healthCheckId
    ) {
        self::assertSlug($slug, 'module slug');
        self::assertTranslationKey($nameKey, 'module name key');
        self::assertTranslationKey($descriptionKey, 'module description key');
        self::assertUniqueStringList($dependencies, 'module dependencies');
        self::assertUniqueStringList($capabilities, 'module capabilities');

        foreach ($dependencies as $dependency) {
            self::assertSlug($dependency, 'module dependency');
        }
        foreach ($capabilities as $capability) {
            if (preg_match('/^[a-z][a-z0-9_-]{1,31}:(?:\*|[a-z0-9][a-z0-9._-]{0,79})$/', $capability) !== 1) {
                throw new ModuleConfigurationException("Invalid module capability: {$capability}");
            }
        }

        if ($navigation !== []) {
            $route = $navigation['route'] ?? null;
            if (!is_string($route) || preg_match('#^/[A-Za-z0-9_./-]*$#', $route) !== 1 || str_contains($route, '..')) {
                throw new ModuleConfigurationException("Invalid navigation route for module {$slug}");
            }
            $icon = $navigation['icon'] ?? null;
            if (!is_string($icon) || preg_match('/^iconoir-[a-z0-9-]+$/', $icon) !== 1) {
                throw new ModuleConfigurationException("Invalid navigation icon for module {$slug}");
            }
            $area = $navigation['area'] ?? null;
            if (!is_string($area) || !in_array($area, ['primary', 'gestures', 'administration', 'hidden'], true)) {
                throw new ModuleConfigurationException("Invalid navigation area for module {$slug}");
            }
        }

        if ($healthCheckId !== null && preg_match('/^[a-z][a-z0-9._-]{1,63}$/', $healthCheckId) !== 1) {
            throw new ModuleConfigurationException("Invalid health-check id for module {$slug}");
        }
    }

    public function slug(): string { return $this->slug; }
    public function nameKey(): string { return $this->nameKey; }
    public function descriptionKey(): string { return $this->descriptionKey; }
    public function defaultEnabled(): bool { return $this->defaultEnabled; }
    public function dependencies(): array { return $this->dependencies; }
    public function capabilities(): array { return $this->capabilities; }
    public function navigation(): array { return $this->navigation; }
    public function configurationSchema(): array { return $this->configurationSchema; }
    public function healthCheckId(): ?string { return $this->healthCheckId; }

    private static function assertSlug(string $value, string $label): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9.-]{1,79}$/', $value) !== 1) {
            throw new ModuleConfigurationException("Invalid {$label}: {$value}");
        }
    }

    private static function assertTranslationKey(string $value, string $label): void
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{2,127}$/', $value) !== 1) {
            throw new ModuleConfigurationException("Invalid {$label}: {$value}");
        }
    }

    private static function assertUniqueStringList(array $values, string $label): void
    {
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new ModuleConfigurationException("Invalid {$label}");
            }
        }
        if (count(array_unique($values)) !== count($values)) {
            throw new ModuleConfigurationException("Duplicate {$label}");
        }
    }
}
