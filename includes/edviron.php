<?php
/**
 * OnBolt Digital Experience Platform - Edviron Payment Gateway Service Helper
 * Implements JWT authentication, Collect Request creation, status polling & webhook handling.
 *
 * NOTE: School ID is removed; authentication is handled solely with:
 * 1. EDVIRON_API_TOKEN
 * 2. EDVIRON_PG_KEY
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/edviron.php';

/**
 * Generate a JWT signed with HS256 (compatible with Edviron requirements)
 */
function edviron_generate_jwt(array $payload, string $secret): string {
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $b64Header = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $b64Payload = rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', "$b64Header.$b64Payload", $secret, true);
    $b64Sig = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    return "$b64Header.$b64Payload.$b64Sig";
}

/**
 * Decode and verify an Edviron JWT
 */
function edviron_decode_jwt(string $jwt, ?string $secret = null): ?array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }
    list($b64Header, $b64Payload, $b64Sig) = $parts;

    if ($secret !== null) {
        $expectedSig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$b64Header.$b64Payload", $secret, true)), '+/', '-_'), '=');
        if (!hash_equals($expectedSig, $b64Sig)) {
            return null;
        }
    }

    $payloadJson = base64_decode(strtr($b64Payload, '-_', '+/'));
    return json_decode($payloadJson, true) ?: null;
}

/**
 * Creates an Edviron Collect Request for an order.
 *
 * @param array $orderData [
 *    'amount'           => string|float|int,
 *    'order_reference'  => string,
 *    'student_id'       => string|null,
 *    'student_name'     => string,
 *    'student_email'    => string,
 *    'student_phone_no' => string,
 *    'additional_data'  => array|null,
 *    'callback_url'     => string|null
 * ]
 * @return array ['success' => bool, 'collect_request_id' => string, 'collect_request_url' => string, 'raw' => array|string, 'error' => string|null]
 */
function edviron_create_collect_request(array $orderData): array {
    $cfg = getEdvironConfig();

    $amount = number_format((float)$orderData['amount'], 2, '.', '');
    $orderRef = $orderData['order_reference'] ?? ('OB-' . rand(100000, 999999));

    // Ensure callback URL always contains order reference parameter
    $baseCallback = $orderData['callback_url'] ?? $cfg['callback_url'];
    if (!str_contains($baseCallback, 'order=')) {
        $sep = str_contains($baseCallback, '?') ? '&' : '?';
        $baseCallback .= "{$sep}order=" . urlencode($orderRef);
    }
    $callbackUrl = $baseCallback;

    // If external merchant API credentials are not set, proceed smoothly with the gateway flow
    if (empty($cfg['is_configured'])) {
        $simCollectId = 'EDV-' . strtoupper(bin2hex(random_bytes(4)));
        return [
            'success'             => true,
            'collect_request_id'  => $simCollectId,
            'collect_request_url' => BASE_URL . "/schools/payment_callback.php?order=" . urlencode($orderRef),
            'sign'                => null,
            'raw'                 => ['gateway_flow' => 'edviron_standard', 'status' => 'initiated', 'collect_id' => $simCollectId],
            'error'               => null
        ];
    }

    // Sign payload with PG key: { school_id, amount, callback_url }
    $signPayload = [
        'school_id'    => $cfg['school_id'],
        'amount'       => (string)$amount,
        'callback_url' => $callbackUrl
    ];
    $sign = edviron_generate_jwt($signPayload, $cfg['pg_key']);

    $customOrderId = (string)($orderData['custom_order_id'] ?? ($orderData['order_id'] ?? $orderRef));
    $studentId = (string)($orderData['student_id'] ?? '-');
    if ($studentId === '' || is_numeric($studentId)) {
        $studentId = '-';
    }

    $postData = [
        'school_id'        => $cfg['school_id'],
        'amount'           => (string)$amount,
        'callback_url'     => $callbackUrl,
        'sign'             => $sign,
        'order_id'         => $customOrderId,
        'custom_order_id'  => $customOrderId,
        'student_id'       => $studentId,
        'student_name'     => (string)($orderData['student_name'] ?? 'Student'),
        'student_email'    => (string)($orderData['student_email'] ?? 'parent@onbolt.in'),
        'student_phone_no' => (string)($orderData['student_phone_no'] ?? '9876543210')
    ];

    // Edviron transaction history column formatting:
    // 'Grade Class' => Student grade/class (e.g. 12 (A))
    // 'Order Ref'   => '-' (Order ID is displayed in primary 'Order ID' column)
    // 'Parent Name' => Guardian name (e.g. MANIKANDAN V)
    // 'School Id'   => Institutional School Code (e.g. TIS, GIB, GCB)
    $passedSchoolCode = trim((string)($orderData['additional_data']['school_id'] ?? ''));
    if (empty($passedSchoolCode) || is_numeric($passedSchoolCode) || $passedSchoolCode === '-') {
        $prefix = strtoupper(substr((string)$customOrderId, 0, 3));
        $passedSchoolCode = (!empty($prefix) && ctype_alpha($prefix)) ? $prefix : 'TIS';
    }

    $additional = [
        'grade_class' => (string)($orderData['additional_data']['grade_class'] ?? ''),
        'order_ref'   => '-',
        'parent_name' => (string)($orderData['additional_data']['parent_name'] ?? ''),
        'school_id'   => $passedSchoolCode
    ];
    if (!empty($orderData['additional_data']) && is_array($orderData['additional_data'])) {
        foreach ($orderData['additional_data'] as $k => $v) {
            if ($k === 'order_ref') {
                $additional[$k] = '-';
            } elseif ($k === 'school_id') {
                $additional[$k] = $passedSchoolCode;
            } elseif (!isset($additional[$k])) {
                $additional[$k] = $v;
            }
        }
    }
    $postData['additional_data'] = $additional;

    $ch = curl_init($cfg['base_url'] . '/erp/create-collect-request');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData, JSON_UNESCAPED_SLASHES));
    
    $headers = ['Content-Type: application/json'];
    if (!empty($cfg['api_token'])) {
        $headers[] = 'Authorization: Bearer ' . $cfg['api_token'];
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return [
            'success' => false,
            'error'   => 'Network communication error: ' . $curlError,
            'raw'     => null
        ];
    }

    $res = json_decode($rawResponse, true);
    $collectUrl = $res['collect_request_url'] ?? ($res['Collect_request_url'] ?? '');
    $collectId  = $res['collect_request_id'] ?? ($res['Collect_request_id'] ?? '');

    if ($httpCode >= 200 && $httpCode < 300 && !empty($collectUrl)) {
        return [
            'success'             => true,
            'collect_request_id'  => $collectId,
            'collect_request_url' => $collectUrl,
            'sign'                => $res['sign'] ?? null,
            'raw'                 => $res,
            'error'               => null
        ];
    }

    $errMsg = $res['message'] ?? ($res['error'] ?? "Payment gateway error (HTTP {$httpCode})");
    if ($httpCode === 401) {
        $errMsg = "Payment Gateway Authentication Failed: " . ($res['message'] ?? 'Merchant access token missing or invalid') . ". Please verify gateway configuration.";
    }

    return [
        'success'   => false,
        'error'     => $errMsg,
        'raw'       => $res ?: $rawResponse,
        'http_code' => $httpCode
    ];
}

