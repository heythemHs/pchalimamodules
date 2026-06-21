{*
 * Template — compact withdrawal panel shown on admin order detail page.
 * Displayed via hookDisplayAdminOrderMainBottom.
 *}

{if $pcw_request}
<div class="panel pcw-order-panel">
  <div class="panel-heading">
    <i class="icon-undo"></i>
    {l s='EU Withdrawal Request' mod='pcwithdrawal'}
    &nbsp;
    {assign var=sc value=$pcw_request.status_code}
    {assign var=scolor value='default'}
    {if $sc == 'submitted'}{assign var=scolor value='default'}{/if}
    {if $sc == 'acknowledged'}{assign var=scolor value='info'}{/if}
    {if $sc == 'matched'}{assign var=scolor value='primary'}{/if}
    {if $sc == 'under_review'}{assign var=scolor value='warning'}{/if}
    {if $sc == 'accepted'}{assign var=scolor value='success'}{/if}
    {if $sc == 'rejected'}{assign var=scolor value='danger'}{/if}
    {if $sc == 'reimbursed'}{assign var=scolor value='success'}{/if}
    {if $sc == 'closed'}{assign var=scolor value='default'}{/if}
    <span class="badge badge-{$scolor|escape:'html':'UTF-8'}">
      {$sc|escape:'html':'UTF-8'}
    </span>
  </div>
  <div class="panel-body">
    <div class="row">
      <div class="col-md-4">
        <dl class="dl-horizontal pcw-dl-compact">
          <dt>{l s='Reference' mod='pcwithdrawal'}</dt>
          <dd><strong>{$pcw_request.public_reference|escape:'html':'UTF-8'}</strong></dd>
          <dt>{l s='Submitted' mod='pcwithdrawal'}</dt>
          <dd><small>{$pcw_request.submitted_at_utc|escape:'html':'UTF-8'}</small></dd>
          <dt>{l s='Eligibility' mod='pcwithdrawal'}</dt>
          <dd><code>{$pcw_request.eligibility_code|escape:'html':'UTF-8'}</code></dd>
        </dl>
      </div>
      <div class="col-md-4">
        <dl class="dl-horizontal pcw-dl-compact">
          <dt>{l s='Consumer' mod='pcwithdrawal'}</dt>
          <dd>{$pcw_request.consumer_firstname|escape:'html':'UTF-8'} {$pcw_request.consumer_lastname|escape:'html':'UTF-8'}</dd>
          <dt>{l s='Email' mod='pcwithdrawal'}</dt>
          <dd><small>{$pcw_request.consumer_email|escape:'html':'UTF-8'}</small></dd>
          <dt>{l s='Scope' mod='pcwithdrawal'}</dt>
          <dd>{$pcw_request.scope_code|escape:'html':'UTF-8'}</dd>
        </dl>
      </div>
      <div class="col-md-4 text-right">
        <a href="{$link->getAdminLink('AdminPcWithdrawalRequests')|escape:'html':'UTF-8'}&id_pcwithdrawal_request={$pcw_request.id_pcwithdrawal_request|intval}"
           class="btn btn-primary">
          <i class="icon-eye"></i> {l s='View Request' mod='pcwithdrawal'}
        </a>
        <br><br>
        {if $pcw_request.deadline_end_at}
        <p class="text-muted">
          <small>{l s='Deadline:' mod='pcwithdrawal'} {$pcw_request.deadline_end_at|escape:'html':'UTF-8'}</small>
        </p>
        {/if}
      </div>
    </div>
  </div>
</div>
{/if}
