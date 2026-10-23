export function paginateItems(items, currentPage, pageSize) {
    const totalPages = Math.max(1, Math.ceil(items.length / pageSize));
    const safePage = Math.min(Math.max(currentPage, 1), totalPages);
    const start = (safePage - 1) * pageSize;
    const end = start + pageSize;

    return {
        items: items.slice(start, end),
        currentPage: safePage,
        totalPages,
        start,
        end
    };
}

export function updatePaginationButtons(pagination, pageInfo) {
    const prevButton = pagination.querySelector('[data-page="prev"]');
    const nextButton = pagination.querySelector('[data-page="next"]');
    const pageLabel = pagination.querySelector('[data-page="label"]');

    if (prevButton) {
        prevButton.disabled = pageInfo.currentPage === 1;
    }

    if (nextButton) {
        nextButton.disabled = pageInfo.currentPage >= pageInfo.totalPages;
    }

    if (pageLabel) {
        pageLabel.textContent = `Page ${pageInfo.currentPage} of ${pageInfo.totalPages}`;
    }
}
