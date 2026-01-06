(function() {
    "use strict";
    
    
    window.nfpcf_inject_infobox = (target_elem, replace_elem = false) => {
        document.querySelectorAll(target_elem).forEach(elem => {
            const new_html = `<span class="pc_nfpcf pc_nfpcf_block pc_nfpcf_w_btn"></span>`;
            (replace_elem) ? elem.outerHTML = new_html : elem.innerHTML = new_html;   
        });
    };
    
    
    
    document.addEventListener('DOMContentLoaded', () => {
        if(typeof(jQuery) == 'undefined') {
            return false;
        }
        const $ = jQuery;
        
        let tooltip = $('<div class="pc_nfpcf_tooltip"></div>').appendTo('body');

        const set_tt_pos = (event) => {
            let tooltipWidth = tooltip.outerWidth();
            let tooltipHeight = tooltip.outerHeight();
            let windowWidth = $(window).width();
            let windowHeight = $(window).height();
            let x = event.clientX + 10;
            let y = event.clientY + 10;

            if (x + tooltipWidth > windowWidth) {
                x = event.clientX - tooltipWidth - 10;
            }
            if (y + tooltipHeight > windowHeight) {
                y = event.clientY - tooltipHeight - 10;
            }

            tooltip.css({ top: y + 'px', left: x + 'px' });
        }

        $(document).on('mouseenter', '.pc_nfpcf', function() {
            tooltip.html(nfpcf_vars.tt_txt).clearQueue().show();
        }).on('mouseleave', '.pc_nfpcf', function() {
            tooltip.clearQueue().hide();
        }).on('mousemove', '.pc_nfpcf', function(event) {
            set_tt_pos(event);
        });
    });
    
})();