{**
 * Front — guest form without order reference.
 * Variables: pcwdl_csrf_token, pcwdl_action_url, pcwdl_errors (array), pcwdl_form_data (array)
 *}
<section class="pcwithdrawal-wrapper" aria-labelledby="pcwdl-noref-heading">

    <h1 id="pcwdl-noref-heading" class="pcwithdrawal-page-title">
        {l s='Submit a Withdrawal Request Without Order Reference' mod='pcwithdrawal'}
    </h1>

    <div class="alert alert-info pcwithdrawal-info-message" role="note">
        <p>
            {l s='Because we cannot verify your order automatically, your request will be reviewed by our team. We will contact you within 14 days.' mod='pcwithdrawal'}
        </p>
    </div>

    {if isset($pcwdl_errors) && $pcwdl_errors|@count > 0}
        <div class="alert alert-danger" role="alert">
            <ul class="pcwithdrawal-error-list">
                {foreach $pcwdl_errors as $err}
                    <li>{$err|escape:'html':'UTF-8'}</li>
                {/foreach}
            </ul>
        </div>
    {/if}

    {assign var='fd' value=$pcwdl_form_data|default:array()}

    <form method="post"
          action="{$pcwdl_action_url|escape:'html':'UTF-8'}"
          id="pcwdl-noref-form"
          novalidate>

        <input type="hidden" name="pcwdl_csrf_token" value="{$pcwdl_csrf_token|escape:'html':'UTF-8'}">
        <input type="hidden" name="pcwdl_identify_submit" value="1">

        {* Honeypot *}
        <div class="pcwithdrawal-hp" aria-hidden="true" tabindex="-1">
            <label for="pcwdl_hp_noref">{l s='Leave this field empty' mod='pcwithdrawal'}</label>
            <input type="text" id="pcwdl_hp_noref" name="pcwdl_hp" value="" autocomplete="off" tabindex="-1">
        </div>

        <fieldset class="pcwithdrawal-fieldset">
            <legend class="pcwithdrawal-fieldset__legend">{l s='Your identity' mod='pcwithdrawal'}</legend>

            <div class="form-group">
                <label for="pcwdl_firstname" class="pcwithdrawal-label required">
                    {l s='First name' mod='pcwithdrawal'}
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="pcwdl_firstname"
                       name="pcwdl_firstname"
                       class="form-control pcwithdrawal-input"
                       required
                       maxlength="100"
                       autocomplete="given-name"
                       aria-required="true"
                       value="{isset($fd.firstname) ? $fd.firstname|escape:'html':'UTF-8' : ''}">
            </div>

            <div class="form-group">
                <label for="pcwdl_lastname" class="pcwithdrawal-label required">
                    {l s='Last name' mod='pcwithdrawal'}
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="pcwdl_lastname"
                       name="pcwdl_lastname"
                       class="form-control pcwithdrawal-input"
                       required
                       maxlength="100"
                       autocomplete="family-name"
                       aria-required="true"
                       value="{isset($fd.lastname) ? $fd.lastname|escape:'html':'UTF-8' : ''}">
            </div>

            <div class="form-group">
                <label for="pcwdl_email_noref" class="pcwithdrawal-label required">
                    {l s='Email address' mod='pcwithdrawal'}
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="email"
                       id="pcwdl_email_noref"
                       name="pcwdl_email"
                       class="form-control pcwithdrawal-input"
                       required
                       maxlength="254"
                       autocomplete="email"
                       aria-required="true"
                       value="{isset($fd.email) ? $fd.email|escape:'html':'UTF-8' : ''}">
            </div>

        </fieldset>

        <fieldset class="pcwithdrawal-fieldset">
            <legend class="pcwithdrawal-fieldset__legend">{l s='Purchase details' mod='pcwithdrawal'}</legend>

            <div class="form-group">
                <label for="pcwdl_purchase_date" class="pcwithdrawal-label">
                    {l s='Approximate date of purchase' mod='pcwithdrawal'}
                    <span class="pcwithdrawal-optional">({l s='optional' mod='pcwithdrawal'})</span>
                </label>
                <input type="date"
                       id="pcwdl_purchase_date"
                       name="pcwdl_purchase_date"
                       class="form-control pcwithdrawal-input"
                       maxlength="10"
                       value="{isset($fd.purchase_date_declared) ? $fd.purchase_date_declared|escape:'html':'UTF-8' : ''}">
                <small class="form-text text-muted">
                    {l s='This helps us locate your order. Leave blank if unknown.' mod='pcwithdrawal'}
                </small>
            </div>

            <div class="form-group">
                <label for="pcwdl_description" class="pcwithdrawal-label required">
                    {l s='Description of the goods or services you wish to withdraw from' mod='pcwithdrawal'}
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <textarea id="pcwdl_description"
                          name="pcwdl_description"
                          class="form-control pcwithdrawal-textarea"
                          required
                          rows="4"
                          maxlength="2000"
                          aria-required="true">{isset($fd.description) ? $fd.description|escape:'html':'UTF-8' : ''}</textarea>
                <small class="form-text text-muted">
                    {l s='Please describe what you purchased (product names, quantities, colours, etc.). Maximum 2000 characters.' mod='pcwithdrawal'}
                </small>
            </div>

            <div class="form-group">
                <label for="pcwdl_approximate_amount" class="pcwithdrawal-label">
                    {l s='Approximate amount paid' mod='pcwithdrawal'}
                    <span class="pcwithdrawal-optional">({l s='optional' mod='pcwithdrawal'})</span>
                </label>
                <input type="text"
                       id="pcwdl_approximate_amount"
                       name="pcwdl_approximate_amount"
                       class="form-control pcwithdrawal-input"
                       maxlength="20"
                       value="{isset($fd.approximate_amount) ? $fd.approximate_amount|escape:'html':'UTF-8' : ''}">
            </div>

            <div class="form-group">
                <label for="pcwdl_contract_info" class="pcwithdrawal-label">
                    {l s='Contract or subscription information' mod='pcwithdrawal'}
                    <span class="pcwithdrawal-optional">({l s='optional' mod='pcwithdrawal'})</span>
                </label>
                <input type="text"
                       id="pcwdl_contract_info"
                       name="pcwdl_contract_info"
                       class="form-control pcwithdrawal-input"
                       maxlength="200"
                       value="{isset($fd.contract_info) ? $fd.contract_info|escape:'html':'UTF-8' : ''}">
                <small class="form-text text-muted">
                    {l s='If your purchase related to a service contract or subscription, please describe it here.' mod='pcwithdrawal'}
                </small>
            </div>

        </fieldset>

        <fieldset class="pcwithdrawal-fieldset">
            <legend class="pcwithdrawal-fieldset__legend">{l s='Your statement' mod='pcwithdrawal'}</legend>

            <div class="form-group">
                <label for="pcwdl_customer_statement" class="pcwithdrawal-label">
                    {l s='Additional statement' mod='pcwithdrawal'}
                    <span class="pcwithdrawal-optional">({l s='optional' mod='pcwithdrawal'})</span>
                </label>
                <textarea id="pcwdl_customer_statement"
                          name="pcwdl_customer_statement"
                          class="form-control pcwithdrawal-textarea"
                          rows="3"
                          maxlength="5000">{isset($fd.customer_statement) ? $fd.customer_statement|escape:'html':'UTF-8' : ''}</textarea>
                <small class="form-text text-muted">
                    {l s='Any additional information you wish to provide (optional, max 5000 characters).' mod='pcwithdrawal'}
                </small>
            </div>

        </fieldset>

        <div class="pcwithdrawal-disclaimer" role="note">
            <p>
                <strong>{l s='Important:' mod='pcwithdrawal'}</strong>
                {l s='Submitting this form is a declaration of your intent to withdraw. It does not automatically cancel your order, trigger a refund, or initiate a return shipment. Our team will contact you to confirm next steps.' mod='pcwithdrawal'}
            </p>
        </div>

        <div class="pcwithdrawal-form-actions">
            <button type="submit" name="pcwdl_identify_submit" class="btn btn-primary pcwithdrawal-btn-submit">
                {l s='Submit withdrawal request' mod='pcwithdrawal'}
            </button>
        </div>

    </form>

</section>
