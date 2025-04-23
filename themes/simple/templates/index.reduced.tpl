<!DOCTYPE html>
<html lang="{$languageIsoCode}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- (c) The Admidio Team - https://www.admidio.org -->

    <link rel="shortcut icon" type="image/x-icon" href="{$urlTheme}/images/favicon.ico" />
    <link rel="apple-touch-icon" type="image/png" href="{$urlTheme}/images/apple-touch-icon.png" sizes="180x180" />

    <title>{$title}</title>

    {include file="system/js_css_files.tpl"}

    {if $printView}
        <link rel="stylesheet" type="text/css" href="{$urlTheme}/css/print.css" />
    {else}
        <link rel="stylesheet" type="text/css" href="{$urlTheme}/css/admidio.css" />
    {/if}

    <script type="text/javascript">
        var gRootPath  = "{$urlAdmidio}";
        var gThemePath = "{$urlTheme}";

        {$javascriptContent}

        // add javascript code to page that will be executed after page is fully loaded
        $(function() {
            $("[data-bs-toggle=popover]").popover();
            $("[data-bs-toggle=tooltip]").tooltip();

            {$javascriptContentExecuteAtPageLoad}
        });
    </script>
</head>
<body id="{$id}" class="admidio admidio-reduced">
    <div id="adm_content" class="admidio-content {$contentClass}" role="main">
        {if strlen($headline) > 0}
            <div class="admidio-content-header">
                <h1 class="admidio-module-headline">{$headline}</h1>

                {if $hasPreviousUrl}
                    <!-- Add link to previous page -->
                    <a id="adm_back_link" class="" href="{$urlAdmidio}/adm_program/system/back.php"><i class="bi bi-arrow-left-circle-fill"></i> {$l10n->get('SYS_BACK')}</a>
                {/if}
            </div>
        {/if}

        {* The main content of the page that will be generated through the Admidio scripts *}
        {$content}

        {* Additional template file that will be loaded if the file was set through $page->setTemplateFile() *}
        {if $templateFile != ''}
            {include file=$templateFile}
        {/if}
    </div>
</body>
</html>
