/**
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the new BSD license.
 *
 * @author      David Zeller <me@zellerda.com>
 * @license     http://www.opensource.org/licenses/BSD-3-Clause New BSD license
 * @version     2.6
 */
(function($, tokenize){

    // Keycodes
    var KEYS = {
        BACKSPACE: 8,
        TAB: 9,
        ENTER: 13,
        ESCAPE: 27,
        ARROW_UP: 38,
        ARROW_DOWN: 40
    };

    // Debounce timeout
    var debounce_timeout = null;

    // Data storage constant
    var DATA = 'tokenize';

    /**
     * Get Tokenize object
     *
     * @param {Object} options
     * @param {jQuery} el
     * @returns {$.tokenize}
     */
    var getObject = function(options, el){

        if(!el.data(DATA)){
            var obj = new $.tokenize($.extend({}, $.fn.tokenize.defaults, options));
            el.data(DATA, obj);
            obj.init(el);
        }

        return el.data(DATA);

    };

    /**
     * Tokenize constructor
     *
     * @param {Object} opts
     */
    $.tokenize = function(opts){

        if(opts == undefined){
            opts = $.fn.tokenize.defaults;
        }

        this.options = opts;
    };

    $.extend($.tokenize.prototype, {

        /**
         * Init tokenize object
         *
         * @param {jQuery} el jQuery object of the select
         */
        init: function(el){

            var $this = this;
            this.select = el.attr('multiple', 'multiple').css({margin: 0, padding: 0, border: 0}).hide();

            this.container = $('<div />')
                .attr('class', this.select.attr('class'))
                .addClass('Tokenize');

            if(this.options.maxElements == 1){
                this.container.addClass('OnlyOne');
            }

            this.dropdown = $('<ul />')
                .addClass('Dropdown');

            this.tokensContainer = $('<ul />')
                .addClass('TokensContainer');

            if(this.options.autosize){
                this.tokensContainer
                    .addClass('Autosize');
            }

            this.searchToken = $('<li />')
                .addClass('TokenSearch')
                .appendTo(this.tokensContainer);

            this.searchInput = $('<input />')
                .appendTo(this.searchToken);

            if(this.options.searchMaxLength > 0){
                this.searchInput.attr('maxlength', this.options.searchMaxLength)
            }

            if(this.select.prop('disabled')){
                this.disable();
            }

            if(this.options.sortable){
                if (typeof $.ui != 'undefined'){
                    this.tokensContainer.sortable({
                        items: 'li.Token',
                        cursor: 'move',
                        placeholder: 'Token MovingShadow',
                        forcePlaceholderSize: true,
                        update: function(){
                            $this.updateOrder();
                        },
                        start: function(){
                            $this.searchToken.hide();
                        },
                        stop: function(){
                            $this.searchToken.show();
                        }
                    }).disableSelection();
                } else {
                    this.options.sortable = false;
                    console.error('jQuery UI is not loaded, sortable option has been disabled');
                }
            }

            this.container
                .append(this.tokensContainer)
                .append(this.dropdown)
                .insertAfter(this.select);

            this.tokensContainer.on('click', function(e){
                e.stopImmediatePropagation();
                $this.searchInput.get(0).focus();
                $this.updatePlaceholder();
                if($this.dropdown.is(':hidden') && $this.searchInput.val() != ''){
                    $this.search();
                }
            });

            this.searchInput.on('blur', function(){
                if($this.searchInput.val()){
                    $this.tokenAdd($this.searchInput.val(), '');
                }
                $this.resetPendingTokens();
                $this.tokensContainer.removeClass('Focused');
            });

            this.searchInput.on('focus click', function(){
                $this.tokensContainer.addClass('Focused');
                if($this.options.displayDropdownOnFocus && $this.options.datas == 'select'){
                    $this.search();
                }
            });

            this.searchInput.on('keydown', function(e){
                $this.resizeSearchInput();
                $this.keydown(e);
            });

            this.searchInput.on('keyup', function(e){
                $this.keyup(e);
            });

            this.searchInput.on('keypress', function(e){
                $this.keypress(e);
            });

            this.searchInput.on('paste', function(){
                setTimeout(function(){ $this.resizeSearchInput(); }, 10);
                setTimeout(function(){
                    var paste_elements = [];
                    if(Array.isArray($this.options.delimiter)){
                        paste_elements = $this.searchInput.val().split(new RegExp($this.options.delimiter.join('|'), 'g'));
                    } else {
                        paste_elements = $this.searchInput.val().split($this.options.delimiter);
                    }
                    if(paste_elements.length > 1){
                        $.each(paste_elements, function(_, value){
                            $this.tokenAdd(value.trim(), '');
                        });
                    }
                }, 20);
            });

            $(document).on('click', function(){
                $this.dropdownHide();
                if($this.options.maxElements == 1){
                    if($this.searchInput.val()){
                        $this.tokenAdd($this.searchInput.val(), '');
                    }
                }
            });

            this.resizeSearchInput();
            this.remap(true);
            this.updatePlaceholder();

        },

        /**
         * Update elements order in the select html element
         */
        updateOrder: function(){

            if(this.options.sortable){
                var previous, current, $this = this;
                $.each(this.tokensContainer.sortable('toArray', {attribute: 'data-value'}), function(k, v){
                    current = $('option[value="' + v + '"]', $this.select);
                    if(previous == undefined){
                        current.prependTo($this.select);
                    } else {
                        previous.after(current);
                    }
                    previous = current;
                });

                this.options.onReorder(this);
            }

        },

        /**
         * Update placeholder visibility
         */
        updatePlaceholder: function(){

            if(this.options.placeholder){
                if(this.placeholder == undefined){
                    this.placeholder = $('<li />').addClass('Placeholder').html(this.options.placeholder);
                    this.placeholder.insertBefore($('li:first-child', this.tokensContainer));
                }

                if(this.searchInput.val().length == 0 && $('li.Token', this.tokensContainer).length == 0){
                    this.placeholder.show();
                } else {
                    this.placeholder.hide();
                }
            }

        },

        /**
         * Display the dropdown
         */
        dropdownShow: function(){

            this.dropdown.show();
            this.options.onDropdownShow(this);

        },

        /**
         * Move the focus on the dropdown previous element
         */
        dropdownPrev: function(){

            if($('li.Hover', this.dropdown).length > 0){
                if(!$('li.Hover', this.dropdown).is('li:first-child')){
                    $('li.Hover', this.dropdown).removeClass('Hover').prev().addClass('Hover');
                } else {
                    $('li.Hover', this.dropdown).removeClass('Hover');
                    $('li:last-child', this.dropdown).addClass('Hover');
                }
            } else {
                $('li:first', this.dropdown).addClass('Hover');
            }

        },

        /**
         * Move the focus on the dropdown next element
         */
        dropdownNext: function(){

            if($('li.Hover', this.dropdown).length > 0){
                if(!$('li.Hover', this.dropdown).is('li:last-child')){
                    $('li.Hover', this.dropdown).removeClass('Hover').next().addClass('Hover');
                } else {
                    $('li.Hover', this.dropdown).removeClass('Hover');
                    $('li:first-child', this.dropdown).addClass('Hover');
                }
            } else {
                $('li:first', this.dropdown).addClass('Hover');
            }

        },

        /**
         * Add an item to the dropdown
         *
         * @param {string} value The value of the item
         * @param {string} text The display text of the item
         * @param {string|undefined} [html] The html display text of the item (override previous parameter)
         * @return {$.tokenize}
         */
        dropdownAddItem: function(value, text, html){

            html = html || text;

            if(!$('li[data-value="' + value + '"]', this.tokensContainer).length){
                var $this = this;
                var item = $('<li />')
                    .attr('data-value', value)
                    .attr('data-text', text)
                    .html(html)
                    .on('click', function(e){
                        e.stopImmediatePropagation();
                        $this.tokenAdd($(this).attr('data-value'), $(this).attr('data-text'));
                    }).on('mouseover', function(){
                        $(this).addClass('Hover');
                    }).on('mouseout', function(){
                        $('li', $this.dropdown).removeClass('Hover');
                    });

                this.dropdown.append(item);
                this.options.onDropdownAddItem(value, text, html, this);
            }

            return this;

        },

        /**
         * Hide dropdown
         */
        dropdownHide: function(){

            this.dropdownReset();
            this.dropdown.hide();

        },

        /**
         * Reset dropdown
         */
        dropdownReset: function(){

            this.dropdown.html('');

        },

        /**
         * Resize search input according the value length
         */
        resizeSearchInput: function(){

            this.searchInput.attr('size', Number(this.searchInput.val().length)+5);
            this.updatePlaceholder();

        },

        /**
         * Reset search input
         */
        resetSearchInput: function(){

            this.searchInput.val("");
            this.resizeSearchInput();

        },

        /**
         * Reset pending tokens
         */
        resetPendingTokens: function(){

            $('li.PendingDelete', this.tokensContainer).removeClass('PendingDelete');

        },

        /**
         * Keypress
         *
         * @param {object} e
         */
        keypress: function(e){

            var delimiter = false;

            if(Array.isArray(this.options.delimiter)){
                if(this.options.delimiter.indexOf(String.fromCharCode(e.which)) >= 0){
                    delimiter = true;
                }
            } else {
                if(String.fromCharCode(e.which) == this.options.delimiter){
                    delimiter = true;
                }
            }

            if(delimiter){
                e.preventDefault();
                this.tokenAdd(this.searchInput.val(), '');
            }

        },

        /**
         * Keydown
         *
         * @param {object} e
         */
        keydown: function(e){

            switch(e.keyCode){
                case KEYS.BACKSPACE:
                    if(this.searchInput.val().length == 0){
                        e.preventDefault();
                        if($('li.Token.PendingDelete', this.tokensContainer).length){
                            this.tokenRemove($('li.Token.PendingDelete').attr('data-value'));
                        } else {
                            $('li.Token:last', this.tokensContainer).addClass('PendingDelete');
                        }
                        this.dropdownHide();
                    }
                    break;

                case KEYS.TAB:
                case KEYS.ENTER:
                    if($('li.Hover', this.dropdown).length){
                        var element = $('li.Hover', this.dropdown);
                        e.preventDefault();
                        this.tokenAdd(element.attr('data-value'), element.attr('data-text'));
                    } else {
                        if(this.searchInput.val()){
                            e.preventDefault();
                            this.tokenAdd(this.searchInput.val(), '');
                        }
                    }
                    this.resetPendingTokens();
                    break;

                case KEYS.ESCAPE:
                    this.resetSearchInput();
                    this.dropdownHide();
                    this.resetPendingTokens();
                    break;

                case KEYS.ARROW_UP:
                    e.preventDefault();
                    this.dropdownPrev();
                    break;

                case KEYS.ARROW_DOWN:
                    e.preventDefault();
                    this.dropdownNext();
                    break;

                default:
                    this.resetPendingTokens();
                    break;
            }

        },

        /**
         * Keyup
         *
         * @param {object} e
         */
        keyup: function(e){

            this.updatePlaceholder();
            switch(e.keyCode){
                case KEYS.TAB:
                case KEYS.ENTER:
                case KEYS.ESCAPE:
                case KEYS.ARROW_UP:
                case KEYS.ARROW_DOWN:
                    break;

                case KEYS.BACKSPACE:
                    if(this.searchInput.val()){
                        this.search();
                    } else {
                        this.dropdownHide();
                    }
                    break;
                default:
                    if(this.searchInput.val()){
                        this.search();
                    }
                    break;
            }

        },

        /**
         * Search an element in the select or using ajax
         */
        search: function(){

            var $this = this;
            var count = 1;

            if((this.options.maxElements > 0 && $('li.Token', this.tokensContainer).length >= this.options.maxElements) ||
                this.searchInput.val().length < this.options.searchMinLength){
                return false;
            }

            if(this.options.datas == 'select'){

                var found = false, regexp = new RegExp(this.searchInput.val().replace(/[-[\]{}()*+?.,\\^$|#\s]/g, "\\$&"), 'i');
                this.dropdownReset();

                $('option', this.select).not(':selected, :disabled').each(function(){
                    if(count <= $this.options.nbDropdownElements){
                        if(regexp.test($(this).html())){
                            $this.dropdownAddItem($(this).attr('value'), $(this).html());
                            found = true;
                            count++;
                        }
                    } else {
                        return false;
                    }
                });

                if(found){
                    $('li:first', this.dropdown).addClass('Hover');
                    this.dropdownShow();
                } else {
                    this.dropdownHide();
                }

            } else {

                this.debounce(function(){
                    if(this.ajax !== undefined){
                        this.ajax.abort();
                    }
                    this.ajax = $.ajax({
                        url: $this.options.datas,
                        data: $this.options.searchParam + "=" + encodeURIComponent($this.searchInput.val()),
                        dataType: $this.options.dataType,
                        success: function(data){
                            if(data){
                                $this.dropdownReset();
                                $.each(data, function(key, val){
                                    if(count <= $this.options.nbDropdownElements){
                                        var html;
                                        if(val[$this.options.htmlField]){
                                            html = val[$this.options.htmlField];
                                        }
                                        $this.dropdownAddItem(val[$this.options.valueField], val[$this.options.textField], html);
                                        count++;
                                    } else {
                                        return false;
                                    }
                                });
                                if($('li', $this.dropdown).length){
                                    $('li:first', $this.dropdown).addClass('Hover');
                                    $this.dropdownShow();
                                    return true;
                                }
                            }
                            $this.dropdownHide();
                        },
                        error: function(xhr, text_status) {
                            $this.options.onAjaxError($this, xhr, text_status);
                        }
                    });
                }, this.options.debounce);

            }

        },

        /**
         * Debounce method for ajax request
         * @param {function} func
         * @param {number} threshold
         */
        debounce: function(func, threshold){

            var obj = this, args = arguments;
            var delayed = function(){
                func.apply(obj, args);
                debounce_timeout = null;
            };
            if(debounce_timeout){
                clearTimeout(debounce_timeout);
            }
            debounce_timeout = setTimeout(delayed, threshold || this.options.debounce);

        },

        /**
         * Add a token in container
         *
         * @param {string} value The value of the token
         * @param {string|undefined} [text] The label of the token (use value if empty)
         * @param {boolean|undefined} [first] If true, onAddToken event will be not called
         * @return {$.tokenize}
         */
        tokenAdd: function(value, text, first){

            value = this.escape(value).trim();

            if(value == undefined || value == ''){
                return this;
            }

            text = text || value;
            first = first || false;

            if(this.options.maxElements > 0 && $('li.Token', this.tokensContainer).length >= this.options.maxElements){
                this.resetSearchInput();
                return this;
            }

            var $this = this;
            var close_btn = $('<a />')
                .addClass('Close')
                .html("&#215;")
                .on('click', function(e){
                    e.stopImmediatePropagation();
                    $this.tokenRemove(value);
                });

            if($('option[value="' + value + '"]', this.select).length){
                if(!first && ($('option[value="' + value + '"]', this.select).attr('selected') === true ||
                    $('option[value="' + value + '"]', this.select).prop('selected') === true)){
                    this.options.onDuplicateToken(value, text, this);
                }
                $('option[value="' + value + '"]', this.select).attr('selected', true).prop('selected', true);
            } else if(this.options.newElements || (!this.options.newElements && $('li[data-value="' + value + '"]', this.dropdown).length > 0)) {
                var option = $('<option />')
                    .attr('selected', true)
                    .attr('value', value)
                    .attr('data-type', 'custom')
                    .prop('selected', true)
                    .html(text);
                this.select.append(option);
            } else {
                this.resetSearchInput();
                return this;
            }

            if($('li.Token[data-value="' + value + '"]', this.tokensContainer).length > 0) {
                return this;
            }

            $('<li />')
                .addClass('Token')
                .attr('data-value', value)
                .append('<span>' + text + '</span>')
                .prepend(close_btn)
                .insertBefore(this.searchToken);

            if(!first){
                this.options.onAddToken(value, text, this);
            }

            this.resetSearchInput();
            this.dropdownHide();
            this.updateOrder();

            return this;

        },

        /**
         * Remove a token
         *
         * @param {string} value The value of the token who has to be removed
         * @returns {$.tokenize}
         */
        tokenRemove: function(value){

            var option = $('option[value="' + value + '"]', this.select);

            if(option.attr('data-type') == 'custom'){
                option.remove();
            } else {
                option.removeAttr('selected').prop('selected', false);
            }

            $('li.Token[data-value="' + value + '"]', this.tokensContainer).remove();

            this.options.onRemoveToken(value, this);
            this.resizeSearchInput();
            this.dropdownHide();
            this.updateOrder();

            return this;

        },

        /**
         * Clear tokens
         *
         * @returns {$.tokenize}
         */
        clear: function(){

            var $this = this;

            $('li.Token', this.tokensContainer).each(function(){
                $this.tokenRemove($(this).attr('data-value'));
            });

            this.options.onClear(this);
            this.dropdownHide();

            return this;

        },

        /**
         * Disable tokenize
         *
         * @returns {$.tokenize}
         */
        disable: function(){

            this.select.prop('disabled', true);
            this.searchInput.prop('disabled', true);
            this.container.addClass('Disabled');
            if(this.options.sortable){
                this.tokensContainer.sortable('disable');
            }

            return this;

        },

        /**
         * Enable tokenize
         *
         * @returns {$.tokenize}
         */
        enable: function(){

            this.select.prop('disabled', false);
            this.searchInput.prop('disabled', false);
            this.container.removeClass('Disabled');
            if(this.options.sortable){
                this.tokensContainer.sortable('enable');
            }

            return this;

        },

        /**
         * Refresh tokens reflecting select options
         *
         * @param {boolean} first If true, onAddToken event will be not called
         * @returns {$.tokenize}
         */
        remap: function(first){

            var $this = this;
            var tmp = $("option:selected", this.select);

            first = first || false;

            this.clear();

            tmp.each(function(){
                $this.tokenAdd($(this).val(), $(this).html(), first);
            });

            return this;

        },

        /**
         * Retrieve tokens value to an array
         *
         * @returns {Array}
         */
        toArray: function(){

            var output = [];
            $("option:selected", this.select).each(function(){
                output.push($(this).val());
            });
            return output;

        },

        /**
         * Escape string
         *
         * @param {string} string
         * @returns {string}
         */
        escape: function(string){

            var tmp = document.createElement("div");
            tmp.innerHTML = string;
            string = tmp.textContent || tmp.innerText || "";

            return String(string).replace(/["]/g, function(){
                return '';
            });

        }

    });

    /**
     * Tokenize plugin
     *
     * @param {Object|undefined} [options]
     * @returns {$.tokenize|Array}
     */
    $.fn.tokenize = function(options){

        options = options || {};

        var selector = this.filter('select');

        if(selector.length > 1){
            var objects = [];
            selector.each(function(){
                objects.push(getObject(options, $(this)));
            });
            return objects;
        }
        else
        {
            return getObject(options, $(this));
        }
    };

    $.fn.tokenize.defaults = {

        datas: 'select',
        placeholder: false,
        searchParam: 'search',
        searchMaxLength: 0,
        searchMinLength: 0,
        debounce: 0,
        delimiter: ',',
        newElements: true,
        autosize: false,
        nbDropdownElements: 10,
        displayDropdownOnFocus: false,
        maxElements: 0,
        sortable: false,
        dataType: 'json',
        valueField: 'value',
        textField: 'text',
        htmlField: 'html',

        onAddToken: function(value, text, e){},
        onRemoveToken: function(value, e){},
        onClear: function(e){},
        onReorder: function(e){},
        onDropdownAddItem: function(value, text, html, e){},
        onDropdownShow: function(e){},
        onDuplicateToken: function(value, text, e){},
        onAjaxError: function(e, xhr, text_status){}

    };

})(jQuery, 'tokenize');
