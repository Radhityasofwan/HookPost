-- SQLite schema aligned with MySQL schema.sql

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS channels (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    platform TEXT NOT NULL,
    platform_account_id TEXT NULL,
    account_name TEXT NULL,
    account_username TEXT NULL,
    page_id TEXT NULL,
    token_type TEXT NULL,
    access_token TEXT NULL,
    refresh_token TEXT NULL,
    token_expiry TEXT NULL,
    last_checked_at TEXT NULL,
    last_error TEXT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    status TEXT NOT NULL DEFAULT 'connected',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    caption TEXT NULL,
    mode TEXT NOT NULL DEFAULT 'mirror',
    overall_status TEXT NOT NULL DEFAULT 'draft',
    scheduled_at TEXT NULL,
    created_by INTEGER NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NULL
);

CREATE TABLE IF NOT EXISTS post_variants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    platform TEXT NOT NULL,
    caption TEXT NULL,
    first_comment TEXT NULL,
    cover_time INTEGER NULL,
    privacy_level TEXT NULL,
    publish_mode TEXT NOT NULL DEFAULT 'direct',
    scheduled_at TEXT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    external_post_id TEXT NULL,
    external_url TEXT NULL,
    published_at TEXT NULL,
    failed_reason TEXT NULL,
    payload_snapshot TEXT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS media_assets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    original_name TEXT NOT NULL,
    file_path TEXT NOT NULL,
    file_url TEXT NULL,
    mime_type TEXT NOT NULL,
    file_size INTEGER NOT NULL DEFAULT 0,
    width INTEGER NULL,
    height INTEGER NULL,
    duration_sec INTEGER NULL,
    checksum TEXT NULL,
    media_kind TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS post_media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_variant_id INTEGER NOT NULL,
    media_asset_id INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS publish_jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    job_key TEXT NOT NULL UNIQUE,
    post_variant_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    attempts INTEGER NOT NULL DEFAULT 0,
    max_attempts INTEGER NOT NULL DEFAULT 3,
    locked_at TEXT NULL,
    locked_by TEXT NULL,
    started_at TEXT NULL,
    finished_at TEXT NULL,
    last_error TEXT NULL,
    next_retry_at TEXT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS publish_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    publish_job_id INTEGER NULL,
    post_variant_id INTEGER NULL,
    platform TEXT NOT NULL,
    log_level TEXT NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    response_snapshot TEXT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
