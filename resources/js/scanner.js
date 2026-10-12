// SPDX-License-Identifier: AGPL-3.0-or-later
//
// The usher's camera scanner (PRD FR-7). Reads a QR from the camera with jsQR and posts the
// token to the server, which answers recorded / already / not_found. The page works without
// this script too: the typed backup code and "Find by name" need no JavaScript.

import jsQR from 'jsqr';

const root = document.querySelector('[data-scanner]');

if (root) {
    const video = root.querySelector('[data-scanner-video]');
    const message = root.querySelector('[data-scanner-message]');
    const result = document.querySelector('[data-scan-result]');
    const title = document.querySelector('[data-scan-title]');
    const detail = document.querySelector('[data-scan-detail]');
    const count = document.querySelector('[data-scan-count]');
    const labels = JSON.parse(root.dataset.labels);
    const styles = JSON.parse(root.dataset.styles);
    const csrf = document.querySelector('input[name="_token"]').value;
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });

    // The same QR held in front of the camera must not be posted again and again.
    const REPEAT_AFTER_MS = 4000;
    let lastToken = null;
    let lastAt = 0;
    let busy = false;

    const vibration = { recorded: [80], already: [60, 60, 60], not_found: [300], no_branch: [300] };

    const show = (data) => {
        const allStyles = Object.values(styles).join(' ').split(' ');
        result.classList.remove(...allStyles, 'border-stone-200', 'bg-white', 'text-stone-600');
        result.classList.add(...styles[data.status].split(' '));
        title.textContent = labels[data.status];

        const parts = [];
        if (data.name) parts.push(data.name);
        if (data.icare) parts.push(`iCare ${data.icare}`);
        if (data.status === 'already' && data.time) parts.push(data.time);
        detail.textContent = parts.join(' · ');

        if (typeof data.count === 'number') count.textContent = data.count;
        if (navigator.vibrate) navigator.vibrate(vibration[data.status] ?? []);
    };

    const send = async (token) => {
        busy = true;

        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ token }),
            });

            if (response.status === 429) {
                title.textContent = 'Too fast';
                detail.textContent = 'Wait a moment, then scan again.';
            } else if (response.status === 419 || response.status === 401) {
                window.location.reload();
            } else if (response.ok) {
                show(await response.json());
            } else {
                show({ status: 'not_found' });
            }
        } catch {
            title.textContent = 'No connection';
            detail.textContent = 'The scan was not sent. Check the network and scan again.';
        } finally {
            busy = false;
        }
    };

    const tick = () => {
        if (video.readyState === video.HAVE_ENOUGH_DATA && !busy) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            const image = context.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });

            if (code && code.data) {
                const now = Date.now();

                if (code.data !== lastToken || now - lastAt > REPEAT_AFTER_MS) {
                    lastToken = code.data;
                    lastAt = now;
                    send(code.data);
                }
            }
        }

        requestAnimationFrame(tick);
    };

    const start = async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            message.textContent = 'This browser cannot open the camera. Type the backup code instead.';

            return;
        }

        try {
            video.srcObject = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            });
            await video.play();
            message.remove();
            requestAnimationFrame(tick);
        } catch {
            message.textContent = 'The camera is not available. Allow camera access, or type the backup code instead.';
        }
    };

    start();
}
