<?php
require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Repos/UserFeatureAccessRepo.php';

use App\Session;
use Repos\UserFeatureAccessRepo;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

// Verificar acceso a este gesto
$accessRepo = new UserFeatureAccessRepo();
if (!$accessRepo->hasGestureAccess((int)$user['id'], 'audio-transcriber')) {
    header('Location: /gestos/?error=no_access');
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
$activeTab = 'gestures';

// Configuración del header unificado
$headerBackUrl = '/gestos/';
$headerBackText = 'All gestures';
$headerTitle = 'Audio transcriber';
$headerIcon = 'iconoir-microphone';
$headerIconColor = 'from-purple-500 to-indigo-600';
$headerDrawerId = 'transcriber-history-drawer';
?><!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="bg-mesh text-slate-900 overflow-hidden">
  <style>
    .audio-drop-zone {
      border: 2px dashed #cbd5e1;
      transition: all 0.2s ease;
    }
    .audio-drop-zone.dragover {
      border-color: #8b5cf6;
      background: rgba(139, 92, 246, 0.05);
    }
    .audio-drop-zone:hover {
      border-color: #a78bfa;
    }
    .history-item.active {
      background-color: rgba(139, 92, 246, 0.05);
      border-left: 3px solid #8b5cf6;
    }
    .history-item.active p {
      color: #7c3aed;
      font-weight: 600;
    }
    @keyframes pulse-wave {
      0%, 100% { transform: scaleY(0.5); opacity: 0.5; }
      50% { transform: scaleY(1); opacity: 1; }
    }
    .wave-bar {
      animation: pulse-wave 1s ease-in-out infinite;
    }
    .wave-bar:nth-child(2) { animation-delay: 0.1s; }
    .wave-bar:nth-child(3) { animation-delay: 0.2s; }
    .wave-bar:nth-child(4) { animation-delay: 0.3s; }
    .wave-bar:nth-child(5) { animation-delay: 0.4s; }
  </style>
  <div class="min-h-screen flex h-screen">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>
    
    <!-- Sidebar de historial (solo desktop) -->
    <aside id="history-sidebar" class="hidden lg:flex w-72 glass-strong border-r border-slate-200/50 flex-col shrink-0">
      <div class="p-4 border-b border-slate-200/50">
        <div class="flex items-center justify-between">
          <h2 class="font-semibold text-slate-800 flex items-center gap-2">
            <i class="iconoir-clock text-purple-500"></i>
            History
          </h2>
        </div>
      </div>
      
      <div id="history-list" class="flex-1 overflow-auto">
        <div class="p-4 text-center text-slate-400 text-sm">
          <i class="iconoir-refresh animate-spin"></i>
          Loading...
        </div>
      </div>
    </aside>
    
    <!-- Mobile Drawer para historial -->
    <?php 
    $drawerId = 'transcriber-history-drawer';
    $drawerTitle = 'History';
    $drawerIcon = 'iconoir-clock';
    $drawerIconColor = 'text-purple-500';
    include __DIR__ . '/../includes/mobile-drawer.php'; 
    ?>
    
    <!-- Main content area -->
    <main class="flex-1 flex flex-col overflow-hidden min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <!-- Single column layout -->
      <div class="flex-1 overflow-auto pb-16 lg:pb-0">
        <div class="max-w-2xl mx-auto p-4 lg:p-6 space-y-4 lg:space-y-6">
          
          <!-- Intro -->
          <div class="text-center mb-6">
            <h1 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-600 to-indigo-600 mb-2">
              Audio transcriber
            </h1>
            <p class="text-slate-500 max-w-lg mx-auto">
              Convert audio files into text. Upload voice recordings, interviews, meetings, or voice notes and get an accurate transcription.
            </p>
          </div>

          <!-- Upload Section -->
          <section id="upload-section" class="glass-strong rounded-2xl p-6 border border-slate-200/50">
            <form id="transcribe-form" class="space-y-5">
              
              <!-- Drop zone -->
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-3">
                  <i class="iconoir-upload text-purple-500 mr-1"></i>
                  Audio file
                </label>
                
                <div id="drop-zone" class="audio-drop-zone rounded-xl p-8 text-center cursor-pointer">
                  <input type="file" id="audio-input" accept="audio/*,.mp3,.wav,.m4a,.webm,.ogg" class="hidden" />
                  
                  <div id="drop-placeholder" class="space-y-3">
                    <div class="w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center mx-auto">
                      <i class="iconoir-music-double-note text-3xl text-purple-500"></i>
                    </div>
                    <div>
                      <p class="text-slate-700 font-medium">Drag an audio file here</p>
                      <p class="text-sm text-slate-500">or click to select</p>
                    </div>
                    <p class="text-xs text-slate-400">
                      Formats: MP3, WAV, M4A, WebM, OGG • Max 50MB
                    </p>
                  </div>
                  
                  <!-- Preview del archivo seleccionado -->
                  <div id="file-preview" class="hidden">
                    <div class="flex items-center justify-center gap-4 mb-4">
                      <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center text-white shadow-lg">
                        <i class="iconoir-sound-high text-2xl"></i>
                      </div>
                      <div class="text-left">
                        <p id="file-name" class="font-semibold text-slate-800 truncate max-w-[200px]">file.mp3</p>
                        <p id="file-size" class="text-sm text-slate-500">0 MB</p>
                      </div>
                      <button type="button" id="remove-file" class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all">
                        <i class="iconoir-xmark text-xl"></i>
                      </button>
                    </div>
                    
                    <!-- Reproductor de audio -->
                    <audio id="audio-player" controls class="w-full max-w-md mx-auto rounded-lg"></audio>
                  </div>
                </div>
              </div>
              
              <!-- Transcribe button -->
              <button type="submit" id="transcribe-btn" disabled
                      class="w-full py-3.5 rounded-xl font-semibold text-white bg-gradient-to-r from-purple-500 to-indigo-600 
                             hover:from-purple-600 hover:to-indigo-700 transition-all shadow-lg shadow-purple-500/25
                             disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none
                             flex items-center justify-center gap-2">
                <i class="iconoir-microphone"></i>
                <span>Transcribe audio</span>
              </button>
              
            </form>
          </section>

          <!-- Loading State -->
          <section id="loading-section" class="hidden glass-strong rounded-2xl p-8 border border-slate-200/50">
            <div class="text-center space-y-4">
              <div class="flex items-center justify-center gap-1 h-12">
                <div class="wave-bar w-1 h-8 bg-purple-500 rounded-full"></div>
                <div class="wave-bar w-1 h-8 bg-purple-500 rounded-full"></div>
                <div class="wave-bar w-1 h-8 bg-purple-500 rounded-full"></div>
                <div class="wave-bar w-1 h-8 bg-purple-500 rounded-full"></div>
                <div class="wave-bar w-1 h-8 bg-purple-500 rounded-full"></div>
              </div>
              <p class="text-slate-600 font-medium">Transcribing audio...</p>
              <p class="text-sm text-slate-500">This may take a few seconds depending on duration</p>
            </div>
          </section>

          <!-- Result Section -->
          <section id="result-section" class="hidden space-y-4">
            
            <!-- Metadata -->
            <div class="glass rounded-xl p-4 border border-slate-200/50">
              <div class="flex flex-wrap gap-4 text-sm">
                <div class="flex items-center gap-2 text-slate-600">
                  <i class="iconoir-clock text-purple-500"></i>
                  <span id="result-duration">--</span>
                </div>
                <div class="flex items-center gap-2 text-slate-600">
                  <i class="iconoir-text text-purple-500"></i>
                  <span id="result-words">-- words</span>
                </div>
                <div class="flex items-center gap-2 text-slate-600">
                  <i class="iconoir-page text-purple-500"></i>
                  <span id="result-chars">-- characters</span>
                </div>
              </div>
            </div>
            
            <!-- Transcription -->
            <div class="glass-strong rounded-2xl border border-slate-200/50 overflow-hidden">
              <div class="p-4 border-b border-slate-200/50 flex items-center justify-between">
                <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                  <i class="iconoir-page-edit text-purple-500"></i>
                  Transcription
                </h3>
                <div class="flex items-center gap-2">
                  <button id="copy-btn" class="p-2 text-slate-500 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition-all" title="Copy">
                    <i class="iconoir-copy"></i>
                  </button>
                  <button id="download-txt-btn" class="p-2 text-slate-500 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition-all" title="Download TXT">
                    <i class="iconoir-download"></i>
                  </button>
                </div>
              </div>
              <div id="transcription-content" class="p-6 prose prose-slate prose-sm max-w-none min-h-[200px] max-h-[400px] overflow-auto">
                <!-- Transcription content -->
              </div>
            </div>
            
            <!-- New transcription button -->
            <button id="new-transcription-btn" 
                    class="w-full py-3 rounded-xl font-medium text-purple-600 bg-purple-50 hover:bg-purple-100 transition-all flex items-center justify-center gap-2">
              <i class="iconoir-plus"></i>
              New transcription
            </button>
            
          </section>

        </div>
      </div>
    </main>
  </div>
  
  <!-- Bottom Navigation (móvil) -->
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>

  <script>
    const csrf = '<?= htmlspecialchars($csrfToken) ?>';
    const gestureType = 'audio-transcriber';
    
    // DOM Elements
    const dropZone = document.getElementById('drop-zone');
    const audioInput = document.getElementById('audio-input');
    const dropPlaceholder = document.getElementById('drop-placeholder');
    const filePreview = document.getElementById('file-preview');
    const fileName = document.getElementById('file-name');
    const fileSize = document.getElementById('file-size');
    const removeFileBtn = document.getElementById('remove-file');
    const audioPlayer = document.getElementById('audio-player');
    const transcribeBtn = document.getElementById('transcribe-btn');
    const transcribeForm = document.getElementById('transcribe-form');
    
    const uploadSection = document.getElementById('upload-section');
    const loadingSection = document.getElementById('loading-section');
    const resultSection = document.getElementById('result-section');
    
    const resultDuration = document.getElementById('result-duration');
    const resultWords = document.getElementById('result-words');
    const resultChars = document.getElementById('result-chars');
    const transcriptionContent = document.getElementById('transcription-content');
    
    const copyBtn = document.getElementById('copy-btn');
    const downloadTxtBtn = document.getElementById('download-txt-btn');
    const newTranscriptionBtn = document.getElementById('new-transcription-btn');
    
    const historyList = document.getElementById('history-list');
    const drawerContent = document.getElementById('transcriber-history-drawer')?.querySelector('.drawer-content');
    const loadingTitle = loadingSection.querySelector('.text-slate-600');
    const loadingSubtitle = loadingSection.querySelector('.text-sm.text-slate-500');
    
    let currentFile = null;
    let currentTranscription = '';
    let currentExecutionId = null;
    let currentJobId = null;
    let statusPollTimer = null;
    let wakeWorkerTimer = null;
    let workerTriggerInFlight = false;
    
    // ===== FILE HANDLING =====
    
    dropZone.addEventListener('click', () => audioInput.click());
    
    dropZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      dropZone.classList.add('dragover');
    });
    
    dropZone.addEventListener('dragleave', () => {
      dropZone.classList.remove('dragover');
    });
    
    dropZone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropZone.classList.remove('dragover');
      const files = e.dataTransfer.files;
      if (files.length > 0) {
        handleFile(files[0]);
      }
    });
    
    audioInput.addEventListener('change', (e) => {
      if (e.target.files.length > 0) {
        handleFile(e.target.files[0]);
      }
    });
    
    removeFileBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      clearFile();
    });
    
    function handleFile(file) {
      // Validate type
      const validTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/wave', 'audio/x-wav', 
                          'audio/mp4', 'audio/m4a', 'audio/x-m4a', 'audio/webm', 'audio/ogg'];
      if (!validTypes.includes(file.type) && !file.name.match(/\.(mp3|wav|m4a|webm|ogg)$/i)) {
        alert('Unsupported format. Use: MP3, WAV, M4A, WebM, OGG');
        return;
      }
      
      // Validate file size
      if (file.size > 50 * 1024 * 1024) {
        alert('File is too large. Maximum 50MB.');
        return;
      }
      
      currentFile = file;
      fileName.textContent = file.name;
      fileSize.textContent = formatFileSize(file.size);
      
      // Create URL for audio player
      audioPlayer.src = URL.createObjectURL(file);
      
      dropPlaceholder.classList.add('hidden');
      filePreview.classList.remove('hidden');
      transcribeBtn.disabled = false;
    }
    
    function clearFile() {
      currentFile = null;
      audioInput.value = '';
      audioPlayer.src = '';
      dropPlaceholder.classList.remove('hidden');
      filePreview.classList.add('hidden');
      transcribeBtn.disabled = true;
    }
    
    function formatFileSize(bytes) {
      if (bytes < 1024) return bytes + ' B';
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
      return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
    
    // ===== TRANSCRIPTION =====
    
    transcribeForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!currentFile) return;
      
      // Show loading
      uploadSection.classList.add('hidden');
      resultSection.classList.add('hidden');
      loadingSection.classList.remove('hidden');
      
      try {
        const formData = new FormData();
        formData.append('audio_file', currentFile, currentFile.name);
        formData.append('async', '1');

        const response = await fetch('/api/gestures/transcribe.php', {
          method: 'POST',
          headers: {
            'X-CSRF-Token': csrf
          },
          body: formData
        });
        
        const data = await response.json();
        
        if (!data.success) {
          throw new Error(data.error?.message || 'Transcription error');
        }

        if (data.async && data.job_id) {
          startJobPolling(parseInt(data.job_id, 10));
        } else {
          throw new Error('Invalid async response: missing job_id');
        }
        
      } catch (err) {
        alert('Error: ' + err.message);
        loadingSection.classList.add('hidden');
        uploadSection.classList.remove('hidden');
      }
    });
    
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function setLoadingState(title, subtitle = '') {
      if (loadingTitle) loadingTitle.textContent = title;
      if (loadingSubtitle) loadingSubtitle.textContent = subtitle;
    }

    async function triggerWorker() {
      if (workerTriggerInFlight) return;
      workerTriggerInFlight = true;
      try {
        await fetch('/api/jobs/process.php', {
          method: 'POST',
          headers: { 'X-CSRF-Token': csrf }
        });
      } catch (err) {
        console.warn('Could not trigger worker:', err);
      } finally {
        workerTriggerInFlight = false;
      }
    }

    function clearJobTimers() {
      if (statusPollTimer) {
        clearInterval(statusPollTimer);
        statusPollTimer = null;
      }
      if (wakeWorkerTimer) {
        clearInterval(wakeWorkerTimer);
        wakeWorkerTimer = null;
      }
    }

    function startJobPolling(jobId) {
      currentJobId = jobId;
      sessionStorage.setItem('audio_transcriber_job_id', String(jobId));
      setLoadingState('Queued transcription job...', 'Processing starts in background.');

      clearJobTimers();
      triggerWorker();

      statusPollTimer = setInterval(() => {
        pollJobStatus(jobId);
      }, 4000);

      pollJobStatus(jobId);
    }

    async function pollJobStatus(jobId) {
      try {
        const res = await fetch(`/api/jobs/status.php?id=${jobId}`, {
          headers: { 'X-CSRF-Token': csrf }
        });
        const data = await res.json();
        if (!data.success || !data.job) return;

        const job = data.job;
        if (job.status === 'pending') {
          setLoadingState('Queued transcription job...', 'Waiting for the background worker.');
          triggerWorker();
          return;
        }

        if (job.progress_text) {
          setLoadingState(job.progress_text, 'You can keep this page open while processing.');
        }

        const partial = job.output_data?.partial_transcription;
        if (partial && loadingSubtitle) {
          loadingSubtitle.textContent = partial.length > 180
            ? `${partial.substring(0, 180)}...`
            : partial;
        }

        if (job.status === 'completed') {
          clearJobTimers();
          sessionStorage.removeItem('audio_transcriber_job_id');
          currentJobId = null;

          const out = job.output_data || {};
          currentTranscription = out.transcription || '';
          currentExecutionId = out.execution_id || null;

          resultDuration.textContent = out.metadata?.duration_estimate || 'N/A';
          resultWords.textContent = (out.metadata?.word_count || 0) + ' words';
          resultChars.textContent = (out.metadata?.char_count || 0) + ' characters';
          transcriptionContent.innerHTML = escapeHtml(currentTranscription).replace(/\n/g, '<br>');

          loadingSection.classList.add('hidden');
          resultSection.classList.remove('hidden');
          loadHistory();
          return;
        }

        if (job.status === 'failed') {
          clearJobTimers();
          sessionStorage.removeItem('audio_transcriber_job_id');
          currentJobId = null;
          throw new Error(job.error_message || 'Transcription job failed');
        }
      } catch (err) {
        clearJobTimers();
        sessionStorage.removeItem('audio_transcriber_job_id');
        currentJobId = null;
        alert('Error: ' + err.message);
        loadingSection.classList.add('hidden');
        uploadSection.classList.remove('hidden');
      }
    }
    
    // ===== ACTIONS =====
    
    copyBtn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(currentTranscription);
        copyBtn.innerHTML = '<i class="iconoir-check"></i>';
        setTimeout(() => {
          copyBtn.innerHTML = '<i class="iconoir-copy"></i>';
        }, 2000);
      } catch (err) {
        alert('Error copying');
      }
    });
    
    downloadTxtBtn.addEventListener('click', () => {
      const blob = new Blob([currentTranscription], { type: 'text/plain' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'transcription.txt';
      a.click();
      URL.revokeObjectURL(url);
    });
    
    newTranscriptionBtn.addEventListener('click', () => {
      clearFile();
      resultSection.classList.add('hidden');
      uploadSection.classList.remove('hidden');
      currentTranscription = '';
      currentExecutionId = null;
    });
    
    // ===== HISTORY =====
    
    async function loadHistory() {
      try {
        const response = await fetch(`/api/gestures/history.php?type=${gestureType}`, {
          headers: { 'X-CSRF-Token': csrf }
        });
        const data = await response.json();
        
        if (data.success) {
          const items = data.items || data.history || [];
          renderHistory(items);
        }
      } catch (err) {
        console.error('Error loading history:', err);
      }
    }

    function renderHistory(items) {
      if (items.length === 0) {
        const emptyHtml = `
          <div class="p-4 text-center text-slate-400 text-sm">
            <i class="iconoir-microphone text-2xl mb-2 block opacity-50"></i>
            <p>No transcriptions yet</p>
          </div>
        `;
        historyList.innerHTML = emptyHtml;
        if (drawerContent) drawerContent.innerHTML = emptyHtml;
        return;
      }
      
      const html = items.map(item => `
        <div class="history-item w-full p-3 hover:bg-slate-50 border-b border-slate-100 transition-colors group flex items-start gap-2 ${item.id == currentExecutionId ? 'active' : ''}" data-id="${item.id}">
          <i class="iconoir-microphone text-purple-500 mt-0.5"></i>
          <div class="flex-1 min-w-0 cursor-pointer history-item-main">
            <p class="text-sm font-medium text-slate-700 truncate group-hover:text-purple-600">${escapeHtml(item.title || 'Untitled')}</p>
            <span class="text-[10px] text-slate-400">${new Date(item.created_at).toLocaleDateString('en-US', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}</span>
          </div>
          <button class="history-item-delete opacity-0 group-hover:opacity-100 lg:opacity-0 transition-opacity text-slate-300 hover:text-red-500 p-1 rounded" title="Delete">
            <i class="iconoir-trash"></i>
          </button>
        </div>
      `).join('');
      
      historyList.innerHTML = html;
      if (drawerContent) {
        drawerContent.innerHTML = html;
        // Force action visibility on mobile (no hover)
        drawerContent.querySelectorAll('.opacity-0, .lg\\:opacity-0').forEach(el => {
          el.classList.remove('opacity-0', 'lg:opacity-0');
          el.classList.add('opacity-100');
        });
      }
      
      // Event listeners to load history transcriptions
      addHistoryListeners(historyList);
      if (drawerContent) addHistoryListeners(drawerContent);
    }
    
    function addHistoryListeners(container) {
      container.querySelectorAll('.history-item-main').forEach(el => {
        el.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const item = e.currentTarget.closest('.history-item');
          const id = item.dataset.id;
          loadFromHistory(id);
        });
      });
      
      container.querySelectorAll('.history-item-delete').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const item = e.currentTarget.closest('.history-item');
          const id = item.dataset.id;
          deleteFromHistory(id);
        });
      });
    }
    
    async function deleteFromHistory(id) {
      if (!confirm('Delete this transcription from history?')) return;
      
      try {
        const response = await fetch('/api/gestures/delete.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf
          },
          body: JSON.stringify({ id: parseInt(id) })
        });
        const data = await response.json();
        
        if (data.success) {
          // If it was the active one, clear view
          if (currentExecutionId == id) {
            currentTranscription = '';
            currentExecutionId = null;
            resultSection.classList.add('hidden');
            uploadSection.classList.remove('hidden');
          }
          loadHistory();
        } else {
          alert(data.error?.message || 'Error deleting');
        }
      } catch (err) {
        console.error('Error deleting:', err);
        alert('Connection error');
      }
    }
    
    async function loadFromHistory(id) {
      try {
        const response = await fetch(`/api/gestures/get.php?id=${id}`, {
          headers: { 'X-CSRF-Token': csrf }
        });
        const data = await response.json();
        
        // The server returns { execution: { ... } }
        const item = data.execution || data.item;
        
        if (item) {
          currentTranscription = item.output_content;
          currentExecutionId = item.id;
          
          // Load metadata if provided as JSON string
          let outputData = item.output_data || {};
          if (typeof outputData === 'string') {
            try {
              outputData = JSON.parse(outputData);
            } catch (e) {
              outputData = {};
            }
          }
          
          resultDuration.textContent = outputData.duration_estimate || 'N/A';
          resultWords.textContent = (outputData.word_count || 0) + ' words';
          resultChars.textContent = (outputData.char_count || 0) + ' characters';
          
        // Render transcription preserving line breaks
          transcriptionContent.innerHTML = escapeHtml(currentTranscription).replace(/\n/g, '<br>');
          
          // Switch views
          uploadSection.classList.add('hidden');
          loadingSection.classList.add('hidden');
          resultSection.classList.remove('hidden');

          // Ensure result section is visible
          setTimeout(() => {
            resultSection.scrollIntoView({ behavior: 'smooth' });
          }, 100);
          
          // Update active history state
          document.querySelectorAll('.history-item').forEach(el => {
            el.classList.toggle('active', el.dataset.id == id);
          });
          
          // Close mobile drawer if available
          const closeBtn = document.querySelector('[data-bs-dismiss="offcanvas"]');
          if (closeBtn && window.getComputedStyle(closeBtn).display !== 'none') {
            closeBtn.click();
          }
        } else {
          alert('Could not load transcription');
        }
      } catch (err) {
        alert('Error loading transcription');
      }
    }
    
    // Load history on startup
    loadHistory();

    // Resume active job after reload
    const savedJobId = parseInt(sessionStorage.getItem('audio_transcriber_job_id') || '', 10);
    if (savedJobId > 0) {
      uploadSection.classList.add('hidden');
      resultSection.classList.add('hidden');
      loadingSection.classList.remove('hidden');
      setLoadingState('Resuming transcription job...', 'Recovering background progress.');
      startJobPolling(savedJobId);
    }
  </script>
</body>
</html>
