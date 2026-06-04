<?php
require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Repos/UserFeatureAccessRepo.php';

use App\Session;
use Repos\UserFeatureAccessRepo;

Session::start();
$user = Session::user();
$userId = $user ? (int)$user['id'] : 0;
$accessRepo = new UserFeatureAccessRepo();

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
        'label' => 'Chat',
        'href' => '/app/',
        'title' => 'Conversations',
        'hoverTitle' => 'Recent conversations',
        'hoverIcon' => 'iconoir-chat-bubble',
        'newLabel' => 'New conversation',
        'newHref' => '/app/'
    ],
    'voices' => [
        'icon' => 'iconoir-voice-square',
        'label' => 'Voices',
        'href' => '/voices/',
        'title' => 'Specialized voices',
        'hoverTitle' => 'Available voices',
        'hoverIcon' => 'iconoir-voice-square',
        'newLabel' => 'View all',
        'newHref' => '/voices/'
    ],
    'gestures' => [
        'icon' => 'iconoir-magic-wand',
        'label' => 'Gestures',
        'href' => '/gestos/',
        'title' => 'Automated workflows',
        'hoverTitle' => 'Available gestures',
        'hoverIcon' => 'iconoir-magic-wand',
        'newLabel' => 'View all',
        'newHref' => '/gestos/'
    ],
    'connectors' => [
        'icon' => 'iconoir-cloud-sync',
        'label' => 'Sources',
        'href' => '/connectors.php',
        'title' => 'Connected sources',
        'hoverTitle' => 'External sources',
        'hoverIcon' => 'iconoir-cloud-sync',
        'newLabel' => 'Manage sources',
        'newHref' => '/connectors.php'
    ]
];

// Gestures available in the hover menu
$gesturesList = [
    [
        'type' => 'podcast-from-article',
        'name' => 'Article to Podcast',
        'icon' => 'iconoir-podcast',
        'href' => '/gestos/podcast-articulo.php',
        'description' => 'Turn text into audio'
    ],
    [
        'type' => 'write-article',
        'name' => 'Write Content',
        'icon' => 'iconoir-edit-pencil',
        'href' => '/gestos/escribir-articulo.php',
        'description' => 'Generate written content'
    ],
    [
        'type' => 'social-media',
        'name' => 'Social Media',
        'icon' => 'iconoir-share-android',
        'href' => '/gestos/redes-sociales.php',
        'description' => 'Create social posts'
    ],
    [
        'type' => 'image-editor',
        'name' => 'Image Studio',
        'icon' => 'iconoir-media-image',
        'href' => '/gestos/editor-imagenes.php',
        'description' => 'Generate AI images'
    ],
    [
        'type' => 'content-repurposer',
        'name' => 'Content Repurposer',
        'icon' => 'iconoir-refresh-double',
        'href' => '/gestos/transformador-contenido.php',
        'description' => 'Adapt content to formats'
    ],
    [
        'type' => 'sop-generator',
        'name' => 'SOP Generator',
        'icon' => 'iconoir-clipboard-check',
        'href' => '/gestos/sop-generator.php',
        'description' => 'Create procedures'
    ],
    [
        'type' => 'audio-transcriber',
        'name' => 'Audio Transcriber',
        'icon' => 'iconoir-microphone',
        'href' => '/gestos/transcriptor-audio.php',
        'description' => 'Turn audio into text'
    ],
    [
        'type' => 'course-creator',
        'name' => 'Course Creator',
        'icon' => 'iconoir-graduation-cap',
        'href' => '/gestos/creador-cursos.php',
        'description' => 'Generate training material'
    ],
    [
        'type' => 'project-admin',
        'name' => 'Project Analysis',
        'icon' => 'iconoir-folder-settings',
        'href' => '/gestos/admin-proyectos.php',
        'description' => 'Analyze project documents'
    ]
];

// Available voices
$voicesList = [
    [
        'id' => 'lex',
        'name' => 'Lex',
        'icon' => 'iconoir-book-stack',
        'href' => '/voices/lex.php',
        'description' => 'Legal assistant'
    ]
];
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
              <?php if ($accessRepo->hasVoiceAccess($userId, $voice['id'])): ?>
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
                <div class="hover-panel-item-meta">Selected files first</div>
              </div>
            </a>
            <?php if (!empty($user['is_superadmin']) || in_array('admin', $user['roles'] ?? [], true)): ?>
              <a href="/admin/connectors.php" class="hover-panel-item">
                <div class="hover-panel-item-icon">
                  <i class="iconoir-dashboard-dots"></i>
                </div>
                <div class="hover-panel-item-info">
                  <div class="hover-panel-item-title">Admin overview</div>
                  <div class="hover-panel-item-meta">Provider health</div>
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
  <a href="/account.php" class="tab-item w-[calc(100%-12px)] mx-1.5 py-3 rounded-2xl flex flex-col items-center gap-1.5 <?php echo $accountStateClasses; ?>" title="My account">
    <i class="iconoir-user text-2xl"></i>
    <span class="text-[10px] font-medium">Account</span>
  </a>
</aside>

<!-- Hover menu JS -->
<script src="/assets/js/sidebar-hover.js"></script>
