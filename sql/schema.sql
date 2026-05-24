CREATE DATABASE IF NOT EXISTS reto_asturias_activa
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE reto_asturias_activa;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS challenge_participants;
DROP TABLE IF EXISTS challenges;
DROP TABLE IF EXISTS user_achievements;
DROP TABLE IF EXISTS achievements;
DROP TABLE IF EXISTS route_completions;
DROP TABLE IF EXISTS route_favorites;
DROP TABLE IF EXISTS route_reports;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS premium_payments;
DROP TABLE IF EXISTS premium_subscriptions;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS email_change_tokens;
DROP TABLE IF EXISTS email_verification_tokens;
DROP TABLE IF EXISTS routes;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  email_verified_at DATETIME DEFAULT NULL,
  avatar_url VARCHAR(255) DEFAULT NULL,
  total_points INT NOT NULL DEFAULT 0,
  level INT NOT NULL DEFAULT 1,
  is_premium TINYINT(1) NOT NULL DEFAULT 0,
  premium_plan ENUM('free','monthly') NOT NULL DEFAULT 'free',
  premium_price_month DECIMAL(6,2) NOT NULL DEFAULT 4.00,
  premium_started_at DATETIME DEFAULT NULL,
  premium_expires_at DATETIME DEFAULT NULL,
  premium_auto_renew TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_points (total_points),
  INDEX idx_users_active (is_active),
  INDEX idx_users_premium (is_premium, premium_expires_at)
) ENGINE=InnoDB;

CREATE TABLE email_verification_tokens (
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

CREATE TABLE email_change_tokens (
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

CREATE TABLE password_reset_tokens (
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

CREATE TABLE routes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  zone VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  distance_km DECIMAL(6,2) NOT NULL,
  elevation_m INT NOT NULL,
  difficulty ENUM('Baja','Media','Alta','Muy Alta') NOT NULL,
  activity_type VARCHAR(50) NOT NULL DEFAULT 'Senderismo',
  base_points INT NOT NULL,
  cover_image VARCHAR(255) DEFAULT NULL,
  coordinates_json JSON NOT NULL,
  is_preloaded TINYINT(1) NOT NULL DEFAULT 1,
  submission_status ENUM('approved','pending','rejected') NOT NULL DEFAULT 'approved',
  review_note VARCHAR(255) DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  reviewed_by INT DEFAULT NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_routes_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_routes_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_routes_difficulty (difficulty),
  INDEX idx_routes_distance (distance_km),
  INDEX idx_routes_zone (zone),
  INDEX idx_routes_status (submission_status)
) ENGINE=InnoDB;

CREATE TABLE route_completions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  route_id INT NOT NULL,
  completed_at DATETIME NOT NULL,
  duration_min INT NOT NULL,
  points_obtained INT NOT NULL,
  notes TEXT DEFAULT NULL,
  gpx_filename VARCHAR(255) DEFAULT NULL,
  CONSTRAINT fk_route_completions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_route_completions_route FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  UNIQUE KEY uq_route_completion_once (user_id, route_id),
  INDEX idx_route_completions_completed (completed_at),
  INDEX idx_route_completions_user (user_id),
  INDEX idx_route_completions_route (route_id)
) ENGINE=InnoDB;

CREATE TABLE route_favorites (
  user_id INT NOT NULL,
  route_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, route_id),
  CONSTRAINT fk_route_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_route_favorites_route FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_route_favorites_route (route_id)
) ENGINE=InnoDB;

CREATE TABLE premium_subscriptions (
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

CREATE TABLE premium_payments (
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

CREATE TABLE notifications (
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

CREATE TABLE route_reports (
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

CREATE TABLE achievements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  description VARCHAR(255) NOT NULL,
  criteria_points INT NOT NULL DEFAULT 0,
  criteria_routes INT NOT NULL DEFAULT 0,
  bonus_points INT NOT NULL DEFAULT 0,
  icon VARCHAR(80) NOT NULL DEFAULT '🏅',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE user_achievements (
  user_id INT NOT NULL,
  achievement_id INT NOT NULL,
  awarded_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, achievement_id),
  CONSTRAINT fk_user_achievements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_user_achievements_achievement FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE challenges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  target_type ENUM('distance_km','routes_count','points') NOT NULL,
  target_value DECIMAL(10,2) NOT NULL,
  reward_points INT NOT NULL DEFAULT 0,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_challenges_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_challenges_dates (start_date, end_date),
  INDEX idx_challenges_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE challenge_participants (
  challenge_id INT NOT NULL,
  user_id INT NOT NULL,
  progress_value DECIMAL(10,2) NOT NULL DEFAULT 0,
  joined_at DATETIME NOT NULL,
  completed_at DATETIME DEFAULT NULL,
  PRIMARY KEY (challenge_id, user_id),
  CONSTRAINT fk_challenge_participants_challenge FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_challenge_participants_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE comments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  route_id INT NOT NULL,
  user_id INT NOT NULL,
  rating TINYINT NOT NULL,
  comment_text TEXT NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  moderated_at DATETIME DEFAULT NULL,
  moderated_by INT DEFAULT NULL,
  CONSTRAINT fk_comments_route FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_comments_moderator FOREIGN KEY (moderated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CHECK (rating BETWEEN 1 AND 5),
  INDEX idx_comments_status (status),
  INDEX idx_comments_route (route_id)
) ENGINE=InnoDB;
