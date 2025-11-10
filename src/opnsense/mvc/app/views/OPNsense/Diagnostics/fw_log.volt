{#
 # Copyright (c) 2014-2025 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}

<script>
    class RingBuffer {
        constructor(capacity) {
            if (capacity <= 0) throw new Error("capacity must be > 0");
            this.capacity = capacity;
            this.buf = new Array(capacity);
            this.head = 0;         // points to most recent item
            this.length = 0;       // number of valid items (≤ capacity)

            this._subs = new Set();
        }

        subscribe(handler) {
            this._subs.add(handler);

            return () => this._subs.delete(handler); // prevent a separate unsubscribe function()
        }

        _emit(event) {
            for (const h of this._subs) {
                h(event);
            }
        }

        reset(src) {
            const cap = this.capacity;
            let k = Math.min(src.length, cap);
            this.length = k;

            // this assumes src[0] is the most recent data
            for (let i = 0; i < k; i++) {
                this.buf[i] = src[i];
            }
            this.head = 0;

            this._emit({
                type:'reset',
            })
        }

        resize(newCapacity) {
            if (!Number.isInteger(newCapacity) || newCapacity <= 0) {
                throw new Error("newCapacity must be a positive integer");
            }
            if (newCapacity === this.capacity) return this;

            const oldCapacity = this.capacity;
            const oldLength = this.length;

            const m = Math.min(oldLength, newCapacity);
            const newBuf = new Array(newCapacity);

            for (let i = 0; i < m; i++) {
                newBuf[i] = this.get(i);
            }

            this.buf = newBuf;
            this.capacity = newCapacity;
            this.length = m;
            this.head = 0;
            return this;
        }

        // Insert as "most recent". O(1)
        push(value) {
            this.head = (this.head - 1 + this.capacity) % this.capacity;
            this.buf[this.head] = value;
            if (this.length < this.capacity) this.length++;
            else {
                // when full, we overwrote the oldest; nothing else to do
            }

            this._emit({
                type:'push',
                data: value
            });
        }

        pushMany(values) {
            const cap = this.capacity;
            const n = values.length;
            if (n === 0) return;

            // We can only keep up to cap newest items
            const m = Math.min(n, cap);

            // Where the newest item (values[0]) will land
            let start = (this.head - m) % cap;
            if (start < 0) start += cap; // JS % can be negative

            // Write in two chunks to handle wraparound
            const firstLen = Math.min(m, cap - start); // chunk at end
            for (let i = 0; i < firstLen; i++) this.buf[start + i] = values[i];
            const secondLen = m - firstLen;              // chunk at start
            for (let i = 0; i < secondLen; i++) this.buf[i] = values[firstLen + i];

            // Update head and length
            this.head = start;
            this.length = Math.min(cap, this.length + n);

            this._emit({
                type: 'pushMany',
                data: values
            });
        }

        clear() {
            this.head = 0;
            this.length = 0;
            this.buf.fill(undefined); // technically not necessary

            this._emit({
                type: 'clear',
            });
        }

        // i = 0 -> most recent; i = length-1 -> oldest
        get(i) {
            if (i < 0 || i >= this.length) return undefined;
            return this.buf[(this.head + i) % this.capacity];
        }

        set(i, value) {
            if (i < 0 || i >= this.length) return;
            this.buf[i] = value;
        }

        // Remove and return oldest. O(1)
        pop() {
            if (this.length === 0) return undefined;
            const idx = (this.head + this.length - 1) % this.capacity;
            const v = this.buf[idx];
            this.length--;
            return v;
        }

        copyTo(dst, n) {
            if (!(dst instanceof RingBuffer)) throw new Error("dst must be a RingBuffer");
            dst.clear();
            const m = Math.min(n, this.length);
            let tmp = [];
            for (let i = 0; i <= m; i++) {
                tmp.push(this.get(i));
            }
            dst.pushMany(tmp);
        }

        // Materialize as a real array, newest → oldest. O(n) but only when called.
        toArray() {
            const out = new Array(this.length);
            for (let i = 0; i < this.length; i++) out[i] = this.get(i);
            return out;
        }

        // practical zero-copy buffer operations (no clone to array necessary)

        // usage: for (const item of buffer)
        *[Symbol.iterator]() {
            for (let i = 0; i < this.length; i++) {
                yield this.buf[(this.head + i) % this.capacity];
            }
        }

        // Find first matching item (newest-first)
        find(predicate) {
            for (let i = 0; i < this.length; i++) {
                const v = this.buf[(this.head + i) % this.capacity];
                if (predicate(v, i)) return v;
            }
            return undefined;
        }

        // usage: const errors = buffer.filter(item => item.type === 'error')
        filter(predicate) {
            const results = [];
            for (let i = 0; i < this.length; i++) {
                const v = this.buf[(this.head + i) % this.capacity];
                if (predicate(v, i)) results.push(v);
            }
            return results;
        }

        // usage: for (const x of buffer.filterIter(item => item.type === 'error'))
        // practical when we need to bail early
        *filterIter(predicate) {
            for (let i = 0; i < this.length; i++) {
                const v = this.buf[(this.head + i) % this.capacity];
                if (predicate(v, i)) yield v;
            }
        }

        map(fn) {
            const { buf, head, length, capacity } = this;
            if (length === 0) return this;

            const end = head + length; // exclusive
            let k = 0;                 // logical index 0..length-1

            if (end <= capacity) {
                // single contiguous block
                for (let i = head; i < end; i++, k++) {
                    const v = buf[i];
                    buf[i] = fn.call(this, v, k, this);
                }
            } else {
                // first segment: head..capacity-1
                for (let i = head; i < capacity; i++, k++) {
                    const v = buf[i];
                    buf[i] = fn.call(this, v, k, this);
                }
                // second segment: 0..(end - capacity - 1)
                const wrapEnd = end - capacity;
                for (let i = 0; i < wrapEnd; i++, k++) {
                    const v = buf[i];
                    buf[i] = fn.call(this, v, k, this);
                }
            }

            return this;
        }
    }

    class FilterViewModel {
        /**
         * Represents the live view as a model,
         * maintaining a snapshot of the most recent entries
         * into the main bucket. The snapshot can be updated
         * with filters, using the bucket to fetch the data.
         *
         * This model also maintains the main live log
         * table.
         */
        constructor(bucket, table = null, bufferSize = 25) {
            this.bufferSize = bufferSize;
            this.bucket = bucket;
            this.table = table;
            this.viewBuffer = new RingBuffer(this.bufferSize);
            this.filterStore = {
                mode: 'AND',
                filters: {},
                combinedFilters: {}
            }
            this.globalQuery = '';

            this.bucket.subscribe(event => this._onBucketEvent(event));
            this.viewBuffer.subscribe(event => this._onViewBufferEvent(event));
        }

        _init() {
            // pushes this.bufferSize entries to the models' buffer
            this.bucket.copyTo(this.viewBuffer, this.bufferSize);
        }

        _onBucketEvent(event) {
            switch (event.type) {
                case 'reset':
                    this._init();
                    break;
                case 'push':
                    if (this._passesCurrentFilters(event.data)) {
                        this.viewBuffer.push(event.data);
                    }
                    break;
                case 'pushMany':
                    const tmp = event.data.filter(record => this._passesCurrentFilters(record));
                    this.viewBuffer.pushMany(tmp);
                    break;
            }
        }

        /**
         * Do table changes based on viewbuffer data change events.
         * Since the viewbuffer is a reflection of the table, any data
         * mutated on the viewbuffer is considered valid (passed the filter(s)).
         */
        _onViewBufferEvent(event) {
            switch (event.type) {
                case 'push':
                case 'pushMany':
                case 'reset':
                    const holder = $('#livelog-table > .tabulator-tableholder')[0];
                    const scrollPos = holder.scrollTop;
                    this.table.clearData();
                    this.table.setData(this.viewBuffer.toArray());
                    holder.scrollTop = scrollPos;
                    $('.tooltip:visible').hide();
                    break;
                case 'clear':
                    this.table.clearData();
                    $('.tooltip:visible').hide();
                    break;
            }
        }

        _hashFilter({field, operator, value}) {
            const str = [
                String(field ?? '').trim().toLowerCase(),
                String(operator ?? '').trim().toLowerCase(),
                String(value ?? '').trim().toLowerCase(),
            ].join('|');


            let h = 5381;
            for (let i = 0; i < str.length; i++) h = ((h << 5) + h) ^ str.charCodeAt(i);
            // to unsigned 32-bit and hex
            return (h >>> 0).toString(16);
        }

        /**
         * Build a predicate from a simple UI filter.
         * @param {string} field - key in the record, e.g. "src"
         * @param {'contains'|'does not contain'|'is'|'is not'} operator
         * @param {string} value - user input
         * @returns {(item: Record<string, string>) => boolean}
         */
        _buildPredicate(field, operator, value) {
            const needle = String(value ?? '').toLowerCase().trim();

            return (item) => {
                const haystack = String(item?.[field] ?? '').toLowerCase();

                switch (operator) {
                case '~':
                    return haystack.includes(needle);
                case '!~':
                    return !haystack.includes(needle);
                case '=':
                    return haystack === needle;
                case '!=':
                    return haystack !== needle;
                default:
                    return true; // unknown operator -> pass everything
                }
            };
        }

        /**
         * Special predicate for global search on top of current filters (if any)
         */
        _matchesAnyValue(item) {
            if (!this.globalQuery) return true; // empty search → no extra filtering
            const q = String(this.globalQuery).toLowerCase().trim();
            if (!q) return true;
            // Since values are strings, scan them all (partial, case-insensitive)
            const vals = Object.values(item);
            for (let i = 0; i < vals.length; i++) {
                // converting this to a String will also implicitly join array values to "a,b,c" (i.e. __spec__)
                const v = String(vals[i] ?? '').toLowerCase();
                if (v.includes(q)) return true;
            }
            return false;
        }

        _buildFilterFn() {
            const preds = [
                ...Object.values(this.filterStore.filters ?? {})
                    .map(f => this._buildPredicate(f.field, f.operator, f.value)),
                ...Object.values(this.filterStore.combinedFilters ?? {})
                    .map(cf => {
                        const fns = cf.field.map(field => this._buildPredicate(field, cf.operator, cf.value));

                        return (item) => {
                            if (cf.operator.match('!')) {
                                // negative case: none of the fields may contain (AND)
                                cf.field.toString = function() {
                                    return this.join(" {{lang._('and')}} ");
                                };
                                return fns.every(f => f(item));
                            } else {
                                // positive case: OR
                                cf.field.toString = function() {
                                    return this.join(" {{lang._('or')}} ");
                                };
                                return fns.some(f => f(item));
                            }
                        };
                    })
            ];

            const hasGlobal = this.globalQuery !== '';
            const isNoop = preds.length === 0 && !hasGlobal;
            const mode = this.filterStore.mode;

            const fieldsPass = (record) => {
                if (preds.length === 0) return true;
                if (mode === 'OR')  return preds.some(p => p(record));
                if (mode === 'AND') return preds.every(p => p(record));
                return false;
            };

            const fn = (record) => {
                if (isNoop) return true;
                if (!fieldsPass(record)) return false;

                // - if we had any field predicates, always also require _matchesAnyValue
                // - if we only have a global query, require _matchesAnyValue
                // (_matchesAnyValue should return true when globalQuery is empty)
                return (preds.length > 0 || hasGlobal) ? this._matchesAnyValue(record) : true;
            };

            return { fn, isNoop };
        }

        /**
         * checks if a record passes the currently applied filters
         * and global search query. Same logic as
         * _filterChange(), but no state is modified.
         */
        _passesCurrentFilters(record) {
            return this._buildFilterFn().fn(record);
        }

        /**
         * Called for any change to the filter state (filters added/removed or global
         * search string modified). This function is idempotent.
         */
        _filterChange() {
            const { fn, reset } = this._buildFilterFn();

            if (reset) {
                this._init();
                return;
            }

            const result = this.bucket.filter(fn);

            this.viewBuffer.reset(result);
        }


        /**
         * public API
         */

        /**
         * data has changed, re-render (with applied filters)
         */
        reset() {
            this._filterChange();
        }

        setBufferSize(bufferSize) {
            this.bufferSize = bufferSize;
            this.viewBuffer.resize(this.bufferSize);
            this.reset();
        }

        /**
         * Update existing records in the table. Records supplied but not found in
         * the table (indexed by __digest__) are ignored.
         */
        updateTable(records) {
            this.table.updateData(records).catch((error) => {});
        }

        setFilterMode(mode = 'AND') {
            if (this.filterStore.mode !== mode) {
                this.filterStore.mode = mode;

                this._filterChange();
            }
        }

        getFilterMode() {
            return this.filterStore.mode;
        }

        /**
         * Example: { field: 'src', operator: '=', value: '192.168.1.1', format:'RFC1918' (optional) }
         * The optional 'format' parameter replaces the value for display purposes only
         */
        addFilter(filter) {
            const id = this._hashFilter(filter);
            this.filterStore.filters[id] = filter;
            this._filterChange();
        }

        /**
         * Example: { field: ['src', 'dst'], operator: '!=', value: '192.168.1.1' }
         * The meaning of a combined filter is dependent on the operator. In the case of a
         * positive operator, "OR" is used, otherwise "AND".
         */
        addCombinedFilter(filter) {
            const id = this._hashFilter(filter);
            this.filterStore.combinedFilters[id] = filter;
            this._filterChange();
        }

        removeFilter(id) {
            delete this.filterStore.filters[id];
            delete this.filterStore.combinedFilters[id];
            this._filterChange();
        }

        getFilters() {
            return {...this.filterStore.filters, ...this.filterStore.combinedFilters};
        }

        clearFilters(refresh=true) {
            this.filterStore.filters = {};
            this.filterStore.combinedFilters = {};

            if (refresh) this._filterChange();
        }

        setGlobalSearch(value) {
            this.globalQuery = value;
            this._filterChange();
        }
    }

    $(document).ready(function() {
        function fetch_log(last_digest=null, limit=null) {
            const map = {
                'pass': 0,
                'nat': 1,
                'rdr': 1,
                'binat': 1,
                'block': 2
            }
            return new Promise((resolve, reject) => {
                ajaxGet('/api/diagnostics/firewall/log/', {'digest': last_digest, 'limit': limit}, function(data, status) {
                    if (status == 'error' || data === undefined || data.length === 0) reject();
                    for (let record of data) {
                        // initial data formatting for front-end purposes
                        record['status'] = map[record['action']];

                        // make sure the hostname key exists
                        record['srchostname'] = hostnames.get(record.src);
                        record['dsthostname'] = hostnames.get(record.dst);
                    }

                    resolve(data);
                });
            });
        }

        function poller(interval) {
            let last_digest = null;
            if (buffer.get(0)) {
                last_digest = buffer.get(0)['__digest__'];
            }
            fetch_log(last_digest).then((data) => {
                // length check already passed
                if (data[0]['__digest__'] === last_digest) {
                    return;
                }
                data.pop(); // data includes last seen digest as last item
                buffer.pushMany(data);
            });

            pollTimeout = setTimeout(poller, interval, interval);
        }

        function stopPoller() {
            clearTimeout(pollTimeout);
            pollTimeout = null;
        }

        function debounce(f, delay = 50, ensure = true) {
            // debounce to prevent a flood of calls in a short time
            let lastCall = Number.NEGATIVE_INFINITY;
            let wait;
            let handle;
            return (...args) => {
                wait = lastCall + delay - Date.now();
                clearTimeout(handle);
                if (wait <= 0 || ensure) {
                    handle = setTimeout(() => {
                        f(...args);
                        lastCall = Date.now();
                    }, wait);
                }
            };
        }

        function renderFilters() {
            $list.empty();
            const entries = Object.entries(filterVM.getFilters());
            if (entries.length === 0) {
                return;
            }

            // Build chips
            let i = 0;
            for (const [id, f] of entries) {
                if (i > 0) {
                    $list.append(
                        $(`<li class="filter-sep" role="separator" aria-hidden="true">${filterVM.getFilterMode()}</li>`)
                    );
                }
                const $chip = $(`
                    <li class="filter-chip badge" data-id="${id}">
                    <span>${f.field} ${operatorMap[f.operator].translation} “${f.format ?? f.value}”</span>
                    <button aria-label="Remove filter" title="Remove filter">&times;</button>
                    </li>
                `);
                $chip.find('button').on('click', function () {
                    filterVM.removeFilter(id);
                    renderFilters();
                });
                $list.append($chip);
                i++;
            }
        }

        /**
         * add new filters template
         * @param t_data template's parameters
         */
        function addTemplate(t_data) {
            ajaxCall('/api/diagnostics/lvtemplate/add_item/', t_data, function(data, status) {
                if (data.result == "saved") {
                    updateTemplatesSelect(data.uuid);
                } else {
                    BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: "{{ lang._('Add filters template') }}",
                    message: "{{ lang._('Template save failed. Message: ') }}" + data.result,
                    buttons: [{
                        label: "{{ lang._('Close') }}",
                        action: function (dialogRef) {
                            dialogRef.close();
                        }
                        }]
                    });
                    updateTemplatesSelect();
                }
            })
        }

        /**
         * set template new values
         * @param t_id template uuid
         * @param t_data template's parameters
         */
        function editTemplate(t_id, t_data) {
            ajaxCall('/api/diagnostics/lvtemplate/set_item/' + t_id, t_data, function(data, status) {
                if (data.result == "saved") {
                    updateTemplatesSelect(t_id);
                } else {
                    BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: "{{ lang._('Filters template edit') }}",
                    message: "{{ lang._('Template edit failed. Message: ') }}" + data.result,
                    buttons: [{
                        label: "{{ lang._('Close') }}",
                        action: function (dialogRef) {
                            dialogRef.close();
                        }
                        }]
                    });
                    updateTemplatesSelect(t_id);
                }
            })
        }

        /**
         * delete filters template
         * @param t_id template uuid
         */
        function delTemplate(t_id) {
            ajaxCall('/api/diagnostics/lvtemplate/del_item/' + t_id, {}, function(data, status) {
                if (data.result == "deleted") {
                    filterVM.clearFilters();
                    renderFilters();
                    updateTemplatesSelect();
                } else {
                    BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: "{{ lang._('Filters template delete') }}",
                    message: "{{ lang._('Template delete failed. Result: ') }}" + data.result,
                    buttons: [{
                        label: "{{ lang._('Close') }}",
                        action: function (dialogRef) {
                            dialogRef.close();
                        }
                        }]
                    });
                }
            })
        }

        let hostnames = new Map();

        const tableWrapper = $("#livelog-table").UIBootgrid({
            options: {
                static: true,
                ajax: false,
                navigation: 0,
                selection: false,
                multiSelect: false,
                virtualDOM: true,
                formatters: {
                    direction: function(column, row, onRendered) {
                        return row.dir == 'in' ? "{{ lang._('In') }}" : "{{ lang._('Out') }}";
                    },
                    lookup: function(column, row, onRendered) {
                        const value = row[column.id.replace("hostname", "")];
                        // deal with IPs we haven't seen before
                        if (!hostnames.get(value)) hostnames.set(value, null);
                        return hostnames.get(value) || '<span class="fa fa-spinner fa-pulse"></span>';
                    },
                    proto: function(column, row, onRendered) {
                        return row.protoname.toUpperCase();
                    },
                    appendPort: function(column, row, onRendered) {
                        const side = column.id === 'src' ? 'src' : 'dst';
                        const ip = row[side], port = row[`${side}port`];
                        return port ? `${row.ipversion == 6 ? `[${ip}]` : ip}:${port}` : ip;
                    },
                    interface: function(column, row, onRendered) {
                        return interfaceMap[row[column.id]] ?? row[column.id];
                    },
                    info: function(column, row, onRendered) {
                        onRendered((cell) => {
                            $(cell.getElement()).click(function() {
                                let sender_details = row;
                                let hidden_columns = ['__spec__', '__host__', '__digest__'];
                                let map_icon = ['dir', 'action'];
                                let sorted_keys = Object.keys(sender_details).sort();
                                let tbl = $('<table class="table table-condensed table-hover"/>');
                                let tbl_tbody = $("<tbody/>");
                                for (let i=0 ; i < sorted_keys.length; i++) {
                                    if (hidden_columns.indexOf(sorted_keys[i]) === -1 ) {
                                        let row = $("<tr/>");
                                        let icon = null;
                                        if (map_icon.indexOf(sorted_keys[i]) !== -1) {
                                            if (field_type_icons[sender_details[sorted_keys[i]]] !== undefined) {
                                                icon = $("<i/>");
                                                icon.addClass("fa fa-fw").addClass(field_type_icons[sender_details[sorted_keys[i]]]);
                                            }
                                        }
                                        row.append($("<td/>").text(sorted_keys[i]));
                                        if (sorted_keys[i] == 'rid') {
                                        // rid field, links to rule origin
                                        let rid = sender_details[sorted_keys[i]];
                                        let rid_td = $("<td/>").addClass("act_info_fld_"+sorted_keys[i]);
                                        if (rid.length >= 32) {
                                            let rid_link = $("<a target='_blank' href='/firewall_rule_lookup.php?rid=" + rid + "'/>");
                                            rid_link.text(rid);
                                            rid_td.append($("<i/>").addClass('fa fa-fw fa-search'));
                                            rid_td.append(rid_link);
                                        }
                                        row.append(rid_td);
                                        } else if (icon === null) {
                                        row.append($("<td/>").addClass("act_info_fld_"+sorted_keys[i]).html(
                                            sender_details[sorted_keys[i]]
                                        ));
                                        } else {
                                        row.append($("<td/>")
                                            .append(icon)
                                            .append($("<span/>").addClass("act_info_fld_"+sorted_keys[i]).text(
                                                " [" + sender_details[sorted_keys[i]] + "]"
                                            ))
                                        );
                                        }
                                        tbl_tbody.append(row);
                                    }
                                }
                                tbl.append(tbl_tbody);
                                BootstrapDialog.show({
                                    title: "{{ lang._('Detailed rule info') }}",
                                    message: tbl,
                                    type: BootstrapDialog.TYPE_INFO,
                                    draggable: true,
                                    buttons: [{
                                        label: '<i class="fa fa-search" aria-hidden="true"></i>',
                                        action: function(){
                                            $(this).unbind('click');
                                            $(".act_info_fld_srchostname, .act_info_fld_dsthostname").each(function(){
                                                let target_field = $(this);
                                                const dir = target_field.attr('class').indexOf('src') > -1 ? 'src' : 'dst';
                                                const ip = sender_details[dir];
                                                const hostname = hostnames.get(ip);
                                                if (hostname && hostname !== '<in-flight>') {
                                                    target_field.text(hostname);
                                                } else {
                                                    ajaxGet('/api/diagnostics/dns/reverse_lookup', {'address': ip}, function(data, status) {
                                                        if (data[ip] != undefined) {
                                                            let resolv_output = data[ip];
                                                            hostnames.set(ip, resolv_output);
                                                            target_field.text(resolv_output);
                                                        }
                                                    });
                                                }
                                            });
                                        }
                                    },{
                                        label: "{{ lang._('Close') }}",
                                        action: function(dialogItself){
                                        dialogItself.close();
                                        }
                                    }]
                                });
                            });
                        })
                        return '<button class="act_info btn btn-xs fa fa-info-circle" aria-hidden="true"></i>'
                    }
                },
                statusMapping: {
                    0: "fw-pass",
                    1: "fw-nat",
                    2: "fw-block",
                }
            },
            tabulatorOptions: {
                index: "__digest__",
                autoResize: false,
                addRowPos: 'top',
                persistence: false, // ideally persistence should be on, but we have no reset button at the moment due to missing navigation
                height:undefined,
                layout:"fitColumns",
                pagination: false
            }
        });

        const $global = $('#globalSearch');
        const $filterField = $('#filter-field');
        const $filterOperator = $('#filter-operator')
        const $filterValue = $('#filter-value');
        const $interfaceSelect = $('#interface-select');
        const $apply = $('#apply-filter');
        const $list = $('#filtersList');
        const $tableSizeSelect = $('#table-size');
        $tableSizeSelect.selectpicker();
        const $historySizeSelect = $('#history-size');
        $historySizeSelect.selectpicker();

        const operatorMap = {
            '~': {val: 'contains', translation: "{{ lang._('contains') }}"},
            '=': {val: 'is', translation: "{{ lang._('is') }}"},
            '!~': {val: 'does not contain', translation: "{{ lang._('does not contain') }}"},
            '!=': {val: 'is not', translation: "{{ lang._('is not') }}"}
        }

        const field_type_icons = {
          'binat': 'fa-exchange',
          'block': 'fa-ban',
          'in': 'fa-arrow-right',
          'nat': 'fa-exchange',
          'out': 'fa-arrow-left',
          'pass': 'fa-play',
          'rdr': 'fa-exchange'
        };

        const table = tableWrapper.bootgrid('getTable');
        const seedAmount = 10000;
        const buffer = new RingBuffer(seedAmount);
        const filterVM = new FilterViewModel(buffer, table);
        let pollTimeout = null;
        let interfaceMap = {};
        let bufferDataUnsubscribe = null;

        $apply.on('click', function () {
            const field = $filterField.val();
            const operator = $filterOperator.val();
            const searchString = $filterValue.val().trim();
            if (!searchString && field !== 'interface') return;

            switch (field) {
                case '__addr__':
                    filterVM.addCombinedFilter({field: ['src', 'dst'], operator: operator, value: searchString});
                    break;
                case '__port__':
                    filterVM.addCombinedFilter({field: ['srcport', 'dstport'], operator: operator, value: searchString});
                    break;
                case 'interface':
                    filterVM.addFilter({field: field, operator: operator, value: $interfaceSelect.val(), format: $interfaceSelect.find('option:selected').text()});
                    break;
                default:
                    filterVM.addFilter({ field: field, operator: operator, value: searchString });
                    break;
            }

            renderFilters();
        });

        $filterValue.on('keydown', function (e) {
            if (e.key === 'Enter') $apply.trigger('click');
        });

        // Global instant (debounced) search
        $global.on('input', debounce(function (e) {
            globalQuery = (e.currentTarget && e.currentTarget.value) || '';
            filterVM.setGlobalSearch(globalQuery);
        }, 250));

        $('#refresh').click(function (e) {
            if (buffer.get(0)) {
                last_digest = buffer.get(0)['__digest__'];
                fetch_log(last_digest).then((data) => {
                    // length check already passed
                    if (data[0]['__digest__'] === last_digest) {
                        return;
                    }
                    data.pop(); // data includes last seen digest as last item
                    buffer.pushMany(data);
                });
            }
        });

        $(document).on('change', '.filters-right input[type="checkbox"]', function() {
            const id = this.id;
            const checked = this.checked;

            switch (id) {
                case 'tg-lookup':
                    if (checked) {
                        const lookup = (maxPerRequest = 50) => {
                            const pending = [];
                            for (const [addr, val] of hostnames) {
                                if (!val && addr) { // new entries are null or already being processed
                                    pending.push(addr);
                                    hostnames.set(addr, '<in-flight>');
                                }
                            }

                            if (pending.length === 0) return [];

                            // Split into chunks
                            const batches = [];
                            for (let i = 0; i < pending.length; i += maxPerRequest) {
                                batches.push(pending.slice(i, i + maxPerRequest));
                            }

                            return batches.map(addresses =>
                                new Promise((resolve, reject) => {
                                    $.ajax({
                                        type: 'GET',
                                        url: '/api/diagnostics/dns/reverse_lookup',
                                        dataType: 'json',
                                        contentType: 'application/json',
                                        data: { address: addresses },
                                        complete: function (xhr) {
                                            const data = xhr && xhr.responseJSON;
                                            if (data) {
                                                addresses.forEach(addr => {
                                                    const resolved = data && data[addr];
                                                    hostnames.set(addr, resolved || addr);
                                                });
                                                resolve();
                                            } else {
                                                // still mark something reasonable to avoid leaving <in-flight>
                                                addresses.forEach(addr => hostnames.set(addr, addr));
                                                reject(new Error('Invalid response for batch: ', addresses));
                                            }
                                        },
                                        error: function (_xhr, status, err) {
                                            // clear in-flight flags on error
                                            addresses.forEach(addr => hostnames.set(addr, null));
                                            reject(err || new Error(status || 'Request failed'));
                                        }
                                    });
                                })
                            );
                        };

                        tableWrapper.bootgrid('setColumns', ['srchostname', 'dsthostname']);

                        // lookup new entries
                        const updateViewBuffer = () => {
                            const promises = lookup();
                            promises.forEach(p => p.then(() => {
                                filterVM.viewBuffer.map((record) => {
                                    if (hostnames.get(record.src) !== '<in-flight>') {
                                        record['srchostname'] = hostnames.get(record.src);
                                    }
                                    if (hostnames.get(record.dst) !== '<in-flight>') {
                                        record['dsthostname'] = hostnames.get(record.dst);
                                    }
                                    return record;
                                });
                                filterVM.reset();
                            }));
                        };


                        bufferDataUnsubscribe = filterVM.viewBuffer.subscribe((event) => {
                            updateViewBuffer();
                        });

                        // lookup current view ("lookup" grid formatter will have collected addresses)
                        // we do not lookup the entire buffer, as most items aren't relevant yet until
                        // specifically asked for.
                        filterVM.reset();

                    } else {
                        if (bufferDataUnsubscribe !== null) {
                            bufferDataUnsubscribe();
                            bufferDataUnsubscribe = null;
                            tableWrapper.bootgrid('unsetColumns', ['srchostname', 'dsthostname']);
                            filterVM.reset();
                        }
                    }
                    break;
                case 'tg-poll':
                    if (checked) {
                        if (pollTimeout == null) {
                            poller(1000);
                        }
                    } else {
                        stopPoller();
                    }
                    break;
                case 'tg-exclusive':
                    filterVM.setFilterMode(checked ? 'OR' : 'AND');
                    renderFilters();
                    break;
            }
        });

        // Main startup logic
        tableWrapper.on("load.rs.jquery.bootgrid", function() {
            $(`#livelog-table > .tabulator-tableholder`)
                .prepend($('<span class="bootgrid-overlay"><i class="fa fa-spinner fa-spin"></i></span>'));
        });

        $interfaceSelect.selectpicker();
        $interfaceSelect.selectpicker('hide');

        $filterField.on('change', function(e) {
            const val = $(this).val();
            if (val === 'interface') {
                $interfaceSelect.selectpicker('show');
                $filterValue.hide();
            } else {
                $interfaceSelect.selectpicker('hide');
                $filterValue.show();
            }
        });

        ajaxGet('/api/diagnostics/interface/get_interface_names', {}, function(data, status) {
            interfaceMap = data;

            for (const [key, value] of Object.entries(interfaceMap)) {
                $interfaceSelect.append(`<option value="${key}">${value}</option>`);
            }

            $interfaceSelect.selectpicker('refresh');

            fetch_log(null, seedAmount).then((data) => {
                buffer.reset(data);
                $(`#livelog-table > .tabulator-tableholder > .bootgrid-overlay`).remove();

                poller(1000);
            });

            renderFilters();
        });

        /**
         * Template logic
         */

        const $templateSelect = $('#templateSelect');
        const $stageTemplate = $('#stageTemplate');
        const $saveTemplate = $('#saveTemplate');
        const $deleteTemplate = $('#deleteTemplate');
        const $cancelTemplate = $('#cancelTemplate');
        const $templateName = $('#templateName');
        $templateSelect.selectpicker();
        let staging = false;

        function updateTemplatesSelect(uuid=null) {
            staging = false;
            $saveTemplate.hide();
            $deleteTemplate.show();
            $templateName.hide();
            $cancelTemplate.hide();
            $stageTemplate.show();
            $templateSelect.empty();
            $templateSelect.append(`<option value="__none__">{{ lang._('None') }}</option>`);
            $templateSelect.selectpicker('show');
            $templateSelect.selectpicker('refresh');
            ajaxGet('/api/diagnostics/lvtemplate/search_item', {}, function(data, status) {
                if (data.rows && data.rows.length > 0) {
                    for (const template of data.rows) {
                        const $opt = $(`<option>`, {value: template.uuid, text: template.name, selected: template.uuid === uuid})
                        .data('template', template);
                        $templateSelect.append($opt);
                    }
                    $templateSelect.selectpicker('refresh');
                }
            });
        }

        updateTemplatesSelect();

        $stageTemplate.click(function(e) {
            staging = true;
            $deleteTemplate.hide();
            $saveTemplate.show();
            $stageTemplate.hide();
            $cancelTemplate.show();
            $templateSelect.find('option[value="__none__"]').remove();
            $templateSelect.prepend(`<option value="__new__" data-content="<span class='fa-solid fa-file'></span> {{ lang._('New') }}"></option>`);
            $templateSelect.selectpicker('refresh');
            $templateSelect.selectpicker('toggle');
        });

        $cancelTemplate.click(function(e) {
            staging = false;
            $saveTemplate.hide();
            $deleteTemplate.show();

            $cancelTemplate.hide();
            $stageTemplate.show();
            $saveTemplate.hide();
            $deleteTemplate.show();
            $templateSelect.find('option[value="__new__"]').remove();
            $templateSelect.prepend(`<option value="__none__">{{ lang._('None') }}</option>`);
            $templateSelect.selectpicker('refresh');
            $templateName.hide();
            $templateSelect.selectpicker('show');
        });

        $templateSelect.on('changed.bs.select', function(e) {
            const val = $(this).val();

            if (staging) {
                $deleteTemplate.hide();
                $saveTemplate.show();
            } else {
                $saveTemplate.hide();
                $deleteTemplate.show();
            }

            if (val === '__new__') {
                $templateSelect.selectpicker('hide');
                $templateName.show();
            } else if (val === '__none__') {
                filterVM.clearFilters(true);
                renderFilters();
                $('#tg-exclusive').prop('checked', false);
            } else {
                // template got selected, apply filters
                filterVM.clearFilters(false);
                const tmpl = $(this).find('option:selected').data('template');
                const parseTemplate = (template) => {
                    const or = template.or;
                    const mode = or === "1" ? 'OR' : 'AND';
                    const filters = template.filters.split(',').map(val => {
                        let interface = null;
                        const parts = val.split(/(!=|!~|=|~)/);
                        // XXX consolidate with earlier parsing
                        let field = parts[0];
                        switch (field) {
                            case '__addr__':
                                field = ['src', 'dst'];
                                break;
                            case '__port__':
                                field = ['srcport', 'dstport']
                            case 'interface':
                            case 'interface_name':
                                interface = interfaceMap[parts[2]];
                                field = 'interface';
                                break;
                        }
                        return {field: field, operator: parts[1], value: parts[2], format: interface};
                    })

                    return {filters, mode}
                };
                const {filters, mode} = parseTemplate(tmpl);
                for (const filter of filters) {
                    if (Array.isArray(filter.field)) {
                        filterVM.addCombinedFilter(filter);
                    } else {
                        filterVM.addFilter(filter);
                    }
                }
                filterVM.setFilterMode(mode);
                if (mode === 'OR') {
                    $('#tg-exclusive').prop('checked', true);
                } else {
                    $('#tg-exclusive').prop('checked', false);
                }
                renderFilters();
            }
        });

        $saveTemplate.click(function(e) {
            const filters = Object.values(filterVM.getFilters()).map(f => {
                return `${f.field}${f.operator}${f.value}`;
            }).join(',');
            if ($templateName.val().length >= 1 && $templateName.is(':visible')) {
                const name = $templateName.val();

                addTemplate({
                    'template': {
                        'name': name,
                        'filters': filters,
                        'or': filterVM.getFilterMode() === 'OR' ? 1 : 0
                    }
                });
            } else if ($templateName.val().length == 0 && $templateName.is(':hidden')) {
                const template = $templateSelect.find('option:selected').data('template');
                editTemplate(template.uuid, {
                    'template': {
                        'name': template.name,
                        'or': filterVM.getFilterMode() === 'OR' ? 1 : 0,
                        'filters': filters
                    }
                })
            }
        });

        $deleteTemplate.click(function(e) {
            const opt = $templateSelect.find('option:selected');
            if (opt.val() !== '__none__') {
                const id = opt.data('template').uuid;
                delTemplate(id);
            }
        });

        $tableSizeSelect.on('changed.bs.select', function(e) {
            filterVM.setBufferSize(parseInt($(this).val()));
        });

        $historySizeSelect.on('changed.bs.select', function(e) {
            const bufSize = parseInt($(this).val());
            buffer.resize(bufSize);
            stopPoller();
            fetch_log(null, bufSize).then((data) => {
                buffer.reset(data);
                poller(1000);
            });
        });
    });
