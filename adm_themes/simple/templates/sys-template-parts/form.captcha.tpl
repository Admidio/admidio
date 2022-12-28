<div id="captcha_puzzle_group" class="form-group captcha row">
    <div class="offset-sm-3 col-sm-9">
        <img id="captcha"
            src="{$ADMIDIO_URL}{$FOLDER_LIBS_SERVER}/dapphp/securimage/securimage_show.php"
            alt="CAPTCHA Image" />
        <a class="admidio-icon-link" href="javascript:void(0)"
            onclick="document.getElementById('captcha').src='{$ADMIDIO_URL}{$FOLDER_LIBS_SERVER}/dapphp/securimage/securimage_show.php?'
            + Math.random(); return false;">
            <i class="fas fa-sync-alt fa-lg" data-toggle="tooltip"
                title="{$l10n->get('SYS_RELOAD')}"></i>
        </a>
    </div>
</div>