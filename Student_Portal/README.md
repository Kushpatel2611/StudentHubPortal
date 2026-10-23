# CHARUSAT Student Hub Portal

A front-end StudentHub Portal project built with HTML, CSS, JavaScript, and JSON sample data.

## Pages

- Home
- About
- Register
- Login
- Dashboard
- Events
- Students
- Profile
- Contact
- Admin
- FAQ
- Feedback

## Run the project

The Events, Students, FAQ, and registration location selectors load JSON files with `fetch()`. Run the project through a local web server rather than opening the HTML files directly with `file://`.

### Option 1: VS Code Live Server
1. Extract the `Student_Portal` folder.
2. Open that folder in VS Code.
3. Install the **Live Server** extension if needed.
4. Right-click `index.html` and choose **Open with Live Server**.

### Option 2: Python
Open a terminal in the `Student_Portal` folder and run:

```bash
python -m http.server 8000
```

Then open `http://localhost:8000` in your browser.

## Notes

- The theme button switches between light and dark mode. The preference is saved in browser local storage.
- Events, students, and FAQ content is sample data stored in the `data` folder.
- This is a front-end demonstration. Login, registration, profile, feedback, and admin pages do not connect to a real authentication system or database.
- Do not use real passwords or personal information in this demo.


### CSS organization

All pages continue to load `css/style.css`. It imports the stylesheet sections in order: `style-base.css`, `layout.css`, `components.css`, `home.css`, `dashboard.css`, `profile.css`, `contact.css`, and `admin.css`. Keep the import order unchanged so existing style precedence is preserved.

## Practical 4: Dynamic JavaScript UI Components

The project demonstrates the following interactive components:

- **Collapsible FAQ:** On `faq.html`, click a question to expand or collapse its answer.
- **Modal popup:** On `index.html`, select **About StudentHub** and close the dialog with either close control, by clicking outside it, or by pressing Escape.
- **Content slider:** The Campus Highlights section on `index.html` has previous/next controls, slide dots, and automatic rotation.
- **Notification banner:** The welcome banner on Home and help banner on FAQ can be dismissed.
- **Hamburger menu:** Resize the browser to mobile width and use the menu icon to show or hide navigation.
- **Light/dark theme:** Use the theme button in the header; the choice is saved in browser local storage.

For evidence, capture each component while it is visibly active, including separate light and dark theme screenshots.
