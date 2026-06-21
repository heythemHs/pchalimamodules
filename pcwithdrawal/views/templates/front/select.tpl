{**
 * Front — product / scope selection.
 * Variables: pcwdl_order (array), pcwdl_order_details (array), pcwdl_csrf_token,
 *            pcwdl_action_url, pcwdl_errors (array), pcwdl_partial_allowed (bool)
 *}
<section class="pcwithdrawal-wrapper" aria-labelledby="pcwdl-select-heading">

    <h1 id="pcwdl-select-heading" class="pcwithdrawal-page-title">
        {l s='What would you like to withdraw from?' mod='pcwithdrawal'}
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

    <div class="pcwithdrawal-order-summary">
        <p>
            <strong>{l s='Order reference:' mod='pcwithdrawal'}</strong>
            {$pcwdl_order.reference|escape:'html':'UTF-8'}
        </p>
        <p>
            <strong>{l s='Order date:' mod='pcwithdrawal'}</strong>
            {$pcwdl_order.date_add|escape:'html':'UTF-8'|truncate:10:''}
        </p>
    </div>

    <form method="post"
          action="{$pcwdl_action_url|escape:'html':'UTF-8'}"
          id="pcwdl-select-form"
          novalidate>

        <input type="hidden" name="pcwdl_csrf_token" value="{$pcwdl_csrf_token|escape:'html':'UTF-8'}">

        {if $pcwdl_partial_allowed}

            <fieldset class="pcwithdrawal-fieldset pcwithdrawal-scope-toggle" id="pcwdl-scope-fieldset">
                <legend class="pcwithdrawal-fieldset__legend">{l s='Withdrawal scope' mod='pcwithdrawal'}</legend>

                <div class="pcwithdrawal-scope-option">
                    <label class="pcwithdrawal-radio-label">
                        <input type="radio"
                               name="pcwdl_scope"
                               value="full"
                               id="pcwdl_scope_full"
                               checked
                               class="pcwithdrawal-scope-radio">
                        <span>{l s='Full order — withdraw from all items' mod='pcwithdrawal'}</span>
                    </label>
                </div>

                <div class="pcwithdrawal-scope-option">
                    <label class="pcwithdrawal-radio-label">
                        <input type="radio"
                               name="pcwdl_scope"
                               value="partial"
                               id="pcwdl_scope_partial"
                               class="pcwithdrawal-scope-radio">
                        <span>{l s='Partial order — select specific items' mod='pcwithdrawal'}</span>
                    </label>
                </div>

            </fieldset>

        {else}
            <input type="hidden" name="pcwdl_scope" value="full">
        {/if}

        {* Items block — shown only for partial scope *}
        <div id="pcwithdrawal-items-block" class="pcwithdrawal-items-block"
             {if !$pcwdl_partial_allowed}style="display:none"{/if}>

            <div class="pcwithdrawal-items-header">
                <label class="pcwithdrawal-select-all-label">
                    <input type="checkbox" id="pcwdl-select-all" class="pcwithdrawal-select-all-checkbox">
                    <span>{l s='Select all items' mod='pcwithdrawal'}</span>
                </label>
            </div>

            <div id="pcwdl-qty-error" class="alert alert-danger" style="display:none" role="alert">
                {l s='Please select at least one item for a partial withdrawal.' mod='pcwithdrawal'}
            </div>

            <table class="table pcwithdrawal-items-table" aria-label="{l s='Order items' mod='pcwithdrawal'}">
                <thead>
                    <tr>
                        <th scope="col">{l s='Select' mod='pcwithdrawal'}</th>
                        <th scope="col">{l s='Product' mod='pcwithdrawal'}</th>
                        <th scope="col">{l s='Reference' mod='pcwithdrawal'}</th>
                        <th scope="col">{l s='Ordered' mod='pcwithdrawal'}</th>
                        <th scope="col">{l s='Qty to withdraw' mod='pcwithdrawal'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $pcwdl_order_details as $detail}
                        {assign var='det_id' value=$detail.id_order_detail|intval}
                        {assign var='max_qty' value=$detail.product_quantity|intval}
                        <tr class="pcwithdrawal-item-row">
                            <td class="pcwithdrawal-item-check">
                                <input type="checkbox"
                                       class="pcwithdrawal-item-checkbox"
                                       data-qty-input="pcwdl_item_{$det_id}"
                                       id="pcwdl_check_{$det_id}"
                                       aria-label="{$detail.product_name|escape:'html':'UTF-8'}">
                            </td>
                            <td>
                                <label for="pcwdl_check_{$det_id}">
                                    {$detail.product_name|escape:'html':'UTF-8'}
                                    {if $detail.product_attribute_combination}
                                        <br><small class="text-muted">{$detail.product_attribute_combination|escape:'html':'UTF-8'}</small>
                                    {/if}
                                </label>
                            </td>
                            <td>{$detail.product_reference|escape:'html':'UTF-8'}</td>
                            <td>{$max_qty}</td>
                            <td>
                                <input type="number"
                                       id="pcwdl_item_{$det_id}"
                                       name="pcwdl_items[{$det_id}]"
                                       class="form-control pcwithdrawal-qty-input"
                                       min="0"
                                       max="{$max_qty}"
                                       value="0"
                                       aria-label="{l s='Quantity for' mod='pcwithdrawal'} {$detail.product_name|escape:'html':'UTF-8'}"
                                       data-max="{$max_qty}">
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>

        </div>

        <div class="form-group pcwithdrawal-statement-group">
            <label for="pcwdl_customer_statement" class="pcwithdrawal-label">
                {l s='Additional statement' mod='pcwithdrawal'}
                <span class="pcwithdrawal-optional">({l s='optional' mod='pcwithdrawal'})</span>
            </label>
            <textarea id="pcwdl_customer_statement"
                      name="pcwdl_customer_statement"
                      class="form-control pcwithdrawal-textarea"
                      rows="3"
                      maxlength="5000"
                      placeholder="{l s='Any reason or comments you wish to add (optional).' mod='pcwithdrawal'}"></textarea>
        </div>

        <div class="pcwithdrawal-form-actions">
            <button type="submit" name="pcwdl_select_submit" class="btn btn-primary pcwithdrawal-btn-submit">
                {l s='Review my withdrawal' mod='pcwithdrawal'}
            </button>
        </div>

    </form>

</section>
