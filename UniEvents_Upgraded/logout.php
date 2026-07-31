<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('login.php');
}

require_valid_csrf('login.php');

$_SESSION = [];
session_regenerate_id(true);
regenerate_csrf_token();
set_flash_message('You have been logged out.');
redirect_to('login.php');
