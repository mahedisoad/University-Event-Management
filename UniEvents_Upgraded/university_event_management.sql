SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS university_event_management;
CREATE DATABASE university_event_management
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE university_event_management;

-- Core master tables
CREATE TABLE customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL CHECK (CHAR_LENGTH(password) >= 6),
    role ENUM('customer', 'organizer') NOT NULL DEFAULT 'customer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE venues (
    venue_id INT AUTO_INCREMENT PRIMARY KEY,
    venue_name VARCHAR(120) NOT NULL,
    address VARCHAR(255) NOT NULL,
    capacity INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE staff (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    service_type VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    venue_id INT NOT NULL,
    event_name VARCHAR(150) NOT NULL,
    event_time DATETIME NOT NULL,
    budget DECIMAL(12, 2) NOT NULL,
    CONSTRAINT fk_events_customer
        FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    CONSTRAINT fk_events_venue
        FOREIGN KEY (venue_id) REFERENCES venues(venue_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_date DATE NOT NULL,
    event_id INT NOT NULL,
    customer_id INT NOT NULL,
    CONSTRAINT unique_event_customer UNIQUE (event_id, customer_id),
    CONSTRAINT fk_bookings_event
        FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    CONSTRAINT fk_bookings_customer
        FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_audit_log (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NULL,
    event_id INT NOT NULL,
    customer_id INT NOT NULL,
    action_type ENUM('BOOKED', 'CANCELLED') NOT NULL,
    action_note VARCHAR(255) NOT NULL,
    action_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_booking_audit_time (action_time),
    INDEX idx_booking_audit_event (event_id),
    INDEX idx_booking_audit_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_staff (
    event_id INT NOT NULL,
    staff_id INT NOT NULL,
    PRIMARY KEY (event_id, staff_id),
    CONSTRAINT fk_event_staff_event
        FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    CONSTRAINT fk_event_staff_staff
        FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_suppliers (
    event_id INT NOT NULL,
    supplier_id INT NOT NULL,
    PRIMARY KEY (event_id, supplier_id),
    CONSTRAINT fk_event_supplier_event
        FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    CONSTRAINT fk_event_supplier_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$

-- Trigger validation for event creation and editing
CREATE TRIGGER trg_events_before_insert_validate
BEFORE INSERT ON events
FOR EACH ROW
BEGIN
    IF TRIM(NEW.event_name) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Event name is required.';
    END IF;

    IF NEW.event_time <= NOW() THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Event date must be in the future.';
    END IF;

    IF NEW.budget <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Event budget must be greater than zero.';
    END IF;
END$$

CREATE TRIGGER trg_events_before_update_validate
BEFORE UPDATE ON events
FOR EACH ROW
BEGIN
    IF TRIM(NEW.event_name) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Event name is required.';
    END IF;

    IF NEW.event_time <= NOW() THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Event date must be in the future.';
    END IF;

    IF NEW.budget <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Event budget must be greater than zero.';
    END IF;
END$$

-- Trigger-based audit trail for booking activity
CREATE TRIGGER trg_bookings_after_insert_audit
AFTER INSERT ON bookings
FOR EACH ROW
BEGIN
    INSERT INTO booking_audit_log (booking_id, event_id, customer_id, action_type, action_note)
    VALUES (
        NEW.booking_id,
        NEW.event_id,
        NEW.customer_id,
        'BOOKED',
        'Booking recorded through transactional stored procedure'
    );
END$$

CREATE TRIGGER trg_bookings_after_delete_audit
AFTER DELETE ON bookings
FOR EACH ROW
BEGIN
    INSERT INTO booking_audit_log (booking_id, event_id, customer_id, action_type, action_note)
    VALUES (
        OLD.booking_id,
        OLD.event_id,
        OLD.customer_id,
        'CANCELLED',
        'Booking cancellation recorded by database trigger'
    );
END$$

-- Transactional stored procedures for the booking workflow
CREATE PROCEDURE sp_create_booking(IN p_event_id INT, IN p_customer_id INT)
BEGIN
    DECLARE v_event_time DATETIME DEFAULT NULL;
    DECLARE v_capacity INT DEFAULT 0;
    DECLARE v_existing_booking INT DEFAULT 0;
    DECLARE v_booking_total INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT e.event_time, v.capacity
      INTO v_event_time, v_capacity
    FROM events e
    INNER JOIN venues v ON e.venue_id = v.venue_id
    WHERE e.event_id = p_event_id
    FOR UPDATE;

    IF v_event_time IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'That event is no longer available.';
    END IF;

    IF v_event_time <= NOW() THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'You can only book upcoming events.';
    END IF;

    SELECT COUNT(*)
      INTO v_existing_booking
    FROM bookings
    WHERE event_id = p_event_id AND customer_id = p_customer_id;

    IF v_existing_booking > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'You have already booked this event.';
    END IF;

    SELECT COUNT(*)
      INTO v_booking_total
    FROM bookings
    WHERE event_id = p_event_id;

    IF v_booking_total >= v_capacity THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This event has reached full capacity.';
    END IF;

    INSERT INTO bookings (booking_date, event_id, customer_id)
    VALUES (CURDATE(), p_event_id, p_customer_id);

    COMMIT;
END$$

CREATE PROCEDURE sp_cancel_booking(IN p_booking_id INT, IN p_customer_id INT)
BEGIN
    DECLARE v_event_time DATETIME DEFAULT NULL;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT e.event_time
      INTO v_event_time
    FROM bookings b
    INNER JOIN events e ON b.event_id = e.event_id
    WHERE b.booking_id = p_booking_id AND b.customer_id = p_customer_id
    FOR UPDATE;

    IF v_event_time IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'That booking could not be found.';
    END IF;

    IF v_event_time <= NOW() THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Past events can no longer be cancelled.';
    END IF;

    DELETE FROM bookings
    WHERE booking_id = p_booking_id AND customer_id = p_customer_id;

    COMMIT;
END$$

CREATE PROCEDURE sp_booking_activity_report(IN p_limit INT)
BEGIN
    SELECT
        a.action_type,
        a.action_time,
        COALESCE(e.event_name, 'Archived Event') AS event_name,
        COALESCE(c.name, 'Archived User') AS customer_name,
        a.action_note
    FROM booking_audit_log a
    LEFT JOIN events e ON a.event_id = e.event_id
    LEFT JOIN customers c ON a.customer_id = c.customer_id
    ORDER BY a.action_time DESC, a.audit_id DESC
    LIMIT p_limit;
END$$

DELIMITER ;

-- Seed data
INSERT INTO customers (name, phone, email, password, role) VALUES
('Farhana Akter', '+8801711000001', 'farhana.organizer@example.com', '$2y$12$IIVdNSaswO5xS1usO.4D/uFOWfbWTqqc6wVcXS58Uy/0KTYUrF9t2', 'organizer'),
('Nabil Hasan', '+8801711000002', 'nabil.organizer@example.com', '$2y$12$IIVdNSaswO5xS1usO.4D/uFOWfbWTqqc6wVcXS58Uy/0KTYUrF9t2', 'organizer'),
('Ayesha Siddika', '+8801711000003', 'ayesha.customer@example.com', '$2y$12$IIVdNSaswO5xS1usO.4D/uFOWfbWTqqc6wVcXS58Uy/0KTYUrF9t2', 'customer'),
('Tanvir Alam', '+8801711000004', 'tanvir.customer@example.com', '$2y$12$IIVdNSaswO5xS1usO.4D/uFOWfbWTqqc6wVcXS58Uy/0KTYUrF9t2', 'customer');

INSERT INTO venues (venue_name, address, capacity) VALUES
('Main Auditorium', 'Central Campus, Block A', 500),
('Innovation Lab Hall', 'Engineering Building, 2nd Floor', 180),
('Open Air Stage', 'South Field, University Grounds', 900);

INSERT INTO staff (name, role, phone) VALUES
('Reza Karim', 'Technical Coordinator', '+8801812000001'),
('Mitu Rahman', 'Registration Desk Lead', '+8801812000002'),
('Shuvo Das', 'Security Supervisor', '+8801812000003');

INSERT INTO suppliers (name, phone, service_type) VALUES
('Campus Catering Co.', '+8801913000001', 'Catering'),
('Bright Light AV', '+8801913000002', 'Audio Visual Support'),
('Print Point Studio', '+8801913000003', 'Branding and Printing');

INSERT INTO events (customer_id, venue_id, event_name, event_time, budget) VALUES
(1, 1, 'University Research Expo 2026', '2026-05-10 10:00:00', 12000.00),
(2, 2, 'Startup Pitch Day', '2026-05-22 14:00:00', 8500.00),
(1, 3, 'Cultural Night Festival', '2026-06-05 18:30:00', 15000.00);

INSERT INTO bookings (booking_date, event_id, customer_id) VALUES
('2026-04-10', 1, 3),
('2026-04-11', 2, 4),
('2026-04-12', 3, 3);

INSERT INTO event_staff (event_id, staff_id) VALUES
(1, 1),
(1, 2),
(2, 1),
(2, 3),
(3, 2),
(3, 3);

INSERT INTO event_suppliers (event_id, supplier_id) VALUES
(1, 2),
(1, 3),
(2, 2),
(3, 1),
(3, 3);

SET FOREIGN_KEY_CHECKS = 1;
