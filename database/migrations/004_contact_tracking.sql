-- Migration 004 — who looks at hidden phone numbers / e-mails (run after 003)
-- Used by the job list (/today) now and by the workers list later (target_type = 'worker').

-- every click on a hidden number or e-mail (counted as a "call" for the admin's report)
CREATE TABLE IF NOT EXISTS contact_clicks (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_type ENUM('job','worker') NOT NULL,
    target_id   INT UNSIGNED NOT NULL,
    kind        ENUM('phone','email') NOT NULL,
    clicked_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_hash     CHAR(64) NULL,
    PRIMARY KEY (id),
    KEY idx_clicks_target (target_type, target_id, clicked_at),
    KEY idx_clicks_day (clicked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- a visitor said "I am interested" and left their name + mobile before seeing the contact
CREATE TABLE IF NOT EXISTS contact_leads (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_type   ENUM('job','worker') NOT NULL,
    target_id     INT UNSIGNED NOT NULL,
    kind          ENUM('phone','email') NOT NULL,
    visitor_name  VARCHAR(100) NOT NULL,
    visitor_phone VARCHAR(20)  NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_hash       CHAR(64) NULL,
    PRIMARY KEY (id),
    KEY idx_leads_target (target_type, target_id, created_at),
    KEY idx_leads_day (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
