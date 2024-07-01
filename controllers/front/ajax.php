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
use UnzerPayment\Classes\UnzerpaymentHelper;
use UnzerPayment\Classes\UnzerpaymentLogger;

if (!defined('_PS_VERSION_')) {
    exit;
}

class UnzerpaymentAjaxModuleFrontController extends ModuleFrontController
{

    public function postProcess()
    {
        $return = null;

        switch (\Tools::getValue('unzerAction')) {
            case 'createRessourcesAndInit':

                $selectedPaymentMethod = \Tools::getValue('selectedUnzerPaymentMethod');

                $unzer = UnzerpaymentClient::getInstance();
                $customer = new \Customer((int)Context::getContext()->cart->id_customer);
                $addressBilling = new \Address((int)Context::getContext()->cart->id_address_invoice);
                $addressShipping = new \Address((int)Context::getContext()->cart->id_address_delivery);

                $unzerAddressBilling = (new \UnzerSDK\Resources\EmbeddedResources\Address())
                    ->setName($addressBilling->firstname . ' ' . $addressBilling->lastname)
                    ->setStreet($addressBilling->address1)
                    ->setZip($addressBilling->postcode)
                    ->setCity($addressBilling->city)
                    ->setCountry(\Country::getIsoById((int) $addressBilling->id_country));

                $unzerAddressShipping = (new \UnzerSDK\Resources\EmbeddedResources\Address())
                    ->setName($addressShipping->firstname . ' ' . $addressShipping->lastname)
                    ->setStreet($addressShipping->address1)
                    ->setZip($addressShipping->postcode)
                    ->setCity($addressShipping->city)
                    ->setCountry(\Country::getIsoById((int) $addressShipping->id_country));

                if ((int)Context::getContext()->cart->id_address_invoice == (int)Context::getContext()->cart->id_address_delivery) {
                    $unzerAddressShipping->setShippingType(
                        \UnzerSDK\Constants\ShippingTypes::EQUALS_BILLING
                    );
                } else {
                    $unzerAddressShipping->setShippingType(
                        \UnzerSDK\Constants\ShippingTypes::DIFFERENT_ADDRESS
                    );
                }

                $unzerCustomer = (new \UnzerSDK\Resources\Customer())
                    ->setCustomerId($customer->id)
                    ->setFirstname($addressBilling->firstname)
                    ->setLastname($addressBilling->lastname)
                    ->setCompany($addressBilling->company)
                    ->setEmail($customer->email)
                    ->setMobile($addressBilling->phone_mobile)
                    ->setPhone($addressBilling->phone_mobile)
                    ->setBillingAddress($unzerAddressBilling)
                    ->setShippingAddress($unzerAddressShipping);

                if (\Validate::isBirthDate($customer->birthday) && $customer->birthday != '0000-00-00') {
                    $unzerCustomer
                        ->setBirthDate((new \DateTime($customer->birthday))->format('Y-m-d'));
                }
                if ($customer->id_gender == 1) {
                    $unzerCustomer
                        ->setSalutation(\UnzerSDK\Constants\Salutations::MR);
                } elseif ($customer->id_gender = 2) {
                    $unzerCustomer
                        ->setSalutation(\UnzerSDK\Constants\Salutations::MRS);
                } else {
                    $unzerCustomer
                        ->setSalutation(\UnzerSDK\Constants\Salutations::UNKNOWN);
                }

                $unzer->createOrUpdateCustomer(
                    $unzerCustomer
                );
                $orderId = UnzerPaymentHelper::getPreOrderId();

                $basket = (new \UnzerSDK\Resources\Basket())
                    ->setTotalValueGross(Context::getContext()->cart->getOrderTotal())
                    ->setCurrencyCode(Context::getContext()->currency->iso_code)
                    ->setOrderId($orderId)
                    ->setNote('');

                $basketItems = [];
                $tmpSum = 0;
                foreach (Context::getContext()->cart->getProducts() as $product) {
                    $tmpSum += UnzerpaymentHelper::prepareAmountValue($product['price_wt']) * $product['quantity'];
                    $basketItem = (new \UnzerSDK\Resources\EmbeddedResources\BasketItem())
                        ->setBasketItemReferenceId('Item-' . $product['id_product'])
                        ->setQuantity($product['quantity'])
                        ->setUnit('m')
                        ->setAmountPerUnitGross(UnzerpaymentHelper::prepareAmountValue($product['price_wt']))
                        ->setVat($product['rate'])
                        ->setTitle($product['name'])
                        ->setType(\UnzerSDK\Constants\BasketItemTypes::GOODS);

                    $basketItems[] = $basketItem;
                }

                if (Context::getContext()->cart->getTotalShippingCost() > 0) {
                    $tmpSum += Context::getContext()->cart->getTotalShippingCost();
                    $basketItem = (new \UnzerSDK\Resources\EmbeddedResources\BasketItem())
                        ->setBasketItemReferenceId('Shipping')
                        ->setQuantity(1)
                        ->setAmountPerUnitGross(UnzerpaymentHelper::prepareAmountValue(Context::getContext()->cart->getTotalShippingCost()))
                        ->setTitle('Shipping')
                        ->setType(\UnzerSDK\Constants\BasketItemTypes::SHIPMENT);
                    $basketItems[] = $basketItem;
                }

                $discountsAmount = Context::getContext()->cart->getOrderTotal(true, CART::ONLY_DISCOUNTS);
                if ($discountsAmount > 0) {
                    $tmpSum -= $discountsAmount;
                    $basketItem = (new \UnzerSDK\Resources\EmbeddedResources\BasketItem())
                        ->setBasketItemReferenceId('Voucher')
                        ->setQuantity(1)
                        ->setAmountDiscountPerUnitGross(UnzerpaymentHelper::prepareAmountValue($discountsAmount))
                        ->setTitle('Voucher Delta')
                        ->setType(\UnzerSDK\Constants\BasketItemTypes::VOUCHER);
                    $basketItems[] = $basketItem;
                }

                $difference = Context::getContext()->cart->getOrderTotal() - $tmpSum;
                if ($difference > 0) {
                    $basketItem = (new \UnzerSDK\Resources\EmbeddedResources\BasketItem())
                        ->setBasketItemReferenceId('add-shipping-delta')
                        ->setQuantity(1)
                        ->setAmountPerUnitGross(UnzerpaymentHelper::prepareAmountValue($difference))
                        ->setTitle('Shipping')
                        ->setSubTitle('Shipping Delta')
                        ->setType(\UnzerSDK\Constants\BasketItemTypes::SHIPMENT);
                    $basketItems[] = $basketItem;
                } elseif ($difference < 0) {
                    $basketItem = (new \UnzerSDK\Resources\EmbeddedResources\BasketItem())
                        ->setBasketItemReferenceId('VoucherDelta')
                        ->setQuantity(1)
                        ->setAmountDiscountPerUnitGross(UnzerpaymentHelper::prepareAmountValue($difference) * -1)
                        ->setTitle('Voucher Delta')
                        ->setType(\UnzerSDK\Constants\BasketItemTypes::VOUCHER);
                    $basketItems[] = $basketItem;
                }

                foreach ($basketItems as $basketItem) {
                    $basket->addBasketItem(
                        $basketItem
                    );
                }

                $successURL = UnzerpaymentHelper::getSuccessUrl(
                    [
                        'caid' => Context::getContext()->cart->id,
                        'cuid' => Context::getContext()->customer->id,
                    ]
                );

                $paypage = new \UnzerSDK\Resources\PaymentTypes\Paypage(
                    Context::getContext()->cart->getOrderTotal(),
                    Context::getContext()->currency->iso_code,
                    $successURL
                );
                $threatMetrixId = 'UnzerPaymentPS_' . $orderId;

                $metadata = new \UnzerSDK\Resources\Metadata();
                foreach ($this->module->getMetaData() as $key => $val) {
                    if ($key == 'shopType') {
                        $metadata->setShopType($val);
                    } elseif ($key == 'shopVersion') {
                        $metadata->setShopVersion($val);
                    } else {
                        $metadata->addMetadata($key, $val);
                    }
                }

                $paypage->setShopName(Context::getContext()->shop->name)
                    ->setOrderId($orderId)
                    ->setAdditionalAttribute('riskData.threatMetrixId', $threatMetrixId)
                    ->setAdditionalAttribute('riskData.customerGroup', 'NEUTRAL')
                    ->setAdditionalAttribute('riskData.customerId', Context::getContext()->customer->id)
                    ->setAdditionalAttribute('riskData.confirmedAmount', UnzerpaymentHelper::getCustomersTotalOrderAmount())
                    ->setAdditionalAttribute('riskData.confirmedOrders', UnzerpaymentHelper::getCustomersTotalOrders())
                    ->setAdditionalAttribute('riskData.registrationLevel', Context::getContext()->customer->logged ? '1' : '0')
                    ->setAdditionalAttribute('riskData.registrationDate', UnzerpaymentHelper::getCustomersRegistrationDate())
                    ;

                foreach (UnzerpaymentHelper::getInactivePaymentMethods() as $inactivePaymentMethod) {
                    $paypage->addExcludeType($inactivePaymentMethod);
                }

                foreach (UnzerpaymentClient::getAvailablePaymentMethods() as $availablePaymentMethod) {
                    if ($selectedPaymentMethod != $availablePaymentMethod->type) {
                        $paypage->addExcludeType($availablePaymentMethod->type);
                    }
                }

                $cssArray = UnzerpaymentHelper::getCustomizedCssArray();

                if (sizeof($cssArray) > 0) {
                    $paypage->setCss(
                        $cssArray
                    );
                }

                UnzerpaymentLogger::getInstance()->addLog('initPayPage Request', 3, false, [
                    'paypage' => $paypage,
                    'unzerCustomer' => $unzerCustomer,
                    'basket' => $basket,
                    'metadata' => $metadata,
                    'tmpsum' => $tmpSum
                ]);

                if (UnzerpaymentHelper::getPaymentMethodChargeMode($selectedPaymentMethod) == 'authorize' || UnzerpaymentHelper::isSandboxMode()) {
                    try {
                        $unzer->initPayPageAuthorize($paypage, $unzerCustomer, $basket, $metadata);
                    } catch (\Exception $e) {
                        UnzerpaymentLogger::getInstance()->addLog('initPayPageAuthorize Error', 1, $e, [
                            'paypage' => $paypage,
                            'unzerCustomer' => $unzerCustomer,
                            'basket' => $basket,
                            'metadata' => $metadata
                        ]);
                    }
                } else {
                    try {
                        $unzer->initPayPageCharge($paypage, $unzerCustomer, $basket, $metadata);
                    } catch (\Exception $e) {
                        UnzerpaymentLogger::getInstance()->addLog('initPayPageCharge Error', 1, $e, [
                            'paypage' => $paypage,
                            'unzerCustomer' => $unzerCustomer,
                            'basket' => $basket,
                            'metadata' => $metadata
                        ]);
                    }
                }

                UnzerpaymentLogger::getInstance()->addLog('received page page token', 3, false, [
                    'UnzerPaymentId' => $paypage->getPaymentId(),
                    'token' => $paypage->getId(),
                ]);

                Context::getContext()->cookie->UnzerPaymentId = $paypage->getPaymentId();

                $return = ['token' => $paypage->getId(), 'successURL' => $successURL];
            break;
        }

        echo json_encode(
            $return
        );
        exit();
    }

}
