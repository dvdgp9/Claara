/**
 * Studio Mode - Editor de Imágenes
 * Layout 3 columnas: Filmstrip + Canvas + Options
 */
(function() {
  'use strict';

  const GESTURE_TYPE = 'image-editor';

  // === DOM References ===
  const promptInput = document.getElementById('prompt-input');
  const generateBtn = document.getElementById('generate-btn');
  const canvasEmpty = document.getElementById('canvas-empty');
  const canvasLoading = document.getElementById('canvas-loading');
  const canvasImageWrapper = document.getElementById('canvas-image-wrapper');
  const canvasImage = document.getElementById('canvas-image');
  const historyList = document.getElementById('history-list');
  const historyCount = document.getElementById('history-count');
  
  // Actions
  const actionEdit = document.getElementById('action-edit');
  const actionVariation = document.getElementById('action-variation');
  const actionRegenerate = document.getElementById('action-regenerate');
  const actionDownload = document.getElementById('action-download');
  
  // Edit overlay
  const editOverlay = document.getElementById('edit-overlay');
  const editPrompt = document.getElementById('edit-prompt');
  const editApply = document.getElementById('edit-apply');
  const editCancel = document.getElementById('edit-cancel');
  
  // Options panel
  const optionsPanel = document.getElementById('options-panel');
  const optionsToggle = document.getElementById('options-toggle');
  
  // Lightbox
  const lightbox = document.getElementById('lightbox');
  const lightboxImage = document.getElementById('lightbox-image');
  const lightboxClose = document.getElementById('lightbox-close');
  
  // Hidden inputs
  const currentProvider = document.getElementById('current-provider');
  const currentFormat = document.getElementById('current-format');
  const currentStyle = document.getElementById('current-style');
  const currentColor = document.getElementById('current-color');
  const currentLighting = document.getElementById('current-lighting');

  // === State ===
  let currentImageBase64 = null;
  let lastPrompt = '';
  let lastInputData = {};

  // === Prompt Maps ===
  const styleMap = {
    '': '',
    'photographic': 'Hyper-realistic professional photography, shot on Sony A7R IV, 8k resolution, extreme detail',
    'corporate': 'Professional Silicon Valley corporate style, modern business aesthetic',
    'headshot-pro': 'Professional studio headshot, 85mm lens, shallow depth of field, sharp focus on eyes',
    '3d-render': 'Ultra-realistic 3D octane render, Ray tracing, 8k, Unreal Engine 5',
    'minimalist': 'Minimalist fine art, simple geometric balance, clean lines, vast negative space'
  };

  const colorMap = {
    '': '',
    'warm': 'Warm cinematic color grading, golden hour hues',
    'cool': 'Cool professional color palette, crisp teals and blues',
    'bw': 'Classic black and white film photography, high dynamic range',
    'vibrant': 'Vibrant and rich color saturation, bold commercial appeal'
  };

  const lightingMap = {
    '': '',
    'natural': 'Natural diffused daylight, soft window light',
    'studio': 'Classic three-point studio lighting, professional setup',
    'dramatic': 'Cinematic dramatic lighting, high contrast, chiaroscuro',
    'golden': 'Golden hour sunset lighting, warm glow'
  };

  const formatMap = {
    '1:1': 'Square format (1:1)',
    '3:4': 'Portrait format (3:4)',
    '4:3': 'Landscape format (4:3)',
    '16:9': 'Widescreen format (16:9)',
    '9:16': 'Vertical social media format (9:16)'
  };

  // === Provider Selection ===
  document.querySelectorAll('.provider-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.provider-chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      currentProvider.value = chip.dataset.provider;
    });
  });

  // === Option Chips ===
  document.querySelectorAll('.option-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      const option = chip.dataset.option;
      const value = chip.dataset.value;
      
      // Remove active from same group
      document.querySelectorAll(`.option-chip[data-option="${option}"]`).forEach(c => {
        c.classList.remove('active');
      });
      chip.classList.add('active');
      
      // Update hidden input
      const input = document.getElementById(`current-${option}`);
      if (input) input.value = value;
    });
  });

  // === Options Panel Toggle ===
  optionsToggle?.addEventListener('click', () => {
    optionsPanel?.classList.toggle('collapsed');
    const icon = optionsToggle.querySelector('i');
    if (optionsPanel?.classList.contains('collapsed')) {
      icon.className = 'iconoir-nav-arrow-left';
    } else {
      icon.className = 'iconoir-nav-arrow-right';
    }
  });

  // === Canvas State Management ===
  function showState(state) {
    canvasEmpty?.classList.add('hidden');
    canvasLoading?.classList.add('hidden');
    canvasImageWrapper?.classList.add('hidden');
    editOverlay?.classList.add('hidden');
    
    if (state === 'empty') canvasEmpty?.classList.remove('hidden');
    else if (state === 'loading') canvasLoading?.classList.remove('hidden');
    else if (state === 'image') canvasImageWrapper?.classList.remove('hidden');
  }

  // === Build Prompt ===
  function buildPrompt(description) {
    let prompt = `Create an ultra-realistic, high-resolution masterpiece image: ${description}`;
    
    const specs = [];
    
    if (formatMap[currentFormat?.value]) specs.push(formatMap[currentFormat.value]);
    if (currentStyle?.value && styleMap[currentStyle.value]) specs.push(styleMap[currentStyle.value]);
    if (currentColor?.value && colorMap[currentColor.value]) specs.push(colorMap[currentColor.value]);
    if (currentLighting?.value && lightingMap[currentLighting.value]) specs.push(lightingMap[currentLighting.value]);
    
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

    const prompt = buildPrompt(description);
    const inputData = {
      mode: 'generate',
      description,
      provider: currentProvider?.value || 'qwen',
      format: currentFormat?.value || '1:1',
      style: currentStyle?.value || '',
      color: currentColor?.value || '',
      lighting: currentLighting?.value || ''
    };

    lastPrompt = prompt;
    lastInputData = inputData;

    await sendRequest(prompt, inputData);
  }

  // === Edit Image ===
  async function editImage() {
    const description = editPrompt?.value.trim();
    if (!description || !currentImageBase64) return;

    const inputData = {
      mode: 'edit',
      description,
      provider: currentProvider?.value || 'qwen',
      source_image: currentImageBase64
    };

    lastPrompt = description;
    lastInputData = inputData;

    editOverlay?.classList.add('hidden');
    await sendRequest(description, inputData);
  }

  // === Send Request ===
  async function sendRequest(prompt, inputData) {
    showState('loading');
    if (generateBtn) generateBtn.disabled = true;

    try {
      const res = await fetch('/api/gestures/generate-image.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.CSRF_TOKEN
        },
        body: JSON.stringify({
          gesture_type: GESTURE_TYPE,
          prompt,
          input_data: inputData
        }),
        credentials: 'include'
      });

      const data = await res.json();
      if (generateBtn) generateBtn.disabled = false;

      if (!res.ok || !data.image) {
        showState('empty');
        alert('Error: ' + (data.error?.message || 'No se pudo generar la imagen'));
        return;
      }

      currentImageBase64 = data.image;
      if (canvasImage) canvasImage.src = `data:image/png;base64,${data.image}`;
      showState('image');
      
      loadHistory();

    } catch (err) {
      if (generateBtn) generateBtn.disabled = false;
      showState('empty');
      console.error('Error:', err);
      alert('Error de conexión');
    }
  }

  // === Event Listeners ===
  generateBtn?.addEventListener('click', generateImage);
  
  promptInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      generateImage();
    }
  });

  // === Action Buttons ===
  actionEdit?.addEventListener('click', () => {
    if (!currentImageBase64) return;
    editOverlay?.classList.remove('hidden');
    editPrompt?.focus();
  });

  editCancel?.addEventListener('click', () => {
    editOverlay?.classList.add('hidden');
  });

  editApply?.addEventListener('click', editImage);
  
  editPrompt?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      editImage();
    }
  });

  actionRegenerate?.addEventListener('click', () => {
    if (lastPrompt) sendRequest(lastPrompt, lastInputData);
  });

  actionVariation?.addEventListener('click', () => {
    if (!currentImageBase64 || !lastInputData.description) return;
    const variationPrompt = lastInputData.description + ' (create a variation with different composition)';
    const inputData = { ...lastInputData, description: variationPrompt };
    sendRequest(variationPrompt, inputData);
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
  canvasImage?.addEventListener('click', () => {
    if (currentImageBase64 && lightbox && lightboxImage) {
      lightboxImage.src = `data:image/png;base64,${currentImageBase64}`;
      lightbox.classList.remove('hidden');
    }
  });

  lightboxClose?.addEventListener('click', () => {
    lightbox?.classList.add('hidden');
  });

  lightbox?.addEventListener('click', (e) => {
    if (e.target === lightbox) {
      lightbox.classList.add('hidden');
    }
  });

  // === History ===
  async function loadHistory() {
    if (!historyList) return;

    try {
      const res = await fetch(`/api/gestures/history.php?type=${GESTURE_TYPE}`, {
        credentials: 'include'
      });
      const data = await res.json();

      if (!res.ok) {
        historyList.innerHTML = '<div class="filmstrip-empty"><i class="iconoir-warning-triangle"></i><span>Error</span></div>';
        return;
      }

      renderHistory(data.items || []);
    } catch (err) {
      historyList.innerHTML = '<div class="filmstrip-empty"><i class="iconoir-warning-triangle"></i><span>Error</span></div>';
    }
  }

  function renderHistory(items) {
    if (!historyList || !historyCount) return;

    if (items.length === 0) {
      historyList.innerHTML = '<div class="filmstrip-empty"><i class="iconoir-media-image"></i><span>Sin imágenes</span></div>';
      historyCount.textContent = '0';
      return;
    }

    historyCount.textContent = items.length;
    historyList.innerHTML = items.map(item => {
      const outputData = item.output_data || {};
      const imageData = outputData.image;
      if (!imageData) return '';

      return `
        <div class="filmstrip-item" data-id="${item.id}" data-image="${imageData}">
          <img src="data:image/png;base64,${imageData}" alt="" />
          <button class="filmstrip-item-delete" data-id="${item.id}">
            <i class="iconoir-xmark"></i>
          </button>
        </div>
      `;
    }).join('');

    // Click handlers
    historyList.querySelectorAll('.filmstrip-item').forEach(item => {
      item.addEventListener('click', (e) => {
        if (e.target.closest('.filmstrip-item-delete')) return;
        
        const imageData = item.dataset.image;
        if (imageData) {
          currentImageBase64 = imageData;
          if (canvasImage) canvasImage.src = `data:image/png;base64,${imageData}`;
          showState('image');
          
          // Mark active
          historyList.querySelectorAll('.filmstrip-item').forEach(i => i.classList.remove('active'));
          item.classList.add('active');
        }
      });
    });

    // Delete handlers
    historyList.querySelectorAll('.filmstrip-item-delete').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const id = btn.dataset.id;
        
        try {
          await fetch('/api/gestures/delete.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.CSRF_TOKEN
            },
            body: JSON.stringify({ id: Number(id) }),
            credentials: 'include'
          });
          loadHistory();
        } catch (err) {
          console.error('Error deleting:', err);
        }
      });
    });
  }

  // === Initialize ===
  showState('empty');
  loadHistory();
})();
