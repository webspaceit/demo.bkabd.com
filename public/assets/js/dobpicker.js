/**
 * Decade-first date-of-birth picker (vanilla JS, no dependencies).
 *
 * Markup convention:
 *   <div class="dob-field">
 *     <input type="text" data-dobpicker readonly placeholder="DD-MM-YYYY">
 *     <input type="hidden" data-dobpicker-value name="dob" value="1996-10-14">
 *   </div>
 *
 * The visible input shows DD-MM-YYYY for people; the paired hidden input
 * carries the ISO Y-m-d the server validates (`date_format:Y-m-d`). Loaded
 * only on the pages that opt in through $pageScripts (student registration),
 * together with /assets/css/dobpicker.css.
 *
 * The popup is appended to <body> so it can never push the surrounding form
 * grid around when it opens.
 */
(function () {
    'use strict';

    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];
    var DOW = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

    var CHEV_PREV = 'M15 18l-6-6 6-6';
    var CHEV_NEXT = 'M9 6l6 6-6 6';
    var CHEV_PREV2 = 'M11 17l-5-5 5-5M18 17l-5-5 5-5';
    var CHEV_NEXT2 = 'M13 7l5 5-5 5M6 7l5 5-5 5';

    function icon(path) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"'
            + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'
            + path + '"/></svg>';
    }

    function pad2(n) { return (n < 10 ? '0' : '') + n; }
    function toISO(y, m, d) { return y + '-' + pad2(m) + '-' + pad2(d); }
    function toHuman(y, m, d) { return pad2(d) + '-' + pad2(m) + '-' + y; }

    function parseISO(value) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value == null ? '' : value).trim());
        if (!m) { return null; }
        var y = +m[1], mo = +m[2], d = +m[3];
        if (mo < 1 || mo > 12 || d < 1 || d > 31) { return null; }
        if (d > new Date(y, mo, 0).getDate()) { return null; }
        return { y: y, m: mo, d: d };
    }

    function today() {
        var t = new Date();
        return { y: t.getFullYear(), m: t.getMonth() + 1, d: t.getDate() };
    }

    function cmp(a, b) { return (a.y - b.y) || (a.m - b.m) || (a.d - b.d); }

    function Picker(display) {
        this.display = display;
        var wrap = display.closest('.dob-field') || display.parentNode;
        this.hidden = wrap ? wrap.querySelector('[data-dobpicker-value]') : null;

        this.popup = null;
        this.isOpen = false;

        this.sel = this.hidden ? parseISO(this.hidden.value) : null;
        this.y = this.sel ? this.sel.y : (today().y - 20);
        this.m = this.sel ? this.sel.m : today().m;
        this.d = this.sel ? this.sel.d : 1;

        this._onDocDown = this._onDocDown.bind(this);
        this._onKey = this._onKey.bind(this);
        this._reposition = this._reposition.bind(this);
        this._onClick = this._onClick.bind(this);

        this._build();
        this._bind();
        this._sync();
    }

    Picker.prototype._build = function () {
        var p = document.createElement('div');
        p.className = 'dobp-popup';
        p.hidden = true;
        p.setAttribute('role', 'dialog');
        p.setAttribute('aria-label', 'Choose date of birth');
        p.innerHTML =
            '<div class="dobp-head">' +
                '<div style="display:flex">' +
                    '<button type="button" class="dobp-nav" data-nav="prev-decade" title="Previous 10 years">' + icon(CHEV_PREV2) + '</button>' +
                    '<button type="button" class="dobp-nav" data-nav="prev-month" title="Previous month">' + icon(CHEV_PREV) + '</button>' +
                '</div>' +
                '<span class="dobp-title" data-title></span>' +
                '<div style="display:flex">' +
                    '<button type="button" class="dobp-nav" data-nav="next-month" title="Next month">' + icon(CHEV_NEXT) + '</button>' +
                    '<button type="button" class="dobp-nav" data-nav="next-decade" title="Next 10 years">' + icon(CHEV_NEXT2) + '</button>' +
                '</div>' +
            '</div>' +
            '<div class="dobp-body">' +
                '<div class="dobp-sec"><span>Year</span><span data-decade></span></div>' +
                '<div class="dobp-years" data-years></div>' +
                '<div class="dobp-sec"><span>Month</span><span></span></div>' +
                '<div class="dobp-months" data-months></div>' +
                '<div class="dobp-dow">' + DOW.map(function (d) { return '<span>' + d + '</span>'; }).join('') + '</div>' +
                '<div class="dobp-days" data-days></div>' +
            '</div>' +
            '<div class="dobp-foot">' +
                '<button type="button" class="dobp-btn" data-today>Today</button>' +
                '<button type="button" class="dobp-btn is-muted" data-clear>Clear</button>' +
            '</div>';
        document.body.appendChild(p);

        this.popup = p;
        this.titleEl = p.querySelector('[data-title]');
        this.decadeEl = p.querySelector('[data-decade]');
        this.yearsEl = p.querySelector('[data-years]');
        this.monthsEl = p.querySelector('[data-months]');
        this.daysEl = p.querySelector('[data-days]');
        this.navPrevDecade = p.querySelector('[data-nav="prev-decade"]');
        this.navNextDecade = p.querySelector('[data-nav="next-decade"]');
        this.navPrevMonth = p.querySelector('[data-nav="prev-month"]');
        this.navNextMonth = p.querySelector('[data-nav="next-month"]');
    };

    Picker.prototype._bind = function () {
        var self = this;
        this.display.addEventListener('click', function () { self.toggle(); });
        this.display.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                e.preventDefault();
                self.open();
            } else if (e.key === 'Escape') {
                self.close();
            }
        });
        this.popup.addEventListener('click', this._onClick);
    };

    Picker.prototype._render = function () {
        var t = today();
        var dec = Math.floor(this.y / 10) * 10;

        this.titleEl.textContent = MONTHS[this.m - 1] + ' ' + this.y;
        this.decadeEl.textContent = dec + '\u2013' + (dec + 9);

        var html = '';
        for (var y = dec; y < dec + 10; y++) {
            html += '<button type="button" class="dobp-year' + (y === this.y ? ' is-active' : '')
                + '" data-year="' + y + '"' + (y > t.y ? ' disabled' : '') + '>' + y + '</button>';
        }
        this.yearsEl.innerHTML = html;

        html = '';
        for (var m = 1; m <= 12; m++) {
            var monthFuture = (this.y > t.y) || (this.y === t.y && m > t.m);
            html += '<button type="button" class="dobp-month' + (m === this.m ? ' is-active' : '')
                + '" data-month="' + m + '"' + (monthFuture ? ' disabled' : '') + '>'
                + MONTHS[m - 1].slice(0, 3) + '</button>';
        }
        this.monthsEl.innerHTML = html;

        var firstDow = new Date(this.y, this.m - 1, 1).getDay();
        var dim = new Date(this.y, this.m, 0).getDate();
        var prevDim = new Date(this.y, this.m - 1, 0).getDate();
        html = '';
        for (var i = 1; i <= firstDow; i++) {
            html += '<span class="dobp-day is-off">' + (prevDim - firstDow + i) + '</span>';
        }
        for (var d = 1; d <= dim; d++) {
            var isSel = this.sel && this.sel.y === this.y && this.sel.m === this.m && this.sel.d === d;
            var isToday = t.y === this.y && t.m === this.m && t.d === d;
            var dayFuture = this.y > t.y || (this.y === t.y && (this.m > t.m || (this.m === t.m && d > t.d)));
            html += '<button type="button" class="dobp-day' + (isSel ? ' is-active' : '') + (isToday ? ' is-today' : '')
                + '" data-day="' + d + '"' + (dayFuture ? ' disabled' : '') + '>' + d + '</button>';
        }
        var cells = Math.ceil((firstDow + dim) / 7) * 7;
        for (var k = 1; k <= cells - firstDow - dim; k++) {
            html += '<span class="dobp-day is-off">' + k + '</span>';
        }
        this.daysEl.innerHTML = html;

        var currentDecade = Math.floor(t.y / 10) * 10;
        this.navPrevDecade.disabled = dec <= 1900;
        this.navNextDecade.disabled = dec >= currentDecade;
        this.navPrevMonth.disabled = (this.y <= 1900 && this.m === 1);
        this.navNextMonth.disabled = (this.y === t.y && this.m === t.m);
    };

    Picker.prototype._shift = function (dy, dm) {
        var t = today();
        var y = this.y + dy;
        var m = this.m + dm;
        while (m > 12) { m -= 12; y += 1; }
        while (m < 1) { m += 12; y -= 1; }
        if (y > t.y) { y = t.y; m = t.m; }
        if (y === t.y && m > t.m) { m = t.m; }
        if (y < 1900) { y = 1900; m = 1; }
        this.y = y;
        this.m = m;
        this._render();
    };

    Picker.prototype._clamp = function () {
        var t = today();
        if (this.y > t.y) { this.y = t.y; }
        if (this.y === t.y && this.m > t.m) { this.m = t.m; }
    };

    Picker.prototype._onClick = function (e) {
        var el = e.target;
        if (!el || !el.closest) { return; }

        var nav = el.closest('[data-nav]');
        if (nav && !nav.disabled) {
            var k = nav.getAttribute('data-nav');
            if (k === 'prev-decade') { this._shift(-10, 0); }
            else if (k === 'next-decade') { this._shift(10, 0); }
            else if (k === 'prev-month') { this._shift(0, -1); }
            else if (k === 'next-month') { this._shift(0, 1); }
            return;
        }

        var yb = el.closest('[data-year]');
        if (yb && !yb.disabled) {
            this.y = +yb.getAttribute('data-year');
            this._clamp();
            this._render();
            return;
        }

        var mb = el.closest('[data-month]');
        if (mb && !mb.disabled) {
            this.m = +mb.getAttribute('data-month');
            this._clamp();
            this._render();
            return;
        }

        var db = el.closest('[data-day]');
        if (db && !db.disabled) {
            this._pick(+db.getAttribute('data-day'));
            return;
        }

        if (el.closest('[data-today]')) {
            var t = today();
            this.y = t.y;
            this.m = t.m;
            this._pick(t.d);
            return;
        }

        if (el.closest('[data-clear]')) {
            this.clear();
            this.close();
        }
    };

    Picker.prototype._sync = function () {
        if (this.sel) {
            if (this.hidden) { this.hidden.value = toISO(this.sel.y, this.sel.m, this.sel.d); }
            this.display.value = toHuman(this.sel.y, this.sel.m, this.sel.d);
        } else {
            if (this.hidden) { this.hidden.value = ''; }
            this.display.value = '';
        }
    };

    Picker.prototype._fire = function () {
        var ev;
        try {
            ev = new Event('change', { bubbles: true });
        } catch (err) {
            return;
        }
        if (this.hidden) { this.hidden.dispatchEvent(ev); }
    };

    Picker.prototype.clear = function () {
        this.sel = null;
        this._sync();
        if (this.isOpen) { this._render(); }
    };

    Picker.prototype._pick = function (d) {
        var t = today();
        var candidate = { y: this.y, m: this.m, d: d };
        if (cmp(candidate, t) > 0) { return; }
        this.sel = candidate;
        this._sync();
        this._fire();
        this.close();
        try { this.display.focus(); } catch (err) { /* noop */ }
    };

    Picker.prototype.open = function () {
        if (this.isOpen) { return; }
        this.isOpen = true;
        if (this.sel) {
            this.y = this.sel.y;
            this.m = this.sel.m;
            this.d = this.sel.d;
        }
        this._render();
        this.popup.hidden = false;
        this.display.setAttribute('aria-expanded', 'true');
        this._reposition();
        document.addEventListener('mousedown', this._onDocDown, true);
        document.addEventListener('keydown', this._onKey, true);
        window.addEventListener('resize', this._reposition, true);
        window.addEventListener('scroll', this._reposition, true);
    };

    Picker.prototype.close = function () {
        if (!this.isOpen) { return; }
        this.isOpen = false;
        this.popup.hidden = true;
        this.display.setAttribute('aria-expanded', 'false');
        document.removeEventListener('mousedown', this._onDocDown, true);
        document.removeEventListener('keydown', this._onKey, true);
        window.removeEventListener('resize', this._reposition, true);
        window.removeEventListener('scroll', this._reposition, true);
    };

    Picker.prototype.toggle = function () {
        if (this.isOpen) { this.close(); } else { this.open(); }
    };

    Picker.prototype._onDocDown = function (e) {
        if (this.popup.contains(e.target) || this.display === e.target || this.display.contains(e.target)) {
            return;
        }
        this.close();
    };

    Picker.prototype._onKey = function (e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            this.close();
            try { this.display.focus(); } catch (err) { /* noop */ }
        }
    };

    Picker.prototype._reposition = function () {
        var r = this.display.getBoundingClientRect();
        var pw = this.popup.offsetWidth;
        var ph = this.popup.offsetHeight;
        var vw = document.documentElement.clientWidth;
        var vh = document.documentElement.clientHeight;

        var left = r.left;
        if (left + pw > vw - 8) { left = vw - 8 - pw; }
        if (left < 8) { left = 8; }

        var top = r.bottom + 6;
        if (top + ph > vh - 8 && r.top - 6 - ph >= 8) { top = r.top - 6 - ph; }

        this.popup.style.left = Math.round(left + window.pageXOffset) + 'px';
        this.popup.style.top = Math.round(top + window.pageYOffset) + 'px';
    };

    function init() {
        var fields = document.querySelectorAll('input[data-dobpicker]');
        for (var i = 0; i < fields.length; i++) {
            if (fields[i]._dobp) { continue; }
            fields[i]._dobp = new Picker(fields[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Clears the picker + hidden ISO value (used after a successful submit).
    window.DOBPicker = {
        clear: function (target) {
            var hidden = typeof target === 'string' ? document.getElementById(target) : target;
            if (!hidden) { return; }
            var wrap = hidden.closest('.dob-field') || hidden.parentNode;
            var display = wrap ? wrap.querySelector('input[data-dobpicker]') : null;
            if (display && display._dobp) {
                display._dobp.sel = null;
                display._dobp._sync();
                display._dobp.close();
            } else {
                hidden.value = '';
                if (display) { display.value = ''; }
            }
        }
    };
})();
