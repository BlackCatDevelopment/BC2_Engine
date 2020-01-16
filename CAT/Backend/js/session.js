/*!
  * session.js
  */
function CATSecondsToTimeString(seconds) {
    if(!seconds>0) {
        seconds = 1;
    }
    return (new Date(seconds * 1000)).toUTCString().match(/(\d\d:\d\d:\d\d)/)[0];
}
function CATTimeStringToSecs(hms) {
    var a = hms.split(':'); // split it at the colons
    return (+a[0]) * 60 * 60 + (+a[1]) * 60 + (+a[2]);
}
function CATSessionSetTimer(sesstime,callback,elementid,warnclass)
{
    // set defaults
    if(typeof sesstime  == 'undefined') { sesstime  = 20;                        }
    if(typeof elementid == 'undefined') { var timer = $('div').appendTo('body'); }
    else                                { var timer = $(elementid);              }
    if(typeof warnclass == 'undefined') { warnclass = 'bg-danger';               }

    var warntime = sesstime * 0.2;
    if(warntime<20) warntime=20;

    var origcolor = timer.css("color");

    timer.text(CATSecondsToTimeString(sesstime));
    let timerId = setInterval(function() {
        var secs = CATTimeStringToSecs(timer.text())-1;
        if(secs <= warntime) { timer.parent().addClass(warnclass); }
        if(secs == 30)       { timer.addClass(warnclass); }
        if(secs == 0)        { timer.parent().removeClass(warnclass); clearInterval(timerId); callback(); }
        timer.text(CATSecondsToTimeString(secs));
    }, 1000);
}
