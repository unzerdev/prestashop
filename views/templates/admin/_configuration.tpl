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

<div class="panel">
    {$authform}
</div>

<div class="panel">
    <div class="panel-heading">
        <h2>Webhooks</h2>
    </div>

    <div class="panel-body">
        <div id="webhooksListing">
            {if $webhooksList}
                <table class="table">
                    <thead>
                        <tr>
                            <th>{l s='ID' mod='unzerpayment'}</th>
                            <th>{l s='Event' mod='unzerpayment'}</th>
                            <th>{l s='URL' mod='unzerpayment'}</th>
                            <th>&nbsp;</th>
                        </tr>
                    </thead>
                    {foreach from=$webhooksList item=webhook}
                        <tr>
                            <td>
                                {$webhook->getId()}
                            </td>
                            <td>
                                {$webhook->getEvent()}
                            </td>
                            <td>
                                {$webhook->getUrl()}
                            </td>
                            <td>
                                <a href="{$webhookDelActionLink|replace:'UNZERWEBHOOKID':$webhook->getId()}" onclick="return confirm('{l s='Do you really want to delete this webhook?' mod='unzerpayment'}')">
                                    <i class="material-icons">delete</i>
                                </a>
                            </td>
                        </tr>
                    {/foreach}
                </table>
            {else}
                <p>
                    {l s='Currently no registered webhook available' mod='unzerpayment'}
                </p>
            {/if}
            {if isset($webhookCreateActionLink)}
                <p>
                    <a href="{$webhookCreateActionLink}" class="btn btn-primary" onclick="return confirm('{l s='Do you really want to register the webhook?' mod='unzerpayment'}')">
                        {l s='Register webhook' mod='unzerpayment'}
                    </a>
                </p>
            {/if}
        </div>
    </div>

</div>
