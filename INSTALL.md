# Bhardwaj Gurukul Notices System - Installation Guide

## Overview

This is a complete PHP + Supabase solution for managing notices on the Bhardwaj Gurukul website. It includes:

- Public display of notices on the main website
- Admin dashboard with secure login
- Support for PDF, image, and text notices
- Supabase database backend

## Prerequisites

1. **Web Server** with PHP 7.4 or higher (PHP 8.0+ recommended)
2. **Supabase Account** and project
3. **File Upload Permissions** on the web server

## Installation Steps

### 1. Database Setup in Supabase

1. Create a new Supabase project or use an existing one
2. Run the SQL schema from `database_schema.sql` using Supabase SQL Editor
   - Copy and paste all SQL commands from the file
3. Note your Supabase credentials:
   - Project URL
   - Anon Key
   - Service Role Key (for admin operations)

### 2. Configuration

Edit the following files with your Supabase credentials:

#### `api/config.php`
```php
define('SUPABASE_URL', 'https://YOUR-PROJECT-ID.supabase.co');
define('SUPABASE_KEY', 'YOUR_SUPABASE_ANON_KEY');
define('SUPABASE_SERVICE_KEY', 'YOUR_SUPABASE_SERVICE_ROLE_KEY');
```

### 3. File Upload Setup

1. Create a directory for uploads (if needed):
   ```bash
   mkdir -p dashboard/uploads
   chmod 755 dashboard/uploads
   ```

2. Make sure your web server has write permissions to this directory.

### 4. Initialize Super Admin

After setting up the database, you need to set the super admin password:

1. Connect to your Supabase SQL Editor
2. Find the `admins` table
3. You should see one record with username `superadmin`
4. Use the following PHP script to generate a password hash and update the admin:

```php
<?php
echo password_hash('your_secure_password', PASSWORD_DEFAULT);
```

Replace the hash in the database with this value.

### 5. Web Server Configuration

Add the following to your `.htaccess` file (if using Apache):

```apache
# Handle API requests
RewriteEngine On
RewriteRule ^api/(.*)$ api/$1 [L,QSA]

# Protect dashboard
AuthType Basic
AuthName "Admin Dashboard"
Require all denied
AuthUserFile /dev/null
ErrorDocument 403 "Access Denied"
```

Or for nginx:

```nginx
# API routes
location /api/ {
    try_files $uri =404;
}

# Protect dashboard
location /dashboard/ {
    deny all;
}
```

### 6. Set Up HTTPS (Recommended)

For secure authentication, configure your web server to use HTTPS.

## Using the System

### Admin Dashboard

1. Navigate to `/dashboard/login.php`
2. Use the super admin credentials you configured
3. Login to:
   - View notices
   - Add new notices (text or PDF uploads)
   - Edit existing notices
   - Delete notices
   - Toggle active/inactive status

### Public Notices

1. The notices are displayed on the main website in the "Notice Board" section
2. Visitors can view:
   - Notice titles
   - Notice content (if text)
   - Download files (PDF, images)
   - Priority indicators

### API Endpoints

All API endpoints are prefixed with `/api/`:

- `GET /api/notices.php?action=list` - Get all notices
- `GET /api/notices.php?action=get&id=X` - Get specific notice
- `POST /api/notices.php?action=create` - Create new notice
- `POST /api/notices.php?action&X` - Update notice
- `POST /api/notices.php?action=delete&id=X` - Delete notice
- `POST /api/notices.php?action=stats` - Get notice statistics
- `POST /api/auth.php?action=login` - Admin login
- `POST /api/auth.php?action=logout` - Admin logout

## Security Features

- Session management with timeout
- CSRF protection
- Rate limiting on login attempts
- File type validation
- SQL injection prevention
- XSS protection
- Secure password hashing

## File Storage

- Files are stored in Supabase Storage
- Maximum file size: 50MB
- Allowed types: PDF, JPG, PNG, DOC, DOCX, TXT

## Customization

### Adding Custom Icons

Modify the `getFileIcon()` function in `index.html` for custom icons.

### Changing Colors

The theme uses Bhardwaj Gurukul colors:
- Saffron: `#f59e0b` (primary)
- Leaf Green: `#22c55e` (secondary)
- Crimson: `#ef4444` (accent)
- Paper: `#fffbf2` (background)

Edit the Tailwind configuration in `index.html` to change colors.

### Translation System

The system includes English and Hindi translations. Add new translations by:
1. Adding entries to the `i18n` object in `index.html`
2. Adding entries to PHP files for admin interface

## Troubleshooting

### File Upload Issues
- Check directory permissions
- Verify PHP upload_max_filesize (should be at least 50M)
- Check Supabase storage bucket permissions

### Database Connection Issues
- Verify Supabase URL and keys
- Check database table exists
- Ensure Row Level Security (RLS) policies are enabled

### Login Issues
- Check super admin password hash
- Verify session configuration
- Clear browser cache and cookies

## Support

For support or customizations, please contact:
- Email: bhardwajgurukulbgs@gmail.com
- Phone: 9934220425

## License

© 2026 Bhardwaj Gurukul. All rights reserved.