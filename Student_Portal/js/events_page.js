import { fetchJson } from './modules/api.js';
import { renderLoadingState, renderEmptyState, renderErrorState } from './modules/render.js';
import { filterEvents, searchItems, sortEvents } from './modules/filters.js';
import { paginateItems, updatePaginationButtons } from './modules/pagination.js';

const listContainer = document.getElementById('eventList');
const searchInput = document.getElementById('eventSearch');
const categorySelect = document.getElementById('eventCategory');
const statusSelect = document.getElementById('eventStatus');
const sortSelect = document.getElementById('eventSort');
const pagination = document.querySelector('.pagination');

const state = {
    allEvents: [],
    category: '',
    status: '',
    search: '',
    sort: 'date',
    page: 1
};

function addLabelValue(parent, label, value) {
    const line = document.createElement('p');
    const strong = document.createElement('strong');
    strong.textContent = `${label}: `;
    line.appendChild(strong);
    line.appendChild(document.createTextNode(value));
    parent.appendChild(line);
}

function createEventCard(event) {
    const card = document.createElement('article');
    card.className = 'event-card';

    if (event.poster) {
        const poster = document.createElement('img');
        poster.className = 'event-poster';
        poster.src = event.poster;
        poster.alt = `Poster for ${event.title}`;
        poster.loading = 'lazy';
        card.appendChild(poster);
    }

    const title = document.createElement('h3');
    title.textContent = event.title;

    const meta = document.createElement('div');
    meta.className = 'meta';

    const categoryBadge = document.createElement('span');
    categoryBadge.textContent = event.category;
    meta.appendChild(categoryBadge);

    const statusBadge = document.createElement('span');
    statusBadge.textContent = event.status;
    meta.appendChild(statusBadge);

    const description = document.createElement('p');
    description.textContent = event.description;

    card.appendChild(title);
    card.appendChild(meta);
    addLabelValue(card, 'Date', event.date);
    addLabelValue(card, 'Venue', event.venue);
    card.appendChild(description);
    return card;
}

function populateCategoryOptions() {
    const categories = [...new Set(state.allEvents.map((event) => event.category))];
    categorySelect.innerHTML = '';

    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = 'All categories';
    categorySelect.appendChild(defaultOption);

    categories.forEach((category) => {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = category;
        categorySelect.appendChild(option);
    });
}

function renderEvents() {
    const searched = searchItems(filterEvents(state.allEvents, state.category, state.status), state.search, ['title', 'category', 'venue', 'description']);
    const sorted = sortEvents(searched, state.sort);
    const pageInfo = paginateItems(sorted, state.page, 6);

    listContainer.innerHTML = '';

    if (!pageInfo.items.length) {
        renderEmptyState(listContainer, 'No events found.');
        updatePaginationButtons(pagination, { currentPage: 1, totalPages: 1 });
        return;
    }

    // Use map() to build a card for each event shown on the page.
    const cards = pageInfo.items.map((event) => createEventCard(event));
    cards.forEach((card) => listContainer.appendChild(card));
    updatePaginationButtons(pagination, pageInfo);
}

async function loadEvents() {
    renderLoadingState(listContainer, 'Loading events...');

    try {
        let events = [];
        try {
            // Events created in the admin panel (database) come first...
            events = await fetchJson('api/events.php');
        } catch (apiError) {
            events = [];
        }
        // ...and the original static JSON file is used when the database has none
        // or PHP/MySQL is not running.
        if (!Array.isArray(events) || events.length === 0) {
            events = await fetchJson('data/events.json');
        }
        state.allEvents = events;
        populateCategoryOptions();
        renderEvents();
    } catch (error) {
        renderErrorState(listContainer, 'Could not load the events. Please try again.', loadEvents);
    }
}

searchInput.addEventListener('input', (event) => {
    state.search = event.target.value;
    state.page = 1;
    renderEvents();
});

categorySelect.addEventListener('change', (event) => {
    state.category = event.target.value;
    state.page = 1;
    renderEvents();
});

statusSelect.addEventListener('change', (event) => {
    state.status = event.target.value;
    state.page = 1;
    renderEvents();
});

sortSelect.addEventListener('change', (event) => {
    state.sort = event.target.value;
    state.page = 1;
    renderEvents();
});

pagination.querySelector('[data-page="prev"]').addEventListener('click', () => {
    if (state.page > 1) {
        state.page -= 1;
        renderEvents();
    }
});

pagination.querySelector('[data-page="next"]').addEventListener('click', () => {
    const totalPages = Math.max(1, Math.ceil(searchItems(filterEvents(state.allEvents, state.category, state.status), state.search, ['title', 'category', 'venue', 'description']).length / 6));
    if (state.page < totalPages) {
        state.page += 1;
        renderEvents();
    }
});

loadEvents();
