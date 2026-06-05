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
$userName = htmlspecialchars($user['first_name'] ?? 'there');

// Shared header configuration
$headerShowConvTitle = true;
$headerShowSearch = true;
$headerShowFaq = true;
$headerDrawerId = 'conversations-drawer';
$headerShowLogo = true;
?><!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/includes/head.php'; ?>
<body class="bg-mesh text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php include __DIR__ . '/includes/left-tabs.php'; ?>

    <!-- Conversations sidebar (desktop only) -->
    <aside id="conversations-sidebar" class="hidden lg:flex w-80 bg-white border-r border-slate-200 flex-col shadow-sm">
      <div class="p-5 border-b border-slate-200">
        <div class="flex items-center gap-3 mb-6">
          <img src="/assets/images/logo.png" alt="Claara" class="h-9">
        </div>
        <button id="new-conv-btn" class="w-full py-2.5 px-4 rounded-lg gradient-brand-btn text-[#2F3440] font-medium shadow-md hover:shadow-lg hover:opacity-90 transition-all duration-200 flex items-center justify-center gap-2">
          <span class="text-lg">+</span> New conversation
        </button>
      </div>
      <div class="flex-1 overflow-y-auto p-3">
        <!-- Folders -->
        <div class="mb-4">
          <div class="flex items-center justify-between mb-2 px-2">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Folders</div>
            <button id="new-folder-btn" class="p-1 text-slate-400 hover:text-[#B7C9F2] hover:bg-[#B7C9F2]/10 rounded transition-colors" title="New folder">
              <i class="iconoir-folder-plus text-sm"></i>
            </button>
          </div>
          <ul id="folder-list" class="space-y-1">
            <!-- "All" is always visible -->
            <li>
              <button data-folder-id="-1" class="folder-item w-full text-left p-2 rounded-lg transition-all duration-200 flex items-center gap-2 hover:bg-slate-50 group">
                <i class="iconoir-folder text-[#B7C9F2]"></i>
                <span class="flex-1 text-sm text-slate-700">All</span>
                <span class="text-xs text-slate-400" id="all-count">0</span>
              </button>
            </li>
            <!-- "No folder" -->
            <li>
              <button data-folder-id="0" class="folder-item w-full text-left p-2 rounded-lg transition-all duration-200 flex items-center gap-2 hover:bg-slate-50 group">
                <i class="iconoir-folder text-[#B7C9F2]"></i>
                <span class="flex-1 text-sm text-slate-700">No folder</span>
                <span class="text-xs text-slate-400" id="root-count">0</span>
              </button>
            </li>
            <!-- Dynamic folders are inserted here -->
          </ul>
        </div>
        
        <!-- Conversations -->
        <div>
          <div class="flex items-center justify-between mb-2 px-2">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Conversations</div>
            <select id="sort-select" class="text-xs border border-slate-200 rounded px-2 py-1 bg-white focus:outline-none focus:border-[#B7C9F2]">
              <option value="updated_at">Recent</option>
              <option value="favorite">Favorites</option>
              <option value="created_at">Created</option>
              <option value="title">Alphabetical</option>
            </select>
          </div>
          <ul id="conv-list" class="space-y-1">
            <li class="text-slate-400 text-sm px-3 py-2">(empty)</li>
          </ul>
        </div>
      </div>
    </aside>

    <!-- Mobile drawer for conversations -->
    <?php 
    $drawerId = 'conversations-drawer';
    $drawerTitle = 'Conversations';
    $drawerIcon = 'iconoir-chat-bubble';
    $drawerIconColor = 'text-[#B7C9F2]';
    $drawerShowNewButton = true;
    $drawerNewButtonId = 'mobile-new-conv-btn';
    $drawerNewButtonText = 'New conversation';
    include __DIR__ . '/includes/mobile-drawer.php'; 
    ?>

    <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <?php include __DIR__ . '/includes/header-unified.php'; ?>
      
      <!-- Scrollable messages area, with mobile padding for footer + bottom nav -->
      <section class="flex-1 overflow-auto bg-mesh relative pb-[140px] lg:pb-0" id="messages-container">
        <div id="context-warning" class="hidden mx-6 mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-start gap-3">
          <i class="iconoir-info-circle text-amber-600 text-lg mt-0.5"></i>
          <div class="flex-1 text-sm">
            <div class="font-medium text-amber-900">Long conversation</div>
            <div class="text-amber-700 mt-0.5">To keep performance stable, only the most recent messages are sent to the assistant. The full history remains saved.</div>
          </div>
        </div>
        <div id="empty-state" class="absolute inset-0 overflow-auto px-4 py-5 pb-36 sm:px-6 lg:px-8 lg:pb-8">
          <div class="empty-shell max-w-6xl mx-auto py-4 lg:py-8">
            
            <!-- Hero Input Section -->
            <div class="grid grid-cols-1 lg:grid-cols-[0.78fr_1.22fr] gap-6 lg:gap-10 items-end mb-8 lg:mb-10">
              <div class="text-left">
                <!-- Status indicator -->
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full glass border border-slate-200/50 shadow-sm mb-5">
                  <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
                  <span class="text-sm text-slate-600">Ready to help</span>
                </div>

                <h2 class="text-3xl lg:text-4xl font-bold tracking-tight text-slate-900 mb-3">
                  <span id="empty-greeting">Hi</span>, <span class="text-[#2F3440]"><?php echo $userName; ?></span>
                </h2>
                <p class="text-base text-slate-500 leading-relaxed max-w-xl">Start with a question, attach a file, or jump into a focused workspace.</p>
              </div>
              
              <!-- Main input -->
              <div class="empty-command bg-white rounded-[1.75rem] p-4 lg:p-5 border border-slate-200/80 max-w-3xl w-full lg:justify-self-end">
                <form id="chat-form-empty" class="w-full">
                  <!-- Attached files preview in empty state (multiple) -->
                  <div id="files-preview-empty" class="hidden mb-3 space-y-2">
                    <div id="files-list-empty" class="space-y-1"></div>
                    <button type="button" id="clear-all-files-empty" class="text-xs text-slate-400 hover:text-red-500 flex items-center gap-1">
                      <i class="iconoir-xmark"></i> Remove all
                    </button>
                  </div>
                  
                  <input type="file" id="file-input-empty" class="hidden" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.csv,.xls,.xlsx" multiple />
                  
                  <!-- Top row: textarea + submit button -->
                  <div class="flex items-start gap-3 mb-3">
                    <textarea id="chat-input-empty" rows="1" class="flex-1 min-w-0 bg-transparent border-0 px-1 py-1 text-[1.05rem] lg:text-lg text-slate-700 placeholder:text-slate-400 placeholder:italic focus:outline-none focus:ring-0 resize-none" placeholder="Ask Claara anything" style="min-height: 34px; max-height: 180px; overflow-y: hidden;"></textarea>
                    <button type="submit" class="w-11 h-11 flex items-center justify-center text-slate-500 hover:text-[#2F3440] hover:bg-[#B7C9F2]/15 active:scale-[0.98] rounded-2xl transition-smooth shrink-0" title="Send">
                      <i class="iconoir-arrow-up text-xl"></i>
                    </button>
                  </div>
                  
                  <div class="flex items-center justify-between px-1">
                    <!-- Bottom row: action buttons -->
                    <div class="flex items-center gap-1">
                      <button type="button" id="attach-btn-empty" class="p-2 text-slate-400 hover:text-[#B7C9F2] hover:bg-[#B7C9F2]/10 rounded-lg transition-smooth" title="Attach file (PDF, image, CSV, or Excel)">
                        <i class="iconoir-attachment text-lg"></i>
                      </button>
                      <button type="button" id="image-mode-btn-empty" class="<?php echo $hasImageGenAccess ? '' : 'hidden'; ?> p-2 text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-smooth" title="Generate image">
                        <i class="iconoir-media-image text-lg"></i>
                      </button>
                      <button type="button" id="web-search-btn-empty" class="p-2 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 rounded-lg transition-smooth" title="Search the web">
                        <i class="iconoir-globe text-lg"></i>
                      </button>
                      <?php if ($user['is_superadmin']): ?>
                      <select id="model-select-empty" class="ml-1 text-[10px] bg-slate-50 border border-slate-200 rounded-md px-2 py-1 text-slate-500 focus:outline-none focus:border-[#B7C9F2] transition-colors" title="Select model (Superadmin only)">
                        <option value="google/gemini-3-flash-preview">Loading models...</option>
                      </select>
                      <button type="button" id="manage-models-btn-empty" class="p-2 text-slate-400 hover:text-[#B7C9F2] hover:bg-cyan-50 rounded-lg transition-smooth" title="Manage models (Superadmin only)">
                        <i class="iconoir-settings text-lg"></i>
                      </button>
                      <?php endif; ?>
                    </div>
                    <span id="shortcut-hint-empty" class="text-[10px] text-slate-400 font-medium opacity-50 select-none pr-1">⌘ + Enter to send</span>
                  </div>
                  <div id="image-mode-files-warning-empty" class="hidden mt-2 px-1 text-xs text-amber-600 flex items-center gap-1.5">
                    <i class="iconoir-warning-triangle"></i>
                    <span>Files cannot be attached, dragged, or pasted in image mode.</span>
                  </div>
                </form>
              </div>
            </div>

            <!-- "Or" divider -->
            <div class="flex items-center gap-4 max-w-3xl mx-auto mb-4 lg:mb-5">
              <div class="flex-1 h-px bg-slate-200"></div>
              <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Or choose an option</span>
              <div class="flex-1 h-px bg-slate-200"></div>
            </div>

            <!-- Options grid: Voices and Gestures -->
            <div class="grid grid-cols-1 xl:grid-cols-[0.86fr_1.14fr] gap-3 lg:gap-4 max-w-5xl mx-auto">
              
              <!-- Voices -->
              <div class="empty-option-panel glass-strong rounded-3xl border border-slate-200/50 p-4 lg:p-5">
                <div class="flex items-center gap-3 mb-3">
                  <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-rose-500 to-[#2F3440] flex items-center justify-center shadow-md animate-float" style="animation-delay: 0s">
                    <i class="iconoir-voice-square text-xl text-white"></i>
                  </div>
                  <div>
                    <h3 class="text-base font-bold text-slate-900">Voices</h3>
                    <p class="text-xs text-slate-500">Specialized assistants</p>
                  </div>
                </div>
                
                <div class="space-y-2">
                  <?php if ($accessRepo->hasVoiceAccess($userId, 'lex')): ?>
                  <!-- Lex - Active -->
                  <button class="voice-option empty-action w-full p-3 bg-white/65 hover:bg-white border border-slate-200/80 hover:border-rose-300 rounded-2xl transition-smooth text-left group hover:shadow-md" data-voice="lex">
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-rose-500 to-pink-600 flex items-center justify-center text-white font-bold text-sm flex-shrink-0 shadow-sm group-hover:scale-110 transition-smooth">L</div>
                      <div class="flex-1 min-w-0">
                        <div class="font-semibold text-slate-800 group-hover:text-rose-600 transition-smooth">Lex</div>
                        <div class="text-xs text-slate-500">Your legal assistant</div>
                      </div>
                      <i class="iconoir-arrow-right text-slate-300 group-hover:text-rose-500 group-hover:translate-x-1 transition-smooth"></i>
                    </div>
                  </button>
                  <?php endif; ?>

                  <div class="w-full px-3 py-2 bg-white/35 border border-slate-200/70 rounded-2xl">
                    <div class="flex items-center justify-between gap-3 text-xs text-slate-400">
                      <span class="truncate">Operations and Knowledge</span>
                      <span class="px-2 py-0.5 bg-slate-100 rounded-full shrink-0">2 more soon</span>
                    </div>
                  </div>

                  <button id="view-all-voices" class="w-full p-2 mt-0.5 hover:bg-rose-50 border border-dashed border-slate-200 hover:border-rose-300 rounded-2xl transition-smooth text-center group">
                    <div class="flex items-center justify-center gap-2 text-sm font-medium text-slate-500 group-hover:text-rose-600 transition-smooth">
                      <span>View all voices</span>
                      <i class="iconoir-arrow-right group-hover:translate-x-1 transition-smooth"></i>
                    </div>
                  </button>
                </div>
              </div>

              <!-- Gestures -->
              <div class="empty-option-panel glass-strong rounded-3xl border border-slate-200/50 p-4 lg:p-5">
                <div class="flex items-center gap-3 mb-3">
                  <div class="w-10 h-10 rounded-2xl gradient-brand flex items-center justify-center shadow-md animate-float" style="animation-delay: 0.5s">
                    <i class="iconoir-magic-wand text-xl text-white"></i>
                  </div>
                  <div>
                    <h3 class="text-base font-bold text-slate-900">Gestures</h3>
                    <p class="text-xs text-slate-500">Quick actions</p>
                  </div>
                </div>
                
                <div class="space-y-2">
                  <?php if ($accessRepo->hasGestureAccess($userId, 'write-article')): ?>
                  <button class="gesture-option empty-action w-full p-3 bg-white/65 hover:bg-white border border-slate-200/80 hover:border-[#B7C9F2]/50 rounded-2xl transition-smooth text-left group hover:shadow-md" data-gesture="write-article">
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#B7C9F2] to-[#2F3440] flex items-center justify-center flex-shrink-0 shadow-sm group-hover:scale-110 transition-smooth">
                        <i class="iconoir-page-edit text-base text-white"></i>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="font-semibold text-slate-800 group-hover:text-[#2F3440] transition-smooth">Write Content</div>
                        <div class="text-xs text-slate-500">Blogs, updates, press notes</div>
                      </div>
                      <i class="iconoir-arrow-right text-slate-300 group-hover:text-[#B7C9F2] group-hover:translate-x-1 transition-smooth"></i>
                    </div>
                  </button>
                  <?php endif; ?>

                  <?php if ($accessRepo->hasGestureAccess($userId, 'social-media')): ?>
                  <button class="gesture-option empty-action w-full p-3 bg-white/65 hover:bg-white border border-slate-200/80 hover:border-slate-300 rounded-2xl transition-smooth text-left group hover:shadow-md" data-gesture="social-media">
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-slate-500 to-[#2F3440] flex items-center justify-center flex-shrink-0 shadow-sm group-hover:scale-110 transition-smooth">
                        <i class="iconoir-send-diagonal text-base text-white"></i>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="font-semibold text-slate-800 group-hover:text-[#2F3440] transition-smooth">Social Media</div>
                        <div class="text-xs text-slate-500">Posts for social channels</div>
                      </div>
                      <i class="iconoir-arrow-right text-slate-300 group-hover:text-[#2F3440] group-hover:translate-x-1 transition-smooth"></i>
                    </div>
                  </button>
                  <?php endif; ?>

                  <?php if ($accessRepo->hasGestureAccess($userId, 'podcast-from-article')): ?>
                  <button class="gesture-option empty-action w-full p-3 bg-white/65 hover:bg-white border border-slate-200/80 hover:border-rose-400/50 rounded-2xl transition-smooth text-left group hover:shadow-md" data-gesture="podcast-from-article">
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-rose-500 to-orange-500 flex items-center justify-center flex-shrink-0 shadow-sm group-hover:scale-110 transition-smooth">
                        <i class="iconoir-podcast text-base text-white"></i>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="font-semibold text-slate-800 group-hover:text-rose-600 transition-smooth">Article to Podcast</div>
                        <div class="text-xs text-slate-500">Audio with 2 AI voices</div>
                      </div>
                      <i class="iconoir-arrow-right text-slate-300 group-hover:text-rose-500 group-hover:translate-x-1 transition-smooth"></i>
                    </div>
                  </button>
                  <?php endif; ?>

                  

                  <button id="view-all-gestures" class="w-full p-2 mt-0.5 hover:bg-[#B7C9F2]/5 border border-dashed border-slate-200 hover:border-[#B7C9F2]/50 rounded-2xl transition-smooth text-center group">
                    <div class="flex items-center justify-center gap-2 text-sm font-medium text-slate-500 group-hover:text-[#B7C9F2] transition-smooth">
                      <span>View all gestures</span>
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
            <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 text-sm font-semibold flex-shrink-0">i</div>
            <div class="bg-white border border-slate-200 px-5 py-3.5 rounded-2xl rounded-tl-sm shadow-sm">
              <span class="streaming-indicator flex gap-1">
                <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce"></span>
                <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0.4s"></span>
              </span>
            </div>
          </div>
        </div>
        <div id="drop-overlay" class="hidden fixed inset-0 z-[65] pointer-events-none p-4 lg:p-8">
          <div class="w-full h-full rounded-2xl border-2 border-dashed border-[#B7C9F2] bg-[#B7C9F2]/10 flex items-center justify-center">
            <div class="px-5 py-3 rounded-xl bg-white/90 backdrop-blur-sm shadow-sm text-center">
              <div class="text-sm font-medium text-[#2F3440]">Drop files here to attach them</div>
              <div class="mt-1 text-[11px] text-slate-400">PDF, PNG, JPG, GIF, WEBP, CSV, XLS, XLSX (max. 30MB)</div>
            </div>
          </div>
        </div>
        <!-- Scroll-to-bottom floating button (positioned by JS inside the conversation viewport) -->
        <button id="scroll-to-bottom" class="scroll-bottom-btn fixed z-40 w-10 h-10 rounded-full bg-white border border-slate-200 shadow-lg text-slate-500 hover:text-[#2F3440] hover:shadow-xl transition-all" title="Scroll to latest" aria-label="Scroll to latest">
        <i class="iconoir-arrow-down text-xl"></i>
        </button>
      </section>
      <!-- Chat footer: fixed on mobile above the bottom nav -->
      <footer id="chat-footer" class="hidden fixed lg:relative bottom-16 lg:bottom-0 left-0 right-0 p-3 lg:p-4 bg-gradient-to-t from-white via-white to-white/80 z-40">
        <form id="chat-form" class="max-w-3xl mx-auto">
          <div class="bg-white rounded-2xl lg:rounded-3xl p-3 lg:p-4 border border-slate-200 shadow-lg">
            <!-- Attached files preview (multiple) -->
            <div id="files-preview" class="hidden mb-3 space-y-2">
              <div id="files-list" class="space-y-1"></div>
              <button type="button" id="clear-all-files" class="text-xs text-slate-400 hover:text-red-500 flex items-center gap-1">
                <i class="iconoir-xmark"></i> Remove all
              </button>
            </div>
            
            <input type="file" id="file-input" class="hidden" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.csv,.xls,.xlsx" multiple />
            
            <!-- Top row: textarea + submit button -->
            <div class="flex items-start gap-3 mb-2">
              <textarea id="chat-input" rows="1" class="flex-1 min-w-0 bg-transparent border-0 px-1 py-1 text-base text-slate-700 placeholder:text-slate-400 placeholder:italic focus:outline-none focus:ring-0 resize-none" placeholder="Write a message..." style="min-height: 28px; max-height: 160px; overflow-y: hidden;"></textarea>
              <button type="submit" class="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-[#B7C9F2] hover:bg-[#B7C9F2]/10 rounded-xl transition-smooth shrink-0" title="Send">
                <i class="iconoir-arrow-up text-xl"></i>
              </button>
            </div>
            
            <div class="flex items-center justify-between px-1">
              <!-- Bottom row: action buttons -->
              <div class="flex items-center gap-1">
                <button type="button" id="attach-btn" class="p-2 text-slate-400 hover:text-[#B7C9F2] hover:bg-[#B7C9F2]/10 rounded-lg transition-smooth" title="Attach file (PDF, image, CSV, or Excel)">
                  <i class="iconoir-attachment text-lg"></i>
                </button>
                <button type="button" id="image-mode-btn" class="<?php echo $hasImageGenAccess ? '' : 'hidden'; ?> p-2 text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-smooth" title="Generate image">
                  <i class="iconoir-media-image text-lg"></i>
                </button>
                <button type="button" id="web-search-btn" class="p-2 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 rounded-lg transition-smooth" title="Search the web">
                  <i class="iconoir-globe text-lg"></i>
                </button>
                <?php if ($user['is_superadmin']): ?>
                <select id="model-select-chat" class="ml-1 text-[10px] bg-slate-50 border border-slate-200 rounded-md px-2 py-1 text-slate-500 focus:outline-none focus:border-[#B7C9F2] transition-colors" title="Select model (Superadmin only)">
                  <option value="google/gemini-3-flash-preview">Loading models...</option>
                </select>
                <button type="button" id="manage-models-btn-chat" class="p-2 text-slate-400 hover:text-[#B7C9F2] hover:bg-cyan-50 rounded-lg transition-smooth" title="Manage models (Superadmin only)">
                  <i class="iconoir-settings text-lg"></i>
                </button>
                <?php endif; ?>
              </div>
              <span id="shortcut-hint-chat" class="text-[10px] text-slate-400 font-medium opacity-50 select-none pr-1">⌘ + Enter to send</span>
            </div>
            <div id="image-mode-files-warning-chat" class="hidden mt-2 px-1 text-xs text-amber-600 flex items-center gap-1.5">
              <i class="iconoir-warning-triangle"></i>
              <span>Files cannot be attached, dragged, or pasted in image mode.</span>
            </div>
          </div>
        </form>
      </footer>
    </main>
  </div>
  
  <!-- Floating selection toolbar for partial editing (desktop) -->
  <div id="selection-toolbar" class="fixed z-50 hidden md:block">
    <div class="bg-slate-900 text-white rounded-xl shadow-2xl px-2 py-1.5 flex items-center gap-1">
      <button id="selection-edit-btn" class="flex items-center gap-1.5 px-3 py-1.5 hover:bg-white/10 rounded-lg transition-colors text-sm font-medium">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
        </svg>
        Edit
      </button>
      <div class="w-px h-5 bg-white/20"></div>
      <button id="selection-regenerate-btn" class="flex items-center gap-1.5 px-3 py-1.5 hover:bg-white/10 rounded-lg transition-colors text-sm font-medium">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Regenerate
      </button>
    </div>
    <div class="absolute left-1/2 -translate-x-1/2 -bottom-1.5 w-3 h-3 bg-slate-900 rotate-45"></div>
  </div>
  
  <!-- Anchored selection bar for mobile -->
  <div id="selection-bar-mobile" class="bg-slate-900 text-white shadow-[0_-8px_30px_rgba(0,0,0,0.5)] border-t border-slate-700 transition-all duration-300" style="position: fixed; bottom: 0; left: 0; right: 0; z-index: 2147483647; display: none; transform: translateY(100%);">
    <div class="px-4 py-4 pb-10"> <!-- Padding extra abajo para notch de iOS -->
      <div class="flex items-center gap-3">
        <!-- Selected text (truncated) -->
        <div class="flex-1 min-w-0">
          <div class="text-[10px] uppercase tracking-wider text-slate-400 mb-1 font-bold">Active selection</div>
          <div id="mobile-selection-preview" class="text-sm truncate opacity-90 italic"></div>
        </div>
        <!-- Botones -->
        <div class="flex items-center gap-2 flex-shrink-0">
          <button id="mobile-edit-btn" class="flex items-center gap-1.5 px-3 py-2 bg-white/10 active:bg-white/20 rounded-xl transition-colors text-sm font-semibold">
            <i class="iconoir-edit-pencil text-base"></i>
            Edit
          </button>
          <button id="mobile-regenerate-btn" class="flex items-center gap-1.5 px-3 py-2 bg-[#B7C9F2] active:bg-[#FF8B73] rounded-xl transition-colors text-sm font-semibold shadow-lg shadow-[#B7C9F2]/30">
            <i class="iconoir-refresh text-base"></i>
            Regen
          </button>
          <button id="mobile-close-selection" class="p-2 text-slate-400 active:text-white active:bg-white/10 rounded-full transition-colors">
            <i class="iconoir-xmark text-xl"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Selection edit modal -->
  <div id="selection-edit-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
      <div class="p-5 border-b border-slate-200">
        <h3 id="edit-modal-title" class="text-lg font-semibold text-slate-900">Edit selection</h3>
        <p class="text-sm text-slate-500 mt-1">Tell the AI how you want this part changed</p>
      </div>
      
      <div class="p-5 space-y-4">
        <!-- Preview del texto seleccionado -->
        <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
          <div class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-2">Selected text</div>
          <div id="edit-modal-selection" class="text-sm text-slate-700 max-h-24 overflow-y-auto"></div>
        </div>
        
        <!-- Input de instrucciones -->
        <div>
          <label for="edit-modal-instructions" class="block text-sm font-medium text-slate-700 mb-2">
            Your instructions
          </label>
          <textarea 
            id="edit-modal-instructions" 
            class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-[#B7C9F2] focus:ring-2 focus:ring-[#B7C9F2]/20 transition-all text-sm resize-none"
            rows="3"
            placeholder="Example: Make it more formal, add more detail about..., simplify this explanation..."
          ></textarea>
        </div>
      </div>
      
      <div class="p-5 border-t border-slate-200 flex items-center justify-end gap-3 bg-slate-50">
        <button id="edit-modal-cancel" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors">
          Cancel
        </button>
        <button id="edit-modal-submit" class="px-5 py-2 text-sm font-medium text-white gradient-brand-btn rounded-lg shadow-md hover:shadow-lg hover:opacity-90 transition-all flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
          </svg>
          Apply changes
        </button>
      </div>
    </div>
  </div>
  
  <!-- Lightbox for generated images -->
  <div id="image-lightbox" class="hidden fixed inset-0 bg-black/90 backdrop-blur-sm z-[60] flex items-center justify-center p-4" onclick="closeLightbox()">
    <button onclick="closeLightbox()" class="absolute top-4 right-4 p-2 text-white/70 hover:text-white hover:bg-white/10 rounded-full transition-colors">
      <i class="iconoir-xmark text-2xl"></i>
    </button>
    <img id="lightbox-img" src="" alt="Expanded image" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl" onclick="event.stopPropagation()">
  </div>
  
  <!-- Move to folder modal -->
  <div id="move-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full max-h-[80vh] flex flex-col">
      <!-- Header -->
      <div class="p-6 border-b border-slate-200 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl gradient-brand flex items-center justify-center">
            <i class="iconoir-folder-settings text-xl text-white"></i>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-slate-900">Move conversation</h3>
            <p class="text-xs text-slate-500" id="move-conv-title">Choose the destination folder</p>
          </div>
        </div>
        <button id="close-move-modal" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
          <i class="iconoir-xmark text-xl"></i>
        </button>
      </div>
      
      <!-- Body - folder list -->
      <div class="flex-1 overflow-y-auto p-6">
        <div class="space-y-2" id="folder-options">
          <!-- "No folder" option -->
          <button data-target-folder="0" class="folder-option w-full p-4 bg-slate-50 hover:bg-[#B7C9F2]/5 border-2 border-slate-200 hover:border-[#B7C9F2] rounded-xl transition-all text-left group">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg bg-slate-200 flex items-center justify-center flex-shrink-0 group-hover:bg-[#B7C9F2]/10">
                <i class="iconoir-folder-minus text-xl text-slate-500 group-hover:text-[#B7C9F2]"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-slate-800 group-hover:text-[#B7C9F2] transition-colors">No folder</div>
                <div class="text-xs text-slate-500">Move to root</div>
              </div>
              <i class="iconoir-nav-arrow-right text-slate-300 group-hover:text-[#B7C9F2] transition-colors"></i>
            </div>
          </button>
          
          <!-- Dynamic folders are inserted here -->
        </div>
        
        <div id="empty-folders" class="hidden text-center py-8 text-slate-400 text-sm">
          <i class="iconoir-folder text-4xl mb-2"></i>
          <p>You do not have any folders yet</p>
        </div>
      </div>
      
      <!-- Footer -->
      <div class="p-6 border-t border-slate-200 flex gap-3">
        <button id="cancel-move" class="flex-1 px-4 py-2.5 border-2 border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors">
          Cancel
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

    // Scroll-to-bottom floating button
    const scrollToBottomBtn = document.getElementById('scroll-to-bottom');
    if (scrollToBottomBtn && messagesContainer) {
      const isNearBottom = () => {
        const remaining = messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight;
        return remaining <= 120;
      };
      const positionScrollBtn = () => {
        const containerRect = messagesContainer.getBoundingClientRect();
        const footerRect = chatFooter && !chatFooter.classList.contains('hidden') ? chatFooter.getBoundingClientRect() : null;
        const right = Math.max(16, window.innerWidth - containerRect.right + 24);
        const bottomLimit = footerRect ? window.innerHeight - footerRect.top + 16 : window.innerHeight - containerRect.bottom + 24;
        scrollToBottomBtn.style.right = `${right}px`;
        scrollToBottomBtn.style.bottom = `${Math.max(24, bottomLimit)}px`;
      };
      const updateScrollBtn = () => {
        const inChat = !messagesEl.classList.contains('hidden');
        positionScrollBtn();
        scrollToBottomBtn.classList.toggle('is-visible', inChat && !isNearBottom());
      };
      messagesContainer.addEventListener('scroll', updateScrollBtn, { passive: true });
      const scrollBtnObserver = new MutationObserver(updateScrollBtn);
      scrollBtnObserver.observe(messagesEl, { childList: true, subtree: true, characterData: true });
      window.addEventListener('resize', updateScrollBtn);
      scrollToBottomBtn.addEventListener('click', () => {
        messagesContainer.scrollTo({ top: messagesContainer.scrollHeight, behavior: 'smooth' });
        updateScrollBtn();
      });
      updateScrollBtn();
    }

    function handleCommandEnter(e, form) {
      if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
        e.preventDefault();
        form.dispatchEvent(new Event('submit'));
      }
    }

    inputEl.addEventListener('keydown', (e) => handleCommandEnter(e, formEl));
    inputEmptyEl.addEventListener('keydown', (e) => handleCommandEnter(e, formEmptyEl));

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
    
    // Multiple-file elements
    const filesPreview = document.getElementById('files-preview');
    const filesList = document.getElementById('files-list');
    const clearAllFilesBtn = document.getElementById('clear-all-files');
    const filesPreviewEmpty = document.getElementById('files-preview-empty');
    const filesListEmpty = document.getElementById('files-list-empty');
    const clearAllFilesEmptyBtn = document.getElementById('clear-all-files-empty');
    const dropOverlay = document.getElementById('drop-overlay');
    const imageModeFilesWarningChat = document.getElementById('image-mode-files-warning-chat');
    const imageModeFilesWarningEmpty = document.getElementById('image-mode-files-warning-empty');

    let csrf = null;
    let currentConversationId = null;
    let emptyConversationId = null; // id for a conversation with no messages yet
    let currentUser = null;
    let currentConvTitle = null;
    let currentFiles = []; // current attached files
    let currentFilesEmpty = []; // attached files in empty state
    let currentFolderId = -1; // -1 = all, 0 = no folder, >0 = specific folder
    let allFolders = []; // folder cache
    let conversationToMove = null; // conversation being moved
    let imageMode = false; // image generation mode
    let webSearchMode = false; // web search mode
    let capabilityRouteIndex = new Map();
    
    // Streaming state
    let isGenerating = false;
    let abortController = null;
    let currentStreamingBubble = null;
    let currentStreamingMessageId = null;
    
    // Selection state for partial regeneration
    let selectedText = null;
    let selectedMessageId = null;
    let dragCounter = 0;

    loadCapabilityCatalog();

    const MAX_FILE_SIZE = 30 * 1024 * 1024;
    const VALID_FILE_TYPES = [
      'application/pdf',
      'image/png',
      'image/jpeg',
      'image/gif',
      'image/webp',
      'text/csv',
      'application/vnd.ms-excel',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];

    function showImageModeAttachmentWarning() {
      const warningId = 'image-mode-drop-paste-warning';
      const existing = document.getElementById(warningId);
      if (existing) {
        existing.remove();
      }

      const warning = document.createElement('div');
      warning.id = warningId;
      warning.className = 'fixed top-20 left-1/2 -translate-x-1/2 bg-amber-500 text-white px-4 py-2 rounded-xl shadow-lg z-[70] text-sm flex items-center gap-2';
      warning.innerHTML = '<i class="iconoir-warning-triangle"></i><span>Disable image mode to attach files.</span>';
      document.body.appendChild(warning);

      setTimeout(() => {
        warning.style.opacity = '0';
        warning.style.transition = 'opacity 0.3s';
        setTimeout(() => warning.remove(), 300);
      }, 2200);
    }

    function isEmptyStateVisible() {
      return !emptyState.classList.contains('hidden');
    }

    function updateEmptyGreeting() {
      const greetingEl = document.getElementById('empty-greeting');
      if (!greetingEl) return;

      const hour = new Date().getHours();
      if (hour < 12) {
        greetingEl.textContent = 'Good morning';
      } else if (hour < 18) {
        greetingEl.textContent = 'Good afternoon';
      } else {
        greetingEl.textContent = 'Good evening';
      }
    }

    function focusEmptyComposer() {
      updateEmptyGreeting();
      if (!inputEmptyEl || !isEmptyStateVisible()) return;
      if (!window.matchMedia('(min-width: 768px)').matches) return;
      window.requestAnimationFrame(() => inputEmptyEl.focus({ preventScroll: true }));
    }
    window.updateEmptyGreeting = updateEmptyGreeting;
    window.focusEmptyComposer = focusEmptyComposer;

    function validateAndAddFiles(files, targetArray, renderFn) {
      let addedCount = 0;
      for (const file of files) {
        if (file.size > MAX_FILE_SIZE) {
          alert(`The file "${file.name}" is too large. Maximum size is 30MB.`);
          continue;
        }
        if (!VALID_FILE_TYPES.includes(file.type)) {
          alert(`The file "${file.name}" is not a supported type.`);
          continue;
        }
        targetArray.push(file);
        addedCount++;
      }

      if (addedCount > 0) {
        renderFn();
      }

      return addedCount;
    }

    function addFilesToActiveComposer(files) {
      if (imageMode) {
        showImageModeAttachmentWarning();
        return;
      }

      if (isEmptyStateVisible()) {
        validateAndAddFiles(files, currentFilesEmpty, renderFilesPreviewEmpty);
      } else {
        validateAndAddFiles(files, currentFiles, renderFilesPreview);
      }
    }

    function hasDataTransferFiles(event) {
      const types = event.dataTransfer?.types;
      if (!types) return false;
      return Array.from(types).includes('Files');
    }

    function showDropOverlay() {
      if (!dropOverlay) return;
      dropOverlay.classList.remove('hidden');
    }

    function hideDropOverlay() {
      if (!dropOverlay) return;
      dropOverlay.classList.add('hidden');
    }

    function getClipboardFiles(event) {
      const files = [];
      const clipboard = event.clipboardData;
      if (!clipboard) return files;

      if (clipboard.items && clipboard.items.length > 0) {
        for (const item of clipboard.items) {
          if (item.kind === 'file') {
            const file = item.getAsFile();
            if (file) files.push(file);
          }
        }
      }

      if (files.length === 0 && clipboard.files && clipboard.files.length > 0) {
        files.push(...Array.from(clipboard.files));
      }

      return files;
    }

    function handleComposerPaste(event) {
      const files = getClipboardFiles(event);
      if (files.length === 0) return;

      event.preventDefault();
      addFilesToActiveComposer(files);
    }

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
      focusEmptyComposer();
    }

    // Delete empty conversations to avoid accumulation.
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
          console.warn('Error cleaning empty conversation:', e);
        }
      }
    }

    // Clean empty conversation when leaving the page.
    window.addEventListener('beforeunload', () => {
      if (emptyConversationId) {
        // Use sendBeacon for an async request that survives page close.
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

      // Fenced code blocks: ```lang\n...```  (protect from other rules)
      const codeBlocks = [];
      s = s.replace(/```([\w-]*)\r?\n([\s\S]*?)```/g, function(_m, lang, code){
        const idx = codeBlocks.length;
        const trimmed = code.replace(/\n$/, '');
        codeBlocks.push({ lang: (lang || '').trim(), code: trimmed });
        return `%%CODEBLOCK${idx}%%`;
      });

      // Markdown links: [text](url)
      s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-cyan-600 hover:underline break-all">$1</a>');

      // Auto-links: URLs not already in an <a> tag
      // This is a simple regex that avoids matching URLs already inside href="..."
      s = s.replace(/(?<!href=")(https?:\/\/[^\s<]+)/g, function(url) {
        // If the URL ends with a closing paren that wasn't matched in the markdown link regex, remove it
        // This is a common issue with auto-linking at the end of a sentence in parens
        let cleanUrl = url.replace(/[\)\.,;:\!\?]$/, '');
        let suffix = url.substring(cleanUrl.length);
        return `<a href="${cleanUrl}" target="_blank" rel="noopener noreferrer" class="text-cyan-600 hover:underline break-all">${cleanUrl}</a>${suffix}`;
      });

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

      // Restore fenced code blocks (code is already HTML-escaped)
      s = s.replace(/%%CODEBLOCK(\d+)%%/g, function(_m, i){
        const block = codeBlocks[parseInt(i, 10)];
        if (!block) return '';
        const label = block.lang ? `<span class="code-lang">${escapeHtml(block.lang)}</span>` : '';
        return `<div class="code-block">${label}<pre><code>${block.code}</code></pre></div>`;
      });

      return s;
    }

    async function loadCapabilityCatalog() {
      try {
        const data = await api('/api/capabilities/catalog.php');
        const catalog = data.catalog || {};
        const items = [
          ...(catalog.voices || []).map(item => ({ ...item, type: 'voice' })),
          ...(catalog.gestures || []).map(item => ({ ...item, type: 'gesture' })),
        ];

        capabilityRouteIndex = new Map();
        items.forEach(item => {
          if (!item.route) return;
          capabilityRouteIndex.set(normalizeCapabilityRoute(item.route), item);
        });
      } catch (error) {
        console.warn('Could not load capability catalog:', error);
      }
    }

    function normalizeCapabilityRoute(route) {
      return String(route || '').trim().replace(/[*_~`"'.,;:!?]+$/g, '');
    }

    function extractCapabilityRoutes(content) {
      const routes = new Set();
      const text = String(content || '');
      const regex = /\/(?:voices|gestos)\/[^\s`)<\]*_~"']+/g;
      let match;

      while ((match = regex.exec(text)) !== null) {
        routes.add(normalizeCapabilityRoute(match[0]));
      }

      return Array.from(routes).filter(route => route.startsWith('/voices/') || route.startsWith('/gestos/'));
    }

    function capabilityIcon(item, route) {
      if (item?.icon) return item.icon;
      return route.startsWith('/voices/') ? 'iconoir-voice-square' : 'iconoir-magic-wand';
    }

    function capabilityLabel(item, route) {
      if (item?.name) return item.name;
      if (route.startsWith('/voices/')) return 'Open voice';
      return 'Open gesture';
    }

    function capabilityMeta(item, route) {
      if (item?.type === 'voice' || route.startsWith('/voices/')) {
        return item?.role || 'Specialized voice';
      }
      return 'Guided workflow';
    }

    function buildCapabilityAction(route) {
      route = normalizeCapabilityRoute(route);
      const item = capabilityRouteIndex.get(route);
      const link = document.createElement('a');
      link.href = route;
      link.className = 'capability-action';
      link.dataset.capabilityRoute = route;
      link.dataset.capabilityType = item?.type || (route.startsWith('/voices/') ? 'voice' : 'gesture');
      const inferredVoiceSlug = route.startsWith('/voices/')
        ? (route.match(/[?&]voice=([^&]+)/)?.[1] || route.match(/\/voices\/([^\/?.]+)\.php/)?.[1] || '')
        : '';
      if (item?.slug || inferredVoiceSlug) {
        link.dataset.capabilitySlug = item?.slug || decodeURIComponent(inferredVoiceSlug);
      }
      link.innerHTML = `
        <span class="capability-action-icon"><i class="${escapeHtml(capabilityIcon(item, route))}"></i></span>
        <span class="capability-action-copy">
          <span class="capability-action-label">${escapeHtml(link.dataset.capabilityType === 'voice' ? `Ask ${capabilityLabel(item, route)}` : capabilityLabel(item, route))}</span>
          <span class="capability-action-meta">${escapeHtml(capabilityMeta(item, route))}</span>
        </span>
        <i class="iconoir-arrow-right capability-action-arrow"></i>
      `;
      if (link.dataset.capabilityType === 'voice') {
        link.addEventListener('click', handleVoiceCapabilityClick);
      }
      return link;
    }

    function getMessageWrapFromElement(element) {
      return element.closest('[data-role="assistant"], [data-role="user"]')?.closest('.group.flex.flex-col')
        || element.closest('.group.flex.flex-col');
    }

    function getPreviousUserPrompt(element) {
      let wrap = getMessageWrapFromElement(element);
      while (wrap && wrap.previousElementSibling) {
        wrap = wrap.previousElementSibling;
        if (wrap.dataset.role === 'user') {
          const bubble = wrap.querySelector('.text-conversation');
          return ((bubble ? bubble.textContent : wrap.textContent) || '').trim();
        }
      }
      return '';
    }

    async function handleVoiceCapabilityClick(event) {
      event.preventDefault();
      const button = event.currentTarget;
      if (button.dataset.loading === '1' || isGenerating) return;

      const voiceSlug = button.dataset.capabilitySlug;
      const prompt = getPreviousUserPrompt(button);
      if (!voiceSlug || !prompt || !currentConversationId) {
        window.location.href = button.href;
        return;
      }

      button.dataset.loading = '1';
      button.classList.add('is-loading');
      const label = button.querySelector('.capability-action-label');
      const originalLabel = label ? label.textContent : '';
      if (label) label.textContent = 'Asking...';

      isGenerating = true;
      const { bubble } = append('assistant', '', null, [], null, { isStreaming: true });
      updateStreamingMessage(bubble, 'Asking the specialized voice...');

      try {
        const result = await api('/api/capabilities/voice-query.php', {
          method: 'POST',
          body: {
            conversation_id: currentConversationId,
            voice_slug: voiceSlug,
            message: prompt
          }
        });

        if (!result.success || !result.message) {
          throw new Error('The voice did not return a response');
        }

        finalizeStreamingMessage(bubble, result.message.content, null, null, result.message.id);
        await loadConversations();
      } catch (error) {
        bubble.innerHTML = `<span class="text-red-500">Error: ${escapeHtml(error.message)}</span>`;
      } finally {
        isGenerating = false;
        button.dataset.loading = '0';
        button.classList.remove('is-loading');
        if (label) label.textContent = originalLabel;
      }
    }

    function routeAppearsInNode(node, route) {
      return normalizeCapabilityRoute(node.textContent || '').includes(route);
    }

    function isRouteOnlyText(text, route) {
      const cleaned = String(text || '')
        .replace(/route\s*:/ig, '')
        .replace(/ruta\s*:/ig, '')
        .replace(/[*_~`\-•]/g, '')
        .replace(/\s+/g, ' ')
        .trim();

      return normalizeCapabilityRoute(cleaned) === route;
    }

    function hideCapabilityRouteText(containerEl, route) {
      const candidates = Array.from(containerEl.querySelectorAll('li, p, div, code, a'))
        .filter(node => !node.closest('.capability-actions') && routeAppearsInNode(node, route));

      for (const node of candidates) {
        if (isRouteOnlyText(node.textContent, route)) {
          node.classList.add('hidden');
          return;
        }
      }

      const walker = document.createTreeWalker(containerEl, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
          if (node.parentElement?.closest('.capability-actions')) {
            return NodeFilter.FILTER_REJECT;
          }
          return normalizeCapabilityRoute(node.textContent || '').includes(route)
            ? NodeFilter.FILTER_ACCEPT
            : NodeFilter.FILTER_SKIP;
        }
      });
      const textNode = walker.nextNode();
      if (!textNode) return;

      textNode.textContent = textNode.textContent
        .replace(new RegExp(`(?:[*_~\\-•]\\s*)?(?:Route|Ruta)\\s*:\\s*${escapeRegExp(route)}[*_~]*`, 'gi'), '')
        .replace(route, '')
        .replace(/\s{2,}/g, ' ')
        .trim();
    }

    function escapeRegExp(value) {
      return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function findCapabilityInsertionTarget(containerEl, route) {
      const directNodes = Array.from(containerEl.childNodes);
      for (const node of directNodes) {
        if (node.nodeType === Node.TEXT_NODE) {
          if (normalizeCapabilityRoute(node.textContent || '').includes(route)) {
            return node.nextSibling ? { mode: 'before', ref: node.nextSibling } : { mode: 'append', ref: containerEl };
          }
          continue;
        }

        if (node.nodeType !== Node.ELEMENT_NODE || node.classList?.contains('capability-actions')) {
          continue;
        }

        if (routeAppearsInNode(node, route)) {
          return { mode: 'after', ref: node };
        }
      }

      const nested = Array.from(containerEl.querySelectorAll('p, li, blockquote, div, h1, h2, h3, code, a'))
        .filter(node => !node.closest('.capability-actions'));
      for (const node of nested) {
        if (routeAppearsInNode(node, route)) {
          return { mode: 'after', ref: node };
        }
      }

      return { mode: 'append', ref: containerEl };
    }

    function insertCapabilityAction(containerEl, route) {
      const actionWrap = document.createElement('div');
      actionWrap.className = 'capability-actions capability-actions-inline';
      actionWrap.appendChild(buildCapabilityAction(route));

      const target = findCapabilityInsertionTarget(containerEl, route);
      hideCapabilityRouteText(containerEl, route);
      if (target.mode === 'after') {
        target.ref.insertAdjacentElement('afterend', actionWrap);
      } else if (target.mode === 'before') {
        target.ref.parentNode.insertBefore(actionWrap, target.ref);
      } else {
        target.ref.appendChild(actionWrap);
      }
    }

    function enhanceCapabilityActions(containerEl, content) {
      if (!containerEl) return;
      containerEl.querySelectorAll('.capability-actions').forEach(existing => existing.remove());
      const routes = extractCapabilityRoutes(content);
      if (!routes.length) return;

      routes.slice(0, 4).forEach(route => insertCapabilityAction(containerEl, route));
    }

    // Add a "copy" button to each fenced code block inside a rendered container
    function enhanceCodeBlocks(containerEl){
      if (!containerEl) return;
      containerEl.querySelectorAll('.code-block:not([data-enhanced])').forEach(block => {
        block.dataset.enhanced = '1';
        const codeEl = block.querySelector('code');
        if (!codeEl) return;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'code-copy-btn';
        btn.innerHTML = '<i class="iconoir-copy"></i>';
        btn.title = 'Copy code';
        btn.addEventListener('click', async () => {
          try {
            await navigator.clipboard.writeText(codeEl.innerText);
            btn.innerHTML = '<i class="iconoir-check"></i>';
            setTimeout(() => { btn.innerHTML = '<i class="iconoir-copy"></i>'; }, 1500);
          } catch (e) {}
        });
        block.appendChild(btn);
      });
    }

    // Build per-message action bar (copy + regenerate) for assistant messages
    function buildMessageActions(bubble, content, messageId){
      if (!bubble) return;
      const msgContainer = bubble.parentElement;
      const wrap = msgContainer ? msgContainer.parentElement : null;
      if (!wrap) return;
      wrap.classList.add('group');

      // Remove a previous actions bar (e.g. after regenerate)
      const existing = wrap.querySelector('.msg-actions');
      if (existing) existing.remove();

      const cleanContent = (content || '').replace(/\[DOC_START\]|\[DOC_END\]|\[DOWNLOAD_INTENT\]/g, '').trim();

      const actions = document.createElement('div');
      actions.className = 'msg-actions flex items-center gap-1 mt-1 ml-12';

      // Copy
      const copyBtn = document.createElement('button');
      copyBtn.type = 'button';
      copyBtn.className = 'msg-action-btn';
      copyBtn.title = 'Copy response';
      copyBtn.innerHTML = '<i class="iconoir-copy"></i>';
      copyBtn.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(cleanContent);
          copyBtn.innerHTML = '<i class="iconoir-check text-emerald-500"></i>';
          setTimeout(() => { copyBtn.innerHTML = '<i class="iconoir-copy"></i>'; }, 1500);
        } catch (e) {}
      });
      actions.appendChild(copyBtn);

      // Regenerate (needs a persisted message id + conversation)
      if (messageId) {
        const regenBtn = document.createElement('button');
        regenBtn.type = 'button';
        regenBtn.className = 'msg-action-btn';
        regenBtn.title = 'Regenerate response';
        regenBtn.innerHTML = '<i class="iconoir-refresh-double"></i>';
        regenBtn.addEventListener('click', () => regenerateMessage(messageId, bubble, regenBtn));
        actions.appendChild(regenBtn);
      }

      wrap.appendChild(actions);
    }

    async function regenerateMessage(messageId, bubble, btn){
      if (!messageId || !currentConversationId || isGenerating) return;
      const originalIcon = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="iconoir-refresh-double animate-spin"></i>';
      bubble.classList.add('opacity-50', 'pointer-events-none');
      try {
        const result = await api('/api/chat-regenerate-full.php', {
          method: 'POST',
          body: { message_id: parseInt(messageId), conversation_id: currentConversationId }
        });
        if (result.success && result.message) {
          const clean = (result.message.content || '').replace(/\[DOC_START\]|\[DOC_END\]|\[DOWNLOAD_INTENT\]/g, '');
          bubble.innerHTML = mdToHtml(clean);
          enhanceCapabilityActions(bubble, result.message.content);
          enhanceCodeBlocks(bubble);
          buildMessageActions(bubble, result.message.content, messageId);
          bubble.classList.add('ring-2', 'ring-emerald-400', 'ring-opacity-75');
          setTimeout(() => bubble.classList.remove('ring-2', 'ring-emerald-400', 'ring-opacity-75'), 2000);
        }
      } catch (error) {
        alert('Error regenerating: ' + error.message);
        btn.innerHTML = originalIcon;
      } finally {
        btn.disabled = false;
        bubble.classList.remove('opacity-50', 'pointer-events-none');
      }
    }

    function append(role, content, file = null, images = null, annotations = null, options = {}){
      if(messagesEl.children.length === 0) showChatMode();
      
      const { messageId, isStreaming } = options;

      // Agrupar mensajes consecutivos del mismo rol
      const prevWrap = messagesEl.lastElementChild;
      const sameAsPrev = !!(prevWrap && prevWrap.dataset.role === role);

      const wrap = document.createElement('div');
      const topMargin = !prevWrap ? '' : (sameAsPrev ? 'mt-1' : 'mt-6');
      wrap.className = ('group flex flex-col ' + topMargin + ' ' + (role === 'user' ? 'items-end' : 'items-start')).trim();
      wrap.dataset.role = role;
      if (messageId) wrap.dataset.messageWrap = messageId;

      // Avatar + burbuja container
      const msgContainer = document.createElement('div');
      msgContainer.className = 'flex gap-3 max-w-3xl ' + (role === 'user' ? 'flex-row-reverse' : 'flex-row');

      // Avatar
      const avatar = document.createElement('div');
      avatar.className = role === 'user'
        ? 'w-9 h-9 rounded-full gradient-brand flex items-center justify-center text-[#2F3440] text-sm font-semibold flex-shrink-0 shadow-sm'
        : 'w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 text-sm font-semibold flex-shrink-0';
      avatar.textContent = role === 'user'
        ? (currentUser ? currentUser.first_name[0] + currentUser.last_name[0] : '?')
        : 'E';
      // En mensajes agrupados, el avatar queda como espaciador invisible
      if (sameAsPrev) avatar.style.visibility = 'hidden';
      
      // Burbuja
      const bubble = document.createElement('div');
      bubble.className = role === 'user' 
        ? 'gradient-brand text-white px-5 py-3.5 rounded-2xl rounded-tr-sm shadow-md text-conversation' 
        : 'bg-white border border-slate-200 text-slate-800 px-5 py-3.5 rounded-2xl rounded-tl-sm shadow-sm text-conversation select-text';
      bubble.style.wordBreak = 'break-word';
      
      // Para mensajes del asistente, añadir data attributes para selección
      if (role === 'assistant' && messageId) {
        bubble.dataset.messageId = messageId;
        bubble.dataset.role = role;
      }
      
      if (role === 'assistant') {
        // Ocultar marcadores de documento e intención de descarga
        const cleanContent = content.replace(/\[DOC_START\]|\[DOC_END\]|\[DOWNLOAD_INTENT\]/g, '');
        if (isStreaming) {
          // Durante streaming se escribe directo en la burbuja (updateStreamingMessage)
          bubble.innerHTML = mdToHtml(cleanContent);
        } else {
          const contentEl = document.createElement('div');
          contentEl.className = 'markdown-content prose prose-slate prose-sm max-w-none';
          contentEl.innerHTML = mdToHtml(cleanContent);
          enhanceCapabilityActions(contentEl, cleanContent);
          bubble.appendChild(contentEl);
        }
        // Si está en streaming, añadir indicador
        if (isStreaming) {
          const indicator = document.createElement('span');
          indicator.className = 'streaming-indicator ml-1 inline-flex gap-1';
          indicator.innerHTML = `
            <span class="w-1 h-1 bg-slate-400 rounded-full animate-pulse"></span>
            <span class="w-1 h-1 bg-slate-400 rounded-full animate-pulse" style="animation-delay: 0.2s"></span>
            <span class="w-1 h-1 bg-slate-400 rounded-full animate-pulse" style="animation-delay: 0.4s"></span>
          `;
          bubble.appendChild(indicator);
        }
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
          imgEl.alt = 'Generated image ' + (idx + 1);
          imgEl.className = 'max-w-full rounded-xl shadow-md cursor-pointer hover:shadow-lg transition-shadow';
          imgEl.style.maxHeight = '400px';
          imgEl.addEventListener('click', () => openLightbox(imgUrl));
          
          const actionsEl = document.createElement('div');
          actionsEl.className = 'mt-2 flex gap-2';
          
          const downloadBtn = document.createElement('a');
          downloadBtn.href = imgUrl;
          downloadBtn.download = `nanobanana-${Date.now()}-${idx + 1}.png`;
          downloadBtn.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-amber-50 text-amber-700 hover:bg-amber-100 rounded-lg transition-colors';
          downloadBtn.innerHTML = '<i class="iconoir-download"></i> Download';
          
          actionsEl.appendChild(downloadBtn);
          imgWrap.appendChild(imgEl);
          imgWrap.appendChild(actionsEl);
          imagesContainer.appendChild(imgWrap);
        });
        
        bubble.appendChild(imagesContainer);
      }
      
      // Añadir botones de descarga PDF/DOCX para respuestas del asistente (solo si hay intención de documento)
      const hasDownloadIntent = content && content.includes('[DOWNLOAD_INTENT]');
      if (role === 'assistant' && content && content.length > 100 && hasDownloadIntent) {
        const downloadActionsEl = document.createElement('div');
        downloadActionsEl.className = 'mt-3 pt-3 border-t border-slate-100 flex gap-2';
        
        const pdfBtn = document.createElement('button');
        pdfBtn.type = 'button';
        pdfBtn.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-red-50 text-red-700 hover:bg-red-100 rounded-lg transition-colors';
        pdfBtn.innerHTML = '<i class="iconoir-page"></i> Download PDF';
        pdfBtn.addEventListener('click', (e) => downloadDocument(content, 'pdf', e.currentTarget));
        
        const docxBtn = document.createElement('button');
        docxBtn.type = 'button';
        docxBtn.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg transition-colors';
        docxBtn.innerHTML = '<i class="iconoir-page-star"></i> Download Word';
        docxBtn.addEventListener('click', (e) => downloadDocument(content, 'docx', e.currentTarget));
        
        downloadActionsEl.appendChild(pdfBtn);
        downloadActionsEl.appendChild(docxBtn);
        bubble.appendChild(downloadActionsEl);
      }
      
      // Añadir citas web si existen (búsqueda web 🌐)
      if (annotations && annotations.length > 0 && role === 'assistant') {
        const citationsContainer = document.createElement('div');
        citationsContainer.className = 'mt-4 pt-3 border-t border-slate-200';
        
        const citationsTitle = document.createElement('div');
        citationsTitle.className = 'text-xs font-medium text-slate-500 mb-2 flex items-center gap-1.5';
        citationsTitle.innerHTML = '<i class="iconoir-globe text-cyan-500"></i> Sources';
        citationsContainer.appendChild(citationsTitle);
        
        const citationsList = document.createElement('div');
        citationsList.className = 'space-y-1.5';
        
        // Deduplicar por URL
        const seenUrls = new Set();
        annotations.forEach(ann => {
          const citation = ann.url_citation;
          if (!citation || !citation.url || seenUrls.has(citation.url)) return;
          seenUrls.add(citation.url);
          
          const citationEl = document.createElement('a');
          citationEl.href = citation.url;
          citationEl.target = '_blank';
          citationEl.rel = 'noopener noreferrer';
          citationEl.className = 'flex items-center gap-2 text-xs text-slate-600 hover:text-cyan-600 hover:bg-cyan-50 px-2 py-1.5 rounded-lg transition-colors';
          
          // Extraer dominio para mostrar
          let domain = '';
          try {
            domain = new URL(citation.url).hostname.replace('www.', '');
          } catch (e) {
            domain = citation.url.substring(0, 30);
          }
          
          const title = citation.title || domain;
          citationEl.innerHTML = `<i class="iconoir-link text-slate-400"></i> <span class="truncate">${escapeHtml(title)}</span> <span class="text-slate-400 flex-shrink-0">${escapeHtml(domain)}</span>`;
          citationsList.appendChild(citationEl);
        });
        
        if (citationsList.children.length > 0) {
          citationsContainer.appendChild(citationsList);
          bubble.appendChild(citationsContainer);
        }
      }
      
      msgContainer.appendChild(avatar);
      msgContainer.appendChild(bubble);
      
      // Timestamp (se revela al hover del mensaje)
      const timestamp = document.createElement('div');
      timestamp.className = 'msg-time text-xs text-slate-400 mt-1 ' + (role === 'user' ? 'px-3' : 'ml-12');
      const now = new Date();
      timestamp.textContent = now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
      
      wrap.appendChild(msgContainer);
      wrap.appendChild(timestamp);
      messagesEl.appendChild(wrap);
      
      // Para mensajes del asistente ya completos (carga de historial), añadir
      // mejoras de código y barra de acciones. Los mensajes en streaming las
      // reciben en finalizeStreamingMessage().
      if (role === 'assistant' && !isStreaming) {
        enhanceCodeBlocks(bubble);
        buildMessageActions(bubble, content, messageId);
      }

      if (role === 'user') {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
      } else {
        // Para el asistente, nos desplazamos al inicio del nuevo mensaje
        wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }

      // Devolver la burbuja para actualización en streaming
      return { wrap, bubble };
    }
    
    // Actualizar contenido de mensaje en streaming
    function updateStreamingMessage(bubble, content) {
      // Ocultar delimitadores de documento e intención de descarga de la vista del usuario
      const displayContent = content.replace(/\[DOC_START\]|\[DOC_END\]|\[DOWNLOAD_INTENT\]/g, '');
      bubble.innerHTML = mdToHtml(displayContent);
      if (bubble.querySelector('.streaming-indicator')) {
        bubble.appendChild(bubble.querySelector('.streaming-indicator'));
      }
    }
    
    // Finalizar mensaje en streaming (quitar indicador, añadir imágenes/citas)
    function finalizeStreamingMessage(bubble, content, images, annotations, messageId) {
      // Limpiar indicador
      bubble.innerHTML = '';
      
      // Ocultar delimitadores de documento e intención de descarga de la vista del usuario
      const displayContent = content.replace(/\[DOC_START\]|\[DOC_END\]|\[DOWNLOAD_INTENT\]/g, '');
      
      // Renderizar contenido
      const contentEl = document.createElement('div');
      contentEl.className = 'markdown-content prose prose-slate prose-sm max-w-none';
      contentEl.innerHTML = mdToHtml(displayContent);
      enhanceCapabilityActions(contentEl, displayContent);
      bubble.appendChild(contentEl);
      enhanceCodeBlocks(contentEl);

      // Añadir data attributes para selección
      if (messageId) {
        bubble.dataset.messageId = messageId;
        bubble.dataset.role = 'assistant';
      }
      
      // Añadir imágenes si existen
      if (images && images.length > 0) {
        const imagesContainer = document.createElement('div');
        imagesContainer.className = 'mt-3 space-y-3';
        
        images.forEach((img, idx) => {
          const imgUrl = img.image_url?.url || img.imageUrl?.url || '';
          if (!imgUrl) return;
          
          const imgWrap = document.createElement('div');
          imgWrap.className = 'relative group';
          
          const imgEl = document.createElement('img');
          imgEl.src = imgUrl;
          imgEl.alt = 'Generated image ' + (idx + 1);
          imgEl.className = 'max-w-full rounded-xl shadow-md cursor-pointer hover:shadow-lg transition-shadow';
          imgEl.style.maxHeight = '400px';
          imgEl.addEventListener('click', () => openLightbox(imgUrl));
          
          const actionsEl = document.createElement('div');
          actionsEl.className = 'mt-2 flex gap-2';
          
          const downloadBtn = document.createElement('a');
          downloadBtn.href = imgUrl;
          downloadBtn.download = `nanobanana-${Date.now()}-${idx + 1}.png`;
          downloadBtn.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-amber-50 text-amber-700 hover:bg-amber-100 rounded-lg transition-colors';
          downloadBtn.innerHTML = '<i class="iconoir-download"></i> Download';
          
          actionsEl.appendChild(downloadBtn);
          imgWrap.appendChild(imgEl);
          imgWrap.appendChild(actionsEl);
          imagesContainer.appendChild(imgWrap);
        });
        
        bubble.appendChild(imagesContainer);
      }
      
      // Añadir botones de descarga PDF/DOCX (solo si hay intención de documento)
      const hasDownloadIntent = content && content.includes('[DOWNLOAD_INTENT]');
      if (content && content.length > 100 && hasDownloadIntent) {
        const downloadActionsEl = document.createElement('div');
        downloadActionsEl.className = 'mt-3 pt-3 border-t border-slate-100 flex gap-2';
        
        const pdfBtn = document.createElement('button');
        pdfBtn.type = 'button';
        pdfBtn.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-red-50 text-red-700 hover:bg-red-100 rounded-lg transition-colors';
        pdfBtn.innerHTML = '<i class="iconoir-page"></i> Download PDF';
        pdfBtn.addEventListener('click', (e) => downloadDocument(content, 'pdf', e.currentTarget));
        
        const docxBtn = document.createElement('button');
        docxBtn.type = 'button';
        docxBtn.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg transition-colors';
        docxBtn.innerHTML = '<i class="iconoir-page-star"></i> Download Word';
        docxBtn.addEventListener('click', (e) => downloadDocument(content, 'docx', e.currentTarget));
        
        downloadActionsEl.appendChild(pdfBtn);
        downloadActionsEl.appendChild(docxBtn);
        bubble.appendChild(downloadActionsEl);
      }
      
      // Añadir citas web si existen
      if (annotations && annotations.length > 0) {
        const citationsContainer = document.createElement('div');
        citationsContainer.className = 'mt-4 pt-3 border-t border-slate-200';
        
        const citationsTitle = document.createElement('div');
        citationsTitle.className = 'text-xs font-medium text-slate-500 mb-2 flex items-center gap-1.5';
        citationsTitle.innerHTML = '<i class="iconoir-globe text-cyan-500"></i> Sources';
        citationsContainer.appendChild(citationsTitle);
        
        const citationsList = document.createElement('div');
        citationsList.className = 'space-y-1.5';
        
        const seenUrls = new Set();
        annotations.forEach(ann => {
          const citation = ann.url_citation;
          if (!citation || !citation.url || seenUrls.has(citation.url)) return;
          seenUrls.add(citation.url);
          
          const citationEl = document.createElement('a');
          citationEl.href = citation.url;
          citationEl.target = '_blank';
          citationEl.rel = 'noopener noreferrer';
          citationEl.className = 'flex items-center gap-2 text-xs text-slate-600 hover:text-cyan-600 hover:bg-cyan-50 px-2 py-1.5 rounded-lg transition-colors';
          
          let domain = '';
          try {
            domain = new URL(citation.url).hostname.replace('www.', '');
          } catch (e) {
            domain = citation.url.substring(0, 30);
          }
          
          const title = citation.title || domain;
          citationEl.innerHTML = `<i class="iconoir-link text-slate-400"></i> <span class="truncate">${escapeHtml(title)}</span> <span class="text-slate-400 flex-shrink-0">${escapeHtml(domain)}</span>`;
          citationsList.appendChild(citationEl);
        });
        
        if (citationsList.children.length > 0) {
          citationsContainer.appendChild(citationsList);
          bubble.appendChild(citationsContainer);
        }
      }

      // Barra de acciones por mensaje (copiar / regenerar)
      buildMessageActions(bubble, content, messageId);
    }

    // Función de streaming SSE
    async function streamChat(params, onChunk, onComplete, onError) {
      abortController = new AbortController();
      isGenerating = true;
      showStopButton();
      
      try {
        const response = await fetch('/api/chat-stream.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf || ''
          },
          body: JSON.stringify(params),
          credentials: 'include',
          signal: abortController.signal
        });
        
        if (!response.ok) {
          const errorData = await response.json().catch(() => ({}));
          throw new Error(errorData?.error?.message || `HTTP ${response.status}`);
        }
        
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let fullText = '';
        let messageId = null;
        let model = null;
        let images = null;
        let annotations = null;
        let newConversationId = null;
        
        while (true) {
          const { done, value } = await reader.read();
          
          if (done) {
            isGenerating = false;
            abortController = null;
            hideStopButton();
            onComplete({
              content: fullText,
              messageId,
              model,
              images,
              annotations,
              newConversationId
            });
            break;
          }
          
          buffer += decoder.decode(value, { stream: true });
          
          // Procesar líneas completas
          const lines = buffer.split('\n');
          buffer = lines.pop() || '';
          
          for (const line of lines) {
            if (line.startsWith('data: ')) {
              const data = line.slice(6);
              
              if (data === '[DONE]') {
                continue;
              }
              
              try {
                const json = JSON.parse(data);
                
                if (json.type === 'chunk' && json.content) {
                  fullText += json.content;
                  onChunk(json.content, fullText);
                } else if (json.type === 'meta') {
                  messageId = json.message_id;
                  model = json.model;
                } else if (json.type === 'images') {
                  images = json.images;
                } else if (json.type === 'annotations') {
                  annotations = json.annotations;
                } else if (json.type === 'error') {
                  throw new Error(json.message || 'Streaming error');
                } else if (json.type === 'conversation') {
                  newConversationId = json.id;
                }
              } catch (e) {
                if (e.name !== 'SyntaxError') {
                console.error('Stream parse error:', e);
                }
              }
            }
          }
        }
      } catch (error) {
        isGenerating = false;
        abortController = null;
        hideStopButton();
        
        if (error.name === 'AbortError') {
          onComplete({ content: fullText || '', cancelled: true });
        } else {
          onError(error);
        }
      }
    }
    
    // Detener generación
    function stopGeneration() {
      if (abortController) {
        abortController.abort();
        abortController = null;
      }
      isGenerating = false;
      hideStopButton();
    }
    
    // Mostrar/ocultar botón de detener
    function showStopButton() {
      let btn = document.getElementById('stop-generation-btn');
      if (!btn) {
        btn = document.createElement('button');
        btn.id = 'stop-generation-btn';
        btn.className = 'fixed bottom-32 left-1/2 -translate-x-1/2 z-40 px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-full shadow-lg flex items-center gap-2 transition-all hover:scale-105';
        btn.innerHTML = `
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <rect x="6" y="6" width="12" height="12" rx="2" stroke-width="2"/>
          </svg>
          Detener generación
        `;
        btn.addEventListener('click', stopGeneration);
        document.body.appendChild(btn);
      }
      btn.classList.remove('hidden');
    }
    
    function hideStopButton() {
      const btn = document.getElementById('stop-generation-btn');
      if (btn) btn.classList.add('hidden');
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

    // ===== SELECCIÓN DE TEXTO Y REGENERACIÓN PARCIAL =====
    const selectionToolbar = document.getElementById('selection-toolbar');
    const selectionEditBtn = document.getElementById('selection-edit-btn');
    const selectionRegenerateBtn = document.getElementById('selection-regenerate-btn');
    const editModal = document.getElementById('selection-edit-modal');
    const editModalTitle = document.getElementById('edit-modal-title');
    const editModalSelection = document.getElementById('edit-modal-selection');
    const editModalInstructions = document.getElementById('edit-modal-instructions');
    const editModalCancel = document.getElementById('edit-modal-cancel');
    const editModalSubmit = document.getElementById('edit-modal-submit');
    
    // Elementos móvil
    const selectionBarMobile = document.getElementById('selection-bar-mobile');
    const mobileSelectionPreview = document.getElementById('mobile-selection-preview');
    const mobileEditBtn = document.getElementById('mobile-edit-btn');
    const mobileRegenerateBtn = document.getElementById('mobile-regenerate-btn');
    const mobileCloseSelection = document.getElementById('mobile-close-selection');
    
    // Detectar si es móvil
    function isMobile() {
      return window.innerWidth < 768;
    }
    
    // Flag para detectar si el usuario está activamente seleccionando (mouse presionado)
    let isSelecting = false;
    
    // Detectar inicio de selección (mousedown en mensajes)
    messagesEl.addEventListener('mousedown', () => {
      isSelecting = true;
    });
    
    // Detectar fin de selección (mouseup en cualquier lugar)
    document.addEventListener('mouseup', () => {
      // Pequeño delay para permitir que selectionchange se procese primero
      setTimeout(() => {
        isSelecting = false;
      }, 100);
    });
    
    // Detectar selección de texto en mensajes del asistente
    document.addEventListener('selectionchange', () => {
      const selection = window.getSelection();
      const text = selection ? selection.toString().trim() : '';
      
      if (!selection || selection.isCollapsed || text === '') {
        // Si no hay selección activa, ocultar UIs
        hideSelectionUI();
        return;
      }
      
      // Verificar si la selección está en un mensaje del asistente
      const range = selection.getRangeAt(0);
      const container = range.commonAncestorContainer;
      const messageEl = container.nodeType === Node.TEXT_NODE 
        ? container.parentElement?.closest('[data-message-id][data-role="assistant"]')
        : container.closest?.('[data-message-id][data-role="assistant"]');
      
      if (!messageEl) {
        hideSelectionUI();
        return;
      }
      
      // Guardar estado de selección
      selectedText = text;
      selectedMessageId = messageEl.dataset.messageId;
      
      // Mostrar solo la UI correspondiente al dispositivo
      if (isMobile()) {
        showMobileSelectionBar();
        hideSelectionToolbar();
      } else {
        const rect = range.getBoundingClientRect();
        positionSelectionToolbar(rect);
        hideMobileSelectionBar();
      }
    });

    // === DESKTOP: Toolbar flotante ===
    function positionSelectionToolbar(rect) {
      if (!selectionToolbar) return;
      
      selectionToolbar.style.visibility = 'hidden';
      selectionToolbar.classList.remove('hidden');
      
      const toolbarRect = selectionToolbar.getBoundingClientRect();
      const padding = 12;
      const toolbarHeight = toolbarRect.height || 44;
      const toolbarWidth = toolbarRect.width || 200;
      
      let top = rect.top - toolbarHeight - padding;
      let left = rect.left + (rect.width / 2) - (toolbarWidth / 2);
      
      // Si está muy arriba, mostrar debajo
      if (top < padding) {
        top = rect.bottom + padding;
      }
      
      // Mantener dentro del viewport horizontal
      if (left < padding) left = padding;
      if (left + toolbarWidth > window.innerWidth - padding) {
        left = window.innerWidth - toolbarWidth - padding;
      }
      
      selectionToolbar.style.top = `${top + window.scrollY}px`;
      selectionToolbar.style.left = `${left}px`;
      selectionToolbar.style.visibility = 'visible';
    }

    function hideSelectionToolbar() {
      if (selectionToolbar) {
        selectionToolbar.classList.add('hidden');
        selectionToolbar.style.visibility = '';
        selectionToolbar.style.top = '';
        selectionToolbar.style.left = '';
      }
    }
    
    // === MÓVIL: Barra anclada ===
    function showMobileSelectionBar() {
      if (!selectionBarMobile || !selectedText) return;
      
      const preview = selectedText.length > 50 
        ? selectedText.substring(0, 50) + '...' 
        : selectedText;
      mobileSelectionPreview.textContent = preview;
      
      // Forzar visualización inmediata
      selectionBarMobile.style.display = 'block';
      
      // Pequeño delay para que la transición de transform funcione tras el display:block
      setTimeout(() => {
        selectionBarMobile.style.transform = 'translateY(0)';
      }, 10);
    }
    
    function hideMobileSelectionBar() {
      if (!selectionBarMobile) return;
      
      selectionBarMobile.style.transform = 'translateY(100%)';
      setTimeout(() => {
        // Solo ocultar si sigue estando fuera de la vista (evitar parpadeos si se vuelve a seleccionar rápido)
        if (selectionBarMobile.style.transform === 'translateY(100%)') {
          selectionBarMobile.style.display = 'none';
        }
      }, 300);
    }
    
    // Ocultar todo
    function hideSelectionUI() {
      hideSelectionToolbar();
      hideMobileSelectionBar();
    }
    
    // Limpiar selección y ocultar UI
    function clearSelection() {
      window.getSelection()?.removeAllRanges();
      selectedText = null;
      selectedMessageId = null;
      hideSelectionUI();
    }
    
    // Eventos móvil
    mobileEditBtn?.addEventListener('click', () => {
      hideMobileSelectionBar();
      showEditModal('edit');
    });
    
    mobileRegenerateBtn?.addEventListener('click', () => {
      hideMobileSelectionBar();
      submitRegeneration("Reescribe esta parte para que sea más clara y natural, manteniendo el mismo significado.");
    });
    
    mobileCloseSelection?.addEventListener('click', clearSelection);
    
    // Ocultar toolbar al hacer clic fuera
    document.addEventListener('mousedown', (e) => {
      const isClickInsideUI = 
        selectionToolbar?.contains(e.target) || 
        selectionBarMobile?.contains(e.target) || 
        editModal?.contains(e.target);
      
      const isClickInMessages = e.target.closest('#messages') !== null;

      if (!isClickInsideUI && !isClickInMessages) {
        // Clear only if the click is outside messages and outside the UI.
        clearSelection();
      }
    });
    
    // Hide toolbar on scroll when not actively selecting.
    messagesContainer.addEventListener('scroll', () => {
      if (!isMobile() && !isSelecting) {
        // Hide toolbar and clear selection on desktop.
        clearSelection();
      }
    });
    
    // Toolbar buttons (desktop)
    selectionEditBtn?.addEventListener('click', () => {
      hideSelectionToolbar();
      showEditModal('edit');
    });
    
    selectionRegenerateBtn?.addEventListener('click', () => {
      hideSelectionToolbar();
      // Instant regeneration with a generic instruction.
      submitRegeneration("Rewrite this part so it is clearer and more natural while keeping the same meaning.");
    });
    
    function showEditModal(mode) {
      if (!editModal || !selectedText) return;
      
      hideSelectionToolbar();
      
      editModalTitle.textContent = 'Edit selection';
      editModalSelection.textContent = selectedText;
      editModalInstructions.value = '';
      
      editModal.classList.remove('hidden');
      editModalInstructions.focus();
    }
    
    function hideEditModal() {
      if (editModal) {
        editModal.classList.add('hidden');
      }
    }
    
    // Modal events
    editModalCancel?.addEventListener('click', hideEditModal);
    
    editModal?.addEventListener('click', (e) => {
      if (e.target === editModal) hideEditModal();
    });
    
    // Close with Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && editModal && !editModal.classList.contains('hidden')) {
        hideEditModal();
      }
    });
    
    // Send with Cmd/Ctrl+Enter
    editModalInstructions?.addEventListener('keydown', (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
        e.preventDefault();
        const inst = editModalInstructions.value.trim();
        if (inst) submitRegeneration(inst);
      }
    });
    
    editModalSubmit?.addEventListener('click', () => {
      const inst = editModalInstructions.value.trim();
      if (inst) submitRegeneration(inst);
    });
    
    async function submitRegeneration(instructions) {
      if (!instructions) return;
      
      const isFromModal = !editModal.classList.contains('hidden');
      
      if (isFromModal) {
        const originalBtnText = editModalSubmit.innerHTML;
        editModalSubmit.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing...';
        editModalSubmit.disabled = true;
      }
      
      // For instant edits, show a small visual indicator on the bubble.
      const bubble = document.querySelector(`[data-message-id="${selectedMessageId}"]`);
      if (!isFromModal && bubble) {
        bubble.classList.add('opacity-50', 'pointer-events-none');
      }
      
      try {
        const result = await api('/api/chat-regenerate.php', {
          method: 'POST',
          body: {
            message_id: parseInt(selectedMessageId),
            conversation_id: currentConversationId,
            selected_text: selectedText,
            instructions: instructions
          }
        });
        
        if (result.success && result.message) {
          if (bubble) {
            bubble.innerHTML = mdToHtml(result.message.content);
            enhanceCapabilityActions(bubble, result.message.content);
            enhanceCodeBlocks(bubble);
            // Green highlight effect.
            bubble.classList.add('ring-2', 'ring-emerald-400', 'ring-opacity-75');
            setTimeout(() => {
              bubble.classList.remove('ring-2', 'ring-emerald-400', 'ring-opacity-75');
            }, 2000);
          }
        }
        
        if (isFromModal) hideEditModal();
        
      } catch (error) {
        alert('Error regenerating: ' + error.message);
      } finally {
        if (isFromModal) {
          editModalSubmit.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> Apply changes';
          editModalSubmit.disabled = false;
        }
        if (bubble) {
          bubble.classList.remove('opacity-50', 'pointer-events-none');
        }
      }
    }
    // ===== END TEXT SELECTION =====

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
    window.appApi = api;
    
    // Generar y descargar documento (PDF/DOCX)
    async function downloadDocument(content, format, buttonElement) {
      const originalHTML = buttonElement.innerHTML;
      const originalDisabled = buttonElement.disabled;
      
      try {
        // Mostrar indicador de carga
        buttonElement.disabled = true;
        buttonElement.innerHTML = format === 'pdf' 
          ? '<i class="iconoir-page"></i> Generando PDF...'
          : '<i class="iconoir-page-star"></i> Generando Word...';
        buttonElement.style.opacity = '0.6';
        
        const response = await api('/api/chat/generate-document.php', {
          method: 'POST',
          body: {
            content: content,
            format: format,
            title: currentConvTitle || 'Claara document'
          }
        });
        
        if (response.success && response.content) {
          // Decodificar base64 y crear blob
          const binaryString = atob(response.content);
          const bytes = new Uint8Array(binaryString.length);
          for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
          }
          const blob = new Blob([bytes], { type: response.mime_type });
          
          // Crear link de descarga
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = response.filename;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          URL.revokeObjectURL(url);
        }
      } catch (error) {
        alert('Error generating document: ' + error.message);
      } finally {
        // Restaurar estado del botón
        buttonElement.innerHTML = originalHTML;
        buttonElement.disabled = originalDisabled;
        buttonElement.style.opacity = '1';
      }
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

    // Profile dropdown is handled in header-unified.php.
    
    sortSelect.addEventListener('change', () => loadConversations());
    
    // Create new folder
    newFolderBtn.addEventListener('click', async () => {
      const name = prompt('Folder name:');
      if (!name || name.trim() === '') return;
      try {
        await api('/api/folders/create.php', { method: 'POST', body: { name: name.trim() } });
        await loadFolders();
        await loadConversations();
      } catch (err) {
        alert('Error creating folder: ' + err.message);
      }
    });
    
    // Close modal
    closeMoveModal.addEventListener('click', () => {
      moveModal.classList.add('hidden');
      conversationToMove = null;
    });
    
    cancelMoveBtn.addEventListener('click', () => {
      moveModal.classList.add('hidden');
      conversationToMove = null;
    });
    
    // Close modal when clicking outside
    moveModal.addEventListener('click', (e) => {
      if (e.target === moveModal) {
        moveModal.classList.add('hidden');
        conversationToMove = null;
      }
    });

    async function loadFolders(){
      const data = await api('/api/folders/list.php');
      allFolders = data.folders || [];
      
      // Count total conversations and root conversations.
      const allConvs = await api('/api/conversations/list.php?folder_id=-1');
      const rootConvs = await api('/api/conversations/list.php?folder_id=0');
      document.getElementById('all-count').textContent = (allConvs.items || []).length;
      document.getElementById('root-count').textContent = (rootConvs.items || []).length;
      
      // Render dynamic folders.
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
          btn.classList.add('bg-gradient-to-r', 'from-[#B7C9F2]/10', 'to-[#2F3440]/10', 'shadow-sm');
        }
        
        const iconEl = document.createElement('i');
        iconEl.className = 'iconoir-folder text-[#B7C9F2] flex-shrink-0';
        
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
        
        // Folder actions (rename, delete), always present but invisible.
        const actions = document.createElement('div');
        actions.className = 'flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0';
        
        const renameBtn = document.createElement('button');
        renameBtn.className = 'flex items-center justify-center p-1 text-slate-400 hover:text-[#B7C9F2] hover:bg-[#B7C9F2]/10 rounded transition-colors';
        renameBtn.style.lineHeight = '0';
        renameBtn.setAttribute('data-action-folder', 'rename');
        renameBtn.innerHTML = '<i class="iconoir-edit-pencil text-xs"></i>';
        renameBtn.title = 'Rename';
        renameBtn.addEventListener('click', async (e) => {
          e.stopPropagation();
          const newName = prompt('New name:', folder.name);
          if (!newName || newName.trim() === '') return;
          try {
            await api('/api/folders/rename.php', { method: 'POST', body: { id: folder.id, name: newName.trim() } });
            await loadFolders();
          } catch (err) {
            alert('Error renaming: ' + err.message);
          }
        });
        
        const delBtn = document.createElement('button');
        delBtn.className = 'flex items-center justify-center p-1 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors';
        delBtn.style.lineHeight = '0';
        delBtn.setAttribute('data-action-folder', 'delete');
        delBtn.innerHTML = '<i class="iconoir-trash text-xs"></i>';
        delBtn.title = 'Delete';
        delBtn.addEventListener('click', async (e) => {
          e.stopPropagation();
          const msg = folder.conversation_count > 0 
            ? `Delete "${folder.name}"? Its ${folder.conversation_count} conversation${folder.conversation_count !== 1 ? 's' : ''} will be moved to No folder.`
            : `Delete "${folder.name}"?`;
          if (!confirm(msg)) return;
          try {
            await api('/api/folders/delete.php', { method: 'POST', body: { id: folder.id } });
            if (currentFolderId === folder.id) {
              currentFolderId = -1;
            }
            await loadFolders();
            await loadConversations();
          } catch (err) {
            alert('Error deleting: ' + err.message);
          }
        });
        
        actions.appendChild(renameBtn);
        actions.appendChild(delBtn);
        btn.appendChild(actions);
        btn.appendChild(countEl);
        
        li.appendChild(btn);
        folderListEl.appendChild(li);
      }
      
      // Update active state for "All" and "No folder".
      const allFolderItems = document.querySelectorAll('.folder-item');
      allFolderItems.forEach(item => {
        const folderId = parseInt(item.dataset.folderId);
        item.classList.remove('bg-gradient-to-r', 'from-[#B7C9F2]/10', 'to-[#2F3440]/10', 'shadow-sm');
        if (folderId === currentFolderId) {
          item.classList.add('bg-gradient-to-r', 'from-[#B7C9F2]/10', 'to-[#2F3440]/10', 'shadow-sm');
        }
        
        // Add event listeners only for "All" (-1) and "No folder" (0).
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
      
      // Render folder options.
      const dynamicOptions = folderOptionsEl.querySelectorAll('.dynamic-folder-option');
      dynamicOptions.forEach(el => el.remove());
      
      if (allFolders.length === 0) {
        emptyFoldersEl.classList.remove('hidden');
      } else {
        emptyFoldersEl.classList.add('hidden');
        
        allFolders.forEach(folder => {
          const btn = document.createElement('button');
          btn.dataset.targetFolder = folder.id;
          btn.className = 'folder-option dynamic-folder-option w-full p-4 bg-slate-50 hover:bg-[#B7C9F2]/5 border-2 border-slate-200 hover:border-[#B7C9F2] rounded-xl transition-all text-left group';
          
          // Mark current folder.
          if (conversation.folder_id && conversation.folder_id == folder.id) {
            btn.classList.add('border-[#B7C9F2]', 'bg-[#B7C9F2]/5');
          }
          
          btn.innerHTML = `
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-[#B7C9F2]/20 to-[#2F3440]/20 flex items-center justify-center flex-shrink-0">
                <i class="iconoir-folder text-xl text-[#B7C9F2]"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-slate-800 group-hover:text-[#B7C9F2] transition-colors">${folder.name}</div>
                <div class="text-xs text-slate-500">${folder.conversation_count} conversation${folder.conversation_count !== 1 ? 's' : ''}</div>
              </div>
              <i class="iconoir-nav-arrow-right text-slate-300 group-hover:text-[#B7C9F2] transition-colors"></i>
            </div>
          `;
          
          btn.addEventListener('click', () => handleMoveConversation(folder.id));
          folderOptionsEl.appendChild(btn);
        });
      }
      
      // Add listener to the "No folder" button.
      const rootBtn = folderOptionsEl.querySelector('[data-target-folder="0"]');
      if (rootBtn) {
        // Remove previous listeners by cloning.
        const newRootBtn = rootBtn.cloneNode(true);
        rootBtn.parentNode.replaceChild(newRootBtn, rootBtn);
        
        // Reset classes defensively.
        newRootBtn.classList.remove('border-[#B7C9F2]', 'bg-[#B7C9F2]/5');
        
        // Mark if in root.
        if (!conversation.folder_id || conversation.folder_id === 0 || conversation.folder_id === "0") {
          newRootBtn.classList.add('border-[#B7C9F2]', 'bg-[#B7C9F2]/5');
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
        alert('Error moving: ' + err.message);
      }
    }

    async function loadConversations(){
      const sort = sortSelect.value || 'updated_at';
      const folderParam = currentFolderId !== null ? `&folder_id=${currentFolderId}` : '';
      const data = await api(`/api/conversations/list.php?sort=${encodeURIComponent(sort)}${folderParam}`);
      const items = data.items || [];
      if(items.length === 0){
        convListEl.innerHTML = '<li class="text-slate-400 text-sm px-3 py-2">(empty)</li>';
        return;
      }
      convListEl.innerHTML = '';
      for(const c of items){
        const li = document.createElement('li');
        const isActive = currentConversationId === c.id;
        li.className = 'group rounded-lg transition-all duration-200 ' + (isActive ? 'bg-gradient-to-r from-[#B7C9F2]/10 to-[#2F3440]/10 shadow-sm' : 'hover:bg-slate-50');
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
        starBtn.title = c.is_favorite ? 'Remove from favorites' : 'Add to favorites';
        starBtn.addEventListener('click', async (e) => {
          e.stopPropagation();
          try {
            await api('/api/conversations/toggle_favorite.php', { method: 'POST', body: { id: c.id } });
            await loadConversations();
          } catch (err) {
            alert('Error changing favorite: ' + err.message);
          }
        });

        const btn = document.createElement('button');
        btn.className = 'text-left flex-1 min-w-0 flex items-center gap-2';
        btn.setAttribute('data-conv-id', c.id);

        const textContainer = document.createElement('div');
        textContainer.className = 'flex-1 min-w-0 max-w-[180px]';
        const titleEl = document.createElement('div');
        titleEl.className = 'font-medium text-sm truncate ' + (isActive ? 'text-[#2F3440]' : 'text-slate-700 group-hover:text-slate-900');
        titleEl.textContent = c.title || `Conversation ${c.id}`;
        const timeEl = document.createElement('div');
        timeEl.className = 'text-xs text-slate-400';
        timeEl.textContent = new Date(c.updated_at).toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
        textContainer.appendChild(titleEl);
        textContainer.appendChild(timeEl);

        btn.appendChild(textContainer);
        btn.addEventListener('click', async () => {
          // Ensure mobile drawer closes.
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
        renameBtn.className = 'p-1.5 text-slate-400 hover:text-[#B7C9F2] hover:bg-[#B7C9F2]/10 rounded transition-colors';
        renameBtn.setAttribute('data-action', 'rename');
        renameBtn.innerHTML = '<i class="iconoir-edit-pencil"></i>';
        renameBtn.title = 'Rename';
        renameBtn.addEventListener('click', async (e) => {
          e.stopPropagation();
          const title = prompt('New title', c.title || '');
          if (!title) return;
          try {
            await api('/api/conversations/rename.php', { method: 'POST', body: { id: c.id, title } });
            if (currentConversationId === c.id) {
              updateConvTitle(title);
            }
            await loadConversations();
          } catch (err) {
              alert('Error renaming: ' + err.message);
          }
        });

        const moveBtn = document.createElement('button');
        moveBtn.className = 'p-1.5 text-slate-400 hover:text-[#B7C9F2] hover:bg-[#B7C9F2]/10 rounded transition-colors';
        moveBtn.setAttribute('data-action', 'move');
        moveBtn.innerHTML = '<i class="iconoir-folder-settings"></i>';
        moveBtn.title = 'Move to folder';
        moveBtn.addEventListener('click', async (e) => {
          e.stopPropagation();
          openMoveModal(c);
        });

        const delBtn = document.createElement('button');
        delBtn.className = 'p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors';
        delBtn.setAttribute('data-action', 'delete');
        delBtn.innerHTML = '<i class="iconoir-trash"></i>';
        delBtn.title = 'Delete';
        delBtn.addEventListener('click', async (e) => {
          e.stopPropagation();
          if(!confirm('Delete conversation?')) return;
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
            alert('Error deleting: ' + err.message);
          }
        });

        actions.appendChild(renameBtn);
        actions.appendChild(moveBtn);
        actions.appendChild(delBtn);

        container.appendChild(starBtn);
        container.appendChild(btn);
        container.appendChild(actions);
        li.appendChild(container);
        // Make the whole row clickable except action buttons.
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
          // Pass messageId so history messages support selection.
          append(m.role, m.content, m.file || null, m.images || null, null, { messageId: m.id });
        }
        emptyConversationId = null;
      } else {
        showEmptyMode();
        emptyConversationId = conversationId;
      }
    }
    
    function updateConvTitle(title) {
      if (title && title !== 'New conversation') {
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
        // If there is already an empty conversation, reuse it.
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
        alert('Error creating conversation: ' + e.message);
      }
    });

    async function handleSubmit(text, files = []){
      if(!text && (!files || files.length === 0)) return;
      
      const filesArray = Array.isArray(files) ? files : (files ? [files] : []);
      
      // Prevent duplicate sends while generating.
      if (isGenerating) return;
      
      // The conversation is no longer empty: it will receive a message.
      if (emptyConversationId === currentConversationId) {
        emptyConversationId = null;
      }

      // Show chat mode if we were in empty state.
      showChatMode();
      
      const fileToUpload = filesArray.length > 0 ? filesArray[0] : null;
      
      // 1. Show user message immediately.
      const userFile = fileToUpload ? {
        name: fileToUpload.name,
        mime_type: fileToUpload.type || '',
        url: URL.createObjectURL(fileToUpload)
      } : null;
      
      append('user', text, userFile);
      
      // 2. Preparar respuesta del asistente (streaming bubble)
      const { bubble: assistantBubble } = append('assistant', '', null, [], null, { isStreaming: true });
      
      isGenerating = true;
      
      let uploadedFileId = null;

      try {
        // 3. Subir primer archivo si existe
        if (fileToUpload) {
          const formData = new FormData();
          formData.append('file', fileToUpload);
          formData.append('csrf_token', csrf);
          if (currentConversationId) formData.append('conversation_id', currentConversationId);

          const uploadRes = await fetch('/api/files/upload.php', {
            method: 'POST',
            body: formData
          });
          const uploadData = await uploadRes.json().catch(() => ({}));
          if (!uploadRes.ok || !uploadData.success) {
            const reason = uploadData?.error?.message
              || uploadData?.message
              || `HTTP ${uploadRes.status}`;
            throw new Error('Could not upload the file: ' + reason);
          }
          uploadedFileId = uploadData.file_id;
        }

        // 4. Iniciar stream
        const response = await fetch('/api/chat-stream.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf
          },
          body: JSON.stringify({
            message: text,
            conversation_id: currentConversationId,
            file_id: uploadedFileId,
            image_mode: imageMode,
            web_search: webSearchMode,
            model: document.getElementById('model-select-empty')?.value || 'google/gemini-3-flash-preview'
          })
        });

        if (!response.ok) {
          throw new Error('Server error');
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let fullContent = '';

        while (true) {
          const { done, value } = await reader.read();
          if (done) break;

          const chunk = decoder.decode(value);
          const lines = chunk.split('\n');

          for (const line of lines) {
            if (line.startsWith('data: ')) {
              const dataStr = line.substring(6).trim();
              if (dataStr === '[DONE]') continue;

              try {
                const data = JSON.parse(dataStr);
                if (data.type === 'chunk') {
                  fullContent += data.content;
                  updateStreamingMessage(assistantBubble, fullContent);
                } else if (data.type === 'conversation') {
                  currentConversationId = data.id;
                  if (emptyConversationId === currentConversationId) emptyConversationId = null;
                  await loadConversations();
                } else if (data.type === 'meta') {
                  finalizeStreamingMessage(assistantBubble, fullContent, data.images, data.annotations, data.message_id);
                } else if (data.type === 'error') {
                  throw new Error(data.message);
                }
              } catch (e) {
                console.error('Chunk parse error:', e);
              }
            }
          }
        }
      } catch (e) {
        console.error('Stream error:', e);
        assistantBubble.innerHTML = `<span class="text-red-500">Error: ${escapeHtml(e.message)}</span>`;
      } finally {
        isGenerating = false;
        
        // Limpiar archivos después de enviar (unificado)
        currentFiles = [];
        currentFilesEmpty = [];
        if (fileInput) fileInput.value = '';
        if (fileInputEmpty) fileInputEmpty.value = '';
        renderFilesPreview();
        renderFilesPreviewEmpty();
      }
    }

    function fileToBase64(file) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
          // Remove the "data:mime/type;base64," prefix.
          const base64 = reader.result.split(',')[1];
          resolve(base64);
        };
        reader.onerror = reject;
        reader.readAsDataURL(file);
      });
    }

    // Handle file attachment.
    attachBtn.addEventListener('click', () => {
      fileInput.click();
    });

    // Toggle image generation mode.
    const imageModeBtn = document.getElementById('image-mode-btn');
    const webSearchBtn = document.getElementById('web-search-btn');
    const webSearchBtnEmpty = document.getElementById('web-search-btn-empty');
    const chatInput = document.getElementById('chat-input');
    const chatInputEmpty = document.getElementById('chat-input-empty');
    const defaultPlaceholder = 'Write a message...';
    const defaultPlaceholderEmpty = 'Ask Claara anything';
    const imagePlaceholder = 'Describe the image you want to create...';
    const webSearchPlaceholder = 'Ask something and I will search the web...';

    const chatInputMaxHeight = 160;

    // Auto-resize textareas. At max height, they enable internal scrolling.
    function autoResize(textarea) {
      const wasAtBottom = textarea.scrollTop + textarea.clientHeight >= textarea.scrollHeight - 4;
      const caretAtEnd = textarea.selectionStart === textarea.value.length && textarea.selectionEnd === textarea.value.length;
      textarea.style.height = 'auto';
      const nextHeight = Math.min(textarea.scrollHeight, chatInputMaxHeight);
      textarea.style.height = nextHeight + 'px';
      textarea.style.overflowY = textarea.scrollHeight > chatInputMaxHeight ? 'auto' : 'hidden';

      if (textarea.scrollHeight > chatInputMaxHeight && (wasAtBottom || caretAtEnd)) {
        textarea.scrollTop = textarea.scrollHeight;
      }
    }

    function resetChatTextarea(textarea) {
      textarea.value = '';
      textarea.style.height = '';
      textarea.style.overflowY = 'hidden';
      textarea.scrollTop = 0;
    }

    // Auto-resize event listeners
    chatInput.addEventListener('input', () => autoResize(chatInput));
    chatInputEmpty.addEventListener('input', () => autoResize(chatInputEmpty));

    function updateImageModeUI() {
      // Classes for the modern design.
      const btnActive = 'p-2 text-amber-600 bg-amber-50 rounded-lg transition-smooth';
      const btnInactive = 'p-2 text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-smooth';

      if (imageMode) {
        // Normal chat
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
        // Disable web search in image mode.
        webSearchBtn.disabled = true;
        webSearchBtn.classList.add('opacity-50', 'cursor-not-allowed');
        webSearchBtnEmpty.disabled = true;
        webSearchBtnEmpty.classList.add('opacity-50', 'cursor-not-allowed');
        imageModeFilesWarningChat?.classList.remove('hidden');
        imageModeFilesWarningEmpty?.classList.remove('hidden');
      } else {
        // Normal chat
        imageModeBtn.className = btnInactive;
        chatInput.placeholder = webSearchMode ? webSearchPlaceholder : defaultPlaceholder;
        attachBtn.disabled = false;
        attachBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        // Empty state
        imageModeBtnEmpty.className = btnInactive;
        chatInputEmpty.placeholder = webSearchMode ? webSearchPlaceholder : defaultPlaceholderEmpty;
        attachBtnEmpty.disabled = false;
        attachBtnEmpty.classList.remove('opacity-50', 'cursor-not-allowed');
        if (attachBtnEmptyDesktop) {
          attachBtnEmptyDesktop.disabled = false;
          attachBtnEmptyDesktop.classList.remove('opacity-50', 'cursor-not-allowed');
        }
        // Enable web search.
        webSearchBtn.disabled = false;
        webSearchBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        webSearchBtnEmpty.disabled = false;
        webSearchBtnEmpty.classList.remove('opacity-50', 'cursor-not-allowed');
        imageModeFilesWarningChat?.classList.add('hidden');
        imageModeFilesWarningEmpty?.classList.add('hidden');
      }
    }

    function updateWebSearchModeUI() {
      const btnActive = 'p-2 text-cyan-600 bg-cyan-50 rounded-lg transition-smooth';
      const btnInactive = 'p-2 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 rounded-lg transition-smooth';

      if (webSearchMode) {
        webSearchBtn.className = btnActive;
        webSearchBtnEmpty.className = btnActive;
        chatInput.placeholder = webSearchPlaceholder;
        chatInputEmpty.placeholder = webSearchPlaceholder;
        // Disable image mode during web search.
        imageModeBtn.disabled = true;
        imageModeBtn.classList.add('opacity-50', 'cursor-not-allowed');
        imageModeBtnEmpty.disabled = true;
        imageModeBtnEmpty.classList.add('opacity-50', 'cursor-not-allowed');
      } else {
        webSearchBtn.className = btnInactive;
        webSearchBtnEmpty.className = btnInactive;
        chatInput.placeholder = defaultPlaceholder;
        chatInputEmpty.placeholder = defaultPlaceholderEmpty;
        // Enable image mode.
        imageModeBtn.disabled = false;
        imageModeBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        imageModeBtnEmpty.disabled = false;
        imageModeBtnEmpty.classList.remove('opacity-50', 'cursor-not-allowed');
      }
    }

    // Image button listener in normal chat.
    imageModeBtn.addEventListener('click', () => {
      imageMode = !imageMode;
      updateImageModeUI();
      // If image mode is activated, clear attached files.
      if (imageMode && currentFiles.length > 0) {
        currentFiles = [];
        fileInput.value = '';
        renderFilesPreview();
      }
    });

    fileInput.addEventListener('change', (e) => {
      const files = Array.from(e.target.files);
      if (files.length === 0) return;
      validateAndAddFiles(files, currentFiles, renderFilesPreview);
      
      fileInput.value = '';
    });

    clearAllFilesBtn?.addEventListener('click', () => {
      currentFiles = [];
      fileInput.value = '';
      renderFilesPreview();
    });

    function removeFile(index) {
      currentFiles.splice(index, 1);
      renderFilesPreview();
    }
    window.removeFile = removeFile;

    function renderFilesPreview() {
      if (currentFiles.length === 0) {
        filesPreview.classList.add('hidden');
        return;
      }
      
      filesPreview.classList.remove('hidden');
      filesList.innerHTML = currentFiles.map((file, idx) => {
        let iconClass = 'iconoir-page text-[#B7C9F2]';
        if (file.type === 'application/pdf') iconClass = 'iconoir-page text-red-500';
        else if (file.type.startsWith('image/')) iconClass = 'iconoir-media-image text-[#B7C9F2]';
        else if (file.type === 'text/csv' || file.type.includes('spreadsheet') || file.type.includes('excel')) iconClass = 'iconoir-table-rows text-emerald-600';
        
        return `<div class="flex items-center gap-2 p-2 bg-slate-50 rounded-lg">
          <i class="${iconClass} text-lg"></i>
          <span class="flex-1 text-sm text-slate-700 truncate">${escapeHtml(file.name)}</span>
          <span class="text-xs text-slate-400">${formatFileSize(file.size)}</span>
          <button type="button" onclick="removeFile(${idx})" class="text-slate-400 hover:text-red-500">
            <i class="iconoir-xmark"></i>
          </button>
        </div>`;
      }).join('');
    }

    function formatFileSize(bytes) {
      if (bytes < 1024) return bytes + ' B';
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
      return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    formEl.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const text = inputEl.value.trim();
      
      if (!text && currentFiles.length === 0) return;
      
      resetChatTextarea(inputEl);
      // Send only the first file for now. The backend processes one at a time.
      await handleSubmit(text, currentFiles.length > 0 ? currentFiles[0] : null);
      
      // Clear files after sending.
      if (currentFiles.length > 0) {
        currentFiles = [];
        fileInput.value = '';
        renderFilesPreview();
      }
    });

    formEmptyEl.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const text = inputEmptyEl.value.trim();
      
      if (!text && currentFilesEmpty.length === 0) return;
      
      resetChatTextarea(inputEmptyEl);
      // Send only the first file for now.
      await handleSubmit(text, currentFilesEmpty.length > 0 ? currentFilesEmpty[0] : null);
      
      // Clear files after sending.
      if (currentFilesEmpty.length > 0) {
        currentFilesEmpty = [];
        fileInputEmpty.value = '';
        renderFilesPreviewEmpty();
      }
    });

    // Handle file attachment in empty state.
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
      // Si se activa modo imagen, limpiar archivos adjuntos
      if (imageMode && currentFilesEmpty.length > 0) {
        currentFilesEmpty = [];
        fileInputEmpty.value = '';
        renderFilesPreviewEmpty();
      }
      
      // Si el usuario clica y ya hay texto, o para forzar el foco
      inputEmptyEl.focus();
    });

    // Event listeners para botón de búsqueda web
    webSearchBtn.addEventListener('click', () => {
      webSearchMode = !webSearchMode;
      updateWebSearchModeUI();
    });

    webSearchBtnEmpty.addEventListener('click', () => {
      webSearchMode = !webSearchMode;
      updateWebSearchModeUI();
      inputEmptyEl.focus();
    });

    // Asegurar estado visual inicial correcto (texto) para los botones de imagen en empty state
    updateImageModeUI();
    updateWebSearchModeUI();

    fileInputEmpty.addEventListener('change', (e) => {
      const files = Array.from(e.target.files);
      if (files.length === 0) return;
      validateAndAddFiles(files, currentFilesEmpty, renderFilesPreviewEmpty);
      
      fileInputEmpty.value = '';
    });

    messagesContainer.addEventListener('dragenter', (e) => {
      if (!hasDataTransferFiles(e)) return;
      e.preventDefault();
      dragCounter++;
      if (!imageMode) {
        showDropOverlay();
      }
    });

    messagesContainer.addEventListener('dragover', (e) => {
      if (!hasDataTransferFiles(e)) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = imageMode ? 'none' : 'copy';
      if (!imageMode) {
        showDropOverlay();
      }
    });

    messagesContainer.addEventListener('dragleave', (e) => {
      if (!hasDataTransferFiles(e)) return;
      e.preventDefault();
      dragCounter = Math.max(0, dragCounter - 1);
      if (dragCounter === 0) {
        hideDropOverlay();
      }
    });

    messagesContainer.addEventListener('drop', (e) => {
      if (!hasDataTransferFiles(e)) return;
      e.preventDefault();
      dragCounter = 0;
      hideDropOverlay();

      const droppedFiles = Array.from(e.dataTransfer?.files || []);
      if (droppedFiles.length === 0) return;

      addFilesToActiveComposer(droppedFiles);
    });

    inputEl.addEventListener('paste', handleComposerPaste);
    inputEmptyEl.addEventListener('paste', handleComposerPaste);

    clearAllFilesEmptyBtn?.addEventListener('click', () => {
      currentFilesEmpty = [];
      fileInputEmpty.value = '';
      renderFilesPreviewEmpty();
    });

    function removeFileEmpty(index) {
      currentFilesEmpty.splice(index, 1);
      renderFilesPreviewEmpty();
    }
    window.removeFileEmpty = removeFileEmpty;

    function renderFilesPreviewEmpty() {
      if (currentFilesEmpty.length === 0) {
        filesPreviewEmpty.classList.add('hidden');
        return;
      }
      
      filesPreviewEmpty.classList.remove('hidden');
      filesListEmpty.innerHTML = currentFilesEmpty.map((file, idx) => {
        let iconClass = 'iconoir-page text-[#B7C9F2]';
        if (file.type === 'application/pdf') iconClass = 'iconoir-page text-red-500';
        else if (file.type.startsWith('image/')) iconClass = 'iconoir-media-image text-[#B7C9F2]';
        else if (file.type === 'text/csv' || file.type.includes('spreadsheet') || file.type.includes('excel')) iconClass = 'iconoir-table-rows text-emerald-600';
        
        return `<div class="flex items-center gap-2 p-2 bg-slate-50 rounded-lg">
          <i class="${iconClass} text-lg"></i>
          <span class="flex-1 text-sm text-slate-700 truncate">${escapeHtml(file.name)}</span>
          <span class="text-xs text-slate-400">${formatFileSize(file.size)}</span>
          <button type="button" onclick="removeFileEmpty(${idx})" class="text-slate-400 hover:text-red-500">
            <i class="iconoir-xmark"></i>
          </button>
        </div>`;
      }).join('');
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
        
        // Show a temporary message for voices that are not implemented yet.
        const tempMsg = document.createElement('div');
        tempMsg.className = 'fixed top-20 left-1/2 -translate-x-1/2 bg-[#2F3440] text-white px-6 py-3 rounded-xl shadow-lg z-50 flex items-center gap-2';
        tempMsg.innerHTML = `<i class="iconoir-voice-square"></i><span><strong>${voiceName}</strong> will be available soon</span>`;
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
        
        // Redirect to the corresponding views.
        if (tab === 'gestures') {
          window.location.href = '/gestos/';
        } else if (tab === 'voices') {
          window.location.href = '/voices/';
        } else if (tab === 'conversations') {
          // Return to empty state if we are inside a conversation.
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
  
  <!-- Claara Quick Answers Modal -->
  <div id="faq-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col">
      <!-- Header -->
      <div class="p-5 border-b border-slate-200 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl gradient-brand flex items-center justify-center">
            <i class="iconoir-help-circle text-xl text-white"></i>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-slate-900">Ask Claara</h3>
            <p class="text-xs text-slate-500">Quick answers from your workspace</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button id="faq-clear-btn" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" title="New conversation">
            <i class="iconoir-refresh text-lg"></i>
          </button>
          <button id="faq-close-btn" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
            <i class="iconoir-xmark text-xl"></i>
          </button>
        </div>
      </div>
      
      <!-- Messages -->
      <div id="faq-messages" class="flex-1 overflow-y-auto p-5 space-y-4">
        <!-- Initial suggestions -->
        <div id="faq-suggestions" class="space-y-3">
          <p class="text-sm text-slate-600 text-center mb-4">What would you like to know? Try one of these:</p>
          <div class="grid grid-cols-1 gap-2">
            <button class="faq-suggestion p-3 text-left bg-slate-50 hover:bg-[#B7C9F2]/5 border border-slate-200 hover:border-[#B7C9F2] rounded-xl transition-all text-sm text-slate-700 hover:text-[#B7C9F2]">
              What can Claara help me with?
            </button>
            <button class="faq-suggestion p-3 text-left bg-slate-50 hover:bg-[#B7C9F2]/5 border border-slate-200 hover:border-[#B7C9F2] rounded-xl transition-all text-sm text-slate-700 hover:text-[#B7C9F2]">
              How do I get better answers from the assistant?
            </button>
            <button class="faq-suggestion p-3 text-left bg-slate-50 hover:bg-[#B7C9F2]/5 border border-slate-200 hover:border-[#B7C9F2] rounded-xl transition-all text-sm text-slate-700 hover:text-[#B7C9F2]">
              What kind of documents can I upload?
            </button>
            <button class="faq-suggestion p-3 text-left bg-slate-50 hover:bg-[#B7C9F2]/5 border border-slate-200 hover:border-[#B7C9F2] rounded-xl transition-all text-sm text-slate-700 hover:text-[#B7C9F2]">
              How should I use Voices and Gestures?
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
          <span>Thinking...</span>
        </div>
      </div>
      
      <!-- Input -->
      <div class="p-4 border-t border-slate-200 flex-shrink-0">
        <form id="faq-form" class="flex gap-3">
          <input 
            id="faq-input" 
            type="text" 
            class="flex-1 border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-[#B7C9F2] focus:ring-2 focus:ring-[#B7C9F2]/20 transition-all text-sm" 
            placeholder="Ask Claara..."
            autocomplete="off"
          />
          <button type="submit" class="px-5 py-3 gradient-brand-btn text-[#2F3440] rounded-xl font-medium shadow-md hover:shadow-lg hover:opacity-90 transition-all">
            <i class="iconoir-send text-lg"></i>
          </button>
        </form>
      </div>
    </div>
  </div>
  
  <script>
    // Claara modal logic.
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
      
      let faqHistory = []; // In-memory history
      
      // Local helpers
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
      
      // Open modal
      faqBtn.addEventListener('click', () => {
        faqModal.classList.remove('hidden');
        faqInput.focus();
      });
      
      // Close modal
      faqCloseBtn.addEventListener('click', () => {
        faqModal.classList.add('hidden');
      });
      
      // Close on backdrop click
      faqModal.addEventListener('click', (e) => {
        if (e.target === faqModal) {
          faqModal.classList.add('hidden');
        }
      });
      
      // Close with Escape
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !faqModal.classList.contains('hidden')) {
          faqModal.classList.add('hidden');
        }
      });
      
      // Clear conversation
      faqClearBtn.addEventListener('click', () => {
        faqHistory = [];
        faqMessages.innerHTML = faqSuggestions.outerHTML;
        faqSuggestions.classList.remove('hidden');
        bindSuggestions();
      });
      
      // Suggestions
      function bindSuggestions() {
        document.querySelectorAll('.faq-suggestion').forEach(btn => {
          btn.addEventListener('click', () => {
            faqInput.value = btn.textContent.trim();
            faqForm.dispatchEvent(new Event('submit'));
          });
        });
      }
      bindSuggestions();
      
      // Send message
      faqForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = faqInput.value.trim();
        if (!message) return;
        
        // Hide suggestions
        const suggestions = faqMessages.querySelector('#faq-suggestions');
        if (suggestions) suggestions.classList.add('hidden');
        
        // Add user message
        appendFaqMessage('user', message);
        faqInput.value = '';
        faqHistory.push({ role: 'user', content: message });
        
        // Show typing
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
              history: faqHistory.slice(0, -1) // Send history without the current message
            }),
            credentials: 'include'
          });
          
          const data = await res.json();
          faqTyping.classList.add('hidden');
          
          if (!res.ok) {
            appendFaqMessage('assistant', 'Sorry, something went wrong. Please try again.');
            return;
          }
          
          appendFaqMessage('assistant', data.reply);
          faqHistory.push({ role: 'assistant', content: data.reply });
          
        } catch (err) {
          faqTyping.classList.add('hidden');
          appendFaqMessage('assistant', 'Connection error. Please try again.');
        }
      });
      
      function appendFaqMessage(role, content) {
        const div = document.createElement('div');
        div.className = 'flex gap-3 ' + (role === 'user' ? 'justify-end' : 'justify-start');
        
        // Get user initials from the existing DOM avatar.
        const userInitials = document.getElementById('user-avatar')?.textContent?.trim() || '?';
        
        const avatar = role === 'user' 
          ? `<div class="w-8 h-8 rounded-full gradient-brand flex items-center justify-center text-[#2F3440] text-xs font-semibold flex-shrink-0">${userInitials}</div>`
          : `<div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 text-xs font-semibold flex-shrink-0">N</div>`;
        
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
          if (el) el.textContent = 'Ctrl + Enter to send';
        });
      }
      window.updateEmptyGreeting?.();
      window.focusEmptyComposer?.();

      // Sincronizar selectores de modelos (Solo Superadmin)
      const modelSelectEmpty = document.getElementById('model-select-empty');
      const modelSelectChat = document.getElementById('model-select-chat');
      const manageModelsBtnEmpty = document.getElementById('manage-models-btn-empty');
      const manageModelsBtnChat = document.getElementById('manage-models-btn-chat');

      function escapeModelHtml(str) {
        return String(str).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
      }

      async function loadModels() {
        if (!modelSelectEmpty || !modelSelectChat) return;

        try {
          const response = window.appApi
            ? await window.appApi('/api/models/list.php')
            : await fetch('/api/models/list.php', { credentials: 'include' }).then(async (res) => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data?.error?.message || res.statusText);
                return data;
              });
          const models = Array.isArray(response.models) ? response.models : [];
          if (models.length === 0) {
            modelSelectEmpty.innerHTML = '<option value="google/gemini-3-flash-preview">Gemini 3 Flash</option>';
            modelSelectChat.innerHTML = modelSelectEmpty.innerHTML;
            return;
          }

          const currentValue = modelSelectEmpty.value || modelSelectChat.value || models[0].model_key;

          const options = models.map((m) => {
            const value = escapeModelHtml(m.model_key || '');
            const label = escapeModelHtml(m.label || m.model_key || 'Model');
            return `<option value="${value}">${label}</option>`;
          }).join('');

          modelSelectEmpty.innerHTML = options;
          modelSelectChat.innerHTML = options;

          const hasCurrent = models.some((m) => m.model_key === currentValue);
          const selected = hasCurrent ? currentValue : models[0].model_key;
          modelSelectEmpty.value = selected;
          modelSelectChat.value = selected;
        } catch (error) {
          console.warn('Could not load model catalog:', error);
          modelSelectEmpty.innerHTML = '<option value="google/gemini-3-flash-preview">Gemini 3 Flash</option>';
          modelSelectChat.innerHTML = modelSelectEmpty.innerHTML;
        }
      }

      function openManageModels() {
        window.location.href = '/admin/models.php';
      }

      if (modelSelectEmpty && modelSelectChat) {
        loadModels();

        modelSelectEmpty.addEventListener('change', () => {
          modelSelectChat.value = modelSelectEmpty.value;
        });
        modelSelectChat.addEventListener('change', () => {
          modelSelectEmpty.value = modelSelectChat.value;
        });

        if (manageModelsBtnEmpty) {
          manageModelsBtnEmpty.addEventListener('click', openManageModels);
        }
        if (manageModelsBtnChat) {
          manageModelsBtnChat.addEventListener('click', openManageModels);
        }
      }

      const desktopSidebar = document.getElementById('conversations-sidebar');
      const mobileDrawerContent = document.getElementById('conversations-drawer-content');
      
      if (desktopSidebar && mobileDrawerContent) {
        // Clone folder and conversation content into the mobile drawer.
        const foldersSection = desktopSidebar.querySelector('.flex-1.overflow-y-auto');
        if (foldersSection) {
          mobileDrawerContent.innerHTML = foldersSection.innerHTML;
          // Force action visibility. There is no hover on mobile.
          mobileDrawerContent.querySelectorAll('.group .opacity-0').forEach(el => {
            el.classList.remove('opacity-0');
            el.classList.add('opacity-100');
          });
        }
        
        // Keep the mobile drawer synchronized.
        const observer = new MutationObserver(() => {
          if (foldersSection) {
            mobileDrawerContent.innerHTML = foldersSection.innerHTML;
            // Force action visibility after refresh.
            mobileDrawerContent.querySelectorAll('.group .opacity-0').forEach(el => {
              el.classList.remove('opacity-0');
              el.classList.add('opacity-100');
            });
          }
        });
        
        observer.observe(desktopSidebar, { childList: true, subtree: true });
        
        // Event delegation for mobile drawer clicks.
        mobileDrawerContent.addEventListener('click', (e) => {
          // "New folder" button
          const newFolderBtnMobile = e.target.closest('#new-folder-btn');
          if (newFolderBtnMobile) {
            const desktopNewFolderBtn = desktopSidebar.querySelector('#new-folder-btn');
            if (desktopNewFolderBtn) desktopNewFolderBtn.click();
            return;
          }

          // Check if a conversation was clicked.
          const convItem = e.target.closest('[data-conv-id]');
          if (convItem) {
            const convId = convItem.getAttribute('data-conv-id');
            // Was an action button clicked inside the conversation?
            const actionBtn = e.target.closest('[data-action]');
            if (actionBtn) {
              const action = actionBtn.getAttribute('data-action');
              const desktopRow = desktopSidebar.querySelector(`[data-conv-id="${convId}"]`);
              if (desktopRow) {
                const desktopAction = desktopRow.querySelector(`[data-action="${action}"]`);
                if (desktopAction) {
                  e.preventDefault();
                  e.stopPropagation();
                  // Do not close the drawer for actions that do not change view, except move, which opens a modal.
                  if (action === 'move') closeMobileDrawer('conversations-drawer');
                  desktopAction.click();
                }
              }
              return;
            }
            // Conversation click (open)
            const desktopConv = desktopSidebar.querySelector(`[data-conv-id="${convId}"]`);
            if (desktopConv) {
              closeMobileDrawer('conversations-drawer');
              // Click the main button inside the row.
              const mainBtn = desktopConv.querySelector('[data-conv-id]');
              if (mainBtn) mainBtn.click(); else desktopConv.click();
            }
            return;
          }
          
          // Check if a folder was clicked.
          const folderItem = e.target.closest('[data-folder-id]');
          if (folderItem) {
            const folderId = folderItem.getAttribute('data-folder-id');
            // Was a folder action clicked?
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
            // Find and click the corresponding desktop folder.
            const desktopFolder = desktopSidebar.querySelector(`[data-folder-id="${folderId}"]`);
            if (desktopFolder) {
              desktopFolder.click();
              // Refresh drawer content after click.
              setTimeout(() => {
                if (foldersSection) {
                  mobileDrawerContent.innerHTML = foldersSection.innerHTML;
                  // Reapply action visibility.
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
      
      // Sync mobile new conversation button.
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
