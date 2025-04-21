<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

require_once _PS_MODULE_DIR_ . 'leanx/classes/LeanXHelper.php';

class LeanxValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $logFile = _PS_ROOT_DIR_ . '/var/logs/leanx_validation.log';

        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        $orderTotal = $cart->getOrderTotal(true, Cart::BOTH);
        $currency = $this->context->currency;

        // Pull config
        $authToken = Configuration::get('LEANX_AUTH_TOKEN');
        $hashKey = Configuration::get('LEANX_HASH_KEY');
        $collectionUuid = Configuration::get('LEANX_COLLECTION_UUID');
        $billInvoiceId = Configuration::get('LEANX_BILL_INVOICE_ID');
        $isSandbox = (bool) Configuration::get('LEANX_IS_SANDBOX');

        // Prepare order ID & invoice ID
        $orderId = (int) $cart->id;

        // Create a PrestaShop order in “Waiting for Payment” state
        $this->module->validateOrder(
            $cart->id,
            Configuration::get('LEANX_OS_AWAITING'),
            $orderTotal,
            $this->module->displayName,
            null,
            [],
            (int) $currency->id,
            false,
            $customer->secure_key
        );

        $fullName = $customer->firstname . ' ' . $customer->lastname;
        $email = $customer->email;
        $address = new Address($cart->id_address_invoice);
        $phoneNumber = $address->phone_mobile ?: $address->phone;
        $amount = $orderTotal;

        // Callback and success URLs
        $callbackUrl = $this->context->link->getModuleLink($this->module->name, 'callback', [], true);
        $successUrl = $this->context->link->getModuleLink('leanx', 'success', [
            'order_id' => $this->module->currentOrder
        ], true);

        $payload = [
            'collection_uuid' => $collectionUuid,
            'amount' => $amount,
            'redirect_url' => $successUrl,
            'callback_url' => $callbackUrl,
            'full_name' => $fullName,
            'email' => $email,
            'phone_number' => $phoneNumber,
            'client_data' => $orderId
        ];
        file_put_contents($logFile, "Payload: " . print_r($payload, true) . "\n", FILE_APPEND);

        $leanxInvoiceId = $billInvoiceId . '-' . $orderId;
        $baseUrl = $isSandbox ? 'https://api.leanx.dev' : 'https://api.leanx.io';
        $apiUrl = $baseUrl . '/api/v1/public-merchant/public/collection-payment-portal?invoice_no=' . urlencode($leanxInvoiceId);

        $responseData = LeanXHelper::callApi($apiUrl, $payload, $authToken);

        // Log response:
        PrestaShopLogger::addLog('Order #' . $orderId . ' created: Awaiting payment on LeanX Payment Gateway', 1);
        file_put_contents($logFile, "Create Bill API Response: " . print_r($responseData, true) . "\n", FILE_APPEND);

        if (!isset($responseData['data']['redirect_url'])) {
            die('Invalid LeanX response. Payment cannot proceed.');
        }

        // Redirect to LeanX-hosted payment page
        Tools::redirect($responseData['data']['redirect_url']);
    }
}