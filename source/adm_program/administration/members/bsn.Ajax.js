/**
 *  author:        Timothy Groves - http://www.brandspankingnew.net
 *    version:    1.0 - 2006-08-04
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


_bsn.Ajax = function ()
{
    this.req = {};
    this.isIE = false;
}



_bsn.Ajax.prototype.makeRequest = function (url, meth, onComp, onErr)
{

    if (meth != "POST")
        meth = "GET";

    this.onComplete = onComp;
    this.onError = onErr;

    var pointer = this;

    // branch for native XMLHttpRequest object
    if (window.XMLHttpRequest)
    {
        this.req = new XMLHttpRequest();
        this.req.onreadystatechange = function () { pointer.processReqChange() };
        this.req.open("GET", url, true); //
        this.req.send(null);
    // branch for IE/Windows ActiveX version
    }
    else if (window.ActiveXObject)
    {
        this.req = new ActiveXObject("Microsoft.XMLHTTP");
        if (this.req)
        {
            this.req.onreadystatechange = function () { pointer.processReqChange() };
            this.req.open(meth, url, true);
            this.req.send();
        }
    }
}


_bsn.Ajax.prototype.processReqChange = function()
{

    // only if req shows "loaded"
    if (this.req.readyState == 4) {
        // only if "OK"
        if (this.req.status == 200)
        {
            this.onComplete( this.req );
        } else {
            this.onError( this.req.status );
        }
    }
}