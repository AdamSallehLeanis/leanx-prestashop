<?php

require_once _PS_MODULE_DIR_ . 'leanx/classes/LeanXHelper.php';

class LeanxSuccessModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $logFile = _PS_ROOT_DIR_ . '/var/logs/leanx_validation.log';

        $orderId = (int) Tools::getValue('order_id');
        if (!$orderId) {
            die('Missing order ID');
        }

        $order = new Order($orderId);
        $authToken = Configuration::get('LEANX_AUTH_TOKEN');
        $isSandbox = (bool) Configuration::get('LEANX_IS_SANDBOX');
        $leanxInvoiceId = Configuration::get('LEANX_BILL_INVOICE_ID') . '-' . $orderId;
        $baseUrl = $isSandbox ? 'https://api.leanx.dev' : 'https://api.leanx.io';
        $apiUrl = $baseUrl . '/api/v1/public-merchant/public/manual-checking-transaction?invoice_no=' . urlencode($leanxInvoiceId);

        $responseData = LeanXHelper::callApi($apiUrl, [], $authToken);

        if (!isset($responseData['data']['transaction_details']['invoice_status'])) {
            die('Invalid API response.');
        }

        $invoiceStatus = $responseData['data']['transaction_details']['invoice_status'];

        // Log response:
        file_put_contents($logFile, "Transaction Status Check for Order #" . $orderId . " using Invoice ID: " . $leanxInvoiceId . "\n", FILE_APPEND);
        file_put_contents($logFile, "Manual Check API Response: " . print_r($responseData, true) . "\n", FILE_APPEND);

        // Handle based on status
        if ($invoiceStatus === 'SUCCESS') {
            $this->updateOrderStatus($order, Configuration::get('PS_OS_PAYMENT')); // Paid
        } elseif (in_array($invoiceStatus, ['FAILED', 'SENANGPAY_STOP_CHECK_FROM_PS', 'FPX_STOP_CHECK_FROM_PS'])) {
            $this->updateOrderStatus($order, Configuration::get('PS_OS_CANCELED')); // Canceled
        } else {
            // Optional: handle PENDING, etc.
        }

        // Redirect to confirmation or cart depending on status
        if ($invoiceStatus === 'SUCCESS') {
            PrestaShopLogger::addLog('Payment successful for order #' . $orderId . ' on LeanX Payment Gateway', 1);
            Tools::redirect($this->context->link->getPageLink('order-confirmation', true, null, [
                'id_cart' => $order->id_cart,
                'id_module' => $this->module->id,
                'id_order' => $order->id,
                'key' => $this->context->customer->secure_key,
            ]));
        } else {
            PrestaShopLogger::addLog('Payment failed for order #' . $orderId . ' on LeanX Payment Gateway', 1);
            Tools::redirect($this->context->link->getModuleLink('leanx', 'failure', [
                'order_id' => $orderId,
            ]));
        }
    }

    private function updateOrderStatus(Order $order, $statusId)
    {
        if ((int) $order->getCurrentState() === (int) $statusId) {
            return;
        }

        $history = new OrderHistory();
        $history->id_order = $order->id;
        $history->changeIdOrderState($statusId, $order->id);
        $history->addWithemail(true);
    }
}