export function createListItem(text, className) {
    const item = document.createElement('li');
    item.className = className;
    item.textContent = text;
    return item;
}

export function renderEmptyState(container, message) {
    container.innerHTML = '';
    const notice = document.createElement('p');
    notice.className = 'empty-state';
    notice.textContent = message;
    container.appendChild(notice);
}

export function renderLoadingState(container, message) {
    container.innerHTML = '';
    const loading = document.createElement('p');
    loading.className = 'loading-state';
    loading.textContent = message;
    container.appendChild(loading);
}

export function renderErrorState(container, message, onRetry) {
    container.innerHTML = '';
    const box = document.createElement('div');
    box.className = 'error-state';

    const text = document.createElement('p');
    text.textContent = message;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn';
    button.textContent = 'Retry';
    button.addEventListener('click', onRetry);

    box.appendChild(text);
    box.appendChild(button);
    container.appendChild(box);
}

export function renderTable(container, rows, columns) {
    if (!rows.length) {
        renderEmptyState(container, 'No results found.');
        return;
    }

    container.innerHTML = '';

    const table = document.createElement('table');
    table.className = 'data-table';

    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');

    columns.forEach((column) => {
        const th = document.createElement('th');
        th.textContent = column;
        headRow.appendChild(th);
    });

    thead.appendChild(headRow);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    rows.forEach((row) => {
        const tr = document.createElement('tr');
        columns.forEach((column) => {
            const td = document.createElement('td');
            td.textContent = row[column.toLowerCase()] ?? 'N/A';
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    container.appendChild(table);
}
