<?php
require_once __DIR__ . '/partials.php';

require_role('organizer');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('manage_resources.php');
    $resourceType = $_POST['resource_type'] ?? '';
    $action       = $_POST['action'] ?? 'create';

    if ($resourceType === 'venue' && $action === 'create') {
        $venueName = normalize_text($_POST['venue_name'] ?? '');
        $address   = normalize_text($_POST['address']    ?? '');
        $capacity  = (int) ($_POST['capacity'] ?? 0);
        if ($venueName === '' || $address === '' || $capacity <= 0) {
            set_flash_message('Please provide a venue name, address, and valid capacity.', 'error');
            redirect_to('manage_resources.php');
        }
        try {
            $s = $conn->prepare('INSERT INTO venues (venue_name, address, capacity) VALUES (?, ?, ?)');
            $s->bind_param('ssi', $venueName, $address, $capacity);
            $s->execute();
            set_flash_message('Venue added successfully.');
        } catch (Throwable $e) { set_flash_message('Unable to add venue.', 'error'); }
        redirect_to('manage_resources.php');
    }

    if ($resourceType === 'staff' && $action === 'create') {
        $name  = normalize_text($_POST['name']      ?? '');
        $role  = normalize_text($_POST['role_name'] ?? '');
        $phone = normalize_text($_POST['phone']     ?? '');
        if ($name === '' || $role === '' || $phone === '') {
            set_flash_message('Please complete every staff field.', 'error');
            redirect_to('manage_resources.php');
        }
        try {
            $s = $conn->prepare('INSERT INTO staff (name, role, phone) VALUES (?, ?, ?)');
            $s->bind_param('sss', $name, $role, $phone);
            $s->execute();
            set_flash_message('Staff member added.');
        } catch (Throwable $e) { set_flash_message('Unable to add staff.', 'error'); }
        redirect_to('manage_resources.php');
    }

    if ($resourceType === 'supplier' && $action === 'create') {
        $name        = normalize_text($_POST['name']         ?? '');
        $phone       = normalize_text($_POST['phone']        ?? '');
        $serviceType = normalize_text($_POST['service_type'] ?? '');
        if ($name === '' || $phone === '' || $serviceType === '') {
            set_flash_message('Please complete every supplier field.', 'error');
            redirect_to('manage_resources.php');
        }
        try {
            $s = $conn->prepare('INSERT INTO suppliers (name, phone, service_type) VALUES (?, ?, ?)');
            $s->bind_param('sss', $name, $phone, $serviceType);
            $s->execute();
            set_flash_message('Supplier added.');
        } catch (Throwable $e) { set_flash_message('Unable to add supplier.', 'error'); }
        redirect_to('manage_resources.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0 || !in_array($resourceType, ['venue', 'staff', 'supplier'], true)) {
            set_flash_message('Please choose a valid resource.', 'error');
            redirect_to('manage_resources.php');
        }

        if ($resourceType === 'venue') {
            $usageStmt = $conn->prepare('SELECT COUNT(*) AS total FROM events WHERE venue_id = ?');
            $usageStmt->bind_param('i', $id);
            $usageStmt->execute();
            $usageTotal = (int) $usageStmt->get_result()->fetch_assoc()['total'];

            if ($usageTotal > 0) {
                set_flash_message('This venue is assigned to existing events and cannot be deleted.', 'error');
                redirect_to('manage_resources.php');
            }
        } elseif ($resourceType === 'staff') {
            $usageStmt = $conn->prepare('SELECT COUNT(*) AS total FROM event_staff WHERE staff_id = ?');
            $usageStmt->bind_param('i', $id);
            $usageStmt->execute();
            $usageTotal = (int) $usageStmt->get_result()->fetch_assoc()['total'];

            if ($usageTotal > 0) {
                set_flash_message('This staff member is assigned to an event and cannot be deleted.', 'error');
                redirect_to('manage_resources.php');
            }
        } else {
            $usageStmt = $conn->prepare('SELECT COUNT(*) AS total FROM event_suppliers WHERE supplier_id = ?');
            $usageStmt->bind_param('i', $id);
            $usageStmt->execute();
            $usageTotal = (int) $usageStmt->get_result()->fetch_assoc()['total'];

            if ($usageTotal > 0) {
                set_flash_message('This supplier is linked to an event and cannot be deleted.', 'error');
                redirect_to('manage_resources.php');
            }
        }

        if ($resourceType === 'venue') {
            $s = $conn->prepare('DELETE FROM venues WHERE venue_id = ?');
        } elseif ($resourceType === 'staff') {
            $s = $conn->prepare('DELETE FROM staff WHERE staff_id = ?');
        } else {
            $s = $conn->prepare('DELETE FROM suppliers WHERE supplier_id = ?');
        }
        $s->bind_param('i', $id);
        try {
            $s->execute();
            set_flash_message('Resource deleted.');
        } catch (Throwable $e) {
            set_flash_message('Cannot delete this resource because it is still linked to an event.', 'error');
        }
        redirect_to('manage_resources.php');
    }
}

