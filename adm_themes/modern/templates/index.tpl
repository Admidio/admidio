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
            {$javascriptContentExecuteAtPageLoad}
        });
    </script>
</head>
<body>
    <nav class="navbar navbar-light" id="admidio-main-navbar">
      <a class="navbar-brand" href="#">
        <img class="d-none d-sm-inline" src="{$urlTheme}/images/admidio_writing_100.png" width="100" height="29" class="d-inline-block align-top" alt="">
        {$headline}
      </a>
    </nav>
</body>
</html>