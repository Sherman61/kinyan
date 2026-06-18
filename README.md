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

The app uses PDO prepared statements, password hashing, CSRF tokens, session cookie hardening, server-side validation, escaping, admin guards, ownership checks, MIME/extension image validation, and basic session rate limits for reports/contact tracking.