$venues       = $conn->query('SELECT * FROM venues   ORDER BY venue_name ASC');
$staffMembers = $conn->query('SELECT * FROM staff    ORDER BY name ASC');
$suppliers    = $conn->query('SELECT * FROM suppliers ORDER BY name ASC');

render_header('Resources', 'manage_resources.php');
?>

<section class="hero">
    <div class="pill-row">
        <span class="badge">Resources</span>
        <span class="badge warning">Organizer Only</span>
    </div>
    <h1>Manage Resources</h1>
    <p>Add and remove venues, staff members, and suppliers. These can then be assigned when creating events.</p>
    <div class="action-row">
        <a class="btn btn-gold" href="add_event.php">Create Event</a>
        <a class="btn btn-light" href="index.php">Dashboard</a>
    </div>
</section>

<!-- ADD FORMS -->
<div class="section-grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr));margin-bottom:32px;">

    <div class="panel">
        <h2 style="margin-bottom:16px;">Add Venue</h2>
        <form method="POST" class="stack">
            <input type="hidden" name="resource_type" value="venue">
            <input type="hidden" name="action"        value="create">
            <?php echo csrf_input(); ?>
            <div class="input-group">
                <label for="venue_name">Venue Name</label>
                <input id="venue_name" type="text" name="venue_name" placeholder="Enter venue name" required>
            </div>
            <div class="input-group">
                <label for="address">Address</label>
                <input id="address" type="text" name="address" placeholder="Enter venue address" required>
            </div>
            <div class="input-group">
                <label for="capacity">Capacity</label>
                <input id="capacity" type="number" name="capacity" min="1" placeholder="Enter seating capacity" required>
            </div>
            <button type="submit" class="btn btn-secondary">Add Venue</button>
        </form>
    </div>

    <div class="panel">
        <h2 style="margin-bottom:16px;">Add Staff</h2>
        <form method="POST" class="stack">
            <input type="hidden" name="resource_type" value="staff">
            <input type="hidden" name="action"        value="create">
            <?php echo csrf_input(); ?>
            <div class="input-group">
                <label for="staff_name">Full Name</label>
                <input id="staff_name" type="text" name="name" placeholder="Enter full name" required>
            </div>
            <div class="input-group">
                <label for="role_name">Role / Position</label>
                <input id="role_name" type="text" name="role_name" placeholder="Enter role or position" required>
            </div>
            <div class="input-group">
                <label for="staff_phone">Phone</label>
                <input id="staff_phone" type="text" name="phone" placeholder="Enter phone number" required>
            </div>
            <button type="submit" class="btn btn-secondary">Add Staff</button>
        </form>
    </div>

    <div class="panel">
        <h2 style="margin-bottom:16px;">Add Supplier</h2>
        <form method="POST" class="stack">
            <input type="hidden" name="resource_type" value="supplier">
            <input type="hidden" name="action"        value="create">
            <?php echo csrf_input(); ?>
            <div class="input-group">
                <label for="supplier_name">Company Name</label>
                <input id="supplier_name" type="text" name="name" placeholder="Enter company name" required>
            </div>
            <div class="input-group">
                <label for="supplier_phone">Phone</label>
                <input id="supplier_phone" type="text" name="phone" placeholder="Enter phone number" required>
            </div>
            <div class="input-group">
                <label for="service_type">Service Type</label>
                <input id="service_type" type="text" name="service_type" placeholder="Enter service type" required>
            </div>
            <button type="submit" class="btn btn-secondary">Add Supplier</button>
        </form>
    </div>
