export function searchItems(items, searchText, fieldNames) {
    const value = searchText.trim().toLowerCase();

    if (!value) {
        return items;
    }

    return items.filter((item) => {
        return fieldNames.some((field) => {
            const fieldValue = item[field];
            return String(fieldValue).toLowerCase().includes(value);
        });
    });
}

export function filterEvents(items, category, status) {
    return items.filter((item) => {
        const matchesCategory = !category || item.category === category;
        const matchesStatus = !status || item.status === status;
        return matchesCategory && matchesStatus;
    });
}

export function filterStudents(items, course, year) {
    return items.filter((item) => {
        const matchesCourse = !course || item.course === course;
        const matchesYear = !year || String(item.year) === String(year);
        return matchesCourse && matchesYear;
    });
}

export function sortEvents(items, sortKey) {
    const sorted = [...items];

    if (sortKey === 'date') {
        return sorted.sort((a, b) => new Date(a.date) - new Date(b.date));
    }

    return sorted.sort((a, b) => a.title.localeCompare(b.title));
}

export function sortStudents(items, sortKey) {
    const sorted = [...items];

    if (sortKey === 'name') {
        return sorted.sort((a, b) => a.name.localeCompare(b.name));
    }

    return sorted;
}
