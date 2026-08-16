<?php
declare(strict_types=1);

namespace Modules;

final class ModulePresentationState
{
    public const INACTIVE = 'inactive';
    public const ACTIVE = 'active';
    public const DEPENDENCY_REQUIRED = 'dependency_required';
    public const APPLYING = 'applying';
    public const NEEDS_ATTENTION = 'needs_attention';
    public const UNAVAILABLE = 'unavailable';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::INACTIVE,
            self::ACTIVE,
            self::DEPENDENCY_REQUIRED,
            self::APPLYING,
            self::NEEDS_ATTENTION,
            self::UNAVAILABLE,
        ];
    }

    public static function labelKey(string $state): string
    {
        self::assertKnown($state);
        return 'module_ui.state.' . $state . '.label';
    }

    public static function descriptionKey(string $state): string
    {
        self::assertKnown($state);
        return 'module_ui.state.' . $state . '.description';
    }

    public static function tone(string $state): string
    {
        self::assertKnown($state);
        return match ($state) {
            self::ACTIVE => 'positive',
            self::DEPENDENCY_REQUIRED => 'warning',
            self::APPLYING => 'progress',
            self::NEEDS_ATTENTION => 'danger',
            self::UNAVAILABLE => 'muted',
            default => 'neutral',
        };
    }

    private static function assertKnown(string $state): void
    {
        if (!in_array($state, self::all(), true)) {
            throw new ModuleConfigurationException("Unknown module presentation state: {$state}");
        }
    }
}
