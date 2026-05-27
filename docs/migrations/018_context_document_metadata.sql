-- Add source metadata used by Lex conflict analysis.

ALTER TABLE context_documents
  ADD COLUMN document_date DATE NULL AFTER description,
  ADD COLUMN is_official_source TINYINT(1) NOT NULL DEFAULT 0 AFTER document_date,
  ADD COLUMN source_authority VARCHAR(255) NULL AFTER is_official_source,
  ADD INDEX idx_context_documents_document_date (document_date),
  ADD INDEX idx_context_documents_official_source (is_official_source);
