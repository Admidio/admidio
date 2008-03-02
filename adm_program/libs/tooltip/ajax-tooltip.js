/************************************************************************************************************
Ajax tooltip
Copyright (C) 2006  DTHMLGoodies.com, Alf Magne Kalleland
This library is free software; you can redistribute it and/or
modify it under the terms of the GNU Lesser General Public
License as published by the Free Software Foundation; either
version 2.1 of the License, or (at your option) any later version.
Alf Magne Kalleland, 2006
Owner of DHTMLgoodies.com
************************************************************************************************************/	

var enableCache = true;
var jsCache = new Array();
var positiontooltip = false;

var dynamicContent_ajaxObjects = new Array();

var ajax_tooltipObj = false;
var ajax_tooltipObj_iframe = false;

var ajax_tooltip_MSIE = false;

if(navigator.userAgent.indexOf('MSIE')>=0)ajax_tooltip_MSIE=true;


function ajax_showContent(divId,ajaxIndex,url)
{
	document.getElementById(divId).innerHTML = dynamicContent_ajaxObjects[ajaxIndex].response;
	if(enableCache){
		jsCache[url] = 	dynamicContent_ajaxObjects[ajaxIndex].response;
	}
	dynamicContent_ajaxObjects[ajaxIndex] = false;
}

function ajax_loadContent(divId,url)
{
	if(enableCache && jsCache[url]){
		document.getElementById(divId).innerHTML = jsCache[url];
		return;
	}

	
	var ajaxIndex = dynamicContent_ajaxObjects.length;
	document.getElementById(divId).innerHTML = 'Inhalt wird geladen - Bitte warten!';
	dynamicContent_ajaxObjects[ajaxIndex] = new sack();
	
	if(url.indexOf('?')>=0){
		dynamicContent_ajaxObjects[ajaxIndex].method='GET';
		var string = url.substring(url.indexOf('?'));
		url = url.replace(string,'');
		string = string.replace('?','');
		var items = string.split(/&/g);
		for(var no=0;no<items.length;no++){
			var tokens = items[no].split('=');
			if(tokens.length==2){
				dynamicContent_ajaxObjects[ajaxIndex].setVar(tokens[0],tokens[1]);
			}	
		}	
		url = url.replace(string,'');
	}
	
	dynamicContent_ajaxObjects[ajaxIndex].requestFile = url;	// Specifying which file to get
	dynamicContent_ajaxObjects[ajaxIndex].onCompletion = function(){ ajax_showContent(divId,ajaxIndex,url); };	// Specify function that will be executed after file has been found
	dynamicContent_ajaxObjects[ajaxIndex].runAJAX();		// Execute AJAX function	
}

function ajax_showTooltip(externalFile,inputObj)
{
	if(!ajax_tooltipObj)	/* Tooltip div not created yet ? */
	{
		ajax_tooltipObj = document.createElement('DIV');
		ajax_tooltipObj.style.position = 'absolute';
		ajax_tooltipObj.id = 'ajax_tooltipObj';		
		document.body.appendChild(ajax_tooltipObj);

		var contentDiv = document.createElement('DIV'); /* Create tooltip content div */
		contentDiv.className = 'ajax_tooltip_content';
		ajax_tooltipObj.appendChild(contentDiv);
		contentDiv.id = 'ajax_tooltip_content';
		
		if(ajax_tooltip_MSIE){	/* Create iframe object for MSIE in order to make the tooltip cover select boxes */
			ajax_tooltipObj_iframe = document.createElement('<IFRAME frameborder="0">');
			ajax_tooltipObj_iframe.style.position = 'absolute';
			ajax_tooltipObj_iframe.border='0';
			ajax_tooltipObj_iframe.frameborder=0;
			ajax_tooltipObj_iframe.style.backgroundColor='#FFF';
			ajax_tooltipObj_iframe.src = 'about:blank';
			contentDiv.appendChild(ajax_tooltipObj_iframe);
			ajax_tooltipObj_iframe.style.left = '0px';
			ajax_tooltipObj_iframe.style.top = '0px';
		}

			
	}
	// Find position of tooltip
	ajax_loadContent('ajax_tooltip_content',externalFile);
	if(ajax_tooltip_MSIE){
		ajax_tooltipObj_iframe.style.width = ajax_tooltipObj.clientWidth + 'px';
		ajax_tooltipObj_iframe.style.height = ajax_tooltipObj.clientHeight + 'px';
	}
}

function ajax_positionTooltip()
{
	positiontooltip = true;
}


function ajax_hideTooltip()
{
	positiontooltip = false;
	if(ajax_tooltipObj)
	{
		ajax_tooltipObj.style.display='none';
	}
}

