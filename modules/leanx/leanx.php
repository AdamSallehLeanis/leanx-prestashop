<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

require_once __DIR__ . '/classes/LeanXHelper.php';

class LeanX extends PaymentModule
{
    const CONFIG_IS_SANDBOX = 'LEANX_IS_SANDBOX';
    const CONFIG_AUTH_TOKEN = 'LEANX_AUTH_TOKEN';
    const CONFIG_HASH_KEY = 'LEANX_HASH_KEY';
    const CONFIG_COLLECTION_UUID = 'LEANX_COLLECTION_UUID';
    const CONFIG_BILL_INVOICE_ID = 'LEANX_BILL_INVOICE_ID';
    const CONFIG_TIMEOUT_MINUTES = 'LEANX_TIMEOUT_MINUTES';

    public function __construct()
    {
        $this->name = 'leanx';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.3';
        $this->author = 'Adam Salleh';
        $this->controllers = ['validation', 'success', 'callback'];
        $this->is_eu_compatible = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('LeanX');
        $this->description = $this->l('Accept payments through online banking, e-wallets, or credit cards with LeanX Payment Gateway');

        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitLeanXConfig')) {
            $authToken = Tools::getValue(self::CONFIG_AUTH_TOKEN);
            $collectionUuid = Tools::getValue(self::CONFIG_COLLECTION_UUID);
            $isSandbox = (bool) Tools::getValue(self::CONFIG_IS_SANDBOX);
            $hashKey = Tools::getValue(self::CONFIG_HASH_KEY);
            $timeoutMinutes = Tools::getValue('LEANX_TIMEOUT_MINUTES');
        
            $baseUrl = $isSandbox ? 'https://api.leanx.dev' : 'https://api.leanx.io';
        
            $errors = [];
        
            // Check empty values
            if (empty($authToken)) {
                $errors[] = $this->l('Auth Token is required.');
            }
        
            if (empty($collectionUuid)) {
                $errors[] = $this->l('Collection UUID is required.');
            }

            if (empty($hashKey)) {
                $errors[] = $this->l('Hash Key is required.');
            }

            // Check that timeout is numeric and positive (optional field, but validated if set)
            if (!empty($timeoutMinutes) && (!is_numeric($timeoutMinutes) || (int) $timeoutMinutes <= 0)) {
                $errors[] = $this->l('Order Timeout must be a positive number.');
            }

            // Validate API Key
            if (empty($errors)) {
                $validateUrl = $baseUrl . '/api/v1/public-merchant/validate';
                $response = LeanXHelper::callApi($validateUrl, ['api_key' => $authToken], $authToken);
        
                if ($response['response_code'] !== 2000 || $response['description'] !== 'SUCCESS') {
                    $errors[] = $this->l('Invalid Auth Token.');
                }
            }
        
            // Validate Collection UUID
            if (empty($errors)) {
                $validateCollectionUrl = $baseUrl . '/api/v1/public-merchant/validate-collection-id';
                $response = LeanXHelper::callApi($validateCollectionUrl, ['uuid' => $collectionUuid], $authToken);
        
                if ($response['response_code'] !== 2000 || $response['description'] !== 'SUCCESS') {
                    $errors[] = $this->l('Invalid Collection UUID.');
                }
            }
        
            if (empty($errors)) {
                Configuration::updateValue(self::CONFIG_IS_SANDBOX, $isSandbox);
                Configuration::updateValue(self::CONFIG_AUTH_TOKEN, $authToken);
                Configuration::updateValue(self::CONFIG_HASH_KEY, $hashKey);
                Configuration::updateValue(self::CONFIG_COLLECTION_UUID, $collectionUuid);
                Configuration::updateValue(self::CONFIG_BILL_INVOICE_ID, Tools::getValue(self::CONFIG_BILL_INVOICE_ID));
                Configuration::updateValue(self::CONFIG_TIMEOUT_MINUTES, (int) $timeoutMinutes);
        
                $output .= $this->displayConfirmation($this->l('Settings updated and validated.'));
            } else {
                foreach ($errors as $err) {
                    $output .= $this->displayError($err);
                }
            }
        } elseif (Tools::isSubmit('submitCheckCronStatus')) {
            $logFile = _PS_ROOT_DIR_ . '/var/logs/leanx_timeout.log';
            if (file_exists($logFile)) {
                $lines = array_reverse(file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
                foreach ($lines as $line) {
                    if (preg_match('/^\[(.*?)\]/', $line, $matches)) {
                        $lastRunTime = strtotime($matches[1]);
                        break;
                    }
                }
        
                if (isset($lastRunTime)) {
                    $diffMinutes = round((time() - $lastRunTime) / 60);
                    if ($diffMinutes <= 20) {
                        $output .= $this->displayConfirmation(
                            $this->l('LeanX timeout handler is running.') .
                            '<br>' .
                            sprintf($this->l('Last run was %d minutes ago (%s).'), $diffMinutes, date('Y-m-d H:i:s', $lastRunTime))
                        );
                    } else {
                        $output .= $this->displayWarning(
                            $this->l('⚠️ LeanX timeout handler has not run in the past 20 minutes.') .
                            '<br>' .
                            $this->l('Ensure the following CRON job is set up:') .
                            '<br><code>*/15 * * * * /usr/bin/php ' . _PS_MODULE_DIR_ . 'leanx/cron/timeout_handler.php > /dev/null 2>&1</code>'
                        );
                    }
                } else {
                    $output .= $this->displayWarning(
                        $this->l('⚠️ Unable to extract timestamp from the cron log.')
                    );
                }
            } else {
                $output .= $this->displayWarning(
                    $this->l('The LeanX timeout handler CRON job has not been set up.') .
                    '<br><br>' .
                    $this->l('This job is required to automatically cancel unpaid orders after a timeout period.') .
                    '<br><br>' .
                    $this->l('Please follow the full CRON setup instructions in the official module README:') .
                    '<br><a href="https://github.com/AdamSallehLeanis/leanx-prestashop#️-cron-integration-for-timeout-handler" target="_blank">' .
                    $this->l('📖 View CRON setup on GitHub') .
                    '</a>'
                );
            }
        }

        return $output . $this->renderForm();
    }

