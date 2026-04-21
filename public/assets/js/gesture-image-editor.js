/**
 * Gesto: Editor de Imagenes con IA
 * Flujo: intencion -> generar/editar -> resultado -> iteracion
 */
(function() {
  'use strict';

  const GESTURE_TYPE = 'image-editor';
  const MAX_GENERATE_REFERENCES = 4;

  const descriptionField = document.getElementById('image-description');
  const generateBtn = document.getElementById('generate-image-btn');
  const downloadBtn = document.getElementById('download-image-btn');
  const regenerateBtn = document.getElementById('regenerate-image-btn');
  const editThisImageBtn = document.getElementById('edit-this-image-btn');
  const fullscreenBtn = document.getElementById('fullscreen-btn');
  const newImageBtn = document.getElementById('new-image-btn');
  const historyList = document.getElementById('history-list');
  const summaryText = document.getElementById('summary-text');
  const imageError = document.getElementById('image-error');
  const loadingTitle = document.getElementById('loading-title');
  const loadingDetail = document.getElementById('loading-detail');
  const loadingMeta = document.getElementById('loading-meta');
  const currentIntentInput = document.getElementById('current-intent');

  const currentModeInput = document.getElementById('current-mode');
  const currentProviderInput = document.getElementById('current-provider');

  const imagePlaceholder = document.getElementById('image-placeholder');
  const editSourceSection = document.getElementById('edit-source-section');
  const imageResult = document.getElementById('image-result');
  const imageLoading = document.getElementById('image-loading');
  const imageCaption = document.getElementById('image-caption');
  const generateReferencesSection = document.getElementById('generate-references-section');
  const editQuickActions = document.getElementById('edit-quick-actions');

  const generatedImage = document.getElementById('generated-image');
  const sourceImageInput = document.getElementById('source-image-input');
  const sourceImagePreview = document.getElementById('source-image-preview');
  const sourceImagePlaceholder = document.getElementById('source-image-placeholder');
  const sourceImageClear = document.getElementById('source-image-clear');
  const targetImageInput = document.getElementById('target-image-input');
  const targetImagePreview = document.getElementById('target-image-preview');
  const targetImagePlaceholder = document.getElementById('target-image-placeholder');
  const targetImageClear = document.getElementById('target-image-clear');
  const addGenerateReferenceBtn = document.getElementById('add-generate-reference-btn');
  const generateReferenceInput = document.getElementById('generate-reference-input');
  const generateReferenceList = document.getElementById('generate-reference-list');
  const intentCards = document.querySelectorAll('.intent-card');
  const editQuickChips = document.querySelectorAll('.edit-quick-chip');

  const lightbox = document.getElementById('image-lightbox');
  const lightboxImage = document.getElementById('lightbox-image');
  const lightboxClose = document.getElementById('lightbox-close');

  let sourceImageBase64 = null;
  let targetImageBase64 = null;
  let generateReferenceImages = [];
  let currentImageBase64 = null;
  let lastPrompt = '';
  let lastInputData = {};
  let loadingTicker = null;
  let loadingTickerIndex = 0;

  const intentConfig = {
    'from-scratch': {
      mode: 'generate',
      placeholder: 'Describe la imagen que quieres crear...',
      defaultDescription: '',
      promptHint: '',
      preset: {}
    },
    'edit-image': {
      mode: 'edit',
      placeholder: 'Describe los cambios que quieres hacer sobre la imagen base...',
      defaultDescription: '',
      promptHint: '',
      preset: {}
    },
    'corporate-image': {
      mode: 'generate',
      placeholder: 'Describe la escena corporativa (contexto, personas, mensaje)...',
      defaultDescription: 'Escena corporativa moderna, limpia y profesional para comunicacion de marca',
      promptHint: 'Busca composicion limpia con espacio util para copy.',
      preset: { style: 'corporate', composition: 'negative-space', lighting: 'soft', color: 'corporate', format: '16:9' }
    },
    'product-mockup': {
      mode: 'generate',
      placeholder: 'Describe el producto o mockup (materiales, entorno, enfoque)...',
      defaultDescription: 'Presentacion de producto premium en entorno limpio',
      promptHint: 'Mantener detalle, textura y acabado comercial.',
      preset: { style: 'luxury-product', composition: 'macro', lighting: 'studio', color: '', format: '4:3' }
    },
    'poster-logos': {
      mode: 'generate',
      placeholder: 'Describe el cartel y como integrar los logos subidos...',
      defaultDescription: 'Cartel corporativo moderno integrando los logos de referencia',
      promptHint: 'Sube logos e indica jerarquia, ubicacion y equilibrio visual.',
      preset: { style: 'corporate', composition: 'wide', lighting: 'soft', color: 'corporate', format: '4:3' }
    }
  };

  const styleMap = {
    '': '',
    'photographic': 'Hyper-realistic professional photography, shot on Sony A7R IV with 35mm G Master lens, 8k resolution, extreme detail.',
    'digital-art': 'High-end digital art illustration, intricate details, vibrant colors, clean vector lines, premium finish.',
    'corporate': 'Professional modern corporate aesthetic, clean, credible and brand-safe composition.',
    'minimalist': 'Minimalist fine art style, clean lines, simple geometry and balanced negative space.',
    '3d-render': 'Ultra-realistic 3D render with ray-traced lighting and physically accurate materials.',
    'flat-design': 'Premium flat design illustration with clear hierarchy and polished geometry.',
    'isometric': 'Isometric 3D composition with professional visual structure.',
    'luxury-product': 'Luxury product photography for commercial advertising with studio setup.'
  };

  const colorMap = {
    '': '',
    'warm': 'Warm cinematic color palette with amber highlights and gentle contrast.',
    'cool': 'Cool professional palette with balanced cyan-blue tones.',
    'corporate': 'Corporate blue-teal palette aligned with brand communication.',
    'monochrome': 'Monochrome palette with rich tonal separation.',
    'pastel': 'Muted pastel tones with soft transitions.',
    'bw': 'Black and white treatment with strong tonal dynamic range.',
    'vibrant': 'Vibrant yet controlled saturation for commercial impact.'
  };

  const lightingMap = {
    '': '',
    'natural': 'Natural soft daylight with realistic shadows.',
    'studio': 'Studio lighting setup with clear key and fill balance.',
    'dramatic': 'Dramatic cinematic lighting with controlled contrast.',
    'soft': 'Soft diffused lighting and clean skin/material rendering.',
    'backlight': 'Subtle rim and backlight for depth and separation.',
    'golden': 'Golden hour warmth with gentle directional highlights.',
    'volumetric': 'Volumetric lighting with atmospheric depth.'
  };

  const compositionMap = {
    '': '',
    'bokeh': 'Shallow depth of field with elegant bokeh separation.',
    'closeup': 'Close-up framing emphasizing subject detail.',
    'wide': 'Wide composition with environmental context.',
    'above': 'Top-down perspective with clear geometry.',
    'below': 'Low-angle perspective for visual impact.',
    'macro': 'Macro-level detail capture with texture emphasis.',
    'negative-space': 'Composition with intentional negative space for overlays or copy.'
  };

  const formatMap = {
    '1:1': 'Square format (1:1).',
    '3:4': 'Portrait format (3:4).',
    '4:3': 'Landscape format (4:3).',
    '16:9': 'Widescreen format (16:9).',
    '9:16': 'Vertical format (9:16).'
  };

  function getCurrentIntent() {
    return currentIntentInput?.value || 'from-scratch';
  }

  function setIntent(intentKey, hydratePrompt) {
    const cfg = intentConfig[intentKey] || intentConfig['from-scratch'];
    if (currentIntentInput) currentIntentInput.value = intentKey;

    intentCards.forEach(card => {
      card.classList.toggle('active', card.dataset.intent === intentKey);
    });

    setMode(cfg.mode, true);
    applyPreset(cfg.preset || {});

    if (descriptionField) {
      descriptionField.placeholder = cfg.placeholder || descriptionField.placeholder;
      if (hydratePrompt && !descriptionField.value.trim() && cfg.defaultDescription) {
        descriptionField.value = cfg.defaultDescription;
      }
    }

    if (intentKey !== 'poster-logos' && generateReferenceImages.length > 0 && cfg.mode !== 'generate') {
      generateReferenceImages = [];
      renderGenerateReferences();
    }

    updateSummary();
  }

  function applyPreset(preset) {
    const fields = ['format', 'style', 'color', 'lighting', 'composition'];
    fields.forEach(field => {
      const value = preset[field] || '';
      const selector = `input[name="${field}"][value="${value}"]`;
      const radio = document.querySelector(selector);
      if (radio) {
        radio.checked = true;
      }
    });
  }

  function setMode(mode, fromIntent) {
    if (!currentModeInput) return;
    currentModeInput.value = mode;
    clearError();

    const isGenerate = mode === 'generate';
    if (!fromIntent && isGenerate && getCurrentIntent() === 'edit-image') {
      setIntent('from-scratch', false);
      return;
    }
    if (!fromIntent && !isGenerate && getCurrentIntent() !== 'edit-image') {
      setIntent('edit-image', false);
      return;
    }

    if (descriptionField && !fromIntent) {
      descriptionField.placeholder = isGenerate
        ? 'Describe la imagen que quieres crear...'
        : 'Describe los cambios: "Añade gafas de sol", "Cambia el fondo"...';
    }

    if (generateBtn) {
      generateBtn.innerHTML = isGenerate
        ? '<i class="iconoir-sparks"></i><span class="hidden sm:inline">Generar</span>'
        : '<i class="iconoir-edit"></i><span class="hidden sm:inline">Editar</span>';
    }

    generateReferencesSection?.classList.toggle('hidden', !isGenerate);
    editQuickActions?.classList.toggle('hidden', isGenerate);

    if (isGenerate) {
      imagePlaceholder?.classList.toggle('hidden', !!currentImageBase64);
      editSourceSection?.classList.add('hidden');
      if (!currentImageBase64) imageResult?.classList.add('hidden');
    } else {
      imagePlaceholder?.classList.add('hidden');
      editSourceSection?.classList.remove('hidden');
      imageResult?.classList.add('hidden');
    }

    updateSummary();
  }

  function setupSourceImageUpload() {
    if (!sourceImageInput) return;
    sourceImageInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;
      readImageAsBase64(file).then(result => {
        sourceImageBase64 = result.base64;
        if (sourceImagePreview) {
          sourceImagePreview.src = result.dataUrl;
          sourceImagePreview.classList.remove('hidden');
        }
        sourceImagePlaceholder?.classList.add('hidden');
        sourceImageClear?.classList.remove('hidden');
        clearError();
      }).catch(() => {
        showError('Selecciona una imagen valida para usar como base.');
      });
    });

    sourceImageClear?.addEventListener('click', (e) => {
      e.stopPropagation();
      clearSourceImage();
    });
  }

  function clearSourceImage() {
    sourceImageBase64 = null;
    if (sourceImageInput) sourceImageInput.value = '';
    if (sourceImagePreview) {
      sourceImagePreview.src = '';
      sourceImagePreview.classList.add('hidden');
    }
    sourceImagePlaceholder?.classList.remove('hidden');
    sourceImageClear?.classList.add('hidden');
    updateSummary();
  }

  function setupTargetImageUpload() {
    if (!targetImageInput) return;
    targetImageInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;
      readImageAsBase64(file).then(result => {
        targetImageBase64 = result.base64;
        if (targetImagePreview) {
          targetImagePreview.src = result.dataUrl;
          targetImagePreview.classList.remove('hidden');
        }
        targetImagePlaceholder?.classList.add('hidden');
        targetImageClear?.classList.remove('hidden');
      }).catch(() => {
        showError('Selecciona una imagen de referencia valida.');
      });
    });

    targetImageClear?.addEventListener('click', (e) => {
      e.stopPropagation();
      clearTargetImage();
    });
  }

  function clearTargetImage() {
    targetImageBase64 = null;
    if (targetImageInput) targetImageInput.value = '';
    if (targetImagePreview) {
      targetImagePreview.src = '';
      targetImagePreview.classList.add('hidden');
    }
    targetImagePlaceholder?.classList.remove('hidden');
    targetImageClear?.classList.add('hidden');
    updateSummary();
  }

  function setupGenerateReferences() {
    addGenerateReferenceBtn?.addEventListener('click', () => {
      generateReferenceInput?.click();
    });

    generateReferenceInput?.addEventListener('change', async (e) => {
      const files = Array.from(e.target.files || []);
      if (files.length === 0) return;

      const availableSlots = MAX_GENERATE_REFERENCES - generateReferenceImages.length;
      if (availableSlots <= 0) {
        showError('Ya tienes 4 referencias. Quita una para subir otra.');
        return;
      }

      const accepted = files.slice(0, availableSlots);
      for (const file of accepted) {
        try {
          const result = await readImageAsBase64(file);
          generateReferenceImages.push({
            id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
            base64: result.base64,
            dataUrl: result.dataUrl,
            name: file.name || 'referencia'
          });
        } catch (_e) {
          showError('Una referencia no es valida. Usa archivos de imagen.');
        }
      }
      if (generateReferenceInput) generateReferenceInput.value = '';
      renderGenerateReferences();
      clearError();
      updateSummary();
    });
  }

  function renderGenerateReferences() {
    if (!generateReferenceList) return;
    if (generateReferenceImages.length === 0) {
      generateReferenceList.innerHTML = '<div class="col-span-full text-xs text-slate-400">Sin referencias cargadas.</div>';
      return;
    }

    generateReferenceList.innerHTML = generateReferenceImages.map((item, idx) => `
      <div class="relative border border-slate-200 rounded-lg overflow-hidden bg-slate-50">
        <img src="${item.dataUrl}" alt="Referencia ${idx + 1}" class="w-full h-20 object-cover" />
        <button type="button" class="remove-generate-reference absolute top-1 right-1 bg-black/70 text-white rounded-full w-5 h-5 flex items-center justify-center" data-id="${item.id}">
          <i class="iconoir-xmark text-[10px]"></i>
        </button>
        <div class="px-1.5 py-1 text-[10px] text-slate-500 truncate">${escapeHtml(item.name)}</div>
      </div>
    `).join('');

    generateReferenceList.querySelectorAll('.remove-generate-reference').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        generateReferenceImages = generateReferenceImages.filter(item => item.id !== id);
        renderGenerateReferences();
        updateSummary();
      });
    });
  }

  function setupQuickEditChips() {
    editQuickChips.forEach(chip => {
      chip.addEventListener('click', () => {
        const text = chip.dataset.text || '';
        if (!descriptionField) return;
        const current = descriptionField.value.trim();
        descriptionField.value = current ? `${current}. ${text}` : text;
        descriptionField.focus();
      });
    });
  }

  function readImageAsBase64(file) {
    return new Promise((resolve, reject) => {
      if (!file.type.startsWith('image/')) {
        reject(new Error('invalid_type'));
        return;
      }
      const reader = new FileReader();
      reader.onload = (ev) => {
        const dataUrl = String(ev.target.result || '');
        const parts = dataUrl.split(',');
        if (!parts[1]) {
          reject(new Error('invalid_image'));
          return;
        }
        resolve({ dataUrl, base64: parts[1] });
      };
      reader.onerror = () => reject(new Error('read_error'));
      reader.readAsDataURL(file);
    });
  }

  function updateSummary() {
    if (!summaryText) return;
    const mode = currentModeInput?.value || 'generate';
    const intent = getCurrentIntent();
    const intentLabel = intentCards.length > 0
      ? (Array.from(intentCards).find(card => card.dataset.intent === intent)?.querySelector('.text-xs')?.textContent || 'Crear desde cero')
      : 'Crear desde cero';

    const format = document.querySelector('input[name="format"]:checked')?.value || '';
    const style = document.querySelector('input[name="style"]:checked')?.value || '';
    const color = document.querySelector('input[name="color"]:checked')?.value || '';
    const lighting = document.querySelector('input[name="lighting"]:checked')?.value || '';
    const composition = document.querySelector('input[name="composition"]:checked')?.value || '';

    const parts = [mode === 'generate' ? `Modo generar` : `Modo editar`, intentLabel];

    if (format) parts.push(`formato ${format}`);
    if (style) parts.push(`estilo ${style}`);
    if (lighting) parts.push(`luz ${lighting}`);
    if (color) parts.push(`color ${color}`);
    if (composition) parts.push(`composicion ${composition}`);

    if (mode === 'generate' && generateReferenceImages.length > 0) {
      parts.push(`${generateReferenceImages.length} referencia${generateReferenceImages.length > 1 ? 's' : ''}`);
    }
    if (mode === 'edit' && sourceImageBase64) {
      parts.push(targetImageBase64 ? 'con referencia objetivo' : 'con imagen base');
    }

    summaryText.textContent = parts.join(' · ');
  }

  function buildPrompt(description, options) {
    const intent = getCurrentIntent();
    const cfg = intentConfig[intent] || intentConfig['from-scratch'];
    const specs = [];

    if (formatMap[options.format]) specs.push(formatMap[options.format]);
    if (options.style && styleMap[options.style]) specs.push(styleMap[options.style]);
    if (options.color && colorMap[options.color]) specs.push(colorMap[options.color]);
    if (options.lighting && lightingMap[options.lighting]) specs.push(lightingMap[options.lighting]);
    if (options.composition && compositionMap[options.composition]) specs.push(compositionMap[options.composition]);

    let prompt = `Create a high-quality image based on this request in Spanish context: ${description}.`;
    if (cfg.promptHint) {
      prompt += `\nCreative direction: ${cfg.promptHint}`;
    }
    if (generateReferenceImages.length > 0) {
      prompt += `\nUse the reference images as visual guidance. Integrate logos/elements naturally when requested and preserve readability/proportions.`;
    }
    if (specs.length > 0) {
      prompt += '\nTechnical and style constraints:\n- ' + specs.join('\n- ');
    }
    prompt += '\nOutput: one coherent, realistic image with clean composition and strong visual clarity.';
    return prompt;
  }

  function buildEditPrompt(description) {
    const userRequest = (description || '').trim();
    let prompt = 'Apply a precise, localized edit to the source image.\n';
    prompt += `Primary request: ${userRequest}\n\n`;
    prompt += 'Hard constraints:\n';
    prompt += '- Preserve identity, pose, framing and perspective unless explicitly requested otherwise.\n';
    prompt += '- Keep existing logos/text unless replacement is explicitly requested.\n';
    prompt += '- Maintain texture, realistic lighting and material consistency.\n';
    prompt += '- Return exactly one edited image.';
    return prompt;
  }

  function getCurrentOptions() {
    return {
      format: document.querySelector('input[name="format"]:checked')?.value || '',
      style: document.querySelector('input[name="style"]:checked')?.value || '',
      color: document.querySelector('input[name="color"]:checked')?.value || '',
      lighting: document.querySelector('input[name="lighting"]:checked')?.value || '',
      composition: document.querySelector('input[name="composition"]:checked')?.value || ''
    };
  }

  function showError(message) {
    if (!imageError) return;
    imageError.textContent = message;
    imageError.classList.remove('hidden');
  }

  function clearError() {
    if (!imageError) return;
    imageError.textContent = '';
    imageError.classList.add('hidden');
  }

  function startLoadingTicker() {
    const steps = [
      'Analizando instrucciones',
      'Ajustando composicion',
      'Refinando color e iluminacion',
      'Preparando resultado final'
    ];
    loadingTickerIndex = 0;
    if (loadingDetail) loadingDetail.textContent = steps[0];
    loadingTicker = window.setInterval(() => {
      loadingTickerIndex = (loadingTickerIndex + 1) % steps.length;
      if (loadingDetail) loadingDetail.textContent = steps[loadingTickerIndex];
    }, 1800);
  }

  function stopLoadingTicker() {
    if (loadingTicker) {
      clearInterval(loadingTicker);
      loadingTicker = null;
    }
  }

  async function generateImage() {
    clearError();
    const mode = currentModeInput?.value || 'generate';
    const description = (descriptionField?.value || '').trim();

    if (!description) {
      showError(mode === 'generate'
        ? 'Describe la imagen que quieres crear.'
        : 'Describe los cambios que quieres aplicar en la imagen base.');
      descriptionField?.focus();
      return;
    }
    if (mode === 'edit' && !sourceImageBase64) {
      showError('Sube una imagen base para usar el modo edicion.');
      return;
    }

    const options = getCurrentOptions();
    const intent = getCurrentIntent();
    let prompt = '';
    let inputData = {};

    if (mode === 'generate') {
      prompt = buildPrompt(description, options);
      inputData = {
        mode: 'generate',
        intent,
        description,
        provider: currentProviderInput?.value || 'nanobanana',
        reference_images: generateReferenceImages.map(item => item.base64),
        ...options
      };
    } else {
      prompt = buildEditPrompt(description);
      inputData = {
        mode: 'edit',
        intent,
        description,
        provider: currentProviderInput?.value || 'nanobanana',
        source_image: sourceImageBase64,
        target_image: targetImageBase64 || null
      };
    }

    lastPrompt = prompt;
    lastInputData = inputData;
    await sendRequest(prompt, inputData);
  }

  async function sendRequest(prompt, inputData) {
    imagePlaceholder?.classList.add('hidden');
    editSourceSection?.classList.add('hidden');
    imageResult?.classList.add('hidden');
    imageLoading?.classList.remove('hidden');

    if (generateBtn) generateBtn.disabled = true;
    if (loadingTitle) loadingTitle.textContent = inputData.mode === 'edit' ? 'Editando imagen...' : 'Generando imagen...';
    if (loadingMeta) {
      const refs = Array.isArray(inputData.reference_images) ? inputData.reference_images.length : 0;
      loadingMeta.textContent = refs > 0 ? `Referencias activas: ${refs}` : '';
    }
    startLoadingTicker();

    try {
      const res = await fetch('/api/gestures/generate-image.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
        credentials: 'include',
        body: JSON.stringify({ gesture_type: GESTURE_TYPE, prompt, input_data: inputData })
      });
      const data = await res.json().catch(() => ({}));

      stopLoadingTicker();
      imageLoading?.classList.add('hidden');
      if (generateBtn) generateBtn.disabled = false;

      if (!res.ok || !data.image) {
        showError(data.error?.message || 'No hemos podido generar la imagen. Prueba ajustando el prompt.');
        if (currentModeInput?.value === 'edit') {
          editSourceSection?.classList.remove('hidden');
        } else {
          imagePlaceholder?.classList.remove('hidden');
        }
        return;
      }

      currentImageBase64 = data.image;
      if (generatedImage) generatedImage.src = `data:image/png;base64,${data.image}`;
      imageResult?.classList.remove('hidden');

      if (data.text && imageCaption) {
        imageCaption.textContent = data.text;
        imageCaption.classList.remove('hidden');
      } else {
        imageCaption?.classList.add('hidden');
      }

      clearError();
      await loadHistory();
    } catch (_err) {
      stopLoadingTicker();
      imageLoading?.classList.add('hidden');
      if (generateBtn) generateBtn.disabled = false;
      showError('Error de conexion al generar la imagen.');
      if (currentModeInput?.value === 'edit') {
        editSourceSection?.classList.remove('hidden');
      } else {
        imagePlaceholder?.classList.remove('hidden');
      }
    }
  }

  function useCurrentImageAsEditBase() {
    if (!currentImageBase64) return;
    sourceImageBase64 = currentImageBase64;
    if (sourceImagePreview) {
      sourceImagePreview.src = `data:image/png;base64,${currentImageBase64}`;
      sourceImagePreview.classList.remove('hidden');
    }
    sourceImagePlaceholder?.classList.add('hidden');
    sourceImageClear?.classList.remove('hidden');
    setIntent('edit-image', false);
    if (descriptionField) {
      descriptionField.value = '';
      descriptionField.focus();
    }
  }

  function openLightbox() {
    if (!currentImageBase64 || !lightboxImage || !lightbox) return;
    lightboxImage.src = `data:image/png;base64,${currentImageBase64}`;
    lightbox.classList.remove('hidden');
    lightbox.classList.add('flex');
  }

  function closeLightbox() {
    lightbox?.classList.add('hidden');
    lightbox?.classList.remove('flex');
  }

  async function loadHistory() {
    if (!historyList) return;
    try {
      const res = await fetch(`/api/gestures/history.php?type=${GESTURE_TYPE}&limit=12`, { credentials: 'include' });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        historyList.innerHTML = '<div class="p-4 text-center text-red-500 text-sm">Error al cargar historial</div>';
        return;
      }
      renderHistory(data.items || []);
    } catch (_err) {
      historyList.innerHTML = '<div class="p-4 text-center text-red-500 text-sm">Error de conexion</div>';
    }
  }

  function renderHistory(items) {
    if (!historyList) return;
    if (items.length === 0) {
      historyList.innerHTML = `
        <div class="p-6 text-center">
          <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
            <i class="iconoir-media-image text-xl text-slate-400"></i>
          </div>
          <p class="text-sm text-slate-500">Aun no has generado imagenes</p>
          <p class="text-xs text-slate-400 mt-1">Empieza con una intencion arriba</p>
        </div>
      `;
      return;
    }

    historyList.innerHTML = items.map(item => {
      const title = escapeHtml(item.title || 'Imagen generada');
      const timeAgo = formatTimeAgo(new Date(item.created_at));
      const mode = item.mode === 'edit' ? 'Editar' : 'Generar';
      return `
        <div class="history-item w-full p-3 hover:bg-slate-50 border-b border-slate-100 transition-colors group flex items-start gap-2" data-id="${item.id}">
          <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center shrink-0 mt-0.5">
            <i class="iconoir-media-image text-slate-400"></i>
          </div>
          <div class="flex-1 min-w-0 cursor-pointer history-item-main">
            <p class="text-sm font-medium text-slate-700 truncate group-hover:text-amber-700">${title}</p>
            <div class="flex items-center gap-2 mt-1">
              <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">${mode}</span>
              <span class="text-[10px] text-slate-400">${timeAgo}</span>
            </div>
          </div>
          <button class="history-item-delete opacity-0 group-hover:opacity-100 transition-opacity text-slate-300 hover:text-red-500 p-1 rounded" data-id="${item.id}" title="Eliminar">
            <i class="iconoir-trash"></i>
          </button>
        </div>
      `;
    }).join('');

    historyList.querySelectorAll('.history-item-main').forEach(el => {
      const id = el.parentElement.dataset.id;
      el.addEventListener('click', () => loadExecution(id));
    });
    historyList.querySelectorAll('.history-item-delete').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        deleteExecution(btn.dataset.id);
      });
    });
  }

  async function loadExecution(id) {
    try {
      const res = await fetch(`/api/gestures/get.php?id=${id}`, { credentials: 'include' });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.execution) {
        showError('No se ha podido cargar esta imagen.');
        return;
      }

      const exec = data.execution;
      const outputData = exec.output_data || {};
      const inputData = exec.input_data || {};

      if (outputData.image) {
        currentImageBase64 = outputData.image;
        if (generatedImage) generatedImage.src = `data:image/png;base64,${outputData.image}`;
        imagePlaceholder?.classList.add('hidden');
        editSourceSection?.classList.add('hidden');
        imageResult?.classList.remove('hidden');
      }

      if (outputData.text && imageCaption) {
        imageCaption.textContent = outputData.text;
        imageCaption.classList.remove('hidden');
      } else {
        imageCaption?.classList.add('hidden');
      }

      if (descriptionField) descriptionField.value = inputData.description || '';

      ['format', 'style', 'color', 'lighting', 'composition'].forEach(field => {
        const value = inputData[field] ?? '';
        const radio = document.querySelector(`input[name="${field}"][value="${value}"]`);
        if (radio) radio.checked = true;
      });

      if (Array.isArray(inputData.reference_images)) {
        generateReferenceImages = inputData.reference_images.slice(0, MAX_GENERATE_REFERENCES).map((base64, idx) => ({
          id: `loaded-${id}-${idx}`,
          base64,
          dataUrl: `data:image/png;base64,${base64}`,
          name: `ref-${idx + 1}`
        }));
      } else {
        generateReferenceImages = [];
      }
      renderGenerateReferences();

      const mode = inputData.mode === 'edit' ? 'edit' : 'generate';
      const intent = inputData.intent && intentConfig[inputData.intent] ? inputData.intent : (mode === 'edit' ? 'edit-image' : 'from-scratch');
      setIntent(intent, false);
      setMode(mode, true);

      updateSummary();
      clearError();
      lastInputData = inputData;
    } catch (_err) {
      showError('Error de conexion al cargar la imagen.');
    }
  }

  async function deleteExecution(id) {
    if (!confirm('Eliminar esta imagen del historial?')) return;
    try {
      const res = await fetch('/api/gestures/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
        credentials: 'include',
        body: JSON.stringify({ id: Number(id) })
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) {
        showError('No se ha podido eliminar la imagen.');
        return;
      }
      await loadHistory();
    } catch (_err) {
      showError('Error de conexion al eliminar.');
    }
  }

  function resetUI() {
    currentImageBase64 = null;
    sourceImageBase64 = null;
    targetImageBase64 = null;
    generateReferenceImages = [];
    lastPrompt = '';
    lastInputData = {};

    if (descriptionField) descriptionField.value = '';
    clearSourceImage();
    clearTargetImage();
    renderGenerateReferences();

    ['format', 'style', 'color', 'lighting', 'composition'].forEach(field => {
      const defaultRadio = document.querySelector(`input[name="${field}"][value=""]`);
      if (defaultRadio) defaultRadio.checked = true;
    });

    setIntent('from-scratch', false);
    imageCaption?.classList.add('hidden');
    imageResult?.classList.add('hidden');
    imagePlaceholder?.classList.remove('hidden');
    clearError();
    descriptionField?.focus();
  }

  function formatTimeAgo(date) {
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    if (diffMins < 1) return 'ahora';
    if (diffMins < 60) return `hace ${diffMins} min`;
    if (diffHours < 24) return `hace ${diffHours}h`;
    if (diffDays === 1) return 'ayer';
    if (diffDays < 7) return `hace ${diffDays} dias`;
    return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
  }

  generateBtn?.addEventListener('click', () => generateImage());
  regenerateBtn?.addEventListener('click', () => {
    if (lastPrompt && Object.keys(lastInputData).length > 0) {
      sendRequest(lastPrompt, lastInputData);
    }
  });
  editThisImageBtn?.addEventListener('click', useCurrentImageAsEditBase);
  downloadBtn?.addEventListener('click', () => {
    if (!currentImageBase64) return;
    const link = document.createElement('a');
    link.href = `data:image/png;base64,${currentImageBase64}`;
    link.download = `ebonia-image-${Date.now()}.png`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  });

  fullscreenBtn?.addEventListener('click', openLightbox);
  generatedImage?.addEventListener('click', openLightbox);
  lightboxClose?.addEventListener('click', closeLightbox);
  lightbox?.addEventListener('click', (e) => {
    if (e.target === lightbox) closeLightbox();
  });
  document.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
      e.preventDefault();
      generateImage();
    }
    if (e.key === 'Escape' && lightbox && !lightbox.classList.contains('hidden')) {
      closeLightbox();
    }
  });

  intentCards.forEach(card => {
    card.addEventListener('click', () => setIntent(card.dataset.intent || 'from-scratch', true));
  });
  newImageBtn?.addEventListener('click', resetUI);
  document.querySelectorAll('input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', updateSummary);
  });

  if (currentProviderInput) currentProviderInput.value = 'nanobanana';
  setupSourceImageUpload();
  setupTargetImageUpload();
  setupGenerateReferences();
  setupQuickEditChips();
  renderGenerateReferences();
  setIntent('from-scratch', false);
  updateSummary();
  loadHistory();
})();
