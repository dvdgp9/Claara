/**
 * Chat Streaming Module
 * Handles SSE streaming responses with abort support
 */

import ChatState from './chat-state.js';

/**
 * Stream a chat response using Server-Sent Events
 * @param {Object} params - Request parameters
 * @param {Function} onChunk - Callback for each text chunk
 * @param {Function} onComplete - Callback when streaming completes
 * @param {Function} onError - Callback on error
 * @returns {AbortController} - Controller to abort the stream
 */
export function streamChat(params, onChunk, onComplete, onError) {
  // Create new AbortController
  const controller = new AbortController();
  ChatState.abortController = controller;
  ChatState.isGenerating = true;
  
  const body = JSON.stringify(params);
  
  fetch('/api/chat-stream.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': ChatState.csrf || ''
    },
    body: body,
    credentials: 'include',
    signal: controller.signal
  })
  .then(async response => {
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}));
      throw new Error(errorData?.error?.message || `HTTP ${response.status}`);
    }
    
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let fullText = '';
    let messageId = null;
    let model = null;
    let images = null;
    let annotations = null;
    
    while (true) {
      const { done, value } = await reader.read();
      
      if (done) {
        ChatState.isGenerating = false;
        ChatState.abortController = null;
        onComplete({
          content: fullText,
          messageId,
          model,
          images,
          annotations
        });
        break;
      }
      
      buffer += decoder.decode(value, { stream: true });
      
      // Process complete lines
      const lines = buffer.split('\n');
      buffer = lines.pop() || '';
      
      for (const line of lines) {
        if (line.startsWith('data: ')) {
          const data = line.slice(6);
          
          if (data === '[DONE]') {
            continue;
          }
          
          try {
            const json = JSON.parse(data);
            
            // Handle different event types
            if (json.type === 'chunk' && json.content) {
              fullText += json.content;
              onChunk(json.content, fullText);
            } else if (json.type === 'meta') {
              messageId = json.message_id;
              model = json.model;
            } else if (json.type === 'images') {
              images = json.images;
            } else if (json.type === 'annotations') {
              annotations = json.annotations;
            } else if (json.type === 'error') {
              throw new Error(json.message || 'Streaming error');
            } else if (json.type === 'conversation') {
              // New conversation created
              ChatState.currentConversationId = json.id;
            }
          } catch (e) {
            if (e.name !== 'SyntaxError') {
              console.error('Stream parse error:', e);
            }
          }
        }
      }
    }
  })
  .catch(error => {
    ChatState.isGenerating = false;
    ChatState.abortController = null;
    
    if (error.name === 'AbortError') {
      // User cancelled - this is expected
      onComplete({
        content: '',
        cancelled: true
      });
    } else {
      onError(error);
    }
  });
  
  return controller;
}

/**
 * Stop the current generation
 */
export function stopGeneration() {
  if (ChatState.abortController) {
    ChatState.abortController.abort();
    ChatState.abortController = null;
  }
  ChatState.isGenerating = false;
}

/**
 * Check if currently generating
 */
export function isGenerating() {
  return ChatState.isGenerating;
}

export default { streamChat, stopGeneration, isGenerating };
