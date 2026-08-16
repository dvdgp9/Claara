<?php
require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Repos/UserFeatureAccessRepo.php';

use App\Session;
use I18n\I18n;
use Repos\UserFeatureAccessRepo;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

$accessRepo = new UserFeatureAccessRepo();
if (!$accessRepo->hasGestureAccess((int)$user['id'], 'lead-finder')) {
    header('Location: /gestos/?error=no_access');
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
$activeTab = 'gestures';
$pageTitle = I18n::translate('lead_ui.page_title');

$headerBackUrl = '/gestos/';
$headerBackText = I18n::translate('lead_ui.all_gestures');
$headerTitle = 'Lead Finder';
$headerSubtitle = I18n::translate('lead_ui.header_subtitle');
$headerIcon = 'iconoir-search-window';
$headerIconColor = 'from-emerald-500 to-teal-700';
$headerDrawerId = 'lead-finder-history-drawer';
$leadFinderJs = I18n::javascriptCatalogPrefixJson('lead_ui.');
?><!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::htmlLang()); ?>">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="bg-mesh text-slate-900 overflow-hidden lead-finder-accent">
  <div class="min-h-[100dvh] flex h-[100dvh]">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>

    <aside id="history-sidebar" class="hidden lg:flex w-72 glass-strong border-r border-slate-200/50 flex-col shrink-0">
      <div class="p-4 border-b border-slate-200/50">
        <div class="flex items-center justify-between">
          <h2 class="font-semibold text-slate-800 flex items-center gap-2">
            <i class="iconoir-clock text-emerald-600"></i>
            <?php echo htmlspecialchars(I18n::translate('lead_ui.history')); ?>
          </h2>
          <button id="history-new-search" class="lead-finder-icon-btn" title="<?php echo htmlspecialchars(I18n::translate('lead_ui.new_search')); ?>">
            <i class="iconoir-plus"></i>
          </button>
        </div>
      </div>
      <div id="history-list" class="flex-1 overflow-auto">
        <div class="p-4 text-center text-slate-400 text-sm">
          <i class="iconoir-refresh animate-spin"></i>
          <?php echo htmlspecialchars(I18n::translate('lead_ui.loading')); ?>
        </div>
      </div>
    </aside>

    <?php
    $drawerId = 'lead-finder-history-drawer';
    $drawerTitle = I18n::translate('lead_ui.history_title');
    $drawerIcon = 'iconoir-clock';
    $drawerIconColor = 'text-emerald-600';
    include __DIR__ . '/../includes/mobile-drawer.php';
    ?>

    <main class="flex-1 flex flex-col overflow-hidden min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <div class="lead-finder-shell flex-1 overflow-auto pb-16 lg:pb-0">
        <div class="max-w-[1400px] mx-auto p-4 lg:p-6 space-y-5">
          <section class="grid grid-cols-1 xl:grid-cols-[minmax(360px,520px)_1fr] gap-5 items-start">
            <div class="lead-finder-panel rounded-2xl p-5 lg:p-6">
              <div class="flex items-start justify-between gap-4 mb-5">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 mb-2">Lead Finder</p>
                  <h1 class="text-2xl lg:text-3xl font-bold text-slate-950 tracking-tight"><?php echo htmlspecialchars(I18n::translate('lead_ui.heading')); ?></h1>
                </div>
                <div class="hidden sm:flex h-12 w-12 rounded-2xl bg-emerald-50 text-emerald-700 items-center justify-center">
                  <i class="iconoir-search-window text-2xl"></i>
                </div>
              </div>

              <form id="lead-search-form" class="lead-finder-command rounded-2xl p-4 space-y-4">
                <div class="space-y-2">
                  <label for="lead-query" class="block text-sm font-semibold text-slate-800"><?php echo htmlspecialchars(I18n::translate('lead_ui.search_request')); ?></label>
                  <textarea id="lead-query" class="lead-finder-input w-full bg-transparent text-base leading-relaxed" placeholder="<?php echo htmlspecialchars(I18n::translate('lead_ui.query_placeholder')); ?>"></textarea>
                  <p id="lead-query-error" class="hidden text-xs text-red-600"><?php echo htmlspecialchars(I18n::translate('lead_ui.query_required')); ?></p>
                </div>

                <div class="grid grid-cols-[1fr_auto] gap-3 items-end">
                  <div class="space-y-2">
                    <label for="lead-max-results" class="block text-sm font-semibold text-slate-800"><?php echo htmlspecialchars(I18n::translate('lead_ui.max_results')); ?></label>
                    <select id="lead-max-results" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 input-focus">
                      <option value="25">25</option>
                      <option value="50">50</option>
                      <option value="100">100</option>
                    </select>
                  </div>
                  <button id="lead-search-btn" type="submit" class="lead-finder-primary h-[46px] px-5 rounded-xl font-semibold transition-all flex items-center gap-2">
                    <i class="iconoir-search"></i>
                    <span><?php echo htmlspecialchars(I18n::translate('lead_ui.search')); ?></span>
                  </button>
                </div>
              </form>

              <div class="mt-4 flex flex-wrap gap-2" aria-label="<?php echo htmlspecialchars(I18n::translate('lead_ui.examples')); ?>">
                <button type="button" class="lead-finder-chip rounded-full px-3 py-2 text-xs font-semibold" data-example="<?php echo htmlspecialchars(I18n::translate('lead_ui.example_schools_query')); ?>"><?php echo htmlspecialchars(I18n::translate('lead_ui.example_schools')); ?></button>
                <button type="button" class="lead-finder-chip rounded-full px-3 py-2 text-xs font-semibold" data-example="<?php echo htmlspecialchars(I18n::translate('lead_ui.example_dentists_query')); ?>"><?php echo htmlspecialchars(I18n::translate('lead_ui.example_dentists')); ?></button>
                <button type="button" class="lead-finder-chip rounded-full px-3 py-2 text-xs font-semibold" data-example="<?php echo htmlspecialchars(I18n::translate('lead_ui.example_hotels_query')); ?>"><?php echo htmlspecialchars(I18n::translate('lead_ui.example_hotels')); ?></button>
              </div>
            </div>

            <div class="lead-finder-panel rounded-2xl p-5 lg:p-6 min-h-[220px]">
              <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                  <p id="run-eyebrow" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400"><?php echo htmlspecialchars(I18n::translate('lead_ui.workspace')); ?></p>
                  <h2 id="run-title" class="text-xl font-bold text-slate-950 mt-1"><?php echo htmlspecialchars(I18n::translate('lead_ui.ready')); ?></h2>
                </div>
                <div class="grid grid-cols-3 gap-2 min-w-[240px]">
                  <div class="rounded-xl bg-white/70 border border-slate-200/70 px-3 py-2">
                    <div id="metric-total" class="text-lg font-bold text-slate-900">0</div>
                    <div class="text-[10px] uppercase font-semibold text-slate-400"><?php echo htmlspecialchars(I18n::translate('lead_ui.found')); ?></div>
                  </div>
                  <div class="rounded-xl bg-white/70 border border-slate-200/70 px-3 py-2">
                    <div id="metric-validated" class="text-lg font-bold text-emerald-700">0</div>
                    <div class="text-[10px] uppercase font-semibold text-slate-400"><?php echo htmlspecialchars(I18n::translate('lead_ui.valid')); ?></div>
                  </div>
                  <div class="rounded-xl bg-white/70 border border-slate-200/70 px-3 py-2">
                    <div id="metric-rejected" class="text-lg font-bold text-red-600">0</div>
                    <div class="text-[10px] uppercase font-semibold text-slate-400"><?php echo htmlspecialchars(I18n::translate('lead_ui.rejected')); ?></div>
                  </div>
                </div>
              </div>

              <div id="status-strip" class="mt-5 hidden rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-3">
                <div class="flex items-center gap-3">
                  <span class="lead-finder-status-dot"></span>
                  <div>
                    <p id="status-title" class="text-sm font-semibold text-emerald-900"><?php echo htmlspecialchars(I18n::translate('lead_ui.preparing')); ?></p>
                    <p id="status-detail" class="text-xs text-emerald-700"><?php echo htmlspecialchars(I18n::translate('lead_ui.worker_starting')); ?></p>
                  </div>
                </div>
              </div>

              <div id="empty-state" class="mt-8 border-t border-slate-200/70 pt-8">
                <div class="max-w-xl">
                  <div class="flex items-center gap-2 text-slate-500 mb-3">
                    <i class="iconoir-table-rows"></i>
                    <span class="text-sm font-semibold"><?php echo htmlspecialchars(I18n::translate('lead_ui.no_results_loaded')); ?></span>
                  </div>
                  <p class="text-sm text-slate-500 leading-relaxed"><?php echo htmlspecialchars(I18n::translate('lead_ui.empty_help')); ?></p>
                </div>
              </div>
            </div>
          </section>

          <section id="results-section" class="hidden lead-finder-panel rounded-2xl overflow-hidden">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 p-4 lg:p-5">
              <div>
                <h3 class="font-bold text-slate-950"><?php echo htmlspecialchars(I18n::translate('lead_ui.review_queue')); ?></h3>
                <p class="text-sm text-slate-500"><?php echo htmlspecialchars(I18n::translate('lead_ui.review_help')); ?></p>
              </div>
              <div class="flex items-center gap-2">
                <button id="export-csv-btn" class="lead-finder-icon-btn" title="<?php echo htmlspecialchars(I18n::translate('lead_ui.export_csv')); ?>">
                  <i class="iconoir-download"></i>
                </button>
                <button id="new-search-btn" class="lead-finder-icon-btn" title="<?php echo htmlspecialchars(I18n::translate('lead_ui.new_search')); ?>">
                  <i class="iconoir-plus"></i>
                </button>
              </div>
            </div>
            <div id="table-loading" class="hidden p-4 space-y-2">
              <div class="lead-finder-skeleton h-10 rounded-xl"></div>
              <div class="lead-finder-skeleton h-10 rounded-xl"></div>
              <div class="lead-finder-skeleton h-10 rounded-xl"></div>
              <div class="lead-finder-skeleton h-10 rounded-xl"></div>
            </div>
            <div id="results-table-wrap" class="lead-finder-table-wrap">
              <table class="lead-finder-table">
                <thead>
                  <tr>
                    <th class="w-[220px]"><?php echo htmlspecialchars(I18n::translate('lead_ui.name')); ?></th>
                    <th class="w-[210px]"><?php echo htmlspecialchars(I18n::translate('lead_ui.website')); ?></th>
                    <th class="w-[220px]">Email</th>
                    <th class="w-[130px]"><?php echo htmlspecialchars(I18n::translate('lead_ui.phone')); ?></th>
                    <th class="w-[260px]"><?php echo htmlspecialchars(I18n::translate('lead_ui.address')); ?></th>
                    <th class="w-[110px]"><?php echo htmlspecialchars(I18n::translate('lead_ui.confidence')); ?></th>
                    <th class="w-[150px]"><?php echo htmlspecialchars(I18n::translate('lead_ui.status')); ?></th>
                    <th class="w-[130px]"><?php echo htmlspecialchars(I18n::translate('lead_ui.actions')); ?></th>
                  </tr>
                </thead>
                <tbody id="results-body"></tbody>
              </table>
            </div>
          </section>
        </div>
      </div>
    </main>
  </div>

  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
  <script>
    window.LEAD_FINDER_CSRF = '<?= htmlspecialchars($csrfToken) ?>';
    window.CLAARA_LEAD_I18N = <?php echo $leadFinderJs; ?>;
  </script>
  <script src="/assets/js/gesture-lead-finder.js"></script>
</body>
</html>
