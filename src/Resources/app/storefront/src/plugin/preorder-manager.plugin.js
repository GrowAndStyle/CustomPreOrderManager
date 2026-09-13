import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import HttpClient from 'src/service/http-client.service';

export default class PreOrderManagerPlugin extends Plugin {
    init() {
        this.client = new HttpClient();
        this._registerEvents();
    }

    _registerEvents() {
        if (this.el.tagName === 'FORM') {
            this.el.addEventListener('submit', this._onWaitlistSubmit.bind(this));
        } else {
            const form = DomAccess.find(this.el, '[data-preorder-waitlist-form]', false);
            if (form) {
                form.addEventListener('submit', this._onWaitlistSubmit.bind(this));
            }
        }
    }

    _onWaitlistSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const actionUrl = form.getAttribute('action');
        const submitBtn = form.querySelector('button[type="submit"]');

        if (submitBtn) {
            submitBtn.disabled = true;
        }

        this.client.post(actionUrl, formData, (response) => {
            try {
                const data = JSON.parse(response);
                if (data.success) {
                    form.innerHTML = `<div class="alert alert-success mt-3" role="alert">${data.message}</div>`;
                } else {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    this._showError(form, data.message || 'Ein Fehler ist aufgetreten.');
                }
            } catch {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                this._showError(form, 'Verbindung fehlgeschlagen. Bitte versuchen Sie es erneut.');
            }
        });
    }

    _showError(form, message) {
        let errorBox = form.querySelector('.preorder-waitlist-error');
        if (!errorBox) {
            errorBox = document.createElement('div');
            errorBox.className = 'alert alert-danger preorder-waitlist-error mt-3';
            form.appendChild(errorBox);
        }
        errorBox.textContent = message;
    }
}
