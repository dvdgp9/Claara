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
 * Partial: Barra lateral izquierda con tabs de navegación
 * Incluye menús hover expandibles para acceso rápido
 * 
 * Variables esperadas:
 * - $activeTab (opcional): Tab activa ('conversations', 'voices', 'gestures'), default 'conversations'
 * - $useTabsJs (opcional): Si true, usa data-tab para manejo JS interno (index.php). Default false.
 */
$activeTab = $activeTab ?? 'conversations';
$useTabsJs = $useTabsJs ?? false;

$tabs = [
    'conversations' => [
        'icon' => 'iconoir-chat-bubble',
        'label' => 'Chat',
        'href' => '/',
        'title' => 'Conversaciones',
        'hoverTitle' => 'Conversaciones recientes',
        'hoverIcon' => 'iconoir-chat-bubble',
        'newLabel' => 'Nueva conversación',
        'newHref' => '/'
    ],
    'voices' => [
        'icon' => 'iconoir-voice-square',
        'label' => 'Voces',
        'href' => '/voices/',
        'title' => 'Voces especializadas',
        'hoverTitle' => 'Voces disponibles',
        'hoverIcon' => 'iconoir-voice-square',
        'newLabel' => 'Ver todas',
        'newHref' => '/voices/'
    ],
    'gestures' => [
        'icon' => 'iconoir-magic-wand',
        'label' => 'Gestos',
        'href' => '/gestos/',
        'title' => 'Flujos automatizados',
        'hoverTitle' => 'Gestos disponibles',
        'hoverIcon' => 'iconoir-magic-wand',
        'newLabel' => 'Ver todos',
        'newHref' => '/gestos/'
    ],
    'apps' => [
        'icon' => 'iconoir-view-grid',
        'label' => 'Apps',
        'href' => '/aplicaciones/',
        'title' => 'Aplicaciones Ebone',
        'hoverTitle' => 'Aplicaciones',
        'hoverIcon' => 'iconoir-view-grid',
        'newLabel' => 'Ver todas',
        'newHref' => '/aplicaciones/'
    ]
];

// Gestos disponibles para el submenú
$gesturesList = [
    [
        'type' => 'podcast-from-article',
        'name' => 'Podcast desde artículo',
        'icon' => 'iconoir-podcast',
        'href' => '/gestos/podcast-articulo.php',
        'description' => 'Convierte texto en audio'
    ],
    [
        'type' => 'write-article',
        'name' => 'Escribir artículo',
        'icon' => 'iconoir-edit-pencil',
        'href' => '/gestos/escribir-articulo.php',
        'description' => 'Genera contenido escrito'
    ],
    [
        'type' => 'social-media',
        'name' => 'Redes sociales',
        'icon' => 'iconoir-share-android',
        'href' => '/gestos/redes-sociales.php',
        'description' => 'Crea posts para redes'
    ],
    [
        'type' => 'image-editor',
        'name' => 'Editor de imágenes',
        'icon' => 'iconoir-media-image',
        'href' => '/gestos/editor-imagenes.php',
        'description' => 'Genera imágenes con IA'
    ],
    [
        'type' => 'content-repurposer',
        'name' => 'Transformador',
        'icon' => 'iconoir-refresh-double',
        'href' => '/gestos/transformador-contenido.php',
        'description' => 'Adapta contenido a formatos'
    ],
    [
        'type' => 'sop-generator',
        'name' => 'Generador SOPs',
        'icon' => 'iconoir-clipboard-check',
        'href' => '/gestos/sop-generator.php',
        'description' => 'Crea procedimientos'
    ]
];

// Voces disponibles
$voicesList = [
    [
        'id' => 'lex',
        'name' => 'Lex',
        'icon' => 'iconoir-book-stack',
        'href' => '/voices/lex.php',
        'description' => 'Abogado experto'
    ]
];

