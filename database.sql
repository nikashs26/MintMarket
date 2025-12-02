-- MintMarket Database Schema

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `mintmarket`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `wallet_address` VARCHAR(66) UNIQUE NOT NULL,
    `balance` DECIMAL(18, 8) DEFAULT 100.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_wallet` (`wallet_address`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `nfts`
--

CREATE TABLE IF NOT EXISTS `nfts` (
    `nft_id` INT PRIMARY KEY AUTO_INCREMENT,
    `token_id` VARCHAR(66) UNIQUE NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `image_url` VARCHAR(500) NOT NULL,
    `creator_id` INT NOT NULL,
    `current_owner_id` INT NOT NULL,
    `royalty_percentage` DECIMAL(5, 2) DEFAULT 10.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_transfer_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`creator_id`) REFERENCES `users`(`user_id`),
    FOREIGN KEY (`current_owner_id`) REFERENCES `users`(`user_id`),
    INDEX `idx_creator` (`creator_id`),
    INDEX `idx_owner` (`current_owner_id`),
    INDEX `idx_token` (`token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE IF NOT EXISTS `transactions` (
    `transaction_id` INT PRIMARY KEY AUTO_INCREMENT,
    `block_hash` VARCHAR(64) NOT NULL,
    `previous_hash` VARCHAR(64),
    `nft_id` INT,
    `from_user_id` INT,
    `to_user_id` INT,
    `transaction_type` ENUM('mint', 'transfer', 'sale') NOT NULL,
    `amount` DECIMAL(18, 8),
    `royalty_amount` DECIMAL(18, 8) DEFAULT 0,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `nonce` INT NOT NULL,
    
    FOREIGN KEY (`nft_id`) REFERENCES `nfts`(`nft_id`),
    FOREIGN KEY (`from_user_id`) REFERENCES `users`(`user_id`),
    FOREIGN KEY (`to_user_id`) REFERENCES `users`(`user_id`),
    INDEX `idx_block` (`block_hash`),
    INDEX `idx_nft` (`nft_id`),
    INDEX `idx_type` (`transaction_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `listings`
--

CREATE TABLE IF NOT EXISTS `listings` (
    `listing_id` INT PRIMARY KEY AUTO_INCREMENT,
    `nft_id` INT NOT NULL,
    `seller_id` INT NOT NULL,
    `price` DECIMAL(18, 8) NOT NULL,
    `status` ENUM('active', 'sold', 'cancelled') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`nft_id`) REFERENCES `nfts`(`nft_id`),
    FOREIGN KEY (`seller_id`) REFERENCES `users`(`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_nft` (`nft_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
