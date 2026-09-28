# DBMS Student Activity Checker & Log Verifier

A PHP web app for grading MySQL/DBMS class activities. It connects to a
MySQL server, inspects each student's database (schema and data), and
produces a scored report per student or for a whole class in one batch.

Built for six activities that use progressively different verification
strategies — from schema/DDL checks to live re-execution of student-submitted
SQL against a shared answer key.

## Features

- **Single Check** — score one student database and see a full task-by-task
  breakdown with pass/fail detail.
- **Class Batch Checker** — auto-discover student databases (by section
  prefix) or paste a list, and score them all in one pass. A failure on one
  student's database never aborts the batch for the rest of the class.
- **MySQL Log Explorer** — browse/filter the server's general query log
  (used by Activity 1).
- **Demo Generator** — spins up a sample student database to sanity-check
  the checker itself.
- **Student Portal** (`student-portal/`) — a self-check page where students
  log in with their own MySQL credentials and see their own score live.
- **CSV / multi-sheet Excel export** of results.

## Activities

| Activity | What's graded | How it's verified |
|---|---|---|
| **1 — Library Database** | `tbl_authors`, `tbl_members`, `tbl_books`, `tbl_borrow_transactions` (DDL, ALTER, foreign keys) | Final schema state via `INFORMATION_SCHEMA` |
| **2 — Suppliers & Items CRUD** | `suppliers` / `items` tables, INSERT/UPDATE/DELETE tasks | Final schema + data state (no query log dependency) |
| **3 — SELECT Statements (Registrar)** | 10 SELECT tasks logged into the student's own `activity_20260805` table | Each logged query is re-run live against `dbms_activity` and compared to the instructor's reference query |
| **4 — Logical Operators & Aggregates (Resort)** | 10 SELECT tasks logged into `activity_20260817` | Same live-comparison approach, against `dbms_activity_answer_key.activity4_answerkey` |
| **5 — SELECT Statements (Bookstore)** | 10 SELECT tasks logged into `activity_20260923` | Same live-comparison approach, against `dbms_activity_answer_key.activity5_answerkey` |
| **6 — Aggregate Functions & Set Operators (Pasalubong Center)** | 10 SELECT tasks logged into `activity_20260929` | Same live-comparison approach, against `dbms_activity_answer_key.activity6_answerkey` |

Activities 3-6 execute student-submitted SQL text to verify it. That
execution is restricted to a single validated `SELECT`/`WITH` statement (no
stacked statements, no DDL/DML keywords) on a connection opened with
`SET SESSION TRANSACTION READ ONLY`, so a malformed or malicious submission
can't affect the shared source database.

## Requirements

- PHP 8+ with the `pdo_mysql` extension
- A MySQL/MariaDB server reachable from the app
- For Activities 3-6: a `dbms_activity` source database and a
  `dbms_activity_answer_key` database (`activity3_answerkey`,
  `activity4_answerkey`, `activity5_answerkey`, `activity6_answerkey` tables
  with `task_number`/`sql_syntax` reference queries)

## Running on another PC with Docker (no programming needed)

Use this if you just want to **use** the checker on a computer that already
has MySQL (or XAMPP/MariaDB) installed. You don't need to install PHP or
anything else besides Docker Desktop.

### Step 1: Install Docker Desktop (one time only)

1. Download Docker Desktop from <https://www.docker.com/products/docker-desktop/>
   and run the installer. Keep the default options.
2. Restart the computer if the installer asks you to.
3. Open **Docker Desktop** from the Start menu. The first time, accept the
   terms. You can skip signing in.
4. Wait until the bottom-left corner of Docker Desktop shows
   **Engine running** (green).

> If Docker says **WSL needs updating**, open *Command Prompt* and run
> `wsl --update`, then restart Docker Desktop.

### Step 2: Copy the app to the computer

Copy the whole `dbms-checker` folder to the computer, for example to
`C:\dbms-checker`. (Or on GitHub, click **Code → Download ZIP** and extract it.)

### Step 3: Let the app log in to MySQL (one time only)

The app runs inside Docker, so MySQL sees it as coming from a different
machine, and MySQL's `root` account normally only accepts logins from the
same machine. Create a separate MySQL account that the app can use:

1. Open your MySQL tool (MySQL Workbench, phpMyAdmin → **SQL** tab, or
   *MySQL Command Line Client*) and log in as `root`.
2. Run the following SQL. You can change `checker` and `checker123` to any
   username/password you like, but remember them for Step 5.

   ```sql
   CREATE USER IF NOT EXISTS 'checker'@'%' IDENTIFIED BY 'checker123';
   GRANT ALL PRIVILEGES ON *.* TO 'checker'@'%' WITH GRANT OPTION;
   FLUSH PRIVILEGES;
   ```

### Step 4: Start the checker

1. Make sure Docker Desktop is open and shows **Engine running**.
2. Open the `dbms-checker` folder and **double-click `start.bat`**.
3. The first start downloads what it needs, so it can take a few minutes and
   needs internet. Later starts take just a few seconds.
