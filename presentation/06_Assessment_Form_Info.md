# 6. Project Assessment Form: Details to Fill In

The form will be provided by the instructor. Use this sheet so every member copies the **same** details.
Fill in the blanks **before** the scheduled presentation and bring the **printed, completed** form.

| Field | Value |
|---|---|
| Group name / number | ______________________ |
| Section / Year | ______________________ |
| Presentation date and time slot | ______________________ |
| **Project title** | PartsBin: A Web-Based Hardware Inventory Management System for Small PC Shops |
| Members (full names) | 1. ______________________ (Overview, Accounts) <br> 2. ______________________ (Dashboard, Categories, Products) <br> 3. ______________________ (Product registration and variants) <br> 4. ______________________ (Stock, Restocks, Valuation, AI) |
| Leader | ______________________ |
| Platform / Technologies | PHP 8, MySQL/MariaDB (XAMPP), Apache, HTML, CSS, JavaScript; Python + Google Gemini (AI assistant) |
| Type | Web-based system |

## Short description (copy if the form asks for one)

> PartsBin is a web-based inventory management system for small PC repair and custom-build shops. It manages products and their variants (such as capacity or model), tracks stock levels with low-stock alerts, records every restock and adjustment in a stock history with supplier and user, computes weighted-average cost, and reports inventory value by category. It includes secure user accounts with hashed passwords and profile pictures, product image uploads, and an AI assistant that answers questions about the current stock.

## Features list (copy if the form asks for one)

1. User sign up, log in and log out (session-based, hashed passwords)
2. User profile: update details, profile picture and password
3. Dashboard: totals, product gallery, low-stock alerts, recent activity
4. Category management (add, edit, delete)
5. Product management with picture upload (create, view, edit, delete)
6. Multiple variants per product saved in one submission
7. Product search and filters (category, condition tag, stock level)
8. Stock table with restock and stock adjustment
9. Weighted-average cost computation
10. Stock movement history (audit trail) and restock history with date and supplier filters
11. Inventory valuation at cost and retail by category, with potential profit
12. AI inventory assistant (Google Gemini)

## PHP concepts implemented (copy if the form asks for one)

Superglobals (`$_GET`, `$_POST`, `$_SESSION`, `$_FILES`, `$_SERVER`), sessions, password hashing (`password_hash` / `password_verify`), file upload and file handling (`move_uploaded_file`, `unlink`, `mkdir`, `file_get_contents`, `copy`), MySQL database connection with mysqli prepared statements, user-defined functions with type declarations, arrays and array functions, include/require, constants, form validation (regex, `filter_var`), output escaping (`htmlspecialchars`), exception handling (`try/catch`), JSON, and redirects with `header()`.
