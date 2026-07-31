<?php
require_once __DIR__ . '/partials.php';

require_role('organizer');

$venues       = $conn->query('SELECT venue_id, venue_name, capacity FROM venues ORDER BY venue_name ASC');
$staffMembers = $conn->query('SELECT staff_id, name, role FROM staff ORDER BY name ASC');
$suppliers    = $conn->query('SELECT supplier_id, name, service_type FROM suppliers ORDER BY name ASC');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('add_event.php');

    $eventName   = normalize_text($_POST['event_name'] ?? '');
    $eventTime   = $_POST['event_time'] ?? '';
    $budget      = (float) ($_POST['budget'] ?? 0);
    $venueId     = (int)   ($_POST['venue_id'] ?? 0);
    $staffIds    = isset($_POST['staff_ids']) ? array_values(array_unique(array_map('intval', $_POST['staff_ids']))) : [];
    $supplierIds = isset($_POST['supplier_ids']) ? array_values(array_unique(array_map('intval', $_POST['supplier_ids']))) : [];

    if ($eventName === '' || $eventTime === '' || $venueId === 0) {
        set_flash_message('Please fill in all required fields.', 'error');
        redirect_to('add_event.php');
    }

    if (!is_future_datetime($eventTime)) {
        set_flash_message('Please choose a future date and time for the event.', 'error');
        redirect_to('add_event.php');
    }

    if ($budget <= 0) {
        set_flash_message('Please enter a valid event budget.', 'error');
        redirect_to('add_event.php');
    }

    $conn->begin_transaction();
    try {
        $cid = current_customer_id();
        $evStmt = $conn->prepare('INSERT INTO events (customer_id, venue_id, event_name, event_time, budget) VALUES (?, ?, ?, ?, ?)');
        $evStmt->bind_param('iissd', $cid, $venueId, $eventName, $eventTime, $budget);
        $evStmt->execute();
        $eventId = (int) $conn->insert_id;

        if (!empty($staffIds)) {
            $stStmt = $conn->prepare('INSERT INTO event_staff (event_id, staff_id) VALUES (?, ?)');
            foreach ($staffIds as $sid) {
                $stStmt->bind_param('ii', $eventId, $sid);
                $stStmt->execute();
            }
        }
        if (!empty($supplierIds)) {
            $spStmt = $conn->prepare('INSERT INTO event_suppliers (event_id, supplier_id) VALUES (?, ?)');
            foreach ($supplierIds as $spid) {
                $spStmt->bind_param('ii', $eventId, $spid);
                $spStmt->execute();
            }
        }

        $conn->commit();
        set_flash_message('Event created successfully.');
        redirect_to('view_events.php');
    } catch (Throwable $e) {
        $conn->rollback();
        set_flash_message('Unable to create the event. Please try again.', 'error');
        redirect_to('add_event.php');
    }
}

render_header('Add Event', 'add_event.php');
?>

<section class="hero">
    <div class="pill-row">
        <span class="badge">Create</span>
        <span class="badge warning">Organizer Only</span>
    </div>
    <h1>Create a New Event</h1>
    <p>Fill in the event details and assign staff and suppliers from your resource pool.</p>
</section>

<?php if ($venues->num_rows === 0): ?>
<div class="empty-state">
    <div class="empty-icon"></div>
    <h3>No venues available</h3>
    <p>Add at least one venue before creating an event.</p>
    <a class="btn btn-gold" href="manage_resources.php">Add a Venue</a>
</div>

<?php else: ?>
<div class="panel">
    <form method="POST" class="stack">
        <?php echo csrf_input(); ?>

        <div class="form-grid">
            <div class="input-group">
                <label for="event_name">Event Name <span style="color:var(--danger)">*</span></label>
                <input id="event_name" type="text" name="event_name"
                       placeholder="e.g. Annual Research Expo" required>
            </div>
            <div class="input-group">
                <label for="event_time">Date &amp; Time <span style="color:var(--danger)">*</span></label>
                <input id="event_time" type="datetime-local" name="event_time" required>
            </div>
        </div>

        <div class="form-grid">
            <div class="input-group">
                <label for="budget">Budget (BDT) <span style="color:var(--danger)">*</span></label>
                <input id="budget" type="number" name="budget" min="0" step="0.01"
                       placeholder="e.g. 12000.00" required>
            </div>
            <div class="input-group">
                <label for="venue_id">Venue <span style="color:var(--danger)">*</span></label>
                <select id="venue_id" name="venue_id" required>
                    <option value="">Select a venue</option>
                    <?php while ($v = $venues->fetch_assoc()): ?>
                    <option value="<?php echo h((string)$v['venue_id']); ?>">
                        <?php echo h($v['venue_name'] . ' (Capacity: ' . $v['capacity'] . ')'); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="resource-columns">
            <div class="panel" style="border:1.5px solid var(--border);box-shadow:none;">
                <h3 style="margin-bottom:6px;">Assign Staff</h3>
                <p class="small-text" style="margin-bottom:14px;">Select staff members to work at this event.</p>
                <div class="checkbox-grid">
                    <?php if ($staffMembers->num_rows > 0):
                        while ($s = $staffMembers->fetch_assoc()): ?>
                        <label>
                            <input type="checkbox" name="staff_ids[]"
                                   value="<?php echo h((string)$s['staff_id']); ?>">
                            <?php echo h($s['name']); ?> - <em style="color:var(--text-muted)"><?php echo h($s['role']); ?></em>
                        </label>
                    <?php endwhile; else: ?>
                        <p class="small-text">No staff records yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel" style="border:1.5px solid var(--border);box-shadow:none;">
                <h3 style="margin-bottom:6px;">Assign Suppliers</h3>
                <p class="small-text" style="margin-bottom:14px;">Link service providers to this event.</p>
                <div class="checkbox-grid">
                    <?php if ($suppliers->num_rows > 0):
                        while ($sp = $suppliers->fetch_assoc()): ?>
                        <label>
                            <input type="checkbox" name="supplier_ids[]"
                                   value="<?php echo h((string)$sp['supplier_id']); ?>">
                            <?php echo h($sp['name']); ?> - <em style="color:var(--text-muted)"><?php echo h($sp['service_type']); ?></em>
                        </label>
                    <?php endwhile; else: ?>
                        <p class="small-text">No supplier records yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="action-row">
            <button type="submit" class="btn btn-gold" style="padding:13px 32px;">Create Event</button>
            <a class="btn btn-light" href="view_events.php">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php render_footer(); ?>
