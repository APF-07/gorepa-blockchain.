(() => {
    const button = document.querySelector('.menu-toggle');
    const sidebar = document.getElementById('menu-lateral');
    if (!button || !sidebar) return;
    const close = () => { sidebar.classList.remove('is-open'); button.setAttribute('aria-expanded', 'false'); };
    button.addEventListener('click', () => {
        const open = sidebar.classList.toggle('is-open');
        button.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') { close(); button.focus(); } });
    document.addEventListener('click', event => { if (!sidebar.contains(event.target) && !button.contains(event.target)) close(); });
    window.matchMedia('(min-width: 901px)').addEventListener('change', close);
})();
