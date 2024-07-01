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
            $data['paymentId']
        );
        if ( empty( $orderId ) ) {
            UnzerpaymentLogger::getInstance()->addLog('no order id for webhook event found', 1, false, [
                'data' => $data
            ]);
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
