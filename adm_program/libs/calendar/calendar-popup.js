// ===================================================================
// Author: Matt Kruse <matt@mattkruse.com>
// WWW: http://www.mattkruse.com/
// ===================================================================

// Funktion prueft, ob das Von-Datum groesser als das Bis-Datum ist und
// passt ggf. das Bis-Datum an
function check(n_from, n_to, format)
{
    var dateFrom = Date.parseDate(document.getElementById(n_from).value, format);
    var dateTo   = Date.parseDate(document.getElementById(n_to).value, format);

    if(dateFrom.getTime() > dateTo.getTime())
    {
        document.getElementById(n_to).value = document.getElementById(n_from).value;
        from = to;
    }
}

// getAnchorPosition(anchorname)
//   This function returns an object having .x and .y properties which are the coordinates
//   of the named anchor, relative to the page.

function getAnchorPosition(anchorname) {
	// This function will return an Object with x and y properties
	var useWindow=false;
	var coordinates=new Object();
	var x=0,y=0;
	// Browser capability sniffing
	var use_gebi=false, use_css=false, use_layers=false;
	if (document.getElementById) { use_gebi=true; }
	else if (document.all) { use_css=true; }
	else if (document.layers) { use_layers=true; }
	// Logic to find position
 	if (use_gebi && document.all) {
		x=AnchorPosition_getPageOffsetLeft(document.all[anchorname]);
		y=AnchorPosition_getPageOffsetTop(document.all[anchorname]);
		}
	else if (use_gebi) {
		var o=document.getElementById(anchorname);
		x=AnchorPosition_getPageOffsetLeft(o);
		y=AnchorPosition_getPageOffsetTop(o);
		}
 	else if (use_css) {
		x=AnchorPosition_getPageOffsetLeft(document.all[anchorname]);
		y=AnchorPosition_getPageOffsetTop(document.all[anchorname]);
		}
	else if (use_layers) {
		var found=0;
		for (var i=0; i<document.anchors.length; i++) {
			if (document.anchors[i].name==anchorname) { found=1; break; }
			}
		if (found==0) {
			coordinates.x=0; coordinates.y=0; return coordinates;
			}
		x=document.anchors[i].x;
		y=document.anchors[i].y;
		}
	else {
		coordinates.x=0; coordinates.y=0; return coordinates;
		}
	coordinates.x=x;
	coordinates.y=y;
	return coordinates;
	}

// getAnchorWindowPosition(anchorname)
//   This function returns an object having .x and .y properties which are the coordinates
//   of the named anchor, relative to the window
function getAnchorWindowPosition(anchorname) {
	var coordinates=getAnchorPosition(anchorname);
	var x=0;
	var y=0;
	if (document.getElementById) {
		if (isNaN(window.screenX)) {
			x=coordinates.x-document.body.scrollLeft+window.screenLeft;
			y=coordinates.y-document.body.scrollTop+window.screenTop;
			}
		else {
			x=coordinates.x+window.screenX+(window.outerWidth-window.innerWidth)-window.pageXOffset;
			y=coordinates.y+window.screenY+(window.outerHeight-24-window.innerHeight)-window.pageYOffset;
			}
		}
	else if (document.all) {
		x=coordinates.x-document.body.scrollLeft+window.screenLeft;
		y=coordinates.y-document.body.scrollTop+window.screenTop;
		}
	else if (document.layers) {
		x=coordinates.x+window.screenX+(window.outerWidth-window.innerWidth)-window.pageXOffset;
		y=coordinates.y+window.screenY+(window.outerHeight-24-window.innerHeight)-window.pageYOffset;
		}
	coordinates.x=x;
	coordinates.y=y;
	return coordinates;
	}

// Functions for IE to get position of an object
function AnchorPosition_getPageOffsetLeft (el) {
	var ol=el.offsetLeft;
	while ((el=el.offsetParent) != null) { ol += el.offsetLeft; }
	return ol;
	}
function AnchorPosition_getWindowOffsetLeft (el) {
	return AnchorPosition_getPageOffsetLeft(el)-document.body.scrollLeft;
	}
function AnchorPosition_getPageOffsetTop (el) {
	var ot=el.offsetTop;
	while((el=el.offsetParent) != null) { ot += el.offsetTop; }
	return ot;
	}
function AnchorPosition_getWindowOffsetTop (el) {
	return AnchorPosition_getPageOffsetTop(el)-document.body.scrollTop;
	}

//var gMonthNames=new Array('Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember','Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez');
function LZ(x) {return(x<0||x>9?"":"0")+x}

// ------------------------------------------------------------------
// isDate ( date_string, format_string )
// Returns true if date string matches format of format string and
// is a valid date. Else returns false.
// It is recommended that you trim whitespace around the value before
// passing it to this function, as whitespace is NOT ignored!
// ------------------------------------------------------------------
function isDate(val,format) {
	var date=getDateFromFormat(val,format);
	if (date==0) { return false; }
	return true;
	}

// -------------------------------------------------------------------
// compareDates(date1,date1format,date2,date2format)
//   Compare two date strings to see which is greater.
//   Returns:
//   1 if date1 is greater than date2
//   0 if date2 is greater than date1 of if they are the same
//  -1 if either of the dates is in an invalid format
// -------------------------------------------------------------------
function compareDates(date1,dateformat1,date2,dateformat2) {
	var d1=getDateFromFormat(date1,dateformat1);
	var d2=getDateFromFormat(date2,dateformat2);
	if (d1==0 || d2==0) {
		return -1;
		}
	else if (d1 > d2) {
		return 1;
		}
	return 0;
	}

