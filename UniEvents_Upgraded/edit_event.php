<?php
require_once __DIR__ . '/partials.php';

require_role('organizer');

$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : (int) ($_POST['event_id'] ?? 0);

if ($eventId === 0) {
    set_flash_message('No event was selected.', 'error');
    redirect_to('view_events.php');
}

$eventStmt = $conn->prepare('SELECT * FROM events WHERE event_id = ? AND customer_id = ?');
$customerId = current_customer_id();
$eventStmt->bind_param('ii', $eventId, $customerId);
$eventStmt->execute();
$event = $eventStmt->get_result()->fetch_assoc();

if (!$event) {
    set_flash_message('You can only edit events that you created.', 'error');
    redirect_to('view_events.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('edit_event.php?event_id=' . $eventId);

    $eventName = normalize_text($_POST['event_name'] ?? '');
    $eventTime = $_POST['event_time'] ?? '';
    $budget = (float) ($_POST['budget'] ?? 0);
    $venueId = (int) ($_POST['venue_id'] ?? 0);
    $staffIds = isset($_POST['staff_ids']) ? array_values(array_unique(array_map('intval', $_POST['staff_ids']))) : [];
    $supplierIds = isset($_POST['supplier_ids']) ? array_values(array_unique(array_map('intval', $_POST['supplier_ids']))) : [];

    if ($eventName === '' || $eventTime === '' || $venueId === 0) {
        set_flash_message('Please fill in all required fields.', 'error');
        redirect_to('edit_event.php?event_id=' . $eventId);
    }

    if (!is_future_datetime($eventTime)) {
        set_flash_message('Please choose a future date and time for the event.', 'error');
        redirect_to('edit_event.php?event_id=' . $eventId);
    }

    if ($budget <= 0) {
        set_flash_message('Please enter a valid event budget.', 'error');
        redirect_to('edit_event.php?event_id=' . $eventId);
    }

    $conn->begin_transaction();

    try {
        $updateStmt = $conn->prepare('UPDATE events SET venue_id = ?, event_name = ?, event_time = ?, budget = ? WHERE event_id = ? AND customer_id = ?');
        $updateStmt->bind_param('issdii', $venueId, $eventName, $eventTime, $budget, $eventId, $customerId);
        $updateStmt->execute();

        $deleteStaffStmt = $conn->prepare('DELETE FROM event_staff WHERE event_id = ?');
        $deleteStaffStmt->bind_param('i', $eventId);
        $deleteStaffStmt->execute();

        $deleteSupplierStmt = $conn->prepare('DELETE FROM event_suppliers WHERE event_id = ?');
        $deleteSupplierStmt->bind_param('i', $eventId);
        $deleteSupplierStmt->execute();

        if (!empty($staffIds)) {
            $insertStaffStmt = $conn->prepare('INSERT INTO event_staff (event_id, staff_id) VALUES (?, ?)');
            foreach ($staffIds as $staffId) {
                $insertStaffStmt->bind_param('ii', $eventId, $staffId);
                $insertStaffStmt->execute();
            }
        }

        if (!empty($supplierIds)) {
            $insertSupplierStmt = $conn->prepare('INSERT INTO event_suppliers (event_id, supplier_id) VALUES (?, ?)');
            foreach ($supplierIds as $supplierId) {
                $insertSupplierStmt->bind_param('ii', $eventId, $supplierId);
                $insertSupplierStmt->execute();
            }
        }

        $conn->commit();
        set_flash_message('Event updated successfully.');
        redirect_to('view_events.php');
    } catch (Throwable $exception) {
        $conn->rollback();
        set_flash_message('Unable to update the event.', 'error');
        redirect_to('edit_event.php?event_id=' . $eventId);
    }
}

$venues = $conn->query('SELECT venue_id, venue_name, capacity FROM venues ORDER BY venue_name ASC');
$staffMembers = $conn->query('SELECT staff_id, name, role FROM staff ORDER BY name ASC');
$suppliers = $conn->query('SELECT supplier_id, name, service_type FROM suppliers ORDER BY name ASC');

$selectedStaffIds = [];
$selectedSuppliers = [];

$assignedStaff = $conn->prepare('SELECT staff_id FROM event_staff WHERE event_id = ?');
$assignedStaff->bind_param('i', $eventId);
$assignedStaff->execute();
$assignedStaffResult = $assignedStaff->get_result();
while ($row = $assignedStaffResult->fetch_assoc()) {
    $selectedStaffIds[] = (int) $row['staff_id'];
}

$assignedSuppliers = $conn->prepare('SELECT supplier_id FROM event_suppliers WHERE event_id = ?');
$assignedSuppliers->bind_param('i', $eventId);
$assignedSuppliers->execute();
$assignedSupplierResult = $assignedSuppliers->get_result();
while ($row = $assignedSupplierResult->fetch_assoc()) {
    $selectedSuppliers[] = (int) $row['supplier_id'];
}

render_header('Edit Event', 'view_events.php');
?>
<section class="hero">
    <div class="pill-row">
        <span class="badge">Edit</span>
        <span class="badge warning">Organizer Only</span>
    </div>
    <h1>Edit Event</h1>
    <p>Update the event details and reassign staff or suppliers as needed.</p>
</section>

<section class="panel">
    <form method="POST" class="stack">
        <input type="hidden" name="event_id" value="<?php echo h((string) $eventId); ?>">
        <?php echo csrf_input(); ?>

        <div class="form-grid">
            <div class="input-group">
                <label for="event_name">Event Name</label>
                <input id="event_name" type="text" name="event_name" value="<?php echo h($event['event_name']); ?>" required>
            </div>
            <div class="input-group">
                <label for="event_time">Date and Time</label>
                <input
                    id="event_time"
                    type="datetime-local"
                    name="event_time"
                    value="<?php echo h(date('Y-m-d\TH:i', strtotime($event['event_time']))); ?>"
                    required
                >
            </div>
        </div>

        <div class="form-grid">
            <div class="input-group">
                <label for="budget">Budget</label>
                <input id="budget" type="number" name="budget" min="0" step="0.01" value="<?php echo h((string) $event['budget']); ?>" required>
            </div>
            <div class="input-group">
                <label for="venue_id">Venue</label>
                <select id="venue_id" name="venue_id" required>
                    <?php while ($venue = $venues->fetch_assoc()): ?>
                        <option
                            value="<?php echo h((string) $venue['venue_id']); ?>"
                            <?php echo (int) $venue['venue_id'] === (int) $event['venue_id'] ? 'selected' : ''; ?>
                        >
                            <?php echo h($venue['venue_name'] . ' (Capacity: ' . $venue['capacity'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="resource-columns">
            <div class="panel">
                <h3>Assigned Staff</h3>
                <div class="checkbox-grid">
                    <?php while ($staff = $staffMembers->fetch_assoc()): ?>
                        <label>
                            <input
                                type="checkbox"
                                name="staff_ids[]"
                                value="<?php echo h((string) $staff['staff_id']); ?>"
                                <?php echo in_array((int) $staff['staff_id'], $selectedStaffIds, true) ? 'checked' : ''; ?>
                            >
                            <?php echo h($staff['name'] . ' - ' . $staff['role']); ?>
                        </label>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="panel">
                <h3>Assigned Suppliers</h3>
                <div class="checkbox-grid">
                    <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                        <label>
                            <input
                                type="checkbox"
                                name="supplier_ids[]"
                                value="<?php echo h((string) $supplier['supplier_id']); ?>"
                                <?php echo in_array((int) $supplier['supplier_id'], $selectedSuppliers, true) ? 'checked' : ''; ?>
                            >
                            <?php echo h($supplier['name'] . ' - ' . $supplier['service_type']); ?>
                        </label>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="action-row">
            <button type="submit" class="btn btn-secondary">Save Changes</button>
            <a class="btn btn-light" href="view_events.php">Back</a>
        </div>
    </form>
</section>
<?php render_footer(); ?>
