(function () {
  const state = {
    voices: [],
    users: [],
    selectedSlug: null,
    isCreating: true,
    documents: [],
    folders: [],
    selectedFolderId: null,
    profiles: [],
    accessUsers: [],
    accessProfileOptions: []
  };

  const $ = (id) => document.getElementById(id);

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[char]));
  }

  async function api(path, options = {}) {
    const response = await fetch(path, {
      method: options.method || 'GET',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        ...(window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {})
      },
      body: options.body ? JSON.stringify(options.body) : undefined
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data?.error?.message || response.statusText);
    }
    return data;
  }

  function showAlert(message, tone = 'ok') {
    const alert = $('voice-alert');
    alert.textContent = message;
    alert.dataset.tone = tone;
    alert.classList.remove('hidden');
    window.setTimeout(() => alert.classList.add('hidden'), 4200);
  }

  function slugify(value) {
    return String(value || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 50);
  }

  function selectedVoice() {
    return state.voices.find((voice) => voice.slug === state.selectedSlug) || null;
  }

  function setBusy(button, busy, label) {
    if (!button) return;
    button.disabled = busy;
    if (!button.dataset.label) {
      button.dataset.label = button.innerHTML;
    }
    button.innerHTML = busy
      ? `<i class="iconoir-refresh animate-spin"></i><span>${escapeHtml(label || 'Working')}</span>`
      : button.dataset.label;
  }

  async function loadVoices(selectSlug = null) {
    $('voice-list-loading').classList.remove('hidden');
    $('voice-list').classList.add('hidden');
    $('voice-empty').classList.add('hidden');

    const data = await api('/api/admin/voices/list.php?include_archived=1');
    state.voices = data.voices || [];
    state.users = data.users || [];
    renderResponsibleOptions();
    renderList();

    const nextSlug = selectSlug || state.selectedSlug || state.voices[0]?.slug || null;
    if (nextSlug) {
      selectVoice(nextSlug);
    } else {
      resetForm();
    }
  }

  function renderList() {
    const list = $('voice-list');
    $('voice-list-loading').classList.add('hidden');
    $('voice-count').textContent = `${state.voices.length} voice${state.voices.length === 1 ? '' : 's'} configured`;

    if (!state.voices.length) {
      $('voice-empty').classList.remove('hidden');
      list.classList.add('hidden');
      list.innerHTML = '';
      return;
    }

    $('voice-empty').classList.add('hidden');
    list.classList.remove('hidden');
    list.innerHTML = state.voices.map((voice) => {
      const responsibleCount = (voice.responsible_users || []).length;
      return `
      <button class="voice-list-item ${voice.slug === state.selectedSlug ? 'is-active' : ''}" type="button" data-slug="${escapeHtml(voice.slug)}">
        <span class="voice-list-icon">${escapeHtml((voice.name || voice.slug).slice(0, 1).toUpperCase())}</span>
        <span class="voice-list-copy">
          <strong>${escapeHtml(voice.name || voice.slug)}</strong>
          <small>${escapeHtml(responsibleCount ? `${responsibleCount} responsible user${responsibleCount === 1 ? '' : 's'}` : (voice.description || voice.role || 'RAG voice'))}</small>
        </span>
        <span class="voice-list-status" data-status="${escapeHtml(voice.status || 'draft')}">${escapeHtml(voice.status || 'draft')}</span>
      </button>
    `;
    }).join('');

    list.querySelectorAll('[data-slug]').forEach((button) => {
      button.addEventListener('click', () => selectVoice(button.dataset.slug));
    });
  }

  function resetForm() {
    state.selectedSlug = null;
    state.isCreating = true;
    state.documents = [];
    state.folders = [];
    state.selectedFolderId = null;
    state.profiles = [];
    state.accessUsers = [];
    state.accessProfileOptions = [];
    $('voice-form').reset();
    $('voice-slug').disabled = false;
    $('voice-editor-status').textContent = 'Draft';
    $('voice-editor-status').dataset.status = 'draft';
    $('voice-editor-title').textContent = 'Create a voice';
    $('voice-editor-subtitle').textContent = 'Define identity, operating instructions, and when Claara should suggest this voice.';
    $('voice-publish-btn').classList.add('hidden');
    $('voice-archive-btn').classList.add('hidden');
    $('voice-form-note').textContent = 'Draft voices can be tested here before publishing.';
    renderList();
    renderFolderTree();
    renderBreadcrumb();
    renderDocuments();
    renderAccessMatrix();
    renderAccessUsers();
  }

  function renderResponsibleOptions() {
    const select = $('voice-responsibles');
    if (!select) return;
    select.innerHTML = state.users.map((user) => `
      <option value="${escapeHtml(user.id)}">${escapeHtml(`${user.first_name} ${user.last_name}${user.job_title ? ` · ${user.job_title}` : ''}`)}</option>
    `).join('');
  }

  function selectResponsibleUsers(users = []) {
    const ids = new Set(users.map((user) => Number(user.id)));
    Array.from($('voice-responsibles')?.options || []).forEach((option) => {
      option.selected = ids.has(Number(option.value));
    });
  }

  function selectedResponsibleUserIds() {
    return Array.from($('voice-responsibles')?.selectedOptions || [])
      .map((option) => Number(option.value))
      .filter(Boolean);
  }

  function selectVoice(slug) {
    const voice = state.voices.find((item) => item.slug === slug);
    if (!voice) {
      resetForm();
      return;
    }

    state.selectedSlug = slug;
    state.isCreating = false;
    state.documents = [];
    $('voice-name').value = voice.name || '';
    $('voice-slug').value = voice.slug || '';
    $('voice-slug').disabled = true;
    $('voice-role').value = voice.role || '';
    $('voice-color').value = voice.color || 'slate';
    $('voice-description').value = voice.description || '';
    $('voice-instructions').value = voice.instructions || '';
    $('voice-trigger').value = voice.trigger_guidance || '';
    selectResponsibleUsers(voice.responsible_users || []);
    $('voice-editor-status').textContent = voice.status || 'draft';
    $('voice-editor-status').dataset.status = voice.status || 'draft';
    $('voice-editor-title').textContent = voice.name || voice.slug;
    $('voice-editor-subtitle').textContent = voice.role || 'Specialized RAG assistant';
    $('voice-publish-btn').classList.toggle('hidden', voice.status === 'published' || voice.status === 'archived');
    $('voice-archive-btn').classList.toggle('hidden', voice.status === 'archived');
    $('voice-form-note').textContent = `RAG collection: ${voice.rag_collection || `voice_${voice.slug}`}`;
    renderList();
    loadKnowledge().catch((error) => showAlert(error.message, 'error'));
  }

  async function loadKnowledge() {
    await loadFolders();
    await loadDocuments();
    renderBreadcrumb();
    await loadAccess();
  }

  /* ---- Folders ---- */

  function rootFolder() {
    return state.folders.find((f) => f.is_root) || state.folders[0] || null;
  }

  function folderById(id) {
    return state.folders.find((f) => f.id === id) || null;
  }

  function currentFolderId() {
    if (state.selectedFolderId && folderById(state.selectedFolderId)) {
      return state.selectedFolderId;
    }
    const root = rootFolder();
    return root ? root.id : null;
  }

  function folderPath(id) {
    const chain = [];
    const seen = new Set();
    let node = folderById(id);
    while (node && !seen.has(node.id)) {
      seen.add(node.id);
      chain.unshift(node);
      node = node.parent_id ? folderById(node.parent_id) : null;
    }
    return chain;
  }

  async function loadFolders() {
    const voice = selectedVoice();
    if (!voice || state.isCreating) {
      state.folders = [];
      state.selectedFolderId = null;
      renderFolderTree();
      return;
    }
    const data = await api(`/api/admin/voices/folders/list.php?slug=${encodeURIComponent(voice.slug)}`);
    state.folders = (data.folders || []).map((f) => ({
      ...f,
      id: Number(f.id),
      parent_id: f.parent_id == null ? null : Number(f.parent_id),
      depth: Number(f.depth || 0),
      doc_count: Number(f.doc_count || 0)
    }));
    if (!folderById(state.selectedFolderId)) {
      const root = rootFolder();
      state.selectedFolderId = root ? root.id : null;
    }
    renderFolderTree();
    renderAccessMatrix();
  }

  function renderFolderTree() {
    const tree = $('voice-folder-tree');
    if (!tree) return;
    if (state.isCreating || !selectedVoice()) {
      tree.innerHTML = '<div class="voice-documents-empty">Select a voice.</div>';
      return;
    }
    if (!state.folders.length) {
      tree.innerHTML = '<div class="voice-documents-empty">No folders yet.</div>';
      return;
    }
    const selected = currentFolderId();
    tree.innerHTML = state.folders.map((f) => {
      const indent = 8 + f.depth * 14;
      const icon = f.is_root ? 'iconoir-home-simple' : 'iconoir-folder';
      const menu = f.is_root
        ? ''
        : `<button class="voice-folder-menu" type="button" data-menu="${f.id}" title="Rename or delete"><i class="iconoir-more-vert"></i></button>`;
      return `
        <div class="voice-folder-node ${f.id === selected ? 'is-selected' : ''}" data-folder="${f.id}" style="padding-left:${indent}px">
          <i class="${icon}"></i>
          <span class="voice-folder-name">${escapeHtml(f.name)}</span>
          <span class="voice-folder-count">${f.doc_count}</span>
          ${menu}
        </div>`;
    }).join('');
    tree.querySelectorAll('.voice-folder-node').forEach((node) => {
      node.addEventListener('click', (ev) => {
        if (ev.target.closest('.voice-folder-menu')) return;
        selectFolder(Number(node.dataset.folder));
      });
    });
    tree.querySelectorAll('.voice-folder-menu').forEach((btn) => {
      btn.addEventListener('click', (ev) => {
        ev.stopPropagation();
        folderMenu(Number(btn.dataset.menu));
      });
    });
  }

  function selectFolder(id) {
    state.selectedFolderId = id;
    renderFolderTree();
    renderBreadcrumb();
    renderDocuments();
  }

  function renderBreadcrumb() {
    const el = $('voice-folder-breadcrumb');
    if (!el) return;
    const id = currentFolderId();
    if (!id || state.isCreating) { el.innerHTML = ''; return; }
    const path = folderPath(id);
    el.innerHTML = path.map((f, i) => {
      const isLast = i === path.length - 1;
      return `<span class="crumb ${isLast ? 'current' : ''}" data-folder="${f.id}">${escapeHtml(f.name)}</span>`
        + (isLast ? '' : '<span class="sep">/</span>');
    }).join('');
    el.querySelectorAll('.crumb').forEach((c) => {
      c.addEventListener('click', () => selectFolder(Number(c.dataset.folder)));
    });
  }

  async function createFolder() {
    const voice = selectedVoice();
    if (!voice || state.isCreating) { showAlert('Save the voice before adding folders.'); return; }
    const name = (window.prompt('New folder name') || '').trim();
    if (!name) return;
    try {
      await api(`/api/admin/voices/folders/create.php?slug=${encodeURIComponent(voice.slug)}`, {
        method: 'POST',
        body: { name, parent_id: currentFolderId() }
      });
      await loadFolders();
      renderBreadcrumb();
      showAlert('Folder created');
    } catch (error) {
      showAlert(error.message, 'error');
    }
  }

  function folderMenu(id) {
    const folder = folderById(id);
    if (!folder) return;
    const action = (window.prompt(`Folder "${folder.name}" — type R to rename or D to delete.`, 'R') || '').trim().toUpperCase();
    if (action === 'R') renameFolder(id);
    else if (action === 'D') deleteFolder(id);
  }

  async function renameFolder(id) {
    const voice = selectedVoice();
    const folder = folderById(id);
    if (!voice || !folder) return;
    const name = (window.prompt('Rename folder', folder.name) || '').trim();
    if (!name || name === folder.name) return;
    try {
      await api(`/api/admin/voices/folders/rename.php?slug=${encodeURIComponent(voice.slug)}`, {
        method: 'POST',
        body: { id, name }
      });
      await loadFolders();
      renderBreadcrumb();
      showAlert('Folder renamed');
    } catch (error) {
      showAlert(error.message, 'error');
    }
  }

  async function deleteFolder(id) {
    const voice = selectedVoice();
    const folder = folderById(id);
    if (!voice || !folder) return;
    if (!window.confirm(`Delete folder "${folder.name}"? Documents inside move to its parent folder.`)) return;
    try {
      const res = await api(`/api/admin/voices/folders/delete.php?slug=${encodeURIComponent(voice.slug)}`, {
        method: 'POST',
        body: { id }
      });
      if (state.selectedFolderId === id) state.selectedFolderId = null;
      await loadFolders();
      await loadDocuments();
      renderBreadcrumb();
      showAlert(`Folder deleted. ${res.reassigned_documents || 0} document(s) moved.`);
    } catch (error) {
      showAlert(error.message, 'error');
    }
  }

  async function moveDocument(id, folderId) {
    const voice = selectedVoice();
    if (!voice) return;
    try {
      await api(`/api/admin/voices/documents/move.php?slug=${encodeURIComponent(voice.slug)}`, {
        method: 'POST',
        body: { id, folder_id: folderId }
      });
      await loadFolders();
      await loadDocuments();
      showAlert('Document moved');
    } catch (error) {
      showAlert(error.message, 'error');
    }
  }

  async function uploadFolder(files) {
    const voice = selectedVoice();
    if (!voice || state.isCreating || !files || !files.length) return;
    const allowed = ['pdf', 'txt', 'md'];
    const list = Array.from(files).filter((f) => allowed.includes((f.name.split('.').pop() || '').toLowerCase()));
    if (!list.length) { showAlert('No PDF, TXT, or MD files found in that folder.', 'error'); return; }

    const button = $('voice-folder-upload-btn');
    const hint = $('voice-folder-upload-hint');
    setBusy(button, true, 'Uploading');
    let done = 0;
    try {
      for (const file of list) {
        const form = new FormData();
        form.append('slug', voice.slug);
        form.append('file', file);
        form.append('relative_path', file.webkitRelativePath || file.name);
        const response = await fetch('/api/admin/voices/documents/upload.php', {
          method: 'POST',
          credentials: 'include',
          headers: window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {},
          body: form
        });
        if (!response.ok) {
          const data = await response.json().catch(() => ({}));
          throw new Error(`${file.name}: ${data?.error?.message || response.statusText}`);
        }
        done += 1;
        if (hint) hint.textContent = `${done}/${list.length}`;
      }
      showAlert(`${done} file(s) uploaded into the folder structure. Process them when ready.`);
      await loadFolders();
      await loadDocuments();
      renderBreadcrumb();
    } catch (error) {
      showAlert(error.message, 'error');
    } finally {
      setBusy(button, false);
      if (hint) hint.textContent = '';
    }
  }

  /* ---- Access: profiles, folder grants, user assignment ---- */

  async function loadAccess() {
    const voice = selectedVoice();
    if (!voice || state.isCreating) {
      state.profiles = [];
      state.accessUsers = [];
      state.accessProfileOptions = [];
      renderAccessMatrix();
      renderAccessUsers();
      return;
    }
    const [profilesData, accessData] = await Promise.all([
      api(`/api/admin/voices/profiles/list.php?slug=${encodeURIComponent(voice.slug)}`),
      api(`/api/admin/voices/access/list.php?slug=${encodeURIComponent(voice.slug)}`)
    ]);
    state.profiles = (profilesData.profiles || []).map((p) => ({
      ...p,
      id: Number(p.id),
      assigned_users: Number(p.assigned_users || 0),
      granted_folder_ids: (p.granted_folder_ids || []).map(Number)
    }));
    state.accessProfileOptions = (accessData.profiles || []).map((p) => ({ id: Number(p.id), name: p.name }));
    state.accessUsers = (accessData.users || []).map((u) => ({
      ...u,
      profile_id: u.profile_id == null ? null : Number(u.profile_id)
    }));
    renderAccessMatrix();
    renderAccessUsers();
  }

  function renderAccessMatrix() {
    const el = $('voice-access-matrix');
    if (!el) return;
    if (state.isCreating || !selectedVoice()) {
      el.innerHTML = '<div class="voice-documents-empty">Select a voice to manage access.</div>';
      return;
    }
    if (!state.profiles.length) {
      el.innerHTML = '<div class="voice-documents-empty">No profiles yet. Create one to control folder access.</div>';
      return;
    }
    const head = state.profiles.map((p) => `
      <th>
        <div class="voice-access-col">
          <span title="${escapeHtml(p.name)}">${escapeHtml(p.name)}</span>
          <button class="voice-folder-menu" type="button" data-profile="${p.id}" title="Rename or delete"><i class="iconoir-more-vert"></i></button>
        </div>
        <small>${p.assigned_users} user${p.assigned_users === 1 ? '' : 's'}</small>
      </th>`).join('');
    const rows = state.folders.map((f) => {
      const cells = state.profiles.map((p) => {
        const checked = p.granted_folder_ids.includes(f.id) ? 'checked' : '';
        return `<td><input type="checkbox" class="voice-grant-box" data-profile="${p.id}" data-folder="${f.id}" ${checked}></td>`;
      }).join('');
      const indent = 2 + f.depth * 12;
      const icon = f.is_root ? 'iconoir-home-simple' : 'iconoir-folder';
      return `<tr><th scope="row"><span style="padding-left:${indent}px"><i class="${icon}"></i>${escapeHtml(f.name)}</span></th>${cells}</tr>`;
    }).join('');
    el.innerHTML = `
      <table class="voice-access-matrix">
        <thead><tr><th class="corner">Folder</th>${head}</tr></thead>
        <tbody>${rows}</tbody>
      </table>
      <p class="voice-access-hint">Granting a folder also grants everything inside it.</p>`;
    el.querySelectorAll('.voice-grant-box').forEach((box) => {
      box.addEventListener('change', () => toggleGrant(Number(box.dataset.profile), Number(box.dataset.folder), box.checked));
    });
    el.querySelectorAll('.voice-folder-menu[data-profile]').forEach((btn) => {
      btn.addEventListener('click', () => profileMenu(Number(btn.dataset.profile)));
    });
  }

  function renderAccessUsers() {
    const el = $('voice-access-users');
    if (!el) return;
    if (state.isCreating || !selectedVoice()) {
      el.innerHTML = '<div class="voice-documents-empty">Select a voice to assign profiles.</div>';
      return;
    }
    if (!state.accessUsers.length) {
      el.innerHTML = '<div class="voice-documents-empty">No users found.</div>';
      return;
    }
    const options = state.accessProfileOptions || [];
    el.innerHTML = state.accessUsers.map((u) => {
      const identity = `<div><strong>${escapeHtml(u.name || u.email)}</strong><small>${escapeHtml(u.email)}</small></div>`;
      if (u.is_superadmin) {
        return `<div class="voice-access-user">${identity}<span class="voice-access-badge">Full access</span></div>`;
      }
      const opts = ['<option value="0">No access</option>']
        .concat(options.map((o) => `<option value="${o.id}" ${u.profile_id === o.id ? 'selected' : ''}>${escapeHtml(o.name)}</option>`))
        .join('');
      return `<div class="voice-access-user">${identity}<select class="voice-access-select" data-user="${u.id}">${opts}</select></div>`;
    }).join('');
    el.querySelectorAll('.voice-access-select').forEach((sel) => {
      sel.addEventListener('change', () => assignUser(Number(sel.dataset.user), Number(sel.value)));
    });
  }

  async function toggleGrant(profileId, folderId, granted) {
    const voice = selectedVoice();
    if (!voice) return;
    try {
      await api(`/api/admin/voices/profiles/grant.php?slug=${encodeURIComponent(voice.slug)}`, {
        method: 'POST',
        body: { profile_id: profileId, folder_id: folderId, granted }
      });
      await loadAccess();
    } catch (error) {
      showAlert(error.message, 'error');
      await loadAccess().catch(() => {});
    }
  }

  async function createProfile() {
    const voice = selectedVoice();
    if (!voice || state.isCreating) { showAlert('Save the voice before adding profiles.'); return; }
    const name = (window.prompt('New profile name (e.g. Standard, Manager, Board)') || '').trim();
    if (!name) return;
    try {
      await api(`/api/admin/voices/profiles/create.php?slug=${encodeURIComponent(voice.slug)}`, {
        method: 'POST',
        body: { name }
      });
      await loadAccess();
      showAlert('Profile created');
    } catch (error) {
      showAlert(error.message, 'error');
    }
  }

  function profileMenu(id) {
    const profile = state.profiles.find((p) => p.id === id);
    if (!profile) return;
    const action = (window.prompt(`Profile "${profile.name}" — type R to rename or D to delete.`, 'R') || '').trim().toUpperCase();
    if (action === 'R') renameProfile(id);
    else if (action === 'D') deleteProfile(id);
  }

  async function renameProfile(id) {
    const voice = selectedVoice();
    const profile = state.profiles.find((p) => p.id === id);
    if (!voice || !profile) return;
    const name = (window.prompt('Rename profile', profile.name) || '').trim();
    if (!name || name === profile.name) return;
    try {
      await api(`/api/admin/voices/profiles/update.php?slug=${encodeURIComponent(voice.slug)}`, {
        method: 'POST',
        body: { id, name }
      });
      await loadAccess();
      showAlert('Profile renamed');
    } catch (error) {
      showAlert(error.message, 'error');
    }
  }

  async function deleteProfile(id) {
    const voice = selectedVoice();
    const profile = state.profiles.find((p) => p.id === id);
    if (!voice || !profile) return;
    if (!window.confirm(`Delete profile "${profile.name}"? People with this profile lose access to the voice.`)) return;
    try {
      await api(`/api/admin/voices/profiles/delete.php?slug=${encodeURIComponent(voice.slug)}`, {
        method: 'POST',
        body: { id }
      });
      await loadAccess();
      showAlert('Profile deleted');
    } catch (error) {
      showAlert(error.message, 'error');
    }
  }

  async function assignUser(userId, profileId) {
    const voice = selectedVoice();
    if (!voice) return;
    try {
      await api(`/api/admin/voices/access/assign.php?slug=${encodeURIComponent(voice.slug)}`, {
        method: 'POST',
        body: { user_id: userId, profile_id: profileId }
      });
      await loadAccess();
      showAlert('Access updated');
    } catch (error) {
      showAlert(error.message, 'error');
      await loadAccess().catch(() => {});
    }
  }

  async function loadDocuments() {
    const voice = selectedVoice();
    if (!voice || state.isCreating) {
      state.documents = [];
      renderDocuments();
      return;
    }
    const data = await api(`/api/admin/voices/documents/list.php?slug=${encodeURIComponent(voice.slug)}`);
    state.documents = data.documents || [];
    renderDocuments();
  }

  function renderDocuments() {
    const list = $('voice-documents-list');
    const summary = $('voice-knowledge-summary');
    if (!list || !summary) return;

    if (state.isCreating || !selectedVoice()) {
      summary.textContent = 'Save the voice before adding knowledge.';
      list.innerHTML = '<div class="voice-documents-empty">Create or select a voice to manage its knowledge.</div>';
      return;
    }

    const processed = state.documents.filter((doc) => doc.rag_status === 'processed').length;
    summary.textContent = `${state.documents.length} document${state.documents.length === 1 ? '' : 's'}, ${processed} processed`;

    if (!state.documents.length) {
      list.innerHTML = '<div class="voice-documents-empty">No documents yet. Upload PDF, TXT, or MD files, then process them for RAG.</div>';
      return;
    }

    const folderId = currentFolderId();
    const root = rootFolder();
    const docFolderId = (doc) => (doc.folder_id != null ? Number(doc.folder_id) : (root ? root.id : null));
    const inFolder = state.documents.filter((doc) => docFolderId(doc) === folderId);

    if (!inFolder.length) {
      list.innerHTML = '<div class="voice-documents-empty">This folder is empty. Upload files here, or move documents into it.</div>';
      return;
    }

    const folderOptions = (selFid) => state.folders.map((f) =>
      `<option value="${f.id}" ${f.id === selFid ? 'selected' : ''}>${escapeHtml(`${'  '.repeat(f.depth)}${f.name}`)}</option>`
    ).join('');

    list.innerHTML = inFolder.map((doc) => `
      <article class="voice-document-row">
        <div>
          <strong>${escapeHtml(doc.original_filename || doc.filename)}</strong>
          <small>${escapeHtml(doc.file_extension || '')} · ${formatBytes(Number(doc.file_size || 0))} · ${escapeHtml(doc.rag_status || 'pending')}</small>
          ${doc.rag_error_message ? `<p>${escapeHtml(doc.rag_error_message)}</p>` : ''}
        </div>
        <div class="voice-document-actions">
          <select class="voice-document-move" data-id="${escapeHtml(doc.id)}" title="Move to folder">
            ${folderOptions(docFolderId(doc))}
          </select>
          <button class="voice-secondary-btn voice-document-process-btn" type="button" data-id="${escapeHtml(doc.id)}">
            <i class="iconoir-database-script"></i>
            <span>${doc.rag_status === 'processed' ? 'Reprocess' : 'Process'}</span>
          </button>
        </div>
      </article>
    `).join('');

    list.querySelectorAll('.voice-document-process-btn').forEach((button) => {
      button.addEventListener('click', () => processDocument(button.dataset.id, button));
    });
    list.querySelectorAll('.voice-document-move').forEach((sel) => {
      sel.addEventListener('change', () => moveDocument(sel.dataset.id, Number(sel.value)));
    });
  }

  function formatBytes(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
  }

  async function uploadDocument(event) {
    event.preventDefault();
    const voice = selectedVoice();
    const fileInput = $('voice-document-file');
    if (!voice || !fileInput.files.length) return;

    const button = $('voice-document-upload-btn');
    setBusy(button, true, 'Uploading');
    try {
      const files = Array.from(fileInput.files);
      for (const file of files) {
        const form = new FormData();
        form.append('slug', voice.slug);
        form.append('file', file);
        form.append('description', $('voice-document-description').value.trim());
        const targetFolder = currentFolderId();
        if (targetFolder) {
          form.append('folder_id', targetFolder);
        }

        const response = await fetch('/api/admin/voices/documents/upload.php', {
          method: 'POST',
          credentials: 'include',
          headers: window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {},
          body: form
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
          throw new Error(`${file.name}: ${data?.error?.message || response.statusText}`);
        }
      }

      fileInput.value = '';
      $('voice-document-description').value = '';
      showAlert(`${files.length} document${files.length === 1 ? '' : 's'} uploaded. Process before testing.`);
      await loadFolders();
      await loadDocuments();
    } catch (error) {
      showAlert(error.message, 'error');
    } finally {
      setBusy(button, false);
    }
  }

  async function processDocument(id, button) {
    const voice = selectedVoice();
    if (!voice || !id) return;
    setBusy(button, true, 'Processing');
    try {
      await api(`/api/admin/voices/documents/process.php?slug=${encodeURIComponent(voice.slug)}&id=${encodeURIComponent(id)}`, {
        method: 'POST',
        body: { slug: voice.slug, id }
      });
      showAlert('Document processed');
      await loadDocuments();
    } catch (error) {
      showAlert(error.message, 'error');
      await loadDocuments().catch(() => {});
    } finally {
      setBusy(button, false);
    }
  }

  async function processAllDocuments() {
    const voice = selectedVoice();
    if (!voice) return;

    const queue = state.documents.filter((doc) => doc.rag_status !== 'processed' && doc.rag_status !== 'processing');
    if (!queue.length) {
      showAlert('No pending documents to process.');
      return;
    }

    const button = $('voice-process-all-btn');
    setBusy(button, true, 'Processing');
    try {
      for (let index = 0; index < queue.length; index += 1) {
        const doc = queue[index];
        showAlert(`Processing ${index + 1} of ${queue.length}: ${doc.original_filename || doc.filename}`);
        await api(`/api/admin/voices/documents/process.php?slug=${encodeURIComponent(voice.slug)}&id=${encodeURIComponent(doc.id)}`, {
          method: 'POST',
          body: { slug: voice.slug, id: doc.id }
        });
      }
      showAlert(`${queue.length} document${queue.length === 1 ? '' : 's'} processed`);
      await loadDocuments();
    } catch (error) {
      showAlert(error.message, 'error');
      await loadDocuments().catch(() => {});
    } finally {
      setBusy(button, false);
    }
  }

  function formPayload() {
    return {
      slug: $('voice-slug').value.trim(),
      name: $('voice-name').value.trim(),
      role: $('voice-role').value.trim(),
      color: $('voice-color').value,
      description: $('voice-description').value.trim(),
      instructions: $('voice-instructions').value.trim(),
      trigger_guidance: $('voice-trigger').value.trim(),
      responsible_user_ids: selectedResponsibleUserIds()
    };
  }

  async function saveVoice(event) {
    event.preventDefault();
    const button = $('voice-save-btn');
    setBusy(button, true, 'Saving');
    try {
      const payload = formPayload();
      const endpoint = state.isCreating ? '/api/admin/voices/create.php' : '/api/admin/voices/update.php';
      const result = await api(endpoint, { method: 'POST', body: payload });
      showAlert(state.isCreating ? 'Voice created' : 'Voice saved');
      await loadVoices(result.voice?.slug || payload.slug);
    } catch (error) {
      showAlert(error.message, 'error');
    } finally {
      setBusy(button, false);
    }
  }

  async function publishVoice() {
    const voice = selectedVoice();
    if (!voice) return;
    const button = $('voice-publish-btn');
    setBusy(button, true, 'Publishing');
    try {
      const result = await api('/api/admin/voices/publish.php', { method: 'POST', body: { slug: voice.slug } });
      showAlert('Voice published');
      await loadVoices(result.voice?.slug || voice.slug);
    } catch (error) {
      showAlert(error.message, 'error');
    } finally {
      setBusy(button, false);
    }
  }

  async function archiveVoice() {
    const voice = selectedVoice();
    if (!voice) return;
    if (!window.confirm(`Archive ${voice.name || voice.slug}?`)) return;
    const button = $('voice-archive-btn');
    setBusy(button, true, 'Archiving');
    try {
      const result = await api('/api/admin/voices/archive.php', { method: 'POST', body: { slug: voice.slug } });
      showAlert('Voice archived');
      await loadVoices(result.voice?.slug || voice.slug);
    } catch (error) {
      showAlert(error.message, 'error');
    } finally {
      setBusy(button, false);
    }
  }

  function bindEvents() {
    $('voice-new-btn').addEventListener('click', resetForm);
    $('voice-refresh-btn').addEventListener('click', () => loadVoices().catch((error) => showAlert(error.message, 'error')));
    $('voice-form').addEventListener('submit', saveVoice);
    $('voice-document-form').addEventListener('submit', uploadDocument);
    $('voice-process-all-btn').addEventListener('click', processAllDocuments);
    $('voice-folder-new-btn').addEventListener('click', createFolder);
    $('voice-folder-upload-btn').addEventListener('click', () => $('voice-folder-file').click());
    $('voice-folder-file').addEventListener('change', (event) => {
      uploadFolder(event.target.files);
      event.target.value = '';
    });
    $('voice-profile-new-btn').addEventListener('click', createProfile);
    $('voice-publish-btn').addEventListener('click', publishVoice);
    $('voice-archive-btn').addEventListener('click', archiveVoice);
    $('voice-name').addEventListener('input', () => {
      if (state.isCreating && !$('voice-slug').dataset.touched) {
        $('voice-slug').value = slugify($('voice-name').value);
      }
    });
    $('voice-slug').addEventListener('input', () => {
      $('voice-slug').dataset.touched = '1';
      $('voice-slug').value = slugify($('voice-slug').value);
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    bindEvents();
    loadVoices().catch((error) => {
      $('voice-list-loading').classList.add('hidden');
      showAlert(error.message, 'error');
    });
  });
}());
