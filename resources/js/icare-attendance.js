// SPDX-License-Identifier: AGPL-3.0-or-later
//
// iCare attendance (PRD FR-9). The form works without this script; this adds:
// - the photo made into the best JPEG under 3 MB, at most 4096 px, before sending (photo-shrink.js)
// - the reason box only under online and absent rows, and the "9 of 14 came" count
// - more guest rows, and opening another date from the date picker

import { wirePhoto } from './photo-shrink.js';

export function init(form) {
    initMembers(form);
    initGuests(form);
    initDate(form);
    wirePhoto(form);
}

function initMembers(form) {
    const rows = [...form.querySelectorAll('[data-member]')];

    const paint = (row) => {
        const came = row.querySelector('[data-came]').checked;
        const online = row.querySelector('[data-online]');

        if (!came) online.checked = false;

        const status = !came ? 'absent' : online.checked ? 'online' : 'onsite';
        row.dataset.status = status;

        row.querySelector('[data-online-wrap]').classList.toggle('hidden', !came);
        row.querySelector('[data-absent-label]').classList.toggle('hidden', came);

        const reasonWrap = row.querySelector('[data-reason-wrap]');
        const reason = reasonWrap.querySelector('input');
        reasonWrap.classList.toggle('hidden', status === 'onsite');
        reason.placeholder = status === 'online' ? reason.dataset.placeholderOnline : reason.dataset.placeholderAbsent;
        reason.required = status !== 'onsite';
    };

    const count = () => {
        const box = form.querySelector('[data-count]');

        if (!box) return;

        const came = rows.filter((row) => row.dataset.status !== 'absent').length;
        const online = rows.filter((row) => row.dataset.status === 'online').length;

        box.querySelector('[data-count-came]').textContent = box.dataset.templateCame
            .replace('__CAME__', came)
            .replace('__TOTAL__', rows.length);
        box.querySelector('[data-count-split]').textContent = box.dataset.templateSplit
            .replace('__ONSITE__', came - online)
            .replace('__ONLINE__', online);
    };

    rows.forEach((row) => {
        paint(row);
        row.addEventListener('change', () => {
            paint(row);
            count();
        });
    });

    count();
}

function initGuests(form) {
    const list = form.querySelector('[data-guests]');
    const add = form.querySelector('[data-guest-add]');

    if (!list || !add) return;

    add.classList.remove('hidden');
    add.classList.add('inline-flex');

    const showRemove = () => {
        list.querySelectorAll('[data-guest-remove]').forEach((button) => {
            button.classList.remove('hidden');
            button.classList.add('inline-flex');
        });
    };

    add.addEventListener('click', () => {
        const rows = list.querySelectorAll('[data-guest]');
        const last = rows[rows.length - 1];
        const index = Date.now();
        const row = last.cloneNode(true);

        row.querySelectorAll('input, label').forEach((element) => {
            for (const attribute of ['name', 'id', 'for']) {
                const value = element.getAttribute(attribute);

                if (value) element.setAttribute(attribute, value.replace(/guests([[.])\d+/, `guests$1${index}`));
            }

            if (element.matches('input[type=text]')) element.value = '';
            if (element.matches('input[type=checkbox]')) element.checked = false;
        });

        last.after(row);
        showRemove();
        row.querySelector('[data-guest-name]').focus();
    });

    list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-guest-remove]');

        if (!button) return;

        const row = button.closest('[data-guest]');

        if (list.querySelectorAll('[data-guest]').length > 1) {
            row.remove();
        } else {
            row.querySelectorAll('input').forEach((input) => (input.type === 'checkbox' ? (input.checked = false) : (input.value = '')));
        }
    });

    showRemove();
}

function initDate(form) {
    const input = form.querySelector('[data-date]');

    input?.addEventListener('change', () => {
        if (!input.value || (input.max && input.value > input.max)) return;

        const url = new URL(form.dataset.openUrl, window.location.origin);
        url.searchParams.set('date', input.value);
        window.location.assign(url);
    });
}
