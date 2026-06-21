{*
 * Template — withdrawal requests list view.
 * All dynamic data is pre-escaped by the controller.
 *}

<div class="panel pcw-requests-panel">
  <div class="panel-heading">
    <i class="icon-list"></i>
    {l s='Withdrawal Requests' mod='pcwithdrawal'}
    <span class="badge">{$pcw_total|intval}</span>
  </div>

  {* Filter form *}
  <form method="get" action="{$pcw_list_url|escape:'html':'UTF-8'}" id="pcw-filter-form" class="form-horizontal">
    <input type="hidden" name="controller" value="AdminPcWithdrawalRequests">
    <input type="hidden" name="token" value="{Tools::getAdminTokenLite('AdminPcWithdrawalRequests')|escape:'html':'UTF-8'}">

    <div class="panel panel-default pcw-filter-panel">
      <div class="panel-heading">
        <a data-toggle="collapse" href="#pcw-filters-body">
          <i class="icon-filter"></i> {l s='Filters' mod='pcwithdrawal'}
        </a>
      </div>
      <div id="pcw-filters-body" class="panel-body collapse in">
        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">{l s='Reference' mod='pcwithdrawal'}</label>
              <input type="text" name="filter_public_reference" class="form-control"
                value="{$pcw_filters.public_reference|escape:'html':'UTF-8'}">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">{l s='Order Reference' mod='pcwithdrawal'}</label>
              <input type="text" name="filter_order_reference" class="form-control"
                value="{$pcw_filters.order_reference|escape:'html':'UTF-8'}">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">{l s='Consumer Email' mod='pcwithdrawal'}</label>
              <input type="text" name="filter_consumer_email" class="form-control"
                value="{$pcw_filters.consumer_email|escape:'html':'UTF-8'}">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">{l s='Status' mod='pcwithdrawal'}</label>
              <select name="filter_status_code" class="form-control">
                <option value="">{l s='— All —' mod='pcwithdrawal'}</option>
                {foreach from=$pcw_status_list item=st}
                  <option value="{$st|escape:'html':'UTF-8'}"
                    {if $pcw_filters.status_code == $st}selected="selected"{/if}>
                    {$st|escape:'html':'UTF-8'}
                  </option>
                {/foreach}
              </select>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">{l s='Eligibility' mod='pcwithdrawal'}</label>
              <select name="filter_eligibility_code" class="form-control">
                <option value="">{l s='— All —' mod='pcwithdrawal'}</option>
                {foreach from=$pcw_eligibility_list item=ec}
                  <option value="{$ec|escape:'html':'UTF-8'}"
                    {if $pcw_filters.eligibility_code == $ec}selected="selected"{/if}>
                    {$ec|escape:'html':'UTF-8'}
                  </option>
                {/foreach}
              </select>
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label class="control-label">{l s='Has Order' mod='pcwithdrawal'}</label>
              <select name="filter_has_order" class="form-control">
                <option value="">{l s='— All —' mod='pcwithdrawal'}</option>
                <option value="1" {if $pcw_filters.has_order == '1'}selected="selected"{/if}>{l s='Yes' mod='pcwithdrawal'}</option>
                <option value="0" {if $pcw_filters.has_order == '0'}selected="selected"{/if}>{l s='No' mod='pcwithdrawal'}</option>
              </select>
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label class="control-label">{l s='Date From' mod='pcwithdrawal'}</label>
              <input type="date" name="filter_date_from" class="form-control"
                value="{$pcw_filters.date_from|escape:'html':'UTF-8'}">
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label class="control-label">{l s='Date To' mod='pcwithdrawal'}</label>
              <input type="date" name="filter_date_to" class="form-control"
                value="{$pcw_filters.date_to|escape:'html':'UTF-8'}">
            </div>
          </div>
          {if $pcw_admin_shop_id == 0}
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">{l s='Shop' mod='pcwithdrawal'}</label>
              <select name="filter_id_shop" class="form-control">
                <option value="">{l s='— All Shops —' mod='pcwithdrawal'}</option>
                {foreach from=$pcw_shops item=shop}
                  <option value="{$shop.id_shop|intval}"
                    {if $pcw_filters.id_shop == $shop.id_shop}selected="selected"{/if}>
                    {$shop.name|escape:'html':'UTF-8'}
                  </option>
                {/foreach}
              </select>
            </div>
          </div>
          {/if}
        </div>
        <div class="row">
          <div class="col-md-12">
            <button type="submit" class="btn btn-primary">
              <i class="icon-search"></i> {l s='Filter' mod='pcwithdrawal'}
            </button>
            <a href="{$pcw_list_url|escape:'html':'UTF-8'}" class="btn btn-default">
              <i class="icon-times"></i> {l s='Clear' mod='pcwithdrawal'}
            </a>
          </div>
        </div>
      </div>
    </div>
  </form>

  {* Bulk actions form *}
  <form method="post" action="{$pcw_list_url|escape:'html':'UTF-8'}" id="pcw-bulk-form">
    <input type="hidden" name="controller" value="AdminPcWithdrawalRequests">
    <input type="hidden" name="token" value="{Tools::getAdminTokenLite('AdminPcWithdrawalRequests')|escape:'html':'UTF-8'}">
    <input type="hidden" name="pcw_action" value="exportCsv" id="pcw-bulk-action-value">
    {* Pass current filters for CSV export *}
    <input type="hidden" name="filter_public_reference" value="{$pcw_filters.public_reference|escape:'html':'UTF-8'}">
    <input type="hidden" name="filter_order_reference" value="{$pcw_filters.order_reference|escape:'html':'UTF-8'}">
    <input type="hidden" name="filter_consumer_email" value="{$pcw_filters.consumer_email|escape:'html':'UTF-8'}">
    <input type="hidden" name="filter_status_code" value="{$pcw_filters.status_code|escape:'html':'UTF-8'}">
    <input type="hidden" name="filter_eligibility_code" value="{$pcw_filters.eligibility_code|escape:'html':'UTF-8'}">
    <input type="hidden" name="filter_id_shop" value="{$pcw_filters.id_shop|escape:'html':'UTF-8'}">
    <input type="hidden" name="filter_date_from" value="{$pcw_filters.date_from|escape:'html':'UTF-8'}">
    <input type="hidden" name="filter_date_to" value="{$pcw_filters.date_to|escape:'html':'UTF-8'}">
    <input type="hidden" name="filter_has_order" value="{$pcw_filters.has_order|escape:'html':'UTF-8'}">

    <div class="table-responsive">
      <table class="table tableDnD pcw-requests-table">
        <thead>
          <tr>
            <th class="col-th-nosort"><input type="checkbox" id="checkBoxAll"></th>
            {assign var=oby_fields value=['public_reference','order_reference','consumer_email','status_code','submitted_at_utc']}
            <th>
              <a href="{$pcw_list_url|escape:'html':'UTF-8'}&orderby=public_reference&orderway={if $pcw_order_by=='public_reference' && $pcw_order_way=='ASC'}DESC{else}ASC{/if}&{http_build_query($pcw_filters)}">
                {l s='Reference' mod='pcwithdrawal'}
                {if $pcw_order_by=='public_reference'}<i class="icon-caret-{if $pcw_order_way=='ASC'}up{else}down{/if}"></i>{/if}
              </a>
            </th>
            {if $pcw_admin_shop_id == 0}
            <th>{l s='Shop' mod='pcwithdrawal'}</th>
            {/if}
            <th>
              <a href="{$pcw_list_url|escape:'html':'UTF-8'}&orderby=order_reference&orderway={if $pcw_order_by=='order_reference' && $pcw_order_way=='ASC'}DESC{else}ASC{/if}&{http_build_query($pcw_filters)}">
                {l s='Order' mod='pcwithdrawal'}
              </a>
            </th>
            <th>{l s='Consumer' mod='pcwithdrawal'}</th>
            <th>
              <a href="{$pcw_list_url|escape:'html':'UTF-8'}&orderby=consumer_email&orderway={if $pcw_order_by=='consumer_email' && $pcw_order_way=='ASC'}DESC{else}ASC{/if}&{http_build_query($pcw_filters)}">
                {l s='Email' mod='pcwithdrawal'}
              </a>
            </th>
            <th>{l s='Scope' mod='pcwithdrawal'}</th>
            <th>
              <a href="{$pcw_list_url|escape:'html':'UTF-8'}&orderby=status_code&orderway={if $pcw_order_by=='status_code' && $pcw_order_way=='ASC'}DESC{else}ASC{/if}&{http_build_query($pcw_filters)}">
                {l s='Status' mod='pcwithdrawal'}
              </a>
            </th>
            <th>{l s='Eligibility' mod='pcwithdrawal'}</th>
            <th>
              <a href="{$pcw_list_url|escape:'html':'UTF-8'}&orderby=submitted_at_utc&orderway={if $pcw_order_by=='submitted_at_utc' && $pcw_order_way=='ASC'}DESC{else}ASC{/if}&{http_build_query($pcw_filters)}">
                {l s='Submitted' mod='pcwithdrawal'}
              </a>
            </th>
            <th>{l s='Ack.' mod='pcwithdrawal'}</th>
            <th>{l s='Actions' mod='pcwithdrawal'}</th>
          </tr>
        </thead>
        <tbody>
          {if !$pcw_rows}
          <tr>
            <td colspan="12" class="text-center text-muted">
              <em>{l s='No withdrawal requests found.' mod='pcwithdrawal'}</em>
            </td>
          </tr>
          {/if}
          {foreach from=$pcw_rows item=row}
          <tr>
            <td><input type="checkbox" name="pcw_ids[]" value="{$row.id_pcwithdrawal_request|intval}"></td>
            <td>
              <a href="{$pcw_detail_base_url|escape:'html':'UTF-8'}&id_pcwithdrawal_request={$row.id_pcwithdrawal_request|intval}">
                <strong>{$row.public_reference|escape:'html':'UTF-8'}</strong>
              </a>
            </td>
            {if $pcw_admin_shop_id == 0}
            <td>{$row.shop_name|escape:'html':'UTF-8'}</td>
            {/if}
            <td>
              {if $row.order_reference}
                {$row.order_reference|escape:'html':'UTF-8'}
              {else}
                <span class="text-muted">&mdash;</span>
              {/if}
            </td>
            <td>{$row.consumer_firstname|escape:'html':'UTF-8'} {$row.consumer_lastname|escape:'html':'UTF-8'}</td>
            <td>{$row.consumer_email|escape:'html':'UTF-8'}</td>
            <td>{$row.scope_code|escape:'html':'UTF-8'}</td>
            <td>
              {assign var=scolor value=$pcw_status_colors[$row.status_code]|default:'default'}
              <span class="badge badge-{$scolor|escape:'html':'UTF-8'} pcw-status-badge pcw-status-{$row.status_code|escape:'html':'UTF-8'}">
                {$row.status_code|escape:'html':'UTF-8'}
              </span>
            </td>
            <td>
              {assign var=ecolor value=$pcw_eligibility_colors[$row.eligibility_code]|default:'default'}
              <span class="badge badge-{$ecolor|escape:'html':'UTF-8'} pcw-eligibility-badge">
                {$row.eligibility_code|escape:'html':'UTF-8'}
              </span>
            </td>
            <td>
              <small>{$row.submitted_at_utc|escape:'html':'UTF-8'}</small>
            </td>
            <td>
              {assign var=ack value=$row.acknowledgement_status}
              {if $ack == 'sent'}<i class="icon-check text-success"></i>
              {elseif $ack == 'failed'}<i class="icon-times text-danger"></i>
              {else}<i class="icon-clock-o text-muted"></i>{/if}
            </td>
            <td class="pcw-action-buttons">
              <a class="btn btn-xs btn-default"
                 href="{$pcw_detail_base_url|escape:'html':'UTF-8'}&id_pcwithdrawal_request={$row.id_pcwithdrawal_request|intval}"
                 title="{l s='View' mod='pcwithdrawal'}">
                <i class="icon-eye"></i>
              </a>
              {if $row.id_order}
              <a class="btn btn-xs btn-default"
                 href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&id_order={$row.id_order|intval}&vieworder"
                 target="_blank" title="{l s='Open Order' mod='pcwithdrawal'}">
                <i class="icon-shopping-cart"></i>
              </a>
              {/if}
              <a class="btn btn-xs btn-default"
                 href="{$pcw_detail_base_url|escape:'html':'UTF-8'}&action=exportEvidence&id_pcwithdrawal_request={$row.id_pcwithdrawal_request|intval}&token={Tools::getAdminTokenLite('AdminPcWithdrawalRequests')|escape:'html':'UTF-8'}"
                 target="_blank" title="{l s='Export Evidence' mod='pcwithdrawal'}">
                <i class="icon-file-text"></i>
              </a>
              <form method="post" action="{$pcw_list_url|escape:'html':'UTF-8'}" style="display:inline;">
                <input type="hidden" name="token" value="{Tools::getAdminTokenLite('AdminPcWithdrawalRequests')|escape:'html':'UTF-8'}">
                <input type="hidden" name="pcw_action" value="resendAcknowledgement">
                <input type="hidden" name="id_pcwithdrawal_request" value="{$row.id_pcwithdrawal_request|intval}">
                <button type="submit" class="btn btn-xs btn-default"
                  title="{l s='Resend Acknowledgement' mod='pcwithdrawal'}"
                  onclick="return confirm('{l s='Resend acknowledgement email?' mod='pcwithdrawal' js=1}');">
                  <i class="icon-envelope"></i>
                </button>
              </form>
            </td>
          </tr>
          {/foreach}
        </tbody>
      </table>
    </div>

    {* Bulk action bar *}
    <div class="row">
      <div class="col-md-6">
        <button type="submit" class="btn btn-default" onclick="document.getElementById('pcw-bulk-action-value').value='exportCsv';">
          <i class="icon-download"></i> {l s='Export CSV' mod='pcwithdrawal'}
        </button>
      </div>
      {* Pagination *}
      <div class="col-md-6 text-right">
        {if $pcw_total > $pcw_page_size}
        <ul class="pagination pagination-sm">
          {if $pcw_current_page > 1}
          <li>
            <a href="{$pcw_list_url|escape:'html':'UTF-8'}&p={$pcw_current_page-1}&orderby={$pcw_order_by|escape:'html':'UTF-8'}&orderway={$pcw_order_way|escape:'html':'UTF-8'}&{http_build_query($pcw_filters)}">&laquo;</a>
          </li>
          {/if}
          {assign var=pstart value=max(1, $pcw_current_page-3)}
          {assign var=pend value=min($pcw_total_pages, $pcw_current_page+3)}
          {for $pi=$pstart to $pend}
          <li class="{if $pi == $pcw_current_page}active{/if}">
            <a href="{$pcw_list_url|escape:'html':'UTF-8'}&p={$pi}&orderby={$pcw_order_by|escape:'html':'UTF-8'}&orderway={$pcw_order_way|escape:'html':'UTF-8'}&{http_build_query($pcw_filters)}">{$pi}</a>
          </li>
          {/for}
          {if $pcw_current_page < $pcw_total_pages}
          <li>
            <a href="{$pcw_list_url|escape:'html':'UTF-8'}&p={$pcw_current_page+1}&orderby={$pcw_order_by|escape:'html':'UTF-8'}&orderway={$pcw_order_way|escape:'html':'UTF-8'}&{http_build_query($pcw_filters)}">&raquo;</a>
          </li>
          {/if}
        </ul>
        <p class="text-muted">
          {l s='Showing' mod='pcwithdrawal'}
          {($pcw_current_page-1)*$pcw_page_size + 1}–{min($pcw_total, $pcw_current_page*$pcw_page_size)}
          {l s='of' mod='pcwithdrawal'} {$pcw_total}
        </p>
        {/if}
      </div>
    </div>
  </form>
</div>

<script>
document.getElementById('checkBoxAll').addEventListener('change', function() {
  var cbs = document.querySelectorAll('input[name="pcw_ids[]"]');
  for (var i = 0; i < cbs.length; i++) { cbs[i].checked = this.checked; }
});
</script>
