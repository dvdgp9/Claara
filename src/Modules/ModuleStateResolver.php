<?php
declare(strict_types=1);

namespace Modules;

final class ModuleStateResolver
{
    /**
     * @param array<string, mixed> $runtimeState
     * @param list<string> $missingDependencies
     */
    public static function resolve(
        bool $effectivelyEnabled,
        array $runtimeState,
        array $missingDependencies
    ): string {
        if (($runtimeState['available'] ?? true) !== true) {
            return ModulePresentationState::UNAVAILABLE;
        }

        $requestedEnabled = ($runtimeState['requested_enabled'] ?? $effectivelyEnabled) === true;
        if (!$effectivelyEnabled && $requestedEnabled && $missingDependencies !== []) {
            return ModulePresentationState::DEPENDENCY_REQUIRED;
        }

        if (($runtimeState['deployment_pending'] ?? false) === true) {
            return ModulePresentationState::APPLYING;
        }

        if ($effectivelyEnabled && ($runtimeState['health'] ?? 'unknown') === 'needs_attention') {
            return ModulePresentationState::NEEDS_ATTENTION;
        }

        return $effectivelyEnabled
            ? ModulePresentationState::ACTIVE
            : ModulePresentationState::INACTIVE;
    }
}
