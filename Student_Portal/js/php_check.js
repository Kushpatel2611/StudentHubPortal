/*
 * Explains the most common problem with this project: .php pages only work when
 * a PHP server runs them. If the site is opened with Live Server, a double-click
 * (file://) or any server that does not run PHP, the browser shows the PHP source
 * code instead of the page. This script
 *   1. shows a notice when the page itself is opened as file://, and
 *   2. checks .php links before following them and explains what to do instead
 *      of dumping source code on screen.
 */
(function () {
    'use strict';

    function showNotice(html) {
        var old = document.getElementById('sh-php-notice');
        if (old) { old.remove(); }
        var box = document.createElement('div');
        box.id = 'sh-php-notice';
        box.className = 'sh-notice';
        box.setAttribute('role', 'alert');
        box.innerHTML = html;
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn';
        close.textContent = 'Close';
        close.addEventListener('click', function () { box.remove(); });
        box.appendChild(close);
        document.body.insertBefore(box, document.body.firstChild);
    }

    var HELP = 'This page needs <strong>PHP</strong>. Start the site with <code>START_SERVER.bat</code> ' +
        '(or XAMPP Apache) and open <code>http://localhost:8000/</code> instead of using Live Server or a double-click.';

    if (location.protocol === 'file:') {
        document.addEventListener('DOMContentLoaded', function () {
            showNotice('You opened this file directly. ' + HELP);
        });
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest && event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 ||
            event.metaKey || event.ctrlKey || event.shiftKey || link.target === '_blank') {
            return;
        }
        var url;
        try { url = new URL(link.href, location.href); } catch (e) { return; }
        if (!/\.php$/i.test(url.pathname) || url.origin !== location.origin && location.protocol !== 'file:') {
            return;
        }

        if (location.protocol === 'file:') {
            event.preventDefault();
            showNotice(HELP);
            return;
        }

        var seen = false;
        try { seen = sessionStorage.getItem('sh-php-ok') === '1'; } catch (e) {}
        if (seen) { return; }   // PHP already proved to work in this tab

        event.preventDefault();
        fetch(url.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'php-check' } })
            .then(function (response) { return response.text(); })
            .then(function (text) {
                if (/^\s*<\?php/i.test(text)) {
                    showNotice('The server returned PHP source code instead of running it. ' + HELP);
                } else {
                    try { sessionStorage.setItem('sh-php-ok', '1'); } catch (e) {}
                    window.location.href = url.href;
                }
            })
            .catch(function () { window.location.href = url.href; });
    });
}());
