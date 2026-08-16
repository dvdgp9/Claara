<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/Modules/ModuleConfigurationException.php';
require_once $root . '/src/Modules/ModuleDefinition.php';
require_once $root . '/src/Modules/ModuleRegistry.php';
require_once $root . '/src/Modules/ModulePresentationState.php';
require_once $root . '/src/Modules/ModuleStateResolver.php';
require_once $root . '/src/Modules/ModuleCatalogPresenter.php';

use Modules\ModuleCatalogPresenter;
use Modules\ModuleRegistry;

$locale = ($_GET['locale'] ?? 'es') === 'en' ? 'en' : 'es';
$dictionary = require $root . '/resources/i18n/' . $locale . '.php';
$moduleCatalogTranslate = static function (string $key, array $parameters = []) use ($dictionary): string {
    $message = (string)($dictionary[$key] ?? "[{$key}]");
    foreach ($parameters as $name => $value) {
        $message = str_replace('{' . $name . '}', (string)$value, $message);
    }
    return $message;
};

$presenter = new ModuleCatalogPresenter(ModuleRegistry::defaults(), [
    'core.chat',
    'core.connectors',
    'core.administration',
]);
$moduleCatalogItems = $presenter->present([
    'core.connectors' => ['deployment_pending' => true],
    'core.administration' => ['health' => 'needs_attention'],
    'gesture.course-creator' => ['requested_enabled' => true],
    'feature.image-generation' => ['available' => false],
]);
?>
<!doctype html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($moduleCatalogTranslate('module_ui.catalog_title'), ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="/public/assets/css/styles.css">
</head>
<body>
  <main>
    <?php require $root . '/resources/views/owner/module-catalog.php'; ?>
  </main>
</body>
</html>
