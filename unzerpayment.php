<?php
/**
 * 2024 Unzer GmbH
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Unzerpayment to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    patworx multimedia GmbH <service@patworx.de>
 *  @copyright 2024 Unzer GmbH / patworx multimedia GmbH
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/vendor/UnzerPaymentRedirectionTrait.php');
require_once(__DIR__ . '/src/classes/UnzerpaymentClient.php');
require_once(__DIR__ . '/src/classes/UnzerpaymentHelper.php');
require_once(__DIR__ . '/src/classes/UnzerpaymentLogger.php');
require_once(__DIR__ . '/src/classes/Helper/UnzerpaymentAdminConfigFormHelper.php');
require_once(__DIR__ . '/src/classes/Helper/UnzerpaymentFormHelper.php');
require_once(__DIR__ . '/src/traits/UnzerpaymentBackendHooksTrait.php');
require_once(__DIR__ . '/src/traits/UnzerpaymentFrontendHooksTrait.php');

class Unzerpayment extends PaymentModule
{
    use UnzerpaymentFrontendHooksTrait;
    use UnzerpaymentBackendHooksTrait;

    protected $this_file = __FILE__;
    protected $_errormessage = false;
    protected $_successmessage = false;

    public static $hooks = [
        'actionFrontControllerSetMedia',
        'actionAdminControllerSetMedia',
        'actionOrderSlipAdd',
        'displayAdminOrderContentOrder',
        'displayAdminOrderTabContent',
        'displayAdminOrderMainBottom',
        'actionOrderStatusUpdate',
        'displayAdminOrder',
        'displayPDFInvoice',
        'adminOrder',
        'header',
        'backOfficeHeader',
        'paymentOptions',
    ];

    /**
     * Unzerpayment constructor.
     */
    public function __construct()
    {
        $this->name = 'unzerpayment';
        $this->tab = 'payments_gateways';
        $this->author = 'Unzer GmbH';
        $this->version = '1.0.4';
        $this->need_instance = 1;
        $this->module_key = '';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Unzer Payments');
        $this->description = $this->l('The app enables the Unzer payment methods in the store');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }


    /**
     * @return bool
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        include(dirname(__FILE__).'/sql/install.php');

        $install = parent::install();
        $hooks = $this->registerHooks();
        $defaults = $this->setDefaults();

        return $install && $hooks && $defaults;
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        include(dirname(__FILE__).'/sql/uninstall.php');
        return parent::uninstall();
    }

    /**
     * @param $var
     * @return |null
     */
    public function getVar($var)
    {
        if (isset($this->$var)) {
            return $this->$var;
        }
        return null;
    }

    /**
     * @return bool
     */
    public function registerHooks()
    {
        $return = true;
        foreach (self::$hooks as $hook) {
            $registerHook = $this->registerHook($hook);
            if (!$registerHook) {
                $return = false;
            }
        }
        return $return;
    }

    /**
     * sets default config values
     */
    public function setDefaults()
    {
        Configuration::updateValue('UNZERPAYMENT_TESTMODE', true);
        Configuration::updateValue('UNZERPAYMENT_PRIVATE_KEY', false);
        Configuration::updateValue('UNZERPAYMENT_PUBLIC_KEY', false);
        Configuration::updateValue('UNZERPAYMENT_LOGLEVEL', 1);
        Configuration::updateValue('UNZERPAYMENT_PAYMENT_METHODS', json_encode([]));
        return true;
    }

    /**
     * @return string
     */
    public static function getVersion()
    {
        $unzerPayment = new self();
        return $unzerPayment->version;
    }


    /**
     * @return bool
     */
    protected function isReadyForFrontend()
    {
        if (Configuration::get('UNZERPAYMENT_PRIVATE_KEY') == '') {
            return false;
        }
        if (Configuration::get('UNZERPAYMENT_PUBLIC_KEY') == '') {
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    public static function isPrestaShop176Static()
    {
        return version_compare(_PS_VERSION_, '1.7.6', '>=');
    }

    /**
     * @return bool
     */
    public static function isPrestaShop177OrHigherStatic()
    {
        return version_compare(_PS_VERSION_, '1.7.7', '>=');
    }

    /**
     * @return bool
     */
    public function isPrestaShop176()
    {
        return self::isPrestaShop176Static();
    }

    /**
     * @return bool
     */
    public function isPrestaShop177OrHigher()
    {
        return self::isPrestaShop177OrHigherStatic();
    }

    /**
     * @return array
     */
    public function getMetaData()
    {
        return [
            'shopType' => 'PrestaShop',
            'shopVersion' => _PS_VERSION_,
            'pluginVersion' => $this->version,
            'pluginType' => 'unzerdev/prestashop'
        ];
    }


    /**
     * @return string
     * @throws SmartyException
     */
    public function getContent()
    {
        $unzer = \Unzerpayment\Classes\UnzerpaymentClient::getInstance();
        if (Tools::getValue('unzerAdminAction') == 'delWebhook') {
            if ($webhookId = Tools::getValue('webhookId')) {
                try {
                    \Unzerpayment\Classes\UnzerpaymentClient::getInstance()->deleteWebhook(
                        $webhookId
                    );
                    $this->_successmessage = $this->l('Webhook successfully removed');
                } catch (\Exception $e) {
                    $this->_errormessage = $this->l('Cannot delete webhook.') . ' API Info: ' . $e->getMessage();
                }
            }
        } elseif (Tools::getValue('unzerAdminAction') == 'createWebhook') {
            $this->registerWebhooks();
        }

        if (((bool)Tools::isSubmit('submitUnzerpaymentModule')) == true) {
            $this->postProcess();
            $this->registerWebhooks();
            $unzer = \Unzerpayment\Classes\UnzerpaymentClient::getInstance();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $forms = \UnzerPayment\Classes\Helper\UnzerpaymentAdminConfigFormHelper::renderForm($this);
        $this->context->smarty->assign('authform', $forms['auth']);
        $this->context->smarty->assign('advancedform', $forms['advanced']);
        $this->context->smarty->assign('designform', $forms['design']);
        $this->context->smarty->assign('webhookDelActionLink',
            Context::getContext()->link->getAdminLink(
                'AdminModules',
                true,
                array(),
                array(
                    'configure' => $this->name,
                    'tab_module' => $this->tab,
                    'module_name' => $this->name,
                    'unzerAdminAction' => 'delWebhook',
                    'webhookId' => 'UNZERWEBHOOKID'
                )
            )
        );
        if (!is_null($unzer)) {
            $this->context->smarty->assign('webhookCreateActionLink',
                Context::getContext()->link->getAdminLink(
                    'AdminModules',
                    true,
                    array(),
                    array(
                        'configure' => $this->name,
                        'tab_module' => $this->tab,
                        'module_name' => $this->name,
                        'unzerAdminAction' => 'createWebhook'
                    )
                )
            );
        }
        $this->context->smarty->assign('webhooksList', (!is_null($unzer)) ? $unzer->getWebhooksList() : false);

        $this->processErrorsAndSuccess();

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/admin.tpl');

        return $output;
    }

    protected function registerWebhooks()
    {
        try {
            if (is_null(\Unzerpayment\Classes\UnzerpaymentClient::getInstance())) {
                return [];
            }
            \Unzerpayment\Classes\UnzerpaymentClient::getInstance()->createWebhook(
                \Unzerpayment\Classes\UnzerpaymentHelper::getNotifyUrl(),
                'all'
            );
            $this->_successmessage = $this->l('Webhook successfully added');
        } catch (\Exception $e) {
            $this->_errormessage = $this->l('Cannot add webhook.') . ' API Info: ' . $e->getMessage();
        }
    }

    /**
     * @return void
     */
    protected function processErrorsAndSuccess()
    {
        if ($this->_errormessage) {
            $this->context->smarty->assign(
                'error_message',
                $this->_errormessage
            );
        }
        if ($this->_successmessage) {
            $this->context->smarty->assign(
                'success_message',
                $this->_successmessage
            );
        }
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = \UnzerPayment\Classes\Helper\UnzerpaymentAdminConfigFormHelper::getConfigFormValues($this);

        foreach (array_keys($form_values) as $key) {
            if (Tools::getValue($key) !== false) {
                Configuration::updateValue($key, trim(Tools::getValue($key)));
            }
        }
        $this->_successmessage = $this->l('Settings updated');
    }

    protected function dummyFunctionTranslation()
    {
        $this->l('wechatpay');
        $this->l('sepa-direct-debit');
        $this->l('alipay');
        $this->l('installment-secured');
        $this->l('invoice-secured');
        $this->l('googlepay');
        $this->l('post-finance-card');
        $this->l('przelewy24');
        $this->l('ideal');
        $this->l('PIS');
        $this->l('EPS');
        $this->l('klarna');
        $this->l('card');
        $this->l('giropay');
        $this->l('prepayment');
        $this->l('invoice');
        $this->l('post-finance-efinance');
        $this->l('sepa-direct-debit-secured');
        $this->l('sofort');
        $this->l('bancontact');
        $this->l('paypal');
        $this->l('applepay');
    }

}
