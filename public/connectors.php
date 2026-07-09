<?php
require_once __DIR__ . '/../src/App/bootstrap.php';
require_once __DIR__ . '/../src/App/Session.php';

use App\Session;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
$activeTab = 'connectors';
$pageTitle = 'Connectors - Claara';
$headerTitle = 'Connectors';
$headerSubtitle = 'Connect selected sources to Claara context';
$headerIcon = 'iconoir-cloud-sync';
$headerIconColor = 'from-cyan-500 to-teal-700';
$headerBackUrl = '/app/';
$headerBackText = 'Chat';
?><!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/includes/head.php'; ?>
<body class="bg-slate-50 text-slate-900 overflow-hidden connectors-accent">
  <div class="min-h-[100dvh] flex h-[100dvh]">
    <?php include __DIR__ . '/includes/left-tabs.php'; ?>

    <main class="flex-1 flex flex-col min-w-0">
      <?php include __DIR__ . '/includes/header-unified.php'; ?>

      <div class="flex-1 overflow-auto pb-16 lg:pb-0">
        <div class="max-w-[1400px] mx-auto p-4 lg:p-6">
          <section class="connectors-hero">
            <div class="connectors-hero-copy">
              <p class="connectors-kicker">Personal sources</p>
              <h1>Connect files when you need context.</h1>
              <p>Start with Google Drive selected files. Broader workplace connectors stay gated until their OAuth and admin approvals are ready.</p>
            </div>
            <div class="connectors-hero-panel">
              <div>
                <span class="connectors-panel-label">Launch mode</span>
                <strong>Google Drive fast path</strong>
              </div>
              <span class="connectors-pulse"></span>
            </div>
          </section>

          <div id="connectors-notice" class="hidden connectors-notice" role="status"></div>

          <section class="connectors-grid-shell">
            <div class="connectors-section-head">
              <div>
                <h2>Available connectors</h2>
                <p id="connectors-count">Loading connector status...</p>
              </div>
              <button id="refresh-connectors-btn" class="connectors-icon-btn" title="Refresh">
                <i class="iconoir-refresh"></i>
              </button>
            </div>

            <div id="connectors-loading" class="connectors-loading-grid">
              <div class="connectors-skeleton"></div>
              <div class="connectors-skeleton"></div>
              <div class="connectors-skeleton"></div>
            </div>

            <div id="connectors-error" class="hidden connectors-error">
              <i class="iconoir-warning-triangle"></i>
              <span>Could not load connectors.</span>
            </div>

            <div id="connectors-list" class="connectors-provider-grid hidden"></div>
          </section>
        </div>
      </div>
    </main>
  </div>

  <?php include __DIR__ . '/includes/bottom-nav.php'; ?>
  <script src="/assets/js/connectors.js"></script>
</body>
</html>
