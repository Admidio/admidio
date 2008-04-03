/**
 *  author:        Timothy Groves - http://www.brandspankingnew.net
 *    version:    1.2 - 2006-11-17
 *
 *    requires:    bsn.DOM.js
 *                 bsn.Ajax.js
 *
 *    modified by Elmar Meuthen from "The Admidio Team - http://www.admidio.org" in January 2007
 *
 */

var useBSNns;

if (useBSNns)
{
    if (typeof(bsn) == "undefined")
        bsn = {}
    _bsn = bsn;
}
else
{
    _bsn = this;
}


if (typeof(_bsn.DOM) == "undefined")
    _bsn.DOM = {}


_bsn.AutoSuggest = function (fldID, param)
{
    if (!document.getElementById)
        return false;

    this.fld = _bsn.DOM.getElement(fldID);

    if (!this.fld)
        return false;


    this.nInputChars = 0;
    this.aSuggestions = [];
    this.iHighlighted = 0;


    // parameters object
    this.oP = (param) ? param : {};
    // defaults
    if (!this.oP.minchars)        this.oP.minchars = 1;
    if (!this.oP.method)        this.oP.meth = "get";
    if (!this.oP.varname)        this.oP.varname = "input";
    if (!this.oP.className)        this.oP.className = "autosuggest";
    if (!this.oP.timeout)        this.oP.timeout = 2500;
    if (!this.oP.delay)            this.oP.delay = 500;
    if (!this.oP.maxheight && this.oP.maxheight !== 0)        this.oP.maxheight = 250;
    if (!this.oP.cache)            this.oP.cache = true;

    var pointer = this;

    this.fld.onkeyup = function () { pointer.getSuggestions( this.value ) };
    this.fld.setAttribute("autocomplete","off");
}



_bsn.AutoSuggest.prototype.getSuggestions = function (val)
{

    if (val.length == this.nInputChars)
        return false;

    if (val.length < this.oP.minchars)
    {
        this.nInputChars = val.length;
        this.aSuggestions = [];
        this.clearSuggestions();
        return false;
    }


    if (val.length>this.nInputChars && this.aSuggestions.length && this.oP.cache)
    {
        // get from cache
        var arr = [];
        for (var i=0;i<this.aSuggestions.length;i++)
        {
            var modifiedVal = val.replace(/,/,"");
            var suggestArray = this.aSuggestions[i].split(", ");

            if (suggestArray[0].concat(" ").concat(suggestArray[1]).substr(0,modifiedVal.length).toLowerCase() == modifiedVal.toLowerCase()
            ||  suggestArray[1].concat(" ").concat(suggestArray[0]).substr(0,modifiedVal.length).toLowerCase() == modifiedVal.toLowerCase())
            {
                arr.push( this.aSuggestions[i] );
            }

        }

        this.nInputChars = val.length;
        this.aSuggestions = arr;


        this.createList( this.aSuggestions );

        return false;
    }


    this.nInputChars = val.length;

    var pointer = this;
    clearTimeout(this.ajID);
    this.ajID = setTimeout( function() { pointer.doAjaxRequest() }, this.oP.delay );


    return false;
}


_bsn.AutoSuggest.prototype.doAjaxRequest = function ()
{
    var pointer = this;

    // create ajax request
    var url = this.oP.script+this.oP.varname+"="+escape(this.fld.value);
    var meth = this.oP.meth;

    var onSuccessFunc = function (req) { pointer.setSuggestions(req) };
    var onErrorFunc = function (status) { alert("AJAX error: "+status); };

    var myAjax = new _bsn.Ajax;
    myAjax.makeRequest( url, meth, onSuccessFunc, onErrorFunc );
}


_bsn.AutoSuggest.prototype.setSuggestions = function (req)
{

    try
    {
        var xml = req.responseXML;

        // traverse xml
        //
        this.aSuggestions = [];
        var results = xml.getElementsByTagName('results')[0].childNodes;

        for (var i=0;i<results.length;i++)
        {
            if (results[i].hasChildNodes())
                this.aSuggestions.push( results[i].childNodes[0].nodeValue );
        }


        this.idAs = "as_"+this.fld.id;


        this.createList(this.aSuggestions);
    }
    catch(Error)
    {
         return;
    }

}

