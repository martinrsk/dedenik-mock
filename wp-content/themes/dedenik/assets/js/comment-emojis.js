document.querySelectorAll('.comment-emojis').forEach(palette => {
    const comment = palette.closest('form')?.querySelector('textarea[name="comment"]');
    if (!comment) return;
    palette.hidden = false;
    palette.addEventListener('click', event => {
        const button = event.target.closest('[data-comment-emoji]');
        if (!button) return;
        comment.setRangeText(button.dataset.commentEmoji, comment.selectionStart, comment.selectionEnd, 'end');
        comment.focus({ preventScroll: true });
        comment.dispatchEvent(new Event('input', { bubbles: true }));
    });
});