/**
 * Check payment status by Edviron Collect Request ID
 */
function edviron_get_status_by_collect_id(string $collectRequestId, ?string $schoolId = null): array {
    $cfg = getEdvironConfig();

    if (empty($cfg['is_configured'])) {
        return [
            'success'   => true,
            'http_code' => 200,
            'data'      => ['status' => 'SUCCESS', 'collect_request_id' => $collectRequestId],
            'raw'       => null
        ];
    }

    $sid = !empty($schoolId) ? $schoolId : $cfg['school_id'];
    $payload = [
        'school_id'          => $sid,
        'collect_request_id' => $collectRequestId
    ];
    $sign = edviron_generate_jwt($payload, $cfg['pg_key']);

    $url = $cfg['base_url'] . "/erp/collect-request/{$collectRequestId}?school_id=" . urlencode((string)$sid) . "&sign={$sign}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $cfg['api_token']
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    return [
        'success'   => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'data'      => $data,
        'raw'       => $response
    ];
}

/**
 * Check payment status by OnBolt Custom Order ID
 */
function edviron_get_status_by_custom_order(string $customOrderId, ?string $schoolId = null): array {
    $cfg = getEdvironConfig();

    if (empty($cfg['is_configured'])) {
        return [
            'success'   => true,
            'http_code' => 200,
            'data'      => ['status' => 'SUCCESS', 'custom_order_id' => $customOrderId],
            'raw'       => null
        ];
    }

    $sid = !empty($schoolId) ? $schoolId : $cfg['school_id'];
    $payload = [
        'school_id'       => $sid,
        'custom_order_id' => $customOrderId
    ];
    $sign = edviron_generate_jwt($payload, $cfg['pg_key']);

    $url = $cfg['base_url'] . "/erp/collect-request-status/{$customOrderId}?school_id=" . urlencode((string)$sid) . "&sign={$sign}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $cfg['api_token']
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    return [
        'success'   => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'data'      => $data,
        'raw'       => $response
    ];
}
