<?php
/**
 * Lightweight, Dependency-Free JWT Helper (HS256) in Pure PHP
 */

require_once __DIR__ . '/config.php';

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

function generate_jwt($payload) {
    $header = json_encode(['typ' => 'JWT', 'alg' => JWT_ALGORITHM]);
    
    $now = time();
    $expire = $now + (ACCESS_TOKEN_EXPIRE_MINUTES * 60);
    $payload['iat'] = $now;
    $payload['exp'] = $expire;

    $base64UrlHeader = base64url_encode($header);
    $base64UrlPayload = base64url_encode(json_encode($payload));

    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET_KEY, true);
    $base64UrlSignature = base64url_encode($signature);

    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

function decode_jwt($token) {
    if (!$token || $token === 'undefined' || strpos(strtolower($token), 'guest') !== false) {
        return null;
    }

    // Strip "Bearer " prefix if present
    if (strpos($token, 'Bearer ') === 0) {
        $token = substr($token, 7);
    }

    $tokenParts = explode('.', $token);
    if (count($tokenParts) !== 3) {
        return null;
    }

    list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $tokenParts;

    $signature = base64url_decode($base64UrlSignature);
    $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET_KEY, true);

    if (!hash_equals($signature, $expectedSignature)) {
        return null;
    }

    $payload = json_decode(base64url_decode($base64UrlPayload), true);
    if (!$payload) {
        return null;
    }

    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return null; // Expired
    }

    return $payload;
}

function get_authenticated_user() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    $defaultUser = [
        'id' => 1,
        'name' => 'Meteorologist',
        'email' => 'meteorologist@imd.gov.in',
        'role' => 'Operational Meteorologist',
        'organization' => 'India Meteorological Department (IMD)',
        'response_tone' => 'moderate',
        'custom_instructions' => '',
        'use_emojis' => true
    ];

    if (!$authHeader) {
        return $defaultUser;
    }

    $payload = decode_jwt($authHeader);
    if (!$payload) {
        return $defaultUser;
    }

    return [
        'id' => $payload['sub'] ?? 1,
        'name' => $payload['name'] ?? 'Meteorologist',
        'email' => $payload['email'] ?? 'meteorologist@imd.gov.in',
        'role' => $payload['role'] ?? 'Authorized Meteorologist',
        'organization' => $payload['organization'] ?? 'India Meteorological Department (IMD)',
        'response_tone' => $payload['response_tone'] ?? 'moderate',
        'custom_instructions' => $payload['custom_instructions'] ?? '',
        'use_emojis' => isset($payload['use_emojis']) ? (bool)$payload['use_emojis'] : true
    ];
}
