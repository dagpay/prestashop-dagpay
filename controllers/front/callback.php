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

class DagpayCallbackModuleFrontController extends ModuleFrontController
{
    /**
     * Handle POST callback status.
     */
    public function postProcess()
    {
        $this->ajaxGetReference();
        parent::postProcess();
    }

    /**
     * Change order status state.
     */
    public function ajaxGetReference()
    {
        $inputJSON = Tools::file_get_contents('php://input');
        $input = json_decode($inputJSON, true);

        $dagpayInvoiceStatus = $input['state'];
        $cartId = $input['paymentId'];

        $order_id = Order::getOrderByCartId($cartId);
        $order = new Order($order_id);

        try {
            if (!$order) {
                $error_message = 'Dagpay Order #' . $input['order_id'] . ' does not exists';

                $this->logError($error_message, $cartId);
                throw new Exception($error_message);
            }
            if (!$this->checkInvoiceSignature($input)) {
                $error_message = 'Invalid signature provided';

                $this->logError($error_message, $cartId);
                throw new Exception($error_message);
            }
            switch ($dagpayInvoiceStatus) {
                case 'PAID':
                case 'PAID_EXPIRED':
                    if (((float)$order->getOrdersTotalPaid()) == ($input['currencyAmount'])) {
                        $order_status = 'PS_OS_PAYMENT';
                    } else {
                        $order_status = 'DAGPAY_FAILED';
                        PrestaShopLogger::addLog('PS Orders Total does not match with Coingate Price Amount');
                    }
                    break;
                case 'PENDING':
                    $order_status = 'DAGPAY_PENDING';
                    break;
                case 'WAITING_FOR_CONFIRMATION':
                    $order_status = 'DAGPAY_WAITING';
                    break;
                case 'EXPIRED':
                    $order_status = 'DAGPAY_EXPIRED';
                    break;
                case 'FAILED':
                    $order_status = 'DAGPAY_FAILED';
                    break;
                case 'CANCELLED':
                    $order_status = 'PS_OS_CANCELED';
                    break;
                default:
                    $order_status = false;
            }

            if ($order_status !== false) {
                $history = new OrderHistory();
                $history->id_order = $order->id;
                $history->changeIdOrderState((int)Configuration::get($order_status), $order->id);
                $history->add(true, array(
                      'order_name' => $cartId,
                    ));

                $this->context->smarty->assign(array(
                 'text' => 'OK'
                ));
            } else {
                $this->context->smarty->assign(array(
                 'text' => 'Order Status ' . $dagpayInvoiceStatus . ' not implemented'
                ));
            }
        } catch (Exception $e) {
            $this->context->smarty->assign(array(
                'text' => get_class($e) . ': ' . $e->getMessage()
            ));
        }
        if (_PS_VERSION_ >= '1.7') {
            $this->setTemplate('module:dagpay/views/templates/front/callback.tpl');
        } else {
            $this->setTemplate('callback.tpl');
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
     * Check received signature from status callback.
     * @param  array $info  Callback payload data.
     * @return bool         Checked state of received and expected signature.
     */
    private function checkInvoiceSignature($info)
    {
        $clientInstance = $this->getClientInstance();
        $expectedSignature = $clientInstance->getInvoiceInfoSignature($info);
        $receivedSignature = $info['signature'];

        return $expectedSignature == $receivedSignature;
    }

    /**
     * Log error to PrestaShop log.
     * @param  string $message Error message.
     * @param  int    $cart_id Order id.
     * @return void
     */
    private function logError($message, $cart_id)
    {
        PrestaShopLogger::addLog($message, 3, null, 'Cart', $cart_id, true);
    }
}
