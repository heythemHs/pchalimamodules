{**
 * Front — token verification page.
 * Variables: pcwdl_auto_submit (bool), pcwdl_token (string), pcwdl_csrf_token,
 *            pcwdl_action_url, pcwdl_errors (array)
 *}
<section class="pcwithdrawal-wrapper" aria-labelledby="pcwdl-verify-heading">

    <h1 id="pcwdl-verify-heading" class="pcwithdrawal-page-title">
        {l s='Verify Your Identity' mod='pcwithdrawal'}
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

    {if $pcwdl_auto_submit}

        {* Auto-submit form when token is in the URL — PRG-safe *}
        <div class="pcwithdrawal-auto-verify" aria-live="polite">
            <p>{l s='Verifying your identity, please wait…' mod='pcwithdrawal'}</p>
        </div>

        <form method="post"
              action="{$pcwdl_action_url|escape:'html':'UTF-8'}"
              id="pcwdl-auto-verify-form"
              aria-hidden="true">
            <input type="hidden" name="pcwdl_csrf_token" value="{$pcwdl_csrf_token|escape:'html':'UTF-8'}">
            <input type="hidden" name="pcwdl_token" value="{$pcwdl_token|escape:'html':'UTF-8'}">
            <input type="hidden" name="pcwdl_verify_submit" value="1">
            <noscript>
                <button type="submit" class="btn btn-primary">
                    {l s='Click here to verify' mod='pcwithdrawal'}
                </button>
            </noscript>
        </form>

    {else}

        {* Manual token entry form *}
        <p class="pcwithdrawal-form-intro">
            {l s='A verification link was sent to the email address you provided. If you cannot click the link, please copy and paste the token from the email into the field below.' mod='pcwithdrawal'}
        </p>

        <form method="post"
              action="{$pcwdl_action_url|escape:'html':'UTF-8'}"
              id="pcwdl-verify-form"
              novalidate>

            <input type="hidden" name="pcwdl_csrf_token" value="{$pcwdl_csrf_token|escape:'html':'UTF-8'}">
            <input type="hidden" name="pcwdl_verify_submit" value="1">

            <div class="form-group">
                <label for="pcwdl_token_input" class="pcwithdrawal-label required">
                    {l s='Verification token' mod='pcwithdrawal'}
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="pcwdl_token_input"
                       name="pcwdl_token"
                       class="form-control pcwithdrawal-input pcwithdrawal-token-input"
                       required
                       maxlength="128"
                       autocomplete="off"
                       aria-required="true"
                       placeholder="{l s='Paste your verification token here' mod='pcwithdrawal'}">
                <small class="form-text text-muted">
                    {l s='The token is a long sequence of letters and numbers from your verification email. It is valid for a limited time.' mod='pcwithdrawal'}
                </small>
            </div>

            <div class="pcwithdrawal-form-actions">
                <button type="submit" name="pcwdl_verify_submit" class="btn btn-primary pcwithdrawal-btn-submit">
                    {l s='Verify token' mod='pcwithdrawal'}
                </button>
            </div>

        </form>

    {/if}

</section>
