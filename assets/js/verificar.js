(() => {
    const select = document.querySelector('[data-document-picker]');
    const search = document.getElementById('document-search');
    const container = document.getElementById('document-search-container');
    const count = document.getElementById('document-search-count');
    if (!select || !search || !container || !count) return;
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
    const automatic = select.options[0].cloneNode(true);
    const options = Array.from(select.options).slice(1).map(option => ({
        node: option.cloneNode(true),
        searchable: normalize(option.dataset.search || option.textContent)
    }));
    container.hidden = false;
    search.addEventListener('input', () => {
        const query = normalize(search.value.trim());
        const selected = select.value;
        const fragment = document.createDocumentFragment();
        fragment.append(automatic.cloneNode(true));
        let matches = 0;
        let preserved = false;
        for (const option of options) {
            const match = option.searchable.includes(query);
            if (match) matches++;
            if (match || option.node.value === selected) {
                const node = option.node.cloneNode(true);
                node.selected = node.value === selected;
                fragment.append(node);
                if (!match) preserved = true;
            }
        }
        select.replaceChildren(fragment);
        select.value = selected;
        count.textContent = `${matches} documento(s) encontrado(s).${preserved ? ' Se conserva el documento seleccionado.' : ''}`;
    });
})();
