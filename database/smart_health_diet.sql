-- ======================================================
-- Smart Health & Diet Recommendation System
-- Database Schema: smart_health_diet
-- Built for MySQL (XAMPP / phpMyAdmin / PHP PDO)
-- ======================================================

CREATE DATABASE IF NOT EXISTS `smart_health_diet` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `smart_health_diet`;

-- ------------------------------------------------------
-- 1. Users Table
-- ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(30) NULL,
    `role` ENUM('admin', 'dietitian', 'user') NOT NULL DEFAULT 'user',
    `status` ENUM('active', 'inactive', 'suspended', 'pending') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------
-- 2. Dietitian Profiles Table
-- ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dietitian_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `qualification` VARCHAR(255) NULL,
    `certification` VARCHAR(255) NULL,
    `experience` INT NULL DEFAULT 0,
    `specialization` VARCHAR(255) NULL,
    `approval_status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `rejection_reason` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------
-- 3. Health Profiles Table (User Biometrics & Goals)
-- ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `health_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `age` INT NULL,
    `gender` ENUM('male', 'female', 'other') NULL,
    `height` DECIMAL(5,2) NULL, -- in cm
    `weight` DECIMAL(5,2) NULL, -- in kg
    `activity_level` ENUM('sedentary', 'light', 'moderate', 'very_active', 'extra_active') DEFAULT 'sedentary',
    `health_goal` ENUM('weight_loss', 'weight_gain', 'maintain_weight', 'general_fitness') DEFAULT 'maintain_weight',
    `dietary_preference` ENUM('anything', 'vegetarian', 'vegan', 'keto', 'halal', 'paleo') DEFAULT 'anything',
    `daily_calorie_target` INT NULL DEFAULT 2000,
    `bmi` DECIMAL(4,1) NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------
-- 4. Food Items Table
-- ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `food_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `food_name` VARCHAR(150) NOT NULL,
    `serving_size` VARCHAR(100) NOT NULL DEFAULT '100g',
    `serving_weight_g` DECIMAL(6,2) NOT NULL DEFAULT 100.00,
    `calories` DECIMAL(6,1) NOT NULL,
    `protein` DECIMAL(6,1) NOT NULL DEFAULT 0.0,
    `carbohydrates` DECIMAL(6,1) NOT NULL DEFAULT 0.0,
    `fat` DECIMAL(6,1) NOT NULL DEFAULT 0.0,
    `fiber` DECIMAL(6,1) NOT NULL DEFAULT 0.0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_food_name` (`food_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------
-- 5. Meal Logs Table
-- ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `meal_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `food_id` INT NULL,
    `custom_food_name` VARCHAR(150) NULL,
    `meal_type` ENUM('Breakfast', 'Lunch', 'Dinner', 'Snack') NOT NULL,
    `quantity` DECIMAL(6,2) NOT NULL DEFAULT 1.0,
    `calories` DECIMAL(6,1) NOT NULL,
    `protein` DECIMAL(6,1) DEFAULT 0.0,
    `carbohydrates` DECIMAL(6,1) DEFAULT 0.0,
    `fat` DECIMAL(6,1) DEFAULT 0.0,
    `meal_date` DATE NOT NULL,
    `meal_time` TIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`food_id`) REFERENCES `food_items`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_meal_date` (`user_id`, `meal_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------
