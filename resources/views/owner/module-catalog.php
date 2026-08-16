<?php
declare(strict_types=1);

/**
 * Reusable owner-control-plane module catalog.
 *
 * Required: $moduleCatalogItems from Modules\ModuleCatalogPresenter.
 * Optional: $moduleCatalogLoading, $moduleCatalogError, $moduleCatalogTranslate.
 */
$moduleCatalogItems = is_array($moduleCatalogItems ?? null) ? $moduleCatalogItems : [];
$moduleCatalogLoading = ($moduleCatalogLoading ?? false) === true;
$moduleCatalogError = is_string($moduleCatalogError ?? null) && $moduleCatalogError !== '';
$moduleCatalogTranslate = is_callable($moduleCatalogTranslate ?? null)
    ? $moduleCatalogTranslate
    : static fn(string $key, array $parameters = []): string => \I18n\I18n::translate($key, $parameters);
$moduleCatalogId = 'module-state-catalog-' . substr(hash('sha256', implode('|', array_column($moduleCatalogItems, 'slug'))), 0, 10);
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="module-state-catalog" aria-labelledby="<?= $escape($moduleCatalogId) ?>-title">
  <header class="module-state-catalog__header">
    <div>
      <p class="module-state-catalog__eyebrow"><?= $escape($moduleCatalogTranslate('module_ui.catalog_eyebrow')) ?></p>
      <h2 id="<?= $escape($moduleCatalogId) ?>-title"><?= $escape($moduleCatalogTranslate('module_ui.catalog_title')) ?></h2>
    </div>
    <p><?= $escape($moduleCatalogTranslate('module_ui.catalog_description')) ?></p>
  </header>

  <?php if ($moduleCatalogLoading): ?>
    <div class="module-state-list module-state-list--loading" aria-busy="true" aria-label="<?= $escape($moduleCatalogTranslate('module_ui.loading')) ?>">
      <?php for ($index = 0; $index < 3; $index++): ?>
        <div class="module-state-skeleton" aria-hidden="true">
          <span></span><span></span><span></span>
        </div>
      <?php endfor; ?>
    </div>
  <?php elseif ($moduleCatalogError): ?>
    <div class="module-state-message module-state-message--error" role="alert">
      <i class="iconoir-warning-triangle" aria-hidden="true"></i>
      <div>
        <h3><?= $escape($moduleCatalogTranslate('module_ui.error_title')) ?></h3>
        <p><?= $escape($moduleCatalogTranslate('module_ui.error_description')) ?></p>
      </div>
    </div>
  <?php elseif ($moduleCatalogItems === []): ?>
    <div class="module-state-message module-state-empty">
      <i class="iconoir-box-iso" aria-hidden="true"></i>
      <div>
        <h3><?= $escape($moduleCatalogTranslate('module_ui.empty_title')) ?></h3>
        <p><?= $escape($moduleCatalogTranslate('module_ui.empty_description')) ?></p>
      </div>
    </div>
  <?php else: ?>
    <div class="module-state-list" role="list">
      <?php foreach ($moduleCatalogItems as $moduleItem): ?>
        <?php
        $moduleName = $moduleCatalogTranslate((string)$moduleItem['name_key']);
        $stateLabel = $moduleCatalogTranslate((string)$moduleItem['state_label_key']);
        $dependencyNames = array_map(
            static fn(string $key): string => $moduleCatalogTranslate($key),
            $moduleItem['missing_dependency_keys'] ?? []
        );
        ?>
        <article class="module-state-row" role="listitem" data-state="<?= $escape((string)$moduleItem['state']) ?>">
          <div class="module-state-row__summary">
            <span class="module-state-row__icon" aria-hidden="true">
              <i class="<?= $escape((string)$moduleItem['icon']) ?>"></i>
            </span>
            <div class="module-state-row__copy">
              <h3><?= $escape($moduleName) ?></h3>
              <p><?= $escape($moduleCatalogTranslate((string)$moduleItem['description_key'])) ?></p>
            </div>
            <span
              class="module-state-badge"
              data-tone="<?= $escape((string)$moduleItem['tone']) ?>"
              aria-label="<?= $escape($moduleCatalogTranslate('module_ui.status_aria', ['status' => $stateLabel])) ?>"
            >
              <span aria-hidden="true"></span><?= $escape($stateLabel) ?>
            </span>
          </div>
          <details class="module-state-details">
            <summary><?= $escape($moduleCatalogTranslate('module_ui.details')) ?></summary>
            <div class="module-state-details__body">
              <p><?= $escape($moduleCatalogTranslate((string)$moduleItem['state_description_key'])) ?></p>
              <?php if ($dependencyNames !== []): ?>
                <p class="module-state-dependencies">
                  <?= $escape($moduleCatalogTranslate('module_ui.dependencies', ['modules' => implode(', ', $dependencyNames)])) ?>
                </p>
              <?php endif; ?>
            </div>
          </details>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
