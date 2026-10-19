# Track-a-Demic

A web-based capstone and research topic management system for students and advisers.

## Description

Track-a-Demic replaces scattered chats, spreadsheets, and paper forms with one place to manage capstone work. Advisers create groups and set milestones and deadlines. Student groups submit topic proposals, log milestones and adviser meetings, save references, and upload drafts. Advisers review topics, comment, and monitor progress.

This project is the final requirement for Web Systems Technology.

## Features

- User registration, login, logout, and session management
- Two user roles with server-side access control: **Student** and **Adviser**
- Adviser tools: create groups, set milestones and deadlines, review and comment
- Student tools: topic proposals, milestone tracking, meeting logs, references, file uploads
- Topic status flow: Proposed, Approved, In Progress, Defended (or For Revision / Rejected)
- Similar-topic check on new proposals
- Dashboard charts, in-system notifications, dark/light mode
- Installable Progressive Web App
- Responsive layout for desktop (1366x768), tablet (768x1024), and mobile (375x667)

## Technologies

- HTML5, CSS3, JavaScript (ES6+)
- Fetch API (JSON requests to the PHP backend)
- PHP 8 with PDO
- MySQL / MariaDB
- Chart.js
- Git and GitHub
- Hosting: InfinityFree

## Project Structure

```
track-a-demic/
├── api/          PHP endpoints that return JSON (called with fetch)
├── assets/       CSS, JavaScript, and images
├── config/       Configuration template (config.php is not committed)
├── database/     schema.sql and seed.sql
├── docs/         Architecture diagram, ERD, and other documentation
├── includes/     Shared PHP code (database connection, auth helpers)
├── pages/        Student and adviser pages
├── uploads/      Uploaded files (not committed)
└── index.php     Entry page
```

## Running the Project Locally

Requirements: [XAMPP](https://www.apachefriends.org/) (Apache, PHP 8+, MySQL/MariaDB) and Git.

1. Clone the repository into the XAMPP web folder:
   ```
   cd C:\xampp\htdocs
   git clone https://github.com/kenyakino/track-a-demic.git
   ```
2. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
3. Create the configuration file: copy `config/config.example.php` to `config/config.php` and set your database name, user, and password.
4. Set up the database (see below).
5. Open `http://localhost/track-a-demic/` in your browser.
6. To confirm the database connection works, open `http://localhost/track-a-demic/api/health.php`. It should show `{"ok":true,"database":"connected"}`.

## Creating and Importing the Database

1. Open phpMyAdmin at `http://localhost/phpmyadmin`.
2. Click **New** and create a database named `track_a_demic` with collation `utf8mb4_general_ci`.
3. Select the new database, open the **Import** tab, and choose `database/schema.sql`. Click **Import**.
4. (Optional) Import `database/seed.sql` to load sample accounts and data.
5. Make sure the values in `config/config.php` match the database you created.

### Sample accounts

If you imported `seed.sql`, you can log in with these accounts. All of them use the password `Password123!`.

| Role | Email |
|---|---|
| Adviser | adviser1@trackademic.test |
| Adviser | adviser2@trackademic.test |
| Student | student1@trackademic.test |
| Student | student2@trackademic.test |
| Student | student3@trackademic.test |
| Student | student4@trackademic.test |

The sample data is for development and demos only. Do not import `seed.sql` on a live site.

### Database tables

| Table | Purpose |
|---|---|
| users | Student and adviser accounts |
| capstone_groups | Groups created by advisers |
| group_members | Students in each group |
| topics | Topic proposals and their status |
| milestone_templates | Milestones and deadlines set by an adviser |
| group_milestones | Each group's progress on each milestone |
| meetings | Adviser meeting logs |
| comments | Feedback on topics, milestones, and files |
| references_list | Saved references |
| files | Uploaded drafts and documents |
| notifications | In-system alerts |

## Deployment (InfinityFree)

Deployment steps will be added once the system is complete.

## Commit Message Convention

Commit messages are short, start with a verb, and describe what the commit does, for example:

- Initial project setup
- Create database connection
- Add user registration
- Implement login
- Fix validation errors

## Author

Ken Justin T. Abellaneda
