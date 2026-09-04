# Artemis 2.0

Artemis 2.0 is a Laravel-based review-center learning management system for nursing licensure and other professional examination programs.

## Main workflow

- Administrators maintain reusable master courses and create course batches.
- Learners enroll in a batch to access its assigned course.
- Course content is organized as subjects, topics, subtopics, and assessments.
- Assessments support multiple choice, SATA, grid/matrix, cloze/dropdown, and highlighting questions.
- Learners follow controlled progression through learning materials and assessments before taking the course mock exam.
- Certificates are issued after satisfying the configured mock-exam passing rule.

## Local installation

Requirements:

- PHP 8.2 or newer
- Composer
- MySQL or SQLite
- Node.js and npm when rebuilding Vite assets

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan storage:link
php artisan serve
```

On macOS or Linux, use `cp .env.example .env` instead of `copy`.

Configure the database and Hostinger SMTP mailbox in `.env`. Never commit `.env` or production credentials.

Private Google Drive videos can be served through Artemis without exposing their
original Drive links. Enable the Google Drive API in the service-account project,
keep the credentials JSON outside the public web directory, share the approved
video folder with the service-account email as Viewer, and configure:

```env
GOOGLE_DRIVE_STREAMING_ENABLED=true
GOOGLE_DRIVE_CREDENTIALS=/home/account/private/artemis-video-reader.json
GOOGLE_DRIVE_FOLDER_ID=your-approved-folder-id
```

The production credentials JSON must never be committed to Git or stored under
Laravel's `public` directory.

On Git-based shared-hosting deployments, keep learner uploads outside the Git
working tree so deployments cannot remove them. Create a persistent directory
and set its absolute path in production:

```env
PUBLIC_STORAGE_ROOT=/home/account/artemis-uploads
```

After changing this path, copy or restore the existing `documentation` and
`subtopic-videos` folders into that directory, then clear Laravel's cached
configuration. Protected learner routes read the files directly from this
location and do not require the files to be committed to Git.

## Production deployment

For Hostinger deployment:

1. Point the domain document root to the Laravel `public` directory.
2. Create a MySQL database and configure its credentials in the server-side `.env`.
3. Set `APP_ENV=production`, `APP_DEBUG=false`, the final HTTPS `APP_URL`, and a unique `APP_KEY`.
4. Configure the Hostinger SMTP mailbox and sender address.
5. Install production dependencies with `composer install --no-dev --optimize-autoloader`.
6. Run `php artisan migrate --force` and `php artisan storage:link`.
7. Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
8. Ensure `storage` and `bootstrap/cache` are writable by the hosting account.
9. Configure a cron job for `php artisan schedule:run` if scheduled tasks are enabled.

Do not run the sample database seeder on production.

## Security

- Public registration always creates learner accounts.
- Email verification uses a six-digit code.
- Administrative and instructor actions are role protected.
- Learner progression and assessment completion are validated on the server.
- Sensitive configuration, uploaded media, runtime caches, and local databases are excluded from Git.
