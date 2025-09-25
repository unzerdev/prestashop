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

namespace UnzerPayment\Classes;
use UnzerSDK\Resources\TransactionTypes\AbstractTransactionType;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Cancellation;
use UnzerSDK\Resources\TransactionTypes\Charge;

if (!defined('_PS_VERSION_')) {
    exit;
}

class UnzerpaymentHelper
{

    /**
     * @param $params
     * @param $controller
     *
     * @return string
     */
    public static function getSuccessUrl($params = [], $controller = 'success')
    {
        return \Context::getContext()->link->getModuleLink(
            'unzerpayment',
            $controller,
            $params,
            true
        );
    }

    /**
     * @return string
     */
    public static function getFailureUrl()
    {
        return \Context::getContext()->link->getModuleLink(
            'unzerpayment',
            'failure',
            [
            ],
            true
        );
    }

    /**
     * @return string
     */
    public static function getNotifyUrl()
    {
        return \Context::getContext()->link->getModuleLink(
            'unzerpayment',
            'notify',
            [
            ],
            true
        );
    }

    /**
     * @param $newStatus
     * @param bool $check
     * @param bool $sendmail
     *
     * @return bool
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function setOrderStatus($id_order, $newStatus, $check = true, $sendmail = true)
    {
        if ((int) $id_order > 0) {
            if ($check) {
                $order = new \Order((int) $id_order);
                $history = $order->getHistory(\Context::getContext()->language->id, $newStatus);
                if (sizeof($history) > 0) {
                    return false;
                }
                if ($order->getCurrentState() == $newStatus) {
                    return false;
                }
            }
            $order_history = new \OrderHistory();
            $order_history->id_order = (int) $id_order;
            $order_history->changeIdOrderState((int) $newStatus, (int) $id_order, true);
            if ($sendmail) {
                $order_history->addWithemail(true);
            } else {
                $order_history->add(true);
            }
        }
    }

    /**
     * @param $number
     * @return string
     */
    public static function prepareAmountValue($number)
    {
        return number_format($number, 2, '.', '');
    }

    /**
     * @param $string
     * @param $capitalizeFirstCharacter
     * @return array|string|string[]
     */
    public static function dashesToCamelCase($string, $capitalizeFirstCharacter = false)
    {

        $str = str_replace('-', '', ucwords($string, '-'));

        if (!$capitalizeFirstCharacter) {
            $str = lcfirst($str);
        }

        return $str;
    }

    /**
     * @param $paymentRessourceClassName
     * @return bool
     */
    public static function paymentMethodCanAuthorize($paymentRessourceClassName)
    {
        if (class_exists("UnzerSDK\Resources\PaymentTypes\\" . $paymentRessourceClassName)) {
            if (method_exists("UnzerSDK\Resources\PaymentTypes\\" . $paymentRessourceClassName, "authorize")) {
                return true;
            }
        };
        return false;
    }

    /**
     * Returns specific  Language
     *
     * @return string
     */
    public static function getUnzerLanguage()
    {
        return \Context::getContext()->language->iso_code . '_' . \Tools::strtoupper(\Context::getContext()->language->iso_code);
    }

    /**
     * @return array
     * @throws \UnzerSDK\Exceptions\UnzerApiException
     */
    public static function getInactivePaymentMethods()
    {
        $inactive_payment_methods = ['giropay', 'PIS', 'bancontact'];
        $methods = UnzerpaymentClient::getAvailablePaymentMethods();
        foreach ($methods as $method) {
            $paymentType = $method->type;
            if (\Configuration::get('UNZERPAYMENT_PAYMENTYPE_STATUS_' . $paymentType) == 0) {
                $inactive_payment_methods[] = $method->type;
            }
        }
        return $inactive_payment_methods;
    }

    /**
     * @param $paymentRessourceClassName
     * @return string
     */
    public static function getPaymentMethodChargeMode($paymentRessourceClassName)
    {
        if (\Configuration::get('UNZERPAYMENT_PAYMENTYPE_CHARGE_MODE_' . $paymentRessourceClassName) == 'authorize') {
            return 'authorize';
        }
        return 'charge';
    }

    /**
     * @return false|string
     */
    public static function getChargeMode()
    {
        return \Configuration::get('UNZERPAYMENT_CHARGEMODE') == 'authorize' ? \Configuration::get('UNZERPAYMENT_CHARGEMODE') : 'charge';
    }

    /**
     * @return bool
     */
    public static function isSandboxMode()
    {
        return \Configuration::get('UNZERPAYMENT_TESTMODE') == '1';
    }

    /**
     * @return string
     */
    public static function getPreOrderId()
    {
        return 'cart-' . \Context::getContext()->cart->id;
    }

