<?php
require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Repos/UserFeatureAccessRepo.php';

use App\Session;
use Repos\UserFeatureAccessRepo;

Session::start();
$user = Session::user();
$userId = $user ? (int)$user['id'] : 0;
$accessRepo = new UserFeatureAccessRepo();
$voiceResolver = new \Voices\VoiceAccessResolver();
$moduleEntitlements = \Modules\ModuleEntitlementService::current();

/**
 * Partial: left navigation rail with hover menus
 * 
 * Variables esperadas:
 * - $activeTab (optional): active tab ('conversations', 'voices', 'gestures'), default 'conversations'
 * - $useTabsJs (optional): if true, use data-tab for internal JS handling (index.php). Default false.
 */
$activeTab = $activeTab ?? 'conversations';
$useTabsJs = $useTabsJs ?? false;

$tabs = [
    'conversations' => [
        'icon' => 'iconoir-chat-bubble',
        'label' => \I18n\I18n::translate('nav.chat'),
        'href' => '/app/',
        'title' => \I18n\I18n::translate('nav.conversations'),
        'hoverTitle' => \I18n\I18n::translate('nav.recent_conversations'),
        'hoverIcon' => 'iconoir-chat-bubble',
        'newLabel' => \I18n\I18n::translate('sidebar.new_conversation'),
        'newHref' => '/app/'
    ],
    'voices' => [
        'icon' => 'iconoir-voice-square',
        'label' => \I18n\I18n::translate('nav.voices'),
        'href' => '/voices/',
        'title' => \I18n\I18n::translate('nav.specialized_voices'),
        'hoverTitle' => \I18n\I18n::translate('nav.available_voices'),
        'hoverIcon' => 'iconoir-voice-square',
        'newLabel' => \I18n\I18n::translate('nav.view_all'),
        'newHref' => '/voices/'
    ],
    'gestures' => [
        'icon' => 'iconoir-magic-wand',
        'label' => \I18n\I18n::translate('nav.gestures'),
        'href' => '/gestos/',
        'title' => \I18n\I18n::translate('nav.automated_workflows'),
        'hoverTitle' => \I18n\I18n::translate('nav.available_gestures'),
        'hoverIcon' => 'iconoir-magic-wand',
        'newLabel' => \I18n\I18n::translate('nav.view_all'),
        'newHref' => '/gestos/'
    ],
    'connectors' => [
        'icon' => 'iconoir-cloud-sync',
        'label' => \I18n\I18n::translate('nav.sources'),
        'href' => '/connectors.php',
        'title' => \I18n\I18n::translate('nav.connected_sources'),
        'hoverTitle' => \I18n\I18n::translate('nav.external_sources'),
        'hoverIcon' => 'iconoir-cloud-sync',
        'newLabel' => \I18n\I18n::translate('nav.manage_sources'),
        'newHref' => '/connectors.php'
    ]
];
$tabModules = [
    'conversations' => 'core.chat',
    'voices' => 'core.voices',
    'gestures' => 'core.gestures',
    'connectors' => 'core.connectors',
];
$tabs = array_filter(
    $tabs,
    static fn(array $tab, string $tabId): bool => $moduleEntitlements->isModuleEnabled($tabModules[$tabId]),
    ARRAY_FILTER_USE_BOTH
);

