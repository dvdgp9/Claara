(function () {
  const state = {
    voices: [],
    selectedSlug: null,
    isCreating: true,
    documents: []
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
    list.innerHTML = state.voices.map((voice) => `
      <button class="voice-list-item ${voice.slug === state.selectedSlug ? 'is-active' : ''}" type="button" data-slug="${escapeHtml(voice.slug)}">
        <span class="voice-list-icon">${escapeHtml((voice.name || voice.slug).slice(0, 1).toUpperCase())}</span>
        <span class="voice-list-copy">
          <strong>${escapeHtml(voice.name || voice.slug)}</strong>
          <small>${escapeHtml(voice.description || voice.role || 'RAG voice')}</small>
        </span>
        <span class="voice-list-status" data-status="${escapeHtml(voice.status || 'draft')}">${escapeHtml(voice.status || 'draft')}</span>
      </button>
    `).join('');

    list.querySelectorAll('[data-slug]').forEach((button) => {
      button.addEventListener('click', () => selectVoice(button.dataset.slug));
    });
  }

  function resetForm() {
    state.selectedSlug = null;
    state.isCreating = true;
    state.documents = [];
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
    renderDocuments();
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
    $('voice-editor-status').textContent = voice.status || 'draft';
    $('voice-editor-status').dataset.status = voice.status || 'draft';
    $('voice-editor-title').textContent = voice.name || voice.slug;
    $('voice-editor-subtitle').textContent = voice.role || 'Specialized RAG assistant';
    $('voice-publish-btn').classList.toggle('hidden', voice.status === 'published' || voice.status === 'archived');
    $('voice-archive-btn').classList.toggle('hidden', voice.status === 'archived');
    $('voice-form-note').textContent = `RAG collection: ${voice.rag_collection || `voice_${voice.slug}`}`;
    renderList();
    loadDocuments().catch((error) => showAlert(error.message, 'error'));
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

    list.innerHTML = state.documents.map((doc) => `
      <article class="voice-document-row">
        <div>
          <strong>${escapeHtml(doc.original_filename || doc.filename)}</strong>
          <small>${escapeHtml(doc.file_extension || '')} · ${formatBytes(Number(doc.file_size || 0))} · ${escapeHtml(doc.rag_status || 'pending')}</small>
          ${doc.rag_error_message ? `<p>${escapeHtml(doc.rag_error_message)}</p>` : ''}
        </div>
        <button class="voice-secondary-btn voice-document-process-btn" type="button" data-id="${escapeHtml(doc.id)}">
          <i class="iconoir-database-script"></i>
          <span>${doc.rag_status === 'processed' ? 'Reprocess' : 'Process'}</span>
        </button>
      </article>
    `).join('');

    list.querySelectorAll('.voice-document-process-btn').forEach((button) => {
      button.addEventListener('click', () => processDocument(button.dataset.id, button));
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
      trigger_guidance: $('voice-trigger').value.trim()
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
