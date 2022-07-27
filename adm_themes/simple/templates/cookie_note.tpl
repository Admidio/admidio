<link rel="stylesheet" type="text/css" href="{$urlAdmidio}/adm_program/libs/client/cookieconsent/cookieconsent.min.css" />
<script src="{$urlAdmidio}/adm_program/libs/client/cookieconsent/cookieconsent.min.js"></script>

<script>
    window.addEventListener("load", function() {
        window.cookieconsent.initialise({
            "cookie": {
                "name": "{$cookiePrefix}_cookieconsent_status",
                "domain": "{$cookieDomain}",
                "path": "{$cookiePath}"
            },
            "content": {
                "message": "{$l10n->get('SYS_COOKIE_DESC')}",
                "dismiss": "{$l10n->get('SYS_OK')}",
                {$cookieDataProtectionUrl}
                "link": "{$l10n->get('SYS_FURTHER_INFORMATIONS')}"
            },
            "position": "bottom",
            "theme": "classic",
            "palette": {
                "popup": {
                    "background": "#252e39"
                },
                "button": {
                    "background": "#409099"
                }
            }
        });
    });
</script>
