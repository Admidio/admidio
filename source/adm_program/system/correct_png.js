/******************************************************************************
 * PNG-Transparenz fuer IE ermoeglichen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
function correctPNG() // correctly handle PNG transparency in Win IE 5.5 and IE 6.0
{
   var arVersion = navigator.appVersion.split("MSIE")
   var version = parseFloat(arVersion[1])
   if ((version >= 5.5) && (document.body.filters)) 
   {
		for(var i=0; i<document.images.length; i++)
		{
			var img = document.images[i]
			var imgName = img.src.toUpperCase()
			if (imgName.substring(imgName.length-3, imgName.length) == "PNG")
			{
				var imgID = (img.id) ? "id='" + img.id + "' " : ""
				var imgClass = (img.className) ? "class='" + img.className + "' " : ""
				var imgTitle = (img.title) ? "title='" + img.title + "' " : "title='" + img.alt + "' "
				var imgStyle = "display:inline-block;" + img.style.cssText
				var imgOnClick = " onclick=\"" + img.getAttributeNode("onclick").nodeValue + "\" "
				if (img.align == "left") imgStyle = "float:left;" + imgStyle
				if (img.align == "right") imgStyle = "float:right;" + imgStyle
				if (img.parentElement.href) imgStyle = "cursor:hand;" + imgStyle
				var strNewHTML = "<span " + imgID + imgClass + imgTitle + imgOnClick
				+ " style=\"/*margin: 0px; padding: 0px;*/ " + "width:" + img.width + "px; height:" 
				+ img.height + "px;" + imgStyle + ";"
				+ "filter:progid:DXImageTransform.Microsoft.AlphaImageLoader"
				+ "(src=\'" + img.src + "\', sizingMethod='scale');\"></span>"
				img.outerHTML = strNewHTML
				i = i-1
			}
		}
	}
}
window.attachEvent("onload", correctPNG);