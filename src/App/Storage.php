<?php
namespace App;

use Instances\InstanceConfigurationException;
use Instances\InstanceContext;

final class Storage
{
    public static function root(): string
    {
        $projectRoot = dirname(__DIR__, 2);
        return InstanceContext::current()->resources()->storageRoot($projectRoot);
    }

    public static function path(string $relative = ''): string
    {
        $relative = trim(str_replace('\\', '/', $relative), '/');
        if ($relative === '') {
            return self::root();
        }
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_contains($segment, "\0")) {
                throw new InstanceConfigurationException('Invalid instance storage path');
            }
        }
        return self::root() . '/' . $relative;
    }
}
