# DIDN API

Production REST backend for the **Direct Impact Development Network (DIDN)** website.

Serves two clients:

1. **DIDN Admin Dashboard** – authenticated content management
2. **DIDN Public Website** – read-only public content

Built with Laravel 13, PHP 8.4, MySQL, Sanctum (token auth), and Pest.

---

## Stack

| Concern | Technology |
|---|---|
| Framework | Laravel 13 |
| Auth | Laravel Sanctum (Bearer tokens) |
| Rich text sanitisation | mews/purifier (HTMLPurifier) |
| Storage | Laravel Filesystem (`public` disk) |
| Testing | Pest + PHPUnit, MySQL `didn_test` database |
| Code style | Laravel Pint |

---

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Configure your database in .env (MySQL recommended)

php artisan migrate
php artisan storage:link     # serve uploaded images from /storage
php artisan db:seed          # creates the admin user (+ demo content non-production)
```

Optional dev server:

```bash
php artisan serve          # http://localhost:8000
```

> `.env` is git-ignored. Never commit real credentials.

---

## Environment variables

| Variable | Purpose | Example |
|---|---|---|
| `APP_URL` | Base URL of the API. Used to build image URLs. | `https://api.didn.org` |
| `DB_*` | Database connection. | `DB_DATABASE=didn_backend` |
| `FILESYSTEM_DISK` | Default filesystem disk. Uploads always use the `public` disk. | `local` |
| `ALLOWED_ORIGINS` | PHP-style array of origins allowed to access the API (CORS). | `['http://localhost:3000','https://www.directimpactnetwork.com']` |
| `SANCTUM_EXPIRATION` | Token lifetime in minutes. Empty = non-expiring tokens. | `10080` |
| `ADMIN_NAME` | Name for the seeded admin account. | `DIDN Admin` |
| `ADMIN_EMAIL` | Email for the seeded admin account. | `admin@didn.org` |
| `ADMIN_PASSWORD` | Password for the seeded admin account (min 8 chars). | … |
| `SEED_DEMO_CONTENT` | Seed clearly-marked `[Demo]` events/posts (`db:seed`). | `true` |

> **CORS is intentionally not `*`.** List every real origin in the **PHP-style
> array** `ALLOWED_ORIGINS`:

```env
ALLOWED_ORIGINS=['http://localhost:3000','http://localhost:3001','https://www.directimpactnetwork.com','https://www.adminapp.directimpactnetwork.com']
```

---

## Database

Run migrations:

```bash
php artisan migrate
```

Tables created by the API:

- `users` – admin accounts (`name`, `email`, `job_title`, `phone`, `bio`)
- `personal_access_tokens` – Sanctum API tokens
- `media` – uploaded files metadata
- `events` – conference/campaign/training/webinar
- `posts` – blog articles

### Seeders

| Seeder | When | Notes |
|---|---|---|
| `AdminSeeder` | always (`db:seed`) | Creates/updates the admin from `ADMIN_*` env. It **syncs name + password every run**, so re-run it after changing these vars. Dev fallback: `admin@didn.org` / `didn-dev-password` (weak, local-only). In production `ADMIN_EMAIL` is required. |
| `DevContentSeeder` | non-production only | Inserts `[Demo]`-prefixed events/posts. Never shipped to production. |

```bash
php artisan db:seed --class=AdminSeeder
php artisan db:seed --class=DevContentSeeder   # dev only
```

---

## Storage / images

Uploads are stored on the **`public` disk** (`storage/app/public`) under a
dedicated **`backend/` folder** and served via the `/storage` symlink (create
with `php artisan storage:link`).

- Image files → `storage/app/public/backend/uploads/{Y}/{m}/<random>.jpg`
- The database only stores relative paths + metadata. **Internal paths are never exposed.**
- The API returns usable public URLs in every response (`image` on events/posts,
  `url` on media), e.g. `https://api.directimpactnetwork.com/storage/backend/uploads/2026/09/ab12cd34.jpg`

### Production / shared hosting (e.g. Hostinger)

Two mechanisms guarantee uploaded images keep working on shared hosts:

1. **Auto-healed symlink.** On every request the app re-creates
   `public/storage` as a *relative* link (`../storage/app/public`) when it is
   missing or broken. This fixes deployments (FTP/zip copies, git clones) that
   carry an absolute symlink pointing at another machine (like a dev laptop) or
   have no link at all.
2. **Built-in `/storage` route.** The `public` disk is configured with
   `serve => true`, so if the symlink can't exist at all, Laravel itself streams
   the file at `/storage/...`. (The scaffolded private `local` disk is no longer
   served at `/storage`, because that shadowed real uploads with a 403/404.)

Diagnose "images not uploading/showing" on the live site:

