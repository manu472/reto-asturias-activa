USE reto_asturias_activa;

SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS email_verified_at DATETIME DEFAULT NULL AFTER password;

CREATE TABLE IF NOT EXISTS email_verification_tokens (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_verification_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_email_verification_tokens_user (user_id),
  INDEX idx_email_verification_tokens_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS email_change_tokens (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  new_email VARCHAR(150) NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_change_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_email_change_tokens_user (user_id),
  INDEX idx_email_change_tokens_new_email (new_email),
  INDEX idx_email_change_tokens_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_password_reset_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_password_reset_tokens_user (user_id),
  INDEX idx_password_reset_tokens_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS route_favorites (
  user_id INT NOT NULL,
  route_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, route_id),
  CONSTRAINT fk_route_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_route_favorites_route FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_route_favorites_route (route_id)
) ENGINE=InnoDB;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS is_premium TINYINT(1) NOT NULL DEFAULT 0 AFTER level,
  ADD COLUMN IF NOT EXISTS premium_plan ENUM('free','monthly') NOT NULL DEFAULT 'free' AFTER is_premium,
  ADD COLUMN IF NOT EXISTS premium_price_month DECIMAL(6,2) NOT NULL DEFAULT 4.00 AFTER premium_plan,
  ADD COLUMN IF NOT EXISTS premium_started_at DATETIME DEFAULT NULL AFTER premium_price_month,
  ADD COLUMN IF NOT EXISTS premium_expires_at DATETIME DEFAULT NULL AFTER premium_started_at,
  ADD COLUMN IF NOT EXISTS premium_auto_renew TINYINT(1) NOT NULL DEFAULT 0 AFTER premium_expires_at;

SET @has_users_premium_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND INDEX_NAME = 'idx_users_premium'
);
SET @sql := IF(@has_users_premium_idx = 0, 'CREATE INDEX idx_users_premium ON users(is_premium, premium_expires_at)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS premium_subscriptions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  plan_type ENUM('monthly') NOT NULL DEFAULT 'monthly',
  status ENUM('active','canceled','expired') NOT NULL DEFAULT 'active',
  price_month DECIMAL(6,2) NOT NULL DEFAULT 4.00,
  started_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  canceled_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_premium_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_premium_subscriptions_user (user_id),
  INDEX idx_premium_subscriptions_status (status, ends_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS premium_payments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  stripe_checkout_session_id VARCHAR(120) NOT NULL UNIQUE,
  stripe_payment_intent_id VARCHAR(120) DEFAULT NULL,
  amount_eur DECIMAL(8,2) NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'eur',
  status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  paid_at DATETIME DEFAULT NULL,
  raw_payload JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_premium_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_premium_payments_user (user_id),
  INDEX idx_premium_payments_status (status, paid_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  notification_type VARCHAR(40) NOT NULL,
  title VARCHAR(140) NOT NULL,
  message VARCHAR(500) NOT NULL,
  link_url VARCHAR(255) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME DEFAULT NULL,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_notifications_user_created (user_id, created_at),
  INDEX idx_notifications_user_read (user_id, is_read)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS route_reports (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  route_id INT NOT NULL,
  user_id INT NOT NULL,
  reason ENUM('senalizacion','seguridad','acceso','estado_camino','datos_erroneos','otro') NOT NULL,
  details TEXT NOT NULL,
  status ENUM('pending','resolved','rejected') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME DEFAULT NULL,
  reviewed_by INT DEFAULT NULL,
  CONSTRAINT fk_route_reports_route FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_route_reports_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_route_reports_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_route_reports_status (status),
  INDEX idx_route_reports_route (route_id),
  INDEX idx_route_reports_user (user_id)
) ENGINE=InnoDB;

ALTER TABLE routes
  ADD COLUMN IF NOT EXISTS submission_status ENUM('approved','pending','rejected') NOT NULL DEFAULT 'approved' AFTER is_preloaded,
  ADD COLUMN IF NOT EXISTS review_note VARCHAR(255) DEFAULT NULL AFTER submission_status,
  ADD COLUMN IF NOT EXISTS reviewed_at DATETIME DEFAULT NULL AFTER review_note,
  ADD COLUMN IF NOT EXISTS reviewed_by INT DEFAULT NULL AFTER reviewed_at;

SET @has_routes_status_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'routes'
    AND INDEX_NAME = 'idx_routes_status'
);
SET @sql := IF(@has_routes_status_idx = 0, 'CREATE INDEX idx_routes_status ON routes(submission_status)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk_routes_reviewer := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'routes'
    AND CONSTRAINT_NAME = 'fk_routes_reviewer'
);
SET @sql := IF(
  @has_fk_routes_reviewer = 0,
  'ALTER TABLE routes ADD CONSTRAINT fk_routes_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE routes
SET submission_status = 'approved'
WHERE submission_status IS NULL OR submission_status = '';

UPDATE users
SET email_verified_at = NOW()
WHERE email_verified_at IS NULL
  AND email IN ('admin@retoasturiasactiva.es', 'usuario@retoasturiasactiva.es');
