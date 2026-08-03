<?php
/**
 * Central bootstrap for all Habit Tracker pages.
 *
 * Loads DB config + shared helpers, sends consistent security headers,
 * and initializes a hardened session. Every page should require this file.
 */

// Load database connection + all shared helper functions.
require __DIR__ . '/../config/db.php';
require __DIR__ . '/helpers.php';

// ---- Security headers (sent once, before any output) ----
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: same-origin');
    // Basic CSP: allow only same-origin + inline styles/scripts used by the app.
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'");
}

// Start a hardened session (idempotent).
initSecureSession();
