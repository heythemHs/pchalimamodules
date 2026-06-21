{**
 * Front — start / landing page.
 * Variables: pcwdl_guest_url, pcwdl_noref_url, pcwdl_is_logged, pcwdl_errors (array)
 *}
<section class="pcwithdrawal-wrapper" aria-labelledby="pcwdl-start-heading">

    <h1 id="pcwdl-start-heading" class="pcwithdrawal-page-title">
        {l s='Exercise Your Right of Withdrawal' mod='pcwithdrawal'}
    </h1>

    <p class="pcwithdrawal-intro">
        {l s='Under EU consumer law, you have 14 days to withdraw from a distance or off-premises purchase without giving any reason. Please choose how you would like to proceed:' mod='pcwithdrawal'}
    </p>

    {if isset($pcwdl_errors) && $pcwdl_errors|@count > 0}
        <div class="alert alert-danger pcwithdrawal-errors" role="alert">
            <ul class="pcwithdrawal-error-list">
                {foreach $pcwdl_errors as $err}
                    <li>{$err|escape:'html':'UTF-8'}</li>
                {/foreach}
            </ul>
        </div>
    {/if}

    <div class="pcwithdrawal-option-cards" role="group" aria-label="{l s='Identification options' mod='pcwithdrawal'}">

        <a href="{$pcwdl_guest_url|escape:'html':'UTF-8'}"
           class="pcwithdrawal-option-card"
           aria-describedby="pcwdl-guest-desc">
            <div class="pcwithdrawal-option-card__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="5" width="18" height="14" rx="2"/>
                    <polyline points="3 7 12 13 21 7"/>
                </svg>
            </div>
            <h2 class="pcwithdrawal-option-card__title">
                {l s='I have my order reference' mod='pcwithdrawal'}
            </h2>
            <p id="pcwdl-guest-desc" class="pcwithdrawal-option-card__desc">
                {l s='I know the reference number on my order confirmation email.' mod='pcwithdrawal'}
            </p>
            <span class="pcwithdrawal-option-card__cta" aria-hidden="true">
                {l s='Continue' mod='pcwithdrawal'} &rarr;
            </span>
        </a>

        <a href="{$pcwdl_noref_url|escape:'html':'UTF-8'}"
           class="pcwithdrawal-option-card"
           aria-describedby="pcwdl-noref-desc">
            <div class="pcwithdrawal-option-card__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            </div>
            <h2 class="pcwithdrawal-option-card__title">
                {l s='I do not have my order reference' mod='pcwithdrawal'}
            </h2>
            <p id="pcwdl-noref-desc" class="pcwithdrawal-option-card__desc">
                {l s='I cannot find my order reference. I will provide my personal details and describe my purchase.' mod='pcwithdrawal'}
            </p>
            <span class="pcwithdrawal-option-card__cta" aria-hidden="true">
                {l s='Continue' mod='pcwithdrawal'} &rarr;
            </span>
        </a>

    </div>

    <p class="pcwithdrawal-legal-note">
        {l s='Submission of this form does not constitute cancellation of any order, nor does it entitle you to a refund before your request has been reviewed.' mod='pcwithdrawal'}
    </p>

</section>
