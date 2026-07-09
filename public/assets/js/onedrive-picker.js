/**
 * ClaaraOneDrivePicker — Claara's own OneDrive file picker.
 *
 * Unlike the Google flow, the Microsoft token never reaches the browser:
 * this modal browses folders through /api/connectors/onedrive/browse.php.
 *
 * Usage:
 *   await ClaaraOneDrivePicker.open({
 *     extensions: ['pdf', 'docx', ...],   // selectable file extensions
 *     title: 'Choose a file from OneDrive',
 *     onPicked: (items) => { ... }        // [{ id, name, mimeType, sizeBytes }]
 *   });
 */
window.ClaaraOneDrivePicker = (function () {
  let modalEl = null;
  let promptEl = null;
  let pendingOpenArgs = null;
  let currentArgs = null;
  let pathStack = [];      // [{ id: null, name: 'OneDrive' }, ...]
  let selectedItem = null;

  // ---------- connect prompt (no account linked yet) ----------

  function buildPrompt() {
    if (promptEl) return promptEl;
    promptEl = document.createElement('div');
    promptEl.className = 'hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[95] flex items-center justify-center p-4';
    promptEl.innerHTML = `
      <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center">
        <div class="w-16 h-16 rounded-2xl bg-[#B7C9F2]/15 flex items-center justify-center mx-auto mb-5">
          <i class="iconoir-cloud text-3xl text-[#2F3440]"></i>
        </div>
        <h3 class="text-xl font-bold text-slate-900 mb-2">Connect OneDrive</h3>
        <p class="text-sm text-slate-500 mb-6 leading-relaxed">Connect your account once and you will be able to pick OneDrive files without leaving Claara.</p>
        <a href="/connectors.php" target="_blank" rel="noopener"
           class="block w-full py-3 px-6 rounded-xl bg-slate-900 text-white font-semibold hover:bg-slate-800 transition-smooth mb-2">
          Connect OneDrive
        </a>
        <button type="button" data-od-prompt-close
                class="w-full py-2.5 px-6 rounded-xl text-slate-500 font-medium hover:bg-slate-100 transition-smooth">
          Not now
        </button>
        <p class="hidden mt-3 text-xs text-[#2F3440] font-medium" data-od-prompt-waiting>
          Waiting for the connection... come back to this tab when you are done.
        </p>
      </div>`;
    document.body.appendChild(promptEl);

    promptEl.addEventListener('click', (event) => {
      if (event.target === promptEl || event.target.closest('[data-od-prompt-close]')) {
        hidePrompt();
      }
      if (event.target.closest('a[href="/connectors.php"]')) {
        promptEl.querySelector('[data-od-prompt-waiting]').classList.remove('hidden');
      }
    });

    window.addEventListener('focus', async () => {
      if (promptEl.classList.contains('hidden') || !pendingOpenArgs) return;
      try {
        const probe = await fetchChildren(null);
        if (!probe.notConnected) {
          const args = pendingOpenArgs;
          hidePrompt();
          openModal(args, probe.items);
        }
      } catch (e) { /* keep the prompt; the user can retry */ }
    });

    return promptEl;
  }

  function hidePrompt() {
    if (!promptEl) return;
    promptEl.classList.add('hidden');
    promptEl.querySelector('[data-od-prompt-waiting]')?.classList.add('hidden');
    pendingOpenArgs = null;
  }

  // ---------- API ----------

  async function fetchChildren(itemId) {
    const url = '/api/connectors/onedrive/browse.php' + (itemId ? `?item_id=${encodeURIComponent(itemId)}` : '');
    const response = await fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } });
    const data = await response.json().catch(() => ({}));
    if (response.status === 409 && data.error?.code === 'not_connected') {
      return { notConnected: true };
    }
    if (!response.ok) {
      throw new Error(data.error?.message || 'Could not load your OneDrive files');
    }
    return { items: data.items || [] };
  }

  // ---------- modal ----------

  function buildModal() {
    if (modalEl) return modalEl;
    modalEl = document.createElement('div');
    modalEl.className = 'hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[95] flex items-center justify-center p-4';
    modalEl.innerHTML = `
      <div class="cfp-dialog">
        <div class="cfp-header">
          <span class="cfp-header-icon"><i class="iconoir-cloud"></i></span>
          <h3 data-cfp-title>Choose a file from OneDrive</h3>
          <button type="button" class="cfp-close" data-cfp-close title="Close"><i class="iconoir-xmark"></i></button>
        </div>
        <div class="cfp-breadcrumb" data-cfp-breadcrumb></div>
        <div class="cfp-body" data-cfp-body></div>
        <div class="cfp-footer">
          <span class="cfp-selection" data-cfp-selection></span>
          <div class="cfp-footer-actions">
            <button type="button" class="cfp-btn-ghost" data-cfp-close>Cancel</button>
            <button type="button" class="cfp-btn-primary" data-cfp-confirm disabled>
              <i class="iconoir-download"></i><span>Add file</span>
            </button>
          </div>
        </div>
      </div>`;
    document.body.appendChild(modalEl);

    modalEl.addEventListener('click', (event) => {
      if (event.target === modalEl || event.target.closest('[data-cfp-close]')) {
        closeModal();
        return;
      }

      const crumb = event.target.closest('[data-cfp-crumb]');
      if (crumb) {
        const index = Number(crumb.dataset.cfpCrumb);
        pathStack = pathStack.slice(0, index + 1);
        loadFolder(pathStack[pathStack.length - 1].id);
        return;
      }

      const row = event.target.closest('[data-cfp-item]');
      if (row) {
        const id = row.dataset.cfpItem;
        const item = (currentArgs._items || []).find((i) => i.id === id);
        if (!item) return;
        if (item.type === 'folder') {
          pathStack.push({ id: item.id, name: item.name });
          loadFolder(item.id);
        } else if (!row.classList.contains('cfp-item-disabled')) {
          selectedItem = item;
          renderList(currentArgs._items);
        }
        return;
      }

      if (event.target.closest('[data-cfp-confirm]') && selectedItem) {
        confirmSelection();
      }
    });

    modalEl.addEventListener('dblclick', (event) => {
      const row = event.target.closest('[data-cfp-item]');
      if (!row || row.classList.contains('cfp-item-disabled')) return;
      const item = (currentArgs._items || []).find((i) => i.id === row.dataset.cfpItem);
      if (item && item.type === 'file') {
        selectedItem = item;
        confirmSelection();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && modalEl && !modalEl.classList.contains('hidden')) {
        closeModal();
      }
    });

    return modalEl;
  }

  function openModal(args, rootItems) {
    currentArgs = args;
    selectedItem = null;
    pathStack = [{ id: null, name: 'OneDrive' }];
    buildModal();
    modalEl.querySelector('[data-cfp-title]').textContent = args.title || 'Choose a file from OneDrive';
    modalEl.classList.remove('hidden');
    if (rootItems) {
      currentArgs._items = rootItems;
      renderBreadcrumb();
      renderList(rootItems);
    } else {
      loadFolder(null);
    }
  }

  function closeModal() {
    if (modalEl) modalEl.classList.add('hidden');
    currentArgs = null;
    selectedItem = null;
  }

  async function loadFolder(itemId) {
    selectedItem = null;
    renderBreadcrumb();
    const body = modalEl.querySelector('[data-cfp-body]');
    body.innerHTML = '<div class="cfp-skeleton"></div><div class="cfp-skeleton"></div><div class="cfp-skeleton"></div><div class="cfp-skeleton"></div>';
    updateFooter();
    try {
      const result = await fetchChildren(itemId);
      if (result.notConnected) {
        closeModal();
        pendingOpenArgs = currentArgs;
        buildPrompt().classList.remove('hidden');
        return;
      }
      if (!currentArgs) return; // modal was closed meanwhile
      currentArgs._items = result.items;
      renderList(result.items);
    } catch (error) {
      body.innerHTML = `<div class="cfp-error"><i class="iconoir-warning-triangle"></i><span>${escapeHtml(error.message)}</span></div>`;
    }
  }

  function isSelectable(item) {
    if (item.type !== 'file') return false;
    const exts = currentArgs?.extensions;
    if (!exts || !exts.length) return true;
    return exts.includes(item.extension);
  }

  function fileIcon(item) {
    if (item.type === 'folder') return 'iconoir-folder cfp-icon-folder';
    const ext = item.extension || '';
    if (ext === 'pdf') return 'iconoir-page cfp-icon-pdf';
    if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'tif', 'tiff'].includes(ext)) return 'iconoir-media-image cfp-icon-image';
    if (['csv', 'xls', 'xlsx', 'xlsm', 'ods'].includes(ext)) return 'iconoir-table-rows cfp-icon-sheet';
    if (['doc', 'docx', 'rtf', 'odt', 'txt', 'md'].includes(ext)) return 'iconoir-page-edit cfp-icon-doc';
    if (['ppt', 'pptx', 'pps', 'ppsx', 'odp'].includes(ext)) return 'iconoir-presentation cfp-icon-slides';
    return 'iconoir-page cfp-icon-generic';
  }

  function formatSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function renderBreadcrumb() {
    const el = modalEl.querySelector('[data-cfp-breadcrumb]');
    el.innerHTML = pathStack.map((node, idx) => {
      const isLast = idx === pathStack.length - 1;
      const label = escapeHtml(node.name);
      return isLast
        ? `<span class="cfp-crumb-current">${label}</span>`
        : `<button type="button" class="cfp-crumb" data-cfp-crumb="${idx}">${label}</button><i class="iconoir-nav-arrow-right cfp-crumb-sep"></i>`;
    }).join('');
  }

  function renderList(items) {
    const body = modalEl.querySelector('[data-cfp-body]');
    if (!items.length) {
      body.innerHTML = '<div class="cfp-empty"><i class="iconoir-folder"></i><span>This folder is empty.</span></div>';
      updateFooter();
      return;
    }
    body.innerHTML = items.map((item) => {
      const selectable = item.type === 'folder' || isSelectable(item);
      const selected = selectedItem && selectedItem.id === item.id;
      const meta = item.type === 'folder'
        ? `${item.child_count ?? 0} item${(item.child_count ?? 0) === 1 ? '' : 's'}`
        : formatSize(item.size);
      return `<div class="cfp-item${selected ? ' cfp-item-selected' : ''}${selectable ? '' : ' cfp-item-disabled'}" data-cfp-item="${escapeHtml(item.id)}" role="button" tabindex="0">
        <i class="${fileIcon(item)}"></i>
        <span class="cfp-item-name">${escapeHtml(item.name)}</span>
        <span class="cfp-item-meta">${escapeHtml(meta)}</span>
        ${item.type === 'folder' ? '<i class="iconoir-nav-arrow-right cfp-item-arrow"></i>' : (selected ? '<i class="iconoir-check-circle cfp-item-check"></i>' : '')}
      </div>`;
    }).join('');
    updateFooter();
  }

  function updateFooter() {
    const confirmBtn = modalEl.querySelector('[data-cfp-confirm]');
    const selection = modalEl.querySelector('[data-cfp-selection]');
    confirmBtn.disabled = !selectedItem;
    selection.textContent = selectedItem ? selectedItem.name : '';
  }

  function confirmSelection() {
    const item = selectedItem;
    const args = currentArgs;
    closeModal();
    if (item && args?.onPicked) {
      args.onPicked([{
        id: item.id,
        name: item.name,
        mimeType: item.mime || '',
        sizeBytes: item.size || 0,
      }]);
    }
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  }

  // ---------- public API ----------

  async function open(args) {
    const probe = await fetchChildren(null);
    if (probe.notConnected) {
      pendingOpenArgs = args;
      buildPrompt().classList.remove('hidden');
      return false;
    }
    openModal(args, probe.items);
    return true;
  }

  return { open };
})();
