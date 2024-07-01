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

use UnzerPayment\Classes\UnzerpaymentLogger;
use Unzerpayment\Classes\UnzerpaymentHelper;

if (!defined('_PS_VERSION_')) {
    exit;
}

class UnzerpaymentSuccessModuleFrontController extends ModuleFrontController
{
    use UnzerPaymentRedirectionTrait;

    public function postProcess()
    {
        if (!isset(Context::getContext()->cookie->UnzerPaymentId)) {
            UnzerpaymentLogger::getInstance()->addLog('Call with missing UnzerPaymentId', 2, false);
            $this->errorRedirect();
        }

        // check for already existing order, especially for chrome where duplicate calls might happen
        if ((int)Tools::getValue('cuid') != Context::getContext()->customer->id) {
            UnzerpaymentLogger::getInstance()->addLog('Customer ID missing or not valid', 2, false);
            $this->errorRedirect('');
        }
        if (UnzerpaymentHelper::usersCartAndOrderExists((int)Tools::getValue('cuid'), (int)Tools::getValue('caid'))) {
            $order = Order::getByCartId((int)Tools::getValue('caid'));
            $confirmationURL = 'index.php?controller=order-confirmation&id_cart=' .
                (int)Tools::getValue('caid') .
                '&id_module=' . (int)$this->module->id .
                '&id_order=' . (int)$order->id .
                '&key=' . $this->context->customer->secure_key;

            Tools::redirect($confirmationURL);
        }

        $unzer = \Unzerpayment\Classes\UnzerpaymentClient::getInstance();
        $payment = $unzer->fetchPayment(Context::getContext()->cookie->UnzerPaymentId);

        UnzerpaymentLogger::getInstance()->addLog('Fetched payment', 3, false, [$payment]);

        if (!UnzerpaymentHelper::isValidState($payment->getState())) {
            UnzerpaymentLogger::getInstance()->addLog('Invalid payment state', 2, false, [$payment]);
            $this->errorRedirect();
        }

        if ($payment->getState() == \UnzerSDK\Constants\PaymentState::STATE_COMPLETED) {
            $orderStatus = Configuration::get('UNZERPAYMENT_STATUS_CAPTURED');
        } else {
            $orderStatus = Configuration::get('UNZERPAYMENT_STATUS_PLACED');
        }

        $amount = Tools::ps_round(Context::getContext()->cart->getOrderTotal(true, Cart::BOTH), 2);

        $unzerPaymentInstance = \UnzerSDK\Services\ResourceService::getTypeInstanceFromIdString(
            $payment->getPaymentType()->getId()
        );
        $paymentMethodName = UnzerpaymentHelper::getPaymentClassNameByFullName(
            $unzerPaymentInstance
        );

        try {
            UnzerpaymentLogger::getInstance()->addLog('Validating order', 3, false, ['transaction_id' => Context::getContext()->cookie->UnzerPaymentId]);
            $this->module->validateOrder(
                $this->context->cart->id,
                $orderStatus,
                $amount,
                $paymentMethodName . ' (' . $this->module->displayName . ')',
                '',
                [
                    'transaction_id' => Context::getContext()->cookie->UnzerPaymentId,
                ],
                $this->context->currency->id,
                false,
                $this->context->customer->secure_key
            );
        } catch (Exception $e) {
            UnzerpaymentLogger::getInstance()->addLog('Order could not be validated', 1, $e);
            $this->errorRedirect();
        }

        $metadata = $unzer->fetchMetadata(
            $payment->getMetadata()->getId()
        );
        $metadata->addMetadata(
            'shopOrderId', $this->module->currentOrder
        );
        $order = new \Order((int)$this->module->currentOrder);
        $metadata->addMetadata(
            'shopOrderReference', $order->reference
        );
        UnzerpaymentLogger::getInstance()->addLog('Trying to set metadata', 3, false, [$metadata]);
        try {
            $unzer->getResourceService()->updateResource(
                $metadata
            );
        } catch (\Exception $e) {
            UnzerpaymentLogger::getInstance()->addLog('Could not update metadata', 1, $e, [$metadata]);
        }

        unset(Context::getContext()->cookie->UnzerPaymentId);

        $confirmationURL = 'index.php?controller=order-confirmation&id_cart=' .
            (int)$this->context->cart->id .
            '&id_module=' . (int)$this->module->id .
            '&id_order=' . $this->module->currentOrder .
            '&key=' . $this->context->customer->secure_key;

        Tools::redirect($confirmationURL);

    }

    protected function errorRedirect($msg = 'There has been an error processing your order.', $pagecontroller = 'cart')
    {
        if ($msg != '') {
            $this->errors[] = $this->module->l($msg);
        }
        $this->PrestaShopRedirectWithNotifications(
            $this->context->link->getPageLink($pagecontroller)
        );
    }

}
