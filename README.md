# Kinyan.live

Kinyan is a PHP 8+ and MySQL direct-contact car marketplace. It supports cars for sale, wanted car posts, user dashboards, admin moderation, image uploads, favorites, reports, SEO metadata, and no checkout/cart/payment flow.

## Setup

1. Point Apache/PHP at this directory.
2. Create a MySQL database and import `database/schema.sql`.
3. Configure credentials by copying `.env.example` to `.env` and editing the values:

```bash
cp .env.example .env
nano .env
```

4. Make `uploads/cars` writable by the web server.
5. Create the first admin account:

```bash
php -r "echo password_hash('change-this-password', PASSWORD_DEFAULT), PHP_EOL;"
```

Paste that hash into the admin `INSERT` statement at the bottom of `database/schema.sql`, or insert it manually:

```sql
INSERT INTO users (name, email, phone, password_hash, role)
VALUES ('Kinyan Admin', 'admin@kinyan.live', '', 'PASTE_HASH_HERE', 'admin');
```

## Moderation

New cars and wanted posts default to `pending`. Admins can switch `auto_approve_listings` in `admin/settings.php`.

User trust levels:

- Level 1: can post, but new posts and active listing edits require admin approval.
- Level 2: can edit their own existing car listings without sending them back to pending.
- Level 3: new posts and edits can go active automatically.

## Security Notes

The app uses PDO prepared statements, password hashing, CSRF tokens, session cookie hardening, server-side validation, escaping, admin guards, ownership checks, MIME/signature image validation, database-backed rate limits, secure password-reset tokens, and centralized error logging.

Logged-in users have account-synced saved listings; guests retain browser-local saves. Browse results are paginated, and users can compare up to four active cars. Contact analytics use a CSRF-protected POST endpoint and never control whether the direct call, text, or email action opens.

## Database migrations and maintenance

Apply every SQL file in `database/migrations/` in filename order when upgrading an existing installation. The migrations are idempotent and `database/schema.sql` remains the source of truth for fresh installations.

Run listing expiration once per day from cron:

```bash
php /var/www/kinyan/maintenance/expire-listings.php
```

The repository includes `maintenance/kinyan.cron` for `/etc/cron.d/kinyan`. Verify database, writable image storage, and the most recent expiration run with:

```bash
php /var/www/kinyan/maintenance/health-check.php
```

The expiration period is configurable from Admin → Settings and defaults to 45 days. Owners can renew expired listings from their dashboard; normal moderation rules still apply.

## Email delivery

Password-reset emails are disabled unless `MAIL_ENABLED=true`. For Mailtrap Email Sending over SMTP, use the token as the SMTP password and `api` as the SMTP username:

```env
MAIL_ENABLED=true
MAIL_TRANSPORT=smtp
MAIL_FROM=support@kinyan.shop
MAIL_FROM_NAME=Kinyan
SMTP_HOST=live.smtp.mailtrap.io
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USER=api
SMTP_PASS=your-mailtrap-api-token
```

If outbound port 587 is blocked by the host, use Mailtrap's alternate SMTP port `2525`. This server was tested with `2525`.

Keep `MAIL_ENABLED=false` until a reset email test succeeds. When email is disabled or SMTP is incomplete, the password-reset screen shows users that the email service is temporarily offline and asks them to try again later.

After sending a test, check Mailtrap sent logs at https://mailtrap.io/sending/email_logs.

## Deployment follow-ups

- Configure an authenticated SMTP relay such as Mailtrap on port 587, then send a real password-reset test email before enabling production resets.
- Keep `kinyan.shop` as the preferred application URL. `kinyan.live` remains available through its separate Apache virtual host.
