<?php
declare(strict_types=1);

namespace Modules;

use Instances\InstanceContext;

final class ModuleEntitlementService
{
    public function __construct(
        private readonly InstanceContext $instance,
        private readonly ModuleRegistry $registry
    ) {
    }

    public static function current(): self
    {
        return new self(InstanceContext::current(), ModuleRegistry::defaults());
    }

    public function isModuleEnabled(string $moduleSlug): bool
    {
        return $this->registry->find($moduleSlug) !== null
            && $this->instance->isModuleEnabled($moduleSlug);
    }

    public function isCapabilityEnabled(string $type, string $slug): bool
    {
        if (!$this->isValidCapabilityIdentifier($type, $slug)) {
            return false;
        }

        $module = $this->registry->moduleForCapability($type, $slug);
        return $module !== null && $this->isModuleEnabled($module->slug());
    }

    public function isCapabilityRegistered(string $type, string $slug): bool
    {
        return $this->isValidCapabilityIdentifier($type, $slug)
            && $this->registry->moduleForCapability($type, $slug) !== null;
    }

    private function isValidCapabilityIdentifier(string $type, string $slug): bool
    {
        return preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $type) === 1
            && preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/', $slug) === 1;
    }
}
