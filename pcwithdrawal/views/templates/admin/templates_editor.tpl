{*
 * Template — email template editor.
 * Language tabs | Template selector | Subject | HTML + Plain text editors
 * Placeholder reference panel | Version history
 * NOTE: merchant HTML is stored in DB but NEVER executed as Smarty/PHP.
 *}

<div class="panel pcw-templates-panel">
  <div class="panel-heading">
    <i class="icon-envelope-alt"></i> {l s='Email Template Editor' mod='pcwithdrawal'}
  </div>

  {* Language tabs *}
  <ul class="nav nav-tabs pcw-lang-tabs" id="pcwLangTabs">
    {foreach from=$pcw_available_langs item=lang}
    <li class="{if $lang.id_lang == $pcw_id_lang}active{/if}">
      <a href="{$pcw_action_url|escape:'html':'UTF-8'}&id_lang={$lang.id_lang|intval}&template_code={$pcw_template_code|escape:'html':'UTF-8'}">
        {$lang.name|escape:'html':'UTF-8'} ({$lang.iso_code|escape:'html':'UTF-8'|upper})
      </a>
    </li>
    {/foreach}
  </ul>

  <div class="row pcw-editor-layout">
    {* ============================================================
       Main editor area
    ============================================================ *}
    <div class="col-md-9">

      {* Template selector *}
      <div class="form-group">
        <label class="control-label">{l s='Template' mod='pcwithdrawal'}</label>
        <div class="input-group" style="max-width:500px;">
          <select id="pcw-template-selector" class="form-control"
            onchange="window.location='{$pcw_action_url|escape:'html':'UTF-8'}&id_lang={$pcw_id_lang|intval}&template_code='+this.value;">
            {foreach from=$pcw_available_templates key=code item=desc}
            <option value="{$code|escape:'html':'UTF-8'}"
              {if $pcw_template_code == $code}selected="selected"{/if}>
              {$code|escape:'html':'UTF-8'} — {$desc|escape:'html':'UTF-8'}
            </option>
            {/foreach}
          </select>
          <span class="input-group-btn">
            <button type="button" class="btn btn-default" id="pcw-reset-btn"
              onclick="document.getElementById('pcw-reset-form').submit();"
              title="{l s='Reset to built-in default' mod='pcwithdrawal'}">
              <i class="icon-rotate-left"></i>
            </button>
          </span>
        </div>
      </div>

      <form method="post" action="{$pcw_action_url|escape:'html':'UTF-8'}" id="pcw-template-form">
        <input type="hidden" name="token" value="{Tools::getAdminTokenLite('AdminPcWithdrawalTemplates')|escape:'html':'UTF-8'}">
        <input type="hidden" name="pcw_action" value="saveTemplate">
        <input type="hidden" name="id_lang" value="{$pcw_id_lang|intval}">
        <input type="hidden" name="id_shop" value="{$pcw_id_shop|intval}">
        <input type="hidden" name="template_code" value="{$pcw_template_code|escape:'html':'UTF-8'}">

        <div class="form-group">
          <label class="control-label">{l s='Subject' mod='pcwithdrawal'} <span class="text-danger">*</span></label>
          <input type="text" name="subject" id="pcw-tpl-subject" class="form-control"
            value="{if $pcw_template}{$pcw_template.subject|escape:'html':'UTF-8'}{/if}"
            required="required" maxlength="512">
          <p class="help-block">{l s='Supports {{placeholders}}. See reference panel.' mod='pcwithdrawal'}</p>
        </div>

        <div class="form-group">
          <label class="control-label">{l s='HTML Content' mod='pcwithdrawal'} <span class="text-danger">*</span></label>
          <div class="pcw-editor-toolbar">
            <button type="button" class="btn btn-xs btn-default" id="pcw-toggle-visual"
              title="{l s='Toggle visual/source mode' mod='pcwithdrawal'}">
              <i class="icon-code"></i> {l s='Source' mod='pcwithdrawal'}
            </button>
            <button type="button" class="btn btn-xs btn-default" id="pcw-preview-btn"
              title="{l s='Preview with sample data' mod='pcwithdrawal'}">
              <i class="icon-eye"></i> {l s='Preview' mod='pcwithdrawal'}
            </button>
          </div>
          <textarea name="html_content" id="pcw-html-editor" class="form-control pcw-html-textarea"
            rows="20" required="required">{if $pcw_template}{$pcw_template.html_content|escape:'html':'UTF-8'}{/if}</textarea>
          <p class="help-block text-warning">
            <i class="icon-info-circle"></i>
            {l s='HTML is stored and used as-is for email sending. Script tags are stripped before delivery.' mod='pcwithdrawal'}
          </p>
        </div>

        <div class="form-group">
          <label class="control-label">{l s='Plain Text Content' mod='pcwithdrawal'}</label>
          <textarea name="text_content" id="pcw-text-editor" class="form-control pcw-text-textarea"
            rows="8">{if $pcw_template}{$pcw_template.text_content|escape:'html':'UTF-8'}{/if}</textarea>
          <p class="help-block">{l s='Shown to email clients that do not render HTML.' mod='pcwithdrawal'}</p>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">{l s='Enabled' mod='pcwithdrawal'}</label>
              <div class="checkbox">
                <label>
                  <input type="checkbox" name="is_enabled" value="1"
                    {if !$pcw_template || $pcw_template.is_enabled}checked="checked"{/if}>
                  {l s='Active' mod='pcwithdrawal'}
                </label>
              </div>
            </div>
          </div>
          <div class="col-md-5">
            <div class="form-group">
              <label class="control-label">{l s='Change Reason (optional)' mod='pcwithdrawal'}</label>
              <input type="text" name="change_reason" class="form-control"
                placeholder="{l s='e.g. Updated footer text' mod='pcwithdrawal'}">
            </div>
          </div>
        </div>

        <div class="form-group">
          <button type="submit" class="btn btn-primary" id="pcw-save-btn">
            <i class="icon-save"></i> {l s='Save Template' mod='pcwithdrawal'}
          </button>
          &nbsp;
          <button type="button" class="btn btn-default" id="pcw-send-test-btn">
            <i class="icon-paper-plane"></i> {l s='Send Test Email' mod='pcwithdrawal'}
          </button>
        </div>
      </form>

      {* Template version history *}
      {if $pcw_history}
      <div class="panel panel-default pcw-history-panel">
        <div class="panel-heading" id="pcw-history-heading"
          data-toggle="collapse" data-target="#pcw-history-body">
          <i class="icon-history"></i> {l s='Version History' mod='pcwithdrawal'}
          <span class="badge">{$pcw_history|count}</span>
          <i class="icon-chevron-down pull-right"></i>
        </div>
        <div id="pcw-history-body" class="panel-body collapse">
          <table class="table table-condensed">
            <thead>
              <tr>
                <th>{l s='Date' mod='pcwithdrawal'}</th>
                <th>{l s='Subject' mod='pcwithdrawal'}</th>
                <th>{l s='Changed by' mod='pcwithdrawal'}</th>
                <th>{l s='Reason' mod='pcwithdrawal'}</th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$pcw_history item=h}
              <tr>
                <td><small>{$h.date_add|escape:'html':'UTF-8'}</small></td>
                <td>{$h.previous_subject|escape:'html':'UTF-8'|truncate:50:'...'}</td>
                <td>{if $h.id_employee}#{$h.id_employee|intval}{else}&mdash;{/if}</td>
                <td><em>{$h.change_reason|escape:'html':'UTF-8'|truncate:80:'...'}</em></td>
              </tr>
              {/foreach}
            </tbody>
          </table>
        </div>
      </div>
      {/if}

      {* Copy from language *}
      <div class="panel panel-default">
        <div class="panel-heading">{l s='Copy from Another Language' mod='pcwithdrawal'}</div>
        <div class="panel-body">
          <form method="post" action="{$pcw_action_url|escape:'html':'UTF-8'}" id="pcw-copy-lang-form">
            <input type="hidden" name="token" value="{Tools::getAdminTokenLite('AdminPcWithdrawalTemplates')|escape:'html':'UTF-8'}">
            <input type="hidden" name="pcw_action" value="copyFromLang">
            <input type="hidden" name="id_lang" value="{$pcw_id_lang|intval}">
            <input type="hidden" name="template_code" value="{$pcw_template_code|escape:'html':'UTF-8'}">
            <div class="input-group" style="max-width:320px;">
              <select name="copy_from_lang" class="form-control">
                {foreach from=$pcw_available_langs item=lang}
                {if $lang.id_lang != $pcw_id_lang}
                <option value="{$lang.id_lang|intval}">
                  {$lang.name|escape:'html':'UTF-8'} ({$lang.iso_code|escape:'html':'UTF-8'|upper})
                </option>
                {/if}
                {/foreach}
              </select>
              <span class="input-group-btn">
                <button type="submit" class="btn btn-default"
                  onclick="return confirm('{l s='Overwrite current template with the selected language?' mod='pcwithdrawal' js=1}');">
                  <i class="icon-copy"></i> {l s='Copy' mod='pcwithdrawal'}
                </button>
              </span>
            </div>
          </form>
        </div>
      </div>

    </div>{* /col-md-9 *}

    {* ============================================================
       Sidebar — Placeholder reference
    ============================================================ *}
    <div class="col-md-3">
      <div class="panel panel-default pcw-placeholder-panel">
        <div class="panel-heading">
          <i class="icon-tags"></i> {l s='Available Placeholders' mod='pcwithdrawal'}
        </div>
        <div class="panel-body pcw-placeholder-body">
          <p class="text-muted"><small>{l s='Click to insert at cursor.' mod='pcwithdrawal'}</small></p>
          {foreach from=$pcw_placeholder_docs key=ph item=desc}
          <div class="pcw-placeholder-item" data-placeholder="{$ph|escape:'html':'UTF-8'}">
            <code class="pcw-ph-code">&#123;&#123;{$ph|escape:'html':'UTF-8'}&#125;&#125;</code>
            <span class="pcw-ph-desc">{$desc|escape:'html':'UTF-8'}</span>
          </div>
          {/foreach}
        </div>
      </div>
    </div>
  </div>{* /row *}
</div>

{* Hidden forms *}
<form method="post" action="{$pcw_action_url|escape:'html':'UTF-8'}" id="pcw-reset-form" style="display:none;">
  <input type="hidden" name="token" value="{Tools::getAdminTokenLite('AdminPcWithdrawalTemplates')|escape:'html':'UTF-8'}">
  <input type="hidden" name="pcw_action" value="resetTemplate">
  <input type="hidden" name="id_lang" value="{$pcw_id_lang|intval}">
  <input type="hidden" name="template_code" value="{$pcw_template_code|escape:'html':'UTF-8'}">
</form>

{* Preview modal *}
<div class="modal fade" id="pcw-preview-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">{l s='Template Preview' mod='pcwithdrawal'}</h4>
        <small class="text-muted pcw-preview-subject-label"></small>
      </div>
      <div class="modal-body">
        <iframe id="pcw-preview-iframe" style="width:100%;height:500px;border:1px solid #eee;"></iframe>
      </div>
    </div>
  </div>
</div>
