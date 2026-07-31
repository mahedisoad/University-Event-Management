<?php
require_once __DIR__ . '/partials.php';

require_login();

$events = $conn->query('
    SELECT
        e.event_id,
        e.customer_id,
        e.event_name,
        e.event_time,
        e.budget,
        c.name AS organizer_name,
        v.venue_name,
        v.capacity,
        COUNT(DISTINCT b.booking_id)  AS booking_total,
        COUNT(DISTINCT es.staff_id)   AS staff_total,
        COUNT(DISTINCT ep.supplier_id) AS supplier_total
    FROM events e
    INNER JOIN customers c ON e.customer_id = c.customer_id
    INNER JOIN venues    v ON e.venue_id    = v.venue_id
    LEFT  JOIN bookings      b  ON e.event_id = b.event_id
    LEFT  JOIN event_staff   es ON e.event_id = es.event_id
    LEFT  JOIN event_suppliers ep ON e.event_id = ep.event_id
    GROUP BY
        e.event_id,
        e.customer_id,
        e.event_name,
        e.event_time,
        e.budget,
        c.name,
        v.venue_name,
        v.capacity
    ORDER BY e.event_time ASC
');

$bookedEventIds = [];
if (current_role() === 'customer') {
    $bookedStmt = $conn->prepare('SELECT event_id FROM bookings WHERE customer_id = ?');
    $cid = current_customer_id();
    $bookedStmt->bind_param('i', $cid);
    $bookedStmt->execute();
    $bookedResult = $bookedStmt->get_result();
    while ($row = $bookedResult->fetch_assoc()) {
        $bookedEventIds[] = (int) $row['event_id'];
    }
}

render_header('Events', 'view_events.php');
?>

<section class="hero">
    <div class="pill-row">
        <span class="badge">All Events</span>
        <span class="badge warning"><?php echo $events->num_rows; ?> Listed</span>
    </div>
    <h1>University Events</h1>
    <p>Browse all upcoming events and review their venue, capacity, staffing, and supplier details.</p>
    <?php if (current_role() === 'organizer'): ?>
    <div class="action-row">
        <a class="btn btn-gold" href="add_event.php">Create New Event</a>
        <a class="btn btn-light" href="manage_resources.php">Manage Resources</a>
    </div>
    <?php endif; ?>
</section>

<?php if ($events->num_rows > 0): ?>
<div class="event-grid">
    <?php while ($event = $events->fetch_assoc()):
        $pct = $event['capacity'] > 0
            ? min(100, round(($event['booking_total'] / $event['capacity']) * 100))
            : 0;
        $isPast = !is_future_datetime($event['event_time']);
        $isFull = (int) $event['booking_total'] >= (int) $event['capacity'];
    ?>
    <article class="event-card">
        <div class="pill-row">
            <span class="badge"><?php echo h($event['venue_name']); ?></span>
            <span class="badge success"><?php echo h((string)$event['booking_total']); ?> booked</span>
        </div>
        <h3><?php echo h($event['event_name']); ?></h3>
        <div class="meta">
            <div><strong>Date &amp; Time:</strong> <?php echo h(format_datetime($event['event_time'])); ?></div>
            <div><strong>Budget:</strong> <?php echo h(format_currency($event['budget'])); ?></div>
            <div><strong>Organizer:</strong> <?php echo h($event['organizer_name']); ?></div>
            <div><strong>Capacity:</strong> <?php echo h((string)$event['capacity']); ?> seats</div>
            <div><strong>Staff:</strong> <?php echo h((string)$event['staff_total']); ?> assigned</div>
            <div><strong>Suppliers:</strong> <?php echo h((string)$event['supplier_total']); ?> linked</div>
        </div>

        <!-- Capacity fill bar -->
        <div style="margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;font-size:0.78rem;color:var(--text-soft);margin-bottom:4px;">
                <span>Booking fill rate</span>
                <span><?php echo $pct; ?>%</span>
            </div>
            <div class="capacity-bar-track">
                <div class="capacity-bar-fill" style="width:<?php echo $pct; ?>%"></div>
            </div>
        </div>

        <div class="action-row">
            <?php if (current_role() === 'customer'): ?>
                <?php if (in_array((int)$event['event_id'], $bookedEventIds, true)): ?>
                    <span class="badge success">Already Booked</span>
                <?php elseif ($isPast): ?>
                    <span class="badge danger">Event Closed</span>
                <?php elseif ($isFull): ?>
                    <span class="badge warning">Event Full</span>
                <?php else: ?>
                    <form method="POST" action="bookings.php" class="inline-form">
                        <input type="hidden" name="action"   value="create_booking">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="event_id" value="<?php echo h((string)$event['event_id']); ?>">
                        <button type="submit" class="btn btn-gold">Reserve Seat</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (current_role() === 'organizer' && (int)$event['customer_id'] === current_customer_id()): ?>
                <a class="btn btn-light" href="edit_event.php?event_id=<?php echo h((string)$event['event_id']); ?>">Edit</a>
                <form method="POST" action="delete_event.php" class="inline-form"
                      onsubmit="return confirm('Permanently delete this event and all its bookings?');">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="event_id" value="<?php echo h((string)$event['event_id']); ?>">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            <?php endif; ?>
        </div>
    </article>
    <?php endwhile; ?>
</div>

<?php else: ?>
<div class="empty-state">
    <div class="empty-icon" aria-hidden="true"></div>
    <h3>No events yet</h3>
    <p>Events will appear here once an organizer creates them.</p>
    <?php if (current_role() === 'organizer'): ?>
        <a class="btn btn-gold" href="add_event.php">Create the First Event</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php render_footer(); ?>
