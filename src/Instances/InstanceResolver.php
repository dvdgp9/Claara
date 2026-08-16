<?php
namespace Instances;

final class InstanceResolver
{
    public static function resolve(InstanceManifest $manifest, string $rawHost): InstanceContext
    {
        try {
            \Modules\ModuleRegistry::defaults()->validateEnabledModules($manifest->modules()['enabled']);
        } catch (\Modules\ModuleConfigurationException $error) {
            throw new InstanceConfigurationException('Invalid instance module configuration: ' . $error->getMessage(), 0, $error);
        }

        $host = self::normalizeHost($rawHost);
        if ($host === null || !in_array($host, $manifest->domains(), true)) {
            throw new UnknownInstanceException('Request host is not assigned to this instance');
        }
        if ($manifest->status() !== 'active') {
            throw new InstanceUnavailableException('Instance is suspended');
        }
        return new InstanceContext($manifest, $host);
    }

    public static function bootFromEnvironment(string $projectRoot): InstanceContext
    {
        $manifestPath = (string)($_ENV['INSTANCE_MANIFEST_PATH'] ?? getenv('INSTANCE_MANIFEST_PATH') ?: '');
        if ($manifestPath === '') {
            $manifestPath = $projectRoot . '/config/instances/default.json';
        } elseif ($manifestPath[0] !== '/') {
            $manifestPath = $projectRoot . '/' . ltrim($manifestPath, '/');
        }

        $rawHost = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($rawHost === '') {
            $appUrl = (string)($_ENV['APP_URL'] ?? getenv('APP_URL') ?: '');
            $rawHost = (string)(parse_url($appUrl, PHP_URL_HOST) ?: '');
        }

        $context = self::resolve(InstanceManifest::fromFile($manifestPath), $rawHost);
        InstanceContext::activate($context);
        return $context;
    }

    public static function normalizeHost(string $rawHost): ?string
    {
        $rawHost = strtolower(trim($rawHost));
        if ($rawHost === '' || preg_match('/[\x00-\x20\x7f\/@]/', $rawHost)) {
            return null;
        }
        $host = parse_url('http://' . $rawHost, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }
        $host = strtolower(rtrim($host, '.'));
        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        if (strlen($host) > 253 || preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/', $host) !== 1) {
            return null;
        }
        foreach (explode('.', $host) as $label) {
            if ($label === '' || strlen($label) > 63 || $label[0] === '-' || str_ends_with($label, '-')) {
                return null;
            }
        }
        return $host;
    }
}
