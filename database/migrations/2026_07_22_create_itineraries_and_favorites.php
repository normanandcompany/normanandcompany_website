<?php

$migrationUser = getenv('NORMAN_DB_MIGRATION_USER');

if ($migrationUser === false || $migrationUser === '') {
    require_once __DIR__ . '/../../admin/api/db.php';
} else {
    $migrationHost = getenv('NORMAN_DB_MIGRATION_HOST') ?: 'localhost';
    $migrationPort = getenv('NORMAN_DB_MIGRATION_PORT') ?: '8889';
    $migrationDb = getenv('NORMAN_DB_MIGRATION_DATABASE') ?: 'normanandcompany';
    $migrationPassword = getenv('NORMAN_DB_MIGRATION_PASSWORD');

    if ($migrationPassword === false) {
        fwrite(STDERR, 'Database password: ');
        $migrationPassword = rtrim((string) fgets(STDIN), "\r\n");
    }

    $pdo = new PDO(
        "mysql:host={$migrationHost};port={$migrationPort};dbname={$migrationDb};charset=utf8mb4",
        $migrationUser,
        $migrationPassword
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS itineraries (
        id INT(11) NOT NULL AUTO_INCREMENT,
        ship_id INT(11) NULL DEFAULT NULL,
        embarkation_port_id INT(11) NOT NULL,
        disembarkation_port_id INT(11) NULL DEFAULT NULL,
        primary_destination_id INT(11) NULL DEFAULT NULL,
        itinerary_name VARCHAR(255) NOT NULL,
        itinerary_description TEXT NULL DEFAULT NULL,
        duration_nights SMALLINT UNSIGNED NULL DEFAULT NULL,
        departure_date DATE NULL DEFAULT NULL,
        return_date DATE NULL DEFAULT NULL,
        visible TINYINT(1) NOT NULL DEFAULT 1,
        is_featured TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_itineraries_ship (ship_id),
        KEY idx_itineraries_embarkation_port (embarkation_port_id),
        KEY idx_itineraries_disembarkation_port (disembarkation_port_id),
        KEY idx_itineraries_primary_destination (primary_destination_id),
        KEY idx_itineraries_visible (visible),
        CONSTRAINT fk_itineraries_ship
            FOREIGN KEY (ship_id) REFERENCES ships (id)
            ON UPDATE CASCADE ON DELETE SET NULL,
        CONSTRAINT fk_itineraries_embarkation_port
            FOREIGN KEY (embarkation_port_id) REFERENCES ports (id)
            ON UPDATE CASCADE ON DELETE RESTRICT,
        CONSTRAINT fk_itineraries_disembarkation_port
            FOREIGN KEY (disembarkation_port_id) REFERENCES ports (id)
            ON UPDATE CASCADE ON DELETE SET NULL,
        CONSTRAINT fk_itineraries_primary_destination
            FOREIGN KEY (primary_destination_id) REFERENCES destinations (id)
            ON UPDATE CASCADE ON DELETE SET NULL,
        CONSTRAINT chk_itineraries_duration
            CHECK (duration_nights IS NULL OR duration_nights > 0),
        CONSTRAINT chk_itineraries_dates
            CHECK (return_date IS NULL OR departure_date IS NULL OR return_date >= departure_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS itinerary_stops (
        id INT(11) NOT NULL AUTO_INCREMENT,
        itinerary_id INT(11) NOT NULL,
        port_id INT(11) NULL DEFAULT NULL,
        destination_id INT(11) NULL DEFAULT NULL,
        stop_order SMALLINT UNSIGNED NOT NULL,
        arrival_at DATETIME NULL DEFAULT NULL,
        departure_at DATETIME NULL DEFAULT NULL,
        is_sea_day TINYINT(1) NOT NULL DEFAULT 0,
        notes TEXT NULL DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_itinerary_stops_order (itinerary_id, stop_order),
        KEY idx_itinerary_stops_port (port_id),
        KEY idx_itinerary_stops_destination (destination_id),
        CONSTRAINT fk_itinerary_stops_itinerary
            FOREIGN KEY (itinerary_id) REFERENCES itineraries (id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_itinerary_stops_port
            FOREIGN KEY (port_id) REFERENCES ports (id)
            ON UPDATE CASCADE ON DELETE SET NULL,
        CONSTRAINT fk_itinerary_stops_destination
            FOREIGN KEY (destination_id) REFERENCES destinations (id)
            ON UPDATE CASCADE ON DELETE SET NULL,
        CONSTRAINT chk_itinerary_stops_times
            CHECK (departure_at IS NULL OR arrival_at IS NULL OR departure_at >= arrival_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS favorites (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        cruise_line_id INT(11) NULL DEFAULT NULL,
        destination_id INT(11) NULL DEFAULT NULL,
        excursion_id INT(11) NULL DEFAULT NULL,
        port_id INT(11) NULL DEFAULT NULL,
        itinerary_id INT(11) NULL DEFAULT NULL,
        ship_id INT(11) NULL DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_favorites_user_cruise_line (user_id, cruise_line_id),
        UNIQUE KEY uq_favorites_user_destination (user_id, destination_id),
        UNIQUE KEY uq_favorites_user_excursion (user_id, excursion_id),
        UNIQUE KEY uq_favorites_user_port (user_id, port_id),
        UNIQUE KEY uq_favorites_user_itinerary (user_id, itinerary_id),
        UNIQUE KEY uq_favorites_user_ship (user_id, ship_id),
        KEY idx_favorites_cruise_line (cruise_line_id),
        KEY idx_favorites_destination (destination_id),
        KEY idx_favorites_excursion (excursion_id),
        KEY idx_favorites_port (port_id),
        KEY idx_favorites_itinerary (itinerary_id),
        KEY idx_favorites_ship (ship_id),
        CONSTRAINT fk_favorites_user
            FOREIGN KEY (user_id) REFERENCES users (id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_favorites_cruise_line
            FOREIGN KEY (cruise_line_id) REFERENCES cruise_lines (id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_favorites_destination
            FOREIGN KEY (destination_id) REFERENCES destinations (id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_favorites_excursion
            FOREIGN KEY (excursion_id) REFERENCES excursions (id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_favorites_port
            FOREIGN KEY (port_id) REFERENCES ports (id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_favorites_itinerary
            FOREIGN KEY (itinerary_id) REFERENCES itineraries (id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_favorites_ship
            FOREIGN KEY (ship_id) REFERENCES ships (id)
            ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec('DROP PROCEDURE IF EXISTS sp_get_customer_profile');
$pdo->exec("
    CREATE PROCEDURE sp_get_customer_profile(IN p_user_id INT)
    SQL SECURITY DEFINER
    BEGIN
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email_address,
            u.phone,
            u.address_1,
            u.address_2,
            u.city,
            u.state_prov_id,
            sp.name AS state_province,
            u.postal_code,
            u.country,
            u.last_login_at,
            u.created_at
        FROM users u
        LEFT JOIN state_prov sp ON sp.id = u.state_prov_id
        WHERE u.id = p_user_id
          AND COALESCE(u.is_active, 1) = 1
        LIMIT 1;

        SELECT
            o.id,
            o.order_number,
            o.order_status,
            o.subtotal_amount,
            o.tax_amount,
            o.shipping_amount,
            o.total_amount,
            o.created_at,
            t.transaction_status,
            t.currency_code,
            t.processed_at
        FROM orders o
        LEFT JOIN transactions t ON t.id = (
            SELECT t2.id
            FROM transactions t2
            WHERE t2.order_id = o.id
              AND COALESCE(t2.visible, 1) = 1
            ORDER BY COALESCE(t2.processed_at, t2.created_at) DESC, t2.id DESC
            LIMIT 1
        )
        WHERE o.user_id = p_user_id
          AND COALESCE(o.visible, 1) = 1
        ORDER BY o.created_at DESC, o.id DESC;

        SELECT
            oi.order_id,
            oi.product_sku,
            oi.product_name,
            oi.product_options,
            oi.quantity,
            oi.unit_price,
            oi.line_subtotal,
            oi.currency_code,
            oi.fulfillment_status
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        WHERE o.user_id = p_user_id
          AND COALESCE(o.visible, 1) = 1
        ORDER BY oi.order_id, oi.id;

        SELECT id, cruise_line_name AS label
        FROM cruise_lines
        WHERE COALESCE(visible, 1) = 1
        ORDER BY cruise_line_name;

        SELECT id, destination_name AS label
        FROM destinations
        WHERE COALESCE(visible, 1) = 1
        ORDER BY destination_name;

        SELECT id, excursion_name AS label
        FROM excursions
        WHERE COALESCE(visible, 1) = 1
        ORDER BY excursion_name;

        SELECT id, CONCAT(port_name, COALESCE(CONCAT(' — ', city_name), '')) AS label
        FROM ports
        WHERE COALESCE(visible, 1) = 1
        ORDER BY port_name;

        SELECT id, itinerary_name AS label
        FROM itineraries
        WHERE COALESCE(visible, 1) = 1
        ORDER BY itinerary_name;

        SELECT s.id, CONCAT(s.ship_name, ' — ', cl.cruise_line_name) AS label
        FROM ships s
        INNER JOIN cruise_lines cl ON cl.id = s.cruise_line_id
        WHERE COALESCE(s.visible, 1) = 1
          AND COALESCE(s.is_active, 1) = 1
        ORDER BY s.ship_name;

        SELECT id, name AS label
        FROM state_prov
        ORDER BY name;

        SELECT
            f.id,
            CASE
                WHEN f.cruise_line_id IS NOT NULL THEN 'cruise_line'
                WHEN f.destination_id IS NOT NULL THEN 'destination'
                WHEN f.excursion_id IS NOT NULL THEN 'excursion'
                WHEN f.port_id IS NOT NULL THEN 'port'
                WHEN f.itinerary_id IS NOT NULL THEN 'itinerary'
                WHEN f.ship_id IS NOT NULL THEN 'ship'
            END AS favorite_type,
            COALESCE(
                cl.cruise_line_name,
                d.destination_name,
                e.excursion_name,
                p.port_name,
                i.itinerary_name,
                s.ship_name
            ) AS label,
            f.created_at
        FROM favorites f
        LEFT JOIN cruise_lines cl ON cl.id = f.cruise_line_id
        LEFT JOIN destinations d ON d.id = f.destination_id
        LEFT JOIN excursions e ON e.id = f.excursion_id
        LEFT JOIN ports p ON p.id = f.port_id
        LEFT JOIN itineraries i ON i.id = f.itinerary_id
        LEFT JOIN ships s ON s.id = f.ship_id
        WHERE f.user_id = p_user_id
        ORDER BY f.created_at DESC, f.id DESC;
    END
");

$pdo->exec('DROP PROCEDURE IF EXISTS sp_add_customer_favorite');
$pdo->exec("
    CREATE PROCEDURE sp_add_customer_favorite(
        IN p_user_id INT,
        IN p_favorite_type VARCHAR(30),
        IN p_entity_id INT
    )
    SQL SECURITY DEFINER
    BEGIN
        DECLARE v_exists INT DEFAULT 0;
        DECLARE v_added INT DEFAULT 0;
        DECLARE v_favorite_id INT DEFAULT NULL;

        IF p_user_id IS NULL OR p_user_id <= 0 OR p_entity_id IS NULL OR p_entity_id <= 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Select a valid favorite.';
        END IF;

        SELECT COUNT(*) INTO v_exists
        FROM users
        WHERE id = p_user_id
          AND COALESCE(is_active, 1) = 1;

        IF v_exists = 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Your customer profile could not be found.';
        END IF;

        IF p_favorite_type = 'cruise_line' THEN
            SELECT COUNT(*) INTO v_exists FROM cruise_lines WHERE id = p_entity_id AND COALESCE(visible, 1) = 1;
            IF v_exists > 0 THEN
                INSERT IGNORE INTO favorites (user_id, cruise_line_id) VALUES (p_user_id, p_entity_id);
                SET v_added = ROW_COUNT();
                SELECT id INTO v_favorite_id FROM favorites WHERE user_id = p_user_id AND cruise_line_id = p_entity_id LIMIT 1;
            END IF;
        ELSEIF p_favorite_type = 'destination' THEN
            SELECT COUNT(*) INTO v_exists FROM destinations WHERE id = p_entity_id AND COALESCE(visible, 1) = 1;
            IF v_exists > 0 THEN
                INSERT IGNORE INTO favorites (user_id, destination_id) VALUES (p_user_id, p_entity_id);
                SET v_added = ROW_COUNT();
                SELECT id INTO v_favorite_id FROM favorites WHERE user_id = p_user_id AND destination_id = p_entity_id LIMIT 1;
            END IF;
        ELSEIF p_favorite_type = 'excursion' THEN
            SELECT COUNT(*) INTO v_exists FROM excursions WHERE id = p_entity_id AND COALESCE(visible, 1) = 1;
            IF v_exists > 0 THEN
                INSERT IGNORE INTO favorites (user_id, excursion_id) VALUES (p_user_id, p_entity_id);
                SET v_added = ROW_COUNT();
                SELECT id INTO v_favorite_id FROM favorites WHERE user_id = p_user_id AND excursion_id = p_entity_id LIMIT 1;
            END IF;
        ELSEIF p_favorite_type = 'port' THEN
            SELECT COUNT(*) INTO v_exists FROM ports WHERE id = p_entity_id AND COALESCE(visible, 1) = 1;
            IF v_exists > 0 THEN
                INSERT IGNORE INTO favorites (user_id, port_id) VALUES (p_user_id, p_entity_id);
                SET v_added = ROW_COUNT();
                SELECT id INTO v_favorite_id FROM favorites WHERE user_id = p_user_id AND port_id = p_entity_id LIMIT 1;
            END IF;
        ELSEIF p_favorite_type = 'itinerary' THEN
            SELECT COUNT(*) INTO v_exists FROM itineraries WHERE id = p_entity_id AND COALESCE(visible, 1) = 1;
            IF v_exists > 0 THEN
                INSERT IGNORE INTO favorites (user_id, itinerary_id) VALUES (p_user_id, p_entity_id);
                SET v_added = ROW_COUNT();
                SELECT id INTO v_favorite_id FROM favorites WHERE user_id = p_user_id AND itinerary_id = p_entity_id LIMIT 1;
            END IF;
        ELSEIF p_favorite_type = 'ship' THEN
            SELECT COUNT(*) INTO v_exists
            FROM ships
            WHERE id = p_entity_id
              AND COALESCE(visible, 1) = 1
              AND COALESCE(is_active, 1) = 1;
            IF v_exists > 0 THEN
                INSERT IGNORE INTO favorites (user_id, ship_id) VALUES (p_user_id, p_entity_id);
                SET v_added = ROW_COUNT();
                SELECT id INTO v_favorite_id FROM favorites WHERE user_id = p_user_id AND ship_id = p_entity_id LIMIT 1;
            END IF;
        ELSE
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Select a valid favorite category.';
        END IF;

        IF v_exists = 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'That item is no longer available.';
        END IF;

        SELECT v_added AS added, v_favorite_id AS favorite_id;
    END
");

$pdo->exec('DROP PROCEDURE IF EXISTS sp_delete_customer_favorite');
$pdo->exec("
    CREATE PROCEDURE sp_delete_customer_favorite(
        IN p_user_id INT,
        IN p_favorite_id INT
    )
    SQL SECURITY DEFINER
    BEGIN
        DELETE FROM favorites
        WHERE id = p_favorite_id
          AND user_id = p_user_id;

        SELECT ROW_COUNT() AS deleted;
    END
");

$pdo->exec('DROP PROCEDURE IF EXISTS sp_update_customer_profile');
$pdo->exec("
    CREATE PROCEDURE sp_update_customer_profile(
        IN p_user_id INT,
        IN p_first_name VARCHAR(100),
        IN p_last_name VARCHAR(100),
        IN p_email_address VARCHAR(255),
        IN p_phone VARCHAR(50),
        IN p_address_1 VARCHAR(255),
        IN p_address_2 VARCHAR(255),
        IN p_city VARCHAR(100),
        IN p_state_prov_id INT,
        IN p_postal_code VARCHAR(25),
        IN p_country VARCHAR(100)
    )
    SQL SECURITY DEFINER
    BEGIN
        DECLARE v_exists INT DEFAULT 0;
        DECLARE v_email VARCHAR(255);

        SET v_email = LOWER(TRIM(p_email_address));

        IF p_user_id IS NULL OR p_user_id <= 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Your customer profile could not be found.';
        END IF;

        IF TRIM(COALESCE(p_first_name, '')) = '' OR TRIM(COALESCE(p_last_name, '')) = '' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'First and last name are required.';
        END IF;

        IF v_email = '' OR v_email NOT LIKE '%_@_%._%' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Enter a valid email address.';
        END IF;

        SELECT COUNT(*) INTO v_exists
        FROM state_prov
        WHERE id = p_state_prov_id;

        IF v_exists = 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Select a valid state or province.';
        END IF;

        SELECT COUNT(*) INTO v_exists
        FROM users
        WHERE email_address = v_email
          AND id <> p_user_id;

        IF v_exists > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'That email address is already in use.';
        END IF;

        UPDATE users
        SET first_name = TRIM(p_first_name),
            last_name = TRIM(p_last_name),
            email_address = v_email,
            phone = NULLIF(TRIM(COALESCE(p_phone, '')), ''),
            address_1 = NULLIF(TRIM(COALESCE(p_address_1, '')), ''),
            address_2 = NULLIF(TRIM(COALESCE(p_address_2, '')), ''),
            city = NULLIF(TRIM(COALESCE(p_city, '')), ''),
            state_prov_id = p_state_prov_id,
            postal_code = NULLIF(TRIM(COALESCE(p_postal_code, '')), ''),
            country = NULLIF(TRIM(COALESCE(p_country, '')), '')
        WHERE id = p_user_id
          AND COALESCE(is_active, 1) = 1;

        IF ROW_COUNT() = 0 THEN
            SELECT COUNT(*) INTO v_exists
            FROM users
            WHERE id = p_user_id
              AND COALESCE(is_active, 1) = 1;

            IF v_exists = 0 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Your customer profile could not be found.';
            END IF;
        END IF;

        SELECT 1 AS success, v_email AS email_address;
    END
");

echo "Itineraries, favorites, and customer account procedures migration complete.\n";