- Confirm the request actually succeeded: the upload response must be `201` and
  contain `data.url`. A `422` means validation/limits, a `500` usually means the
  `storage` directory isn't writable.
- Uploaded files live in **`storage/app/public/backend/uploads/{Y}/{m}`** — not
  `storage/backend`. If `public/storage` appears as a real folder after a bad
  FTP transfer, delete it and reload so the app can re-create the link.
- Set `APP_URL` in `.env` to the live HTTPS domain so generated URLs are correct.
- Matching DB access: Media writes both a file and a DB row, so a misconfigured
  `.env` (wrong DB) makes uploads appear to do nothing.

### Upload limits

PHP's runtime limits trump the API's 5MB rule — if `upload_max_filesize` is
smaller than your image, PHP rejects the file before validation and the API
returns `"The file failed to upload."` The current server limit is printed in
that error message.

Set these in `php.ini` (or a `.user.ini` next to `public/`) so real photos upload:

```ini
upload_max_filesize = 10M
post_max_size = 12M
max_file_uploads = 20
```

A template is included in this repo's `.user.ini` and applies automatically when
running under PHP-FPM/CGI (most shared hosts and production servers).

> `php artisan serve` uses your CLI `php.ini` and does **not** read `.user.ini`.
> For local testing of large images: `PHP_INI_SCAN_DIR=:/path/to/your/conf-dir php artisan serve`
> (where that dir holds a `99-uploads.ini` with the settings above), or raise
> `upload_max_filesize` in your CLI `php.ini` directly.

To move to cloud storage later, swap the disk config (e.g. S3) in
`config/filesystems.php` – no application code changes.

---

## Authentication flow

Token-based (Bearer). Not session/SPA.

```
POST /api/v1/auth/login
  { "email": "...", "password": "..." }

200 →
{
  "success": true,
  "message": "Logged in successfully.",
  "data": {
    "token": "1|abc123...",
    "token_type": "Bearer",
    "expires_at": null,
    "user": { "id": 1, "name": "...", "email": "..." }
  }
}
```

Use the token on every admin request:

```
Authorization: Bearer 1|abc123...
```

- `token` is returned **only once** on login.
- Logout revokes the current token: `POST /api/v1/auth/logout`
- Changing the password revokes **all** tokens for the account.
- Optionally expire tokens by setting `SANCTUM_EXPIRATION` (minutes).
- Login is throttled (5 attempts / minute).
- Passwords are hashed with Laravel's `hashed` cast (bcrypt). Password hashes are never returned.

---

## API overview

Prefix: `/api/v1`

### Auth

| Method | URI | Auth | Description |
|---|---|---|---|
| POST | `/auth/login` | – | Obtain a bearer token |
| POST | `/auth/logout` | ✓ | Revoke current token |
| GET | `/auth/me` | ✓ | Current admin |

### Admin profile & settings

| Method | URI | Auth | Description |
|---|---|---|---|
| GET | `/admin/profile` | ✓ | View own profile |
| PUT | `/admin/profile` | ✓ | Update name/email/job_title/phone/bio |
| PUT | `/admin/password` | ✓ | Change password (requires `current_password`) |

### Admin events

| Method | URI | Auth | Description |
|---|---|---|---|
| GET | `/admin/events` | ✓ | List all events (incl. unpublished) |
| POST | `/admin/events` | ✓ | Create event |
| GET | `/admin/events/{id}` | ✓ | Show event |
| PUT | `/admin/events/{id}` | ✓ | Update event |
| DELETE | `/admin/events/{id}` | ✓ | Delete event |

### Admin posts

| Method | URI | Auth | Description |
|---|---|---|---|
| GET | `/admin/posts` | ✓ | List posts (drafts + published) |
| POST | `/admin/posts` | ✓ | Create post |
| GET | `/admin/posts/{id}` | ✓ | Show post |
| PUT | `/admin/posts/{id}` | ✓ | Update post |
| DELETE | `/admin/posts/{id}` | ✓ | Delete post |

### Admin media

| Method | URI | Auth | Description |
|---|---|---|---|
| GET | `/admin/media` | ✓ | List uploaded media (paginated) |
| POST | `/admin/media` | ✓ | Upload an image |
| DELETE | `/admin/media/{id}` | ✓ | Delete media + detach from events/posts |

### Public (read-only)

| Method | URI | Description |
|---|---|---|
| GET | `/events` | Published events (filtered/paginated) |
| GET | `/events/{slug}` | Single published event |
| GET | `/posts` | Published posts (filtered/paginated) |
| GET | `/posts/{slug}` | Single published post |

Public endpoints never return drafts, unpublished content, or admin data.

---

## Response envelope

Every endpoint returns the same structure.

**Success**

```json
{
  "success": true,
  "message": "Event created successfully.",
  "data": { ... }
}
```

