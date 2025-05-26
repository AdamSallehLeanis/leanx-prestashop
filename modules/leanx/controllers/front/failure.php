<?php

class LeanxFailureModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        // Step 1: if retry is POSTed
        if (Tools::isSubmit('retry_checkout')) {
            $this->cloneCartAndRedirect();
            return;
        }

        // Step 2: just display failure.tpl
        $this->context->smarty->assign([
            'order_id' => Tools::getValue('order_id'),
        ]);

        $this->setTemplate('module:leanx/views/templates/front/failure.tpl');
    }

    private function cloneCartAndRedirect()
    {
        $orderId = (int) Tools::getValue('order_id');
        if (!$orderId) {
            die('Missing order ID');
        }

        $order = new Order($orderId);
        $oldCart = new Cart($order->id_cart);

        $newCart = new Cart();
        $newCart->id_customer = $this->context->customer->id;
        $newCart->id_address_delivery = $oldCart->id_address_delivery;
        $newCart->id_address_invoice = $oldCart->id_address_invoice;
        $newCart->id_currency = $oldCart->id_currency;
        $newCart->id_lang = $oldCart->id_lang;
        $newCart->save();

        // Set context early
        $this->context->cart = $newCart;
        $this->context->cookie->id_cart = (int) $newCart->id;
        $this->context->cookie->write();

        $products = $oldCart->getProducts();
        $outOfStock = []; // ← make sure it's initialized

        foreach ($products as $product) {
            $result = $newCart->updateQty(
                (int) $product['cart_quantity'],
                (int) $product['id_product'],
                (int) $product['id_product_attribute'],
                null,
                'up'
            );

            if (!$result) {
                $outOfStock[] = $product['name'];
            }
        }

        if (!empty($outOfStock)) {
            // Store as JSON string in cookie
            $this->context->cookie->__set('leanx_restore_out_of_stock', json_encode($outOfStock));
            $this->context->cookie->write();
        }

        // Redirect to cart page with full cart summary view
        Tools::redirect($this->context->link->getPageLink('cart', true) . '?action=show');
    }
}
