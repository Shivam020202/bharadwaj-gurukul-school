<?php
if(!defined('SECURE_ACCESS')) { header('HTTP/1.1 403 Forbidden'); exit; }
return json_decode('[
    {
        "id": "11111111-1111-1111-1111-111111111111",
        "username": "admin2",
        "email": "admin@bhardwajgurukul.com",
        "full_name": "Super Administrator",
        "password_hash": "$2y$10$e0bFQprp.0PP7FC0V3iQk.FG9RPwBhJjgqtSWea9t.GH8sH3x.bCK",
        "is_super_admin": true,
        "is_active": true,
        "created_at": "2026-08-28T14:57:23+00:00",
        "updated_at": "2026-08-28T14:57:23+00:00"
    }
]', true);