// ------------------------------------------------------------------
// Utility functions for parsing in getDateFromFormat()
// ------------------------------------------------------------------
function _isInteger(val) {
	var digits="1234567890";
	for (var i=0; i < val.length; i++) {
		if (digits.indexOf(val.charAt(i))==-1) { return false; }
		}
	return true;
	}
function _getInt(str,i,minlength,maxlength) {
	for (var x=maxlength; x>=minlength; x--) {
		var token=str.substring(i,i+x);
		if (token.length < minlength) { return null; }
		if (_isInteger(token)) { return token; }
		}
	return null;
	}

// ------------------------------------------------------------------
// getDateFromFormat( date_string , format_string )
//
// This function takes a date string and a format string. It matches
// If the date string matches the format string, it returns the
// getTime() of the date. If it does not match, it returns 0.
// ------------------------------------------------------------------
function getDateFromFormat(val,format) {
	var newDate = Date.parseDate(val, format);
	return newDate.getTime();
	}

// ------------------------------------------------------------------
// parseDate( date_string [, prefer_euro_format] )
//
// This function takes a date string and tries to match it to a
// number of possible date formats to get the value. It will try to
// match against the following international formats, in this order:
// y-M-d   MMM d, y   MMM d,y   y-MMM-d   d-MMM-y  MMM d
// M/d/y   M-d-y      M.d.y     MMM-d     M/d      M-d
// d/M/y   d-M-y      d.M.y     d-MMM     d/M      d-M
// A second argument may be passed to instruct the method to search
// for formats like d/M/y (european format) before M/d/y (American).
// Returns a Date object or null if no patterns match.
// ------------------------------------------------------------------
function parseDate(val) {
	var preferEuro=(arguments.length==2)?arguments[1]:false;
	generalFormats=new Array('y-M-d','MMM d, y','MMM d,y','y-MMM-d','d-MMM-y','MMM d');
	monthFirst=new Array('M/d/y','M-d-y','M.d.y','MMM-d','M/d','M-d');
	dateFirst =new Array('d/M/y','d-M-y','d.M.y','d-MMM','d/M','d-M');
	var checkList=new Array('generalFormats',preferEuro?'dateFirst':'monthFirst',preferEuro?'monthFirst':'dateFirst');
	var d=null;
	for (var i=0; i<checkList.length; i++) {
		var l=window[checkList[i]];
		for (var j=0; j<l.length; j++) {
			d=getDateFromFormat(val,l[j]);
			if (d!=0) { return new Date(d); }
			}
		}
	return null;
	}

/* SOURCE FILE: PopupWindow.js */

// Set the position of the popup window based on the anchor
function PopupWindow_getXYPosition(anchorname) {
	var coordinates;
	if (this.type == "WINDOW") {
		coordinates = getAnchorWindowPosition(anchorname);
		}
	else {
		coordinates = getAnchorPosition(anchorname);
		}
	this.x = coordinates.x;
	this.y = coordinates.y;
	}
// Set width/height of DIV/popup window
function PopupWindow_setSize(width,height) {
	this.width = width;
	this.height = height;
	}
// Fill the window with contents
function PopupWindow_populate(contents) {
	this.contents = contents;
	this.populated = false;
	}
// Set the URL to go to
function PopupWindow_setUrl(url) {
	this.url = url;
	}
// Set the window popup properties
function PopupWindow_setWindowProperties(props) {
	this.windowProperties = props;
	}
// Refresh the displayed contents of the popup
function PopupWindow_refresh() {
	if (this.divName != null) {
		// refresh the DIV object
		if (this.use_gebi) {
			document.getElementById(this.divName).innerHTML = this.contents;
			}
		else if (this.use_css) {
			document.all[this.divName].innerHTML = this.contents;
			}
		else if (this.use_layers) {
			var d = document.layers[this.divName];
			d.document.open();
			d.document.writeln(this.contents);
			d.document.close();
			}
		}
	else {
		if (this.popupWindow != null && !this.popupWindow.closed) {
			if (this.url!="") {
				this.popupWindow.location.href=this.url;
				}
			else {
				this.popupWindow.document.open();
				this.popupWindow.document.writeln(this.contents);
				this.popupWindow.document.close();
			}
			this.popupWindow.focus();
			}
		}
	}
// Position and show the popup, relative to an anchor object
function PopupWindow_showPopup(anchorname) {
	this.getXYPosition(anchorname);
	this.x += this.offsetX;
	this.y += this.offsetY;
	if (!this.populated && (this.contents != "")) {
		this.populated = true;
		this.refresh();
		}
	if (this.divName != null) {
		// Show the DIV object
		if (this.use_gebi) {
			document.getElementById(this.divName).style.left = this.x + "px";
			document.getElementById(this.divName).style.top = this.y + "px";
			document.getElementById(this.divName).style.visibility = "visible";
			}
		else if (this.use_css) {
			document.all[this.divName].style.left = this.x;
			document.all[this.divName].style.top = this.y;
			document.all[this.divName].style.visibility = "visible";
			}
		else if (this.use_layers) {
			document.layers[this.divName].left = this.x;
			document.layers[this.divName].top = this.y;
			document.layers[this.divName].visibility = "visible";
			}
		}
	else {
		if (this.popupWindow == null || this.popupWindow.closed) {
			// If the popup window will go off-screen, move it so it doesn't
			if (this.x<0) { this.x=0; }
			if (this.y<0) { this.y=0; }
			if (screen && screen.availHeight) {
				if ((this.y + this.height) > screen.availHeight) {
					this.y = screen.availHeight - this.height;
					}
				}
			if (screen && screen.availWidth) {
				if ((this.x + this.width) > screen.availWidth) {
					this.x = screen.availWidth - this.width;
					}
				}
			var avoidAboutBlank = window.opera || ( document.layers && !navigator.mimeTypes['*'] ) || navigator.vendor == 'KDE' || ( document.childNodes && !document.all && !navigator.taintEnabled );
			this.popupWindow = window.open(avoidAboutBlank?"":"about:blank","window_"+anchorname,this.windowProperties+",width="+this.width+",height="+this.height+",screenX="+this.x+",left="+this.x+",screenY="+this.y+",top="+this.y+"");
			}
		this.refresh();
		}
	}
