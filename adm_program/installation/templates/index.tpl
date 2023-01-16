<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="author"   content="Admidio Team" />
    <meta name="robots"   content="noindex" />

    <!-- (c) 2004 - 2023 The Admidio Team - https://www.admidio.org -->

    <link rel="shortcut icon" type="image/x-icon" href="{$urlAdmidio}/adm_program/system/logo/favicon.ico" />
    <link rel="icon" type="image/png" href="{$urlAdmidio}/adm_program/system/logo/admidio_logo_32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="{$urlAdmidio}/adm_program/system/logo/admidio_logo_16.png" sizes="16x16" />
    <link rel="apple-touch-icon" type="image/png" href="{$urlAdmidio}/adm_program/system/logo/apple-touch-icon.png" sizes="180x180" />

    <title>Admidio - {$title}</title>

    {include file="js_css_files.tpl"}

    {$additionalHeaderData}

    <link rel="stylesheet" type="text/css" href="{$urlAdmidio}/adm_program/installation/templates/installation.css" />

    <script type="text/javascript">
        {$javascriptContent}

        $(function() {
            $("[data-toggle=popover]").popover();
            {$javascriptContentExecuteAtPageLoad}
        });
    </script>
</head>
<body id="{$id}" class="admidio">
    <div id="installation-header" class="admidio-area">
        <div class="admidio-container container">
            <img id="admidio-logo" src="{$urlAdmidio}/adm_program/system/logo/admidio_writing_white_150.png" alt="Logo" />
            <span id="installation-headline">{$headline}</span>
        </div>
    </div>
    <div id="installation-body" class="admidio-area">
        <div class="admidio-container container">
            {include file=$templateFile}

            <div id="imprint">Powered by <a href="https://www.admidio.org">Admidio</a> &copy; Admidio Team
                {if $urlImprint != ''}
                    &nbsp;&nbsp;-&nbsp;&nbsp;<a href="{$urlImprint}">{$l10n->get('SYS_IMPRINT')}</a>
                {/if}
                {if $urlDataProtection != ''}
                    &nbsp;&nbsp;-&nbsp;&nbsp;<a href="{$urlDataProtection}">{$l10n->get('SYS_DATA_PROTECTION')}</a>
                {/if}
            </div>
        </div>
    </div>
</body>
</html>
