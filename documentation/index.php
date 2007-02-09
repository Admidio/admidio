<?php

if(strlen($_SERVER['QUERY_STRING']) > 0)
{
    // Übergabevariablen hier erstellen
    $main_uebergabe = substr(strchr($_SERVER['QUERY_STRING'], "?"), 1);
    parse_str($main_uebergabe);
    

    $local_path = substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], 'index.php'));

    // die uebergebene Datei linken
    if(strpos($_SERVER['QUERY_STRING'], "?") > 0)
    {
        $main_link_seite = "http://". $_SERVER['HTTP_HOST']. $local_path. substr($_SERVER['QUERY_STRING'], 0, strpos($_SERVER['QUERY_STRING'], "?"));
    }
    else
    {
        $main_link_seite = "http://". $_SERVER['HTTP_HOST']. $local_path. $_SERVER['QUERY_STRING'];
    }
}
else
{
    $main_link_seite = "help/help.php";
}
?>

<? echo "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?". ">\n"; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="de" lang="de">
<head>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
   <meta http-equiv="Content-Language" content="de" />
   <meta name="description" content="Admidio ist eine kostenlose Online-Mitgliederverwaltung, die f&uuml;r Vereine, Gruppen und Organisationen optimiert ist. Sie besteht aus einer Vielzahl an Modulen, die in eine neue oder bestehende Homepage eingebaut und angepasst werden k&ouml;nnen." />
   <meta name="keywords"    content="admidio, mitgliederverwaltung, benutzerverwaltung, verwaltung, php, system, cms, webmaster, verein, gruppierung, organisation, termine, fotos, download" />
   <meta name="author"      content="Markus Fassbender" />
   <meta name="robots"      content="index,follow" />
   <meta name="language"    content="de" />

   <title>Admidio - Die Online-Mitgliederverwaltung (PHP) für Vereine, Gruppen und Organisationen</title>

   <link rel="stylesheet" type="text/css" href="content.css" />

    <!--[if lt IE 7]>
       <script type="text/javascript" src="correct_png.js"></script>
    <![endif]-->
</head>
<body>
   <table style="width: 750px; border: 0px; text-align: center; margin: auto;" cellpadding="0" cellspacing="0">
      <tr>
         <td style="width: 20px;"><img src="help/images/gr_tl.png" style="display: block;" alt="tl" /></td>
         <td style="background-image:url(help/images/gr_t.png); background-repeat:repeat-x;"></td>
         <td style="width: 20px;"><img src="help/images/gr_tr.png" style="display: block;" alt="tr" /></td>
      </tr>
      <tr>
         <td style="background-image:url(help/images/gr_l.png); background-repeat:repeat-y;"></td>
         <td style="background-color: #85C226;">
            <div style="float: left;">
               <a href="index.php">
                  <img style="border: 0px;" src="help/images/admidio_logo_75.png" alt="Admidio - Die Online-Mitgliederverwaltung für Vereine, Gruppen und Organisationen" />
               </a>
            </div>
            <div style="margin-left: 200px; padding-top: 10px; padding-bottom: 0px;">
               <span style="font-size: 20pt; font-weight: bold;">Die Online- &amp; Offlinehilfe</span><br />
               <span style="font-size: 12pt; font-weight: bold;">für Admidio</span><br />
               <a class="menu" href="index.php?help/help.php">Hilfe</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
               <a class="menu" href="index.php?download/download.php">Download</a>
            </div>
         </td>
         <td style="background-image:url(help/images/gr_r.png); background-repeat:repeat-y;"></td>
      </tr>
      <tr>
         <td style="width: 20px;"><img src="help/images/gr_bl.png" style="display: block;" alt="bl" /></td>
         <td style="background-image:url(help/images/gr_b.png); background-repeat:repeat-x;"></td>
         <td style="width: 20px;"><img src="help/images/gr_br.png" style="display: block;" alt="br" /></td>
      </tr>
   </table>

   <table style="width: 750px; border: 0px; text-align: center; margin: auto; margin-top: 20px;" cellpadding="0" cellspacing="0">
      <tr>
         <td style="width: 20px;"><img src="help/images/bl_tl.png" style="display: block;" alt="tl" /></td>
         <td style="background-image:url(help/images/bl_t.png); background-repeat:repeat-x;"></td>
         <td style="background-image:url(help/images/wi_t.png); background-repeat:repeat-x;"></td>
         <td style="width: 20px;"><img src="help/images/wi_tr.png" style="display: block;" alt="tr" /></td>
      </tr>
      <tr>
         <td style="background-image:url(help/images/bl_l.png); background-repeat:repeat-y;"></td>
         <?php require($main_link_seite); ?>
         <td style="background-image:url(help/images/wi_r.png); background-repeat:repeat-y;"></td>
      </tr>
      <tr>
         <td style="width: 20px;"><img src="help/images/bl_bl.png" style="display: block;" alt="bl" /></td>
         <td style="background-image:url(help/images/bl_b.png); background-repeat:repeat-x;"></td>
         <td style="background-image:url(help/images/wi_b.png); background-repeat:repeat-x;"></td>
         <td style="width: 20px;"><img src="help/images/wi_br.png" style="display: block;" alt="br" /></td>
      </tr>
   </table>

   <p style="text-align: center;">
         <img style="border: 0px; vertical-align: middle;" src="help/images/admidio_logo_20.png" alt="Das Online-Verwaltungssystem für Vereine, Gruppen und Organisationen" />
         <span style="font-size: 9pt; color: #85C226">&nbsp;&nbsp;&copy; 2004 - <? echo date('Y', time()); ?>&nbsp;&nbsp;Admidio Team - <a href="index.php?impressum.php">Impressum</a></span>
   </p>
</body>
</html>