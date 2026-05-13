<?php
/**
 * Bottom Navigation Bar - visible on mobile only (<lg)
 * Includes quick-access modals for Voices and Gestures
 * 
 * Variables esperadas:
 * - $activeTab: active tab ('conversations', 'voices', 'gestures', 'account')
 */
require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Repos/UserFeatureAccessRepo.php';

use App\Session;
use Repos\UserFeatureAccessRepo;

Session::start();
$user = Session::user();
$userId = $user ? (int)$user['id'] : 0;
$accessRepo = new UserFeatureAccessRepo();

$activeTab = $activeTab ?? 'conversations';

// Available voices
$voicesList = [
    [
        'id' => 'lex',
        'name' => 'Lex',
        'icon' => 'iconoir-book-stack',
        'href' => '/voices/lex.php',
        'description' => 'Legal assistant',
        'gradient' => 'from-rose-500 to-pink-600'
    ]
];

// Available gestures
$gesturesList = [
    [
        'type' => 'podcast-from-article',
        'name' => 'Generate Podcast',
        'icon' => 'iconoir-podcast',
        'href' => '/gestos/podcast-articulo.php',
        'description' => 'Turn text into audio',
        'gradient' => 'from-rose-500 to-orange-500'
    ],
    [
        'type' => 'write-article',
        'name' => 'Write Content',
        'icon' => 'iconoir-edit-pencil',
        'href' => '/gestos/escribir-articulo.php',
        'description' => 'Generate articles and blogs',
        'gradient' => 'from-cyan-500 to-teal-600'
    ],
    [
        'type' => 'social-media',
        'name' => 'Social Media',
        'icon' => 'iconoir-share-android',
        'href' => '/gestos/redes-sociales.php',
        'description' => 'Create social posts',
        'gradient' => 'from-violet-500 to-fuchsia-600'
    ],
    [
        'type' => 'image-editor',
        'name' => 'Image Studio',
        'icon' => 'iconoir-media-image',
        'href' => '/gestos/editor-imagenes.php',
        'description' => 'Generate AI images',
        'gradient' => 'from-amber-500 to-orange-600'
    ],
    [
        'type' => 'audio-transcriber',
        'name' => 'Audio Transcriber',
        'icon' => 'iconoir-microphone',
        'href' => '/gestos/transcriptor-audio.php',
        'description' => 'Turn audio into text',
        'gradient' => 'from-purple-500 to-indigo-600'
    ]
];

$tabs = [
    'conversations' => [
        'icon' => 'iconoir-chat-bubble',
        'iconActive' => 'iconoir-chat-bubble-solid',
        'label' => 'Chat',
        'href' => '/',
        'modal' => false
    ],
    'voices' => [
        'icon' => 'iconoir-voice-square',
        'iconActive' => 'iconoir-voice-square',
        'label' => 'Voices',
        'href' => '/voices/',
        'modal' => 'mobile-voices-modal'
    ],
    'gestures' => [
        'icon' => 'iconoir-magic-wand',
        'iconActive' => 'iconoir-magic-wand',
        'label' => 'Gestures',
        'href' => '/gestos/',
        'modal' => 'mobile-gestures-modal'
    ],
    'account' => [
        'icon' => 'iconoir-user',
        'iconActive' => 'iconoir-user',
        'label' => 'Account',
        'href' => '/account.php',
        'modal' => false
    ]
];
?>
<!-- Bottom Navigation - mobile only -->
<nav class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-slate-200 shadow-lg safe-area-bottom">
  <div class="flex items-center justify-around h-16">
    <?php foreach ($tabs as $tabId => $tab): ?>
      <?php 
        $isActive = ($activeTab === $tabId);
        $colorClass = $isActive ? 'text-[#23AAC5]' : 'text-slate-400';
        $iconClass = $isActive ? ($tab['iconActive'] ?? $tab['icon']) : $tab['icon'];
        $hasModal = !empty($tab['modal']);
      ?>
      <?php if ($hasModal): ?>
        <button type="button"
           data-modal="<?php echo $tab['modal']; ?>"
           class="mobile-nav-modal-trigger flex flex-col items-center justify-center flex-1 h-full py-2 <?php echo $colorClass; ?> transition-colors tap-highlight-none active:bg-slate-50 relative">
          <i class="<?php echo $iconClass; ?> text-xl mb-0.5"></i>
          <span class="text-[10px] font-medium"><?php echo htmlspecialchars($tab['label']); ?></span>
          <?php if ($isActive): ?>
            <div class="absolute bottom-1 w-1 h-1 rounded-full bg-[#23AAC5]"></div>
          <?php endif; ?>
        </button>
      <?php else: ?>
        <a href="<?php echo $tab['href']; ?>" 
           class="flex flex-col items-center justify-center flex-1 h-full py-2 <?php echo $colorClass; ?> transition-colors tap-highlight-none active:bg-slate-50 relative">
          <i class="<?php echo $iconClass; ?> text-xl mb-0.5"></i>
          <span class="text-[10px] font-medium"><?php echo htmlspecialchars($tab['label']); ?></span>
          <?php if ($isActive): ?>
            <div class="absolute bottom-1 w-1 h-1 rounded-full bg-[#23AAC5]"></div>
          <?php endif; ?>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</nav>

