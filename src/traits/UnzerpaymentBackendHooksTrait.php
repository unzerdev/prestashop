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

use UnzerPayment\Classes\UnzerpaymentHelper;
use UnzerPayment\Classes\UnzerpaymentLogger;
use UnzerSDK\Resources\TransactionTypes\Charge;

if (!defined('_PS_VERSION_')) {
    exit;
}

trait UnzerpaymentBackendHooksTrait
{

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            if (_PS_VERSION_ < 9) {
                $this->context->controller->addJquery();
            }
            $this->context->controller->addJS($this->_path.'views/js/admin_1.0.0.js');
            $this->context->controller->addCSS($this->_path.'views/css/admin_1.0.0.css');
        } elseif (Tools::getValue('controller') == 'AdminOrders') {
            $this->context->controller->addCSS($this->_path.'views/css/admin_orders.css');
        }
    }

    /**
     * Add CSS & JavaScript in modern PrestaShop Versions correctly in BO.
     */
    public function hookActionAdminControllerSetMedia()
    {
        if (Tools::getValue('configure') == $this->name) {
            if (_PS_VERSION_ < 9) {
                $this->context->controller->addJquery();
            }
            $this->context->controller->addJS($this->_path.'views/js/admin_1.0.0.js');
            $this->context->controller->addCSS($this->_path.'views/css/admin_1.0.0.css');
        }
    }

    /**
     * New hook in PrestaShop 1.7.7.0 - replaces the DisplayAdminOrderContentOrder hook
     *
     * @param $params
     */
    public function hookDisplayAdminOrderTabContent($params)
    {
        if (!isset($params['order'])) {
            if (isset($params['id_order'])) {
                $params['order'] = new Order((int)$params['id_order']);
            }
        }
        return $this->hookDisplayAdminOrderContentOrder($params);
    }

    /**
     * Workarround... is used for handling of Unzer actions, then redirection to avoid doubled form submission
     *
     * @param $params
     */
    public function hookDisplayAdminOrderContentOrder($params)
    {
        if (Tools::getValue('unzer_action') != '') {
            $redirect = false;
            $order = new Order($params['id_order']);
            if ($transaction_id = \UnzerPayment\Classes\UnzerpaymentHelper::getTransactionIdByOrder($order)) {
                $unzer = \Unzerpayment\Classes\UnzerpaymentClient::getInstance();
                switch (Tools::getValue('unzer_action')) {
                    case 'unzer_capture':
                        $amount  = Tools::getValue('unzer_capture_amount') ? (float)Tools::getValue('unzer_capture_amount') : null;
                        $unzer->performChargeOnAuthorization(
                            $transaction_id,
                            $amount,
                            $order->reference
                        );
                        $redirect = true;
                        break;
                }
            }
            if ($redirect) {
                Tools::redirectAdmin(
                    Context::getContext()->link->getAdminLink(
                        'AdminOrders',
                        true,
                        array(),
                        array(
                            'vieworder' => '',
                            'id_order' => $params['order']->id
                        )
                    ) . '#unzer_transactions_block'
                );
            }
        }
    }

    /**
     * Workarround PS 1.7.7.0
     *
     * @param $params
     * @return mixed
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookDisplayAdminOrder($params)
    {
        if (!self::isPrestaShop177OrHigherStatic()) {
            return $this->hookAdminOrder($params);
        }
    }

    /**
     * @return mixed
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookDisplayAdminOrderMainBottom($params)
    {
        if (self::isPrestaShop177OrHigherStatic()) {
            return $this->hookAdminOrder($params);
        }
    }

    /**
     * @param $params
     * @return void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionOrderStatusUpdate($params)
    {
        if ($params['newOrderStatus']->id == (int)\Configuration::get('UNZERPAYMENT_STATUS_CANCELLED')) {
            $order = new Order((int)$params['id_order']);
            if ($transaction_id = \UnzerPayment\Classes\UnzerpaymentHelper::getTransactionIdByOrder($order)) {
                $unzer = \Unzerpayment\Classes\UnzerpaymentClient::getInstance();
                try {
                    UnzerpaymentLogger::getInstance()->addLog('cancelPayment Call', 2, false, [
                        'paymentId' => $transaction_id
                    ]);
                    $payment = $unzer->fetchPayment($transaction_id);
                    $paymentId = (\UnzerSDK\Services\IdService::getResourceTypeFromIdString($payment->getPaymentType()->getId()));
                    if ($paymentId == 'pdd' || $paymentId == 'piv' || $paymentId == 'pit') {
                        if ($payment->getAmount()->getCharged() > 0) {
                            $unzer->cancelChargedPayment(
                                $transaction_id
                            );
                        } else {
                            $unzer->cancelAuthorizedPayment(
                                $transaction_id
                            );
                        }
                    } else {
                        $unzer->cancelPayment(
                            $transaction_id
                        );
                    }
                } catch (\Exception $e) {
                    UnzerpaymentLogger::getInstance()->addLog('cancelPayment Error', 1, $e, [
                        'paymentId' => $transaction_id
                    ]);
                }
            }
        } elseif ($params['newOrderStatus']->id == (int)\Configuration::get('UNZERPAYMENT_STATUS_FULL_REFUND')) {
            $order = new Order((int)$params['id_order']);
            if ($transaction_id = \UnzerPayment\Classes\UnzerpaymentHelper::getTransactionIdByOrder($order)) {
                $unzer = \Unzerpayment\Classes\UnzerpaymentClient::getInstance();
                try {
                    UnzerpaymentLogger::getInstance()->addLog('cancelPayment Call for full Refund', 2, false, [
                        'paymentId' => $transaction_id
                    ]);
                    $payment = $unzer->fetchPayment($transaction_id);
                    $paymentId = (\UnzerSDK\Services\IdService::getResourceTypeFromIdString($payment->getPaymentType()->getId()));
                    if ($paymentId == 'pdd' || $paymentId == 'piv' || $paymentId == 'pit') {
                        if ($payment->getAmount()->getCharged() > 0) {
                            $unzer->cancelChargedPayment(
                                $transaction_id
                            );
                        } else {
                            $unzer->cancelAuthorizedPayment(
                                $transaction_id
                            );
                        }
                    } else {
                        $unzer->cancelPayment(
                            $transaction_id
                        );
                    }
                } catch (\Exception $e) {
                    UnzerpaymentLogger::getInstance()->addLog('cancelPayment Error', 1, $e, [
                        'paymentId' => $transaction_id
                    ]);
                }
            }
        } elseif ($params['newOrderStatus']->id == (int)\Configuration::get('UNZERPAYMENT_STATUS_CHARGE')) {
            if (!UnzerpaymentHelper::isSandboxMode()) {
                $order = new Order((int)$params['id_order']);
                if ($transaction_id = \UnzerPayment\Classes\UnzerpaymentHelper::getTransactionIdByOrder($order)) {
                    $unzer = \Unzerpayment\Classes\UnzerpaymentClient::getInstance();
                    try {
                        $payment = $unzer->fetchPayment($transaction_id);
                        if ($payment->getAmount()->getRemaining() > 0) {
                            $paymentId = (\UnzerSDK\Services\IdService::getResourceTypeFromIdString($payment->getPaymentType()->getId()));
                            if ($paymentId != 'ppy') {
                                UnzerpaymentLogger::getInstance()->addLog('performChargeOnAuthorization Call', 2, false, [
                                    'paymentId' => $transaction_id,
                                    'amount' =>$payment->getAmount()->getRemaining()
                                ]);
                                $unzer->performChargeOnAuthorization(
                                    $transaction_id,
                                    $payment->getAmount()->getRemaining(),
                                    $order->reference
                                );
                            }
                        }
                    } catch (\Exception $e) {
                        UnzerpaymentLogger::getInstance()->addLog('performChargeOnAuthorization Error', 1, $e, [
                            'paymentId' => $transaction_id,
                            'amount' =>$payment->getAmount()->getRemaining()
                        ]);
                    }
                }
            }
        }
    }

    /**
     * @param $params
     * @return void
     */
    public function hookActionOrderSlipAdd($params)
    {
        /** @var Order $order */
        $order = $params['order'];
        if ($order->module == $this->name) {
            if ($transaction_id = \UnzerPayment\Classes\UnzerpaymentHelper::getTransactionIdByOrder($order)) {
                /** @var OrderSlip[] $orderSlips */
                $orderSlips = $order->getOrderSlipsCollection()->getResults();
                $refundAmount = 0;
                /* we always use the last orderSlip in the array to fetch the latest one, as PrestaShop does not give that information in the hook params */
                foreach ($orderSlips as $orderSlip) {
                    $refundAmount = $orderSlip->amount + $orderSlip->shipping_cost_amount;
                }
                if ($refundAmount > 0) {
                    $unzer = \Unzerpayment\Classes\UnzerpaymentClient::getInstance();
                    try {
                        UnzerpaymentLogger::getInstance()->addLog('cancelPayment Call', 2, false, [
                            'paymentId' => $transaction_id,
                            'amount' =>$refundAmount
                        ]);
                        $payment = $unzer->fetchPayment($transaction_id);
                        $paymentId = (\UnzerSDK\Services\IdService::getResourceTypeFromIdString($payment->getPaymentType()->getId()));
                        if ($paymentId == 'pdd' || $paymentId == 'piv' || $paymentId == 'pit') {
                            if ($payment->getAmount()->getCharged() > 0) {
                                $unzer->cancelChargedPayment(
                                    $transaction_id,
                                    new \UnzerSDK\Resources\TransactionTypes\Cancellation($refundAmount)
                                );
                            } else {
                                $unzer->cancelAuthorizedPayment(
                                    $transaction_id,
                                    new \UnzerSDK\Resources\TransactionTypes\Cancellation($refundAmount)
                                );
                            }
                        } else {
                            $unzer->cancelPayment(
                                $transaction_id,
                                $refundAmount
                            );
                        }
                    } catch (\Exception $e) {
                        UnzerpaymentLogger::getInstance()->addLog('cancelPayment Error', 1, $e, [
                            'paymentId' => $transaction_id,
                            'amount' => $refundAmount
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Build Unzer Transactions overview and actions in order view
     *
     * @param $params
     * @return mixed
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookAdminOrder($params)
    {
        if ($this->isReadyForFrontend()) {
            $this->context->smarty->assign('isHigher176', UnzerPayment::isPrestaShop177OrHigherStatic());
            $order = new Order($params['id_order']);
            if ($order->module == $this->name) {
                if ($transaction_id = \UnzerPayment\Classes\UnzerpaymentHelper::getTransactionIdByOrder($order)) {
                    $transactions = \UnzerPayment\Classes\UnzerpaymentHelper::getTransactions($transaction_id, $order);
                    $this->context->smarty->assign(
                        'unzer_form_action',
                        Context::getContext()->link->getAdminLink(
                            'AdminOrders',
                            true,
                            array(),
                            array(
                                'vieworder' => '',
                                'id_order' => $params['id_order']
                            )
                        )
                    );
                    $this->context->smarty->assign('unzer_transactions', $transactions);
                    return $this->display($this->this_file, 'views/templates/admin/transactions.tpl');
                }
            }
        }
    }

}
