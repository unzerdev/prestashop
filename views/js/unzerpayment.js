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

$(document).ready(function ($) {

    $(".unzer-payment-form").parent().each(function() {
        let paymentOptionFormName = $(this).attr('id');
        let paymentOptionContainerId = paymentOptionFormName.replace('pay-with-', '').replace('-form', '-container');
        if ($("#" + paymentOptionContainerId).length > 0) {
            $("#" + paymentOptionContainerId).addClass('unzer-payment-option');
        }
    });

    $(".unzer-payment-form").on('submit', function(e) {
        let selectedUnzerPaymentMethod = $(this).attr('data-payment-method');
        e.preventDefault();
        $.post(
            unzerAjaxUrl,
            {
                'unzerAction': 'createRessourcesAndInit',
                'selectedUnzerPaymentMethod': selectedUnzerPaymentMethod
            },
            function(data)
            {
                if (!data.token) {
                    if ($("#notifications .notifications-container").length > 0) {
                        $("#notifications .notifications-container").html(
                            '<div class="alert alert-danger">' + unzer_paypage_generic_error + '</div>'
                        );
                    }
                    return false;
                }
                var successURL = data.successURL;
                var unzerPubKey = data.pubKey;
                var unzerPayPageId = data.token;
                var unzerClickToPay = data.ctp ? '' : 'disableCTP';

                if (document.getElementById("unzer-container") === null) {
                    $("#checkout-payment-step").append('<div id="unzer-container"></div>');
                }

                const unzerContainer = document.getElementById("unzer-container");
                unzerContainer.innerHTML = `
                    <unzer-payment publicKey="${unzerPubKey}">
                        <unzer-pay-page
                            id="unzer-checkout"
                            payPageId="${unzerPayPageId}"
                            ${unzerClickToPay}
                        ></unzer-pay-page>
                    </unzer-payment>
                `;

                const checkout = document.getElementById("unzer-checkout");

                // Subscribe to the abort event
                checkout.abort(function () {
                    if ($("#notifications .notifications-container").length > 0) {
                        $("#notifications .notifications-container").html(
                            '<div class="alert alert-danger">' + unzer_transaction_canceled_by_user + '</div>'
                        );
                    }
                    if ($("#payment-confirmation .btn-primary").length > 0) {
                        $("#payment-confirmation .btn-primary").attr("disabled", false).removeClass('disabled');
                    }
                });

                // Subscribe to the success event
                checkout.success(function (data) {
                    window.location.href = successURL;
                });

                // Subscribe to the error event
                checkout.error(function (error) {
                    console.log(error);
                    alert(error);
                    return;
                    window.location.href = unzerErrorUrl;
                });

                console.log('opening layer');
                // Render the Embedded Payment Page overlay
                checkout.open();

            },
            'json'
        )
        return false;
    });

});
