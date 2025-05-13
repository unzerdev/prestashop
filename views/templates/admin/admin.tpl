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

{if isset($success_message)}
	<div class="alert alert-success">{$success_message|escape:'htmlall':'UTF-8'}</div>
{/if}
{if isset($error_message)}
	<div class="alert alert-danger">{$error_message|escape:'htmlall':'UTF-8'}</div>
{/if}

<ul class="nav nav-tabs" role="tablist" id="unzertabs">
	<li class="active"><a href="#unzer_configuration" role="tab" data-toggle="tab">{l s='Configuration' mod='unzerpayment'}</a></li>
	<li><a href="#unzer_advanced" role="tab" data-toggle="tab">{l s='Advanced Settings' mod='unzerpayment'}</a></li>
	{* <li><a href="#unzer_design" role="tab" data-toggle="tab">{l s='Design' mod='unzerpayment'}</a></li> *}
</ul>

<div class="tab-content">
	<div class="tab-pane active" id="unzer_configuration">{include file='./_configuration.tpl'}</div>
	<div class="tab-pane" id="unzer_advanced">{include file='./_advanced.tpl'}</div>
	{* div class="tab-pane" id="unzer_design">{include file='./_design.tpl'}</div> *}
</div>

