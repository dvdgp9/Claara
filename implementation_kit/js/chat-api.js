/**
 * Chat API Module
 * Handles all API calls for chat functionality
 */

import ChatState from './chat-state.js';

/**
 * Make an API request with CSRF token handling
 */
export async function api(path, opts = {}) {
  const res = await fetch(path, {
    method: opts.method || 'GET',
    headers: {
      'Content-Type': 'application/json',
      ...(ChatState.csrf ? { 'X-CSRF-Token': ChatState.csrf } : {})
    },
    body: opts.body ? JSON.stringify(opts.body) : undefined,
    credentials: 'include',
    signal: opts.signal // Support AbortController
  });
  
  const data = await res.json().catch(() => ({}));
  
  // If CSRF error, try to refresh token once
  if (res.status === 403 && data?.error?.code === 'csrf_invalid' && !opts._retry) {
    try {
      const meRes = await fetch('/api/auth/me.php', { credentials: 'include' });
      if (meRes.ok) {
        const meData = await meRes.json();
        ChatState.csrf = meData.csrf_token || null;
        if (ChatState.csrf) {
          if (typeof window.CSRF_TOKEN !== 'undefined') window.CSRF_TOKEN = ChatState.csrf;
          return api(path, { ...opts, _retry: true });
        }
      }
    } catch (e) {
      console.error('Error refreshing CSRF:', e);
    }
  }

  if (!res.ok) {
    if (res.status === 401) {
      window.location.href = '/login.php';
      return;
    }
    throw new Error(data?.error?.message || res.statusText);
  }
  return data;
}

/**
 * Convert file to base64
 */
export function fileToBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      const base64 = reader.result.split(',')[1];
      resolve(base64);
    };
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}

/**
 * Regenerate a specific part of a message
 */
export async function regenerateSelection(messageId, originalContent, selectedText, instructions) {
  return api('/api/chat-regenerate.php', {
    method: 'POST',
    body: {
      message_id: messageId,
      conversation_id: ChatState.currentConversationId,
      original_content: originalContent,
      selected_text: selectedText,
      instructions: instructions
    }
  });
}

export default { api, fileToBase64, regenerateSelection };
