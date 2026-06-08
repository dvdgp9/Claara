<?php
require_once __DIR__ . '/../src/App/bootstrap.php';
require_once __DIR__ . '/../src/Auth/AuthService.php';
require_once __DIR__ . '/../src/Repos/VoiceFlagsRepo.php';

use App\Session;
use Repos\VoiceFlagsRepo;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

$isAdmin = !empty($user['is_superadmin']) || in_array('admin', $user['roles'] ?? [], true);
$flagsRepo = new VoiceFlagsRepo();
$canSee = $isAdmin || $flagsRepo->isResponsibleForAnyVoice((int)$user['id']);
if (!$canSee) {
    header('Location: /app/');
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Reports — Claara</title>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" href="/assets/images/isotipo.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
  <link rel="stylesheet" href="/assets/css/styles.css">
  <style>
    .gradient-brand { background: linear-gradient(135deg, #B7C9F2 0%, #2F3440 100%); }
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
  </style>
</head>
<body class="bg-slate-50 text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php
    $activeTab = 'flags';
    $pageTitle = 'Reports';
    include __DIR__ . '/includes/left-tabs.php';
    ?>

    <main class="flex-1 flex flex-col min-w-0">
      <?php include __DIR__ . '/includes/header-unified.php'; ?>

      <div class="flex-1 overflow-auto bg-slate-50 pb-16 lg:pb-0">
        <div class="max-w-5xl mx-auto p-4 lg:p-6">

          <div class="mt-6 mb-6">
            <div class="text-xs font-semibold uppercase tracking-wider text-[#B7C9F2]">Voice responsibility</div>
            <h1 class="text-3xl font-bold text-slate-900">Reports</h1>
            <p class="text-slate-500 mt-1">Issues users flagged on the voices you are responsible for.</p>
          </div>

          <!-- Status filter -->
          <div id="flag-filters" class="flex flex-wrap gap-2 mb-6">
            <button data-status="" class="flag-filter-btn organization-tab is-active px-3.5 py-1.5 rounded-full text-sm border border-slate-200">All</button>
            <button data-status="open" class="flag-filter-btn organization-tab px-3.5 py-1.5 rounded-full text-sm border border-slate-200">Open</button>
            <button data-status="in_progress" class="flag-filter-btn organization-tab px-3.5 py-1.5 rounded-full text-sm border border-slate-200">In progress</button>
            <button data-status="resolved" class="flag-filter-btn organization-tab px-3.5 py-1.5 rounded-full text-sm border border-slate-200">Resolved</button>
            <button data-status="dismissed" class="flag-filter-btn organization-tab px-3.5 py-1.5 rounded-full text-sm border border-slate-200">Dismissed</button>
          </div>

          <div id="flags-loading" class="text-center py-16 text-slate-400">
            <i class="iconoir-refresh-double text-3xl animate-spin"></i>
            <p class="mt-2 text-sm">Loading reports...</p>
          </div>

          <div id="flags-empty" class="hidden text-center py-16">
            <i class="iconoir-warning-triangle text-4xl text-slate-300"></i>
            <p class="text-slate-500 mt-3">No reports here.</p>
          </div>

          <div id="flags-list" class="space-y-3"></div>

          <div id="unassigned-section" class="hidden mt-10">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-2">
              <i class="iconoir-help-circle"></i> Unassigned (no responsible)
            </h2>
            <div id="unassigned-list" class="space-y-3"></div>
          </div>

        </div>
      </div>
    </main>
  </div>

  <script>
    const csrf = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
    const IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    let currentStatus = '';

    async function api(path, opts = {}) {
      const res = await fetch(path, {
        method: opts.method || 'GET',
        headers: { 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-Token': csrf } : {}) },
        body: opts.body ? JSON.stringify(opts.body) : undefined,
        credentials: 'include'
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data?.error?.message || res.statusText);
      return data;
    }

    function escapeHtml(str) {
      return String(str ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    const TYPE_LABELS = { missing_info: 'Missing info', incorrect: 'Incorrect', other: 'Other' };
    const STATUS_STYLES = {
      open: 'bg-amber-50 text-amber-700 border-amber-200',
      in_progress: 'bg-sky-50 text-sky-700 border-sky-200',
      resolved: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      dismissed: 'bg-slate-100 text-slate-500 border-slate-200'
    };
    const STATUS_LABELS = { open: 'Open', in_progress: 'In progress', resolved: 'Resolved', dismissed: 'Dismissed' };

    function fmtDate(s) {
      if (!s) return '';
      const d = new Date(s.replace(' ', 'T'));
      if (isNaN(d)) return s;
      return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
        ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    }

    function flagCard(f) {
      const statusCls = STATUS_STYLES[f.status] || STATUS_STYLES.open;
      const actions = [];
      if (f.status === 'open') {
        actions.push(`<button onclick="updateFlag(${f.id}, 'in_progress')" class="flag-act px-3 py-1.5 text-sm font-medium text-sky-700 hover:bg-sky-50 rounded-lg">Take</button>`);
        actions.push(`<button onclick="resolveFlag(${f.id})" class="flag-act px-3 py-1.5 text-sm font-medium text-emerald-700 hover:bg-emerald-50 rounded-lg">Resolve</button>`);
        actions.push(`<button onclick="updateFlag(${f.id}, 'dismissed')" class="flag-act px-3 py-1.5 text-sm font-medium text-slate-500 hover:bg-slate-100 rounded-lg">Dismiss</button>`);
      } else if (f.status === 'in_progress') {
        actions.push(`<button onclick="resolveFlag(${f.id})" class="flag-act px-3 py-1.5 text-sm font-medium text-emerald-700 hover:bg-emerald-50 rounded-lg">Resolve</button>`);
        actions.push(`<button onclick="updateFlag(${f.id}, 'dismissed')" class="flag-act px-3 py-1.5 text-sm font-medium text-slate-500 hover:bg-slate-100 rounded-lg">Dismiss</button>`);
      } else {
        actions.push(`<button onclick="updateFlag(${f.id}, 'open')" class="flag-act px-3 py-1.5 text-sm font-medium text-amber-700 hover:bg-amber-50 rounded-lg">Reopen</button>`);
      }

      const excerpt = f.message_excerpt
        ? `<div class="mt-3 text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-lg p-3 max-h-28 overflow-auto whitespace-pre-wrap">${escapeHtml(f.message_excerpt)}</div>`
        : '';
      const resolution = (f.resolved_by && f.resolution_note)
        ? `<div class="mt-2 text-xs text-emerald-700"><i class="iconoir-check"></i> ${escapeHtml(f.resolved_by.name)}: ${escapeHtml(f.resolution_note)}</div>`
        : '';

      return `
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-800"><i class="iconoir-voice-square text-[#B7C9F2]"></i>${escapeHtml(f.voice_name || f.voice_slug || 'General')}</span>
                <span class="px-2 py-0.5 rounded-full bg-[#FF8B73]/10 text-[#c2410c] text-xs font-medium">${escapeHtml(TYPE_LABELS[f.type] || f.type)}</span>
                <span class="px-2 py-0.5 rounded-full border text-xs font-medium ${statusCls}">${escapeHtml(STATUS_LABELS[f.status] || f.status)}</span>
              </div>
              ${f.note ? `<p class="mt-2 text-sm text-slate-700 whitespace-pre-wrap">${escapeHtml(f.note)}</p>` : '<p class="mt-2 text-sm text-slate-400 italic">No details provided.</p>'}
              ${excerpt}
              ${resolution}
              <div class="mt-2 text-xs text-slate-400">Reported by ${escapeHtml(f.raised_by?.name || 'Unknown')} · ${escapeHtml(fmtDate(f.created_at))}</div>
            </div>
          </div>
          <div class="mt-3 flex items-center justify-end gap-1 border-t border-slate-100 pt-2">${actions.join('')}</div>
        </div>
      `;
    }

    async function loadFlags() {
      const loading = document.getElementById('flags-loading');
      const empty = document.getElementById('flags-empty');
      const list = document.getElementById('flags-list');
      const unassignedSection = document.getElementById('unassigned-section');
      const unassignedList = document.getElementById('unassigned-list');

      loading.classList.remove('hidden');
      empty.classList.add('hidden');
      list.innerHTML = '';
      unassignedSection.classList.add('hidden');

      try {
        const q = currentStatus ? `?status=${encodeURIComponent(currentStatus)}` : '';
        const data = await api(`/api/flags/list.php${q}`);
        loading.classList.add('hidden');

        const flags = data.flags || [];
        if (flags.length === 0 && (data.unassigned || []).length === 0) {
          empty.classList.remove('hidden');
        } else {
          list.innerHTML = flags.map(flagCard).join('');
        }

        if (IS_ADMIN && (data.unassigned || []).length > 0) {
          unassignedSection.classList.remove('hidden');
          unassignedList.innerHTML = data.unassigned.map(flagCard).join('');
        }
      } catch (e) {
        loading.classList.add('hidden');
        empty.classList.remove('hidden');
        empty.querySelector('p').textContent = 'Could not load reports: ' + e.message;
      }
    }

    async function updateFlag(id, status, note) {
      try {
        await api('/api/flags/update.php', { method: 'POST', body: { id, status, resolution_note: note || null } });
        loadFlags();
      } catch (e) {
        alert('Could not update: ' + e.message);
      }
    }

    function resolveFlag(id) {
      const note = prompt('Resolution note (optional):', '');
      if (note === null) return; // cancelled
      updateFlag(id, 'resolved', note.trim());
    }

    document.getElementById('flag-filters').addEventListener('click', (e) => {
      const btn = e.target.closest('.flag-filter-btn');
      if (!btn) return;
      currentStatus = btn.dataset.status || '';
      document.querySelectorAll('.flag-filter-btn').forEach(b => b.classList.toggle('is-active', b === btn));
      loadFlags();
    });

    loadFlags();
  </script>
</body>
</html>
