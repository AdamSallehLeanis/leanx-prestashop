<?php

class LeanxCallbackModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $logFile = _PS_ROOT_DIR_ . '/leanx_callback.log';

        try {
            $rawInput = file_get_contents('php://input');
            file_put_contents($logFile, "Raw input: $rawInput\n", FILE_APPEND);

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

            $postData = json_encode([
                'signed' => $signed,
                'secret_key' => $hashKey,
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_TIMEOUT, 90);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            file_put_contents($logFile, "Decode API Response: $response\n", FILE_APPEND);

            if ($httpCode >= 400 || !$response) {
                throw new Exception("API Error with response code $httpCode");
            }

            $decoded = json_decode($response, true);
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
            $pendingStates = [Configuration::get('PS_OS_PENDING'), Configuration::get('PS_OS_OUTOFSTOCK')];

            if ($invoiceStatus === 'SUCCESS' && in_array($currentStatus, $pendingStates)) {
                $order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
            } elseif (in_array($currentStatus, $pendingStates)) {
                $order->setCurrentState(Configuration::get('PS_OS_CANCELED'));
            }

            // Optional: log transaction into a custom table (manual setup required)
            // Example (commented out):
            // Db::getInstance()->insert('leanx_transaction_details', [
            //     'order_id' => $orderId,
            //     'unique_id' => pSQL($merchantInvoiceNo),
            //     'api_key' => pSQL($uuid),
            //     'callback_data' => pSQL(serialize($signed)),
            //     'data' => pSQL($amount),
            //     'invoice_id' => pSQL($invoiceNo),
            // ]);

            file_put_contents($logFile, "Successfully processed callback for order #$orderId\n", FILE_APPEND);

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