// SPDX-License-Identifier: AGPL-3.0-or-later
//
// The few behaviours the components of design/DESIGN.md §5 need. Every page works without this
// script: a form still submits, an error summary is still read, a code can still be selected.

// x-button — while its form is being sent the button shows its spinner and a second tap does
// nothing. The button is not disabled, so its own name and value still travel with the form.
document.addEventListener('submit', (event) => {
    const button = event.submitter;

    if (!button?.matches('[data-button]')) return;

    if (event.target.dataset.submitting) {
        event.preventDefault();

        return;
    }

    event.target.dataset.submitting = 'true';
    button.setAttribute('aria-busy', 'true');
});

// Coming back with the browser's Back button restores the page as it was left: clear the state.
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;

    document.querySelectorAll('form[data-submitting]').forEach((form) => delete form.dataset.submitting);
    document.querySelectorAll('[data-button][aria-busy]').forEach((button) => button.removeAttribute('aria-busy'));
});

// x-alert with `summary` — after a failed submit, focus goes to the list of what to fix.
document.querySelector('[data-error-summary]')?.focus();

// x-qr-card — "Copy the code" answers with "Copied" for two seconds.
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');

    if (!button || !navigator.clipboard) return;

    await navigator.clipboard.writeText(button.dataset.copy);

    const label = button.querySelector('span');
    const original = label.textContent;

    label.textContent = button.dataset.copied;
    setTimeout(() => (label.textContent = original), 2000);
});

// Registration — the example in the WhatsApp box follows the chosen country code.
document.querySelectorAll('[data-phone-country]').forEach((select) => {
    const input = select.closest('form')?.querySelector('[data-phone-number]');

    if (!input) return;

    select.addEventListener('change', () => {
        input.placeholder = select.selectedOptions[0]?.dataset.example ?? '';
    });
});

// A form with data-confirm asks first (deleting an iCare meeting photo).
document.addEventListener('submit', (event) => {
    const message = event.target.dataset?.confirm;

    if (message && !window.confirm(message)) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
}, true);

// iCare attendance (PRD FR-9) — loaded only on that page.
const icareAttendance = document.querySelector('[data-icare-attendance]');

if (icareAttendance) {
    import('./icare-attendance.js').then(({ init }) => init(icareAttendance));
}

// A member's own photo — shrunk in the browser before it is sent.
const selfPhoto = document.querySelector('[data-self-photo]');

if (selfPhoto) {
    import('./photo-shrink.js').then(({ wirePhoto }) => wirePhoto(selfPhoto));
}
