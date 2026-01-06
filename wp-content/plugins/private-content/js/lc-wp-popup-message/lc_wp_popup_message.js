/* ------------------------------------------------------------------------
	* LC WordPress Popup Message
	* Minimal popup message, styled to be used in WordPreess admin area
	*
	* @version: 	1.2.1
	* @author:		Luca Montanari (LCweb)
	* @website:		https://lcweb.it
	
	* Licensed under the MIT license
------------------------------------------------------------------------- */

(function($){
	"use strict";
	if(typeof(window.lc_wp_popup_message) != 'undefined') {return false;} // prevent multiple script inits
    
    
    let style_appended = false;
    const append_style = () => {
        if(style_appended) {
            return true;    
        }
        style_appended = true;
        
        document.head.insertAdjacentHTML('beforeend', 
`<style>
#lc-wp-popup-mess,
#lc-wp-popup-mess *,
#lc-wp-popup-mess *:after,
#lc-wp-popup-mess *:before {
	box-sizing: border-box;
}
#lc-wp-popup-mess {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    overflow: auto; 
	background: rgba(0,0,0, .25);
	position: fixed;
	top: 0;
	right: -9999px;
	width: 100%;
	height: 100%;
	margin: auto;
	z-index: 99999;
	opacity: 0;
	transition: opacity .15s ease-in-out .05s, right 0s linear .5s;
}
#lc-wp-popup-mess.lcwpm_shown {
	opacity: 1;
	right: 0;
	transition: opacity .3s ease-in-out 0s, right 0s linear 0s;
}
#lc-wp-popup-mess > div {
	position: relative;
	padding: 15px 30px;
	border-radius: 2px;
	box-shadow: 0 3px 15px rgba(20, 20, 20, .35);
	display: inline-block;
    width: auto;
    min-width: 300px;
    max-width: min(calc(100vw - min(100px, 10vw)), 1200px);
    margin: min(50px, 6.5vw) 0;
	top: -13px;
	transition: top .2s linear 0s;
}
#lc-wp-popup-mess.lcwpm_shown > div {
	top: 0;
	transition: top .25s cubic-bezier(0.175, 0.885, 0.320, 2) .1s;
}
#lc-wp-popup-mess > div > span:after {
	background: #fff;
	border-radius: 50%;
	color: #bababa;
	content: "×";
	cursor: pointer;
	font-size: 21px;
	height: 25px;
	position: absolute;
	right: -7px;
	top: -7px;
	width: 25px;
	font-family: arial;
	line-height: 23px;
	text-align: center;
    transition: color .2s ease;
}
#lc-wp-popup-mess > div:hover > span:after {
	color: #a3a3a3;
}
#lc-wp-popup-mess > div {
	background: #fefefe;
	font-size: 1rem;
	line-height: normal;
}
#lc-wp-popup-mess > div p:not(:last-child),
#lc-wp-popup-mess > div ul:not(:last-child),
#lc-wp-popup-mess > div ol:not(:last-child) {
    margin-bottom: 1.25rem;
}
#lc-wp-popup-mess > div ol {
    list-style: decimal inside;
}
#lc-wp-popup-mess > div ul {
    list-style: disc inside;
}
.lcwpm_error {
	border-left: 5px solid #dd3d36;
}
.lcwpm_success {
	border-left: 5px solid #7ad03a;
}
.lcwpm_warn {
	border-left: 5px solid #efdd19;
}
.lcwpm_modal {
	background: #fff;
}
.lcwpm_modal > span {
	display: none;
}
</style>`);
    };
    
    
    
    /*
     * @param (string) type = popup type (success | error | warning | modal) 
     * @param (string) text = popup contents
     * @param (function) on_close = callback function to trigger whenever popup closes
     * @param (string) extra_class = extra class(es) to be added to the popup wrapper
     */
	window.lc_wp_popup_message = function(type, text, on_close = null, extra_class = '') {
        append_style();
        
        // append code 
        if(!document.getElementById('lc-wp-popup-mess')) {
            extra_class = (extra_class) ? 'class="'+ extra_class.replaceAll('"', '').replaceAll("'", '') +'"' : '';
            document.body.insertAdjacentHTML('beforeend', '<div id="lc-wp-popup-mess" '+ extra_class +'></div>');
        }
                                             
        const el = document.getElementById('lc-wp-popup-mess');
        const close_btn = '<span title="close"></span>';

        
        // setup
        let inner_class;
        switch(type) {
            case 'error' : 
                inner_class = 'lcwpm_error'; break;  
            
            case 'modal' : 
                inner_class = 'lcwpm_modal'; break; 
            
            case 'warning' : 
                inner_class = 'lcwpm_warn'; break; 
            
            case 'success' : 
            default : 
                inner_class = 'lcwpm_success'; break; 
        }
		el.innerHTML = '<div class="'+ inner_class +'"><div>'+ text +'</div>'+ close_btn +'</div>';	

        
        // close btn click event 
        if(document.querySelector('#lc-wp-popup-mess > div > span')) {
            document.querySelector('#lc-wp-popup-mess > div > span').addEventListener('click', function() {
                el.classList.remove('lcwpm_shown'); 

                // event on close?
                if(typeof(on_close) == 'function') {
                    on_close.call(this, type);
                }
            });
        }
        
        
        // success - auto-close after 2 secs
        if(type == 'success') {
			setTimeout(function() {
                if(document.querySelector('#lc-wp-popup-mess.lcwpm_shown span')) {
				    document.querySelector('#lc-wp-popup-mess.lcwpm_shown span').click();
                }
			}, 2150);	
		}

        
		// show - use a micro delay to let CSS animations propagate
		setTimeout(function() {
            el.classList.add('lcwpm_shown');
            window.focus();
		}, 30);	
	};
    
    
    
    // close popup binding Escape key
    document.onkeydown = function(evt) {
        evt = evt || window.event;
        var isEscape = false;
        
        if("key" in evt) {
            isEscape = (evt.key === "Escape" || evt.key === "Esc");
        } else {
            isEscape = (evt.keyCode === 27);
        }
        
        if(isEscape && document.querySelector('#lc-wp-popup-mess.lcwpm_shown')) {
            window.lcwpm_close();
        }
    };
    
    
    
    /* utility function to allow remote popup closing */
    window.lcwpm_close = function() {
        if(document.querySelector('#lc-wp-popup-mess.lcwpm_shown')) {
            document.getElementById('lc-wp-popup-mess').classList.remove('lcwpm_shown');
            return true;     
        }
        else {
            return false;    
        }
    };
    
})();
