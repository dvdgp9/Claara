<?php
require_once __DIR__ . '/../src/App/bootstrap.php';
require_once __DIR__ . '/../src/Auth/AuthService.php';

use App\Session;
use Auth\AuthService;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My account — iaiaPRO</title>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" href="/assets/images/isotipo.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
  <style>
    /* Base layout styles */
    .gradient-brand { background: linear-gradient(135deg, #23AAC5 0%, #115c6c 100%); }
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
  </style>
</head>
<body class="bg-slate-50 text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php 
    $activeTab = 'account';
    $pageTitle = 'My Account';
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
                <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-[#23AAC5] to-[#115c6c] flex items-center justify-center text-white text-2xl font-bold shadow-lg" id="avatar-big">
                  <?php 
                    $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
                    echo htmlspecialchars($initials);
                  ?>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-slate-800">Personal information</h2>
                  <p class="text-slate-500 text-sm">Manage your details and preferences</p>
                </div>
              </div>
              <button id="edit-toggle-btn" class="text-sm text-[#23AAC5] hover:text-[#115c6c] font-medium flex items-center gap-1">
                <i class="iconoir-edit-pencil"></i>
                <span>Edit</span>
              </button>
            </div>
            
            <!-- Read-only view -->
            <div id="profile-view" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">First name</label>
          <div class="mt-1 text-slate-800 font-medium" id="display-first-name"><?php echo htmlspecialchars($user['first_name']); ?></div>
        </div>
        <div>
          <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Last name</label>
          <div class="mt-1 text-slate-800 font-medium" id="display-last-name"><?php echo htmlspecialchars($user['last_name']); ?></div>
        </div>
        <div>
          <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Email</label>
          <div class="mt-1 text-slate-800 font-medium"><?php echo htmlspecialchars($user['email']); ?></div>
        </div>
        <div>
          <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Department</label>
          <div class="mt-1 text-slate-800 font-medium"><?php echo htmlspecialchars($user['department_name'] ?? 'Unassigned'); ?></div>
        </div>
      </div>

      <!-- Edit form -->
      <form id="profile-edit-form" class="hidden space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-xs font-medium text-slate-500 uppercase tracking-wider block mb-2">First name</label>
            <input type="text" id="edit-first-name" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors" required>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500 uppercase tracking-wider block mb-2">Last name</label>
            <input type="text" id="edit-last-name" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors" required>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500 uppercase tracking-wider block mb-2">Email</label>
            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" disabled>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500 uppercase tracking-wider block mb-2">Department</label>
            <input type="text" value="<?php echo htmlspecialchars($user['department_name'] ?? 'Unassigned'); ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 cursor-not-allowed" disabled>
          </div>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 flex items-start gap-3">
          <i class="iconoir-info-circle text-blue-600 text-lg flex-shrink-0 mt-0.5"></i>
          <p class="text-sm text-blue-800">
            If you need to change your email or department, contact your workspace administrator.
          </p>
        </div>
        <div class="flex gap-3 pt-2">
          <button type="submit" class="px-4 py-2 bg-gradient-to-r from-[#23AAC5] to-[#115c6c] text-white rounded-lg font-medium hover:opacity-90 transition-all text-sm shadow-md">
            Save changes
          </button>
          <button type="button" id="cancel-edit-btn" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors text-sm">
            Cancel
          </button>
        </div>
      </form>
    </div>

    <!-- Security -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6">
      <h2 class="text-lg font-semibold text-slate-800 mb-6">Security</h2>
      
      <div class="flex items-center justify-between py-3">
        <div>
          <div class="font-medium text-slate-800">Password</div>
          <div class="text-sm text-slate-500 mt-0.5">••••••••</div>
        </div>
        <button id="change-password-btn" class="px-4 py-2 text-sm font-medium text-slate-700 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
          Change password
        </button>
      </div>
    </div>

    <!-- Activity -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
      <h2 class="text-lg font-semibold text-slate-800 mb-6">Recent activity</h2>
      
      <div id="activity-loading" class="text-center py-8">
        <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-[#23AAC5] border-r-transparent"></div>
        <p class="text-sm text-slate-500 mt-3">Loading stats...</p>
      </div>

      <div id="activity-content" class="hidden space-y-4">
        <div class="flex items-start gap-3 py-3 border-b border-slate-100 last:border-0">
          <div class="h-10 w-10 rounded-lg bg-[#23AAC5]/10 flex items-center justify-center flex-shrink-0">
            <i class="iconoir-chat-bubble text-[#23AAC5]"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-slate-800">Conversations created</div>
            <div class="text-sm text-slate-500 mt-0.5">
              <span id="stats-conversations-week" class="font-semibold text-slate-800">0</span> this week · 
              <span id="stats-conversations-total" class="text-slate-600">0</span> total
            </div>
          </div>
        </div>

        <div class="flex items-start gap-3 py-3 border-b border-slate-100 last:border-0">
          <div class="h-10 w-10 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
            <i class="iconoir-message-text text-indigo-600"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-slate-800">Messages sent</div>
            <div class="text-sm text-slate-500 mt-0.5">
              <span id="stats-messages-week" class="font-semibold text-slate-800">0</span> this week · 
              <span id="stats-messages-total" class="text-slate-600">0</span> total
            </div>
          </div>
        </div>

        <div class="flex items-start gap-3 py-3 border-b border-slate-100 last:border-0">
          <div class="h-10 w-10 rounded-lg bg-emerald-50 flex items-center justify-center flex-shrink-0">
            <i class="iconoir-clock text-emerald-600"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-slate-800">Last login</div>
            <div class="text-sm text-slate-500 mt-0.5">
              <?php 
                if ($user['last_login_at']) {
                  $date = new DateTime($user['last_login_at']);
                  echo $date->format('d/m/Y H:i');
                } else {
                  echo 'First session';
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
            <div class="text-sm font-medium text-slate-800">Account created</div>
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
      <p>© 2025 iaiaPRO. All rights reserved.</p>
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
        <h3 class="text-lg font-semibold text-slate-800">Change password</h3>
        <button id="close-modal-btn" class="p-1 text-slate-400 hover:text-slate-600 transition-colors">
          <i class="iconoir-xmark text-xl"></i>
        </button>
      </div>

      <form id="password-form" class="space-y-4">
        <div>
          <label class="text-sm font-medium text-slate-700 block mb-2">Current password</label>
          <input type="password" id="current-password" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors" required>
        </div>

        <div>
          <label class="text-sm font-medium text-slate-700 block mb-2">New password</label>
          <input type="password" id="new-password" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors" required minlength="8">
          <p class="text-xs text-slate-500 mt-1">Minimum 8 characters</p>
        </div>

        <div>
          <label class="text-sm font-medium text-slate-700 block mb-2">Confirm password</label>
          <input type="password" id="confirm-password" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors" required>
        </div>

        <div id="password-error" class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>
        <div id="password-success" class="hidden text-sm text-green-600 bg-green-50 border border-green-200 rounded-lg px-3 py-2"></div>

        <div class="flex gap-3 pt-2">
          <button type="submit" class="flex-1 px-4 py-2 bg-gradient-to-r from-[#23AAC5] to-[#115c6c] text-white rounded-lg font-medium hover:opacity-90 transition-all text-sm shadow-md">
            Change password
          </button>
          <button type="button" id="cancel-password-btn" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors text-sm">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
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
      if (!res.ok) throw new Error(data?.error?.message || res.statusText);
      return data;
    }

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
        document.getElementById('activity-loading').innerHTML = '<p class="text-sm text-red-600">Error loading stats</p>';
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
      submitBtn.textContent = 'Saving...';

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
        alert('Error updating profile: ' + err.message);
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save changes';
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
        passwordError.textContent = 'Passwords do not match';
        passwordError.classList.remove('hidden');
        return;
      }

      const submitBtn = passwordForm.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Changing...';

      try {
        await api('/api/account/change_password.php', {
          method: 'POST',
          body: {
            current_password: current,
            new_password: newPass,
            confirm_password: confirm
          }
        });

        passwordSuccess.textContent = 'Password updated successfully';
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
        submitBtn.textContent = 'Change password';
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
