
<?php
require_once __DIR__ . '/auth.php';
requireRole('admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - Practical 13</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 30px;
            background: #f4f6f9;
            color: #222;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        h1 {
            color: #164477;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #555;
            margin-bottom: 25px;
        }

        .panel {
            background: white;
            padding: 22px;
            margin-bottom: 22px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        h2 {
            margin-top: 0;
            color: #222;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 12px;
        }

        input {
            padding: 12px;
            border: 1px solid #bbb;
            border-radius: 4px;
            font-size: 14px;
            width: 230px;
            max-width: 100%;
        }

        input:focus {
            outline: 2px solid #b8d0ff;
            border-color: #164bb5;
        }

        button {
            padding: 10px 15px;
            margin: 3px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            background: #164bb5;
            color: white;
            font-size: 14px;
        }

        button:hover {
            background: #103a8c;
        }

        button.delete {
            background: #b42318;
        }

        button.delete:hover {
            background: #8f1c13;
        }

        button.secondary {
            background: #666;
        }

        button:disabled {
            opacity: 0.6;
            cursor: wait;
        }

        .message {
            margin: 15px 0;
            min-height: 20px;
        }

        .success {
            color: #187344;
        }

        .error {
            color: #b42318;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th,
        td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #e7edf7;
        }

        tr:hover {
            background: #f8faff;
        }

        #search {
            width: 350px;
        }

        @media (max-width: 600px) {
            body {
                padding: 15px;
            }

            input,
            #search {
                width: 100%;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <h1>Student Management — Practical 13</h1>

    <p class="subtitle">
        Add, view, update, delete, and search students without reloading the page.
    </p>

    <div class="panel">
        <h2 id="formTitle">Add New Student</h2>

        <form id="studentForm">
            <input type="hidden" id="student_id">

            <div class="form-row">
                <input
                    type="text"
                    id="student_code"
                    maxlength="20"
                    placeholder="Student Code"
                    required
                >

                <input
                    type="text"
                    id="full_name"
                    maxlength="100"
                    placeholder="Full Name"
                    required
                >
            </div>

            <div class="form-row">
                <input
                    type="email"
                    id="email"
                    maxlength="254"
                    placeholder="Email"
                    required
                >

                <input
                    type="text"
                    id="course"
                    maxlength="100"
                    placeholder="Course"
                    required
                >
            </div>

            <button type="submit" id="saveButton">
                Add Student
            </button>

            <button
                type="button"
                id="cancelButton"
                class="secondary"
                hidden
            >
                Cancel Edit
            </button>
        </form>
    </div>

    <div class="panel">
        <h2>Student Records</h2>

        <input
            type="search"
            id="search"
            placeholder="Search name, email, course, or code"
        >

        <p
            id="message"
            class="message"
            role="status"
            aria-live="polite"
        ></p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student Code</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody id="studentTable">
                    <tr>
                        <td colspan="6">Loading students...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
const API_URL = 'api/students.php';
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

const form = document.getElementById('studentForm');
const table = document.getElementById('studentTable');
const message = document.getElementById('message');
const searchInput = document.getElementById('search');
const saveButton = document.getElementById('saveButton');
const cancelButton = document.getElementById('cancelButton');
const formTitle = document.getElementById('formTitle');

let students = [];
let searchTimer;

function showMessage(text, type = '') {
    message.textContent = text;
    message.className = 'message ' + type;
}

async function requestApi(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            ...(options.headers || {})
        }
    });

    let data;

    try {
        data = await response.json();
    } catch {
        throw new Error(
            'The server returned an invalid JSON response.'
        );
    }

    if (!response.ok || !data.success) {
        throw new Error(
            data.message || 'The request failed.'
        );
    }

    return data;
}

function makeCell(value) {
    const cell = document.createElement('td');
    cell.textContent = value ?? '';
    return cell;
}

