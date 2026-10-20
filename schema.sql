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

CREATE TABLE IF NOT EXISTS game_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(50) NOT NULL UNIQUE,
  setting_value VARCHAR(255) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_leaderboards (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  player_nick VARCHAR(50) NOT NULL,
  game_key VARCHAR(50) NOT NULL DEFAULT 'kralagario',
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

INSERT IGNORE INTO game_settings (setting_key, setting_value) VALUES
('game_speed', '1.6'),
('max_split_cells', '16'),
('map_size', '8000'),
('remerge_time_sec', '10'),
('rgb_speed', '2');

INSERT IGNORE INTO game_leaderboards (player_nick, game_key, high_score) VALUES
('KRAL_AGARIO_1', 'kralagario', 52400),
('DGRWEB_MASTER', 'kralagario', 41200),
('SiberEfsane', 'kralagario', 33100),
('NeoMatrix', 'kralagario', 25400),
('CyberNinja', 'kralagario', 19800),
('AlphaTitan', 'kralagario', 16200),
('VortexPlayer', 'kralagario', 13900),
('QuantumBlob', 'kralagario', 11000),
('ShadowHunter', 'kralagario', 8400),
('GamerPro007', 'kralagario', 6100);