// Hide the popup
function PopupWindow_hidePopup() {
	if (this.divName != null) {
		if (this.use_gebi) {
			document.getElementById(this.divName).style.visibility = "hidden";
			}
		else if (this.use_css) {
			document.all[this.divName].style.visibility = "hidden";
			}
		else if (this.use_layers) {
			document.layers[this.divName].visibility = "hidden";
			}
		}
	else {
		if (this.popupWindow && !this.popupWindow.closed) {
			this.popupWindow.close();
			this.popupWindow = null;
			}
		}
	}
// Pass an event and return whether or not it was the popup DIV that was clicked
function PopupWindow_isClicked(e) {
	if (this.divName != null) {
		if (this.use_layers) {
			var clickX = e.pageX;
			var clickY = e.pageY;
			var t = document.layers[this.divName];
			if ((clickX > t.left) && (clickX < t.left+t.clip.width) && (clickY > t.top) && (clickY < t.top+t.clip.height)) {
				return true;
				}
			else { return false; }
			}
		else if (document.all) { // Need to hard-code this to trap IE for error-handling
			var t = window.event.srcElement;
			while (t.parentElement != null) {
				if (t.id==this.divName) {
					return true;
					}
				t = t.parentElement;
				}
			return false;
			}
		else if (this.use_gebi && e) {
				var t = e.originalTarget;
				if(t == "[object HTMLImageElement]" || ( t != "[object HTMLDivElement]" && t != "[object HTMLOptionElement]" && t != "[object HTMLSelectElement]") )
				{
					while (t.parentNode != null) 
					{
						if (t.id==this.divName) 
						{
							return true;
						}
						t = t.parentNode;
					}
					return false;
				}
				else
				{
					return true;
				}
			}
		return false;
		}
	return false;
	}

// Check an onMouseDown event to see if we should hide
function PopupWindow_hideIfNotClicked(e) {
	if (this.autoHideEnabled && !this.isClicked(e)) {
		this.hidePopup();
		}
	}
// Call this to make the DIV disable automatically when mouse is clicked outside it
function PopupWindow_autoHide() {
	this.autoHideEnabled = true;
	}
// This global function checks all PopupWindow objects onmouseup to see if they should be hidden
function PopupWindow_hidePopupWindows(e) {
	for (var i=0; i<popupWindowObjects.length; i++) {
		if (popupWindowObjects[i] != null) {
			var p = popupWindowObjects[i];
			p.hideIfNotClicked(e);
			}
		}
	}
// Run this immediately to attach the event listener
function PopupWindow_attachListener() {
	if (document.layers) {
		document.captureEvents(Event.MOUSEUP);
		}
	window.popupWindowOldEventListener = document.onmouseup;
	if (window.popupWindowOldEventListener != null) {
		document.onmouseup = new Function("window.popupWindowOldEventListener(); PopupWindow_hidePopupWindows();");
		}
	else {
		document.onmouseup = PopupWindow_hidePopupWindows;
		}
	}
// CONSTRUCTOR for the PopupWindow object
// Pass it a DIV name to use a DHTML popup, otherwise will default to window popup
function PopupWindow() {
	if (!window.popupWindowIndex) { window.popupWindowIndex = 0; }
	if (!window.popupWindowObjects) { window.popupWindowObjects = new Array(); }
	if (!window.listenerAttached) {
		window.listenerAttached = true;
		PopupWindow_attachListener();
		}
	this.index = popupWindowIndex++;
	popupWindowObjects[this.index] = this;
	this.divName = null;
	this.popupWindow = null;
	this.width=0;
	this.height=0;
	this.populated = false;
	this.visible = false;
	this.autoHideEnabled = false;

	this.contents = "";
	this.url="";
	this.windowProperties="toolbar=no,location=no,status=no,menubar=no,scrollbars=auto,resizable,alwaysRaised,dependent,titlebar=no";
	if (arguments.length>0) {
		this.type="DIV";
		this.divName = arguments[0];
		}
	else {
		this.type="WINDOW";
		}
	this.use_gebi = false;
	this.use_css = false;
	this.use_layers = false;
	if (document.getElementById) { this.use_gebi = true; }
	else if (document.all) { this.use_css = true; }
	else if (document.layers) { this.use_layers = true; }
	else { this.type = "WINDOW"; }
	this.offsetX = 0;
	this.offsetY = 0;
	// Method mappings
	this.getXYPosition = PopupWindow_getXYPosition;
	this.populate = PopupWindow_populate;
	this.setUrl = PopupWindow_setUrl;
	this.setWindowProperties = PopupWindow_setWindowProperties;
	this.refresh = PopupWindow_refresh;
	this.showPopup = PopupWindow_showPopup;
	this.hidePopup = PopupWindow_hidePopup;
	this.setSize = PopupWindow_setSize;
	this.isClicked = PopupWindow_isClicked;
	this.autoHide = PopupWindow_autoHide;
	this.hideIfNotClicked = PopupWindow_hideIfNotClicked;
	}

