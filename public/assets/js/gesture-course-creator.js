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
  
  // Fase 3: Materiales complementarios
  const extrasSection = document.getElementById('extras-section');
  const extrasCheckboxes = document.querySelectorAll('input[name="extras"]');
  const generateExtrasBtn = document.getElementById('generate-extras-btn');
  const extrasResults = document.getElementById('extras-results');

  // === State ===
  let currentTab = 'pdf';
  let pdfBase64 = null;
  let currentOutline = null;
  let currentExecutionId = null;
  let sourceContent = null;
  let currentDevelopedModules = null;
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
  function showDevelopedModules(data) {
    if (inputSection) inputSection.classList.add('hidden');
    if (outlineSection) outlineSection.classList.add('hidden');
    if (resultSection) resultSection.classList.remove('hidden');
    
    if (resultTitle) resultTitle.textContent = data.course_title || 'Curso desarrollado';
    if (resultSource) resultSource.textContent = `${data.total_developed} módulo${data.total_developed !== 1 ? 's' : ''} generado${data.total_developed !== 1 ? 's' : ''}`;
    
    const modules = data.modules || [];
    
    if (modulesContainer) {
      modulesContainer.innerHTML = modules.map((module, i) => `
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
                <button class="download-module-btn px-3 py-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors flex items-center gap-1.5" data-index="${i}">
                  <i class="iconoir-download"></i> Descargar
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
      
      modulesContainer.querySelectorAll('.download-module-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const idx = parseInt(btn.dataset.index);
          const module = modules[idx];
          downloadModule(module, data.course_title);
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
    }

    // Guardar módulos en estado para Fase 3
    currentDevelopedModules = modules;
    currentCourseTitle = data.course_title;
    
    // Configurar checkboxes de extras
    setupExtrasUI();

    // Botón nuevo curso
    const newCourseBtn = document.getElementById('new-course-btn');
    if (newCourseBtn) {
      newCourseBtn.addEventListener('click', resetUI);
    }
  }
  
  // =========================================================================
  // FASE 3: MATERIALES COMPLEMENTARIOS
  // =========================================================================
  function setupExtrasUI() {
    // Limpiar resultados previos
    if (extrasResults) {
      extrasResults.innerHTML = '';
      extrasResults.classList.add('hidden');
    }
    
    // Reset checkboxes
    extrasCheckboxes.forEach(cb => {
      cb.checked = false;
    });
    
    // Actualizar estado del botón
    updateExtrasButton();
    
    // Event listeners para checkboxes
    extrasCheckboxes.forEach(cb => {
      cb.removeEventListener('change', updateExtrasButton);
      cb.addEventListener('change', updateExtrasButton);
    });
    
    // Event listener para botón generar
    if (generateExtrasBtn) {
      generateExtrasBtn.removeEventListener('click', generateExtras);
      generateExtrasBtn.addEventListener('click', generateExtras);
    }
  }
  
  function updateExtrasButton() {
    const selectedFormats = getSelectedExtras();
    
    if (generateExtrasBtn) {
      if (selectedFormats.length === 0) {
        generateExtrasBtn.disabled = true;
        generateExtrasBtn.innerHTML = `
          <i class="iconoir-magic-wand"></i>
          <span>Selecciona materiales para generar</span>
        `;
      } else {
        generateExtrasBtn.disabled = false;
        generateExtrasBtn.innerHTML = `
          <i class="iconoir-magic-wand"></i>
          <span>Generar ${selectedFormats.length} material${selectedFormats.length > 1 ? 'es' : ''}</span>
        `;
      }
    }
  }
  
  function getSelectedExtras() {
    return Array.from(extrasCheckboxes)
      .filter(cb => cb.checked)
      .map(cb => cb.value);
  }
  
  async function generateExtras() {
    const formats = getSelectedExtras();
    
    if (formats.length === 0 || !currentDevelopedModules) {
      alert('Selecciona al menos un material para generar');
      return;
    }
    
    // Mostrar progreso
    if (generateExtrasBtn) {
      generateExtrasBtn.disabled = true;
      generateExtrasBtn.innerHTML = `
        <i class="iconoir-refresh animate-spin"></i>
        <span>Generando materiales...</span>
      `;
    }
    
    // Mostrar panel de progreso
    if (extrasResults) {
      extrasResults.innerHTML = `
        <div class="p-4 bg-violet-50 border border-violet-200 rounded-xl">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center">
              <i class="iconoir-refresh animate-spin text-violet-600 text-xl"></i>
            </div>
            <div>
              <p class="font-semibold text-violet-800">Generando ${formats.length} material${formats.length > 1 ? 'es' : ''}...</p>
              <p class="text-sm text-violet-600">Esto puede tardar unos minutos.</p>
            </div>
          </div>
        </div>
      `;
      extrasResults.classList.remove('hidden');
    }
    
    try {
      const response = await fetch('/api/gestures/course-extras.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          execution_id: currentExecutionId,
          formats: formats,
          modules: currentDevelopedModules,
          course_title: currentCourseTitle
        })
      });
      
      const data = await response.json();
      
      console.log('Respuesta extras:', data);
      
      if (!data.success) {
        throw new Error(data.error?.message || data.message || 'Error generando materiales');
      }
      
      showExtrasResults(data);
      loadHistory();
      
    } catch (err) {
      if (extrasResults) {
        extrasResults.innerHTML = `
          <div class="p-4 bg-red-50 border border-red-200 rounded-xl">
            <p class="text-red-700"><i class="iconoir-warning-circle mr-2"></i>${err.message}</p>
          </div>
        `;
      }
    } finally {
      updateExtrasButton();
    }
  }
  
  function showExtrasResults(data) {
    const materials = data.materials || {};
    
    const formatIcons = {
      flashcards: 'iconoir-card-wallet',
      quiz: 'iconoir-check-circle',
      final_exam: 'iconoir-trophy',
      podcast: 'iconoir-podcast'
    };
    
    if (extrasResults) {
      extrasResults.innerHTML = Object.entries(materials).map(([key, material]) => `
        <div class="glass-strong rounded-2xl border border-slate-200/50 overflow-hidden">
          <div class="p-4 border-b border-slate-200/50 bg-gradient-to-r from-violet-50 to-purple-50">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-violet-600 text-white flex items-center justify-center">
                  <i class="${formatIcons[key] || 'iconoir-document'} text-lg"></i>
                </div>
                <div>
                  <h3 class="font-semibold text-slate-800">${escapeHtml(material.format_name)}</h3>
                  <p class="text-xs text-slate-500">Generado con ${material.model || 'IA'}</p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <button class="copy-extra-btn px-3 py-1.5 text-sm bg-white hover:bg-slate-50 border border-slate-200 rounded-lg transition-colors flex items-center gap-1.5" data-format="${key}">
                  <i class="iconoir-copy"></i> Copiar
                </button>
                <button class="download-extra-btn px-3 py-1.5 text-sm bg-violet-600 hover:bg-violet-700 text-white rounded-lg transition-colors flex items-center gap-1.5" data-format="${key}">
                  <i class="iconoir-download"></i> Descargar
                </button>
              </div>
            </div>
          </div>
          
          <div class="p-4">
            <div class="content-preview max-h-96 overflow-auto text-sm">
              <pre class="whitespace-pre-wrap font-sans">${escapeHtml(material.content)}</pre>
            </div>
          </div>
        </div>
      `).join('');
      
      extrasResults.classList.remove('hidden');
      
      // Event listeners para copiar/descargar
      extrasResults.querySelectorAll('.copy-extra-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
          const format = btn.dataset.format;
          const content = materials[format]?.content;
          if (content) {
            try {
              await navigator.clipboard.writeText(content);
              btn.innerHTML = '<i class="iconoir-check"></i> Copiado';
              setTimeout(() => {
                btn.innerHTML = '<i class="iconoir-copy"></i> Copiar';
              }, 2000);
            } catch (err) {
              console.error('Error copying:', err);
            }
          }
        });
      });
      
      extrasResults.querySelectorAll('.download-extra-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const format = btn.dataset.format;
          const material = materials[format];
          if (material) {
            const filename = `${slugify(currentCourseTitle || 'curso')}-${format}.md`;
            const blob = new Blob([material.content], { type: 'text/markdown;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
          }
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
    
    if (sourceText) sourceText.value = '';
    if (sourcePdf) sourcePdf.value = '';
    pdfBase64 = null;
    if (pdfFilename) pdfFilename.classList.add('hidden');
    
    currentOutline = null;
    currentExecutionId = null;
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
    
    if (!items || items.length === 0) {
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
    const phaseLabels = { 1: 'Índice', 2: 'Desarrollado', 3: 'Materiales' };
    const phaseColors = {
      1: { bg: 'bg-emerald-100', text: 'text-emerald-600', icon: 'iconoir-graduation-cap' },
      2: { bg: 'bg-emerald-600', text: 'text-white', icon: 'iconoir-graduation-cap' },
      3: { bg: 'bg-violet-600', text: 'text-white', icon: 'iconoir-magic-wand' }
    };

    historyList.innerHTML = items.map(item => {
      const inputData = typeof item.input_data === 'string' ? JSON.parse(item.input_data) : (item.input_data || {});
      const config = inputData?.config || {};
      
      // Detectar fase usando content_type
      let phase = 1;
      if (item.content_type === 'course_developed') phase = 2;
      else if (item.content_type === 'course_materials') phase = 3;
      
      const date = new Date(item.created_at).toLocaleDateString('es-ES', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
      });
      const levelIcon = levelIcons[config.level] || '📚';
      const phaseLabel = phaseLabels[phase] || '';
      const colors = phaseColors[phase] || phaseColors[1];

      return `
        <div class="history-item group border-b border-slate-100 last:border-0" data-id="${item.id}" data-phase="${phase}">
          <div class="history-item-main p-3 hover:bg-slate-50 cursor-pointer flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg ${colors.bg} flex items-center justify-center shrink-0">
              <i class="${colors.icon} ${colors.text} text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-slate-800 truncate">${escapeHtml(item.title || 'Sin título')}</p>
              <p class="text-xs text-slate-500 mt-0.5">
                ${levelIcon} ${config.duration || '8h'} · ${phaseLabel} · ${date}
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
        const id = el.closest('.history-item').dataset.id;
        const phase = parseInt(el.closest('.history-item').dataset.phase) || 1;
        loadHistoryItem(id, phase);
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

  async function loadHistoryItem(id, phase) {
    try {
      const response = await fetch(`/api/gestures/get.php?id=${id}`, {
        credentials: 'include'
      });
      const data = await response.json();
      
      console.log('Loading history item:', { id, phase, data }); // Debug
      
      if (data.execution) {
        const item = data.execution;
        const outputData = typeof item.output_data === 'string' 
          ? JSON.parse(item.output_data) 
          : item.output_data;
        
        console.log('Output data:', outputData); // Debug
        
        // Detectar tipo de contenido
        const contentType = item.content_type;
        
        // Fase 3: Materiales complementarios
        if (contentType === 'course_materials' && outputData?.materials) {
          console.log('Mostrando materiales complementarios'); // Debug
          if (inputSection) inputSection.classList.add('hidden');
          if (outlineSection) outlineSection.classList.add('hidden');
          if (resultSection) resultSection.classList.remove('hidden');
          if (resultTitle) resultTitle.textContent = outputData.course_title || 'Materiales del curso';
          if (resultSource) resultSource.textContent = `${outputData.total_generated || Object.keys(outputData.materials).length} materiales generados`;
          if (modulesContainer) modulesContainer.innerHTML = '';
          showExtrasResults({ materials: outputData.materials });
        }
        // Fase 2: Módulos desarrollados
        else if (outputData?.modules && outputData.modules.length > 0) {
          console.log('Mostrando módulos desarrollados'); // Debug
          showDevelopedModules({
            course_title: outputData.course_title || item.title,
            modules: outputData.modules,
            total_developed: outputData.total_developed || outputData.modules.length
          });
        } 
        // Fase 1: Índice editable
        else if (outputData?.outline) {
          console.log('Mostrando editor de índice'); // Debug
          currentOutline = outputData.outline;
          currentExecutionId = id;
          showOutlineEditor({ outline: outputData.outline });
        }
        else {
          console.warn('No se encontraron datos válidos'); // Debug
        }
        
        historyList?.querySelectorAll('.history-item').forEach(el => {
          el.classList.remove('bg-emerald-50');
        });
        historyList?.querySelector(`.history-item[data-id="${id}"]`)?.classList.add('bg-emerald-50');
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
