<?php
declare(strict_types=1);
const DATA_DIR = __DIR__ . '/data';
const PROJECT_DIR = DATA_DIR . '/projects';
const VERSION_DIR = DATA_DIR . '/versions';
const USER_FILE = DATA_DIR . '/users.json';
const APP_NAME = 'Bukovina Planner';

define('BASE_URL', rtrim((string)(getenv('BUKOVINA_BASE_URL') ?: ''), '/'));
define('MAIL_FROM', (string)(getenv('BUKOVINA_MAIL_FROM') ?: ''));
define('MAIL_FROM_NAME', (string)(getenv('BUKOVINA_MAIL_FROM_NAME') ?: 'Svadobná sála'));
define('ORGANIZER_EMAIL', (string)(getenv('BUKOVINA_ORGANIZER_EMAIL') ?: ''));
define('SMTP_HOST', (string)(getenv('BUKOVINA_SMTP_HOST') ?: ''));
define('SMTP_PORT', (int)(getenv('BUKOVINA_SMTP_PORT') ?: 587));
define('SMTP_USER', (string)(getenv('BUKOVINA_SMTP_USER') ?: ''));
define('SMTP_PASSWORD', (string)(getenv('BUKOVINA_SMTP_PASSWORD') ?: ''));
define('SMTP_ENCRYPTION', strtolower((string)(getenv('BUKOVINA_SMTP_ENCRYPTION') ?: 'tls')));