    private function renderForm()
    {
        $defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');

        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => $this->l('LeanX Gateway Settings'),
            ],
            'input' => [
                [
                    'type' => 'switch',
                    'label' => $this->l('Sandbox Mode'),
                    'name' => 'LEANX_IS_SANDBOX',
                    'is_bool' => true,
                    'desc' => $this->l('Enable sandbox/test mode for the LeanX payment gateway'),
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ],
                        [
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        ]
                    ]
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Auth Token'),
                    'name' => 'LEANX_AUTH_TOKEN',
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Hash Key'),
                    'name' => 'LEANX_HASH_KEY',
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Collection UUID'),
                    'name' => 'LEANX_COLLECTION_UUID',
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Bill Invoice ID'),
                    'name' => 'LEANX_BILL_INVOICE_ID',
                    'required' => false,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Order Timeout (minutes)'),
                    'name' => 'LEANX_TIMEOUT_MINUTES',
                    'desc' => '<div style="margin-top:5px;color:#7a7a7a;">' 
                        . $this->l('Optional: auto-cancel orders after X minutes if unpaid. Default: 30 minutes') 
                        . '</div>' 
                        . '<br>'
                        . '<button type="submit" name="submitCheckCronStatus" class="btn btn-info">' 
                        . $this->l('Check Cron Status') 
                        . '</button>',
                    'required' => false,
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right',
                'name' => 'submitLeanXConfig',
            ],
        ];

        $timeoutValue = Configuration::get(self::CONFIG_TIMEOUT_MINUTES);
        if (empty($timeoutValue) || !is_numeric($timeoutValue) || (int) $timeoutValue <= 0) {
            $timeoutValue = 30;
        }

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;
        $helper->title = $this->displayName;
        $helper->show_cancel_button = false;
        $helper->fields_value = [
            'LEANX_IS_SANDBOX' => (int) Configuration::get(self::CONFIG_IS_SANDBOX),
            'LEANX_AUTH_TOKEN' => Configuration::get(self::CONFIG_AUTH_TOKEN),
            'LEANX_HASH_KEY' => Configuration::get(self::CONFIG_HASH_KEY),
            'LEANX_COLLECTION_UUID' => Configuration::get(self::CONFIG_COLLECTION_UUID),
            'LEANX_BILL_INVOICE_ID' => Configuration::get(self::CONFIG_BILL_INVOICE_ID),
            'LEANX_TIMEOUT_MINUTES' => $timeoutValue,
        ];

        return $helper->generateForm($fieldsForm);
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('displayCartItemOOSAlert') &&
            $this->registerHook('displayFooter') &&
            $this->createLeanxOrderState();
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        $this->context->smarty->assign([
            'module_dir' => $this->_path,
        ]);

        $newOption = new PaymentOption();
        $newOption->setCallToActionText($this->l('Pay via LeanX'))
                ->setAdditionalInformation($this->context->smarty->fetch('module:leanx/views/templates/hook/payment_description.tpl'))
                ->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true));

        return [$newOption];
    }

    public function hookPaymentReturn($params)
    {
        // You can show a message after payment here
        return $this->fetch('module:leanx/views/templates/hook/payment_return.tpl');
    }

    public function hookDisplayCartItemOOSAlert($params)
    {
        if ($this->context->cookie->__isset('leanx_restore_out_of_stock')) {
            $json = $this->context->cookie->__get('leanx_restore_out_of_stock');
            $products = json_decode($json, true);

            if (!empty($products)) {
                $this->context->smarty->assign([
                    'out_of_stock_products' => $products,
                ]);

                $output = $this->fetch('module:leanx/views/templates/hook/out_of_stock_toast.tpl');

                $this->context->cookie->__unset('leanx_restore_out_of_stock');
                $this->context->cookie->write();

                return $output;
            }
        }

        return '';
    }

    public function hookDisplayFooter($params)
    {    
        // Only run on cart page
        if (Tools::getValue('controller') === 'cart') {
            return Hook::exec('displayCartItemOOSAlert', $params);
        }
    
        return '';
    }

    private function createLeanxOrderState()
    {
        // Check if a state with this name already exists
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $sql = 'SELECT id_order_state 
                FROM ' . _DB_PREFIX_ . 'order_state_lang 
                WHERE name = "Awaiting payment on LeanX" 
                AND id_lang = ' . $idLang;

        $existingId = (int) Db::getInstance()->getValue($sql);

        if ($existingId > 0) {
            Configuration::updateValue('LEANX_OS_AWAITING', $existingId);
            return true;
        }

        // Otherwise create new
        $orderState = new OrderState();
        $orderState->color = '#3498db';
        $orderState->send_email = false;
        $orderState->module_name = $this->name;
        $orderState->unremovable = false;
        $orderState->paid = false;
        $orderState->logable = true;

        foreach (Language::getLanguages(false) as $lang) {
            $orderState->name[$lang['id_lang']] = 'Awaiting payment on LeanX';
        }

        if ($orderState->add()) {
            Configuration::updateValue('LEANX_OS_AWAITING', (int) $orderState->id);
            return true;
        }

        return false;
    }
}