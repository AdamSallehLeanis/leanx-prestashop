<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class LeanXHelper
{
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
}