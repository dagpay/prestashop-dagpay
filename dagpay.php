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

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

include_once _PS_MODULE_DIR_.'dagpay/lib/DagpayClient.php';

class Dagpay extends PaymentModule
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'dagpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Dagpay';
        $this->need_instance = 1;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Dagpay');
        $this->description = $this->l('Dagpay payment gateway plugin for accepting dagcoin payments.');

        $this->confirmUninstall = $this->l('Are you sure about removing these details?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }


    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        Configuration::updateValue('DAGPAY_LIVE_MODE', false);

        $order_pending = new OrderState();
        $order_pending->name = array_fill(0, 10, 'Pending Dagpay payment');
        $order_pending->send_email = 0;
        $order_pending->invoice = 0;
        $order_pending->color = 'RoyalBlue';
        $order_pending->unremovable = false;
        $order_pending->logable = 0;
        $order_pending->save();

        $order_expired = new OrderState();
        $order_expired->name = array_fill(0, 10, 'Expired Dagpay payment');
        $order_expired->send_email = 0;
        $order_expired->invoice = 0;
        $order_expired->color = '#DC143C';
        $order_expired->unremovable = false;
        $order_expired->logable = 0;
        $order_expired->save();

        $order_confirming = new OrderState();
        $order_confirming->name = array_fill(0, 10, 'Waiting Dagpay payment');
        $order_confirming->send_email = 0;
        $order_confirming->invoice = 0;
        $order_confirming->color = '#d9ff94';
        $order_confirming->unremovable = false;
        $order_confirming->logable = 0;
        $order_confirming->save();

        $order_invalid = new OrderState();
        $order_invalid->name = array_fill(0, 10, 'Failed Dagpay payment');
        $order_invalid->send_email = 0;
        $order_invalid->invoice = 0;
        $order_invalid->color = '#8f0621';
        $order_invalid->unremovable = false;
        $order_invalid->logable = 0;
        $order_invalid->save();
        
        Configuration::updateValue('DAGPAY_PENDING', $order_pending->id);
        Configuration::updateValue('DAGPAY_EXPIRED', $order_expired->id);
        Configuration::updateValue('DAGPAY_WAITING', $order_confirming->id);
        Configuration::updateValue('DAGPAY_FAILED', $order_invalid->id);

        return parent::install() &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('paymentReturn');
    }

    public function uninstall()
    {
        $order_state_pending    = new OrderState(Configuration::get('DAGPAY_PENDING'));
        $order_state_expired    = new OrderState(Configuration::get('DAGPAY_EXPIRED'));
        $order_state_confirming = new OrderState(Configuration::get('DAGPAY_WAITING'));
        $order_state_failed     = new OrderState(Configuration::get('DAGPAY_FAILED'));

        $order_state_pending->delete();
        $order_state_expired->delete();
        $order_state_confirming->delete();
        $order_state_failed->delete();

        Configuration::deleteByName('DAGPAY_LIVE_MODE');
        Configuration::deleteByName('DAGPAY_ENVIRONMENT_ID');
        Configuration::deleteByName('DAGPAY_USER_ID');
        Configuration::deleteByName('DAGPAY_SECRET');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        if (((bool)Tools::isSubmit('submitDagpayModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        return $this->renderForm();
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitDagpayModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'DAGPAY_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'desc' => $this->l('Get your environmentID from https://dagpay.io/ or https://test.dagpay.io/'),
                        'name' => 'DAGPAY_ENVIRONMENT_ID',
                        'label' => $this->l('Environment ID'),
                    ),
                    array(
                        'type' => 'text',
                        'desc' => $this->l('Get your user ID from https://dagpay.io/ or https://test.dagpay.io/'),
                        'name' => 'DAGPAY_USER_ID',
                        'label' => $this->l('User ID'),
                    ),
                    array(
                        'type' => 'password',
                        'name' => 'DAGPAY_SECRET',
                        'desc' => $this->l('Get your secret from https://dagpay.io/ or https://test.dagpay.io/'),
                        'label' => $this->l('Secret'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    protected function getConfigFormValues()
    {
        return array(
            'DAGPAY_LIVE_MODE' => Configuration::get('DAGPAY_LIVE_MODE', true),
            'DAGPAY_ENVIRONMENT_ID' => Configuration::get('DAGPAY_ENVIRONMENT_ID', 'your environment ID'),
            'DAGPAY_USER_ID' => Configuration::get('DAGPAY_USER_ID', 'your user ID'),
            'DAGPAY_SECRET' => Configuration::get('DAGPAY_SECRET', null),
        );
    }

    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $externalOption = new PaymentOption();
        $externalOption->setModuleName($this->name)
               ->setCallToActionText($this->l('Dagpay'))
               ->setAction($this->context->link->getModuleLink($this->name, 'redirect', array(), true));

        $this->smarty->assign('status', 'ok');
        $paymentOptions = array($externalOption);

        return $paymentOptions;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
        if (_PS_VERSION_ < 1.7) {
            $order = $params['objOrder'];
            $state = $order->current_state;
        } else {
            $state = $params['order']->getCurrentState();
        }
        $this->smarty->assign(array(
            'state' => $state,
            'paid_state' => (int)Configuration::get('PS_OS_PAYMENT'),
            'this_path' => $this->_path,
            'this_path_bw' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
        ));

        return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');
    }
}
