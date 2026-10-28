// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Photos are made into a JPEG before they are sent: an iCare meeting photo the best JPEG under
// 3 MB at most 4096 px (PRD FR-9), a member's own photo under 1 MB at most 1600 px. The input
// says which with data-max-bytes and data-max-side. The server does the same calculation again
// (AttendancePhotoStore), so this only saves the leader's mobile data and time.

const QUALITY_HIGH = 0.9;
const QUALITY_LOW = 0.8;
const SAFETY = 0.97;
const CLOSE = 0.9;

// Wires the [data-photo] input of a form: shrink on change, show the result, and hold the
// submit until the shrinking is done.
export function wirePhoto(form) {
    const input = form.querySelector('[data-photo]');
    const label = form.querySelector('[data-photo-label]');
    const note = form.querySelector('[data-photo-note]');
    const noPhoto = form.querySelector('[data-no-photo]');

    if (!input || !window.DataTransfer || !window.createImageBitmap) return;

    const maxBytes = Number(input.dataset.maxBytes ?? 3_000_000);
    const maxSide = Number(input.dataset.maxSide ?? 4096);
    let working = null;

    input.addEventListener('change', () => {
        const file = input.files?.[0];

        if (!file) return;

        if (noPhoto) noPhoto.checked = false;
        label.textContent = file.name;
        note.textContent = '…';

        working = shrink(file, maxBytes, maxSide)
            .then((jpeg) => {
                const transfer = new DataTransfer();
                transfer.items.add(jpeg);
                input.files = transfer.files;
                note.textContent = `${Math.round(jpeg.size / 1024)} KB · JPG`;
            })
            .catch(() => {
                // The browser cannot read this kind of image (HEIC on most browsers): send it as
                // it is and let the server answer.
                note.textContent = `${Math.round(file.size / 1024)} KB`;
            })
            .finally(() => (working = null));
    });

    form.addEventListener('submit', (event) => {
        if (!working) return;

        // Wait for the photo; keep app.js from marking the form as already sent.
        event.preventDefault();
        event.stopPropagation();
        working.then(() => form.requestSubmit(event.submitter ?? undefined));
    });
}

// The same calculation as AttendancePhotoStore::encode(): measure at quality 0.9 and 0.8, then
// work out the quality that lands on the cap (size falls about exponentially with quality); if
// even 0.8 is too big, shrink both sides by √(cap · 0.97 / size), since size follows the pixel
// count, and correct the scale after measuring again.
export async function shrink(file, maxBytes, maxSide) {
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    const toFile = (blob) => new File([blob], 'photo.jpg', { type: 'image/jpeg' });
    const fitted = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
    let scale = fitted;
    const canvas = draw(bitmap, scale);

    const high = await jpeg(canvas, QUALITY_HIGH);
    if (high.size <= maxBytes) return toFile(high);

    const low = await jpeg(canvas, QUALITY_LOW);

    if (low.size <= maxBytes) {
        const steps = Math.floor((10 * Math.log(maxBytes / low.size)) / Math.log(high.size / low.size));

        for (let step = steps; step > 0; step--) {
            const blob = await jpeg(canvas, QUALITY_LOW + step / 100);
            if (blob.size <= maxBytes) return toFile(blob);
        }

        return toFile(low);
    }

    let blob = low;
    let best = null;

    for (let attempt = 0; attempt < 3; attempt++) {
        scale = Math.min(fitted, scale * Math.sqrt((maxBytes * SAFETY) / blob.size));
        blob = await jpeg(draw(bitmap, scale), QUALITY_LOW);

        if (blob.size <= maxBytes) {
            best = blob;
            if (blob.size >= CLOSE * maxBytes) break;
        }
    }

    for (let attempt = 0; attempt < 5 && !best; attempt++) {
        scale *= 0.8;
        blob = await jpeg(draw(bitmap, scale), QUALITY_LOW);
        if (blob.size <= maxBytes) best = blob;
    }

    if (!best) throw new Error('Could not make the photo small enough.');

    return toFile(best);
}

function draw(bitmap, scale) {
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

    return canvas;
}

function jpeg(canvas, quality) {
    return new Promise((resolve, reject) =>
        canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('No JPEG'))), 'image/jpeg', quality),
    );
}
