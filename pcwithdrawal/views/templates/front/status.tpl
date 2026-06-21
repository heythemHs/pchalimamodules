{**
 * Front — public status tracking page.
 * Variables: pcwdl_token_valid (bool), pcwdl_request (array|null), pcwdl_events (array),
 *            pcwdl_status_label (string), pcwdl_public_ref (string), pcwdl_shop_name (string)
 *}
<section class="pcwithdrawal-wrapper pcwithdrawal-status" aria-labelledby="pcwdl-status-heading">

    <h1 id="pcwdl-status-heading" class="pcwithdrawal-page-title">
        {l s='Withdrawal Request Status' mod='pcwithdrawal'}
    </h1>

    {if !$pcwdl_token_valid || !$pcwdl_request}

        <div class="alert alert-warning pcwithdrawal-not-found" role="status">
            <p>{l s='The reference was not found or the tracking link has expired. Please contact us if you need assistance.' mod='pcwithdrawal'}</p>
        </div>

    {else}

        <div class="pcwithdrawal-status-header">
            <div class="pcwithdrawal-status-ref">
                <span class="pcwithdrawal-status-ref__label">{l s='Reference:' mod='pcwithdrawal'}</span>
                <strong class="pcwithdrawal-status-ref__value">{$pcwdl_public_ref}</strong>
            </div>
            <div class="pcwithdrawal-status-badge pcwithdrawal-status-badge--{$pcwdl_request.status_code|escape:'html':'UTF-8'}">
                {$pcwdl_status_label|escape:'html':'UTF-8'}
            </div>
        </div>

        <dl class="pcwithdrawal-dl">
            <dt>{l s='Submitted on' mod='pcwithdrawal'}</dt>
            <dd>
                {$pcwdl_request.submitted_at_utc|escape:'html':'UTF-8'}
                {if $pcwdl_request.submitted_timezone}
                    <span class="pcwithdrawal-tz">({$pcwdl_request.submitted_timezone|escape:'html':'UTF-8'})</span>
                {/if}
            </dd>
            <dt>{l s='Last updated' mod='pcwithdrawal'}</dt>
            <dd>{$pcwdl_request.date_upd|escape:'html':'UTF-8'}</dd>
        </dl>

        {if $pcwdl_events|@count > 0}
            <div class="pcwithdrawal-timeline" aria-label="{l s='Request timeline' mod='pcwithdrawal'}">
                <h2 class="pcwithdrawal-review-section__title">{l s='Timeline' mod='pcwithdrawal'}</h2>
                <ol class="pcwithdrawal-timeline__list">
                    {foreach $pcwdl_events as $event}
                        <li class="pcwithdrawal-timeline__item">
                            <time class="pcwithdrawal-timeline__time" datetime="{$event.date_add|escape:'html':'UTF-8'}">
                                {$event.date_add|escape:'html':'UTF-8'|truncate:16:''}
                            </time>
                            <span class="pcwithdrawal-timeline__label">
                                {$event.event_label|default:$event.event_code|escape:'html':'UTF-8'}
                            </span>
                            {if $event.public_note}
                                <p class="pcwithdrawal-timeline__note">{$event.public_note|escape:'html':'UTF-8'}</p>
                            {/if}
                        </li>
                    {/foreach}
                </ol>
            </div>
        {/if}

        <div class="pcwithdrawal-contact-info" role="note">
            <p>
                {l s='If you have questions about your request, please contact us and quote your reference number.' mod='pcwithdrawal'}
                {if $pcwdl_shop_name}
                    {l s='Shop:' mod='pcwithdrawal'} {$pcwdl_shop_name|escape:'html':'UTF-8'}
                {/if}
            </p>
        </div>

    {/if}

</section>
