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

if (!defined('_PS_VERSION_')) {
    exit;
}

class UnzerpaymentFailureModuleFrontController extends ModuleFrontController
{
    use UnzerPaymentRedirectionTrait;

    public function postProcess()
    {
        $this->errorRedirect();
    }

    protected function errorRedirect($msg = 'There has been an error processing your order.', $pagecontroller = 'cart')
    {
        $request = null;
        if ($pagecontroller == 'cart') {
            $request = ['action' => 'show'];
        }
        $this->errors[] = $this->module->l($msg);
        $this->PrestaShopRedirectWithNotifications(
            $this->context->link->getPageLink($pagecontroller, null, null, $request)
        );
    }

}