-- 6. Water Logs Table
-- ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `water_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `amount_ml` INT NOT NULL,
    `log_date` DATE NOT NULL,
    `log_time` TIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_water_date` (`user_id`, `log_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------
-- 7. Sleep Logs Table
-- ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sleep_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `sleep_date` DATE NOT NULL,
    `bedtime` TIME NOT NULL,
    `wake_time` TIME NOT NULL,
    `duration_minutes` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_sleep_date` (`user_id`, `sleep_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------
-- 8. Weight Logs Table
-- ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `weight_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `weight` DECIMAL(5,2) NOT NULL,
    `log_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_weight_date` (`user_id`, `log_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------
-- 9. Meal Plans Table
-- ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `meal_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `dietitian_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `goal` VARCHAR(150) NULL,
    `status` ENUM('active', 'completed', 'draft') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`dietitian_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------
-- 10. Meal Plan Items Table
-- ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `meal_plan_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `meal_plan_id` INT NOT NULL,
    `meal_type` ENUM('Breakfast', 'Morning Snack', 'Lunch', 'Afternoon Snack', 'Dinner') NOT NULL,
    `food_id` INT NULL,
    `custom_food_name` VARCHAR(150) NULL,
    `quantity` VARCHAR(100) NOT NULL,
    `calories` DECIMAL(6,1) NOT NULL DEFAULT 0.0,
    `notes` TEXT NULL,
    FOREIGN KEY (`meal_plan_id`) REFERENCES `meal_plans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`food_id`) REFERENCES `food_items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------
-- 11. Recommendations Table
-- ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recommendations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `dietitian_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `recommendation` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`dietitian_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------
-- 12. Messages Table (Simple User <-> Dietitian Chat)
-- ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sender_id` INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_conversation` (`sender_id`, `receiver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------
-- 13. Resources / Nutritional Guides Table
-- ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `resources` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `dietitian_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `category` VARCHAR(100) NOT NULL DEFAULT 'Nutrition Guide',
    `file_path` VARCHAR(255) NOT NULL,
    `file_size_kb` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`dietitian_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- Initial Seed Data: Admin, Approved Dietitian, Pending Dietitian, Regular User
-- Passwords:
-- Admin: admin123
-- Dietitian: dietitian123
-- User: user123
-- ======================================================

INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `role`, `status`) VALUES
(1, 'System Administrator', 'admin@healthtrack.com', '$2y$10$eUIqT3R6StKa/6rVD7FxTOV33nUL/13oXzZ/9YQXaaDg0DTTpGkPe', '+1234567890', 'admin', 'active'),
(2, 'Dr. Sarah Miller', 'sarah@healthtrack.com', '$2y$10$kh/Dqh2go5ejR7GERNA.C.1eBJuBwt7Nc1zSX93.ioS/ZMr/yYQEC', '+1987654321', 'dietitian', 'active'),
(3, 'Dr. James Wilson', 'james@healthtrack.com', '$2y$10$kh/Dqh2go5ejR7GERNA.C.1eBJuBwt7Nc1zSX93.ioS/ZMr/yYQEC', '+1555123456', 'dietitian', 'pending'),
(4, 'Alice Johnson', 'alice@example.com', '$2y$10$fbZDzAnlb0U6W5j4yfmLTusRieGKjEiJP3tRSezAnJKTvMDpJWhBu', '+1444555666', 'user', 'active')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Dietitian profile for Dr. Sarah Miller (Approved)
INSERT INTO `dietitian_profiles` (`id`, `user_id`, `qualification`, `certification`, `experience`, `specialization`, `approval_status`) VALUES
(1, 2, 'M.Sc. Clinical Nutrition, RD', 'Certified Sports Nutritionist (CSN)', 8, 'Weight Management & Sports Nutrition', 'approved')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Dietitian profile for Dr. James Wilson (Pending Approval)
INSERT INTO `dietitian_profiles` (`id`, `user_id`, `qualification`, `certification`, `experience`, `specialization`, `approval_status`) VALUES
(2, 3, 'Ph.D. Nutritional Science', 'Licensed Dietitian Nutritionist (LDN)', 5, 'Diabetes & Cardiovascular Diets', 'pending')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Health Profile for Alice Johnson
INSERT INTO `health_profiles` (`id`, `user_id`, `age`, `gender`, `height`, `weight`, `activity_level`, `health_goal`, `dietary_preference`, `daily_calorie_target`, `bmi`) VALUES
(1, 4, 28, 'female', 165.00, 61.00, 'moderate', 'weight_loss', 'anything', 1850, 22.4)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed starter food items
INSERT INTO `food_items` (`food_name`, `serving_size`, `serving_weight_g`, `calories`, `protein`, `carbohydrates`, `fat`, `fiber`) VALUES
('White Rice (Cooked)', '100g', 100.00, 130.0, 2.7, 28.2, 0.3, 0.4),
('Chicken Breast (Grilled)', '100g', 100.00, 165.0, 31.0, 0.0, 3.6, 0.0),
('Boiled Egg (Large)', '1 egg (50g)', 50.00, 78.0, 6.3, 0.6, 5.3, 0.0),
('Oatmeal with Almond Milk', '1 bowl (200g)', 200.00, 240.0, 8.0, 42.0, 5.0, 6.0),
('Greek Yogurt (Non-fat)', '1 cup (150g)', 150.00, 90.0, 15.0, 6.0, 0.0, 0.0),
('Banana', '1 medium (118g)', 118.00, 105.0, 1.3, 27.0, 0.3, 3.1),
('Salmon Fillet (Baked)', '100g', 100.00, 208.0, 20.4, 0.0, 13.4, 0.0),
('Steamed Broccoli', '100g', 100.00, 35.0, 2.4, 7.2, 0.4, 2.6),
('Almonds', '1 handful (30g)', 30.00, 170.0, 6.0, 6.0, 15.0, 3.5),
('Apple', '1 medium (182g)', 182.00, 95.0, 0.5, 25.0, 0.3, 4.4)
ON DUPLICATE KEY UPDATE `id`=`id`;
