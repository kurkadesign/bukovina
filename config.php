<?php
declare(strict_types=1);

const DATA_DIR = __DIR__ . '/data';
const PROJECT_DIR = DATA_DIR . '/projects';
const VERSION_DIR = DATA_DIR . '/versions';
const USER_FILE = DATA_DIR . '/users.json';
const MAIL_CONFIG_FILE = DATA_DIR . '/mail-config.json';
const APP_NAME = 'Bukovina Planner';

$mailConfig = [];

if (is_file(MAIL_CONFIG_FILE) && is_readable(MAIL_CONFIG_FILE)) {
    try {
        $decoded = json_decode(
            (string) file_get_contents(MAIL_CONFIG_FILE),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (is_array($decoded)) {
            $mailConfig = $decoded;
        }
    } catch (Throwable $e) {
        error_log('Neplatný mail-config.json: ' . $e->getMessage());
    }
}

function mail_config_value(
    array $config,
    string $jsonKey,
    string $environmentKey,
    mixed $default = ''
): mixed {
    $jsonValue = $config[$jsonKey] ?? null;

    if ($jsonValue !== null && trim((string) $jsonValue) !== '') {
        return $jsonValue;
    }

    $environmentValue = getenv($environmentKey);

    if ($environmentValue !== false && trim((string) $environmentValue) !== '') {
        return $environmentValue;
    }

    return $default;
}

define(
    'BASE_URL',
    rtrim((string) (getenv('BUKOVINA_BASE_URL') ?: 'https://kurkadesign.sk/bukovina'), '/')
);

define('MAIL_FROM', (string) mail_config_value(
    $mailConfig,
    'mailFrom',
    'BUKOVINA_MAIL_FROM',
    'kurka@kurkadesign.sk'
));

define('MAIL_FROM_NAME', (string) mail_config_value(
    $mailConfig,
    'mailFromName',
    'BUKOVINA_MAIL_FROM_NAME',
    'Svadobná sála'
));

define('ORGANIZER_EMAIL', (string) mail_config_value(
    $mailConfig,
    'organizerEmail',
    'BUKOVINA_ORGANIZER_EMAIL',
    'zakutny.r@gmail.com'
));

define('SMTP_HOST', (string) mail_config_value(
    $mailConfig,
    'smtpHost',
    'BUKOVINA_SMTP_HOST',
    'smtp.eu.mailgun.org'
));

define('SMTP_PORT', (int) mail_config_value(
    $mailConfig,
    'smtpPort',
    'BUKOVINA_SMTP_PORT',
    587
));

define('SMTP_USER', (string) mail_config_value(
    $mailConfig,
    'smtpUser',
    'BUKOVINA_SMTP_USER',
    'kurka@mail.kurkadesign.sk'
));

define('SMTP_PASSWORD', (string) mail_config_value(
    $mailConfig,
    'smtpPassword',
    'BUKOVINA_SMTP_PASSWORD'
));

define('SMTP_ENCRYPTION', strtolower((string) mail_config_value(
    $mailConfig,
    'smtpEncryption',
    'BUKOVINA_SMTP_ENCRYPTION',
    'tls'
)));
