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

namespace Unzerpayment\Classes;

use UnzerSDK\Resources\TransactionTypes\AbstractTransactionType;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Cancellation;
use UnzerSDK\Resources\TransactionTypes\Charge;

if (!defined('_PS_VERSION_')) {
    exit;
}

class UnzerpaymentClient extends \UnzerSDK\Unzer {

    public static $_instance = null;

    /**
     * @param $reset
     * @return self|null
     */
    public static function getInstance($reset = false)
    {
        if ($reset) {
            self::$_instance = null;
        }
        if (\Configuration::get('UNZERPAYMENT_PRIVATE_KEY') == '') {
            return null;
        }
        if (null === self::$_instance) {
            try {
                self::$_instance = new self(
                    \Configuration::get('UNZERPAYMENT_PRIVATE_KEY'),
                    UnzerpaymentHelper::getUnzerLanguage()
                );
            } catch (\Exception $e) {
                self::$_instance = null;
            }
        }
        return self::$_instance;
    }

    /**
     * @param $paymentId
     * @param $amount
     * @param $order_id
     * @param $invoice_id
     * @return bool
     */
    public function performChargeOnAuthorization( $paymentId, $amount = null, $order_id = null, $invoice_id = null ) {
        $charge = new Charge();
        if ($amount) {
            $charge->setAmount($amount);
        }
        if ($order_id) {
            $charge->setOrderId($order_id);
        }
        if ($invoice_id) {
            $charge->setInvoiceId($invoice_id);
        }
        $chargeResult = false;
        try {
            $chargeResult = $this->performChargeOnPayment($paymentId, $charge);
        } catch (\UnzerSDK\Exceptions\UnzerApiException $e) {
            UnzerpaymentLogger::getInstance()->addLog('performChargeOnPayment Error', 1, $e, [
                'paymentId' => $paymentId,
                'amount' => $amount
            ]);
        } catch (\RuntimeException $e) {
            UnzerpaymentLogger::getInstance()->addLog('performChargeOnPayment Error', 1, $e, [
                'paymentId' => $paymentId,
                'amount' => $amount
            ]);
        }
        return (bool)$chargeResult;
    }

    /**
     * @param $context
     * @return array
     * @throws \UnzerSDK\Exceptions\UnzerApiException
     */
    public static function getAvailablePaymentMethods($context = 'backend')
    {
        $unzerClient = self::getInstance();
        if ($unzerClient === null) {
            return [];
        }

        $keypairResponse = $unzerClient->fetchKeypair(true);
        $availablePaymentTypes = $keypairResponse->getAvailablePaymentTypes();

        $availablePaymentTypes = array_values(array_filter(
            $availablePaymentTypes,
            static function ($paymentType) {
                return !in_array($paymentType->type, ['PIS', 'giropay'], true);
            }
        ));

        if ($context === 'frontend') {
            $order = [
                'paylater-invoice',
                'paylater-installment',
                'paylater-direct-debit',
                'openbanking-pis',
                'card',
                'clicktopay',
                'applepay',
                'googlepay',
                'wero',
                'klarna',
                'EPS',
                'ideal',
                'przelewy24',
                'twint',
                'post-finance-efinance',
                'post-finance-card',
                'payu',
                'sepa-direct-debit',
                'prepayment',
                'invoice',
                'bancontact',
                'paypal',
                'alipay',
                'wechatpay',
                'sofort'
            ];

            $orderMap = array_flip($order);

            usort($availablePaymentTypes, static function ($a, $b) use ($orderMap) {
                $aPos = $orderMap[$a->type] ?? PHP_INT_MAX;
                $bPos = $orderMap[$b->type] ?? PHP_INT_MAX;

                return $aPos <=> $bPos;
            });
        } else {
            usort($availablePaymentTypes, static function ($a, $b) {
                return strcasecmp($a->type, $b->type);
            });
        }

        return $availablePaymentTypes;
    }

    /**
     * @return array|false
     */
    public function getWebhooksList()
    {
        try {
            $webhooks = $this->fetchAllWebhooks();
            if (sizeof($webhooks) > 0) {
                return $webhooks;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function guessPaymentMethodClass($paymentType)
    {
        $newParts = [];
        $paymentType = str_replace('-', '_', $paymentType);
        $parts = explode('_', $paymentType);
        foreach ($parts as $part) {
            $newParts[] = ucfirst($part);
        }
        $className = join('', $newParts);
        if (class_exists("UnzerSDK\Resources\PaymentTypes\\" . $className)) {
            if ($className == 'OpenbankingPis') {
                return strtolower($className);
            }
            return lcfirst($className);
        }
        return $paymentType;
    }


}
