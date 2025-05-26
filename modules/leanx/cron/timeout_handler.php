<?php

require_once dirname(__DIR__, 3) . '/config/config.inc.php';
require_once dirname(__DIR__, 3) . '/init.php';
require_once _PS_MODULE_DIR_ . 'leanx/classes/LeanXHelper.php';

$logFile = _PS_ROOT_DIR_ . '/var/logs/leanx_timeout.log';
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Scheduled Timeout Handler started\n", FILE_APPEND);
$timeoutMinutes = (int) Configuration::get('LEANX_TIMEOUT_MINUTES');
if (!$timeoutMinutes || $timeoutMinutes <= 0) {
    $timeoutMinutes = 30; // fallback default
}

try {
    $awaitingState = (int) Configuration::get('LEANX_OS_AWAITING');
    if (!$awaitingState) {
        throw new Exception('LEANX_OS_AWAITING not configured.');
    }

    $threshold = date('Y-m-d H:i:s', strtotime("-{$timeoutMinutes} minutes"));

    $sql = new DbQuery();
    $sql->select('o.id_order, o.date_add, o.id_cart')
        ->from('orders', 'o')
        ->where('o.current_state = ' . $awaitingState)
        ->where('o.date_add < "' . pSQL($threshold) . '"');

    $orders = Db::getInstance()->executeS($sql);

    file_put_contents($logFile, "[" . date('c') . "] Checking " . count($orders) . " order(s)\n", FILE_APPEND);

    foreach ($orders as $row) {
        $orderId = (int) $row['id_order'];
        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            continue;
        }

        $billInvoiceId = Configuration::get('LEANX_BILL_INVOICE_ID');
        $authToken = Configuration::get('LEANX_AUTH_TOKEN');
        $isSandbox = (bool) Configuration::get('LEANX_IS_SANDBOX');

        $invoiceNo = $billInvoiceId . '-' . $orderId;
        $apiUrl = $isSandbox
            ? "https://api.leanx.dev/api/v1/public-merchant/public/manual-checking-transaction?invoice_no={$invoiceNo}"
            : "https://api.leanx.io/api/v1/public-merchant/public/manual-checking-transaction?invoice_no={$invoiceNo}";

        $response = LeanXHelper::callApi($apiUrl, [], $authToken);
        $invoiceStatus = $response['data']['transaction_details']['invoice_status'] ?? null;

        file_put_contents($logFile, "[" . date('c') . "] Order #$orderId → invoice status = " . ($invoiceStatus ?? 'N/A') . "\n", FILE_APPEND);

        if ($invoiceStatus === 'SUCCESS') {
            // Paid — do nothing
            file_put_contents($logFile, "[" . date('c') . "] Order #$orderId already paid. Skipping.\n", FILE_APPEND);
        } elseif (!$invoiceStatus) {
            // Invoice not found → treat as abandoned
            $history = new OrderHistory();
            $history->id_order = $orderId;
            $history->changeIdOrderState(Configuration::get('PS_OS_CANCELED'), $orderId);
            $history->addWithemail(true);
            file_put_contents($logFile, "[" . date('c') . "] Order #$orderId cancelled: order timed out and no invoice found in LeanX.\n", FILE_APPEND);
        } elseif (in_array($invoiceStatus, ['FAILED', 'SENANGPAY_STOP_CHECK_FROM_PS', 'FPX_STOP_CHECK_FROM_PS'])) {
            // Explicit failure
            $history = new OrderHistory();
            $history->id_order = $orderId;
            $history->changeIdOrderState(Configuration::get('PS_OS_CANCELED'), $orderId);
            $history->addWithemail(true);
            file_put_contents($logFile, "[" . date('c') . "] Order #$orderId cancelled: invoice failed status = $invoiceStatus\n", FILE_APPEND);
        } else {
            file_put_contents($logFile, "[" . date('c') . "] Order #$orderId not cancelled: status = $invoiceStatus\n", FILE_APPEND);
        }
    }

} catch (Exception $e) {
    file_put_contents($logFile, "[" . date('c') . "] ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    exit(1);
}
