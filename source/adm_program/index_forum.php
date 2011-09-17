<?php
/******************************************************************************
 * Index Seite des Forums
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

include("system/common.php");

// Url-Stack loeschen
$_SESSION['navigation']->clear();

// Html-Kopf ausgeben
$gLayout['title']  = "Admidio Forum";
$gLayout['header'] = '<link rel="stylesheet" href="'. THEME_PATH. '/css/overview_modules.css" type="text/css" />';

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
?>
<script type="text/javascript"> 
<!--
	notIE=document.getElementById&&!document.all
	function resizeIframe(obj, id)
	{
		var notIE = document.getElementById&&!document.all;
		var heightOffset = 0;
		var IFrameObj = document.getElementById(id);
		IFrameObj.style.height="";
		var IFrameDoc;
		if (notIE) {
			// For NS6
			IFrameDoc = IFrameObj.contentDocument; 
			IFrameObj.style.height=IFrameDoc.body.scrollHeight+(notIE?0:heightOffset)+"px";
		} else {
			// For IE5.5 and IE6 and IE7
			this.obj=obj
			this.obj.style.height=""
			setTimeout("this.obj.style.height=this.obj.contentWindow.document.body.scrollHeight+(notIE?heightOffset:0)",10)
		}
	}
// -->
</script>
<br />
<iframe id="sizeframe" name="sizeframe" style="padding:0px; margin:0px; width:<?php if ($gPreferences['forum_width']){echo $gPreferences['forum_width'];}else{echo "570";}?>px ;height:100px;" scrolling="no" frameborder="no" allowtransparency="true" background-color="transparent" marginheight="0" marginwidth="0"  src="<?php echo $gForum->url_intern; ?>" onload="resizeIframe(this, 'sizeframe');"></iframe>
<br />
<?
require(SERVER_PATH. '/adm_program/system/overall_footer.php');
?>