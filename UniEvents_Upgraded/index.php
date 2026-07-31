<?php
require_once __DIR__ . '/partials.php';

require_login();

$customerId = current_customer_id();
$role = current_role();

$eventCount    = (int) $conn->query('SELECT COUNT(*) AS total FROM events')->fetch_assoc()['total'];
$venueCount    = (int) $conn->query('SELECT COUNT(*) AS total FROM venues')->fetch_assoc()['total'];
$staffCount    = (int) $conn->query('SELECT COUNT(*) AS total FROM staff')->fetch_assoc()['total'];
$supplierCount = (int) $conn->query('SELECT COUNT(*) AS total FROM suppliers')->fetch_assoc()['total'];

if ($role === 'organizer') {
    $createdStmt = $conn->prepare('SELECT COUNT(*) AS total FROM events WHERE customer_id = ?');
    $createdStmt->bind_param('i', $customerId);
    $createdStmt->execute();
    $createdEvents = (int) $createdStmt->get_result()->fetch_assoc()['total'];

    $bookingStmt = $conn->prepare('
        SELECT COUNT(*) AS total
        FROM bookings b
        INNER JOIN events e ON b.event_id = e.event_id
        WHERE e.customer_id = ?
    ');
    $bookingStmt->bind_param('i', $customerId);
    $bookingStmt->execute();
    $receivedBookings = (int) $bookingStmt->get_result()->fetch_assoc()['total'];
} else {
    $bookingStmt = $conn->prepare('SELECT COUNT(*) AS total FROM bookings WHERE customer_id = ?');
    $bookingStmt->bind_param('i', $customerId);
    $bookingStmt->execute();
    $myBookings = (int) $bookingStmt->get_result()->fetch_assoc()['total'];
}

$upcomingEvents = $conn->query('
    SELECT e.event_name, e.event_time, v.venue_name, c.name AS organizer_name, e.budget
    FROM events e
    INNER JOIN venues v ON e.venue_id = v.venue_id
    INNER JOIN customers c ON e.customer_id = c.customer_id
    WHERE e.event_time >= NOW()
    ORDER BY e.event_time ASC
    LIMIT 4
');

render_header('Dashboard', 'index.php');
?>

<!-- HERO -->
<section class="hero">
    <div class="hero-grid"></div>
    <div class="hero-inner hero-layout">
        <div class="hero-copy">
        <div class="hero-eyebrow">
            <?php echo h(ucfirst($role)); ?> Account
        </div>
        <h1>Welcome back,<br><em><?php echo h($_SESSION['customer_name']); ?></em></h1>
        <p class="hero-desc">A polished academic control center for planning, scheduling, and presenting university events with clarity.</p>
        <div class="action-row">
            <a class="btn btn-gold" href="view_events.php">Browse Events</a>
            <a class="btn btn-outline" href="bookings.php">Bookings</a>
            <?php if ($role === 'organizer'): ?>
                <a class="btn btn-secondary" href="add_event.php">Create Event</a>
                <a class="btn btn-light" href="manage_resources.php">Manage Resources</a>
            <?php endif; ?>
        </div>
        </div>
        <aside class="hero-spotlight">
            <div class="hero-spotlight-label">Showcase Summary</div>
            <p>Built to keep event planning, booking activity, and resource coordination clear and organized.</p>
            <div class="hero-metric-grid">
                <div class="hero-metric">
                    <span class="hero-metric-value"><?php echo $eventCount; ?></span>
                    <span class="hero-metric-label">Events</span>
                </div>
                <div class="hero-metric">
                    <span class="hero-metric-value"><?php echo $venueCount; ?></span>
                    <span class="hero-metric-label">Venues</span>
                </div>
                <div class="hero-metric">
                    <span class="hero-metric-value"><?php echo $staffCount; ?></span>
                    <span class="hero-metric-label">Staff</span>
                </div>
                <div class="hero-metric">
                    <span class="hero-metric-value"><?php echo $supplierCount; ?></span>
                    <span class="hero-metric-label">Suppliers</span>
                </div>
            </div>
        </aside>
    </div>
</section>

<!-- STATS GRID -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-accent"></div>
        <div class="stat-icon"></div>
        <h3>Total Events</h3>
        <div class="stat-number amber"><?php echo $eventCount; ?></div>
        <div class="stat-label">All events in the system</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-accent jade"></div>
        <div class="stat-icon jade"></div>
        <h3>Venues</h3>
        <div class="stat-number jade"><?php echo $venueCount; ?></div>
        <div class="stat-label">Available locations</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-accent soft"></div>
        <div class="stat-icon soft"></div>
        <h3>Staff Members</h3>
        <div class="stat-number purple"><?php echo $staffCount; ?></div>
        <div class="stat-label">Assignable personnel</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-accent" style="background:linear-gradient(90deg,#60a5fa,transparent)"></div>
        <div class="stat-icon" style="background:#eff6ff"></div>
        <h3>Suppliers</h3>
        <div class="stat-number" style="color:#2563eb"><?php echo $supplierCount; ?></div>
        <div class="stat-label">Service providers</div>
    </div>

    <?php if ($role === 'organizer'): ?>
    <div class="stat-card">
        <div class="stat-card-accent"></div>
        <div class="stat-icon"></div>
        <h3>Your Events</h3>
        <div class="stat-number amber"><?php echo $createdEvents; ?></div>
        <div class="stat-label">Events you have created</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-accent jade"></div>
        <div class="stat-icon jade"></div>
        <h3>Attendees</h3>
        <div class="stat-number jade"><?php echo $receivedBookings; ?></div>
        <div class="stat-label">Bookings on your events</div>
    </div>
    <?php else: ?>
    <div class="stat-card">
        <div class="stat-card-accent"></div>
        <div class="stat-icon"></div>
        <h3>Your Bookings</h3>
        <div class="stat-number amber"><?php echo $myBookings; ?></div>
        <div class="stat-label">Events you're attending</div>
    </div>
    <?php endif; ?>
</div>

<!-- UPCOMING EVENTS -->
<div class="panel">
    <div class="section-header">
        <div>
            <div class="panel-eyebrow">Scheduled</div>
            <h2>Upcoming Events</h2>
        </div>
        <a class="btn btn-light" href="view_events.php">View All</a>
    </div>

    <?php if ($upcomingEvents->num_rows > 0): ?>
    <div class="event-grid">
        <?php while ($event = $upcomingEvents->fetch_assoc()): ?>
        <article class="event-card">
            <div class="event-card-header">
                <span class="badge" style="margin-bottom:12px;position:relative;z-index:1"><?php echo h($event['venue_name']); ?></span>
                <h3><?php echo h($event['event_name']); ?></h3>
            </div>
            <div class="event-card-body">
                <div class="event-meta-row">
                    <div class="event-meta-icon"></div>
                    <div>
                        <span class="event-meta-label">Date &amp; Time</span>
                        <span class="event-meta-value"><?php echo h(format_datetime($event['event_time'])); ?></span>
                    </div>
                </div>
                <div class="event-meta-row">
                    <div class="event-meta-icon"></div>
                    <div>
                        <span class="event-meta-label">Budget</span>
                        <span class="event-meta-value"><?php echo h(format_currency($event['budget'])); ?></span>
                    </div>
                </div>
                <div class="event-meta-row">
                    <div class="event-meta-icon"></div>
                    <div>
                        <span class="event-meta-label">Organizer</span>
                        <span class="event-meta-value"><?php echo h($event['organizer_name']); ?></span>
                    </div>
                </div>
            </div>
            <div class="event-card-footer">
                <a class="btn btn-light" href="view_events.php" style="width:100%;justify-content:center">View Details</a>
            </div>
        </article>
        <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <span class="empty-icon" aria-hidden="true"></span>
        <h3>No events yet</h3>
        <p>Start by creating a venue and then adding your first event.</p>
        <?php if ($role === 'organizer'): ?>
            <a class="btn btn-gold" href="add_event.php">Create First Event</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php render_footer(); ?>
