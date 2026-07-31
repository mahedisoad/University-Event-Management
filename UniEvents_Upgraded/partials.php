<?php
require_once __DIR__ . '/config.php';

function nav_link_html($href, $label, $active)
{
    $activeClass = $active === $href ? 'active' : '';
    return '<a class="' . $activeClass . '" href="' . h($href) . '">' . h($label) . '</a>';
}

function render_header($title, $active = '')
{
    $flash    = pull_flash_message();
    $loggedIn = is_logged_in();
    $dashboardLink = $loggedIn ? 'index.php' : 'login.php';
    $pageName = $active !== '' ? pathinfo($active, PATHINFO_FILENAME) : 'generic';
    $bodyClass = 'app-page page-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($pageName));

    // Get initials for avatar
    $initials = '';
    if ($loggedIn && isset($_SESSION['customer_name'])) {
        $parts = explode(' ', trim($_SESSION['customer_name']));
        $initials = strtoupper(substr($parts[0], 0, 1));
        if (isset($parts[1])) $initials .= strtoupper(substr($parts[1], 0, 1));
    }

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '    <meta charset="UTF-8">';
    echo '    <meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '    <title>' . h($title) . ' - ' . h(app_name()) . '</title>';
    echo '    <link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">';
    echo '    <link rel="stylesheet" href="assets/style.css">';
    echo '</head>';
    echo '<body class="' . h($bodyClass) . '">';
    echo '    <header class="navbar">';
    echo '        <a class="brand" href="' . h($dashboardLink) . '">';
    echo '            <div class="brand-icon" aria-hidden="true"></div>';
    echo '            <span class="brand-lockup">';
    echo '                <span class="brand-title">Campus Event</span>';
    echo '                <span class="brand-subtitle">Management System</span>';
    echo '            </span>';
    echo '        </a>';
    echo '        <div class="nav-group">';

    if ($loggedIn) {
        echo '            <nav class="nav-links">';
        echo nav_link_html('index.php', 'Home', $active);
        echo nav_link_html('view_events.php', 'Events', $active);
        echo nav_link_html('bookings.php', 'Bookings', $active);
        echo nav_link_html('search.php', 'Search', $active);

        if (current_role() === 'organizer') {
            echo nav_link_html('add_event.php', 'Add Event', $active);
            echo nav_link_html('manage_resources.php', 'Resources', $active);
        }

        echo nav_link_html('account.php', 'Account', $active);
        echo '            </nav>';
        echo '            <div class="user-meta">';
        echo '                <div class="user-pill">';
        echo '                    <div class="user-avatar">' . h($initials) . '</div>';
        echo '                    ' . h($_SESSION['customer_name']) . ' &nbsp;<strong>' . h(ucfirst(current_role())) . '</strong>';
        echo '                </div>';
        echo '                <form method="POST" action="logout.php" class="inline-form">';
        echo csrf_input();
        echo '                    <button type="submit" class="btn btn-danger" style="padding:8px 16px;font-size:0.82rem;">Logout</button>';
        echo '                </form>';
        echo '            </div>';
    } else {
        echo '            <nav class="nav-links">';
        echo nav_link_html('login.php', 'Login', $active);
        echo '            </nav>';
    }

    echo '        </div>';
    echo '    </header>';
    echo '    <main class="page-shell">';

    if ($flash !== null) {
        echo '        <div class="flash flash-' . h($flash['type']) . '">' . h($flash['message']) . '</div>';
    }
}

function render_footer()
{
    echo '    </main>';
    echo '    <footer style="position:relative;z-index:1;text-align:center;padding:24px;font-size:0.78rem;color:var(--text-soft);border-top:1px solid var(--border);background:var(--parchment-2);">';
    echo '        ' . h(app_full_name()) . ' &mdash; DBMS Project &copy; ' . date('Y');
    echo '    </footer>';
    echo '</body>';
    echo '</html>';
}