**List**

```json
{
  "success": true,
  "message": "Events fetched successfully.",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 4,
    "per_page": 12,
    "total": 42,
    "from": 1,
    "to": 12,
    "path": "http://localhost:8000/api/v1/events",
    "first_page_url": "...",
    "last_page_url": "...",
    "next_page_url": "...",
    "prev_page_url": null
  }
}
```

**Error** (validation, 422)

```json
{
  "success": false,
  "message": "The event type field is required.",
  "errors": {
    "event_type": ["The event type field is required."]
  }
}
```

Status codes: `200` success · `201` created · `401` unauthenticated/bad credentials · `404` not found · `422` validation.

---

## Events

`event_type` (required, enum): `conference`, `campaign`, `training`, `webinar`.

`status` on an event is **derived from dates**, never stored:

- `upcoming` – its effective end date (`end_date`, falling back to `start_date`) has not passed
- `past` – it has ended

Dates are stored UTC and compared in the application timezone. To publish/unpublish an
event use `is_published` (boolean).

**Create**

```
POST /api/v1/admin/events
Content-Type: application/json
Authorization: Bearer <token>
```

```json
{
  "title": "Annual Policy Conference",
  "event_type": "conference",
  "description": "Short description.",
  "content": "<p>Full rich text body…</p>",
  "location": "Abuja, FCT",
  "start_date": "2026-10-01T09:00:00Z",
  "end_date": "2026-10-02T18:00:00Z",
  "featured_image_id": 12,
  "is_published": true
}
```

`slug` is optional – it is auto-generated from `title` (and uniquified) when omitted.
`description` is required; `content` is **optional** — an event can be created
before the rich text body is finished. When `content` is null the response
includes `"has_content": false` so the frontend can show an empty state.
All other fields are validated (types, dates `end_date >= start_date`,
referenced media exists).

**Event response**

```json
{
  "id": 1,
  "title": "Annual Policy Conference",
  "slug": "annual-policy-conference",
  "description": "Short description.",
  "content": "<h2>Agenda</h2><p>…</p>",
  "has_content": true,
  "type": "conference",
  "location": "Abuja, FCT",
  "start_date": "2026-10-01T09:00:00.000000Z",
  "end_date": "2026-10-02T18:00:00.000000Z",
  "featured_image_id": 12,
  "image": "https://api.didn.org/storage/backend/uploads/2026/09/ab12cd34.jpg",
  "status": "upcoming",
  "is_published": true,
  "created_at": "2026-09-12T07:00:00.000000Z",
  "updated_at": "2026-09-12T07:00:00.000000Z"
}
```

**Public filters**

| Query | Example | Behaviour |
|---|---|---|
| `type` | `?type=training` | Filter by event type |
| `status` | `?status=upcoming` / `?status=past` | Filter by derived date status |
| `sort_by` | `?sort_by=title` | `start_date` (default), `end_date`, `created_at`, `title` |
| `sort_dir` | `?sort_dir=asc` | `asc` (default `/`), `desc` |
| `per_page` | `?per_page=24` | Page size (cap 100) |
| `page` | `?page=2` | Page number |

Default ordering is nearest/most-recent `start_date` first; `?status=upcoming`
sorts ascending (nearest date first).

---

## Blog / posts

**Status** (string enum): `draft`, `published`.

- `draft` – visible only to admins.
- `published` – visible publicly; `published_at` is set automatically on first
  publish and preserved when moving back to `draft`.

**Create**

```
POST /api/v1/admin/posts
Content-Type: application/json
Authorization: Bearer <token>
```

```json
{
  "title": "Leveraging Technology for Crime Prevention",
  "category": "Legal Services",
  "excerpt": "A short teaser.",
  "content": "<h2>Summary</h2><p>…</p>",
  "cover_image_id": 5,
  "status": "published"
}
```

The authenticated admin is automatically recorded as `author`.

`content` is **optional** — a draft can be started without the body and filled in
later. When `content` is null the response includes `"has_content": false` so the
admin can show an empty state.

**Post response**

```json
{
  "id": 1,
  "title": "Leveraging Technology for Crime Prevention",
  "slug": "leveraging-technology-for-crime-prevention",
  "category": "Legal Services",
  "excerpt": "A short teaser.",
  "content": "<h2>Summary</h2><p>…</p>",
  "has_content": true,
  "cover_image_id": 5,
  "image": "https://api.didn.org/storage/backend/uploads/2026/09/cover.jpg",
  "date": "September 12, 2026",
  "published_at": "2026-09-12T07:00:00.000000Z",
  "status": "published",
  "author": { "id": 1, "name": "DIDN Admin" },
  "created_at": "2026-09-12T07:00:00.000000Z",
  "updated_at": "2026-09-12T07:00:00.000000Z"
}
```

