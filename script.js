
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
    var btn = $('do__export');
    var frm = $('nsexport__auto');
    if (!btn && !frm) return;

    // prepare ajax
    var ajax = new sack(DOKU_BASE+'lib/exe/ajax.php');
    var param = 'call=nsexport_start';

    ajax.AjaxFailedAlert = '';
    ajax.encodeURIString = false;

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

        frm = getElementsByClass('plugin_nsexport__form', document, 'form')[0];
        function getItems() {
            return frm.getElementsByTagName('li');
        }

        function pagePosY(obj) {
            var c_val = 0;
            var c_elem = obj;
            do {
                c_val += c_elem.offsetTop;
                c_elem = c_elem.offsetParent;
            } while (c_elem);
            return c_val;
        }

        function getListItem(target, items) {
            for (var i = 0 ; i < items.length; ++i) {
                var startelem = items[i];
                var c_val = pagePosY(items[i]);
                if (target >= c_val && c_val >= target - startelem.offsetHeight) {
                    return i;
                }
            }
            return -1;
        }

        function NSExportDrag() {
            this.start = function (e) {
                document.body.style.cursor = 'move';
                return drag.start.call(this, e);
            };

            this.drag = function (e) {
                var items = getItems();
                var target = getListItem(e.pageY, items);
                if (target === -1) {
                    return false;
                }
                for (var mypos = 0 ; items[mypos] !== this.obj &&
                                     mypos < items.length ; ++mypos);
                if (target === mypos || mypos === items.length) {
                    return false;
                } else if (target > mypos) {
                    ++target;
                }
                this.obj.parentNode.insertBefore(this.obj,
                                                 items[target] || null);
                return false;
            };

            this.stop = function () {
                document.body.style.cursor = '';
                // Prevent unchecking of item
                addEvent(this.obj.firstChild.firstChild,'click', function (e) {
                    removeEvent(this, 'click', arguments.callee);
                    return false;
                });
                return drag.stop.call(this);
            };
        }
        NSExportDrag.prototype = drag;

        var items = getItems();
        for (var i = 0 ; i < items.length ; ++i) {
            (new NSExportDrag()).attach(items[i], items[i].getElementsByTagName('label')[0]);
        }
    }
});
