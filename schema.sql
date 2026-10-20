CREATE DATABASE IF NOT EXISTS lead_panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE lead_panel;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  theme_pref VARCHAR(20) NOT NULL DEFAULT 'dark',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_leaderboards (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  player_nick VARCHAR(50) NOT NULL,
  game_key VARCHAR(50) NOT NULL DEFAULT 'dgrwebio',
  high_score INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  action VARCHAR(100) NOT NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO game_leaderboards (player_nick, game_key, high_score) VALUES
('DGRWEB_KRAL', 'dgrwebio', 48500),
('CyberNinja_X', 'dgrwebio', 36200),
('NeoBlob', 'dgrwebio', 28900),
('MatrixPro', 'dgrwebio', 21400),
('SiberKurt', 'dgrwebio', 17800),
('AlphaTitan', 'dgrwebio', 15200),
('VortexPlayer', 'dgrwebio', 12900),
('QuantumBlob', 'dgrwebio', 9800),
('ShadowHunter', 'dgrwebio', 7400),
('Gamer007', 'dgrwebio', 5100);