<!-- Modal: Voices (mobile) -->
<div id="mobile-voices-modal" class="mobile-quick-modal hidden lg:hidden fixed inset-0 z-[60]">
  <!-- Backdrop -->
  <div class="mobile-modal-backdrop absolute inset-0 bg-black/40 backdrop-blur-sm" data-close-modal></div>
  
  <!-- Bottom sheet -->
  <div class="mobile-modal-panel absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl shadow-2xl transform translate-y-full transition-transform duration-300 ease-out max-h-[85vh] flex flex-col safe-area-bottom">
    <!-- Handle -->
    <div class="flex justify-center pt-3 pb-2">
      <div class="w-10 h-1 bg-slate-300 rounded-full"></div>
    </div>
    
    <!-- Header -->
    <div class="px-5 pb-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center shadow-lg">
          <i class="iconoir-voice-square text-xl text-white"></i>
        </div>
        <div>
          <h3 class="font-semibold text-slate-900 text-lg">Voices</h3>
          <p class="text-xs text-slate-500">Specialized assistants</p>
        </div>
      </div>
    </div>
    
    <!-- Content -->
    <div class="flex-1 overflow-y-auto px-4 py-4">
      <div class="space-y-3">
        <?php 
        $hasVoices = false;
        foreach ($voicesList as $voice): 
          if ($accessRepo->hasVoiceAccess($userId, $voice['id'])):
            $hasVoices = true;
        ?>
          <a href="<?php echo $voice['href']; ?>" 
             class="flex items-center gap-4 p-4 bg-slate-50 hover:bg-slate-100 rounded-2xl transition-all active:scale-[0.98]">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br <?php echo $voice['gradient']; ?> flex items-center justify-center text-white shadow-md">
              <i class="<?php echo $voice['icon']; ?> text-xl"></i>
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($voice['name']); ?></div>
              <div class="text-sm text-slate-500 truncate"><?php echo htmlspecialchars($voice['description']); ?></div>
            </div>
            <i class="iconoir-nav-arrow-right text-slate-400"></i>
          </a>
        <?php 
          endif;
        endforeach; 
        
        if (!$hasVoices):
        ?>
          <div class="text-center py-8">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-3">
              <i class="iconoir-voice-square text-3xl text-slate-300"></i>
            </div>
            <p class="text-slate-500 text-sm">No voices available</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
    
    <!-- Footer -->
    <div class="px-4 py-4 border-t border-slate-100">
      <a href="/voices/" class="flex items-center justify-center gap-2 w-full py-3 bg-gradient-to-r from-violet-500 to-purple-600 text-white font-medium rounded-xl shadow-lg active:scale-[0.98] transition-transform">
        <span>View all voices</span>
        <i class="iconoir-arrow-right"></i>
      </a>
    </div>
  </div>
</div>

<!-- Modal: Gestures (mobile) -->
<div id="mobile-gestures-modal" class="mobile-quick-modal hidden lg:hidden fixed inset-0 z-[60]">
  <!-- Backdrop -->
  <div class="mobile-modal-backdrop absolute inset-0 bg-black/40 backdrop-blur-sm" data-close-modal></div>
  
  <!-- Bottom sheet -->
  <div class="mobile-modal-panel absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl shadow-2xl transform translate-y-full transition-transform duration-300 ease-out max-h-[85vh] flex flex-col safe-area-bottom">
    <!-- Handle -->
    <div class="flex justify-center pt-3 pb-2">
      <div class="w-10 h-1 bg-slate-300 rounded-full"></div>
    </div>
    
    <!-- Header -->
    <div class="px-5 pb-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-cyan-500 to-teal-600 flex items-center justify-center shadow-lg">
          <i class="iconoir-magic-wand text-xl text-white"></i>
        </div>
        <div>
          <h3 class="font-semibold text-slate-900 text-lg">Gestures</h3>
          <p class="text-xs text-slate-500">Automated workflows</p>
        </div>
      </div>
    </div>
    
    <!-- Content -->
    <div class="flex-1 overflow-y-auto px-4 py-4">
      <div class="space-y-3">
        <?php 
        $hasGestures = false;
        foreach ($gesturesList as $gesture): 
          if ($accessRepo->hasGestureAccess($userId, $gesture['type'])):
            $hasGestures = true;
        ?>
          <a href="<?php echo $gesture['href']; ?>" 
             class="flex items-center gap-4 p-4 bg-slate-50 hover:bg-slate-100 rounded-2xl transition-all active:scale-[0.98]">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br <?php echo $gesture['gradient']; ?> flex items-center justify-center text-white shadow-md">
              <i class="<?php echo $gesture['icon']; ?> text-xl"></i>
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($gesture['name']); ?></div>
              <div class="text-sm text-slate-500 truncate"><?php echo htmlspecialchars($gesture['description']); ?></div>
            </div>
            <i class="iconoir-nav-arrow-right text-slate-400"></i>
          </a>
        <?php 
          endif;
        endforeach; 
        
        if (!$hasGestures):
        ?>
          <div class="text-center py-8">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-3">
              <i class="iconoir-magic-wand text-3xl text-slate-300"></i>
            </div>
            <p class="text-slate-500 text-sm">No gestures available</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
    
    <!-- Footer -->
    <div class="px-4 py-4 border-t border-slate-100">
      <a href="/gestos/" class="flex items-center justify-center gap-2 w-full py-3 bg-gradient-to-r from-cyan-500 to-teal-600 text-white font-medium rounded-xl shadow-lg active:scale-[0.98] transition-transform">
        <span>View all gestures</span>
        <i class="iconoir-arrow-right"></i>
      </a>
    </div>
  </div>
