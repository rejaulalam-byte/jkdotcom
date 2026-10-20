-- 001: tables used by the Home page. Run once in phpMyAdmin (cPanel) on the NEW database.
-- Character set utf8mb4 is required for Bangla text.

SET NAMES utf8mb4;

-- Admin-editable website text (key = e.g. "home.hero.title_1")
CREATE TABLE IF NOT EXISTS content_blocks (
    content_key   VARCHAR(120) NOT NULL,
    content_value TEXT         NOT NULL,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (content_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Service categories (electrician, plumber ...)
CREATE TABLE IF NOT EXISTS services (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(120) NOT NULL,
    icon_path   VARCHAR(190) NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    is_featured TINYINT(1)   NOT NULL DEFAULT 0,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_services_listing (is_active, is_featured, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "আজকের প্রকাশিত" job notices (new rows start as 'pending' until the admin approves)
CREATE TABLE IF NOT EXISTS job_posts (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title         VARCHAR(190) NOT NULL,
    details       TEXT         NOT NULL,
    contact_name  VARCHAR(120) NOT NULL,
    phone         VARCHAR(20)  NOT NULL,
    email         VARCHAR(150) NULL,
    address       VARCHAR(255) NOT NULL,
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    published_at  DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_posts_recent (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Advertisement banners in the strip under the home hero
CREATE TABLE IF NOT EXISTS advertisements (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title      VARCHAR(150) NOT NULL,
    image_path VARCHAR(190) NOT NULL,           -- file name inside public/uploads/ads/
    width      SMALLINT UNSIGNED NOT NULL,
    height     SMALLINT UNSIGNED NOT NULL,
    link_url   VARCHAR(500) NULL,               -- optional advertiser website (http/https only)
    sort_order INT          NOT NULL DEFAULT 0,
    starts_at  DATETIME     NULL,
    ends_at    DATETIME     NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_ads_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Visitor feedback (admin can read / delete / keep)
CREATE TABLE IF NOT EXISTS feedback (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL DEFAULT '',
    phone      VARCHAR(20)  NOT NULL DEFAULT '',
    subject    VARCHAR(150) NOT NULL DEFAULT '',
    message    TEXT         NOT NULL,
    ip_hash    CHAR(64)     NOT NULL,
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_feedback_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Footer e-mail box
CREATE TABLE IF NOT EXISTS subscribers (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email      VARCHAR(150) NOT NULL,
    ip_hash    CHAR(64)     NOT NULL,
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscribers_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
