<?php
require_once __DIR__ . '/partials.php';

require_login();

function booking_database_message($message, $fallback)
{
    $knownMessages = [
        'That event is no longer available.' => 'That event is no longer available.',
        'You can only book upcoming events.' => 'You can only book upcoming events.',
        'You have already booked this event.' => 'You have already booked this event.',
        'This event has reached full capacity.' => 'This event has reached full capacity.',
        'That booking could not be found.' => 'That booking could not be found.',
        'Past events can no longer be cancelled.' => 'Past events can no longer be cancelled.',
        'Duplicate entry' => 'You have already booked this event.',
    ];

    foreach ($knownMessages as $needle => $friendlyMessage) {
        if (stripos((string) $message, $needle) !== false) {
            return $friendlyMessage;
        }
    }

    return $fallback;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('bookings.php');
    $action = $_POST['action'] ?? '';

    if ($action === 'create_booking' && current_role() === 'customer') {
        $eventId    = (int) ($_POST['event_id'] ?? 0);
        $customerId = current_customer_id();

        if ($eventId <= 0) {
            set_flash_message('Please choose a valid event.', 'error');
            redirect_to('bookings.php');
        }

        try {
            $bookingProcedure = $conn->prepare('CALL sp_create_booking(?, ?)');
            $bookingProcedure->bind_param('ii', $eventId, $customerId);
            $bookingProcedure->execute();
            $bookingProcedure->close();
            clear_mysqli_results($conn);
            set_flash_message('Booking confirmed. See you at the event.');
        } catch (Throwable $e) {
            clear_mysqli_results($conn);
            set_flash_message(booking_database_message($e->getMessage(), 'Unable to complete your booking.'), 'error');
        }

        redirect_to('bookings.php');
    }

    if ($action === 'cancel_booking' && current_role() === 'customer') {
        $bookingId  = (int) ($_POST['booking_id'] ?? 0);
        $customerId = current_customer_id();

        if ($bookingId <= 0) {
            set_flash_message('That booking could not be found.', 'error');
            redirect_to('bookings.php');
        }

        try {
            $cancelProcedure = $conn->prepare('CALL sp_cancel_booking(?, ?)');
            $cancelProcedure->bind_param('ii', $bookingId, $customerId);
            $cancelProcedure->execute();
            $cancelProcedure->close();
            clear_mysqli_results($conn);
            set_flash_message('Booking cancelled successfully.');
        } catch (Throwable $e) {
            clear_mysqli_results($conn);
            set_flash_message(booking_database_message($e->getMessage(), 'Unable to cancel that booking.'), 'error');
        }

        redirect_to('bookings.php');
    }
}

render_header('Bookings', 'bookings.php');
?>

<section class="hero">
    <div class="pill-row">
        <span class="badge">Bookings</span>
        <span class="badge <?php echo current_role() === 'organizer' ? 'warning' : 'success'; ?>">
            <?php echo current_role() === 'organizer' ? 'Attendee View' : 'My Registrations'; ?>
        </span>
    </div>
    <h1><?php echo current_role() === 'organizer' ? 'Event Attendees' : 'My Bookings'; ?></h1>
    <p>
        <?php if (current_role() === 'customer'): ?>
            All events you have registered for. Cancel anytime before the event date.
        <?php else: ?>
            Customers who have booked events you organised.
        <?php endif; ?>
    </p>
    <?php if (current_role() === 'customer'): ?>
    <div class="action-row">
        <a class="btn btn-gold" href="view_events.php">Browse More Events</a>
    </div>
    <?php endif; ?>
</section>

<?php if (current_role() === 'customer'): ?>
    <?php
    $cid = current_customer_id();
    $stmt = $conn->prepare('
        SELECT b.booking_id, b.booking_date,
               e.event_name, e.event_time, e.budget,
               v.venue_name, c.name AS organizer_name
        FROM bookings b
        INNER JOIN events    e ON b.event_id    = e.event_id
        INNER JOIN venues    v ON e.venue_id     = v.venue_id
        INNER JOIN customers c ON e.customer_id = c.customer_id
        WHERE b.customer_id = ?
        ORDER BY e.event_time ASC
    ');
    $stmt->bind_param('i', $cid);
    $stmt->execute();
    $bookings = $stmt->get_result();
    ?>

    <?php if ($bookings->num_rows > 0): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Date &amp; Time</th>
                    <th>Venue</th>
                    <th>Organizer</th>
                    <th>Budget</th>
                    <th>Booked On</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($b = $bookings->fetch_assoc()): ?>
                <tr>
                    <td><strong><?php echo h($b['event_name']); ?></strong></td>
                    <td><?php echo h(format_datetime($b['event_time'])); ?></td>
                    <td><?php echo h($b['venue_name']); ?></td>
                    <td><?php echo h($b['organizer_name']); ?></td>
                    <td><?php echo h(format_currency($b['budget'])); ?></td>
                    <td><?php echo h($b['booking_date']); ?></td>
                    <td>
                        <form method="POST" class="inline-form"
                              onsubmit="return confirm('Cancel this booking?');">
                            <input type="hidden" name="action"     value="cancel_booking">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="booking_id" value="<?php echo h((string)$b['booking_id']); ?>">
                            <button type="submit" class="btn btn-danger" style="padding:7px 14px;font-size:0.84rem;">Cancel</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon"></div>
        <h3>No bookings yet</h3>
        <p>Browse upcoming events and reserve your spot.</p>
        <a class="btn btn-gold" href="view_events.php">Find Events</a>
    </div>
    <?php endif; ?>

<?php else: ?>
    <?php
    $oid  = current_customer_id();
    $stmt = $conn->prepare('
        SELECT b.booking_date, e.event_name, e.event_time,
               a.name AS attendee_name, a.email, a.phone
        FROM bookings b
        INNER JOIN events    e ON b.event_id    = e.event_id
        INNER JOIN customers a ON b.customer_id = a.customer_id
        WHERE e.customer_id = ?
        ORDER BY e.event_time ASC, a.name ASC
    ');
    $stmt->bind_param('i', $oid);
    $stmt->execute();
    $bookings = $stmt->get_result();
    ?>

    <?php if ($bookings->num_rows > 0): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Date &amp; Time</th>
                    <th>Attendee</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Booked On</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($b = $bookings->fetch_assoc()): ?>
                <tr>
                    <td><strong><?php echo h($b['event_name']); ?></strong></td>
                    <td><?php echo h(format_datetime($b['event_time'])); ?></td>
                    <td><?php echo h($b['attendee_name']); ?></td>
                    <td><?php echo h($b['email']); ?></td>
                    <td><?php echo h($b['phone']); ?></td>
                    <td><?php echo h($b['booking_date']); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon"></div>
        <h3>No attendees yet</h3>
        <p>Once customers register for your events, they will appear here.</p>
        <a class="btn btn-gold" href="view_events.php">View Your Events</a>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php render_footer(); ?>
