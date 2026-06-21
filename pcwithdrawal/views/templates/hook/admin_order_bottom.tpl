{**
 * Admin order page — bottom panel withdrawal information.
 * Variables: pcwithdrawal_request (array|null), pcwithdrawal_id_order (int)
 *}
<div class="panel pcwithdrawal-admin-panel" id="pcwithdrawal-order-panel">
    <div class="panel-heading">
        <i class="icon-undo"></i>
        {l s='EU Withdrawal Request' mod='pcwithdrawal'}
    </div>
    <div class="panel-body">
        {if $pcwithdrawal_request}
            <table class="table table-condensed">
                <tr>
                    <th>{l s='Reference' mod='pcwithdrawal'}</th>
                    <td>{$pcwithdrawal_request.public_reference|escape:'html':'UTF-8'}</td>
                </tr>
                <tr>
                    <th>{l s='Status' mod='pcwithdrawal'}</th>
                    <td>{$pcwithdrawal_request.status_code|escape:'html':'UTF-8'}</td>
                </tr>
                <tr>
                    <th>{l s='Submitted' mod='pcwithdrawal'}</th>
                    <td>{$pcwithdrawal_request.submitted_at_utc|escape:'html':'UTF-8'}</td>
                </tr>
                <tr>
                    <th>{l s='Consumer' mod='pcwithdrawal'}</th>
                    <td>
                        {$pcwithdrawal_request.consumer_firstname|escape:'html':'UTF-8'}
                        {$pcwithdrawal_request.consumer_lastname|escape:'html':'UTF-8'}
                        &lt;{$pcwithdrawal_request.consumer_email|escape:'html':'UTF-8'}&gt;
                    </td>
                </tr>
                <tr>
                    <th>{l s='Eligibility' mod='pcwithdrawal'}</th>
                    <td>{$pcwithdrawal_request.eligibility_code|escape:'html':'UTF-8'}</td>
                </tr>
            </table>
        {else}
            <p class="text-muted">{l s='No withdrawal request found for this order.' mod='pcwithdrawal'}</p>
        {/if}
    </div>
</div>
