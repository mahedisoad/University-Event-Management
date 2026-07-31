<?php
require_once __DIR__ . '/config.php';

require_role('organizer');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('view_events.php');

    $eventId = (int) ($_POST['event_id'] ?? 0);
    $customerId = current_customer_id();

    $checkStmt = $conn->prepare('SELECT event_id FROM events WHERE event_id = ? AND customer_id = ?');
    $checkStmt->bind_param('ii', $eventId, $customerId);
    $checkStmt->execute();
    $eventExists = $checkStmt->get_result()->fetch_assoc();

    if (!$eventExists) {
        set_flash_message('That event could not be found.', 'error');
        redirect_to('view_events.php');
    }

    $conn->begin_transaction();

    try {
        $deleteStaffStmt = $conn->prepare('DELETE FROM event_staff WHERE event_id = ?');
        $deleteStaffStmt->bind_param('i', $eventId);
        $deleteStaffStmt->execute();

        $deleteSupplierStmt = $conn->prepare('DELETE FROM event_suppliers WHERE event_id = ?');
        $deleteSupplierStmt->bind_param('i', $eventId);
        $deleteSupplierStmt->execute();

        $deleteBookingsStmt = $conn->prepare('DELETE FROM bookings WHERE event_id = ?');
        $deleteBookingsStmt->bind_param('i', $eventId);
        $deleteBookingsStmt->execute();

        $deleteEventStmt = $conn->prepare('DELETE FROM events WHERE event_id = ? AND customer_id = ?');
        $deleteEventStmt->bind_param('ii', $eventId, $customerId);
        $deleteEventStmt->execute();

        $conn->commit();
        set_flash_message('Event deleted successfully.');
    } catch (Throwable $exception) {
        $conn->rollback();
        set_flash_message('Unable to delete the event.', 'error');
    }
}

redirect_to('view_events.php');