function sack(file){
	this.AjaxFailedAlert = "Dein Browser unterstützt nicht die erweiterte Funktionalität der Website!\n";
	this.requestFile = file;
	this.method = "POST";
	this.URLString = "";
	this.encodeURIString = true;
	this.execute = false;

	this.onLoading = function() {ajax_hideTooltip(); };
	this.onLoaded = function() {ajax_hideTooltip(); ajax_positionTooltip();};
	this.onInteractive = function() {};
	this.onCompletion = function() {};

	this.createAJAX = function() {
		try {
			this.xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (e) {
			try {
				this.xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (err) {
				this.xmlhttp = null;
			}
		}
		if(!this.xmlhttp && typeof XMLHttpRequest != "undefined")
			this.xmlhttp = new XMLHttpRequest();
		if (!this.xmlhttp){
			this.failed = true; 
		}
	};
	
	this.setVar = function(name, value){
		if (this.URLString.length < 3){
			this.URLString = name + "=" + value;
		} else {
			this.URLString += "&" + name + "=" + value;
		}
	}
	
	this.encVar = function(name, value){
		var varString = encodeURIComponent(name) + "=" + encodeURIComponent(value);
	return varString;
	}
	
	this.encodeURLString = function(string){
		varArray = string.split('&');
		for (i = 0; i < varArray.length; i++){
			urlVars = varArray[i].split('=');
			if (urlVars[0].indexOf('amp;') != -1){
				urlVars[0] = urlVars[0].substring(4);
			}
			varArray[i] = this.encVar(urlVars[0],urlVars[1]);
		}
	return varArray.join('&');
	}
	
	this.runResponse = function(){
		eval(this.response);
	}
	
	this.runAJAX = function(urlstring){
		this.responseStatus = new Array(2);
		if(this.failed && this.AjaxFailedAlert){ 
			alert(this.AjaxFailedAlert); 
		} else {
			if (urlstring){ 
				if (this.URLString.length){
					this.URLString = this.URLString + "&" + urlstring; 
				} else {
					this.URLString = urlstring; 
				}
			}
			if (this.encodeURIString){
				var timeval = new Date().getTime(); 
				this.URLString = this.encodeURLString(this.URLString);
				this.setVar("rndval", timeval);
			}
			if (this.element) { this.elementObj = document.getElementById(this.element); }
			if (this.xmlhttp) {
				var self = this;
				if (this.method == "GET") {
					var totalurlstring = this.requestFile + "?" + this.URLString;
					this.xmlhttp.open(this.method, totalurlstring, true);
				} else {
					this.xmlhttp.open(this.method, this.requestFile, true);
				}
				if (this.method == "POST"){
  					try {
						this.xmlhttp.setRequestHeader('Content-Type','application/x-www-form-urlencoded')  
					} catch (e) {}
				}

				this.xmlhttp.send(this.URLString);
				this.xmlhttp.onreadystatechange = function() {
					switch (self.xmlhttp.readyState){
						case 1:
							self.onLoading();
						break;
						case 2:
							self.onLoaded();
						break;
						case 3:
							self.onInteractive();
						break;
						case 4:
							self.response = self.xmlhttp.responseText;
							self.responseXML = self.xmlhttp.responseXML;
							self.responseStatus[0] = self.xmlhttp.status;
							self.responseStatus[1] = self.xmlhttp.statusText;
							self.onCompletion();
							if(self.execute){ self.runResponse(); }
							if (self.elementObj) {
								var elemNodeName = self.elementObj.nodeName;
								elemNodeName.toLowerCase();
								if (elemNodeName == "input" || elemNodeName == "select" || elemNodeName == "option" || elemNodeName == "textarea"){
									self.elementObj.value = self.response;
								} else {
									self.elementObj.innerHTML = self.response;
								}
							}
							self.URLString = ""; 
						break;
					}
				};
			}
		}
	};
this.createAJAX();
}
function ietruebody()
{
return (document.compatMode && document.compatMode!="BackCompat")? document.documentElement : document.body
}
function mouseMove(evt)
{
	if(positiontooltip == true)
	{
		var offsetfromcursorX=15; //Customize x offset of tooltip
		var offsetfromcursorY=15; //Customize y offset of tooltip
		var ie=document.all;
		var ns6=document.getElementById && !document.all;
		var curX=(ns6)?evt.pageX : event.clientX+ietruebody().scrollLeft;
		var curY=(ns6)?evt.pageY : event.clientY+ietruebody().scrollTop;
		var winwidth=ie&&!window.opera? ietruebody().clientWidth : window.innerWidth-20;
		var winheight=ie&&!window.opera? ietruebody().clientHeight : window.innerHeight-20;
		var rightedge=ie&&!window.opera? winwidth-event.clientX-offsetfromcursorX : winwidth-evt.clientX-offsetfromcursorX;
		var bottomedge=ie&&!window.opera? winheight-event.clientY-offsetfromcursorY : winheight-evt.clientY-offsetfromcursorY;
		var leftedge=(offsetfromcursorX<0)? offsetfromcursorX*(-1) : -1000;
		
		if (ie||ns6)
		{
			var tipobj=document.all? document.all["ajax_tooltipObj"] : document.getElementById? document.getElementById("ajax_tooltipObj") : ""
		}
		//if the horizontal distance isn't enough to accomodate the width of the context menu
		if (rightedge<tipobj.offsetWidth)
		{
			//move the horizontal position of the menu to the left by it's width
			tipobj.style.left=curX-tipobj.offsetWidth+offsetfromcursorX+"px";
		}
		else if (curX<leftedge)
		{
			tipobj.style.left="50px";
		}
		else
		{
			//position the horizontal position of the menu where the mouse is positioned
			tipobj.style.left=curX+offsetfromcursorX+"px";
		}
		
		//same concept with the vertical position
		if (bottomedge<tipobj.offsetHeight)
		{
			tipobj.style.top=curY-tipobj.offsetHeight-offsetfromcursorY+"px";
		}
		else
		{
			tipobj.style.top=curY+offsetfromcursorY+"px";
		}
		if(ajax_tooltipObj)
		{
			ajax_tooltipObj.style.display='block';
		}
	}
	else
	{
		positiontooltip = false;
		if(ajax_tooltipObj)	
		{
			ajax_hideTooltip();
		}
	}
}
document.onmousemove	= mouseMove;
document.onmouseover	= ajax_hideTooltip;
document.onmouseout		= ajax_hideTooltip;
document.onclick		= ajax_hideTooltip;