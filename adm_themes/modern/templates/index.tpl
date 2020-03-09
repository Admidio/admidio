<!DOCTYPE html>
<html>
<head>
    <!-- (c) 2004 - 2019 The Admidio Team - https://www.admidio.org -->

    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>{$title}</title>
    
    {include file="$jsCssFiles"}
    
    <script type="text/javascript">
        var gRootPath  = "{$urlAdmidio}";
        var gThemePath = "{$urlTheme}";

        {$javascriptContent}

        // add javascript code to page that will be executed after page is fully loaded
        $(function() {
            $("[data-toggle=\'popover\']").popover();
            $("[data-toggle=tooltip]").tooltip();
            
  // Sidebar toggle behavior
  $('#sidebarCollapse').on('click', function() {
    $('#sidebar, #content').toggleClass('active');
  });
            
            {$javascriptContentExecuteAtPageLoad}
        });
    </script>
</head>
<body>
    <nav class="navbar fixed-top navbar-light navbar-expand flex-column flex-md-row bd-navbar" id="admidio-main-navbar">
      <a class="navbar-brand" href="{$urlAdmidio}/adm_program/index.php">
        <img class="d-none d-sm-inline" src="{$urlTheme}/images/admidio_logo.png" width="120" height="40" class="d-inline-block align-top" alt="">
      </a>
      {$organizationName}
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
          {if $validLogin}
            <ul class="navbar-nav ml-auto">
              <li class="nav-item">
                <a class="nav-link" href="{$urlAdmidio}/adm_program/modules/profile/profile.php">{$l10n->get('PRO_MY_PROFILE')}</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="{$urlAdmidio}/adm_program/system/logout.php">{$l10n->get('SYS_LOGOUT')}</a>
              </li>
            </ul>
          {else}
            <ul class="navbar-nav ml-auto">
              <li class="nav-item">
                <a class="nav-link" href="{$urlAdmidio}/adm_program/system/login.php">{$l10n->get('SYS_LOGIN')}</a>
              </li>
              {if $registrationEnabled}
                <li class="nav-item">
                  <a class="nav-link" href="{$urlAdmidio}/adm_program/modules/registration/registration.php">{$l10n->get('SYS_REGISTRATION')}</a>
                </li>
              {/if} 
            </ul>
          {/if}
      </div>
      <!--<img id="admidio-navbar-photo" class="rounded-circle float-right" src="{$urlAdmidio}/adm_program/modules/profile/profile_photo_show.php?usr_id={$userId}" alt="Profile picture" />-->
    </nav>
    
    <div class="container-fluid">
        <div class="row flex-xl-nowrap">
            <div class="col-12 col-md-3 col-xl-2 admidio-sidebar" id="sidebar">
                <div class="admidio-headline-mobile-menu d-md-none p-2 ml-2">
                <span class="text-uppercase">Menü</span>
                <button class="btn btn-link d-md-none collapsed" type="button" data-toggle="collapse" 
                    data-target="#admidio-main-menu" aria-controls="admidio-main-menu" aria-expanded="false">
                    <i class="fas fa-bars fa-fw"></i>
                </button>
                </div>
                {$menuSidebar}
            </div>
            
            <div class="col-12 col-md-9 col-xl-10 admidio-content" id="content" role="main">
                {$content}
            </div>
        </div>
    </div>
</body>
</html>