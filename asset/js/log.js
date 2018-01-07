$(document).ready(function() {
    $('#content').on('click', 'a.search', function(e) {
        e.preventDefault();
        var sidebar = $('#sidebar-search');
        Omeka.openSidebar(sidebar);

        // Auto-close if other sidebar opened
        $('body').one('o:sidebar-opened', '.sidebar', function () {
            if (!sidebar.is(this)) {
                Omeka.closeSidebar(sidebar);
            }
        });
    });

    $('a.popover').webuiPopover('destroy').webuiPopover({
        placement: 'auto-bottom',
        content: function (element) {
            var target = $('[data-target=' + element.id + ']');
            var content = target.closest('.webui-popover-parent').find('.webui-popover-current');
            $(content).removeClass('truncate').show();
            return content;
        },
        title: '',
        arrow: false,
        backdrop: true,
        onShow: function(element) { element.css({left: 0}); }
    });
});
