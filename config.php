<?php
declare(strict_types=1);
const DATA_DIR = __DIR__ . '/data';
const DEFAULT_ROOM_FILE = DATA_DIR . '/default-room.json';
const PROJECT_DIR = DATA_DIR . '/projects';
const VERSION_DIR = DATA_DIR . '/versions';
const USER_FILE = DATA_DIR . '/users.json';
const BACKUP_DIR = __DIR__ . '/backup';
const BACKUP_META_FILE = BACKUP_DIR . '/backup-meta.json';
const AUTOMATIC_BACKUP_INTERVAL_DAYS = 2;
const AUTOMATIC_BACKUP_RETENTION = 5;
const APP_NAME = 'Bukovina Planner';

$mailConfigFile = DATA_DIR . '/mail-config.json';
$mailConfig = is_file($mailConfigFile) ? json_decode((string)file_get_contents($mailConfigFile), true) : [];
if (!is_array($mailConfig)) $mailConfig = [];

define('BASE_URL', rtrim((string)($mailConfig['baseUrl'] ?? ''), '/'));
define('MAIL_FROM', (string)($mailConfig['mailFrom'] ?? ''));
define('MAIL_FROM_NAME', (string)($mailConfig['mailFromName'] ?? 'Eventová sála'));
define('ORGANIZER_EMAIL', (string)($mailConfig['organizerEmail'] ?? ''));
define('SMTP_HOST', (string)($mailConfig['smtpHost'] ?? ''));
define('SMTP_PORT', (int)($mailConfig['smtpPort'] ?? 587));
define('SMTP_USER', (string)($mailConfig['smtpUser'] ?? ''));
define('SMTP_PASSWORD', (string)($mailConfig['smtpPassword'] ?? ''));
define('SMTP_ENCRYPTION', strtolower((string)($mailConfig['smtpEncryption'] ?? 'tls')));
