<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secureCookie,
    ]);
    session_start();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Dhaka');

function app_name()
{
    return 'Event Management';
}

function app_full_name()
{
    return 'University Event Management System';
}

function connect_database()
{
    $database = getenv('DB_NAME') ?: 'university_event_management';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = (int) (getenv('DB_PORT') ?: 3306);

    $socketCandidates = array_filter([
        getenv('DB_SOCKET') ?: null,
        '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock',
        '/tmp/mysql.sock',
        '/opt/homebrew/var/mysql/mysql.sock',
        '/usr/local/var/mysql/mysql.sock',
    ]);

    $attempts = [];

    foreach ($socketCandidates as $socketPath) {
        if (!file_exists($socketPath)) {
            continue;
        }

        $attempts[] = [
            'host' => 'localhost',
            'port' => 0,
            'socket' => $socketPath,
            'label' => 'socket:' . $socketPath,
        ];
    }

    $attempts[] = [
        'host' => $host,
        'port' => $port,
        'socket' => null,
        'label' => $host . ':' . $port,
    ];

    $lastError = 'Unknown database connection error.';

    foreach ($attempts as $attempt) {
        try {
            $mysqli = new mysqli(
                $attempt['host'],
                $username,
                $password,
                $database,
                $attempt['port'],
                $attempt['socket']
            );

            return $mysqli;
        } catch (mysqli_sql_exception $exception) {
            $lastError = $exception->getMessage() . ' [' . $attempt['label'] . ']';
        }
    }

    error_log('Database connection failed: ' . $lastError);
    http_response_code(500);
    exit('Database connection failed. Please make sure MySQL is running and try again.');
}

$conn = connect_database();
$conn->set_charset('utf8mb4');

function clear_mysqli_results($connection)
{
    if (!($connection instanceof mysqli)) {
        return;
    }

    while ($connection->more_results()) {
        $connection->next_result();
        $result = $connection->store_result();
        if ($result instanceof mysqli_result) {
            $result->free();
        }
    }
}

function redirect_to($path)
{
    header('Location: ' . $path);
    exit;
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function normalize_text($value)
{
    return trim(preg_replace('/\s+/', ' ', (string) $value));
}

function is_valid_email_address($value)
{
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

function is_future_datetime($value)
{
    $timestamp = strtotime((string) $value);

    return $timestamp !== false && $timestamp > time();
}

function ensure_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function regenerate_csrf_token()
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    return $_SESSION['csrf_token'];
}

function csrf_token()
{
    return ensure_csrf_token();
}

function csrf_input()
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function is_valid_csrf_request()
{
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');

    return $submittedToken !== '' && $sessionToken !== '' && hash_equals($sessionToken, $submittedToken);
}

function require_valid_csrf($redirectPath = 'index.php')
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (!is_valid_csrf_request()) {
        set_flash_message('Your session expired. Please try again.', 'error');
        redirect_to($redirectPath);
    }
}

function is_logged_in()
{
    return isset($_SESSION['customer_id']);
}

function current_customer_id()
{
    return isset($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : 0;
}

function current_role()
{
    return $_SESSION['role'] ?? '';
}

function require_login()
{
    if (!is_logged_in()) {
        redirect_to('login.php');
    }
}

function require_role($role)
{
    require_login();

    if (current_role() !== $role) {
        set_flash_message('You are not allowed to access that page.', 'error');
        redirect_to('index.php');
    }
}

function set_flash_message($message, $type = 'success')
{
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type,
    ];
}

function pull_flash_message()
{
    if (!isset($_SESSION['flash_message'])) {
        return null;
    }

    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);

    return $flash;
}

function format_datetime($value)
{
    $timestamp = strtotime((string) $value);

    if ($timestamp === false) {
        return (string) $value;
    }

    return date('M d, Y h:i A', $timestamp);
}

function format_currency($value)
{
    return 'BDT ' . number_format((float) $value, 2);
}
