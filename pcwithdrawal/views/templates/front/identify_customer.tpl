{**
 * Front — order selection for logged-in customers.
 * Variables: pcwdl_orders (array), pcwdl_csrf_token, pcwdl_action_url, pcwdl_errors (array)
 *}
<section class="pcwithdrawal-wrapper" aria-labelledby="pcwdl-customer-heading">

    <h1 id="pcwdl-customer-heading" class="pcwithdrawal-page-title">
        {l s='Select the order you wish to withdraw from' mod='pcwithdrawal'}
    </h1>

    {if isset($pcwdl_errors) && $pcwdl_errors|@count > 0}
        <div class="alert alert-danger" role="alert">
            <ul class="pcwithdrawal-error-list">
                {foreach $pcwdl_errors as $err}
                    <li>{$err|escape:'html':'UTF-8'}</li>
                {/foreach}
            </ul>
        </div>
    {/if}

    {if isset($pcwdl_orders) && $pcwdl_orders|@count > 0}
        <form method="post" action="{$pcwdl_action_url|escape:'html':'UTF-8'}" id="pcwdl-customer-form" novalidate>
            <input type="hidden" name="pcwdl_csrf_token" value="{$pcwdl_csrf_token|escape:'html':'UTF-8'}">
            <input type="hidden" name="pcwdl_identify_submit" value="1">

            {* Honeypot — visually hidden, must remain empty *}
            <div class="pcwithdrawal-hp" aria-hidden="true" tabindex="-1">
                <label for="pcwdl_hp_field">{l s='Leave this field empty' mod='pcwithdrawal'}</label>
                <input type="text" id="pcwdl_hp_field" name="pcwdl_hp" value="" autocomplete="off" tabindex="-1">
            </div>

            <fieldset>
                <legend class="sr-only">{l s='Your orders' mod='pcwithdrawal'}</legend>

                <div class="pcwithdrawal-order-list" role="list">
                    {foreach $pcwdl_orders as $order}
                        <label class="pcwithdrawal-order-card" role="listitem">
                            <input type="radio"
                                   name="pcwdl_id_order"
                                   value="{$order.id_order|intval}"
                                   required
                                   class="pcwithdrawal-order-card__radio">
                            <span class="pcwithdrawal-order-card__body">
                                <span class="pcwithdrawal-order-card__ref">
                                    {l s='Reference:' mod='pcwithdrawal'}
                                    <strong>{$order.reference|escape:'html':'UTF-8'}</strong>
                                </span>
                                <span class="pcwithdrawal-order-card__date">
                                    {l s='Date:' mod='pcwithdrawal'}
                                    {$order.date_add|escape:'html':'UTF-8'|truncate:10:''}
                                </span>
                                <span class="pcwithdrawal-order-card__total">
                                    {l s='Total:' mod='pcwithdrawal'}
                                    {$order.total_paid_tax_incl|string_format:"%.2f"}
                                </span>
                                {if isset($order.state_name)}
                                    <span class="pcwithdrawal-order-card__status">
                                        {l s='Status:' mod='pcwithdrawal'}
                                        {$order.state_name|escape:'html':'UTF-8'}
                                    </span>
                                {/if}
                            </span>
                        </label>
                    {/foreach}
                </div>

            </fieldset>

            <div class="pcwithdrawal-form-actions">
                <button type="submit" name="pcwdl_identify_submit" class="btn btn-primary pcwithdrawal-btn-submit">
                    {l s='Select this order' mod='pcwithdrawal'}
                </button>
            </div>

        </form>
    {else}
        <div class="alert alert-info" role="status">
            {l s='No orders were found for your account. If you placed an order as a guest, please use the "I have my order reference" option.' mod='pcwithdrawal'}
        </div>
    {/if}

</section>
