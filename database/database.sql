CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    firstName VARCHAR(30) NOT NULL,
    lastName VARCHAR(30) NOT NULL,
    userPassword TEXT NOT NULL,
    email VARCHAR(50) UNIQUE NOT NULL,
    contact VARCHAR(20) NOT NULL,
    country VARCHAR(40),
    gender CHAR(1) CHECK(gender IN ('M', 'F', 'O')), -- M = Male, F = Female, O = Other
    birthDate DATE
);

CREATE TABLE roles (
    role_id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_name TEXT UNIQUE NOT NULL CHECK(role_name IN ('freelancer', 'client', 'admin'))
);

CREATE TABLE user_roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    role_id INTEGER NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
    UNIQUE(user_id, role_id) -- prevent duplicate role entries for the same user
);

CREATE TABLE freelancer_roles (
    role_id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE services (
    service_id INTEGER PRIMARY KEY AUTOINCREMENT,
    freelancer_id INTEGER NOT NULL,
    role_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    category TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    delivery_time INTEGER NOT NULL, -- in days
    image_url TEXT,
    FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES freelancer_roles(role_id) ON DELETE SET NULL
);

CREATE TABLE freelancer_service_roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_id INTEGER NOT NULL,
    service_id INTEGER NOT NULL,
    FOREIGN KEY (role_id) REFERENCES freelancer_roles(role_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE CASCADE,
    UNIQUE(role_id, service_id)
);

CREATE TABLE Dish (
    dish_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    category_id INTEGER NOT NULL,
    service_id INTEGER NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    photo TEXT,
    PreparationTime INTEGER CHECK(PreparationTime > 0), -- Time in minutes
    Preparation TEXT,
    FOREIGN KEY (category_id) REFERENCES Categories(category_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE CASCADE
);

CREATE TABLE Categories (
    category_id INTEGER PRIMARY KEY AUTOINCREMENT,
    categoryName VARCHAR(50) UNIQUE NOT NULL
);

CREATE TABLE Orders (
    order_id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    dish_id INTEGER NOT NULL,
    service_id INTEGER NOT NULL,
    quantity INTEGER CHECK(quantity > 0) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status TEXT CHECK(status IN ('pending', 'preparing', 'delivered', 'cancelled')) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (dish_id) REFERENCES Dish(dish_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE CASCADE
);

/*
Bonus: Keep Data Consistent
You might want to ensure the dish_id actually belongs to the service_id. 
This requires logic in your application (e.g., when placing an order, check that the selected dish is from the chosen service).
SQLite doesn't support complex cross-table constraints, so this part goes in your backend logic (PHP). 
*/

CREATE TABLE transactions (
    transaction_id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    client_id INTEGER NOT NULL,
    total_price DECIMAL(10,2) NOT NULL, -- Store at time of transaction
    payment_method TEXT CHECK(payment_method IN ('credit_card', 'paypal', 'bank_transfer', 'cash')) DEFAULT 'cash',
    status TEXT CHECK(status IN ('pending', 'completed', 'cancelled')) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES Orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE reviews (
    review_id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_id INTEGER NOT NULL,
    client_id INTEGER NOT NULL,
    rating INTEGER CHECK(rating BETWEEN 1 AND 5) NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- When review was made
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE messages (
    message_id INTEGER PRIMARY KEY AUTOINCREMENT,
    sender_id INTEGER NOT NULL,
    receiver_id INTEGER NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE, -- Track if message has been read
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO freelancer_roles (roleName) VALUES ('Cooker'), ('Cleaner'), ('Barman'), ('Servant');

INSERT INTO roles (role_name) VALUES ('freelancer'), ('client'), ('admin');
