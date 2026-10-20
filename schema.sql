CREATE DATABASE IF NOT EXISTS lead_panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE lead_panel;

CREATE TABLE IF NOT EXISTS tenants (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid VARCHAR(36) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  domain VARCHAR(100) NOT NULL UNIQUE,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'SUPER_ADMIN',
  theme_pref VARCHAR(20) NOT NULL DEFAULT 'dark',
  two_factor_secret VARCHAR(100) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS youtube_videos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  youtube_url VARCHAR(255) NOT NULL,
  video_id VARCHAR(50) NOT NULL,
  category VARCHAR(50) NOT NULL DEFAULT 'Cyberpunk',
  views_count INT UNSIGNED NOT NULL DEFAULT 0,
  like_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_cams (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  location VARCHAR(100) NOT NULL,
  embed_url VARCHAR(500) NOT NULL,
  provider VARCHAR(50) NOT NULL DEFAULT 'Skyline / YouTube',
  stream_type VARCHAR(30) NOT NULL DEFAULT 'WEBRTC_HLS',
  status VARCHAR(20) NOT NULL DEFAULT 'ONLINE 4K',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cluster_nodes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  node_name VARCHAR(100) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'WORKER_TRANSCODER',
  cpu_usage FLOAT NOT NULL DEFAULT 0.0,
  ram_usage FLOAT NOT NULL DEFAULT 0.0,
  status VARCHAR(20) NOT NULL DEFAULT 'HEALTHY',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_ticks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  symbol VARCHAR(20) NOT NULL,
  price DECIMAL(18,8) NOT NULL,
  change_24h DECIMAL(6,2) NOT NULL,
  volume_24h DECIMAL(18,2) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NULL,
  severity VARCHAR(15) NOT NULL DEFAULT 'INFO',
  action VARCHAR(100) NOT NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO youtube_videos (id, title, youtube_url, video_id, category, views_count) VALUES
(1, 'Cyberpunk 2077 — Night City Ambient Drive 4K', 'https://www.youtube.com/watch?v=Leed38_x0_0', 'Leed38_x0_0', 'Cyberpunk', 48200),
(2, 'Lo-Fi Synthwave Chill Beats for Coding & Chill', 'https://www.youtube.com/watch?v=4xDzrJKXOOY', '4xDzrJKXOOY', 'Lo-Fi Chill', 32100),
(3, 'Tokyo Future City Walk — Rainy Night 8K', 'https://www.youtube.com/watch?v=680kXgA2X2U', '680kXgA2X2U', 'City Walk', 19800),
(4, 'Deep Focus Techno & Coding Session', 'https://www.youtube.com/watch?v=Leed38_x0_0', 'Leed38_x0_0', 'Techno', 17600);

INSERT IGNORE INTO live_cams (id, title, location, embed_url, provider, status) VALUES
(1, 'Duomo di Milano Live Stream', 'Milano, Italia', 'https://www.skylinewebcams.com/en/webcam/italia/lombardia/milano/duomo-milano.html', 'SkylineWebcams', 'ONLINE 4K'),
(2, 'Shibuya Crossing Live 4K', 'Tokyo, Japan', 'https://www.youtube.com/embed/h3fUgOKFMNU', 'YouTube Live', 'ONLINE 4K'),
(3, 'Times Square Heart of NYC', 'New York, USA', 'https://www.youtube.com/embed/mRe-514tGMg', 'YouTube Live', 'ONLINE 4K'),
(4, 'Istanbul Bosphorus Strait Cam', 'Istanbul, Türkiye', 'https://www.youtube.com/embed/1-iS724X-E0', 'HD Live', 'ONLINE 4K');

INSERT IGNORE INTO cluster_nodes (id, node_name, ip_address, role, cpu_usage, ram_usage, status) VALUES
(1, 'clouding-vps-istanbul-01', '185.254.30.12', 'MASTER_ORCHESTRATOR', 12.4, 38.2, 'HEALTHY'),
(2, 'hetzner-node-frankfurt-02', '159.69.140.88', 'MEDIA_TRANSCODER', 42.1, 64.5, 'HEALTHY'),
(3, 'digitalocean-sgp-03', '128.199.200.15', 'EDGE_CACHE_PROXY', 8.5, 22.1, 'HEALTHY');

INSERT IGNORE INTO financial_ticks (id, symbol, price, change_24h, volume_24h) VALUES
(1, 'BTC/USD', 68420.50000000, 3.45, 42100500000.00),
(2, 'ETH/USD', 3820.10000000, 2.10, 18400200000.00),
(3, 'USD/TRY', 34.84500000, 0.15, 8900100000.00),
(4, 'EUR/TRY', 38.19200000, 0.22, 6200400000.00);