    /**
     * @param $state
     * @return bool
     */
    public static function isValidState($state)
    {
        return in_array(
            $state,
            [
                \UnzerSDK\Constants\TransactionStatus::STATUS_SUCCESS,
                \UnzerSDK\Constants\TransactionStatus::STATUS_PENDING
            ]
        );
    }

    /**
     * @param $string
     * @return false|string
     */
    public static function parsePaymentIdString($string)
    {
        $stringExploded = explode('-', $string);
        if (isset($stringExploded[1])) {
            return $stringExploded[1];
        }
        return false;
    }

    /**
     * @param $paymentType
     * @return string
     */
    public static function getMappedPaymentName($paymentType)
    {
        return (new \Unzerpayment())->l($paymentType);
    }

    /**
     * @param $fullName
     * @return string
     * @throws \ReflectionException
     */
    public static function getPaymentClassNameByFullName($fullName)
    {
        return (new \ReflectionClass($fullName))->getShortName();
    }

    /**
     * @param $transactionId
     * @return false|mixed
     */
    public static function getOrderIdByTransactionId($transactionId)
    {
        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            'SELECT o.`id_order` FROM `' . _DB_PREFIX_ . 'order_payment` a
               JOIN `' . _DB_PREFIX_ . 'orders` o ON o.reference = a.order_reference
              WHERE a.`transaction_id` = "' . pSQL($$transactionId) . '"'
        );
        if (is_array($result) && $result['id_order']) {
            return $result['id_order'];
        }
        return false;
    }

    /**
     * @param $order
     * @return false|mixed
     */
    public static function getTransactionIdByOrder($order)
    {
        $payments = $order->getOrderPayments();
        if (sizeof($payments) > 0) {
            foreach ($payments as $payment) {
                if ($payment->transaction_id != '') {
                    return $payment->transaction_id;
                }
            }
        }
        return false;
    }

    /**
     * @return int|mixed
     */
    public static function getCustomersTotalOrderAmount()
    {
        $amount = 0;
        foreach (\Order::getCustomerOrders((int)\Context::getContext()->customer->id) as $customerOrder) {
            $amount = $amount + $customerOrder['total_paid_real'];
        }
        return $amount;
    }

    /**
     * @return int|null
     */
    public static function getCustomersTotalOrders()
    {
        return sizeof(\Order::getCustomerOrders((int)\Context::getContext()->customer->id));
    }

    /**
     * @return array
     */
    public static function getCustomizedCssArray()
    {
        $css = [];
        foreach (['header', 'shopName', 'tagline'] as $cssKey) {
            $cssRules = [];
            $color = \Configuration::get('UNZERPAYMENT_DESIGN_' . strtoupper($cssKey) . '_COLOR');
            $fontsize = \Configuration::get('UNZERPAYMENT_DESIGN_' . strtoupper($cssKey) . '_FONTSIZE');
            $backgroundcolor = \Configuration::get('UNZERPAYMENT_DESIGN_' . strtoupper($cssKey) . '_BACKGROUNDCOLOR');

            if (trim($color) != '') {
                $cssRules[] = 'color: ' . $color;
            }
            if (trim($fontsize) != '') {
                $cssRules[] = 'font-size: ' . $fontsize;
            }
            if (trim($backgroundcolor) != '') {
                $cssRules[] = 'background-color: ' . $backgroundcolor;
            }
            if (sizeof($cssRules) > 0) {
                $css[$cssKey] = join('; ', $cssRules);
            }
        }
        return $css;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public static function getCustomersRegistrationDate()
    {
        $customer = new \Customer((int)\Context::getContext()->customer->id);
        return date_format(new \DateTime($customer->date_add), 'Ymd');
    }

    /**
     * @param $id_customer
     * @param $id_cart
     * @return bool
     */
    public static function usersCartAndOrderExists($id_customer, $id_cart)
    {
        return (bool) \Db::getInstance()->getValue(
            'SELECT count(*) FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_cart` = ' . (int) $id_cart . ' AND `id_customer` = ' . (int) $id_customer,
            false
        );
    }

    /**
     * @param $price
     * @param $idCurrency
     * @param $no_utf8
     * @param $context
     * @return string
     */
    public static function displayPrice($price, $idCurrency = null, $no_utf8 = false, $context = null)
    {
        // Währung ermitteln
        if (!$idCurrency) {
            $context = \Context::getContext();
            $idCurrency = $context->currency->id;
        }

        $currency = new \Currency($idCurrency);

        // PrestaShop 9: Verwende LocaleService
        try {
            // Prüfen ob Service Locator und LocaleInterface verfügbar sind
            if (class_exists('\PrestaShop\PrestaShop\Adapter\ServiceLocator')) {
                $localeService = \PrestaShop\PrestaShop\Adapter\ServiceLocator::get('\\PrestaShop\\PrestaShop\\Core\\Localization\\LocaleInterface');
                if ($localeService && method_exists($localeService, 'formatPrice')) {
                    return $localeService->formatPrice($price, $currency->iso_code);
                }
            }
        } catch (\Throwable $e) {
            // Ignoriere und nutze fallback
        }

        // PrestaShop 8: Verwende Tools::displayPrice
        if (class_exists('\Tools') && method_exists('\Tools', 'displayPrice')) {
            return \Tools::displayPrice($price, $idCurrency, $no_utf8, $context);
        }

        // Fallback: einfache Formatierung
        return number_format($price, 2) . ' ' . $currency->iso_code;
    }

    /**
     * @param $payment_id
     * @param \Order $order
     * @return array
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     * @throws \UnzerSDK\Exceptions\UnzerApiException
     */
    public static function getTransactions($payment_id, $order)
    {
        $unzer = \Unzerpayment\Classes\UnzerpaymentClient::getInstance();
        $payment = $unzer->fetchPayment($payment_id);
        $currency     = $payment->getCurrency();
        $transactions = array();
        if ( $payment->getAuthorization() ) {
            $transactions[] = $payment->getAuthorization();
            if ( $payment->getAuthorization()->getCancellations() ) {
                $transactions = array_merge( $transactions, $payment->getAuthorization()->getCancellations() );
            }
        }
        if ( $payment->getCharges() ) {
            foreach ( $payment->getCharges() as $charge ) {
                $transactions[] = $charge;
                if ( $charge->getCancellations() ) {
                    $transactions = array_merge( $transactions, $charge->getCancellations() );
                }
            }
        }
        if ( $payment->getReversals() ) {
            foreach ( $payment->getReversals() as $reversal ) {
                $transactions[] = $reversal;
            }
        }
        if ( $payment->getRefunds() ) {
            foreach ( $payment->getRefunds() as $refund ) {
                $transactions[] = $refund;
            }
        }
        $transactionTypes = array(
            Cancellation::class  => 'cancellation',
            Charge::class        => 'charge',
            Authorization::class => 'authorization',
        );
        $transactions = array_map(
            function ( AbstractTransactionType $transaction ) use ( $transactionTypes, $currency ) {
                $return         = $transaction->expose();
                $class          = get_class( $transaction );
                $return['type'] = $transactionTypes[ $class ] ?? $class;
                $return['time'] = $transaction->getDate();
                if ( method_exists( $transaction, 'getAmount' ) && method_exists( $transaction, 'getCurrency' ) ) {
                    $return['amount'] = self::displayPrice( $transaction->getAmount(), \Currency::getIdByIsoCode($transaction->getCurrency()) );
                } elseif ( isset( $return['amount'] ) ) {
                    $return['amount'] = self::displayPrice( $return['amount'], \Currency::getIdByIsoCode($currency) );
                }
                $status           = $transaction->isSuccess() ? 'success' : 'error';
                $status           = $transaction->isPending() ? 'pending' : $status;
                $return['status'] = $status;

                return $return;
            },
            $transactions
        );
        usort(
            $transactions,
            function ( $a, $b ) {
                return strcmp( $a['time'], $b['time'] );
            }
        );
        $data = array(
            'id'                => $payment->getId(),
            'paymentMethod'     => $order->payment,
            'cartID'            => $order->id_cart,
            'paymentBaseMethod' => \UnzerSDK\Services\IdService::getResourceTypeFromIdString($payment->getPaymentType()->getId()),
            'shortID'           => $payment->getInitialTransaction()->getShortId(),
            'amount'            => self::displayPrice( $payment->getAmount()->getTotal(), \Currency::getIdByIsoCode($payment->getAmount()->getCurrency())  ),
            'charged'           => self::displayPrice( $payment->getAmount()->getCharged(), \Currency::getIdByIsoCode($payment->getAmount()->getCurrency())  ),
            'cancelled'         => self::displayPrice( $payment->getAmount()->getCanceled(), \Currency::getIdByIsoCode($payment->getAmount()->getCurrency())  ),
            'remaining'         => self::displayPrice( $payment->getAmount()->getRemaining(), \Currency::getIdByIsoCode($payment->getAmount()->getCurrency())  ),
            'remainingPlain'    => $payment->getAmount()->getRemaining(),
            'transactions'      => $transactions,
            'status'            => $payment->getStateName(),
            'raw'               => print_r( $payment, true ),
        );
        return $data;
    }

}
