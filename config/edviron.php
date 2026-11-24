<?php
/**
 * OnBolt Digital Experience Platform - Edviron Payment Gateway Configuration
 * Only requires:
 * 1. EDVIRON_API_TOKEN (Bearer JWT API Token)
 * 2. EDVIRON_PG_KEY (Merchant Signing Secret)
 *
 * NOTE: School ID is completely removed as OnBolt manages all institutions internally.
 */

declare(strict_types=1);

if (!defined('EDVIRON_ENV')) {
    define('EDVIRON_ENV', getenv('EDVIRON_ENV') ?: 'production'); // 'dev' or 'production'
}

define('EDVIRON_DEV_BASE_URL', 'https://dev-vanilla.edviron.com');
define('EDVIRON_PROD_BASE_URL', 'https://vanilla.edviron.com');

// Credentials loaded securely from environment variables (API Token & PG Key only)
if (!defined('EDVIRON_DEFAULT_API_TOKEN')) {
    define('EDVIRON_DEFAULT_API_TOKEN', getenv('EDVIRON_API_TOKEN') ?: '');
}

if (!defined('EDVIRON_DEFAULT_PG_KEY')) {
    define('EDVIRON_DEFAULT_PG_KEY', getenv('EDVIRON_PG_KEY') ?: '');
}

/**
 * Returns Edviron configuration settings (API Token and PG Key only)
 */
function getEdvironConfig(): array {
    $baseUrl = (EDVIRON_ENV === 'dev') ? EDVIRON_DEV_BASE_URL : EDVIRON_PROD_BASE_URL;
    $apiToken = EDVIRON_DEFAULT_API_TOKEN;
    $pgKey = EDVIRON_DEFAULT_PG_KEY;

    return [
        'env'           => EDVIRON_ENV,
        'base_url'      => $baseUrl,
        'api_token'     => $apiToken,
        'pg_key'        => $pgKey,
        'callback_url'  => BASE_URL . '/schools/payment_callback.php',
        'webhook_url'   => BASE_URL . '/api/edviron_webhook.php',
        'is_configured' => (!empty($apiToken) && !empty($pgKey))
    ];
}
