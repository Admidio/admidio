<?php
/******************************************************************************
 * Index Seite des Forums
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Thomas Thoss
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
include("system/common.php");

// Url-Stack loeschen
$_SESSION['navigation']->clear();

// Html-Kopf ausgeben
$g_layout['title']  = "Admidio Übersicht";
$g_layout['header'] = '<link rel="stylesheet" href="'. THEME_PATH. '/css/overview_modules.css" type="text/css" />';

require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
?>

<script type="text/javascript"> 
<!--
notIE=document.getElementById&&!document.all
heightOffset=20
function resizeIframe(obj){
this.obj=obj
this.obj.style.height="" // for Firefox and Opera
setTimeout("this.obj.style.height=this.obj.contentWindow.document.body.scrollHeight+(notIE?heightOffset:0)",10) // setTimeout required for Opera
}
// -->
</script>

<iframe id="sizeframe" name="sizeframe" width="<?php echo $g_preferences['forum_width']; ?>px" height="100px" scrolling="no" frameborder="no" allowtransparency="true" background-color="transparent" src="<?php echo $g_forum->url; ?>" onload="resizeIframe(this)"></iframe>

<?
require(THEME_SERVER_PATH. "/overall_footer.php");

?>