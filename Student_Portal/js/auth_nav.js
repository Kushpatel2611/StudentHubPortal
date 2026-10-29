/*
 * Keeps the menu of the static .html pages in step with the PHP session:
 *   logged out -> Register, Login
 *   student    -> Dashboard, Logout
 *   admin      -> Admin Panel, Logout
 * Without PHP (or on error) the default Register/Login links simply stay.
 */
(function () {
    'use strict';

    function link(href, text) {
        var li = document.createElement('li');
        li.setAttribute('data-auth-item', '');
        var a = document.createElement('a');
        a.href = href;
        a.textContent = text;
        li.appendChild(a);
        return li;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var list = document.querySelector('#main-nav ul');
        if (!list || location.protocol === 'file:') { return; }

        fetch('auth_status.php', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (state) {
                if (!state || typeof state.loggedIn !== 'boolean') { return; }
                list.querySelectorAll('[data-auth-item]').forEach(function (item) { item.remove(); });
                if (!state.loggedIn) { return; }   // default markup already shows Register/Login
                if (state.role === 'admin') {
                    list.appendChild(link('admin_dashboard.php', 'Admin Panel'));
                } else {
                    list.appendChild(link('dashboard.php', 'Dashboard'));
                }
                list.appendChild(link('logout.php', 'Logout'));
            })
            .catch(function () { /* keep the default menu */ });
    });
}());
