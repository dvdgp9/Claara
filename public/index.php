<?php
require_once __DIR__ . '/../src/App/bootstrap.php';

use App\Session;

Session::start();
$user = Session::user();
$appHref = $user ? '/app/' : '/login.php';
$ctaLabel = $user ? 'Open workspace' : 'Log in';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Claara — AI workspace for Grupo Ebone</title>
  <meta name="description" content="Claara brings chat, specialized voices, content gestures, and connected knowledge into one private AI workspace for Grupo Ebone." />
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" href="/assets/images/isotipo.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
  <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body class="claara-landing-page text-slate-900">
  <header class="claara-landing-nav">
    <a href="/" class="claara-landing-brand" aria-label="Claara home">
      <img src="/assets/images/claara-logo.png" alt="Claara" class="h-12 sm:h-14">
    </a>
    <nav class="hidden lg:flex items-center gap-7 text-sm font-medium text-slate-600" aria-label="Primary">
      <a href="#workspace" class="claara-landing-link">Workspace</a>
      <a href="#workflows" class="claara-landing-link">Workflows</a>
      <a href="#company" class="claara-landing-link">For companies</a>
      <a href="#access" class="claara-landing-link">Access</a>
    </nav>
    <a href="<?php echo htmlspecialchars($appHref); ?>" class="claara-landing-login">
      <span><?php echo htmlspecialchars($ctaLabel); ?></span>
      <i class="iconoir-arrow-right" aria-hidden="true"></i>
    </a>
  </header>

  <main>
    <section class="claara-landing-hero">
      <div class="claara-landing-hero-copy">
        <p class="claara-landing-kicker">B2B AI workspace</p>
        <h1>Claara brings company AI into controlled daily work.</h1>
        <p class="claara-landing-lede">
          A private platform for teams that need useful AI without losing structure: chat, files, web search, specialized assistants, guided workflows, permissions, and company context in one place.
        </p>
        <div class="claara-hero-proof">
          <span><i class="iconoir-lock" aria-hidden="true"></i> Session-based access</span>
          <span><i class="iconoir-building" aria-hidden="true"></i> Companies and departments</span>
          <span><i class="iconoir-settings" aria-hidden="true"></i> Admin controls</span>
        </div>
        <div class="claara-landing-actions">
          <a href="<?php echo htmlspecialchars($appHref); ?>" class="claara-landing-primary">
            <span><?php echo htmlspecialchars($ctaLabel); ?></span>
            <i class="iconoir-arrow-up-right" aria-hidden="true"></i>
          </a>
          <a href="#workflows" class="claara-landing-secondary">Explore the platform</a>
        </div>
      </div>

      <div id="workspace" class="claara-product-frame" aria-label="Claara workspace preview">
        <div class="claara-product-rail">
          <span class="is-active"><i class="iconoir-chat-bubble" aria-hidden="true"></i></span>
          <span><i class="iconoir-voice-square" aria-hidden="true"></i></span>
          <span><i class="iconoir-magic-wand" aria-hidden="true"></i></span>
        </div>
        <div class="claara-product-main">
          <div class="claara-product-topbar">
            <div>
              <span class="claara-product-eyebrow">Company workspace</span>
              <strong>Operations briefing</strong>
            </div>
            <span class="claara-product-status">Context ready</span>
          </div>
          <div class="claara-product-chat">
            <div class="claara-product-bubble user">Summarize the uploaded tender and prepare actions for the project team.</div>
            <div class="claara-product-bubble assistant">
              <span>Claara extracted requirements, non-staff costs, open risks, and the next review points.</span>
              <div class="claara-product-lines" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
              </div>
            </div>
          </div>
          <div class="claara-product-dock">
            <span><i class="iconoir-attachment" aria-hidden="true"></i> Files</span>
            <span><i class="iconoir-globe" aria-hidden="true"></i> Web</span>
            <span><i class="iconoir-media-image" aria-hidden="true"></i> Images</span>
            <span><i class="iconoir-page" aria-hidden="true"></i> PDF and Word</span>
          </div>
        </div>
        <aside class="claara-product-side">
          <div>
            <span>Voices</span>
            <strong>Lex</strong>
            <small>Legal assistant</small>
          </div>
          <div>
            <span>Gestures</span>
            <strong>Project Analysis</strong>
            <small>Tender document review</small>
          </div>
          <div>
            <span>Connectors</span>
            <strong>Drive files</strong>
            <small>Selected source context</small>
          </div>
        </aside>
      </div>
    </section>

    <section id="workflows" class="claara-landing-capabilities">
      <div class="claara-section-heading">
        <p class="claara-landing-kicker">What the platform does</p>
        <h2>Claara is built for operational teams, not one-off prompts.</h2>
      </div>
      <div class="claara-capability-grid">
        <article class="claara-capability-block block-wide">
          <i class="iconoir-chat-bubble" aria-hidden="true"></i>
          <h3>Chat that becomes a workbench</h3>
          <p>Start a conversation, attach PDFs, images, spreadsheets, CSV files, or Excel files, choose web search when the answer depends on current information, and generate downloadable PDF or Word drafts when the output needs to leave the chat.</p>
        </article>
        <article class="claara-capability-block">
          <i class="iconoir-voice-square" aria-hidden="true"></i>
          <h3>Specialized voices</h3>
          <p>Use domain assistants with targeted reference material. Lex handles legal and labor questions with documents, citations, source match indicators, and conflict warnings when sources disagree.</p>
        </article>
        <article class="claara-capability-block">
          <i class="iconoir-magic-wand" aria-hidden="true"></i>
          <h3>Guided gestures</h3>
          <p>Run repeatable workflows with forms, history, exports, uploads, and purpose-built prompts instead of rebuilding the same instructions every time.</p>
        </article>
      </div>
    </section>

    <section class="claara-b2b-section">
      <div class="claara-b2b-copy">
        <p class="claara-landing-kicker">For companies</p>
        <h2>Designed for controlled adoption across departments.</h2>
        <p>
          Claara is structured around users, departments, permissions, and internal context. Superadmins can manage models, users, feature access, connectors, and the document collections that feed specialized assistants.
        </p>
      </div>
      <div class="claara-b2b-grid">
        <div class="claara-b2b-item">
          <i class="iconoir-user-badge-check" aria-hidden="true"></i>
          <strong>Role and feature access</strong>
          <span>Give each user the voices, gestures, and image features they should actually use.</span>
        </div>
        <div class="claara-b2b-item">
          <i class="iconoir-database" aria-hidden="true"></i>
          <strong>Managed knowledge</strong>
          <span>Admin tools sync, upload, edit, and index context documents for general chat, FAQ, and Lex.</span>
        </div>
        <div class="claara-b2b-item">
          <i class="iconoir-cloud-sync" aria-hidden="true"></i>
          <strong>Selected sources</strong>
          <span>Connectors start with a Google Drive fast path so teams can bring chosen files into context deliberately.</span>
        </div>
        <div class="claara-b2b-item">
          <i class="iconoir-privacy-policy" aria-hidden="true"></i>
          <strong>Private workspace</strong>
          <span>Authenticated access, CSRF-protected actions, conversation history, folders, favorites, and account controls.</span>
        </div>
      </div>
    </section>

    <section id="company" class="claara-operating-section">
      <div class="claara-section-heading compact">
        <p class="claara-landing-kicker">The operating layer</p>
        <h2>From a question to a finished deliverable.</h2>
      </div>
      <div class="claara-operating-grid">
        <article>
          <span>01</span>
          <h3>Bring context</h3>
          <p>Upload files, paste source text, point to an article, search the web, or connect selected Drive files when a task needs grounded material.</p>
        </article>
        <article>
          <span>02</span>
          <h3>Choose the right mode</h3>
          <p>Use general chat for open work, Lex for legal reference, or a gesture when the output needs a guided structure and repeatable inputs.</p>
        </article>
        <article>
          <span>03</span>
          <h3>Review and reuse</h3>
          <p>Keep conversations, histories, generated assets, exports, transcripts, lead tables, SOP documents, and course materials available for the team workflow.</p>
        </article>
      </div>
    </section>

    <section class="claara-library-section">
      <div class="claara-library-head">
        <div>
          <p class="claara-landing-kicker">Workflow library</p>
          <h2>Built-in gestures cover the work teams repeat most.</h2>
        </div>
        <p>Each gesture narrows the task, asks for the useful inputs, and keeps the result easier to review than a blank chat prompt.</p>
      </div>
      <div class="claara-library-grid">
        <article><i class="iconoir-page-edit" aria-hidden="true"></i><h3>Write content</h3><p>Articles, blog posts, SEO structures, and press notes.</p></article>
        <article><i class="iconoir-send-diagonal" aria-hidden="true"></i><h3>Social media</h3><p>Channel-aware posts with intent, tone, and variant controls.</p></article>
        <article><i class="iconoir-podcast" aria-hidden="true"></i><h3>Podcast from article</h3><p>Turn source content into a two-host audio script and generated podcast.</p></article>
        <article><i class="iconoir-media-image" aria-hidden="true"></i><h3>Image studio</h3><p>Create or edit brand-ready images with prompts and reference uploads.</p></article>
        <article><i class="iconoir-refresh-double" aria-hidden="true"></i><h3>Content transformer</h3><p>Repurpose material into posts, blogs, landing pages, newsletters, and FAQs.</p></article>
        <article><i class="iconoir-clipboard-check" aria-hidden="true"></i><h3>Process generator</h3><p>Build SOPs from text, audio, screenshots, PDFs, or web sources.</p></article>
        <article><i class="iconoir-microphone" aria-hidden="true"></i><h3>Audio transcriber</h3><p>Convert meetings, interviews, and voice notes into readable text.</p></article>
        <article><i class="iconoir-graduation-cap" aria-hidden="true"></i><h3>Course creator</h3><p>Generate editable outlines, modules, quizzes, handouts, exams, and supporting material.</p></article>
        <article><i class="iconoir-folder-settings" aria-hidden="true"></i><h3>Project analysis</h3><p>Extract tender costs, hours, requirements, and operational signals from documents.</p></article>
        <article><i class="iconoir-search-window" aria-hidden="true"></i><h3>Lead Finder</h3><p>Search, review, validate, and export structured leads from a plain request.</p></article>
      </div>
    </section>

    <section class="claara-knowledge-section">
      <div class="claara-knowledge-panel">
        <p class="claara-landing-kicker">Knowledge and governance</p>
        <h2>Company context stays visible and maintainable.</h2>
        <p>
          Claara supports general context files, FAQ context, indexed Lex knowledge, conversation storage, usage logging, model management, and admin pages for users, departments, permissions, connectors, and context documents.
        </p>
      </div>
      <div class="claara-knowledge-list">
        <div><strong>Context manager</strong><span>Upload, sync, view, edit, delete, and process reference documents.</span></div>
        <div><strong>RAG for Lex</strong><span>Qdrant-backed retrieval, source matching, citations, and conflict metadata.</span></div>
        <div><strong>Model control</strong><span>Superadmins can manage available models without changing code.</span></div>
        <div><strong>Usage visibility</strong><span>Stats and usage logs help track adoption and activity.</span></div>
      </div>
    </section>

    <section id="access" class="claara-landing-access">
      <div>
        <p class="claara-landing-kicker">Access</p>
        <h2>Start from the landing. Work inside the private app.</h2>
        <p>Visitors understand the platform first. Authenticated users continue into the workspace with one button.</p>
      </div>
      <a href="<?php echo htmlspecialchars($appHref); ?>" class="claara-landing-primary">
        <span><?php echo htmlspecialchars($ctaLabel); ?></span>
        <i class="iconoir-arrow-right" aria-hidden="true"></i>
      </a>
    </section>
  </main>
</body>
</html>
