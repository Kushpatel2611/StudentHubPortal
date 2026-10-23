import { fetchJson } from './modules/api.js';
import { renderLoadingState, renderEmptyState, renderErrorState } from './modules/render.js';
import { searchItems } from './modules/filters.js';

const listContainer = document.getElementById('faqList');
const searchInput = document.getElementById('faqSearch');
const categorySelect = document.getElementById('faqCategory');

const state = {
    allFaqs: [],
    category: '',
    search: ''
};

function createFaqCard(item) {
    const card = document.createElement('article');
    card.className = 'faq-card';

    const category = document.createElement('span');
    category.className = 'badge';
    category.textContent = item.category;

    const question = document.createElement('button');
    question.type = 'button';
    question.className = 'faq-question';
    question.textContent = item.question;
    question.setAttribute('aria-expanded', 'false');

    const answer = document.createElement('div');
    answer.className = 'faq-answer';
    answer.hidden = true;
    const answerText = document.createElement('p');
    answerText.textContent = item.answer;
    answer.appendChild(answerText);

    question.addEventListener('click', () => {
        const isOpen = question.getAttribute('aria-expanded') === 'true';
        question.setAttribute('aria-expanded', String(!isOpen));
        question.classList.toggle('is-open', !isOpen);
        answer.hidden = isOpen;
    });

    card.appendChild(category);
    card.appendChild(question);
    card.appendChild(answer);
    return card;
}

function populateCategoryOptions() {
    const categories = [...new Set(state.allFaqs.map((item) => item.category))];
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

function renderFaqs() {
    const filtered = state.allFaqs.filter((item) => {
        const matchesCategory = !state.category || item.category === state.category;
        const matchesSearch = !state.search || searchItems([item], state.search, ['category', 'question', 'answer']).length > 0;
        return matchesCategory && matchesSearch;
    });

    listContainer.innerHTML = '';

    if (!filtered.length) {
        renderEmptyState(listContainer, 'No FAQs found.');
        return;
    }

    // Use filter() to keep only matching FAQ items and map() to create the visible cards.
    const cards = filtered.map((item) => createFaqCard(item));
    cards.forEach((card) => listContainer.appendChild(card));
}

async function loadFaqs() {
    renderLoadingState(listContainer, 'Loading FAQs...');

    try {
        const faqs = await fetchJson('data/faqs.json');
        state.allFaqs = faqs;
        populateCategoryOptions();
        renderFaqs();
    } catch (error) {
        renderErrorState(listContainer, 'Could not load the FAQs. Please try again.', loadFaqs);
    }
}

searchInput.addEventListener('input', (event) => {
    state.search = event.target.value;
    renderFaqs();
});

categorySelect.addEventListener('change', (event) => {
    state.category = event.target.value;
    renderFaqs();
});

loadFaqs();
