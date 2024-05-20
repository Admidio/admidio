{if $data.formtype neq "vertical" and $data.formtype neq "navbar"}
    <div class="row mb-3">
        <div class="col-sm-9 offset-sm-3">
{/if}
<div id="captcha_puzzle_group" class="admidio-form-group mb-2 {$class}">
    <img id="captcha" src="{$ADMIDIO_URL}/adm_program/libs/securimage/securimage_show.php" alt="CAPTCHA Image" />
    <a class="admidio-icon-link" href="javascript:void(0)">
        <i class="bi bi-arrow-repeat" style="font-size: 22pt;" data-bs-toggle="tooltip" title="{$l10n->get("SYS_RELOAD")}"></i>
    </a>
</div>
{if $data.formtype neq "vertical" and $data.formtype neq "navbar"}</div></div>{/if}
