/* Extra client-side checks for the register page (the server validates again). */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    var password = document.getElementById('password');
    var confirm = document.getElementById('confirm_password');
    if (!password || !confirm) { return; }

    function check() {
        confirm.setCustomValidity(confirm.value !== '' && confirm.value !== password.value ? 'Passwords do not match.' : '');
    }
    password.addEventListener('input', check);
    confirm.addEventListener('input', check);
});
