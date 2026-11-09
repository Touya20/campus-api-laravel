## Campus Student Portal API (Laravel)

This is the PHP/Laravel backend for the Campus Student Portal. The former Express/TypeScript `campus-api` folder has been removed, and the sibling `campus-app` React frontend now calls this API through Axios. Routes are versioned under `/api/v1`, and responses use `{ success, data }` or `{ success: false, message, requestId }` envelopes.

The requested `Touya20/elluna-api` GitHub repository could not be inspected because GitHub returned 404. This project follows a conventional layered Laravel structure with controllers, Form Requests, services, repositories, middleware, migrations, and seeders; it can be aligned later if an accessible folder tree is provided.

### Requirements

- PHP 8.3 or newer (scaffolded with PHP 8.5)
- Composer 2
- PostgreSQL PDO extension (`pdo_pgsql`) enabled
- Neon PostgreSQL or compatible database

### Local setup

1. Copy `.env.example` to `.env`.
2. Generate an application key with `php artisan key:generate`; replace `JWT_SECRET` with a random secret of at least 32 characters.
3. Set `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` from the Neon connection string. Keep credentials in `.env`, not in the React app. Keep `DB_SSLMODE=require`.
4. Set `CLIENT_ORIGIN` to the React development origin.
5. Install PHP dependencies using `composer install`.
6. Apply the schema with `php artisan migrate`.
7. For local demo records only, set `SEED_STUDENT_PASSWORD` to a unique password of at least 12 characters and run `php artisan db:seed`. It creates the fictional account `student@example.test` and sample portal content. The seeder refuses to run in production.
8. Start the API with `php artisan serve --host=127.0.0.1 --port=8000`.

`GET /health` and Laravel's `/up` are operational health checks. The API base URL is `http://127.0.0.1:8000/api/v1`. Switching the frontend from the original backend is a separate integration step.

### API modules

- Authentication: register, login, stateless logout, and current identity. Student registration is enabled as in the existing backend; the server always assigns the `student` role.
- Student profile and dashboard.
- Enrolled courses, modules, and allowlisted HTTPS learning material links.
- Assignment lists, text drafts, and text submissions. File uploads are deferred.
- Weekly schedule, released grades, and campus/course announcements with per-student read state.

Protected student routes use JWT bearer tokens. Passwords use Argon2id. Requests are validated, unknown fields are rejected, and data access is scoped by student ownership and enrollment. Logout means discarding the access token on the client.

### Notes

- The temporary setup runtime used while scaffolding may not be on the system PATH. Install PHP and Composer normally, then reopen the terminal.
- Neon needs the PHP PostgreSQL PDO driver. If `php -m` does not list `pdo_pgsql`, install/enable that extension for the PHP used by the terminal.
- Instructor/admin authoring routes, password-reset delivery, refresh-token revocation, secure file storage, and broader role management remain later enhancements.
