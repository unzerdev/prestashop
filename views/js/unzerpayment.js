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
                var checkout = new window.checkout(data.token, {locale: prestashop.language.locale});
                checkout.init().then(function() {
                    checkout.open();
                    checkout.abort(function() {
                        if ($("#notifications .notifications-container").length > 0) {
                            $("#notifications .notifications-container").html(
                                '<div class="alert alert-danger">' + unzer_transaction_canceled_by_user + '</div>'
                            );
                        }
                        if ($("#payment-confirmation .btn-primary").length > 0) {
                            $("#payment-confirmation .btn-primary").attr("disabled", false).removeClass('disabled');
                        }
                    });
                    checkout.success(function(data) {
                        window.location.href = successURL;
                    });
                    checkout.error(function(error) {
                        window.location.href = unzerErrorUrl;
                    });
                });
            },
            'json'
        )
        return false;
    });

});
