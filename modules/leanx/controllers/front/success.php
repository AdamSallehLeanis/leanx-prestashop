<?php

class LeanxSuccessModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $orderId = (int) Tools::getValue('order_id');
        if (!$orderId) {
            die('Missing order ID');
        }

        $order = new Order($orderId);
        $invoiceNo = Configuration::get('LEANX_BILL_INVOICE_ID') . '-' . $orderId;

        $authToken = Configuration::get('LEANX_AUTH_TOKEN');
        $isSandbox = (bool) Configuration::get('LEANX_IS_SANDBOX');
        $apiUrl = $isSandbox
            ? "https://api.leanx.dev/api/v1/public-merchant/public/manual-checking-transaction?invoice_no={$invoiceNo}"
            : "https://api.leanx.io/api/v1/public-merchant/public/manual-checking-transaction?invoice_no={$invoiceNo}";

        // Prepare request
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'auth-token: ' . $authToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            die('Unable to verify transaction.');
        }

        $data = json_decode($response, true);

        if (!isset($data['data']['transaction_details']['invoice_status'])) {
            die('Invalid API response.');
        }

        $invoiceStatus = $data['data']['transaction_details']['invoice_status'];

        // Log if needed:
        // file_put_contents(_PS_ROOT_DIR_ . '/leanx_success_debug.json', json_encode($data));

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
            Tools::redirect($this->context->link->getPageLink('order-confirmation', true, null, [
                'id_cart' => $order->id_cart,
                'id_module' => $this->module->id,
                'id_order' => $order->id,
                'key' => $this->context->customer->secure_key,
            ]));
        } else {
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