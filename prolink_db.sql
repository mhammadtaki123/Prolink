-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Dec 14, 2025 at 11:40 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `prolink_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `email`, `password`, `created_at`) VALUES
(2, 'Admin_mhammad', 'Admin_mhammad@gmail.com', '$2y$10$Kk/m/atw.fxHRuksKgvoTOMSm3AkrR1Eqg/WLqqJPdQTD.KxrEHsW', '2025-10-27 17:26:27');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `status` enum('pending','accepted','completed','cancelled') DEFAULT 'pending',
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `scheduled_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `user_id`, `worker_id`, `service_id`, `status`, `booking_date`, `scheduled_at`, `notes`) VALUES
(6, 1, 1, 15, 'accepted', '2025-10-31 11:47:31', NULL, NULL),
(7, 1, 1, 14, 'completed', '2025-10-31 11:47:51', NULL, NULL),
(9, 1, 1, 7, 'cancelled', '2025-10-31 13:31:35', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `subject` varchar(190) NOT NULL,
  `message` text NOT NULL,
  `status` enum('new','read','archived') NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `status`, `created_at`) VALUES
(1, 'Mhammad', 'User_mhammad@gmail.com', 'feedback', 'Testing Contact form', 'new', '2025-11-07 14:02:13');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `sender_role` enum('user','worker') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_role` enum('user','worker') NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `recipient_role` enum('user','worker','admin') NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `recipient_role`, `recipient_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 'user', 1, 'Booking #7 Status Updated', 'The status of your booking for \'Electricalian\' has been changed to \'pending\' by the admin.', 1, '2025-10-31 11:49:44'),
(2, 'worker', 1, 'Booking #7 Status Updated', 'The status of your booking for \'Electricalian\' has been changed to \'pending\' by the admin.', 1, '2025-10-31 11:49:44'),
(3, 'user', 1, 'Booking #6 Status Updated', 'The status of your booking for \'Plumbing\' has been changed to \'accepted\' by the admin.', 1, '2025-10-31 11:49:46'),
(4, 'worker', 1, 'Booking #6 Status Updated', 'The status of your booking for \'Plumbing\' has been changed to \'accepted\' by the admin.', 1, '2025-10-31 11:49:46'),
(5, 'user', 1, 'Booking #6 Status Updated', 'The status of your booking for \'Plumbing\' has been changed to \'completed\' by the admin.', 1, '2025-10-31 11:49:47'),
(6, 'worker', 1, 'Booking #6 Status Updated', 'The status of your booking for \'Plumbing\' has been changed to \'completed\' by the admin.', 1, '2025-10-31 11:49:47'),
(7, 'user', 1, 'Booking #6 Status Updated', 'The status of your booking for \'Plumbing\' has been changed to \'pending\' by the admin.', 1, '2025-10-31 11:49:48'),
(8, 'worker', 1, 'Booking #6 Status Updated', 'The status of your booking for \'Plumbing\' has been changed to \'pending\' by the admin.', 1, '2025-10-31 11:49:48'),
(9, 'user', 1, 'Booking #7 Status Updated', 'The status of your booking for \'Electricalian\' has been changed to \'completed\' by the admin.', 1, '2025-10-31 11:49:49'),
(10, 'worker', 1, 'Booking #7 Status Updated', 'The status of your booking for \'Electricalian\' has been changed to \'completed\' by the admin.', 1, '2025-10-31 11:49:49'),
(11, 'user', 1, 'Booking #7 Status Updated', 'The status of your booking for \'Electricalian\' has been changed to \'accepted\' by the admin.', 1, '2025-10-31 11:49:50'),
(12, 'worker', 1, 'Booking #7 Status Updated', 'The status of your booking for \'Electricalian\' has been changed to \'accepted\' by the admin.', 1, '2025-10-31 11:49:50'),
(13, 'user', 1, 'Booking #7 Status Updated', 'The status of your booking for \'Electricalian\' has been changed to \'pending\' by the admin.', 1, '2025-10-31 11:49:54'),
(14, 'worker', 1, 'Booking #7 Status Updated', 'The status of your booking for \'Electricalian\' has been changed to \'pending\' by the admin.', 1, '2025-10-31 11:49:54'),
(15, 'user', 1, 'Booking #6 Status Updated', 'The status of your booking for \'Plumbing\' has been changed to \'pending\' by the admin.', 1, '2025-10-31 11:49:55'),
(16, 'worker', 1, 'Booking #6 Status Updated', 'The status of your booking for \'Plumbing\' has been changed to \'pending\' by the admin.', 1, '2025-10-31 11:49:55'),
(17, 'user', 1, 'Booking #9 Status Updated', 'The status of your booking for \'Lawn Mowing\' has been changed to \'cancelled\' by the admin.', 1, '2025-10-31 13:48:52'),
(18, 'worker', 1, 'Booking #9 Status Updated', 'The status of your booking for \'Lawn Mowing\' has been changed to \'cancelled\' by the admin.', 1, '2025-10-31 13:48:52'),
(19, 'worker', 1, 'New service submitted', 'You added “svd”. An admin can activate it.', 1, '2025-11-04 08:51:21');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` enum('cash','card') NOT NULL,
  `status` enum('completed','failed') NOT NULL DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `booking_id`, `user_id`, `worker_id`, `amount`, `method`, `status`, `created_at`) VALUES
(1, 7, 1, 1, 90.00, 'cash', 'completed', '2025-12-12 09:12:51');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `location` varchar(150) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `worker_id`, `title`, `description`, `category`, `price`, `location`, `status`, `created_at`) VALUES
(7, 1, 'Lawn Mowing', 'Professional lawn mowing and yard maintenance for residential and commercial properties.', 'Gardening', 25.00, 'Beirut, Lebanon', 'active', '2025-10-31 11:42:23'),
(8, 1, 'Appliance Repair', 'Fast and reliable repair services for home appliances including fridges, washers, and ovens.', 'General Repair', 40.00, 'Beirut, Lebanon', 'active', '2025-10-31 11:42:57'),
(9, 1, 'Cleaning Services', 'Comprehensive house cleaning, deep cleaning, and move-in/move-out cleaning packages.', 'Cleaning', 20.00, 'Downtown Beirut, Lebanon', 'active', '2025-10-31 11:43:25'),
(10, 1, 'Electrical Troubleshooting', 'Licensed electrician to diagnose and fix electrical issues safely and efficiently.', 'Electrician', 35.00, 'Tripoli, Lebanon', 'active', '2025-10-31 11:43:59'),
(11, 1, 'Plumbing Repairs', 'Leak repair, faucet replacement, and emergency plumbing services.', 'Plumbing', 30.00, 'Sidon, Lebanon', 'active', '2025-10-31 11:44:25'),
(12, 1, 'Cleaning Services', 'Residential and commercial cleaning, window washing, and sanitization.', 'Cleaning', 70.00, 'Dubai, United Arab Emirates', 'active', '2025-10-31 11:44:59'),
(13, 1, 'Gardening', 'Landscaping, garden maintenance, and irrigation system setup.', 'Gardening', 120.00, 'Abu Dhabi, United Arab Emirates', 'active', '2025-10-31 11:45:23'),
(14, 1, 'Electricalian', 'Licensed electrician for wiring, panel upgrades, and electrical safety assessments.', 'Electrician', 90.00, 'Dubai, United Arab Emirates', 'active', '2025-10-31 11:45:47'),
(15, 1, 'Plumbing', 'Plumbing installations and emergency repairs, clogged drains, and water heater service.', 'Plumbing', 80.00, 'Sharjah, United Arab Emirates', 'active', '2025-10-31 11:46:12');

-- --------------------------------------------------------

--
-- Table structure for table `service_images`
--

CREATE TABLE `service_images` (
  `image_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_images`
--

INSERT INTO `service_images` (`image_id`, `service_id`, `file_path`, `caption`, `created_at`) VALUES
(3, 15, '/Prolink/uploads/services/15/Plumbing-image-1762685453.jpeg', '', '2025-11-09 10:50:53'),
(4, 14, '/Prolink/uploads/services/14/Electricalian-image-1762685504.jpeg', 'Electricalian work', '2025-11-09 10:51:44');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `phone`, `address`, `created_at`) VALUES
(1, 'User_Mhammad', 'User_mhammad@gmail.com', '$2y$10$Onoi3DX7r8Ww1TQFY0k0S.IjOtd7MiE68i2cJlXQwf19L81hs5wh2', NULL, NULL, '2025-10-27 17:24:08');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_user_payments`
-- (See below for the actual view)
--
CREATE TABLE `vw_user_payments` (
`payment_id` int(11)
,`booking_id` int(11)
,`user_id` int(11)
,`worker_id` int(11)
,`amount` decimal(10,2)
,`method` enum('cash','card')
,`status` enum('completed','failed')
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_worker_wallet`
-- (See below for the actual view)
--
CREATE TABLE `vw_worker_wallet` (
`worker_id` int(11)
,`total_earned` decimal(32,2)
,`completed_payments` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `workers`
--

CREATE TABLE `workers` (
  `worker_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `skill_category` varchar(100) DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `rating` float DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workers`
--

INSERT INTO `workers` (`worker_id`, `full_name`, `email`, `password`, `phone`, `skill_category`, `hourly_rate`, `bio`, `rating`, `created_at`, `status`) VALUES
(1, 'Worker_Mhammad', 'Worker_mhammad@gmail.com', '$2y$10$wf1Qq2bVwyJU26sIKpEti.4einQba7MTZBZcOEFNTFQ7qp0IZXX/O', NULL, 'Outdoor', 10.00, 'Skilled in pool work', 0, '2025-10-27 17:27:31', 'active');

-- --------------------------------------------------------

--
-- Structure for view `vw_user_payments`
--
DROP TABLE IF EXISTS `vw_user_payments`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_user_payments`  AS SELECT `p`.`payment_id` AS `payment_id`, `p`.`booking_id` AS `booking_id`, `p`.`user_id` AS `user_id`, `p`.`worker_id` AS `worker_id`, `p`.`amount` AS `amount`, `p`.`method` AS `method`, `p`.`status` AS `status`, `p`.`created_at` AS `created_at` FROM `payments` AS `p` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_worker_wallet`
--
DROP TABLE IF EXISTS `vw_worker_wallet`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_worker_wallet`  AS SELECT `p`.`worker_id` AS `worker_id`, coalesce(sum(case when `p`.`status` = 'completed' then `p`.`amount` else 0 end),0) AS `total_earned`, count(case when `p`.`status` = 'completed' then 1 end) AS `completed_payments` FROM `payments` AS `p` GROUP BY `p`.`worker_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `worker_id` (`worker_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `recipient_role` (`recipient_role`,`recipient_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `uq_payments_booking` (`booking_id`),
  ADD KEY `idx_payments_user` (`user_id`),
  ADD KEY `idx_payments_worker` (`worker_id`),
  ADD KEY `idx_payments_created` (`created_at`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `uq_review_booking` (`booking_id`),
  ADD KEY `idx_worker` (`worker_id`),
  ADD KEY `idx_service` (`service_id`),
  ADD KEY `fk_reviews_user` (`user_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `fk_worker_services` (`worker_id`);

--
-- Indexes for table `service_images`
--
ALTER TABLE `service_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `fk_service_images_service` (`service_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `workers`
--
ALTER TABLE `workers`
  ADD PRIMARY KEY (`worker_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `service_images`
--
ALTER TABLE `service_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `workers`
--
ALTER TABLE `workers`
  MODIFY `worker_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`worker_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_reviews_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_worker` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`worker_id`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `fk_worker_services` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`worker_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`worker_id`) ON DELETE CASCADE;

--
-- Constraints for table `service_images`
--
ALTER TABLE `service_images`
  ADD CONSTRAINT `fk_service_images_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
