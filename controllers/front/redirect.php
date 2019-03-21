<?php
/**
* 2007-2019 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2019 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

include_once _PS_MODULE_DIR_.'dagpay/lib/DagpayClient.php';

class DagpayRedirectModuleFrontController extends ModuleFrontController
{

    /**
     * Redirect to Dagpay invoice page.
     */
    public function initContent()
    {
        if (Tools::getValue('action') == 'error') {
            return $this->displayError('An error occurred while trying to redirect the customer');
        } else {
            parent::initContent();

            $cart = $this->context->cart;

            if (!$this->module->checkCurrency($cart)) {
                Tools::redirect('index.php?controller=order');
            }

            $total = (float)number_format($cart->getOrderTotal(true), 2, '.', '');
            $currency = Context::getContext()->currency;

            $description = '';
            foreach ($cart->getProducts() as $product) {
                $description .= $product['cart_quantity'] . ' × ' . $product['name'].';';
            }
            $response = $this->redirectToPayment(
                $cart->id,
                $total,
                $description,
                $currency->iso_code
            );

            $customer = new Customer($cart->id_customer);

            $this->module->validateOrder(
                $cart->id,
                Configuration::get('DAGPAY_PENDING'),
                $total,
                $this->module->displayName,
                null,
                null,
                (int)$currency->id,
                false,
                $customer->secure_key
            );
            if ($response) {
                Tools::redirect($response['paymentUrl']);
            } else {
                Tools::redirect('index.php?controller=order&step=1');
            }
        }
    }

    /**
     * Configure Dagpay client.
     * @return DagpayClient
     */
    private function getClientInstance()
    {
        return new DagpayClient(
            Configuration::get('DAGPAY_ENVIRONMENT_ID'),
            Configuration::get('DAGPAY_USER_ID'),
            Configuration::get('DAGPAY_SECRET'),
            Configuration::get('DAGPAY_LIVE_MODE'),
            'standalone'
        );
    }

    /**
     * Create new invoice for Dagpay gateway redirect.
     * @param  int    $orderId     Order id.
     * @param  float  $total       Price of order.
     * @param  string $desc        Description to order.
     * @param  string $currency    Current currency.
     * @return array               Innvoice payload.
     */
    private function redirectToPayment($orderId, $total, $desc, $currency = 'DAG')
    {
        $client = $this->getClientInstance();
        $data = $client->createInvoice($orderId, $currency, $total, $desc);

        return $data;
    }
}
