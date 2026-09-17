{if !isset($pwaEnabled)}
    {assign var="pwaEnabled" value=(!isset($settings) || !$settings->has('system_pwa_enabled') || $settings->getBool('system_pwa_enabled'))}
{/if}
<!DOCTYPE html>
<html lang="{$languageIsoCode}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- (c) The Admidio Team - https://www.admidio.org -->

    <link rel="shortcut icon" type="image/x-icon" href="{if ($faviconFile)}{$urlAdmidio}/{$faviconFile}{else}{get_themed_file filepath='/images/favicon.ico'}{/if}" />
    <link rel="apple-touch-icon" type="image/png" href="{get_themed_file filepath='/images/apple-touch-icon.png'}" sizes="180x180" />
    <meta name="theme-color" content="{$themeColorPrimary}" />
    {if $pwaEnabled}
    <link rel="manifest" href="{$urlAdmidio}/system/manifest.json.php" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="default" />
    <meta name="apple-mobile-web-app-title" content="{$organizationName|escape}" />
    {/if}

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

        {if $pwaEnabled}
        // Register Service Worker for PWA support
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('{$urlAdmidio}/sw.js', { scope: '{$urlAdmidio}/' })
                    .catch(function(err) {
                        console.error('ServiceWorker registration failed: ', err);
                    });
            });
        }

        var deferredPrompt = null;

        function isPwaInstalled() {
            return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        }

        function isPwaBannerDismissed() {
            try {
                return localStorage.getItem('adm_pwa_banner_dismissed') === '1';
            } catch (e) {
                return false;
            }
        }

        function dismissPwaBanner() {
            try {
                localStorage.setItem('adm_pwa_banner_dismissed', '1');
            } catch (e) {}
        }

        function isPwaInstallDismissed() {
            try {
                return localStorage.getItem('adm_pwa_install_dismissed') === '1';
            } catch (e) {
                return false;
            }
        }

        function dismissPwaInstall() {
            try {
                localStorage.setItem('adm_pwa_install_dismissed', '1');
            } catch (e) {}
        }

        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            deferredPrompt = e;
            if (!isPwaInstalled() && !isPwaInstallDismissed()) {
                $('#adm_pwa_install_container').removeClass('d-none');
                if (!isPwaBannerDismissed()) {
                    $('#adm_pwa_install_banner').removeClass('d-none');
                }
            }
        });

        window.addEventListener('appinstalled', function() {
            $('#adm_pwa_install_banner').addClass('d-none');
            $('#adm_pwa_install_container').addClass('d-none');
            $('#adm_pwa_profile_menu_item, #adm_pwa_footer_item').addClass('d-none');
            dismissPwaInstall();
            deferredPrompt = null;
        });
        {/if}

        {$javascriptContent}

        // add JavaScript code to page that will be executed after page is fully loaded
        $(function() {
            $("[data-bs-toggle=popover]").popover();
            $("[data-bs-toggle=tooltip]").tooltip();

            {if $pwaEnabled}
            var isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
            var isFirefox = /Firefox/i.test(navigator.userAgent);

            if (!isPwaInstalled()) {
                $('#adm_pwa_profile_menu_item, #adm_pwa_footer_item').removeClass('d-none');
                if (!isPwaInstallDismissed()) {
                    if (isMobile || isFirefox) {
                        $('#adm_pwa_install_container').removeClass('d-none');
                    }
                    if (isMobile && !isPwaBannerDismissed()) {
                        setTimeout(function() {
                            if (!isPwaInstalled() && !isPwaBannerDismissed() && !isPwaInstallDismissed()) {
                                $('#adm_pwa_install_banner').removeClass('d-none');
                            }
                        }, 1500);
                    }
                }
            } else {
                $('#adm_pwa_profile_menu_item, #adm_pwa_footer_item').addClass('d-none');
            }

            $(document).on('click', '#adm_pwa_banner_install_btn, #adm_pwa_install_btn, #adm_pwa_profile_install_btn, #adm_pwa_footer_install_btn', function(e) {
                e.preventDefault();
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function(choiceResult) {
                        if (choiceResult.outcome === 'accepted') {
                            $('#adm_pwa_install_banner').addClass('d-none');
                            $('#adm_pwa_install_container').addClass('d-none');
                            $('#adm_pwa_profile_menu_item, #adm_pwa_footer_item').addClass('d-none');
                            dismissPwaInstall();
                        }
                        deferredPrompt = null;
                    });
                } else {
                    var ua = navigator.userAgent;
                    var isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
                    var isFirefoxBrowser = /Firefox/i.test(ua);
                    var isMobileBrowser = /Android|iPhone|iPad|iPod/i.test(ua);

                    $('#adm_pwa_guide_ios, #adm_pwa_guide_firefox, #adm_pwa_guide_firefox_desktop, #adm_pwa_guide_browser').addClass('d-none');
                    if (isIOS) {
                        $('#adm_pwa_guide_ios').removeClass('d-none');
                    } else if (isFirefoxBrowser) {
                        if (isMobileBrowser) {
                            $('#adm_pwa_guide_firefox').removeClass('d-none');
                        } else {
                            $('#adm_pwa_guide_firefox_desktop').removeClass('d-none');
                        }
                    } else {
                        $('#adm_pwa_guide_browser').removeClass('d-none');
                    }

                    if (isPwaInstallDismissed()) {
                        $('#adm_pwa_modal_dismiss_forever').addClass('d-none');
                    } else {
                        $('#adm_pwa_modal_dismiss_forever').removeClass('d-none');
                    }

                    var modalEl = document.getElementById('adm_pwa_guide_modal');
                    if (modalEl) {
                        var modal = new bootstrap.Modal(modalEl);
                        modal.show();
                    }
                }
            });

            $(document).on('click', '#adm_pwa_banner_close', function() {
                $('#adm_pwa_install_banner').addClass('d-none');
                dismissPwaBanner();
            });

            $(document).on('click', '#adm_pwa_install_dismiss_btn, #adm_pwa_modal_dismiss_forever', function(e) {
                e.preventDefault();
                $('#adm_pwa_install_container').addClass('d-none');
                $('#adm_pwa_install_banner').addClass('d-none');
                dismissPwaInstall();
            });
            {/if}

            {$javascriptContentExecuteAtPageLoad}
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
            <div class="ms-auto d-flex align-items-center">
                {if $pwaEnabled}
                <!-- PWA Install Button -->
                <div id="adm_pwa_install_container" class="btn-group btn-group-sm me-2 d-none" role="group">
                    <button id="adm_pwa_install_btn" class="btn btn-outline-primary btn-sm" type="button" title="{$l10n->get('SYS_INSTALL_APP')}">
                        <i class="bi bi-download"></i> <span class="d-none d-sm-inline">{$l10n->get('SYS_INSTALL_APP')}</span><span class="d-sm-none">{$l10n->get('SYS_INSTALL')}</span>
                    </button>
                    <button id="adm_pwa_install_dismiss_btn" class="btn btn-outline-primary btn-sm" type="button" title="{$l10n->get('SYS_DO_NOT_SHOW_AGAIN')}" aria-label="{$l10n->get('SYS_CLOSE')}">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                {/if}

                <span id="adm_dropdown_user_photo" class="dropdown">
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
                        {if $pwaEnabled}
                        <li class="nav-item mb-2 d-none" id="adm_pwa_profile_menu_item">
                            <a class="nav-link link-primary" href="#" id="adm_pwa_profile_install_btn"><i class="bi bi-download me-1"></i> {$l10n->get('SYS_INSTALL_APP')}</a>
                        </li>
                        {/if}
                        <li class="nav-item">
                            <a class="nav-link link-primary" href="{$urlAdmidio}/system/logout.php">{$l10n->get('SYS_LOGOUT')}</a>
                        </li>
                    </ul>
                </span>
            </div>
        {else}
            <div id="adm_navbar_nav" class="collapse navbar-collapse">
                <div class="ms-auto d-flex align-items-center">
                    {if $pwaEnabled}
                    <!-- PWA Install Button -->
                    <div id="adm_pwa_install_container" class="btn-group btn-group-sm me-2 d-none" role="group">
                        <button id="adm_pwa_install_btn" class="btn btn-outline-primary btn-sm" type="button" title="{$l10n->get('SYS_INSTALL_APP')}">
                            <i class="bi bi-download"></i> <span class="d-none d-sm-inline">{$l10n->get('SYS_INSTALL_APP')}</span><span class="d-sm-none">{$l10n->get('SYS_INSTALL')}</span>
                        </button>
                        <button id="adm_pwa_install_dismiss_btn" class="btn btn-outline-primary btn-sm" type="button" title="{$l10n->get('SYS_DO_NOT_SHOW_AGAIN')}" aria-label="{$l10n->get('SYS_CLOSE')}">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    {/if}

                    <ul class="navbar-nav flex-wrap">
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
                        {if $pwaEnabled}
                            <span id="adm_pwa_footer_item" class="d-none">&nbsp;&nbsp;-&nbsp;&nbsp;<a href="#" id="adm_pwa_footer_install_btn">{$l10n->get('SYS_INSTALL_APP')}</a></span>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    {if $pwaEnabled}
    <!-- PWA Mobile Install Banner -->
    <div id="adm_pwa_install_banner" class="alert alert-light border shadow position-fixed start-50 translate-middle-x d-none" style="z-index: 1050; max-width: 95%; width: 420px; bottom: calc(12px + env(safe-area-inset-bottom, 0px));" role="alert">
        <div class="d-flex align-items-center">
            <img src="{if ($faviconFile)}{$urlAdmidio}/{$faviconFile}{else}{$urlAdmidio}/system/logo/admidio_logo_64.png{/if}" width="40" height="40" class="rounded me-3 shadow-sm" alt="App Icon" />
            <div class="flex-grow-1">
                <strong class="d-block text-dark">{$organizationName}</strong>
                <small class="text-muted">{$l10n->get('SYS_INSTALL_AS_APP')}</small>
            </div>
            <button id="adm_pwa_banner_install_btn" class="btn btn-sm btn-primary me-2"><i class="bi bi-download"></i> {$l10n->get('SYS_INSTALL')}</button>
            <button type="button" class="btn-close" id="adm_pwa_banner_close" aria-label="{$l10n->get('SYS_CLOSE')}"></button>
        </div>
    </div>

    <!-- PWA Install Guide Modal (for iOS Safari, Firefox, and browsers without beforeinstallprompt) -->
    <div class="modal fade" id="adm_pwa_guide_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-phone"></i> {$l10n->get('SYS_INSTALL_APP')}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{$l10n->get('SYS_CLOSE')}"></button>
                </div>
                <div class="modal-body" id="adm_pwa_guide_body">
                    <div id="adm_pwa_guide_ios" class="d-none">
                        <p>{$l10n->get('SYS_PWA_GUIDE_IOS_TITLE', array($organizationName))}</p>
                        <ol class="mb-0">
                            <li>{$l10n->get('SYS_PWA_GUIDE_IOS_STEP1')}</li>
                            <li>{$l10n->get('SYS_PWA_GUIDE_IOS_STEP2')}</li>
                            <li>{$l10n->get('SYS_PWA_GUIDE_IOS_STEP3')}</li>
                        </ol>
                    </div>
                    <div id="adm_pwa_guide_firefox" class="d-none">
                        <p>{$l10n->get('SYS_PWA_GUIDE_FIREFOX_TITLE', array($organizationName))}</p>
                        <ol class="mb-0">
                            <li>{$l10n->get('SYS_PWA_GUIDE_FIREFOX_STEP1')}</li>
                            <li>{$l10n->get('SYS_PWA_GUIDE_FIREFOX_STEP2')}</li>
                            <li>{$l10n->get('SYS_PWA_GUIDE_FIREFOX_STEP3')}</li>
                        </ol>
                        <div class="alert alert-info py-2 px-3 small mt-3 mb-0">
                            <i class="bi bi-info-circle me-1"></i> {$l10n->get('SYS_PWA_GUIDE_FIREFOX_TIP')}
                        </div>
                    </div>
                    <div id="adm_pwa_guide_firefox_desktop" class="d-none">
                        <p><strong>{$l10n->get('SYS_PWA_GUIDE_FIREFOX_DESKTOP_TITLE', array($organizationName))}</strong></p>
                        <p class="mb-0">{$l10n->get('SYS_PWA_GUIDE_FIREFOX_DESKTOP_DESC', array($organizationName))}</p>
                    </div>
                    <div id="adm_pwa_guide_browser" class="d-none">
                        <p>{$l10n->get('SYS_PWA_GUIDE_BROWSER_TITLE', array($organizationName))}</p>
                        <ol class="mb-0">
                            <li>{$l10n->get('SYS_PWA_GUIDE_BROWSER_STEP1')}</li>
                            <li>{$l10n->get('SYS_PWA_GUIDE_BROWSER_STEP2')}</li>
                            <li>{$l10n->get('SYS_PWA_GUIDE_BROWSER_STEP3')}</li>
                        </ol>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-link btn-sm text-muted text-decoration-none" id="adm_pwa_modal_dismiss_forever" data-bs-dismiss="modal">
                        {$l10n->get('SYS_DO_NOT_SHOW_AGAIN')}
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{$l10n->get('SYS_CLOSE')}</button>
                </div>
            </div>
        </div>
    </div>
    {/if}
</body>
</html>
