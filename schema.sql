-- DOVYE Studio — Lead & Customer Analytics Database Schema
CREATE DATABASE IF NOT EXISTS lead_panel
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE lead_panel;

CREATE TABLE IF NOT EXISTS leads (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(190) NOT NULL,
  service    VARCHAR(30)  NOT NULL,
  message    TEXT         NOT NULL,
  status     ENUM('new','read','contacted') NOT NULL DEFAULT 'new',
  ip_address VARCHAR(45)  NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status_created (status, created_at),
  KEY idx_created (created_at),
  KEY idx_service (service)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