**Public filters**

| Query | Example | Behaviour |
|---|---|---|
| `category` | `?category=Legal%20Services` | Exact category match |
| `search` | `?search=climate` | Case-insensitive match on title, excerpt, content |
| `sort_by` | `?sort_by=title` | `published_at` (default), `created_at`, `title` |
| `sort_dir` | `?sort_dir=asc` | Sort direction |
| `per_page` | `?per_page=9` | Page size (cap 100) |
| `page` | `?page=2` | Page number |

---

## Rich text (Tiptap) content

**Expected content format:** the `content` field is an **HTML string** produced by
Tiptap's `editor.getHTML()` (not the JSON editor state).

The backend sanitises every value against a strict whitelist before storing, so:

- Upload blog/event body images through `POST /api/v1/admin/media` and embed the
  returned `url` in `<img src="...">`.
- Allowed tags/attributes (see `config/purifier.php` → `settings.tiptap`):

| Feature | Allowed output |
|---|---|
| Headings | `<h1>` … `<h6>` |
| Paragraphs | `<p>` |
| Bold / Italic / Strike / Underline | `<strong>`, `<em>`, `<s>`, `<u>` |
| Links | `<a href target rel>` (http/https/mailto/tel only) |
| Bullet / numbered lists | `<ul>`, `<ol>`, `<li>` |
| Blockquotes | `<blockquote>` |
| Images + captions | `<img src alt title>`, `<figure>`, `<figcaption>` |
| Horizontal rules | `<hr>`, `<br>` |
| Text alignment | `style="text-align: …"` on block-level tags |
| Code | `<pre>`, `<code>` |
| Undo / redo | handled client-side by Tiptap (no server state) |

Anything else (scripts, iframes, `data:` URIs, on-event attributes, active
`style` properties other than text-align, etc.) is **stripped or rejected**.

> The admin frontend should send `Content-Type: application/json` and pass the
> Tiptap HTML `"content"` as a plain string. The API returns the sanitised
> version; render it with `dangerouslySetInnerHTML` client-side only after
> backend sanitisation.

---

## Image uploads

```
POST /api/v1/admin/media
Content-Type: multipart/form-data
Authorization: Bearer <token>

field "file" → <binary image>
```

Validated: required image, `jpeg | png | webp | gif | avif`, max **5 MB**.

```json
{
  "success": true,
  "message": "Media uploaded successfully.",
  "data": {
    "id": 9,
    "url": "https://api.didn.org/storage/backend/uploads/2026/09/ab12cd34.jpg",
    "original_name": "cover.jpg",
    "mime_type": "image/jpeg",
    "size": 123456,
    "created_at": "...",
    "updated_at": "..."
  }
}
```

Use the returned `id` for `featured_image_id` / `cover_image_id` and the `url` in
event/post editor images. Deleting media nulls the reference on any event or post
that uses it.

---

## CORS

CORS is configured in `config/cors.php` and driven by:

```env
ALLOWED_ORIGINS=['http://localhost:3000','http://localhost:3001','https://www.directimpactnetwork.com','https://www.adminapp.directimpactnetwork.com']
```

A PHP-style array of exact origins. Adding or removing entries in `.env` controls
exactly which client origins may access the API.

The list applies globally to every `/api/*` request (see `paths` in
`config/cors.php`).
`allowed_methods` is `*`; credentials are not used (token auth).

---

## Testing

Tests run against a dedicated MySQL database `didn_test` (see `phpunit.xml`).
Create it once:

```bash
mysql -u root -e "CREATE DATABASE didn_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

Run the suite:

```bash
php artisan test
```

Coverage includes: authentication, profile/password, event CRUD + dates +
visibility, post CRUD + draft/publish behaviour + search/filtering, media upload
validation/URLs/deletion, public-vs-admin visibility, and consistent response
envelopes.

Format code:

```bash
vendor/bin/pint
```

---

## Project layout

```
app/
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── Auth/          login, logout, me
│   │   ├── Admin/         profile, password, events, posts, media (protected)
│   │   └── Public/        events, posts (read-only)
│   ├── Requests/          form request validation
│   └── Resources/         API resource shapes
├── Models/                User, Media, Event, Post
├── Services/              RichTextSanitizer, MediaService, SlugService
└── Traits/                ApiResponse, SortsEvents
routes/api.php             all /api/v1 routes
config/purifier.php        Tiptap HTML whitelist
config/cors.php            CORS origins (env-driven)
```

---

## Roadmap notes

The API is intentionally limited to AUTH, PROFILE, EVENTS, BLOG, MEDIA and the
public read endpoints. Donations, volunteers, newsletters, partners, projects,
and staff management are out of scope and were not added.