// Gestures available in the hover menu
$gesturesList = [
    [
        'type' => 'podcast-from-article',
        'name' => \I18n\I18n::translate('gesture.podcast.name'),
        'icon' => 'iconoir-podcast',
        'href' => '/gestos/podcast-articulo.php',
        'description' => \I18n\I18n::translate('gesture.podcast.description')
    ],
    [
        'type' => 'write-article',
        'name' => \I18n\I18n::translate('gesture.write.name'),
        'icon' => 'iconoir-edit-pencil',
        'href' => '/gestos/escribir-articulo.php',
        'description' => \I18n\I18n::translate('gesture.write.description')
    ],
    [
        'type' => 'social-media',
        'name' => \I18n\I18n::translate('gesture.social.name'),
        'icon' => 'iconoir-share-android',
        'href' => '/gestos/redes-sociales.php',
        'description' => \I18n\I18n::translate('gesture.social.description')
    ],
    [
        'type' => 'image-editor',
        'name' => \I18n\I18n::translate('gesture.image.name'),
        'icon' => 'iconoir-media-image',
        'href' => '/gestos/editor-imagenes.php',
        'description' => \I18n\I18n::translate('gesture.image.description')
    ],
    [
        'type' => 'content-repurposer',
        'name' => \I18n\I18n::translate('gesture.repurpose.name'),
        'icon' => 'iconoir-refresh-double',
        'href' => '/gestos/transformador-contenido.php',
        'description' => \I18n\I18n::translate('gesture.repurpose.description')
    ],
    [
        'type' => 'sop-generator',
        'name' => \I18n\I18n::translate('gesture.sop.name'),
        'icon' => 'iconoir-clipboard-check',
        'href' => '/gestos/sop-generator.php',
        'description' => \I18n\I18n::translate('gesture.sop.description')
    ],
    [
        'type' => 'audio-transcriber',
        'name' => \I18n\I18n::translate('gesture.transcribe.name'),
        'icon' => 'iconoir-microphone',
        'href' => '/gestos/transcriptor-audio.php',
        'description' => \I18n\I18n::translate('gesture.transcribe.description')
    ],
    [
        'type' => 'course-creator',
        'name' => \I18n\I18n::translate('gesture.course.name'),
        'icon' => 'iconoir-graduation-cap',
        'href' => '/gestos/creador-cursos.php',
        'description' => \I18n\I18n::translate('gesture.course.description')
    ],
    [
        'type' => 'project-admin',
        'name' => \I18n\I18n::translate('gesture.project.name'),
        'icon' => 'iconoir-folder-settings',
        'href' => '/gestos/admin-proyectos.php',
        'description' => \I18n\I18n::translate('gesture.project.description')
    ]
];

// Available voices
$voicesList = [];
try {
    $voicesRepo = new \Repos\VoicesRepo();
    foreach ($voicesRepo->listPublished() as $voice) {
        $voicesList[] = [
            'id' => $voice['slug'],
            'name' => $voice['name'],
            'icon' => $voice['icon'] ?: 'iconoir-voice-square',
            'href' => $voice['slug'] === 'lex' ? '/voices/lex.php' : '/voices/view.php?voice=' . urlencode($voice['slug']),
            'description' => $voice['role'] ?: \I18n\I18n::translate('voice.specialized'),
        ];
    }
} catch (\Throwable $e) {
    $voicesList = [
        [
            'id' => 'lex',
            'name' => 'Lex',
            'icon' => 'iconoir-book-stack',
            'href' => '/voices/lex.php',
            'description' => \I18n\I18n::translate('voice.legal_assistant')
        ]
    ];
}
?>
<!-- Hover menu CSS -->
<link rel="stylesheet" href="/assets/css/sidebar-hover.css">

