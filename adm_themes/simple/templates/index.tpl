<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- (c) 2004 - 2023 The Admidio Team - https://www.admidio.org -->

    <link rel="shortcut icon" type="image/x-icon" href="{$urlTheme}/images/favicon.ico" />
    <link rel="apple-touch-icon" type="image/png" href="{$urlTheme}/images/apple-touch-icon.png" sizes="180x180" />

    <title>{$title}</title>

    {include file="js_css_files.tpl"}

    {* Additional header informations that will be displayed if the header was set through $page->addHeader() *}
    {$additionalHeaderData}

    <link rel="stylesheet" type="text/css" href="{$urlTheme}/css/admidio.css" />

    <script type="text/javascript">
        var gRootPath  = "{$urlAdmidio}";
        var gThemePath = "{$urlTheme}";

        {$javascriptContent}

        // add javascript code to page that will be executed after page is fully loaded
        $(function() {
            $("[data-toggle=popover]").popover();
            $("[data-toggle=tooltip]").tooltip();

            // Sidebar toggle behavior
            $('#sidebarCollapse').on('click', function() {
                $('#sidebar, #content').toggleClass('active');
            });

            {$javascriptContentExecuteAtPageLoad}

            // function to handle modal window and load data from url
            $('.openPopup').on('click',function(){
                $('.modal-dialog').attr('class', 'modal-dialog ' + $(this).attr('data-class'));
                $('.modal-content').load($(this).attr('data-href'),function(){
                    $('#admidio-modal').modal({
                        show:true
                    });
                });
            });

            // remove data from modal if modal is closed
            $("body").on("hidden.bs.modal", ".modal", function() {
                $(this).removeData("bs.modal");
            });
        });
    </script>

    {* If activated in the Admidio settings a cookie note script will be integrated and show a cookie message that the user must accept *}
    {if $cookieNote}
        {include file="cookie_note.tpl"}
    {/if}
</head>
<body id="{$id}" class="admidio">
    <div id="admidio-modal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">Test</div>
        </div>
    </div>

    <nav class="navbar fixed-top navbar-light navbar-expand flex-column flex-md-row bd-navbar" id="admidio-main-navbar">
        <a class="navbar-brand" href="{$urlAdmidio}/adm_program/overview.php">
            <img class="d-none d-md-block align-top" src="{$urlTheme}/images/admidio_logo.png"
                alt="{$l10n->get('SYS_ADMIDIO_SHORT_DESC')}" title="{$l10n->get('SYS_ADMIDIO_SHORT_DESC')}">
        </a>
        <span id="headline-organization" class="d-block d-lg-none">{$organizationName}</span>
        <span id="headline-membership" class="d-none d-lg-block">{$l10n->get('SYS_ONLINE_MEMBERSHIP_ADMINISTRATION')} - {$organizationName}</span>

        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div id="navbarNav" class="collapse navbar-collapse">
            <ul class="navbar-nav ml-auto">
            {if $validLogin}
                <li class="nav-item">
                    <a class="nav-link" href="{$urlAdmidio}/adm_program/modules/profile/profile.php">{$l10n->get('PRO_MY_PROFILE')}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{$urlAdmidio}/adm_program/system/logout.php">{$l10n->get('SYS_LOGOUT')}</a>
                </li>
            {else}
                <li class="nav-item">
                    <a class="nav-link" href="{$urlAdmidio}/adm_program/system/login.php">{$l10n->get('SYS_LOGIN')}</a>
                </li>
                {if $registrationEnabled}
                    <li class="nav-item">
                        <a class="nav-link" href="{$urlAdmidio}/adm_program/modules/registration/registration.php">{$l10n->get('SYS_REGISTRATION')}</a>
                    </li>
                {/if}
            {/if}
            </ul>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row flex-xl-nowrap">
            <div class="col-12 col-md-3 col-xl-2 admidio-sidebar" id="sidebar">
                <div class="admidio-headline-mobile-menu d-md-none p-2">
                <span class="text-uppercase">{$l10n->get('SYS_MENU')}</span>
                <button class="btn btn-link d-md-none collapsed float-right" type="button" data-toggle="collapse"
                    data-target="#admidio-main-menu" aria-controls="admidio-main-menu" aria-expanded="false">
                    <i class="fas fa-bars fa-fw"></i>
                </button>
                </div>
                {$menuSidebar->getHtml()}
            </div>

            <div class="admidio-content-col col-12 col-md-9 col-xl-10">
                <nav class="admidio-breadcrumb" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        {foreach $navigationStack as $navElementArray}
                            {if !empty($navElementArray['icon'])}
                                {$breadcrumbIcon="<i class=\"admidio-icon-chain fas `$navElementArray['icon']`\"></i>"}
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

                <div id="content" class="admidio-content" role="main">
                    <div class="admidio-content-header">
                        <h1 class="admidio-module-headline">{$headline}</h1>
                        {$menuFunctions->getHtml()}
                    </div>

                    {* The main content of the page that will be generated through the Admidio scripts *}
                    {$content}

                    {* Additional template file that will be loaded if the file was set through $page->setTemplateFile() *}
                    {if $templateFile != ''}
                        {include file=$templateFile}
                    {/if}

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
        </div>
    </div>
</body>
</html>
