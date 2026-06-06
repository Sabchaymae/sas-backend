-- ============================================
-- Oriotel ERP — Database Initialization
-- One database per microservice (isolation)
-- ============================================

CREATE DATABASE IF NOT EXISTS `oriotel1_identity`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `oriotel1_operations`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `oriotel1_communication`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `oriotel1_subscription`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant privileges to the application user
GRANT ALL PRIVILEGES ON `oriotel1_identity`.*       TO 'oriotel1'@'%';
GRANT ALL PRIVILEGES ON `oriotel1_operations`.*     TO 'oriotel1'@'%';
GRANT ALL PRIVILEGES ON `oriotel1_communication`.*  TO 'oriotel1'@'%';
GRANT ALL PRIVILEGES ON `oriotel1_subscription`.*   TO 'oriotel1'@'%';

FLUSH PRIVILEGES;
