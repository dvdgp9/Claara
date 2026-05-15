<?php
require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/App/Session.php';

use App\Session;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

if (empty($user['is_superadmin']) && !in_array('admin', $user['roles'] ?? [], true)) {
    header('Location: /');
    exit;
}

$activeTab = 'connectors';
$pageTitle = 'Connector Overview - iaiaPRO';
$headerTitle = 'Connector Overview';
$headerSubtitle = 'Global connection health and rollout status';
$headerIcon = 'iconoir-cloud-sync';
$headerIconColor = 'from-cyan-500 to-teal-700';
$headerBackUrl = '/connectors.php';
$headerBackText = 'My connectors';
?><!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="bg-slate-50 text-slate-900 overflow-hidden connectors-accent">
  <div class="min-h-[100dvh] flex h-[100dvh]">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>

    <main class="flex-1 flex flex-col min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <div class="flex-1 overflow-auto pb-16 lg:pb-0">
        <div class="max-w-[1400px] mx-auto p-4 lg:p-6">
          <section class="connectors-admin-layout">
            <div class="connectors-admin-copy">
              <p class="connectors-kicker">Administration</p>
              <h1>Monitor connector rollout without touching user accounts.</h1>
              <p>Users connect their own sources. Admins supervise provider availability, connection volume and failed imports from this overview.</p>
            </div>
            <div class="connectors-admin-metrics">
              <div>
                <span id="admin-connected-count">0</span>
                <label>Connected accounts</label>
              </div>
              <div>
                <span id="admin-imported-count">0</span>
                <label>Imported items</label>
              </div>
              <div>
                <span id="admin-error-count">0</span>
                <label>Needs attention</label>
              </div>
            </div>
          </section>

          <section class="connectors-grid-shell">
            <div class="connectors-section-head">
              <div>
                <h2>Provider status</h2>
                <p id="admin-connectors-count">Loading provider status...</p>
              </div>
              <button id="refresh-admin-connectors-btn" class="connectors-icon-btn" title="Refresh">
                <i class="iconoir-refresh"></i>
              </button>
            </div>

            <div id="admin-connectors-loading" class="connectors-loading-grid">
              <div class="connectors-skeleton"></div>
              <div class="connectors-skeleton"></div>
              <div class="connectors-skeleton"></div>
            </div>

            <div id="admin-connectors-error" class="hidden connectors-error">
              <i class="iconoir-warning-triangle"></i>
              <span>Could not load provider status.</span>
            </div>

            <div id="admin-connectors-list" class="connectors-admin-list hidden"></div>
          </section>
        </div>
      </div>
    </main>
  </div>

  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
  <script src="/assets/js/admin-connectors.js"></script>
</body>
</html>

