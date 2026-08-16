<?php
declare(strict_types=1);

namespace Modules;

final class ModuleCatalogPresenter
{
    /** @var array<string, true> */
    private array $enabledModules;

    /** @param list<string> $effectivelyEnabledModules */
    public function __construct(
        private readonly ModuleRegistry $registry,
        array $effectivelyEnabledModules
    ) {
        $this->enabledModules = array_fill_keys($effectivelyEnabledModules, true);
    }

    /**
     * Runtime state is operational input from the future owner control plane, not client input.
     *
     * @param array<string, array<string, mixed>> $runtimeStates
     * @return list<array<string, mixed>>
     */
    public function present(array $runtimeStates = []): array
    {
        $items = [];
        foreach ($this->registry->all() as $definition) {
            $slug = $definition->slug();
            $effectivelyEnabled = isset($this->enabledModules[$slug]);
            $runtimeState = is_array($runtimeStates[$slug] ?? null) ? $runtimeStates[$slug] : [];
            $missingDependencies = array_values(array_filter(
                $definition->dependencies(),
                fn(string $dependency): bool => !isset($this->enabledModules[$dependency])
            ));
            $state = ModuleStateResolver::resolve($effectivelyEnabled, $runtimeState, $missingDependencies);
            $navigation = $definition->navigation();

            $items[] = [
                'slug' => $slug,
                'name_key' => $definition->nameKey(),
                'description_key' => $definition->descriptionKey(),
                'state' => $state,
                'state_label_key' => ModulePresentationState::labelKey($state),
                'state_description_key' => ModulePresentationState::descriptionKey($state),
                'tone' => ModulePresentationState::tone($state),
                'is_effectively_active' => $effectivelyEnabled,
                'missing_dependency_keys' => array_map(
                    fn(string $dependency): string => $this->registry->require($dependency)->nameKey(),
                    $missingDependencies
                ),
                'route' => is_string($navigation['route'] ?? null) ? $navigation['route'] : null,
                'icon' => is_string($navigation['icon'] ?? null) ? $navigation['icon'] : 'iconoir-puzzle',
                'area' => is_string($navigation['area'] ?? null) ? $navigation['area'] : 'hidden',
            ];
        }

        $areaOrder = ['primary' => 0, 'administration' => 1, 'gestures' => 2, 'hidden' => 3];
        usort($items, static function (array $left, array $right) use ($areaOrder): int {
            $areaComparison = ($areaOrder[$left['area']] ?? 9) <=> ($areaOrder[$right['area']] ?? 9);
            return $areaComparison !== 0 ? $areaComparison : strcmp($left['slug'], $right['slug']);
        });
        return $items;
    }
}