function renderStudents() {
    table.replaceChildren();

    if (students.length === 0) {
        const row = document.createElement('tr');
        const cell = makeCell('No students found.');

        cell.colSpan = 6;
        row.appendChild(cell);
        table.appendChild(row);

        return;
    }

    students.forEach(student => {
        const row = document.createElement('tr');

        row.appendChild(makeCell(student.student_id));
        row.appendChild(makeCell(student.student_code));
        row.appendChild(makeCell(student.full_name));
        row.appendChild(makeCell(student.email));
        row.appendChild(makeCell(student.course));

        const actions = document.createElement('td');

        const editButton = document.createElement('button');
        editButton.textContent = 'Edit';

        editButton.addEventListener('click', () => {
            editStudent(student);
        });

        const deleteButton = document.createElement('button');
        deleteButton.textContent = 'Delete';
        deleteButton.className = 'delete';

        deleteButton.addEventListener('click', () => {
            deleteStudent(student);
        });

        actions.appendChild(editButton);
        actions.appendChild(deleteButton);

        row.appendChild(actions);
        table.appendChild(row);
    });
}

async function loadStudents() {
    const loadingRow = document.createElement('tr');
    const loadingCell = makeCell('Loading students...');

    loadingCell.colSpan = 6;
    loadingRow.appendChild(loadingCell);
    table.replaceChildren(loadingRow);

    try {
        const query = new URLSearchParams({
            search: searchInput.value.trim()
        });

        const data = await requestApi(
            `${API_URL}?${query.toString()}`
        );

        students = data.students;
        renderStudents();

        showMessage(
            `${students.length} student(s) loaded.`,
            'success'
        );
    } catch (error) {
        students = [];
        renderStudents();
        showMessage(error.message, 'error');
    }
}

function editStudent(student) {
    document.getElementById('student_id').value =
        student.student_id;

    document.getElementById('student_code').value =
        student.student_code;

    document.getElementById('full_name').value =
        student.full_name;

    document.getElementById('email').value =
        student.email;

    document.getElementById('course').value =
        student.course;

    // Change the heading and button for editing.
    formTitle.textContent = 'Edit Student';
    saveButton.textContent = 'Update Student';
    cancelButton.hidden = false;

    showMessage(
        'Editing student ID ' + student.student_id + '.'
    );

    form.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });
}

function resetForm() {
    form.reset();

    document.getElementById('student_id').value = '';

    // Restore the original add-student form.
    formTitle.textContent = 'Add New Student';
    saveButton.textContent = 'Add Student';
    cancelButton.hidden = true;
}

cancelButton.addEventListener('click', () => {
    resetForm();
    showMessage('Edit cancelled.');
});

form.addEventListener('submit', async event => {
    event.preventDefault();

    const id = document.getElementById('student_id').value;

    const payload = {
        action: id ? 'update' : 'create',
        csrf_token: CSRF_TOKEN,
        student_code:
            document.getElementById('student_code').value.trim(),
        full_name:
            document.getElementById('full_name').value.trim(),
        email:
            document.getElementById('email').value.trim(),
        course:
            document.getElementById('course').value.trim()
    };

    if (id) {
        payload.student_id = Number(id);
    }

    saveButton.disabled = true;
    showMessage('Saving student...');

    try {
        const data = await requestApi(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        resetForm();
        showMessage(data.message, 'success');

        await loadStudents();
    } catch (error) {
        showMessage(error.message, 'error');
    } finally {
        saveButton.disabled = false;
    }
});

async function deleteStudent(student) {
    const confirmed = confirm(
        `Are you sure you want to delete ${student.full_name}?`
    );

    if (!confirmed) {
        return;
    }

    showMessage('Deleting student...');

    try {
        const data = await requestApi(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'delete',
                csrf_token: CSRF_TOKEN,
                student_id: Number(student.student_id)
            })
        });

        showMessage(data.message, 'success');

        // Clear the form if this student was being edited.
        if (
            document.getElementById('student_id').value ===
            String(student.student_id)
        ) {
            resetForm();
        }

        await loadStudents();
    } catch (error) {
        showMessage(error.message, 'error');
    }
}

// Search after the user stops typing for 300 milliseconds.
searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadStudents, 300);
});

// Load the initial list.
loadStudents();
</script>

</body>
</html>
