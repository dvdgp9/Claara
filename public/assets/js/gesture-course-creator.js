/**
 * Gesto: Creador de Cursos
 * Flujo de 3 fases:
 * - Fase 1: Subir PDF/texto → Generar índice editable
 * - Fase 2: Editar índice (opcional) → Desarrollar módulos
 * - Fase 3 (opcional): Generar materiales complementarios (flashcards, tests, examen, podcast)
 */

(function() {
  'use strict';

  const GESTURE_TYPE = 'course-creator';

  // === DOM Elements ===
  const form = document.getElementById('course-form');
  const tabBtns = document.querySelectorAll('.tab-btn');
  const tabContents = document.querySelectorAll('.tab-content');
  
  const sourcePdf = document.getElementById('source-pdf');
  const sourceText = document.getElementById('source-text');
  const pdfFilename = document.getElementById('pdf-filename');
  
  const generateBtn = document.getElementById('generate-btn');
  const generateBtnText = document.getElementById('generate-btn-text');
  const progressPanel = document.getElementById('progress-panel');
  const progressText = document.getElementById('progress-text');
  const progressDetail = document.getElementById('progress-detail');
  const errorPanel = document.getElementById('error-panel');
  const errorMessage = document.getElementById('error-message');
  
  const inputSection = document.getElementById('input-section');
  const outlineSection = document.getElementById('outline-section');
  const resultSection = document.getElementById('result-section');
  const resultTitle = document.getElementById('result-title');
  const resultSource = document.getElementById('result-source');
  const modulesContainer = document.getElementById('modules-container');
  
  const historyList = document.getElementById('history-list');

  // === State ===
  let currentTab = 'pdf';
  let pdfBase64 = null;
  let currentOutline = null;
  let currentExecutionId = null;
  let sourceContent = null;
  let currentModules = null; // Módulos desarrollados para paso 3
  let currentCourseTitle = null;

  // === Tab switching ===
  if (tabBtns) {
    tabBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        currentTab = tab;
        
        tabBtns.forEach(b => {
          b.classList.remove('active', 'bg-emerald-100', 'text-emerald-700');
          b.classList.add('bg-slate-100', 'text-slate-600');
        });
        btn.classList.add('active', 'bg-emerald-100', 'text-emerald-700');
        btn.classList.remove('bg-slate-100', 'text-slate-600');
        
        tabContents.forEach(c => c.classList.add('hidden'));
        document.getElementById(`tab-${tab}`)?.classList.remove('hidden');
      });
    });
  }

  // === PDF handling ===
  if (sourcePdf) {
    sourcePdf.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;
      
      if (file.type !== 'application/pdf') {
        alert('Por favor, selecciona un archivo PDF');
        sourcePdf.value = '';
        return;
      }
      
      if (file.size > 20 * 1024 * 1024) {
        alert('El PDF es demasiado grande (máximo 20MB)');
        sourcePdf.value = '';
        return;
      }
      
      const reader = new FileReader();
      reader.onload = (ev) => {
        const base64 = ev.target.result.split(',')[1];
        pdfBase64 = base64;
        if (pdfFilename) {
          pdfFilename.querySelector('span').textContent = file.name;
          pdfFilename.classList.remove('hidden');
        }
      };
      reader.readAsDataURL(file);
    });
  }

  // === Form submission (Fase 1: Generar índice) ===
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      await generateOutline();
    });
  }

  // =========================================================================
  // FASE 1: GENERAR ÍNDICE
  // =========================================================================
  async function generateOutline() {
    const duration = document.querySelector('input[name="duration"]:checked')?.value || '8h';
    const level = document.querySelector('input[name="level"]:checked')?.value || 'intermedio';
    const courseFormat = document.querySelector('input[name="course_format"]:checked')?.value || 'online';
    
    let inputData = { 
      source_type: currentTab,
      duration,
      level,
      course_format: courseFormat
    };
    
    switch (currentTab) {
      case 'text':
        const text = sourceText?.value?.trim();
        if (!text) {
          showError('Por favor, introduce el texto del material');
          return;
        }
        if (text.split(/\s+/).length < 50) {
          showError('El texto es demasiado corto (mínimo 50 palabras)');
          return;
        }
        inputData.text = text;
        break;
        
      case 'pdf':
      default:
        if (!pdfBase64) {
          showError('Por favor, selecciona un archivo PDF');
          return;
        }
        inputData.pdf_base64 = pdfBase64;
        break;
    }

    showProgress('Analizando contenido...', 'Generando índice pedagógico del curso');
    hideError();

    try {
      const response = await fetch('/api/gestures/course-creator.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(inputData)
      });

      const data = await response.json();

      if (!data.success) {
        throw new Error(data.error?.message || data.message || 'Error generando índice');
      }

      currentOutline = data.outline;
      currentExecutionId = data.execution_id;
      
      showOutlineEditor(data);
      loadHistory();

    } catch (err) {
      showError(err.message);
    } finally {
      hideProgress();
    }
  }

  // =========================================================================
  // EDITOR DE ÍNDICE
  // =========================================================================
  function showOutlineEditor(data) {
    if (inputSection) inputSection.classList.add('hidden');
    if (outlineSection) outlineSection.classList.remove('hidden');
    if (resultSection) resultSection.classList.add('hidden');
    
    const outline = data.outline;
    if (!outline) {
      showError('No se pudo generar el índice. Intenta de nuevo.');
      return;
    }

    // Renderizar editor
    const editorHtml = `
      <div class="glass-strong rounded-2xl border border-slate-200/50 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-xl font-bold text-slate-800">${escapeHtml(outline.course_title || 'Índice del Curso')}</h2>
            <p class="text-sm text-slate-500">${escapeHtml(outline.course_description || '')}</p>
          </div>
          <div class="flex items-center gap-2">
            <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded-full">${outline.total_hours || 8}h</span>
            <span class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded-full">${outline.level || 'intermedio'}</span>
          </div>
        </div>
        
        <div class="mb-4">
          <h3 class="font-semibold text-slate-700 mb-2">Objetivos generales</h3>
          <ul class="text-sm text-slate-600 space-y-1">
            ${(outline.objectives || []).map(obj => `<li class="flex items-start gap-2"><i class="iconoir-check-circle text-emerald-500 mt-0.5"></i> ${escapeHtml(obj)}</li>`).join('')}
          </ul>
        </div>
      </div>

      <div class="space-y-4 mb-6" id="modules-editor">
        ${(outline.modules || []).map((module, i) => renderModuleEditor(module, i)).join('')}
      </div>

      <div class="flex items-center justify-between">
        <button type="button" id="back-to-input-btn" class="px-4 py-2 text-slate-600 hover:text-slate-800 flex items-center gap-2">
          <i class="iconoir-arrow-left"></i> Volver
        </button>
        <button type="button" id="develop-modules-btn" class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-semibold rounded-xl hover:opacity-90 transition-opacity flex items-center gap-2">
          <i class="iconoir-play"></i>
          <span>Desarrollar ${outline.modules?.length || 0} módulos</span>
        </button>
      </div>
    `;

    const outlineEditor = document.getElementById('outline-editor');
    if (outlineEditor) {
      outlineEditor.innerHTML = editorHtml;
      
      // Event listeners
      document.getElementById('back-to-input-btn')?.addEventListener('click', () => {
        if (outlineSection) outlineSection.classList.add('hidden');
        if (inputSection) inputSection.classList.remove('hidden');
      });
      
      document.getElementById('develop-modules-btn')?.addEventListener('click', developModules);
    }
  }

  function renderModuleEditor(module, index) {
    const lessons = module.lessons || [];
    return `
      <div class="glass rounded-xl border border-slate-200/50 overflow-hidden module-card" data-module-id="${module.id || index + 1}">
        <div class="p-4 bg-gradient-to-r from-emerald-50 to-teal-50 border-b border-slate-200/50">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-bold">
              ${module.id || index + 1}
            </div>
            <div class="flex-1">
              <input type="text" class="module-title-input w-full font-semibold text-slate-800 bg-white/50 border border-slate-200 rounded px-2 py-1 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30" value="${escapeHtml(module.title || '')}">
              <p class="text-xs text-slate-500">${module.duration_hours || 2}h · ${lessons.length} lecciones</p>
            </div>
            <button type="button" class="toggle-module-btn p-2 hover:bg-white/50 rounded-lg transition-colors">
              <i class="iconoir-nav-arrow-down text-slate-600"></i>
            </button>
          </div>
        </div>
        <div class="module-content p-4 space-y-3">
          <div class="text-xs text-slate-500 mb-2">Objetivos: ${(module.objectives || []).join(', ')}</div>
          ${lessons.map((lesson, li) => `
            <div class="flex items-start gap-3 p-3 bg-slate-50 rounded-lg">
              <span class="text-xs font-medium text-slate-400">${lesson.id || (index + 1) + '.' + (li + 1)}</span>
              <div class="flex-1">
                <input type="text" class="lesson-title-input w-full text-sm text-slate-700 bg-white border border-slate-200 rounded px-2 py-1 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30 font-medium" value="${escapeHtml(lesson.title || '')}">
                <p class="text-xs text-slate-400 mt-1">${(lesson.topics || []).join(' · ')}</p>
              </div>
              <span class="text-xs text-slate-400">${lesson.duration_minutes || 30}min</span>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }

  // =========================================================================
  // FASE 2: DESARROLLAR MÓDULOS
  // =========================================================================
  async function developModules() {
    if (!currentOutline || !currentExecutionId) {
      alert('No hay índice para desarrollar');
      return;
    }

    // Recoger cambios del editor
    const updatedOutline = collectOutlineChanges();
    const totalModules = updatedOutline.modules?.length || 0;
    
    // Mostrar progreso VISIBLE en la sección de outline
    const developBtn = document.getElementById('develop-modules-btn');
    const outlineEditor = document.getElementById('outline-editor');
    
    if (developBtn) {
      developBtn.disabled = true;
      developBtn.innerHTML = `
        <i class="iconoir-refresh animate-spin"></i>
        <span>Generando módulo 1 de ${totalModules}...</span>
      `;
      developBtn.classList.add('opacity-75', 'cursor-wait');
    }
    
    // Añadir panel de progreso visible
    const progressHtml = `
      <div id="develop-progress" class="mt-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center">
            <i class="iconoir-refresh animate-spin text-emerald-600 text-xl"></i>
          </div>
          <div class="flex-1">
            <p class="font-semibold text-emerald-800">Desarrollando contenido del curso...</p>
            <p class="text-sm text-emerald-600">Generando módulo 1 de ${totalModules}. Esto puede tardar varios minutos.</p>
          </div>
        </div>
        <div class="mt-3 bg-emerald-200 rounded-full h-2 overflow-hidden">
          <div id="develop-progress-bar" class="bg-emerald-600 h-2 transition-all duration-500" style="width: 5%"></div>
        </div>
      </div>
    `;
    
    // Insertar antes de los botones
    const buttonsContainer = outlineEditor?.querySelector('.flex.items-center.justify-between');
    if (buttonsContainer) {
      buttonsContainer.insertAdjacentHTML('beforebegin', progressHtml);
    }

    try {
      const response = await fetch('/api/gestures/course-develop.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          execution_id: currentExecutionId,
          outline: updatedOutline
        })
      });

      const data = await response.json();
      
      console.log('Respuesta de develop:', data); // Debug
      
      // Quitar panel de progreso
      document.getElementById('develop-progress')?.remove();

      if (!data.success) {
        throw new Error(data.error?.message || data.message || 'Error desarrollando módulos');
      }

      if (!data.modules || data.modules.length === 0) {
        throw new Error('No se generaron módulos. Revisa el contenido fuente.');
      }

      console.log('Módulos generados:', data.modules.length); // Debug
      
      // Mostrar módulos desarrollados
      showDevelopedModules(data);
      loadHistory();

    } catch (err) {
      // Quitar panel de progreso
      document.getElementById('develop-progress')?.remove();
      
      // Restaurar botón
      if (developBtn) {
        developBtn.disabled = false;
        developBtn.innerHTML = `
          <i class="iconoir-play"></i>
          <span>Desarrollar ${totalModules} módulos</span>
        `;
        developBtn.classList.remove('opacity-75', 'cursor-wait');
      }
      
      alert('Error: ' + err.message);
    }
  }

  function collectOutlineChanges() {
    // Recoger títulos editados de módulos y lecciones
    const updatedOutline = JSON.parse(JSON.stringify(currentOutline));
    
    document.querySelectorAll('.module-card').forEach((card, mi) => {
      const titleInput = card.querySelector('.module-title-input');
      if (titleInput && updatedOutline.modules[mi]) {
        updatedOutline.modules[mi].title = titleInput.value;
      }
      
      card.querySelectorAll('.lesson-title-input').forEach((lessonInput, li) => {
        if (updatedOutline.modules[mi]?.lessons?.[li]) {
          updatedOutline.modules[mi].lessons[li].title = lessonInput.value;
        }
      });
    });
    
    return updatedOutline;
  }

  // =========================================================================
  // MOSTRAR MÓDULOS DESARROLLADOS
  // =========================================================================
  async function showDevelopedModules(data) {
    if (inputSection) inputSection.classList.add('hidden');
    if (outlineSection) outlineSection.classList.add('hidden');
    if (resultSection) resultSection.classList.remove('hidden');
    
    // Guardar para paso 3
    currentModules = data.modules || [];
    currentCourseTitle = data.course_title || 'Curso desarrollado';
    currentExecutionId = data.execution_id || currentExecutionId;
    
    if (resultTitle) resultTitle.textContent = currentCourseTitle;
    if (resultSource) resultSource.textContent = `${data.total_developed} módulo${data.total_developed !== 1 ? 's' : ''} generado${data.total_developed !== 1 ? 's' : ''}`;
    
    const modules = data.modules || [];
    
    if (modulesContainer) {
      // Botón exportar curso completo
      const exportAllHtml = `
        <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-xl flex items-center justify-between">
          <div>
            <p class="font-semibold text-blue-800">Exportar curso completo</p>
            <p class="text-xs text-blue-600">Descarga todos los módulos en un solo documento Word</p>
          </div>
          <button id="export-all-course-btn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors flex items-center gap-2">
            <i class="iconoir-page"></i>
            <span>Exportar a Word</span>
          </button>
        </div>
      `;
      
      modulesContainer.innerHTML = exportAllHtml + modules.map((module, i) => `
        <div class="glass-strong rounded-2xl border border-slate-200/50 overflow-hidden mb-4">
          <div class="p-4 border-b border-slate-200/50 bg-gradient-to-r from-emerald-50 to-teal-50">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-bold">
                  ${module.module_id || i + 1}
                </div>
                <div>
                  <h3 class="font-semibold text-slate-800">${escapeHtml(module.title)}</h3>
                  <p class="text-xs text-slate-500">${module.word_count || 0} palabras</p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <button class="copy-module-btn px-3 py-1.5 text-sm bg-white hover:bg-slate-50 border border-slate-200 rounded-lg transition-colors flex items-center gap-1.5" data-index="${i}">
                  <i class="iconoir-copy"></i> Copiar
                </button>
                <button class="export-module-word-btn px-3 py-1.5 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center gap-1.5" data-index="${i}">
                  <i class="iconoir-page"></i> Word
                </button>
              </div>
            </div>
          </div>
          
          <div class="p-4">
            <div class="preview-toggle mb-4">
              <button class="active" data-view="preview">Vista previa</button>
              <button data-view="raw">Markdown</button>
            </div>
            <div class="preview-view content-preview max-h-96 overflow-auto">${module.html || renderMarkdownPreview(module.content)}</div>
            <div class="raw-view hidden">
              <div class="raw-preview max-h-96 overflow-auto"><pre>${escapeHtml(module.content)}</pre></div>
            </div>
          </div>
        </div>
      `).join('');
      
      // Event listeners para botones de módulos
      modulesContainer.querySelectorAll('.copy-module-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
          const idx = parseInt(btn.dataset.index);
          const module = modules[idx];
          try {
            await navigator.clipboard.writeText(module.content);
            btn.innerHTML = '<i class="iconoir-check"></i> Copiado';
            setTimeout(() => {
              btn.innerHTML = '<i class="iconoir-copy"></i> Copiar';
            }, 2000);
          } catch (err) {
            console.error('Error copying:', err);
          }
        });
      });
      
      modulesContainer.querySelectorAll('.export-module-word-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
          const idx = parseInt(btn.dataset.index);
          const module = modules[idx];
          btn.disabled = true;
          btn.innerHTML = '<i class="iconoir-refresh animate-spin"></i> Exportando...';
          await exportToWord('module', module.content, module.title);
          btn.disabled = false;
          btn.innerHTML = '<i class="iconoir-page"></i> Word';
        });
      });
      
      // Preview/Raw toggle
      modulesContainer.querySelectorAll('.preview-toggle button').forEach(btn => {
        btn.addEventListener('click', () => {
          const panel = btn.closest('.glass-strong');
          const view = btn.dataset.view;
          
          btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          
          const previewView = panel.querySelector('.preview-view');
          const rawView = panel.querySelector('.raw-view');
          
          if (view === 'preview') {
            previewView?.classList.remove('hidden');
            rawView?.classList.add('hidden');
          } else {
            previewView?.classList.add('hidden');
            rawView?.classList.remove('hidden');
          }
        });
      });
      
      // Botón exportar todo el curso
      document.getElementById('export-all-course-btn')?.addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="iconoir-refresh animate-spin"></i> Exportando...';
        
        // Concatenar todo el contenido de los módulos
        const allContent = modules.map(m => m.content).join('\n\n---\n\n');
        await exportToWord('course', allContent, currentCourseTitle);
        
        btn.disabled = false;
        btn.innerHTML = '<i class="iconoir-page"></i> Exportar a Word';
      });
      
      // Añadir panel de materiales complementarios (Paso 3)
      renderMaterialsPanel(modules);
    }
    
    // Buscar materiales ya generados para este curso
    if (currentExecutionId) {
      await loadRelatedMaterials(currentExecutionId);
    }

    // Botón nuevo curso
    const newCourseBtn = document.getElementById('new-course-btn');
    if (newCourseBtn) {
      newCourseBtn.addEventListener('click', resetUI);
    }
  }
  
  // =========================================================================
  // PASO 3: MATERIALES COMPLEMENTARIOS
  // =========================================================================
  let selectedMaterialType = null;
  
  function renderMaterialsPanel(modules) {
    // Crear el panel si no existe
    let materialsPanel = document.getElementById('materials-panel');
    if (materialsPanel) {
      materialsPanel.remove();
    }
    
    selectedMaterialType = null;
    
    const panelHtml = `
      <div id="materials-panel" class="mt-8 glass-strong rounded-2xl border border-slate-200/50 overflow-hidden">
        <div class="p-4 border-b border-slate-200/50 bg-gradient-to-r from-violet-50 to-purple-50">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center">
              <i class="iconoir-spark text-violet-600 text-xl"></i>
            </div>
            <div>
              <h3 class="font-bold text-slate-800">Materiales complementarios</h3>
              <p class="text-xs text-slate-500">Paso 3 (opcional): Selecciona un tipo y genera recursos adicionales</p>
            </div>
          </div>
        </div>
        
        <div class="p-4">
          <p class="text-sm text-slate-600 mb-3">Selecciona qué quieres generar:</p>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div class="material-card cursor-pointer p-4 rounded-xl border-2 border-slate-200 hover:border-violet-400 transition-all text-left" data-type="flashcards">
              <i class="iconoir-multiple-pages text-2xl text-violet-500 mb-2 block"></i>
              <p class="font-semibold text-slate-700 text-sm">Flashcards</p>
              <p class="text-xs text-slate-500">Tarjetas de estudio</p>
            </div>
            
            <div class="material-card cursor-pointer p-4 rounded-xl border-2 border-slate-200 hover:border-emerald-400 transition-all text-left" data-type="quiz">
              <i class="iconoir-check-circle text-2xl text-emerald-500 mb-2 block"></i>
              <p class="font-semibold text-slate-700 text-sm">Tests</p>
              <p class="text-xs text-slate-500">5-10 preguntas/módulo</p>
            </div>
            
            <div class="material-card cursor-pointer p-4 rounded-xl border-2 border-slate-200 hover:border-orange-400 transition-all text-left" data-type="final_exam">
              <i class="iconoir-graduation-cap text-2xl text-orange-500 mb-2 block"></i>
              <p class="font-semibold text-slate-700 text-sm">Examen final</p>
              <p class="text-xs text-slate-500">20 preguntas tipo test</p>
            </div>
            
            <div class="material-card cursor-pointer p-4 rounded-xl border-2 border-slate-200 hover:border-pink-400 transition-all text-left" data-type="podcast">
              <i class="iconoir-microphone text-2xl text-pink-500 mb-2 block"></i>
              <p class="font-semibold text-slate-700 text-sm">Podcast</p>
              <p class="text-xs text-slate-500">Guion de audio</p>
            </div>
          </div>
          
          <!-- Botón de generar (aparece al seleccionar) -->
          <div id="generate-material-action" class="hidden mb-4 p-4 bg-slate-50 border border-slate-200 rounded-xl">
            <div class="flex items-center justify-between">
              <div>
                <p class="font-semibold text-slate-700">Seleccionado: <span id="selected-material-name" class="text-violet-600"></span></p>
                <p class="text-xs text-slate-500">Haz clic en Generar para crear el material</p>
              </div>
              <button id="confirm-generate-btn" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white font-semibold rounded-lg transition-colors flex items-center gap-2">
                <i class="iconoir-spark"></i>
                <span>Generar</span>
              </button>
            </div>
          </div>
          
          <!-- Área de resultado del material generado -->
          <div id="material-result" class="hidden mt-4">
            <div class="flex items-center justify-between mb-3">
              <h4 id="material-result-title" class="font-semibold text-slate-700"></h4>
              <div class="flex gap-2">
                <button id="copy-material-btn" class="px-3 py-1.5 text-sm bg-white hover:bg-slate-50 border border-slate-200 rounded-lg transition-colors flex items-center gap-1.5">
                  <i class="iconoir-copy"></i> Copiar
                </button>
                <button id="download-material-btn" class="px-3 py-1.5 text-sm bg-violet-600 hover:bg-violet-700 text-white rounded-lg transition-colors flex items-center gap-1.5">
                  <i class="iconoir-download"></i> Descargar
                </button>
              </div>
            </div>
            <div class="preview-toggle mb-3">
              <button class="active" data-view="preview">Vista previa</button>
              <button data-view="raw">Texto</button>
            </div>
            <div id="material-preview" class="content-preview max-h-96 overflow-auto border border-slate-200 rounded-xl p-4 bg-white"></div>
            <div id="material-raw" class="hidden">
              <div class="raw-preview max-h-96 overflow-auto"><pre id="material-raw-content"></pre></div>
            </div>
          </div>
          
          <!-- Progress/Loading -->
          <div id="material-progress" class="hidden mt-4 p-4 bg-violet-50 border border-violet-200 rounded-xl">
            <div class="flex items-center gap-3">
              <i class="iconoir-refresh animate-spin text-violet-600 text-xl"></i>
              <div>
                <p class="font-semibold text-violet-800">Generando material...</p>
                <p id="material-progress-text" class="text-sm text-violet-600">Esto puede tardar unos segundos</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
    
    modulesContainer.insertAdjacentHTML('beforebegin', panelHtml);
    
    const typeNames = {
      'flashcards': 'Flashcards',
      'quiz': 'Tests por módulo',
      'final_exam': 'Examen final',
      'podcast': 'Guion de Podcast'
    };
    
    // Event listeners para tarjetas de selección
    document.querySelectorAll('.material-card').forEach(card => {
      card.addEventListener('click', () => {
        // Quitar selección anterior
        document.querySelectorAll('.material-card').forEach(c => {
          c.classList.remove('ring-2', 'ring-violet-500', 'bg-violet-50');
        });
        
        // Seleccionar esta tarjeta
        card.classList.add('ring-2', 'ring-violet-500', 'bg-violet-50');
        selectedMaterialType = card.dataset.type;
        
        // Mostrar botón de generar
        const action = document.getElementById('generate-material-action');
        const nameLbl = document.getElementById('selected-material-name');
        if (action) action.classList.remove('hidden');
        if (nameLbl) nameLbl.textContent = typeNames[selectedMaterialType] || selectedMaterialType;
      });
    });
    
    // Botón confirmar generación
    document.getElementById('confirm-generate-btn')?.addEventListener('click', () => {
      if (selectedMaterialType) {
        generateMaterial(selectedMaterialType);
      }
    });
    
    // Toggle vista previa/raw del material
    const materialsPanel2 = document.getElementById('materials-panel');
    materialsPanel2?.querySelectorAll('.preview-toggle button').forEach(btn => {
      btn.addEventListener('click', () => {
        btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        const preview = document.getElementById('material-preview');
        const raw = document.getElementById('material-raw');
        
        if (btn.dataset.view === 'preview') {
          preview?.classList.remove('hidden');
          raw?.classList.add('hidden');
        } else {
          preview?.classList.add('hidden');
          raw?.classList.remove('hidden');
        }
      });
    });
  }
  
  let currentMaterialOutput = null;
  let currentMaterialType = null;
  
  async function generateMaterial(type) {
    const typeNames = {
      'flashcards': 'Flashcards',
      'quiz': 'Tests por módulo',
      'final_exam': 'Examen final',
      'podcast': 'Guion de Podcast'
    };
    
    const progress = document.getElementById('material-progress');
    const progressText = document.getElementById('material-progress-text');
    const result = document.getElementById('material-result');
    const btns = document.querySelectorAll('.material-btn');
    
    // Mostrar progreso
    progress?.classList.remove('hidden');
    result?.classList.add('hidden');
    if (progressText) progressText.textContent = `Generando ${typeNames[type]}...`;
    btns.forEach(b => b.disabled = true);
    
    try {
      // Concatenar contenido de módulos
      const modulesContent = currentModules.map(m => 
        `## ${m.title}\n\n${m.content}`
      ).join('\n\n---\n\n');
      
      const response = await fetch('/api/gestures/course-materials.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          execution_id: currentExecutionId,
          material_type: type,
          course_title: currentCourseTitle,
          modules_content: modulesContent
        })
      });
      
      const data = await response.json();
      
      if (!data.success) {
        throw new Error(data.error?.message || data.message || 'Error generando material');
      }
      
      // Guardar y mostrar resultado
      currentMaterialOutput = data.output;
      currentMaterialType = type;
      
      showMaterialResult(typeNames[type], data.output);
      loadHistory();
      
      // Recargar badges de materiales relacionados
      if (currentExecutionId) {
        await loadRelatedMaterials(currentExecutionId);
      }
      
    } catch (err) {
      alert('Error: ' + err.message);
    } finally {
      progress?.classList.add('hidden');
      btns.forEach(b => b.disabled = false);
    }
  }
  
  function showMaterialResult(title, output) {
    const result = document.getElementById('material-result');
    const resultTitle = document.getElementById('material-result-title');
    const preview = document.getElementById('material-preview');
    const rawContent = document.getElementById('material-raw-content');
    
    result?.classList.remove('hidden');
    if (resultTitle) resultTitle.textContent = title;
    if (preview) preview.innerHTML = renderMarkdownPreview(output);
    if (rawContent) rawContent.textContent = output;
    
    // Copiar material
    document.getElementById('copy-material-btn')?.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(currentMaterialOutput);
        const btn = document.getElementById('copy-material-btn');
        if (btn) {
          btn.innerHTML = '<i class="iconoir-check"></i> Copiado';
          setTimeout(() => {
            btn.innerHTML = '<i class="iconoir-copy"></i> Copiar';
          }, 2000);
        }
      } catch (err) {
        console.error('Error copying:', err);
      }
    });
    
    // Descargar material a Word
    document.getElementById('download-material-btn')?.addEventListener('click', async () => {
      const btn = document.getElementById('download-material-btn');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="iconoir-refresh animate-spin"></i> Exportando...';
      }
      
      const typeNames = {
        'flashcards': 'Flashcards',
        'quiz': 'Tests',
        'final_exam': 'Examen Final',
        'podcast': 'Podcast'
      };
      const materialTitle = `${currentCourseTitle} - ${typeNames[currentMaterialType] || 'Material'}`;
      
      await exportToWord('material', currentMaterialOutput, materialTitle);
      
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="iconoir-download"></i> Descargar';
      }
    });
  }
  
  // =========================================================================
  // EXPORTAR A WORD
  // =========================================================================
  async function exportToWord(exportType, content, title) {
    try {
      const response = await fetch('/api/gestures/course-export.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          export_type: exportType,
          content: content,
          title: title,
          format: 'docx'
        })
      });
      
      const data = await response.json();
      
      if (!data.success) {
        throw new Error(data.error?.message || data.message || 'Error exportando documento');
      }
      
      // Decodificar base64 y descargar
      const byteCharacters = atob(data.content);
      const byteNumbers = new Array(byteCharacters.length);
      for (let i = 0; i < byteCharacters.length; i++) {
        byteNumbers[i] = byteCharacters.charCodeAt(i);
      }
      const byteArray = new Uint8Array(byteNumbers);
      const blob = new Blob([byteArray], { type: data.mime_type });
      
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = data.filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      
    } catch (err) {
      console.error('Error exportando a Word:', err);
      alert('Error exportando: ' + err.message);
    }
  }

  // Buscar materiales complementarios ya generados para un curso
  async function loadRelatedMaterials(courseExecutionId) {
    if (!courseExecutionId) return;
    
    const targetId = parseInt(courseExecutionId, 10);
    
    try {
      const response = await fetch(`/api/gestures/history.php?type=${GESTURE_TYPE}&limit=100`, {
        credentials: 'include'
      });
      const data = await response.json();
      
      if (!data.items) return;
      
      // Buscar materiales que tengan source_execution_id = courseExecutionId
      const relatedMaterials = data.items.filter(item => {
        const contentType = item.content_type || '';
        if (!contentType.startsWith('course_material_')) return false;
        
        // input_data ya viene parseado desde el backend
        const inputData = item.input_data || {};
        const sourceId = parseInt(inputData.source_execution_id, 10);
        
        return sourceId === targetId;
      });
      
      if (relatedMaterials.length > 0) {
        showRelatedMaterialsBadges(relatedMaterials);
      }
    } catch (err) {
      console.error('Error loading related materials:', err);
    }
  }
  
  function showRelatedMaterialsBadges(materials) {
    const materialsPanel = document.getElementById('materials-panel');
    if (!materialsPanel) return;
    
    // Mapeo con clases CSS completas
    const materialTypeMap = {
      'course_material_flashcards': { 
        icon: 'iconoir-multiple-pages', 
        label: 'Flashcards', 
        btnClass: 'border-violet-300 hover:bg-violet-50 text-violet-700'
      },
      'course_material_quiz': { 
        icon: 'iconoir-check-circle', 
        label: 'Tests', 
        btnClass: 'border-emerald-300 hover:bg-emerald-50 text-emerald-700'
      },
      'course_material_final_exam': { 
        icon: 'iconoir-graduation-cap', 
        label: 'Examen', 
        btnClass: 'border-orange-300 hover:bg-orange-50 text-orange-700'
      },
      'course_material_podcast': { 
        icon: 'iconoir-microphone', 
        label: 'Podcast', 
        btnClass: 'border-pink-300 hover:bg-pink-50 text-pink-700'
      }
    };
    
    // Eliminar sección existente si hay
    const existingSection = materialsPanel.querySelector('.existing-materials');
    if (existingSection) {
      existingSection.remove();
    }
    
    // Ordenar por fecha (más reciente primero)
    materials.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    
    const materialsHtml = `
      <div class="existing-materials mb-4 p-4 bg-gradient-to-r from-violet-50 to-purple-50 border border-violet-200 rounded-xl">
        <p class="text-sm font-semibold text-violet-800 mb-3 flex items-center gap-2">
          <i class="iconoir-check-circle"></i> Materiales ya generados (clic para ver)
        </p>
        <div class="flex flex-wrap gap-2">
          ${materials.map(mat => {
            const info = materialTypeMap[mat.content_type] || { 
              icon: 'iconoir-spark', 
              label: 'Material', 
              btnClass: 'border-gray-300 hover:bg-gray-50 text-gray-700'
            };
            // Formatear fecha corta
            const date = mat.created_at ? new Date(mat.created_at).toLocaleDateString('es-ES', { 
              day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' 
            }) : '';
            return `
              <button class="existing-material-badge px-3 py-2 bg-white border-2 ${info.btnClass} rounded-lg transition-all flex items-center gap-2 text-sm font-medium shadow-sm hover:shadow" data-material-id="${mat.id}" data-content-type="${mat.content_type}">
                <i class="${info.icon}"></i>
                <span>${info.label}</span>
                <span class="text-xs opacity-60">${date}</span>
                <i class="iconoir-arrow-right text-xs opacity-50"></i>
              </button>
            `;
          }).join('')}
        </div>
      </div>
    `;
    
    // Insertar al principio del panel de materiales
    const panelContent = materialsPanel.querySelector('.p-4');
    if (panelContent) {
      panelContent.insertAdjacentHTML('afterbegin', materialsHtml);
      
      // Event listeners para cargar cada material
      panelContent.querySelectorAll('.existing-material-badge').forEach(btn => {
        btn.addEventListener('click', async () => {
          const materialId = btn.dataset.materialId;
          const contentType = btn.dataset.contentType;
          await loadHistoryItem(materialId, 3, contentType);
        });
      });
    }
  }

  function downloadModule(module, courseTitle) {
    const filename = `${slugify(courseTitle || 'curso')}-modulo-${module.module_id}.md`;
    const blob = new Blob([module.content], { type: 'text/markdown;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  // === UI Functions ===
  function showProgress(text, detail) {
    if (progressPanel) progressPanel.classList.remove('hidden');
    if (progressText) progressText.textContent = text;
    if (progressDetail) progressDetail.textContent = detail;
    if (generateBtn) {
      generateBtn.disabled = true;
      generateBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
  }

  function hideProgress() {
    if (progressPanel) progressPanel.classList.add('hidden');
    if (generateBtn) {
      generateBtn.disabled = false;
      generateBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
  }

  function showError(msg) {
    if (errorPanel) errorPanel.classList.remove('hidden');
    if (errorMessage) errorMessage.textContent = msg;
  }

  function hideError() {
    if (errorPanel) errorPanel.classList.add('hidden');
  }

  function resetUI() {
    if (resultSection) resultSection.classList.add('hidden');
    if (outlineSection) outlineSection.classList.add('hidden');
    if (inputSection) inputSection.classList.remove('hidden');
    
    // Quitar panel de materiales si existe
    document.getElementById('materials-panel')?.remove();
    
    if (sourceText) sourceText.value = '';
    if (sourcePdf) sourcePdf.value = '';
    pdfBase64 = null;
    if (pdfFilename) pdfFilename.classList.add('hidden');
    
    currentOutline = null;
    currentExecutionId = null;
    currentModules = null;
    currentCourseTitle = null;
    currentMaterialOutput = null;
    currentMaterialType = null;
    hideError();
  }

  // === Markdown Preview ===
  function renderMarkdownPreview(text) {
    if (!text) return '<p class="text-slate-400">Sin contenido</p>';
    
    let html = escapeHtml(text);
    
    html = html.replace(/^#### (.*$)/gm, '<h4 class="text-base font-semibold text-slate-700 mt-4 mb-2">$1</h4>');
    html = html.replace(/^### (.*$)/gm, '<h3 class="text-lg font-semibold text-slate-800 mt-6 mb-3">$1</h3>');
    html = html.replace(/^## (.*$)/gm, '<h2 class="text-xl font-bold text-slate-800 mt-8 mb-4 pb-2 border-b">$1</h2>');
    html = html.replace(/^# (.*$)/gm, '<h1 class="text-2xl font-bold text-slate-900 mb-4">$1</h1>');
    
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
    
    html = html.replace(/^- (.*$)/gm, '<li class="ml-4">$1</li>');
    html = html.replace(/^(\d+)\. (.*$)/gm, '<li class="ml-4">$2</li>');
    
    html = html.replace(/\n\n/g, '</p><p class="mb-3">');
    html = '<p class="mb-3">' + html + '</p>';
    
    html = html.replace(/<p class="mb-3"><\/p>/g, '');
    html = html.replace(/<p class="mb-3">(\s*<h[1-4])/g, '$1');
    html = html.replace(/(<\/h[1-4]>)\s*<\/p>/g, '$1');
    
    html = html.replace(/\n/g, '<br>');
    html = html.replace(/^---$/gm, '<hr class="my-6 border-slate-200">');
    
    return html;
  }

  // === History ===
  async function loadHistory() {
    try {
      const response = await fetch(`/api/gestures/history.php?type=${GESTURE_TYPE}&limit=20`, {
        credentials: 'include'
      });
      const data = await response.json();
      
      if (data.items) {
        renderHistory(data.items);
      }
    } catch (err) {
      console.error('Error loading history:', err);
    }
  }

  function renderHistory(items) {
    if (!historyList) return;
    
    // Filtrar solo fase 1 y 2 (los materiales fase 3 se mostrarán dentro del curso)
    const mainItems = items.filter(item => {
      const contentType = item.content_type || '';
      return !contentType.startsWith('course_material_');
    });
    
    if (!mainItems || mainItems.length === 0) {
      historyList.innerHTML = `
        <div class="p-6 text-center">
          <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
            <i class="iconoir-graduation-cap text-xl text-emerald-400"></i>
          </div>
          <p class="text-sm text-slate-500">Aún no has creado cursos</p>
          <p class="text-xs text-slate-400 mt-1">Sube un PDF para empezar</p>
        </div>
      `;
      return;
    }

    const levelIcons = { basico: '🌱', intermedio: '🌿', avanzado: '🌳' };
    
    // Mapeo de content_type a fase y etiqueta
    const contentTypeMap = {
      'course_outline': { phase: 1, label: 'Índice', icon: 'iconoir-list', bgClass: 'bg-emerald-100', iconClass: 'text-emerald-600' },
      'course_developed': { phase: 2, label: 'Desarrollado', icon: 'iconoir-graduation-cap', bgClass: 'bg-emerald-600', iconClass: 'text-white' },
      'course_material_flashcards': { phase: 3, label: 'Flashcards', icon: 'iconoir-multiple-pages', bgClass: 'bg-violet-500', iconClass: 'text-white' },
      'course_material_quiz': { phase: 3, label: 'Tests', icon: 'iconoir-check-circle', bgClass: 'bg-emerald-500', iconClass: 'text-white' },
      'course_material_final_exam': { phase: 3, label: 'Examen', icon: 'iconoir-graduation-cap', bgClass: 'bg-orange-500', iconClass: 'text-white' },
      'course_material_podcast': { phase: 3, label: 'Podcast', icon: 'iconoir-microphone', bgClass: 'bg-pink-500', iconClass: 'text-white' }
    };

    historyList.innerHTML = mainItems.map(item => {
      const inputData = typeof item.input_data === 'string' ? JSON.parse(item.input_data) : (item.input_data || {});
      const config = inputData?.config || {};
      
      // Detectar fase y estilo usando content_type
      const typeInfo = contentTypeMap[item.content_type] || contentTypeMap['course_outline'];
      const phase = typeInfo.phase;
      
      const date = new Date(item.created_at).toLocaleDateString('es-ES', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
      });
      const levelIcon = levelIcons[config.level] || '📚';

      return `
        <div class="history-item group border-b border-slate-100 last:border-0" data-id="${item.id}" data-phase="${phase}" data-content-type="${item.content_type || ''}">
          <div class="history-item-main p-3 hover:bg-slate-50 cursor-pointer flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg ${typeInfo.bgClass} flex items-center justify-center shrink-0">
              <i class="${typeInfo.icon} ${typeInfo.iconClass} text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-slate-800 truncate">${escapeHtml(item.title || 'Sin título')}</p>
              <p class="text-xs text-slate-500 mt-0.5">
                ${typeInfo.label} · ${date}
              </p>
            </div>
            <button class="history-item-delete opacity-0 group-hover:opacity-100 p-1.5 hover:bg-red-50 rounded transition-all" title="Eliminar">
              <i class="iconoir-trash text-slate-400 hover:text-red-500 text-sm"></i>
            </button>
          </div>
        </div>
      `;
    }).join('');

    historyList.querySelectorAll('.history-item-main').forEach(el => {
      el.addEventListener('click', () => {
        const historyItem = el.closest('.history-item');
        const id = historyItem.dataset.id;
        const phase = parseInt(historyItem.dataset.phase) || 1;
        const contentType = historyItem.dataset.contentType || '';
        loadHistoryItem(id, phase, contentType);
      });
    });

    historyList.querySelectorAll('.history-item-delete').forEach(el => {
      el.addEventListener('click', (e) => {
        e.stopPropagation();
        const id = el.closest('.history-item').dataset.id;
        deleteHistoryItem(id);
      });
    });
  }

  async function loadHistoryItem(id, phase, contentType = '') {
    try {
      const response = await fetch(`/api/gestures/get.php?id=${id}`, {
        credentials: 'include'
      });
      const data = await response.json();
      
      if (data.execution) {
        const item = data.execution;
        const outputData = typeof item.output_data === 'string' 
          ? JSON.parse(item.output_data) 
          : item.output_data;
        
        // Fase 3: Material complementario
        if (phase === 3 || contentType.startsWith('course_material_')) {
          const materialTypeNames = {
            'course_material_flashcards': 'Flashcards',
            'course_material_quiz': 'Tests por módulo',
            'course_material_final_exam': 'Examen final',
            'course_material_podcast': 'Guion de Podcast'
          };
          
          const materialOutput = outputData?.raw || item.output_content || '';
          const materialTitle = materialTypeNames[contentType] || 'Material';
          
          // Obtener input_data para el botón "volver al curso"
          const inputData = typeof item.input_data === 'string' 
            ? JSON.parse(item.input_data) 
            : (item.input_data || {});
          const sourceExecutionId = inputData.source_execution_id;
          
          // Ocultar otras secciones y mostrar resultado
          if (inputSection) inputSection.classList.add('hidden');
          if (outlineSection) outlineSection.classList.add('hidden');
          if (resultSection) resultSection.classList.remove('hidden');
          
          if (resultTitle) resultTitle.textContent = outputData?.course_title || 'Curso';
          if (resultSource) resultSource.textContent = materialTitle;
          
          if (modulesContainer) {
            modulesContainer.innerHTML = `
              ${sourceExecutionId ? `
              <div class="mb-4">
                <button id="back-to-course-btn" class="px-4 py-2 bg-emerald-100 hover:bg-emerald-200 text-emerald-700 rounded-lg transition-colors flex items-center gap-2 text-sm font-medium" data-course-id="${sourceExecutionId}">
                  <i class="iconoir-arrow-left"></i>
                  <span>Volver al curso</span>
                </button>
              </div>
              ` : ''}
              <div class="glass-strong rounded-2xl border border-slate-200/50 overflow-hidden mb-4">
                <div class="p-4 border-b border-slate-200/50 bg-gradient-to-r from-violet-50 to-purple-50">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 rounded-lg bg-violet-600 text-white flex items-center justify-center font-bold">
                        <i class="iconoir-spark"></i>
                      </div>
                      <div>
                        <h3 class="font-semibold text-slate-800">${escapeHtml(materialTitle)}</h3>
                        <p class="text-xs text-slate-500">${escapeHtml(outputData?.course_title || '')}</p>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <button class="copy-material-hist-btn px-3 py-1.5 text-sm bg-white hover:bg-slate-50 border border-slate-200 rounded-lg transition-colors flex items-center gap-1.5">
                        <i class="iconoir-copy"></i> Copiar
                      </button>
                      <button class="download-material-hist-btn px-3 py-1.5 text-sm bg-violet-600 hover:bg-violet-700 text-white rounded-lg transition-colors flex items-center gap-1.5">
                        <i class="iconoir-download"></i> Descargar
                      </button>
                    </div>
                  </div>
                </div>
                <div class="p-4">
                  <div class="preview-toggle mb-4">
                    <button class="active" data-view="preview">Vista previa</button>
                    <button data-view="raw">Texto</button>
                  </div>
                  <div class="preview-view content-preview max-h-96 overflow-auto">${renderMarkdownPreview(materialOutput)}</div>
                  <div class="raw-view hidden">
                    <div class="raw-preview max-h-96 overflow-auto"><pre>${escapeHtml(materialOutput)}</pre></div>
                  </div>
                </div>
              </div>
            `;
            
            // Event listeners
            modulesContainer.querySelector('.copy-material-hist-btn')?.addEventListener('click', async () => {
              try {
                await navigator.clipboard.writeText(materialOutput);
                const btn = modulesContainer.querySelector('.copy-material-hist-btn');
                if (btn) {
                  btn.innerHTML = '<i class="iconoir-check"></i> Copiado';
                  setTimeout(() => btn.innerHTML = '<i class="iconoir-copy"></i> Copiar', 2000);
                }
              } catch (err) { console.error(err); }
            });
            
            modulesContainer.querySelector('.download-material-hist-btn')?.addEventListener('click', async () => {
              const btn = modulesContainer.querySelector('.download-material-hist-btn');
              if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="iconoir-refresh animate-spin"></i> Exportando...';
              }
              
              const typeNames = {
                'course_material_flashcards': 'Flashcards',
                'course_material_quiz': 'Tests',
                'course_material_final_exam': 'Examen Final',
                'course_material_podcast': 'Podcast'
              };
              const title = `${outputData?.course_title || 'Curso'} - ${typeNames[contentType] || 'Material'}`;
              
              await exportToWord('material', materialOutput, title);
              
              if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="iconoir-download"></i> Descargar';
              }
            });
            
            // Toggle
            modulesContainer.querySelectorAll('.preview-toggle button').forEach(btn => {
              btn.addEventListener('click', () => {
                const panel = btn.closest('.glass-strong');
                btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const pv = panel.querySelector('.preview-view');
                const rv = panel.querySelector('.raw-view');
                if (btn.dataset.view === 'preview') {
                  pv?.classList.remove('hidden');
                  rv?.classList.add('hidden');
                } else {
                  pv?.classList.add('hidden');
                  rv?.classList.remove('hidden');
                }
              });
            });
            
            // Botón volver al curso
            const backBtn = document.getElementById('back-to-course-btn');
            if (backBtn) {
              backBtn.addEventListener('click', () => {
                const courseId = backBtn.dataset.courseId;
                if (courseId) {
                  loadHistoryItem(courseId, 2, 'course_developed');
                }
              });
            }
          }
          
          // Quitar panel de materiales si existe
          document.getElementById('materials-panel')?.remove();
        }
        // Fase 2: Módulos desarrollados
        else if (outputData?.modules && outputData.modules.length > 0) {
          showDevelopedModules({
            course_title: outputData.course_title || item.title,
            modules: outputData.modules,
            total_developed: outputData.total_developed || outputData.modules.length,
            execution_id: id
          });
        } 
        // Fase 1: Índice editable
        else if (outputData?.outline) {
          currentOutline = outputData.outline;
          currentExecutionId = id;
          showOutlineEditor({ outline: outputData.outline });
        }
        
        historyList?.querySelectorAll('.history-item').forEach(el => {
          el.classList.remove('bg-emerald-50', 'bg-violet-50');
        });
        historyList?.querySelector(`.history-item[data-id="${id}"]`)?.classList.add(phase === 3 ? 'bg-violet-50' : 'bg-emerald-50');
      }
    } catch (err) {
      console.error('Error loading history item:', err);
    }
  }

  async function deleteHistoryItem(id) {
    if (!confirm('¿Eliminar este curso del historial?')) return;
    
    try {
      const response = await fetch('/api/gestures/delete.php', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.CSRF_TOKEN
        },
        credentials: 'include',
        body: JSON.stringify({ id })
      });
      
      const data = await response.json();
      if (data.success) {
        loadHistory();
      }
    } catch (err) {
      console.error('Error deleting:', err);
    }
  }

  // === Utilities ===
  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function slugify(text) {
    return text
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '')
      .substring(0, 50);
  }

  // === Init ===
  loadHistory();

  // Export for external access
  window.courseCreator = { resetUI };
})();
