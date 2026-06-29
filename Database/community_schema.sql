-- ADMS Community module — run against database `adms`
-- Posts, likes, comments, reposts with foreign keys to `admins` and `alumni`

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS community_posts (
  id INT NOT NULL AUTO_INCREMENT,
  author_type ENUM('admin','alumni') NOT NULL,
  author_admin_id INT NULL,
  author_alumni_id INT NULL,
  body TEXT NULL,
  link_url VARCHAR(1000) NULL,
  image_path VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT chk_community_posts_author CHECK (
    (author_type = 'admin' AND author_admin_id IS NOT NULL AND author_alumni_id IS NULL)
    OR (author_type = 'alumni' AND author_alumni_id IS NOT NULL AND author_admin_id IS NULL)
  ),
  CONSTRAINT fk_community_posts_admin
    FOREIGN KEY (author_admin_id) REFERENCES admins(id) ON DELETE CASCADE,
  CONSTRAINT fk_community_posts_alumni
    FOREIGN KEY (author_alumni_id) REFERENCES alumni(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_likes (
  id INT NOT NULL AUTO_INCREMENT,
  post_id INT NOT NULL,
  liker_type ENUM('admin','alumni') NOT NULL,
  liker_admin_id INT NULL,
  liker_alumni_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_community_like (post_id, liker_type, liker_admin_id, liker_alumni_id),
  CONSTRAINT chk_community_likes_liker CHECK (
    (liker_type = 'admin' AND liker_admin_id IS NOT NULL AND liker_alumni_id IS NULL)
    OR (liker_type = 'alumni' AND liker_alumni_id IS NOT NULL AND liker_admin_id IS NULL)
  ),
  CONSTRAINT fk_community_likes_post
    FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_community_likes_admin
    FOREIGN KEY (liker_admin_id) REFERENCES admins(id) ON DELETE CASCADE,
  CONSTRAINT fk_community_likes_alumni
    FOREIGN KEY (liker_alumni_id) REFERENCES alumni(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_comments (
  id INT NOT NULL AUTO_INCREMENT,
  post_id INT NOT NULL,
  author_type ENUM('admin','alumni') NOT NULL,
  author_admin_id INT NULL,
  author_alumni_id INT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT chk_community_comments_author CHECK (
    (author_type = 'admin' AND author_admin_id IS NOT NULL AND author_alumni_id IS NULL)
    OR (author_type = 'alumni' AND author_alumni_id IS NOT NULL AND author_admin_id IS NULL)
  ),
  CONSTRAINT fk_community_comments_post
    FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_community_comments_admin
    FOREIGN KEY (author_admin_id) REFERENCES admins(id) ON DELETE CASCADE,
  CONSTRAINT fk_community_comments_alumni
    FOREIGN KEY (author_alumni_id) REFERENCES alumni(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_reposts (
  id INT NOT NULL AUTO_INCREMENT,
  original_post_id INT NOT NULL,
  reposter_type ENUM('admin','alumni') NOT NULL,
  reposter_admin_id INT NULL,
  reposter_alumni_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_community_repost (original_post_id, reposter_type, reposter_admin_id, reposter_alumni_id),
  CONSTRAINT chk_community_reposts_reposter CHECK (
    (reposter_type = 'admin' AND reposter_admin_id IS NOT NULL AND reposter_alumni_id IS NULL)
    OR (reposter_type = 'alumni' AND reposter_alumni_id IS NOT NULL AND reposter_admin_id IS NULL)
  ),
  CONSTRAINT fk_community_reposts_post
    FOREIGN KEY (original_post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_community_reposts_admin
    FOREIGN KEY (reposter_admin_id) REFERENCES admins(id) ON DELETE CASCADE,
  CONSTRAINT fk_community_reposts_alumni
    FOREIGN KEY (reposter_alumni_id) REFERENCES alumni(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_community_posts_created ON community_posts (created_at DESC);
CREATE INDEX idx_community_reposts_created ON community_reposts (created_at DESC);
CREATE INDEX idx_community_comments_post ON community_comments (post_id);
