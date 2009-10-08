
addInitEvent(function(){
    var frm = $('nsexport__auto');
    if(!frm) return;

    frm.submit();
    frm.innerHTML = LANG['plugins']['nsexport']['loading'];
});
