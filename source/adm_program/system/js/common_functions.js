/******************************************************************************
 * Allgemeine JavaScript-Funktionen, die an diversen Stellen in Admidio 
 * benoetigt werden
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
// das uebergebene Element wird optisch schick ein- und ausgeblendet
// diese Funktion kann nicht auf <table>-Elemente angewendet werden
function toggleElement(elementID, iconID)
{
    if($("#" + elementID).css("display") == "none")
    {
        $("#" + elementID).show("slow");
        if($("#" + iconID).length > 0)
        {
            $("#" + iconID).attr("src",   gThemePath + "/icons/triangle_open.gif");
            $("#" + iconID).attr("title", "Ausblenden");
            $("#" + iconID).attr("alt",   "Ausblenden");
        }
    }
    else
    {
        $("#" + elementID).hide("slow");
        if($("#" + iconID).length > 0)
        {    
            $("#" + iconID).attr("src",   gThemePath + "/icons/triangle_close.gif");
            $("#" + iconID).attr("title", "Einblenden");
            $("#" + iconID).attr("alt",   "Einblenden");
        }
    }
}

// Identisch zu toggleElement allerdings nicht so schick, 
// dafuer aber auch auf <table>-Element anwendbar
function showHideBlock(elementID)
{
	var imageID = 'img_' + elementID;
    
    if($("#" + elementID).css("display") == "none")
    {
        $("#" + elementID).css("visibility", "visible")
        $("#" + elementID).css("display", "")
        $("#" + iconID).attr("src",   gThemePath + "/icons/triangle_open.gif");
        $("#" + iconID).attr("title", "Ausblenden");
        $("#" + iconID).attr("alt",   "Ausblenden");
    }
    else
    {
        $("#" + elementID).css("visibility", "hidden")
        $("#" + elementID).css("display", "none")
        $("#" + iconID).attr("src",   gThemePath + "/icons/triangle_close.gif");
        $("#" + iconID).attr("title", "Einblenden");
        $("#" + iconID).attr("alt",   "Einblenden");
    }
}

// jQuery-Funktion welche PNG-Transparenz beim IE6 moeglich macht
// weitere Infos unter http://allinthehead.com/retro/338/supersleight-jquery-plugin
jQuery.fn.supersleight = function(settings) {
	settings = jQuery.extend({
		imgs: true,
		backgrounds: true,
		shim: 'x.gif',
		apply_positioning: true
	}, settings);
	
	return this.each(function(){
		if (jQuery.browser.msie && parseInt(jQuery.browser.version) < 7 && parseInt(jQuery.browser.version) > 4) {
			jQuery(this).find('*').andSelf().each(function(i,obj) {
				var self = jQuery(obj);
				// background pngs
				if (settings.backgrounds && self.css('background-image').match(/\.png/i) !== null) {
					var bg = self.css('background-image');
					var src = bg.substring(5,bg.length-2);
					var mode = (self.css('background-repeat') == 'no-repeat' ? 'crop' : 'scale');
					var styles = {
						'filter': "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + src + "', sizingMethod='" + mode + "')",
						'background-image': 'url('+settings.shim+')'
					};
					self.css(styles);
				};
				// image elements
				if (settings.imgs && self.is('img[src$=png]')){
					var styles = {
						'width': self.width() + 'px',
						'height': self.height() + 'px',
						'filter': "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + self.attr('src') + "', sizingMethod='scale')"
					};
					self.css(styles).attr('src', settings.shim);
				};
				// apply position to 'active' elements
				if (settings.applyPositioning && self.is('a, input') && self.css('position') === ''){
					self.css('position', 'relative');
				};
			});
		};
	});
};