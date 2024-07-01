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

<p>
    {l s='Please transfer the amount of %s %s to the following account:' sprintf=[$unzer_amount, $unzer_currency] mod='unzerpayment'}
</p>

<p>
    {l s='Holder' mod='unzerpayment'}: {$unzer_account_holder} <br>
    {l s='IBAN' mod='unzerpayment'}: {$unzer_account_iban} <br>
    {l s='BIC' mod='unzerpayment'}: {$unzer_account_bic} <br>
    {l s='Please use only this identification number as the descriptor' mod='unzerpayment'}: {$unzer_account_descriptor}
</p>
