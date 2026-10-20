CREATE DATABASE IF NOT EXISTS lead_panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE lead_panel;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  theme_pref VARCHAR(20) NOT NULL DEFAULT 'dark',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS youtube_videos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  youtube_url VARCHAR(255) NOT NULL,
  video_id VARCHAR(50) NOT NULL,
  category VARCHAR(50) NOT NULL DEFAULT 'Cyberpunk',
  views_count INT UNSIGNED NOT NULL DEFAULT 1200,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_cams (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  location VARCHAR(100) NOT NULL,
  embed_url VARCHAR(500) NOT NULL,
  provider VARCHAR(50) NOT NULL DEFAULT 'Skyline / YouTube',
  status VARCHAR(20) NOT NULL DEFAULT 'LIVE 4K',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
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

INSERT IGNORE INTO youtube_videos (id, title, youtube_url, video_id, category, views_count) VALUES
(1, 'Cyberpunk 2077 — Night City Ambient Drive 4K', 'https://www.youtube.com/watch?v=Leed38_x0_0', 'Leed38_x0_0', 'Cyberpunk', 18400),
(2, 'Lo-Fi Synthwave Chill Beats for Coding & Chill', 'https://www.youtube.com/watch?v=4xDzrJKXOOY', '4xDzrJKXOOY', 'Lo-Fi Chill', 12300),
(3, 'Tokyo Future City Walk — Rainy Night 8K', 'https://www.youtube.com/watch?v=680kXgA2X2U', '680kXgA2X2U', 'City Walk', 9800),
(4, 'Deep Focus Techno & Coding Session', 'https://www.youtube.com/watch?v=Leed38_x0_0', 'Leed38_x0_0', 'Techno', 7600);

INSERT IGNORE INTO live_cams (id, title, location, embed_url, provider, status) VALUES
(1, 'Duomo di Milano Live Cam', 'Milano, Italia', 'https://www.skylinewebcams.com/en/webcam/italia/lombardia/milano/duomo-milano.html', 'SkylineWebcams', 'LIVE 4K'),
(2, 'Shibuya Crossing Live Stream 4K', 'Tokyo, Japan', 'https://www.youtube.com/embed/h3fUgOKFMNU', 'YouTube Live', 'LIVE 4K'),
(3, 'Times Square Heart of NYC', 'New York, USA', 'https://www.youtube.com/embed/mRe-514tGMg', 'YouTube Live', 'LIVE 4K'),
(4, 'Istanbul Bosphorus Strait Cam', 'Istanbul, Türkiye', 'https://www.youtube.com/embed/1-iS724X-E0', 'HD Live', 'LIVE 4K');
