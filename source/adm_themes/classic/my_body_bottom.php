
<!-- Hier koennen Sie Ihren HTML-Code einbauen, der am Ende des <body> Bereichs
     einer Admidio-Modul-Seite erscheinen soll.
-->

<?
// Link zur Moduluebersicht
if(strpos($_SERVER['REQUEST_URI'], 'index.php') === false)
{
    echo '<div style="text-align: center; margin-top: 5px;">
        <a href="'.$g_homepage.'">Zurück zur Modulübersicht</a>
    </div>';
}
?>



<div style="text-align: center; margin: 15px;">
    <a href="http://www.admidio.org" target="_blank"><img 
        src="<?php echo THEME_PATH; ?>/images/admidio_logo_20.png" style="vertical-align: middle; border-width: 0px;" 
        alt="Die Online-Mitgliederverwaltung für Vereine, Gruppen und Organisationen"
        title="Die Online-Mitgliederverwaltung für Vereine, Gruppen und Organisationen" /></a>
    <span style="font-size: 9pt; vertical-align: bottom;">&nbsp;&nbsp;&copy; 2004 - 2009&nbsp;&nbsp;Admidio Team</span>
</div>
