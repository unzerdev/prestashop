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

if (!defined('_PS_VERSION_')) {
    exit;
}

class UnzerpaymentCountryRestriction
{

    const ALLOWED_COUNTRIES = [
        'alipay' => ['DE', 'AT', 'BE', 'IT', 'ES', 'NL'],
        'bancontact' => ['BE'],
        'eps' => ['AT'],
        'ideal' => ['NL'],
        'klarna' => ['AU','AT', 'BE', 'CA', 'CZ', 'DK', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'MX', 'NL', 'NZ', 'NO', 'PL', 'PT', 'RO', 'SK', 'ES', 'SE', 'CH', 'GB', 'US'],
        'openbanking_pis' => ['DE', 'AT', 'DK', 'GB', 'SE', 'NO', 'FR', 'PL'],
        'paylater_direct_debit' => ['DE', 'AT'],
        'paylater_installment' => ['DE', 'AT', 'CH'],
        'paylater_invoice' => ['DE', 'AT', 'CH', 'NL'],
        'payu' => ['PL', 'CZ'],
        'post_finance_card' => ['CH'],
        'post_finance_efinance' => ['CH'],
        'przelewy24' => ['PL'],
        'twint' => ['CH'],
        'wechatpay' => ['AT', 'BE', 'DK', 'FI', 'FR', 'DE', 'ES', 'GB', 'GR', 'HU', 'IE', 'IS', 'IT', 'LI', 'LU', 'MT', 'NL', 'NO', 'PT', 'SE'],
    ];

    /**
     * @param $paymenttype
     * @param $countryIsoCode
     * @return bool
     */
    public static function isCountryAllowed($paymenttype, $countryIsoCode)
    {
        $paymenttype = strtolower(str_replace('-', '_', $paymenttype));
        if (!isset(self::ALLOWED_COUNTRIES[$paymenttype])) {
            return true;
        }
        if (in_array($countryIsoCode, self::ALLOWED_COUNTRIES[$paymenttype])) {
            return true;
        }
        return false;
    }
}