</script>

<style>
.fw-pass {
    background: rgba(5, 142, 73, 0.3);
}
.fw-block {
    background: rgba(235, 9, 9, 0.3);
}
.fw-nat {
    background: rgba(73, 173, 255, 0.3);
}

.filters-wrap { display:flex; gap:1rem; align-items:center; margin:0.5rem 0; }
.filters-list { display:flex; gap:0.5rem; flex-wrap:wrap; margin:0.25rem 0 0; padding:0; list-style:none; align-items: center; }
.filter-chip { border-radius:999px; padding:0.25rem 0.6rem; display:flex; gap:0.4rem; align-items:center; }
.filter-chip button { border:none; background:transparent; cursor:pointer; font-weight:bold; }
.muted { color:#666; font-size:0.9em; }
.stack { display:flex; flex-direction:column; gap:0.35rem; }

.filters-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;   /* keep tops aligned */
  gap: 2rem;
  min-height: 130px;
  margin-bottom: 10px;
}

.filters-middle {
    /* margin-left: auto;
    margin-right: auto; */
    display: flex;
    flex-direction: row;
    gap: 0.75rem;
    /* min-width: 260px; */
}

/* Right column */
.filters-right {
  margin-left: auto;         /* push all the way right */
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-width: 260px;
}

/* Toggle group row */
.toggle-group {
  display: flex;
  flex-wrap: wrap;
  flex-direction: column;
}

