-- SECTION: BOOTSTRAP
-- Database schema for Social Publisher (MySQL)

-- SECTION: AUTH
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SECTION: HANDLE REQUEST
CREATE TABLE IF NOT EXISTS channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    platform ENUM('instagram','facebook','threads','tiktok') NOT NULL,
    platform_account_id VARCHAR(100) NULL,
    account_name VARCHAR(150) NULL,
    account_username VARCHAR(150) NULL,
    page_id VARCHAR(100) NULL,
    token_type VARCHAR(50) NULL,
    access_token LONGTEXT NULL,
    refresh_token LONGTEXT NULL,
    token_expiry DATETIME NULL,
    last_checked_at DATETIME NULL,
    last_error TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    status VARCHAR(50) NOT NULL DEFAULT 'connected',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_channels_platform (platform),
    INDEX idx_channels_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SECTION: LOAD DATA
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    caption LONGTEXT NULL,
    mode ENUM('mirror','custom') NOT NULL DEFAULT 'mirror',
    overall_status ENUM('draft','scheduled','published','failed') NOT NULL DEFAULT 'draft',
    scheduled_at DATETIME NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_posts_status (overall_status),
    INDEX idx_posts_schedule (scheduled_at),
    CONSTRAINT fk_posts_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS post_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    platform ENUM('instagram','facebook','threads','tiktok') NOT NULL,
    caption LONGTEXT NULL,
    first_comment LONGTEXT NULL,
    cover_time INT NULL,
    privacy_level VARCHAR(50) NULL,
    publish_mode ENUM('direct','draft') NOT NULL DEFAULT 'direct',
    scheduled_at DATETIME NULL,
    status ENUM('draft','pending','scheduled','processing','published','failed') NOT NULL DEFAULT 'draft',
    external_post_id VARCHAR(120) NULL,
    external_url TEXT NULL,
    published_at DATETIME NULL,
    failed_reason TEXT NULL,
    payload_snapshot LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_variant_post (post_id),
    INDEX idx_variant_status (status),
    CONSTRAINT fk_variants_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS media_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_url VARCHAR(255) NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT NOT NULL DEFAULT 0,
    width INT NULL,
    height INT NULL,
    duration_sec INT NULL,
    checksum VARCHAR(64) NULL,
    media_kind ENUM('image','video') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS post_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_variant_id INT NOT NULL,
    media_asset_id INT NOT NULL,
    CONSTRAINT fk_post_media_variant FOREIGN KEY (post_variant_id) REFERENCES post_variants(id) ON DELETE CASCADE,
    CONSTRAINT fk_post_media_asset FOREIGN KEY (media_asset_id) REFERENCES media_assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS publish_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_key VARCHAR(150) NOT NULL UNIQUE,
    post_variant_id INT NOT NULL,
    status ENUM('pending','processing','success','failed','retrying') NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 3,
    locked_at DATETIME NULL,
    locked_by VARCHAR(100) NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    last_error TEXT NULL,
    next_retry_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_jobs_status (status),
    INDEX idx_jobs_retry (next_retry_at),
    CONSTRAINT fk_jobs_variant FOREIGN KEY (post_variant_id) REFERENCES post_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS publish_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    publish_job_id INT NULL,
    post_variant_id INT NULL,
    platform VARCHAR(50) NOT NULL,
    log_level VARCHAR(20) NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    response_snapshot LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_publish_logs_platform (platform),
    INDEX idx_publish_logs_variant (post_variant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SECTION: HTML
-- Not applicable.

-- SECTION: INLINE CSS
-- Not applicable.

-- SECTION: INLINE JS
-- Not applicable.
