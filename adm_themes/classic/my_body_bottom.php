
<!-- Hier koennen Sie Ihren HTML-Code einbauen, der am Ende des <body> Bereichs
     einer Admidio-Modul-Seite erscheinen soll.
-->

<?php
// Link zur Moduluebersicht
if(strpos($_SERVER['REQUEST_URI'], 'index.php') === false)
{
    echo '<div style="text-align: center; margin-top: 5px;">
        <a href="'.$gHomepage.'">'.$gL10n->get('SYS_BACK_TO_MODULE_OVERVIEW').'</a>
    </div>';
}
?>



<div style="text-align: center; margin: 15px;">
    <a href="http://www.admidio.org" target="_blank"><img 
        src="<?php echo THEME_PATH; ?>/images/admidio_logo_20.png" style="vertical-align: middle; border-width: 0px;" 
        alt="<?php echo $gL10n->get('SYS_ADMIDIO_SHORT_DESC'); ?>" title="<?php echo $gL10n->get('SYS_ADMIDIO_SHORT_DESC'); ?>" /></a>
    <span style="font-size: 9pt; vertical-align: bottom;">&nbsp;&nbsp;&copy; 2004 - 2012&nbsp;&nbsp;<?php echo $gL10n->get('SYS_ADMIDIO_TEAM'); ?></span>
</div>
