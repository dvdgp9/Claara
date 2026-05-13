<?php
/**
 * Shared header for all pages
 * 
 * Optional variables:
 * - $headerBackUrl: back navigation URL
 * - $headerBackText: back button text
 * - $headerTitle: page title
 * - $headerSubtitle: optional subtitle
 * - $headerIcon: icon class
 * - $headerIconColor: icon gradient classes
 * - $headerIconText: text instead of icon
 * - $headerCustomButtons: custom HTML before profile actions
 * - $headerShowConvTitle: if true, shows the dynamic conversation title
 * - $headerShowSearch: if true, shows the search button
 * - $headerShowFaq: if true, shows the Nana quick answers button
 * - $headerDrawerId: mobile drawer ID opened by hamburger
 * - $headerShowLogo: if true, shows the logo on mobile
 */

$headerBackUrl = $headerBackUrl ?? null;
$headerBackText = $headerBackText ?? 'Back';
$headerTitle = $headerTitle ?? ($pageTitle ?? '');
$headerSubtitle = $headerSubtitle ?? null;
$headerIcon = $headerIcon ?? null;
$headerIconColor = $headerIconColor ?? 'from-cyan-500 to-teal-600';
$headerIconText = $headerIconText ?? null;
$headerCustomButtons = $headerCustomButtons ?? '';
$headerShowConvTitle = $headerShowConvTitle ?? false;
$headerShowSearch = $headerShowSearch ?? false;
$headerShowFaq = $headerShowFaq ?? false;
$headerDrawerId = $headerDrawerId ?? null;
$headerShowLogo = $headerShowLogo ?? false;

