<?php
declare(strict_types=1);

namespace Modules;

final class ModuleRegistry
{
    /** @var array<string, ModuleDefinition> */
    private array $definitions = [];

    /** @var array<string, string> capability => module slug */
    private array $capabilityOwners = [];

    /** @param list<ModuleDefinition> $definitions */
    public function __construct(array $definitions)
    {
        foreach ($definitions as $definition) {
            if (!$definition instanceof ModuleDefinition) {
                throw new ModuleConfigurationException('Module registry accepts only ModuleDefinition values');
            }
            if (isset($this->definitions[$definition->slug()])) {
                throw new ModuleConfigurationException("Duplicate module definition: {$definition->slug()}");
            }
            $this->definitions[$definition->slug()] = $definition;

            foreach ($definition->capabilities() as $capability) {
                if (isset($this->capabilityOwners[$capability])) {
                    throw new ModuleConfigurationException("Duplicate module capability owner: {$capability}");
                }
                $this->capabilityOwners[$capability] = $definition->slug();
            }
        }

        if ($this->definitions === []) {
            throw new ModuleConfigurationException('Module registry cannot be empty');
        }

        foreach ($this->definitions as $definition) {
            foreach ($definition->dependencies() as $dependency) {
                if (!isset($this->definitions[$dependency])) {
                    throw new ModuleConfigurationException("Unknown dependency {$dependency} for module {$definition->slug()}");
                }
            }
        }

        $this->assertAcyclic();
    }

    public static function defaults(): self
    {
        return new self(self::defaultDefinitions());
    }

    /** @return list<ModuleDefinition> */
    public function all(): array
    {
        $definitions = $this->definitions;
        ksort($definitions);
        return array_values($definitions);
    }

    public function find(string $slug): ?ModuleDefinition
    {
        return $this->definitions[$slug] ?? null;
    }

    public function require(string $slug): ModuleDefinition
    {
        $definition = $this->find($slug);
        if ($definition === null) {
            throw new ModuleConfigurationException("Unknown module: {$slug}");
        }
        return $definition;
    }

    public function moduleForCapability(string $type, string $slug): ?ModuleDefinition
    {
        $exact = $type . ':' . $slug;
        $moduleSlug = $this->capabilityOwners[$exact] ?? $this->capabilityOwners[$type . ':*'] ?? null;
        return $moduleSlug === null ? null : $this->definitions[$moduleSlug];
    }

    /** @param list<string> $enabled */
    public function validateEnabledModules(array $enabled): void
    {
        if (count(array_unique($enabled)) !== count($enabled)) {
            throw new ModuleConfigurationException('Enabled modules must be unique');
        }
        $enabledMap = [];
        foreach ($enabled as $slug) {
            if (!is_string($slug) || !isset($this->definitions[$slug])) {
                throw new ModuleConfigurationException('Instance references an unknown module');
            }
            $enabledMap[$slug] = true;
        }
        foreach (array_keys($enabledMap) as $slug) {
            foreach ($this->definitions[$slug]->dependencies() as $dependency) {
                if (!isset($enabledMap[$dependency])) {
                    throw new ModuleConfigurationException("Enabled module {$slug} requires {$dependency}");
                }
            }
        }
    }

    /**
     * @param list<string> $enabled
     * @return list<string>
     */
    public function orderedEnabled(array $enabled): array
    {
        $this->validateEnabledModules($enabled);
        $enabledMap = array_fill_keys($enabled, true);
        $ordered = [];
        $visited = [];
        $slugs = array_keys($enabledMap);
        sort($slugs);
        foreach ($slugs as $slug) {
            $this->appendDependenciesFirst($slug, $enabledMap, $visited, $ordered);
        }
        return $ordered;
    }

    private function assertAcyclic(): void
    {
        $visiting = [];
        $visited = [];
        foreach (array_keys($this->definitions) as $slug) {
            $this->visitForCycle($slug, $visiting, $visited);
        }
    }

