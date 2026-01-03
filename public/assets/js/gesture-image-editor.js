/**
 * Studio Mode - Editor de Imágenes Revolucionario
 * Canvas central + Filmstrip historial + Controles flotantes
 */
(function() {
  'use strict';

  const GESTURE_TYPE = 'image-editor';

  // === DOM References ===
  const promptInput = document.getElementById('prompt-input');
  const generateBtn = document.getElementById('generate-btn');
  const optionsToggle = document.getElementById('options-toggle');
  const optionsPanel = document.getElementById('options-panel');
  
  // Canvas states
  const canvasEmpty = document.getElementById('canvas-empty');
  const canvasLoading = document.getElementById('canvas-loading');
  const canvasResult = document.getElementById('canvas-result');
  const resultImage = document.getElementById('result-image');
  
  // Result actions
  const actionEdit = document.getElementById('action-edit');
  const actionRegenerate = document.getElementById('action-regenerate');
  const actionDownload = document.getElementById('action-download');
  
  // Mode & Provider
  const modeGenerate = document.getElementById('mode-generate');
  const modeEdit = document.getElementById('mode-edit');
  const providerQwen = document.getElementById('provider-qwen');
  const providerNanobanana = document.getElementById('provider-nanobanana');
  const providerFlux = document.getElementById('provider-flux');
  
  // Edit overlay
  const editOverlay = document.getElementById('edit-overlay');
  const editClose = document.getElementById('edit-close');
  const sourceInput = document.getElementById('source-input');
  const targetInput = document.getElementById('target-input');
  const sourceUploadTrigger = document.getElementById('source-upload-trigger');
  const targetUploadTrigger = document.getElementById('target-upload-trigger');
  const sourcePreview = document.getElementById('source-preview');
  const targetPreview = document.getElementById('target-preview');
  
  // Filmstrip
  const filmstripScroll = document.getElementById('filmstrip-scroll');
  const filmstripEmpty = document.getElementById('filmstrip-empty');
  
  // Lightbox
  const lightbox = document.getElementById('lightbox');
  const lightboxImage = document.getElementById('lightbox-image');
  const lightboxClose = document.getElementById('lightbox-close');
  
  // === State ===
  let currentMode = 'generate';
  let currentProvider = 'qwen';
  let currentOptions = {
    format: '1:1',
    style: '',
    color: '',
    lighting: ''
  };
  let sourceImageBase64 = null;
  let targetImageBase64 = null;
  let currentImageBase64 = null;
  let lastPrompt = '';
  let lastInputData = {};

  // === Prompt Maps ===
  const styleMap = {
    '': '',
    'photographic': 'Hyper-realistic professional photography, shot on Sony A7R IV, 8k resolution, extreme detail',
    'corporate': 'Professional Silicon Valley corporate style, modern business aesthetic, clean and crisp',
    'headshot-pro': 'Professional studio headshot, 85mm f/1.8 lens, shallow depth of field, sharp focus on eyes',
    '3d-render': 'Ultra-realistic 3D octane render, Ray tracing, 8k, Unreal Engine 5 style',
    'minimalist': 'Minimalist fine art, simple geometric balance, clean lines, vast negative space'
  };

  const colorMap = {
    '': '',
    'warm': 'Warm cinematic color grading, golden hour hues, soft oranges and ambers',
    'cool': 'Cool professional color palette, crisp teals and blues, modern aesthetic',
    'bw': 'Classic black and white film photography, high dynamic range, deep blacks',
    'vibrant': 'Vibrant and rich color saturation, bold commercial appeal'
  };

  const lightingMap = {
    '': '',
    'natural': 'Natural diffused daylight, soft window light',
    'studio': 'Classic three-point studio lighting, professional key and fill lights',
    'dramatic': 'Cinematic dramatic lighting, high contrast, chiaroscuro effect',
    'golden': 'Golden hour sunset lighting, warm glow, long soft shadows'
  };

  const formatMap = {
    '1:1': 'Square aspect ratio (1:1)',
    '3:4': 'Portrait aspect ratio (3:4)',
    '4:3': 'Landscape aspect ratio (4:3)',
    '16:9': 'Widescreen cinematic format (16:9)',
    '9:16': 'Vertical social media format (9:16)'
  };

  // === Options Panel ===
  optionsToggle?.addEventListener('click', () => {
    optionsPanel?.classList.toggle('visible');
  });

  // Close options when clicking outside
  document.addEventListener('click', (e) => {
    if (!optionsPanel?.contains(e.target) && !optionsToggle?.contains(e.target)) {
      optionsPanel?.classList.remove('visible');
    }
  });

  // Option chips
  document.querySelectorAll('.option-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      const option = chip.dataset.option;
      const value = chip.dataset.value;
      
      // Remove active from siblings
      document.querySelectorAll(`.option-chip[data-option="${option}"]`).forEach(c => {
        c.classList.remove('active');
      });
      
      // Add active to clicked
      chip.classList.add('active');
      
      // Update state
      currentOptions[option] = value;
    });
  });

  // === Mode Toggle ===
  function setMode(mode) {
    currentMode = mode;
    
    document.querySelectorAll('.mode-btn').forEach(btn => btn.classList.remove('active'));
    
    if (mode === 'generate') {
      modeGenerate?.classList.add('active');
      editOverlay?.classList.remove('visible');
      if (promptInput) promptInput.placeholder = 'Describe la imagen que quieres crear...';
    } else {
      modeEdit?.classList.add('active');
      editOverlay?.classList.add('visible');
      if (promptInput) promptInput.placeholder = 'Describe los cambios que quieres hacer...';
    }
  }

  modeGenerate?.addEventListener('click', () => setMode('generate'));
  modeEdit?.addEventListener('click', () => setMode('edit'));

  // === Provider Toggle ===
  function setProvider(provider) {
    currentProvider = provider;
    document.querySelectorAll('.provider-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(`provider-${provider}`)?.classList.add('active');
  }

  providerQwen?.addEventListener('click', () => setProvider('qwen'));
  providerNanobanana?.addEventListener('click', () => setProvider('nanobanana'));
  providerFlux?.addEventListener('click', () => setProvider('flux'));

  // === Edit Mode Image Uploads ===
  sourceUploadTrigger?.addEventListener('click', () => sourceInput?.click());
  targetUploadTrigger?.addEventListener('click', () => targetInput?.click());

  sourceInput?.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = (ev) => {
      sourceImageBase64 = ev.target.result.split(',')[1];
      if (sourcePreview) {
        sourcePreview.src = ev.target.result;
        sourcePreview.style.display = 'block';
        sourceUploadTrigger.querySelector('i')?.remove();
      }
    };
    reader.readAsDataURL(file);
  });

  targetInput?.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = (ev) => {
      targetImageBase64 = ev.target.result.split(',')[1];
      if (targetPreview) {
        targetPreview.src = ev.target.result;
        targetPreview.style.display = 'block';
        targetUploadTrigger.querySelector('i')?.remove();
      }
    };
    reader.readAsDataURL(file);
  });

  editClose?.addEventListener('click', () => {
    setMode('generate');
  });

  // === Canvas State Management ===
  function showCanvas(state) {
    canvasEmpty.style.display = 'none';
    canvasLoading.style.display = 'none';
    canvasResult.style.display = 'none';
    
    if (state === 'empty') canvasEmpty.style.display = 'block';
    else if (state === 'loading') canvasLoading.style.display = 'block';
    else if (state === 'result') canvasResult.style.display = 'block';
  }

  // === Build Prompt ===
  function buildPrompt(description) {
    let prompt = `Create an ultra-realistic, high-resolution masterpiece image: ${description}`;
    
    const specs = [];
    if (formatMap[currentOptions.format]) specs.push(formatMap[currentOptions.format]);
    if (currentOptions.style && styleMap[currentOptions.style]) specs.push(styleMap[currentOptions.style]);
    if (currentOptions.color && colorMap[currentOptions.color]) specs.push(colorMap[currentOptions.color]);
    if (currentOptions.lighting && lightingMap[currentOptions.lighting]) specs.push(lightingMap[currentOptions.lighting]);

    if (specs.length > 0) {
      prompt += '\n\nTechnical Specifications:\n- ' + specs.join('\n- ');
    }

    prompt += '\n\nFinal Quality: 8k resolution, photorealistic, cinematic color grading, sharp focus, HDR.';
    prompt += `\n\nSeed: ${Math.floor(Math.random() * 1000000)}`;

    return prompt;
  }

  // === Generate Image ===
  async function generateImage() {
    const description = promptInput?.value.trim();
    
    if (!description) {
      promptInput?.focus();
      return;
    }

    if (currentMode === 'edit' && !sourceImageBase64) {
      alert('Sube una imagen para editar');
      return;
    }

    let prompt, inputData;

    if (currentMode === 'generate') {
      prompt = buildPrompt(description);
      inputData = {
        mode: 'generate',
        description,
        provider: currentProvider,
        ...currentOptions
      };
    } else {
      prompt = description;
      inputData = {
        mode: 'edit',
        description,
        provider: currentProvider,
        source_image: sourceImageBase64,
        target_image: targetImageBase64 || null
      };
    }

    lastPrompt = prompt;
    lastInputData = inputData;

    await sendRequest(prompt, inputData);
  }

  // === Send Request ===
  async function sendRequest(prompt, inputData) {
    showCanvas('loading');
    if (generateBtn) generateBtn.disabled = true;

    try {
      const res = await fetch('/api/gestures/generate-image.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
        body: JSON.stringify({ gesture_type: GESTURE_TYPE, prompt, input_data: inputData }),
        credentials: 'include'
      });

      const data = await res.json();
      if (generateBtn) generateBtn.disabled = false;

      if (!res.ok || !data.image) {
        showCanvas('empty');
        alert('Error: ' + (data.error?.message || 'No se pudo generar la imagen'));
        return;
      }

      currentImageBase64 = data.image;
      const imgSrc = `data:image/png;base64,${data.image}`;
      
      if (resultImage) resultImage.src = imgSrc;
      showCanvas('result');
      
      // Reload history
      loadHistory();

    } catch (err) {
      if (generateBtn) generateBtn.disabled = false;
      showCanvas('empty');
      console.error('Error:', err);
      alert('Error de conexión');
    }
  }

  // === Generate on Enter ===
  promptInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      generateImage();
    }
  });

  generateBtn?.addEventListener('click', generateImage);

  // === Result Actions ===
  actionEdit?.addEventListener('click', () => {
    if (!currentImageBase64) return;
    
    // Set result as source for editing
    sourceImageBase64 = currentImageBase64;
    if (sourcePreview) {
      sourcePreview.src = `data:image/png;base64,${currentImageBase64}`;
      sourcePreview.style.display = 'block';
      sourceUploadTrigger.querySelector('i')?.remove();
    }
    
    setMode('edit');
    promptInput.value = '';
    promptInput.focus();
  });

  actionRegenerate?.addEventListener('click', () => {
    if (lastPrompt) sendRequest(lastPrompt, lastInputData);
  });

  actionDownload?.addEventListener('click', () => {
    if (!currentImageBase64) return;
    const link = document.createElement('a');
    link.href = `data:image/png;base64,${currentImageBase64}`;
    link.download = `studio-${Date.now()}.png`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  });

  // === Lightbox ===
  resultImage?.addEventListener('click', () => {
    if (currentImageBase64 && lightbox && lightboxImage) {
      lightboxImage.src = `data:image/png;base64,${currentImageBase64}`;
      lightbox.classList.add('visible');
    }
  });

  lightboxClose?.addEventListener('click', () => {
    lightbox?.classList.remove('visible');
  });

  lightbox?.addEventListener('click', (e) => {
    if (e.target === lightbox) {
      lightbox.classList.remove('visible');
    }
  });

  // === History (Filmstrip) ===
  async function loadHistory() {
    try {
      const res = await fetch(`/api/gestures/history.php?gesture_type=${GESTURE_TYPE}&limit=20`, {
        credentials: 'include'
      });
      const data = await res.json();
      
      if (!filmstripScroll) return;
      
      if (!data.executions || data.executions.length === 0) {
        filmstripScroll.innerHTML = `
          <div class="filmstrip-empty">
            <i class="iconoir-media-image"></i>
            Las imágenes que generes aparecerán aquí
          </div>
        `;
        return;
      }

      filmstripScroll.innerHTML = data.executions.map(exec => {
        const outputData = typeof exec.output_data === 'string' 
          ? JSON.parse(exec.output_data) 
          : exec.output_data;
        
        if (!outputData?.image) return '';
        
        return `
          <div class="filmstrip-item" data-id="${exec.id}" data-image="${outputData.image}">
            <img src="data:image/png;base64,${outputData.image}" alt="Historial" />
            <button type="button" class="filmstrip-item-delete" data-id="${exec.id}">
              <i class="iconoir-xmark"></i>
            </button>
          </div>
        `;
      }).join('');

      // Click handlers
      filmstripScroll.querySelectorAll('.filmstrip-item').forEach(item => {
        item.addEventListener('click', (e) => {
          if (e.target.closest('.filmstrip-item-delete')) return;
          
          const imageData = item.dataset.image;
          if (imageData) {
            currentImageBase64 = imageData;
            if (resultImage) resultImage.src = `data:image/png;base64,${imageData}`;
            showCanvas('result');
            
            // Mark as active
            filmstripScroll.querySelectorAll('.filmstrip-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
          }
        });
      });

      // Delete handlers
      filmstripScroll.querySelectorAll('.filmstrip-item-delete').forEach(btn => {
        btn.addEventListener('click', async (e) => {
          e.stopPropagation();
          const id = btn.dataset.id;
          
          try {
            await fetch(`/api/gestures/delete.php`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
              body: JSON.stringify({ id }),
              credentials: 'include'
            });
            loadHistory();
          } catch (err) {
            console.error('Error deleting:', err);
          }
        });
      });

    } catch (err) {
      console.error('Error loading history:', err);
    }
  }

  // === Initialize ===
  showCanvas('empty');
  loadHistory();

})();