/* SOURCE FILE: CalendarPopup.js */

// CONSTRUCTOR for the CalendarPopup Object
function CalendarPopup() {
	var c;
	if (arguments.length>0) {
		c = new PopupWindow(arguments[0]);
		}
	else {
		c = new PopupWindow();
		c.setSize(150,175);
		}
	c.offsetX = 20;
	c.offsetY = -60;
	c.autoHide();
	// Calendar-specific properties
	c.monthNames = new Array(gMonthNames[0],gMonthNames[1],gMonthNames[2],gMonthNames[3],gMonthNames[4],gMonthNames[5],gMonthNames[6],gMonthNames[7],gMonthNames[8],gMonthNames[9],gMonthNames[10],gMonthNames[11]);
	c.monthAbbreviations = new Array(gMonthNames[12],gMonthNames[13],gMonthNames[14],gMonthNames[15],gMonthNames[16],gMonthNames[17],gMonthNames[18],gMonthNames[19],gMonthNames[20],gMonthNames[21],gMonthNames[22],gMonthNames[23]);
	c.dayHeaders = new Array(gTranslations[6],gTranslations[0],gTranslations[1],gTranslations[2],gTranslations[3],gTranslations[4],gTranslations[5]);
	c.returnFunction = "CP_tmpReturnFunction";
	c.returnMonthFunction = "CP_tmpReturnMonthFunction";
	c.returnQuarterFunction = "CP_tmpReturnQuarterFunction";
	c.returnYearFunction = "CP_tmpReturnYearFunction";
	c.weekStartDay = 1;
	c.isShowYearNavigation = false;
	c.displayType = "date";
	c.disabledWeekDays = new Object();
	c.disabledDatesExpression = "";
	c.yearSelectStartOffset = 30;
    c.yearSelectEndOffset = 10;
	c.currentDate = null;
	c.todayText=gTranslations[7];
	c.cssPrefix="";
	c.isShowNavigationDropdowns=false;
	c.isShowYearNavigationInput=false;
    c.date_from = "";
	c.date_to = "";
	c.time_from = "";
	c.time_to = "";
	window.CP_calendarObject = null;
	window.CP_targetInput = null;
	window.CP_dateFormat = "dd.mm.yyyy";
	// Method mappings
	c.copyMonthNamesToWindow = CP_copyMonthNamesToWindow;
	c.setReturnFunction = CP_setReturnFunction;
	c.setReturnMonthFunction = CP_setReturnMonthFunction;
	c.setReturnQuarterFunction = CP_setReturnQuarterFunction;
	c.setReturnYearFunction = CP_setReturnYearFunction;
	c.setMonthNames = CP_setMonthNames;
	c.setMonthAbbreviations = CP_setMonthAbbreviations;
	c.setDayHeaders = CP_setDayHeaders;
	c.setWeekStartDay = CP_setWeekStartDay;
	c.setDisplayType = CP_setDisplayType;
	c.setDisabledWeekDays = CP_setDisabledWeekDays;
	c.addDisabledDates = CP_addDisabledDates;
	c.setYearSelectStartOffset = CP_setYearSelectStartOffset;
    c.setYearSelectEndOffset = CP_setYearSelectEndOffset;
	c.setTodayText = CP_setTodayText;
	c.showYearNavigation = CP_showYearNavigation;
	c.showCalendar = CP_showCalendar;
	c.hideCalendar = CP_hideCalendar;
	c.getStyles = getCalendarStyles;
	c.refreshCalendar = CP_refreshCalendar;
	c.getCalendar = CP_getCalendar;
	c.select = CP_select;
	c.setCssPrefix = CP_setCssPrefix;
	c.showNavigationDropdowns = CP_showNavigationDropdowns;
	c.showYearNavigationInput = CP_showYearNavigationInput;
	c.copyMonthNamesToWindow();
	// Return the object
	return c;
	}
function CP_copyMonthNamesToWindow() {
	// Copy these values over to the date.js
	if (typeof(window.gMonthNames)!="undefined" && window.gMonthNames!=null) {
		window.gMonthNames = new Array();
		for (var i=0; i<this.monthNames.length; i++) {
			window.gMonthNames[window.gMonthNames.length] = this.monthNames[i];
		}
		for (var i=0; i<this.monthAbbreviations.length; i++) {
			window.gMonthNames[window.gMonthNames.length] = this.monthAbbreviations[i];
		}
	}
}
// Temporary default functions to be called when items clicked, so no error is thrown
function CP_tmpReturnFunction(y,m,d) {
	if (window.CP_targetInput!=null) {
		var dt = new Date(y,m-1,d,0,0,0);
		if (window.CP_calendarObject!=null) { window.CP_calendarObject.copyMonthNamesToWindow(); }
		window.CP_targetInput.value = dt.dateFormat(window.CP_dateFormat);
		}
	else {
		alert('Use setReturnFunction() to define which function will get the clicked results!');
		}
	}
function CP_tmpReturnMonthFunction(y,m) {
	alert('Use setReturnMonthFunction() to define which function will get the clicked results!\nYou clicked: year='+y+' , month='+m);
	}
function CP_tmpReturnQuarterFunction(y,q) {
	alert('Use setReturnQuarterFunction() to define which function will get the clicked results!\nYou clicked: year='+y+' , quarter='+q);
	}
function CP_tmpReturnYearFunction(y) {
	alert('Use setReturnYearFunction() to define which function will get the clicked results!\nYou clicked: year='+y);
	}

