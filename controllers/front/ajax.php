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

                $customerId = !Context::getContext()->customer->logged ? ('PS-Guest-' . $customer->id) : ('PS-' . $customer->id);

                $need_customer_update = false;
                try {
                    $unzerCustomer = $unzer->fetchCustomer($customerId);
                    $need_customer_update = true;
                } catch (\Exception $e) {
                    $unzerCustomer = new \UnzerSDK\Resources\Customer();
                }

                $unzerCustomer
                    ->setCustomerId($customerId)
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
                } elseif ($customer->id_gender == 2) {
                    $unzerCustomer
                        ->setSalutation(\UnzerSDK\Constants\Salutations::MRS);
                } else {
                    $unzerCustomer
                        ->setSalutation(\UnzerSDK\Constants\Salutations::UNKNOWN);
                }

                if ($need_customer_update) {
                    $unzer->updateCustomer($unzerCustomer);
                } else {
                    $unzer->createCustomer($unzerCustomer);
                }

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
                    $basketItemReferenceId = 'Item-' . $product['id_product'];
                    if (isset($product['id_product_attribute']) && $product['id_product_attribute'] != '') {
                        $basketItemReferenceId.= '-' . $product['id_product_attribute'];
                    }
                    $basketItem = (new \UnzerSDK\Resources\EmbeddedResources\BasketItem())
                        ->setBasketItemReferenceId($basketItemReferenceId)
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

                $discountsAmount = Context::getContext()->cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS);
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

                $difference = ((float)Context::getContext()->cart->getOrderTotal()*100 - (float)$tmpSum*100)/100;
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

                $unzer->createBasket($basket);

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
                $unzer->createMetadata($metadata);

                $resources = new \UnzerSDK\Resources\EmbeddedResources\Paypage\Resources(
                    $unzerCustomer->getId(),
                    $basket->getId(),
                    $metadata->getId()
                );

                $paymentMethodClass = UnzerpaymentClient::guessPaymentMethodClass($selectedPaymentMethod);
                $currentMethodConfig = new \UnzerSDK\Resources\EmbeddedResources\Paypage\PaymentMethodConfig(true, 1);
                $paymentMethodsConfig = (new \UnzerSDK\Resources\EmbeddedResources\Paypage\PaymentMethodsConfigs())
                    ->setDefault((new \UnzerSDK\Resources\EmbeddedResources\Paypage\PaymentMethodConfig())->setEnabled(false))
                    ->addMethodConfig(
                        $paymentMethodClass,
                        $currentMethodConfig
                    );

                if (Context::getContext()->customer->logged && in_array($paymentMethodClass, ['card', 'sepaDirectDebit', 'paypal'])) {
                    $currentMethodConfig->setCredentialOnFile(true);
                    $paymentMethodsConfig->addMethodConfig(
                        $paymentMethodClass,
                        $currentMethodConfig
                    );
                }

                if ($paymentMethodClass == 'card') {
                    if (Configuration::get('UNZERPAYMENT_PAYMENTYPE_STATUS_clicktopay') == '1') {
                        $paymentMethodsConfig->addMethodConfig(
                            'clicktopay',
                            new \UnzerSDK\Resources\EmbeddedResources\Paypage\PaymentMethodConfig(true, 2)
                        );
                    }
                }

                $risk = new \UnzerSDK\Resources\EmbeddedResources\RiskData();
                $risk->setRegistrationLevel(Context::getContext()->customer->logged ? '1' : '0');
                if (Context::getContext()->customer->logged) {
                    $risk->setRegistrationDate(
                        UnzerpaymentHelper::getCustomersRegistrationDate()
                    );
                    $risk->setConfirmedAmount(UnzerpaymentHelper::getCustomersTotalOrderAmount())
                        ->setConfirmedOrders(UnzerpaymentHelper::getCustomersTotalOrders());

                    if (UnzerpaymentHelper::getCustomersTotalOrders() > 3) {
                        $risk->setCustomerGroup('TOP');
                    } elseif (UnzerpaymentHelper::getCustomersTotalOrders() >= 1) {
                        $risk->setCustomerGroup('GOOD');
                    } else {
                        $risk->setCustomerGroup('NEUTRAL');
                    }
                }

                $paypage = new \UnzerSDK\Resources\V2\Paypage(Context::getContext()->cart->getOrderTotal(), Context::getContext()->currency->iso_code);
                $paypage->setPaymentMethodsConfigs($paymentMethodsConfig);
                $paypage->setResources($resources);
                $paypage->setType("embedded");
                $paypage->setMode(UnzerpaymentHelper::getPaymentMethodChargeMode($selectedPaymentMethod) == 'authorize' || UnzerpaymentHelper::isSandboxMode() ? 'authorize' : 'charge');
                $paypage->setCheckoutType(\UnzerSDK\Constants\PaypageCheckoutTypes::PAYMENT_ONLY);

                $redirectUrl = UnzerpaymentHelper::getSuccessUrl(
                    [
                        'caid' => Context::getContext()->cart->id,
                        'cuid' => Context::getContext()->customer->id,
                    ]
                );

                $paypage->setUrls(
                    (new \UnzerSDK\Resources\EmbeddedResources\Paypage\Urls())
                        ->setReturnSuccess($redirectUrl)
                        ->setReturnFailure($redirectUrl)
                        ->setReturnPending($redirectUrl)
                        ->setReturnCancel($redirectUrl)
                );

                try {
                    $unzer->createPaypage($paypage);
                } catch (\Exception $exception) {
                    UnzerpaymentLogger::getInstance()->addLog('createPaypage Error', 1, $exception, [
                        'paypage' => $paypage,
                        'unzerCustomer' => $unzerCustomer,
                        'basket' => $basket,
                        'metadata' => $metadata
                    ]);
                }

                UnzerpaymentLogger::getInstance()->addLog('received page page token', 3, false, [
                    'UnzerPaymentId' => $paypage->getId(),
                    'token' => $paypage->getId(),
                ]);

                Context::getContext()->cookie->UnzerPaypageId = $paypage->getId();
                Context::getContext()->cookie->UnzerMetadataId = $metadata->getId();
                Context::getContext()->cookie->UnzerSelectedPaymentMethod = $selectedPaymentMethod;

                $return = [
                    'token' => $paypage->getId(),
                    'pubKey' => Configuration::get('UNZERPAYMENT_PUBLIC_KEY'),
                    'successURL' => $redirectUrl,
                    'unzerLocale' => strtolower(Context::getContext()->language->iso_code),
                    'ctp' => Configuration::get('UNZERPAYMENT_PAYMENTYPE_STATUS_clicktopay') == '1'];
            break;
        }

        echo json_encode(
            $return
        );
        exit();
    }

}
