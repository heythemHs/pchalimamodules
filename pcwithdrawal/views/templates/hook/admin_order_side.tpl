{**
 * Admin order page — sidebar withdrawal badge/link.
 * Variable: pcwithdrawal_request_side (array)
 *}
<div class="panel pcwithdrawal-side-panel">
    <div class="panel-heading">
        <i class="icon-undo"></i>
        {l s='Withdrawal' mod='pcwithdrawal'}
    </div>
    <div class="panel-body">
        <span class="label label-info">
            {$pcwithdrawal_request_side.status_code|escape:'html':'UTF-8'}
        </span>
        <br />
        <small>{$pcwithdrawal_request_side.public_reference|escape:'html':'UTF-8'}</small>
    </div>
</div>