// Set the name of the functions to call to get the clicked item
function CP_setReturnFunction(name) { this.returnFunction = name; }
function CP_setReturnMonthFunction(name) { this.returnMonthFunction = name; }
function CP_setReturnQuarterFunction(name) { this.returnQuarterFunction = name; }
function CP_setReturnYearFunction(name) { this.returnYearFunction = name; }

// Over-ride the built-in month names
function CP_setMonthNames() {
	for (var i=0; i<arguments.length; i++) { this.monthNames[i] = arguments[i]; }
	this.copyMonthNamesToWindow();
	}

// Over-ride the built-in month abbreviations
function CP_setMonthAbbreviations() {
	for (var i=0; i<arguments.length; i++) { this.monthAbbreviations[i] = arguments[i]; }
	this.copyMonthNamesToWindow();
	}

// Over-ride the built-in column headers for each day
function CP_setDayHeaders() {
	for (var i=0; i<arguments.length; i++) { this.dayHeaders[i] = arguments[i]; }
	}

// Set the day of the week (0-7) that the calendar display starts on
// This is for countries other than the US whose calendar displays start on Monday(1), for example
function CP_setWeekStartDay(day) { this.weekStartDay = day; }

// Show next/last year navigation links
function CP_showYearNavigation() { this.isShowYearNavigation = (arguments.length>0)?arguments[0]:true; }

// Which type of calendar to display
function CP_setDisplayType(type) {
	if (type!="date"&&type!="week-end"&&type!="month"&&type!="quarter"&&type!="year") { alert("Invalid display type! Must be one of: date,week-end,month,quarter,year"); return false; }
	this.displayType=type;
	}

// How many years back to start by default for year display
function CP_setYearSelectStartOffset(num) { this.yearSelectStartOffset=num; }

// How many years forward to end by default for year display
function CP_setYearSelectEndOffset(num) { this.yearSelectEndOffset=num; }

// Set which weekdays should not be clickable
function CP_setDisabledWeekDays() {
	this.disabledWeekDays = new Object();
	for (var i=0; i<arguments.length; i++) { this.disabledWeekDays[arguments[i]] = true; }
	}

// Disable individual dates or ranges
// Builds an internal logical test which is run via eval() for efficiency
function CP_addDisabledDates(start, end) {
	if (arguments.length==1) { end=start; }
	if (start==null && end==null) { return; }
	if (this.disabledDatesExpression!="") { this.disabledDatesExpression+= "||"; }
	if (start!=null) { start = parseDate(start); start=""+start.getFullYear()+LZ(start.getMonth()+1)+LZ(start.getDate());}
	if (end!=null) { end=parseDate(end); end=""+end.getFullYear()+LZ(end.getMonth()+1)+LZ(end.getDate());}
	if (start==null) { this.disabledDatesExpression+="(ds<="+end+")"; }
	else if (end  ==null) { this.disabledDatesExpression+="(ds>="+start+")"; }
	else { this.disabledDatesExpression+="(ds>="+start+"&&ds<="+end+")"; }
	}

// Set the text to use for the "Today" link
function CP_setTodayText(text) {
	this.todayText = text;
	}

// Set the prefix to be added to all CSS classes when writing output
function CP_setCssPrefix(val) {
	this.cssPrefix = val;
	}

// Show the navigation as an dropdowns that can be manually changed
function CP_showNavigationDropdowns() { this.isShowNavigationDropdowns = (arguments.length>0)?arguments[0]:true; }

// Show the year navigation as an input box that can be manually changed
function CP_showYearNavigationInput() { this.isShowYearNavigationInput = (arguments.length>0)?arguments[0]:true; }

// Hide a calendar object
function CP_hideCalendar() {
	if (arguments.length > 0) { 
        window.popupWindowObjects[arguments[0]].hidePopup(); 
        if(window.popupWindowObjects[arguments[0]].date_from.length > 0) {
            // Datumsfelder im Formular untereinander plausibilisieren
            check(window.popupWindowObjects[arguments[0]].date_from, window.popupWindowObjects[arguments[0]].date_to, window.CP_dateFormat);
        }
    }
	else { 
        this.hidePopup(); 
        if(this.date_from.length > 0)
        {
            // Datumsfelder im Formular untereinander plausibilisieren
            check(this.date_from, this.date_to, this.CP_dateFormat);
        }
    }
	}

// Refresh the contents of the calendar display
function CP_refreshCalendar(index) {
	var calObject = window.popupWindowObjects[index];
	if (arguments.length>1) {
		calObject.populate(calObject.getCalendar(arguments[1],arguments[2],arguments[3],arguments[4],arguments[5]));
		}
	else {
		calObject.populate(calObject.getCalendar());
		}
	calObject.refresh();
	}

// Populate the calendar and display it
function CP_showCalendar(anchorname) {
	if (arguments.length>1) {
		if (arguments[1]==null||arguments[1]=="") {
			this.currentDate=new Date();
			}
		else {
			this.currentDate=new Date(parseDate(arguments[1]));
			}
		}
	this.populate(this.getCalendar());
	this.showPopup(anchorname);
	}

