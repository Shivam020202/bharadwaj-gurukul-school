# Bhardwaj Gurukul Notices System - Installation Guide

## Overview

This is a complete, self-contained PHP notices system for the Bhardwaj Gurukul website. It allows administrators to upload circulars, PDF documents, images, or publish text notices without requiring any external databases or cloud systems (fully self-hosted and flat-file based).

- Public display of notices on the main website
- Admin dashboard with secure login
- Support for PDF, image, and text notices
- Local secure file-based storage for notices, admin accounts, and sessions
- No external database (Supabase/MySQL/PostgreSQL) required!

## Installation Steps

### 1. File Upload Setup

Make sure your web server has write permissions to the following directories:
- `static/uploads/` (for public document uploads)
- `api/data/` (for local JSON data files)

Permissions should typically be set to `755` or `775` depending on your server configuration:
```bash
chmod 755 static/uploads api/data
```

### 2. Security Configuration

Security policies are pre-configured out-of-the-box:
- `/static/uploads/.htaccess` disables PHP and CGI execution to prevent Remote Code Execution (RCE) via uploaded files.
- `/api/data/.htaccess` denies all web requests to prevent downloading database files directly.
- Session cookies are configured with HttpOnly and SameSite flags for security.

### 3. Administrator Credentials

A default admin account is automatically initialized when the system runs for the first time:
- **Username**: `admin2`
- **Password**: `Admin@2026`

You can change this password or add other admin users directly from the Admin Dashboard. Password changes will update the local data securely.

### 4. Running the System

1. Upload all files to your web server.
2. Ensure PHP 7.4 or higher (PHP 8.0+ recommended) is installed.
3. Access `/dashboard/login.php` in your browser.
4. Sign in with the default credentials (`admin2` / `Admin@2026`).

## API Endpoints

All endpoints are hosted locally under `/api/`:

- `GET /api/notices.php?action=list` - Get active notices (Public: returns JSON array; Admin: returns wrapped JSON with all drafts)
- `GET /api/notices.php?action=get&id=X` - Get a specific notice details
- `POST /api/notices.php?action=create` - Publish new notice (with or without files)
- `POST /api/notices.php?action=update&id=X` - Update notice details
- `POST /api/notices.php?action=delete&id=X` - Delete notice and its associated files
- `GET /api/notices.php?action=stats` - Count of total, active, and PDF notices
- `POST /api/auth.php?action=login` - Admin login
- `POST /api/auth.php?action=logout` - Admin logout

## Troubleshooting

### File Upload Issues
- Verify that your PHP configuration allows file uploads: `file_uploads = On` in `php.ini`.
- Verify the PHP post size and file size limits in `php.ini` match or exceed 50M:
  ```ini
  upload_max_filesize = 50M
  post_max_size = 50M
  ```
- Ensure the server has write permissions for `static/uploads/`.