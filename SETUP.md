# Run Locally on Windows

## Requirements

XAMPP provides PHP, MySQL/MariaDB, and the PDO MySQL extension. This project uses the installed XAMPP bundle at `C:\xampp`; no Composer or npm packages are required.

## Start the Database

Open `C:\xampp\xampp-control.exe` and start **MySQL**. Apache is not required when using PHP's built-in development server below.

From the project root, import the schema and starter data once:

```powershell
Get-Content .\database\smart_health_diet.sql -Raw | & 'C:\xampp\mysql\bin\mysql.exe' -u root
```

The default database settings in `config/database.php` expect MySQL on localhost, database `smart_health_diet`, user `root`, and a blank password. Update those constants if your local MySQL credentials differ.

## Start the Web App

Run this from the project root:

```powershell
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8000 -t .
```

Open <http://127.0.0.1:8000/login.php> in a browser. Keep the terminal running while using the app.

## Demo Accounts

- Admin: `admin@healthtrack.com` / `admin123`
- Approved dietitian: `sarah@healthtrack.com` / `dietitian123`
- Pending dietitian: `james@healthtrack.com` / `dietitian123`
- User: `alice@example.com` / `user123`

These accounts and the blank MySQL password are for local development only.

Password reset email delivery is not configured in this project. The reset page now directs users to contact the local administrator instead of pretending to send an email.

## Verify the Setup

```powershell
& 'C:\xampp\php\php.exe' tests\verify_step1.php
```
