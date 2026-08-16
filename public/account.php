<?php
require_once __DIR__ . '/../src/App/bootstrap.php';
require_once __DIR__ . '/../src/Auth/AuthService.php';
require_once __DIR__ . '/../src/Repos/UsersRepo.php';
require_once __DIR__ . '/../src/Repos/UserFeatureAccessRepo.php';
require_once __DIR__ . '/../src/Repos/OrganizationResponsibilityRepo.php';

use App\Session;
use Auth\AuthService;
use I18n\I18n;
use Repos\UsersRepo;
use Repos\UserFeatureAccessRepo;
use Repos\OrganizationResponsibilityRepo;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

$usersRepo = new UsersRepo();
$freshUser = $usersRepo->findById((int)$user['id']);
if ($freshUser) {
    $user = array_merge($user, $freshUser);
}
$accessRepo = new UserFeatureAccessRepo();
$responsibilityRepo = new OrganizationResponsibilityRepo();
$departmentResponsibilities = $responsibilityRepo->getUserDepartmentResponsibilitiesMap()[(int)$user['id']] ?? [];
$voiceResponsibilities = $responsibilityRepo->getUserVoiceResponsibilitiesMap()[(int)$user['id']] ?? [];
$accessibleVoices = $accessRepo->getAccessibleVoices((int)$user['id']);
$accountJs = I18n::javascriptCatalogJson([
    'account.save_changes',
    'account.saving',
    'account.change_password',
    'account.changing_password',
    'account.password_mismatch',
    'account.password_updated',
    'account.error_loading_stats',
    'account.error_updating_profile',
    'account.language_save',
    'account.language_saving',
    'account.language_error',
    'common.error',
]);
?><!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::htmlLang()); ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars(I18n::translate('account.page_title')); ?></title>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" href="/assets/images/isotipo.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
  <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body class="claara-account-page bg-slate-50 text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php 
    $activeTab = 'account';
    $pageTitle = I18n::translate('account.title');
    include __DIR__ . '/includes/left-tabs.php'; 
    ?>

    <main class="flex-1 flex flex-col min-w-0">
      <?php include __DIR__ . '/includes/header-unified.php'; ?>

      <div class="flex-1 overflow-auto bg-slate-50 pb-16 lg:pb-0">
        <div class="max-w-4xl mx-auto p-4 lg:p-6">
          
          <!-- Profile -->
          <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6 mt-6">
            <div class="flex items-center justify-between mb-6">
              <div class="flex items-center gap-4">
                <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-[#B7C9F2] to-[#2F3440] flex items-center justify-center text-white text-2xl font-bold shadow-lg" id="avatar-big">
                  <?php 
                    $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
                    echo htmlspecialchars($initials);
                  ?>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-slate-800"><?php echo htmlspecialchars(I18n::translate('account.personal_information')); ?></h2>
                  <p class="text-slate-500 text-sm"><?php echo htmlspecialchars(I18n::translate('account.personal_information_help')); ?></p>
                </div>
              </div>
              <button id="edit-toggle-btn" class="text-sm text-[#B7C9F2] hover:text-[#2F3440] font-medium flex items-center gap-1">
                <i class="iconoir-edit-pencil"></i>
                <span><?php echo htmlspecialchars(I18n::translate('account.edit')); ?></span>
              </button>
            </div>
            
            <!-- Read-only view -->
            <div id="profile-view" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="text-xs font-medium text-slate-500 uppercase tracking-wider"><?php echo htmlspecialchars(I18n::translate('account.first_name')); ?></label>
          <div class="mt-1 text-slate-800 font-medium" id="display-first-name"><?php echo htmlspecialchars($user['first_name']); ?></div>
        </div>
        <div>
          <label class="text-xs font-medium text-slate-500 uppercase tracking-wider"><?php echo htmlspecialchars(I18n::translate('account.last_name')); ?></label>
          <div class="mt-1 text-slate-800 font-medium" id="display-last-name"><?php echo htmlspecialchars($user['last_name']); ?></div>
        </div>
        <div>
          <label class="text-xs font-medium text-slate-500 uppercase tracking-wider"><?php echo htmlspecialchars(I18n::translate('account.email')); ?></label>
          <div class="mt-1 text-slate-800 font-medium"><?php echo htmlspecialchars($user['email']); ?></div>
        </div>
        <div>
          <label class="text-xs font-medium text-slate-500 uppercase tracking-wider"><?php echo htmlspecialchars(I18n::translate('account.job_title')); ?></label>
          <div class="mt-1 text-slate-800 font-medium"><?php echo htmlspecialchars($user['job_title'] ?? I18n::translate('account.not_set')); ?></div>
        </div>
        <div>
          <label class="text-xs font-medium text-slate-500 uppercase tracking-wider"><?php echo htmlspecialchars(I18n::translate('account.department')); ?></label>
          <div class="mt-1 text-slate-800 font-medium"><?php echo htmlspecialchars($user['department_name'] ?? I18n::translate('account.unassigned')); ?></div>
        </div>
      </div>

      <div class="mt-6 pt-6 border-t border-slate-100 grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3"><?php echo htmlspecialchars(I18n::translate('account.department_responsibility')); ?></div>
          <?php if ($departmentResponsibilities): ?>
            <div class="flex flex-wrap gap-2">
              <?php foreach ($departmentResponsibilities as $department): ?>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-white border border-slate-200 text-xs font-medium text-slate-700"><?php echo htmlspecialchars($department['name']); ?></span>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-sm text-slate-500"><?php echo htmlspecialchars(I18n::translate('account.no_department_responsibility')); ?></p>
          <?php endif; ?>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3"><?php echo htmlspecialchars(I18n::translate('account.voice_responsibility')); ?></div>
          <?php if ($voiceResponsibilities): ?>
            <div class="flex flex-wrap gap-2">
              <?php foreach ($voiceResponsibilities as $voice): ?>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-white border border-slate-200 text-xs font-medium text-slate-700"><?php echo htmlspecialchars($voice['name'] ?: $voice['slug']); ?></span>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-sm text-slate-500"><?php echo htmlspecialchars(I18n::translate('account.no_voice_responsibility')); ?></p>
          <?php endif; ?>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3"><?php echo htmlspecialchars(I18n::translate('account.voice_access')); ?></div>
          <?php if ($accessibleVoices): ?>
            <div class="flex flex-wrap gap-2">
              <?php foreach ($accessibleVoices as $voice): ?>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-white border border-slate-200 text-xs font-medium text-slate-700"><?php echo htmlspecialchars($voice['name'] ?? $voice['feature_slug']); ?></span>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-sm text-slate-500"><?php echo htmlspecialchars(I18n::translate('account.no_voice_access')); ?></p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Edit form -->
      <form id="profile-edit-form" class="hidden space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="edit-first-name" class="text-xs font-medium text-slate-500 uppercase tracking-wider block mb-2"><?php echo htmlspecialchars(I18n::translate('account.first_name')); ?></label>
            <input type="text" id="edit-first-name" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#B7C9F2] focus:ring-2 focus:ring-[#B7C9F2]/20 transition-colors" required>
          </div>
          <div>
            <label for="edit-last-name" class="text-xs font-medium text-slate-500 uppercase tracking-wider block mb-2"><?php echo htmlspecialchars(I18n::translate('account.last_name')); ?></label>
            <input type="text" id="edit-last-name" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#B7C9F2] focus:ring-2 focus:ring-[#B7C9F2]/20 transition-colors" required>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500 uppercase tracking-wider block mb-2"><?php echo htmlspecialchars(I18n::translate('account.email')); ?></label>
            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" disabled>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500 uppercase tracking-wider block mb-2"><?php echo htmlspecialchars(I18n::translate('account.department')); ?></label>
            <input type="text" value="<?php echo htmlspecialchars($user['department_name'] ?? I18n::translate('account.unassigned')); ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" disabled>
          </div>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 flex items-start gap-3">
          <i class="iconoir-info-circle text-blue-600 text-lg flex-shrink-0 mt-0.5"></i>
          <p class="text-sm text-blue-800">
            <?php echo htmlspecialchars(I18n::translate('account.admin_help')); ?>
          </p>
        </div>
        <div class="flex gap-3 pt-2">
          <button type="submit" class="px-4 py-2 bg-gradient-to-r from-[#B7C9F2] to-[#2F3440] text-white rounded-lg font-medium hover:opacity-90 transition-all text-sm shadow-md">
            <?php echo htmlspecialchars(I18n::translate('account.save_changes')); ?>
          </button>
          <button type="button" id="cancel-edit-btn" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors text-sm">
            <?php echo htmlspecialchars(I18n::translate('common.cancel')); ?>
          </button>
        </div>
      </form>
    </div>

    <!-- Security -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6">
      <h2 class="text-lg font-semibold text-slate-800 mb-6"><?php echo htmlspecialchars(I18n::translate('account.security')); ?></h2>
      
      <div class="flex items-center justify-between py-3">
        <div>
          <div class="font-medium text-slate-800"><?php echo htmlspecialchars(I18n::translate('account.password')); ?></div>
          <div class="text-sm text-slate-500 mt-0.5">••••••••</div>
        </div>
        <button id="change-password-btn" class="px-4 py-2 text-sm font-medium text-slate-700 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
          <?php echo htmlspecialchars(I18n::translate('account.change_password')); ?>
        </button>
      </div>
    </div>

    <!-- Language -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 class="text-lg font-semibold text-slate-800"><?php echo htmlspecialchars(I18n::translate('account.language')); ?></h2>
          <p class="text-sm text-slate-500 mt-1"><?php echo htmlspecialchars(I18n::translate('account.language_help')); ?></p>
        </div>
        <form id="language-form" class="flex flex-col sm:flex-row gap-2 sm:items-center shrink-0">
          <label for="language-select" class="sr-only"><?php echo htmlspecialchars(I18n::translate('account.language')); ?></label>
          <select id="language-select" class="min-w-52 px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm text-slate-700 focus:outline-none focus:border-[#B7C9F2] focus:ring-2 focus:ring-[#B7C9F2]/20">
            <?php
              $instanceDefaultLocale = \Instances\InstanceContext::current()->defaultLocale();
              $languageLabels = [
                'en' => I18n::translate('account.language_en'),
                'es' => I18n::translate('account.language_es'),
              ];
              $defaultLanguageName = $languageLabels[$instanceDefaultLocale] ?? strtoupper($instanceDefaultLocale);
              $selectedLocale = $user['locale'] ?? '';
            ?>
            <option value="" <?php echo $selectedLocale === '' ? 'selected' : ''; ?>><?php echo htmlspecialchars(I18n::translate('account.language_default', ['language' => $defaultLanguageName])); ?></option>
            <?php foreach (\Instances\InstanceContext::current()->allowedLocales() as $localeOption): ?>
              <option value="<?php echo htmlspecialchars($localeOption); ?>" <?php echo $selectedLocale === $localeOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($languageLabels[$localeOption] ?? strtoupper($localeOption)); ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-lg font-medium hover:opacity-90 transition-all text-sm whitespace-nowrap">
            <?php echo htmlspecialchars(I18n::translate('account.language_save')); ?>
          </button>
        </form>
      </div>
      <div id="language-error" class="hidden mt-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>
    </div>

    <!-- Activity -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
      <h2 class="text-lg font-semibold text-slate-800 mb-6"><?php echo htmlspecialchars(I18n::translate('account.recent_activity')); ?></h2>
      
      <div id="activity-loading" class="text-center py-8">
        <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-[#B7C9F2] border-r-transparent"></div>
        <p class="text-sm text-slate-500 mt-3"><?php echo htmlspecialchars(I18n::translate('account.loading_stats')); ?></p>
      </div>

      <div id="activity-content" class="hidden space-y-4">
        <div class="flex items-start gap-3 py-3 border-b border-slate-100 last:border-0">
          <div class="h-10 w-10 rounded-lg bg-[#B7C9F2]/10 flex items-center justify-center flex-shrink-0">
            <i class="iconoir-chat-bubble text-[#B7C9F2]"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-slate-800"><?php echo htmlspecialchars(I18n::translate('account.conversations_created')); ?></div>
            <div class="text-sm text-slate-500 mt-0.5">
              <span id="stats-conversations-week" class="font-semibold text-slate-800">0</span> <?php echo htmlspecialchars(I18n::translate('account.this_week')); ?> ·
              <span id="stats-conversations-total" class="text-slate-600">0</span> <?php echo htmlspecialchars(I18n::translate('account.total')); ?>
            </div>
          </div>
        </div>

        <div class="flex items-start gap-3 py-3 border-b border-slate-100 last:border-0">
          <div class="h-10 w-10 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
            <i class="iconoir-message-text text-indigo-600"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-slate-800"><?php echo htmlspecialchars(I18n::translate('account.messages_sent')); ?></div>
            <div class="text-sm text-slate-500 mt-0.5">
              <span id="stats-messages-week" class="font-semibold text-slate-800">0</span> <?php echo htmlspecialchars(I18n::translate('account.this_week')); ?> ·
              <span id="stats-messages-total" class="text-slate-600">0</span> <?php echo htmlspecialchars(I18n::translate('account.total')); ?>
            </div>
          </div>
        </div>

        <div class="flex items-start gap-3 py-3 border-b border-slate-100 last:border-0">
          <div class="h-10 w-10 rounded-lg bg-emerald-50 flex items-center justify-center flex-shrink-0">
            <i class="iconoir-clock text-emerald-600"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-slate-800"><?php echo htmlspecialchars(I18n::translate('account.last_login')); ?></div>
            <div class="text-sm text-slate-500 mt-0.5">
              <?php 
                if ($user['last_login_at']) {
                  $date = new DateTime($user['last_login_at']);
                  echo $date->format('d/m/Y H:i');
                } else {
                  echo htmlspecialchars(I18n::translate('account.first_session'));
                }
              ?>
            </div>
          </div>
        </div>

        <div class="flex items-start gap-3 py-3">
          <div class="h-10 w-10 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <i class="iconoir-calendar text-blue-600"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-slate-800"><?php echo htmlspecialchars(I18n::translate('account.created')); ?></div>
            <div class="text-sm text-slate-500 mt-0.5">
              <?php 
                $created = new DateTime($user['created_at']);
                echo $created->format('d/m/Y');
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="mt-8 text-center text-sm text-slate-500">
      <p><?php echo htmlspecialchars(I18n::translate('account.footer', ['year' => date('Y')])); ?></p>
    </div>
  </div>
</div>
</div>
</div>

</main>
</div>

  <!-- Change password modal -->
  <div id="password-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-slate-800"><?php echo htmlspecialchars(I18n::translate('account.change_password')); ?></h3>
        <button id="close-modal-btn" class="p-1 text-slate-400 hover:text-slate-600 transition-colors">
          <i class="iconoir-xmark text-xl"></i>
        </button>
      </div>

      <form id="password-form" class="space-y-4">
        <div>
          <label for="current-password" class="text-sm font-medium text-slate-700 block mb-2"><?php echo htmlspecialchars(I18n::translate('account.current_password')); ?></label>
          <input type="password" id="current-password" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#B7C9F2] focus:ring-2 focus:ring-[#B7C9F2]/20 transition-colors" required>
        </div>

        <div>
          <label for="new-password" class="text-sm font-medium text-slate-700 block mb-2"><?php echo htmlspecialchars(I18n::translate('account.new_password')); ?></label>
          <input type="password" id="new-password" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#B7C9F2] focus:ring-2 focus:ring-[#B7C9F2]/20 transition-colors" required minlength="8">
          <p class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars(I18n::translate('account.password_minimum')); ?></p>
        </div>

        <div>
          <label for="confirm-password" class="text-sm font-medium text-slate-700 block mb-2"><?php echo htmlspecialchars(I18n::translate('account.confirm_password')); ?></label>
          <input type="password" id="confirm-password" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#B7C9F2] focus:ring-2 focus:ring-[#B7C9F2]/20 transition-colors" required>
        </div>

        <div id="password-error" class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>
        <div id="password-success" class="hidden text-sm text-green-600 bg-green-50 border border-green-200 rounded-lg px-3 py-2"></div>

        <div class="flex gap-3 pt-2">
          <button type="submit" class="flex-1 px-4 py-2 bg-gradient-to-r from-[#B7C9F2] to-[#2F3440] text-white rounded-lg font-medium hover:opacity-90 transition-all text-sm shadow-md">
            <?php echo htmlspecialchars(I18n::translate('account.change_password')); ?>
          </button>
          <button type="button" id="cancel-password-btn" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors text-sm">
            <?php echo htmlspecialchars(I18n::translate('common.cancel')); ?>
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const accountI18n = <?php echo $accountJs; ?>;
    const accountT = (key, parameters = {}) => {
      let message = accountI18n.messages[key] || key;
      Object.entries(parameters).forEach(([name, value]) => {
        message = message.split(`{${name}}`).join(String(value));
      });
      return message;
    };
    const csrf = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
    
    // API helper
    async function api(path, opts = {}) {
      const res = await fetch(path, {
        method: opts.method || 'GET',
        headers: {
          'Content-Type': 'application/json',
          ...(csrf ? { 'X-CSRF-Token': csrf } : {})
        },
        body: opts.body ? JSON.stringify(opts.body) : undefined,
        credentials: 'include'
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data?.error?.message || accountT('common.error'));
      return data;
    }

    const languageForm = document.getElementById('language-form');
    const languageSelect = document.getElementById('language-select');
    const languageError = document.getElementById('language-error');
    languageForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const submitButton = languageForm.querySelector('button[type="submit"]');
      submitButton.disabled = true;
      submitButton.textContent = accountT('account.language_saving');
      languageError.classList.add('hidden');
      try {
        await api('/api/account/update_locale.php', {
          method: 'POST',
          body: { locale: languageSelect.value }
        });
        window.location.reload();
      } catch (error) {
        languageError.textContent = error.message || accountT('account.language_error');
        languageError.classList.remove('hidden');
        submitButton.disabled = false;
        submitButton.textContent = accountT('account.language_save');
      }
    });

    // Load stats
    async function loadActivity() {
      try {
        const stats = await api('/api/account/activity.php');
        document.getElementById('stats-conversations-week').textContent = stats.conversations_this_week;
        document.getElementById('stats-conversations-total').textContent = stats.total_conversations;
        document.getElementById('stats-messages-week').textContent = stats.messages_this_week;
        document.getElementById('stats-messages-total').textContent = stats.total_messages;
        
        document.getElementById('activity-loading').classList.add('hidden');
        document.getElementById('activity-content').classList.remove('hidden');
      } catch (err) {
        const errorMessage = document.createElement('p');
        errorMessage.className = 'text-sm text-red-600';
        errorMessage.textContent = accountT('account.error_loading_stats');
        document.getElementById('activity-loading').replaceChildren(errorMessage);
      }
    }

    // Edit profile
    const profileView = document.getElementById('profile-view');
    const profileForm = document.getElementById('profile-edit-form');
    const editToggleBtn = document.getElementById('edit-toggle-btn');
    const cancelEditBtn = document.getElementById('cancel-edit-btn');
    const editFirstName = document.getElementById('edit-first-name');
    const editLastName = document.getElementById('edit-last-name');
    const displayFirstName = document.getElementById('display-first-name');
    const displayLastName = document.getElementById('display-last-name');
    const avatarBig = document.getElementById('avatar-big');

    editToggleBtn.addEventListener('click', () => {
      profileView.classList.add('hidden');
      profileForm.classList.remove('hidden');
      editFirstName.value = displayFirstName.textContent.trim();
      editLastName.value = displayLastName.textContent.trim();
      editFirstName.focus();
    });

    cancelEditBtn.addEventListener('click', () => {
      profileView.classList.remove('hidden');
      profileForm.classList.add('hidden');
    });

    profileForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = profileForm.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.textContent = accountT('account.saving');

      try {
        const data = await api('/api/account/update_profile.php', {
          method: 'POST',
          body: {
            first_name: editFirstName.value.trim(),
            last_name: editLastName.value.trim()
          }
        });

        displayFirstName.textContent = data.user.first_name;
        displayLastName.textContent = data.user.last_name;
        
        // Update avatar
        const initials = data.user.first_name[0].toUpperCase() + data.user.last_name[0].toUpperCase();
        avatarBig.textContent = initials;

        profileView.classList.remove('hidden');
        profileForm.classList.add('hidden');
      } catch (err) {
        alert(accountT('account.error_updating_profile', { message: err.message }));
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = accountT('account.save_changes');
      }
    });

    // Change password modal
    const passwordModal = document.getElementById('password-modal');
    const changePasswordBtn = document.getElementById('change-password-btn');
    const closeModalBtn = document.getElementById('close-modal-btn');
    const cancelPasswordBtn = document.getElementById('cancel-password-btn');
    const passwordForm = document.getElementById('password-form');
    const passwordError = document.getElementById('password-error');
    const passwordSuccess = document.getElementById('password-success');

    changePasswordBtn.addEventListener('click', () => {
      passwordModal.classList.remove('hidden');
      document.getElementById('current-password').focus();
    });

    [closeModalBtn, cancelPasswordBtn].forEach(btn => {
      btn.addEventListener('click', () => {
        passwordModal.classList.add('hidden');
        passwordForm.reset();
        passwordError.classList.add('hidden');
        passwordSuccess.classList.add('hidden');
      });
    });

    passwordForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      passwordError.classList.add('hidden');
      passwordSuccess.classList.add('hidden');

      const current = document.getElementById('current-password').value;
      const newPass = document.getElementById('new-password').value;
      const confirm = document.getElementById('confirm-password').value;

      if (newPass !== confirm) {
        passwordError.textContent = accountT('account.password_mismatch');
        passwordError.classList.remove('hidden');
        return;
      }

      const submitBtn = passwordForm.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.textContent = accountT('account.changing_password');

      try {
        await api('/api/account/change_password.php', {
          method: 'POST',
          body: {
            current_password: current,
            new_password: newPass,
            confirm_password: confirm
          }
        });

        passwordSuccess.textContent = accountT('account.password_updated');
        passwordSuccess.classList.remove('hidden');
        passwordForm.reset();

        setTimeout(() => {
          passwordModal.classList.add('hidden');
          passwordSuccess.classList.add('hidden');
        }, 2000);
      } catch (err) {
        passwordError.textContent = err.message;
        passwordError.classList.remove('hidden');
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = accountT('account.change_password');
      }
    });

    // Close modal when clicking outside
    passwordModal.addEventListener('click', (e) => {
      if (e.target === passwordModal) {
        passwordModal.classList.add('hidden');
        passwordForm.reset();
        passwordError.classList.add('hidden');
        passwordSuccess.classList.add('hidden');
      }
    });

    // Load activity on start
    loadActivity();
  </script>
  
  <!-- Bottom Navigation (mobile) -->
  <?php include __DIR__ . '/includes/bottom-nav.php'; ?>
</body>
</html>
