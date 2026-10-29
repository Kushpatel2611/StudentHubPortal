# Practical 8 — MySQL Schema, ER Model, PDO and Prepared Statements

## Files added
- `db.php`: PDO connection configuration.
- `practical8.php`: connection status, database table display, registration insert using a prepared statement, and parameterized search.
- `sql/studenthub_practical8.sql`: database schema and sample seed data.
- `sql/ER_MODEL.md`: ER relationship summary.

## Setup in XAMPP
1. Copy the complete `Student_Portal` folder into `C:\\xampp\\htdocs\\`.
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin/`.
4. Select **Import**, choose `Student_Portal/sql/studenthub_practical8.sql`, then click **Import/Go**.
5. If your MySQL username/password differ from XAMPP defaults, update `DB_USER` and `DB_PASS` in `db.php`.
6. Open `http://localhost/Student_Portal/practical8.php`.

## Run using PHP's built-in server instead
From VS Code PowerShell terminal:

```powershell
cd C:\xampp\htdocs\Student_Portal
C:\xampp\php\php.exe -S localhost:8000
```

Then open `http://localhost:8000/practical8.php`. MySQL must still be running in XAMPP.

## Test checklist
- Successful PDO connection status appears.
- Seed students, events, and registrations display.
- Searching by student, student code, or event filters the registrations.
- Submitting a new student/event combination saves a registration.
- Submitting the same combination again is rejected by the unique constraint.
- Foreign keys reject invalid student/event IDs.

## Security notes
- PDO uses exception mode and native prepared statements.
- All user values used in SQL are passed as bound parameters.
- Displayed data is escaped with `htmlspecialchars()`.
- The registration form has a session CSRF token.
- For production, use a dedicated database account with least privilege instead of MySQL root.
