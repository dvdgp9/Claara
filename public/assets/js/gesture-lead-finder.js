(function() {
  'use strict';

  const csrf = window.LEAD_FINDER_CSRF || window.CSRF_TOKEN || '';
  const storageJobKey = 'lead_finder_job_id';
  const storageRunKey = 'lead_finder_run_id';

  const els = {
    form: document.getElementById('lead-search-form'),
    query: document.getElementById('lead-query'),
    queryError: document.getElementById('lead-query-error'),
    maxResults: document.getElementById('lead-max-results'),
    searchBtn: document.getElementById('lead-search-btn'),
    historyList: document.getElementById('history-list'),
    drawerContent: document.getElementById('lead-finder-history-drawer-content'),
    historyNewSearch: document.getElementById('history-new-search'),
    runEyebrow: document.getElementById('run-eyebrow'),
    runTitle: document.getElementById('run-title'),
    metricTotal: document.getElementById('metric-total'),
    metricValidated: document.getElementById('metric-validated'),
    metricRejected: document.getElementById('metric-rejected'),
    statusStrip: document.getElementById('status-strip'),
    statusTitle: document.getElementById('status-title'),
    statusDetail: document.getElementById('status-detail'),
    emptyState: document.getElementById('empty-state'),
    resultsSection: document.getElementById('results-section'),
    tableLoading: document.getElementById('table-loading'),
    resultsBody: document.getElementById('results-body'),
    exportBtn: document.getElementById('export-csv-btn'),
    newSearchBtn: document.getElementById('new-search-btn')
  };

  let currentRunId = null;
  let currentJobId = null;
  let statusPollTimer = null;
  let workerTriggerInFlight = false;
  let currentResults = [];

  init();

  function init() {
    bindEvents();
    loadHistory();

    const savedJobId = parseInt(sessionStorage.getItem(storageJobKey) || '', 10);
    const savedRunId = parseInt(sessionStorage.getItem(storageRunKey) || '', 10);
    if (savedJobId > 0 && savedRunId > 0) {
      currentRunId = savedRunId;
      startJobPolling(savedJobId, savedRunId);
    }
  }

  function bindEvents() {
    els.form?.addEventListener('submit', startSearch);
    els.exportBtn?.addEventListener('click', exportCurrentRun);
    els.newSearchBtn?.addEventListener('click', resetWorkspace);
    els.historyNewSearch?.addEventListener('click', resetWorkspace);

    document.querySelectorAll('[data-example]').forEach(btn => {
      btn.addEventListener('click', () => {
        els.query.value = btn.dataset.example || '';
        els.query.focus();
      });
    });

    els.resultsBody?.addEventListener('click', handleResultAction);
    els.resultsBody?.addEventListener('change', handleResultChange);
  }

  async function startSearch(event) {
    event.preventDefault();
    const query = els.query.value.trim();
    if (!query) {
      els.queryError.classList.remove('hidden');
      els.query.focus();
      return;
    }
    els.queryError.classList.add('hidden');

    setSearchDisabled(true);
    showStatus('Queued search...', 'Creating the background job.');
    showLoadingTable();

    try {
      const data = await jsonFetch('/api/gestures/lead-finder/search.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrf
        },
        body: JSON.stringify({
          query,
          max_results: parseInt(els.maxResults.value || '25', 10)
        })
      });

      currentRunId = parseInt(data.run_id, 10);
      currentJobId = parseInt(data.job_id, 10);
      startJobPolling(currentJobId, currentRunId);
      loadHistory();
    } catch (error) {
      hideLoadingTable();
      showStatus('Search failed', error.message || 'Could not start Lead Finder.', true);
      setSearchDisabled(false);
    }
  }

  function startJobPolling(jobId, runId) {
    currentJobId = jobId;
    currentRunId = runId;
    sessionStorage.setItem(storageJobKey, String(jobId));
    sessionStorage.setItem(storageRunKey, String(runId));
    clearJobTimer();
    triggerWorker();
    pollJobStatus(jobId, runId);
    statusPollTimer = setInterval(() => pollJobStatus(jobId, runId), 3000);
  }

  async function triggerWorker() {
    if (workerTriggerInFlight) return;
    workerTriggerInFlight = true;
    try {
      await fetch('/api/jobs/process.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrf }
      });
    } catch (error) {
      console.warn('Could not trigger worker:', error);
    } finally {
      workerTriggerInFlight = false;
    }
  }

  async function pollJobStatus(jobId, runId) {
    try {
      const data = await jsonFetch(`/api/jobs/status.php?id=${jobId}`, {
        headers: { 'X-CSRF-Token': csrf }
      });
      const job = data.job;
      if (!job) return;

      if (job.status === 'pending') {
        showStatus('Queued search...', 'Waiting for the background worker.');
        triggerWorker();
        return;
      }

      if (job.progress_text) {
        const detail = job.output_data?.found_count
          ? `${job.output_data.found_count} candidates collected.`
          : 'The job is running in the background.';
        showStatus(job.progress_text, detail);
      }

      if (job.status === 'completed') {
        clearJobTimer();
        sessionStorage.removeItem(storageJobKey);
        sessionStorage.removeItem(storageRunKey);
        currentJobId = null;
        await loadRun(job.output_data?.run_id || runId);
        setSearchDisabled(false);
        return;
      }

      if (job.status === 'failed') {
        clearJobTimer();
        sessionStorage.removeItem(storageJobKey);
        sessionStorage.removeItem(storageRunKey);
        throw new Error(job.error_message || 'Lead Finder job failed');
      }
    } catch (error) {
      clearJobTimer();
      hideLoadingTable();
      setSearchDisabled(false);
      showStatus('Search failed', error.message || 'Could not process Lead Finder.', true);
    }
  }

  async function loadRun(runId) {
    const data = await jsonFetch(`/api/gestures/lead-finder/get.php?id=${runId}`, {
      headers: { 'X-CSRF-Token': csrf }
    });
    currentRunId = parseInt(data.run.id, 10);
    currentResults = data.results || [];
    renderRun(data.run, currentResults);
    renderResults(currentResults);
    loadHistory();
  }

  async function loadHistory() {
    try {
      const data = await jsonFetch('/api/gestures/lead-finder/history.php?limit=30', {
        headers: { 'X-CSRF-Token': csrf }
      });
      renderHistory(data.items || data.history || []);
    } catch (error) {
      console.warn('Could not load Lead Finder history:', error);
    }
  }

  function renderHistory(items) {
    const emptyHtml = `
      <div class="p-4 text-center text-slate-400 text-sm">
        <i class="iconoir-search-window text-2xl mb-2 block opacity-50"></i>
        <p>No searches yet</p>
      </div>
    `;
    if (!items.length) {
      els.historyList.innerHTML = emptyHtml;
      if (els.drawerContent) els.drawerContent.innerHTML = emptyHtml;
      return;
    }

    const html = items.map(item => {
      const active = parseInt(item.id, 10) === currentRunId;
      const status = escapeHtml(item.status || 'pending');
      return `
        <div class="history-item w-full p-3 hover:bg-slate-50 border-b border-slate-100 transition-colors group flex items-start gap-2 ${active ? 'active' : ''}" data-id="${item.id}">
          <i class="iconoir-search-window text-emerald-600 mt-0.5"></i>
          <button type="button" class="flex-1 min-w-0 text-left history-item-main">
            <p class="text-sm font-medium text-slate-700 truncate group-hover:text-emerald-700">${escapeHtml(item.query || 'Untitled search')}</p>
            <span class="text-[10px] text-slate-400">${formatDate(item.created_at)} · ${status}</span>
          </button>
          <button type="button" class="history-item-delete opacity-0 group-hover:opacity-100 lg:opacity-0 transition-opacity text-slate-300 hover:text-red-500 p-1 rounded" title="Delete">
            <i class="iconoir-trash"></i>
          </button>
        </div>
      `;
    }).join('');

    els.historyList.innerHTML = html;
    if (els.drawerContent) {
      els.drawerContent.innerHTML = html;
      els.drawerContent.querySelectorAll('.opacity-0, .lg\\:opacity-0').forEach(el => {
        el.classList.remove('opacity-0', 'lg:opacity-0');
        el.classList.add('opacity-100');
      });
    }

    bindHistory(els.historyList);
    if (els.drawerContent) bindHistory(els.drawerContent);
  }

  function bindHistory(container) {
    container.querySelectorAll('.history-item-main').forEach(btn => {
      btn.addEventListener('click', async event => {
        const item = event.currentTarget.closest('.history-item');
        const runId = parseInt(item.dataset.id, 10);
        await loadRun(runId);
        if (typeof closeMobileDrawer === 'function') closeMobileDrawer('lead-finder-history-drawer');
      });
    });

    container.querySelectorAll('.history-item-delete').forEach(btn => {
      btn.addEventListener('click', async event => {
        event.stopPropagation();
        const item = event.currentTarget.closest('.history-item');
        await deleteRun(parseInt(item.dataset.id, 10));
      });
    });
  }

  async function deleteRun(runId) {
    if (!confirm('Delete this Lead Finder search?')) return;
    await jsonFetch('/api/gestures/lead-finder/delete.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf
      },
      body: JSON.stringify({ id: runId })
    });
    if (currentRunId === runId) resetWorkspace();
    loadHistory();
  }

  function renderRun(run, results) {
    els.emptyState.classList.add('hidden');
    els.resultsSection.classList.remove('hidden');
    els.runEyebrow.textContent = `${run.provider || 'mock'} · ${run.status || 'completed'}`;
    els.runTitle.textContent = run.query || 'Lead Finder search';
    els.metricTotal.textContent = String(results.length);
    els.metricValidated.textContent = String(results.filter(row => row.status === 'validated').length);
    els.metricRejected.textContent = String(results.filter(row => row.status === 'rejected').length);
    hideLoadingTable();
    hideStatus();
    document.querySelectorAll('.history-item').forEach(el => {
      el.classList.toggle('active', parseInt(el.dataset.id, 10) === currentRunId);
    });
  }

  function renderResults(results) {
    if (!results.length) {
      els.resultsBody.innerHTML = `
        <tr>
          <td colspan="8" class="text-center text-slate-400 py-10">No leads found for this search.</td>
        </tr>
      `;
      return;
    }

    els.resultsBody.innerHTML = results.map(row => `
      <tr data-id="${row.id}">
        <td><input class="lead-finder-cell-input" data-field="name" value="${attr(row.name)}"></td>
        <td><input class="lead-finder-cell-input" data-field="website" value="${attr(row.website)}"></td>
        <td><input class="lead-finder-cell-input" data-field="email" value="${attr(row.email)}"></td>
        <td><input class="lead-finder-cell-input" data-field="phone" value="${attr(row.phone)}"></td>
        <td><input class="lead-finder-cell-input" data-field="address" value="${attr(row.address)}"></td>
        <td><input class="lead-finder-cell-input" data-field="confidence" value="${attr(confidencePercent(row.confidence))}"></td>
        <td>
          <select class="lead-finder-cell-select" data-field="status">
            <option value="pending" ${row.status === 'pending' ? 'selected' : ''}>Pending</option>
            <option value="validated" ${row.status === 'validated' ? 'selected' : ''}>Validated</option>
            <option value="rejected" ${row.status === 'rejected' ? 'selected' : ''}>Rejected</option>
          </select>
        </td>
        <td>
          <div class="flex items-center gap-1">
            <button type="button" class="lead-finder-icon-btn" data-action="validate" title="Validate"><i class="iconoir-check"></i></button>
            <button type="button" class="lead-finder-icon-btn" data-action="reject" title="Reject"><i class="iconoir-xmark"></i></button>
            <button type="button" class="lead-finder-icon-btn" data-action="source" title="Open source"><i class="iconoir-open-new-window"></i></button>
          </div>
        </td>
      </tr>
    `).join('');
  }

  async function handleResultAction(event) {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    const rowEl = button.closest('tr');
    const id = parseInt(rowEl.dataset.id, 10);
    const action = button.dataset.action;
    if (action === 'source') {
      const result = currentResults.find(row => parseInt(row.id, 10) === id);
      const url = result?.source_url || result?.website;
      if (url) window.open(url, '_blank', 'noopener');
      return;
    }
    const status = action === 'validate' ? 'validated' : 'rejected';
    rowEl.querySelector('[data-field="status"]').value = status;
    await saveRow(rowEl);
  }

  async function handleResultChange(event) {
    const field = event.target.closest('[data-field]');
    if (!field) return;
    await saveRow(field.closest('tr'));
  }

  async function saveRow(rowEl) {
    const id = parseInt(rowEl.dataset.id, 10);
    const payload = { id };
    rowEl.querySelectorAll('[data-field]').forEach(input => {
      payload[input.dataset.field] = input.value.trim();
    });
    payload.confidence = parseConfidence(payload.confidence);

    await jsonFetch('/api/gestures/lead-finder/update-result.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf
      },
      body: JSON.stringify(payload)
    });

    const existing = currentResults.find(row => parseInt(row.id, 10) === id);
    if (existing) Object.assign(existing, payload);
    updateMetricsFromCurrent();
  }

  async function exportCurrentRun() {
    if (!currentRunId) return;
    const response = await fetch('/api/gestures/lead-finder/export.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf
      },
      body: JSON.stringify({ id: currentRunId, format: 'csv' })
    });
    if (!response.ok) {
      const data = await response.json().catch(() => ({}));
      alert(data.error?.message || 'Export failed');
      return;
    }
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `lead-finder-${currentRunId}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  function resetWorkspace() {
    currentRunId = null;
    currentJobId = null;
    currentResults = [];
    clearJobTimer();
    sessionStorage.removeItem(storageJobKey);
    sessionStorage.removeItem(storageRunKey);
    els.query.value = '';
    els.emptyState.classList.remove('hidden');
    els.resultsSection.classList.add('hidden');
    els.runEyebrow.textContent = 'Workspace';
    els.runTitle.textContent = 'Ready for a new search';
    els.metricTotal.textContent = '0';
    els.metricValidated.textContent = '0';
    els.metricRejected.textContent = '0';
    hideStatus();
    loadHistory();
  }

  function setSearchDisabled(disabled) {
    els.searchBtn.disabled = disabled;
    els.searchBtn.classList.toggle('opacity-60', disabled);
    els.searchBtn.classList.toggle('cursor-not-allowed', disabled);
  }

  function showStatus(title, detail, isError = false) {
    els.statusStrip.classList.remove('hidden');
    els.statusStrip.classList.toggle('bg-red-50', isError);
    els.statusStrip.classList.toggle('border-red-100', isError);
    els.statusTitle.textContent = title;
    els.statusDetail.textContent = detail || '';
  }

  function hideStatus() {
    els.statusStrip.classList.add('hidden');
  }

  function showLoadingTable() {
    els.emptyState.classList.add('hidden');
    els.resultsSection.classList.remove('hidden');
    els.tableLoading.classList.remove('hidden');
    els.resultsBody.innerHTML = '';
  }

  function hideLoadingTable() {
    els.tableLoading.classList.add('hidden');
  }

  function clearJobTimer() {
    if (statusPollTimer) {
      clearInterval(statusPollTimer);
      statusPollTimer = null;
    }
  }

  function updateMetricsFromCurrent() {
    els.metricTotal.textContent = String(currentResults.length);
    els.metricValidated.textContent = String(currentResults.filter(row => row.status === 'validated').length);
    els.metricRejected.textContent = String(currentResults.filter(row => row.status === 'rejected').length);
  }

  async function jsonFetch(url, options = {}) {
    const response = await fetch(url, options);
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.error) {
      throw new Error(data.error?.message || 'Request failed');
    }
    return data;
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
  }

  function attr(value) {
    return escapeHtml(String(value ?? '')).replace(/"/g, '&quot;');
  }

  function formatDate(value) {
    if (!value) return '';
    return new Date(value.replace(' ', 'T')).toLocaleDateString('en-US', {
      month: 'short',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  function confidencePercent(value) {
    if (value === null || value === undefined || value === '') return '';
    return Math.round(parseFloat(value) * 100);
  }

  function parseConfidence(value) {
    const number = parseFloat(String(value || '').replace('%', ''));
    if (Number.isNaN(number)) return null;
    return Math.max(0, Math.min(1, number > 1 ? number / 100 : number));
  }
})();
