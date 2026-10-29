import { fetchJson } from './modules/api.js';
import { renderLoadingState, renderEmptyState, renderErrorState } from './modules/render.js';
import { filterStudents, searchItems, sortStudents } from './modules/filters.js';
import { paginateItems, updatePaginationButtons } from './modules/pagination.js';

const listContainer = document.getElementById('studentList');
const searchInput = document.getElementById('studentSearch');
const courseSelect = document.getElementById('studentCourse');
const yearSelect = document.getElementById('studentYear');
const sortSelect = document.getElementById('studentSort');
const pagination = document.querySelector('.pagination');

const state = {
    allStudents: [],
    course: '',
    year: '',
    search: '',
    sort: 'name',
    page: 1
};

function createStudentCard(student) {
    const card = document.createElement('article');
    card.className = 'student-card';

    const title = document.createElement('h3');
    title.textContent = student.name;

    const meta = document.createElement('div');
    meta.className = 'meta';

    const courseBadge = document.createElement('span');
    courseBadge.textContent = student.course;
    meta.appendChild(courseBadge);

    const yearBadge = document.createElement('span');
    yearBadge.textContent = `Year ${student.year}`;
    meta.appendChild(yearBadge);

    const email = document.createElement('p');
    const emailLabel = document.createElement('strong');
    emailLabel.textContent = 'Email: ';
    email.appendChild(emailLabel);
    email.appendChild(document.createTextNode(student.email));

    card.appendChild(title);
    card.appendChild(meta);
    card.appendChild(email);
    return card;
}

function populateSelectOptions() {
    const courses = [...new Set(state.allStudents.map((student) => student.course))];
    courseSelect.innerHTML = '';

    const courseDefault = document.createElement('option');
    courseDefault.value = '';
    courseDefault.textContent = 'All courses';
    courseSelect.appendChild(courseDefault);

    courses.forEach((course) => {
        const option = document.createElement('option');
        option.value = course;
        option.textContent = course;
        courseSelect.appendChild(option);
    });

    const years = [...new Set(state.allStudents.map((student) => student.year))].sort();
    yearSelect.innerHTML = '';

    const yearDefault = document.createElement('option');
    yearDefault.value = '';
    yearDefault.textContent = 'All years';
    yearSelect.appendChild(yearDefault);

    years.forEach((year) => {
        const option = document.createElement('option');
        option.value = String(year);
        option.textContent = String(year);
        yearSelect.appendChild(option);
    });
}

function renderStudents() {
    const searched = searchItems(filterStudents(state.allStudents, state.course, state.year), state.search, ['name', 'course', 'email']);
    const sorted = sortStudents(searched, state.sort);
    const pageInfo = paginateItems(sorted, state.page, 6);

    listContainer.innerHTML = '';

    if (!pageInfo.items.length) {
        renderEmptyState(listContainer, 'No students found.');
        updatePaginationButtons(pagination, { currentPage: 1, totalPages: 1 });
        return;
    }

    // Use map() to create the cards for the visible student page.
    const cards = pageInfo.items.map((student) => createStudentCard(student));
    cards.forEach((card) => listContainer.appendChild(card));
    updatePaginationButtons(pagination, pageInfo);
}

async function loadStudents() {
    renderLoadingState(listContainer, 'Loading students...');

    try {
        let students = [];
        try {
            // First attempt to load dynamic active students from MySQL API
            const res = await fetchJson('api/students.php');
            if (res && res.success && Array.isArray(res.students) && res.students.length > 0) {
                students = res.students.map((s) => {
                    // Extract starting numbers or default to Year 2
                    const match = String(s.student_code || '').match(/\d+/);
                    const yearNum = match ? match[0].substring(0, 2) : '25';
                    return {
                        id: s.student_id,
                        name: s.full_name || s.student_code,
                        course: s.course,
                        email: s.email,
                        year: 2
                    };
                });
            }
        } catch (apiErr) {
            students = [];
        }

        // Fall back to static JSON if database has no rows or server is running statically
        if (!students.length) {
            students = await fetchJson('data/students.json');
        }

        state.allStudents = students;
        populateSelectOptions();
        renderStudents();
    } catch (error) {
        renderErrorState(listContainer, 'Could not load the student list. Please try again.', loadStudents);
    }
}

searchInput.addEventListener('input', (event) => {
    state.search = event.target.value;
    state.page = 1;
    renderStudents();
});

courseSelect.addEventListener('change', (event) => {
    state.course = event.target.value;
    state.page = 1;
    renderStudents();
});

yearSelect.addEventListener('change', (event) => {
    state.year = event.target.value;
    state.page = 1;
    renderStudents();
});

sortSelect.addEventListener('change', (event) => {
    state.sort = event.target.value;
    state.page = 1;
    renderStudents();
});

pagination.querySelector('[data-page="prev"]').addEventListener('click', () => {
    if (state.page > 1) {
        state.page -= 1;
        renderStudents();
    }
});

pagination.querySelector('[data-page="next"]').addEventListener('click', () => {
    const totalPages = Math.max(1, Math.ceil(searchItems(filterStudents(state.allStudents, state.course, state.year), state.search, ['name', 'course', 'email']).length / 6));
    if (state.page < totalPages) {
        state.page += 1;
        renderStudents();
    }
});

loadStudents();
