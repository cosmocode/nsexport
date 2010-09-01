
/**
 * check if the dl is ready
 *
 * @param key file key
 */
function nsexport_check(key) {
    // build request
    var ajax = new sack(DOKU_BASE+'lib/exe/ajax.php');
    var param = 'call=nsexport_check&key=' + key;
    ajax.AjaxFailedAlert = '';
    ajax.encodeURIString = false;

    ajax.onCompletion = function(){
        var data = this.response;

        if(data === '1') {
            // download is ready - get it
            window.location = DOKU_BASE+'lib/plugins/nsexport/export.php?key=' + key;
            return false;
        }

        // download not ready - wait
        return false;
    };
    ajax.runAJAX(param);
}


addInitEvent(function(){
    // prepare ajax
    var ajax = new sack(DOKU_BASE+'lib/exe/ajax.php');
    var param = 'call=nsexport_start';
    var btn = $('do__export');

    ajax.AjaxFailedAlert = '';
    ajax.encodeURIString = false;

    var frm = $('nsexport__auto');

    ajax.onCompletion = function(){
        var data = this.response;
        if(data === '') {
            return;
        }
        // start waiting for dl
        setInterval("nsexport_check('" + data + "')", 10000);
        return false;
    };

    if (frm !== null) {
        // add export pages
        for (var i = 0; i < frm['export[]'].length; i++) {
            param += '&export[]=' + frm['export[]'][i].value;
        }
        ajax.runAJAX(param);
    } else if (btn !== null) {
        addEvent(btn, 'click', function(e){
            var form = btn.parentNode.parentNode;
            btn.parentNode.parentNode.style.display = 'none';

            var msg = document.createElement('div');
            msg.innerHTML = LANG.plugins.nsexport.loading
                + '<img src="' + DOKU_BASE + 'lib/images/throbber.gif" alt="..." />';

            btn.parentNode.parentNode.parentNode.appendChild(msg);

            // add export pages
            for (var i = 0; i < form['export[]'].length; i++) {
                if (form['export[]'][i].checked) {
                    param += '&export[]=' + form['export[]'][i].value;
                }
            }

            ajax.runAJAX(param);
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
    }
});
