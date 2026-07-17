import QRCode from 'qrcode';

const renderQrCodes = async () => {
    const containers = document.querySelectorAll('[data-qr-payload]');

    await Promise.all(
        Array.from(containers).map(async (container) => {
            const payload = container.dataset.qrPayload;
            const size = Number(container.dataset.qrSize || 220);

            if (!payload) {
                container.textContent = 'QR no disponible.';
                return;
            }

            try {
                const canvas = document.createElement('canvas');

                await QRCode.toCanvas(canvas, payload, {
                    width: size,
                    margin: 1,
                    errorCorrectionLevel: 'M',
                });

                container.replaceChildren(canvas);
            } catch (error) {
                console.error('No se pudo generar el QR.', error);
                container.textContent = 'No se pudo generar el QR.';
            }
        }),
    );
};

const bindCopyButtons = () => {
    document.querySelectorAll('[data-copy-target]').forEach((button) => {
        button.addEventListener('click', async () => {
            const target = document.getElementById(button.dataset.copyTarget);

            if (!target) {
                return;
            }

            await navigator.clipboard.writeText(target.value ?? target.textContent ?? '');
        });
    });

    document.querySelectorAll('[data-copy-text]').forEach((button) => {
        button.addEventListener('click', async () => {
            await navigator.clipboard.writeText(button.dataset.copyText ?? '');
        });
    });
};

document.addEventListener('DOMContentLoaded', async () => {
    bindCopyButtons();
    await renderQrCodes();
});