// Apps disponibles (catálogo real)
$appsList = [
    [
        'id' => 'campus',
        'name' => 'Campus',
        'icon' => 'iconoir-book',
        'href' => 'https://campus.ebone.es',
        'description' => 'Formación online'
    ],
    [
        'id' => 'firmas',
        'name' => 'Firmas',
        'icon' => 'iconoir-mail',
        'href' => 'https://firmas.ebone.es',
        'description' => 'Firmas de correo'
    ],
    [
        'id' => 'happy',
        'name' => 'Happy',
        'icon' => 'iconoir-emoji',
        'href' => 'https://happy.ebone.es',
        'description' => 'Encuestas'
    ],
    [
        'id' => 'loop',
        'name' => 'Loop',
        'icon' => 'iconoir-calendar',
        'href' => 'https://loop.ebone.es',
        'description' => 'RRSS y Blogs'
    ],
    [
        'id' => 'passwords',
        'name' => 'Passwords',
        'icon' => 'iconoir-lock',
        'href' => 'https://passwords.ebone.es/gestionar',
        'description' => 'Gestor seguro'
    ],
    [
        'id' => 'prisma',
        'name' => 'Prisma',
        'icon' => 'iconoir-folder',
        'href' => 'https://prisma.wthefox.com/solicitud.php?empresa=Ebone',
        'description' => 'Cambios y mejoras'
    ],
    [
        'id' => 'puri',
        'name' => 'Puri',
        'icon' => 'iconoir-check-circle',
        'href' => 'https://puri.ebone.es',
        'description' => 'Control asistencia'
    ],
    [
        'id' => 'resq',
        'name' => 'RESQ',
        'icon' => 'iconoir-swimming',
        'href' => 'https://resq.ebone.es',
        'description' => 'Seguridad acuática'
    ]
];
?>
<!-- CSS del hover menu -->
<link rel="stylesheet" href="/assets/css/sidebar-hover.css">

<!-- Barra de tabs lateral izquierda - Solo desktop -->
<aside class="hidden lg:flex w-[70px] gradient-brand flex-col items-center py-6 gap-2 shrink-0">
  <?php foreach ($tabs as $tabId => $tab): ?>
    <?php 
      $isActive = ($activeTab === $tabId);
      $baseClasses = 'tab-item w-full py-4 flex flex-col items-center gap-1.5 relative z-10';
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
      
      <!-- Panel Hover -->
      <div class="sidebar-hover-panel">
        <div class="hover-panel-header">
          <div class="hover-panel-title">
            <i class="<?php echo $tab['hoverIcon']; ?> text-orange-500"></i>
            <?php echo htmlspecialchars($tab['hoverTitle']); ?>
          </div>
        </div>
        
        <div class="hover-panel-content <?php echo ($tabId === 'gestures') ? 'overflow-visible' : ''; ?>">
          <?php if ($tabId === 'conversations'): ?>
            <!-- Cargado dinámicamente via JS -->
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
                <div class="hover-panel-item-wrapper hover-panel-item-expandable" data-gesture-type="<?php echo $gesture['type']; ?>">
                  <a href="<?php echo $gesture['href']; ?>" class="hover-panel-item">
                    <div class="hover-panel-item-icon">
                      <i class="<?php echo $gesture['icon']; ?>"></i>
                    </div>
                    <div class="hover-panel-item-info">
                      <div class="hover-panel-item-title"><?php echo htmlspecialchars($gesture['name']); ?></div>
                      <div class="hover-panel-item-meta"><?php echo htmlspecialchars($gesture['description']); ?></div>
                    </div>
                  </a>
                  
                  <!-- Submenú con historial -->
                  <div class="hover-submenu">
                    <div class="hover-submenu-header">
                      <span class="hover-submenu-title">Historial reciente</span>
                      <a href="<?php echo $gesture['href']; ?>" class="hover-submenu-new">
                        <i class="iconoir-plus"></i> Crear
                      </a>
                    </div>
                    <div class="hover-submenu-content">
                      <!-- Cargado dinámicamente via JS -->
                      <div class="hover-panel-loading">
                        <i class="iconoir-refresh"></i>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php elseif ($tabId === 'apps'): ?>
            <?php foreach ($appsList as $app): ?>
              <a href="<?php echo $app['href']; ?>" target="_blank" class="hover-panel-item">
                <div class="hover-panel-item-icon">
                  <i class="<?php echo $app['icon']; ?>"></i>
                </div>
                <div class="hover-panel-item-info">
                  <div class="hover-panel-item-title"><?php echo htmlspecialchars($app['name']); ?></div>
                  <div class="hover-panel-item-meta"><?php echo htmlspecialchars($app['description']); ?></div>
                </div>
              </a>
            <?php endforeach; ?>
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
  
  <!-- Tab Mi cuenta (sin hover menu) -->
  <?php 
    $isAccountActive = ($activeTab === 'account');
    $accountClasses = $isAccountActive ? 'text-white' : 'text-white/60 hover:text-white/80';
  ?>
  <a href="/account.php" class="tab-item w-full py-4 flex flex-col items-center gap-1.5 <?php echo $accountClasses; ?>" title="Mi cuenta">
    <i class="iconoir-user text-2xl"></i>
    <span class="text-[10px] font-medium">Cuenta</span>
  </a>
</aside>

<!-- JS del hover menu -->
<script src="/assets/js/sidebar-hover.js"></script>
