-- ============================================
-- Oriotel ERP — Database Initialization
-- One database per microservice (isolation)
-- ============================================

CREATE DATABASE IF NOT EXISTS `oriotel_identity`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `oriotel_operations`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `oriotel_communication`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `oriotel_subscription`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant privileges to the application user
GRANT ALL PRIVILEGES ON `oriotel_identity`.*       TO 'oriotel'@'%';
GRANT ALL PRIVILEGES ON `oriotel_operations`.*     TO 'oriotel'@'%';
GRANT ALL PRIVILEGES ON `oriotel_communication`.*  TO 'oriotel'@'%';
GRANT ALL PRIVILEGES ON `oriotel_subscription`.*   TO 'oriotel'@'%';

FLUSH PRIVILEGES;
