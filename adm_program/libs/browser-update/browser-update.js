var $buoop = {notify:{i:10,f:-2,o:-2,s:-2,c:-2},insecure:true,unsupported:true,api:5};
function $buo_f(){
    var e = document.createElement("script");
    e.src = "//browser-update.org/update.min.js";
    document.body.appendChild(e);
}
try {document.addEventListener("DOMContentLoaded", $buo_f,false)}
catch(e){window.attachEvent("onload", $buo_f)}
