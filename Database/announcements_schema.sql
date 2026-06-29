-- Announcements & Events Table
-- Run this in your 'adms' database

CREATE TABLE IF NOT EXISTS `announcements` (
  `id`          INT(11)       NOT NULL AUTO_INCREMENT,
  `type`        ENUM('job','event','announcement','alert','link','statement') NOT NULL DEFAULT 'announcement',
  `title`       VARCHAR(255)  NOT NULL,
  `content`     TEXT          NULL,
  `image_path`  VARCHAR(500)  NULL,
  `url`         VARCHAR(1000) NULL,
  `created_by`  INT(11)       NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
