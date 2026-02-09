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

namespace UnzerPayment\Classes\Helper;
use Unzerpayment\Classes\UnzerpaymentClient;
use UnzerPayment\Classes\UnzerpaymentHelper;

if (!defined('_PS_VERSION_')) {
    exit;
}

class UnzerpaymentAdminConfigFormHelper
{

    const TRANSLATIONKEY = 'unzerpaymentadminconfigformhelper';

    /**
     * @return array
     * @throws \PrestaShopException
     */
    public static function renderForm($module)
    {
        $helper = new UnzerpaymentFormHelper();

        $helper->show_toolbar = false;
        $helper->table = $module->getVar('table');
        $helper->module = $module;
        $helper->default_form_language = $module->getVar('context')->language->id;
        $helper->allow_employee_form_lang = \Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $module->getVar('identifier');
        $helper->submit_action = 'submitUnzerpaymentModule';
        $helper->currentIndex = $module->getVar('context')->link->getAdminLink('AdminModules', false)
            . '&configure=' . $module->getVar('name') . '&tab_module=' . $module->getVar('tab') . '&module_name=' . $module->getVar('name');
        $helper->token = \Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => self::getConfigFormValues($module),
            'languages' => $module->getVar('context')->controller->getLanguages(),
            'id_language' => $module->getVar('context')->language->id,
        );

