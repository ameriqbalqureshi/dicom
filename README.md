# DICOM Viewer

A web application for uploading, viewing, sharing and reporting on DICOM medical imaging studies, built with Laravel 12 and Livewire 4. It is designed around cardiology referrals: a cath lab technician uploads an angiogram, shares it with a cardiologist, and the cardiologist forwards it to a surgeon, writes a signed report, or sends a guest link, all in the browser with nothing to install.

## Features

### Browser DICOM viewer
- Pure JavaScript decoder (no plugins), with uncompressed (implicit/explicit VR, little and big endian) and JPEG Lossless transfer syntaxes
- Multi-frame cine playback with adjustable frame rate. Playback keeps running when you switch series
- Window/level by dragging, invert, and reset
- Magnifier that stays clear of the finger on touch screens
- Caliper measurements in millimetres, from Pixel Spacing or calibrated against a known length
- Series and frame navigation, full screen, and keyboard shortcuts:

  | Key | Action | Key | Action |
  | --- | --- | --- | --- |
  | `Space` | Play / pause | `←` / `→` | Previous / next image |
  | `Home` / `End` | First / last image | `Page Up` / `Page Down` | Previous / next series |
  | `C` | Caliper | `M` | Magnifier |
  | `I` | Invert | `R` | Reset window/level |
  | `F` | Full screen | `Delete` / `Esc` | Delete caliper / cancel calibration |

### Uploading and storage
- Upload single files, a whole DICOM folder or CD (detected by content; `DICOMDIR` is skipped), or a `.zip`
- Zips are stored whole and the viewer reads each image from them by HTTP range request
- Files are organised by uploader, study date, study and series
- Local disk or Amazon S3. With S3, browsers upload and download directly through short-lived signed URLs

### Roles
| Role | Can |
| --- | --- |
| **Administrator** | Manage users, packages, payments, payment accounts and marketing officers; verify physicians; view and delete any study |
| **Technician** | Upload studies and share them with physicians |
| **Physician** | Receive, forward and report on studies; create guest links; keep private patient records; control who may send them studies |
| **Patient** | Upload one study at a time (deleted automatically after 7 days) and share it with their doctors |
| **Marketing Officer** | Refer clients with a referral code and earn commission on their payments |

### Sharing
- Studies are shared only with verified physicians, who can forward them on. Revoking a share also removes access for everyone it was forwarded to
- A sender's first share to a physician waits on the physician's **Requests** page. The physician can then allow, suspend or block that sender
- **Guest links** last 24 hours and can require a 6-digit PIN. A guest can view the images and final reports without an account
- **Handover:** when an owner deletes a shared study, or a patient's study expires, physicians who have access get a few days to keep it before it is deleted
- Study comments, with PDF export

### Reports
- Any physician with access can write reports. A report is a draft until it is finalised, after which it is locked and only addenda can be added
- Signed PDF output with the physician's qualifications, registration number and signature image

### Patient records
- Private clinical records for physicians: notes, linked studies, and PDF export. Clinical fields are encrypted at rest

### Packages and payments
- Self sign-up as Physician, Technician or Patient, starting on a free trial
- Packages are defined per role by an admin, with a duration and limits on studies stored, storage, reports, shares and guest links
- Manual payment by bank transfer or mobile wallet (accounts managed by an admin). Users submit a reference or receipt, an admin confirms it, and a PDF receipt is issued. A public form accepts receipts without logging in
- Email reminders before a package ends

### Marketing officers
- Public application form, approved by an admin
- Referral codes, commission rates (editable by admins) for first payments and renewals, a holding period, manual bonuses, payouts and PDF statements

### Public website
- Home, pricing, about, contact, privacy and terms pages, with SEO metadata and `sitemap.xml`

## Requirements

- PHP 8.2+ with the usual Laravel extensions, plus `ext-gd` for images in PDFs
- Composer
- Node.js 20+ and npm
- SQLite (development) or MySQL/MariaDB (production)
- Optional: an S3 bucket for media storage

## Installation

```bash
git clone https://github.com/ameriqbalqureshi/dicom.git
cd dicom

composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate --seed

npm run build
```

Start everything for development (web server, queue worker and Vite):

```bash
composer run dev
```

Then open http://localhost:8000.

### Demo accounts

`php artisan migrate --seed` creates one account per role, each with the password `password`:

| Email | Role |
| --- | --- |
| `admin@example.com` | Administrator |
| `physician@example.com` | Physician |
| `technician@example.com` | Technician |
| `patient@example.com` | Patient |
| `marketing@example.com` | Marketing Officer |

It also creates example packages and a payment account.

## Usage

1. **Upload:** sign in as a technician and open **Upload**. Drop in `.dcm` files, a DICOM folder or a `.zip`.
2. **View:** open the study to use the viewer. Pick a series from the strip, press `Space` to play, and drag to adjust window/level.
3. **Share:** from the study page, share with a physician. Sign in as the physician and accept the share on **Requests**.
4. **Report:** as the physician, write a report, finalise it, and download the signed PDF. Add qualifications and a signature under **Profile** first.
5. **Guest link:** create a link with a PIN and send it to someone without an account.
6. **Packages:** as an admin, manage packages under **Admin → Packages** and confirm payments under **Admin → Payments**.

## Configuration

Key `.env` settings beyond the Laravel defaults:

| Variable | Purpose |
| --- | --- |
| `MEDIA_DISK` | `local` or `s3` for DICOM files, signatures and receipts |
| `AWS_*` | S3 credentials and bucket. The bucket needs a CORS rule that allows `GET`, `PUT` and `HEAD` from the app's origin |
| `STUDY_HANDOVER_DAYS` | Days physicians have to keep a study that has been given up (default 7) |
| `BILLING_CURRENCY` | Currency shown on prices and receipts (default `PKR`) |
| `SITE_CONTACT_*` | Contact details shown on the public website |
| `MAIL_*` | SMTP settings. All emails are queued notifications |

> **Note:** patient record fields are encrypted with `APP_KEY`. Back the key up, because changing it makes existing records unreadable.

## Scheduled tasks

Add one cron entry on the server:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

| Command | Schedule | Purpose |
| --- | --- | --- |
| `queue:work --stop-when-empty` | Every minute | Sends queued emails (no long-running worker is needed on shared hosting) |
| `studies:delete-expired` | Hourly | Hands over or deletes expired and given-up studies |
| `uploads:prune-incoming` | Hourly | Removes direct-to-S3 uploads that were never finished |
| `packages:send-expiry-reminders` | Daily 09:00 | Emails users whose package ends within 3 days |
| `model:prune` | Daily | Prunes expired records such as guest links |

Other Artisan commands:

| Command | Purpose |
| --- | --- |
| `users:create-admin` | Create a verified administrator on a new server |
| `media:copy-to-s3` | Copy locally stored media to the S3 bucket |
| `dicom:reorganize` | Move stored files into the uploader / date / study / series layout |

## Testing

```bash
php artisan test --compact
```

The suite uses Pest and covers sharing rules, quotas, reports, billing, handover, guest links, uploads and the marketing commission flow.

## Tech stack

- [Laravel 12](https://laravel.com) and [Livewire 4](https://livewire.laravel.com), with Alpine.js for the viewer
- Tailwind CSS and Vite
- [barryvdh/laravel-dompdf](https://github.com/barryvdh/laravel-dompdf) for PDFs
- League Flysystem S3 adapter for media storage
- Pest for tests

## Disclaimer

This software is not a certified medical device. It is not intended for primary diagnosis unless your jurisdiction's regulations permit it. You are responsible for meeting the patient-data protection requirements that apply to your deployment.
