<?php
if(!defined('SECURE_ACCESS')) { header('HTTP/1.1 403 Forbidden'); exit; }
return json_decode('[]', true);