        $forms = [];
        foreach (self::getConfigForms($module) as $formKey => $formVars) {
            $forms[$formKey] = $helper->generateUnzerForm($module->getVar('context')->smarty, array($formVars));
        }
        return $forms;
    }

    /**
     * @param \Unzerpayment $module
     * @return array
     */
    public static function getConfigFormValues(\Unzerpayment $module)
    {
        $configFormValuesReturn = array(
            'UNZERPAYMENT_PUBLIC_KEY' => \Configuration::get('UNZERPAYMENT_PUBLIC_KEY'),
            'UNZERPAYMENT_PRIVATE_KEY' => \Configuration::get('UNZERPAYMENT_PRIVATE_KEY'),
            'UNZERPAYMENT_TESTMODE' => \Configuration::get('UNZERPAYMENT_TESTMODE'),
            'UNZERPAYMENT_LOGLEVEL' => \Configuration::get('UNZERPAYMENT_LOGLEVEL'),
            'UNZERPAYMENT_STATUS_CHARGE' => \Configuration::get('UNZERPAYMENT_STATUS_CHARGE'),
            'UNZERPAYMENT_STATUS_CAPTURED' => \Configuration::get('UNZERPAYMENT_STATUS_CAPTURED'),
            'UNZERPAYMENT_STATUS_PLACED' => \Configuration::get('UNZERPAYMENT_STATUS_PLACED'),
            'UNZERPAYMENT_STATUS_CANCELLED' => \Configuration::get('UNZERPAYMENT_STATUS_CANCELLED'),
            'UNZERPAYMENT_STATUS_CHARGEBACK' => \Configuration::get('UNZERPAYMENT_STATUS_CHARGEBACK'),
            'UNZERPAYMENT_STATUS_FULL_REFUND' => \Configuration::get('UNZERPAYMENT_STATUS_FULL_REFUND'),
            'UNZERPAYMENT_DESIGN_HEADER_COLOR' => \Configuration::get('UNZERPAYMENT_DESIGN_HEADER_COLOR'),
            'UNZERPAYMENT_DESIGN_SHOPNAME_COLOR' => \Configuration::get('UNZERPAYMENT_DESIGN_SHOPNAME_COLOR'),
            'UNZERPAYMENT_DESIGN_TAGLINE_COLOR' => \Configuration::get('UNZERPAYMENT_DESIGN_TAGLINE_COLOR'),
            'UNZERPAYMENT_DESIGN_HEADER_FONTSIZE' => \Configuration::get('UNZERPAYMENT_DESIGN_HEADER_FONTSIZE'),
            'UNZERPAYMENT_DESIGN_SHOPNAME_FONTSIZE' => \Configuration::get('UNZERPAYMENT_DESIGN_SHOPNAME_FONTSIZE'),
            'UNZERPAYMENT_DESIGN_TAGLINE_FONTSIZE' => \Configuration::get('UNZERPAYMENT_DESIGN_TAGLINE_FONTSIZE'),
            'UNZERPAYMENT_DESIGN_HEADER_BACKGROUNDCOLOR' => \Configuration::get('UNZERPAYMENT_DESIGN_HEADER_BACKGROUNDCOLOR'),
            'UNZERPAYMENT_DESIGN_SHOPNAME_BACKGROUNDCOLOR' => \Configuration::get('UNZERPAYMENT_DESIGN_SHOPNAME_BACKGROUNDCOLOR'),
            'UNZERPAYMENT_DESIGN_TAGLINE_BACKGROUNDCOLOR' => \Configuration::get('UNZERPAYMENT_DESIGN_TAGLINE_BACKGROUNDCOLOR'),
        );

        foreach (self::getPaymentMethodsConfigElements($module) as $paymentMethodsConfigElement) {
            $paymentType = $paymentMethodsConfigElement->type;
            if (\Configuration::get('UNZERPAYMENT_PAYMENTYPE_STATUS_' . $paymentType) === false) {
                \Configuration::updateValue('UNZERPAYMENT_PAYMENTYPE_STATUS_' . $paymentType, 1);
            }
            $configFormValuesReturn['UNZERPAYMENT_PAYMENTYPE_STATUS_' . $paymentType] = \Configuration::get('UNZERPAYMENT_PAYMENTYPE_STATUS_' . $paymentType);
            $configFormValuesReturn['UNZERPAYMENT_PAYMENTYPE_CHARGE_MODE_' . $paymentType] = \Configuration::get('UNZERPAYMENT_PAYMENTYPE_CHARGE_MODE_' . $paymentType);
        }

        return $configFormValuesReturn;
    }

    /**
     * @param \Unzerpayment $module
     * @return \array[][]
     */
    public static function getConfigForms(\Unzerpayment $module)
    {
        $auth = array(
            'form' => array(
                'form' => array(
                    'id_form' => 'unzerpayment_auth_form'
                ),
                'legend' => array(
                    'title' => $module->l('Configuration', self::TRANSLATIONKEY),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'name' => 'UNZERPAYMENT_PUBLIC_KEY',
                        'label' => $module->l('Public Key', self::TRANSLATIONKEY),
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'UNZERPAYMENT_PRIVATE_KEY',
                        'label' => $module->l('Private Key', self::TRANSLATIONKEY),
                    ),
                ),
                'submit' => array(
                    'title' => $module->l('Save', self::TRANSLATIONKEY),
                ),
            ),
        );

        $advanced_inputs = [];
        $advanced_inputs[] =
            array(
                'type' => 'select',
                'label' => $module->l('Mode', self::TRANSLATIONKEY),
                'name' => 'UNZERPAYMENT_TESTMODE',
                'options' => array(
                    'query' => array(
                        array('key' => '1', 'name' => $module->l('Test mode (no charges)', self::TRANSLATIONKEY)),
                        array('key' => '0', 'name' => $module->l('Live mode', self::TRANSLATIONKEY)),
                    ),
                    'id' => 'key',
                    'name' => 'name'
                ),
            );
        /*
        $advanced_inputs[] =
            array(
                'type' => 'select',
                'label' => $module->l('Charge Mode', self::TRANSLATIONKEY),
                'name' => 'UNZERPAYMENT_CHARGEMODE',
                'options' => array(
                    'query' => array(
                        array('key' => 'charge', 'name' => $module->l('Charge', self::TRANSLATIONKEY)),
                        array('key' => 'authorize', 'name' => $module->l('Authorize', self::TRANSLATIONKEY)),
                    ),
                    'id' => 'key',
                    'name' => 'name'
                ),
            );
        */
        $advanced_inputs[] = [
            'name' => 'unzer.status.setting.header',
            'label' => '',
            'type' => 'html',
            'html_content' => self::parseHtmlHeader($module->l('Status Settings', self::TRANSLATIONKEY)),
        ];
        $advanced_inputs[] =
            [
                'col' => 3,
                'type' => 'select',
                'desc' => $module->l('This status is set for placed and authorized orders', self::TRANSLATIONKEY),
                'prefix' => '<i class="icon icon-tag"></i>',
                'name' => 'UNZERPAYMENT_STATUS_PLACED',
                'label' => $module->l('Order status for placed orders', self::TRANSLATIONKEY),
                'options' => [
                    'query' => array_merge(
                        [
                            [
                                'id_order_state' => 0,
                                'id_lang' => (int) \Configuration::get('PS_LANG_DEFAULT'),
                                'name' => '',
                            ],
                        ],
                        \OrderState::getOrderStates((int) \Configuration::get('PS_LANG_DEFAULT'))
                    ),
                    'id' => 'id_order_state',
                    'name' => 'name',
                ],
            ];
        $advanced_inputs[] =
            [
                'col' => 3,
                'type' => 'select',
                'prefix' => '<i class="icon icon-tag"></i>',
                'name' => 'UNZERPAYMENT_STATUS_CAPTURED',
                'label' => $module->l('Order status for captured orders', self::TRANSLATIONKEY),
                'options' => [
                    'query' => array_merge(
                        [
                            [
                                'id_order_state' => 0,
                                'id_lang' => (int) \Configuration::get('PS_LANG_DEFAULT'),
                                'name' => '',
                            ],
                        ],
                        \OrderState::getOrderStates((int) \Configuration::get('PS_LANG_DEFAULT'))
                    ),
                    'id' => 'id_order_state',
                    'name' => 'name',
                ],
            ];
        $advanced_inputs[] =
            [
                'col' => 3,
                'type' => 'select',
                'desc' => $module->l('Switching to this status triggers automatic cancellation of the payment at Unzer', self::TRANSLATIONKEY),
                'prefix' => '<i class="icon icon-tag"></i>',
                'name' => 'UNZERPAYMENT_STATUS_CANCELLED',
                'label' => $module->l('Order status for cancelled orders', self::TRANSLATIONKEY),
                'options' => [
                    'query' => array_merge(
                        [
                            [
                                'id_order_state' => 0,
                                'id_lang' => (int) \Configuration::get('PS_LANG_DEFAULT'),
                                'name' => '',
                            ],
                        ],
                        \OrderState::getOrderStates((int) \Configuration::get('PS_LANG_DEFAULT'))
                    ),
                    'id' => 'id_order_state',
                    'name' => 'name',
                ],
            ];
        $advanced_inputs[] =
            [
                'col' => 3,
                'type' => 'select',
                'prefix' => '<i class="icon icon-tag"></i>',
                'name' => 'UNZERPAYMENT_STATUS_CHARGEBACK',
                'label' => $module->l('Order status for chargeback', self::TRANSLATIONKEY),
                'options' => [
                    'query' => array_merge(
                        [
                            [
                                'id_order_state' => 0,
                                'id_lang' => (int) \Configuration::get('PS_LANG_DEFAULT'),
                                'name' => '',
                            ],
                        ],
                        \OrderState::getOrderStates((int) \Configuration::get('PS_LANG_DEFAULT'))
                    ),
                    'id' => 'id_order_state',
                    'name' => 'name',
                ],
            ];
        $advanced_inputs[] =
            [
                'col' => 3,
                'type' => 'select',
                'prefix' => '<i class="icon icon-tag"></i>',
                'name' => 'UNZERPAYMENT_STATUS_CHARGE',
                'label' => $module->l('Order status to automatically charge', self::TRANSLATIONKEY),
                'options' => [
                    'query' => array_merge(
                        [
                            [
                                'id_order_state' => 0,
                                'id_lang' => (int) \Configuration::get('PS_LANG_DEFAULT'),
                                'name' => '',
                            ],
                        ],
                        \OrderState::getOrderStates((int) \Configuration::get('PS_LANG_DEFAULT'))
                    ),
                    'id' => 'id_order_state',
                    'name' => 'name',
                ],
            ];
        $advanced_inputs[] =
            [
                'col' => 3,
                'type' => 'select',
                'prefix' => '<i class="icon icon-tag"></i>',
                'name' => 'UNZERPAYMENT_STATUS_FULL_REFUND',
                'label' => $module->l('Order status to automatically refund the full amount', self::TRANSLATIONKEY),
                'options' => [
                    'query' => array_merge(
                        [
                            [
                                'id_order_state' => 0,
                                'id_lang' => (int) \Configuration::get('PS_LANG_DEFAULT'),
                                'name' => '',
                            ],
                        ],
                        \OrderState::getOrderStates((int) \Configuration::get('PS_LANG_DEFAULT'))
                    ),
                    'id' => 'id_order_state',
                    'name' => 'name',
                ],
            ];

        $advanced_inputs[] = [
            'name' => 'unzer.payment.setting.header',
            'label' => '',
            'type' => 'html',
            'html_content' => self::parseHtmlHeader($module->l('Payment Methods Settings', self::TRANSLATIONKEY)),
        ];

        foreach (self::getPaymentMethodsConfigElements($module) as $paymentMethodsConfigElement) {
            $paymentType = $paymentMethodsConfigElement->type;
            $advanced_inputs[] =
                array(
                    'type' => 'switch',
                    'label' => $module->l($paymentType),
                    'name' => 'UNZERPAYMENT_PAYMENTYPE_STATUS_' . $paymentType,
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_' . $paymentType,
                            'value' => true,
                            'label' => $module->l('Active', self::TRANSLATIONKEY)
                        ),
                        array(
                            'id' => 'inactive_' . $paymentType,
                            'value' => '0',
                            'label' => $module->l('Inactive', self::TRANSLATIONKEY)
                        )
                    )
                );
            $paymentRessourceClassName = UnzerpaymentHelper::dashesToCamelCase(
                $paymentType,
                true
            );
            if (UnzerpaymentHelper::paymentMethodCanAuthorize($paymentRessourceClassName) && $paymentType != 'clicktopay' && $paymentType != 'wero') {
                $advanced_inputs[] =
                    array(
                        'type' => 'select',
                        'label' => $module->l('Charge mode ', self::TRANSLATIONKEY) . $module->l($paymentType),
                        'name' => 'UNZERPAYMENT_PAYMENTYPE_CHARGE_MODE_' . $paymentType,
                        'options' => array(
                            'query' => array(
                                array('key' => 'charge', 'name' => $module->l('Charge', self::TRANSLATIONKEY)),
                                array('key' => 'authorize', 'name' => $module->l('Authorize', self::TRANSLATIONKEY)),
                            ),
                            'id' => 'key',
                            'name' => 'name'
                        ),
                    );
            }
        }

        $advanced_inputs[] = [
            'name' => 'unzer.status.setting.header',
            'label' => '',
            'type' => 'html',
            'html_content' => self::parseHtmlHeader($module->l('Logging', self::TRANSLATIONKEY)),
        ];

        $advanced_inputs[] =
            array(
                'type' => 'select',
                'label' => $module->l('Loglevel', self::TRANSLATIONKEY),
                'name' => 'UNZERPAYMENT_LOGLEVEL',
                'options' => array(
                    'query' => array(
                        array('key' => '0', 'name' => 'Disable logging completely'),
                        array('key' => '1', 'name' => 'Only errors'),
                        #array('key' => '2', 'name' => 'Log errors and informations'),
                        array('key' => '3', 'name' => 'Debug mode')
                    ),
                    'id' => 'key',
                    'name' => 'name'
                ),
                'desc' => $module->l('Set different log levels. Debug mode will log the most information.', self::TRANSLATIONKEY)
            );

        $advanced = array(
            'form' => array(
                'form' => array(
                    'id_form' => 'unzerpayment_advanced_form'
                ),
                'legend' => array(
                    'title' => $module->l('Advanced Configuration', self::TRANSLATIONKEY),
                    'icon' => 'icon-cogs',
                ),
                'input' => $advanced_inputs,
                'submit' => array(
                    'title' => $module->l('Save', self::TRANSLATIONKEY),
                ),
            ),
        );

        $design = array(
            'form' => array(
                'form' => array(
                    'id_form' => 'unzerpayment_design_form'
                ),
                'legend' => array(
                    'title' => $module->l('Design', self::TRANSLATIONKEY),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'color',
                        'label' => $module->l('Header Color', self::TRANSLATIONKEY),
                        'name' => 'UNZERPAYMENT_DESIGN_HEADER_COLOR',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $module->l('Header Font-Size', self::TRANSLATIONKEY),
                        'name' => 'UNZERPAYMENT_DESIGN_HEADER_FONTSIZE',
                        'options' => array(
                            'query' => self::getFontSizesArray(),
                            'id' => 'key',
                            'name' => 'name'
                        ),
                    ),
                    array(
                        'type' => 'color',
                        'label' => $module->l('Header Background-Color', self::TRANSLATIONKEY),
                        'name' => 'UNZERPAYMENT_DESIGN_HEADER_BACKGROUNDCOLOR',
                    ),
                    array(
                        'type' => 'color',
                        'label' => $module->l('Shopname Color', self::TRANSLATIONKEY),
                        'name' => 'UNZERPAYMENT_DESIGN_SHOPNAME_COLOR',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $module->l('Shopname Font-Size', self::TRANSLATIONKEY),
                        'name' => 'UNZERPAYMENT_DESIGN_SHOPNAME_FONTSIZE',
                        'options' => array(
                            'query' => self::getFontSizesArray(),
                            'id' => 'key',
                            'name' => 'name'
                        ),
                    ),
                    array(
                        'type' => 'color',
                        'label' => $module->l('Shopname Background-Color', self::TRANSLATIONKEY),
                        'name' => 'UNZERPAYMENT_DESIGN_SHOPNAME_BACKGROUNDCOLOR',
                    ),
                    array(
                        'type' => 'color',
                        'label' => $module->l('Tagline Color', self::TRANSLATIONKEY),
                        'name' => 'UNZERPAYMENT_DESIGN_TAGLINE_COLOR',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $module->l('Tagline Font-Size', self::TRANSLATIONKEY),
                        'name' => 'UNZERPAYMENT_DESIGN_TAGLINE_FONTSIZE',
                        'options' => array(
                            'query' => self::getFontSizesArray(),
                            'id' => 'key',
                            'name' => 'name'
                        ),
                    ),
                    array(
                        'type' => 'color',
                        'label' => $module->l('Tagline Background-Color', self::TRANSLATIONKEY),
                        'name' => 'UNZERPAYMENT_DESIGN_TAGLINE_BACKGROUNDCOLOR',
                    ),
                ),
                'submit' => array(
                    'title' => $module->l('Save', self::TRANSLATIONKEY),
                ),
            ),
        );

        return array(
            'auth' => $auth,
            'advanced' => $advanced,
            'design' => $design,
        );
    }

    public static function parseHtmlHeader($str)
    {
        return '<h4 class="unzer_config_head">' . $str . '</h4>';
    }

    /**
     * @param \Unzerpayment $module
     * @return array
     */
    protected static function getPaymentMethodsConfigElements(\Unzerpayment $module)
    {
        try {
            return UnzerpaymentClient::getAvailablePaymentMethods();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @return \string[][]
     */
    public static function getFontSizesArray()
    {
        $r = [
            ['key' => '', 'name' => '']
        ];
        for ($x=10;$x<50;$x++) {
            $r[] = ['key' => $x . 'px', 'name' => $x . 'px'];
        }
        return $r;
    }

}
