<?php
if(!defined('SECURE_ACCESS')) { header('HTTP/1.1 403 Forbidden'); exit; }
return json_decode('[
    {
        "id": "d7b427b3-cf01-4475-b82b-63a2cd7de385",
        "title": "Welcome Circular & Academic Calendar 2026-27",
        "content": "Welcome to the new academic session! Please download the attached PDF for the full exam schedule, holiday list, and visitor regulations.",
        "file_url": "\\/static\\/uploads\\/documents\\/notices\\/2026\\/08\\/dummy_academic_calendar.pdf",
        "file_type": "pdf",
        "file_size": 284,
        "is_active": true,
        "priority": "high",
        "created_by": "admin2",
        "created_at": "2026-08-28T12:00:00+00:00",
        "updated_at": "2026-08-28T12:00:00+00:00"
    }
]', true);