// Simple method to interface popup calendar with a text-entry box
function CP_select(inputobj, linkname, format) {
	var selectedDate=(arguments.length>7)?arguments[7]:null;
    // Datum und Uhrzeit wird nicht immer uebergeben, deshalb hier dynamisch fuellen
    for (var i = 0; i < arguments.length; i++)
    {
        if(i == 3)
            this.date_from = arguments[i];
        else if(i == 4)
            this.date_to = arguments[i];
        else if(i == 5)
            this.time_from = arguments[i];
        else if(i == 6)
            this.time_to = arguments[i];
    }
	if (!window.getDateFromFormat) {
		alert("calendar.select: To use this method you must also include 'date.js' for date formatting");
		return;
		}
	if (this.displayType!="date"&&this.displayType!="week-end") {
		alert("calendar.select: This function can only be used with displayType 'date' or 'week-end'");
		return;
		}
	if (inputobj.type!="text" && inputobj.type!="hidden" && inputobj.type!="textarea") {
		alert("calendar.select: Input object passed is not a valid form input object");
		window.CP_targetInput=null;
		return;
		}
	if (inputobj.disabled) { return; } // Can't use calendar input on disabled form input!
	window.CP_targetInput = inputobj;
	window.CP_calendarObject = this;
	this.currentDate=null;
	var time=0;
	if (selectedDate!=null) {
		time = getDateFromFormat(selectedDate,format)
		}
	else if (inputobj.value!="") {
		time = getDateFromFormat(inputobj.value,format);
		}
	if (selectedDate!=null || inputobj.value!="") {
		if (time==0) { this.currentDate=null; }
		else { this.currentDate=new Date(time); }
		}
	window.CP_dateFormat = format;
	this.showCalendar(linkname);
	}

// Get style block needed to display the calendar correctly
function getCalendarStyles() {
	var result = "";
	var p = "";
	if (this!=null && typeof(this.cssPrefix)!="undefined" && this.cssPrefix!=null && this.cssPrefix!="") { p=this.cssPrefix; }
	return result;
	}

