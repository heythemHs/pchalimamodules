{*
 * Template — withdrawal request detail view.
 * Immutable sections have grey background. Mutable sections are editable.
 *}

<div class="pcw-detail-wrap">

  <div class="row">
    <div class="col-md-12">
      <a href="{$pcw_list_url|escape:'html':'UTF-8'}" class="btn btn-default btn-sm">
        <i class="icon-arrow-left"></i> {l s='Back to list' mod='pcwithdrawal'}
      </a>
      &nbsp;
      <a href="{$pcw_action_url|escape:'html':'UTF-8'}&action=exportEvidence&id_pcwithdrawal_request={$pcw_request.id_pcwithdrawal_request|intval}&token={Tools::getAdminTokenLite('AdminPcWithdrawalRequests')|escape:'html':'UTF-8'}"
         class="btn btn-default btn-sm" target="_blank">
        <i class="icon-file-text"></i> {l s='Export Evidence' mod='pcwithdrawal'}
      </a>
    </div>
  </div>
  <br>

  {* ============================================================
     SECTION 1 — Immutable Declaration
  ============================================================ *}
  <div class="panel pcw-immutable-panel">
    <div class="panel-heading" data-toggle="collapse" data-target="#pcw-section-decl">
      <i class="icon-lock"></i>
      {l s='1. Withdrawal Declaration (Immutable)' mod='pcwithdrawal'}
      <span class="badge badge-info">{$pcw_request.public_reference|escape:'html':'UTF-8'}</span>
    </div>
    <div id="pcw-section-decl" class="panel-body collapse in pcw-immutable-body">
      <div class="row">
        <div class="col-md-6">
          <table class="table table-bordered pcw-info-table">
            <tr><th>{l s='Public Reference' mod='pcwithdrawal'}</th>
                <td><strong>{$pcw_request.public_reference|escape:'html':'UTF-8'}</strong></td></tr>
            <tr><th>{l s='Submitted At (UTC)' mod='pcwithdrawal'}</th>
                <td>{$pcw_request.submitted_at_utc|escape:'html':'UTF-8'}</td></tr>
            <tr><th>{l s='Local Time' mod='pcwithdrawal'}</th>
                <td>{$pcw_request.submitted_local_at|escape:'html':'UTF-8'} ({$pcw_request.submitted_timezone|escape:'html':'UTF-8'})</td></tr>
            <tr><th>{l s='Consumer' mod='pcwithdrawal'}</th>
                <td>{$pcw_request.consumer_firstname|escape:'html':'UTF-8'} {$pcw_request.consumer_lastname|escape:'html':'UTF-8'}</td></tr>
            <tr><th>{l s='Email' mod='pcwithdrawal'}</th>
                <td>{$pcw_request.consumer_email|escape:'html':'UTF-8'}</td></tr>
            <tr><th>{l s='Order Reference' mod='pcwithdrawal'}</th>
                <td>
                  {if $pcw_request.order_reference}
                    {$pcw_request.order_reference|escape:'html':'UTF-8'}
                  {else}
                    <em class="text-muted">{l s='Not provided' mod='pcwithdrawal'}</em>
                  {/if}
                </td></tr>
          </table>
        </div>
        <div class="col-md-6">
          <table class="table table-bordered pcw-info-table">
            <tr><th>{l s='Scope' mod='pcwithdrawal'}</th>
                <td>{$pcw_request.scope_code|escape:'html':'UTF-8'}</td></tr>
            <tr><th>{l s='Contract Type' mod='pcwithdrawal'}</th>
                <td>{$pcw_request.contract_type|escape:'html':'UTF-8'}</td></tr>
            <tr><th>{l s='Source' mod='pcwithdrawal'}</th>
                <td>{$pcw_request.source_code|escape:'html':'UTF-8'}</td></tr>
            <tr><th>{l s='Shop' mod='pcwithdrawal'}</th>
                <td>{$pcw_request.shop_name|escape:'html':'UTF-8'}</td></tr>
            <tr><th>{l s='Language ID' mod='pcwithdrawal'}</th>
                <td>{$pcw_request.id_lang|intval}</td></tr>
            <tr><th>{l s='Purchase Date (Declared)' mod='pcwithdrawal'}</th>
                <td>{$pcw_request.purchase_date_declared|escape:'html':'UTF-8'}</td></tr>
          </table>
        </div>
      </div>
      {if $pcw_request.customer_statement}
      <div class="row">
        <div class="col-md-12">
          <label><strong>{l s='Customer Statement' mod='pcwithdrawal'}</strong></label>
          <div class="pcw-statement-box">
            {$pcw_request.customer_statement|escape:'html':'UTF-8'|nl2br}
          </div>
        </div>
      </div>
      {/if}
    </div>
  </div>

  {* ============================================================
     SECTION 2 — Selected Items
  ============================================================ *}
  {if $pcw_items}
  <div class="panel">
    <div class="panel-heading" data-toggle="collapse" data-target="#pcw-section-items">
      <i class="icon-shopping-cart"></i>
      {l s='2. Selected Items' mod='pcwithdrawal'}
      <span class="badge">{$pcw_items|count}</span>
    </div>
    <div id="pcw-section-items" class="panel-body collapse in">
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>{l s='Product' mod='pcwithdrawal'}</th>
            <th>{l s='Reference' mod='pcwithdrawal'}</th>
            <th>{l s='Attribute' mod='pcwithdrawal'}</th>
            <th>{l s='Qty Ordered' mod='pcwithdrawal'}</th>
            <th>{l s='Qty Withdrawn' mod='pcwithdrawal'}</th>
            <th>{l s='Unit Price' mod='pcwithdrawal'}</th>
            <th>{l s='Total' mod='pcwithdrawal'}</th>
            <th>{l s='Currency' mod='pcwithdrawal'}</th>
            <th>{l s='Exception' mod='pcwithdrawal'}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$pcw_items item=item}
          <tr>
            <td>{$item.product_name|escape:'html':'UTF-8'}</td>
            <td>{$item.product_reference|escape:'html':'UTF-8'}</td>
            <td>{$item.attribute_name|escape:'html':'UTF-8'}</td>
            <td>{$item.quantity_ordered|intval}</td>
            <td>{$item.quantity_withdrawn|intval}</td>
            <td>{$item.unit_price_tax_incl|escape:'html':'UTF-8'}</td>
            <td>{$item.total_price_tax_incl|escape:'html':'UTF-8'}</td>
            <td>{$item.currency_iso|escape:'html':'UTF-8'}</td>
            <td>
              {if $item.exception_code}
                <span class="badge badge-warning">{$item.exception_code|escape:'html':'UTF-8'}</span>
              {else}&mdash;{/if}
            </td>
          </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  </div>
  {/if}

  {* ============================================================
     SECTION 3 — Deadline & Eligibility
  ============================================================ *}
  <div class="panel pcw-immutable-panel">
    <div class="panel-heading" data-toggle="collapse" data-target="#pcw-section-deadline">
      <i class="icon-calendar"></i>
      {l s='3. Deadline & Eligibility' mod='pcwithdrawal'}
    </div>
    <div id="pcw-section-deadline" class="panel-body collapse in pcw-immutable-body">
      <div class="row">
        <div class="col-md-6">
          <table class="table table-bordered pcw-info-table">
            <tr><th>{l s='Delivery Date Used' mod='pcwithdrawal'}</th>
                <td>{$pcw_request.delivery_date_used|escape:'html':'UTF-8'}</td></tr>
            <tr><th>{l s='Deadline Start' mod='pcwithdrawal'}</th>
                <td>{$pcw_request.deadline_start_at|escape:'html':'UTF-8'}</td></tr>
            <tr><th>{l s='Deadline End' mod='pcwithdrawal'}</th>
                <td><strong>{$pcw_request.deadline_end_at|escape:'html':'UTF-8'}</strong></td></tr>
          </table>
        </div>
        <div class="col-md-6">
          <table class="table table-bordered pcw-info-table">
            <tr><th>{l s='Eligibility Code' mod='pcwithdrawal'}</th>
                <td>
                  {assign var=ec value=$pcw_request.eligibility_code}
                  {assign var=ecolor value=$pcw_eligibility_colors[$ec]|default:'default'}
                  <span class="badge badge-{$ecolor|escape:'html':'UTF-8'} pcw-eligibility-badge">
                    {$ec|escape:'html':'UTF-8'}
                  </span>
                </td></tr>
            <tr><th>{l s='Eligibility Reason' mod='pcwithdrawal'}</th>
                <td>{$pcw_request.eligibility_reason|escape:'html':'UTF-8'}</td></tr>
          </table>
        </div>
      </div>
    </div>
  </div>

  {* ============================================================
     SECTION 4 — Status & Transition
  ============================================================ *}
  <div class="panel">
    <div class="panel-heading" data-toggle="collapse" data-target="#pcw-section-status">
      <i class="icon-refresh"></i>
      {l s='4. Current Status' mod='pcwithdrawal'}
      &nbsp;
      {assign var=sc value=$pcw_request.status_code}
      {assign var=scolor value=$pcw_status_colors[$sc]|default:'default'}
      <span class="badge badge-{$scolor|escape:'html':'UTF-8'} pcw-status-badge pcw-status-{$sc|escape:'html':'UTF-8'}">
        {$sc|escape:'html':'UTF-8'}
      </span>
    </div>
    <div id="pcw-section-status" class="panel-body collapse in">
      {if $pcw_allowed_next_statuses}
      <form method="post" action="{$pcw_action_url|escape:'html':'UTF-8'}">
        <input type="hidden" name="token" value="{Tools::getAdminTokenLite('AdminPcWithdrawalRequests')|escape:'html':'UTF-8'}">
        <input type="hidden" name="pcw_action" value="changeStatus">
        <input type="hidden" name="id_pcwithdrawal_request" value="{$pcw_request.id_pcwithdrawal_request|intval}">

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">{l s='New Status' mod='pcwithdrawal'}</label>
              <select name="new_status" class="form-control" id="pcw-new-status-select"
                data-reasons-required='{$pcw_transition_reasons_json}'>
                <option value="">{l s='— Select —' mod='pcwithdrawal'}</option>
                {foreach from=$pcw_allowed_next_statuses item=ns}
                <option value="{$ns|escape:'html':'UTF-8'}">{$ns|escape:'html':'UTF-8'}</option>
                {/foreach}
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group" id="pcw-reason-group" style="display:none;">
              <label class="control-label">
                {l s='Reason' mod='pcwithdrawal'}
                <span id="pcw-reason-required" class="text-danger" style="display:none;"> *</span>
              </label>
              <textarea name="reason" class="form-control" rows="3" id="pcw-reason-textarea"></textarea>
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label class="control-label">{l s='Notify Customer' mod='pcwithdrawal'}</label>
              <div class="checkbox">
                <label>
                  <input type="checkbox" name="notify_customer" value="1"> {l s='Send email' mod='pcwithdrawal'}
                </label>
              </div>
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" id="pcw-change-status-btn" disabled="disabled">
          <i class="icon-save"></i> {l s='Apply Status Change' mod='pcwithdrawal'}
        </button>
      </form>
      {else}
      <p class="text-muted"><em>{l s='This request is in a terminal status. No further transitions are available.' mod='pcwithdrawal'}</em></p>
      {/if}
    </div>
  </div>

  {* ============================================================
     SECTION 5 — Event Timeline
  ============================================================ *}
  <div class="panel">
    <div class="panel-heading" data-toggle="collapse" data-target="#pcw-section-events">
      <i class="icon-time"></i>
      {l s='5. Event Timeline' mod='pcwithdrawal'}
      <span class="badge">{$pcw_events|count}</span>
    </div>
    <div id="pcw-section-events" class="panel-body collapse in">
      {if $pcw_events}
      <div class="pcw-timeline">
        {foreach from=$pcw_events item=ev}
        <div class="pcw-timeline-item pcw-event-{$ev.event_code|escape:'html':'UTF-8'|replace:'_':'-'}">
          <div class="pcw-timeline-dot"></div>
          <div class="pcw-timeline-content">
            <div class="pcw-timeline-header">
              <strong>{$ev.event_code|escape:'html':'UTF-8'}</strong>
              {if $ev.previous_status && $ev.new_status}
                &nbsp;
                <span class="pcw-status-change">
                  {$ev.previous_status|escape:'html':'UTF-8'} &rarr; {$ev.new_status|escape:'html':'UTF-8'}
                </span>
              {/if}
              <span class="text-muted pull-right">
                {$ev.created_at_utc|escape:'html':'UTF-8'}
                &nbsp;&mdash;&nbsp;
                {if $ev.employee_name}{$ev.employee_name|escape:'html':'UTF-8'}{else}{$ev.actor_type|escape:'html':'UTF-8'}{/if}
              </span>
            </div>
            {if $ev.event_payload}
              {assign var=payload value=$ev.event_payload|json_decode:true}
              {if $payload.note}
              <div class="pcw-note-body">{$payload.note|escape:'html':'UTF-8'|nl2br}</div>
              {/if}
              {if $payload.reason}
              <div class="pcw-event-reason"><em>{$payload.reason|escape:'html':'UTF-8'}</em></div>
              {/if}
              {if $payload.tracking_ref}
              <div><small>{l s='Tracking:' mod='pcwithdrawal'} {$payload.tracking_ref|escape:'html':'UTF-8'}</small></div>
              {/if}
            {/if}
          </div>
        </div>
        {/foreach}
      </div>
      {else}
      <p class="text-muted"><em>{l s='No events recorded yet.' mod='pcwithdrawal'}</em></p>
      {/if}
    </div>
  </div>

  {* ============================================================
     SECTION 6 — Mail History
  ============================================================ *}
  <div class="panel">
    <div class="panel-heading" data-toggle="collapse" data-target="#pcw-section-mail">
      <i class="icon-envelope"></i>
      {l s='6. Mail History' mod='pcwithdrawal'}
      <span class="badge">{$pcw_mail_log|count}</span>
    </div>
    <div id="pcw-section-mail" class="panel-body collapse in">
      {if $pcw_mail_log}
      <table class="table table-bordered table-condensed">
        <thead>
          <tr>
            <th>{l s='Date (UTC)' mod='pcwithdrawal'}</th>
            <th>{l s='Template' mod='pcwithdrawal'}</th>
            <th>{l s='Recipient' mod='pcwithdrawal'}</th>
            <th>{l s='Subject' mod='pcwithdrawal'}</th>
            <th>{l s='Status' mod='pcwithdrawal'}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$pcw_mail_log item=ml}
          <tr>
            <td><small>{$ml.created_at_utc|escape:'html':'UTF-8'}</small></td>
            <td>{$ml.template_code|escape:'html':'UTF-8'}</td>
            <td>{$ml.recipient|escape:'html':'UTF-8'}</td>
            <td>{$ml.subject_snapshot|escape:'html':'UTF-8'|truncate:60:'...'}</td>
            <td>
              {if $ml.send_status == 'sent'}<span class="badge badge-success">sent</span>
              {elseif $ml.send_status == 'failed'}<span class="badge badge-danger">failed</span>
              {else}<span class="badge badge-default">{$ml.send_status|escape:'html':'UTF-8'}</span>{/if}
            </td>
          </tr>
          {/foreach}
        </tbody>
      </table>
      {else}
      <p class="text-muted"><em>{l s='No emails logged for this request.' mod='pcwithdrawal'}</em></p>
      {/if}
    </div>
  </div>

  {* ============================================================
     SECTION 7 — Internal Notes
  ============================================================ *}
  <div class="panel">
    <div class="panel-heading" data-toggle="collapse" data-target="#pcw-section-notes">
      <i class="icon-comment"></i>
      {l s='7. Add Internal Note' mod='pcwithdrawal'}
    </div>
    <div id="pcw-section-notes" class="panel-body collapse in">
      <form method="post" action="{$pcw_action_url|escape:'html':'UTF-8'}">
        <input type="hidden" name="token" value="{Tools::getAdminTokenLite('AdminPcWithdrawalRequests')|escape:'html':'UTF-8'}">
        <input type="hidden" name="pcw_action" value="addNote">
        <input type="hidden" name="id_pcwithdrawal_request" value="{$pcw_request.id_pcwithdrawal_request|intval}">
        <div class="form-group">
          <label class="control-label">{l s='Note (visible in event log only, not sent to customer)' mod='pcwithdrawal'}</label>
          <textarea name="note" class="form-control" rows="4" required="required"></textarea>
        </div>
        <button type="submit" class="btn btn-default">
          <i class="icon-plus"></i> {l s='Add Note' mod='pcwithdrawal'}
        </button>
      </form>
    </div>
  </div>

  {* ============================================================
     SECTION 8 — Return & Refund Tracking
  ============================================================ *}
  <div class="panel">
    <div class="panel-heading" data-toggle="collapse" data-target="#pcw-section-tracking">
      <i class="icon-truck"></i>
      {l s='8. Return & Refund Tracking' mod='pcwithdrawal'}
    </div>
    <div id="pcw-section-tracking" class="panel-body collapse in">
      <form method="post" action="{$pcw_action_url|escape:'html':'UTF-8'}">
        <input type="hidden" name="token" value="{Tools::getAdminTokenLite('AdminPcWithdrawalRequests')|escape:'html':'UTF-8'}">
        <input type="hidden" name="pcw_action" value="updateTracking">
        <input type="hidden" name="id_pcwithdrawal_request" value="{$pcw_request.id_pcwithdrawal_request|intval}">
        <div class="row">
          <div class="col-md-5">
            <div class="form-group">
              <label class="control-label">{l s='Return Tracking Reference' mod='pcwithdrawal'}</label>
              <input type="text" name="tracking_ref" class="form-control" placeholder="e.g. 1Z999AA10123456784">
            </div>
          </div>
          <div class="col-md-5">
            <div class="form-group">
              <label class="control-label">{l s='Refund Reference' mod='pcwithdrawal'}</label>
              <input type="text" name="refund_ref" class="form-control" placeholder="e.g. REFUND-2024-001">
            </div>
          </div>
          <div class="col-md-2">
            <label class="control-label">&nbsp;</label>
            <button type="submit" class="btn btn-default form-control">
              {l s='Log' mod='pcwithdrawal'}
            </button>
          </div>
        </div>
        <p class="help-block">
          <i class="icon-info-circle"></i>
          {l s='These references are logged in the event trail. No automatic action is taken.' mod='pcwithdrawal'}
        </p>
      </form>
    </div>
  </div>

  {* ============================================================
     SECTION 9 — Order Linking
  ============================================================ *}
  {if !$pcw_request.id_order}
  <div class="panel panel-warning">
    <div class="panel-heading" data-toggle="collapse" data-target="#pcw-section-link">
      <i class="icon-link"></i>
      {l s='9. Order Linking' mod='pcwithdrawal'}
      <span class="badge badge-warning">{l s='Unlinked' mod='pcwithdrawal'}</span>
    </div>
    <div id="pcw-section-link" class="panel-body collapse in">
      <form method="post" action="{$pcw_action_url|escape:'html':'UTF-8'}">
        <input type="hidden" name="token" value="{Tools::getAdminTokenLite('AdminPcWithdrawalRequests')|escape:'html':'UTF-8'}">
        <input type="hidden" name="pcw_action" value="linkOrder">
        <input type="hidden" name="id_pcwithdrawal_request" value="{$pcw_request.id_pcwithdrawal_request|intval}">
        <div class="row">
          <div class="col-md-5">
            <div class="form-group">
              <label class="control-label">{l s='Order Reference' mod='pcwithdrawal'} <span class="text-danger">*</span></label>
              <input type="text" name="link_order_reference" class="form-control"
                value="{$pcw_request.order_reference|escape:'html':'UTF-8'}"
                placeholder="e.g. ABCDEFGH">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">&nbsp;</label>
              <div class="checkbox">
                <label>
                  <input type="checkbox" name="force_link" value="1" id="pcw-force-link">
                  {l s='Force link (override customer mismatch)' mod='pcwithdrawal'}
                </label>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <label class="control-label">&nbsp;</label>
            <button type="submit" class="btn btn-warning btn-block">
              <i class="icon-link"></i> {l s='Link Order' mod='pcwithdrawal'}
            </button>
          </div>
        </div>
        <div class="alert alert-warning" id="pcw-force-link-warning" style="display:none;">
          <i class="icon-warning"></i>
          {l s='Force link bypasses customer identity check. Only use when you have verified the customer manually.' mod='pcwithdrawal'}
        </div>
      </form>
    </div>
  </div>
  {else}
  <div class="panel">
    <div class="panel-heading">
      <i class="icon-link"></i>
      {l s='9. Order Linking' mod='pcwithdrawal'}
      <span class="badge badge-success">{l s='Linked' mod='pcwithdrawal'}</span>
    </div>
    <div class="panel-body">
      <p>
        {l s='Linked to order:' mod='pcwithdrawal'}
        <strong>{$pcw_request.order_reference|escape:'html':'UTF-8'}</strong>
        &nbsp;
        <a href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&id_order={$pcw_request.id_order|intval}&vieworder"
           class="btn btn-xs btn-default" target="_blank">
          <i class="icon-external-link"></i> {l s='Open Order' mod='pcwithdrawal'}
        </a>
      </p>
      <p class="text-muted"><small>{l s='Matched at:' mod='pcwithdrawal'} {$pcw_request.matched_at|escape:'html':'UTF-8'}</small></p>
    </div>
  </div>
  {/if}

