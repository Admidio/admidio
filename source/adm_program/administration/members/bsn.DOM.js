/**
 *  author:        Timothy Groves - http://www.brandspankingnew.net
 *    version:    1.5 - 2006-08-03
 *
 *    requires:    nothing
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




_bsn.DOM.createElement = function ( type, attr, cont, html )
{
    var ne = document.createElement( type );
    if (!ne)
        return false;

    for (var a in attr)
        ne[a] = attr[a];

    if (typeof(cont) == "string" && !html)
        ne.appendChild( document.createTextNode(cont) );
    else if (typeof(cont) == "string" && html)
        ne.innerHTML = cont;
    else if (typeof(cont) == "object")
        ne.appendChild( cont );

    return ne;
}


_bsn.DOM.clearElement = function ( id )
{
    var ele = this.getElement( id );

    if (!ele)
        return false;

    while (ele.childNodes.length)
        ele.removeChild( ele.childNodes[0] );

    return true;
}


_bsn.DOM.removeElement = function ( ele )
{
    var e = this.getElement(ele);

    if (!e)
        return false;
    else if (e.parentNode.removeChild(e))
        return true;
    else
        return false;
}


_bsn.DOM.replaceContent = function ( id, cont, html )
{
    var ele = this.getElement( id );

    if (!ele)
        return false;

    this.clearElement( ele );

    if (typeof(cont) == "string" && !html)
        ele.appendChild( document.createTextNode(cont) );
    else if (typeof(cont) == "string" && html)
        ele.innerHTML = cont;
    else if (typeof(cont) == "object")
        ele.appendChild( cont );
}



_bsn.DOM.getElement = function ( ele )
{
    if (typeof(ele) == "undefined")
    {
        return false;
    }
    else if (typeof(ele) == "string")
    {
        var re = document.getElementById( ele );
        if (!re)
            return false;
        else if (typeof(re.appendChild) != "undefined" ) {
            return re;
        } else {
            return false;
        }
    }
    else if (typeof(ele.appendChild) != "undefined")
        return ele;
    else
        return false;
}



_bsn.DOM.appendChildren = function ( id, arr )
{
    var ele = this.getElement( id );

    if (!ele)
        return false;


    if (typeof(arr) != "object")
        return false;

    for (var i=0;i<arr.length;i++)
    {
        var cont = arr[i];
        if (typeof(cont) == "string")
            ele.appendChild( document.createTextNode(cont) );
        else if (typeof(cont) == "object")
            ele.appendChild( cont );
    }
}


//    var opt = new Array( '1'=>'lorem', '2'=>'ipsum' );
// var sel = '2';

_bsn.DOM.createSelect = function ( attr, opt, sel )
{
    var select = this.createElement( 'select', attr );
    for (var a in opt)
    {

        var o = {id:a};
        if (a == sel)    o.selected = "selected";
        select.appendChild( this.createElement( 'option', o, opt[a] ) );

    }

    return select;
}


_bsn.DOM.getPos = function ( ele )
{
    var ele = this.getElement(ele);

    var obj = ele;

    var curleft = 0;
    if (obj.offsetParent)
    {
        while (obj.offsetParent)
        {
            curleft += obj.offsetLeft
            obj = obj.offsetParent;
        }
    }
    else if (obj.x)
        curleft += obj.x;


    var obj = ele;

    var curtop = 0;
    if (obj.offsetParent)
    {
        while (obj.offsetParent)
        {
            curtop += obj.offsetTop
            obj = obj.offsetParent;
        }
    }
    else if (obj.y)
        curtop += obj.y;

    return {x:curleft, y:curtop}
}