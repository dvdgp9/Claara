-- Ebonia: Sistema de Compartición y Presencia en Tiempo Real
-- Migración 010
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: conversation_shares
-- Permisos de compartición para conversaciones individuales.
-- El propietario (owner) es siempre conversations.user_id, aquí se registran
-- los usuarios adicionales que tienen acceso.
-- ============================================================================
CREATE TABLE IF NOT EXISTS conversation_shares (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,           -- Usuario con quien se comparte
  shared_by_user_id BIGINT UNSIGNED NOT NULL, -- Usuario que otorgó el acceso
  can_write TINYINT(1) NOT NULL DEFAULT 1,    -- 0=solo lectura, 1=puede escribir
  created_at DATETIME NOT NULL,
  
  -- Evitar duplicados: un usuario solo puede tener un registro por conversación
  UNIQUE KEY conversation_shares_conv_user_uq (conversation_id, user_id),
  
  -- Índices para consultas frecuentes
  KEY conversation_shares_user_id_idx (user_id),
  KEY conversation_shares_shared_by_idx (shared_by_user_id),
  
  -- Foreign keys
  CONSTRAINT fk_conversation_shares_conversation_id FOREIGN KEY (conversation_id)
    REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_conversation_shares_user_id FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_conversation_shares_shared_by FOREIGN KEY (shared_by_user_id)
    REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: folder_shares
-- Permisos de compartición para carpetas completas.
-- Al compartir una carpeta, el usuario invitado ve todas las conversaciones
-- actuales y futuras de esa carpeta.
-- ============================================================================
CREATE TABLE IF NOT EXISTS folder_shares (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  folder_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,           -- Usuario con quien se comparte
  shared_by_user_id BIGINT UNSIGNED NOT NULL, -- Usuario que otorgó el acceso
  can_write TINYINT(1) NOT NULL DEFAULT 1,    -- 0=solo lectura, 1=puede escribir
  created_at DATETIME NOT NULL,
  
  -- Evitar duplicados
  UNIQUE KEY folder_shares_folder_user_uq (folder_id, user_id),
  
  -- Índices
  KEY folder_shares_user_id_idx (user_id),
  KEY folder_shares_shared_by_idx (shared_by_user_id),
  
  -- Foreign keys
  CONSTRAINT fk_folder_shares_folder_id FOREIGN KEY (folder_id)
    REFERENCES folders(id) ON DELETE CASCADE,
  CONSTRAINT fk_folder_shares_user_id FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_folder_shares_shared_by FOREIGN KEY (shared_by_user_id)
    REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: presence_states
-- Estados efímeros de presencia y escritura para colaboración en tiempo real.
-- Registros volátiles que se limpian automáticamente (TTL ~30s sin actividad).
-- ============================================================================
CREATE TABLE IF NOT EXISTS presence_states (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  conversation_id BIGINT UNSIGNED NOT NULL,
  is_typing TINYINT(1) NOT NULL DEFAULT 0,
  is_online TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL,               -- Se actualiza con cada señal de vida
  
  -- Un usuario solo puede tener un estado por conversación
  UNIQUE KEY presence_states_user_conv_uq (user_id, conversation_id),
  
  -- Índice para consultas de "quién está en esta conversación"
  KEY presence_states_conversation_id_idx (conversation_id),
  
  -- Índice para limpieza automática de registros antiguos
  KEY presence_states_updated_at_idx (updated_at),
  
  -- Foreign keys
  CONSTRAINT fk_presence_states_user_id FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_presence_states_conversation_id FOREIGN KEY (conversation_id)
    REFERENCES conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ÍNDICE ADICIONAL: Conversaciones compartidas por usuario
-- Para listar rápidamente "todas las conversaciones compartidas conmigo"
-- ============================================================================
-- (Ya cubierto por conversation_shares_user_id_idx)

-- ============================================================================
-- EVENTO DE LIMPIEZA (Opcional - requiere EVENT_SCHEDULER activado)
-- Elimina registros de presencia con más de 60 segundos de inactividad.
-- Ejecutar manualmente si el scheduler no está disponible:
--   DELETE FROM presence_states WHERE updated_at < NOW() - INTERVAL 60 SECOND;
-- ============================================================================
-- DELIMITER //
-- CREATE EVENT IF NOT EXISTS cleanup_stale_presence
-- ON SCHEDULE EVERY 30 SECOND
-- DO
-- BEGIN
--   DELETE FROM presence_states WHERE updated_at < NOW() - INTERVAL 60 SECOND;
-- END//
-- DELIMITER ;
