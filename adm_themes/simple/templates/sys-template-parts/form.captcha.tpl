{if $formType neq "vertical" and $formType neq "navbar"}
    <div class="row mb-3">
        <div class="col-sm-9 offset-sm-3">
{/if}
<div id="admCaptchaPuzzleGroup" class="admidio-form-group mb-3 {$data.class}">
    <img id="admCaptcha" src="{$urlAdmidio}/adm_program/libs/securimage/securimage_show.php" alt="CAPTCHA Image" />
    <a id="{$data.id}Refresh" class="admidio-icon-link" href="javascript:void(0)">
        <i class="bi bi-arrow-repeat" style="font-size: 22pt;" data-bs-toggle="tooltip" title="{$l10n->get("SYS_RELOAD")}"></i>
    </a>
</div>
{if $formType neq "vertical" and $formType neq "navbar"}</div></div>{/if}

{if isset($data.type)}
    {include 'sys-template-parts/form.input.tpl' data=$data}
{/if}
