-- =============================================================
-- ADMS — Support Direct Messages
-- Channel between Alumni and Admins (like a help-desk inbox)
-- =============================================================

CREATE TABLE IF NOT EXISTS `support_messages` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `alumni_id`     INT(11) NOT NULL,                     -- which alumni thread this belongs to
  `sender_type`   ENUM('alumni','admin') NOT NULL,      -- who sent the message
  `sender_admin_id`  INT(11) DEFAULT NULL,              -- if admin sent it
  `body`          TEXT NOT NULL,
  `is_read_by_admin`  TINYINT(1) NOT NULL DEFAULT 0,    -- 1 = some admin opened the thread after this
  `is_read_by_alumni` TINYINT(1) NOT NULL DEFAULT 0,    -- 1 = alumni opened the thread after this
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alumni`     (`alumni_id`),
  KEY `idx_alumni_created` (`alumni_id`, `created_at`),
  KEY `idx_unread_admin`   (`is_read_by_admin`),
  KEY `idx_unread_alumni`  (`alumni_id`, `is_read_by_alumni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
