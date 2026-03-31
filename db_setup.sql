CREATE DATABASE IF NOT EXISTS devoult_db;
USE devoult_db;

CREATE TABLE IF NOT EXISTS countries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS cities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    country_id INT,
    FOREIGN KEY (country_id) REFERENCES countries(id)
);

CREATE TABLE IF NOT EXISTS states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    city_id INT,
    FOREIGN KEY (city_id) REFERENCES cities(id)
);

CREATE TABLE IF NOT EXISTS main_forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    zipcode VARCHAR(6) NOT NULL,
    country_id INT NOT NULL,
    city_id INT NOT NULL,
    state_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (country_id) REFERENCES countries(id),
    FOREIGN KEY (city_id) REFERENCES cities(id),
    FOREIGN KEY (state_id) REFERENCES states(id)
);

CREATE TABLE IF NOT EXISTS form_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    qty INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (form_id) REFERENCES main_forms(id) ON DELETE CASCADE
);

-- Insert sample data
INSERT INTO countries (name) VALUES ('India'), ('USA') ON DUPLICATE KEY UPDATE name=name;

INSERT INTO cities (name, country_id) VALUES 
('Mumbai', 1), ('Delhi', 1), ('New York', 2), ('Los Angeles', 2);

INSERT INTO states (name, city_id) VALUES 
('Maharashtra', 1), ('Delhi', 2), ('New York', 3), ('California', 4);

