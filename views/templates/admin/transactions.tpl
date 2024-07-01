{**
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
*}

<div class="{if $isHigher176}card mt-2{else}panel{/if}">
    <div class="{if $isHigher176}card-header{else}panel-heading{/if}">
        <h3>{l s='Unzer Transactions' mod='unzerpayment'}</h3>
        {if $unzer_transactions}
            <p class="unzer-regular-text">
                Short ID: {$unzer_transactions.shortID},
                Cart ID: {$unzer_transactions.cartID}
            </p>
        {/if}
        <a name="unzer_transactions_block"></a>
    </div>

    <div class="{if $isHigher176}card-body{else}panel-body{/if}">
        <div id="unzerTransactionsListing">
            {if $unzer_transactions}

                <table class="table">
                    <tr>
                        <th>
                            {l s='Total amount' mod='unzerpayment'}
                        </th>
                        <td>
                            {$unzer_transactions.amount}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {l s='Charged amount' mod='unzerpayment'}
                        </th>
                        <td>
                            {$unzer_transactions.charged}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {l s='Cancelled amount' mod='unzerpayment'}
                        </th>
                        <td>
                            {$unzer_transactions.cancelled}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {l s='Remaining amount' mod='unzerpayment'}
                        </th>
                        <td>
                            {$unzer_transactions.remaining}
                        </td>
                    </tr>
                    {if $unzer_transactions.remainingPlain && $unzer_transactions.paymentBaseMethod != 'ppy'}
                    <tr>
                        <td colspan="2">
                            <form class="form-horizontal well" id="unzerpaymentTransactionsActions" method="post" action="{$unzer_form_action}" onsubmit="return confirm('{l s='Should the action really be executed?' mod='unzerpayment'}')">
                                <input type="number" step="0.01" min="0.01" max="{$unzer_transactions.remainingPlain}" value="{$unzer_transactions.remainingPlain}" name="unzer_capture_amount" id="unzer-capture-amount-input" />
                                <button class="btn btn-primary btn-sm" type="submit" name="unzer_action" value="unzer_capture">
                                    {l s='Capture amount' mod='unzerpayment'}
                                </button>
                            </form>
                        </td>
                    </tr>
                    {/if}
                </table>


                <table class="table">
                    <thead>
                    <tr>
                        <th>{l s='Time' mod='unzerpayment'}</th>
                        <th>{l s='Type' mod='unzerpayment'}</th>
                        <th>{l s='ID' mod='unzerpayment'}</th>
                        <th>{l s='Amount' mod='unzerpayment'}</th>
                        <th>{l s='Status' mod='unzerpayment'}</th>
                    </tr>
                    </thead>
                    {foreach from=$unzer_transactions.transactions item=unzer_transaction}
                        <tr>
                            <td>
                                {$unzer_transaction.time}
                            </td>
                            <td>
                                {$unzer_transaction.type}
                            </td>
                            <td>
                                {$unzer_transaction.id}
                            </td>
                            <td>
                                {$unzer_transaction.amount}
                            </td>
                            <td>
                                {$unzer_transaction.status}
                            </td>
                        </tr>
                    {/foreach}
                </table>
            {else}
                <p>
                    {l s='Currently no transactions available' mod='unzerpayment'}
                </p>
            {/if}
        </div>
    </div>

</div>
