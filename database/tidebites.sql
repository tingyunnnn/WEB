-- TideBites PR 1 candidate contract; MySQL 8.4 LTS, UTC timestamps.
-- No DROP/TRUNCATE/UPDATE. Not a schema migration tool: see database-setup.md.
-- Seed the ten original dishes only when food_items is entirely empty.
-- Keep the statement separators: the CLI importer executes one statement at a time.
SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;
-- statement
SET time_zone = '+00:00';
-- statement
CREATE DATABASE IF NOT EXISTS `tidebites` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
-- statement
USE `tidebites`;
-- statement
CREATE TABLE IF NOT EXISTS food_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    category VARCHAR(20) NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    image_alt VARCHAR(500) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_food_menu (is_active, category, sort_order, id),
    CONSTRAINT chk_food_name CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT chk_food_description CHECK (CHAR_LENGTH(TRIM(description)) > 0),
    CONSTRAINT chk_food_price CHECK (price >= 0),
    CONSTRAINT chk_food_category CHECK (category COLLATE utf8mb4_0900_as_cs IN ('meals', 'desserts')),
    CONSTRAINT chk_food_image CHECK (CHAR_LENGTH(TRIM(image_path)) > 0),
    CONSTRAINT chk_food_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
-- statement
CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_username (username),
    CONSTRAINT chk_admin_username CHECK (CHAR_LENGTH(TRIM(username)) > 0),
    CONSTRAINT chk_admin_hash CHECK (CHAR_LENGTH(password_hash) >= 60)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
-- statement
CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_name VARCHAR(100) NULL,
    phone VARCHAR(32) NULL,
    delivery_location VARCHAR(255) NOT NULL,
    notes VARCHAR(500) NULL,
    pickup_time DATETIME NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orders_created (created_at, id),
    KEY idx_orders_pickup (pickup_time),
    CONSTRAINT chk_order_location CHECK (CHAR_LENGTH(TRIM(delivery_location)) > 0),
    CONSTRAINT chk_order_subtotal CHECK (subtotal >= 0),
    CONSTRAINT chk_order_discount CHECK (discount_amount >= 0 AND discount_amount <= subtotal),
    CONSTRAINT chk_order_total CHECK (total_amount = subtotal - discount_amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
-- statement
CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    food_item_id INT UNSIGNED NULL,
    food_name VARCHAR(150) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_food (food_item_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_food FOREIGN KEY (food_item_id) REFERENCES food_items(id) ON DELETE SET NULL,
    CONSTRAINT chk_item_name CHECK (CHAR_LENGTH(TRIM(food_name)) > 0),
    CONSTRAINT chk_item_price CHECK (unit_price >= 0),
    CONSTRAINT chk_item_quantity CHECK (quantity BETWEEN 1 AND 99)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
-- statement
INSERT INTO food_items (id, name, description, price, category, image_path, image_alt, sort_order)
SELECT seed.id, seed.name, seed.description, seed.price, seed.category, seed.image_path, seed.image_alt, seed.sort_order
FROM (
    SELECT 1 AS id, 'Thai Basil Pork Rice' AS name, 'Savory minced pork with fragrant basil and vegetables, served over fluffy rice and topped with two sunny-side-up eggs. A hearty pick for a busy day.' AS description, 129.00 AS price, 'meals' AS category, 'assets/images/thai-basil-pork-rice.jpg' AS image_path, 'Thai basil minced pork and vegetables served with rice and two fried eggs.' AS image_alt, 1 AS sort_order
    UNION ALL
    SELECT 2, 'Chicken Oyakodon Bowl', 'A comforting rice bowl with tender chicken, egg, and sweet onions, finished with a sprinkle of green onions and sesame.', 139.00, 'meals', 'assets/images/chicken-oyakodon-bowl.jpg', 'Chicken and egg rice bowl topped with green onions, sesame, and vegetables.', 2
    UNION ALL
    SELECT 3, 'Taiwanese Fish Ball Noodle Soup', 'Springy fish balls, sliced chicken, noodles, and fresh greens in a light, savory broth. A warm bowl of comfort between classes.', 139.00, 'meals', 'assets/images/taiwanese-fish-ball-noodle-soup.jpg', 'Taiwanese noodle soup with fish balls, sliced chicken, and green vegetables.', 3
    UNION ALL
    SELECT 4, 'Roasted Chicken Set Meal', 'A golden roasted chicken leg served with steamed rice, vegetables, and tasty sides. Everything you need in one satisfying plate.', 169.00, 'meals', 'assets/images/roasted-chicken-set-meal.jpg', 'Roasted chicken leg set with rice, vegetables, and small side dishes.', 4
    UNION ALL
    SELECT 5, 'Tomato Udon Soup', 'Thick udon noodles in a tangy tomato broth with sliced meat, egg, and green onions. Bright, savory, and wonderfully comforting.', 159.00, 'meals', 'assets/images/tomato-udon-soup.jpg', 'Tomato broth udon with sliced meat, egg, green onions, and fish cake.', 5
    UNION ALL
    SELECT 6, 'Beef and Egg Noodles', 'Juicy beef slices and a sunny-side-up egg over a bowl of noodles. A filling favorite when you need an energy boost.', 179.00, 'meals', 'assets/images/beef-and-egg-noodles.jpg', 'Beef slices and a fried egg served over noodles in a dark bowl.', 6
    UNION ALL
    SELECT 7, 'Chilled Pork Noodle Bowl', 'A colorful bowl of chilled noodles with sliced pork, edamame, cucumber, lotus root, and sweet corn, finished with a savory sauce.', 149.00, 'meals', 'assets/images/chilled-pork-noodle-bowl.jpg', 'Chilled noodles topped with pork, edamame, cucumber, lotus root, and corn.', 7
    UNION ALL
    SELECT 8, 'Watermelon Snow Ice', 'Light, fluffy shaved milk ice topped with juicy watermelon cubes. A refreshing little escape on a sunny afternoon.', 99.00, 'desserts', 'assets/images/watermelon-snow-ice.jpg', 'White shaved milk ice generously topped with red watermelon cubes.', 8
    UNION ALL
    SELECT 9, 'Mango Snow Ice', 'Delicate ribbons of shaved milk ice piled high with sweet mango pieces and a fruity drizzle. Bright, tropical, and irresistible.', 109.00, 'desserts', 'assets/images/mango-snow-ice.jpg', 'A bowl of yellow shaved milk ice topped with fresh mango chunks.', 9
    UNION ALL
    SELECT 10, 'Peanut Tang Yuan', 'Soft and chewy glutinous tang yuan topped with crushed peanut powder and served in a sweet sauce. A cozy Taiwanese dessert.', 79.00, 'desserts', 'assets/images/peanut-rice-dumplings.jpg', 'White glutinous tang yuan coated with peanut powder in a dark sweet sauce.', 10
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM food_items LIMIT 1);
