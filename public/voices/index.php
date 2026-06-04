<?php
require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Repos/UserFeatureAccessRepo.php';
require_once __DIR__ . '/../../src/Repos/VoicesRepo.php';

use App\Session;
use Repos\UserFeatureAccessRepo;
use Repos\VoicesRepo;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
$activeTab = 'voices';
$headerBackUrl = '/app/';
$headerBackText = 'Home';
$headerTitle = 'Voices';
$headerSubtitle = 'Specialized RAG assistants';
$headerIcon = 'iconoir-voice-square';
$headerIconColor = 'from-slate-700 to-cyan-700';

$voicesRepo = new VoicesRepo();
$accessRepo = new UserFeatureAccessRepo();
$voices = array_values(array_filter(
    $voicesRepo->listPublished(),
    static fn(array $voice): bool => $accessRepo->hasVoiceAccess((int)$user['id'], $voice['slug'])
));
?><!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="bg-slate-50 text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>

    <main class="flex-1 flex flex-col overflow-hidden">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <div class="flex-1 overflow-auto pb-20 lg:pb-8">
        <div class="voices-catalog-shell">
          <section class="voices-catalog-hero">
            <div>
              <p class="voices-catalog-kicker">Company assistants</p>
              <h1>Voices</h1>
              <p>Each voice is a focused RAG assistant with its own instructions, knowledge base, and access permissions.</p>
            </div>
            <?php if (!empty($user['is_superadmin'])): ?>
              <a class="voices-catalog-admin-link" href="/admin/voices.php">
                <i class="iconoir-settings"></i>
                <span>Manage voices</span>
              </a>
            <?php endif; ?>
          </section>

          <?php if (!$voices): ?>
            <section class="voices-catalog-empty">
              <i class="iconoir-voice-square"></i>
              <h2>No voices available</h2>
              <p>Your account does not have access to any published voice yet.</p>
            </section>
          <?php else: ?>
            <section class="voices-catalog-grid">
              <?php foreach ($voices as $index => $voice): ?>
                <?php
                  $initial = strtoupper(substr($voice['name'] ?: $voice['slug'], 0, 1));
                  $href = $voice['slug'] === 'lex' ? '/voices/lex.php' : '/voices/view.php?voice=' . urlencode($voice['slug']);
                ?>
                <a class="voices-catalog-card" href="<?php echo htmlspecialchars($href); ?>">
                  <div class="voices-catalog-card-head">
                    <span class="voices-catalog-avatar" data-color="<?php echo htmlspecialchars($voice['color'] ?? 'slate'); ?>"><?php echo htmlspecialchars($initial); ?></span>
                    <span class="voices-catalog-state">Published</span>
                  </div>
                  <h2><?php echo htmlspecialchars($voice['name']); ?></h2>
                  <p class="voices-catalog-role"><?php echo htmlspecialchars($voice['role'] ?: 'Specialized assistant'); ?></p>
                  <p class="voices-catalog-description"><?php echo htmlspecialchars($voice['description'] ?: 'Answers with its own indexed knowledge base.'); ?></p>
                  <div class="voices-catalog-foot">
                    <span><i class="iconoir-database"></i> RAG voice</span>
                    <span>Open <i class="iconoir-arrow-right"></i></span>
                  </div>
                </a>
              <?php endforeach; ?>
            </section>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
</body>
</html>
