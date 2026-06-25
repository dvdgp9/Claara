<?php
require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Auth/VoiceEditorGuard.php';

use App\Session;
use Auth\VoiceEditorGuard;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

VoiceEditorGuard::requireCanEdit($user);

$csrfToken = $_SESSION['csrf_token'] ?? '';
$activeTab = 'voices';
$pageTitle = 'Voice Studio';
$headerTitle = 'Voice Studio';
$headerSubtitle = 'Create and test RAG voices';
$headerIcon = 'iconoir-voice-square';
$headerIconColor = 'from-slate-700 to-cyan-700';
$headerBackUrl = '/app/';
$headerBackText = 'Chat';
?><!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="bg-slate-50 text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>

    <main class="flex-1 flex flex-col min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <div class="flex-1 overflow-auto bg-slate-50 pb-16 lg:pb-0">
        <div class="voice-admin-shell">
          <section class="voice-admin-hero">
            <div>
              <p class="voice-admin-kicker">Administration</p>
              <h1>Voice Studio</h1>
              <p>Build specialized RAG assistants for teams, keep their instructions clear, and test their behavior before publishing them to the company.</p>
            </div>
            <button id="voice-new-btn" class="voice-primary-btn" type="button">
              <i class="iconoir-plus-circle"></i>
              <span>New voice</span>
            </button>
          </section>

          <div id="voice-alert" class="voice-alert hidden" role="status"></div>

          <section class="voice-admin-grid">
            <aside class="voice-list-panel">
              <div class="voice-panel-head">
                <div>
                  <h2>Catalog</h2>
                  <p id="voice-count">Loading voices</p>
                </div>
                <button id="voice-refresh-btn" class="voice-icon-btn" type="button" title="Refresh voices">
                  <i class="iconoir-refresh"></i>
                </button>
              </div>
              <div id="voice-list-loading" class="voice-skeleton-list">
                <div></div><div></div><div></div>
              </div>
              <div id="voice-empty" class="voice-empty hidden">
                <i class="iconoir-voice-square"></i>
                <h3>No voices yet</h3>
                <p>Create the first RAG voice for a business area.</p>
              </div>
              <div id="voice-list" class="voice-list hidden"></div>
            </aside>

            <section class="voice-editor-panel">
              <div class="voice-editor-top">
                <div>
                  <p id="voice-editor-status" class="voice-status-pill">Draft</p>
                  <h2 id="voice-editor-title">Create a voice</h2>
                  <p id="voice-editor-subtitle">Define identity, operating instructions, and when Claara should suggest this voice.</p>
                </div>
                <div class="voice-editor-actions">
                  <button id="voice-archive-btn" class="voice-secondary-btn hidden" type="button">
                    <i class="iconoir-archive"></i>
                    <span>Archive</span>
                  </button>
                  <button id="voice-publish-btn" class="voice-primary-btn" type="button">
                    <i class="iconoir-check-circle"></i>
                    <span>Publish</span>
                  </button>
                </div>
              </div>

              <form id="voice-form" class="voice-form">
                <div class="voice-form-row">
                  <label>
                    <span>Name</span>
                    <input id="voice-name" name="name" type="text" maxlength="100" placeholder="Legal Operations" required>
                    <small>The human name shown in Claara.</small>
                  </label>
                  <label>
                    <span>Slug</span>
                    <input id="voice-slug" name="slug" type="text" maxlength="50" placeholder="legal-ops" required>
                    <small>Lowercase identifier. It cannot be changed after creation.</small>
                  </label>
                </div>

                <div class="voice-form-row">
                  <label>
                    <span>Area or role</span>
                    <input id="voice-role" name="role" type="text" maxlength="120" placeholder="Legal knowledge assistant">
                    <small>Short internal role for the assistant.</small>
                  </label>
                  <label>
                    <span>Visual tone</span>
                    <select id="voice-color" name="color">
                      <option value="slate">Slate</option>
                      <option value="cyan">Cyan</option>
                      <option value="emerald">Emerald</option>
                      <option value="rose">Rose</option>
                      <option value="amber">Amber</option>
                    </select>
                    <small>Used for admin state and future voice pages.</small>
                  </label>
                </div>

                <label>
                  <span>Description</span>
                  <textarea id="voice-description" name="description" rows="2" maxlength="255" placeholder="Answers questions about legal documents, policies, agreements, and internal procedures."></textarea>
                  <small>Concrete summary shown in permissions and catalogs.</small>
                </label>

                <label>
                  <span>Instructions</span>
                  <textarea id="voice-instructions" name="instructions" rows="7" maxlength="4000" placeholder="Answer only from the available knowledge base when possible. Be precise, cite sources, and say when the documents are insufficient."></textarea>
                  <small>This is the operating prompt for the voice.</small>
                </label>

                <label>
                  <span>When Claara should suggest it</span>
                  <textarea id="voice-trigger" name="trigger_guidance" rows="4" maxlength="1000" placeholder="Use this voice when the user asks about contracts, agreements, labor policy, compliance, rights, leave, or legal procedures."></textarea>
                  <small>Future chat routing will use this guidance to recommend the voice.</small>
                </label>

                <label>
                  <span>Responsible users</span>
                  <select id="voice-responsibles" name="responsible_user_ids" multiple size="5"></select>
                  <small>Responsible users keep access to this voice. Hold Cmd/Ctrl to select more than one person.</small>
                </label>

                <div class="voice-form-footer">
                  <p id="voice-form-note">Draft voices can be tested here before publishing.</p>
                  <button id="voice-save-btn" class="voice-primary-btn" type="submit">
                    <i class="iconoir-floppy-disk"></i>
                    <span>Save voice</span>
                  </button>
                </div>
              </form>

              <section class="voice-knowledge-panel">
                <div class="voice-panel-head">
                  <div>
                    <h2>Knowledge</h2>
                    <p id="voice-knowledge-summary">Add documents before testing RAG answers.</p>
                  </div>
                  <button id="voice-process-all-btn" class="voice-secondary-btn" type="button">
                    <i class="iconoir-database-script"></i>
                    <span>Process all</span>
                  </button>
                </div>
                <div class="voice-knowledge-body">
                  <aside class="voice-folders-col">
                    <div class="voice-folders-head">
                      <span>Folders</span>
                      <button id="voice-folder-new-btn" class="voice-icon-btn" type="button" title="New folder">
                        <i class="iconoir-folder-plus"></i>
                      </button>
                    </div>
                    <div id="voice-folder-tree" class="voice-folder-tree">
                      <div class="voice-documents-empty">Select a voice.</div>
                    </div>
                  </aside>
                  <div class="voice-docs-col">
                    <div id="voice-folder-breadcrumb" class="voice-folder-breadcrumb"></div>
                    <form id="voice-document-form" class="voice-document-form">
                      <input id="voice-document-file" type="file" accept=".pdf,.txt,.md" multiple required>
                      <input id="voice-document-description" type="text" maxlength="255" placeholder="Optional description">
                      <button id="voice-document-upload-btn" class="voice-secondary-btn" type="submit">
                        <i class="iconoir-upload"></i>
                        <span>Upload</span>
                      </button>
                    </form>
                    <div class="voice-folder-upload-row">
                      <input id="voice-folder-file" type="file" multiple hidden webkitdirectory directory>
                      <button id="voice-folder-upload-btn" class="voice-ghost-btn" type="button">
                        <i class="iconoir-folder"></i>
                        <span>Upload a folder — keeps its structure</span>
                      </button>
                      <span id="voice-folder-upload-hint" class="voice-folder-hint"></span>
                    </div>
                    <div id="voice-documents-list" class="voice-documents-list">
                      <div class="voice-documents-empty">Select a voice to manage its knowledge.</div>
                    </div>
                  </div>
                </div>
              </section>
            </section>
          </section>
        </div>
      </div>
    </main>
  </div>

  <script src="/assets/js/admin-voices.js"></script>
</body>
</html>
