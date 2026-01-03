<?php
require_once __DIR__ . '/../src/App/bootstrap.php';
require_once __DIR__ . '/../src/Repos/UserFeatureAccessRepo.php';

use App\Session;
use Repos\UserFeatureAccessRepo;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

$accessRepo = new UserFeatureAccessRepo();
$userId = (int)$user['id'];
$hasImageGenAccess = $accessRepo->hasImageGenerationAccess($userId);

$csrfToken = $_SESSION['csrf_token'] ?? '';
$activeTab = 'conversations';
$useTabsJs = true;
$userName = htmlspecialchars($user['first_name'] ?? 'Usuario');

// Configuración del header unificado
$headerShowConvTitle = true;
$headerShowSearch = true;
$headerShowFaq = true;
$headerDrawerId = 'conversations-drawer';
$headerShowLogo = true;
?><!DOCTYPE html>
<html lang="es">
<?php include __DIR__ . '/includes/head.php'; ?>
<body class="bg-mesh text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php include __DIR__ . '/includes/left-tabs.php'; ?>

    <!-- Sidebar conversaciones (solo desktop) -->
    <aside id="conversations-sidebar" class="hidden lg:flex w-80 bg-white border-r border-slate-200 flex-col shadow-sm">
      <div class="p-5 border-b border-slate-200">
        <div class="flex items-center gap-3 mb-6">
          <img src="/assets/images/logo.png" alt="Ebonia" class="h-9">
        </div>
        <button id="new-conv-btn" class="w-full py-2.5 px-4 rounded-lg gradient-brand-btn text-white font-medium shadow-md hover:shadow-lg hover:opacity-90 transition-all duration-200 flex items-center justify-center gap-2">
          <span class="text-lg">+</span> Nueva conversación
        </button>
      </div>
      <div class="flex-1 overflow-y-auto p-3">
        <!-- Sección Carpetas -->
        <div class="mb-4">
          <div class="flex items-center justify-between mb-2 px-2">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Carpetas</div>
            <button id="new-folder-btn" class="p-1 text-slate-400 hover:text-[#23AAC5] hover:bg-[#23AAC5]/10 rounded transition-colors" title="Nueva carpeta">
              <i class="iconoir-folder-plus text-sm"></i>
            </button>
          </div>
          <ul id="folder-list" class="space-y-1">
            <!-- Opción "Todas" siempre visible -->
            <li>
              <button data-folder-id="-1" class="folder-item w-full text-left p-2 rounded-lg transition-all duration-200 flex items-center gap-2 hover:bg-slate-50 group">
                <i class="iconoir-folder text-[#23AAC5]"></i>
                <span class="flex-1 text-sm text-slate-700">Todas</span>
                <span class="text-xs text-slate-400" id="all-count">0</span>
              </button>
            </li>
            <!-- Opción "Sin carpeta" -->
            <li>
              <button data-folder-id="0" class="folder-item w-full text-left p-2 rounded-lg transition-all duration-200 flex items-center gap-2 hover:bg-slate-50 group">
                <i class="iconoir-folder text-[#23AAC5]"></i>
                <span class="flex-1 text-sm text-slate-700">Sin carpeta</span>
                <span class="text-xs text-slate-400" id="root-count">0</span>
              </button>
            </li>
            <!-- Carpetas dinámicas se insertarán aquí -->
          </ul>
        </div>
        
        <!-- Sección Conversaciones -->
        <div>
          <div class="flex items-center justify-between mb-2 px-2">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Conversaciones</div>
            <select id="sort-select" class="text-xs border border-slate-200 rounded px-2 py-1 bg-white focus:outline-none focus:border-[#23AAC5]">
              <option value="updated_at">Recientes</option>
              <option value="favorite">Favoritos</option>
              <option value="created_at">Creación</option>
              <option value="title">Alfabético</option>
            </select>
          </div>
          <ul id="conv-list" class="space-y-1">
            <li class="text-slate-400 text-sm px-3 py-2">(vacío)</li>
          </ul>
        </div>
      </div>
    </aside>

    <!-- Mobile Drawer para conversaciones -->
    <?php 
    $drawerId = 'conversations-drawer';
    $drawerTitle = 'Conversaciones';
    $drawerIcon = 'iconoir-chat-bubble';
    $drawerIconColor = 'text-[#23AAC5]';
    $drawerShowNewButton = true;
    $drawerNewButtonId = 'mobile-new-conv-btn';
    $drawerNewButtonText = 'Nueva conversación';
    include __DIR__ . '/includes/mobile-drawer.php'; 
    ?>

    <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <?php include __DIR__ . '/includes/header-unified.php'; ?>
      
      <!-- Área de mensajes con scroll, padding para footer+bottom-nav en móvil -->
      <section class="flex-1 overflow-auto bg-mesh relative pb-[140px] lg:pb-0" id="messages-container">
        <div id="context-warning" class="hidden mx-6 mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-start gap-3">
          <i class="iconoir-info-circle text-amber-600 text-lg mt-0.5"></i>
          <div class="flex-1 text-sm">
            <div class="font-medium text-amber-900">Conversación muy larga</div>
            <div class="text-amber-700 mt-0.5">Para optimizar el rendimiento, solo se envían los mensajes más recientes al asistente. El historial completo permanece guardado.</div>
          </div>
        </div>
        <div id="empty-state" class="absolute inset-0 overflow-auto p-6 pb-36 lg:pb-6">
          <div class="max-w-6xl mx-auto py-8">
            
            <!-- Hero Input Section -->
            <div class="text-center mb-10">
              <!-- Status indicator -->
              <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full glass border border-slate-200/50 shadow-sm mb-6">
                <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
                <span class="text-sm text-slate-600">Lista para ayudarte</span>
              </div>
              
              <h2 class="text-3xl font-bold text-slate-900 mb-3">
                Hola, <span class="text-transparent bg-clip-text gradient-brand"><?php echo $userName; ?></span> 👋
              </h2>
              <p class="text-base text-slate-500 mb-8 max-w-lg mx-auto">¿En qué puedo ayudarte hoy? Escribe tu pregunta o elige una opción de abajo.</p>
              
              <!-- Input principal con diseño moderno -->
              <div class="bg-white rounded-3xl p-4 lg:p-5 border border-slate-200 shadow-lg max-w-2xl mx-auto">
                <form id="chat-form-empty" class="w-full">
                  <!-- Preview de archivo adjunto en estado vacío -->
                  <div id="file-preview-empty" class="hidden mb-3 p-3 bg-slate-50 rounded-xl flex items-center gap-3">
                    <div class="flex-1 flex items-center gap-3">
                      <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-[#23AAC5]/10 to-[#115c6c]/10 flex items-center justify-center flex-shrink-0">
                        <i id="file-icon-empty" class="iconoir-page text-xl text-[#23AAC5]"></i>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div id="file-name-empty" class="text-sm font-medium text-slate-800 truncate"></div>
                        <div id="file-size-empty" class="text-xs text-slate-500"></div>
                      </div>
                    </div>
                    <button type="button" id="remove-file-empty" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-smooth">
                      <i class="iconoir-xmark"></i>
                    </button>
                  </div>
                  
                  <input type="file" id="file-input-empty" class="hidden" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp" />
                  
                  <!-- Fila superior: textarea + botón enviar -->
                  <div class="flex items-start gap-3 mb-3">
                    <textarea id="chat-input-empty" rows="1" class="flex-1 min-w-0 bg-transparent border-0 px-1 py-1 text-base text-slate-700 placeholder:text-slate-400 placeholder:italic focus:outline-none focus:ring-0 resize-none overflow-hidden" placeholder="Pregúntame lo que quieras" style="min-height: 28px; max-height: 120px;"></textarea>
                    <button type="submit" class="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-[#23AAC5] hover:bg-[#23AAC5]/10 rounded-xl transition-smooth shrink-0" title="Enviar">
                      <i class="iconoir-arrow-up text-xl"></i>
                    </button>
                  </div>
                  
                  <div class="flex items-center justify-between px-1">
                    <!-- Fila inferior: botones de acción -->
                    <div class="flex items-center gap-1">
                      <button type="button" id="attach-btn-empty" class="p-2 text-slate-400 hover:text-[#23AAC5] hover:bg-[#23AAC5]/10 rounded-lg transition-smooth" title="Adjuntar archivo (PDF o imagen)">
                        <i class="iconoir-attachment text-lg"></i>
                      </button>
                      <button type="button" id="image-mode-btn-empty" class="<?php echo $hasImageGenAccess ? '' : 'hidden'; ?> p-2 text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-smooth" title="Generar imagen con nanobanana 🍌">
                        <i class="iconoir-media-image text-lg"></i>
                      </button>
                      <?php if ($user['is_superadmin']): ?>
                      <select id="model-select-empty" class="ml-1 text-[10px] bg-slate-50 border border-slate-200 rounded-md px-2 py-1 text-slate-500 focus:outline-none focus:border-[#23AAC5] transition-colors" title="Seleccionar modelo (Solo Superadmin)">
                        <option value="google/gemini-3-flash-preview">Gemini 3 Flash</option>
                        <option value="deepseek/deepseek-v3.2">Deepseek v3.2</option>
                        <option value="z-ai/glm-4.7">GLM 4.7</option>
                        <option value="xiaomi/mimo-v2-flash:free">Xiaomi Mimo v2</option>
                      </select>
                      <?php endif; ?>
                    </div>
                    <span id="shortcut-hint-empty" class="text-[10px] text-slate-400 font-medium opacity-50 select-none pr-1">⌘ + Enter para enviar</span>
                  </div>
                </form>
              </div>
            </div>

            <!-- Divisor con "o" -->
            <div class="flex items-center gap-4 max-w-2xl mx-auto mb-8">
              <div class="flex-1 h-px bg-slate-200"></div>
              <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">O elige una opción</span>
              <div class="flex-1 h-px bg-slate-200"></div>
            </div>

            <!-- Grid de opciones: Voces y Gestos -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-5xl mx-auto">
              
              <!-- Sección Voces -->
              <div class="glass-strong rounded-3xl border border-slate-200/50 p-6 card-hover">
                <div class="flex items-center gap-3 mb-5">
                  <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center shadow-lg animate-float" style="animation-delay: 0s">
                    <i class="iconoir-voice-square text-2xl text-white"></i>
                  </div>
                  <div>
                    <h3 class="text-lg font-bold text-slate-900">Voces</h3>
                    <p class="text-sm text-slate-500">Saben todo lo que necesitas</p>
                  </div>
                </div>
                
                <div class="space-y-2.5">
                  <?php if ($accessRepo->hasVoiceAccess($userId, 'lex')): ?>
                  <!-- Lex - Activo -->
                  <button class="voice-option w-full p-4 bg-white/60 hover:bg-white border border-slate-200/80 hover:border-rose-300 rounded-2xl transition-smooth text-left group hover:shadow-md" data-voice="lex">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-500 to-pink-600 flex items-center justify-center text-white font-bold text-sm flex-shrink-0 shadow-sm group-hover:scale-110 transition-smooth">L</div>
                      <div class="flex-1 min-w-0">
                        <div class="font-semibold text-slate-800 group-hover:text-rose-600 transition-smooth">Lex</div>
                        <div class="text-xs text-slate-500">Tu asistente legal de Ebone</div>
                      </div>
                      <i class="iconoir-arrow-right text-slate-300 group-hover:text-rose-500 group-hover:translate-x-1 transition-smooth"></i>
                    </div>
                  </button>
                  <?php endif; ?>

                  <!-- Cubo - Próximamente -->
                  <div class="w-full p-4 bg-white/40 border border-slate-200/80 rounded-2xl opacity-60">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 rounded-xl bg-slate-200 flex items-center justify-center text-slate-400 font-bold text-sm flex-shrink-0">C</div>
                      <div class="flex-1 min-w-0">
                        <div class="font-semibold text-slate-500">Cubo</div>
                        <div class="text-xs text-slate-400">Próximamente</div>
                      </div>
                      <span class="px-2 py-0.5 text-xs bg-slate-100 text-slate-400 rounded-full">Soon</span>
                    </div>
                  </div>

                  <!-- Uniges - Próximamente -->
                  <div class="w-full p-4 bg-white/40 border border-slate-200/80 rounded-2xl opacity-60">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 rounded-xl bg-slate-200 flex items-center justify-center text-slate-400 font-bold text-sm flex-shrink-0">U</div>
                      <div class="flex-1 min-w-0">
                        <div class="font-semibold text-slate-500">Uniges</div>
                        <div class="text-xs text-slate-400">Próximamente</div>
                      </div>
                      <span class="px-2 py-0.5 text-xs bg-slate-100 text-slate-400 rounded-full">Soon</span>
                    </div>
                  </div>

                  <button id="view-all-voices" class="w-full p-3 mt-1 hover:bg-violet-50 border-2 border-dashed border-slate-200 hover:border-violet-300 rounded-2xl transition-smooth text-center group">
                    <div class="flex items-center justify-center gap-2 text-sm font-medium text-slate-500 group-hover:text-violet-600 transition-smooth">
                      <span>Ver todas las voces</span>
                      <i class="iconoir-arrow-right group-hover:translate-x-1 transition-smooth"></i>
                    </div>
                  </button>
                </div>
              </div>

              <!-- Sección Gestos -->
              <div class="glass-strong rounded-3xl border border-slate-200/50 p-6 card-hover">
                <div class="flex items-center gap-3 mb-5">
                  <div class="w-12 h-12 rounded-2xl gradient-brand flex items-center justify-center shadow-lg animate-float" style="animation-delay: 0.5s">
                    <i class="iconoir-magic-wand text-2xl text-white"></i>
                  </div>
                  <div>
                    <h3 class="text-lg font-bold text-slate-900">Gestos</h3>
                    <p class="text-sm text-slate-500">Acciones rápidas optimizadas</p>
                  </div>
                </div>
                
                <div class="space-y-2.5">
                  <?php if ($accessRepo->hasGestureAccess($userId, 'write-article')): ?>
                  <button class="gesture-option w-full p-4 bg-white/60 hover:bg-white border border-slate-200/80 hover:border-[#23AAC5]/50 rounded-2xl transition-smooth text-left group hover:shadow-md" data-gesture="write-article">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#23AAC5] to-[#115c6c] flex items-center justify-center flex-shrink-0 shadow-sm group-hover:scale-110 transition-smooth">
                        <i class="iconoir-page-edit text-lg text-white"></i>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="font-semibold text-slate-800 group-hover:text-[#115c6c] transition-smooth">Escribir artículo</div>
                        <div class="text-xs text-slate-500">Blogs, noticias, notas de prensa</div>
                      </div>
                      <i class="iconoir-arrow-right text-slate-300 group-hover:text-[#23AAC5] group-hover:translate-x-1 transition-smooth"></i>
                    </div>
                  </button>
                  <?php endif; ?>

                  <?php if ($accessRepo->hasGestureAccess($userId, 'social-media')): ?>
                  <button class="gesture-option w-full p-4 bg-white/60 hover:bg-white border border-slate-200/80 hover:border-violet-400/50 rounded-2xl transition-smooth text-left group hover:shadow-md" data-gesture="social-media">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-600 flex items-center justify-center flex-shrink-0 shadow-sm group-hover:scale-110 transition-smooth">
                        <i class="iconoir-send-diagonal text-lg text-white"></i>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="font-semibold text-slate-800 group-hover:text-violet-600 transition-smooth">Redes sociales</div>
                        <div class="text-xs text-slate-500">Publicaciones para RRSS</div>
                      </div>
                      <i class="iconoir-arrow-right text-slate-300 group-hover:text-violet-500 group-hover:translate-x-1 transition-smooth"></i>
                    </div>
                  </button>
                  <?php endif; ?>

                  <?php if ($accessRepo->hasGestureAccess($userId, 'podcast-from-article')): ?>
                  <button class="gesture-option w-full p-4 bg-white/60 hover:bg-white border border-slate-200/80 hover:border-rose-400/50 rounded-2xl transition-smooth text-left group hover:shadow-md" data-gesture="podcast-from-article">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-500 to-orange-500 flex items-center justify-center flex-shrink-0 shadow-sm group-hover:scale-110 transition-smooth">
                        <i class="iconoir-podcast text-lg text-white"></i>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="font-semibold text-slate-800 group-hover:text-rose-600 transition-smooth">Podcast desde artículo</div>
                        <div class="text-xs text-slate-500">Audio con 2 voces IA</div>
                      </div>
                      <i class="iconoir-arrow-right text-slate-300 group-hover:text-rose-500 group-hover:translate-x-1 transition-smooth"></i>
                    </div>
                  </button>
                  <?php endif; ?>

                  

                  <button id="view-all-gestures" class="w-full p-3 mt-1 hover:bg-[#23AAC5]/5 border-2 border-dashed border-slate-200 hover:border-[#23AAC5]/50 rounded-2xl transition-smooth text-center group">
                    <div class="flex items-center justify-center gap-2 text-sm font-medium text-slate-500 group-hover:text-[#23AAC5] transition-smooth">
                      <span>Ver todos los gestos</span>
                      <i class="iconoir-arrow-right group-hover:translate-x-1 transition-smooth"></i>
                    </div>
                  </button>
                </div>
              </div>

            </div>
          </div>
        </div>
        <div id="messages" class="hidden p-4 lg:p-8 pb-36 lg:pb-8 space-y-2"></div>
        <div id="typing-indicator" class="hidden px-8 pb-4">
          <div class="flex gap-3 items-start max-w-3xl">
            <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 text-sm font-semibold flex-shrink-0">E</div>
            <div class="bg-white border border-slate-200 px-5 py-3.5 rounded-2xl rounded-tl-sm shadow-sm">
              <span class="streaming-indicator"><span></span><span></span><span></span></span>
            </div>
          </div>
        </div>
      </section>
      <!-- Footer del chat: fijo en móvil sobre el bottom-nav -->
      <footer id="chat-footer" class="hidden fixed lg:relative bottom-16 lg:bottom-0 left-0 right-0 p-3 lg:p-4 bg-gradient-to-t from-white via-white to-white/80 z-40">
        <form id="chat-form" class="max-w-3xl mx-auto">
          <div class="bg-white rounded-2xl lg:rounded-3xl p-3 lg:p-4 border border-slate-200 shadow-lg">
            <!-- Preview de archivo adjunto -->
            <div id="file-preview" class="hidden mb-3 p-3 bg-slate-50 rounded-xl flex items-center gap-3">
              <div class="flex-1 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-[#23AAC5]/10 to-[#115c6c]/10 flex items-center justify-center flex-shrink-0">
                  <i id="file-icon" class="iconoir-page text-xl text-[#23AAC5]"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <div id="file-name" class="text-sm font-medium text-slate-800 truncate"></div>
                  <div id="file-size" class="text-xs text-slate-500"></div>
                </div>
              </div>
              <button type="button" id="remove-file" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                <i class="iconoir-xmark"></i>
              </button>
            </div>
            
            <input type="file" id="file-input" class="hidden" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp" />
            
            <!-- Fila superior: textarea + botón enviar -->
            <div class="flex items-start gap-3 mb-2">
              <textarea id="chat-input" rows="1" class="flex-1 min-w-0 bg-transparent border-0 px-1 py-1 text-base text-slate-700 placeholder:text-slate-400 placeholder:italic focus:outline-none focus:ring-0 resize-none overflow-hidden" placeholder="Escribe un mensaje..." style="min-height: 28px; max-height: 120px;"></textarea>
              <button type="submit" class="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-[#23AAC5] hover:bg-[#23AAC5]/10 rounded-xl transition-smooth shrink-0" title="Enviar">
                <i class="iconoir-arrow-up text-xl"></i>
              </button>
            </div>
            
            <div class="flex items-center justify-between px-1">
              <!-- Fila inferior: botones de acción -->
              <div class="flex items-center gap-1">
                <button type="button" id="attach-btn" class="p-2 text-slate-400 hover:text-[#23AAC5] hover:bg-[#23AAC5]/10 rounded-lg transition-smooth" title="Adjuntar archivo (PDF o imagen)">
                  <i class="iconoir-attachment text-lg"></i>
                </button>
                <button type="button" id="image-mode-btn" class="<?php echo $hasImageGenAccess ? '' : 'hidden'; ?> p-2 text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-smooth" title="Generar imagen con nanobanana 🍌">
                  <i class="iconoir-media-image text-lg"></i>
                </button>
                <?php if ($user['is_superadmin']): ?>
                <select id="model-select-chat" class="ml-1 text-[10px] bg-slate-50 border border-slate-200 rounded-md px-2 py-1 text-slate-500 focus:outline-none focus:border-[#23AAC5] transition-colors" title="Seleccionar modelo (Solo Superadmin)">
                  <option value="google/gemini-3-flash-preview">Gemini 3 Flash</option>
                  <option value="deepseek/deepseek-v3.2">Deepseek v3.2</option>
                  <option value="z-ai/glm-4.7">GLM 4.7</option>
                  <option value="xiaomi/mimo-v2-flash:free">Xiaomi Mimo v2</option>
                </select>
                <?php endif; ?>
              </div>
              <span id="shortcut-hint-chat" class="text-[10px] text-slate-400 font-medium opacity-50 select-none pr-1">⌘ + Enter para enviar</span>
            </div>
          </div>
        </form>
      </footer>
    </main>
  </div>
  
  <!-- Lightbox para imágenes generadas (nanobanana 🍌) -->
  <div id="image-lightbox" class="hidden fixed inset-0 bg-black/90 backdrop-blur-sm z-[60] flex items-center justify-center p-4" onclick="closeLightbox()">
    <button onclick="closeLightbox()" class="absolute top-4 right-4 p-2 text-white/70 hover:text-white hover:bg-white/10 rounded-full transition-colors">
      <i class="iconoir-xmark text-2xl"></i>
    </button>
    <img id="lightbox-img" src="" alt="Imagen ampliada" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl" onclick="event.stopPropagation()">
  </div>
  
  <!-- Modal Mover a Carpeta -->
  <div id="move-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full max-h-[80vh] flex flex-col">
      <!-- Header -->
      <div class="p-6 border-b border-slate-200 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl gradient-brand flex items-center justify-center">
            <i class="iconoir-folder-settings text-xl text-white"></i>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-slate-900">Mover conversación</h3>
            <p class="text-xs text-slate-500" id="move-conv-title">Selecciona la carpeta de destino</p>
          </div>
        </div>
        <button id="close-move-modal" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
          <i class="iconoir-xmark text-xl"></i>
        </button>
      </div>
      
      <!-- Body - Lista de carpetas -->
      <div class="flex-1 overflow-y-auto p-6">
        <div class="space-y-2" id="folder-options">
          <!-- Opción "Sin carpeta" -->
          <button data-target-folder="0" class="folder-option w-full p-4 bg-slate-50 hover:bg-[#23AAC5]/5 border-2 border-slate-200 hover:border-[#23AAC5] rounded-xl transition-all text-left group">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg bg-slate-200 flex items-center justify-center flex-shrink-0 group-hover:bg-[#23AAC5]/10">
                <i class="iconoir-folder-minus text-xl text-slate-500 group-hover:text-[#23AAC5]"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-slate-800 group-hover:text-[#23AAC5] transition-colors">Sin carpeta</div>
                <div class="text-xs text-slate-500">Mover a la raíz</div>
              </div>
              <i class="iconoir-nav-arrow-right text-slate-300 group-hover:text-[#23AAC5] transition-colors"></i>
            </div>
          </button>
          
          <!-- Carpetas dinámicas se insertarán aquí -->
        </div>
        
        <div id="empty-folders" class="hidden text-center py-8 text-slate-400 text-sm">
          <i class="iconoir-folder text-4xl mb-2"></i>
          <p>No tienes carpetas creadas</p>
        </div>
      </div>
      
      <!-- Footer -->
      <div class="p-6 border-t border-slate-200 flex gap-3">
        <button id="cancel-move" class="flex-1 px-4 py-2.5 border-2 border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors">
          Cancelar
        </button>
      </div>
    </div>
  </div>
  
  <script type="module">
    const messagesEl = document.getElementById('messages');
    const messagesContainer = document.getElementById('messages-container');
    const emptyState = document.getElementById('empty-state');
    const chatFooter = document.getElementById('chat-footer');
    const inputEl = document.getElementById('chat-input');
    const inputEmptyEl = document.getElementById('chat-input-empty');

    const formEl = document.getElementById('chat-form');
    const formEmptyEl = document.getElementById('chat-form-empty');

    function handleCommandEnter(e, form) {
      if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
        e.preventDefault();
        form.dispatchEvent(new Event('submit'));
      }
    }

    inputEl.addEventListener('keydown', (e) => handleCommandEnter(e, formEl));
    inputEmptyEl.addEventListener('keydown', (e) => handleCommandEnter(e, formEmptyEl));

    const logoutBtn = document.getElementById('logout-btn');
    const sessionUser = document.getElementById('session-user');
    const sessionMeta = document.getElementById('session-meta');
    const userAvatar = document.getElementById('user-avatar');
    const profileBtn = document.getElementById('profile-btn');
    const profileDropdown = document.getElementById('profile-dropdown');
    const convTitleEl = document.getElementById('conv-title');
    const convListEl = document.getElementById('conv-list');
    const newConvBtn = document.getElementById('new-conv-btn');
    const sortSelect = document.getElementById('sort-select');
    const typingIndicator = document.getElementById('typing-indicator');
    const folderListEl = document.getElementById('folder-list');
    const newFolderBtn = document.getElementById('new-folder-btn');
    const moveModal = document.getElementById('move-modal');
    const closeMoveModal = document.getElementById('close-move-modal');
    const cancelMoveBtn = document.getElementById('cancel-move');
    const folderOptionsEl = document.getElementById('folder-options');
    const emptyFoldersEl = document.getElementById('empty-folders');
    const fileInput = document.getElementById('file-input');
    const attachBtn = document.getElementById('attach-btn');
    const filePreview = document.getElementById('file-preview');
    const fileName = document.getElementById('file-name');
    const fileSize = document.getElementById('file-size');
    const fileIcon = document.getElementById('file-icon');
    const removeFileBtn = document.getElementById('remove-file');
    
    const fileInputEmpty = document.getElementById('file-input-empty');
    const attachBtnEmpty = document.getElementById('attach-btn-empty');
    const imageModeBtnEmpty = document.getElementById('image-mode-btn-empty');
    const attachBtnEmptyDesktop = document.getElementById('attach-btn-empty-desktop');
    const filePreviewEmpty = document.getElementById('file-preview-empty');
    const fileNameEmpty = document.getElementById('file-name-empty');
    const fileSizeEmpty = document.getElementById('file-size-empty');
    const fileIconEmpty = document.getElementById('file-icon-empty');
    const removeFileBtnEmpty = document.getElementById('remove-file-empty');

    let csrf = null;
    let currentConversationId = null;
    let emptyConversationId = null; // id de conversación sin mensajes aún
    let currentUser = null;
    let currentConvTitle = null;
    let currentFile = null; // archivo adjunto actual
    let currentFileEmpty = null; // archivo adjunto en estado vacío
    let currentFolderId = -1; // -1 = todas, 0 = sin carpeta, >0 = carpeta específica
    let allFolders = []; // cache de carpetas
    let conversationToMove = null; // conversación que se está moviendo
    let imageMode = false; // modo generación de imágenes (nanobanana 🍌)

    function showChatMode(){
      emptyState.classList.add('hidden');
      messagesEl.classList.remove('hidden');
      chatFooter.classList.remove('hidden');
    }

    

    function showEmptyMode(){
      emptyState.classList.remove('hidden');
      messagesEl.classList.add('hidden');
      chatFooter.classList.add('hidden');
      messagesEl.innerHTML = '';
      document.getElementById('context-warning').classList.add('hidden');
      convTitleEl.classList.add('hidden');
      inputEmptyEl?.focus();
    }

    // Eliminar conversación vacía (sin mensajes) para evitar acumulación
    async function cleanupEmptyConversation(exceptId = null) {
      if (emptyConversationId && emptyConversationId !== exceptId) {
        const idToDelete = emptyConversationId;
        emptyConversationId = null;
        try {
          await api('/api/conversations/delete.php', { 
            method: 'POST', 
            body: { id: idToDelete } 
          });
        } catch (e) {
          console.warn('Error limpiando conversación vacía:', e);
        }
      }
    }

    // Limpiar conversación vacía al salir de la página
    window.addEventListener('beforeunload', () => {
      if (emptyConversationId) {
        // Usar sendBeacon para petición asíncrona que sobrevive al cierre
        const data = new FormData();
        data.append('id', emptyConversationId);
        data.append('csrf_token', csrf);
        navigator.sendBeacon('/api/conversations/delete.php', data);
      }
    });

    function escapeHtml(str){
      return str.replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    }

    function mdToHtml(md){
      // escape first
      let s = escapeHtml(md);
      // headings
      s = s.replace(/^###\s+(.+)$/gm, '<h3 class="font-semibold text-base mb-1">$1<\/h3>');
      s = s.replace(/^##\s+(.+)$/gm, '<h2 class="font-semibold text-lg mb-1">$1<\/h2>');
      s = s.replace(/^#\s+(.+)$/gm, '<h1 class="font-semibold text-xl mb-1">$1<\/h1>');
      // bold and italics (basic)
      s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1<\/strong>');
      s = s.replace(/\*(.+?)\*/g, '<em>$1<\/em>');
      // inline code
      s = s.replace(/`([^`]+)`/g, '<code class="px-1 py-0.5 bg-slate-100 rounded">$1<\/code>');
      // tables
      s = s.replace(/((?:\n|^)\|[^\n]+\|\r?\n\|[ :\-|]+\|\r?\n(?:\|[^\n]+\|(?:\r?\n|$))+)/g, function(match) {
        const lines = match.trim().split(/\r?\n/);
        let html = '<div class="table-container"><table class="md-table">';
        let hasHeader = false;
        lines.forEach((line) => {
          // Detectar línea de separación (contiene solo pipes, guiones, dos puntos y espacios)
          if (line.match(/^\|[ :\-|]+\|$/)) return;
          
          const cells = line.split('|').filter((c, i, a) => i > 0 && i < a.length - 1);
          if (cells.length === 0) return;
          
          const tag = !hasHeader ? 'th' : 'td';
          const row = '<tr>' + cells.map(c => `<${tag}>${c.trim()}<\/${tag}>`).join('') + '<\/tr>';
          
          if (!hasHeader) {
            html += '<thead>' + row + '<\/thead><tbody>';
            hasHeader = true;
          } else {
            html += row;
          }
        });
        html += '<\/tbody><\/table><\/div>';
        return html;
      });
      // line breaks
      s = s.replace(/\n/g, '<br>');
      return s;
    }

    function append(role, content, file = null, images = null){
      if(messagesEl.children.length === 0) showChatMode();
      
      const wrap = document.createElement('div');
      wrap.className = 'mb-6 flex flex-col ' + (role === 'user' ? 'items-end' : 'items-start');
      
      // Avatar + burbuja container
      const msgContainer = document.createElement('div');
      msgContainer.className = 'flex gap-3 max-w-3xl ' + (role === 'user' ? 'flex-row-reverse' : 'flex-row');
      
      // Avatar
      const avatar = document.createElement('div');
      avatar.className = role === 'user'
        ? 'w-9 h-9 rounded-full gradient-brand flex items-center justify-center text-white text-sm font-semibold flex-shrink-0 shadow-sm'
        : 'w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 text-sm font-semibold flex-shrink-0';
      avatar.textContent = role === 'user' 
        ? (currentUser ? currentUser.first_name[0] + currentUser.last_name[0] : '?')
        : 'E';
      
      // Burbuja
      const bubble = document.createElement('div');
      bubble.className = role === 'user' 
        ? 'gradient-brand text-white px-5 py-3.5 rounded-2xl rounded-tr-sm shadow-md text-conversation' 
        : 'bg-white border border-slate-200 text-slate-800 px-5 py-3.5 rounded-2xl rounded-tl-sm shadow-sm text-conversation';
      bubble.style.wordBreak = 'break-word';
      
      if (role === 'assistant') {
        bubble.innerHTML = mdToHtml(content);
      } else {
        bubble.textContent = content;
      }
      
      // Añadir archivo adjunto si existe
      if (file && role === 'user') {
        const fileEl = document.createElement('div');
        fileEl.className = 'mt-2 flex items-center gap-2 text-sm opacity-90';
        
        const icon = file.mime_type === 'application/pdf' 
          ? '<i class="iconoir-page"></i>' 
          : '<i class="iconoir-media-image"></i>';
        
        if (file.expired) {
          fileEl.innerHTML = `${icon} <span class="line-through">${escapeHtml(file.name)}</span> <span class="text-xs">(expirado)</span>`;
        } else {
          fileEl.innerHTML = `${icon} <a href="${file.url}" target="_blank" class="underline hover:no-underline">${escapeHtml(file.name)}</a>`;
        }
        bubble.appendChild(fileEl);
      }
      
      // Añadir imágenes generadas si existen (nanobanana 🍌)
      if (images && images.length > 0 && role === 'assistant') {
        const imagesContainer = document.createElement('div');
        imagesContainer.className = 'mt-3 space-y-3';
        
        images.forEach((img, idx) => {
          const imgUrl = img.image_url?.url || img.imageUrl?.url || '';
          if (!imgUrl) return;
          
          const imgWrap = document.createElement('div');
          imgWrap.className = 'relative group';
          
          const imgEl = document.createElement('img');
          imgEl.src = imgUrl;
          imgEl.alt = 'Imagen generada ' + (idx + 1);
          imgEl.className = 'max-w-full rounded-xl shadow-md cursor-pointer hover:shadow-lg transition-shadow';
          imgEl.style.maxHeight = '400px';
          imgEl.addEventListener('click', () => openLightbox(imgUrl));
          
          const actionsEl = document.createElement('div');
          actionsEl.className = 'mt-2 flex gap-2';
          
          const downloadBtn = document.createElement('a');
          downloadBtn.href = imgUrl;
          downloadBtn.download = `nanobanana-${Date.now()}-${idx + 1}.png`;
          downloadBtn.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-amber-50 text-amber-700 hover:bg-amber-100 rounded-lg transition-colors';
          downloadBtn.innerHTML = '<i class="iconoir-download"></i> Descargar';
          
          actionsEl.appendChild(downloadBtn);
          imgWrap.appendChild(imgEl);
          imgWrap.appendChild(actionsEl);
          imagesContainer.appendChild(imgWrap);
        });
        
        bubble.appendChild(imagesContainer);
      }
      
      msgContainer.appendChild(avatar);
      msgContainer.appendChild(bubble);
      
      // Timestamp
      const timestamp = document.createElement('div');
      timestamp.className = 'text-xs text-slate-400 mt-1 px-3';
      const now = new Date();
      timestamp.textContent = now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
      
      wrap.appendChild(msgContainer);
      wrap.appendChild(timestamp);
      messagesEl.appendChild(wrap);
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Lightbox para ver imágenes en grande (nanobanana 🍌)
    function openLightbox(imgUrl) {
      const lightbox = document.getElementById('image-lightbox');
      const lightboxImg = document.getElementById('lightbox-img');
      lightboxImg.src = imgUrl;
      lightbox.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
      const lightbox = document.getElementById('image-lightbox');
      lightbox.classList.add('hidden');
      document.body.style.overflow = '';
    }

    // Cerrar lightbox con Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeLightbox();
    });

    async function api(path, opts={}){
      const res = await fetch(path, {
        method: opts.method || 'GET',
        headers: {
          'Content-Type': 'application/json',
          ...(csrf ? { 'X-CSRF-Token': csrf } : {})
        },
        body: opts.body ? JSON.stringify(opts.body) : undefined,
        credentials: 'include'
      });
      const data = await res.json().catch(()=>({}));
      if(!res.ok) throw new Error(data?.error?.message || res.statusText);
      return data;
    }

    // Restaurar sesión al cargar (si existe cookie de sesión)
    (async function initSession(){
      try {
        const res = await fetch('/api/auth/me.php', { credentials: 'include' });
        if (res.status === 401) {
          window.location.href = '/login.php';
          return;
        }
        const data = await res.json();
        csrf = data.csrf_token || null;
        currentUser = data.user;
        
        // Actualizar UI de perfil
        const fullName = `${data.user.first_name} ${data.user.last_name}`;
        sessionUser.textContent = fullName;
        sessionMeta.textContent = data.user.email;
        
        // Avatar con iniciales
        const initials = `${data.user.first_name[0]}${data.user.last_name[0]}`.toUpperCase();
        userAvatar.textContent = initials;
        
        // Mostrar enlaces admin si es superadmin
        if (data.user.roles && data.user.roles.includes('admin')) {
          document.getElementById('admin-link').classList.remove('hidden');
          document.getElementById('stats-link').classList.remove('hidden');
        }
        
        await loadFolders();
        await loadConversations();
      } catch (_) {
        window.location.href = '/login.php';
      }
    })();

    // El dropdown de perfil se maneja en header-unified.php
    
    sortSelect.addEventListener('change', () => loadConversations());
    
    // Crear nueva carpeta
    newFolderBtn.addEventListener('click', async () => {
      const name = prompt('Nombre de la carpeta:');
      if (!name || name.trim() === '') return;
      try {
        await api('/api/folders/create.php', { method: 'POST', body: { name: name.trim() } });
        await loadFolders();
        await loadConversations();
      } catch (err) {
        alert('Error al crear carpeta: ' + err.message);
      }
    });
    
    // Cerrar modal
    closeMoveModal.addEventListener('click', () => {
      moveModal.classList.add('hidden');
      conversationToMove = null;
    });
    
    cancelMoveBtn.addEventListener('click', () => {
      moveModal.classList.add('hidden');
      conversationToMove = null;
    });
    
    // Cerrar modal al hacer clic fuera
    moveModal.addEventListener('click', (e) => {
      if (e.target === moveModal) {
        moveModal.classList.add('hidden');
        conversationToMove = null;
      }
    });

    logoutBtn.addEventListener('click', async (e)=>{
      e.stopPropagation();
      try {
        await api('/api/auth/logout.php', { method: 'POST' });
        window.location.href = '/login.php';
      } catch(e){
        alert('Logout error: ' + e.message);
      }
    });

    async function loadFolders(){
      const data = await api('/api/folders/list.php');
      allFolders = data.folders || [];
      
      // Contar conversaciones totales y sin carpeta
      const allConvs = await api('/api/conversations/list.php?folder_id=-1');
      const rootConvs = await api('/api/conversations/list.php?folder_id=0');
      document.getElementById('all-count').textContent = (allConvs.items || []).length;
      document.getElementById('root-count').textContent = (rootConvs.items || []).length;
      
      // Renderizar carpetas dinámicas
      const existingDynamic = folderListEl.querySelectorAll('.dynamic-folder');
      existingDynamic.forEach(el => el.remove());
      
      for (const folder of allFolders) {
        const li = document.createElement('li');
        li.className = 'dynamic-folder group';
        
        const btn = document.createElement('div');
        btn.dataset.folderId = folder.id;
        btn.setAttribute('role', 'button');
        btn.tabIndex = 0;
        btn.className = 'folder-item w-full text-left p-2 rounded-lg transition-all duration-200 flex items-center gap-2 hover:bg-slate-50 whitespace-nowrap min-w-0';
        if (currentFolderId === folder.id) {
          btn.classList.add('bg-gradient-to-r', 'from-[#23AAC5]/10', 'to-[#115c6c]/10', 'shadow-sm');
        }
        
        const iconEl = document.createElement('i');
        iconEl.className = 'iconoir-folder text-[#23AAC5] flex-shrink-0';
        
        const nameEl = document.createElement('span');
        nameEl.className = 'flex-1 text-sm text-slate-700 truncate min-w-0';
        nameEl.textContent = folder.name;
        
        const countEl = document.createElement('span');
        countEl.className = 'text-xs text-slate-400 flex-shrink-0';
        countEl.textContent = folder.conversation_count;
        
        btn.appendChild(iconEl);
        btn.appendChild(nameEl);
        
        btn.addEventListener('click', () => {
          currentFolderId = folder.id;
          loadFolders();
          loadConversations();
        });
        
        // Acciones de carpeta (renombrar, eliminar) - siempre presentes pero invisibles
        const actions = document.createElement('div');
        actions.className = 'flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0';
        
        const renameBtn = document.createElement('button');
        renameBtn.className = 'p-1 text-slate-400 hover:text-[#23AAC5] hover:bg-[#23AAC5]/10 rounded transition-colors';
        renameBtn.setAttribute('data-action-folder', 'rename');
        renameBtn.innerHTML = '<i class="iconoir-edit-pencil text-xs"></i>';
        renameBtn.title = 'Renombrar';
        renameBtn.addEventListener('click', async (e) => {
          e.stopPropagation();
          const newName = prompt('Nuevo nombre:', folder.name);
          if (!newName || newName.trim() === '') return;
          try {
            await api('/api/folders/rename.php', { method: 'POST', body: { id: folder.id, name: newName.trim() } });
            await loadFolders();
          } catch (err) {
            alert('Error al renombrar: ' + err.message);
          }
        });
        
        const delBtn = document.createElement('button');
        delBtn.className = 'p-1 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors';
        delBtn.setAttribute('data-action-folder', 'delete');
        delBtn.innerHTML = '<i class="iconoir-trash text-xs"></i>';
        delBtn.title = 'Eliminar';
        delBtn.addEventListener('click', async (e) => {
          e.stopPropagation();
          const msg = folder.conversation_count > 0 
            ? `¿Eliminar "${folder.name}"? Las ${folder.conversation_count} conversaciones quedarán sin carpeta.`
            : `¿Eliminar "${folder.name}"?`;
          if (!confirm(msg)) return;
          try {
            await api('/api/folders/delete.php', { method: 'POST', body: { id: folder.id } });
            if (currentFolderId === folder.id) {
              currentFolderId = -1;
            }
            await loadFolders();
            await loadConversations();
          } catch (err) {
            alert('Error al eliminar: ' + err.message);
          }
        });
        
        actions.appendChild(renameBtn);
        actions.appendChild(delBtn);
        btn.appendChild(actions);
        btn.appendChild(countEl);
        
        li.appendChild(btn);
        folderListEl.appendChild(li);
      }
      
      // Actualizar estado activo de "Todas" y "Sin carpeta"
      const allFolderItems = document.querySelectorAll('.folder-item');
      allFolderItems.forEach(item => {
        const folderId = parseInt(item.dataset.folderId);
        item.classList.remove('bg-gradient-to-r', 'from-[#23AAC5]/10', 'to-[#115c6c]/10', 'shadow-sm');
        if (folderId === currentFolderId) {
          item.classList.add('bg-gradient-to-r', 'from-[#23AAC5]/10', 'to-[#115c6c]/10', 'shadow-sm');
        }
        
        // Añadir event listeners solo para "Todas" (-1) y "Sin carpeta" (0)
        if (folderId === -1 || folderId === 0) {
          item.addEventListener('click', () => {
            currentFolderId = folderId;
            loadFolders();
            loadConversations();
          });
        }
      });
    }
    
    function openMoveModal(conversation) {
      conversationToMove = conversation;
      document.getElementById('move-conv-title').textContent = `"${conversation.title}"`;
      
      // Renderizar opciones de carpetas
      const dynamicOptions = folderOptionsEl.querySelectorAll('.dynamic-folder-option');
      dynamicOptions.forEach(el => el.remove());
      
      if (allFolders.length === 0) {
        emptyFoldersEl.classList.remove('hidden');
      } else {
        emptyFoldersEl.classList.add('hidden');
        
        allFolders.forEach(folder => {
          const btn = document.createElement('button');
          btn.dataset.targetFolder = folder.id;
          btn.className = 'folder-option dynamic-folder-option w-full p-4 bg-slate-50 hover:bg-[#23AAC5]/5 border-2 border-slate-200 hover:border-[#23AAC5] rounded-xl transition-all text-left group';
          
          // Marcar si es la carpeta actual
          if (conversation.folder_id && conversation.folder_id == folder.id) {
            btn.classList.add('border-[#23AAC5]', 'bg-[#23AAC5]/5');
          }
          
          btn.innerHTML = `
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-[#23AAC5]/20 to-[#115c6c]/20 flex items-center justify-center flex-shrink-0">
                <i class="iconoir-folder text-xl text-[#23AAC5]"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-slate-800 group-hover:text-[#23AAC5] transition-colors">${folder.name}</div>
                <div class="text-xs text-slate-500">${folder.conversation_count} conversación${folder.conversation_count !== 1 ? 'es' : ''}</div>
              </div>
              <i class="iconoir-nav-arrow-right text-slate-300 group-hover:text-[#23AAC5] transition-colors"></i>
            </div>
          `;
          
          btn.addEventListener('click', () => handleMoveConversation(folder.id));
          folderOptionsEl.appendChild(btn);
        });
      }
      
      // Añadir listener al botón "Sin carpeta"
      const rootBtn = folderOptionsEl.querySelector('[data-target-folder="0"]');
      if (rootBtn) {
        // Remover listeners anteriores clonando
        const newRootBtn = rootBtn.cloneNode(true);
        rootBtn.parentNode.replaceChild(newRootBtn, rootBtn);
        
        // Resetear clases por si acaso
        newRootBtn.classList.remove('border-[#23AAC5]', 'bg-[#23AAC5]/5');
        
        // Marcar si está en raíz
        if (!conversation.folder_id || conversation.folder_id === 0 || conversation.folder_id === "0") {
          newRootBtn.classList.add('border-[#23AAC5]', 'bg-[#23AAC5]/5');
        }
        
        newRootBtn.addEventListener('click', () => handleMoveConversation(null));
      }
      
      moveModal.classList.remove('hidden');
    }
    
    async function handleMoveConversation(targetFolderId) {
      if (!conversationToMove) return;
      
      try {
        await api('/api/conversations/move_to_folder.php', { 
          method: 'POST', 
          body: { 
            conversation_id: conversationToMove.id, 
            folder_id: targetFolderId 
          } 
        });
        
        moveModal.classList.add('hidden');
        conversationToMove = null;
        
        await loadFolders();
        await loadConversations();
      } catch (err) {
        alert('Error al mover: ' + err.message);
      }
    }

    async function loadConversations(){
      const sort = sortSelect.value || 'updated_at';
      const folderParam = currentFolderId !== null ? `&folder_id=${currentFolderId}` : '';
      const data = await api(`/api/conversations/list.php?sort=${encodeURIComponent(sort)}${folderParam}`);
      const items = data.items || [];
      if(items.length === 0){
        convListEl.innerHTML = '<li class="text-slate-400 text-sm px-3 py-2">(vacío)</li>';
        return;
      }
      convListEl.innerHTML = '';
      for(const c of items){
        const li = document.createElement('li');
        const isActive = currentConversationId === c.id;
        li.className = 'group rounded-lg transition-all duration-200 ' + (isActive ? 'bg-gradient-to-r from-[#23AAC5]/10 to-[#115c6c]/10 shadow-sm' : 'hover:bg-slate-50');
        li.setAttribute('data-conv-id', c.id);
        li.style.minHeight = '48px';

        const container = document.createElement('div');
        container.className = 'flex items-center gap-3 p-2';

        // Botón estrella fuera del botón principal
        const starBtn = document.createElement('button');
        starBtn.className = 'flex-shrink-0 transition-colors';
        starBtn.setAttribute('data-action', 'favorite');
        starBtn.innerHTML = c.is_favorite 
          ? '<i class="iconoir-star-solid text-amber-500"></i>'
          : '<i class="iconoir-star text-slate-300 group-hover:text-slate-400"></i>';
        starBtn.title = c.is_favorite ? 'Quitar de favoritos' : 'Añadir a favoritos';
        starBtn.addEventListener('click', async (e) => {
          e.stopPropagation();
          try {
            await api('/api/conversations/toggle_favorite.php', { method: 'POST', body: { id: c.id } });
            await loadConversations();
          } catch (err) {
            alert('Error al cambiar favorito: ' + err.message);
          }
        });

        const btn = document.createElement('button');
        btn.className = 'text-left flex-1 min-w-0 flex items-center gap-2';
        btn.setAttribute('data-conv-id', c.id);

        const textContainer = document.createElement('div');
        textContainer.className = 'flex-1 min-w-0 max-w-[180px]';
        const titleEl = document.createElement('div');
        titleEl.className = 'font-medium text-sm truncate ' + (isActive ? 'text-[#115c6c]' : 'text-slate-700 group-hover:text-slate-900');
        titleEl.textContent = c.title || `Conversación ${c.id}`;
        const timeEl = document.createElement('div');
        timeEl.className = 'text-xs text-slate-400';
        timeEl.textContent = new Date(c.updated_at).toLocaleDateString('es-ES', {month: 'short', day: 'numeric'});
        textContainer.appendChild(titleEl);
        textContainer.appendChild(timeEl);

        btn.appendChild(textContainer);
        btn.addEventListener('click', async () => {
          // Asegurar cierre de drawer móvil
          closeMobileDrawer('conversations-drawer');
          await cleanupEmptyConversation(c.id);
          currentConversationId = c.id;
          updateConvTitle(c.title);
          await loadConversations();
          messagesEl.innerHTML = '';
          await loadMessages(c.id);
        });

        const actions = document.createElement('div');
        actions.className = 'flex items-center gap-0.5 opacity-100 lg:opacity-0 lg:group-hover:opacity-100 transition-opacity flex-shrink-0 whitespace-nowrap';
        const renameBtn = document.createElement('button');
        renameBtn.className = 'p-1.5 text-slate-400 hover:text-[#23AAC5] hover:bg-[#23AAC5]/10 rounded transition-colors';
        renameBtn.setAttribute('data-action', 'rename');
        renameBtn.innerHTML = '<i class="iconoir-edit-pencil"></i>';
        renameBtn.title = 'Renombrar';
        renameBtn.addEventListener('click', async (e) => {
          e.stopPropagation();
          const title = prompt('Nuevo título', c.title || '');
          if (!title) return;
          try {
            await api('/api/conversations/rename.php', { method: 'POST', body: { id: c.id, title } });
            if (currentConversationId === c.id) {
              updateConvTitle(title);
            }
            await loadConversations();
          } catch (err) {
            alert('Error al renombrar: ' + err.message);
          }
        });

        const moveBtn = document.createElement('button');
        moveBtn.className = 'p-1.5 text-slate-400 hover:text-[#23AAC5] hover:bg-[#23AAC5]/10 rounded transition-colors';
        moveBtn.setAttribute('data-action', 'move');
        moveBtn.innerHTML = '<i class="iconoir-folder-settings"></i>';
        moveBtn.title = 'Mover a carpeta';
        moveBtn.addEventListener('click', async (e) => {
          e.stopPropagation();
          openMoveModal(c);
        });

        const delBtn = document.createElement('button');
        delBtn.className = 'p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors';
        delBtn.setAttribute('data-action', 'delete');
        delBtn.innerHTML = '<i class="iconoir-trash"></i>';
        delBtn.title = 'Borrar';
        delBtn.addEventListener('click', async (e) => {
          e.stopPropagation();
          if(!confirm('¿Eliminar conversación?')) return;
          try {
            await api('/api/conversations/delete.php', { method: 'POST', body: { id: c.id } });
            if(currentConversationId === c.id){
              currentConversationId = null;
              messagesEl.innerHTML = '';
              updateConvTitle(null);
              emptyState?.classList.remove('hidden');
              messagesEl.classList.add('hidden');
              chatFooter.classList.add('hidden');
            }
            await loadFolders();
            await loadConversations();
          } catch (err) {
            alert('Error al eliminar: ' + err.message);
          }
        });

        actions.appendChild(renameBtn);
        actions.appendChild(moveBtn);
        actions.appendChild(delBtn);

        container.appendChild(starBtn);
        container.appendChild(btn);
        container.appendChild(actions);
        li.appendChild(container);
        // Hacer clic en toda la fila (excepto botones de acción)
        li.addEventListener('click', (e) => {
          if (e.target.closest('button') && !e.target.closest('[data-conv-id]')) return;
          btn.click();
        });
        convListEl.appendChild(li);
      }
    }

    async function loadMessages(conversationId){
      const data = await api(`/api/messages/list.php?conversation_id=${encodeURIComponent(conversationId)}`);
      messagesEl.innerHTML = '';
      document.getElementById('context-warning').classList.add('hidden');
      const items = data.items || [];
      if(items.length > 0){
        showChatMode();
        for(const m of items){
          append(m.role, m.content, m.file || null, m.images || null);
        }
        emptyConversationId = null;
      } else {
        showEmptyMode();
        emptyConversationId = conversationId;
      }
    }
    
    function updateConvTitle(title) {
      if (title && title !== 'Nueva conversación') {
        currentConvTitle = title;
        const span = convTitleEl.querySelector('span');
        if (span) span.textContent = title;
        convTitleEl.classList.remove('hidden');
      } else {
        currentConvTitle = null;
        convTitleEl.classList.add('hidden');
      }
    }

    newConvBtn.addEventListener('click', async ()=>{
      try{
        // Si ya hay una conversación vacía sin mensajes, reutilizarla
        if (emptyConversationId) {
          currentConversationId = emptyConversationId;
          updateConvTitle(null);
          await loadConversations();
          showEmptyMode();
          return;
        }
        const res = await api('/api/conversations/create.php', { method: 'POST', body: {} });
        currentConversationId = res.id;
        emptyConversationId = res.id;
        updateConvTitle(null);
        await loadConversations();
        showEmptyMode();
      }catch(e){
        alert('Error al crear conversación: ' + e.message);
      }
    });

    async function handleSubmit(text, file = null){
      if(!text && !file) return;
      
      // Mostrar mensaje del usuario con archivo si existe
      let userMessage = text || '';
      if (file) {
        userMessage += file ? ` 📎 ${file.name}` : '';
      }
      append('user', userMessage);
      
      // Mostrar indicador de escritura
      typingIndicator.classList.remove('hidden');
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
      
      try {
        const body = {
          conversation_id: currentConversationId,
          message: text || (file ? '¿Qué puedes decirme sobre este archivo?' : '')
        };

        // Si está en modo imagen, añadir flag
        if (imageMode) {
          body.image_mode = true;
        }

        // Si es superadmin, añadir el modelo seleccionado
        const modelSelectEmpty = document.getElementById('model-select-empty');
        const modelSelectChat = document.getElementById('model-select-chat');
        if (modelSelectEmpty) {
          body.model = modelSelectEmpty.value;
        } else if (modelSelectChat) {
          body.model = modelSelectChat.value;
        }

        // Si hay archivo, subirlo primero para obtener file_id persistente
        if (file) {
          const base64 = await fileToBase64(file);
          const uploadRes = await api('/api/files/upload.php', {
            method: 'POST',
            body: {
              data: base64,
              mime_type: file.type,
              name: file.name,
              conversation_id: currentConversationId || null
            }
          });
          if (uploadRes.file_id) {
            body.file_id = uploadRes.file_id;
          } else {
            body.file = {
              mime_type: file.type,
              data: base64,
              name: file.name
            };
          }
        }

        const data = await api('/api/chat.php', { method: 'POST', body });
        
        // Ocultar indicador de escritura
        typingIndicator.classList.add('hidden');
        
        if (!currentConversationId && data.conversation && data.conversation.id) {
          currentConversationId = data.conversation.id;
          await loadConversations();
        }
        // Actualizar título tras auto-title
        if (data.conversation && data.conversation.id === currentConversationId) {
          const convData = await api(`/api/conversations/list.php`);
          const conv = convData.items?.find(c => c.id === currentConversationId);
          if (conv) updateConvTitle(conv.title);
        }
        // Al enviar el primer mensaje, ya no es conversación vacía
        if (emptyConversationId === currentConversationId) emptyConversationId = null;
        // Mostrar/ocultar aviso de truncamiento
        const warning = document.getElementById('context-warning');
        if (data.context_truncated) {
          warning.classList.remove('hidden');
        } else {
          warning.classList.add('hidden');
        }
        // Pasar imágenes generadas si las hay
        const images = data.message.images || null;
        append('assistant', data.message.content, null, images);
        
        // Si se generó imagen, desactivar modo imagen después
        if (imageMode && images && images.length > 0) {
          imageMode = false;
          updateImageModeUI();
        }
      } catch(e){
        typingIndicator.classList.add('hidden');
        append('assistant', 'Error: ' + e.message);
      }
    }

    function fileToBase64(file) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
          // Quitar el prefijo "data:mime/type;base64,"
          const base64 = reader.result.split(',')[1];
          resolve(base64);
        };
        reader.onerror = reject;
        reader.readAsDataURL(file);
      });
    }

    // Manejar adjuntar archivo
    attachBtn.addEventListener('click', () => {
      fileInput.click();
    });

    // Toggle modo generación de imágenes (nanobanana 🍌)
    const imageModeBtn = document.getElementById('image-mode-btn');
    const chatInput = document.getElementById('chat-input');
    const chatInputEmpty = document.getElementById('chat-input-empty');
    const defaultPlaceholder = 'Escribe un mensaje...';
    const defaultPlaceholderEmpty = 'Pregúntame lo que quieras';
    const imagePlaceholder = 'Describe la imagen que quieres crear... 🍌';

    // Auto-resize para textareas
    function autoResize(textarea) {
      textarea.style.height = 'auto';
      textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    // Event listeners para auto-resize
    chatInput.addEventListener('input', () => autoResize(chatInput));
    chatInputEmpty.addEventListener('input', () => autoResize(chatInputEmpty));

    function updateImageModeUI() {
      // Clases para el nuevo diseño moderno
      const btnActive = 'p-2 text-amber-600 bg-amber-50 rounded-lg transition-smooth';
      const btnInactive = 'p-2 text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-smooth';

      if (imageMode) {
        // Chat normal
        imageModeBtn.className = btnActive;
        chatInput.placeholder = imagePlaceholder;
        attachBtn.disabled = true;
        attachBtn.classList.add('opacity-50', 'cursor-not-allowed');
        // Empty state
        imageModeBtnEmpty.className = btnActive;
        chatInputEmpty.placeholder = imagePlaceholder;
        attachBtnEmpty.disabled = true;
        attachBtnEmpty.classList.add('opacity-50', 'cursor-not-allowed');
        if (attachBtnEmptyDesktop) {
          attachBtnEmptyDesktop.disabled = true;
          attachBtnEmptyDesktop.classList.add('opacity-50', 'cursor-not-allowed');
        }
      } else {
        // Chat normal
        imageModeBtn.className = btnInactive;
        chatInput.placeholder = defaultPlaceholder;
        attachBtn.disabled = false;
        attachBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        // Empty state
        imageModeBtnEmpty.className = btnInactive;
        chatInputEmpty.placeholder = defaultPlaceholderEmpty;
        attachBtnEmpty.disabled = false;
        attachBtnEmpty.classList.remove('opacity-50', 'cursor-not-allowed');
        if (attachBtnEmptyDesktop) {
          attachBtnEmptyDesktop.disabled = false;
          attachBtnEmptyDesktop.classList.remove('opacity-50', 'cursor-not-allowed');
        }
      }
    }

    // Event listener para botón de imagen en chat normal
    imageModeBtn.addEventListener('click', () => {
      imageMode = !imageMode;
      updateImageModeUI();
      // Si se activa modo imagen, limpiar archivo adjunto
      if (imageMode && currentFile) {
        currentFile = null;
        fileInput.value = '';
        filePreview.classList.add('hidden');
      }
    });

    fileInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;

      // Validar tamaño (10MB máximo)
      const maxSize = 10 * 1024 * 1024;
      if (file.size > maxSize) {
        alert('El archivo es demasiado grande. Máximo 10MB.');
        fileInput.value = '';
        return;
      }

      // Validar tipo
      const validTypes = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'];
      if (!validTypes.includes(file.type)) {
        alert('Tipo de archivo no soportado. Solo PDF e imágenes.');
        fileInput.value = '';
        return;
      }

      currentFile = file;
      showFilePreview(file);
    });

    removeFileBtn.addEventListener('click', () => {
      currentFile = null;
      fileInput.value = '';
      filePreview.classList.add('hidden');
    });

    function showFilePreview(file) {
      fileName.textContent = file.name;
      fileSize.textContent = formatFileSize(file.size);
      
      // Cambiar icono según tipo
      if (file.type === 'application/pdf') {
        fileIcon.className = 'iconoir-page text-xl text-red-500';
      } else if (file.type.startsWith('image/')) {
        fileIcon.className = 'iconoir-media-image text-xl text-[#23AAC5]';
      }
      
      filePreview.classList.remove('hidden');
    }

    function formatFileSize(bytes) {
      if (bytes < 1024) return bytes + ' B';
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
      return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    formEl.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const text = inputEl.value.trim();
      
      if (!text && !currentFile) return;
      
      inputEl.value = '';
      await handleSubmit(text, currentFile);
      
      // Limpiar archivo después de enviar
      if (currentFile) {
        currentFile = null;
        fileInput.value = '';
        filePreview.classList.add('hidden');
      }
    });

    formEmptyEl.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const text = inputEmptyEl.value.trim();
      
      if (!text && !currentFileEmpty) return;
      
      inputEmptyEl.value = '';
      await handleSubmit(text, currentFileEmpty);
      
      // Limpiar archivo después de enviar
      if (currentFileEmpty) {
        currentFileEmpty = null;
        fileInputEmpty.value = '';
        filePreviewEmpty.classList.add('hidden');
      }
    });

    // Manejar adjuntar archivo en estado vacío
    attachBtnEmpty.addEventListener('click', () => {
      fileInputEmpty.click();
    });
    if (attachBtnEmptyDesktop) {
      attachBtnEmptyDesktop.addEventListener('click', () => {
        fileInputEmpty.click();
      });
    }

    // Event listener para botón de imagen en estado vacío
    imageModeBtnEmpty.addEventListener('click', () => {
      imageMode = !imageMode;
      updateImageModeUI();
      // Si se activa modo imagen, limpiar archivo adjunto
      if (imageMode && currentFileEmpty) {
        currentFileEmpty = null;
        fileInputEmpty.value = '';
        filePreviewEmpty.classList.add('hidden');
      }
      
      // Si el usuario clica y ya hay texto, o para forzar el foco
      inputEmptyEl.focus();
    });

    // Asegurar estado visual inicial correcto (texto) para los botones de imagen en empty state
    updateImageModeUI();

    fileInputEmpty.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;

      // Validar tamaño (10MB máximo)
      const maxSize = 10 * 1024 * 1024;
      if (file.size > maxSize) {
        alert('El archivo es demasiado grande. Máximo 10MB.');
        fileInputEmpty.value = '';
        return;
      }

      // Validar tipo
      const validTypes = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'];
      if (!validTypes.includes(file.type)) {
        alert('Tipo de archivo no soportado. Solo PDF e imágenes.');
        fileInputEmpty.value = '';
        return;
      }

      currentFileEmpty = file;
      showFilePreviewEmpty(file);
    });

    removeFileBtnEmpty.addEventListener('click', () => {
      currentFileEmpty = null;
      fileInputEmpty.value = '';
      filePreviewEmpty.classList.add('hidden');
    });

    function showFilePreviewEmpty(file) {
      fileNameEmpty.textContent = file.name;
      fileSizeEmpty.textContent = formatFileSize(file.size);
      
      // Cambiar icono según tipo
      if (file.type === 'application/pdf') {
        fileIconEmpty.className = 'iconoir-page text-xl text-red-500';
      } else if (file.type.startsWith('image/')) {
        fileIconEmpty.className = 'iconoir-media-image text-xl text-[#23AAC5]';
      }
      
      filePreviewEmpty.classList.remove('hidden');
    }

    // Manejar clics en voces - rutas a páginas específicas
    const voiceRoutes = {
      'lex': '/voices/lex.php'
      // Otras voces se añadirán cuando estén listas
    };
    
    document.querySelectorAll('.voice-option').forEach(btn => {
      btn.addEventListener('click', () => {
        const voice = btn.getAttribute('data-voice');
        const voiceName = btn.querySelector('.font-semibold').textContent;
        
        // Si la voz tiene ruta, redirigir
        if (voiceRoutes[voice]) {
          window.location.href = voiceRoutes[voice];
          return;
        }
        
        // Mostrar mensaje temporal (próximamente) para voces sin implementar
        const tempMsg = document.createElement('div');
        tempMsg.className = 'fixed top-20 left-1/2 -translate-x-1/2 bg-violet-600 text-white px-6 py-3 rounded-xl shadow-lg z-50 flex items-center gap-2';
        tempMsg.innerHTML = `<i class="iconoir-voice-square"></i><span>Voz <strong>${voiceName}</strong> disponible próximamente</span>`;
        document.body.appendChild(tempMsg);
        
        setTimeout(() => {
          tempMsg.style.opacity = '0';
          tempMsg.style.transition = 'opacity 0.3s';
          setTimeout(() => tempMsg.remove(), 300);
        }, 2000);
      });
    });

    async function highlightActive(){
      const items = convListEl.querySelectorAll('li');
      items.forEach(li => li.classList.remove('bg-gray-100'));
      // volver a poner la clase sobre el seleccionado en el próximo render de lista
    }

    // Manejo de tabs laterales
    const tabButtons = document.querySelectorAll('[data-tab]');
    const conversationsSidebar = document.getElementById('conversations-sidebar');
    
    tabButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const tab = btn.getAttribute('data-tab');
        
        // Actualizar estado activo de tabs
        tabButtons.forEach(b => {
          b.classList.remove('active', 'text-white/80');
          b.classList.add('text-white/60');
        });
        btn.classList.add('active', 'text-white/80');
        btn.classList.remove('text-white/60');
        
        // Redirigir a las vistas correspondientes
        if (tab === 'gestures') {
          window.location.href = '/gestos/';
        } else if (tab === 'voices') {
          window.location.href = '/voices/';
        } else if (tab === 'apps') {
          window.location.href = '/aplicaciones/';
        } else if (tab === 'conversations') {
          // Volver al estado vacío si estamos en una conversación
          if (currentConversationId) {
            cleanupEmptyConversation();
            currentConversationId = null;
            showEmptyMode();
            loadConversations();
          }
        }
      });
    });

    // Botones "Ver todas" que cambian a las tabs correspondientes
    const viewAllVoicesBtn = document.getElementById('view-all-voices');
    const viewAllGesturesBtn = document.getElementById('view-all-gestures');

    if (viewAllVoicesBtn) {
      viewAllVoicesBtn.addEventListener('click', () => {
        window.location.href = '/voices/';
      });
    }

    if (viewAllGesturesBtn) {
      viewAllGesturesBtn.addEventListener('click', () => {
        window.location.href = '/gestos/';
      });
    }
  </script>
  
  <!-- Modal FAQ / Dudas Rápidas -->
  <div id="faq-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col">
      <!-- Header -->
      <div class="p-5 border-b border-slate-200 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl gradient-brand flex items-center justify-center">
            <i class="iconoir-help-circle text-xl text-white"></i>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-slate-900">Dudas rápidas</h3>
            <p class="text-xs text-slate-500">Pregunta sobre el Grupo Ebone</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button id="faq-clear-btn" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" title="Nueva conversación">
            <i class="iconoir-refresh text-lg"></i>
          </button>
          <button id="faq-close-btn" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
            <i class="iconoir-xmark text-xl"></i>
          </button>
        </div>
      </div>
      
      <!-- Mensajes -->
      <div id="faq-messages" class="flex-1 overflow-y-auto p-5 space-y-4">
        <!-- Estado inicial con sugerencias -->
        <div id="faq-suggestions" class="space-y-3">
          <p class="text-sm text-slate-600 text-center mb-4">¿Qué quieres saber? Aquí tienes algunas ideas:</p>
          <div class="grid grid-cols-1 gap-2">
            <button class="faq-suggestion p-3 text-left bg-slate-50 hover:bg-[#23AAC5]/5 border border-slate-200 hover:border-[#23AAC5] rounded-xl transition-all text-sm text-slate-700 hover:text-[#23AAC5]">
              ¿Qué es CUBOFIT y cómo funciona?
            </button>
            <button class="faq-suggestion p-3 text-left bg-slate-50 hover:bg-[#23AAC5]/5 border border-slate-200 hover:border-[#23AAC5] rounded-xl transition-all text-sm text-slate-700 hover:text-[#23AAC5]">
              ¿Cuántos empleados tiene el Grupo Ebone?
            </button>
            <button class="faq-suggestion p-3 text-left bg-slate-50 hover:bg-[#23AAC5]/5 border border-slate-200 hover:border-[#23AAC5] rounded-xl transition-all text-sm text-slate-700 hover:text-[#23AAC5]">
              ¿Qué servicios ofrece UNIGES-3?
            </button>
            <button class="faq-suggestion p-3 text-left bg-slate-50 hover:bg-[#23AAC5]/5 border border-slate-200 hover:border-[#23AAC5] rounded-xl transition-all text-sm text-slate-700 hover:text-[#23AAC5]">
              ¿Dónde están las sedes del grupo?
            </button>
          </div>
        </div>
      </div>
      
      <!-- Typing indicator -->
      <div id="faq-typing" class="hidden px-5 pb-2">
        <div class="flex items-center gap-2 text-slate-500 text-sm">
          <div class="flex gap-1">
            <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
            <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
            <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
          </div>
          <span>Pensando...</span>
        </div>
      </div>
      
      <!-- Input -->
      <div class="p-4 border-t border-slate-200 flex-shrink-0">
        <form id="faq-form" class="flex gap-3">
          <input 
            id="faq-input" 
            type="text" 
            class="flex-1 border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-all text-sm" 
            placeholder="Escribe tu pregunta..."
            autocomplete="off"
          />
          <button type="submit" class="px-5 py-3 gradient-brand-btn text-white rounded-xl font-medium shadow-md hover:shadow-lg hover:opacity-90 transition-all">
            <i class="iconoir-send text-lg"></i>
          </button>
        </form>
      </div>
    </div>
  </div>
  
  <script>
    // FAQ Modal Logic
    (function() {
      const faqBtn = document.getElementById('faq-btn');
      const faqModal = document.getElementById('faq-modal');
      const faqCloseBtn = document.getElementById('faq-close-btn');
      const faqClearBtn = document.getElementById('faq-clear-btn');
      const faqForm = document.getElementById('faq-form');
      const faqInput = document.getElementById('faq-input');
      const faqMessages = document.getElementById('faq-messages');
      const faqSuggestions = document.getElementById('faq-suggestions');
      const faqTyping = document.getElementById('faq-typing');
      
      let faqHistory = []; // Historial en memoria
      
      // Helpers locales
      function escapeHtml(str) {
        return str.replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
      }
      
      function mdToHtml(md) {
        let s = escapeHtml(md);
        s = s.replace(/^###\s+(.+)$/gm, '<h3 class="font-semibold text-base mb-1">$1</h3>');
        s = s.replace(/^##\s+(.+)$/gm, '<h2 class="font-semibold text-lg mb-1">$1</h2>');
        s = s.replace(/^#\s+(.+)$/gm, '<h1 class="font-semibold text-xl mb-1">$1</h1>');
        s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/\*(.+?)\*/g, '<em>$1</em>');
        s = s.replace(/`([^`]+)`/g, '<code class="px-1 py-0.5 bg-slate-200 rounded text-xs">$1</code>');
        s = s.replace(/\n/g, '<br>');
        return s;
      }
      
      // Abrir modal
      faqBtn.addEventListener('click', () => {
        faqModal.classList.remove('hidden');
        faqInput.focus();
      });
      
      // Cerrar modal
      faqCloseBtn.addEventListener('click', () => {
        faqModal.classList.add('hidden');
      });
      
      // Cerrar con click fuera
      faqModal.addEventListener('click', (e) => {
        if (e.target === faqModal) {
          faqModal.classList.add('hidden');
        }
      });
      
      // Cerrar con Escape
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !faqModal.classList.contains('hidden')) {
          faqModal.classList.add('hidden');
        }
      });
      
      // Limpiar conversación
      faqClearBtn.addEventListener('click', () => {
        faqHistory = [];
        faqMessages.innerHTML = faqSuggestions.outerHTML;
        faqSuggestions.classList.remove('hidden');
        bindSuggestions();
      });
      
      // Sugerencias
      function bindSuggestions() {
        document.querySelectorAll('.faq-suggestion').forEach(btn => {
          btn.addEventListener('click', () => {
            faqInput.value = btn.textContent.trim();
            faqForm.dispatchEvent(new Event('submit'));
          });
        });
      }
      bindSuggestions();
      
      // Enviar mensaje
      faqForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = faqInput.value.trim();
        if (!message) return;
        
        // Ocultar sugerencias
        const suggestions = faqMessages.querySelector('#faq-suggestions');
        if (suggestions) suggestions.classList.add('hidden');
        
        // Añadir mensaje usuario
        appendFaqMessage('user', message);
        faqInput.value = '';
        faqHistory.push({ role: 'user', content: message });
        
        // Mostrar typing
        faqTyping.classList.remove('hidden');
        faqMessages.scrollTop = faqMessages.scrollHeight;
        
        try {
          const res = await fetch('/api/faq.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.CSRF_TOKEN
            },
            body: JSON.stringify({
              message: message,
              history: faqHistory.slice(0, -1) // Enviar historial sin el mensaje actual
            }),
            credentials: 'include'
          });
          
          const data = await res.json();
          faqTyping.classList.add('hidden');
          
          if (!res.ok) {
            appendFaqMessage('assistant', 'Lo siento, ha ocurrido un error. Por favor, inténtalo de nuevo.');
            return;
          }
          
          appendFaqMessage('assistant', data.reply);
          faqHistory.push({ role: 'assistant', content: data.reply });
          
        } catch (err) {
          faqTyping.classList.add('hidden');
          appendFaqMessage('assistant', 'Error de conexión. Por favor, inténtalo de nuevo.');
        }
      });
      
      function appendFaqMessage(role, content) {
        const div = document.createElement('div');
        div.className = 'flex gap-3 ' + (role === 'user' ? 'justify-end' : 'justify-start');
        
        // Obtener iniciales del usuario del avatar existente en el DOM
        const userInitials = document.getElementById('user-avatar')?.textContent?.trim() || '?';
        
        const avatar = role === 'user' 
          ? `<div class="w-8 h-8 rounded-full gradient-brand flex items-center justify-center text-white text-xs font-semibold flex-shrink-0">${userInitials}</div>`
          : `<div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 text-xs font-semibold flex-shrink-0">E</div>`;
        
        const bubbleClass = role === 'user'
          ? 'gradient-brand text-white'
          : 'bg-slate-100 text-slate-800';
        
        const contentHtml = role === 'assistant' ? mdToHtml(content) : escapeHtml(content);
        
        div.innerHTML = role === 'user'
          ? `<div class="${bubbleClass} px-4 py-2.5 rounded-2xl rounded-tr-sm max-w-[80%] text-sm">${contentHtml}</div>${avatar}`
          : `${avatar}<div class="${bubbleClass} px-4 py-2.5 rounded-2xl rounded-tl-sm max-w-[80%] text-sm">${contentHtml}</div>`;
        
        faqMessages.appendChild(div);
        faqMessages.scrollTop = faqMessages.scrollHeight;
      }
    })();
  </script>
  
  <script>
    // Gestures Navigation - redirige a páginas individuales de cada gesto
    (function() {
      const gestureRoutes = {
        'write-article': '/gestos/escribir-articulo.php',
        'social-media': '/gestos/redes-sociales.php',
        'podcast-from-article': '/gestos/podcast-articulo.php'
      };
      
      const gestureCards = document.querySelectorAll('[data-gesture]');
      gestureCards.forEach(card => {
        card.addEventListener('click', () => {
          const gestureId = card.getAttribute('data-gesture');
          if (gestureRoutes[gestureId]) {
            window.location.href = gestureRoutes[gestureId];
          }
        });
      });
    })();
  </script>
  
  <!-- Bottom Navigation (móvil) -->
  <?php include __DIR__ . '/includes/bottom-nav.php'; ?>
  
  <script>
    // Sincronizar contenido del drawer móvil con sidebar desktop
    document.addEventListener('DOMContentLoaded', () => {
      // Detectar OS y actualizar hints de teclado
      const isMac = /Mac|iPod|iPhone|iPad/.test(navigator.platform);
      if (!isMac) {
        const hints = ['shortcut-hint-empty', 'shortcut-hint-chat'];
        hints.forEach(id => {
          const el = document.getElementById(id);
          if (el) el.textContent = 'Ctrl + Enter para enviar';
        });
      }

      // Sincronizar selectores de modelos (Solo Superadmin)
      const modelSelectEmpty = document.getElementById('model-select-empty');
      const modelSelectChat = document.getElementById('model-select-chat');
      if (modelSelectEmpty && modelSelectChat) {
        modelSelectEmpty.addEventListener('change', () => {
          modelSelectChat.value = modelSelectEmpty.value;
        });
        modelSelectChat.addEventListener('change', () => {
          modelSelectEmpty.value = modelSelectChat.value;
        });
      }

      const desktopSidebar = document.getElementById('conversations-sidebar');
      const mobileDrawerContent = document.getElementById('conversations-drawer-content');
      
      if (desktopSidebar && mobileDrawerContent) {
        // Clonar contenido de carpetas y conversaciones al drawer móvil
        const foldersSection = desktopSidebar.querySelector('.flex-1.overflow-y-auto');
        if (foldersSection) {
          mobileDrawerContent.innerHTML = foldersSection.innerHTML;
          // Forzar visibilidad de acciones (no hay hover en móvil)
          mobileDrawerContent.querySelectorAll('.group .opacity-0').forEach(el => {
            el.classList.remove('opacity-0');
            el.classList.add('opacity-100');
          });
        }
        
        // Observer para mantener sincronizado
        const observer = new MutationObserver(() => {
          if (foldersSection) {
            mobileDrawerContent.innerHTML = foldersSection.innerHTML;
            // Forzar visibilidad de acciones tras refrescar
            mobileDrawerContent.querySelectorAll('.group .opacity-0').forEach(el => {
              el.classList.remove('opacity-0');
              el.classList.add('opacity-100');
            });
          }
        });
        
        observer.observe(desktopSidebar, { childList: true, subtree: true });
        
        // Event delegation para clics en el drawer móvil
        mobileDrawerContent.addEventListener('click', (e) => {
          // Botón "Nueva carpeta"
          const newFolderBtnMobile = e.target.closest('#new-folder-btn');
          if (newFolderBtnMobile) {
            const desktopNewFolderBtn = desktopSidebar.querySelector('#new-folder-btn');
            if (desktopNewFolderBtn) desktopNewFolderBtn.click();
            return;
          }

          // Buscar si se hizo clic en una conversación
          const convItem = e.target.closest('[data-conv-id]');
          if (convItem) {
            const convId = convItem.getAttribute('data-conv-id');
            // ¿Se clicó un botón de acción dentro de la conversación?
            const actionBtn = e.target.closest('[data-action]');
            if (actionBtn) {
              const action = actionBtn.getAttribute('data-action');
              const desktopRow = desktopSidebar.querySelector(`[data-conv-id="${convId}"]`);
              if (desktopRow) {
                const desktopAction = desktopRow.querySelector(`[data-action="${action}"]`);
                if (desktopAction) {
                  e.preventDefault();
                  e.stopPropagation();
                  // No cerrar el drawer para acciones que no cambian de vista, excepto mover que abre modal
                  if (action === 'move') closeMobileDrawer('conversations-drawer');
                  desktopAction.click();
                }
              }
              return;
            }
            // Click en la conversación (abrir)
            const desktopConv = desktopSidebar.querySelector(`[data-conv-id="${convId}"]`);
            if (desktopConv) {
              closeMobileDrawer('conversations-drawer');
              // Click sobre el botón principal dentro de la fila
              const mainBtn = desktopConv.querySelector('[data-conv-id]');
              if (mainBtn) mainBtn.click(); else desktopConv.click();
            }
            return;
          }
          
          // Buscar si se hizo clic en una carpeta
          const folderItem = e.target.closest('[data-folder-id]');
          if (folderItem) {
            const folderId = folderItem.getAttribute('data-folder-id');
            // ¿Se clicó una acción de carpeta?
            const folderActionBtn = e.target.closest('[data-action-folder]');
            if (folderActionBtn) {
              const action = folderActionBtn.getAttribute('data-action-folder');
              const desktopFolder = desktopSidebar.querySelector(`[data-folder-id="${folderId}"]`);
              if (desktopFolder) {
                const desktopAction = desktopFolder.parentElement.querySelector(`[data-action-folder="${action}"]`);
                if (desktopAction) {
                  e.preventDefault();
                  e.stopPropagation();
                  desktopAction.click();
                }
              }
              return;
            }
            // Buscar y clickear la carpeta correspondiente en desktop
            const desktopFolder = desktopSidebar.querySelector(`[data-folder-id="${folderId}"]`);
            if (desktopFolder) {
              desktopFolder.click();
              // Refrescar contenido del drawer después del clic
              setTimeout(() => {
                if (foldersSection) {
                  mobileDrawerContent.innerHTML = foldersSection.innerHTML;
                  // Reaplicar visibilidad de acciones
                  mobileDrawerContent.querySelectorAll('.group .opacity-0').forEach(el => {
                    el.classList.remove('opacity-0');
                    el.classList.add('opacity-100');
                  });
                }
              }, 100);
            }
            return;
          }
        });
      }
      
      // Sincronizar botón nueva conversación móvil
      const mobileNewBtn = document.getElementById('mobile-new-conv-btn');
      const desktopNewBtn = document.getElementById('new-conv-btn');
      if (mobileNewBtn && desktopNewBtn) {
        mobileNewBtn.addEventListener('click', () => {
          closeMobileDrawer('conversations-drawer');
          desktopNewBtn.click();
        });
      }
    });
  </script>
</body>
</html>
