<?php
if(!defined('SECURE_ACCESS')) { header('HTTP/1.1 403 Forbidden'); exit; }
return json_decode('[
    {
        "admin_id": "11111111-1111-1111-1111-111111111111",
        "session_token": "89608222023a687d60f8defd7a3dca841913bbd44fcd3d28bf4a36f789033713",
        "expires_at": "2026-08-30T04:28:39+00:00",
        "ip_address": "::1",
        "user_agent": "unknown",
        "id": "94cb6173-20d4-4a97-a920-701da7875e61",
        "created_at": "2026-08-29T04:28:39+00:00",
        "updated_at": "2026-08-29T04:28:39+00:00"
    },
    {
        "admin_id": "11111111-1111-1111-1111-111111111111",
        "session_token": "0fcfe7143e1b749b2d680c3df789891d87ea49bf8f6611147a4befe5f8464310",
        "expires_at": "2026-08-30T04:32:17+00:00",
        "ip_address": "::1",
        "user_agent": "unknown",
        "id": "7e09b048-9fe6-45c9-8872-cf5f474a3332",
        "created_at": "2026-08-29T04:32:17+00:00",
        "updated_at": "2026-08-29T04:32:17+00:00"
    },
    {
        "admin_id": "11111111-1111-1111-1111-111111111111",
        "session_token": "599d39baa1eb3624bc8055573ed853842de01f2dcaacaf88def84c6d0d03a6fa",
        "expires_at": "2026-08-30T04:34:42+00:00",
        "ip_address": "::1",
        "user_agent": "Mozilla\\/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit\\/605.1.15 (KHTML, like Gecko) Version\\/18.5 Mobile\\/15E148 Safari\\/604.1",
        "id": "e5c7fb9a-3700-4316-82bf-18073d9c9b65",
        "created_at": "2026-08-29T04:34:42+00:00",
        "updated_at": "2026-08-29T04:34:42+00:00"
    }
]', true);
