Core Features:
--------------

-   **Single PHP file** with all functionality
-   **JSON-based storage** (no external database needed)
-   **User authentication** with protected access
-   **Multilingual support** (French default, English available)
-   **Security protection** for all inputs
-   **File attachments** with hashed storage

Security Features:
------------------

-   CSRF token protection on all forms
-   Input sanitization with `htmlspecialchars()`
-   Password hashing with `password_hash()`
-   Session-based authentication
-   Protected uploads directory with `.htaccess`
-   Hashed filenames for security

Data Structure:
---------------

-   `/data/issues.json` - Issues storage
-   `/data/users.json` - User credentials (admin/tracker02 by default)
-   `/data/files.json` - File metadata
-   `/data/locales.json` - Language translations
-   `/uploads/` - File attachments (protected)

Issue Fields:
-------------

-   Date, Category, ID, Subject, Description
-   State (New, In Process, Review, Done)
-   Comments with Date, Comment, Author, File attachment

Pages:
------

1.  **Homepage** - Lists all issues with ID, date, subject, category, state, comment count
2.  **Issue Detail** - Shows full issue details with comments
3.  **New Issue** - Form to create new issues

Styling:
--------

-   Clean, minimalistic design using Tailwind CSS
-   Responsive layout
-   Color-coded issue states
-   Professional appearance

Setup Instructions:
-------------------

1.  Create the directory structure and upload the PHP file
2.  Ensure the web server has write permissions for `data/` and `uploads/` directories
3.  Access the application - it will auto-initialize all required files
4.  Login with: **admin** / **tracker02**

The application is ready to use on any hosting environment without external dependencies!
