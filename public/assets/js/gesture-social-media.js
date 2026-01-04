/**
 * Gesto: Redes Sociales (constructor de publicaciones)
 */
(function() {
  'use strict';

  const GESTURE_TYPE = 'social-media';

  // === Referencias DOM ===
  const socialMediaForm = document.getElementById('social-media-form');
  const postResult = document.getElementById('post-result');
  const postContent = document.getElementById('post-content');
  const hashtagsContent = document.getElementById('hashtags-content');
  const editorialSummary = document.getElementById('editorial-summary');
  const editorialPanel = document.getElementById('editorial-panel');
  const postLoading = document.getElementById('post-loading');
  const resultPlaceholder = document.getElementById('result-placeholder');
  const generatePostBtn = document.getElementById('generate-post-btn');
  const copyPostBtn = document.getElementById('copy-post-btn');
  const copyHashtagsBtn = document.getElementById('copy-hashtags-btn');
  const regeneratePostBtn = document.getElementById('regenerate-post-btn');
  const historyList = document.getElementById('history-list');
  const newPostBtn = document.getElementById('new-post-btn');
  const variantBtns = document.querySelectorAll('.variant-btn');

  // === Mapas de valores ===
  const businessLineMap = {
    'ebone': 'Grupo Ebone',
    'ebone-servicios': 'Ebone Servicios',
    'cubofit': 'CUBOFIT',
    'uniges': 'UNIGES-3',
    'cide': 'CIDE',
    'teia': 'Teià (El CIM)'
  };

  const intentionMap = {
    'informar': 'Informar',
    'reforzar-marca': 'Reforzar marca',
    'conectar': 'Conectar emocionalmente',
    'activar': 'Activar interés',
    'aportar-valor': 'Aportar valor / explicar'
  };

  const channelMap = {
    'instagram': 'Instagram',
    'facebook': 'Facebook',
    'linkedin': 'LinkedIn',
    'transversal': 'Texto transversal'
  };

  const narrativeMap = {
    '': 'Automático',
    'personas': 'Personas / equipo',
    'proyecto': 'Proyecto / acción',
    'detalle': 'Detalle diferencial',
    'impacto': 'Impacto en usuarios',
    'vision': 'Visión / propósito'
  };

  const lengthMap = {
    '': 'Automático',
    'corto': 'Corto (impacto rápido)',
    'medio': 'Medio (equilibrado)',
    'largo': 'Largo (desarrollo completo)'
  };

  const closingMap = {
    '': 'Automático',
    'informativo': 'Informativo',
    'inspirador': 'Inspirador',
    'cta-suave': 'CTA suave',
    'cta-claro': 'CTA claro'
  };

  // Estado para regenerar y variantes
  let lastPrompt = '';
  let lastInputData = {};
  let lastBusinessLine = '';
  let lastGeneratedContent = '';
  let lastHashtags = '';

  // === Submit del formulario ===
  if (socialMediaForm) {
    socialMediaForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      await generatePost();
    });
  }

  // === Generar publicación ===
  async function generatePost() {
    const context = document.getElementById('post-context').value.trim();
    if (!context) {
      alert('Por favor, indica de qué va la publicación');
      return;
    }

    const intention = document.querySelector('input[name="intention"]:checked')?.value || 'informar';
    const businessLine = document.querySelector('input[name="business-line"]:checked')?.value || 'ebone';
    const channel = document.querySelector('input[name="channel"]:checked')?.value || 'instagram';
    const narrative = document.querySelector('input[name="narrative"]:checked')?.value || '';
    const length = document.querySelector('input[name="length"]:checked')?.value || '';
    const closing = document.querySelector('input[name="closing"]:checked')?.value || '';

    const businessName = businessLineMap[businessLine];

    const inputData = {
      context,
      intention,
      businessLine,
      channel,
      narrative,
      length,
      closing
    };

    // Obtener últimas publicaciones de esta línea de negocio
    const recentPosts = await fetchRecentPosts(businessLine);

    const prompt = buildPrompt(inputData, businessName, recentPosts);
    
    lastPrompt = prompt;
    lastInputData = inputData;
    lastBusinessLine = businessLine;

    await sendPrompt(prompt, inputData, businessLine);
  }

  // === Obtener publicaciones recientes de la línea de negocio ===
  async function fetchRecentPosts(businessLine) {
    try {
      const res = await fetch(`/api/gestures/recent.php?type=${GESTURE_TYPE}&business_line=${businessLine}&limit=5`, {
        credentials: 'include'
      });
      const data = await res.json();
      if (res.ok && data.posts) {
        return data.posts;
      }
    } catch (err) {
      console.warn('No se pudieron obtener publicaciones recientes:', err);
    }
    return [];
  }

  // === Construir prompt ===
  function buildPrompt(data, businessName, recentPosts = []) {
    const { context, intention, channel, narrative, length, closing } = data;

    // Instrucciones según intención
    const intentionInstructions = {
      'informar': 'El objetivo es INFORMAR: transmitir un hecho, novedad o actualización de forma clara y directa.',
      'reforzar-marca': 'El objetivo es REFORZAR MARCA: mostrar los valores, identidad y diferenciación de la marca.',
      'conectar': 'El objetivo es CONECTAR EMOCIONALMENTE: generar cercanía, empatía o identificación con la audiencia.',
      'activar': 'El objetivo es ACTIVAR INTERÉS: despertar curiosidad o motivar a la audiencia a saber más o actuar.',
      'aportar-valor': 'El objetivo es APORTAR VALOR: educar, explicar o compartir conocimiento útil para la audiencia.'
    };

    // Instrucciones según canal
    const channelInstructions = {
      'instagram': `Para INSTAGRAM:
- Tono visual, cercano y dinámico
- Apertura potente que enganche en los primeros segundos
- Emojis con moderación para dar ritmo visual
- Saltos de línea para facilitar lectura móvil
- Máximo 2200 caracteres (ideal: 150-300 para feed, puede ser más largo para carrusel)`,
      'facebook': `Para FACEBOOK:
- Tono conversacional y accesible
- Puede ser más extenso que Instagram
- Invita a la interacción (comentarios, compartir)
- Funciona bien con preguntas o reflexiones`,
      'linkedin': `Para LINKEDIN:
- Tono profesional pero humano
- Aporta valor o perspectiva sectorial
- Puede incluir datos o logros
- Estructura con párrafos cortos
- Evita emojis excesivos (1-2 máximo si procede)`,
      'transversal': `Texto TRANSVERSAL (adaptable):
- Estilo neutro que funcione en múltiples canales
- Ni muy informal ni muy corporativo
- Sin emojis ni elementos específicos de un canal
- Longitud media, adaptable`
    };

    // Enfoque narrativo
    let narrativeInstruction = '';
    if (narrative) {
      const narrativeTexts = {
        'personas': 'Enfoque desde las PERSONAS o el equipo: cuenta la historia poniendo el foco en quienes lo hacen posible.',
        'proyecto': 'Enfoque desde el PROYECTO o la acción: centra el mensaje en lo que se está haciendo o se ha logrado.',
        'detalle': 'Enfoque desde el DETALLE DIFERENCIAL: destaca lo que hace único o especial este hecho.',
        'impacto': 'Enfoque desde el IMPACTO: cuenta cómo esto afecta positivamente a usuarios, ciudadanía o comunidad.',
        'vision': 'Enfoque desde la VISIÓN: conecta con el propósito mayor o los valores de la organización.'
      };
      narrativeInstruction = `\n${narrativeTexts[narrative]}`;
    } else {
      narrativeInstruction = '\nDeduce el mejor enfoque narrativo según el contexto y la intención.';
    }

    // Longitud
    let lengthInstruction = '';
    if (length) {
      const lengthTexts = {
        'corto': 'LONGITUD CORTA: texto breve, impacto rápido, 1-3 frases.',
        'medio': 'LONGITUD MEDIA: desarrollo equilibrado, 3-5 frases o párrafos cortos.',
        'largo': 'LONGITUD LARGA: desarrollo completo, permite contexto y detalles.'
      };
      lengthInstruction = `\n${lengthTexts[length]}`;
    } else {
      lengthInstruction = '\nDecide la longitud óptima según el contenido y el canal.';
    }

    // Cierre
    let closingInstruction = '';
    if (closing) {
      const closingTexts = {
        'informativo': 'CIERRE INFORMATIVO: termina aportando un dato adicional o una conclusión factual.',
        'inspirador': 'CIERRE INSPIRADOR: termina con una reflexión o mensaje que motive.',
        'cta-suave': 'CIERRE CTA SUAVE: invita sutilmente a una acción (ej: "Descubre más en...", "¿Qué opinas?").',
        'cta-claro': 'CIERRE CTA CLARO: llamada a la acción directa y explícita.'
      };
      closingInstruction = `\n${closingTexts[closing]}`;
    } else {
      closingInstruction = '\nElige el tipo de cierre más adecuado según intención y canal.';
    }

    // Instrucciones por línea de negocio
    const businessInstructions = {
      'ebone': `ESTILO GRUPO EBONE:
- Tono institucional pero cercano
- Profesionalidad sin frialdad
- Valores: compromiso, experiencia, cercanía con administraciones y ciudadanía
- Habla en primera persona del plural (nosotros)`,
      'ebone-servicios': `ESTILO EBONE SERVICIOS:
- Tono institucional pero cercano
- Profesionalidad sin frialdad
- Valores: compromiso, experiencia, cercanía con administraciones y ciudadanía
- Habla en primera persona del plural (nosotros)`,
      'cubofit': `ESTILO CUBOFIT:
- Tono energético, moderno y motivador
- Lenguaje dinámico y positivo
- Valores: fitness, bienestar, innovación, accesibilidad
- Puede usar más emojis y expresiones cercanas
- Conecta con el estilo de vida activo`,
      'uniges': `ESTILO UNIGES-3:
- Tono técnico pero accesible
- Profesional y orientado a resultados
- Valores: gestión deportiva, eficiencia, servicio público
- Balance entre institucional y cercano
- Conecta con la comunidad deportiva y municipal`,
      'cide': `ESTILO CIDE (Cátedra de Innovación Deportiva):
- Tono académico pero accesible
- Autoridad en innovación y gestión deportiva
- Valores: conocimiento, investigación, transferencia, vanguardia
- Conecta con profesionales del sector, universidades y entidades deportivas
- Puede incluir datos, tendencias o reflexiones del sector`,
      'teia': `ESTILO TEIÀ - EL CIM (Polideportivo Municipal):
- IMPORTANTE: Escribe SIEMPRE en CATALÁN
- Tono cercano y comunitario
- Valores: salud, actividad física, comunidad local, servicio público
- Contenidos típicos: eventos deportivos, consejos de salud/ejercicio, actividades ofertadas, horarios
- Conecta con los vecinos y usuarios del polideportivo
- Usa un lenguaje inclusivo y motivador`
    };

    return `Eres el community manager de ${businessName}. Construye una publicación para redes sociales.

CONTEXTO DE LA PUBLICACIÓN:
"${context}"

${intentionInstructions[intention]}

${channelInstructions[channel]}

${businessInstructions[data.businessLine]}
${narrativeInstruction}
${lengthInstruction}
${closingInstruction}

FORMATO DE RESPUESTA:
Devuelve la publicación en el siguiente formato exacto:

---PUBLICACION---
[Aquí el texto de la publicación, listo para copiar y pegar]

---HASHTAGS---
[Hashtags relevantes separados por espacios, entre 5-10]

---FIN---
${recentPosts.length > 0 ? `
PUBLICACIONES ANTERIORES DE ESTA MARCA (para evitar repetirte):
${recentPosts.map((post, i) => `${i + 1}. "${post.substring(0, 200)}${post.length > 200 ? '...' : ''}"`).join('\n')}

Ten en cuenta estas publicaciones para:
- NO repetir frases, estructuras o aperturas similares
- Variar el estilo y enfoque
- Aportar frescura y originalidad` : ''}

IMPORTANTE:
- El texto debe estar listo para publicar, sin explicaciones ni comentarios.
- No inventes datos, fechas, nombres ni cifras que no estén en el contexto.
- Los hashtags deben ser relevantes: algunos de marca, algunos del sector, algunos de posicionamiento.
- Si faltan datos concretos, redacta de forma que no queden huecos evidentes.`;
  }

  // === Enviar prompt a la API ===
  async function sendPrompt(prompt, inputData, businessLine, isVariant = false) {
    resultPlaceholder.classList.add('hidden');
    postResult.classList.add('hidden');
    postLoading.classList.remove('hidden');
    generatePostBtn.disabled = true;
    variantBtns.forEach(btn => btn.disabled = true);

    try {
      const res = await fetch('/api/gestures/generate.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.CSRF_TOKEN
        },
        body: JSON.stringify({
          gesture_type: GESTURE_TYPE,
          prompt: prompt,
          input_data: inputData,
          content_type: isVariant ? 'variant' : 'original',
          business_line: businessLine
        }),
        credentials: 'include'
      });

      const data = await res.json();
      postLoading.classList.add('hidden');
      generatePostBtn.disabled = false;
      variantBtns.forEach(btn => btn.disabled = false);

      if (!res.ok) {
        alert('Error al generar la publicación: ' + (data.error?.message || 'Error desconocido'));
        return;
      }

      // Parsear respuesta
      const parsed = parseResponse(data.content);
      lastGeneratedContent = parsed.post;

      // Mantener hashtags si la variante no devuelve nuevos
      const hashtags = (parsed.hashtags || '').trim();
      if (!isVariant) {
        lastHashtags = hashtags;
      } else if (hashtags) {
        lastHashtags = hashtags;
      }

      // Mostrar resultado
      postContent.textContent = parsed.post;
      hashtagsContent.textContent = lastHashtags;
      
      // Resumen editorial
      renderEditorialSummary(isVariant ? lastInputData : inputData);
      editorialPanel.classList.remove('hidden');

      postResult.classList.remove('hidden');

      // Recargar historial
      loadHistory();

    } catch (err) {
      postLoading.classList.add('hidden');
      generatePostBtn.disabled = false;
      variantBtns.forEach(btn => btn.disabled = false);
      alert('Error de conexión al generar la publicación');
    }
  }

  // === Parsear respuesta del LLM ===
  function parseResponse(content) {
    let post = content;
    let hashtags = '';

    // Intentar extraer partes estructuradas
    const postMatch = content.match(/---PUBLICACION---\s*([\s\S]*?)\s*---HASHTAGS---/);
    const hashtagsMatch = content.match(/---HASHTAGS---\s*([\s\S]*?)\s*---FIN---/);

    if (postMatch && postMatch[1]) {
      post = postMatch[1].trim();
    }
    if (hashtagsMatch && hashtagsMatch[1]) {
      hashtags = hashtagsMatch[1].trim();
    }

    // Fallback: buscar hashtags al final si no se encontraron
    if (!hashtags) {
      const hashtagFallback = content.match(/(#\w+\s*)+$/);
      if (hashtagFallback) {
        hashtags = hashtagFallback[0].trim();
        post = content.replace(hashtagFallback[0], '').trim();
      }
    }

    // Si aún no hay estructura, limpiar marcadores
    post = post.replace(/---PUBLICACION---|---HASHTAGS---|---FIN---/g, '').trim();

    return { post, hashtags };
  }

  // === Renderizar resumen editorial ===
  function renderEditorialSummary(data) {
    const items = [
      { label: 'Intención', value: intentionMap[data.intention] || data.intention },
      { label: 'Línea', value: businessLineMap[data.businessLine] || data.businessLine },
      { label: 'Canal', value: channelMap[data.channel] || data.channel },
      { label: 'Enfoque', value: narrativeMap[data.narrative] || 'Automático' },
      { label: 'Longitud', value: lengthMap[data.length] || 'Automático' },
      { label: 'Cierre', value: closingMap[data.closing] || 'Automático' }
    ];

    editorialSummary.innerHTML = items.map(item => 
      `<div><span class="font-medium text-slate-700">${item.label}:</span> ${item.value}</div>`
    ).join('');
  }

  // === Copiar publicación ===
  if (copyPostBtn) {
    copyPostBtn.addEventListener('click', () => {
      const text = postContent.textContent;
      navigator.clipboard.writeText(text).then(() => {
        const originalText = copyPostBtn.innerHTML;
        copyPostBtn.innerHTML = '<i class="iconoir-check"></i> Copiado';
        setTimeout(() => {
          copyPostBtn.innerHTML = originalText;
        }, 2000);
      });
    });
  }

  // === Copiar hashtags ===
  if (copyHashtagsBtn) {
    copyHashtagsBtn.addEventListener('click', () => {
      const text = hashtagsContent.textContent;
      navigator.clipboard.writeText(text).then(() => {
        const originalText = copyHashtagsBtn.innerHTML;
        copyHashtagsBtn.innerHTML = '<i class="iconoir-check"></i> Copiado';
        setTimeout(() => {
          copyHashtagsBtn.innerHTML = originalText;
        }, 2000);
      });
    });
  }

  // === Regenerar publicación ===
  if (regeneratePostBtn) {
    regeneratePostBtn.addEventListener('click', () => {
      if (lastPrompt) {
        sendPrompt(lastPrompt, lastInputData, lastBusinessLine);
      }
    });
  }

  // === Variantes rápidas ===
  variantBtns.forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!lastGeneratedContent || !lastInputData.context) return;

      const variant = btn.dataset.variant;
      const variantInstructions = {
        'cercano': 'Reescribe esta publicación con un tono MÁS CERCANO y personal, como si hablaras directamente a un amigo. Mantén el mismo mensaje.',
        'institucional': 'Reescribe esta publicación con un tono MÁS INSTITUCIONAL y formal, más corporativo pero sin perder calidez. Mantén el mismo mensaje.',
        'corto': 'Reescribe esta publicación MÁS CORTA, condensando el mensaje sin perder lo esencial. Máximo 2-3 frases.',
        'directo': 'Reescribe esta publicación MÁS DIRECTA, yendo al grano desde el inicio. Sin rodeos ni introducciones.',
        'emocional': 'Reescribe esta publicación con un tono MÁS EMOCIONAL, que conecte con los sentimientos de la audiencia. Mantén el mismo mensaje.'
      };

      const variantPrompt = `${variantInstructions[variant]}

PUBLICACIÓN ORIGINAL:
"${lastGeneratedContent}"

HASHTAGS ORIGINALES (si existen):
"${lastHashtags}"

CONTEXTO ORIGINAL:
"${lastInputData.context}"

Devuelve SOLO el texto reescrito de la publicación, sin explicaciones ni marcadores. Mantén los hashtags al final si los había.`;

      // Actualizar input_data para variante
      const variantInputData = { ...lastInputData, variant };

      await sendPrompt(variantPrompt, variantInputData, lastBusinessLine, true);
    });
  });

  // === HISTORIAL ===
  loadHistory();
  
  // === CHECK FOR URL PARAMETER (from sidebar navigation) ===
  checkUrlParameter();
  
  function checkUrlParameter() {
    const urlParams = new URLSearchParams(window.location.search);
    const executionId = urlParams.get('id');
    
    if (executionId) {
      // Cargar el contenido automáticamente
      loadExecution(executionId);
      
      // Limpiar el parámetro de la URL sin recargar
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
            <i class="iconoir-send-diagonal text-xl text-slate-400"></i>
          </div>
          <p class="text-sm text-slate-500">Aún no has creado publicaciones</p>
          <p class="text-xs text-slate-400 mt-1">Usa el formulario para empezar</p>
        </div>
      `;
      return;
    }

    const channelIcons = {
      'instagram': 'iconoir-instagram',
      'facebook': 'iconoir-facebook',
      'linkedin': 'iconoir-linkedin',
      'transversal': 'iconoir-multi-window'
    };

    const businessColors = {
      'ebone': 'bg-blue-100 text-blue-700',
      'ebone-servicios': 'bg-blue-100 text-blue-700',
      'cubofit': 'bg-orange-100 text-orange-700',
      'uniges': 'bg-purple-100 text-purple-700',
      'cide': 'bg-emerald-100 text-emerald-700',
      'teia': 'bg-amber-100 text-amber-700'
    };

    historyList.innerHTML = items.map(item => {
      const inputData = item.input_data || {};
      const icon = channelIcons[inputData.channel] || 'iconoir-send-diagonal';
      const businessClass = businessColors[item.business_line] || 'bg-slate-100 text-slate-600';
      const businessLabel = businessLineMap[item.business_line] || item.business_line || '';
      const timeAgo = formatTimeAgo(new Date(item.created_at));

      return `
        <div class="history-item w-full p-3 hover:bg-slate-50 border-b border-slate-100 transition-colors group flex items-start gap-2" data-id="${item.id}">
          <i class="${icon} text-violet-500 mt-0.5"></i>
          <div class="flex-1 min-w-0 cursor-pointer history-item-main">
            <p class="text-sm font-medium text-slate-700 truncate group-hover:text-violet-600">${escapeHtml(item.title)}</p>
            <div class="flex items-center gap-2 mt-1">
              ${businessLabel ? `<span class="text-[10px] px-1.5 py-0.5 rounded ${businessClass}">${businessLabel}</span>` : ''}
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
        alert('Error al cargar la publicación');
        return;
      }

      const exec = data.execution;
      const parsed = parseResponse(exec.output_content);

      // Guardar datos
      lastInputData = exec.input_data || {};
      lastBusinessLine = exec.business_line || '';
      lastGeneratedContent = parsed.post;
      if ((parsed.hashtags || '').trim()) {
        lastHashtags = parsed.hashtags.trim();
      }

      // Rellenar formulario con los datos guardados
      fillFormFromData(lastInputData);

      // Mostrar contenido
      postContent.textContent = parsed.post;
      hashtagsContent.textContent = lastHashtags;

      // Resumen editorial
      renderEditorialSummary(lastInputData);
      editorialPanel.classList.remove('hidden');

      // Mostrar resultado, ocultar placeholder
      resultPlaceholder.classList.add('hidden');
      postResult.classList.remove('hidden');

    } catch (err) {
      alert('Error de conexión');
    }
  }

  // === Rellenar formulario desde datos guardados ===
  function fillFormFromData(data) {
    // Contexto
    const contextEl = document.getElementById('post-context');
    if (contextEl && data.context) {
      contextEl.value = data.context;
    }

    // Radio buttons
    const radioFields = [
      { name: 'intention', value: data.intention },
      { name: 'business-line', value: data.businessLine },
      { name: 'channel', value: data.channel },
      { name: 'narrative', value: data.narrative || '' },
      { name: 'length', value: data.length || '' },
      { name: 'closing', value: data.closing || '' }
    ];

    radioFields.forEach(field => {
      if (field.value !== undefined) {
        const radio = document.querySelector(`input[name="${field.name}"][value="${field.value}"]`);
        if (radio) {
          radio.checked = true;
        }
      }
    });
  }

  async function deleteExecution(id) {
    if (!confirm('¿Eliminar esta publicación del historial?')) return;

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
        alert('No se ha podido eliminar el elemento');
        return;
      }

      loadHistory();
    } catch (err) {
      alert('Error de conexión al eliminar');
    }
  }

  // Botón nueva publicación
  if (newPostBtn) {
    newPostBtn.addEventListener('click', () => {
      socialMediaForm.reset();
      postResult.classList.add('hidden');
      editorialPanel.classList.add('hidden');
      resultPlaceholder.classList.remove('hidden');
      lastGeneratedContent = '';
      lastHashtags = '';
      lastInputData = {};
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
})();
