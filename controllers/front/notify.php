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
use UnzerSDK\Constants\WebhookEvents;
use Unzerpayment\Classes\UnzerpaymentHelper;
use Unzerpayment\Classes\UnzerpaymentClient;
use PrestaShop\PrestaShop\Adapter\Order\OrderCreator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class UnzerpaymentNotifyModuleFrontController extends ModuleFrontController
{

    use UnzerPaymentRedirectionTrait;

    const REGISTERED_EVENTS = array(
        WebhookEvents::CHARGE_CANCELED,
        WebhookEvents::AUTHORIZE_CANCELED,
        WebhookEvents::AUTHORIZE_SUCCEEDED,
        WebhookEvents::CHARGE_SUCCEEDED,
        WebhookEvents::PAYMENT_CHARGEBACK,
    );

    protected $unzer;

    public function postProcess()
    {
        $this->unzer = UnzerpaymentClient::getInstance();
        $jsonRequest = Tools::file_get_contents('php://input');
        $data = json_decode($jsonRequest, true);

        if ( empty( $data ) ) {
            header("HTTP/1.0 404 Not Found");
            UnzerpaymentLogger::getInstance()->addLog('empty webhook call', 1, false, [
                'server' => self::getServerVar()
            ]);
            exit();
        }


        if ( ! in_array( $data['event'], self::REGISTERED_EVENTS, true ) ) {
            $this->renderJson(
                array(
                    'success' => true,
                    'msg'     => 'event not relevant',
                )
            );
        }

        UnzerpaymentLogger::getInstance()->addLog('webhook received', 2, false, [
            'data' => $data
        ]);
        if ( empty( $data['paymentId'] ) ) {
            UnzerpaymentLogger::getInstance()->addLog('no payment id in webhook event', 1, false, [
                'data' => $data
            ]);
            exit();
        }

        $orderId = UnzerpaymentHelper::getOrderIdByTransactionId(
            $data['paymentId'],
            UnzerpaymentHelper::getShopIdsByPubKey($data['publicKey'])
        );

        if ( empty( $orderId ) ) {
            UnzerpaymentLogger::getInstance()->addLog('no order id for webhook event found', 1, false, [
                'data' => $data
            ]);

            if ($data['event'] === WebhookEvents::CHARGE_SUCCEEDED || $data['event'] === WebhookEvents::AUTHORIZE_SUCCEEDED) {
                $unzer = UnzerpaymentClient::getInstance();
                $payment = $unzer->fetchPayment(
                    $data['paymentId']
                );

                if ($payment) {
                    $metadata = $payment->getMetadata();
                    $psCartId = $metadata->getMetadata('psCartId');
                    $psCustomerId = $metadata->getMetadata('psCustomerId');
                    $psSelectedPaymentMethod = $metadata->getMetadata('psSelectedPaymentMethod');

                    $cart = new Cart((int)$psCartId);
                    $customer = new Customer((int)$psCustomerId);

                    if (!is_null($cart->id) && !is_null($customer->id)) {

                        if ($payment->isCompleted()) {
                            $orderStatus = Configuration::get('UNZERPAYMENT_STATUS_CAPTURED');
                        } else {
                            $orderStatus = Configuration::get('UNZERPAYMENT_STATUS_PLACED');
                        }

                        $paymentMethodName = \Unzerpayment\Classes\UnzerpaymentClient::guessPaymentMethodClass(
                            $psSelectedPaymentMethod
                        );
                        $paymentMethodName = \UnzerPayment\Classes\UnzerpaymentHelper::getMappedPaymentName(
                            $paymentMethodName
                        );

                        try {
                            UnzerpaymentLogger::getInstance()->addLog('Validating order from Webhook', 3, false, ['transaction_id' => $data['paymentId']]);
                            $this->module->validateOrder(
                                $psCartId,
                                $orderStatus,
                                $payment->getAmount()->getTotal(),
                                $paymentMethodName . ' (' . $this->module->displayName . ')',
                                '',
                                [
                                    'transaction_id' => $data['paymentId'],
                                ],
                                $cart->id_currency,
                                false,
                                $customer->secure_key
                            );

                            $metadata = $unzer->fetchMetadata(
                                $metadata->getId()
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

                        } catch (Exception $e) {
                            UnzerpaymentLogger::getInstance()->addLog('Order could not be validated from Webhook', 1, $e);
                            $this->renderJson(
                                array(
                                    'success' => true,
                                    'msg'     => 'order not found or relevant in shop',
                                )
                            );
                        }

                    }
                }
            }

            exit();
        }

        $eventHash = 'unzer_event_' . md5( $data['paymentId'] . '|' . $data['event'] );

        switch ( $data['event'] ) {
            case WebhookEvents::CHARGE_CANCELED:
            case WebhookEvents::AUTHORIZE_CANCELED:
                $this->handleCancel( $data['paymentId'], $orderId );
                break;
            case WebhookEvents::AUTHORIZE_SUCCEEDED:
                $this->handleAuthorizeSucceeded( $data['paymentId'], $orderId );
                break;
            case WebhookEvents::CHARGE_SUCCEEDED:
                $this->handleChargeSucceeded( $data['paymentId'], $orderId );
                break;
            case WebhookEvents::PAYMENT_CHARGEBACK:
                $this->handleChargeback( $data['paymentId'], $orderId );
                break;
        }

        $this->renderJson( array( 'success' => true ) );
    }

    /**
     * @param $data
     * @return void
     */
    protected function renderJson($data) {
        header( 'Content-Type: application/json' );
        echo json_encode($data);
        die;
    }

    public function handleChargeback( $paymentId, $orderId ) {
        UnzerpaymentLogger::getInstance()->addLog('webhook handleChargeback', 3, false, [
            'paymentId' => $paymentId,
            'orderId' => $orderId
        ]);
        UnzerpaymentHelper::setOrderStatus(
            $orderId,
            (int)\Configuration::get('UNZERPAYMENT_STATUS_CHARGEBACK') ? (int)\Configuration::get('UNZERPAYMENT_STATUS_CHARGEBACK') : (int)\Configuration::get('_PS_OS_ERROR_')
        );
    }
    private function handleCancel( $paymentId, $orderId ) {
        UnzerpaymentLogger::getInstance()->addLog('webhook handleCancle', 3, false, [
            'paymentId' => $paymentId,
            'orderId' => $orderId
        ]);
        if (!(int)\Configuration::get('UNZERPAYMENT_STATUS_CANCELLED') > 0) {
            return;
        }
        UnzerpaymentHelper::setOrderStatus(
            $orderId,
            \Configuration::get('UNZERPAYMENT_STATUS_CANCELLED')
        );
    }
    private function handleAuthorizeSucceeded( $paymentId, $orderId ) {
        UnzerpaymentLogger::getInstance()->addLog('webhook handleAuthorizeSucceeded', 3, false, [
            'paymentId' => $paymentId,
            'orderId' => $orderId
        ]);
        if (!(int)\Configuration::get('UNZERPAYMENT_STATUS_PLACED') > 0) {
            return;
        }
        UnzerpaymentHelper::setOrderStatus(
            $orderId,
            \Configuration::get('UNZERPAYMENT_STATUS_PLACED')
        );
    }
    private function handleChargeSucceeded( $paymentId, $orderId ) {
        UnzerpaymentLogger::getInstance()->addLog('webhook handleChargeSucceeded', 3, false, [
            'paymentId' => $paymentId,
            'orderId' => $orderId
        ]);
        if (!(int)\Configuration::get('UNZERPAYMENT_STATUS_CAPTURED') > 0) {
            return;
        }
        UnzerpaymentHelper::setOrderStatus(
            $orderId,
            \Configuration::get('UNZERPAYMENT_STATUS_CAPTURED')
        );
    }

}