_bsn.AutoSuggest.prototype.createList = function(arr)
{
    // clear previous list
    //
    this.clearSuggestions();

    // create and populate ul
    //
    var ul = _bsn.DOM.createElement("ul", {id:this.idAs, className:this.oP.className});


    var pointer = this;
    for (var i=0;i<arr.length;i++)
    {
        var a = _bsn.DOM.createElement("a", { href:"#" }, arr[i]);
        a.onclick = function () { 
            pointer.setValue( this.childNodes[0].nodeValue ); 
            document.getElementById('autosuggest').submit(); 
            return false; 
        }
        var li = _bsn.DOM.createElement(  "li", {}, a  );
        ul.appendChild(  li  );
    }

    var pos = _bsn.DOM.getPos(this.fld);

    ul.style.left = pos.x + "px";
    ul.style.top = ( pos.y + this.fld.offsetHeight ) + "px";
    ul.style.width = this.fld.offsetWidth + "px";
    ul.onmouseover = function(){ pointer.killTimeout() }
    ul.onmouseout = function(){ pointer.resetTimeout() }


    document.getElementsByTagName("body")[0].appendChild(ul);

    if (ul.offsetHeight > this.oP.maxheight && this.oP.maxheight != 0)
    {
        ul.style['height'] = this.oP.maxheight + "px";
    }


    var TAB = 9;
    var ESC = 27;
    var KEYUP = 38;
    var KEYDN = 40;
    var RETURN = 13;


    this.fld.onkeydown = function(ev)
    {
        var key = (window.event) ? window.event.keyCode : ev.keyCode;

        switch(key)
        {
            case TAB:
            case RETURN:
            pointer.setHighlightedValue();
            break;

            case ESC:
            pointer.clearSuggestions();
            break;

            case KEYUP:
            case KEYDN:
            pointer.changeHighlight(key);
            return false;
            break;
        }

    };

    this.iHighlighted = 0;


    // remove autosuggest after an interval
    //
    clearTimeout(this.toID);
    var pointer = this;
    this.toID = setTimeout(function () { pointer.clearSuggestions() }, this.oP.timeout);
}

_bsn.AutoSuggest.prototype.changeHighlight = function(key)
{
    var list = _bsn.DOM.getElement(this.idAs);
    if (!list)
        return false;


    if (this.iHighlighted > 0)
        list.childNodes[this.iHighlighted-1].className = "";

    if (key == 40)
        this.iHighlighted ++;
    else if (key = 38)
        this.iHighlighted --;


    if (this.iHighlighted > list.childNodes.length)
        this.iHighlighted = list.childNodes.length;
    if (this.iHighlighted < 1)
        this.iHighlighted = 1;

    list.childNodes[this.iHighlighted-1].className = "highlight";

    this.killTimeout();
}


_bsn.AutoSuggest.prototype.killTimeout = function()
{
    clearTimeout(this.toID);
}

_bsn.AutoSuggest.prototype.resetTimeout = function()
{
    clearTimeout(this.toID);
    var pointer = this;
    this.toID = setTimeout(function () { pointer.clearSuggestions() }, 1000);
}


_bsn.AutoSuggest.prototype.clearSuggestions = function ()
{
    if (document.getElementById(this.idAs))
        _bsn.DOM.removeElement(this.idAs);
    this.fld.onkeydown = null;
}


_bsn.AutoSuggest.prototype.setHighlightedValue = function ()
{
    if (this.iHighlighted)
    {
        this.fld.value = document.getElementById(this.idAs).childNodes[this.iHighlighted-1].firstChild.firstChild.nodeValue;
        this.killTimeout();
        this.clearSuggestions();
    }
}

_bsn.AutoSuggest.prototype.setValue = function (val)
{
    this.fld.value = val;
    this.resetTimeout();
}