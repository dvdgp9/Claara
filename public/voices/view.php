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

$slug = strtolower(trim((string)($_GET['voice'] ?? '')));
if ($slug === '' || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug)) {
    header('Location: /voices/');
    exit;
}

$voicesRepo = new VoicesRepo();
$voice = $voicesRepo->findBySlug($slug);
if (!$voice || $voice['status'] !== 'published') {
    header('Location: /voices/');
    exit;
}

$voiceResolver = new \Voices\VoiceAccessResolver();
if (!$voiceResolver->hasVoiceAccess((int)$user['id'], $voice)) {
    header('Location: /app/?error=no_access');
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
$activeTab = 'voices';
$userName = htmlspecialchars($user['first_name'] ?? 'there');
$initial = strtoupper(substr($voice['name'] ?: $voice['slug'], 0, 1));
$headerBackUrl = '/voices/';
$headerBackText = 'Voices';
$headerTitle = $voice['name'];
$headerSubtitle = $voice['role'] ?: 'Specialized RAG assistant';
$headerIconText = $initial;
$headerIconColor = 'from-slate-700 to-cyan-700';
$headerCustomButtons = '
  <button id="toggle-docs-panel" class="hidden lg:flex items-center gap-2 px-3 py-1.5 text-sm text-slate-600 hover:text-cyan-700 hover:bg-cyan-50 rounded-lg transition-smooth">
    <i class="iconoir-folder"></i>
    <span>Documents</span>
    <i class="iconoir-nav-arrow-right text-xs" id="docs-arrow"></i>
  </button>';
?><!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="bg-mesh text-slate-900 overflow-hidden">
  <script>
    window.CLAARA_VOICE = <?php echo json_encode([
        'slug' => $voice['slug'],
        'name' => $voice['name'],
        'role' => $voice['role'],
        'description' => $voice['description'],
        'initial' => $initial,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  </script>
  <div class="min-h-screen flex h-screen">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>
    <main class="flex-1 flex flex-col overflow-hidden min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>
      <div class="flex-1 flex overflow-hidden pb-16 lg:pb-0">
        <div class="flex-1 flex flex-col bg-mesh min-w-0 overflow-hidden">
          <div id="messages-container" class="flex-1 overflow-auto p-4 lg:p-6 pb-[140px] lg:pb-0">
            <div id="empty-state" class="h-full flex items-center justify-center">
              <div class="text-center max-w-lg">
                <div class="w-20 h-20 rounded-3xl bg-slate-900 flex items-center justify-center mx-auto mb-6 shadow-xl animate-float">
                  <span class="text-4xl font-bold text-white"><?php echo htmlspecialchars($initial); ?></span>
                </div>
                <h2 class="text-2xl font-bold text-slate-900 mb-3">Hi, <?php echo $userName; ?></h2>
                <p class="text-slate-600 mb-6">I am <strong><?php echo htmlspecialchars($voice['name']); ?></strong>. <?php echo htmlspecialchars($voice['description'] ?: 'Ask me about my indexed knowledge base.'); ?></p>
                <div class="space-y-2">
                  <button class="suggestion-btn w-full p-3 glass border border-slate-200/50 hover:border-cyan-300 rounded-xl text-left transition-smooth group">
                    <span class="text-sm text-slate-700 group-hover:text-cyan-700">What documents do you have available?</span>
                  </button>
                  <button class="suggestion-btn w-full p-3 glass border border-slate-200/50 hover:border-cyan-300 rounded-xl text-left transition-smooth group">
                    <span class="text-sm text-slate-700 group-hover:text-cyan-700">Summarize the most important rules in your knowledge base.</span>
                  </button>
                </div>
              </div>
            </div>
            <div id="messages" class="hidden space-y-6 max-w-4xl mx-auto"></div>
            <div id="typing-indicator" class="hidden max-w-4xl mx-auto">
              <div class="glass border border-slate-200/50 px-5 py-3.5 rounded-2xl shadow-sm inline-flex gap-1.5">
                <div class="w-2 h-2 bg-cyan-500 rounded-full animate-bounce"></div>
                <div class="w-2 h-2 bg-cyan-500 rounded-full animate-bounce"></div>
                <div class="w-2 h-2 bg-cyan-500 rounded-full animate-bounce"></div>
              </div>
            </div>
          </div>
          <footer class="fixed lg:relative bottom-16 lg:bottom-0 left-0 right-0 p-3 lg:p-6 bg-white border-t border-slate-200 shadow-lg z-40">
            <form id="chat-form" class="max-w-4xl mx-auto">
              <div class="flex gap-2 lg:gap-3 items-center">
                <textarea id="chat-input" rows="1" class="voice-chat-input flex-1 min-w-0 border-2 border-slate-200 rounded-xl px-3 lg:px-4 py-2.5 input-focus transition-smooth bg-white/80 resize-none overflow-hidden" placeholder="Ask <?php echo htmlspecialchars($voice['name']); ?>..."></textarea>
                <button type="submit" class="h-11 p-3 lg:px-6 lg:py-[10px] bg-slate-900 text-white rounded-xl font-medium shadow-md hover:shadow-lg hover:opacity-90 transition-all duration-200 flex items-center justify-center gap-2">
                  <span class="hidden lg:inline">Send</span>
                  <i class="iconoir-send-diagonal text-base"></i>
                </button>
              </div>
            </form>
          </footer>
        </div>
        <aside id="docs-panel" class="hidden w-80 glass-strong border-l border-slate-200/50 flex flex-col shrink-0">
          <div class="p-4 border-b border-slate-200/50">
            <h3 class="font-semibold text-slate-800 flex items-center gap-2"><i class="iconoir-folder text-cyan-700"></i> Documents</h3>
          </div>
          <div id="docs-list" class="flex-1 overflow-auto p-4 space-y-2"></div>
        </aside>
      </div>
    </main>
  </div>
  <div id="history-list" class="hidden"></div>
  <div id="doc-viewer-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="glass-strong rounded-3xl shadow-2xl w-full max-w-4xl max-h-[85vh] flex flex-col border border-slate-200/50">
      <div class="p-5 border-b border-slate-200/50 flex items-center justify-between">
        <h3 id="doc-viewer-title" class="text-lg font-semibold text-slate-900">Document</h3>
        <button id="close-doc-viewer" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-smooth"><i class="iconoir-xmark text-xl"></i></button>
      </div>
      <div id="doc-viewer-content" class="flex-1 overflow-y-auto p-6"></div>
      <button id="close-doc-viewer-btn" class="hidden" type="button"></button>
    </div>
  </div>
  <script src="/assets/js/voice-lex.js"></script>
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
</body>
</html>