/* Right side actions */
.filters-actions {
  display: flex;
  gap: 1rem;
  align-items: center;
}

/* Responsive: stack on narrow screens */
@media (max-width: 800px) {
  .filters-bar {
    flex-direction: column;
    align-items: stretch;
  }
  .filters-right {
    margin-left: 0;
    align-items: flex-start;
  }
  .filters-actions { justify-content: flex-start; }
}

</style>

<div class="tab-content content-box" style="padding: 10px;">

    <div class="filters-bar">
        <div class="filters-left">
            <div class="filters-ui">
                <!-- Live global search (matches ANY value in a record) -->
                <div class="filters-wrap">
                    <input id="globalSearch" type="text" placeholder="{{ lang._('Quick search (all fields)…') }}" />
                    <button id="refresh" class="btn btn-default" type="button" title="{{ lang._('Refresh') }}">
                        <span class="icon fa-solid fa-arrows-rotate"></span>
                    </button>
                </div>

                <div class="filters-wrap">
                    <select id="filter-field" class="selectpicker" data-width="120px">
                        <option value="action">{{ lang._('action') }}</option>
                        <option value="interface">{{ lang._('interface') }}</option>
                        <option value="dir">{{ lang._('dir') }}</option>
                        <option value="__timestamp__">{{ lang._('Time') }}</option>
                        <option value="src">{{ lang._('src') }}</option>
                        <option value="srchostname">{{ lang._('srchostname') }}</option>
                        <option value="srcport">{{ lang._('src_port') }}</option>
                        <option value="dst">{{ lang._('dst') }}</option>
                        <option value="dsthostname">{{ lang._('dsthostname') }}</option>
                        <option value="dstport">{{ lang._('dst_port') }}</option>
                        <option value="__addr__">{{ lang._('address') }}</option>
                        <option value="__port__">{{ lang._('port') }}</option>
                        <option value="protoname">{{ lang._('protoname') }}</option>
                        <option value="label">{{ lang._('label') }}</option>
                        <option value="rid">{{ lang._('rule id') }}</option>
                        <option value="tcpflags">{{ lang._('tcpflags') }}</option>
                    </select>
                    <select id="filter-operator" class="selectpicker" data-width="150px">
                        <option value="~" selected=selected>{{ lang._('contains') }}</option>
                        <option value="=">{{ lang._('is') }}</option>
                        <option value="!~">{{ lang._('does not contain') }}</option>
                        <option value="!=">{{ lang._('is not') }}</option>
                    </select>
                    <input id="filter-value" type="text" placeholder="Search…" style="width: 200px;"/>
                    <select id="interface-select" class="selectpicker" data-width="200px"></select>
                    <button id="apply-filter" class="btn">{{ lang._('Apply') }}</button>
                </div>

                <div class="muted">{{ lang._('Active filters')}}:</div>
                <ul id="filtersList" class="filters-list"></ul>
            </div>
        </div>

        <aside class="filters-middle">

        </aside>

        <aside class="filters-right">
            <div>
                <div class="muted">{{ lang._('Templates')}}</div>
                <button id="stageTemplate" class="btn btn-default">
                    <span class="fa fa-angle-double-right"></span>
                </button>
                <button id="cancelTemplate" class="btn btn-default" style="display: none;">
                    <span class="fa fa-times"></span>
                </button>
                <select id="templateSelect" class="selectpicker" data-width="200px" title="{{ lang._('Choose template') }}"></select>
                <input id="templateName" type="text" placeholder="{{ lang._('Template name...') }}" style="display: none; width: 200px;"/>
                <button id="deleteTemplate" class="btn btn-default">
                    <span class="fa fa-trash"></span>
                </button>
                <button id="saveTemplate" class="btn btn-default" style="display: none;">
                    <span class="fa fa-save"></span>
                </button>
            </div>

            &nbsp;

            <div class="toggle-group">
                <div class="muted">{{ lang._('Options')}}</div>
                <label class="toggle">
                    <input type="checkbox" id="tg-poll" checked/>
                    <span class="toggle-ui"></span>
                    <span class="fa fa-refresh"></span>
                    <span class="toggle-label">{{ lang._('Auto-refresh') }}</span>
                </label>

                <label class="toggle">
                    <input type="checkbox" id="tg-lookup" />
                    <span class="toggle-ui"></span>
                    <span class="fa fa-search"></span>
                    <span class="toggle-label">{{ lang._('Lookup hostnames') }}</span>
                </label>

                <label class="toggle">
                    <input type="checkbox" id="tg-exclusive" />
                    <span class="toggle-ui"></span>
                    <span class="fa fa-filter"></span>
                    <span class="toggle-label">{{ lang._('Select any of given criteria (or)') }}</span>
                </label>
            </div>

            <div>
                <select id="table-size" class="selectpicker" data-width="100px">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="75">75</option>
                    <option value="100">100</option>
                </select>
                <label>{{ lang._('Table size') }}</label>
            </div>
            <div>
                <select id="history-size" class="selectpicker" data-width="100px">
                    <option value="10000">10000</option>
                    <option value="20000">20000</option>
                    <option value="30000">30000</option>
                </select>
                <label>{{ lang._('History size') }}</label>
            </div>
        </aside>
    </div>

    <table id="livelog-table" class="table table-condensed table-hover table-striped table-responsive">
        <thead>
            <tr>
                <th data-column-id="__digest__" data-identifier="true" data-sortable="false" data-visible="false">{{ lang._('Digest') }}</th>
                <th data-column-id="interface" data-type="string" data-formatter="interface" data-sortable="false" data-width="80">{{ lang._('Interface') }}</th>
                <th data-column-id="dir" data-type="string" data-formatter="direction" data-sortable="false" data-width="30"></th>
                <th data-column-id="__timestamp__" data-sortable="false" data-width="150">{{ lang._('Time') }}</th>
                <th data-column-id="protoname" data-sortable="false" data-formatter="proto" data-width="80">{{ lang._('Protocol') }}</th>
                <th data-column-id="src" data-type="string" data-formatter="appendPort" data-sortable="false">{{ lang._('Source') }}</th>
                <th data-column-id="srchostname" data-type="string" data-formatter="lookup" data-sortable="false" data-visible="false">{{ lang._('Source Hostname') }}</th>
                <th data-column-id="dst" data-type="string" data-formatter="appendPort" data-sortable="false">{{ lang._('Destination') }}</th>
                <th data-column-id="dsthostname" data-type="string" data-formatter="lookup" data-sortable="false" data-visible="false">{{ lang._('Destination Hostname') }}</th>
                <th data-column-id="action" data-type="string" data-sortable="false" data-width="80">{{ lang._('Action') }}</th>
                <th data-column-id="label" data-type="string" data-sortable="false">{{ lang._('Label') }}</th>
                <th data-column-id="status" data-type="string" data-sortable="false" data-visible="false">{{ lang._('Status') }}</th>
                <th data-column-id="" data-sortable="false" data-formatter="info" data-width="30"></th>
            </tr>
        </thead>
    </table>
</div>
