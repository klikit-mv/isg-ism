# isg-ism

A Laravel 13 application with login and registration, built on [Laravel Breeze](https://laravel.com/docs/starter-kits) (Blade + Tailwind CSS).

## Features

- User registration and login
- Logout, "remember me"
- Forgot / reset password
- Email verification
- Password confirmation
- Profile page (update name/email, change password, delete account)
- Protected `/dashboard` route (requires authentication)

## Requirements

- PHP 8.3+
- Composer
- Node.js 20+ and npm
- SQLite (default) or MySQL/PostgreSQL

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # default DB is SQLite; edit .env to use MySQL etc.
php artisan migrate
npm install
npm run build
php artisan serve
```

Then open http://127.0.0.1:8000 and use the **Register** / **Log in** links.

For development with hot reload, run `npm run dev` in a second terminal (or `composer run dev` to start everything together).

## Key files

| Path | Purpose |
| --- | --- |
| `routes/auth.php` | Login, registration, password reset, verification routes |
| `routes/web.php` | Home, dashboard and profile routes |
| `app/Http/Controllers/Auth/` | Authentication controllers |
| `app/Http/Requests/Auth/LoginRequest.php` | Login validation and rate limiting |
| `resources/views/auth/` | Login, register and password Blade views |
| `tests/Feature/Auth/` | Authentication feature tests |

## Tests

```bash
php artisan test
```