4. When it finishes, your browser opens **<http://localhost:8080/>**
   automatically.

| Page | Address |
|---|---|
| Admin dashboard | <http://localhost:8080/> |
| Student portal | <http://localhost:8080/student-portal/> |

### Step 5: Enter your MySQL login in Settings (one time only)

1. On the dashboard, click **Settings** (gear icon, top right).
2. Fill in:
   - **MySQL Host:** `host.docker.internal` (this means "the MySQL on this PC").
     If MySQL is on a different computer, enter that computer's IP address
     instead, e.g. `192.168.1.11`.
   - **Port:** `3306`
   - **Admin User / Password:** the account you created in Step 3
     (e.g. `checker` / `checker123`)
3. Click **Save Settings**. The status indicator should show that MySQL is connected.

Settings are remembered even after you stop Docker or restart the computer.

### Everyday use

- **Start:** open Docker Desktop, then double-click `start.bat`.
- **Stop:** double-click `stop.bat` (or simply shut down the computer).
- **Phones and other computers on the same network (e.g. students):**
  Windows Firewall blocks them by default. Do this once:
  1. Double-click **`allow-network-access.bat`** and click **Yes** when
     Windows asks for permission.
  2. It shows the addresses to open on the phone/PC, e.g.
     `http://192.168.1.2:8080/` and `http://192.168.1.2:8080/student-portal/`.
  3. The phone must be on the same Wi-Fi/network as this computer (not
     mobile data).

### Updating to a newer version

Replace the files in the `dbms-checker` folder with the new ones (your
Settings are stored separately and are kept), then double-click `start.bat`
again. It rebuilds automatically.

### Troubleshooting

| Problem | What to do |
|---|---|
| `start.bat` says Docker Desktop is not running | Open Docker Desktop and wait for **Engine running**, then try again. |
| "port is already allocated" | Something else uses port 8080. Open `docker-compose.yml` in Notepad, change `"8080:80"` to e.g. `"8090:80"`, save, then use <http://localhost:8090/>. |
| "Access denied for user 'root'@'172.x.x.x'" | You're using `root`. Do Step 3 and use that account in Settings. |
| "Connection refused" / "timed out" | Check that MySQL (or XAMPP's MySQL) is started. If it still fails, check that MySQL's `bind-address` in `my.ini` is `0.0.0.0` (or not set), and allow port 3306 in Windows Firewall. |
| Student portal says the student's login is wrong | Student MySQL accounts must be created for host `'%'` (e.g. `'2_cs4_delacruz'@'%'`), not `'localhost'`, for the same reason as Step 3. |
| Works on this PC but not on a phone / other PC | Run `allow-network-access.bat` (see *Everyday use*). Make sure the phone is on the same Wi-Fi and not on mobile data or a "guest" Wi-Fi, since guest networks often block devices from reaching each other. If you changed the port in `docker-compose.yml`, change `8080` in the `.bat` file too. |
| The page doesn't open at all | Wait a few seconds after `start.bat` finishes and refresh. In Docker Desktop → **Containers**, `dbms-checker` should be green/running. |

## Setup (for developers, without Docker)

1. Clone the repo and configure the database connection in `config.json`
   (created automatically on first save from the Settings modal, or edit
   directly):

   ```json
   {
     "db_host": "127.0.0.1",
     "db_port": "3306",
     "db_user": "root",
     "db_pass": "",
     "log_check_enabled": true,
     "log_date_enabled": false,
     "section_filter": "2_cs4",
     "score_weights": { "task1": 15, "task2": 15, "task3": 15, "task4": 15, "task5": 20, "task6": 20 }
   }
   ```

   `score_weights` only applies to Activity 1; Activities 2–4 use fixed
   per-task weights.

2. Run the built-in PHP dev server from the project root:

   ```bash
   php -S 127.0.0.1:8000
   ```

3. Open `http://127.0.0.1:8000/` for the admin dashboard, or
   `http://127.0.0.1:8000/student-portal/` for the student self-check page.

## Project structure

```
api.php              Backend router (all AJAX actions)
config.php            Config loader + PDO connection helpers
config.json            DB connection & scoring settings
index.php              Admin dashboard
src/Checker.php        Scoring engine for all four activities
assets/css/style.css   Dashboard styling
assets/js/app.js       Dashboard frontend logic
student-portal/        Student self-check portal
activity/               Activity briefs (Word docs) given to students
Dockerfile             Docker image (PHP 8.3 + Apache + pdo_mysql)
docker-compose.yml     Port, first-run DB defaults, settings volume
start.bat / stop.bat   Double-click launchers for Windows
allow-network-access.bat  Opens port 8080 in Windows Firewall for phones/other PCs
```

## Security notes

- `config.json` holds a MySQL user/password in plaintext and is currently
  committed to the repo — treat this repo as private, or move real
  credentials to an untracked config before making it public.
- The Student Portal's login verifies credentials by opening a real MySQL
  connection with the student's own username/password (`api.php`'s
  `student_login` action), so students can only see their own database.
