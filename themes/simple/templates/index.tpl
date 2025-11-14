<!DOCTYPE html>
<html lang="{$languageIsoCode}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- (c) The Admidio Team - https://www.admidio.org -->

    <link rel="shortcut icon" type="image/x-icon" href="{if ($faviconFile)}{$urlAdmidio}/{$faviconFile}{else}{get_themed_file filepath='/images/favicon.ico'}{/if}" />
    <link rel="apple-touch-icon" type="image/png" href="{get_themed_file filepath='/images/apple-touch-icon.png'}" sizes="180x180" />

    <title>{$title}</title>

    {include file="system/js_css_files.tpl"}

    {* Additional header informations that will be displayed if the header was set through $page->addHeader() *}
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
    {if count($rssFeeds) > 0}
        {foreach $rssFeeds as $title => $url}
            <link rel="alternate" type="application/rss+xml" title="{$title}" href="{$url}" />
        {/foreach}
    {/if}

    <link rel="stylesheet" type="text/css" href="{get_themed_file filepath='/css/admidio.css'}" />
    {if ($additionalStylesFile)}
    <link rel="stylesheet" type="text/css" href="{$urlAdmidio}/{$additionalStylesFile}" />
    {/if}
    {if ($additionalStyles)}<style>
        {$additionalStyles}
    </style>
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

            // function to handle modal window and load data from url
            $(document).on('click', '.openPopup', function (){
                $('#adm_modal .modal-dialog').attr('class', 'modal-dialog ' + $(this).attr('data-class'));
                $('#adm_modal .modal-content').load($(this).attr('data-href'),function(){
                    const myModal = new bootstrap.Modal($('#adm_modal'));
                    myModal.show();
                });
            });
            // function to handle modal messagebox window
            $(document).on('click', '.admidio-messagebox', function (){
                messageBox($(this).data('message'), $(this).data('title'), $(this).data('type'), $(this).data('buttons'), $(this).data('href'));
            });

            // remove data from modal if modal is closed
            $("body").on("hidden.bs.modal", ".modal", function() {
                $(this).removeData("bs.modal");
            });
        });
    </script>

    {* If activated in the Admidio settings a cookie note script will be integrated and show a cookie message that the user must accept *}
    {if $cookieNote}
        {include file="system/cookie_note.tpl"}
    {/if}