</div>

<style>
  /* Safe area for devices with a notch */
  .safe-area-bottom {
    padding-bottom: env(safe-area-inset-bottom, 0);
  }
  
  /* Remove tap highlight */
  .tap-highlight-none {
    -webkit-tap-highlight-color: transparent;
  }
  
  /* Space for bottom nav in the main content */
  .has-bottom-nav {
    padding-bottom: 4rem; /* h-16 = 4rem */
  }
  
  @media (min-width: 1024px) {
    .has-bottom-nav {
      padding-bottom: 0;
    }
  }
  
  /* Mobile Quick Modals */
  .mobile-quick-modal {
    pointer-events: none;
  }
  
  .mobile-quick-modal.active {
    pointer-events: auto;
  }
  
  .mobile-quick-modal .mobile-modal-backdrop {
    opacity: 0;
    transition: opacity 0.3s ease-out;
  }
  
  .mobile-quick-modal.active .mobile-modal-backdrop {
    opacity: 1;
  }
  
  .mobile-quick-modal .mobile-modal-panel {
    transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
  }
  
  .mobile-quick-modal.active .mobile-modal-panel {
    transform: translateY(0);
  }
</style>

<script>
(function() {
  // Mobile Quick Modal System
  const modalTriggers = document.querySelectorAll('.mobile-nav-modal-trigger');
  const modals = document.querySelectorAll('.mobile-quick-modal');
  
  function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    modal.classList.remove('hidden');
    // Force reflow so the transition works.
    modal.offsetHeight;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
  
  function closeModal(modal) {
    if (!modal) return;
    
    modal.classList.remove('active');
    document.body.style.overflow = '';
    
    // Wait for the animation before hiding.
    setTimeout(() => {
      if (!modal.classList.contains('active')) {
        modal.classList.add('hidden');
      }
    }, 300);
  }
  
  function closeAllModals() {
    modals.forEach(modal => closeModal(modal));
  }
  
  // Trigger listeners
  modalTriggers.forEach(trigger => {
    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      const modalId = trigger.dataset.modal;
      openModal(modalId);
    });
  });
  
  // Cerrar al hacer clic en backdrop
  modals.forEach(modal => {
    const backdrop = modal.querySelector('[data-close-modal]');
    if (backdrop) {
      backdrop.addEventListener('click', () => closeModal(modal));
    }
    
    // También cerrar al arrastrar hacia abajo el handle (opcional: swipe to close)
    const panel = modal.querySelector('.mobile-modal-panel');
    if (panel) {
      let startY = 0;
      let currentY = 0;
      let isDragging = false;
      
      panel.addEventListener('touchstart', (e) => {
        // Solo iniciar drag si es en el handle o header
        const handle = panel.querySelector('.w-10.h-1');
        const header = panel.querySelector('.px-5.pb-4');
        if (handle && (handle.contains(e.target) || (header && header.contains(e.target)))) {
          startY = e.touches[0].clientY;
          isDragging = true;
          panel.style.transition = 'none';
        }
      });
      
      panel.addEventListener('touchmove', (e) => {
        if (!isDragging) return;
        
        currentY = e.touches[0].clientY;
        const diff = currentY - startY;
        
        if (diff > 0) {
          panel.style.transform = `translateY(${diff}px)`;
        }
      });
      
      panel.addEventListener('touchend', () => {
        if (!isDragging) return;
        
        isDragging = false;
        panel.style.transition = '';
        
        const diff = currentY - startY;
        if (diff > 100) {
          closeModal(modal);
        } else {
          panel.style.transform = '';
        }
      });
    }
  });
  
  // Cerrar con Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeAllModals();
    }
  });
})();
</script>
