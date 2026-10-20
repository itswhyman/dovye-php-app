-- DOVYE STUDIO — Mega Cyber OS v4.0 & CMS Schema
CREATE DATABASE IF NOT EXISTS lead_panel
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE lead_panel;

-- Admin Security & Theme Preferences
CREATE TABLE IF NOT EXISTS admin_users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  theme_pref    VARCHAR(20)  NOT NULL DEFAULT 'dark',
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- YouTube Videos (Theatre Mode & Grid)
CREATE TABLE IF NOT EXISTS youtube_videos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(255) NOT NULL,
  youtube_url VARCHAR(255) NOT NULL,
  video_id    VARCHAR(50)  NOT NULL,
  category    VARCHAR(50)  NOT NULL DEFAULT 'Cyberpunk',
  views_count INT UNSIGNED NOT NULL DEFAULT 1200,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Live World Cameras (Supports YouTube Live & External Embeds like SkylineWebcams)
CREATE TABLE IF NOT EXISTS live_cams (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(255) NOT NULL,
  location    VARCHAR(100) NOT NULL,
  embed_url   VARCHAR(500) NOT NULL,
  provider    VARCHAR(50)  NOT NULL DEFAULT 'Skyline / YouTube',
  status      VARCHAR(20)  NOT NULL DEFAULT 'LIVE 4K',
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Audit Logs
CREATE TABLE IF NOT EXISTS system_logs (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  action     VARCHAR(100) NOT NULL,
  details    TEXT         NULL,
  ip_address VARCHAR(45)  NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial Seed Data: YouTube Videos
INSERT IGNORE INTO youtube_videos (id, title, youtube_url, video_id, category, views_count) VALUES
(1, 'Cyberpunk 2077 — Night City 4K Ambience', 'https://www.youtube.com/watch?v=Leed38_x0_0', 'Leed38_x0_0', 'Cyberpunk', 18400),
(2, 'Lo-Fi Beats for Cyber Coding & Chill', 'https://www.youtube.com/watch?v=4xDzrJKXOOY', '4xDzrJKXOOY', 'Lo-Fi Chill', 12300),
(3, 'Tokyo Future City Walk — Heavy Rain 8K', 'https://www.youtube.com/watch?v=680kXgA2X2U', '680kXgA2X2U', 'City Walk', 9800),
(4, 'Synthwave Retro Wave Radio Live Stream', 'https://www.youtube.com/watch?v=4xDzrJKXOOY', '4xDzrJKXOOY', 'Synthwave', 15100),
(5, 'Deep Focus Techno & Coding Session', 'https://www.youtube.com/watch?v=Leed38_x0_0', 'Leed38_x0_0', 'Techno', 7600);

-- Initial Seed Data: Live World Cameras
INSERT IGNORE INTO live_cams (id, title, location, embed_url, provider, status) VALUES
(1, 'Duomo di Milano Cam', 'Milano, Italia', 'https://www.skylinewebcams.com/en/webcam/italia/lombardia/milano/duomo-milano.html', 'SkylineWebcams', 'LIVE 4K'),
(2, 'Shibuya Crossing Live 4K', 'Tokyo, Japan', 'https://www.youtube.com/embed/h3fUgOKFMNU', 'YouTube Live', 'LIVE 4K'),
(3, 'Times Square Heart of NYC', 'New York, USA', 'https://www.youtube.com/embed/mRe-514tGMg', 'YouTube Live', 'LIVE 4K'),
(4, 'Istanbul Bosphorus Cam', 'Istanbul, Türkiye', 'https://www.youtube.com/embed/1-iS724X-E0', 'HD Live', 'LIVE 4K'),
(5, 'Earth From Space (ISS Live Stream)', 'Orbit Space Station', 'https://www.youtube.com/embed/xRPj288f6A0', 'NASA Live', 'ORBITING');