</head>
<body id="{$id}" class="admidio">
    <div id="adm_modal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content"></div>
        </div>
    </div>

    {include 'system/messagebox.tpl'}


    <nav id="adm_main_navbar" class="navbar fixed-top navbar-light navbar-expand flex-md-row bd-navbar">
        <a class="navbar-brand d-none d-md-block" href="{$urlAdmidio}/modules/overview.php">
            <img style="max-height: {$logoFileMaxHeight}px;" src="{if ($logoFile)}{$urlAdmidio}/{$logoFile}{else}{get_themed_file filepath='/images/admidio_logo.png'}{/if}" alt="{$l10n->get('SYS_ADMIDIO_SHORT_DESC')}" title="{$l10n->get('SYS_ADMIDIO_SHORT_DESC')}">
        </a>
        <span id="adm_headline_organization" class="d-block d-lg-none">{$organizationName}</span>
        <span id="adm_headline_membership" class="d-none d-lg-block">{$organizationName}{if $admidioHeadline} - {$admidioHeadline}{/if}</span>

        {if $validLogin}
            <span id="adm_dropdown_user_photo" class="dropdown ms-auto">
                <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <img id="adm_profile_photo" style="max-height: 40px; max-width: 40px;" class="rounded-circle" src="{$urlAdmidio}/modules/profile/profile_photo_show.php?user_uuid={$currentUser->getValue('usr_uuid')}&timestamp={$currentUser->getValue('usr_timestamp_change', 'Y-m-d-H-i-s')}" alt="{$l10n->get('SYS_CURRENT_PROFILE_PICTURE')}" />
                </a>
                <ul class="dropdown-menu dropdown-menu-end text-center">
                    <li class="nav-item mb-2">
                        <img id="adm_profile_photo" style="max-height: 200px; max-width: 200px;" class="rounded-circle" src="{$urlAdmidio}/modules/profile/profile_photo_show.php?user_uuid={$currentUser->getValue('usr_uuid')}&timestamp={$currentUser->getValue('usr_timestamp_change', 'Y-m-d-H-i-s')}" alt="{$l10n->get('SYS_CURRENT_PROFILE_PICTURE')}" />
                    </li>
                    <li class="nav-item mb-4">
                        {$currentUser->getValue('FIRST_NAME')} {$currentUser->getValue('LAST_NAME')}
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link link-primary" href="{$urlAdmidio}/modules/profile/profile.php">{$l10n->get('SYS_MY_PROFILE')}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link link-primary" href="{$urlAdmidio}/system/logout.php">{$l10n->get('SYS_LOGOUT')}</a>
                    </li>
                </ul>
            </span>
        {else}
            <div id="adm_navbar_nav" class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{$urlAdmidio}/system/login.php">{$l10n->get('SYS_LOGIN')}</a>
                    </li>
                    {if $registrationEnabled}
                        <li class="nav-item">
                            <a class="nav-link" href="{$urlAdmidio}/modules/registration.php">{$l10n->get('SYS_REGISTER')}</a>
                        </li>
                    {/if}
                </ul>
            </div>
        {/if}
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adm_navbar_nav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
    </nav>

    <div class="container-fluid">
        <div class="row flex-xl-nowrap">
            <div id="adm_sidebar" class="col-12 col-md-3 col-xl-2 admidio-sidebar">
                {include file='sys-template-parts/menu.main.tpl'}
            </div>

            <div class="admidio-content-col col-12 col-md-9 col-xl-10">
                <nav class="admidio-breadcrumb" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        {foreach $navigationStack as $navElementArray}
                            {if !empty($navElementArray['icon'])}
                                {$breadcrumbIcon="<i class=\"admidio-icon-chain bi `$navElementArray['icon']`\"></i>"}
                            {else}
                                {$breadcrumbIcon=''}
                            {/if}
                            {if $navElementArray@iteration == $navElementArray@last}
                                <li class="breadcrumb-item active">{$breadcrumbIcon}{$navElementArray['text']}</li>
                            {else}
                                <li class="breadcrumb-item"><a href="{$navElementArray['url']}">{$breadcrumbIcon}{$navElementArray['text']}</a></li>
                            {/if}
                        {/foreach}
                    </ol>
                </nav>

                <div id="adm_content" class="admidio-content {$contentClass}" role="main">
                    <div class="admidio-content-header">
                        <h1 class="admidio-module-headline">{$headline}</h1>
                        {include file='sys-template-parts/menu.functions.tpl'}
                    </div>

                    {* The main content of the page that will be generated through the Admidio scripts *}
                    {$content}

                    {* Additional template file that will be loaded if the file was set through $page->setTemplateFile() *}
                    {if $templateFile != ''}
                        {include file=$templateFile}
                    {/if}

                    <div id="adm_imprint">Powered by <a href="https://www.admidio.org">Admidio</a> &copy; Admidio Team
                        {if $urlImprint != ''}
                            &nbsp;&nbsp;-&nbsp;&nbsp;<a href="{$urlImprint}">{$l10n->get('SYS_IMPRINT')}</a>
                        {/if}
                        {if $urlDataProtection != ''}
                            &nbsp;&nbsp;-&nbsp;&nbsp;<a href="{$urlDataProtection}">{$l10n->get('SYS_DATA_PROTECTION')}</a>
                        {/if}
                        {if count($rssFeeds) > 0}
                            &nbsp;&nbsp;-&nbsp;&nbsp;
                            {foreach $rssFeeds as $title => $url}
                                <a href="{$url}" title="{$title}"><i class="bi bi-rss-fill"></i></a>
                            {/foreach}
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
