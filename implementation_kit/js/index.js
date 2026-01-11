/**
 * Chat Module Entry Point
 * Exports all chat functionality
 */

export { ChatState } from './chat-state.js';
export { api, fileToBase64, regenerateSelection } from './chat-api.js';
export { streamChat, stopGeneration, isGenerating } from './chat-streaming.js';
export { initSelection, hideToolbar, hideEditModal, makeMessageSelectable } from './chat-selection.js';
export { 
  escapeHtml, 
  mdToHtml, 
  createMessageElement, 
  updateStreamingMessage, 
  finalizeStreamingMessage,
  createStopButton 
} from './chat-ui.js';