    private function visitForCycle(string $slug, array &$visiting, array &$visited): void
    {
        if (isset($visited[$slug])) {
            return;
        }
        if (isset($visiting[$slug])) {
            throw new ModuleConfigurationException("Module dependency cycle detected at {$slug}");
        }
        $visiting[$slug] = true;
        foreach ($this->definitions[$slug]->dependencies() as $dependency) {
            $this->visitForCycle($dependency, $visiting, $visited);
        }
        unset($visiting[$slug]);
        $visited[$slug] = true;
    }

    private function appendDependenciesFirst(string $slug, array $enabledMap, array &$visited, array &$ordered): void
    {
        if (isset($visited[$slug])) {
            return;
        }
        $dependencies = $this->definitions[$slug]->dependencies();
        sort($dependencies);
        foreach ($dependencies as $dependency) {
            if (isset($enabledMap[$dependency])) {
                $this->appendDependenciesFirst($dependency, $enabledMap, $visited, $ordered);
            }
        }
        $visited[$slug] = true;
        $ordered[] = $slug;
    }

    /** @return list<ModuleDefinition> */
    private static function defaultDefinitions(): array
    {
        return [
            self::module('core.chat', true, [], ['surface:chat'], '/app.php', 'iconoir-chat-bubble', 'primary', 'chat'),
            self::module('core.voices', true, [], ['surface:voices', 'voice:*'], '/voices/', 'iconoir-microphone-speaking', 'primary', 'voices'),
            self::module('core.gestures', true, [], ['surface:gestures'], '/gestos/', 'iconoir-sparks', 'primary', 'gestures'),
            self::module('core.connectors', true, [], ['surface:connectors'], '/connectors/', 'iconoir-link', 'primary', 'connectors'),
            self::module('core.administration', true, [], ['surface:administration', 'feature:voice-editor'], '/admin/', 'iconoir-settings', 'administration', 'administration'),
            self::module('feature.image-generation', false, ['core.chat'], ['feature:image-generation'], '', '', 'hidden'),
            self::gesture('write-article', 'escribir-articulo.php', 'edit-pencil'),
            self::gesture('social-media', 'redes-sociales.php', 'instagram'),
            self::gesture('podcast-from-article', 'podcast-articulo.php', 'podcast'),
            self::gesture('image-editor', 'editor-imagenes.php', 'media-image'),
            self::gesture('content-repurposer', 'transformador-contenido.php', 'refresh-double'),
            self::gesture('sop-generator', 'sop-generator.php', 'page-edit'),
            self::gesture('audio-transcriber', 'transcriptor-audio.php', 'microphone'),
            self::gesture('course-creator', 'creador-cursos.php', 'book'),
            self::gesture('project-admin', 'admin-proyectos.php', 'folder-settings'),
            self::gesture('lead-finder', 'lead-finder.php', 'search'),
        ];
    }

    private static function gesture(string $slug, string $route, string $icon): ModuleDefinition
    {
        return self::module(
            'gesture.' . $slug,
            false,
            ['core.gestures'],
            ['gesture:' . $slug],
            '/gestos/' . $route,
            'iconoir-' . $icon,
            'gestures'
        );
    }

    private static function module(
        string $slug,
        bool $defaultEnabled,
        array $dependencies,
        array $capabilities,
        string $route,
        string $icon,
        string $area,
        ?string $healthCheckId = null
    ): ModuleDefinition {
        $key = str_replace(['.', '-'], '_', $slug);
        $navigation = $route === '' ? [] : ['route' => $route, 'icon' => $icon, 'area' => $area];
        return new ModuleDefinition(
            $slug,
            "modules.{$key}.name",
            "modules.{$key}.description",
            $defaultEnabled,
            $dependencies,
            $capabilities,
            $navigation,
            [],
            $healthCheckId
        );
    }
}
