{*
 * Template — module settings page.
 * Tabbed layout: General | Notifications | Delivery | Security | Retention
 *}

<div class="panel pcw-settings-panel">
  <div class="panel-heading">
    <i class="icon-cogs"></i> {l s='EU Withdrawal Function — Settings' mod='pcwithdrawal'}
  </div>

  <ul class="nav nav-tabs pcw-settings-tabs" id="pcwSettingsTabs">
    <li class="active"><a href="#tab-general" data-toggle="tab"><i class="icon-home"></i> {l s='General' mod='pcwithdrawal'}</a></li>
    <li><a href="#tab-notifications" data-toggle="tab"><i class="icon-envelope"></i> {l s='Notifications' mod='pcwithdrawal'}</a></li>
    <li><a href="#tab-delivery" data-toggle="tab"><i class="icon-truck"></i> {l s='Delivery' mod='pcwithdrawal'}</a></li>
    <li><a href="#tab-security" data-toggle="tab"><i class="icon-lock"></i> {l s='Security' mod='pcwithdrawal'}</a></li>
    <li><a href="#tab-retention" data-toggle="tab"><i class="icon-database"></i> {l s='Retention' mod='pcwithdrawal'}</a></li>
  </ul>

  <form method="post" action="{$pcw_action_url|escape:'html':'UTF-8'}" id="pcw-settings-form">
    <input type="hidden" name="token" value="{Tools::getAdminTokenLite('AdminPcWithdrawalSettings')|escape:'html':'UTF-8'}">
    <input type="hidden" name="pcw_action" value="saveSettings">

    <div class="tab-content pcw-tab-content">

      {* ============================================================
         TAB — General
      ============================================================ *}
      <div class="tab-pane active" id="tab-general">
        <fieldset>
          <legend>{l s='Display &amp; Features' mod='pcwithdrawal'}</legend>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Module Enabled' mod='pcwithdrawal'}</label>
            <div class="col-md-8">
              <span class="pcw-switch">
                <input type="hidden" name="PCWITHDRAWAL_ENABLED" value="0">
                <input type="checkbox" name="PCWITHDRAWAL_ENABLED" value="1" id="cfg_enabled"
                  {if $pcw_enabled}checked="checked"{/if}>
                <label for="cfg_enabled"></label>
              </span>
              <p class="help-block">{l s='Globally enable or disable the module without uninstalling.' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Footer Link' mod='pcwithdrawal'}</label>
            <div class="col-md-8">
              <input type="hidden" name="PCWITHDRAWAL_FOOTER_LINK" value="0">
              <input type="checkbox" name="PCWITHDRAWAL_FOOTER_LINK" value="1"
                {if $pcw_footer_link}checked="checked"{/if}>
              <p class="help-block">{l s='Show withdrawal link in shop footer.' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Customer Account Link' mod='pcwithdrawal'}</label>
            <div class="col-md-8">
              <input type="hidden" name="PCWITHDRAWAL_ACCOUNT_LINK" value="0">
              <input type="checkbox" name="PCWITHDRAWAL_ACCOUNT_LINK" value="1"
                {if $pcw_account_link}checked="checked"{/if}>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Order Detail Button' mod='pcwithdrawal'}</label>
            <div class="col-md-8">
              <input type="hidden" name="PCWITHDRAWAL_ORDER_BUTTON" value="0">
              <input type="checkbox" name="PCWITHDRAWAL_ORDER_BUTTON" value="1"
                {if $pcw_order_button}checked="checked"{/if}>
              <p class="help-block">{l s='Show withdrawal button on the customer order detail page.' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Withdrawal Period (days)' mod='pcwithdrawal'} <span class="text-danger">*</span></label>
            <div class="col-md-4">
              <input type="number" name="PCWITHDRAWAL_WITHDRAWAL_PERIOD" class="form-control"
                value="{$pcw_withdrawal_period|intval}" min="1" max="365" required="required">
              <p class="help-block">{l s='Legal default: 14 days. Max: 365.' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Timezone' mod='pcwithdrawal'} <span class="text-danger">*</span></label>
            <div class="col-md-4">
              <select name="PCWITHDRAWAL_TIMEZONE" class="form-control">
                {foreach from=$pcw_timezones item=tz}
                <option value="{$tz|escape:'html':'UTF-8'}" {if $pcw_timezone == $tz}selected="selected"{/if}>
                  {$tz|escape:'html':'UTF-8'}
                </option>
                {/foreach}
              </select>
              <p class="help-block">{l s='Used for displaying submission timestamps.' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Date Format' mod='pcwithdrawal'}</label>
            <div class="col-md-4">
              <input type="text" name="PCWITHDRAWAL_DATE_FORMAT" class="form-control"
                value="{$pcw_date_format|escape:'html':'UTF-8'}">
              <p class="help-block">{l s='PHP date() format string, e.g. Y-m-d H:i' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Require Guest Verification' mod='pcwithdrawal'}</label>
            <div class="col-md-8">
              <input type="hidden" name="PCWITHDRAWAL_REQUIRE_GUEST_VERIFY" value="0">
              <input type="checkbox" name="PCWITHDRAWAL_REQUIRE_GUEST_VERIFY" value="1"
                {if $pcw_require_guest_verify}checked="checked"{/if}>
              <p class="help-block">{l s='Guests must verify via email link before submitting.' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Allow No Order Reference' mod='pcwithdrawal'}</label>
            <div class="col-md-8">
              <input type="hidden" name="PCWITHDRAWAL_ALLOW_NO_REF" value="0">
              <input type="checkbox" name="PCWITHDRAWAL_ALLOW_NO_REF" value="1"
                {if $pcw_allow_no_ref}checked="checked"{/if}>
              <p class="help-block">{l s='Allow submissions without an order reference (requires manual matching).' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Allow Partial Withdrawal' mod='pcwithdrawal'}</label>
            <div class="col-md-8">
              <input type="hidden" name="PCWITHDRAWAL_PARTIAL" value="0">
              <input type="checkbox" name="PCWITHDRAWAL_PARTIAL" value="1"
                {if $pcw_partial}checked="checked"{/if}>
              <p class="help-block">{l s='Allow customers to select specific items rather than the entire order.' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Send Customer Emails' mod='pcwithdrawal'}</label>
            <div class="col-md-8">
              <input type="hidden" name="PCWITHDRAWAL_CUSTOMER_EMAILS" value="0">
              <input type="checkbox" name="PCWITHDRAWAL_CUSTOMER_EMAILS" value="1"
                {if $pcw_customer_emails}checked="checked"{/if}>
              <p class="help-block">{l s='Enable all customer-facing transactional emails.' mod='pcwithdrawal'}</p>
            </div>
          </div>
        </fieldset>
      </div>

      {* ============================================================
         TAB — Notifications
      ============================================================ *}
      <div class="tab-pane" id="tab-notifications">
        <fieldset>
          <legend>{l s='Admin Notifications' mod='pcwithdrawal'}</legend>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Send Admin Notification' mod='pcwithdrawal'}</label>
            <div class="col-md-8">
              <input type="hidden" name="PCWITHDRAWAL_ADMIN_NOTIFY" value="0">
              <input type="checkbox" name="PCWITHDRAWAL_ADMIN_NOTIFY" value="1"
                {if $pcw_admin_notify}checked="checked"{/if}>
              <p class="help-block">{l s='Notify admin recipients when a new request is submitted.' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Admin Recipients' mod='pcwithdrawal'}</label>
            <div class="col-md-6">
              <textarea name="PCWITHDRAWAL_ADMIN_RECIPIENTS" class="form-control" rows="3"
                placeholder="admin@example.com&#10;team@example.com">{$pcw_admin_recipients|escape:'html':'UTF-8'}</textarea>
              <p class="help-block">{l s='One email per line or comma-separated. Leave blank to use the shop email.' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Sender Email' mod='pcwithdrawal'}</label>
            <div class="col-md-4">
              <input type="email" name="PCWITHDRAWAL_SENDER_EMAIL" class="form-control"
                value="{$pcw_sender_email|escape:'html':'UTF-8'}" placeholder="noreply@example.com">
              <p class="help-block">{l s='From address for all module emails. Leave blank to use shop email.' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Sender Name' mod='pcwithdrawal'}</label>
            <div class="col-md-4">
              <input type="text" name="PCWITHDRAWAL_SENDER_NAME" class="form-control"
                value="{$pcw_sender_name|escape:'html':'UTF-8'}">
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Reply-To Email' mod='pcwithdrawal'}</label>
            <div class="col-md-4">
              <input type="email" name="PCWITHDRAWAL_REPLY_TO" class="form-control"
                value="{$pcw_reply_to|escape:'html':'UTF-8'}">
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">&nbsp;</label>
            <div class="col-md-8">
              <button type="submit" form="pcw-test-mail-form" class="btn btn-default">
                <i class="icon-envelope"></i> {l s='Send Test Email' mod='pcwithdrawal'}
              </button>
            </div>
          </div>
        </fieldset>
      </div>

      {* ============================================================
         TAB — Delivery & Deadline
      ============================================================ *}
      <div class="tab-pane" id="tab-delivery">
        <fieldset>
          <legend>{l s='Delivery Detection' mod='pcwithdrawal'}</legend>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Delivered Order States' mod='pcwithdrawal'}</label>
            <div class="col-md-8">
              <select name="PCWITHDRAWAL_DELIVERED_STATES[]" class="form-control" multiple="multiple" size="8">
                {foreach from=$pcw_order_states item=os}
                <option value="{$os.id_order_state|intval}"
                  {if in_array($os.id_order_state, $pcw_delivered_states)}selected="selected"{/if}>
                  {$os.name|escape:'html':'UTF-8'}
                </option>
                {/foreach}
              </select>
              <p class="help-block">{l s='Hold Ctrl/Cmd to select multiple states that indicate delivery.' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Use Order State History' mod='pcwithdrawal'}</label>
            <div class="col-md-8">
              <input type="hidden" name="PCWITHDRAWAL_USE_STATE_HISTORY" value="0">
              <input type="checkbox" name="PCWITHDRAWAL_USE_STATE_HISTORY" value="1"
                {if $pcw_use_state_history}checked="checked"{/if}>
              <p class="help-block">{l s='Search order history for delivered state transitions to determine delivery date.' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Show Eligibility to Customer' mod='pcwithdrawal'}</label>
            <div class="col-md-8">
              <input type="hidden" name="PCWITHDRAWAL_SHOW_ELIGIBILITY" value="0">
              <input type="checkbox" name="PCWITHDRAWAL_SHOW_ELIGIBILITY" value="1"
                {if $pcw_show_eligibility}checked="checked"{/if}>
              <p class="help-block">{l s='Display eligibility assessment on the success/status page.' mod='pcwithdrawal'}</p>
            </div>
          </div>
        </fieldset>
      </div>

      {* ============================================================
         TAB — Security
      ============================================================ *}
      <div class="tab-pane" id="tab-security">
        <fieldset>
          <legend>{l s='Rate Limiting &amp; Tokens' mod='pcwithdrawal'}</legend>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Token Lifetime (minutes)' mod='pcwithdrawal'}</label>
            <div class="col-md-3">
              <input type="number" name="PCWITHDRAWAL_TOKEN_LIFETIME" class="form-control"
                value="{$pcw_token_lifetime|intval}" min="5" max="1440">
              <p class="help-block">{l s='5–1440 minutes.' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Max Verify Attempts' mod='pcwithdrawal'}</label>
            <div class="col-md-3">
              <input type="number" name="PCWITHDRAWAL_MAX_VERIFY_ATTEMPTS" class="form-control"
                value="{$pcw_max_verify_attempts|intval}" min="1" max="20">
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Max Identifications per Hour' mod='pcwithdrawal'}</label>
            <div class="col-md-3">
              <input type="number" name="PCWITHDRAWAL_MAX_ID_PER_HOUR" class="form-control"
                value="{$pcw_max_id_per_hour|intval}" min="1">
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Max Submissions per Hour' mod='pcwithdrawal'}</label>
            <div class="col-md-3">
              <input type="number" name="PCWITHDRAWAL_MAX_SUBMIT_PER_HOUR" class="form-control"
                value="{$pcw_max_submit_per_hour|intval}" min="1">
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Rate Limit Window Retention (hours)' mod='pcwithdrawal'}</label>
            <div class="col-md-3">
              <input type="number" name="PCWITHDRAWAL_RATE_RETENTION" class="form-control"
                value="{$pcw_rate_retention|intval}" min="1">
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Honeypot Field' mod='pcwithdrawal'}</label>
            <div class="col-md-8">
              <input type="hidden" name="PCWITHDRAWAL_HONEYPOT" value="0">
              <input type="checkbox" name="PCWITHDRAWAL_HONEYPOT" value="1"
                {if $pcw_honeypot}checked="checked"{/if}>
              <p class="help-block">{l s='Include an invisible honeypot field to catch bots.' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Status Link Lifetime (days)' mod='pcwithdrawal'}</label>
            <div class="col-md-3">
              <input type="number" name="PCWITHDRAWAL_STATUS_LINK_LIFETIME" class="form-control"
                value="{$pcw_status_link_lifetime|intval}" min="1" max="365">
              <p class="help-block">{l s='How long public status links remain valid.' mod='pcwithdrawal'}</p>
            </div>
          </div>
        </fieldset>
      </div>

      {* ============================================================
         TAB — Data Retention
      ============================================================ *}
      <div class="tab-pane" id="tab-retention">
        <fieldset>
          <legend>{l s='Data Retention' mod='pcwithdrawal'}</legend>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Keep Data on Uninstall' mod='pcwithdrawal'}</label>
            <div class="col-md-8">
              <input type="hidden" name="PCWITHDRAWAL_KEEP_DATA" value="0">
              <input type="checkbox" name="PCWITHDRAWAL_KEEP_DATA" value="1"
                {if $pcw_keep_data}checked="checked"{/if}>
              <p class="help-block text-info">
                <i class="icon-info-circle"></i>
                {l s='Recommended: ON. When enabled, tables and data are preserved on module uninstall. Disable only if you intend to permanently remove all data.' mod='pcwithdrawal'}
              </p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Token Retention (minutes)' mod='pcwithdrawal'}</label>
            <div class="col-md-3">
              <input type="number" name="PCWITHDRAWAL_TOKEN_RETENTION" class="form-control"
                value="{$pcw_token_retention|intval}" min="1">
              <p class="help-block">{l s='How long expired verification tokens are kept.' mod='pcwithdrawal'}</p>
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Mail Log Retention (days)' mod='pcwithdrawal'}</label>
            <div class="col-md-3">
              <input type="number" name="PCWITHDRAWAL_MAIL_LOG_RETENTION" class="form-control"
                value="{$pcw_mail_log_retention|intval}" min="1">
            </div>
          </div>

          <div class="form-group row">
            <label class="col-md-4 control-label">{l s='Request Retention (days)' mod='pcwithdrawal'}</label>
            <div class="col-md-3">
              <input type="number" name="PCWITHDRAWAL_REQUEST_RETENTION" class="form-control"
                value="{$pcw_request_retention|intval}" min="0">
              <p class="help-block">{l s='Days to keep closed requests. 0 = keep forever.' mod='pcwithdrawal'}</p>
            </div>
          </div>
        </fieldset>

        {if $pcw_is_superadmin}
        <fieldset class="pcw-danger-zone">
          <legend><i class="icon-warning"></i> {l s='Danger Zone — Permanent Purge' mod='pcwithdrawal'}</legend>

          <div class="alert alert-danger">
            <strong>{l s='Warning:' mod='pcwithdrawal'}</strong>
            {l s='This action permanently deletes ALL withdrawal request data, events, mail logs, templates, and related tables. This CANNOT be undone.' mod='pcwithdrawal'}
          </div>

          <form method="post" action="{$pcw_action_url|escape:'html':'UTF-8'}" id="pcw-purge-form">
            <input type="hidden" name="token" value="{Tools::getAdminTokenLite('AdminPcWithdrawalSettings')|escape:'html':'UTF-8'}">
            <input type="hidden" name="pcw_action" value="purgePermanently">

            <div class="form-group row">
              <label class="col-md-4 control-label">
                {l s='Type' mod='pcwithdrawal'} <code>CONFIRM PURGE</code> {l s='to enable' mod='pcwithdrawal'}
              </label>
              <div class="col-md-4">
                <input type="text" name="purge_confirm_phrase" id="pcw-purge-phrase" class="form-control"
                  placeholder="CONFIRM PURGE" autocomplete="off">
              </div>
            </div>
            <button type="submit" class="btn btn-danger" id="pcw-purge-btn" disabled="disabled">
              <i class="icon-trash"></i> {l s='Permanently Delete All Data' mod='pcwithdrawal'}
            </button>
          </form>
        </fieldset>
        {/if}
      </div>{* /tab-retention *}

    </div>{* /tab-content *}

    <div class="panel-footer">
      <button type="submit" class="btn btn-success">
        <i class="icon-save"></i> {l s='Save Settings' mod='pcwithdrawal'}
      </button>
    </div>
  </form>
</div>

{* Separate form for test email (not inside main form) *}
<form method="post" action="{$pcw_action_url|escape:'html':'UTF-8'}" id="pcw-test-mail-form" style="display:none;">
  <input type="hidden" name="token" value="{Tools::getAdminTokenLite('AdminPcWithdrawalSettings')|escape:'html':'UTF-8'}">
  <input type="hidden" name="pcw_action" value="sendTestEmail">
</form>

<script>
(function() {
  // Purge button: enable only when phrase matches
  var phraseInput = document.getElementById('pcw-purge-phrase');
  var purgeBtn    = document.getElementById('pcw-purge-btn');
  if (phraseInput && purgeBtn) {
    phraseInput.addEventListener('input', function() {
      purgeBtn.disabled = (this.value !== 'CONFIRM PURGE');
    });
    purgeBtn.addEventListener('click', function(e) {
      if (!confirm('{l s='Are you absolutely sure? This will permanently delete ALL module data.' mod='pcwithdrawal' js=1}')) {
        e.preventDefault();
      }
    });
  }

  // Tab persistence via hash
  var hash = window.location.hash;
  if (hash) {
    var tab = document.querySelector('.pcw-settings-tabs a[href="' + hash + '"]');
    if (tab) { tab.click(); }
  }
  var tabs = document.querySelectorAll('.pcw-settings-tabs a');
  for (var i = 0; i < tabs.length; i++) {
    tabs[i].addEventListener('click', function() {
      history.replaceState(null, null, this.getAttribute('href'));
    });
  }
})();
</script>
