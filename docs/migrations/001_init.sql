-- Ebonia DB Init (MVP)
-- Charset/Collation
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Schema migrations registry
CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL,
  executed_at DATETIME NOT NULL,
  UNIQUE KEY schema_migrations_filename_uq (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Companies
CREATE TABLE IF NOT EXISTS companies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(150) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY companies_slug_uq (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Departments
CREATE TABLE IF NOT EXISTS departments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY departments_company_id_idx (company_id),
  UNIQUE KEY departments_slug_uq (slug),
  CONSTRAINT fk_departments_company_id FOREIGN KEY (company_id)
    REFERENCES companies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NULL,
  department_id BIGINT UNSIGNED NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(120) NOT NULL,
  locale VARCHAR(10) NULL,
  is_superadmin TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY users_email_uq (email),
  KEY users_company_id_idx (company_id),
  KEY users_department_id_idx (department_id),
  CONSTRAINT fk_users_company_id FOREIGN KEY (company_id)
    REFERENCES companies(id) ON DELETE SET NULL,
  CONSTRAINT fk_users_department_id FOREIGN KEY (department_id)
    REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RBAC: roles
CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY roles_slug_uq (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RBAC: permissions
CREATE TABLE IF NOT EXISTS permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY permissions_slug_uq (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RBAC: user_roles
CREATE TABLE IF NOT EXISTS user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, role_id),
  KEY user_roles_role_id_idx (role_id),
  CONSTRAINT fk_user_roles_user_id FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_roles_role_id FOREIGN KEY (role_id)
    REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RBAC: role_permissions
CREATE TABLE IF NOT EXISTS role_permissions (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  KEY role_permissions_permission_id_idx (permission_id),
  CONSTRAINT fk_role_permissions_role_id FOREIGN KEY (role_id)
    REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_role_permissions_permission_id FOREIGN KEY (permission_id)
    REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Folders
CREATE TABLE IF NOT EXISTS folders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY folders_user_id_idx (user_id),
  KEY folders_parent_id_idx (parent_id),
  CONSTRAINT fk_folders_user_id FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_folders_parent_id FOREIGN KEY (parent_id)
    REFERENCES folders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Voices (assistants)
CREATE TABLE IF NOT EXISTS voices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(300) NULL,
  provider ENUM('gemini','openai','qwen','other') NOT NULL DEFAULT 'gemini',
  model VARCHAR(120) NOT NULL,
  system_prompt TEXT NULL,
  visibility ENUM('global','company','department','user') NOT NULL DEFAULT 'global',
  scope_company_id BIGINT UNSIGNED NULL,
  scope_department_id BIGINT UNSIGNED NULL,
  scope_user_id BIGINT UNSIGNED NULL,
  temperature DECIMAL(3,2) NULL,
  top_p DECIMAL(3,2) NULL,
  max_output_tokens INT NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY voices_scope_company_id_idx (scope_company_id),
  KEY voices_scope_department_id_idx (scope_department_id),
  KEY voices_scope_user_id_idx (scope_user_id),
  CONSTRAINT fk_voices_scope_company_id FOREIGN KEY (scope_company_id)
    REFERENCES companies(id) ON DELETE SET NULL,
  CONSTRAINT fk_voices_scope_department_id FOREIGN KEY (scope_department_id)
    REFERENCES departments(id) ON DELETE SET NULL,
  CONSTRAINT fk_voices_scope_user_id FOREIGN KEY (scope_user_id)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conversations
CREATE TABLE IF NOT EXISTS conversations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  folder_id BIGINT UNSIGNED NULL,
  voice_id BIGINT UNSIGNED NULL,
  company_id BIGINT UNSIGNED NULL,
  title VARCHAR(200) NOT NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  metadata JSON NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY conversations_user_id_idx (user_id),
  KEY conversations_folder_id_idx (folder_id),
  KEY conversations_voice_id_idx (voice_id),
  CONSTRAINT fk_conversations_user_id FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_conversations_folder_id FOREIGN KEY (folder_id)
    REFERENCES folders(id) ON DELETE SET NULL,
  CONSTRAINT fk_conversations_voice_id FOREIGN KEY (voice_id)
    REFERENCES voices(id) ON DELETE SET NULL,
  CONSTRAINT fk_conversations_company_id FOREIGN KEY (company_id)
    REFERENCES companies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages
CREATE TABLE IF NOT EXISTS messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  role ENUM('user','assistant','system') NOT NULL,
  content LONGTEXT NOT NULL,
  model VARCHAR(120) NULL,
  input_tokens INT NULL,
  output_tokens INT NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL,
  KEY messages_conversation_id_idx (conversation_id),
  KEY messages_user_id_idx (user_id),
  CONSTRAINT fk_messages_conversation_id FOREIGN KEY (conversation_id)
    REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_user_id FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Voices (assistants)
CREATE TABLE IF NOT EXISTS voices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(300) NULL,
  provider ENUM('gemini','openai','qwen','other') NOT NULL DEFAULT 'gemini',
  model VARCHAR(120) NOT NULL,
  system_prompt TEXT NULL,
  visibility ENUM('global','company','department','user') NOT NULL DEFAULT 'global',
  scope_company_id BIGINT UNSIGNED NULL,
  scope_department_id BIGINT UNSIGNED NULL,
  scope_user_id BIGINT UNSIGNED NULL,
  temperature DECIMAL(3,2) NULL,
  top_p DECIMAL(3,2) NULL,
  max_output_tokens INT NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY voices_scope_company_id_idx (scope_company_id),
  KEY voices_scope_department_id_idx (scope_department_id),
  KEY voices_scope_user_id_idx (scope_user_id),
  CONSTRAINT fk_voices_scope_company_id FOREIGN KEY (scope_company_id)
    REFERENCES companies(id) ON DELETE SET NULL,
  CONSTRAINT fk_voices_scope_department_id FOREIGN KEY (scope_department_id)
    REFERENCES departments(id) ON DELETE SET NULL,
  CONSTRAINT fk_voices_scope_user_id FOREIGN KEY (scope_user_id)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gestures (quick actions)
CREATE TABLE IF NOT EXISTS gestures (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(300) NULL,
  prompt_template TEXT NOT NULL,
  config JSON NULL,
  provider ENUM('gemini','openai','qwen','other') NOT NULL DEFAULT 'gemini',
  model VARCHAR(120) NOT NULL,
  visibility ENUM('global','company','department','user') NOT NULL DEFAULT 'global',
  scope_company_id BIGINT UNSIGNED NULL,
  scope_department_id BIGINT UNSIGNED NULL,
  scope_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY gestures_scope_company_id_idx (scope_company_id),
  KEY gestures_scope_department_id_idx (scope_department_id),
  KEY gestures_scope_user_id_idx (scope_user_id),
  CONSTRAINT fk_gestures_scope_company_id FOREIGN KEY (scope_company_id)
    REFERENCES companies(id) ON DELETE SET NULL,
  CONSTRAINT fk_gestures_scope_department_id FOREIGN KEY (scope_department_id)
    REFERENCES departments(id) ON DELETE SET NULL,
  CONSTRAINT fk_gestures_scope_user_id FOREIGN KEY (scope_user_id)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
