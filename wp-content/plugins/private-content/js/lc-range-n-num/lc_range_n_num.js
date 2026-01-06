/**
 * lc_range-n-num.js - HTML5 ranges + numerical input for uber UIs
 * Version: 1.0.1
 * Author: Luca Montanari (LCweb)
 * Website: https://lcweb.it
 * Licensed under the MIT license
 */


(function() { 
	"use strict";
      
    /*** vars ***/
    let debounced_vars  = [],
        style_generated = null;

    
    /*** default options ***/
    const def_opts = {
        num_width       : 'auto', // (int|string) defines number input width. "auto" to try calculating it basing on the max value, "none" to not set. Otherwise the static pixel number
        unit_width      : 'auto', // (int|string) defines unit element input width. "auto" to get it via JS on init, otherwise the static pixel number
        unit            : '', // (string) optional number unit adding context (eg. px or %)
        respect_limits  : true, // (bool) whether to force values into min/max ranges
        on_change       : null, // function(new_value, target_field) {}, - triggered every time field value changes. Passes value and target field object as parameters
    };
    
    
    
    /*** plugin class ***/
    window.lc_range_n_num = function(attachTo, options = {}) {
    
        this.attachTo = attachTo;
        if(!this.attachTo) {
            return console.error('You must provide a valid selector as first argument');
        }
    
        // override options
        if(typeof(options) !=  'object') {
            return console.error('Options must be an object');    
        }
        options = Object.assign({}, def_opts, options);
        

        
        /* initialize */
        this.init = function() {
            const $this = this;
            
            // Generate style
            if(!style_generated) {
                this.generate_style();
                style_generated = true;
            }
            
            
            // assign to each target element
            maybe_querySelectorAll(attachTo).forEach(function(el) {
                if(el.tagName == 'INPUT' && el.getAttribute('type') != 'number') {
                    return;    
                }

                // do not initialize twice
                if(el.parentNode.classList.length && el.parentNode.classList.contains('lcrnn-el-wrap')) {
                    return;    
                }
                
                $this.wrap_element(el);
            });
        };
    
        
        
        /* wrap target element to allow trigger display */
        this.wrap_element = function(el) {
            const $this     = this,
                  f_name    = el.getAttribute('name'),
                  range_name= 'lcrnn-'+ f_name;

            // wrap
            let div = document.createElement('div');
            div.classList.add("lcrnn-el-wrap");
            div.innerHTML = el.outerHTML.replace('type="number"', 'type="range"').replace("type='number'", "type='range'") + '<span class="lcrnn_spacer"></span>';

            el.parentNode.insertBefore(div, el);
            div.appendChild(el);
            div.querySelector('input[type=range]').setAttribute('name', range_name);
            
            
            // unit?
            let unit = '';
            if(options.unit) {
                unit = options.unit;    
            }
            if(el.hasAttribute('data-unit')) {
                unit = el.getAttribute('data-unit').trim();    
            }
            div.innerHTML += '<span class="lcrnn_unit" data-for="'+ f_name +'">'+ unit +'</span>';
            
            
            // num width
            el = div.querySelector('input[type=number]');
            
            let f_width;
            if(options.num_width != 'none') {
                
                if(options.num_width == 'auto' || !parseInt(options.num_width, 10)) {
                    f_width = 24 + Math.max(el.getAttribute('max').length, el.getAttribute('step').length) * 10;
                    f_width = f_width + 
                        parseInt(getComputedStyle(el)['paddingLeft'], 10) + parseInt(getComputedStyle(el)['paddingRight'], 10) + 
                        parseInt(getComputedStyle(el)['borderLeftWidth'], 10) + parseInt(getComputedStyle(el)['borderRightWidth'], 10);
                }
                else {
                    f_width = parseInt(options.num_width, 10);      
                }

                if(f_width < 50) {
                    f_width = 50;    
                }
                el.style.width = f_width +'px';   
            }
            
            
            // unit width
            let unit_width = 0;
            if(unit) {
                const unit_el = div.querySelector('.lcrnn_unit');
                
                if(options.unit_width == 'auto') {
                    unit_width = unit_el.getBoundingClientRect().width;        
                }
                else {
                    unit_width = parseInt(options.unit_width, 10) + 
                        parseInt(getComputedStyle(unit_el)['paddingLeft'], 10) + parseInt(getComputedStyle(unit_el)['paddingRight'], 10) + 
                        parseInt(getComputedStyle(unit_el)['borderLeftWidth'], 10) + parseInt(getComputedStyle(unit_el)['borderRightWidth'], 10);       
                }
            }
            
            
            // help CSS grid to properly space elements
            div.style.gridTemplateColumns = 'auto ' +
                getComputedStyle( div.querySelector('.lcrnn_spacer') )['width'] +' '+ 
                ((f_width) ? f_width : Math.ceil(el.getBoundingClientRect().width)) +'px ' + 
                unit_width + 'px';
            
            
            // handlers
            div.querySelector('input[type=range]').addEventListener("input", (e) => {
                $this.range_to_num_sync(div, e.target.value);
            });
            
            div.querySelector('input[type=number]').addEventListener("change", (e) => {
                $this.num_to_range_sync(e);
            });
            div.querySelector('input[type=number]').addEventListener("keyup", (e) => {
                $this.debounce('num_to_range_sync', 650, 'num_to_range_sync', e); 
            });
        };
        
        
        //////////////////////////
        

        // num to range sync
        this.num_to_range_sync = function(e) {
            const el = e.target,
                  min   = parseFloat(el.getAttribute('min')),
                  max   = parseFloat(el.getAttribute('max')),
                  type  = (el.getAttribute('step').indexOf('.') !== -1 || el.getAttribute('min').indexOf('.') !== -1 || el.getAttribute('max').indexOf('.') !== -1) ? 'float' : 'int';
            
            // value retrieval and validation
            let val = (type == 'float') ? parseFloat(el.value) : parseInt(el.value, 10); 
            if(!val.toString().length) {val = min;}
            
            // respect limits?
            if(el.hasAttribute('data-respect-limits')) {
                if(parseInt(el.getAttribute('data-respect-limits'), 10)) {
                    if(val < min) {val = min;}
                    if(val > max) {val = max;}    
                }
            }
            else if (options.respect_limits) {
                if(val < min) {val = min;}
                if(val > max) {val = max;}
            }
            
            el.value = val;
            el.parentNode.querySelector('input[type=range]').value = val;
            
            // event?
            if(typeof(options.on_change) == 'function') {
                options.on_change.call(this, val, el);
            }
        };
        
        
        // range to num sync
        this.range_to_num_sync = function(wrap, val) {
            wrap.querySelector('input[type=number]').value = val;
            
            // event?
            if(typeof(options.on_change) == 'function') {
                options.on_change.call(this, val, wrap.querySelector('input[type=number]'));
            }
        };
        
        
        //////////////////////////
        
        
        /* 
         * UTILITY FUNCTION - debounce action to run once after X time 
         *
         * @param (string) action_name
         * @param (int) timing - milliseconds to debounce
         * @param (string) - class method name to call after debouncing
         * @param (mixed) - extra parameters to pass to callback function
         */
        this.debounce = function(action_name, timing, cb_function, cb_params) {
            if( typeof(debounced_vars[ action_name ]) != 'undefined' && debounced_vars[ action_name ]) {
                clearTimeout(debounced_vars[ action_name ]);    
            }
            const $this = this;
            
            debounced_vars[ action_name ] = setTimeout(() => {
                $this[cb_function].call($this, cb_params);    
            }, timing); 
        };
        
        
        //////////////////////////
        
        
        /* CSS - creates inline CSS into the page */
        this.generate_style = function() {
            document.head.insertAdjacentHTML('beforeend', 
`<style>
.lcrnn-el-wrap, 
.lcrnn-el-wrap * {  
    box-sizing: border-box;
}
.lcrnn-el-wrap {
	display: grid;
	grid-column-gap: 0;
	grid-template-columns: auto 20px auto auto;
    align-items: center;
}
.lcrnn-el-wrap > * {
    margin: 0;
    min-width: 0;
    max-width: none;
}
.lcrnn-el-wrap input[type=number] {
    text-align: center;
}
.lcrnn_spacer {
    width: 20px;
	display: inline-block;
}
.lcrnn_unit:not(:empty) {
    padding-left: 7px;
}
.lcrnn-el-wrap input[type=range] {
    -webkit-appearance: none;
    height: 6px;
    padding: 0;
    margin: 0;
    background: #d5d5d5;
    outline: none;
    border: none;
}
.lcrnn-el-wrap input[type=range]::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 17px;
    height: 17px;
    background: #888;
    cursor: pointer;
    border-radius: 50%;
    border: 1px solid #aaa;
    box-shadow: 0 0 0 5px #fff inset, 0 0 2px rgba(0,0,0,.15);
}
.lcrnn-el-wrap input[type=range]::-moz-range-thumb {
    width: 15px;
    height: 15px;
    background: #888;
    cursor: pointer;
    border-radius: 50%;
    border: 1px solid #aaa;
    box-shadow: 0 0 0 5px #fff inset, 0 0 2px rgba(0,0,0,.15);
}
</style>`);
        };
        

        // init when called
        this.init();
    };
    
    
    
    
    
    // UTILITIES
    
    // sanitize "selector" parameter allowing both strings and DOM objects
    const maybe_querySelectorAll = (selector) => {
             
        if(typeof(selector) != 'string') {
            if(selector instanceof Element) { // JS or jQuery 
                return [selector];
            }
            else {
                let to_return = [];
                
                for(const obj of selector) {
                    if(obj instanceof Element) {
                        to_return.push(obj);    
                    }
                }
                return to_return;
            }
        }
        
        // clean problematic selectors
        (selector.match(/(#[0-9][^\s:,]*)/g) || []).forEach(function(n) {
            selector = selector.replace(n, '[id="' + n.replace("#", "") + '"]');
        });
        
        return document.querySelectorAll(selector);
    };
    
})();





/* Global utility function to refresh integration basing onn input attributes */
window.lcrnn_update = function(selector) {
    
    document.querySelectorAll(attachTo).forEach(function(el) {
        if(el.tagName == 'INPUT' && el.getAttribute('type') != 'number') {
            return;    
        }
        
        const parent = el.parentNode;
        if(!parent.classList.contains('lcrnn-el-wrap')) {
            return;     
        }
        
        const num   = parent.querySelector('input[type=number]'),
              range = parent.querySelector('input[type=range]');

        // unit?
        if(el.hasAttribute('data-unit')) {
            unit = el.getAttribute('data-unit').trim();   
            
            if(parent.querySelector('.lcrnn_unit').length) {
                if(unit) {
                    parent.querySelector('.lcrnn_unit').innerHTML = unit;        
                } else {
                    parent.querySelector('.lcrnn_unit').remove();        
                }
            }
            else if(unit) {
                parent.innerHTML += '<span class="lcrnn_unit" data-for="'+ num.getAttribute('name') +'">'+ unit +'</span>';
            }
        }
        
        
        // sync range
        range.setAttribute('min', num.getAttribute('min'));
        range.setAttribute('max', num.getAttribute('max'));
        range.setAttribute('step', num.getAttribute('step'));
        
        range.value = num.value;
    });
};
