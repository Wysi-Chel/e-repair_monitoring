document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');

    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            const open = sidebar.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', String(open));
        });
    }

    const equipmentSelect = document.querySelector('[data-equipment-select]');
    const equipmentDescription = document.querySelector('#equipment_description');
    if (equipmentSelect && equipmentDescription) {
        const syncDescription = () => {
            const option = equipmentSelect.options[equipmentSelect.selectedIndex];
            if (option?.value && (!equipmentDescription.value.trim() || equipmentDescription.dataset.autofilled === 'true')) {
                equipmentDescription.value = option.dataset.description || option.textContent.trim();
                equipmentDescription.dataset.autofilled = 'true';
            }
        };
        equipmentSelect.addEventListener('change', syncDescription);
        equipmentDescription.addEventListener('input', () => {
            equipmentDescription.dataset.autofilled = 'false';
        });
        syncDescription();
    }
});
