<!DOCTYPE html>
<html>
<head>
    <!-- (c) 2004 - 2020 The Admidio Team - https://www.admidio.org -->

    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <link rel="shortcut icon" type="image/x-icon" href="{$urlTheme}/images/favicon.ico" />
    <link rel="icon" type="image/png" href="{$urlTheme}/images/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="{$urlTheme}/images/favicon-16x16.png" sizes="16x16" />
    <link rel="apple-touch-icon" type="image/png" href="{$urlTheme}/images/apple-touch-icon.png" sizes="180x180" />

    <title>{$title}</title>

    {include file="js_css_files.tpl"}

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
            $("[data-toggle=\'popover\']").popover();
            $("[data-toggle=tooltip]").tooltip();

            {$javascriptContentExecuteAtPageLoad}
        });
    </script>
</head>
<body class="admidio-reduced">
    <div class="admidio-content" id="content" role="main">
        <div class="admidio-content-header">
            <h1 class="admidio-module-headline">{$headline}</h1>

            <!-- Add link to previous page -->
            {if $urlPreviousPage != ''}
                <a class="" href="{$urlPreviousPage}"><i class="fas fa-arrow-circle-left fa-fw"></i> {$l10n->get('SYS_BACK')}</a>
            {/if}
        </div>

        {$content}
    </div>
</body>
</html>
