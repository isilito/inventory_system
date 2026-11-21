-- PartsBin database schema 
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    full_name  VARCHAR(100) NOT NULL,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    email      VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    avatar     VARCHAR(255) NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A product. `image` is the file name of its picture inside uploads/products/
CREATE TABLE IF NOT EXISTS items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NULL,
    name        VARCHAR(150) NOT NULL,
    brand       VARCHAR(100) NOT NULL DEFAULT '',
    status      VARCHAR(30)  NOT NULL DEFAULT 'New',
    notes       TEXT,
    image       VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Each item has one or more variants (capacity, color, model...).
-- Stock, cost and price live on the variant.
CREATE TABLE IF NOT EXISTS variants (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    item_id       INT           NOT NULL,
    label         VARCHAR(100)  NOT NULL,
    sku           VARCHAR(60)   NOT NULL DEFAULT '',
    quantity      INT           NOT NULL DEFAULT 0,
    unit_cost     DECIMAL(10,2) NOT NULL DEFAULT 0,
    sell_price    DECIMAL(10,2) NOT NULL DEFAULT 0,
    low_threshold INT           NOT NULL DEFAULT 2,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every change to stock is logged here (opening counts, restocks, adjustments)
CREATE TABLE IF NOT EXISTS stock_movements (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    variant_id INT           NOT NULL,
    user_id    INT           NULL,
    type       VARCHAR(20)   NOT NULL,
    qty_change INT           NOT NULL,
    qty_after  INT           NOT NULL,
    unit_cost  DECIMAL(10,2) NOT NULL DEFAULT 0,
    supplier   VARCHAR(150)  NOT NULL DEFAULT '',
    reason     VARCHAR(255)  NOT NULL DEFAULT '',
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (variant_id) REFERENCES variants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Login sessions. Kept here instead of in files so logins also work on
-- hosts like Vercel, where each request can run on a different server.
CREATE TABLE IF NOT EXISTS sessions (
    id         VARCHAR(128) NOT NULL PRIMARY KEY,
    data       MEDIUMBLOB   NOT NULL,
    updated_at INT UNSIGNED NOT NULL,
    INDEX (updated_at)
) ENGINE=InnoDB;

-- Uploaded pictures (product photos and avatars). `name` is what
-- items.image / users.avatar store; image.php sends the picture.
CREATE TABLE IF NOT EXISTS images (
    name       VARCHAR(255) NOT NULL PRIMARY KEY,
    mime       VARCHAR(50)  NOT NULL,
    data       MEDIUMBLOB   NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
