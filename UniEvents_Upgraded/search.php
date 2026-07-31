<?php
require_once __DIR__ . '/partials.php';

require_login();

$searchQuery = trim($_GET['search_query'] ?? '');
$results = [];
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

if ($searchQuery !== '') {
    $like = '%' . $searchQuery . '%';
    $stmt = $conn->prepare('
        SELECT e.event_id, e.customer_id, e.event_name, e.event_time, e.budget,
               v.venue_name, v.capacity, c.name AS organizer_name,
               COUNT(DISTINCT b.booking_id) AS booking_total
        FROM events e
        INNER JOIN venues    v ON e.venue_id    = v.venue_id
        INNER JOIN customers c ON e.customer_id = c.customer_id
        LEFT JOIN bookings   b ON e.event_id    = b.event_id
        WHERE e.event_name LIKE ? OR v.venue_name LIKE ? OR c.name LIKE ?
        GROUP BY
            e.event_id,
            e.customer_id,
            e.event_name,
            e.event_time,
            e.budget,
            v.venue_name,
            v.capacity,
            c.name
        ORDER BY e.event_time ASC
    ');
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $qr = $stmt->get_result();
    while ($row = $qr->fetch_assoc()) {
        $results[] = $row;
    }
}

render_header('Search', 'search.php');
?>

<section class="hero">
    <div class="pill-row">
        <span class="badge">Search</span>
    </div>
    <h1>Find an Event</h1>
    <p>Search by event name, venue, or organizer name.</p>
    <form method="GET" style="margin-top:24px;display:flex;gap:10px;flex-wrap:wrap;max-width:600px;">
        <input type="text" name="search_query"
               placeholder="e.g. Research Expo, Main Auditorium"
               value="<?php echo h($searchQuery); ?>"
               style="flex:1;min-width:220px;background:rgba(255,255,255,0.12);border-color:rgba(255,255,255,0.20);color:#fff;">
        <button type="submit" class="btn btn-gold">Search</button>
    </form>
</section>

<?php if ($searchQuery !== ''): ?>
    <div style="margin-bottom:16px;color:var(--text-muted);font-size:0.92rem;">
        <?php if (!empty($results)): ?>
            Found <strong><?php echo count($results); ?></strong> result<?php echo count($results) !== 1 ? 's' : ''; ?>
            for <strong>"<?php echo h($searchQuery); ?>"</strong>
        <?php else: ?>
            No results for <strong>"<?php echo h($searchQuery); ?>"</strong>
        <?php endif; ?>
    </div>

    <?php if (!empty($results)): ?>
    <div class="event-grid">
        <?php foreach ($results as $event): ?>
        <?php
            $isPast = !is_future_datetime($event['event_time']);
            $isFull = (int) $event['booking_total'] >= (int) $event['capacity'];
        ?>
        <article class="event-card">
            <div class="pill-row">
                <span class="badge"><?php echo h($event['venue_name']); ?></span>
            </div>
            <h3><?php echo h($event['event_name']); ?></h3>
            <div class="meta">
                <div><strong>Date &amp; Time:</strong> <?php echo h(format_datetime($event['event_time'])); ?></div>
                <div><strong>Budget:</strong> <?php echo h(format_currency($event['budget'])); ?></div>
                <div><strong>Organizer:</strong> <?php echo h($event['organizer_name']); ?></div>
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
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon"></div>
        <h3>No matching events</h3>
        <p>Try a different keyword, such as an event name, venue, or organizer.</p>
        <a class="btn btn-light" href="view_events.php">Browse All Events</a>
    </div>
    <?php endif; ?>

<?php else: ?>
<div class="empty-state">
    <div class="empty-icon"></div>
    <h3>Enter a search term above</h3>
    <p>You can search by event name, venue location, or organizer name.</p>
</div>
<?php endif; ?>

<?php render_footer(); ?>
