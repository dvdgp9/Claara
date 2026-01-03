/**
 * Gesto: Editor de Imágenes con Nanobanana 🍌
 * Genera imágenes usando selectores de formato, estilo, color, iluminación y composición
 */
(function() {
  'use strict';

  const GESTURE_TYPE = 'image-editor';

  // === Referencias DOM ===
  const form = document.getElementById('image-editor-form');
  const imageResult = document.getElementById('image-result');
  const imageContainer = document.getElementById('image-container');
  const generatedImage = document.getElementById('generated-image');
  const imageCaption = document.getElementById('image-caption');
  const imageLoading = document.getElementById('image-loading');
  const generateBtn = document.getElementById('generate-image-btn');
  const downloadBtn = document.getElementById('download-image-btn');
  const regenerateBtn = document.getElementById('regenerate-image-btn');
  const historyList = document.getElementById('history-list');
  const newImageBtn = document.getElementById('new-image-btn');
  const descriptionField = document.getElementById('image-description');
  const summaryText = document.getElementById('summary-text');

  // Lightbox
  const lightbox = document.getElementById('image-lightbox');
  const lightboxImage = document.getElementById('lightbox-image');
  const lightboxClose = document.getElementById('lightbox-close');

  // Tabs
  const optionTabs = document.querySelectorAll('.option-tab');
  const tabContents = document.querySelectorAll('.tab-content');

  // === Mapas de opciones para construir prompts ===
  const styleMap = {
    '': '',
    'photographic': 'photorealistic photography style, captured with a professional DSLR camera',
    'digital-art': 'digital art illustration style, clean vector-like rendering',
    'corporate': 'professional corporate style, clean and modern business aesthetic',
    'minimalist': 'minimalist design, simple shapes, clean lines, lots of negative space',
    '3d-render': '3D rendered image, realistic materials and textures, CGI quality',
    'flat-design': 'flat design style, simple geometric shapes, no gradients or shadows',
    'isometric': 'isometric view, 3D isometric illustration style'
  };

  const colorMap = {
    '': '',
    'warm': 'warm color palette with oranges, reds, and yellows',
    'cool': 'cool color palette with blues, teals, and greens',
    'corporate': 'corporate color scheme with professional blues and teals (#23AAC5 accent)',
    'monochrome': 'monochromatic color scheme, single color with different shades',
    'pastel': 'soft pastel colors, muted and gentle tones',
    'bw': 'black and white, high contrast monochrome',
    'vibrant': 'vibrant and saturated colors, bold and eye-catching'
  };

  const lightingMap = {
    '': '',
    'natural': 'natural daylight lighting, soft and even illumination',
    'studio': 'professional studio lighting setup, three-point lighting',
    'dramatic': 'dramatic lighting with strong shadows and highlights, chiaroscuro',
    'soft': 'soft diffused lighting, gentle shadows, flattering illumination',
    'backlight': 'backlit scene with rim lighting, silhouette effect',
    'golden': 'golden hour lighting, warm sunset/sunrise glow',
    'volumetric': 'volumetric lighting with visible light rays, atmospheric'
  };

  const compositionMap = {
    '': '',
    'bokeh': 'shallow depth of field, beautiful bokeh background blur',
    'closeup': 'close-up shot, detailed macro view of the subject',
    'wide': 'wide-angle shot, expansive view showing full scene',
    'above': 'bird\'s eye view, shot from directly above, top-down perspective',
    'below': 'low angle shot from below, looking upward, dramatic perspective',
    'macro': 'macro photography, extreme close-up showing tiny details',
    'negative-space': 'composition with lots of negative space, subject isolated'
  };

  const formatMap = {
    '1:1': 'square format (1:1 aspect ratio)',
    '3:4': 'portrait format (3:4 aspect ratio)',
    '4:3': 'landscape format (4:3 aspect ratio)',
    '16:9': 'widescreen format (16:9 aspect ratio)',
    '9:16': 'vertical mobile format (9:16 aspect ratio)'
  };

  // Estado para regenerar
  let lastPrompt = '';
  let lastInputData = {};
  let currentImageBase64 = '';

  // === Gestión de tabs ===
  optionTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const targetTab = tab.dataset.tab;
      
      // Actualizar tabs activas
      optionTabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      
      // Mostrar contenido correspondiente
      tabContents.forEach(content => {
        content.classList.add('hidden');
      });
      document.getElementById(`tab-${targetTab}`).classList.remove('hidden');
    });
  });

  // === Actualizar resumen de selección ===
  function updateSummary() {
    const format = document.querySelector('input[name="format"]:checked')?.value || '1:1';
    const style = document.querySelector('input[name="style"]:checked')?.value || '';
    const color = document.querySelector('input[name="color"]:checked')?.value || '';
    const lighting = document.querySelector('input[name="lighting"]:checked')?.value || '';
    const composition = document.querySelector('input[name="composition"]:checked')?.value || '';

    const parts = [`Formato ${format}`];
    
    const styleLabels = {
      '': 'Sin estilo', 'photographic': 'Fotográfico', 'digital-art': 'Digital Art',
      'corporate': 'Corporativo', 'minimalist': 'Minimalista', '3d-render': '3D Render',
      'flat-design': 'Flat Design', 'isometric': 'Isométrico'
    };
    const colorLabels = {
      '': '', 'warm': 'Cálidos', 'cool': 'Fríos', 'corporate': 'Corporativo',
      'monochrome': 'Monocromo', 'pastel': 'Pastel', 'bw': 'B/N', 'vibrant': 'Vibrante'
    };
    const lightingLabels = {
      '': '', 'natural': 'Luz natural', 'studio': 'Estudio', 'dramatic': 'Dramática',
      'soft': 'Suave', 'backlight': 'Contraluz', 'golden': 'Hora dorada', 'volumetric': 'Volumétrica'
    };
    const compositionLabels = {
      '': '', 'bokeh': 'Bokeh', 'closeup': 'Primer plano', 'wide': 'Plano general',
      'above': 'Cenital', 'below': 'Contrapicado', 'macro': 'Macro', 'negative-space': 'Espacio negativo'
    };

    if (style) parts.push(styleLabels[style]);
    if (color) parts.push(colorLabels[color]);
    if (lighting) parts.push(lightingLabels[lighting]);
    if (composition) parts.push(compositionLabels[composition]);

    summaryText.textContent = parts.join(' • ');
  }

  // Escuchar cambios en todos los radios
  document.querySelectorAll('input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', updateSummary);
  });

  // === Construir prompt profesional ===
  function buildPrompt(description, options) {
    const { format, style, color, lighting, composition } = options;

    // Construir el prompt estructurado
    let prompt = `Create an image: ${description}`;
    
    // Añadir especificaciones técnicas
    const specs = [];
    
    if (formatMap[format]) {
      specs.push(formatMap[format]);
    }
    
    if (style && styleMap[style]) {
      specs.push(styleMap[style]);
    }
    
    if (color && colorMap[color]) {
      specs.push(colorMap[color]);
    }
    
    if (lighting && lightingMap[lighting]) {
      specs.push(lightingMap[lighting]);
    }
    
    if (composition && compositionMap[composition]) {
      specs.push(compositionMap[composition]);
    }

    if (specs.length > 0) {
      prompt += '\n\nStyle specifications:\n- ' + specs.join('\n- ');
    }

    // Añadir calidad
    prompt += '\n\nQuality: High resolution, professional quality, detailed, sharp focus, 8K.';

    return prompt;
  }

  // === Submit del formulario ===
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      await generateImage();
    });
  }

  // === Generar imagen ===
  async function generateImage() {
    const description = descriptionField.value.trim();
    if (!description) {
      alert('Por favor, describe la imagen que quieres crear');
      descriptionField.focus();
      return;
    }

    const options = {
      format: document.querySelector('input[name="format"]:checked')?.value || '1:1',
      style: document.querySelector('input[name="style"]:checked')?.value || '',
      color: document.querySelector('input[name="color"]:checked')?.value || '',
      lighting: document.querySelector('input[name="lighting"]:checked')?.value || '',
      composition: document.querySelector('input[name="composition"]:checked')?.value || ''
    };

    const prompt = buildPrompt(description, options);
    const inputData = { description, ...options };

    // Guardar para regenerar
    lastPrompt = prompt;
    lastInputData = inputData;

    await sendRequest(prompt, inputData);
  }

  // === Enviar request a la API ===
  async function sendRequest(prompt, inputData) {
    // Mostrar loading
    imageResult.classList.add('hidden');
    imageLoading.classList.remove('hidden');
    generateBtn.disabled = true;

    try {
      const res = await fetch('/api/gestures/generate-image.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.CSRF_TOKEN
        },
        body: JSON.stringify({
          gesture_type: GESTURE_TYPE,
          prompt: prompt,
          input_data: inputData
        }),
        credentials: 'include'
      });

      const data = await res.json();
      imageLoading.classList.add('hidden');
      generateBtn.disabled = false;

      if (!res.ok) {
        alert('Error al generar la imagen: ' + (data.error?.message || 'Error desconocido'));
        return;
      }

      if (!data.image) {
        alert('No se pudo generar la imagen. Intenta con otra descripción.');
        return;
      }

      // Mostrar imagen
      currentImageBase64 = data.image;
      generatedImage.src = `data:image/png;base64,${data.image}`;
      imageResult.classList.remove('hidden');

      // Mostrar caption si hay texto
      if (data.text) {
        imageCaption.textContent = data.text;
        imageCaption.classList.remove('hidden');
      } else {
        imageCaption.classList.add('hidden');
      }

      // Scroll al resultado
      imageResult.scrollIntoView({ behavior: 'smooth', block: 'start' });

      // Recargar historial
      loadHistory();

    } catch (err) {
      imageLoading.classList.add('hidden');
      generateBtn.disabled = false;
      console.error('Error:', err);
      alert('Error de conexión al generar la imagen');
    }
  }

  // === Descargar imagen ===
  if (downloadBtn) {
    downloadBtn.addEventListener('click', () => {
      if (!currentImageBase64) return;

      const link = document.createElement('a');
      link.href = `data:image/png;base64,${currentImageBase64}`;
      link.download = `nanobanana-${Date.now()}.png`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    });
  }

  // === Regenerar imagen ===
  if (regenerateBtn) {
    regenerateBtn.addEventListener('click', () => {
      if (lastPrompt) {
        sendRequest(lastPrompt, lastInputData);
      }
    });
  }

  // === Lightbox ===
  if (generatedImage) {
    generatedImage.addEventListener('click', () => {
      if (currentImageBase64) {
        lightboxImage.src = `data:image/png;base64,${currentImageBase64}`;
        lightbox.classList.remove('hidden');
        lightbox.classList.add('flex');
      }
    });
  }

  if (lightboxClose) {
    lightboxClose.addEventListener('click', () => {
      lightbox.classList.add('hidden');
      lightbox.classList.remove('flex');
    });
  }

  if (lightbox) {
    lightbox.addEventListener('click', (e) => {
      if (e.target === lightbox) {
        lightbox.classList.add('hidden');
        lightbox.classList.remove('flex');
      }
    });
  }

  // === HISTORIAL ===
  loadHistory();
  checkUrlParameter();

  function checkUrlParameter() {
    const urlParams = new URLSearchParams(window.location.search);
    const executionId = urlParams.get('id');
    
    if (executionId) {
      loadExecution(executionId);
      const newUrl = window.location.pathname;
      window.history.replaceState({}, document.title, newUrl);
    }
  }

  async function loadHistory() {
    try {
      const res = await fetch(`/api/gestures/history.php?type=${GESTURE_TYPE}`, {
        credentials: 'include'
      });
      const data = await res.json();

      if (!res.ok) {
        historyList.innerHTML = '<div class="p-4 text-center text-red-500 text-sm">Error al cargar</div>';
        return;
      }

      renderHistory(data.items || []);
    } catch (err) {
      historyList.innerHTML = '<div class="p-4 text-center text-red-500 text-sm">Error de conexión</div>';
    }
  }

  function renderHistory(items) {
    if (items.length === 0) {
      historyList.innerHTML = `
        <div class="p-6 text-center">
          <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
            <i class="iconoir-media-image text-xl text-slate-400"></i>
          </div>
          <p class="text-sm text-slate-500">Aún no has generado imágenes</p>
          <p class="text-xs text-slate-400 mt-1">Usa el formulario para empezar</p>
        </div>
      `;
      return;
    }

    historyList.innerHTML = items.map(item => {
      const timeAgo = formatTimeAgo(new Date(item.created_at));
      const description = item.input_data?.description || item.title || 'Imagen generada';
      const truncatedDesc = description.length > 40 ? description.substring(0, 40) + '...' : description;

      return `
        <div class="history-item w-full p-3 hover:bg-slate-50 border-b border-slate-100 transition-colors group flex items-start gap-2" data-id="${item.id}">
          <i class="iconoir-media-image text-amber-500 mt-0.5"></i>
          <div class="flex-1 min-w-0 cursor-pointer history-item-main">
            <p class="text-sm font-medium text-slate-700 truncate group-hover:text-amber-700">${escapeHtml(truncatedDesc)}</p>
            <div class="flex items-center gap-2 mt-1">
              <span class="text-[10px] text-slate-400">${timeAgo}</span>
            </div>
          </div>
          <button class="history-item-delete opacity-0 group-hover:opacity-100 transition-opacity text-slate-300 hover:text-red-500 p-1 rounded" title="Eliminar">
            <i class="iconoir-trash"></i>
          </button>
        </div>
      `;
    }).join('');

    // Event listeners
    historyList.querySelectorAll('.history-item-main').forEach(el => {
      const id = el.parentElement.dataset.id;
      el.addEventListener('click', () => loadExecution(id));
    });

    historyList.querySelectorAll('.history-item-delete').forEach(btn => {
      const id = btn.parentElement.dataset.id;
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        deleteExecution(id);
      });
    });
  }

  async function loadExecution(id) {
    try {
      const res = await fetch(`/api/gestures/get.php?id=${id}`, {
        credentials: 'include'
      });
      const data = await res.json();

      if (!res.ok || !data.execution) {
        alert('Error al cargar la imagen');
        return;
      }

      const exec = data.execution;
      const outputData = exec.output_data || {};

      // Mostrar imagen
      if (outputData.image) {
        currentImageBase64 = outputData.image;
        generatedImage.src = `data:image/png;base64,${outputData.image}`;
        imageResult.classList.remove('hidden');

        if (outputData.text) {
          imageCaption.textContent = outputData.text;
          imageCaption.classList.remove('hidden');
        } else {
          imageCaption.classList.add('hidden');
        }
      }

      // Restaurar inputs
      const inputData = exec.input_data || {};
      if (inputData.description) {
        descriptionField.value = inputData.description;
      }
      if (inputData.format) {
        const radio = document.querySelector(`input[name="format"][value="${inputData.format}"]`);
        if (radio) radio.checked = true;
      }
      if (inputData.style !== undefined) {
        const radio = document.querySelector(`input[name="style"][value="${inputData.style}"]`);
        if (radio) radio.checked = true;
      }
      if (inputData.color !== undefined) {
        const radio = document.querySelector(`input[name="color"][value="${inputData.color}"]`);
        if (radio) radio.checked = true;
      }
      if (inputData.lighting !== undefined) {
        const radio = document.querySelector(`input[name="lighting"][value="${inputData.lighting}"]`);
        if (radio) radio.checked = true;
      }
      if (inputData.composition !== undefined) {
        const radio = document.querySelector(`input[name="composition"][value="${inputData.composition}"]`);
        if (radio) radio.checked = true;
      }

      updateSummary();
      lastInputData = inputData;

      // Scroll
      imageResult.scrollIntoView({ behavior: 'smooth', block: 'start' });

    } catch (err) {
      alert('Error de conexión');
    }
  }

  async function deleteExecution(id) {
    if (!confirm('¿Eliminar esta imagen del historial?')) return;

    try {
      const res = await fetch('/api/gestures/delete.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.CSRF_TOKEN
        },
        body: JSON.stringify({ id: Number(id) }),
        credentials: 'include'
      });

      const data = await res.json();
      if (!res.ok || !data.success) {
        alert('No se ha podido eliminar');
        return;
      }

      loadHistory();
    } catch (err) {
      alert('Error de conexión al eliminar');
    }
  }

  // Botón nueva imagen
  if (newImageBtn) {
    newImageBtn.addEventListener('click', () => {
      form.reset();
      imageResult.classList.add('hidden');
      currentImageBase64 = '';
      updateSummary();
      descriptionField.focus();
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  // === Utilidades ===
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
    if (diffDays < 7) return `hace ${diffDays} días`;
    return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Inicializar
  updateSummary();
})();