</div>{* /pcw-detail-wrap *}

<script>
(function() {
  // Status change: show reason field based on transition
  var statusSelect = document.getElementById('pcw-new-status-select');
  var reasonGroup  = document.getElementById('pcw-reason-group');
  var reasonReq    = document.getElementById('pcw-reason-required');
  var reasonTa     = document.getElementById('pcw-reason-textarea');
  var submitBtn    = document.getElementById('pcw-change-status-btn');
  var reasonsMap   = {};
  try { reasonsMap = JSON.parse(statusSelect.getAttribute('data-reasons-required') || '{}'); } catch(e) {}

  if (statusSelect) {
    statusSelect.addEventListener('change', function() {
      var ns = this.value;
      if (!ns) {
        reasonGroup.style.display = 'none';
        submitBtn.disabled = true;
        return;
      }
      submitBtn.disabled = false;
      if (reasonsMap[ns]) {
        reasonGroup.style.display = 'block';
        reasonReq.style.display   = 'inline';
        reasonTa.required = true;
      } else {
        reasonGroup.style.display = 'block';
        reasonReq.style.display   = 'none';
        reasonTa.required = false;
      }
    });
  }

  // Force link warning
  var forceCb = document.getElementById('pcw-force-link');
  var forceWarn = document.getElementById('pcw-force-link-warning');
  if (forceCb && forceWarn) {
    forceCb.addEventListener('change', function() {
      forceWarn.style.display = this.checked ? 'block' : 'none';
    });
  }
})();
</script>
