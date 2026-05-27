-- Gestor de Contexto: Tabla para tracking de documentos
-- Permite gestionar documentos de contexto para Lex (RAG), Eboniato (FAQ) y Ebonia (chat general)

CREATE TABLE IF NOT EXISTS context_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  target ENUM('lex', 'eboniato', 'ebonia') NOT NULL COMMENT 'Sistema al que pertenece el documento',
  filename VARCHAR(255) NOT NULL COMMENT 'Nombre del archivo en disco (sanitizado)',
  original_filename VARCHAR(255) NOT NULL COMMENT 'Nombre original del archivo subido',
  file_extension VARCHAR(10) NOT NULL COMMENT 'Extensión del archivo (pdf, txt, md)',
  file_size INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Tamaño en bytes',
  status ENUM('active', 'processing', 'error', 'pending') NOT NULL DEFAULT 'active',
  rag_status ENUM('not_applicable', 'pending', 'processing', 'processed', 'error') NOT NULL DEFAULT 'not_applicable' COMMENT 'Estado de procesamiento RAG (solo para Lex)',
  rag_chunk_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Número de chunks en Qdrant',
  rag_error_message TEXT NULL COMMENT 'Mensaje de error si rag_status=error',
  description TEXT NULL COMMENT 'Descripción opcional del documento',
  document_date DATE NULL COMMENT 'Document publication/effective date for conflict analysis',
  is_official_source TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether the document is marked as an official source',
  source_authority VARCHAR(255) NULL COMMENT 'Authority or organization behind the official source',
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_target (target),
  INDEX idx_status (status),
  INDEX idx_rag_status (rag_status),
  INDEX idx_document_date (document_date),
  INDEX idx_official_source (is_official_source),
  UNIQUE KEY unique_target_filename (target, filename),
  
  CONSTRAINT fk_context_documents_created_by 
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
