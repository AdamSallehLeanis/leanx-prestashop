<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class LeanxValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
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
            Configuration::get('PS_OS_OUTOFSTOCK'),
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

        $leanxInvoiceId = $billInvoiceId . '-' . $orderId;
        $baseUrl = $isSandbox ? 'https://api.leanx.dev' : 'https://api.leanx.io';
        $apiUrl = $baseUrl . '/api/v1/public-merchant/public/collection-payment-portal?invoice_no=' . urlencode($leanxInvoiceId);
        // $apiUrl = 'https://api.leanx.dev/api/v1/public-merchant/public/collection-payment-portal?invoice_no=' . urlencode($leanxInvoiceId);

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'auth-token: ' . $authToken
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            die('Error communicating with LeanX payment API');
        }

        $responseData = json_decode($response, true);

        file_put_contents(_PS_ROOT_DIR_ . '/leanx_debug.json', $response);
        if (!isset($responseData['data']['redirect_url'])) {
            die('Invalid LeanX response. Payment cannot proceed.');
        }

        // Redirect to LeanX-hosted payment page
        Tools::redirect($responseData['data']['redirect_url']);
    }
}