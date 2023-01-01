<div id="{$id}_group" class="form-group form-group-editor">
    <label for="{$id}" class="control-label">
        {include file='sys-template-parts/parts/form.part.icon.tpl'}
        {$label}
        {include file='sys-template-parts/parts/form.part.iconhelp.tpl'}
    </label>
  <div {foreach $data.attributes as $itemvar}
  {$itemvar@key}="{$itemvar}"
  {/foreach}>
    <textarea id="{$id}" name="{$id}" style="width: 100%">{$value}</textarea>
  </div>
  
  {include file='sys-template-parts/parts/form.part.helptext.tpl'}
  {include file='sys-template-parts/parts/form.part.warning.tpl'}
</div>
