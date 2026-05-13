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
    'brand': 'Brand',
    'service': 'Service',
    'product': 'Product',
    'team': 'Team',
    'campaign': 'Campaign',
    'community': 'Community'
  };

  const intentionMap = {
    'informar': 'Inform',
    'reforzar-marca': 'Reinforce brand',
    'conectar': 'Emotional connection',
    'activar': 'Activate interest',
    'aportar-valor': 'Deliver value / explain'
  };

  const channelMap = {
    'instagram': 'Instagram',
    'facebook': 'Facebook',
    'linkedin': 'LinkedIn',
    'transversal': 'Cross-channel text'
  };

  const narrativeMap = {
    '': 'Automatic',
    'personas': 'People / team',
    'proyecto': 'Project / action',
    'detalle': 'Differentiating detail',
    'impacto': 'User impact',
    'vision': 'Vision / purpose'
  };

  const lengthMap = {
    '': 'Automatic',
    'corto': 'Short (quick impact)',
    'medio': 'Medium (balanced)',
    'largo': 'Long (full development)'
  };

  const closingMap = {
    '': 'Automatic',
    'informativo': 'Informative',
    'inspirador': 'Inspirational',
    'cta-suave': 'Soft CTA',
    'cta-claro': 'Clear CTA'
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
      alert('Please describe what the post is about');
      return;
    }

    const intention = document.querySelector('input[name="intention"]:checked')?.value || 'informar';
    const businessLine = document.querySelector('input[name="business-line"]:checked')?.value || 'brand';
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
      console.warn('Could not fetch recent posts:', err);
    }
    return [];
  }

  // === Build prompt ===
  function buildPrompt(data, businessName, recentPosts = []) {
    const { context, intention, channel, narrative, length, closing } = data;

    // Instructions by intent
    const intentionInstructions = {
      'informar': 'Goal: INFORM. Communicate a fact, update, or announcement clearly and directly.',
      'reforzar-marca': 'Goal: REINFORCE BRAND. Highlight values, identity, and differentiation.',
      'conectar': 'Goal: CONNECT EMOTIONALLY. Build closeness, empathy, and audience identification.',
      'activar': 'Goal: ACTIVATE INTEREST. Spark curiosity and motivate the audience to learn more or act.',
      'aportar-valor': 'Goal: DELIVER VALUE. Educate, explain, or share useful knowledge.'
    };

    // Instructions by channel
    const channelInstructions = {
      'instagram': `For INSTAGRAM:
- Visual, close, and dynamic tone
- Strong opening that hooks immediately
- Emojis in moderation for rhythm
- Line breaks for mobile readability
- Max 2200 characters (ideal: 150-300 for feed, can be longer for carousel)`,
      'facebook': `For FACEBOOK:
- Conversational and accessible tone
- Can be longer than Instagram
- Invite interaction (comments, shares)
- Questions or reflections work well`,
      'linkedin': `For LINKEDIN:
- Professional but human tone
- Provide value or sector perspective
- May include data or achievements
- Use short paragraphs
- Avoid excessive emojis (1-2 max when appropriate)`,
      'transversal': `CROSS-CHANNEL text (adaptable):
- Neutral style that works across channels
- Neither too informal nor too corporate
- No channel-specific emojis or elements
- Medium, adaptable length`
    };

    // Narrative focus
    let narrativeInstruction = '';
    if (narrative) {
      const narrativeTexts = {
        'personas': 'PEOPLE-LED FOCUS: tell the story by highlighting the people or team who make it possible.',
        'proyecto': 'PROJECT-LED FOCUS: center the message on what is being done or achieved.',
        'detalle': 'DETAIL-LED FOCUS: highlight what makes this unique or special.',
        'impacto': 'IMPACT-LED FOCUS: explain how this positively affects users, citizens, or community.',
        'vision': 'VISION-LED FOCUS: connect the message with broader purpose and organizational values.'
      };
      narrativeInstruction = `\n${narrativeTexts[narrative]}`;
    } else {
      narrativeInstruction = '\nInfer the best narrative focus based on context and intent.';
    }

    // Length
    let lengthInstruction = '';
    if (length) {
      const lengthTexts = {
        'corto': 'SHORT LENGTH: brief copy, quick impact, 1-3 sentences.',
        'medio': 'MEDIUM LENGTH: balanced development, 3-5 sentences or short paragraphs.',
        'largo': 'LONG LENGTH: full development, allows context and details.'
      };
      lengthInstruction = `\n${lengthTexts[length]}`;
    } else {
      lengthInstruction = '\nChoose the optimal length based on content and channel.';
    }

    // Closing
    let closingInstruction = '';
    if (closing) {
      const closingTexts = {
        'informativo': 'INFORMATIVE CLOSING: end with an extra data point or factual conclusion.',
        'inspirador': 'INSPIRATIONAL CLOSING: end with a motivating reflection or message.',
        'cta-suave': 'SOFT CTA CLOSING: subtly invite action (e.g., "Learn more at...", "What do you think?").',
        'cta-claro': 'CLEAR CTA CLOSING: direct and explicit call to action.'
      };
      closingInstruction = `\n${closingTexts[closing]}`;
    } else {
      closingInstruction = '\nChoose the most suitable closing style based on intent and channel.';
    }

    // Instructions by brand/context.
    const businessInstructions = {
      'brand': `BRAND STYLE:
- Professional, close, and clear
- Confident without sounding cold
- Use first-person plural when appropriate`,
      'service': `SERVICE STYLE:
- Practical, helpful, and trustworthy
- Focus on customer value and clarity
- Avoid vague claims`,
      'product': `PRODUCT STYLE:
- Energetic, modern, and specific
- Highlight benefits, use cases, and differentiation
- Keep the message concrete`,
      'team': `TEAM STYLE:
- Human, collaborative, and operational
- Highlight people, process, and impact`,
      'campaign': `CAMPAIGN STYLE:
- Direct, memorable, and action-oriented
- Keep the main message easy to repeat`,
      'community': `COMMUNITY STYLE:
- Warm, accessible, and inclusive
- Connect the message to shared value and participation`
    };

    return `You are the community manager for ${businessName}. Create a social media post.

CONTEXTO DE LA PUBLICACIÓN:
"${context}"

${intentionInstructions[intention]}

${channelInstructions[channel]}

${businessInstructions[data.businessLine]}
${narrativeInstruction}
${lengthInstruction}
${closingInstruction}

RESPONSE FORMAT:
Return the post in the following exact format:

---POST---
[Paste-ready post text here]

---HASHTAGS---
[Relevant hashtags separated by spaces, between 5-10]

---FIN---
${recentPosts.length > 0 ? `
PREVIOUS POSTS FROM THIS BRAND (to avoid repetition):
${recentPosts.map((post, i) => `${i + 1}. "${post.substring(0, 200)}${post.length > 200 ? '...' : ''}"`).join('\n')}

Use these posts to:
- NOT repeat similar phrases, structures, or openings
- Vary style and approach
- Keep freshness and originality` : ''}

IMPORTANT:
- The text must be publish-ready, with no explanations or comments.
- Do not invent data, dates, names, or figures not present in context.
- Hashtags must be relevant: some brand, some sector, some positioning.
- If concrete details are missing, write so no obvious gaps remain.`;
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
        alert('Error generating the post: ' + (data.error?.message || 'Unknown error'));
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
      alert('Connection error while generating the post');
    }
  }

  // === Parsear respuesta del LLM ===
  function parseResponse(content) {
    let post = content;
    let hashtags = '';

    // Intentar extraer partes estructuradas
    const postMatch = content.match(/---POST---\s*([\s\S]*?)\s*---HASHTAGS---/);
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
    post = post.replace(/---POST---|---HASHTAGS---|---FIN---/g, '').trim();

    return { post, hashtags };
  }

  // === Renderizar resumen editorial ===
  function renderEditorialSummary(data) {
    const items = [
      { label: 'Intent', value: intentionMap[data.intention] || data.intention },
      { label: 'Line', value: businessLineMap[data.businessLine] || data.businessLine },
      { label: 'Channel', value: channelMap[data.channel] || data.channel },
      { label: 'Focus', value: narrativeMap[data.narrative] || 'Automatic' },
      { label: 'Length', value: lengthMap[data.length] || 'Automatic' },
      { label: 'Closing', value: closingMap[data.closing] || 'Automatic' }
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
        copyPostBtn.innerHTML = '<i class="iconoir-check"></i> Copied';
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
        copyHashtagsBtn.innerHTML = '<i class="iconoir-check"></i> Copied';
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
        'cercano': 'Rewrite this post with a MORE CONVERSATIONAL and personal tone, as if speaking directly to a friend. Keep the same message.',
        'institucional': 'Rewrite this post with a MORE FORMAL and institutional tone, more corporate but still warm. Keep the same message.',
        'corto': 'Rewrite this post SHORTER, condensing the message without losing the core point. Maximum 2-3 sentences.',
        'directo': 'Rewrite this post MORE DIRECTLY, getting to the point from the start. No detours or long introductions.',
        'emocional': 'Rewrite this post with a MORE EMOTIONAL tone that connects with the audience’s feelings. Keep the same message.'
      };

      const variantPrompt = `${variantInstructions[variant]}

ORIGINAL POST:
"${lastGeneratedContent}"

ORIGINAL HASHTAGS (if any):
"${lastHashtags}"

ORIGINAL CONTEXT:
"${lastInputData.context}"

Return ONLY the rewritten post text, with no explanations or markers. Keep hashtags at the end if they existed.`;

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
        historyList.innerHTML = '<div class="p-4 text-center text-red-500 text-sm">Could not load</div>';
        return;
      }

      renderHistory(data.items || []);
    } catch (err) {
      historyList.innerHTML = '<div class="p-4 text-center text-red-500 text-sm">Connection error</div>';
    }
  }

  function renderHistory(items) {
    if (items.length === 0) {
      historyList.innerHTML = `
        <div class="p-6 text-center">
          <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
            <i class="iconoir-send-diagonal text-xl text-slate-400"></i>
          </div>
          <p class="text-sm text-slate-500">You have not created posts yet</p>
          <p class="text-xs text-slate-400 mt-1">Use the form to get started</p>
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
      'brand': 'bg-blue-100 text-blue-700',
      'service': 'bg-blue-100 text-blue-700',
      'product': 'bg-orange-100 text-orange-700',
      'team': 'bg-purple-100 text-purple-700',
      'campaign': 'bg-emerald-100 text-emerald-700',
      'community': 'bg-amber-100 text-amber-700'
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
          <button class="history-item-delete opacity-0 group-hover:opacity-100 transition-opacity text-slate-300 hover:text-red-500 p-1 rounded" title="Delete">
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
        alert('Error loading the post');
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
      alert('Connection error');
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
    if (!confirm('Delete this post from history?')) return;

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
        alert('Could not delete the item');
        return;
      }

      loadHistory();
    } catch (err) {
      alert('Connection error while deleting');
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

    if (diffMins < 1) return 'now';
    if (diffMins < 60) return `${diffMins} min ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays === 1) return 'yesterday';
    if (diffDays < 7) return `${diffDays} days ago`;
    return date.toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
})();
