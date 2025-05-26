<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class LeanXHelper
{
    // API calls with auth token
    public static function callApi($url, $payload, $authToken)
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'auth-token: ' . $authToken
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $raw = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($raw, true);
        return $decoded ?: ['response_code' => 0, 'description' => 'Invalid response'];
    }

    // Generic API calls
    public static function postJson($url, $payload, array $headers = [], $timeout = 30)
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array_merge([
                'Content-Type: application/json',
                'Accept: application/json'
            ], $headers),
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $timeout,
        ]);

        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'http_code' => $status,
            'raw' => $raw,
            'body' => json_decode($raw, true)
        ];
    }
}
