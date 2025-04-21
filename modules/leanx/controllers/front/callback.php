<?php

class LeanxCallbackModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $logFile = _PS_ROOT_DIR_ . '/var/logs/leanx_callback.log';

        try {
            $rawInput = file_get_contents('php://input');
            $requestData = json_decode($rawInput, true);
            file_put_contents($logFile, "Decoded input: " . print_r($requestData, true) . "\n", FILE_APPEND);

            if (!isset($requestData['data'])) {
                throw new Exception('No data found in callback payload.');
            }

            $signed = $requestData['data'];

            // Prepare API request to decode signed token
            $isSandbox = Configuration::get('LEANX_IS_SANDBOX');
            $hashKey = Configuration::get('LEANX_HASH_KEY');
            $url = $isSandbox ? 'https://api.leanx.dev/api/v1/jwt/decode' : 'https://api.leanx.io/api/v1/jwt/decode';
            
            $response = LeanXHelper::postJson($url, [
                'signed' => $signed,
                'secret_key' => $hashKey
            ], [], 90);

            file_put_contents($logFile, "Decode API Response: " . $response['raw'] . "\n", FILE_APPEND);

            if ($response['http_code'] >= 400 || !$response['raw']) {
                throw new Exception("API Error with response code " . $response['http_code']);
            }

            $decoded = $response['body'];
            if (!isset($decoded['data']['client_data']['order_id'])) {
                throw new Exception('Order ID not found in API response.');
            }

            $processData = $decoded['data'];
            $orderId = (int) $processData['client_data']['order_id'];
            $invoiceNo = $processData['invoice_no'] ?? '';
            $merchantInvoiceNo = $processData['client_data']['merchant_invoice_no'] ?? '';
            $uuid = $processData['client_data']['uuid'] ?? '';
            $invoiceStatus = $processData['invoice_status'] ?? '';
            $amount = $processData['amount'] ?? '';

            file_put_contents($logFile, "Order ID: $orderId, Invoice No: $invoiceNo, Status: $invoiceStatus, Amount: $amount\n", FILE_APPEND);

            // Update order status
            $order = new Order($orderId);
            if (!Validate::isLoadedObject($order)) {
                throw new Exception("Order #$orderId not found.");
            }

            $currentStatus = $order->getCurrentState();
            $pendingStates = [Configuration::get('PS_OS_PENDING'), Configuration::get('PS_OS_OUTOFSTOCK'), Configuration::get('LEANX_OS_AWAITING')];

            if ($invoiceStatus === 'SUCCESS' && in_array($currentStatus, $pendingStates)) {
                $order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
            } elseif (in_array($currentStatus, $pendingStates)) {
                $order->setCurrentState(Configuration::get('PS_OS_CANCELED'));
            }

            file_put_contents($logFile, "Successfully processed callback for order #$merchantInvoiceNo\n", FILE_APPEND);
            PrestaShopLogger::addLog('Successfully processed callback for order #' . $orderId . ' by LeanX Payment Gateway', 1);
            PrestaShopLogger::addLog('Payment successful for order #' . $orderId . ' on LeanX Payment Gateway', 1);

            header('Content-Type: application/json');
            http_response_code(200);
            echo json_encode(['status' => 'ok', 'order_id' => $orderId]);
        } catch (Exception $e) {
            file_put_contents($logFile, "Callback error: {$e->getMessage()}\n", FILE_APPEND);
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }

        exit;
    }
}