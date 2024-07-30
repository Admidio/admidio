<div class="form-group form-custom-content row">
    {include file='sys-template-parts/parts/form.part.icon.tpl'}
    <label class="col-sm-3">{$label}</label>
    {include file='sys-template-parts/parts/form.part.iconhelp.tpl'}
    <div class="col-sm-9">
        {$content}
        {include file='sys-template-parts/parts/form.part.helptext.tpl'}
        {include file='sys-template-parts/parts/form.part.warning.tpl'}
    </div>
</div>
