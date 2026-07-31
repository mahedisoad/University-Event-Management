<?php
require_once __DIR__ . '/partials.php';

require_login();

$customerId = current_customer_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('account.php');

    $name = normalize_text($_POST['name'] ?? '');
    $phone = normalize_text($_POST['phone'] ?? '');
    $email = normalize_text($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '' || $phone === '') {
        set_flash_message('Name and phone number are required.', 'error');
        redirect_to('account.php');
    }

    if (!is_valid_email_address($email)) {
        set_flash_message('Please enter a valid email address.', 'error');
        redirect_to('account.php');
    }

    if ($password !== '' && strlen($password) < 6) {
        set_flash_message('New password must be at least 6 characters long.', 'error');
        redirect_to('account.php');
    }

    $chk = $conn->prepare('SELECT customer_id FROM customers WHERE email = ? AND customer_id != ?');
    $chk->bind_param('si', $email, $customerId);
    $chk->execute();

    if ($chk->get_result()->fetch_assoc()) {
        set_flash_message('That email is already used by another account.', 'error');
        redirect_to('account.php');
    }

    if ($password !== '') {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $upd = $conn->prepare('UPDATE customers SET name=?, phone=?, email=?, password=? WHERE customer_id=?');
        $upd->bind_param('ssssi', $name, $phone, $email, $hashed, $customerId);
    } else {
        $upd = $conn->prepare('UPDATE customers SET name=?, phone=?, email=? WHERE customer_id=?');
        $upd->bind_param('sssi', $name, $phone, $email, $customerId);
    }

    $upd->execute();
    $_SESSION['customer_name']  = $name;
    $_SESSION['customer_email'] = $email;

    set_flash_message('Profile updated successfully.');
    redirect_to('account.php');
}

$profileStmt = $conn->prepare('SELECT * FROM customers WHERE customer_id = ?');
$profileStmt->bind_param('i', $customerId);
$profileStmt->execute();
$customer = $profileStmt->get_result()->fetch_assoc();

// Booking count for stats
$bcStmt = $conn->prepare('SELECT COUNT(*) AS total FROM bookings WHERE customer_id = ?');
$bcStmt->bind_param('i', $customerId);
$bcStmt->execute();
$bookingCount = (int) $bcStmt->get_result()->fetch_assoc()['total'];

render_header('Account', 'account.php');
?>

<section class="hero">
    <div class="pill-row">
        <span class="badge">Account</span>
        <span class="badge <?php echo $customer['role'] === 'organizer' ? 'warning' : 'success'; ?>">
            <?php echo h(ucfirst($customer['role'])); ?>
        </span>
    </div>
    <h1><?php echo h($customer['name']); ?></h1>
    <p><?php echo h($customer['email']); ?> &nbsp;|&nbsp; <?php echo h($customer['phone']); ?></p>
</section>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:24px;">

    <!-- Profile form -->
    <div class="panel">
        <h2 style="margin-bottom:20px;">Edit Profile</h2>
        <form method="POST" class="stack">
            <?php echo csrf_input(); ?>
            <div class="input-group">
                <label for="name">Full Name</label>
                <input id="name" type="text" name="name" value="<?php echo h($customer['name']); ?>" required>
            </div>
            <div class="input-group">
                <label for="phone">Phone Number</label>
                <input id="phone" type="text" name="phone" value="<?php echo h($customer['phone']); ?>" required>
            </div>
            <div class="input-group">
                <label for="email">Email Address</label>
                <input id="email" type="email" name="email" value="<?php echo h($customer['email']); ?>" required>
            </div>
            <div class="input-group">
                <label for="role_display">Role</label>
                <input id="role_display" type="text" value="<?php echo h(ucfirst($customer['role'])); ?>" disabled
                       style="background:#f0ebe0;color:var(--text-muted);">
            </div>
            <div class="input-group">
                <label for="password">New Password</label>
                <input id="password" type="password" name="password"
                       placeholder="Leave blank to keep current password">
            </div>
            <div class="action-row">
                <button type="submit" class="btn btn-gold">Save Changes</button>
                <a class="btn btn-light" href="index.php">Back to Dashboard</a>
            </div>
        </form>
    </div>

    <!-- Account summary -->
    <div style="display:flex;flex-direction:column;gap:16px;">
        <div class="stat-card">
            <h3>Account Role</h3>
            <p class="small-text">Your permission level in the system</p>
            <div class="stat-number <?php echo $customer['role'] === 'organizer' ? 'gold' : 'teal'; ?>">
                <?php echo h(ucfirst($customer['role'])); ?>
            </div>
        </div>

        <?php if ($customer['role'] === 'customer'): ?>
        <div class="stat-card">
            <h3>Your Bookings</h3>
            <p class="small-text">Total events registered</p>
            <div class="stat-number teal"><?php echo $bookingCount; ?></div>
        </div>
        <div class="panel" style="padding:20px;">
            <h3 style="margin-bottom:10px;">Quick Links</h3>
            <div class="action-row" style="flex-direction:column;align-items:stretch;">
                <a class="btn btn-secondary" href="view_events.php">Browse Events</a>
                <a class="btn btn-light"     href="bookings.php">My Bookings</a>
                <a class="btn btn-light"     href="search.php">Search Events</a>
            </div>
        </div>
        <?php else: ?>
        <div class="panel" style="padding:20px;">
            <h3 style="margin-bottom:10px;">Organizer Tools</h3>
            <div class="action-row" style="flex-direction:column;align-items:stretch;">
                <a class="btn btn-gold"       href="add_event.php">Create Event</a>
                <a class="btn btn-secondary"  href="manage_resources.php">Manage Resources</a>
                <a class="btn btn-light"      href="bookings.php">View Attendees</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>
