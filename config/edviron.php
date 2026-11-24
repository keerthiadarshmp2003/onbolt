<?php
/**
 * OnBolt Digital Experience Platform - Edviron Payment Gateway Configuration
 * Credentials:
 * 1. EDVIRON_PG_KEY: Merchant Signing Secret (used for JWT payload signing)
 * 2. EDVIRON_SCHOOL_ID: Institution Identifier
 * 3. EDVIRON_API_TOKEN: Bearer JWT API Token from Edviron Dashboard
 */

declare(strict_types=1);

if (!defined('EDVIRON_ENV')) {
    define('EDVIRON_ENV', getenv('EDVIRON_ENV') ?: 'dev'); // 'dev' or 'production'
}

define('EDVIRON_DEV_BASE_URL', 'https://dev-vanilla.edviron.com');
define('EDVIRON_PROD_BASE_URL', 'https://vanilla.edviron.com');

// Default Provided Test Keys
if (!defined('EDVIRON_DEFAULT_PG_KEY')) {
    define('EDVIRON_DEFAULT_PG_KEY', getenv('EDVIRON_PG_KEY') ?: 'b8f2c9e4a1d7053c6f9e2a7d4b0c18f5e3a9d6c4b2f1e7085a9c3d6e2f4b1a79');
}

if (!defined('EDVIRON_DEFAULT_SCHOOL_ID')) {
    define('EDVIRON_DEFAULT_SCHOOL_ID', getenv('EDVIRON_SCHOOL_ID') ?: '698af719dd7e15bb78dd0a8b');
}

if (!defined('EDVIRON_DEFAULT_API_TOKEN')) {
    define('EDVIRON_DEFAULT_API_TOKEN', getenv('EDVIRON_API_TOKEN') ?: '');
}

/**
 * Returns Edviron configuration settings
 */
function getEdvironConfig(): array {
    $baseUrl = (EDVIRON_ENV === 'dev') ? EDVIRON_DEV_BASE_URL : EDVIRON_PROD_BASE_URL;
    $apiToken = EDVIRON_DEFAULT_API_TOKEN;
    $pgKey = EDVIRON_DEFAULT_PG_KEY;
    $schoolId = EDVIRON_DEFAULT_SCHOOL_ID;

    return [
        'env'           => EDVIRON_ENV,
        'base_url'      => $baseUrl,
        'api_token'     => $apiToken,
        'pg_key'        => $pgKey,
        'school_id'     => $schoolId,
        'callback_url'  => BASE_URL . '/schools/payment_callback.php',
        'webhook_url'   => BASE_URL . '/api/edviron_webhook.php',
        'is_configured' => (!empty($pgKey) && !empty($schoolId))
    ];
}
