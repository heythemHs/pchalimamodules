{**
 * Front — guest identification with order reference.
 * Variables: pcwdl_csrf_token, pcwdl_action_url, pcwdl_errors (array), pcwdl_guest_submitted (bool)
 *}
<section class="pcwithdrawal-wrapper" aria-labelledby="pcwdl-guest-heading">

    <h1 id="pcwdl-guest-heading" class="pcwithdrawal-page-title">
        {l s='Identify Your Order' mod='pcwithdrawal'}
    </h1>

    {if isset($pcwdl_guest_submitted) && $pcwdl_guest_submitted}

        {* Generic success message — never reveal whether order was found *}
        <div class="alert alert-success pcwithdrawal-info-message" role="status">
            <p>
                {l s='If the supplied information matches an order, a verification message has been sent to that email address. Please check your inbox (and spam folder) and follow the link provided.' mod='pcwithdrawal'}
            </p>
        </div>

    {else}

        {if isset($pcwdl_errors) && $pcwdl_errors|@count > 0}
            <div class="alert alert-danger" role="alert">
                <ul class="pcwithdrawal-error-list">
                    {foreach $pcwdl_errors as $err}
                        <li>{$err|escape:'html':'UTF-8'}</li>
                    {/foreach}
                </ul>
            </div>
        {/if}

        <p class="pcwithdrawal-form-intro">
            {l s='Please enter the email address used to place your order and your order reference number. We will send a verification link to that email address.' mod='pcwithdrawal'}
        </p>

        <form method="post"
              action="{$pcwdl_action_url|escape:'html':'UTF-8'}"
              id="pcwdl-guest-form"
              novalidate
              autocomplete="off">

            <input type="hidden" name="pcwdl_csrf_token" value="{$pcwdl_csrf_token|escape:'html':'UTF-8'}">
            <input type="hidden" name="pcwdl_identify_submit" value="1">

            {* Honeypot *}
            <div class="pcwithdrawal-hp" aria-hidden="true" tabindex="-1">
                <label for="pcwdl_hp_guest">{l s='Leave this field empty' mod='pcwithdrawal'}</label>
                <input type="text" id="pcwdl_hp_guest" name="pcwdl_hp" value="" autocomplete="off" tabindex="-1">
            </div>

            <div class="form-group">
                <label for="pcwdl_email" class="pcwithdrawal-label required">
                    {l s='Email address' mod='pcwithdrawal'}
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="email"
                       id="pcwdl_email"
                       name="pcwdl_email"
                       class="form-control pcwithdrawal-input"
                       required
                       maxlength="254"
                       autocomplete="email"
                       aria-required="true"
                       placeholder="{l s='your@email.com' mod='pcwithdrawal'}">
                <small class="form-text text-muted">
                    {l s='Enter the email address you used when placing the order.' mod='pcwithdrawal'}
                </small>
            </div>

            <div class="form-group">
                <label for="pcwdl_order_reference" class="pcwithdrawal-label required">
                    {l s='Order reference' mod='pcwithdrawal'}
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="pcwdl_order_reference"
                       name="pcwdl_order_reference"
                       class="form-control pcwithdrawal-input"
                       required
                       maxlength="32"
                       autocomplete="off"
                       aria-required="true"
                       placeholder="{l s='e.g. XZPTF123' mod='pcwithdrawal'}">
                <small class="form-text text-muted">
                    {l s='Your order reference can be found in your order confirmation email (e.g. XZPTF123).' mod='pcwithdrawal'}
                </small>
            </div>

            <div class="pcwithdrawal-form-actions">
                <button type="submit" name="pcwdl_identify_submit" class="btn btn-primary pcwithdrawal-btn-submit">
                    {l s='Send verification link' mod='pcwithdrawal'}
                </button>
            </div>

        </form>

    {/if}

</section>