<!-- Left navigation rail - desktop only -->
<aside class="hidden lg:flex w-[70px] sidebar-rail flex-col items-center py-5 gap-1.5 shrink-0">
  <?php foreach ($tabs as $tabId => $tab): ?>
    <?php 
      $isActive = ($activeTab === $tabId);
      $baseClasses = 'tab-item w-[calc(100%-12px)] mx-1.5 py-3 rounded-2xl flex flex-col items-center gap-1.5 relative z-10';
      $stateClasses = $isActive 
        ? 'active text-white' 
        : 'text-white/60 hover:text-white/80';
    ?>
    
    <div class="sidebar-tab-container w-full" data-tab-type="<?php echo $tabId; ?>">
      <?php if ($useTabsJs): ?>
        <button data-tab="<?php echo $tabId; ?>" 
                class="<?php echo $baseClasses . ' ' . $stateClasses; ?>" 
                title="<?php echo htmlspecialchars($tab['title']); ?>">
          <i class="<?php echo $tab['icon']; ?> text-2xl"></i>
          <span class="text-[10px] font-medium"><?php echo htmlspecialchars($tab['label']); ?></span>
        </button>
      <?php elseif ($tab['href']): ?>
        <a href="<?php echo $tab['href']; ?>" 
           class="<?php echo $baseClasses . ' ' . $stateClasses; ?>" 
           title="<?php echo htmlspecialchars($tab['title']); ?>">
          <i class="<?php echo $tab['icon']; ?> text-2xl"></i>
          <span class="text-[10px] font-medium"><?php echo htmlspecialchars($tab['label']); ?></span>
        </a>
      <?php endif; ?>
      
      <!-- Hover panel -->
      <div class="sidebar-hover-panel">
        <div class="hover-panel-header">
          <div class="hover-panel-title">
            <i class="<?php echo $tab['hoverIcon']; ?> text-orange-500"></i>
            <?php echo htmlspecialchars($tab['hoverTitle']); ?>
          </div>
        </div>
        
        <div class="hover-panel-content">
          <?php if ($tabId === 'conversations'): ?>
            <!-- Loaded dynamically via JS -->
            <div class="hover-panel-loading">
              <i class="iconoir-refresh"></i>
            </div>
          <?php elseif ($tabId === 'voices'): ?>
            <?php foreach ($voicesList as $voice): ?>
              <?php if ($voiceResolver->canAccessSlug($userId, $voice['id'])): ?>
                <a href="<?php echo $voice['href']; ?>" class="hover-panel-item">
                  <div class="hover-panel-item-icon">
                    <i class="<?php echo $voice['icon']; ?>"></i>
                  </div>
                  <div class="hover-panel-item-info">
                    <div class="hover-panel-item-title"><?php echo htmlspecialchars($voice['name']); ?></div>
                    <div class="hover-panel-item-meta"><?php echo htmlspecialchars($voice['description']); ?></div>
                  </div>
                </a>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php elseif ($tabId === 'gestures'): ?>
            <?php foreach ($gesturesList as $gesture): ?>
              <?php if ($accessRepo->hasGestureAccess($userId, $gesture['type'])): ?>
                <a href="<?php echo $gesture['href']; ?>" class="hover-panel-item">
                  <div class="hover-panel-item-icon">
                    <i class="<?php echo $gesture['icon']; ?>"></i>
                  </div>
                  <div class="hover-panel-item-info">
                    <div class="hover-panel-item-title"><?php echo htmlspecialchars($gesture['name']); ?></div>
                    <div class="hover-panel-item-meta"><?php echo htmlspecialchars($gesture['description']); ?></div>
                  </div>
                </a>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php elseif ($tabId === 'connectors'): ?>
            <a href="/connectors.php" class="hover-panel-item">
              <div class="hover-panel-item-icon">
                <i class="iconoir-google-drive"></i>
              </div>
              <div class="hover-panel-item-info">
                <div class="hover-panel-item-title">Google Drive</div>
                <div class="hover-panel-item-meta"><?php echo htmlspecialchars(\I18n\I18n::translate('connector.selected_files_first')); ?></div>
              </div>
            </a>
            <?php if (!empty($user['is_superadmin']) || in_array('admin', $user['roles'] ?? [], true)): ?>
              <a href="/admin/connectors.php" class="hover-panel-item">
                <div class="hover-panel-item-icon">
                  <i class="iconoir-dashboard-dots"></i>
                </div>
                <div class="hover-panel-item-info">
                <div class="hover-panel-item-title"><?php echo htmlspecialchars(\I18n\I18n::translate('connector.admin_overview')); ?></div>
                <div class="hover-panel-item-meta"><?php echo htmlspecialchars(\I18n\I18n::translate('connector.provider_health')); ?></div>
                </div>
              </a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        
        <div class="hover-panel-footer">
          <a href="<?php echo $tab['newHref']; ?>" class="hover-panel-action">
            <i class="iconoir-arrow-right"></i>
            <?php echo htmlspecialchars($tab['newLabel']); ?>
          </a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  
  <!-- Spacer -->
  <div class="flex-1"></div>

  <!-- Divider -->
  <div class="w-8 h-px bg-white/10 my-2"></div>

  <!-- Account tab (no hover menu) -->
  <?php
    $isAccountActive = ($activeTab === 'account');
    $accountStateClasses = $isAccountActive ? 'active text-white' : 'text-white/60 hover:text-white/90';
  ?>
  <a href="/account.php" class="tab-item w-[calc(100%-12px)] mx-1.5 py-3 rounded-2xl flex flex-col items-center gap-1.5 <?php echo $accountStateClasses; ?>" title="<?php echo htmlspecialchars(\I18n\I18n::translate('header.my_account')); ?>">
    <i class="iconoir-user text-2xl"></i>
    <span class="text-[10px] font-medium"><?php echo htmlspecialchars(\I18n\I18n::translate('nav.account')); ?></span>
  </a>
</aside>

<!-- Hover menu JS -->
<script src="/assets/js/sidebar-hover.js"></script>
