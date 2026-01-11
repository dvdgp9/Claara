/**
 * Chat State Management Module
 * Centralized state for chat functionality
 */

export const ChatState = {
  csrf: null,
  currentConversationId: null,
  emptyConversationId: null,
  currentUser: null,
  currentConvTitle: null,
  currentFile: null,
  currentFileEmpty: null,
  currentFolderId: -1,
  allFolders: [],
  conversationToMove: null,
  imageMode: false,
  webSearchMode: false,
  
  // Streaming state
  isGenerating: false,
  abortController: null,
  currentStreamingMessageId: null,
  
  // Selection editing state
  selectedText: null,
  selectedMessageId: null,
  selectionRange: null,
  
  // Reset streaming state
  resetStreaming() {
    this.isGenerating = false;
    if (this.abortController) {
      this.abortController.abort();
      this.abortController = null;
    }
    this.currentStreamingMessageId = null;
  },
  
  // Reset selection state
  resetSelection() {
    this.selectedText = null;
    this.selectedMessageId = null;
    this.selectionRange = null;
  }
};

export default ChatState;
