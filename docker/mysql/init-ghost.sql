-- Create Ghost database
CREATE DATABASE IF NOT EXISTS ghost_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create Ghost user and grant privileges
CREATE USER IF NOT EXISTS 'ghost_user'@'%' IDENTIFIED BY 'ghost_password';
GRANT ALL PRIVILEGES ON ghost_db.* TO 'ghost_user'@'%';
FLUSH PRIVILEGES;