// Return a string containing all the calendar code to be displayed
function CP_getCalendar() {
	var now = new Date();
	// Reference to window
	if (this.type == "WINDOW") { var windowref = "window.opener."; }
	else { var windowref = ""; }
	var result = "";
	// If POPUP, write entire HTML document
	if (this.type == "WINDOW") {
		result += "<html><head><titel>Kalender</titel>"+this.getStyles()+"</head><body marginwidth=0 marginheight=0 topmargin=0 rightmargin=0 leftmargin=0>\n";
		result += '<center><table width=100% border=0 borderwidth=0 cellspacing=0 cellpadding=0>\n';
		}
	else {
		result += '<table id="'+this.cssPrefix+'cpBorder" width=100% border=0 borderwidth=0 cellspacing=0 cellpadding=0>\n';
		result += '<tr><td align=center>\n';
		result += '<center>\n';
		}
	// Code for DATE display (default)
	// -------------------------------
	if (this.displayType=="date" || this.displayType=="week-end") {
		if (this.currentDate==null) { this.currentDate = now; }
		if (arguments.length > 0) { var month = arguments[0]; }
			else { var month = this.currentDate.getMonth()+1; }
		if (arguments.length > 1 && arguments[1]>0 && arguments[1]-0==arguments[1]) { var year = arguments[1]; }
			else { var year = this.currentDate.getFullYear(); }
		var daysinmonth= new Array(0,31,28,31,30,31,30,31,31,30,31,30,31);
		if ( ( (year%4 == 0)&&(year%100 != 0) ) || (year%400 == 0) ) {
			daysinmonth[2] = 29;
			}
		var current_month = new Date(year,month-1,1);
		var display_year = year;
		var display_month = month;
		var display_date = 1;
		var weekday= current_month.getDay();
		var offset = 0;

		offset = (weekday >= this.weekStartDay) ? weekday-this.weekStartDay : 7-this.weekStartDay+weekday ;
		if (offset > 0) {
			display_month--;
			if (display_month < 1) { display_month = 12; display_year--; }
			display_date = daysinmonth[display_month]-offset+1;
			}
		var next_month = month+1;
		var next_month_year = year;
		if (next_month > 12) { next_month=1; next_month_year++; }
		var last_month = month-1;
		var last_month_year = year;
		if (last_month < 1) { last_month=12; last_month_year--; }
		var date_class;
		if (this.type!="WINDOW") {
			result += "<table id=navigationbar width=100% style='border:0px;' border=0 cellpadding=0 cellspacing=0 cols=0 rules=none align=center>";
			}
		result += '<tr>\n';
		var refresh = windowref+'CP_refreshCalendar';
		var refreshLink = 'javascript:' + refresh;
		if (this.isShowNavigationDropdowns) {
			result += '<td id="'+this.cssPrefix+'cpMonthNavigation" width="78" colspan="3"><select id="'+this.cssPrefix+'cpMonthNavigation" name="cpMonth" onChange="'+refresh+'('+this.index+',this.options[this.selectedIndex].value-0,'+(year-0)+');">';
			for( var monthCounter=1; monthCounter<=12; monthCounter++ ) {
				var selected = (monthCounter==month) ? 'SELECTED' : '';
				result += '<option value="'+monthCounter+'" '+selected+'>'+this.monthNames[monthCounter-1]+'</option>';
				}
			result += '</select></td>';
			result += '<td id="'+this.cssPrefix+'cpMonthNavigation" width="10">&nbsp;</td>';

			result += '<td id="'+this.cssPrefix+'cpYearNavigation" width="56" colspan="3"><select id="'+this.cssPrefix+'cpYearNavigation" name="cpYear" onChange="'+refresh+'('+this.index+','+month+',this.options[this.selectedIndex].value-0);">';
            var actDate = new Date();
            var actYear = actDate.getFullYear();

			for( var yearCounter=actYear-this.yearSelectStartOffset; yearCounter<=actYear+this.yearSelectEndOffset; yearCounter++ ) {
				var selected = (yearCounter==year) ? 'SELECTED' : '';
				result += '<option value="'+yearCounter+'" '+selected+'>'+yearCounter+'</option>';
				}
			result += '</select></td>';
			}
		else {
			if (this.isShowYearNavigation) {
				result += '<td id="'+this.cssPrefix+'cpMonthNavigation" width="10"><a onclick="'+refreshLink+'('+this.index+','+last_month+','+last_month_year+');">&lt;</a></td>';
				result += '<td id="'+this.cssPrefix+'cpMonthNavigation" width="58"><span id="'+this.cssPrefix+'cpMonthNavigation">'+this.monthNames[month-1]+'</span></td>';
				result += '<td id="'+this.cssPrefix+'cpMonthNavigation" width="10"><a onclick="'+refreshLink+'('+this.index+','+next_month+','+next_month_year+');">&gt;</a></td>';
				result += '<td id="'+this.cssPrefix+'cpMonthNavigation" width="10">&nbsp;</td>';

				result += '<td id="'+this.cssPrefix+'cpYearNavigation" width="10"><a id="'+this.cssPrefix+'cpYearNavigation" onclick="'+refreshLink+'('+this.index+','+month+','+(year-1)+');">&lt;</a></td>';
				if (this.isShowYearNavigationInput) {
					result += '<td id="'+this.cssPrefix+'cpYearNavigation" width="36"><input name="cpYear" id="'+this.cssPrefix+'cpYearNavigation" size="4" maxlenght="4" value="'+year+'" onBlur="'+refresh+'('+this.index+','+month+',this.value-0);"></td>';
					}
				else {
					result += '<td id="'+this.cssPrefix+'cpYearNavigation" width="36"><span id="'+this.cssPrefix+'cpYearNavigation">'+year+'</span></td>';
					}
				result += '<td id="'+this.cssPrefix+'cpYearNavigation" width="10"><a id="'+this.cssPrefix+'cpYearNavigation" onclick="'+refreshLink+'('+this.index+','+month+','+(year+1)+');">&gt;</a></td>';
				}
			else {
				result += '<td id="'+this.cssPrefix+'cpMonthNavigation" width="22"><a onclick="'+refreshLink+'('+this.index+','+last_month+','+last_month_year+');">&lt;&lt;</a></td>\n';
				result += '<td id="'+this.cssPrefix+'cpMonthNavigation" width="100"><span id="'+this.cssPrefix+'cpMonthNavigation">'+this.monthNames[month-1]+' '+year+'</span></td>\n';
				result += '<td id="'+this.cssPrefix+'cpMonthNavigation" width="22"><a onclick="'+refreshLink+'('+this.index+','+next_month+','+next_month_year+');">&gt;&gt;</a></td>\n';
				}
			}
		result += '</tr></table>\n';
		result += '<table id="daynames" rules="cols" width="100%" style="border:0px;" border="0" cellpadding="0" cellspacing="0" align="center">\n';
		result += '<tr>\n';
		for (var j=0; j<7; j++) {

			result += '<td id="'+this.cssPrefix+'cpDayColumnHeader" width="14%"><span id="'+this.cssPrefix+'cpDayColumnHeader">'+this.dayHeaders[(this.weekStartDay+j)%7]+'</td>\n';
			}
		result += '</tr>\n';
		for (var row=1; row<=6; row++) {
			result += '<tr>\n';
			for (var col=1; col<=7; col++) {
				var disabled=false;
				if (this.disabledDatesExpression!="") {
					var ds=""+display_year+LZ(display_month)+LZ(display_date);
					eval("disabled=("+this.disabledDatesExpression+")");
					}
				var dateClass = "";
				if ((display_month == this.currentDate.getMonth()+1) && (display_date==this.currentDate.getDate()) && (display_year==this.currentDate.getFullYear())) {
					dateClass = "cpCurrentDate";
					}
				else if (display_month == month) {
					dateClass = "cpCurrentMonthDate";
					}
				else {
					dateClass = "cpOtherMonthDate";
					}
				if (disabled || this.disabledWeekDays[col-1]) {
					result += '	<td id="'+this.cssPrefix+dateClass+'"><span id="'+this.cssPrefix+dateClass+'Disabled">'+display_date+'</span></td>\n';
					}
				else {
					var selected_date = display_date;
					var selected_month = display_month;
					var selected_year = display_year;
					if (this.displayType=="week-end") {
						var d = new Date(selected_year,selected_month-1,selected_date,0,0,0,0);
						d.setDate(d.getDate() + (7-col));
						selected_year = d.getYear();
						if (selected_year < 1000) { selected_year += 1900; }
						selected_month = d.getMonth()+1;
						selected_date = d.getDate();
						}
					result += '	<td id="'+this.cssPrefix+dateClass+'"><a  onclick="javascript:'+windowref+this.returnFunction+'('+selected_year+','+selected_month+','+selected_date+');'+windowref+'CP_hideCalendar(\''+this.index+'\');">'+display_date+'</a></td>\n';
					}
				display_date++;
				if (display_date > daysinmonth[display_month]) {
					display_date=1;
					display_month++;
					}
				if (display_month > 12) {
					display_month=1;
					display_year++;
					}
				}
			result += '</tr>';
			}
		var current_weekday = now.getDay() - this.weekStartDay;
		if (current_weekday < 0) {
			current_weekday += 7;
			}
		result += '<tr>\n';
		result += '	<td colspan=7 align=center id="'+this.cssPrefix+'cpTodayText">\n';
		if (this.disabledDatesExpression!="") {
			var ds=""+now.getFullYear()+LZ(now.getMonth()+1)+LZ(now.getDate());
			eval("disabled=("+this.disabledDatesExpression+")");
			}
		if (disabled || this.disabledWeekDays[current_weekday+1]) {
			result += '		<span id="'+this.cssPrefix+'cpTodayTextDisabled">'+this.todayText+'</span>\n';
			}
		else {
			result += '		<a id="'+this.cssPrefix+'cpTodayText" onclick="javascript:'+windowref+this.returnFunction+'(\''+now.getFullYear()+'\',\''+(now.getMonth()+1)+'\',\''+now.getDate()+'\');'+windowref+'CP_hideCalendar(\''+this.index+'\');">'+this.todayText+'</a>\n';
			}
		result += '		<br />\n';
		result += '	</td></tr></table></center></td></tr></table>\n';
	}

	// Code common for MONTH, QUARTER, YEAR
	// ------------------------------------
	if (this.displayType=="month" || this.displayType=="quarter" || this.displayType=="year") {
		if (arguments.length > 0) { var year = arguments[0]; }
		else {
			if (this.displayType=="year") {	var year = now.getFullYear()-this.yearSelectStartOffset; }
			else { var year = now.getFullYear(); }
			}
		if (this.displayType!="year" && this.isShowYearNavigation) {
			result += "<table width=144 border=0 borderwidth=0 cellspacing=0 cellpadding=0>";
			result += '<tr>\n';
			result += '	<td id="'+this.cssPrefix+'cpYearNavigation" width="22"><a id="'+this.cssPrefix+'cpYearNavigation" onclick="javascript:'+windowref+'CP_refreshCalendar('+this.index+','+(year-1)+');">&lt;&lt;</a></td>\n';
			result += '	<td id="'+this.cssPrefix+'cpYearNavigation" width="100">'+year+'</td>\n';
			result += '	<td id="'+this.cssPrefix+'cpYearNavigation" width="22"><a id="'+this.cssPrefix+'cpYearNavigation" onclick="javascript:'+windowref+'CP_refreshCalendar('+this.index+','+(year+1)+');">&gt;&gt;</a></td>\n';
			result += '</tr></table>\n';
			}
		}

	// Code for MONTH display
	// ----------------------
	if (this.displayType=="month") {
		// If POPUP, write entire HTML document
		result += '<table width=120 border=0 cellspacing=1 cellpadding=0 align=center>\n';
		for (var i=0; i<4; i++) {
			result += '<tr>';
			for (var j=0; j<3; j++) {
				var monthindex = ((i*3)+j);
				result += '<td width=33% align=center><a id="'+this.cssPrefix+'cpText" onclick="javascript:'+windowref+this.returnMonthFunction+'('+year+','+(monthindex+1)+');'+windowref+'CP_hideCalendar(\''+this.index+'\');" id="'+date_class+'">'+this.monthAbbreviations[monthindex]+'</a></td>';
				}
			result += '</tr>';
			}
		result += '</table></center></td></tr></table>\n';
		}

	// Code for QUARTER display
	// ------------------------
	if (this.displayType=="quarter") {
		result += '<br /><table width=120 border=1 cellspacing=0 cellpadding=0 align=center>\n';
		for (var i=0; i<2; i++) {
			result += '<tr>';
			for (var j=0; j<2; j++) {
				var quarter = ((i*2)+j+1);
				result += '<td width=50% align=center><br /><a id="'+this.cssPrefix+'cpText" onclick="javascript:'+windowref+this.returnQuarterFunction+'('+year+','+quarter+');'+windowref+'CP_hideCalendar(\''+this.index+'\');" id="'+date_class+'">Q'+quarter+'</a><br /><br /></td>';
				}
			result += '</tr>';
			}
		result += '</table></center></td></tr></table>\n';
		}

	// Code for YEAR display
	// ---------------------
	if (this.displayType=="year") {
		var yearColumnSize = 4;
		result += "<table width=144 border=0 borderwidth=0 cellspacing=0 cellpadding=0>";
		result += '<tr>\n';
		result += '	<td id="'+this.cssPrefix+'cpYearNavigation" width="50%"><a id="'+this.cssPrefix+'cpYearNavigation" onclick="javascript:'+windowref+'CP_refreshCalendar('+this.index+','+(year-(yearColumnSize*2))+');">&lt;&lt;</a></td>\n';
		result += '	<td id="'+this.cssPrefix+'cpYearNavigation" width="50%"><a id="'+this.cssPrefix+'cpYearNavigation" onclick="javascript:'+windowref+'CP_refreshCalendar('+this.index+','+(year+(yearColumnSize*2))+');">&gt;&gt;</a></td>\n';
		result += '</tr></table>\n';
		result += '<table width=120 border=0 cellspacing=1 cellpadding=0 align=center>\n';
		for (var i=0; i<yearColumnSize; i++) {
			for (var j=0; j<2; j++) {
				var currentyear = year+(j*yearColumnSize)+i;
				result += '<td width=50% align=center><a id="'+this.cssPrefix+'cpText" onclick="javascript:'+windowref+this.returnYearFunction+'('+currentyear+');'+windowref+'CP_hideCalendar(\''+this.index+'\');" id="'+date_class+'">'+currentyear+'</a></td>';
				}
			result += '</tr>';
			}
		result += '</table></center></td></tr></table>\n';
		}
	// Common
	if (this.type == "WINDOW") {
		result += "</body></html>\n";
		}
	return result;
	}

var Standardfarbe	= '#85C226';   // Standardfarbe eintragen
var wahlfarbe		= '#EAEAEA';

function neueFarbe(id)
{
	document.getElementById(id).style.backgroundColor = wahlfarbe;
}

function alteFarbe(id)
{
	document.getElementById(id).style.backgroundColor = Standardfarbe;
}