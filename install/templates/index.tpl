<!DOCTYPE html>
<html lang="{$languageIsoCode}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="author"   content="Admidio Team" />
    <meta name="robots"   content="noindex" />

    <!-- (c) 2004 - 2023 The Admidio Team - https://www.admidio.org -->

    <link rel="shortcut icon" type="image/x-icon" href="{$urlAdmidio}/system/logo/favicon.ico" />
    <link rel="icon" type="image/png" href="{$urlAdmidio}/system/logo/admidio_logo_32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="{$urlAdmidio}/system/logo/admidio_logo_16.png" sizes="16x16" />
    <link rel="apple-touch-icon" type="image/png" href="{$urlAdmidio}/system/logo/apple-touch-icon.png" sizes="180x180" />

    <title>Admidio - {$title}</title>

    {include file="js_css_files.tpl"}

    {$additionalHeaderData}

    {if count($cssFiles) > 0}
        {foreach $cssFiles as $key => $file}
            <link rel="stylesheet" type="text/css" href="{$file}" />
        {/foreach}
    {/if}
    {if count($javascriptFiles) > 0}
        {foreach $javascriptFiles as $key => $file}
            <script type="text/javascript" src="{$file}"></script>
        {/foreach}
    {/if}

    <link rel="stylesheet" type="text/css" href="{$urlAdmidio}/install/templates/installation.css" />

    <script type="text/javascript">
        {$javascriptContent}

        $(function() {
            $("[data-bs-toggle=popover]").popover();
            {$javascriptContentExecuteAtPageLoad}
        });
    </script>
</head>
<body id="{$id}" class="admidio">
    <div id="adm_installation_header" class="admidio-area">
        <div class="admidio-container container">
            <img id="adm_logo" src="{$urlAdmidio}/system/logo/admidio_writing_white_150.png" alt="Logo" />
            <span id="adm_installation_headline" class="align-middle">{$headline}</span>
        </div>
    </div>
    <div id="adm_installation_body" class="admidio-area">
        <div class="admidio-container container">
            {include file=$templateFile}

            <div id="adm_imprint">Powered by <a href="https://www.admidio.org">Admidio</a> &copy; Admidio Team
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