</div>

<!-- EXISTING RECORDS -->
<div class="resource-records">

    <!-- Venues table -->
    <div class="table-wrap resource-card">
        <div style="padding:16px 18px 0;display:flex;justify-content:space-between;align-items:center;">
            <h3>Venues <span style="font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:400;color:var(--text-soft);">(<?php echo $venues->num_rows; ?>)</span></h3>
        </div>
        <table class="resource-table">
            <thead><tr><th>Name</th><th>Address</th><th>Capacity</th><th></th></tr></thead>
            <tbody>
            <?php if ($venues->num_rows > 0): while ($v = $venues->fetch_assoc()): ?>
            <tr>
                <td><strong><?php echo h($v['venue_name']); ?></strong></td>
                <td style="font-size:0.85rem;color:var(--text-muted);"><?php echo h($v['address']); ?></td>
                <td><?php echo h((string)$v['capacity']); ?></td>
                <td>
                    <form method="POST" class="inline-form" onsubmit="return confirm('Delete this venue?');">
                        <input type="hidden" name="resource_type" value="venue">
                        <input type="hidden" name="action" value="delete">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="id"     value="<?php echo h((string)$v['venue_id']); ?>">
                        <button type="submit" class="btn btn-danger" style="padding:5px 12px;font-size:0.82rem;">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="4" style="text-align:center;color:var(--text-soft);padding:24px;">No venues yet</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Staff table -->
    <div class="table-wrap resource-card">
        <div style="padding:16px 18px 0;">
            <h3>Staff <span style="font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:400;color:var(--text-soft);">(<?php echo $staffMembers->num_rows; ?>)</span></h3>
        </div>
        <table class="resource-table">
            <thead><tr><th>Name</th><th>Role</th><th>Phone</th><th></th></tr></thead>
            <tbody>
            <?php if ($staffMembers->num_rows > 0): while ($s = $staffMembers->fetch_assoc()): ?>
            <tr>
                <td><strong><?php echo h($s['name']); ?></strong></td>
                <td style="font-size:0.85rem;color:var(--text-muted);"><?php echo h($s['role']); ?></td>
                <td style="font-size:0.85rem;"><?php echo h($s['phone']); ?></td>
                <td>
                    <form method="POST" class="inline-form" onsubmit="return confirm('Delete this staff member?');">
                        <input type="hidden" name="resource_type" value="staff">
                        <input type="hidden" name="action" value="delete">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="id"     value="<?php echo h((string)$s['staff_id']); ?>">
                        <button type="submit" class="btn btn-danger" style="padding:5px 12px;font-size:0.82rem;">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="4" style="text-align:center;color:var(--text-soft);padding:24px;">No staff yet</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Suppliers table -->
    <div class="table-wrap resource-card">
        <div style="padding:16px 18px 0;">
            <h3>Suppliers <span style="font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:400;color:var(--text-soft);">(<?php echo $suppliers->num_rows; ?>)</span></h3>
        </div>
        <table class="resource-table">
            <thead><tr><th>Company</th><th>Service</th><th>Phone</th><th></th></tr></thead>
            <tbody>
            <?php if ($suppliers->num_rows > 0): while ($sp = $suppliers->fetch_assoc()): ?>
            <tr>
                <td><strong><?php echo h($sp['name']); ?></strong></td>
                <td style="font-size:0.85rem;color:var(--text-muted);"><?php echo h($sp['service_type']); ?></td>
                <td style="font-size:0.85rem;"><?php echo h($sp['phone']); ?></td>
                <td>
                    <form method="POST" class="inline-form" onsubmit="return confirm('Delete this supplier?');">
                        <input type="hidden" name="resource_type" value="supplier">
                        <input type="hidden" name="action" value="delete">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="id"     value="<?php echo h((string)$sp['supplier_id']); ?>">
                        <button type="submit" class="btn btn-danger" style="padding:5px 12px;font-size:0.82rem;">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="4" style="text-align:center;color:var(--text-soft);padding:24px;">No suppliers yet</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php render_footer(); ?>
