/**
 * Gesto: Editor de Imagenes con IA
 * Flujo: intencion -> generar/editar -> resultado -> iteracion
 */
(function() {
  'use strict';

  const GESTURE_TYPE = 'image-editor';
  const MAX_GENERATE_REFERENCES = 4;
  const imageI18n = window.CLAARA_IMAGE_I18N?.messages || {};
  function imageT(key, vars = {}) {
    let value = imageI18n[`image_ui.${key}`] || key;
    Object.entries(vars).forEach(([name, replacement]) => {
      value = value.replaceAll(`{${name}}`, String(replacement));
    });
    return value;
  }

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
  const generateReferenceCount = document.getElementById('generate-reference-count');
  const intentCards = document.querySelectorAll('.intent-card');
  const editQuickChips = document.querySelectorAll('.edit-quick-chip');

  const lightbox = document.getElementById('image-lightbox');
  const lightboxImage = document.getElementById('lightbox-image');
  const lightboxClose = document.getElementById('lightbox-close');

  let sourceImageBase64 = null;
  let targetImageBase64 = null;
  let generateReferenceImages = [];
  let currentImageBase64 = null;
  let currentImageSrc = null;
  let lastPrompt = '';
  let lastInputData = {};
  let loadingTicker = null;
  let loadingTickerIndex = 0;

  const intentConfig = {
    'from-scratch': {
      mode: 'generate',
      placeholder: imageT('description_placeholder'),
      defaultDescription: '',
      promptHint: '',
      preset: {}
    },
    'edit-image': {
      mode: 'edit',
      placeholder: imageT('edit_placeholder'),
      defaultDescription: '',
      promptHint: '',
      preset: {}
    },
    'corporate-image': {
      mode: 'generate',
      placeholder: imageT('corporate_placeholder'),
      defaultDescription: imageT('corporate_default'),
      promptHint: imageT('corporate_hint'),
      preset: { style: 'corporate', composition: 'negative-space', lighting: 'soft', color: 'corporate', format: '16:9' }
    },
    'product-mockup': {
      mode: 'generate',
      placeholder: imageT('product_placeholder'),
      defaultDescription: imageT('product_default'),
      promptHint: imageT('product_hint'),
      preset: { style: 'luxury-product', composition: 'macro', lighting: 'studio', color: '', format: '4:3' }
    },
    'poster-logos': {
      mode: 'generate',
      placeholder: imageT('poster_placeholder'),
      defaultDescription: imageT('poster_default'),
      promptHint: imageT('poster_hint'),
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

  const summaryOptionLabels = {
    style: {
      photographic: imageT('photographic'),
      'digital-art': imageT('digital_art'),
      corporate: imageT('corporate_style'),
      minimalist: imageT('minimalist'),
      '3d-render': imageT('render_3d'),
      'flat-design': imageT('flat_design'),
      isometric: imageT('isometric'),
      'luxury-product': imageT('luxury_product')
    },
    composition: {
      bokeh: imageT('bokeh'),
      closeup: imageT('close_up'),
      wide: imageT('wide_shot'),
      above: imageT('top_down'),
      below: imageT('low_angle'),
      macro: imageT('macro'),
      'negative-space': imageT('negative_space')
    },
    lighting: {
      natural: imageT('natural'),
      studio: imageT('studio'),
      dramatic: imageT('dramatic'),
      soft: imageT('soft'),
      golden: imageT('golden_hour'),
      backlight: imageT('backlight'),
      volumetric: imageT('volumetric')
    },
    color: {
      warm: imageT('warm'),
      cool: imageT('cool'),
      corporate: imageT('corporate_style'),
      monochrome: imageT('monochrome'),
      pastel: imageT('pastel'),
      bw: imageT('black_white'),
      vibrant: imageT('vibrant')
    }
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

    // Auto-abrir referencias cuando son claramente necesarias (cartel con logos)
    if (generateReferencesSection && cfg.mode === 'generate') {
      if (intentKey === 'poster-logos') {
        generateReferencesSection.open = true;
      } else if (generateReferenceImages.length === 0) {
        generateReferencesSection.open = false;
      }
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
        ? imageT('description_placeholder')
        : imageT('edit_placeholder');
    }

    if (generateBtn) {
      generateBtn.innerHTML = isGenerate
        ? `<i class="iconoir-sparks"></i><span class="hidden sm:inline">${escapeHtml(imageT('generate'))}</span>`
        : `<i class="iconoir-edit"></i><span class="hidden sm:inline">${escapeHtml(imageT('edit'))}</span>`;
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
        showError(imageT('invalid_base'));
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
        showError(imageT('invalid_target'));
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
    addGenerateReferenceBtn?.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (generateReferencesSection && !generateReferencesSection.open) {
        generateReferencesSection.open = true;
      }
      generateReferenceInput?.click();
    });

    generateReferenceInput?.addEventListener('change', async (e) => {
      const files = Array.from(e.target.files || []);
      if (files.length === 0) return;

      const availableSlots = MAX_GENERATE_REFERENCES - generateReferenceImages.length;
      if (availableSlots <= 0) {
        showError(imageT('reference_limit'));
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
            name: file.name || imageT('reference_name')
          });
        } catch (_e) {
          showError(imageT('invalid_reference'));
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

    // Actualizar contador en la etiqueta del summary
    if (generateReferenceCount) {
      const n = generateReferenceImages.length;
      generateReferenceCount.textContent = n === 0
        ? imageT('optional_max_four')
        : `(${n}/4)`;
    }

    if (generateReferenceImages.length === 0) {
      generateReferenceList.innerHTML = `<div class="col-span-full text-[11px] text-slate-400 pt-1">${escapeHtml(imageT('no_references'))}</div>`;
      return;
    }

    generateReferenceList.innerHTML = generateReferenceImages.map((item, idx) => `
      <div class="relative border border-slate-200 rounded-lg overflow-hidden bg-slate-50">
        <img src="${item.dataUrl}" alt="${escapeHtml(imageT('reference_alt', { count: idx + 1 }))}" class="w-full h-20 object-cover" />
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

  function extractImageFromText(text) {
    if (typeof text !== 'string' || text === '') return null;
    const dataUrlMatch = text.match(/data:image\/[a-zA-Z0-9.+-]+;base64,[A-Za-z0-9+/=]+/);
    if (dataUrlMatch) return dataUrlMatch[0];
    const urlMatch = text.match(/https?:\/\/\S+\.(?:png|jpg|jpeg|webp)(?:\?\S*)?/i);
    return urlMatch ? urlMatch[0] : null;
  }

  function normalizeImageSource(rawImage) {
    if (typeof rawImage !== 'string') return null;
    const trimmed = rawImage.trim();
    if (!trimmed) return null;

    if (/^data:image\//i.test(trimmed)) return trimmed;
    if (/^https?:\/\//i.test(trimmed)) return trimmed;

    const isLikelyBase64 = /^[A-Za-z0-9+/=\s]+$/.test(trimmed) && trimmed.length > 120;
    if (isLikelyBase64) {
      return `data:image/png;base64,${trimmed.replace(/\s+/g, '')}`;
    }
    return null;
  }

  function extractBase64FromSource(src) {
    if (typeof src !== 'string') return null;
    const match = src.match(/^data:image\/[a-zA-Z0-9.+-]+;base64,(.+)$/);
    return match ? match[1] : null;
  }

  function setCurrentImage(rawImage) {
    const normalizedSrc = normalizeImageSource(rawImage);
    if (!normalizedSrc) return false;

    currentImageSrc = normalizedSrc;
    currentImageBase64 = extractBase64FromSource(normalizedSrc);
    if (generatedImage) generatedImage.src = normalizedSrc;
    return true;
  }

  function updateSummary() {
    if (!summaryText) return;
    const mode = currentModeInput?.value || 'generate';

    const format = document.querySelector('input[name="format"]:checked')?.value || '';
    const style = document.querySelector('input[name="style"]:checked')?.value || '';
    const color = document.querySelector('input[name="color"]:checked')?.value || '';
    const lighting = document.querySelector('input[name="lighting"]:checked')?.value || '';
    const composition = document.querySelector('input[name="composition"]:checked')?.value || '';

    const parts = [];
    if (format) parts.push(format);
    if (style) parts.push(summaryOptionLabels.style[style] || style);
    if (composition) parts.push(summaryOptionLabels.composition[composition] || composition);
    if (lighting) parts.push(summaryOptionLabels.lighting[lighting] || lighting);
    if (color) parts.push(summaryOptionLabels.color[color] || color);

    if (mode === 'generate' && generateReferenceImages.length > 0) {
      parts.push(generateReferenceImages.length === 1
        ? imageT('references_summary_one')
        : imageT('references_summary_many', { count: generateReferenceImages.length }));
    }
    if (mode === 'edit' && sourceImageBase64) {
      parts.push(targetImageBase64 ? imageT('with_target') : imageT('base_loaded'));
    }

    summaryText.textContent = parts.length === 0
      ? imageT('automatic_settings')
      : parts.join(' · ');
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

    let prompt = `Create a high-quality image based on this request: ${description}.`;
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
      imageT('loading_analyzing'),
      imageT('loading_composition'),
      imageT('loading_color'),
      imageT('loading_final')
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
        ? imageT('describe_generate')
        : imageT('describe_edit'));
      descriptionField?.focus();
      return;
    }
    if (mode === 'edit' && !sourceImageBase64) {
      showError(imageT('upload_base'));
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
    if (loadingTitle) loadingTitle.textContent = inputData.mode === 'edit' ? imageT('editing') : imageT('generating');
    if (loadingMeta) {
      const refs = Array.isArray(inputData.reference_images) ? inputData.reference_images.length : 0;
      loadingMeta.textContent = refs > 0 ? imageT('active_references', { count: refs }) : '';
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
        showError(data.error?.message || imageT('generation_error'));
        if (currentModeInput?.value === 'edit') {
          editSourceSection?.classList.remove('hidden');
        } else {
          imagePlaceholder?.classList.remove('hidden');
        }
        return;
      }

      setCurrentImage(data.image);
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
      showError(imageT('generation_connection_error'));
      if (currentModeInput?.value === 'edit') {
        editSourceSection?.classList.remove('hidden');
      } else {
        imagePlaceholder?.classList.remove('hidden');
      }
    }
  }

  function useCurrentImageAsEditBase() {
    if (!currentImageBase64) {
      showError(imageT('unusable_base'));
      return;
    }
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
    if (!currentImageSrc || !lightboxImage || !lightbox) return;
    lightboxImage.src = currentImageSrc;
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
        historyList.innerHTML = `<div class="p-4 text-center text-red-500 text-sm">${escapeHtml(imageT('history_load_error'))}</div>`;
        return;
      }
      renderHistory(data.items || []);
    } catch (_err) {
      historyList.innerHTML = `<div class="p-4 text-center text-red-500 text-sm">${escapeHtml(imageT('connection_error'))}</div>`;
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
          <p class="text-sm text-slate-500">${escapeHtml(imageT('no_history'))}</p>
          <p class="text-xs text-slate-400 mt-1">${escapeHtml(imageT('no_history_help'))}</p>
        </div>
      `;
      return;
    }

    historyList.innerHTML = items.map(item => {
      const title = escapeHtml(item.title || imageT('generated_title'));
      const timeAgo = formatTimeAgo(new Date(item.created_at));
      const mode = item.mode === 'edit' ? imageT('mode_edit') : imageT('mode_generate');
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
          <button class="history-item-delete opacity-0 group-hover:opacity-100 transition-opacity text-slate-300 hover:text-red-500 p-1 rounded" data-id="${item.id}" title="${escapeHtml(imageT('delete'))}">
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
        showError(imageT('load_error'));
        return;
      }

      const exec = data.execution;
      const outputData = exec.output_data || {};
      const inputData = exec.input_data || {};

      const mode = inputData.mode === 'edit' ? 'edit' : 'generate';
      const intent = inputData.intent && intentConfig[inputData.intent] ? inputData.intent : (mode === 'edit' ? 'edit-image' : 'from-scratch');
      setIntent(intent, false);
      setMode(mode, true);

      const imageCandidate = outputData.image || outputData.image_url || extractImageFromText(outputData.text || exec.output_content || '');
      if (setCurrentImage(imageCandidate)) {
        imagePlaceholder?.classList.add('hidden');
        editSourceSection?.classList.add('hidden');
        imageResult?.classList.remove('hidden');
      } else {
        currentImageSrc = null;
        currentImageBase64 = null;
        imageResult?.classList.add('hidden');
        if (mode === 'edit') {
          editSourceSection?.classList.remove('hidden');
          imagePlaceholder?.classList.add('hidden');
        } else {
          imagePlaceholder?.classList.remove('hidden');
          editSourceSection?.classList.add('hidden');
        }
        showError(imageT('history_image_missing'));
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

      updateSummary();
      if (currentImageSrc) clearError();
      lastInputData = inputData;
    } catch (_err) {
      showError(imageT('connection_error'));
    }
  }

  async function deleteExecution(id) {
    if (!confirm(imageT('delete_confirm'))) return;
    try {
      const res = await fetch('/api/gestures/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
        credentials: 'include',
        body: JSON.stringify({ id: Number(id) })
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) {
        showError(imageT('delete_error'));
        return;
      }
      await loadHistory();
    } catch (_err) {
      showError(imageT('connection_error'));
    }
  }

  function resetUI() {
    currentImageSrc = null;
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
    if (diffMins < 1) return imageT('just_now');
    if (diffMins < 60) return imageT('minutes_ago', { count: diffMins });
    if (diffHours < 24) return imageT('hours_ago', { count: diffHours });
    if (diffDays === 1) return imageT('yesterday');
    if (diffDays < 7) return imageT('days_ago', { count: diffDays });
    return date.toLocaleDateString(document.documentElement.lang || 'en', { day: 'numeric', month: 'short' });
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
    if (!currentImageSrc) return;
    const link = document.createElement('a');
    link.href = currentImageSrc;
    const extension = currentImageSrc.startsWith('data:image/jpeg') ? 'jpg' : (currentImageSrc.startsWith('data:image/webp') ? 'webp' : 'png');
    link.download = `claara-image-${Date.now()}.${extension}`;
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
