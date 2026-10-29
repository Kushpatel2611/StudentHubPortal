/*
 * Sends the Contact form (contact.html) and the Feedback form (feedback.html)
 * to the PHP API so they are stored on the server and show up in the admin
 * Messages page. Browser validation runs first.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var form = document.querySelector('form[data-api]');
    if (!form) { return; }
    var status = form.querySelector('.form-status');
    var button = form.querySelector('[type="submit"]');

    function show(text, type) {
        if (!status) { return; }
        status.textContent = text;
        status.className = 'form-status ' + (type === 'ok' ? 'is-success' : 'is-error');
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!form.reportValidity()) { return; }

        if (button) { button.disabled = true; }
        show('Sending...', 'ok');

        fetch(form.getAttribute('data-api'), {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json().then(function (data) { return { ok: response.ok, data: data }; });
            })
            .then(function (result) {
                if (result.ok && result.data.success) {
                    show(result.data.message, 'ok');
                    form.reset();
                } else {
                    show(result.data.message || 'The form could not be sent.', 'error');
                }
            })
            .catch(function () {
                show('Could not reach the server. Start the site with PHP (START_SERVER.bat or XAMPP) and try again.', 'error');
            })
            .finally(function () {
                if (button) { button.disabled = false; }
            });
    });
});
