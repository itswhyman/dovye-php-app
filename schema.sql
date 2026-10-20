-- DOVYE STUDIO — Ultimate Cyber Core & CMS Schema v3.0
CREATE DATABASE IF NOT EXISTS lead_panel
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE lead_panel;

-- Admin Security & Credentials
CREATE TABLE IF NOT EXISTS admin_users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- YouTube Video Engine
CREATE TABLE IF NOT EXISTS youtube_videos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(255) NOT NULL,
  youtube_url VARCHAR(255) NOT NULL,
  video_id    VARCHAR(50)  NOT NULL,
  category    VARCHAR(50)  NOT NULL DEFAULT 'General',
  is_featured TINYINT(1)   NOT NULL DEFAULT 0,
  views_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_category (category),
  KEY idx_featured (is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Live World Cameras
CREATE TABLE IF NOT EXISTS live_cams (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(255) NOT NULL,
  location    VARCHAR(100) NOT NULL,
  embed_url   VARCHAR(500) NOT NULL,
  status      VARCHAR(20)  NOT NULL DEFAULT 'LIVE',
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Audit & Security Logs
CREATE TABLE IF NOT EXISTS system_logs (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  action     VARCHAR(100) NOT NULL,
  details    TEXT         NULL,
  ip_address VARCHAR(45)  NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_action (action),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Leads & Form Submissions
CREATE TABLE IF NOT EXISTS leads (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(190) NOT NULL,
  service    VARCHAR(50)  NOT NULL,
  message    TEXT         NOT NULL,
  status     ENUM('new','read','contacted') NOT NULL DEFAULT 'new',
  ip_address VARCHAR(45)  NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Seed Data for YouTube Videos
INSERT IGNORE INTO youtube_videos (id, title, youtube_url, video_id, category, is_featured, views_count) VALUES
(1, 'Cyberpunk 2077 — Night City Ambient Drive 4K', 'https://www.youtube.com/watch?v=Leed38_x0_0', 'Leed38_x0_0', 'Cyberpunk', 1, 14200),
(2, 'Lo-Fi Synthwave Chill Beats for Coding & Chill', 'https://www.youtube.com/watch?v=4xDzrJKXOOY', '4xDzrJKXOOY', 'Lo-Fi Beats', 1, 8900),
(3, 'Tokyo Future City Walk — Rainy Night 8K', 'https://www.youtube.com/watch?v=680kXgA2X2U', '680kXgA2X2U', 'City Walks', 0, 5400);

-- Default Seed Data for Live World Cams
INSERT IGNORE INTO live_cams (id, title, location, embed_url, status) VALUES
(1, 'Shibuya Crossing Live Stream 4K', 'Tokyo, Japan', 'https://www.youtube.com/embed/h3fUgOKFMNU', 'LIVE'),
(2, 'Times Square 4K Live Camera', 'New York, USA', 'https://www.youtube.com/embed/mRe-514tGMg', 'LIVE'),
(3, 'Istanbul Bosphorus Strait Cam', 'Istanbul, Türkiye', 'https://www.youtube.com/embed/1-iS724X-E0', 'LIVE'),
(4, 'International Space Station ISS View', 'Orbit Space', 'https://www.youtube.com/embed/xRPj288f6A0', 'LIVE');
