<?php
require_once __DIR__ . '/partials.php';

if (is_logged_in()) {
    redirect_to('index.php');
}

$loginError = '';
$registerError = '';
$registerSuccess = '';
$activePane = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!is_valid_csrf_request()) {
        if ($action === 'register') {
            $activePane = 'register';
            $registerError = 'Your session expired. Please refresh the page and try again.';
        } else {
            $activePane = 'login';
            $loginError = 'Your session expired. Please refresh the page and try again.';
        }
    } elseif ($action === 'login') {
        $activePane = 'login';
        $email = normalize_text($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if (!is_valid_email_address($email) || $password === '') {
            $loginError = 'Enter a valid email address and password.';
        } else {
            $stmt = $conn->prepare('SELECT customer_id, name, email, password, role FROM customers WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $customer = $stmt->get_result()->fetch_assoc();

            if ($customer && password_verify($password, $customer['password'])) {
                session_regenerate_id(true);
                regenerate_csrf_token();

                $_SESSION['customer_id'] = (int) $customer['customer_id'];
                $_SESSION['customer_name'] = $customer['name'];
                $_SESSION['customer_email'] = $customer['email'];
                $_SESSION['role'] = $customer['role'];

                set_flash_message('Welcome back, ' . $customer['name'] . '!');
                redirect_to('index.php');
            }

            $loginError = 'Invalid email or password. Please try again.';
        }
    } elseif ($action === 'register') {
        $activePane = 'register';
        $name = normalize_text($_POST['name'] ?? '');
        $phone = normalize_text($_POST['phone'] ?? '');
        $email = normalize_text($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $role = $_POST['role'] ?? 'customer';

        if (!in_array($role, ['customer', 'organizer'], true)) {
            $role = 'customer';
        }

        if ($name === '' || $phone === '') {
            $registerError = 'Please complete your name and phone number.';
        } elseif (!is_valid_email_address($email)) {
            $registerError = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $registerError = 'Password must be at least 6 characters long.';
        } else {
            $checkStmt = $conn->prepare('SELECT customer_id FROM customers WHERE email = ?');
            $checkStmt->bind_param('s', $email);
            $checkStmt->execute();

            if ($checkStmt->get_result()->num_rows > 0) {
                $registerError = 'That email address is already registered.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $ins = $conn->prepare('INSERT INTO customers (name, phone, email, password, role) VALUES (?, ?, ?, ?, ?)');
                $ins->bind_param('sssss', $name, $phone, $email, $hashed, $role);

                if ($ins->execute()) {
                    regenerate_csrf_token();
                    $registerSuccess = 'Registration complete! You can sign in now.';
                    $activePane = 'login';
                } else {
                    $registerError = 'Registration failed. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - <?php echo h(app_name()); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
<div class="auth-shell">

    <!-- ── LEFT: Decorative Panel ── -->
    <div class="auth-left">
        <div class="auth-left-grid"></div>

        <!-- Brand -->
        <div class="auth-brand">
            <div class="auth-brand-icon" aria-hidden="true"></div>
            <span class="auth-brand-lockup">
                <span class="auth-brand-title">Campus Event</span>
                <span class="auth-brand-subtitle">Management System</span>
            </span>
        </div>

        <!-- Main hero text -->
        <div class="auth-hero-text">
            <h2>Plan every<br><em>campus event</em><br>with confidence.</h2>
            <p>A polished academic event portal for organizing seminars, festivals, workshops, and student programs in one place.</p>
        </div>

        <!-- Feature bullets -->
        <div class="auth-features">
            <div class="auth-feature">
                <div class="auth-feature-dot"></div>
                <span>Browse and reserve upcoming university events</span>
            </div>
            <div class="auth-feature">
                <div class="auth-feature-dot" style="background:var(--jade-light)"></div>
                <span>Organizers can create structured event plans</span>
            </div>
            <div class="auth-feature">
                <div class="auth-feature-dot" style="background:#a78bfa"></div>
                <span>Track venues, staff teams, and suppliers</span>
            </div>
            <div class="auth-feature">
                <div class="auth-feature-dot" style="background:#60a5fa"></div>
                <span>Present live metrics with a project-ready dashboard</span>
            </div>
        </div>
    </div>

    <!-- ── RIGHT: Form Panel ── -->
    <div class="auth-right">
        <div class="auth-card">
            <p class="auth-card-title"><?php echo $activePane === 'register' ? 'Create account' : 'Welcome back'; ?></p>
            <p class="auth-card-sub"><?php echo $activePane === 'register' ? 'Create your Event Management profile and choose the right campus role.' : 'Sign in to continue managing and booking university events.'; ?></p>

            <!-- Toggle -->
            <div class="auth-toggle">
                <button type="button" id="showLogin" class="<?php echo $activePane === 'login' ? 'active-tab' : ''; ?>">Sign In</button>
                <button type="button" id="showRegister" class="<?php echo $activePane === 'register' ? 'active-tab' : ''; ?>">Create Account</button>
            </div>

            <!-- LOGIN PANE -->
            <div id="loginPane" class="auth-pane <?php echo $activePane === 'login' ? 'active' : ''; ?>">
                <?php if ($loginError !== ''): ?>
                    <div class="flash flash-error"><?php echo h($loginError); ?></div>
                <?php endif; ?>
                <?php if ($registerSuccess !== ''): ?>
                    <div class="flash flash-success"><?php echo h($registerSuccess); ?></div>
                <?php endif; ?>

                <form method="POST" class="stack">
                    <input type="hidden" name="action" value="login">
                    <?php echo csrf_input(); ?>
                    <div class="input-group">
                        <label for="login_email">Email Address</label>
                        <input id="login_email" type="email" name="email" placeholder="Enter a valid email address" required>
                    </div>
                    <div class="input-group">
                        <label for="login_password">Password</label>
                        <input id="login_password" type="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="btn btn-gold" style="width:100%;padding:14px;font-size:0.95rem;">Sign In</button>
                </form>

                <p style="text-align:center;margin-top:20px;font-size:0.84rem;color:var(--text-soft);">
                    Don't have an account?
                    <a href="#" id="goRegister" style="color:var(--amber);font-weight:600;">Create one</a>
                </p>
            </div>

            <!-- REGISTER PANE -->
            <div id="registerPane" class="auth-pane <?php echo $activePane === 'register' ? 'active' : ''; ?>">
                <?php if ($registerError !== ''): ?>
                    <div class="flash flash-error"><?php echo h($registerError); ?></div>
                <?php endif; ?>

                <form method="POST" class="stack">
                    <input type="hidden" name="action" value="register">
                    <?php echo csrf_input(); ?>
                    <div class="form-grid">
                        <div class="input-group">
                            <label for="register_name">Full Name</label>
                            <input id="register_name" type="text" name="name" placeholder="Your full name" required>
                        </div>
                        <div class="input-group">
                            <label for="register_phone">Phone Number</label>
                            <input id="register_phone" type="text" name="phone" placeholder="+8801XXXXXXXXX" required>
                        </div>
                    </div>
                    <div class="input-group">
                        <label for="register_email">Email Address</label>
                        <input id="register_email" type="email" name="email" placeholder="Enter a valid email address" required>
                    </div>
                    <div class="input-group">
                        <label for="register_role">Account Role</label>
                        <select id="register_role" name="role">
                            <option value="customer">Customer - Browse and book events</option>
                            <option value="organizer">Organizer - Create and manage events</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="register_password">Password</label>
                        <input id="register_password" type="password" name="password" placeholder="Create a strong password" required>
                    </div>
                    <button type="submit" class="btn btn-secondary" style="width:100%;padding:14px;font-size:0.95rem;">Create Account</button>
                </form>

                <p style="text-align:center;margin-top:20px;font-size:0.84rem;color:var(--text-soft);">
                    Already have an account?
                    <a href="#" id="goLogin" style="color:var(--amber);font-weight:600;">Sign in</a>
                </p>
            </div>

        </div>
    </div>
</div>

<script>
const showLogin    = document.getElementById('showLogin');
const showRegister = document.getElementById('showRegister');
const goRegister   = document.getElementById('goRegister');
const goLogin      = document.getElementById('goLogin');
const loginPane    = document.getElementById('loginPane');
const registerPane = document.getElementById('registerPane');

function switchTo(target) {
    const isLogin = target === 'login';
    loginPane.classList.toggle('active', isLogin);
    registerPane.classList.toggle('active', !isLogin);
    showLogin.classList.toggle('active-tab', isLogin);
    showRegister.classList.toggle('active-tab', !isLogin);

    // Update title text
    const title = document.querySelector('.auth-card-title');
    const sub   = document.querySelector('.auth-card-sub');
    title.textContent = isLogin ? 'Welcome back' : 'Create account';
    sub.textContent   = isLogin
        ? 'Sign in to continue managing and booking university events.'
        : 'Create your Event Management profile and choose the right campus role.';
}

showLogin.addEventListener('click', () => switchTo('login'));
showRegister.addEventListener('click', () => switchTo('register'));
if (goRegister) goRegister.addEventListener('click', (e) => { e.preventDefault(); switchTo('register'); });
if (goLogin)    goLogin.addEventListener('click',    (e) => { e.preventDefault(); switchTo('login'); });

switchTo('<?php echo $activePane === 'register' ? 'register' : 'login'; ?>');
</script>
</body>
</html>
