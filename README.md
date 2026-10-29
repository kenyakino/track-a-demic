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
├── docs/         Diagrams (architecture, ERD, use case, flowcharts)
├── includes/     Shared PHP code (database, auth helpers, page layout)
├── pages/        Student and adviser pages
├── uploads/      Uploaded files (not committed)
└── index.php     Landing page
```

## Running the Project Locally

Requirements: [XAMPP](https://www.apachefriends.org/) (Apache, PHP 8+, MySQL/MariaDB) and Git.

1. Clone the repository into the XAMPP web folder:
   ```
   cd C:\xampp\htdocs
   git clone https://github.com/kenyakino/track-a-demic.git
   ```
2. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
3. Create the configuration file: copy `config/config.example.php` to `config/config.php`. Set your database name, user, and password, and change `adviser_code` to your own secret (see [Authentication](#authentication)).
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

## Authentication

- Passwords are hashed with PHP's `password_hash()` and checked with `password_verify()`. Plain-text passwords are never stored.
- Sessions use an HttpOnly, SameSite cookie, and the session id is renewed at login.
- Every POST request to the API must send a CSRF token (the pages add it automatically).
- Repeated wrong passwords temporarily lock sign-in for that browser session.
- Pages and API endpoints check the user's role on the server. A student cannot open adviser pages, and the other way around.

**Roles:** anyone can register as a **Student**. To register as an **Adviser**, the person must enter the adviser code. Set it in `config/config.php`:

```php
'auth' => [
    'adviser_code' => 'your-own-secret-code',
],
```

Share the code only with advisers. The default placeholder code is rejected when `env` is set to `production`. After 5 wrong codes, adviser sign-up is locked for 5 minutes for that browser session.

| Page or endpoint | Purpose |
|---|---|
| `pages/login.php` | Sign in |
| `pages/register.php` | Create an account |
| `pages/student/dashboard.php` | Student dashboard (students only) |
| `pages/adviser/dashboard.php` | Adviser dashboard (advisers only) |
| `api/register.php` | Create an account |
| `api/login.php` | Sign in |
| `api/logout.php` | Sign out |
| `api/me.php` | Current signed-in user |
| `api/student-overview.php` | Student's group, topic, and milestones (GET) |
| `api/topic-create.php` | Propose a topic (POST) |
| `api/topic-update.php` | Edit a topic and send it back for review (POST) |
| `api/topic-delete.php` | Delete a topic (POST, group leader only) |
| `api/adviser-overview.php` | Adviser's totals, attention list, and groups (GET) |
| `api/adviser-groups.php` | Adviser's groups with members, plus students without a group (GET) |
| `api/adviser-topics.php` | Topics of the adviser's groups with comments (GET) |
| `api/group-create.php` | Create a group, optionally with students (POST) |
| `api/group-update.php` | Rename a group (POST) |
| `api/group-delete.php` | Delete a group that has no work yet (POST) |
| `api/group-member-add.php` | Add a student to a group (POST) |
| `api/group-member-remove.php` | Remove a student from a group (POST) |
| `api/group-leader-set.php` | Choose the group leader (POST) |
| `api/topic-review.php` | Approve, request revision, reject, start, or defend a topic (POST) |
| `api/topic-comment-add.php` | Comment on a topic (POST) |

## Student Dashboard

After signing in, a student sees their group, research topic, and milestones. The page loads its data with the Fetch API and renders it in the browser.

- A student sees only their own group. The server checks this on every request, so a student cannot read or change another group's topic.
- **Create:** any group member can propose a topic. A group has one active topic at a time.
- **Read:** the topic card shows the status (Proposed, Approved, In Progress, For Revision, Defended), the adviser's remarks, and past rejected topics.
- **Update:** a topic can be edited while it is Proposed or For Revision. Saving sends it back to the adviser for review.
- **Delete:** only the group leader can delete a topic, and only while it is Proposed or For Revision.
- The adviser gets an in-system notification whenever a topic is submitted, changed, or withdrawn.
- Forms use a dialog, live character counters, inline validation messages, and toast messages.

## Adviser Dashboard

Advisers have three pages, reached from the navigation bar:

- **Overview:** totals (groups, students, topics to review, overdue milestones), a list of things that need attention, and a table of groups with their topic status and milestone progress.
- **Groups:** create groups, rename them, add or remove students, choose the group leader, and delete groups that have no work yet. Only students who are not in a group yet can be added.
- **Topic reviews:** filter topics by status, then approve, ask for changes, or reject a proposal. Approved topics can be moved to In Progress and Defended. Rejecting or asking for changes requires remarks. Advisers can also comment on a topic, and the group sees the comments on their dashboard.

An adviser can only see and change their own groups. The server checks ownership on every request. Students get an in-system notification for each decision, comment, or change to their group.

## Design and Theme

The interface is responsive and tested for these minimum sizes: desktop 1366x768, tablet 768x1024, and mobile 375x667.

| Role | Color |
|---|---|
| Main (background) | `#E6E8E6` |
| Text | `#000000` |
| Cards and panels | `#F4F5F4` and `#D9DCD9` |
| Secondary (links, progress, accents) | `#2F6B4A` forest green |
| Secondary tint | `#CBDCD1` |

Signed-in pages have a navigation bar under the top bar. It lists the pages for the user's role, marks the current page, and scrolls sideways on narrow screens. A **dark mode** switch is in every page header. The choice is saved with `localStorage`, so it stays after a refresh. If nothing is saved yet, the page follows the device's light or dark setting. Colors are defined once as CSS variables at the top of `assets/css/style.css`.

Shared page pieces (head, logo, theme button) live in `includes/layout.php`, so every page uses the same markup.

### System architecture

![System architecture](docs/diagrams/architecture.png)

### Entity relationship diagram

![Entity relationship diagram](docs/diagrams/erd.png)

### Use case diagram

![Use case diagram](docs/diagrams/use-case.png)

### Flowcharts

Registration, login, and access control:

![Authentication flowchart](docs/diagrams/flowchart-authentication.png)

Capstone workflow, from topic proposal to defense:

![Capstone workflow flowchart](docs/diagrams/flowchart-capstone-workflow.png)

To regenerate the diagrams, install [Graphviz](https://graphviz.org/download/) and Python 3, then run from `docs/diagrams/source`:

```
neato -Tpng -Gdpi=150 erd.dot -o ../erd.png
dot -Tpng -Gdpi=150 architecture.dot -o ../architecture.png
dot -Tpng -Gdpi=150 flowchart_authentication.dot -o ../flowchart-authentication.png
dot -Tpng -Gdpi=150 flowchart_capstone_workflow.dot -o ../flowchart-capstone-workflow.png
python3 use_case.py
```

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
