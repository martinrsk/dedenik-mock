(() => {
    'use strict';
    const dialog = document.getElementById('photo-viewer');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const preview = dialog.querySelector('img');
    const description = dialog.querySelector('#photo-viewer-description');
    const close = dialog.querySelector('.photo-viewer-close');
    const controls = dialog.querySelector('.photo-viewer-controls');
    let photos = [];
    let index = 0;
    let opener;

    function showPhoto(position) {
        index = (position + photos.length) % photos.length;
        const link = photos[index];
        const image = link.querySelector('img');
        const caption = link.closest('figure').querySelector('figcaption')?.textContent.trim();
        preview.alt = image.alt;
        preview.src = link.href;
        description.textContent = [`${index + 1} / ${photos.length}`, caption || image.alt].filter(Boolean).join(' · ');
    }

    document.querySelectorAll('.photo-gallery .wp-block-gallery').forEach(gallery => {
        const links = Array.from(gallery.querySelectorAll('figure.wp-block-image > a[href]'))
            .filter(link => link.querySelector('img') && /^https?:$/.test(link.protocol)
                && /\.(avif|gif|heic|jpe?g|png|webp)$/i.test(link.pathname));

        links.forEach((link, position) => {
            link.setAttribute('aria-haspopup', 'dialog');
            link.setAttribute('aria-controls', dialog.id);
            if (!link.querySelector('img').alt.trim()) {
                const caption = link.closest('figure').querySelector('figcaption')?.textContent.trim();
                link.setAttribute('aria-label', caption || String(position + 1) + ' / ' + links.length);
            }
            link.addEventListener('click', event => {
                if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || dialog.open) return;

                photos = links;
                opener = link;
                controls.hidden = photos.length < 2;
                dialog.showModal();
                event.preventDefault();
                showPhoto(position);
            });
        });
    });

    controls.querySelectorAll('[data-step]').forEach(button => {
        button.addEventListener('click', () => showPhoto(index + Number(button.dataset.step)));
    });
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('keydown', event => {
        if (photos.length < 2 || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
        const step = { ArrowLeft: -1, ArrowRight: 1 }[event.key];
        if (!step) return;
        event.preventDefault();
        showPhoto(index + step);
    });
    dialog.addEventListener('close', () => {
        preview.removeAttribute('src');
        opener?.focus({ preventScroll: true });
    });
})();
