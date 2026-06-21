{**
 * Front — review and confirmation page.
 * Variables: pcwdl_review_data (array), pcwdl_items (array), pcwdl_csrf_token,
 *            pcwdl_idempotency_token, pcwdl_submit_url, pcwdl_select_url,
 *            pcwdl_action_label, pcwdl_scope_code
 *}
<section class="pcwithdrawal-wrapper pcwithdrawal-review" aria-labelledby="pcwdl-review-heading">

    <h1 id="pcwdl-review-heading" class="pcwithdrawal-page-title">
        {l s='Review Your Withdrawal Declaration' mod='pcwithdrawal'}
    </h1>

    <p class="pcwithdrawal-form-intro">
        {l s='Please review all information below before confirming. Once submitted, this declaration is permanent.' mod='pcwithdrawal'}
    </p>

    {if isset($pcwdl_submit_error)}
        <div class="alert alert-danger" role="alert">
            {$pcwdl_submit_error|escape:'html':'UTF-8'}
        </div>
    {/if}

    {* Consumer identity *}
    <div class="pcwithdrawal-review-section">
        <h2 class="pcwithdrawal-review-section__title">{l s='Consumer identity' mod='pcwithdrawal'}</h2>
        <dl class="pcwithdrawal-dl">
            <dt>{l s='Name' mod='pcwithdrawal'}</dt>
            <dd>
                {$pcwdl_review_data.consumer.firstname|escape:'html':'UTF-8'}
                {$pcwdl_review_data.consumer.lastname|escape:'html':'UTF-8'}
            </dd>
            {if $pcwdl_review_data.consumer.email}
                <dt>{l s='Email' mod='pcwithdrawal'}</dt>
                <dd>{$pcwdl_review_data.consumer.email|escape:'html':'UTF-8'}</dd>
            {/if}
        </dl>
    </div>

    {* Order / contract *}
    <div class="pcwithdrawal-review-section">
        <h2 class="pcwithdrawal-review-section__title">{l s='Order information' mod='pcwithdrawal'}</h2>
        <dl class="pcwithdrawal-dl">
            <dt>{l s='Order reference' mod='pcwithdrawal'}</dt>
            <dd>{$pcwdl_review_data.order.reference|escape:'html':'UTF-8'}</dd>
            <dt>{l s='Order date' mod='pcwithdrawal'}</dt>
            <dd>{$pcwdl_review_data.order.date_add|escape:'html':'UTF-8'|truncate:10:''}</dd>
            <dt>{l s='Withdrawal scope' mod='pcwithdrawal'}</dt>
            <dd>
                {if $pcwdl_scope_code eq 'full'}
                    {l s='Full order' mod='pcwithdrawal'}
                {else}
                    {l s='Partial order (selected items only)' mod='pcwithdrawal'}
                {/if}
            </dd>
        </dl>
    </div>

    {* Items table *}
    <div class="pcwithdrawal-review-section">
        <h2 class="pcwithdrawal-review-section__title">{l s='Items to withdraw' mod='pcwithdrawal'}</h2>
        <table class="table pcwithdrawal-items-table" aria-label="{l s='Items to withdraw' mod='pcwithdrawal'}">
            <thead>
                <tr>
                    <th scope="col">{l s='Product' mod='pcwithdrawal'}</th>
                    <th scope="col">{l s='Reference' mod='pcwithdrawal'}</th>
                    <th scope="col">{l s='Qty ordered' mod='pcwithdrawal'}</th>
                    <th scope="col">{l s='Qty withdrawn' mod='pcwithdrawal'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach $pcwdl_items as $item}
                    <tr>
                        <td>
                            {$item.product_name|escape:'html':'UTF-8'}
                            {if $item.attribute_name}
                                <br><small class="text-muted">{$item.attribute_name|escape:'html':'UTF-8'}</small>
                            {/if}
                        </td>
                        <td>{$item.product_reference|escape:'html':'UTF-8'}</td>
                        <td>{$item.quantity_ordered|intval}</td>
                        <td><strong>{$item.quantity_withdrawn|intval}</strong></td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>

    {* Customer statement *}
    {if $pcwdl_review_data.customer_statement}
        <div class="pcwithdrawal-review-section">
            <h2 class="pcwithdrawal-review-section__title">{l s='Your statement' mod='pcwithdrawal'}</h2>
            <blockquote class="pcwithdrawal-statement-quote">
                {$pcwdl_review_data.customer_statement|escape:'html':'UTF-8'|nl2br}
            </blockquote>
        </div>
    {/if}

    {* Disclaimer *}
    <div class="pcwithdrawal-disclaimer" role="note" aria-label="{l s='Important notice' mod='pcwithdrawal'}">
        <h2 class="pcwithdrawal-disclaimer__title">{l s='Important notice' mod='pcwithdrawal'}</h2>
        <p>
            {l s='By clicking the button below, you declare your intent to exercise your right of withdrawal under EU Directive 2011/83/EU.' mod='pcwithdrawal'}
        </p>
        <ul>
            <li>{l s='This submission does not automatically cancel your order.' mod='pcwithdrawal'}</li>
            <li>{l s='This submission does not automatically trigger a refund.' mod='pcwithdrawal'}</li>
            <li>{l s='No goods should be returned until you receive instructions from us.' mod='pcwithdrawal'}</li>
            <li>{l s='We will process your request within 14 days of receipt.' mod='pcwithdrawal'}</li>
        </ul>
    </div>

    {* Confirmation form *}
    <form method="post"
          action="{$pcwdl_submit_url|escape:'html':'UTF-8'}"
          id="pcwdl-confirm-form"
          data-dirty-check="true">

        <input type="hidden" name="pcwdl_csrf_token"        value="{$pcwdl_csrf_token|escape:'html':'UTF-8'}">
        <input type="hidden" name="pcwdl_idempotency_token" value="{$pcwdl_idempotency_token|escape:'html':'UTF-8'}">

        <div class="pcwithdrawal-form-actions pcwithdrawal-review-actions">
            <a href="{$pcwdl_select_url|escape:'html':'UTF-8'}"
               class="btn btn-default pcwithdrawal-btn-back"
               id="pcwdl-back-link">
                &larr; {l s='Go back and change' mod='pcwithdrawal'}
            </a>
            <button type="submit" class="btn btn-danger pcwithdrawal-btn-confirm" id="pcwdl-confirm-btn">
                {$pcwdl_action_label|escape:'html':'UTF-8'}
            </button>
        </div>

    </form>

</section>