// Determine header style from context.
$headerStyle = 'h-14 lg:h-[60px] px-4 lg:px-6 border-b border-slate-200';
if (isset($activeTab) && in_array($activeTab, ['gestures', 'voices'])) {
    $headerStyle .= '/50 glass-strong';
} else {
    $headerStyle .= ' bg-white/95 backdrop-blur-sm';
}
// Keep the header sticky while scrolling.
$headerStyle .= ' flex items-center justify-between shadow-sm shrink-0 sticky top-0 z-30';
?>
<header class="<?php echo $headerStyle; ?>">
  <!-- Navigation and title -->
  <div class="flex items-center gap-2 lg:gap-3 min-w-0 flex-1">
    <?php if ($headerDrawerId): ?>
      <!-- Hamburger button (mobile only) -->
      <button onclick="openMobileDrawer('<?php echo htmlspecialchars($headerDrawerId); ?>')" 
              class="lg:hidden p-2 -ml-2 text-slate-600 hover:text-[#23AAC5] hover:bg-slate-50 rounded-lg transition-colors tap-highlight-none">
        <i class="iconoir-menu text-xl"></i>
      </button>
    <?php endif; ?>
    
    <?php if ($headerShowLogo): ?>
      <!-- Logo (mobile) -->
      <img src="/assets/images/logo.png" alt="iaiaPRO" class="h-7 lg:hidden">
    <?php endif; ?>
    
    <?php if ($headerShowConvTitle): ?>
      <!-- Dynamic conversation title (chat) -->
      <div id="conv-title" class="hidden flex items-center gap-2 min-w-0">
        <i class="iconoir-chat-bubble text-[#23AAC5] hidden lg:block"></i>
        <span class="text-sm font-medium leading-tight text-slate-700 truncate max-w-[60vw] lg:max-w-md"></span>
      </div>
    <?php else: ?>
      <?php if ($headerBackUrl): ?>
        <!-- Back navigation (desktop only) -->
        <a href="<?php echo htmlspecialchars($headerBackUrl); ?>" class="hidden lg:flex items-center gap-2 text-slate-600 hover:text-cyan-600 transition-smooth">
          <i class="iconoir-arrow-left text-lg"></i>
          <span class="text-sm font-medium"><?php echo htmlspecialchars($headerBackText); ?></span>
        </a>
        <div class="hidden lg:block h-6 w-px bg-slate-200"></div>
      <?php endif; ?>
      
      <?php if ($headerTitle): ?>
        <!-- Page title with icon -->
        <div class="flex items-center gap-2">
          <?php if ($headerIcon || $headerIconText): ?>
            <div class="w-7 h-7 lg:w-8 lg:h-8 rounded-lg bg-gradient-to-br <?php echo htmlspecialchars($headerIconColor); ?> flex items-center justify-center text-white shadow-md">
              <?php if ($headerIconText): ?>
                <span class="font-bold text-xs lg:text-sm"><?php echo htmlspecialchars($headerIconText); ?></span>
              <?php else: ?>
                <i class="<?php echo htmlspecialchars($headerIcon); ?> text-xs lg:text-sm"></i>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <div class="min-w-0">
            <span class="font-semibold text-slate-800 text-sm lg:text-base leading-tight truncate block"><?php echo htmlspecialchars($headerTitle); ?></span>
            <?php if ($headerSubtitle): ?>
              <span class="hidden lg:block text-xs text-slate-500 -mt-0.5"><?php echo htmlspecialchars($headerSubtitle); ?></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  
  <!-- Right actions -->
  <div class="flex items-center gap-1 lg:gap-3">
    <?php if ($headerShowSearch): ?>
      <!-- Search (desktop only) -->
      <button class="hidden lg:flex p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-50 rounded-lg transition-colors" title="Search (coming soon)">
        <i class="iconoir-search text-xl"></i>
      </button>
    <?php endif; ?>
    
    <?php if ($headerShowFaq): ?>
      <!-- Nana quick answers (desktop only) -->
      <button id="faq-btn" class="hidden lg:flex p-2 text-slate-400 hover:text-[#23AAC5] hover:bg-[#23AAC5]/10 rounded-lg transition-colors" title="Ask Nana">
        <i class="iconoir-help-circle text-xl"></i>
      </button>
    <?php endif; ?>
    
    <?php if ($headerCustomButtons): ?>
      <!-- Custom buttons -->
      <?php echo $headerCustomButtons; ?>
    <?php endif; ?>
    
    <!-- Avatar + Dropdown -->
    <div class="relative" id="profile-dropdown-container">
      <button id="profile-btn" class="flex items-center gap-1 lg:gap-2 p-1 lg:p-1.5 hover:bg-slate-50 rounded-lg transition-colors tap-highlight-none">
        <div class="h-7 w-7 lg:h-8 lg:w-8 rounded-full gradient-brand flex items-center justify-center text-white text-xs lg:text-sm font-semibold" id="user-avatar">
          <?php 
            if (isset($user)) {
              echo strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
            } else {
              echo '?';
            }
          ?>
        </div>
        <i class="iconoir-nav-arrow-down text-slate-400 text-xs lg:text-sm hidden lg:block"></i>
      </button>
      
      <!-- Dropdown menu -->
      <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-56 lg:w-64 bg-white rounded-xl shadow-xl border border-slate-200 py-2 z-50">
        <div class="px-4 py-3 border-b border-slate-100">
          <div class="font-semibold text-slate-800 text-sm" id="session-user">
            <?php 
              if (isset($user)) {
                echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
              } else {
              echo 'Loading...';
              }
            ?>
          </div>
          <div class="text-xs text-slate-500 mt-0.5" id="session-meta">
            <?php echo isset($user) ? htmlspecialchars($user['email'] ?? '') : ''; ?>
          </div>
        </div>
        <a href="/account.php" class="lg:hidden w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 transition-colors flex items-center gap-2">
          <i class="iconoir-user"></i>
          <span>My account</span>
        </a>
        
        <?php if (isset($user) && in_array('admin', $user['roles'] ?? [], true)): ?>
          <a href="/admin/users.php" id="admin-link" class="w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 transition-colors flex items-center gap-2 border-t border-slate-100">
            <i class="iconoir-settings"></i>
            <span>User management</span>
          </a>
          <a href="/admin/departments.php" id="departments-link" class="w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 transition-colors flex items-center gap-2">
            <i class="iconoir-community"></i>
            <span>Departments</span>
          </a>
          <a href="/admin/stats.php" id="stats-link" class="w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 transition-colors flex items-center gap-2">
            <i class="iconoir-graph-up"></i>
            <span>Dashboard</span>
          </a>
          <a href="/admin/context-manager.php" id="context-link" class="w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 transition-colors flex items-center gap-2">
            <i class="iconoir-folder"></i>
            <span>Context manager</span>
          </a>
          <?php if (!empty($user['is_superadmin'])): ?>
            <a href="/admin/models.php" id="models-link" class="w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 transition-colors flex items-center gap-2">
              <i class="iconoir-settings"></i>
              <span>Chat models</span>
            </a>
          <?php endif; ?>
        <?php endif; ?>
        
        <button id="logout-btn" class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 transition-colors flex items-center gap-2 border-t border-slate-100">
          <i class="iconoir-log-out"></i>
          <span>Log out</span>
        </button>
      </div>
    </div>
  </div>
</header>

<script>
// Simple profile dropdown logic.
document.addEventListener('DOMContentLoaded', () => {
  const profileBtn = document.getElementById('profile-btn');
  const profileDropdown = document.getElementById('profile-dropdown');
  const logoutBtn = document.getElementById('logout-btn');
  
  if(profileBtn && profileDropdown) {
    profileBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      profileDropdown.classList.toggle('hidden');
    });
    
    document.addEventListener('click', (e) => {
      if (!profileDropdown.contains(e.target) && !profileBtn.contains(e.target)) {
        profileDropdown.classList.add('hidden');
      }
    });
  }

  if (logoutBtn) {
    logoutBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      try {
        const response = await fetch('/api/auth/logout.php', {
          method: 'POST',
          headers: {
            'X-CSRF-Token': window.CSRF_TOKEN
          },
          credentials: 'include'
        });
        window.location.href = '/login.php';
      } catch (error) {
        console.error('Logout error:', error);
        // Fallback in case of error.
        window.location.href = '/logout.php';
      }
    });
  }
});
</script>
