# invoice-debt-tracker
Invoice and Debt Tracker (IDT) is a Laravel-based SaaS application designed to help businesses manage invoices, track debts, monitor payments, and automate customer payment reminders.


---

## 🚀 Features

### Current Features

* User Authentication
* Login & Registration
* Dashboard Access
* Role-based User System
* Laravel Breeze Authentication
* ...
* 
* 

### Planned Features

* Invoice Management
* Debt Tracking
* Payment Monitoring
* Reminder Automation
* Customer Management
* Admin Dashboard
* Reports & Analytics
* Email Notifications
* Multi-user Roles & Permissions

---

## 🛠️ Tech Stack

* PHP
* Laravel
* MySQL
* Blade
* JavaScript
* Bootstrp CSS
* Laravel Breeze

---

## 📂 Project Structure

```bash

app/
bootstrap/
config/
database/
public/
resources/
screenshots/
routes/
storage/
```

---

## ⚡ Installation

Clone the repository:

```bash
git clone https://github.com/GodsonWalter/invoice-debt-tracker.git
```

Move into the project directory:

```bash
cd invoice-debt-tracker
```

Install dependencies:

```bash
composer install
npm install
```

Copy the environment file:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

Configure your database in `.env`

Run migrations:

```bash
php artisan migrate
```

Start the development server:

```bash
php artisan serve
```

Run Vite:

```bash
npm run dev
```

---

## 🔐 Authentication

The application uses Laravel Breeze for authentication.

Features include:

* Registration
* Login
* Logout
* Remember Me
* Password Reset
* Session Authentication

---

## 👥 User Roles

The system supports the following roles:

* Owner
* Admin
* Manager
* Staff
* Customer

The first registered user is automatically assigned the `owner` role.

---

## 📌 Roadmap

* [x] Laravel Setup
* [x] Database Configuration
* [x] Authentication System
* [ ] Invoice Module
* [ ] Debt Tracking Module
* [ ] Reminder System
* [ ] Reporting Dashboard
* [ ] Payment Integration
* [ ] Multi-tenancy
* [ ] API Development

---

## 📸 Screenshots

Goto screenshots folder in the root directory

---

## 🤝 Contributing

Contributions, ideas, and feedback are welcome.

Fork the repository and submit a pull request.

---

## 📄 License

This project is open-sourced under the MIT License.

---

## 👨‍💻 Author

Walter Godson Nazike

Open to:
💼 Freelance | Remote | Hybrid | opportunities
🤝 Collaborations
📈 Networking with developers & founders

#Laravel #PHP #WebDevelopment #SaaS #BuildInPublic #MySQL #BootstrapCSS #JavaScript #SoftwareEngineer #SoftwareDeveloper #FullStackDeveloper #Tech

## Workspace lifecycle operations

Workspace lifecycle processing is configuration-driven. The defaults are 30 days for owner self-service recovery, 120 days before permanent deletion, and permanent-deletion warnings at 30, 7, and 1 day(s). These can be changed with `WORKSPACE_SELF_SERVICE_RESTORE_DAYS`, `WORKSPACE_PERMANENT_DELETION_DAYS`, `WORKSPACE_OWNER_RESTORE_WARNING_DAYS`, and `WORKSPACE_PERMANENT_DELETION_WARNING_DAYS`.

Production must run both a queue worker and Laravel’s scheduler. For example:

```text
php artisan queue:work --tries=3
php artisan schedule:work
```

Alternatively, configure cron to run `php artisan schedule:run` every minute. The scheduler runs `workspaces:lifecycle` daily, which queues retention notifications and permanent-deletion jobs. Keep the queue worker running so deletion, restoration, and retention emails are delivered.

Connect with me:

* [LinkedIn](https://www.linkedin.com/in/walter-godson)
* [GitHub](https://github.com/GodsonWalter)

---

## ⭐ Support

If you like this project, please consider giving it a star on GitHub.
