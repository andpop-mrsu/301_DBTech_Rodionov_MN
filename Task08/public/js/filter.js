window.allBoxOptions = null;
window.allServiceOptions = null;

function initializeFiltering(categoryBoxMapping, services) {
    window.categoryBoxMapping = categoryBoxMapping;
    window.servicesData = services;
}

function filterBoxesAndServices() {
    const categorySelect = document.getElementById('car_category_id');
    const boxSelect = document.getElementById('box_id');
    const serviceSelect = document.getElementById('service_id');

    if (!categorySelect || !boxSelect || !serviceSelect) {
        console.error('Элементы формы не найдены!');
        return;
    }

    const selectedCategoryId = categorySelect.value;

    if (!selectedCategoryId) {
        boxSelect.disabled = true;
        serviceSelect.disabled = true;

        while (boxSelect.options.length > 1) {
            boxSelect.remove(1);
        }
        boxSelect.options[0].textContent = 'Сначала выберите тип ТС';

        while (serviceSelect.options.length > 1) {
            serviceSelect.remove(1);
        }
        serviceSelect.options[0].textContent = 'Сначала выберите тип ТС';

        boxSelect.value = '';
        serviceSelect.value = '';
        return;
    }

    boxSelect.disabled = false;
    serviceSelect.disabled = false;

    const catIdNum = parseInt(selectedCategoryId);
    const catIdStr = String(selectedCategoryId);

    let compatibleBoxIds = [];
    if (window.categoryBoxMapping) {
        if (window.categoryBoxMapping[catIdNum] !== undefined) {
            compatibleBoxIds = window.categoryBoxMapping[catIdNum];
        } else if (window.categoryBoxMapping[catIdStr] !== undefined) {
            compatibleBoxIds = window.categoryBoxMapping[catIdStr];
        }
    }

    compatibleBoxIds = compatibleBoxIds.map(id => parseInt(id)).filter(id => !isNaN(id));

    if (!window.allBoxOptions) {
        window.allBoxOptions = [];
        Array.from(boxSelect.querySelectorAll('.box-option')).forEach(opt => {
            window.allBoxOptions.push({
                value: opt.value,
                text: opt.textContent,
                description: opt.getAttribute('data-description')
            });
        });
    }

    const currentBoxValue = boxSelect.value;

    while (boxSelect.options.length > 1) {
        boxSelect.remove(1);
    }
    boxSelect.options[0].textContent = 'Выберите бокс';

    window.allBoxOptions.forEach(boxOpt => {
        const boxId = parseInt(boxOpt.value);
        if (compatibleBoxIds.includes(boxId)) {
            const opt = new Option(boxOpt.text, boxOpt.value);
            boxSelect.add(opt);
        }
    });

    if (currentBoxValue && Array.from(boxSelect.options).some(opt => opt.value == currentBoxValue)) {
        boxSelect.value = currentBoxValue;
    }

    if (!window.allServiceOptions) {
        window.allServiceOptions = [];
        Array.from(serviceSelect.querySelectorAll('.service-option')).forEach(opt => {
            window.allServiceOptions.push({
                value: opt.value,
                text: opt.textContent,
                categoryId: parseInt(opt.getAttribute('data-category-id')),
                price: opt.getAttribute('data-price')
            });
        });
    }

    const currentServiceValue = serviceSelect.value;

    while (serviceSelect.options.length > 1) {
        serviceSelect.remove(1);
    }
    serviceSelect.options[0].textContent = 'Выберите услугу';

    window.allServiceOptions.forEach(serviceOpt => {
        if (serviceOpt.categoryId === catIdNum) {
            const opt = new Option(serviceOpt.text, serviceOpt.value);
            opt.setAttribute('data-price', serviceOpt.price);
            serviceSelect.add(opt);
        }
    });

    if (currentServiceValue && Array.from(serviceSelect.options).some(opt => opt.value == currentServiceValue)) {
        serviceSelect.value = currentServiceValue;
    }
}

function updatePrice() {
    const serviceSelect = document.getElementById('service_id');
    const priceField = document.getElementById('actual_price');

    if (serviceSelect && serviceSelect.value && window.servicesData && window.servicesData[serviceSelect.value]) {
        priceField.value = window.servicesData[serviceSelect.value];
    }
}

function initializeFilterOnLoad() {
    const boxSelect = document.getElementById('box_id');
    if (boxSelect && !window.allBoxOptions) {
        window.allBoxOptions = [];
        Array.from(boxSelect.querySelectorAll('.box-option')).forEach(opt => {
            window.allBoxOptions.push({
                value: opt.value,
                text: opt.textContent,
                description: opt.getAttribute('data-description')
            });
        });
    }

    const serviceSelect = document.getElementById('service_id');
    if (serviceSelect && !window.allServiceOptions) {
        window.allServiceOptions = [];
        Array.from(serviceSelect.querySelectorAll('.service-option')).forEach(opt => {
            window.allServiceOptions.push({
                value: opt.value,
                text: opt.textContent,
                categoryId: parseInt(opt.getAttribute('data-category-id')),
                price: opt.getAttribute('data-price')
            });
        });
    }

    filterBoxesAndServices();

    if (serviceSelect) {
        serviceSelect.addEventListener('change', updatePrice);
    }
}
