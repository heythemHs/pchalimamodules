{**
 * Front — submission success / acknowledgement page.
 * Variables: pcwdl_request (array|null), pcwdl_public_ref (string),
 *            pcwdl_timezone (string), pcwdl_status_url (string), pcwdl_shop_name (string)
 *}
<section class="pcwithdrawal-wrapper pcwithdrawal-success" aria-labelledby="pcwdl-success-heading">

    <div class="pcwithdrawal-success-icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
    </div>

    <h1 id="pcwdl-success-heading" class="pcwithdrawal-page-title pcwithdrawal-success-title">
        {l s='Withdrawal Request Received' mod='pcwithdrawal'}
    </h1>

    <p class="pcwithdrawal-success-intro">
        {l s='Your withdrawal declaration has been recorded. Please keep your reference number for your records.' mod='pcwithdrawal'}
    </p>

    {if $pcwdl_public_ref}
        <div class="pcwithdrawal-success-ref" aria-label="{l s='Your reference number' mod='pcwithdrawal'}">
            <span class="pcwithdrawal-success-ref__label">
                {l s='Your reference number:' mod='pcwithdrawal'}
            </span>
            <strong class="pcwithdrawal-success-ref__value">{$pcwdl_public_ref}</strong>
        </div>
    {/if}

    {if $pcwdl_request}
        <dl class="pcwithdrawal-dl pcwithdrawal-success-details">
            <dt>{l s='Submitted on' mod='pcwithdrawal'}</dt>
            <dd>
                {$pcwdl_request.submitted_at_utc|escape:'html':'UTF-8'}
                <span class="pcwithdrawal-tz">({$pcwdl_timezone})</span>
            </dd>
            <dt>{l s='Status' mod='pcwithdrawal'}</dt>
            <dd>{l s='Received' mod='pcwithdrawal'}</dd>
        </dl>
    {/if}

    <div class="pcwithdrawal-acknowledgement-notice" role="note">
        <p>
            <strong>{l s='What happens next?' mod='pcwithdrawal'}</strong>
        </p>
        <ol>
            <li>{l s='You will receive an acknowledgement email shortly.' mod='pcwithdrawal'}</li>
            <li>{l s='Our team will review your request within 14 days.' mod='pcwithdrawal'}</li>
            <li>{l s='We will contact you with instructions regarding any return shipment and reimbursement.' mod='pcwithdrawal'}</li>
        </ol>
        <p>
            {l s='Please note: this submission does not automatically cancel your order or trigger a refund.' mod='pcwithdrawal'}
        </p>
    </div>

    {if $pcwdl_status_url}
        <p class="pcwithdrawal-status-link">
            <a href="{$pcwdl_status_url|escape:'html':'UTF-8'}">
                {l s='Track the status of your request' mod='pcwithdrawal'}
            </a>
        </p>
    {/if}

    <div class="pcwithdrawal-print-actions">
        <button type="button"
                class="btn btn-default pcwithdrawal-print-btn"
                id="pcwdl-print-btn"
                onclick="window.print()">
            {l s='Print this page' mod='pcwithdrawal'}
        </button>
    </div>

</section>
