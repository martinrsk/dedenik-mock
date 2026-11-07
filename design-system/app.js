/* Progressive enhancement: all documentation is readable without JavaScript. */
(() => {
  'use strict';
  const toast = document.querySelector('.toast');
  let toastTimer;
  function announce(message) {
    if (!toast) return;
    clearTimeout(toastTimer);
    toast.textContent = message;
    toast.hidden = false;
    toastTimer = setTimeout(() => { toast.hidden = true; }, 4000);
  }

  document.querySelectorAll('[data-copy]').forEach(button => {
    button.addEventListener('click', async () => {
      const text = button.dataset.copy;
      if (!text) return;
      try {
        if (!navigator.clipboard || !window.isSecureContext) throw new Error('clipboard-unavailable');
        await navigator.clipboard.writeText(text);
        announce(`Zkopírováno: ${text}`);
      } catch {
        announce(`Ke zkopírování: ${text}`);
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(button);
        if (selection) { selection.removeAllRanges(); selection.addRange(range); }
      }
    });
  });

  document.querySelectorAll('[data-demo-action]').forEach(button => {
    button.addEventListener('click', () => announce('Ukázka tlačítka. Žádný obsah webu se nemění.'));
  });
  document.querySelectorAll('[data-demo-filter]').forEach(button => {
    button.addEventListener('click', () => {
      const group = button.closest('.pill-group');
      if (!group) return;
      group.querySelectorAll('[data-demo-filter]').forEach(item => {
        const selected = item === button;
        item.classList.toggle('is-selected', selected);
        item.setAttribute('aria-pressed', String(selected));
      });
      announce(`Ukázka výběru: ${button.textContent.trim()}`);
    });
  });

  const sections = [...document.querySelectorAll('[data-section]')];
  const navLinks = [...document.querySelectorAll('.section-nav a')];
  const normalize = text => text.toLocaleLowerCase('cs').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  const searchable = sections.map(section => ({ section, text:normalize(section.textContent) }));
  const search = document.querySelector('#section-search');
  const searchWrapper = document.querySelector('[data-enhanced-search]');
  const empty = document.querySelector('[data-empty-results]');
  const searchStatus = document.querySelector('#search-status');
  if (search && searchWrapper) {
    searchWrapper.hidden = false;
    search.addEventListener('input', () => {
      const terms = normalize(search.value.trim()).split(/\s+/).filter(Boolean);
      let visible = 0;
      searchable.forEach(({ section, text }) => {
        const matches = terms.every(term => text.includes(term));
        section.hidden = !matches;
        if (matches) visible++;
        const link = navLinks.find(item => item.hash === `#${section.id}`);
        if (link) link.hidden = !matches;
      });
      if (empty) empty.hidden = visible > 0;
      if (searchStatus) searchStatus.textContent = terms.length ? `Nalezené sekce: ${visible} z ${sections.length}.` : '';
    });
    document.addEventListener('keydown', event => {
      const target = event.target;
      const editing = target instanceof HTMLElement && (target.matches('input,textarea,select') || target.isContentEditable);
      if (event.key === '/' && !editing && !event.ctrlKey && !event.metaKey && !event.altKey && searchWrapper.getClientRects().length) {
        event.preventDefault(); search.focus();
      }
      if (event.key === 'Escape' && target === search) {
        search.value = ''; search.dispatchEvent(new Event('input')); search.blur();
      }
    });
  }
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
      const entering = entries.filter(entry => entry.isIntersecting && !entry.target.hidden).sort((a,b) => a.boundingClientRect.top - b.boundingClientRect.top);
      if (!entering.length) return;
      const id = entering[0].target.id;
      navLinks.forEach(link => {
        const active = link.hash === `#${id}`;
        link.classList.toggle('is-active', active);
        if (active) link.setAttribute('aria-current', 'location'); else link.removeAttribute('aria-current');
      });
    }, { rootMargin:'-10% 0px -65% 0px', threshold:0 });
    sections.forEach(section => observer.observe(section));
  }
})();
