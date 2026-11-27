<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_post();
verify_csrf();

$_SESSION = [];
session_destroy();
redirect('login.xhtml?pesan=logout');
