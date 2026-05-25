<?php
require 'db.php';

$sql = "
CREATE TABLE IF NOT EXISTS zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'finance_officer', 'field_agent') DEFAULT 'field_agent',
    zone_id INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES zones(id)
);

CREATE TABLE IF NOT EXISTS businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    owner_name VARCHAR(255) NOT NULL,
    gps_lat DECIMAL(10,8),
    gps_lng DECIMAL(11,8),
    zone_id INT,
    levy_type VARCHAR(255),
    fee_amount DECIMAL(10,2),
    status ENUM('paid', 'unpaid') DEFAULT 'unpaid',
    FOREIGN KEY (zone_id) REFERENCES zones(id)
);

CREATE TABLE IF NOT EXISTS collections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    agent_id INT,
    amount DECIMAL(10,2),
    receipt_number VARCHAR(50) UNIQUE,
    gps_lat DECIMAL(10,8),
    gps_lng DECIMAL(11,8),
    collected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id),
    FOREIGN KEY (agent_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS reconciliations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    collection_id INT,
    finance_officer_id INT,
    status ENUM('pending', 'verified', 'suspicious') DEFAULT 'pending',
    confirmed_amount DECIMAL(10,2),
    bank_slip_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (collection_id) REFERENCES collections(id),
    FOREIGN KEY (finance_officer_id) REFERENCES users(id)
);
";

try {
    $pdo->exec($sql);
    
    // Seed Admin if not exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['admin@ratepoint.com']);
    if (!$stmt->fetch()) {
        $pass = password_hash('password', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)")
            ->execute(['Super Admin', 'admin@ratepoint.com', $pass, 'super_admin']);
    }

    echo "Database setup successfully! Access the login page now.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
