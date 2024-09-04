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

use Unzerpayment\Classes\UnzerpaymentClient;

if (!defined('_PS_VERSION_')) {
    exit;
}

trait UnzerpaymentFrontendHooksTrait
{
    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
    }

    /**
     * @param $params
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        if (!isset($this->context->cookie->unz_tmx_id)) {
            $this->context->cookie->unz_tmx_id = 'UnzerPaymentPS_' . substr(md5(uniqid(rand(), true)), 0, 25) . '_' .substr(md5(__PS_BASE_URI__), 0, 25);
        }
        if (isset($this->context->controller->php_self) && $this->context->controller->php_self == 'order') {
            $this->context->controller->registerJavascript(
                'unzerpayment_static.js',
                'https://static.unzer.com/v1/checkout.js',
                ['position' => 'bottom', 'priority' => 120, 'server' => 'remote']
            );
            $this->context->controller->registerJavascript(
                'unzerpayment_tmx.js',
                'https://h.online-metrix.net/fp/tags.js?org_id=363t8kgq&session_id=' . $this->context->cookie->unz_tmx_id,
                ['position' => 'bottom', 'priority' => 122, 'server' => 'remote']
            );
            $this->context->controller->registerStylesheet(
                'unzerpayment_static.css',
                'https://static.unzer.com/v1/unzer.css'
            );
        }
        $this->context->controller->registerJavascript(
            'unzerpayment.js',
            'modules/'.$this->name.'/views/js/unzerpayment.js',
            ['position' => 'bottom', 'priority' => 100, 'server' => 'local']
        );
        $this->context->controller->registerStylesheet(
            'unzerpayment.css',
            'modules/'.$this->name.'/views/css/unzerpayment.css'
        );
        \Media::addJsDef(
            [
                'unzerAjaxUrl' => Context::getContext()->link->getModuleLink(
                    $this->name,
                    'ajax'
                ),
                'unzerSuccessUrl' => \UnzerPayment\Classes\UnzerpaymentHelper::getSuccessUrl(),
                'unzerErrorUrl' => \UnzerPayment\Classes\UnzerpaymentHelper::getFailureUrl(),
                'unzer_transaction_canceled_by_user' => $this->l('Transaction canceled by user.'),
                'unzer_paypage_generic_error' => $this->l('There has been an error, please try another payment method.'),
            ]
        );
    }

    /**
     * Return payment options available for PS 1.7+
     *
     * @param array Hook parameters
     *
     * @return array|null
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->isReadyForFrontend()) {
            return;
        }
        if (!$this->active) {
            return;
        }

        $options = [];

        foreach (UnzerpaymentClient::getAvailablePaymentMethods() as $paymentMethod) {
            $paymentType = $paymentMethod->type;
            $currencyOK = false;
            if (isset($paymentMethod->supports[0]->currency)) {
                foreach ($paymentMethod->supports[0]->currency as $currency_code) {
                    if ($currency_code == Context::getContext()->currency->iso_code) {
                        $currencyOK = true;
                    }
                }
            }
            if ($currencyOK && \Configuration::get('UNZERPAYMENT_PAYMENTYPE_STATUS_' . $paymentType)) {
                $this->context->smarty->assign('currentPaymentType', $paymentType);
                $option = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
                $option->setModuleName($this->name)
                    ->setCallToActionText(\UnzerPayment\Classes\UnzerpaymentHelper::getMappedPaymentName($paymentType))
                    ->setLogo(
                        Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/icons/' . strtolower($paymentType) . '.png')
                    )
                    ->setForm(
                        $this->context->smarty->fetch('module:unzerpayment/views/templates/front/paymentOption.tpl')
                    );
                $options[] = $option;
            }
        }

        return $options;
    }

    /**
     * Add bank transfer data to invoice PDF
     *
     * @param array $params
     */
    public function hookDisplayPDFInvoice($params)
    {
        $order = new Order((int)$params['object']->id_order);
        if ($order->module != $this->name) {
            return;
        }
        if ($transaction_id = \UnzerPayment\Classes\UnzerpaymentHelper::getTransactionIdByOrder($order)) {
            $unzer = \Unzerpayment\Classes\UnzerpaymentClient::getInstance();
            try {
                $payment = $unzer->fetchPayment($transaction_id);
                $paymentId = (\UnzerSDK\Services\IdService::getResourceTypeFromIdString($payment->getPaymentType()->getId()));
                if ($paymentId == 'ppy' || $paymentId == 'piv' || $paymentId == 'ivc') {
                    $this->context->smarty->assign(
                        [
                            'unzer_amount' => $payment->getInitialTransaction()->getAmount(),
                            'unzer_currency' => $payment->getInitialTransaction()->getCurrency(),
                            'unzer_account_holder' => $payment->getInitialTransaction()->getHolder(),
                            'unzer_account_iban' => $payment->getInitialTransaction()->getIban(),
                            'unzer_account_bic' => $payment->getInitialTransaction()->getBic(),
                            'unzer_account_descriptor' => $payment->getInitialTransaction()->getDescriptor(),
                        ]
                    );
                    return $this->fetch('module:unzerpayment/views/templates/hook/displayPDFInvoice.tpl');
                }
            } catch (\Exception $e) {
            }
        }
    }


}
