-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Erstellungszeit: 05. Okt 2025 um 13:29
-- Server-Version: 8.0.35
-- PHP-Version: 8.2.20

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `admidio_50`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_announcements`
--

CREATE TABLE `%PREFIX%_announcements` (
  `ann_id` int UNSIGNED NOT NULL,
  `ann_cat_id` int UNSIGNED NOT NULL,
  `ann_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ann_headline` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ann_description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `ann_usr_id_create` int UNSIGNED DEFAULT NULL,
  `ann_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ann_usr_id_change` int UNSIGNED DEFAULT NULL,
  `ann_timestamp_change` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_announcements`
--

INSERT INTO `%PREFIX%_announcements` (`ann_id`, `ann_cat_id`, `ann_uuid`, `ann_headline`, `ann_description`, `ann_usr_id_create`, `ann_timestamp_create`, `ann_usr_id_change`, `ann_timestamp_change`) VALUES
(1, 13, 'e49d66f4-0546-4a23-bb57-27eb2b97d271', 'New jerseys', 'Starting next season, there are new jerseys for all active players. These can be picked up before the first training at the trainer.', 1, '2025-08-30 07:12:34', NULL, NULL),
(2, 13, 'e84aae2a-7e1d-4f91-b2e1-ead4bac900ed', 'Aerobics course', 'During the holidays we offer a <i>aerobic course</i> to all interested members.<br /><br />Registrations are accepted on our <b>homepage</b> or in our <b>office</b>.', 1, '2025-08-30 09:30:59', 1, '2025-09-25 17:21:32'),
(3, 300, '934346cc-123c-4162-9506-86b07c6c08ce', 'Welcome to the demo area', '<p>In this area you can play around with Admidio and see whether the program\'s functions meet your needs.</p><p>We have also provided some test data so that you can see in the individual modules how this could look later on your site. However, emails are not actually sent in the demo area so that this function cannot be abused. You are welcome to play with this installation.</p><p>We have created a few test accounts with different rights:</p><p><span style=\"color:#008080;\"><strong>Administrator</strong></span></p><table border=\"0\" cellpadding=\"1\" cellspacing=\"1\" style=\"width: 100%;\"><tbody><tr><td>Username:</td><td><strong>Admin</strong></td></tr><tr><td>Password:</td><td><strong>Admidio</strong></td></tr><tr><td>Rights:</td><td>Can see and edit everything. More rights are not possible :)</td></tr></tbody></table><p><span style=\"color:#008080;\"><strong>Chairman</strong></span></p><table border=\"0\" cellpadding=\"1\" cellspacing=\"1\" style=\"width: 100%;\"><tbody><tr><td>Username:</td><td><strong>Chairman</strong></td></tr><tr><td>Password:</td><td><strong>Admidio</strong></td></tr><tr><td>Rights:</td><td>Can edit and view everything, except assigning roles and changing program/module settings.</td></tr></tbody></table><p><span style=\"color:#008080;\"><strong>Member</strong></span></p><table border=\"0\" cellpadding=\"1\" cellspacing=\"1\" style=\"width: 100%;\"><tbody><tr><td>Username:</td><td><strong>Member</strong></td></tr><tr><td>Password:</td><td><strong>Admidio</strong></td></tr><tr><td>Rechte:</td><td>Can edit his profile and view lists of roles, where he is a member.</td></tr></tbody></table><p>Have fun trying !<br />The Admidio Team</p>', 1, '2025-09-16 22:15:33', NULL, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_auto_login`
--

CREATE TABLE `%PREFIX%_auto_login` (
  `atl_id` int UNSIGNED NOT NULL,
  `atl_auto_login_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `atl_session_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `atl_org_id` int UNSIGNED NOT NULL,
  `atl_usr_id` int UNSIGNED NOT NULL,
  `atl_last_login` timestamp NULL DEFAULT NULL,
  `atl_number_invalid` smallint NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_auto_login`
--

INSERT INTO `%PREFIX%_auto_login` (`atl_id`, `atl_auto_login_id`, `atl_session_id`, `atl_org_id`, `atl_usr_id`, `atl_last_login`, `atl_number_invalid`) VALUES
(2, '1:PyQIIXAENPZ8KOcGp26cPGo7erNAod2Zzj8cdV7m', '', 1, 1, '2025-09-28 10:14:39', 0),
(3, '0:mcVLKSnNJ9SzcEyi30PS0PeEJo8OlEzsGwH1n2va', 'n8augeudoflh2ougitrthui096', 1, 1, '2025-10-05 13:27:49', 0),
(4, '355:LuftagL7PluArgKlsnO7v6FJsrFwo2uhqCjQxDqO', '5vg0ulq7o3qkotrkv8tsptclsd', 1, 355, '2025-10-05 12:55:01', 0),
(5, '354:dWti04JF0K111e2OWp6VIEqR0cgg5K6vggBK0fYz', 'ahl7gobab8ir0omd2ig92sa7li', 1, 354, '2025-10-05 12:56:59', 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_categories`
--

CREATE TABLE `%PREFIX%_categories` (
  `cat_id` int UNSIGNED NOT NULL,
  `cat_org_id` int UNSIGNED DEFAULT NULL,
  `cat_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `cat_type` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `cat_name_intern` varchar(110) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `cat_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `cat_system` tinyint(1) NOT NULL DEFAULT '0',
  `cat_default` tinyint(1) NOT NULL DEFAULT '0',
  `cat_sequence` smallint NOT NULL,
  `cat_usr_id_create` int UNSIGNED DEFAULT NULL,
  `cat_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cat_usr_id_change` int UNSIGNED DEFAULT NULL,
  `cat_timestamp_change` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_categories`
--

INSERT INTO `%PREFIX%_categories` (`cat_id`, `cat_org_id`, `cat_uuid`, `cat_type`, `cat_name_intern`, `cat_name`, `cat_system`, `cat_default`, `cat_sequence`, `cat_usr_id_create`, `cat_timestamp_create`, `cat_usr_id_change`, `cat_timestamp_change`) VALUES
(1, NULL, '4404540e-bdad-46b7-8c52-f30e7ab51675', 'USF', 'BASIC_DATA', 'SYS_BASIC_DATA', 1, 0, 1, 1, '2012-01-08 10:12:05', NULL, NULL),
(2, NULL, 'f8e89d85-325d-4620-a7d4-36b42ae06f45', 'USF', 'SOCIAL_NETWORKS', 'SYS_SOCIAL_NETWORKS', 0, 0, 2, 1, '2012-01-08 10:12:07', NULL, NULL),
(3, 1, 'dd483a2f-c8dd-47da-a4d1-cb97d58a62a0', 'ROL', 'COMMON', 'SYS_COMMON', 0, 0, 1, 1, '2012-01-08 10:12:05', NULL, NULL),
(4, 1, '1f3d2f16-e81d-4f63-9582-2e9e4419aba8', 'ROL', 'GROUPS', 'INS_GROUPS', 0, 1, 2, 1, '2012-01-08 10:12:05', NULL, NULL),
(5, 1, 'e5329e2d-8fc5-4cc2-994c-a5f687289754', 'ROL', 'COURSES', 'INS_COURSES', 0, 0, 3, 1, '2012-01-08 10:12:05', NULL, NULL),
(6, 1, '22a1c65c-588c-427a-939e-0ea30dad2012', 'ROL', 'TEAMS', 'INS_TEAMS', 0, 0, 4, 1, '2012-01-08 10:12:05', NULL, NULL),
(7, 1, 'c0778d6e-804b-4d19-b624-802663ebcdca', 'LNK', 'COMMON', 'SYS_COMMON', 0, 0, 2, 1, '2012-01-08 10:12:05', 2, '2025-09-27 09:00:21'),
(8, NULL, 'ad3a3cd8-3108-4df2-b08b-60aa3dad4975', 'USF', 'ADDIDIONAL_DATA', 'INS_ADDIDIONAL_DATA', 0, 0, 3, 1, '2012-01-08 10:12:05', NULL, NULL),
(9, 1, '32edc214-cb7b-42f1-a4af-7336a28ada5e', 'LNK', 'ADMIDIO', 'Admidio', 0, 1, 3, 1, '2011-04-06 20:05:20', 2, '2025-09-27 09:00:21'),
(10, 1, '67850ca7-990b-4791-a238-5fd370caa23d', 'EVT', 'COMMON', 'SYS_COMMON', 0, 1, 2, 1, '2012-01-08 10:12:05', 2, '2025-09-27 09:00:21'),
(11, 1, '22c1edc4-4af3-4098-9bb9-375341f1e6c4', 'EVT', 'COURSES', 'INS_COURSES', 0, 0, 3, 1, '2012-01-08 10:12:05', 2, '2025-09-27 09:00:21'),
(12, 1, '63573db9-9ad4-47c6-9064-ff77f53d9e6e', 'EVT', 'TRAINING', 'INS_TRAINING', 0, 0, 4, 1, '2012-01-08 10:12:05', 2, '2025-09-27 09:00:21'),
(13, 1, 'ab11ce61-471b-4f49-8735-92134b417d6e', 'ANN', 'COMMON', 'SYS_COMMON', 0, 0, 2, 1, '2012-01-08 10:12:05', 2, '2025-09-27 09:00:21'),
(14, 1, '8803b2aa-f36e-4c9b-99ed-52685b5048d7', 'ANN', 'IMPORTANT', 'SYS_IMPORTANT', 0, 0, 3, 1, '2012-01-08 10:12:05', 2, '2025-09-27 09:00:21'),
(100, 2, '6beea24e-58b7-4668-8ac6-6ccc625cc989', 'ROL', 'COMMON', 'SYS_COMMON', 0, 0, 1, 1, '2012-01-08 10:12:05', NULL, NULL),
(101, 2, '93456821-ec9f-4ca0-a645-9bb1433985ac', 'ROL', 'GROUPS', 'INS_GROUPS', 0, 0, 2, 1, '2012-01-08 10:12:05', NULL, NULL),
(102, 2, 'cd47a540-d0e1-4cac-8ef9-e1c64880dedd', 'ROL', 'COURSES', 'INS_COURSES', 0, 0, 3, 1, '2012-01-08 10:12:05', NULL, NULL),
(103, 2, '160819d4-d192-4120-82f7-88e565e2bf18', 'ROL', 'TEAMS', 'INS_TEAMS', 0, 0, 4, 1, '2012-01-08 10:12:05', NULL, NULL),
(104, 2, '9bf1a1ea-69b4-4226-aa55-2517455ed32d', 'LNK', 'COMMON', 'SYS_COMMON', 0, 0, 2, 1, '2012-01-08 10:12:05', 2, '2025-09-27 09:00:21'),
(105, 2, '9a4b3de1-3cab-40db-97f6-77719c731f01', 'LNK', 'ADMIDIO', 'Admidio', 0, 0, 3, 1, '2011-04-06 20:05:20', 2, '2025-09-27 09:00:21'),
(106, 2, '1141cb37-7e15-4107-aa3f-15e1cd740860', 'EVT', 'COMMON', 'SYS_COMMON', 0, 0, 2, 1, '2012-01-08 10:12:05', 2, '2025-09-27 09:00:21'),
(107, 2, 'b1c33600-6e8a-47db-88a0-0665e7005fec', 'EVT', 'COURSES', 'INS_COURSES', 0, 0, 3, 1, '2012-01-08 10:12:05', 2, '2025-09-27 09:00:21'),
(108, 2, '0001df5c-1ef1-49da-bed2-c88152cfa792', 'EVT', 'TRAINING', 'INS_TRAINING', 0, 0, 4, 1, '2012-01-08 10:12:05', 2, '2025-09-27 09:00:21'),
(109, 2, '84205a4e-4fd7-49e4-bed9-63e3280a70d8', 'ANN', 'COMMON', 'SYS_COMMON', 0, 0, 2, 1, '2012-01-08 10:12:05', 2, '2025-09-27 09:00:21'),
(110, 2, 'c9767836-63fe-470c-abc8-1187d8143d19', 'ANN', 'IMPORTANT', 'SYS_IMPORTANT', 0, 0, 3, 1, '2012-01-08 10:12:05', 2, '2025-09-27 09:00:21'),
(200, 1, 'fff9f4cf-2368-43c4-bb3a-322008830729', 'ROL', 'EVENTS', 'SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION', 1, 0, 5, 1, '2012-01-08 10:12:05', NULL, NULL),
(201, 2, '6ee60a30-4721-4427-ac12-dc11f624c5fb', 'ROL', 'EVENTS', 'SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION', 1, 0, 5, 1, '2012-01-08 10:12:05', NULL, NULL),
(300, NULL, 'a657459f-cef8-4f68-8008-0c5e34f4bfa5', 'ANN', 'ANN_ALL_ORGANIZATIONS', 'SYS_ALL_ORGANIZATIONS', 0, 0, 1, 1, '2012-01-08 10:12:05', 2, '2025-09-27 09:00:21'),
(301, NULL, 'f4d498ba-87db-4a4e-a894-7b7f42bd4d12', 'EVT', 'EVT_ALL_ORGANIZATIONS', 'SYS_ALL_ORGANIZATIONS', 0, 0, 1, 1, '2012-01-08 10:12:05', 2, '2025-09-27 09:00:21'),
(302, NULL, 'a0fa893e-c600-4157-831e-a946cc73fb58', 'LNK', 'LNK_ALL_ORGANIZATIONS', 'SYS_ALL_ORGANIZATIONS', 0, 0, 1, 1, '2012-01-08 10:12:05', 2, '2025-09-27 09:00:21'),
(303, 1, 'ae3d3f73-a23a-4b08-8c4f-72d1251d4ca0', 'FOT', 'COMMON', 'SYS_COMMON', 0, 0, 1, 2, '2025-09-27 09:00:21', NULL, NULL),
(304, 2, 'bea45e89-b8c7-4624-b997-f2c4f69eac4c', 'FOT', 'COMMON', 'SYS_COMMON', 0, 1, 1, 2, '2025-09-27 09:00:21', NULL, NULL),
(305, 1, 'f6b2e061-4db1-411a-9371-85d1d19666da', 'IVT', 'COMMON', 'SYS_COMMON', 0, 1, 1, 2, '2025-09-27 09:00:21', NULL, NULL),
(306, 2, 'c16e3a6d-49f3-4ce7-bb0c-0d6f2b6a585f', 'IVT', 'COMMON', 'SYS_COMMON', 0, 1, 1, 2, '2025-09-27 09:00:21', NULL, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_category_report`
--

CREATE TABLE `%PREFIX%_category_report` (
  `crt_id` int UNSIGNED NOT NULL,
  `crt_org_id` int UNSIGNED DEFAULT NULL,
  `crt_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `crt_col_fields` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `crt_selection_role` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `crt_selection_cat` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `crt_number_col` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_category_report`
--

INSERT INTO `%PREFIX%_category_report` (`crt_id`, `crt_org_id`, `crt_name`, `crt_col_fields`, `crt_selection_role`, `crt_selection_cat`, `crt_number_col`) VALUES
(1, 1, 'General role assignment', 'p2,p1,p3,p5,r1', NULL, NULL, 0),
(2, 2, 'General role assignment', 'p2,p1,p3,p5,r6', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_components`
--

CREATE TABLE `%PREFIX%_components` (
  `com_id` int UNSIGNED NOT NULL,
  `com_type` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `com_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `com_name_intern` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `com_version` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `com_beta` smallint NOT NULL DEFAULT '0',
  `com_update_step` int NOT NULL DEFAULT '0',
  `com_update_completed` tinyint(1) NOT NULL DEFAULT '1',
  `com_timestamp_installed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_components`
--

INSERT INTO `%PREFIX%_components` (`com_id`, `com_type`, `com_name`, `com_name_intern`, `com_version`, `com_beta`, `com_update_step`, `com_update_completed`, `com_timestamp_installed`) VALUES
(10, 'SYSTEM', 'Admidio Core', 'CORE', '5.0.0', 1, 1700, 1700, '2025-09-27 09:00:17'),
(20, 'MODULE', 'SYS_ANNOUNCEMENTS', 'ANNOUNCEMENTS', '5.0.0', 1, 0, 1, '2025-09-27 09:00:17'),
(30, 'MODULE', 'SYS_EVENTS', 'EVENTS', '5.0.0', 1, 0, 1, '2025-09-27 09:00:17'),
(40, 'MODULE', 'SYS_MESSAGES', 'MESSAGES', '5.0.0', 1, 0, 1, '2025-09-27 09:00:17'),
(50, 'MODULE', 'SYS_GROUPS_ROLES', 'GROUPS-ROLES', '5.0.0', 1, 0, 1, '2025-09-27 09:00:17'),
(60, 'MODULE', 'SYS_CONTACTS', 'CONTACTS', '5.0.0', 1, 0, 1, '2025-09-27 09:00:17'),
(70, 'MODULE', 'SYS_DOCUMENTS_FILES', 'DOCUMENTS-FILES', '5.0.0', 1, 0, 1, '2025-09-27 09:00:17'),
(80, 'MODULE', 'SYS_PHOTOS', 'PHOTOS', '5.0.0', 1, 0, 1, '2025-09-27 09:00:17'),
(90, 'MODULE', 'SYS_CATEGORY_REPORT', 'CATEGORY-REPORT', '5.0.0', 1, 0, 1, '2025-09-27 09:00:17'),
(100, 'MODULE', 'SYS_WEBLINKS', 'LINKS', '5.0.0', 1, 0, 1, '2025-09-27 09:00:17'),
(110, 'MODULE', 'SYS_FORUM', 'FORUM', '5.0.0', 1, 0, 1, '2025-09-27 09:00:17'),
(120, 'MODULE', 'SYS_SETTINGS', 'PREFERENCES', '5.0.0', 1, 0, 1, '2025-09-27 09:00:17'),
(130, 'MODULE', 'SYS_REGISTRATION', 'REGISTRATION', '5.0.0', 1, 0, 1, '2025-09-27 09:00:17'),
(140, 'MODULE', 'SYS_MENU', 'MENU', '5.0.0', 1, 0, 1, '2025-09-27 09:00:17'),
(200, 'MODULE', 'SYS_CATEGORIES', 'CATEGORIES', '5.0.0', 1, 0, 1, '2025-09-27 09:00:17'),
(210, 'MODULE', 'SYS_PROFILE', 'PROFILE', '5.0.0', 1, 0, 1, '2025-09-27 09:00:17'),
(220, 'MODULE', 'SYS_ROOM_MANAGEMENT', 'ROOMS', '5.0.0', 1, 0, 1, '2025-09-27 09:00:17'),
(221, 'MODULE', 'SYS_ORGANIZATION', 'ORGANIZATIONS', '5.0.0', 1, 0, 1, '2025-09-27 09:00:23'),
(222, 'MODULE', 'SYS_INVENTORY', 'INVENTORY', '5.0.0', 1, 0, 1, '2025-09-27 09:00:24');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_events`
--

CREATE TABLE `%PREFIX%_events` (
  `dat_id` int UNSIGNED NOT NULL,
  `dat_cat_id` int UNSIGNED NOT NULL,
  `dat_rol_id` int UNSIGNED DEFAULT NULL,
  `dat_room_id` int UNSIGNED DEFAULT NULL,
  `dat_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `dat_begin` timestamp NULL DEFAULT NULL,
  `dat_end` timestamp NULL DEFAULT NULL,
  `dat_all_day` tinyint(1) NOT NULL DEFAULT '0',
  `dat_headline` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `dat_description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `dat_highlight` tinyint(1) NOT NULL DEFAULT '0',
  `dat_location` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `dat_country` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `dat_deadline` timestamp NULL DEFAULT NULL,
  `dat_max_members` int NOT NULL DEFAULT '0',
  `dat_usr_id_create` int UNSIGNED DEFAULT NULL,
  `dat_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dat_usr_id_change` int UNSIGNED DEFAULT NULL,
  `dat_timestamp_change` timestamp NULL DEFAULT NULL,
  `dat_allow_comments` tinyint(1) NOT NULL DEFAULT '0',
  `dat_additional_guests` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_events`
--

INSERT INTO `%PREFIX%_events` (`dat_id`, `dat_cat_id`, `dat_rol_id`, `dat_room_id`, `dat_uuid`, `dat_begin`, `dat_end`, `dat_all_day`, `dat_headline`, `dat_description`, `dat_highlight`, `dat_location`, `dat_country`, `dat_deadline`, `dat_max_members`, `dat_usr_id_create`, `dat_timestamp_create`, `dat_usr_id_change`, `dat_timestamp_change`, `dat_allow_comments`, `dat_additional_guests`) VALUES
(3, 12, NULL, NULL, 'e539f6d4-a5ac-4536-8779-df203a83ef39', '2025-12-17 14:00:00', '2025-12-17 16:00:00', 0, 'Youth training 1', 'Today we will put the focus on physical fitness and stamina.<br /><br />Please appear all in time with running shoes on the sports field!', 0, 'Sports field Norwich', 'GBR', NULL, 0, 1, '2017-07-06 15:38:26', NULL, NULL, 0, 0),
(4, 10, 8, NULL, '2bc7d168-7b4e-4ec1-9765-18989e32030c', '2026-01-08 17:00:00', '2026-01-08 21:30:00', 0, 'Barbecue', 'Today we have our barbecue. In addition to crisp sausages, chops and bacon, there are also various salads.', 1, NULL, NULL, NULL, 0, 1, '2017-07-06 15:41:18', NULL, NULL, 1, 1),
(5, 10, NULL, NULL, '10408fec-1534-4115-a83d-60681c13bcfd', '2026-01-01 23:00:00', '2026-01-22 22:59:59', 1, 'Trainer course', 'A four-day training course for youth coaches from the tennis department :)', 1, 'Youth hostel Lyon', 'FRA', NULL, 0, 1, '2017-07-06 15:49:13', NULL, NULL, 0, 0),
(6, 301, NULL, NULL, '0df388d7-b8f0-4c11-88f4-fbac697b2297', '2025-11-11 13:00:00', '2025-11-11 17:00:00', 0, 'Computer course', 'The focus of this course lies with the Office products.', 0, 'Munich Marienplatz', 'DEU', NULL, 0, 1, '2017-01-06 10:25:13', NULL, NULL, 0, 0),
(7, 301, NULL, NULL, '2a0151ef-2f03-4b6f-abe3-ce86d5a74ba8', '2025-11-08 22:00:00', '2025-11-09 21:59:59', 1, 'Trip to Amsterdam', 'On this hopefully sunny day it goes to Amsterdam.<br /><br />A canal cruise and a shopping trip are planned.', 0, 'Amsterdam Gracht', 'NLD', NULL, 0, 1, '2018-01-06 10:25:13', NULL, NULL, 0, 0),
(8, 12, NULL, NULL, '2c610a75-15e8-4ab2-9bd5-63769800d2e8', '2025-10-31 15:00:00', '2025-10-31 16:30:00', 0, 'Team training', NULL, 0, 'Sports hall Alpenstraße Salzburg', 'AUT', NULL, 0, 1, '2017-09-06 10:05:26', NULL, NULL, 0, 0),
(9, 12, NULL, NULL, '236c9f98-c826-4f42-a0e4-8421f83e11ff', '2026-01-07 15:00:00', '2026-01-07 16:30:00', 0, 'Team training', NULL, 0, 'Sports hall Alpenstraße Salzburg', 'AUT', NULL, 0, 1, '2017-09-06 10:05:26', NULL, NULL, 0, 0),
(10, 12, NULL, NULL, '9dbbb1d4-ec43-4704-b4d5-3a4f29d5dab1', '2026-01-04 15:00:00', '2026-01-04 16:30:00', 0, 'Team training', NULL, 0, 'Sports hall Alpenstraße Salzburg', 'AUT', NULL, 0, 1, '2017-09-06 10:05:26', NULL, NULL, 0, 0),
(11, 12, NULL, NULL, '86c27d41-caf3-49b6-9d68-a079c532dbe3', '2025-12-06 15:00:00', '2025-12-06 16:30:00', 0, 'Team training', NULL, 0, 'Sports hall Alpenstraße Salzburg', 'AUT', NULL, 0, 1, '2017-09-06 10:05:26', NULL, NULL, 0, 0),
(12, 12, NULL, NULL, 'fadeff52-a0e0-4ab9-8e43-a2a0578ab5ed', '2025-12-03 15:00:00', '2025-12-03 16:30:00', 0, 'Team training', NULL, 0, 'Sports hall Alpenstraße Salzburg', 'AUT', NULL, 0, 1, '2017-09-06 10:05:26', NULL, NULL, 0, 0),
(13, 12, NULL, NULL, 'cd9f4490-ddae-4949-a083-a826a12ea3d1', '2025-12-10 16:00:00', '2025-12-10 17:30:00', 0, 'Team training', NULL, 0, 'Sports hall Alpenstraße Salzburg', 'AUT', NULL, 0, 1, '2017-09-06 10:05:26', NULL, NULL, 0, 0),
(14, 107, 9, NULL, '6fa731ef-e166-49ed-bb56-182243cbc5c8', '2025-11-12 11:00:00', '2025-11-12 12:00:00', 0, 'Yoga for beginners', 'This course teaches the basics of yoga.<br /><br />A registration for this course is required.', 1, 'Madrid center', 'ESP', NULL, 0, 1, '2017-07-06 15:41:18', NULL, NULL, 0, 0),
(15, 10, 10, NULL, '7095ed97-9cf5-4247-b057-613164aaa512', '2026-01-01 18:00:00', '2026-01-01 20:00:00', 0, 'Board meeting', NULL, 0, 'Clubhouse', 'DEU', NULL, 0, 1, '2018-05-06 21:03:18', NULL, NULL, 0, 0),
(16, 10, 11, NULL, '217da340-7419-4e07-8f5f-bf037cbd2a4f', '2025-12-06 19:00:00', '2025-12-06 21:00:00', 0, 'Board meeting', NULL, 0, 'Clubhouse', 'DEU', NULL, 0, 1, '2018-05-06 21:03:18', NULL, NULL, 0, 0),
(17, 10, 12, NULL, 'b89b03a5-867b-4747-8429-981c76e0b61e', '2025-11-22 17:00:00', '2025-11-22 21:30:00', 0, 'Team evening', NULL, 0, 'Clubhouse', 'DEU', NULL, 0, 355, '2018-02-14 17:38:18', NULL, NULL, 0, 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_files`
--

CREATE TABLE `%PREFIX%_files` (
  `fil_id` int UNSIGNED NOT NULL,
  `fil_fol_id` int UNSIGNED NOT NULL,
  `fil_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `fil_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `fil_description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `fil_locked` tinyint(1) NOT NULL DEFAULT '0',
  `fil_counter` int DEFAULT NULL,
  `fil_usr_id` int UNSIGNED DEFAULT NULL,
  `fil_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_files`
--

INSERT INTO `%PREFIX%_files` (`fil_id`, `fil_fol_id`, `fil_uuid`, `fil_name`, `fil_description`, `fil_locked`, `fil_counter`, `fil_usr_id`, `fil_timestamp`) VALUES
(1, 2, '445789b2-dee8-4a95-a116-9a3f0974841c', 'gpl-1.0.txt', NULL, 0, 15, 1, '2015-08-13 06:24:45'),
(2, 2, 'ef0334bd-53e5-4dcd-8417-6cde7d460d20', 'gpl-2.0.txt', NULL, 0, 45, 1, '2015-08-13 06:24:45'),
(3, 2, 'bb7b5322-c04d-44e2-8bdb-cc539a827560', 'gpl-3.0.txt', NULL, 0, 8, 1, '2015-08-13 06:24:45'),
(4, 2, '9c856e3b-784b-461d-96b2-ae1286197211', 'lgpl-3.0.txt', NULL, 0, 0, 1, '2015-08-13 06:24:45'),
(5, 1, '2fee7e92-a38a-4129-864c-77d4c90bcf77', 'admidio-readme.md', NULL, 0, 134, 1, '2015-07-01 08:05:23'),
(6, 1, '9792023e-a74d-4a89-ab89-8a15645b94a4', 'admidio-logo.png', NULL, 0, 45, 1, '2015-07-01 08:07:23'),
(7, 3, 'd9b14196-e272-44cb-b263-6dd46d704692', '20160511_meeting.txt', NULL, 0, 45, 354, '2015-07-01 08:07:23'),
(100, 100, '2859ffec-8691-4d22-a0df-188215e7df25', 'admidio-readme.md', NULL, 0, 14, 1, '2015-07-01 08:05:23'),
(101, 100, '0213c7e4-3564-4448-ad39-8a19bc56adcb', 'admidio-logo.png', NULL, 0, 6, 1, '2015-07-01 08:07:23');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_folders`
--

CREATE TABLE `%PREFIX%_folders` (
  `fol_id` int UNSIGNED NOT NULL,
  `fol_org_id` int UNSIGNED NOT NULL,
  `fol_fol_id_parent` int UNSIGNED DEFAULT NULL,
  `fol_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `fol_type` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `fol_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `fol_description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `fol_path` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `fol_locked` tinyint(1) NOT NULL DEFAULT '0',
  `fol_public` tinyint(1) NOT NULL DEFAULT '0',
  `fol_usr_id` int UNSIGNED DEFAULT NULL,
  `fol_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_folders`
--

INSERT INTO `%PREFIX%_folders` (`fol_id`, `fol_org_id`, `fol_fol_id_parent`, `fol_uuid`, `fol_type`, `fol_name`, `fol_description`, `fol_path`, `fol_locked`, `fol_public`, `fol_usr_id`, `fol_timestamp`) VALUES
(1, 1, NULL, '80b3c79b-27d1-4cdc-b86c-d9e8d79d59eb', 'DOCUMENTS', 'documents_demo', NULL, '/%PREFIX%_my_files', 0, 1, 1, '2025-09-19 22:00:00'),
(2, 1, 1, 'b1621a3d-0cea-4cbd-94c4-fc0cde15f324', 'DOCUMENTS', 'licenses', NULL, '/%PREFIX%_my_files/documents_demo', 0, 1, 1, '2014-02-05 13:05:34'),
(3, 1, 1, '3e26f7db-a167-445b-8441-215eb63153f5', 'DOCUMENTS', 'board-meeting', NULL, '/%PREFIX%_my_files/documents_demo', 0, 0, 354, '2016-04-15 11:25:06'),
(100, 2, NULL, '63973fe1-1a26-4561-b564-b1065f2c863b', 'DOCUMENTS', 'documents_test', NULL, '/%PREFIX%_my_files', 0, 1, 1, '2014-01-01 21:35:07');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_forum_posts`
--

CREATE TABLE `%PREFIX%_forum_posts` (
  `fop_id` int UNSIGNED NOT NULL,
  `fop_fot_id` int UNSIGNED NOT NULL,
  `fop_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `fop_text` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `fop_usr_id_create` int UNSIGNED DEFAULT NULL,
  `fop_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fop_usr_id_change` int UNSIGNED DEFAULT NULL,
  `fop_timestamp_change` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_forum_posts`
--

INSERT INTO `%PREFIX%_forum_posts` (`fop_id`, `fop_fot_id`, `fop_uuid`, `fop_text`, `fop_usr_id_create`, `fop_timestamp_create`, `fop_usr_id_change`, `fop_timestamp_change`) VALUES
(4, 4, 'e01359ab-98a3-4024-ae1e-55d2dfdfa002', '<p>Hi everyone,<br>the new <strong>training plan</strong> for the upcoming season is now available in the members’ area.</p><p>We’ve adjusted some of the sessions to better fit everyone’s fitness levels and training goals.</p><p>There are also a few new activities designed for beginners who want to build endurance gradually.</p><p>I’d love to hear your thoughts — what do you think about the new structure?</p>', 355, '2025-10-05 12:56:26', NULL, NULL),
(5, 4, '4a26f9a7-8810-407f-9ea6-2c5f5804eae1', '<p>I really like the idea of adding more variety to the sessions.<br>Mixing running drills with strength exercises keeps things interesting.</p><p>Will we still have separate groups for different experience levels?</p>', 354, '2025-10-05 12:57:25', NULL, NULL),
(6, 4, '7b1131e0-e70e-4fd9-8b87-ff083c76ee04', '<p>Yes, Eric — we’ll continue with the same three training groups: beginners, intermediate, and advanced.</p><p>The goal is to make sure everyone trains at a pace that suits their current level while still being challenged.</p>', 355, '2025-10-05 12:58:18', NULL, NULL),
(7, 4, '21de6139-e513-4c35-8243-d722232e6f9f', '<p>Love it!<br>The new plan seems balanced and motivating.<br>Thanks to everyone who worked on organizing it — really appreciate the effort that goes into keeping this club running smoothly.</p>', 1, '2025-10-05 12:58:54', NULL, NULL),
(8, 5, '02354806-5e4d-42a2-943b-0a7c04d3ec2b', '<p>Hi everyone,<br>as we prepare for our upcoming club events, we’re looking for a few <strong>volunteers</strong> to help with organization and setup.</p><p>Tasks include welcoming guests, handing out water and snacks, and assisting with registration.</p><p>It’s a great way to get involved and meet other members — no special experience required!</p><p>Anyone interested?</p>', 354, '2025-10-05 13:00:30', NULL, NULL),
(9, 5, '103e21c4-52b5-4170-ba49-0df98b92be25', '<p>Count me in!<br>I can help with registration or setup — whatever’s needed.<br>Always happy to give something back to the club.</p>', 355, '2025-10-05 13:01:07', NULL, NULL),
(10, 6, '2f19c641-617d-4ffa-b9d3-b5c5e6b8ee0b', '<p>Hi everyone,<br>next week we’ll be organizing a <strong>community clean-up day</strong> at the club grounds.</p><p>It’s a great opportunity to keep our training area in top shape and spend some time together outside of regular practice.</p><p>All members are welcome to join — tools and materials will be provided by the club.</p><p>Thanks in advance to everyone who helps make our environment clean and welcoming for all!</p>', 1, '2025-10-05 13:02:03', NULL, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_forum_topics`
--

CREATE TABLE `%PREFIX%_forum_topics` (
  `fot_id` int UNSIGNED NOT NULL,
  `fot_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `fot_cat_id` int UNSIGNED NOT NULL,
  `fot_fop_id_first_post` int UNSIGNED DEFAULT NULL,
  `fot_title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `fot_views` int UNSIGNED NOT NULL DEFAULT '0',
  `fot_usr_id_create` int UNSIGNED DEFAULT NULL,
  `fot_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_forum_topics`
--

INSERT INTO `%PREFIX%_forum_topics` (`fot_id`, `fot_uuid`, `fot_cat_id`, `fot_fop_id_first_post`, `fot_title`, `fot_views`, `fot_usr_id_create`, `fot_timestamp_create`) VALUES
(4, '85944966-4967-44f3-8b76-b6a425225970', 303, 4, 'Thoughts on the New Training Plan?', 8, 355, '2025-10-05 12:56:26'),
(5, '5cb5159a-8cea-4dc2-a154-f9faadfc8d48', 303, 8, 'Volunteers Needed for Upcoming Club Events', 3, 354, '2025-10-05 13:00:30'),
(6, 'a5807ef6-92c3-4eda-bb12-10c66ca0a9b8', 303, 10, 'Community Clean-Up Day at the Club Grounds', 0, 1, '2025-10-05 13:02:03');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_ids`
--

CREATE TABLE `%PREFIX%_ids` (
  `ids_usr_id` int UNSIGNED NOT NULL,
  `ids_reference_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_inventory_fields`
--

CREATE TABLE `%PREFIX%_inventory_fields` (
  `inf_id` int UNSIGNED NOT NULL,
  `inf_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `inf_org_id` int UNSIGNED NOT NULL,
  `inf_type` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `inf_name_intern` varchar(110) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `inf_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `inf_description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `inf_system` tinyint(1) NOT NULL DEFAULT '0',
  `inf_required_input` smallint NOT NULL DEFAULT '0',
  `inf_sequence` smallint NOT NULL,
  `inf_usr_id_create` int UNSIGNED DEFAULT NULL,
  `inf_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `inf_usr_id_change` int UNSIGNED DEFAULT NULL,
  `inf_timestamp_change` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_inventory_fields`
--

INSERT INTO `%PREFIX%_inventory_fields` (`inf_id`, `inf_uuid`, `inf_org_id`, `inf_type`, `inf_name_intern`, `inf_name`, `inf_description`, `inf_system`, `inf_required_input`, `inf_sequence`, `inf_usr_id_create`, `inf_timestamp_create`, `inf_usr_id_change`, `inf_timestamp_change`) VALUES
(1, '87e206b9-550f-41b9-b810-cd1d845a3a1b', 1, 'TEXT', 'ITEMNAME', 'Artikelname', 'Der Name des Gegenstandes', 1, 1, 0, 2, '2025-09-27 09:00:21', NULL, NULL),
(2, '712b3a8f-b4c0-448f-8fc8-d28cdfb26527', 1, 'CATEGORY', 'CATEGORY', 'Kategorie', 'Die Kategorie des Gegenstandes', 1, 1, 1, 2, '2025-09-27 09:00:21', NULL, NULL),
(3, 'e9cf9d7d-7403-4aa9-ac63-2b27f941bf1c', 1, 'DROPDOWN', 'STATUS', 'Status', 'Der aktuelle Status des Gegenstandes', 1, 1, 2, 2, '2025-09-27 09:00:21', NULL, NULL),
(4, 'acc1905f-36a6-424d-9884-939288d022ba', 1, 'TEXT', 'KEEPER', 'Verwalter', 'Der Verwalter des Gegenstandes', 1, 0, 3, 2, '2025-09-27 09:00:21', NULL, NULL),
(5, 'abaceca6-b781-4121-ba67-38d120940731', 1, 'TEXT', 'LAST_RECEIVER', 'Letzter Empfänger', 'Der letzte Empfänger des Gegenstandes', 1, 0, 4, 2, '2025-09-27 09:00:21', NULL, NULL),
(6, '4d88c91e-a37d-4985-837c-15c357d44dd2', 1, 'DATE', 'BORROW_DATE', 'Ausleihdatum', 'Das Verleihdatum des Gegenstandes an den letzten Empfänger', 1, 0, 5, 2, '2025-09-27 09:00:21', NULL, NULL),
(7, 'f80f3bea-460b-4aaa-be04-afd561acac89', 1, 'DATE', 'RETURN_DATE', 'Rückgabedatum', 'Das Datum, an dem der Gegenstand an den Verwalter zurückgegeben wurde', 1, 0, 6, 2, '2025-09-27 09:00:21', NULL, NULL),
(8, '3981704a-568a-4f4d-9bad-df6bb78e610e', 2, 'TEXT', 'ITEMNAME', 'Artikelname', 'Der Name des Gegenstandes', 1, 1, 0, 2, '2025-09-27 09:00:21', NULL, NULL),
(9, '31e78677-fbd3-4c51-91d2-0aeafceb2bfc', 2, 'CATEGORY', 'CATEGORY', 'Kategorie', 'Die Kategorie des Gegenstandes', 1, 1, 1, 2, '2025-09-27 09:00:21', NULL, NULL),
(10, '5233418f-d381-462a-af50-92fbf5c6388d', 2, 'DROPDOWN', 'STATUS', 'Status', 'Der aktuelle Status des Gegenstandes', 1, 1, 2, 2, '2025-09-27 09:00:21', NULL, NULL),
(11, '294c2d85-b6c2-4138-a0be-437ae6854162', 2, 'TEXT', 'KEEPER', 'Verwalter', 'Der Verwalter des Gegenstandes', 1, 0, 3, 2, '2025-09-27 09:00:21', NULL, NULL),
(12, '01b46909-4755-4409-a045-155c5058f432', 2, 'TEXT', 'LAST_RECEIVER', 'Letzter Empfänger', 'Der letzte Empfänger des Gegenstandes', 1, 0, 4, 2, '2025-09-27 09:00:21', NULL, NULL),
(13, '1279bd8f-7b29-4ba1-ade8-dbec6bc6466d', 2, 'DATE', 'BORROW_DATE', 'Ausleihdatum', 'Das Verleihdatum des Gegenstandes an den letzten Empfänger', 1, 0, 5, 2, '2025-09-27 09:00:21', NULL, NULL),
(14, 'ce37d8be-da97-45ef-9145-6d7d7f39b4a7', 2, 'DATE', 'RETURN_DATE', 'Rückgabedatum', 'Das Datum, an dem der Gegenstand an den Verwalter zurückgegeben wurde', 1, 0, 6, 2, '2025-09-27 09:00:21', NULL, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_inventory_field_select_options`
--

CREATE TABLE `%PREFIX%_inventory_field_select_options` (
  `ifo_id` int UNSIGNED NOT NULL,
  `ifo_inf_id` int UNSIGNED NOT NULL,
  `ifo_value` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ifo_system` tinyint(1) NOT NULL DEFAULT '0',
  `ifo_sequence` smallint NOT NULL,
  `ifo_obsolete` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_inventory_field_select_options`
--

INSERT INTO `%PREFIX%_inventory_field_select_options` (`ifo_id`, `ifo_inf_id`, `ifo_value`, `ifo_system`, `ifo_sequence`, `ifo_obsolete`) VALUES
(1, 3, 'In Verwendung', 1, 1, 0),
(2, 3, 'Ausgesondert', 1, 2, 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_inventory_items`
--

CREATE TABLE `%PREFIX%_inventory_items` (
  `ini_id` int UNSIGNED NOT NULL,
  `ini_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ini_cat_id` int UNSIGNED NOT NULL,
  `ini_org_id` int UNSIGNED NOT NULL,
  `ini_status` int UNSIGNED NOT NULL,
  `ini_picture` blob,
  `ini_usr_id_create` int UNSIGNED DEFAULT NULL,
  `ini_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ini_usr_id_change` int UNSIGNED DEFAULT NULL,
  `ini_timestamp_change` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_inventory_item_borrow_data`
--

CREATE TABLE `%PREFIX%_inventory_item_borrow_data` (
  `inb_id` int UNSIGNED NOT NULL,
  `inb_ini_id` int UNSIGNED NOT NULL,
  `inb_last_receiver` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `inb_borrow_date` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `inb_return_date` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_inventory_item_data`
--

CREATE TABLE `%PREFIX%_inventory_item_data` (
  `ind_id` int UNSIGNED NOT NULL,
  `ind_inf_id` int UNSIGNED NOT NULL,
  `ind_ini_id` int UNSIGNED NOT NULL,
  `ind_value` varchar(4000) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_links`
--

CREATE TABLE `%PREFIX%_links` (
  `lnk_id` int UNSIGNED NOT NULL,
  `lnk_cat_id` int UNSIGNED NOT NULL,
  `lnk_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `lnk_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `lnk_description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `lnk_url` varchar(2000) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `lnk_counter` int NOT NULL DEFAULT '0',
  `lnk_usr_id_create` int UNSIGNED DEFAULT NULL,
  `lnk_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lnk_usr_id_change` int UNSIGNED DEFAULT NULL,
  `lnk_timestamp_change` timestamp NULL DEFAULT NULL,
  `lnk_sequence` smallint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_links`
--

INSERT INTO `%PREFIX%_links` (`lnk_id`, `lnk_cat_id`, `lnk_uuid`, `lnk_name`, `lnk_description`, `lnk_url`, `lnk_counter`, `lnk_usr_id_create`, `lnk_timestamp_create`, `lnk_usr_id_change`, `lnk_timestamp_change`, `lnk_sequence`) VALUES
(1, 7, '07bdb749-e925-4715-ba92-360bf3b2821d', 'Sample page', 'On this site there\'s not much news :(', 'https://www.example.com', 6, 1, '2025-09-22 22:00:00', 1, '2025-09-23 22:00:00', 1),
(2, 9, 'ae39a20e-b5b2-4ebb-8b1a-882bd6d777d5', 'Admidio', 'The homepage of the <b>best</b> open source membership management in the net.', 'https://www.admidio.org/', 157, 1, '2025-09-22 22:00:00', NULL, NULL, 1),
(3, 9, '476855ec-6c36-449c-a4ac-c17b27a34e11', 'Forum', 'The forum for the online membership management software. Here gets everyone support, who has encountered a problem while installing or setting up Admidio. But also suggestions and tips can be posted here.', 'https://www.admidio.org/forum/', 46, 1, '2025-09-22 22:00:00', NULL, NULL, 2),
(4, 9, '69e19ac6-1744-495b-bd70-bf8c3baaf15c', 'Documentation', 'The documentation for Admidio with valuable help and tips.', 'https://www.admidio.org/dokuwiki', 21, 1, '2012-04-05 12:13:23', NULL, NULL, 3),
(5, 9, '325564f8-4630-4efe-912b-79358b6cae98', 'GitHub', '<p>Our developement area at Github. If you want to help us and add some new feature to Admidio go there and get the code.</p>', 'https://github.com/Admidio/admidio', 0, 1, '2025-10-04 08:52:40', NULL, NULL, 4);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_lists`
--

CREATE TABLE `%PREFIX%_lists` (
  `lst_id` int UNSIGNED NOT NULL,
  `lst_org_id` int UNSIGNED NOT NULL,
  `lst_usr_id` int UNSIGNED NOT NULL,
  `lst_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `lst_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `lst_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lst_global` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_lists`
--

INSERT INTO `%PREFIX%_lists` (`lst_id`, `lst_org_id`, `lst_usr_id`, `lst_uuid`, `lst_name`, `lst_timestamp`, `lst_global`) VALUES
(1, 1, 1, '485a11f0-e4f9-4771-a71c-1eacff12dd4c', 'Address list', '2009-02-27 20:50:57', 1),
(2, 1, 1, '914693d9-5e08-42f9-a97f-1b1e8ed8ae2a', 'Phone list', '2009-02-27 20:50:57', 1),
(3, 1, 1, 'c28f0e74-d95f-44d7-8e02-2ca80ee220ae', 'Contact information', '2009-02-27 20:50:57', 1),
(4, 1, 1, '4e3d9b48-eeff-4760-98c6-a69b38221342', 'Membership', '2009-02-27 20:50:57', 1),
(5, 1, 1, '3a28db85-bf2c-4828-82f7-f8c67a0ff692', 'Social networks', '2009-02-27 20:56:52', 0),
(6, 1, 1, 'ca9a32ec-efd2-46da-a9ee-8cf6aa0c179e', 'Birthday', '2009-02-27 20:57:38', 0),
(7, 1, 351, 'a4889bdb-e294-46a6-a76f-4456707012e3', 'Website', '2009-02-27 21:34:28', 0),
(8, 1, 351, '5ebcd56b-3095-4656-82b7-70f451de07b8', NULL, '2009-02-27 21:34:47', 0),
(9, 2, 1, '82b5af7a-d535-4383-8f0e-befbd6e8d9e8', 'Address list', '2012-02-27 20:50:57', 1),
(10, 2, 1, 'df811fdd-89cd-49f2-9031-49a8aab71860', 'Phone list', '2012-02-27 20:50:57', 1),
(11, 2, 1, '77afdf9a-1d25-4993-8680-6fc556c2972c', 'Contact information', '2012-02-27 20:50:57', 1),
(12, 2, 1, 'a834cebe-3592-4f05-8c9b-73551615f570', 'Membership', '2012-02-27 20:50:57', 1),
(13, 1, 1, 'afc87e5f-fffa-46f3-82d1-6e8c65472ae4', 'Members', '2018-04-05 19:50:57', 1),
(14, 2, 1, 'a94a023b-56fa-4d6e-b05e-267a3d37ba09', 'Members', '2018-04-05 19:50:57', 1),
(15, 1, 1, 'd39d0642-c367-43ca-bc73-578219febbc6', 'Contacts', '2021-11-13 14:08:45', 1),
(16, 2, 1, '6efad974-5b15-4bb7-a1f8-350fa4b7a452', 'Contacts', '2021-11-13 14:08:45', 1);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_list_columns`
--

CREATE TABLE `%PREFIX%_list_columns` (
  `lsc_id` int UNSIGNED NOT NULL,
  `lsc_lst_id` int UNSIGNED NOT NULL,
  `lsc_number` smallint NOT NULL,
  `lsc_usf_id` int UNSIGNED DEFAULT NULL,
  `lsc_special_field` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `lsc_sort` varchar(5) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `lsc_filter` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_list_columns`
--

INSERT INTO `%PREFIX%_list_columns` (`lsc_id`, `lsc_lst_id`, `lsc_number`, `lsc_usf_id`, `lsc_special_field`, `lsc_sort`, `lsc_filter`) VALUES
(1, 1, 1, 1, NULL, 'ASC', NULL),
(2, 1, 2, 2, NULL, 'ASC', NULL),
(3, 1, 3, 10, NULL, NULL, NULL),
(4, 1, 4, 3, NULL, NULL, NULL),
(5, 1, 5, 4, NULL, NULL, NULL),
(6, 1, 6, 5, NULL, NULL, NULL),
(10, 2, 1, 1, NULL, 'ASC', NULL),
(11, 2, 2, 2, NULL, 'ASC', NULL),
(12, 2, 3, 7, NULL, NULL, NULL),
(13, 2, 4, 8, NULL, NULL, NULL),
(14, 2, 5, 12, NULL, NULL, NULL),
(20, 3, 1, 1, NULL, 'ASC', NULL),
(21, 3, 2, 2, NULL, 'ASC', NULL),
(22, 3, 3, 10, NULL, NULL, NULL),
(23, 3, 4, 3, NULL, NULL, NULL),
(24, 3, 5, 4, NULL, NULL, NULL),
(25, 3, 6, 5, NULL, NULL, NULL),
(26, 3, 7, 7, NULL, NULL, NULL),
(27, 3, 8, 8, NULL, NULL, NULL),
(28, 3, 9, 12, NULL, NULL, NULL),
(30, 4, 1, 1, NULL, NULL, NULL),
(31, 4, 2, 2, NULL, NULL, NULL),
(32, 4, 3, 10, NULL, NULL, NULL),
(33, 4, 4, NULL, 'mem_begin', NULL, NULL),
(34, 4, 5, NULL, 'mem_end', 'DESC', NULL),
(40, 5, 1, 1, NULL, 'ASC', NULL),
(41, 5, 2, 2, NULL, NULL, NULL),
(43, 5, 4, 22, NULL, NULL, NULL),
(49, 5, 7, 24, NULL, NULL, NULL),
(60, 6, 1, 1, NULL, NULL, NULL),
(61, 6, 2, 2, NULL, NULL, NULL),
(62, 6, 3, 10, NULL, 'DESC', NULL),
(70, 7, 1, 1, NULL, NULL, NULL),
(71, 7, 2, 2, NULL, NULL, NULL),
(72, 7, 3, 13, NULL, 'DESC', NULL),
(73, 7, 4, 12, NULL, NULL, NULL),
(80, 8, 1, 1, NULL, NULL, NULL),
(81, 8, 2, 2, NULL, 'ASC', NULL),
(82, 8, 3, 13, NULL, NULL, NULL),
(83, 8, 4, 11, NULL, NULL, NULL),
(101, 9, 1, 1, NULL, 'ASC', NULL),
(102, 9, 2, 2, NULL, 'ASC', NULL),
(103, 9, 3, 10, NULL, NULL, NULL),
(104, 9, 4, 3, NULL, NULL, NULL),
(105, 9, 5, 4, NULL, NULL, NULL),
(106, 9, 6, 5, NULL, NULL, NULL),
(107, 10, 1, 1, NULL, 'ASC', NULL),
(108, 10, 2, 2, NULL, 'ASC', NULL),
(109, 10, 3, 7, NULL, NULL, NULL),
(110, 10, 4, 8, NULL, NULL, NULL),
(111, 10, 5, 12, NULL, NULL, NULL),
(113, 11, 1, 1, NULL, 'ASC', NULL),
(114, 11, 2, 2, NULL, 'ASC', NULL),
(115, 11, 3, 10, NULL, NULL, NULL),
(116, 11, 4, 3, NULL, NULL, NULL),
(117, 11, 5, 4, NULL, NULL, NULL),
(118, 11, 6, 5, NULL, NULL, NULL),
(119, 11, 7, 7, NULL, NULL, NULL),
(120, 11, 8, 8, NULL, NULL, NULL),
(121, 11, 9, 12, NULL, NULL, NULL),
(122, 12, 1, 1, NULL, NULL, NULL),
(123, 12, 2, 2, NULL, NULL, NULL),
(124, 12, 3, 10, NULL, NULL, NULL),
(125, 12, 4, NULL, 'mem_begin', NULL, NULL),
(126, 12, 5, NULL, 'mem_end', 'DESC', NULL),
(127, 13, 1, 1, NULL, 'ASC', NULL),
(128, 13, 2, 2, NULL, NULL, NULL),
(129, 13, 3, NULL, 'mem_approved', NULL, NULL),
(130, 13, 4, NULL, 'mem_comment', NULL, NULL),
(131, 13, 5, NULL, 'mem_count_guests', NULL, NULL),
(132, 14, 1, 1, NULL, 'ASC', NULL),
(133, 14, 2, 2, NULL, NULL, NULL),
(134, 14, 3, NULL, 'mem_approved', NULL, NULL),
(135, 14, 4, NULL, 'mem_comment', NULL, NULL),
(136, 14, 5, NULL, 'mem_count_guests', NULL, NULL),
(140, 15, 1, 1, NULL, 'ASC', NULL),
(141, 15, 2, 2, NULL, 'ASC', NULL),
(142, 15, 3, NULL, 'usr_login_name', NULL, NULL),
(143, 15, 4, 11, NULL, NULL, NULL),
(144, 15, 5, 10, NULL, NULL, NULL),
(145, 15, 6, 5, NULL, NULL, NULL),
(146, 15, 7, NULL, 'usr_timestamp_change', NULL, NULL),
(150, 16, 1, 1, NULL, 'ASC', NULL),
(151, 16, 2, 2, NULL, 'ASC', NULL),
(152, 16, 3, NULL, 'usr_login_name', NULL, NULL),
(153, 16, 4, 11, NULL, NULL, NULL),
(154, 16, 5, 10, NULL, NULL, NULL),
(155, 16, 6, 5, NULL, NULL, NULL),
(156, 16, 7, NULL, 'usr_timestamp_change', NULL, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_log_changes`
--

CREATE TABLE `%PREFIX%_log_changes` (
  `log_id` int NOT NULL,
  `log_table` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `log_record_id` int UNSIGNED NOT NULL,
  `log_record_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `log_record_name` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `log_record_linkid` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `log_related_id` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `log_related_name` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `log_field` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `log_field_name` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `log_action` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `log_value_old` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `log_value_new` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `log_usr_id_create` int UNSIGNED DEFAULT NULL,
  `log_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `log_comment` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_log_changes`
--

INSERT INTO `%PREFIX%_log_changes` (`log_id`, `log_table`, `log_record_id`, `log_record_uuid`, `log_record_name`, `log_record_linkid`, `log_related_id`, `log_related_name`, `log_field`, `log_field_name`, `log_action`, `log_value_old`, `log_value_new`, `log_usr_id_create`, `log_timestamp_create`, `log_comment`) VALUES
(1, 'user_data', 3, '7a854ed2-50db-49ee-9379-31d07f467d47', 'Schmidt, Paul', '1', NULL, NULL, '3', 'Street', 'MODIFY', 'Musterallee 25', 'Unter den Linden 45', 1, '2025-07-14 18:42:25', NULL),
(2, 'user_data', 4, '7a854ed2-50db-49ee-9379-31d07f467d47', 'Schmidt, Paul', '1', NULL, NULL, '4', 'Postal code', 'MODIFY', '54897', '10117', 1, '2025-07-14 18:42:25', NULL),
(3, 'user_data', 5, '7a854ed2-50db-49ee-9379-31d07f467d47', 'Schmidt, Paul', '1', NULL, NULL, '5', 'City', 'MODIFY', 'Düren', 'Berlin', 1, '2025-07-14 18:42:25', NULL),
(4, 'user_data', 25, '7a854ed2-50db-49ee-9379-31d07f467d47', 'Schmidt, Paul', '1', NULL, NULL, '25', 'Bundesland', 'MODIFY', '10', '3', 1, '2025-07-14 18:42:25', NULL),
(5, 'user_data', 7, '7a854ed2-50db-49ee-9379-31d07f467d47', 'Schmidt, Paul', '1', NULL, NULL, '7', 'Phone', 'MODIFY', '02456-3908903', '0211-85858585', 1, '2025-07-14 18:42:25', NULL),
(6, 'user_data', 22, '7a854ed2-50db-49ee-9379-31d07f467d47', 'Schmidt, Paul', '1', NULL, NULL, '22', 'Facebook', 'MODIFY', NULL, 'Admidio', 1, '2025-08-05 07:42:03', NULL),
(7, 'user_data', 8, 'de709436-a2d5-4270-999f-adb8a06bb443', 'Begunk, Damion', '213', NULL, NULL, '8', 'Mobile', 'MODIFY', '0183-342342', '0181-457412', 1, '2025-07-04 13:02:03', NULL),
(8, 'user_data', 13, 'de709436-a2d5-4270-999f-adb8a06bb443', 'Begunk, Damion', '213', NULL, NULL, '13', 'Website', 'MODIFY', 'www.example.org', NULL, 1, '2025-09-02 02:02:35', NULL),
(9, 'user_data', 22, 'de709436-a2d5-4270-999f-adb8a06bb443', 'Begunk, Damion', '213', NULL, NULL, '22', 'Facebook', 'MODIFY', 'begunk', NULL, 1, '2025-09-02 02:02:35', NULL),
(16, 'user_data', 18136, '97f8346c-ca53-40de-857a-459d26d9df40', 'Schmidt, Jennifer', '355', NULL, NULL, '10', 'Birthday', 'MODIFY', NULL, '1994-02-09', 355, '2025-10-05 13:05:58', NULL),
(17, 'user_data', 18137, '97f8346c-ca53-40de-857a-459d26d9df40', 'Schmidt, Jennifer', '355', NULL, NULL, '21', 'Favorite color', 'MODIFY', NULL, 'red', 355, '2025-10-05 13:05:58', NULL),
(18, 'user_data', 207, 'd41b8e54-d55d-42f1-bb52-71a1286e3dc3', 'Bensien, Daniel', '218', NULL, NULL, '3', 'Street', 'MODIFY', 'Blumenwiese 39', 'Blumenwiese 58', 354, '2025-10-05 13:07:39', NULL),
(19, 'user_data', 18138, 'd41b8e54-d55d-42f1-bb52-71a1286e3dc3', 'Bensien, Daniel', '218', NULL, NULL, '21', 'Favorite color', 'MODIFY', NULL, 'green', 354, '2025-10-05 13:07:39', NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_members`
--

CREATE TABLE `%PREFIX%_members` (
  `mem_id` int UNSIGNED NOT NULL,
  `mem_rol_id` int UNSIGNED NOT NULL,
  `mem_usr_id` int UNSIGNED NOT NULL,
  `mem_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `mem_begin` date NOT NULL,
  `mem_end` date NOT NULL DEFAULT '9999-12-31',
  `mem_leader` tinyint(1) NOT NULL DEFAULT '0',
  `mem_usr_id_create` int UNSIGNED DEFAULT NULL,
  `mem_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `mem_usr_id_change` int UNSIGNED DEFAULT NULL,
  `mem_timestamp_change` timestamp NULL DEFAULT NULL,
  `mem_approved` int UNSIGNED DEFAULT NULL,
  `mem_comment` varchar(4000) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `mem_count_guests` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_members`
--

INSERT INTO `%PREFIX%_members` (`mem_id`, `mem_rol_id`, `mem_usr_id`, `mem_uuid`, `mem_begin`, `mem_end`, `mem_leader`, `mem_usr_id_create`, `mem_timestamp_create`, `mem_usr_id_change`, `mem_timestamp_change`, `mem_approved`, `mem_comment`, `mem_count_guests`) VALUES
(1, 1, 1, 'fd3e1942-1285-4fe0-b3c0-eb3c5cebfad0', '2008-04-20', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(2, 2, 1, '46da7d44-9209-45cb-acaa-f4b9ba7fa40b', '2008-04-20', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(3, 4, 1, '3fbe3c81-dfa4-4354-ba96-06d531ba712a', '2008-04-20', '2009-10-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(4, 6, 1, 'ca1d02ea-ff41-4a96-9484-2012d129a7b8', '2009-04-20', '9999-12-31', 0, 354, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(5, 7, 1, '1d0a3c88-f464-4f3c-8735-c2904807c82d', '2009-04-20', '9999-12-31', 0, 354, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(6, 9, 1, '91f8f88b-8e84-416d-9d6b-dbeb7a51cd83', '2008-04-20', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, 2, NULL, 0),
(7, 11, 1, 'da316d25-8e33-410f-9d29-9926bd06f9e1', '2016-04-20', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, 2, NULL, 0),
(10, 7, 2, '51ee7ad9-3d4a-4f19-ab9b-fe29dec88329', '2008-04-20', '9999-12-31', 0, NULL, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(11, 7, 2, '9e8f92bd-6d05-4ca1-b2e9-72abd7fff7e7', '2008-04-20', '9999-12-31', 0, NULL, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(201, 2, 202, 'b9502822-1ae7-44c1-8cf2-7f87b1d15946', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(202, 2, 203, '5b6df531-4576-455d-a7d3-6b79ba41ef09', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(203, 2, 204, '17e0ce5c-726c-485b-bb9e-c4e62cac0920', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(204, 2, 205, 'f8d5636f-c278-4188-9ba6-7ce275d20f20', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(205, 2, 206, '6a7648a4-ff84-4105-84dd-f7fd4bbebdfd', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(206, 2, 207, 'e5510b7f-ca35-434c-b744-fedcd80dd8bd', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(207, 2, 208, '8acf2c76-f9ce-4602-a1d9-8e7458260b46', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(208, 2, 209, 'f5939a65-2f67-48ac-a9f3-f62eb4881280', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(209, 2, 210, '30555d7e-d161-4ac3-af40-cc86874e1741', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(211, 2, 212, '351d96a4-2732-4a9c-9560-d652494cff99', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(212, 2, 213, '74c826ee-18be-4957-a784-fbc5c4b63b39', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(213, 2, 214, '9f26dade-f080-4119-bff5-0e6b00be9f0d', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(214, 2, 215, '2ecdfaa5-6af5-4c4e-93b6-ab6edb2c69ee', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(215, 2, 216, '9c10af84-bbfb-4748-8bff-cfafa3cd1a96', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(216, 2, 217, 'f5ec5dae-7085-41b1-8389-424c184be214', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(217, 2, 218, '563f61e5-1116-4a63-aa77-cad8b3aa41ed', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(218, 2, 219, '8db7589b-08d4-4618-980b-f115e299c641', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(219, 2, 220, '4f5d544e-e5c0-49d7-979e-77850e76f67a', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(220, 2, 221, '940bd559-8e96-423c-a6ab-b68502c05ac8', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(221, 2, 222, 'ba278734-e59f-4134-99dd-4bac886283b3', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(222, 2, 223, 'ea078cfa-9917-4d80-91f3-1bd5cd832e68', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(223, 2, 224, '6b8eedac-c5cc-4293-b1a7-371b556c5515', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(224, 2, 225, '17dea8af-9560-4006-8a38-544dae3f9ba5', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(225, 2, 226, 'b1dbdf7b-a708-4242-b9f1-73dc19c121c4', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(226, 2, 227, '41748d24-56a7-4d35-acd1-69a34c261411', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(227, 2, 228, '256c6ffb-170c-4bd8-8112-a032dfb96bd5', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(228, 2, 229, '70200655-507c-4fca-93ac-4696ef6d189f', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(229, 2, 230, 'b329e4d6-96d3-462e-b4e5-756f5fa10fae', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(230, 2, 231, '5ab2525a-9465-4ce3-8bfb-dd5d13f3f22b', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(231, 2, 232, 'df9a9dad-909c-4916-be65-07d046456a28', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(232, 2, 233, '31b9390e-3343-4397-9ea2-f82e91e570e5', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(233, 2, 234, '04e2d5ae-552e-4413-b8d0-88118d885c54', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(234, 2, 235, '94d56c24-4d92-42db-819c-dd47dbd68b4c', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(235, 2, 236, '9f29dc74-184f-4b61-91d8-5d8928bdece8', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(236, 2, 237, '276fb65e-167f-42dc-b294-9e6b49d5365f', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(237, 2, 238, 'f97acfec-7c4f-4bd1-ba14-f0f1b90449c5', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(238, 2, 239, '871a812d-767a-4588-bb91-cacb4aa0e694', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(239, 2, 240, 'c49cb6db-8096-4564-a209-de425d6ac314', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(240, 2, 241, '8caa8722-5009-46d7-b01a-d6a76fa1fb53', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(241, 2, 242, '6d8e7880-ebaf-4ab4-8532-e44f52eb0b7b', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(242, 2, 243, '9afa6fd1-cf94-40bd-8e9d-16c9f3115614', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(243, 2, 244, 'f1d47a6a-57d2-49b4-b7bc-3bcd951514dc', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(244, 2, 245, '10da187c-b11c-4f1f-ac88-b3e65dbfc060', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(245, 2, 246, '6d385e13-f457-434b-9cfd-389e7e90c947', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(246, 2, 247, 'ae1dbadf-c8aa-487f-891a-eee9587f6941', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(247, 2, 248, '0e3ecd4a-2ea0-4476-960e-6bbf3aa857d1', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(248, 2, 249, 'ad44894f-cca1-4718-95cf-13cc415febb5', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(249, 2, 250, 'b27f6a5e-fc40-4d76-92db-613316d2011a', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(250, 2, 251, 'ca26a6ac-2f2e-4167-96cc-90f48ae76e6f', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(251, 2, 252, '9a4fb40b-1408-416e-9d99-36b4b803b358', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(252, 2, 253, 'fff1962c-9957-4366-a3fb-6b00665f7559', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(253, 2, 254, '03445038-1ca0-4ccb-b2c5-43b12ecb72ff', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(254, 2, 255, '99b567a6-cbe0-4e37-96ef-64a7158ce613', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(255, 2, 256, 'd13d843c-4265-4d81-ad72-957413505628', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(256, 2, 257, 'a49a5b09-57ef-4abd-a436-81c7b55b8998', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(257, 2, 258, 'eada7eed-cabb-492e-bee8-9a7024780b10', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(258, 2, 259, '8bbca168-7a03-4ddb-939b-dfcc85dba3da', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(259, 2, 260, '016ccd02-9097-451d-86a7-d383d7d5b139', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(260, 2, 261, '0fb31975-ea21-4953-9fc4-7d7e2800a8e5', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(261, 2, 262, 'd72789c9-56f6-4a7d-9d9a-8f2c6c0078b1', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(262, 2, 263, 'd493ba22-b957-479b-bf92-065e039e95a6', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(263, 2, 264, '8d390ae5-1114-45c3-9de1-c3bc1982e044', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(264, 2, 265, '0a901e60-f4d0-4eaa-90a2-fc6bc958faac', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(265, 2, 266, '96d0e294-1198-4e51-8583-7d9972a0d59b', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(266, 2, 267, 'c2fa4f30-017c-4f1f-8fa9-87c29055d67c', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(267, 2, 268, '741c7150-bef7-417d-96d5-89f866fc09b6', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(268, 2, 269, 'e56d6cc5-89b2-4f36-932c-c5da05408823', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(269, 2, 270, 'a7d7b03d-1c18-4a6e-9e13-c44b7c03a6e9', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(270, 2, 271, '1b139734-2a57-4531-91f4-15377aea5b7e', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(271, 2, 272, '4a254300-871b-4ded-975f-605ef725dcdb', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(272, 2, 273, 'a7f6b05e-e2c7-4b7f-8c08-cead8b36a780', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(273, 2, 274, '944ef4c4-9933-4f1b-b913-3a1c6162eb5d', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(274, 2, 275, '6cf99c3c-6bd4-4d42-ad8a-5ba23d7e72d3', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(275, 2, 276, 'e36a08b4-b7bd-4565-af1a-39740b4d33c7', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(276, 2, 277, '19b6c7f5-e199-4634-a003-74efc084861f', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(277, 2, 278, 'ea0953b3-6e04-49f0-ab52-ceaaf28b2840', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(278, 2, 279, '583669b7-42dc-4183-9b70-834894660c2f', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(279, 2, 280, 'e0f728f9-7826-4052-a04e-03b69df6257e', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(280, 2, 281, 'b3e192f2-8eb2-47ac-98f2-38e677ad2f68', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(281, 2, 282, 'd3c8b3b7-6d19-4f15-9ec1-2115dcc94e88', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(282, 2, 283, '4a3c4aef-2158-4cf3-9cdb-7e4fa3f6ee7f', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(283, 2, 284, '447afc3f-c4b7-4922-9e43-76b1d13505d2', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(284, 2, 285, '402a1db3-0044-4dd9-9e03-dda83dcea96a', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(285, 2, 286, '6fcb184c-15fa-4b56-9ffe-a459d92f31df', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(286, 2, 287, 'cb89079d-20dd-41a5-9672-27acb97e2d8f', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(287, 2, 288, 'dd480354-b16b-4c16-86eb-cda37c8fc217', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(288, 2, 289, '1ebddc49-cdfb-464d-a023-edcdc5445472', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(289, 2, 290, 'cc709580-4cf0-4578-a198-cf9b0f4c78e1', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(290, 2, 291, '2c586ab9-6a52-4bc7-8fe9-9bbee2de9707', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(291, 2, 292, '4c026c78-25e5-4dd8-bdde-6a0dbac02928', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(292, 2, 293, '371f1f76-a443-45b5-9a94-e842fd7b1d16', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(293, 2, 294, '22edd590-9964-47b1-9ae4-07974582d189', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(294, 2, 295, '9ff8ff89-517f-493b-9dcb-60cbbcf1f311', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(295, 2, 296, 'd1dd57a3-280b-4fd9-b362-068f298cb37a', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(296, 2, 297, 'e69ea2a9-07a8-425a-b42b-cfb37e925aeb', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(297, 2, 298, 'cae931c1-7cb4-4646-bf02-dfb94d3c381c', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(298, 2, 299, 'e242b230-b461-4613-9607-8436abc08b37', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(299, 2, 300, '3013b8c7-5ec6-4686-ac94-b755c85397c5', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(300, 2, 301, 'c0d3a82f-27aa-4071-8f4b-32e337b75980', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(301, 2, 302, '06a4b0fd-9fc1-4206-b7d0-1fb1810eb69e', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(302, 2, 303, 'fe35b25b-bbc0-4598-a896-bc24a706fe75', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(303, 2, 304, '1db85131-4b0e-43c5-931f-7e4b748671e1', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(304, 2, 305, '5760d370-b330-4e22-9275-02591e44b1e3', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(305, 2, 306, '7d72d8c2-3393-405a-870f-b65b31057191', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(306, 2, 307, '8214f854-0530-4a23-982d-ea2199ea792a', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(307, 2, 308, '807f25f4-3362-458b-8859-d13ad0c7b218', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(308, 2, 309, '591bae19-a226-48ea-97e0-4d655dd9169b', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(309, 2, 310, '49fe5b9a-52d2-4d7b-94ca-d43b8bf6b0ed', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(310, 2, 311, '55d098bd-6884-47c7-9d13-94adb85d7f5d', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(311, 2, 312, 'cab2c557-cc83-496f-a009-78dac3d575f7', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(312, 2, 313, '46877631-60b4-4c96-bbec-1340a4d4f463', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(313, 2, 314, '8cd59b24-0cb1-4236-9c17-c4834b111116', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(314, 2, 315, '46ca35dd-3eda-46ac-8b86-36ef729a2db1', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(315, 2, 316, '99538362-bd80-4ff0-99b2-8c0969769002', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(316, 2, 317, 'c71c8925-4aa6-415b-af82-e5dd7671b360', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(317, 2, 318, 'b4e16d38-8d94-4db9-8d4c-0b5404747294', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(318, 2, 319, 'd25ec87d-08a5-4402-8fb8-9e5de2bbd7ac', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(319, 2, 320, '517986a3-60f9-448e-bf3d-2fa82b2b8dc1', '2008-04-26', '2008-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(320, 2, 321, '1ff9b34a-33b6-48e1-82c4-3cf23f9c0c17', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(321, 2, 322, '2757d517-16d5-452b-b409-10a81abb9cf6', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(322, 2, 323, 'eabcf3c6-03b9-4702-b9b4-8c12f24d34b6', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(323, 2, 324, '62e60b1f-d0bc-4f08-9dc3-0f2ff1054a3d', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(324, 2, 325, 'e7f7df15-9108-4771-9fa1-8b7fbd584f99', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(325, 2, 326, '241b8d38-284a-4e2b-9952-1c6f9879131a', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(326, 2, 327, 'bef0a4fe-50f8-4d77-9fa3-351acc044269', '2008-04-26', '2008-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(327, 2, 328, '04de04fb-5fb8-4daf-aff4-361ee3fdc86d', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(328, 2, 329, '2bb2e202-5edd-4f9a-ba18-59b45eba724c', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(329, 2, 330, '1db73c85-2d08-44f0-b715-2f3b91912eb3', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(330, 2, 331, 'ffd0ee3a-b0cb-427c-a10a-d6c6119256e4', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(331, 2, 332, '99a20f23-d3f5-4bc7-956d-abab252768eb', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(332, 2, 333, '27817601-a6dc-44e8-8ea4-f55491be0752', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(333, 2, 334, 'b256c61d-06bc-40bf-86f4-f5f703a16c28', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(334, 2, 335, '9d8435cd-e311-4d69-a508-24546d631598', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(335, 2, 336, '20e4164a-a6ac-41f8-88fd-a8e4092110c0', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(336, 2, 337, 'ee7c4f19-6bd7-435a-a259-1b985209e73e', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(337, 2, 338, 'f01dbf07-7443-455b-8517-9837782d4201', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(338, 2, 339, '53834f65-b7f7-49bc-9e99-640271a18422', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(339, 2, 340, '8f3b7e7c-1ffb-4c45-9fb7-4419e98cf5ff', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(340, 2, 341, 'd86c8207-86b3-425b-a466-d9e17966cdb2', '2008-04-26', '2008-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(341, 2, 342, 'a1f3d6b3-f290-4e36-8d9a-a563cc00f48b', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(342, 2, 343, '6c001c53-a1dc-46e2-a1a7-194c18eed7de', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(343, 2, 344, '600a3c42-7a11-4aaf-b1e2-f3a0f36eec1d', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(346, 2, 347, '9b14f1e7-3ec2-43fc-a1f6-4e087f0c8e98', '2008-04-26', '2008-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(347, 2, 348, 'a01e9318-ab1f-4af7-935e-90447b3df20e', '2008-04-26', '2008-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(348, 2, 349, 'afdfd7d4-9ee7-4af4-b97c-7ed438cefd68', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(349, 2, 350, '234bca7f-52bd-4133-9613-da4d11ce6b21', '2008-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(350, 2, 351, '136dd336-bcc0-4c0a-88e4-9c7688bfacae', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(351, 3, 351, 'ba2e7b2f-62a9-41ac-ac75-203e2101f4de', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(352, 3, 1, '9af70a15-fc26-4a3f-af48-733b3f5cde39', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(353, 4, 255, '11c4a389-56fc-4642-9ae2-c0fed697e11c', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(354, 4, 262, 'fbe556f8-c236-467e-9b7c-b1772743a850', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(355, 4, 271, '4eec44a8-ccc7-4c4c-bcb3-a1e1242331f6', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(356, 4, 273, 'e60246ed-1e9e-4456-adc2-3009fdb2f4d1', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(357, 4, 275, '1dc48b81-a306-47b3-a3ee-a1eef3424f53', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(358, 4, 289, '747ec675-1514-4b9c-8879-0e974a364e29', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(359, 4, 292, '95d90a05-22c6-4264-b926-342ed1337ecf', '2008-05-03', '2008-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(360, 4, 341, 'e44c7f2e-0104-4a0b-aebd-0e78171461f4', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(361, 4, 347, '30764e53-1fcd-440b-9302-67d965dbb20c', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(362, 4, 351, 'e7c034bf-4791-4947-b3fe-aa9d254a16bd', '2008-05-03', '9999-12-31', 1, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(363, 4, 204, 'ce570ae8-0186-4887-9905-910b989fbc85', '2008-05-03', '9999-12-31', 1, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(364, 5, 212, 'bd6193ba-410f-4c74-8b6c-208eb469d74c', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(365, 5, 214, '59fd88b3-f1bc-449b-936d-1301bb20f0f5', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(366, 5, 220, 'fdc602a7-307b-41c3-bac0-498595550dd3', '2008-05-03', '9999-12-31', 1, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(367, 5, 226, '5226d969-c670-4e51-b205-15df7d9234a3', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(368, 5, 263, '525f8181-010c-4081-ac1a-2fc521c5bc2e', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(369, 5, 269, 'b6a827cb-806b-4267-83fb-61c6287b218a', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(370, 5, 283, 'b49c1d83-9a44-449c-8cea-8ad7bb890dab', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(371, 5, 290, '2d55366f-e53f-48cd-addd-ad7397343319', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(372, 5, 294, '5f48d090-4e66-4b1d-ad0c-98dadb3249ae', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(373, 5, 325, '90dc20dc-b0fa-4e7d-af65-0f81a6289380', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(374, 5, 326, '24213712-7df1-4400-9ed0-fa7b9c0e6c0e', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(375, 5, 342, 'a7d03c99-89da-49fa-8c59-879e557922fe', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(376, 5, 350, '8d6775ba-5bba-4852-ad18-c2a6ab502885', '2008-05-03', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(377, 3, 213, 'd43c83ae-3636-4d15-a548-5e643297f4e3', '2008-05-28', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(378, 3, 240, '16bb2a64-a46d-4d21-8585-3f32bf4138d3', '2008-05-28', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(379, 2, 354, '3c65f3a7-3677-4f50-b805-08fcdcf495a6', '2008-05-28', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(380, 3, 354, 'fdf8adf1-d06e-4fde-b14e-d503b550fa51', '2009-06-12', '9999-12-31', 1, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(381, 2, 355, '795afe4d-ffd4-471c-943b-dc13036e89c3', '2009-07-12', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(382, 5, 355, '08a28c94-935f-4f92-8d72-5e1278dc080d', '2012-11-03', '9999-12-31', 1, 2, '2014-08-25 15:13:52', NULL, NULL, NULL, NULL, 0),
(400, 7, 205, 'b0f06fbe-3738-4a8b-ab63-de50e720c2fe', '2010-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(401, 7, 211, 'd38b54ad-149d-498b-a628-e6cb296a63a8', '2010-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(402, 7, 290, 'cfa20f73-976b-4006-9322-f31abf719030', '2010-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(403, 7, 291, '69d8d75b-736a-407b-8dae-349164a4adec', '2010-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(404, 7, 292, '7f48731f-059b-40a5-92ca-60113d3eefc1', '2010-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(405, 7, 293, 'b0baf871-f39e-41f5-97e9-2517aee5628b', '2010-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(406, 7, 345, '2cb9b255-e189-4c37-bddb-8f0d5a836242', '2010-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(407, 7, 351, '531f5e42-e37c-450e-b4e7-7dcb4ce35ca3', '2010-04-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(500, 8, 1, 'f5b30256-e61d-4e71-b052-44d5d08f775e', '2025-09-23', '9999-12-31', 1, 2, '2008-05-03 05:43:02', 1, '2017-01-21 08:21:02', 2, NULL, 1),
(501, 8, 223, '5325c400-1ca7-4131-9ecc-bf35e78de7e1', '2025-09-25', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, 2, NULL, 3),
(502, 8, 255, '586cd51a-8332-47e9-9a98-2869ae7a0824', '2025-09-25', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, 3, NULL, 0),
(503, 8, 302, '171004d5-098a-4a0f-a678-ddcdb04cb88c', '2025-09-25', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, 2, NULL, 2),
(504, 8, 265, '130bf8b5-a83f-4e11-90c4-3540bb88f067', '2025-09-26', '9999-12-31', 0, 2, '2008-05-03 05:43:02', 328, '2017-02-01 21:04:21', 2, NULL, 0),
(505, 8, 266, '6c51cbfd-069a-48b0-b375-a2e7a37d77b2', '2020-09-18', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, 3, NULL, 0),
(506, 8, 267, '4f671955-257b-4215-ba11-b283761d1268', '2020-09-18', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, 2, NULL, 1),
(507, 8, 268, 'fd793b02-0d97-462e-8670-a02cf07ab043', '2020-09-18', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, 1, NULL, 0),
(508, 8, 270, 'bd258607-f940-4a08-8ff6-5cbc837c5a10', '2020-09-19', '9999-12-31', 0, 2, '2008-05-03 05:43:02', 328, '2017-02-01 21:04:21', 2, NULL, 0),
(509, 2, 356, 'bf789f06-8f99-4ef3-a34c-4f4106571c72', '2015-07-12', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(510, 2, 357, '1cec63b0-33ac-4076-8e29-518caca63728', '2015-07-12', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, NULL, NULL, 0),
(520, 12, 355, '3151a59a-4697-478c-90c7-0cf06b5ee860', '2018-06-03', '9999-12-31', 1, 2, '2014-08-25 15:13:52', NULL, NULL, 2, NULL, 0),
(521, 12, 263, '0babe98c-24ac-4bd4-9b53-4855439261c0', '2018-06-05', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, 3, NULL, 0),
(522, 12, 269, '9b87c9a3-e25a-4b54-b9ab-441692045fdb', '2018-06-04', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, 2, NULL, 0),
(523, 12, 283, '9b95ad01-c45a-4e78-a00b-092add02624e', '2018-06-05', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, 1, NULL, 0),
(524, 12, 300, 'b0445844-049e-41d8-969f-d3c76018226a', '2018-06-03', '9999-12-31', 1, 2, '2014-08-25 15:13:52', NULL, NULL, 1, NULL, 0),
(525, 12, 301, '38d8a478-8f1d-4483-a59c-944166c36ed4', '2018-06-05', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, 2, NULL, 0),
(526, 12, 310, 'd03a900b-4ac6-4ab8-963b-27a079b36303', '2018-06-04', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, 2, NULL, 0),
(540, 11, 351, 'fd47c224-6f9e-4689-8c9c-d36e6c0257c7', '2018-06-05', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, 2, NULL, 0),
(541, 11, 354, '34dd8ad2-3d96-465c-b7d0-2071fa79c9fb', '2018-06-05', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, 2, NULL, 0),
(542, 11, 240, '58cde084-6aa8-49c9-88ab-8238ca510bbf', '2018-06-05', '9999-12-31', 0, 2, '2008-05-03 05:43:02', NULL, NULL, 3, NULL, 0),
(543, 7, 358, 'bae36fe4-161b-438e-a99d-0a8df95ee29b', '2018-11-15', '9999-12-31', 0, 2, '2022-05-03 05:43:02', NULL, NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_menu`
--

CREATE TABLE `%PREFIX%_menu` (
  `men_id` int UNSIGNED NOT NULL,
  `men_men_id_parent` int UNSIGNED DEFAULT NULL,
  `men_com_id` int UNSIGNED DEFAULT NULL,
  `men_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `men_name_intern` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `men_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `men_description` varchar(4000) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `men_node` tinyint(1) NOT NULL DEFAULT '0',
  `men_order` int UNSIGNED DEFAULT NULL,
  `men_standard` tinyint(1) NOT NULL DEFAULT '0',
  `men_url` varchar(2000) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `men_icon` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_menu`
--

INSERT INTO `%PREFIX%_menu` (`men_id`, `men_men_id_parent`, `men_com_id`, `men_uuid`, `men_name_intern`, `men_name`, `men_description`, `men_node`, `men_order`, `men_standard`, `men_url`, `men_icon`) VALUES
(1, NULL, NULL, 'fbc2b774-8666-4d3d-996c-4c86e137d189', 'modules', 'SYS_MODULES', '', 1, 1, 1, NULL, ''),
(2, NULL, NULL, '9f27b3da-805c-4b1a-adfd-9ac7cc9d4c82', 'administration', 'SYS_ADMINISTRATION', '', 1, 2, 1, NULL, ''),
(3, NULL, NULL, '4317f28b-ce43-4ac1-a8eb-7e583d16add4', 'extensions', 'SYS_EXTENSIONS', '', 1, 3, 1, NULL, ''),
(4, 1, NULL, 'f037c6b0-e71e-4961-80d7-9ea5e42ddb7a', 'overview', 'SYS_OVERVIEW', '', 0, 1, 1, '/modules/overview.php', 'house-door-fill'),
(5, 1, 20, 'd96ba837-9b02-4c4e-afa5-c12167cd01db', 'announcements', 'SYS_ANNOUNCEMENTS', 'Display or edit announcements, news and other information.', 0, 2, 1, '/modules/announcements.php', 'newspaper'),
(6, 1, 30, '446a2c54-b269-4b6c-8a1c-869901b35b01', 'events', 'SYS_EVENTS', 'Events can be created and viewed. Members can register for special events if they wish.', 0, 3, 1, '/modules/events/events.php', 'calendar-week-fill'),
(7, 1, 40, 'edb6a573-fb66-4dfb-ba90-466317572204', 'messages', 'SYS_MESSAGES', 'Overview of all written e-mails and private messages. New emails and private messages can be entered and sent to individual contacts or roles.', 0, 4, 1, '/modules/messages/messages.php', 'envelope-fill'),
(8, 1, 50, 'ebd216b3-26eb-48ec-a082-d1d9645bb051', 'groups-roles', 'SYS_GROUPS_ROLES', 'Overview and management of all groups and roles of the organization. Different member lists can be displayed, exported and own lists can be created.', 0, 5, 1, '/modules/groups-roles/groups_roles.php', 'people-fill'),
(9, 1, 60, '168ad66e-e34d-4f14-8a65-a78dd7dbd058', 'contacts', 'SYS_CONTACTS', 'Display and organize all active and former members here. New contacts can be imported or created.', 0, 7, 1, '/modules/contacts/contacts.php', 'person-vcard-fill'),
(10, 1, 70, '187c7c07-4b2a-4f3f-925c-6f40c03e740b', 'documents-files', 'SYS_DOCUMENTS_FILES', 'Different documents and files can be viewed or provided for download. These files can be categorized in folders with different access permission levels.', 0, 8, 1, '/modules/documents-files.php', 'file-earmark-arrow-down-fill'),
(11, 1, 80, '21db7ac5-7aae-4616-bf88-5b29507d4a02', 'photo', 'SYS_PHOTOS', 'Pictures of events can be uploaded and displayed. Pictures can be organized in albums which can be interlinked.', 0, 10, 1, '/modules/photos/photos.php', 'image-fill'),
(12, 1, 90, 'a3ff5504-8f13-44c9-8e0a-d45b99e2239b', 'category-report', 'SYS_CATEGORY_REPORT', 'Generates a listing of a member\'s role and category memberships.', 0, 11, 1, '/modules/category-report/category_report.php', 'list-stars'),
(13, 1, 100, '5b8b3e60-bc71-4375-bfc3-a9783a63bb72', 'weblinks', 'SYS_WEBLINKS', 'Create and organize by categories interesting hyperlinks.', 0, 12, 1, '/modules/links/links.php', 'link-45deg'),
(14, 1, 110, 'a3bc93d4-5853-4b5a-bd7f-2cda06390a0e', 'forum', 'SYS_FORUM', 'Ein einfaches Forum für Diskussionen innerhalb einer Organisation. Es können verschiedene Themen veröffentlicht und diskutiert werden.', 0, 6, 1, '/modules/forum.php', 'chat-dots-fill'),
(15, 2, 120, '2965d083-8dd3-4a43-9b27-53018e5f22c1', 'orgprop', 'SYS_SETTINGS', 'ORG_ORGANIZATION_PROPERTIES_DESC', 0, 1, 1, '/modules/preferences.php', 'gear-fill'),
(16, 2, 130, '62330cbc-4c15-4860-b841-10a35d88cd3c', 'registration', 'SYS_REGISTRATION', 'New registrations of visitors can be listed, approved or refused here.', 0, 2, 1, '/modules/registration.php', 'card-checklist'),
(17, 2, 140, 'ef4b5380-3500-4ec4-a432-a7f4099a2a92', 'menu', 'SYS_MENU', 'The menu can be configured here. Beside the URL a description and an icon can be deposited. In addition, it can be determined who is allowed to view the menu entry.', 0, 3, 1, '/modules/menu.php', 'menu-button-wide-fill'),
(19, 2, 221, 'e946f478-4228-4afa-9d11-d8235f93e18c', 'organization', 'SYS_ORGANIZATION', 'Name und Kontaktangaben der aktuellen Organisation mit der Möglichkeit, Unterorganisationen zu erstellen und anzuzeigen.', 0, 4, 1, '/modules/organizations.php', 'diagram-3-fill'),
(20, 1, 222, 'ffe47320-d4d0-4893-9914-148bbbfd8237', 'inventory', 'SYS_INVENTORY', 'Die Inventarverwaltung ermöglicht es, Gegenstände zu organisieren, zu verwalten und zu verfolgen.', 0, 9, 1, '/modules/inventory.php', 'box-seam-fill');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_messages`
--

CREATE TABLE `%PREFIX%_messages` (
  `msg_id` int UNSIGNED NOT NULL,
  `msg_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `msg_type` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `msg_subject` varchar(256) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `msg_usr_id_sender` int UNSIGNED NOT NULL,
  `msg_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `msg_read` smallint NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_messages`
--

INSERT INTO `%PREFIX%_messages` (`msg_id`, `msg_uuid`, `msg_type`, `msg_subject`, `msg_usr_id_sender`, `msg_timestamp`, `msg_read`) VALUES
(1, '39a0e3e2-8163-4cbf-bb9b-ac87dbf8ab77', 'EMAIL', 'Events on the website', 1, '2021-01-20 13:58:16', 0),
(2, 'd12a108d-0bc7-468c-86a5-b05626a21f15', 'EMAIL', 'New module unlocked', 1, '2021-02-01 15:07:01', 0),
(3, '479fb7ae-52eb-401e-aba7-a3ca74f69c32', 'EMAIL', 'New training times', 1, '2021-02-03 11:08:02', 0),
(4, '42fb2917-a25b-4d1f-92ae-2cc837aae4a6', 'EMAIL', 'Invitation to members meeting', 354, '2021-02-03 04:11:37', 0),
(5, '9be7e4cc-4cf9-4945-84e8-0cb387063501', 'PM', 'Reserve room', 354, '2021-02-02 08:12:35', 1),
(6, '62d38fd3-b392-43f5-983a-bf563e780c07', 'PM', 'Membership fee missing', 354, '2021-02-03 04:14:22', 1),
(7, '693a032a-fee7-4a03-8e21-05647b6b6848', 'EMAIL', 'Training', 355, '2021-02-03 04:16:19', 0),
(8, 'c727b421-e303-4381-b097-9f6eeb56ca39', 'PM', 'No access to documents', 1, '2021-02-03 04:18:18', 1);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_messages_attachments`
--

CREATE TABLE `%PREFIX%_messages_attachments` (
  `msa_id` int UNSIGNED NOT NULL,
  `msa_msg_id` int UNSIGNED NOT NULL,
  `msa_file_name` varchar(256) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `msa_original_file_name` varchar(256) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `msa_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_messages_content`
--

CREATE TABLE `%PREFIX%_messages_content` (
  `msc_id` int UNSIGNED NOT NULL,
  `msc_msg_id` int UNSIGNED NOT NULL,
  `msc_usr_id` int UNSIGNED DEFAULT NULL,
  `msc_message` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `msc_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_messages_content`
--

INSERT INTO `%PREFIX%_messages_content` (`msc_id`, `msc_msg_id`, `msc_usr_id`, `msc_message`, `msc_timestamp`) VALUES
(1, 1, 1, '<p>Hi all,</p><br /><p>please maintain your schedules on the website so that all members have the opportunity to view and participate.</p><br /><p>Regards</p><br /><p>Paul</p><br />', '2021-02-03 03:58:16'),
(2, 2, 1, '<p>Hello Board,</p><br /><p>I have now unlocked the <strong>Documents and Files</strong> module. Please log in and have a look at this module</p><br /><p>The module has among others. following functions:</p><br /><ul><br /> <li>Files and documents can be uploaded by the board</li><br /> <li>Files and documents can be downloaded by all members</li><br /> <li>Files and documents can be displayed directly on the web</li><br /><br /ul><br /><p>You can send feedback directly to me. </p><br /><p>Best regards</p><br /><p>Paul</p><br />', '2021-02-03 04:07:01'),
(3, 3, 1, '<p>Hello everyone,</p><br /><p>I have put the new training times on the website.</p><br /><p>Many greetings</p><br /><p>Paul</p><br />', '2021-02-03 04:08:02'),
(4, 4, 354, '<p>Dear Ladies and Gentlemen,</p><br /><p>the board of directors hereby invites you to the annual members meeting in our clubhouse.</p><br /><p>Yours sincerely</p><br /><p>Paul Schmidt</p><br />', '2021-02-03 04:11:37'),
(5, 5, 354, 'Hi Paul,<br />can you reserve the room for the general meeting?<br />Greetings<br />Eric', '2021-02-03 04:12:35'),
(6, 6, 354, 'Hi Jennifer,<br />you haven\'t transferred your membership fee yet. <br />Can you please do it yet.<br />Regards<br />Eric', '2021-02-03 04:14:22'),
(7, 7, 355, '<p>Hi Dana and Daria,</p><br /><p>are you coming for training next week?</p><br /><p>Please write me a short answer</p><br /><p>Many greetings</p><br /><p>Jennifer</p><br />', '2021-02-03 04:16:19'),
(8, 8, 355, 'Hi Paul,<br />unfortunately I don\'t have access to the documents.<br />Can you check it out.<br />Regards<br />Jennifer', '2021-02-03 04:17:23'),
(9, 8, 1, 'Hi Jennifer,<br />I have redeposited the rights. Please check this again.<br />Greetings<br />Paul', '2021-02-03 04:18:18');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_messages_recipients`
--

CREATE TABLE `%PREFIX%_messages_recipients` (
  `msr_id` int UNSIGNED NOT NULL,
  `msr_msg_id` int UNSIGNED NOT NULL,
  `msr_rol_id` int UNSIGNED DEFAULT NULL,
  `msr_usr_id` int UNSIGNED DEFAULT NULL,
  `msr_role_mode` smallint NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_messages_recipients`
--

INSERT INTO `%PREFIX%_messages_recipients` (`msr_id`, `msr_msg_id`, `msr_rol_id`, `msr_usr_id`, `msr_role_mode`) VALUES
(1, 1, NULL, 313, 0),
(2, 1, NULL, 332, 0),
(3, 1, NULL, 355, 0),
(4, 1, NULL, 354, 0),
(5, 2, 3, NULL, 0),
(6, 3, 4, NULL, 0),
(7, 3, 5, NULL, 0),
(8, 3, NULL, 354, 0),
(9, 4, 2, NULL, 2),
(10, 4, NULL, 1, 0),
(11, 5, NULL, 1, 0),
(12, 6, NULL, 355, 0),
(13, 7, NULL, 216, 0),
(14, 7, NULL, 227, 0),
(15, 8, NULL, 355, 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_oidc_access_tokens`
--

CREATE TABLE `%PREFIX%_oidc_access_tokens` (
  `oat_id` int UNSIGNED NOT NULL,
  `oat_usr_id` int UNSIGNED NOT NULL,
  `oat_ocl_id` int UNSIGNED NOT NULL,
  `oat_token` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `oat_scope` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `oat_expires_at` timestamp NOT NULL,
  `oat_revoked` tinyint(1) DEFAULT '0',
  `oat_usr_id_create` int UNSIGNED DEFAULT NULL,
  `oat_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_oidc_auth_codes`
--

CREATE TABLE `%PREFIX%_oidc_auth_codes` (
  `oac_id` int UNSIGNED NOT NULL,
  `oac_usr_id` int UNSIGNED NOT NULL,
  `oac_ocl_id` int UNSIGNED NOT NULL,
  `oac_token` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `oac_scope` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `oac_nonce` varchar(2550) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `oac_expires_at` timestamp NOT NULL,
  `oac_revoked` tinyint(1) DEFAULT '0',
  `oac_redirect_uri` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `oac_used` tinyint(1) DEFAULT '0',
  `oac_usr_id_create` int UNSIGNED DEFAULT NULL,
  `oac_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_oidc_clients`
--

CREATE TABLE `%PREFIX%_oidc_clients` (
  `ocl_id` int UNSIGNED NOT NULL,
  `ocl_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ocl_client_id` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ocl_client_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ocl_enabled` tinyint(1) DEFAULT '1',
  `ocl_client_secret` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ocl_redirect_uri` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ocl_grant_types` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ocl_scope` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `ocl_userid_field` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'usr_id',
  `ocl_field_mapping` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `ocl_role_mapping` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `ocl_usr_id_create` int UNSIGNED DEFAULT NULL,
  `ocl_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ocl_usr_id_change` int UNSIGNED DEFAULT NULL,
  `ocl_timestamp_change` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_oidc_refresh_tokens`
--

CREATE TABLE `%PREFIX%_oidc_refresh_tokens` (
  `ort_id` int UNSIGNED NOT NULL,
  `ort_usr_id` int UNSIGNED NOT NULL,
  `ort_ocl_id` int UNSIGNED NOT NULL,
  `ort_token` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `ort_scope` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `ort_expires_at` timestamp NOT NULL,
  `ort_revoked` tinyint(1) DEFAULT '0',
  `ort_usr_id_create` int UNSIGNED DEFAULT NULL,
  `ort_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_organizations`
--

CREATE TABLE `%PREFIX%_organizations` (
  `org_id` int UNSIGNED NOT NULL,
  `org_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `org_shortname` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `org_longname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `org_org_id_parent` int UNSIGNED DEFAULT NULL,
  `org_homepage` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `org_email_administrator` varchar(254) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `org_show_org_select` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_organizations`
--

INSERT INTO `%PREFIX%_organizations` (`org_id`, `org_uuid`, `org_shortname`, `org_longname`, `org_org_id_parent`, `org_homepage`, `org_email_administrator`, `org_show_org_select`) VALUES
(1, 'f04eef83-91ad-40bf-8267-09cd40ce0799', 'DEMO', 'Demo-Organisation', NULL, 'https://www.admidio.org/demo/', 'administrator@admidio.org', 1),
(2, '8418cd76-3ac9-455f-bfb4-ed6561abdb7b', 'TEST', 'Test-Organisation', 1, 'https://www.admidio.org/demo/', 'administrator@admidio.org', 1);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_photos`
--

CREATE TABLE `%PREFIX%_photos` (
  `pho_id` int UNSIGNED NOT NULL,
  `pho_org_id` int UNSIGNED NOT NULL,
  `pho_pho_id_parent` int UNSIGNED DEFAULT NULL,
  `pho_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `pho_quantity` int UNSIGNED NOT NULL DEFAULT '0',
  `pho_name` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `pho_begin` date NOT NULL,
  `pho_end` date NOT NULL,
  `pho_description` varchar(4000) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `pho_photographers` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `pho_locked` tinyint(1) NOT NULL DEFAULT '0',
  `pho_usr_id_create` int UNSIGNED DEFAULT NULL,
  `pho_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pho_usr_id_change` int UNSIGNED DEFAULT NULL,
  `pho_timestamp_change` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_photos`
--

INSERT INTO `%PREFIX%_photos` (`pho_id`, `pho_org_id`, `pho_pho_id_parent`, `pho_uuid`, `pho_quantity`, `pho_name`, `pho_begin`, `pho_end`, `pho_description`, `pho_photographers`, `pho_locked`, `pho_usr_id_create`, `pho_timestamp_create`, `pho_usr_id_change`, `pho_timestamp_change`) VALUES
(1, 1, NULL, 'b4aaf3eb-8735-45b3-a2f0-f2a7e9d289eb', 0, 'Croatia', '2022-10-05', '2022-10-11', 'An unforgettable vacation in Croatia with most beautiful sunshine and much nature.', 'Steven Smith and others', 0, 1, '2022-10-23 16:15:37', NULL, NULL),
(2, 1, 1, '3d45f9cf-957e-41be-bb48-f452429fcd05', 5, 'Plitvice lakes', '2022-10-05', '2022-10-07', NULL, 'Steven Smith and others', 0, 1, '2022-10-23 16:17:44', NULL, NULL),
(3, 1, 1, 'bf174cf8-f190-4898-bb3e-af881ad68780', 4, 'Krka', '2022-10-08', '2022-10-11', NULL, 'Steven Smith and others', 0, 1, '2022-10-23 16:18:44', NULL, NULL),
(4, 1, NULL, 'f6af3421-f80c-4145-89f2-75bec24640b8', 6, 'Machu Picchu', '2022-09-14', '2022-09-17', 'A trip to the legendary Inca city of Machu Picchu in the mountains of Peru.', 'Admin', 0, 1, '2022-10-23 16:20:50', NULL, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_preferences`
--

CREATE TABLE `%PREFIX%_preferences` (
  `prf_id` int UNSIGNED NOT NULL,
  `prf_org_id` int UNSIGNED NOT NULL,
  `prf_name` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `prf_value` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_preferences`
--

INSERT INTO `%PREFIX%_preferences` (`prf_id`, `prf_org_id`, `prf_name`, `prf_value`) VALUES
(1000, 1, 'announcements_per_page', '10'),
(1100, 1, 'photo_ecard_scale', '500'),
(2000, 1, 'photo_ecard_template', 'postcard.tpl'),
(2200, 1, 'announcements_module_enabled', '1'),
(2300, 1, 'enable_auto_login', '1'),
(2305, 1, 'security_login_email_address_enabled', '0'),
(2400, 1, 'events_ical_export_enabled', '1'),
(2500, 1, 'events_module_enabled', '1'),
(2600, 1, 'documents_files_module_enabled', '1'),
(2700, 1, 'photo_ecard_enabled', '1'),
(2950, 1, 'system_notifications_new_entries', '0'),
(2955, 1, 'system_notifications_role', 'a8fd58c3-c926-40ca-96fb-5db86bfe6a16'),
(2960, 1, 'system_notifications_profile_changes', '0'),
(3700, 1, 'enable_password_recovery', '1'),
(3710, 1, 'two_factor_authentication_enabled', '0'),
(3800, 1, 'photo_module_enabled', '1'),
(3900, 1, 'registration_enable_captcha', '1'),
(4000, 1, 'registration_send_notification_email', '0'),
(4100, 1, 'enable_rss', '1'),
(4200, 1, 'system_notifications_enabled', '0'),
(4305, 1, 'category_report_default_configuration', '1'),
(4330, 1, 'events_view', 'detail'),
(4400, 1, 'events_per_page', '10'),
(4600, 1, 'events_show_map_link', '1'),
(4700, 1, 'events_rooms_enabled', '0'),
(4705, 1, 'events_list_configuration', '13'),
(4710, 1, 'events_save_cancellations', '1'),
(4720, 1, 'events_may_take_part', '1'),
(4900, 1, 'default_country', 'DEU'),
(6300, 1, 'homepage_logout', 'modules/announcements.php'),
(6400, 1, 'homepage_login', 'modules/overview.php'),
(6455, 1, 'groups_roles_export', '1'),
(6600, 1, 'groups_roles_members_per_page', '25'),
(6705, 1, 'groups_roles_default_configuration', '1'),
(6710, 1, 'groups_roles_show_former_members', '2'),
(6720, 1, 'groups_roles_edit_lists', '1'),
(6800, 1, 'logout_minutes', '20'),
(6900, 1, 'mail_number_recipients', '50'),
(6910, 1, 'mail_character_encoding', 'utf-8'),
(6915, 1, 'mail_delivery_confirmation', '0'),
(6920, 1, 'mail_html_registered_users', '1'),
(6925, 1, 'mail_into_to', '0'),
(6927, 1, 'mail_show_former', '1'),
(6930, 1, 'mail_max_receiver', '4'),
(6940, 1, 'mail_save_attachments', '0'),
(7000, 1, 'mail_sendmail_address', 'demo@admidio.org'),
(7010, 1, 'mail_sendmail_name', 'Demo-User'),
(7020, 1, 'mail_send_method', 'phpmail'),
(7025, 1, 'mail_sending_mode', '1'),
(7030, 1, 'mail_smtp_host', ''),
(7040, 1, 'mail_smtp_auth', '1'),
(7050, 1, 'mail_smtp_port', '587'),
(7060, 1, 'mail_smtp_secure', 'tls'),
(7070, 1, 'mail_smtp_authentication_type', ''),
(7080, 1, 'mail_smtp_user', ''),
(7090, 1, 'mail_smtp_password', ''),
(7095, 1, 'mail_send_to_all_addresses', '1'),
(7098, 1, 'mail_template', 'default.html'),
(7100, 1, 'max_email_attachment_size', '1'),
(7200, 1, 'documents_files_max_upload_size', '3'),
(7250, 1, 'contacts_per_page', '25'),
(7251, 1, 'contacts_field_history_days', '365'),
(7252, 1, 'contacts_list_configuration', '15'),
(7253, 1, 'contacts_show_all', '1'),
(7300, 1, 'photo_image_text', '© demo.admidio.org'),
(7305, 1, 'photo_image_text_size', '40'),
(7350, 1, 'photo_albums_per_page', '24'),
(7500, 1, 'photo_show_width', '1200'),
(7600, 1, 'photo_show_height', '1200'),
(7700, 1, 'photo_show_mode', '1'),
(7800, 1, 'photo_thumbs_scale', '500'),
(7900, 1, 'photo_thumbs_page', '24'),
(8000, 1, 'photo_keep_original', '0'),
(8100, 1, 'photo_download_enabled', '1'),
(8300, 1, 'profile_photo_storage', '0'),
(8400, 1, 'profile_show_map_link', '1'),
(8500, 1, 'profile_show_roles', '1'),
(8600, 1, 'profile_show_former_roles', '1'),
(8700, 1, 'profile_show_extern_roles', '1'),
(8810, 1, 'registration_adopt_all_data', '0'),
(8900, 1, 'theme', 'simple'),
(9000, 1, 'weblinks_per_page', '0'),
(9100, 1, 'weblinks_redirect_seconds', '10'),
(9200, 1, 'weblinks_target', '_blank'),
(9300, 1, 'system_currency', '€'),
(9305, 1, 'system_cookie_note', '1'),
(9310, 1, 'system_date', 'd.m.Y'),
(9320, 1, 'system_js_editor_enabled', '1'),
(9340, 1, 'system_language', 'en'),
(9360, 1, 'system_search_similar', '1'),
(9365, 1, 'system_show_create_edit', '1'),
(9370, 1, 'system_time', 'H:i'),
(9375, 1, 'system_url_imprint', 'https://www.admidio.org/imprint.php'),
(9380, 1, 'system_url_data_protection', 'https://www.admidio.org/data_protection.php'),
(9400, 1, 'captcha_background_color', '#FFEFC4'),
(9420, 1, 'captcha_fonts', 'AHGBold.ttf'),
(9430, 1, 'captcha_width', '250'),
(9460, 1, 'captcha_signature', 'Powered by Admidio.org'),
(9480, 1, 'captcha_type', 'pic'),
(10001, 2, 'announcements_per_page', '10'),
(11001, 2, 'photo_ecard_scale', '500'),
(20001, 2, 'photo_ecard_template', 'postcard.tpl'),
(22001, 2, 'announcements_module_enabled', '1'),
(23001, 2, 'enable_auto_login', '1'),
(23005, 2, 'security_login_email_address_enabled', '0'),
(24001, 2, 'events_ical_export_enabled', '1'),
(25001, 2, 'events_module_enabled', '1'),
(26001, 2, 'documents_files_module_enabled', '1'),
(27001, 2, 'photo_ecard_enabled', '1'),
(29501, 2, 'system_notifications_new_entries', '0'),
(29510, 2, 'system_notifications_profile_changes', '0'),
(29550, 2, 'system_notifications_role', '7a9e3ff4-197a-48db-9abc-c32c4cc79567'),
(37001, 2, 'enable_password_recovery', '1'),
(37010, 2, 'two_factor_authentication_enabled', '0'),
(38001, 2, 'photo_module_enabled', '1'),
(39001, 2, 'registration_enable_captcha', '1'),
(40001, 2, 'registration_send_notification_email', '0'),
(41001, 2, 'enable_rss', '1'),
(42001, 2, 'system_notifications_enabled', '0'),
(43055, 2, 'category_report_default_configuration', '2'),
(43300, 2, 'events_view', 'detail'),
(44001, 2, 'events_per_page', '10'),
(46001, 2, 'events_show_map_link', '1'),
(47001, 2, 'events_rooms_enabled', '0'),
(47005, 2, 'events_list_configuration', '14'),
(47010, 2, 'events_save_cancellations', '1'),
(47020, 2, 'events_may_take_part', '1'),
(49001, 2, 'default_country', 'DEU'),
(63001, 2, 'homepage_logout', 'modules/announcements.php'),
(64001, 2, 'homepage_login', 'modules/overview.php'),
(64055, 2, 'groups_roles_export', '1'),
(66001, 2, 'groups_roles_members_per_page', '25'),
(67005, 2, 'groups_roles_default_configuration', '9'),
(67010, 2, 'groups_roles_show_former_members', '2'),
(67020, 2, 'groups_roles_edit_lists', '1'),
(68001, 2, 'logout_minutes', '20'),
(69001, 2, 'mail_number_recipients', '50'),
(69100, 2, 'mail_character_encoding', 'utf-8'),
(69150, 2, 'mail_delivery_confirmation', '0'),
(69200, 2, 'mail_html_registered_users', '1'),
(69250, 2, 'mail_into_to', '0'),
(69270, 2, 'mail_show_former', '1'),
(69300, 2, 'mail_max_receiver', '1'),
(69400, 2, 'mail_save_attachments', '0'),
(70001, 2, 'mail_sendmail_address', 'demo@admidio.org'),
(70010, 2, 'mail_sendmail_name', 'Demo-User'),
(70020, 2, 'mail_send_method', 'phpmail'),
(70025, 2, 'mail_sending_mode', '1'),
(70030, 2, 'mail_smtp_host', ''),
(70040, 2, 'mail_smtp_auth', '1'),
(70050, 2, 'mail_smtp_port', '587'),
(70060, 2, 'mail_smtp_secure', 'tls'),
(70070, 2, 'mail_smtp_authentication_type', ''),
(70080, 2, 'mail_smtp_user', ''),
(70090, 2, 'mail_smtp_password', ''),
(70095, 2, 'mail_send_to_all_addresses', '1'),
(70098, 2, 'mail_template', 'default.html'),
(71001, 2, 'max_email_attachment_size', '1'),
(72001, 2, 'documents_files_max_upload_size', '3'),
(72500, 2, 'contacts_per_page', '25'),
(72510, 2, 'contacts_field_history_days', '365'),
(72515, 2, 'contacts_list_configuration', '16'),
(72520, 2, 'contacts_show_all', '0'),
(73000, 2, 'photo_albums_per_page', '24'),
(73025, 2, 'photo_image_text', '© demo.admidio.org'),
(73050, 2, 'photo_image_text_size', '40'),
(75001, 2, 'photo_show_width', '1200'),
(76001, 2, 'photo_show_height', '1200'),
(77001, 2, 'photo_show_mode', '1'),
(78001, 2, 'photo_thumbs_scale', '500'),
(79001, 2, 'photo_thumbs_page', '24'),
(80001, 2, 'photo_keep_original', '0'),
(81001, 2, 'photo_download_enabled', '0'),
(83001, 2, 'profile_photo_storage', '0'),
(84001, 2, 'profile_show_map_link', '1'),
(85001, 2, 'profile_show_roles', '1'),
(86001, 2, 'profile_show_former_roles', '1'),
(87001, 2, 'profile_show_extern_roles', '1'),
(88010, 2, 'registration_adopt_all_data', '0'),
(89001, 2, 'theme', 'simple'),
(90001, 2, 'weblinks_per_page', '0'),
(91001, 2, 'weblinks_redirect_seconds', '10'),
(92001, 2, 'weblinks_target', '_blank'),
(93010, 2, 'system_currency', '€'),
(93015, 2, 'system_cookie_note', '1'),
(93020, 2, 'system_date', 'd.m.Y'),
(93030, 2, 'system_js_editor_enabled', '1'),
(93050, 2, 'system_language', 'en'),
(93070, 2, 'system_search_similar', '1'),
(93080, 2, 'system_time', 'H:i'),
(93650, 2, 'system_show_create_edit', '2'),
(93750, 2, 'system_url_imprint', 'https://www.admidio.org/imprint.php'),
(93800, 2, 'system_url_data_protection', 'https://www.admidio.org/data_protection.php'),
(94000, 2, 'captcha_background_color', '#FFEFC4'),
(94020, 2, 'captcha_fonts', 'AHGBold.ttf'),
(94030, 2, 'captcha_width', '250'),
(94060, 2, 'captcha_signature', 'Powered by Admidio.org'),
(94080, 2, 'captcha_type', 'pic'),
(94081, 1, 'password_min_strength', '1'),
(94082, 1, 'system_browser_update_check', '0'),
(94083, 1, 'system_hashing_cost', '10'),
(94084, 1, 'mail_recipients_with_roles', '1'),
(94085, 1, 'captcha_lines_numbers', '5'),
(94086, 1, 'captcha_perturbation', '0.75'),
(94087, 1, 'captcha_background_image', ''),
(94088, 1, 'captcha_text_color', '#707070'),
(94089, 1, 'captcha_line_color', '#707070'),
(94090, 1, 'captcha_charset', '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxy'),
(94091, 1, 'contacts_user_relations_enabled', '1'),
(94181, 2, 'password_min_strength', '1'),
(94182, 2, 'system_browser_update_check', '0'),
(94183, 2, 'system_hashing_cost', '10'),
(94184, 2, 'mail_recipients_with_roles', '1'),
(94185, 2, 'captcha_lines_numbers', '5'),
(94186, 2, 'captcha_perturbation', '0.75'),
(94187, 2, 'captcha_background_image', ''),
(94188, 2, 'captcha_text_color', '#707070'),
(94189, 2, 'captcha_line_color', '#707070'),
(94190, 2, 'captcha_charset', '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxy'),
(94191, 2, 'contacts_user_relations_enabled', '1'),
(94192, 1, 'theme_fallback', 'simple'),
(94193, 1, 'color_primary', '#349aaa'),
(94194, 1, 'color_secondary', '#263340'),
(94195, 1, 'logo_file', ''),
(94196, 1, 'logo_file_max_height', '60'),
(94197, 1, 'admidio_headline', 'Mitgliederverwaltung'),
(94198, 1, 'favicon_file', ''),
(94199, 1, 'additional_styles_file', ''),
(94200, 1, 'registration_module_enabled', '1'),
(94201, 1, 'registration_manual_approval', '1'),
(94202, 1, 'changelog_module_enabled', '1'),
(94203, 1, 'changelog_table_user_data', '1'),
(94204, 1, 'changelog_table_users', '1'),
(94205, 1, 'changelog_table_members', '1'),
(94206, 1, 'changelog_table_user_fields', '0'),
(94207, 1, 'changelog_table_user_field_select_options', '0'),
(94208, 1, 'changelog_table_announcements', '0'),
(94209, 1, 'changelog_table_events', '0'),
(94210, 1, 'changelog_table_rooms', '0'),
(94211, 1, 'changelog_table_roles', '0'),
(94212, 1, 'changelog_table_role_dependencies', '0'),
(94213, 1, 'changelog_table_roles_rights', '0'),
(94214, 1, 'changelog_table_roles_rights_data', '0'),
(94215, 1, 'changelog_table_categories', '0'),
(94216, 1, 'changelog_table_category_report', '0'),
(94217, 1, 'changelog_table_links', '0'),
(94218, 1, 'changelog_table_folders', '0'),
(94219, 1, 'changelog_table_files', '0'),
(94220, 1, 'changelog_table_organizations', '0'),
(94221, 1, 'changelog_table_menu', '0'),
(94222, 1, 'changelog_table_user_relation_types', '0'),
(94223, 1, 'changelog_table_user_relations', '1'),
(94224, 1, 'changelog_table_photos', '0'),
(94225, 1, 'changelog_table_lists', '0'),
(94226, 1, 'changelog_table_list_columns', '0'),
(94227, 1, 'changelog_table_preferences', '0'),
(94228, 1, 'changelog_table_texts', '0'),
(94229, 1, 'changelog_table_forum_topics', '0'),
(94230, 1, 'changelog_table_forum_posts', '0'),
(94231, 1, 'changelog_table_inventory_fields', '0'),
(94232, 1, 'changelog_table_inventory_field_select_options', '0'),
(94233, 1, 'changelog_table_inventory_items', '0'),
(94234, 1, 'changelog_table_inventory_item_data', '0'),
(94235, 1, 'changelog_table_inventory_item_borrow_data', '0'),
(94236, 1, 'changelog_table_saml_clients', '0'),
(94237, 1, 'changelog_table_oidc_clients', '0'),
(94238, 1, 'changelog_table_others', '0'),
(94239, 1, 'announcements_clamp_text_lines', '0'),
(94240, 1, 'category_report_module_enabled', '1'),
(94241, 1, 'inventory_module_enabled', '1'),
(94242, 1, 'inventory_visible_for', ''),
(94243, 1, 'inventory_items_per_page', '25'),
(94244, 1, 'inventory_field_history_days', '365'),
(94245, 1, 'inventory_item_picture_enabled', '1'),
(94246, 1, 'inventory_item_picture_storage', '0'),
(94247, 1, 'inventory_item_picture_width', '130'),
(94248, 1, 'inventory_item_picture_height', '170'),
(94249, 1, 'inventory_show_obsolete_select_field_options', '1'),
(94250, 1, 'inventory_system_field_names_editable', '0'),
(94251, 1, 'inventory_allow_keeper_edit', '0'),
(94252, 1, 'inventory_allowed_keeper_edit_fields', 'LAST_RECEIVER,BORROW_DATE,RETURN_DATE'),
(94253, 1, 'inventory_current_user_default_keeper', '0'),
(94254, 1, 'inventory_allow_negative_numbers', '1'),
(94255, 1, 'inventory_decimal_places', '1'),
(94256, 1, 'inventory_field_date_time_format', 'date'),
(94257, 1, 'inventory_items_disable_borrowing', '0'),
(94258, 1, 'inventory_profile_view_enabled', '1'),
(94259, 1, 'inventory_profile_view', 'LAST_RECEIVER'),
(94260, 1, 'inventory_export_filename', 'Inventarverwaltung'),
(94261, 1, 'inventory_add_date', '0'),
(94262, 1, 'events_clamp_text_lines', '0'),
(94263, 1, 'forum_module_enabled', '1'),
(94264, 1, 'forum_posts_per_page', '15'),
(94265, 1, 'forum_topics_per_page', '25'),
(94266, 1, 'forum_view', 'cards'),
(94267, 1, 'groups_roles_module_enabled', '1'),
(94268, 1, 'mail_module_enabled', '1'),
(94269, 1, 'pm_module_enabled', '1'),
(94270, 1, 'mail_captcha_enabled', '1'),
(94271, 1, 'profile_show_obsolete_select_field_options', '1'),
(94272, 1, 'profile_show_empty_fields', '1'),
(94273, 1, 'sso_saml_enabled', '0'),
(94274, 1, 'sso_saml_entity_id', 'http://localhost/GitHub/admidio'),
(94275, 1, 'sso_saml_want_requests_signed', '1'),
(94276, 1, 'sso_saml_signing_key', '0'),
(94277, 1, 'sso_saml_encryption_key', '0'),
(94278, 1, 'sso_oidc_enabled', '0'),
(94279, 1, 'sso_oidc_issuer_url', 'http://localhost/GitHub/admidio/modules/sso/index.php'),
(94280, 1, 'sso_oidc_signing_key', '0'),
(94281, 1, 'sso_oidc_encryption_key', ''),
(94282, 1, 'weblinks_module_enabled', '1'),
(94283, 2, 'theme_fallback', 'simple'),
(94284, 2, 'color_primary', '#349aaa'),
(94285, 2, 'color_secondary', '#263340'),
(94286, 2, 'logo_file', ''),
(94287, 2, 'logo_file_max_height', '60'),
(94288, 2, 'admidio_headline', 'Mitgliederverwaltung'),
(94289, 2, 'favicon_file', ''),
(94290, 2, 'additional_styles_file', ''),
(94291, 2, 'registration_module_enabled', '1'),
(94292, 2, 'registration_manual_approval', '1'),
(94293, 2, 'changelog_module_enabled', '1'),
(94294, 2, 'changelog_table_user_data', '1'),
(94295, 2, 'changelog_table_users', '1'),
(94296, 2, 'changelog_table_members', '1'),
(94297, 2, 'changelog_table_user_fields', '0'),
(94298, 2, 'changelog_table_user_field_select_options', '0'),
(94299, 2, 'changelog_table_announcements', '0'),
(94300, 2, 'changelog_table_events', '0'),
(94301, 2, 'changelog_table_rooms', '0'),
(94302, 2, 'changelog_table_roles', '0'),
(94303, 2, 'changelog_table_role_dependencies', '0'),
(94304, 2, 'changelog_table_roles_rights', '0'),
(94305, 2, 'changelog_table_roles_rights_data', '0'),
(94306, 2, 'changelog_table_categories', '0'),
(94307, 2, 'changelog_table_category_report', '0'),
(94308, 2, 'changelog_table_links', '0'),
(94309, 2, 'changelog_table_folders', '0'),
(94310, 2, 'changelog_table_files', '0'),
(94311, 2, 'changelog_table_organizations', '0'),
(94312, 2, 'changelog_table_menu', '0'),
(94313, 2, 'changelog_table_user_relation_types', '0'),
(94314, 2, 'changelog_table_user_relations', '1'),
(94315, 2, 'changelog_table_photos', '0'),
(94316, 2, 'changelog_table_lists', '0'),
(94317, 2, 'changelog_table_list_columns', '0'),
(94318, 2, 'changelog_table_preferences', '0'),
(94319, 2, 'changelog_table_texts', '0'),
(94320, 2, 'changelog_table_forum_topics', '0'),
(94321, 2, 'changelog_table_forum_posts', '0'),
(94322, 2, 'changelog_table_inventory_fields', '0'),
(94323, 2, 'changelog_table_inventory_field_select_options', '0'),
(94324, 2, 'changelog_table_inventory_items', '0'),
(94325, 2, 'changelog_table_inventory_item_data', '0'),
(94326, 2, 'changelog_table_inventory_item_borrow_data', '0'),
(94327, 2, 'changelog_table_saml_clients', '0'),
(94328, 2, 'changelog_table_oidc_clients', '0'),
(94329, 2, 'changelog_table_others', '0'),
(94330, 2, 'announcements_clamp_text_lines', '0'),
(94331, 2, 'category_report_module_enabled', '1'),
(94332, 2, 'inventory_module_enabled', '0'),
(94333, 2, 'inventory_visible_for', ''),
(94334, 2, 'inventory_items_per_page', '25'),
(94335, 2, 'inventory_field_history_days', '365'),
(94336, 2, 'inventory_item_picture_enabled', '1'),
(94337, 2, 'inventory_item_picture_storage', '0'),
(94338, 2, 'inventory_item_picture_width', '130'),
(94339, 2, 'inventory_item_picture_height', '170'),
(94340, 2, 'inventory_show_obsolete_select_field_options', '1'),
(94341, 2, 'inventory_system_field_names_editable', '0'),
(94342, 2, 'inventory_allow_keeper_edit', '0'),
(94343, 2, 'inventory_allowed_keeper_edit_fields', 'LAST_RECEIVER,BORROW_DATE,RETURN_DATE'),
(94344, 2, 'inventory_current_user_default_keeper', '0'),
(94345, 2, 'inventory_allow_negative_numbers', '1'),
(94346, 2, 'inventory_decimal_places', '1'),
(94347, 2, 'inventory_field_date_time_format', 'date'),
(94348, 2, 'inventory_items_disable_borrowing', '0'),
(94349, 2, 'inventory_profile_view_enabled', '1'),
(94350, 2, 'inventory_profile_view', 'LAST_RECEIVER'),
(94351, 2, 'inventory_export_filename', 'Inventarverwaltung'),
(94352, 2, 'inventory_add_date', '0'),
(94353, 2, 'events_clamp_text_lines', '0'),
(94354, 2, 'forum_module_enabled', '0'),
(94355, 2, 'forum_posts_per_page', '15'),
(94356, 2, 'forum_topics_per_page', '25'),
(94357, 2, 'forum_view', 'cards'),
(94358, 2, 'groups_roles_module_enabled', '1'),
(94359, 2, 'mail_module_enabled', '1'),
(94360, 2, 'pm_module_enabled', '1'),
(94361, 2, 'mail_captcha_enabled', '1'),
(94362, 2, 'profile_show_obsolete_select_field_options', '1'),
(94363, 2, 'profile_show_empty_fields', '1'),
(94364, 2, 'sso_saml_enabled', '0'),
(94365, 2, 'sso_saml_entity_id', 'http://localhost/GitHub/admidio'),
(94366, 2, 'sso_saml_want_requests_signed', '1'),
(94367, 2, 'sso_saml_signing_key', '0'),
(94368, 2, 'sso_saml_encryption_key', '0'),
(94369, 2, 'sso_oidc_enabled', '0'),
(94370, 2, 'sso_oidc_issuer_url', 'http://localhost/GitHub/admidio/modules/sso/index.php'),
(94371, 2, 'sso_oidc_signing_key', '0'),
(94372, 2, 'sso_oidc_encryption_key', ''),
(94373, 2, 'weblinks_module_enabled', '1');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_registrations`
--

CREATE TABLE `%PREFIX%_registrations` (
  `reg_id` int UNSIGNED NOT NULL,
  `reg_org_id` int UNSIGNED NOT NULL,
  `reg_usr_id` int UNSIGNED NOT NULL,
  `reg_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reg_validation_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_registrations`
--

INSERT INTO `%PREFIX%_registrations` (`reg_id`, `reg_org_id`, `reg_usr_id`, `reg_timestamp`, `reg_validation_id`) VALUES
(1, 1, 352, '2025-09-30 11:45:23', 'sdovijoi2342lfvsdnmvoi32n5249090fewklfn342klnklf9'),
(2, 1, 353, '2025-09-28 18:54:12', NULL),
(3, 2, 360, '2025-09-30 11:45:23', NULL),
(4, 1, 359, '2025-10-01 03:03:52', NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_roles`
--

CREATE TABLE `%PREFIX%_roles` (
  `rol_id` int UNSIGNED NOT NULL,
  `rol_cat_id` int UNSIGNED NOT NULL,
  `rol_lst_id` int UNSIGNED DEFAULT NULL,
  `rol_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `rol_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `rol_description` varchar(4000) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `rol_assign_roles` tinyint(1) NOT NULL DEFAULT '0',
  `rol_approve_users` tinyint(1) NOT NULL DEFAULT '0',
  `rol_announcements` tinyint(1) NOT NULL DEFAULT '0',
  `rol_events` tinyint(1) NOT NULL DEFAULT '0',
  `rol_documents_files` tinyint(1) NOT NULL DEFAULT '0',
  `rol_edit_user` tinyint(1) NOT NULL DEFAULT '0',
  `rol_mail_to_all` tinyint(1) NOT NULL DEFAULT '0',
  `rol_mail_this_role` smallint NOT NULL DEFAULT '0',
  `rol_photo` tinyint(1) NOT NULL DEFAULT '0',
  `rol_profile` tinyint(1) NOT NULL DEFAULT '0',
  `rol_weblinks` tinyint(1) NOT NULL DEFAULT '0',
  `rol_all_lists_view` tinyint(1) NOT NULL DEFAULT '0',
  `rol_default_registration` tinyint(1) NOT NULL DEFAULT '0',
  `rol_leader_rights` smallint NOT NULL DEFAULT '0',
  `rol_view_memberships` smallint NOT NULL DEFAULT '0',
  `rol_view_members_profiles` smallint NOT NULL DEFAULT '0',
  `rol_start_date` date DEFAULT NULL,
  `rol_start_time` time DEFAULT NULL,
  `rol_end_date` date DEFAULT NULL,
  `rol_end_time` time DEFAULT NULL,
  `rol_weekday` smallint DEFAULT NULL,
  `rol_location` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `rol_max_members` int DEFAULT NULL,
  `rol_cost` float DEFAULT NULL,
  `rol_cost_period` smallint DEFAULT NULL,
  `rol_usr_id_create` int UNSIGNED DEFAULT NULL,
  `rol_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `rol_usr_id_change` int UNSIGNED DEFAULT NULL,
  `rol_timestamp_change` timestamp NULL DEFAULT NULL,
  `rol_valid` tinyint(1) NOT NULL DEFAULT '1',
  `rol_system` tinyint(1) NOT NULL DEFAULT '0',
  `rol_administrator` tinyint(1) NOT NULL DEFAULT '0',
  `rol_forum_admin` tinyint(1) NOT NULL DEFAULT '0',
  `rol_inventory_admin` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_roles`
--

INSERT INTO `%PREFIX%_roles` (`rol_id`, `rol_cat_id`, `rol_lst_id`, `rol_uuid`, `rol_name`, `rol_description`, `rol_assign_roles`, `rol_approve_users`, `rol_announcements`, `rol_events`, `rol_documents_files`, `rol_edit_user`, `rol_mail_to_all`, `rol_mail_this_role`, `rol_photo`, `rol_profile`, `rol_weblinks`, `rol_all_lists_view`, `rol_default_registration`, `rol_leader_rights`, `rol_view_memberships`, `rol_view_members_profiles`, `rol_start_date`, `rol_start_time`, `rol_end_date`, `rol_end_time`, `rol_weekday`, `rol_location`, `rol_max_members`, `rol_cost`, `rol_cost_period`, `rol_usr_id_create`, `rol_timestamp_create`, `rol_usr_id_change`, `rol_timestamp_change`, `rol_valid`, `rol_system`, `rol_administrator`, `rol_forum_admin`, `rol_inventory_admin`) VALUES
(1, 3, NULL, 'a8fd58c3-c926-40ca-96fb-5db86bfe6a16', 'Administrator', 'Group of system administrators', 1, 1, 1, 1, 1, 1, 1, 3, 1, 1, 1, 1, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2008-04-20 20:35:08', 1, '2008-04-20 20:35:08', 1, 0, 1, 1, 1),
(2, 3, NULL, 'd1dc4c6e-eb17-4d1a-a491-237257f6b1fb', 'Member', 'All organization members', 0, 0, 0, 0, 0, 0, 0, 2, 0, 1, 0, 0, 1, 1, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 1, '2008-05-03 14:26:36', 1, '2008-05-03 14:26:36', 1, 0, 0, 0, 0),
(3, 3, NULL, '621fa25f-2fac-4310-af52-af939041cb66', 'Association\'s board', 'Administrative board of association', 0, 0, 1, 1, 1, 1, 1, 3, 1, 1, 1, 1, 0, 1, 2, 2, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 1, '2008-05-03 14:26:12', 1, '2008-05-03 14:26:12', 1, 0, 0, 0, 0),
(4, 6, NULL, '685c8a84-e58c-4d40-8297-8d2671e1fb89', '1. youth team', 'Young people between 12 and 15 years', 0, 0, 0, 0, 0, 0, 0, 2, 0, 1, 0, 0, 0, 3, 1, 1, NULL, '15:00:00', NULL, '16:00:00', 3, 'Sportplatz', NULL, NULL, NULL, 1, '2008-05-03 14:24:41', 1, '2008-05-03 14:24:41', 1, 0, 0, 0, 0),
(5, 6, NULL, '5f4fb933-806c-4161-a333-212cba85ae6c', '2. youth team', 'Young people between 16 and 18 years', 0, 0, 0, 0, 0, 0, 0, 2, 0, 1, 0, 0, 0, 3, 1, 1, NULL, '16:00:00', NULL, '17:00:00', 5, 'Sportplatz', NULL, NULL, NULL, 1, '2008-05-03 14:25:58', 1, '2008-05-03 14:25:58', 1, 0, 0, 0, 0),
(6, 100, NULL, '7a9e3ff4-197a-48db-9abc-c32c4cc79567', 'Administrator', 'Group of system administrators', 1, 1, 1, 1, 1, 1, 1, 3, 1, 1, 1, 1, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2009-05-20 20:35:08', 1, '2010-01-21 19:35:08', 1, 0, 1, 1, 1),
(7, 100, NULL, '77b0c6cc-cc66-4384-a34e-3277cdf081c6', 'Member', 'All organization members', 0, 0, 0, 0, 0, 0, 0, 2, 0, 1, 0, 0, 1, 1, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 1, '2009-05-20 14:26:36', 1, '2010-12-22 05:34:06', 1, 0, 0, 0, 0),
(8, 200, NULL, '515c99a1-28d6-4395-b966-4b04cd512f12', '2026-01-08 17:00 Barbecue', 'Today we have our barbecue. In addition to crisp sausages, chops and bacon, there are also various salads.', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2011-06-03 08:08:00', NULL, NULL, 1, 0, 0, 0, 0),
(9, 200, NULL, '1b3d4123-2898-40e5-b9c4-b4db65207133', '2025-11-12 11:00 Yoga for beginners', 'This course teaches the basics of yoga.<br /><br />A registration for this course is required.', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2016-11-24 11:08:23', NULL, NULL, 1, 0, 0, 0, 0),
(10, 200, NULL, '040b4f49-2e45-460a-a354-1004d8bef27e', '2026-01-01 18:00 Board meeting', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2011-06-03 08:08:00', NULL, NULL, 1, 0, 0, 0, 0),
(11, 200, NULL, '7450a81b-5b69-43c6-906b-47e343ecb55f', '2025-12-06 19:00 Board meeting', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2011-06-03 08:08:00', NULL, NULL, 1, 0, 0, 0, 0),
(12, 200, NULL, '3c16c9da-9425-4ee3-9b53-8aed1c19bc34', '2025-11-22 17:00 Team evening', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2018-02-14 17:38:18', NULL, NULL, 1, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_roles_rights`
--

CREATE TABLE `%PREFIX%_roles_rights` (
  `ror_id` int UNSIGNED NOT NULL,
  `ror_name_intern` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ror_table` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ror_ror_id_parent` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_roles_rights`
--

INSERT INTO `%PREFIX%_roles_rights` (`ror_id`, `ror_name_intern`, `ror_table`, `ror_ror_id_parent`) VALUES
(1, 'folder_view', '%PREFIX%_folders', NULL),
(2, 'folder_upload', '%PREFIX%_folders', NULL),
(3, 'category_view', '%PREFIX%_categories', NULL),
(4, 'category_edit', '%PREFIX%_categories', 3),
(5, 'event_participation', '%PREFIX%_events', NULL),
(6, 'menu_view', '%PREFIX%_menu', NULL),
(7, 'sso_saml_access', '%PREFIX%_saml_clients', NULL),
(8, 'sso_oidc_access', '%PREFIX%_oidc_clients', NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_roles_rights_data`
--

CREATE TABLE `%PREFIX%_roles_rights_data` (
  `rrd_id` int UNSIGNED NOT NULL,
  `rrd_ror_id` int UNSIGNED NOT NULL,
  `rrd_rol_id` int UNSIGNED NOT NULL,
  `rrd_object_id` int UNSIGNED NOT NULL,
  `rrd_usr_id_create` int UNSIGNED DEFAULT NULL,
  `rrd_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_roles_rights_data`
--

INSERT INTO `%PREFIX%_roles_rights_data` (`rrd_id`, `rrd_ror_id`, `rrd_rol_id`, `rrd_object_id`, `rrd_usr_id_create`, `rrd_timestamp_create`) VALUES
(1, 1, 3, 3, 1, '2015-01-08 12:12:05'),
(2, 2, 3, 1, 1, '2015-03-08 14:56:35'),
(3, 2, 1, 2, 1, '2016-10-07 23:45:05'),
(4, 2, 3, 3, 1, '2016-10-08 09:23:05'),
(5, 5, 3, 15, 1, '2016-10-08 09:23:05'),
(6, 5, 3, 16, 1, '2016-10-08 09:23:05'),
(7, 5, 7, 14, 1, '2016-10-08 09:23:05'),
(8, 5, 3, 4, 1, '2016-10-08 09:23:05'),
(9, 5, 5, 17, 355, '2018-02-14 17:38:18'),
(10, 4, 2, 303, 1, '2025-10-05 12:56:05');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_role_dependencies`
--

CREATE TABLE `%PREFIX%_role_dependencies` (
  `rld_rol_id_parent` int UNSIGNED NOT NULL,
  `rld_rol_id_child` int UNSIGNED NOT NULL,
  `rld_comment` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `rld_usr_id` int UNSIGNED DEFAULT NULL,
  `rld_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_rooms`
--

CREATE TABLE `%PREFIX%_rooms` (
  `room_id` int UNSIGNED NOT NULL,
  `room_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `room_name` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `room_description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `room_capacity` int NOT NULL,
  `room_overhang` int DEFAULT NULL,
  `room_usr_id_create` int UNSIGNED DEFAULT NULL,
  `room_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `room_usr_id_change` int UNSIGNED DEFAULT NULL,
  `room_timestamp_change` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_rooms`
--

INSERT INTO `%PREFIX%_rooms` (`room_id`, `room_uuid`, `room_name`, `room_description`, `room_capacity`, `room_overhang`, `room_usr_id_create`, `room_timestamp_create`, `room_usr_id_change`, `room_timestamp_change`) VALUES
(1, 'fcc15de8-0c3c-4e2a-a3a5-df20f0fee1c3', 'Meeting room', 'In this room meetings can take place. The room must be reserved in advance. A projector is available.', 15, NULL, 1, '2011-04-07 17:15:08', NULL, NULL),
(2, '0faef968-2a2d-41bd-a668-911e322b4e50', 'Function room', 'The function room can be used for birthday parties, annual meetings or party\'s. Advance booking is desirable.', 65, 15, 1, '2012-01-15 09:03:38', NULL, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_saml_clients`
--

CREATE TABLE `%PREFIX%_saml_clients` (
  `smc_id` int UNSIGNED NOT NULL,
  `smc_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `smc_org_id` int UNSIGNED NOT NULL,
  `smc_client_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `smc_client_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `smc_enabled` tinyint(1) DEFAULT '1',
  `smc_metadata_url` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `smc_acs_url` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `smc_slo_url` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `smc_x509_certificate` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `smc_userid_field` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'usr_id',
  `smc_field_mapping` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `smc_role_mapping` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `smc_allowed_clock_skew` int UNSIGNED DEFAULT NULL,
  `smc_assertion_lifetime` int UNSIGNED DEFAULT NULL,
  `smc_sign_assertions` tinyint(1) DEFAULT '1',
  `smc_encrypt_assertions` tinyint(1) DEFAULT '0',
  `smc_require_auth_signed` tinyint(1) DEFAULT '0',
  `smc_validate_signatures` tinyint(1) DEFAULT '1',
  `smc_usr_id_create` int UNSIGNED DEFAULT NULL,
  `smc_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `smc_usr_id_change` int UNSIGNED DEFAULT NULL,
  `smc_timestamp_change` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_sessions`
--

CREATE TABLE `%PREFIX%_sessions` (
  `ses_id` int UNSIGNED NOT NULL,
  `ses_usr_id` int UNSIGNED DEFAULT NULL,
  `ses_org_id` int UNSIGNED NOT NULL,
  `ses_session_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ses_begin` timestamp NULL DEFAULT NULL,
  `ses_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ses_ip_address` varchar(39) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ses_binary` blob,
  `ses_reload` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_sessions`
--

INSERT INTO `%PREFIX%_sessions` (`ses_id`, `ses_usr_id`, `ses_org_id`, `ses_session_id`, `ses_begin`, `ses_timestamp`, `ses_ip_address`, `ses_binary`, `ses_reload`) VALUES
(4, 1, 1, 'n8augeudoflh2ougitrthui096', '2025-10-05 13:27:49', '2025-10-05 13:28:20', '127.0.0.XXX', NULL, 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_sso_keys`
--

CREATE TABLE `%PREFIX%_sso_keys` (
  `key_id` int UNSIGNED NOT NULL,
  `key_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `key_org_id` int UNSIGNED NOT NULL,
  `key_name` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `key_algorithm` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'RSA',
  `key_private` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `key_public` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `key_certificate` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `key_expires_at` date DEFAULT NULL,
  `key_is_active` tinyint(1) NOT NULL DEFAULT '1',
  `key_usr_id_create` int UNSIGNED DEFAULT NULL,
  `key_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `key_usr_id_change` int UNSIGNED DEFAULT NULL,
  `key_timestamp_change` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_texts`
--

CREATE TABLE `%PREFIX%_texts` (
  `txt_id` int UNSIGNED NOT NULL,
  `txt_org_id` int UNSIGNED NOT NULL,
  `txt_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `txt_text` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_texts`
--

INSERT INTO `%PREFIX%_texts` (`txt_id`, `txt_org_id`, `txt_name`, `txt_text`) VALUES
(1, 1, 'SYSMAIL_REGISTRATION_APPROVED', '#subject# Registration at #organization_long_name# confirmed\r\n#content# Hello #user_first_name#,\r\n\r\nyour registration on #organization_homepage# has been confirmed.\r\n\r\nYou can now log in to the homepage with your username #user_login_name# and your password.\r\n\r\nIf you have any questions, write an email to #administrator_email#.\r\n\r\nRegards,\r\nThe team of #organization_long_name#'),
(2, 1, 'SYSMAIL_REGISTRATION_NEW', '#subject# New registration at #organization_long_name# website\r\n#content# A new user has registered on #organization_homepage#.\r\n\r\nSurname: #user_last_name#\r\nFirst Name: #user_first_name#\r\nE-Mail: #user_email#\r\n\r\n\r\nThis message was generated automatically.'),
(3, 1, 'SYSMAIL_LOGIN_INFORMATION', '#subject# Login data for #organization_long_name#\r\n#content# Hello #user_first_name#,\r\n\r\nYou receive your login data for the website #organization_homepage#.\r\nUsername: #user_login_name#\r\nPassword: #variable1#\r\n\r\nThe password was generated automatically,\r\nYou should change it after logging in to #organization_homepage# in your profile.\r\n\r\nRegards,\r\nThe team of #organization_long_name#'),
(4, 1, 'SYSMAIL_PASSWORD_RESET', '#subject# Reset password for #organization_long_name#\r\n#content# Hello #user_first_name#,\r\n\r\nWe have received a request to reset your password on #organization_homepage#.\r\n\r\nIf the request came from you, you can use the following link to reset your password and set a new one: \r\n#variable1#\r\n\r\nRegards,\r\nThe team of #organization_long_name#'),
(5, 1, 'SYSMAIL_REGISTRATION_REFUSED', '#subject# in registration at #organization_long_name# rejected.\r\n#content#Hello #user_first_name#,\r\n\r\nyour registration at #organization_homepage# was rejected.\r\n\r\nRegistrations are accepted in general by our users. If you are a member and your registration was still rejected, it may be because you were not identified as member.\r\nTo clarify the reasons for the rejection please contact the administrator #administrator_email# from #organization_homepage#.\r\n\r\nRegards,\r\nThe team of #organization_long_name#'),
(6, 1, 'SYSMAIL_REGISTRATION_CONFIRMATION', '#subject# Your registration at #organization_long_name#\r\n#content# Hello #user_first_name#,\r\nwe are very glad that you have registered on our website #organization_homepage#.\r\n\r\nTo complete your registration, please click on the following link: #variable1#. By clicking on the link you will be automatically redirected to our website and your registration will be confirmed.\r\n\r\nOnce you have confirmed your registration, we will check it. You will receive a reply within a few hours whether your registration has been accepted and you can log in with your credentials or whether your registration has been rejected.\r\n\r\nBest regards\r\nThe team of #organization_long_name#'),
(101, 2, 'SYSMAIL_REGISTRATION_APPROVED', '#subject# Registration at #organization_long_name# confirmed\r\n#content# Hello #user_first_name#,\r\n\r\nyour registration on #organization_homepage# has been confirmed.\r\n\r\nYou can now log in to the homepage with your username #user_login_name# and your password.\r\n\r\nIf you have any questions, write an email to #administrator_email#.\r\n\r\nRegards,\r\nThe team of #organization_long_name#'),
(102, 2, 'SYSMAIL_REGISTRATION_NEW', '#subject# New registration at #organization_long_name# website\r\n#content# A new user has registered on #organization_homepage#.\r\n\r\nSurname: #user_last_name#\r\nFirst Name: #user_first_name#\r\nE-Mail: #user_email#\r\n\r\n\r\nThis message was generated automatically.'),
(103, 2, 'SYSMAIL_LOGIN_INFORMATION', '#subject# Login data for #organization_long_name#\r\n#content# Hello #user_first_name#,\r\n\r\nYou receive your login data for the website #organization_homepage#.\r\nUsername: #user_login_name#\r\nPassword: #variable1#\r\n\r\nThe password was generated automatically,\r\nYou should change it after logging in to #organization_homepage# in your profile.\r\n\r\nRegards,\r\nThe team of #organization_long_name#'),
(104, 2, 'SYSMAIL_PASSWORD_RESET', '#subject# Reset password for #organization_long_name#\r\n#content# Hello #user_first_name#,\r\n\r\nWe have received a request to reset your password on #organization_homepage#.\r\n\r\nIf the request came from you, you can use the following link to reset your password and set a new one: \r\n#variable1#\r\n\r\nRegards,\r\nThe team of #organization_long_name#'),
(105, 2, 'SYSMAIL_REGISTRATION_REFUSED', '#subject# in registration at #organization_long_name# rejected.\r\n#content#Hello #user_first_name#,\r\n\r\nyour registration at #organization_homepage# was rejected.\r\n\r\nRegistrations are accepted in general by our users. If you are a member and your registration was still rejected, it may be because you were not identified as member.\r\nTo clarify the reasons for the rejection please contact the administrator #administrator_email# from #organization_homepage#.\r\n\r\nRegards,\r\nThe team of #organization_long_name#'),
(106, 2, 'SYSMAIL_REGISTRATION_CONFIRMATION', '#subject# Your registration at #organization_long_name#\r\n#content# Hello #user_first_name#,\r\nwe are very glad that you have registered on our website #organization_homepage#.\r\n\r\nTo complete your registration, please click on the following link: #variable1#. By clicking on the link you will be automatically redirected to our website and your registration will be confirmed.\r\n\r\nOnce you have confirmed your registration, we will check it. You will receive a reply within a few hours whether your registration has been accepted and you can log in with your credentials or whether your registration has been rejected.\r\n\r\nBest regards\r\nThe team of #organization_long_name#');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_users`
--

CREATE TABLE `%PREFIX%_users` (
  `usr_id` int UNSIGNED NOT NULL,
  `usr_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `usr_login_name` varchar(254) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `usr_password` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `usr_photo` blob,
  `usr_text` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `usr_pw_reset_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `usr_pw_reset_timestamp` timestamp NULL DEFAULT NULL,
  `usr_last_login` timestamp NULL DEFAULT NULL,
  `usr_actual_login` timestamp NULL DEFAULT NULL,
  `usr_number_login` int NOT NULL DEFAULT '0',
  `usr_date_invalid` timestamp NULL DEFAULT NULL,
  `usr_number_invalid` smallint NOT NULL DEFAULT '0',
  `usr_usr_id_create` int UNSIGNED DEFAULT NULL,
  `usr_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usr_usr_id_change` int UNSIGNED DEFAULT NULL,
  `usr_timestamp_change` timestamp NULL DEFAULT NULL,
  `usr_valid` tinyint(1) NOT NULL DEFAULT '0',
  `usr_tfa_secret` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_users`
--

INSERT INTO `%PREFIX%_users` (`usr_id`, `usr_uuid`, `usr_login_name`, `usr_password`, `usr_photo`, `usr_text`, `usr_pw_reset_id`, `usr_pw_reset_timestamp`, `usr_last_login`, `usr_actual_login`, `usr_number_login`, `usr_date_invalid`, `usr_number_invalid`, `usr_usr_id_create`, `usr_timestamp_create`, `usr_usr_id_change`, `usr_timestamp_change`, `usr_valid`, `usr_tfa_secret`) VALUES
(1, '7a854ed2-50db-49ee-9379-31d07f467d47', 'admin', '$2y$10$4t2PfiywA9CVkp5Tn3D1iOc5WpG5QWkq84zM1vZrfeGBpROMXvVie', NULL, NULL, NULL, NULL, '2025-10-05 13:27:49', '2025-10-05 13:28:17', 41, NULL, 0, 2, '2008-05-03 07:43:02', 354, '2009-02-24 08:43:02', 1, NULL),
(2, '5b84e9c8-b9b9-44d1-acf2-29fbb1015d01', 'System', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, '2008-05-03 07:40:43', NULL, NULL, 0, NULL),
(202, '93ce816e-7cfd-45e1-b025-a3644828c47c', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:44:59', NULL, NULL, 1, NULL),
(203, 'f4361cf5-0b58-4602-b6f9-9ce4535b111f', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:44:59', 1, '2009-02-14 14:24:39', 1, NULL),
(204, 'df18391b-e9a0-454a-ba0d-c589ade80b17', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:00', 1, '2009-02-14 19:45:00', 1, NULL),
(205, 'd5843761-d0c2-4a17-a196-cb0795abbc27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:00', 1, '2009-02-14 19:45:00', 1, NULL),
(206, 'cd3c047a-a7cc-4b1e-aa18-8cf83408fd08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:01', 1, '2009-02-14 19:45:01', 1, NULL),
(207, 'cd60260c-646a-495a-bc9e-d675554c7962', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:03', NULL, NULL, 1, NULL),
(208, 'e3d76810-5c64-4ca7-9219-b6bb2113c6b7', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:05', NULL, NULL, 1, NULL),
(209, '1b5b1b29-0c7b-4ec1-ad42-0eeb8e0af891', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:06', NULL, NULL, 1, NULL),
(210, '4574b93d-22e0-4e18-93c1-1e6c93869ee9', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:07', NULL, NULL, 1, NULL),
(211, '2d5c235d-3e97-4c31-baf9-01664b449800', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:09', 1, '2009-02-14 19:45:09', 1, NULL),
(212, 'c67a424f-9f89-46f9-8554-165582168d42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:11', 1, '2009-02-14 19:45:11', 1, NULL),
(213, 'de709436-a2d5-4270-999f-adb8a06bb443', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:13', 1, '2009-02-14 19:45:13', 1, NULL),
(214, 'ffffba2e-a41b-4fa3-9074-f02ac7271ef8', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:15', 1, '2009-02-14 19:45:15', 1, NULL),
(215, '6ad567f4-68aa-4add-a01f-64787cee2f09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:18', 1, '2009-02-14 19:45:18', 1, NULL),
(216, '7703951f-2658-48fc-b301-4ea1f30c1d93', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:19', 1, '2009-02-14 19:45:19', 1, NULL),
(217, '9fa45677-54e0-4bea-b86b-4e6ee6e894f2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:21', 1, '2009-02-14 19:45:21', 1, NULL),
(218, 'd41b8e54-d55d-42f1-bb52-71a1286e3dc3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:26', 354, '2025-10-05 13:07:39', 1, NULL),
(219, 'befe86bf-7479-4cd1-919c-783f41a6cb53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:30', 1, '2009-02-14 19:45:30', 1, NULL),
(220, 'e841a425-4707-4489-9d9e-a2348ef78a40', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:33', 1, '2009-02-14 19:45:33', 1, NULL),
(221, '446b790c-7276-454e-873f-eddecd212b2d', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:35', 1, '2009-02-14 19:45:35', 1, NULL),
(222, '81b68c73-ca8e-473d-b93c-d255bda67787', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:36', 1, '2009-02-14 19:45:36', 1, NULL),
(223, 'fcf95aa0-04b9-4251-b6d3-3e738d97fbd0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:37', 1, '2009-02-14 19:45:37', 1, NULL),
(224, '649bc587-131c-40f0-82ab-17a84e925883', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:40', 1, '2009-02-14 19:45:40', 1, NULL),
(225, 'ed93b673-dee0-4c9a-beeb-5bea86df1d33', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:41', 1, '2009-02-14 19:45:41', 1, NULL),
(226, '1e05d4df-68f1-46da-97cd-f8106a2448b3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:44', 1, '2009-02-14 19:45:44', 1, NULL),
(227, '68bb8c2a-d50c-4d1b-bf15-fcee832c8b3f', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:46', 1, '2009-02-14 19:45:46', 1, NULL),
(228, '7da8b421-5933-4c0c-b7c1-a5a74743284f', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:47', 1, '2009-02-14 19:45:47', 1, NULL),
(229, '120fe91e-706e-433c-a69d-e750a3958da3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:49', 1, '2009-02-14 19:45:49', 1, NULL),
(230, 'fb8568ea-0e62-4019-bdc5-366cb89ccfed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:51', 1, '2009-02-14 19:45:51', 1, NULL),
(231, '20302356-314b-4752-a967-2bbe4d06e2ba', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:53', 1, '2009-02-14 19:45:53', 1, NULL),
(232, 'b0648307-3763-4e11-9814-c8f629eb0e87', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:54', 1, '2009-02-14 19:45:54', 1, NULL),
(233, '6111bc37-2a05-4804-ac1b-20957eadaa73', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:55', 1, '2009-02-14 19:45:55', 1, NULL),
(234, 'c08a6a65-86ce-4307-b7e9-cde87d3d6ebb', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:56', NULL, NULL, 1, NULL),
(235, '8b3c611b-a524-4134-8f4d-dde8eae13242', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:45:58', 1, '2009-02-14 19:45:58', 1, NULL),
(236, 'c2694fd6-e158-4de2-90ce-83b7eccae532', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:00', NULL, NULL, 1, NULL),
(237, '44171fc4-4039-4502-9072-5dccb3a36349', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:01', 1, '2009-02-14 19:46:01', 1, NULL),
(238, '735544e3-374b-4967-bacc-a467c3dd4b4d', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:03', 1, '2009-02-14 19:46:03', 1, NULL),
(239, 'eb1c4b06-85b5-4e00-a311-a4c1c5108be6', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:06', NULL, NULL, 1, NULL),
(240, '1525af50-1b2b-45ce-8a07-35b0b1eb5a5e', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:08', 1, '2009-02-14 19:46:08', 1, NULL),
(241, 'f829ff81-2950-4bad-a8ef-ed437fdafce1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:10', 1, '2009-02-14 19:46:10', 1, NULL),
(242, '310c655b-f85f-419b-a33c-4dad98ea924c', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:11', 1, '2009-02-14 19:46:11', 1, NULL),
(243, 'c2670498-5018-4549-b1e0-a448c71de399', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:12', 1, '2009-02-14 19:46:12', 1, NULL),
(244, '355ba9cd-c8af-4777-a00b-267183a25f3f', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:13', 1, '2009-02-14 19:46:13', 1, NULL),
(245, '1cafa7e1-166b-4f79-b760-f03100dd94f1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:15', 1, '2009-02-14 19:46:15', 1, NULL),
(246, '4b9dd602-1c30-4441-83e7-a81babe57008', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:16', 1, '2009-02-14 19:46:16', 1, NULL),
(247, '8f70e5fa-d525-48be-ada6-3c6a694faf91', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:16', 1, '2009-02-14 19:46:16', 1, NULL),
(248, '4d6fabea-e2e9-48ec-9e24-c458dd3bf51a', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:17', 1, '2009-02-14 19:46:17', 1, NULL),
(249, 'f1ad561c-fb7b-4c49-81e5-b4f4e60bf04d', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:18', 1, '2009-02-14 19:46:18', 1, NULL),
(250, '587f587a-3618-411a-b3e2-ae9fc3becdb9', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:18', 1, '2009-02-14 19:46:18', 1, NULL),
(251, '03f4c5cf-b2ab-4868-b951-c0dff196d21f', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:19', 1, '2009-02-14 19:46:19', 1, NULL),
(252, 'd222cb1b-4b7c-4354-be02-67168160e2d6', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:20', 1, '2009-02-14 19:46:20', 1, NULL),
(253, '1424bee6-5a45-43b2-9bba-37fae9060fe9', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:21', 1, '2009-02-14 19:46:21', 1, NULL),
(254, '8f4523b8-10bd-4dc7-9d24-1ad85375d188', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:22', 1, '2009-02-14 19:46:22', 1, NULL),
(255, '8cfad614-cd2f-4fb2-9e82-e7e43bb61353', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:22', 1, '2009-02-14 19:46:22', 1, NULL),
(256, 'eac653cf-5265-4161-9bc7-0a1e1808075e', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:23', 1, '2009-02-14 19:46:23', 1, NULL),
(257, 'f8691a52-a207-4c32-9953-eda4f20a0f00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:23', 1, '2009-02-14 19:46:23', 1, NULL),
(258, 'd0e9bd42-930f-4096-b8cc-cdcaa9d63088', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:24', 1, '2009-02-14 19:46:24', 1, NULL),
(259, 'b94fdf67-8a68-411c-8048-527539a35894', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:25', 1, '2009-02-14 19:46:25', 1, NULL),
(260, '79b14a0a-3f6e-43d0-9543-28fb1a0a7acc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:25', 1, '2009-02-14 19:46:25', 1, NULL),
(261, 'de94811a-efc4-4df7-9d97-05fa249f7543', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:26', 1, '2009-02-14 19:46:26', 1, NULL),
(262, 'ebbc0fe3-23e3-43a3-b75a-9100d50429e6', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:26', 1, '2009-02-14 19:46:26', 1, NULL),
(263, '749891b6-6b9c-4e8d-a0ed-f24413565950', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:27', 1, '2009-02-14 19:46:27', 1, NULL),
(264, 'a0c48e53-e935-4209-b108-b05ca4b58293', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:28', 1, '2009-02-14 19:46:28', 1, NULL),
(265, 'be8b0107-2364-4d25-a6e4-5599e6c94df0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:28', 1, '2009-02-14 19:46:28', 1, NULL),
(266, 'f793f3d1-ff21-411f-bd69-eb6483123647', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:29', NULL, NULL, 1, NULL),
(267, '59b7dd7f-77b0-43f5-9145-bc41757847df', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:29', 1, '2009-02-14 19:46:29', 1, NULL),
(268, '253d65b6-b0cf-48c3-a0f5-aebe2021e849', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:30', NULL, NULL, 1, NULL),
(269, '9bff3c81-5c94-4e69-9291-d2469de0f973', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:30', NULL, NULL, 1, NULL),
(270, 'e7483706-9591-41eb-9df2-69ad470cad45', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:31', 1, '2009-02-14 19:46:31', 1, NULL),
(271, '7fd5cdc9-801d-4373-9f15-40d131ed6c57', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:31', 1, '2009-02-14 19:46:31', 1, NULL),
(272, '327eda38-525a-4ab1-9b2b-eb732019365b', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:32', 1, '2009-02-14 19:46:32', 1, NULL),
(273, '406ef1ec-a666-4f51-a309-098fe1d99a1f', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:33', 1, '2009-02-14 19:46:33', 1, NULL),
(274, '6f7bde18-8bcc-4176-81d9-3f04eeb8ff8e', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:33', 1, '2009-02-14 19:46:33', 1, NULL),
(275, 'c07b940a-7282-4dec-8446-3c82c6ebd784', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:34', 1, '2009-02-14 19:46:34', 1, NULL),
(276, 'b969e22c-7db6-4133-be28-6acf30ba6d89', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:35', 1, '2009-02-14 19:46:35', 1, NULL),
(277, '11929404-1bd6-4e67-87eb-180e47d3c6b7', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:35', 1, '2009-02-14 19:46:35', 1, NULL),
(278, '6aaebdc7-4b59-414f-817f-d4845014d7c1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:36', 1, '2009-02-14 19:46:36', 1, NULL),
(279, 'e806b13e-0c39-4f06-97ee-c389ecdb1df5', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:37', 1, '2009-02-14 19:46:37', 1, NULL),
(280, 'f787f83b-1a4a-411f-b447-4308c2f529d5', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:37', 1, '2009-02-14 19:46:37', 1, NULL),
(281, '68689ffa-49d5-4b93-bd56-209ee07a028a', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:38', 1, '2009-02-14 19:46:38', 1, NULL),
(282, 'd985a4ed-3be1-4e98-9f12-2d04664996e0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:38', 1, '2009-02-14 19:46:38', 1, NULL),
(283, '4758a63e-a5f3-4d58-a25b-2c8bb323b30c', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:39', 1, '2009-02-14 19:46:39', 1, NULL),
(284, '3d28e30b-7c29-456d-afc3-64019fcddb30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:40', 1, '2009-02-14 19:46:40', 1, NULL),
(285, '9a27b1ed-3cee-48a2-b886-d9e768fa2973', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:41', 1, '2009-02-14 19:46:41', 1, NULL),
(286, '2e433f1a-50da-4ab5-abb8-d0ad2f14ac58', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:41', 1, '2009-02-14 19:46:41', 1, NULL),
(287, '5cb7ae95-63b4-47ce-955a-5e318d4484db', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:42', 1, '2009-02-14 19:46:42', 1, NULL),
(288, '3a5f0186-bbb5-4c00-831b-5b909fc924f0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:43', 1, '2009-02-14 19:46:43', 1, NULL),
(289, '537d8d74-882b-466d-b14b-aba396f951c7', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:43', 1, '2009-02-14 19:46:43', 1, NULL),
(290, '348d9d0b-0d63-44a8-a797-7e0c21d7c7cb', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:44', 1, '2009-02-14 19:46:44', 1, NULL),
(291, '7f02a06a-22f8-4020-aa01-c7e51fb3b1d2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:45', 1, '2009-02-14 19:46:45', 1, NULL),
(292, '20e9aca7-e84a-4829-8d32-6e254e607488', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:45', 1, '2009-02-14 19:46:45', 1, NULL),
(293, '90e280ed-1d37-4fd7-ad91-9af60b806f8f', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:46', 1, '2009-02-14 19:46:46', 1, NULL),
(294, '3d61ba92-56f3-480b-b3cc-c37b19ac04d7', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:47', 1, '2009-02-14 19:46:47', 1, NULL),
(295, 'd427e53b-a2cd-4b7d-81b7-904d9fb636dc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:47', 1, '2009-02-14 19:46:47', 1, NULL),
(296, 'c11dab58-c20a-407b-8357-f6b869af0714', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:47', 1, '2009-02-14 19:46:47', 1, NULL),
(297, 'ceba0dd2-8e9c-4204-84a7-523c5a491cbd', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:48', 1, '2009-02-14 19:46:48', 1, NULL),
(298, 'b95d1942-88d0-4b08-9857-0314a0f6b995', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:48', 1, '2009-02-14 19:46:48', 1, NULL),
(299, '37a00f5a-b9e6-4095-891a-ab7b860e9918', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:49', 1, '2009-02-14 19:46:49', 1, NULL),
(300, '4564365c-0931-4a53-b35f-ab35d6229809', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:50', 1, '2009-02-14 19:46:50', 1, NULL),
(301, '73ad9e68-0715-4f34-aa79-6eb6de3f3cf0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:50', 1, '2009-02-14 19:46:50', 1, NULL),
(302, '2c7ff257-7bfd-40ba-afab-8e55794eedcf', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:51', 1, '2009-02-14 19:46:51', 1, NULL),
(303, '370e6a8d-a341-45c7-9be9-49e9af23e7ba', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:52', 1, '2009-02-14 19:46:52', 1, NULL),
(304, '37a01c8c-fa7b-42d2-832c-ec0589f1fee3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:52', 1, '2009-02-14 19:46:52', 1, NULL),
(305, 'c932f06e-4f9c-41db-a15d-baf4546d673f', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:53', 1, '2009-02-14 19:46:53', 1, NULL),
(306, 'f1413fb4-02d7-4d8b-ac5c-64b808b8e714', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:53', 1, '2009-02-14 19:46:53', 1, NULL),
(307, '3400a119-ec6f-4ba5-b4ae-80f3078c9398', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:54', 1, '2009-02-14 19:46:54', 1, NULL),
(308, '7ab5d98f-4c1c-480f-9fc8-c5fe10f7c794', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:54', NULL, NULL, 1, NULL),
(309, 'fca89818-fa9b-4e75-94d0-7e19819299bb', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:55', NULL, NULL, 1, NULL),
(310, '15694740-b06c-4fbd-9a79-10543c9232bd', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:55', NULL, NULL, 1, NULL),
(311, '1a9e0ad6-172e-4438-a54b-3cb76da32af2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:56', 1, '2009-02-14 19:46:56', 1, NULL),
(312, '98c0074d-8c01-4857-a46b-820696a444c6', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:56', 1, '2009-02-14 19:46:56', 1, NULL),
(313, '753bd2c6-a8ab-4f08-af96-7c8998831b96', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:57', 1, '2009-02-14 19:46:57', 1, NULL),
(314, 'fc76d351-8985-41c8-9766-d20c790efe70', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:58', 1, '2009-02-14 19:46:58', 1, NULL),
(315, '018eba8d-e7a6-4c55-9f73-b48881975aa3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:58', 1, '2009-02-14 19:46:58', 1, NULL),
(316, '3c54fc5f-06ea-430a-8f9c-0eae5477ddee', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:46:59', 1, '2009-02-14 19:46:59', 1, NULL),
(317, '602cce9e-0670-4716-9532-e0e294b15dcf', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:00', 1, '2009-02-14 19:47:00', 1, NULL),
(318, '24b5221b-247b-4225-8d40-47b625a6ba16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:00', 1, '2009-02-14 19:47:00', 1, NULL),
(319, '6f8e487b-12aa-43e2-b26b-ef04c6c3eb7c', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:01', 1, '2009-02-14 19:47:01', 1, NULL),
(320, '9605a4bf-3e87-4b77-aa49-195ebcc2afc6', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:01', 1, '2009-02-14 19:47:01', 1, NULL),
(321, 'a277890c-b2f9-4c6a-bf63-75e225ba152e', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:02', 1, '2009-02-14 19:47:02', 1, NULL),
(322, '0bd8cb99-50a8-4cf8-91e6-78cd5fc883b3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:03', 1, '2009-02-14 19:47:03', 1, NULL),
(323, '15073e01-2532-4240-9a5c-b672c4cce8b6', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:03', 1, '2009-02-14 19:47:03', 1, NULL),
(324, '1baea179-91c6-4167-8ad5-58c474f62311', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:04', 1, '2009-02-14 19:47:04', 1, NULL),
(325, 'e8cfa1dd-11bb-41ec-bab6-41b8b508affe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:04', 1, '2009-02-14 19:47:04', 1, NULL),
(326, 'b522f5d5-4163-4dc5-ae03-3cf2f983931e', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:05', 1, '2009-02-14 19:47:05', 1, NULL),
(327, '960f7456-238f-42fa-a234-59968dc4b009', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:05', 1, '2009-02-14 19:47:05', 1, NULL),
(328, 'b85d7a1f-d784-4aef-84c6-30441d38625c', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:06', 1, '2009-02-14 19:47:06', 1, NULL),
(329, 'ae29aa7c-579c-4f2f-8e70-bedf02027717', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:07', 1, '2009-02-14 19:47:07', 1, NULL),
(330, '0cf04db2-2484-4eb6-bb28-98a55cb459c6', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:07', 1, '2009-02-14 19:47:07', 1, NULL),
(331, '8935e4c1-56a8-4678-8977-680402f22757', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:08', 1, '2009-02-14 19:47:08', 1, NULL),
(332, '8a869d19-fa79-4c90-9ef9-5ff5d9fa62f0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:09', 1, '2009-02-14 19:47:09', 1, NULL),
(333, 'dee5bb3e-60ee-4bed-88d0-afaaffacadd3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:09', 1, '2009-02-14 19:47:09', 1, NULL),
(334, '2409f3a1-6a08-4edc-b8ba-95f3ea1e2025', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:10', 1, '2009-02-14 19:47:10', 1, NULL),
(335, 'fdfb7e6f-329e-4e56-b851-39d8cf7c7af0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:10', 1, '2009-02-14 19:47:10', 1, NULL),
(336, 'eeb1275c-6c65-463a-9d16-04be64e2f7c5', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:11', 1, '2009-02-14 19:47:11', 1, NULL),
(337, '6da0b876-2faf-4668-8bdc-ed0ec9607b56', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:12', 1, '2009-02-14 19:47:12', 1, NULL),
(338, '8c4f43de-c226-44a6-a76e-6fd530b34d36', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:12', 1, '2009-02-14 19:47:12', 1, NULL),
(339, '7ebcfa5c-7552-45ef-8199-a3655ab3510e', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:13', 1, '2009-02-14 19:47:13', 1, NULL),
(340, '413a7e7d-1fb9-4224-bc31-21350e9257eb', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:14', 1, '2009-02-14 19:47:14', 1, NULL),
(341, '8d058857-3174-455e-844f-6c909f43e55f', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:14', 1, '2009-02-14 19:47:14', 1, NULL),
(342, 'e73da122-b961-4c7f-92a3-c7224a2960c1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:15', NULL, NULL, 1, NULL),
(343, '6afff462-2c5d-465e-8120-ec43f579c7d9', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:16', 1, '2009-02-14 19:47:16', 1, NULL),
(344, 'c5a78a84-9d19-4f16-81a9-eae94530d17c', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:16', NULL, NULL, 1, NULL),
(345, '91c2e034-f671-467c-ab5b-8268e0505fb0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:18', 1, '2009-02-14 19:47:18', 1, NULL),
(346, '9ca4bc75-15ca-416d-abf3-d07f0799ba95', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:18', 1, '2009-02-14 19:47:18', 1, NULL),
(347, 'b2e88992-59f2-4f15-97a8-b4b028bae598', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:19', 1, '2009-02-14 19:47:19', 1, NULL),
(348, 'd00f7e21-53a4-41bd-8324-52cfd7a01e99', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:20', NULL, NULL, 1, NULL),
(349, 'b50d8ea6-2afe-44ae-9721-dea54413724d', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:21', 1, '2009-02-14 19:47:21', 1, NULL),
(350, 'eace5159-f088-4af0-afa0-5e691f3d5cc8', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:22', 1, '2009-02-14 19:47:22', 1, NULL),
(351, '56920e00-761c-495a-b43d-082eda94a145', 'Demo', '$2y$10$4t2PfiywA9CVkp5Tn3D1iOc5WpG5QWkq84zM1vZrfeGBpROMXvVie', NULL, NULL, NULL, NULL, '2008-05-12 18:29:15', '2009-02-27 21:34:06', 2, NULL, 0, 351, '2008-05-12 18:29:33', 354, '2011-02-03 13:19:13', 1, NULL),
(352, '4cae66ed-6c2f-4eac-be07-2635ddc1ceda', 'Mustermann01', '$2y$10$4t2PfiywA9CVkp5Tn3D1iOc5WpG5QWkq84zM1vZrfeGBpROMXvVie', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 352, '2008-05-28 21:31:26', NULL, NULL, 0, NULL),
(353, '386e8531-deb7-40ed-a06b-df4366d9bba6', 'Dina', '$2y$10$4t2PfiywA9CVkp5Tn3D1iOc5WpG5QWkq84zM1vZrfeGBpROMXvVie', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 353, '2008-05-28 21:33:38', NULL, NULL, 0, NULL),
(354, '006bd130-34d4-4b86-9e82-b1464ad60a0b', 'Chairman', '$2y$10$4t2PfiywA9CVkp5Tn3D1iOc5WpG5QWkq84zM1vZrfeGBpROMXvVie', NULL, NULL, NULL, NULL, '2009-02-27 01:04:16', '2025-10-05 12:56:59', 3, NULL, 0, 1, '2009-06-12 18:29:33', 354, '2010-05-12 18:29:33', 1, NULL),
(355, '97f8346c-ca53-40de-857a-459d26d9df40', 'Member', '$2y$10$4t2PfiywA9CVkp5Tn3D1iOc5WpG5QWkq84zM1vZrfeGBpROMXvVie', NULL, NULL, NULL, NULL, '2010-02-27 11:34:09', '2025-10-05 12:55:01', 3, NULL, 0, 1, '2009-07-12 18:29:33', 355, '2025-10-05 13:05:58', 1, NULL),
(356, '232a5ee7-4ed9-41de-b081-0b5090b00462', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:22', 1, '2009-02-14 19:47:22', 1, NULL),
(357, 'd1ba8d2b-ad8f-43bc-a22d-cbd71eed09a4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2008-04-26 18:47:22', 1, '2009-02-14 19:47:22', 1, NULL),
(358, 'fc793b70-16c7-4ff7-b245-6df94d5d6057', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2019-04-26 18:47:22', NULL, NULL, 1, NULL),
(359, 'ac793b70-16c7-4ff8-b245-6df94d5d6123', 'bakers', '$2y$10$4t2PfiywA9CVkp5Tn3D1iOc5WpG5QWkq84zM1vZrfeGBpROMXvVie', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2023-04-26 18:47:22', NULL, NULL, 0, NULL),
(360, 'reg93b70-76c7-4fdf-b344-6df94re64343', 'smith', '$2y$10$4t2PfiywA9CVkp5Tn3D1iOc5WpG5QWkq84zM1vZrfeGBpROMXvVie', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1, '2023-04-15 16:06:22', NULL, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_user_data`
--

CREATE TABLE `%PREFIX%_user_data` (
  `usd_id` int UNSIGNED NOT NULL,
  `usd_usr_id` int UNSIGNED NOT NULL,
  `usd_usf_id` int UNSIGNED NOT NULL,
  `usd_value` varchar(4000) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_user_data`
--

INSERT INTO `%PREFIX%_user_data` (`usd_id`, `usd_usr_id`, `usd_usf_id`, `usd_value`) VALUES
(1, 1, 1, 'Schmidt'),
(2, 1, 2, 'Paul'),
(3, 1, 3, 'Unter den Linden 45'),
(4, 1, 4, '10117'),
(5, 1, 5, 'Berlin'),
(6, 1, 6, 'DEU'),
(7, 1, 7, '0211-85858585'),
(8, 1, 10, '2007-01-03'),
(9, 1, 11, '1'),
(10, 1, 12, 'administrator@admidio.org'),
(11, 1, 13, 'https://www.admidio.org/'),
(12, 1, 20, '001'),
(13, 1, 21, 'blau'),
(14, 202, 1, 'Ahrends'),
(15, 202, 2, 'Dagmar'),
(16, 202, 3, 'Blumenwiese 23'),
(17, 202, 4, '12345'),
(18, 202, 5, 'Müllerhausen'),
(19, 202, 6, 'DEU'),
(20, 202, 7, '0875-2255773'),
(21, 202, 8, '0170-457412'),
(22, 202, 10, '2011-01-05'),
(23, 202, 11, '2'),
(24, 202, 12, 'ahrends.dagmar@example.com'),
(26, 203, 1, 'Allegre'),
(27, 203, 2, 'Dagobert'),
(28, 203, 3, 'Blumenwiese 24'),
(29, 203, 4, '12345'),
(30, 203, 5, 'Müllerhausen'),
(31, 203, 6, 'DEU'),
(32, 203, 7, '0876-2255773'),
(33, 203, 8, '0171-457412'),
(34, 203, 10, '1993-01-07'),
(35, 203, 11, '1'),
(36, 203, 12, 'allegre.dagobert@example.com'),
(38, 204, 1, 'Appel'),
(39, 204, 2, 'Daisy'),
(40, 204, 3, 'Blumenwiese 25'),
(41, 204, 4, '12345'),
(42, 204, 5, 'Müllerhausen'),
(43, 204, 6, 'DEU'),
(44, 204, 7, '0877-2255773'),
(45, 204, 8, '0172-457412'),
(46, 204, 10, '2006-01-09'),
(47, 204, 11, '2'),
(48, 204, 12, 'appel.daisy@example.com'),
(50, 205, 1, 'Arndt'),
(51, 205, 2, 'Dakota'),
(52, 205, 3, 'Blumenwiese 26'),
(53, 205, 4, '12345'),
(54, 205, 5, 'Müllerhausen'),
(55, 205, 6, 'DEU'),
(56, 205, 7, '0878-2255773'),
(57, 205, 8, '0173-457412'),
(58, 205, 10, '1974-01-11'),
(59, 205, 11, '2'),
(60, 205, 12, 'arndt.dakota@example.com'),
(62, 206, 1, 'Baade'),
(63, 206, 2, 'Dale'),
(64, 206, 3, 'Blumenwiese 27'),
(65, 206, 4, '12345'),
(66, 206, 5, 'Müllerhausen'),
(67, 206, 6, 'DEU'),
(68, 206, 7, '0879-2255773'),
(69, 206, 8, '0174-457412'),
(70, 206, 10, '1960-01-13'),
(71, 206, 11, '1'),
(72, 206, 12, 'baade.dale@example.com'),
(74, 207, 1, 'Bachmann'),
(75, 207, 2, 'Dallas'),
(76, 207, 3, 'Blumenwiese 28'),
(77, 207, 4, '12345'),
(78, 207, 5, 'Müllerhausen'),
(79, 207, 6, 'DEU'),
(80, 207, 7, '0880-2255773'),
(81, 207, 8, '0175-457412'),
(82, 207, 10, '1993-01-15'),
(83, 207, 11, '1'),
(84, 207, 12, 'bachmann.dallas@example.com'),
(86, 208, 1, 'Barbosa'),
(87, 208, 2, 'Daltin'),
(88, 208, 3, 'Blumenwiese 29'),
(89, 208, 4, '12345'),
(90, 208, 5, 'Müllerhausen'),
(91, 208, 6, 'DEU'),
(92, 208, 7, '0881-2255773'),
(93, 208, 8, '0176-457412'),
(94, 208, 10, '2012-01-17'),
(95, 208, 11, '1'),
(96, 208, 12, 'barbosa.daltin@example.com'),
(98, 209, 1, 'Baumgarten'),
(99, 209, 2, 'Dalton'),
(100, 209, 3, 'Blumenwiese 30'),
(101, 209, 4, '4711'),
(102, 209, 5, 'Bergdorf'),
(103, 209, 6, 'CHE'),
(104, 209, 7, '0882-2255773'),
(105, 209, 8, '0177-457412'),
(106, 209, 10, '1994-01-19'),
(107, 209, 11, '1'),
(108, 209, 12, 'baumgarten.dalton@example.com'),
(110, 210, 1, 'Báierle'),
(111, 210, 2, 'Damasus'),
(112, 210, 3, 'Blumenwiese 31'),
(113, 210, 4, '4711'),
(114, 210, 5, 'Bergdorf'),
(115, 210, 6, 'CHE'),
(116, 210, 7, '0883-2255773'),
(117, 210, 8, '0178-457412'),
(118, 210, 10, '1979-01-21'),
(119, 210, 11, '1'),
(121, 211, 1, 'Beck'),
(122, 211, 2, 'Damian'),
(123, 211, 3, 'Blumenwiese 32'),
(124, 211, 4, '12345'),
(125, 211, 5, 'Müllerhausen'),
(126, 211, 6, 'DEU'),
(127, 211, 7, '0884-2255773'),
(128, 211, 8, '0179-457412'),
(129, 211, 10, '1974-01-23'),
(130, 211, 11, '1'),
(131, 211, 12, 'beck.damian@example.com'),
(133, 212, 1, 'Becker'),
(134, 212, 2, 'Damien'),
(135, 212, 3, 'Blumenwiese 33'),
(136, 212, 4, '12345'),
(137, 212, 5, 'Müllerhausen'),
(138, 212, 6, 'DEU'),
(139, 212, 7, '0885-2255773'),
(140, 212, 8, '0180-457412'),
(141, 212, 10, '2012-01-25'),
(142, 212, 11, '1'),
(143, 212, 12, 'becker.damien@example.com'),
(145, 213, 1, 'Begunk'),
(146, 213, 2, 'Damion'),
(147, 213, 3, 'Blumenwiese 34'),
(148, 213, 4, '12345'),
(149, 213, 5, 'Müllerhausen'),
(150, 213, 6, 'DEU'),
(151, 213, 7, '0886-2255773'),
(152, 213, 8, '0181-457412'),
(153, 213, 10, '1965-01-27'),
(154, 213, 11, '1'),
(155, 213, 12, 'begunk.damion@example.com'),
(157, 214, 1, 'Behnke'),
(158, 214, 2, 'Damon'),
(159, 214, 3, 'Blumenwiese 35'),
(160, 214, 4, '12345'),
(161, 214, 5, 'Müllerhausen'),
(162, 214, 6, 'DEU'),
(163, 214, 7, '0887-2255773'),
(164, 214, 8, '0182-457412'),
(165, 214, 10, '1992-01-29'),
(166, 214, 11, '1'),
(167, 214, 12, 'behnke.damon@example.com'),
(169, 215, 1, 'Behrend'),
(170, 215, 2, 'Dan'),
(171, 215, 3, 'Blumenwiese 36'),
(172, 215, 4, '12345'),
(173, 215, 5, 'Müllerhausen'),
(174, 215, 6, 'DEU'),
(175, 215, 7, '0888-2255773'),
(176, 215, 8, '0183-457412'),
(177, 215, 10, '2002-01-31'),
(178, 215, 11, '1'),
(179, 215, 12, 'behrend.dan@example.com'),
(181, 216, 1, 'Bender'),
(182, 216, 2, 'Dana'),
(183, 216, 3, 'Blumenwiese 37'),
(184, 216, 4, '12345'),
(185, 216, 5, 'Müllerhausen'),
(186, 216, 6, 'DEU'),
(187, 216, 7, '0889-2255773'),
(188, 216, 8, '0184-457412'),
(189, 216, 10, '1995-02-02'),
(190, 216, 11, '2'),
(191, 216, 12, 'bender.dana@example.com'),
(193, 217, 1, 'Benn'),
(194, 217, 2, 'Dania'),
(195, 217, 3, 'Blumenwiese 38'),
(196, 217, 4, '12345'),
(197, 217, 5, 'Müllerhausen'),
(198, 217, 6, 'DEU'),
(199, 217, 7, '0890-2255773'),
(200, 217, 8, '0185-457412'),
(201, 217, 10, '2012-02-04'),
(202, 217, 11, '2'),
(203, 217, 12, 'benn.dania@example.com'),
(205, 218, 1, 'Bensien'),
(206, 218, 2, 'Daniel'),
(207, 218, 3, 'Blumenwiese 58'),
(208, 218, 4, '12345'),
(209, 218, 5, 'Müllerhausen'),
(210, 218, 6, 'DEU'),
(211, 218, 7, '0891-2255773'),
(212, 218, 8, '0186-457412'),
(213, 218, 10, '1960-02-06'),
(214, 218, 11, '1'),
(215, 218, 12, 'bensien.daniel@example.com'),
(217, 219, 1, 'Berodt'),
(218, 219, 2, 'Daniela'),
(219, 219, 3, 'Blumenwiese 40'),
(220, 219, 4, '12345'),
(221, 219, 5, 'Müllerhausen'),
(222, 219, 6, 'DEU'),
(223, 219, 7, '0892-2255773'),
(224, 219, 8, '0187-457412'),
(225, 219, 10, '1986-02-08'),
(226, 219, 11, '2'),
(227, 219, 12, 'berodt.daniela@example.com'),
(229, 220, 1, 'Besemann'),
(230, 220, 2, 'Daniella'),
(231, 220, 3, 'Blumenwiese 41'),
(232, 220, 4, '12345'),
(233, 220, 5, 'Müllerhausen'),
(234, 220, 6, 'DEU'),
(235, 220, 7, '0893-2255773'),
(236, 220, 8, '0188-457412'),
(237, 220, 10, '2008-02-10'),
(238, 220, 11, '2'),
(239, 220, 12, 'besemann.daniella@example.com'),
(241, 221, 1, 'Bicalho'),
(242, 221, 2, 'Danielle'),
(243, 221, 3, 'Blumenwiese 42'),
(244, 221, 4, '12345'),
(245, 221, 5, 'Müllerhausen'),
(246, 221, 6, 'DEU'),
(247, 221, 7, '0894-2255773'),
(248, 221, 8, '0189-457412'),
(249, 221, 10, '2006-02-12'),
(250, 221, 11, '2'),
(251, 221, 12, 'bicalho.danielle@example.com'),
(253, 222, 1, 'Bielfeld'),
(254, 222, 2, 'Danika'),
(255, 222, 3, 'Blumenwiese 43'),
(256, 222, 4, '12345'),
(257, 222, 5, 'Müllerhausen'),
(258, 222, 6, 'DEU'),
(259, 222, 7, '0895-2255773'),
(260, 222, 8, '0190-457412'),
(261, 222, 10, '1991-02-14'),
(262, 222, 11, '2'),
(263, 222, 12, 'bielfeld.danika@example.com'),
(265, 223, 1, 'Blar'),
(266, 223, 2, 'Dannika'),
(267, 223, 3, 'Blumenwiese 44'),
(268, 223, 4, '12345'),
(269, 223, 5, 'Müllerhausen'),
(270, 223, 6, 'DEU'),
(271, 223, 7, '0896-2255773'),
(272, 223, 8, '0191-457412'),
(273, 223, 10, '1969-02-16'),
(274, 223, 11, '2'),
(275, 223, 12, 'blar.dannika@example.com'),
(277, 224, 1, 'Bleidorn'),
(278, 224, 2, 'Danny'),
(279, 224, 3, 'Blumenwiese 45'),
(280, 224, 4, '12345'),
(281, 224, 5, 'Müllerhausen'),
(282, 224, 6, 'DEU'),
(283, 224, 7, '0897-2255773'),
(284, 224, 8, '0192-457412'),
(285, 224, 10, '1960-02-18'),
(286, 224, 11, '2'),
(287, 224, 12, 'bleidorn.danny@example.com'),
(289, 225, 1, 'Blöcker'),
(290, 225, 2, 'Dante'),
(291, 225, 3, 'Blumenwiese 46'),
(292, 225, 4, '12345'),
(293, 225, 5, 'Müllerhausen'),
(294, 225, 6, 'DEU'),
(295, 225, 7, '0898-2255773'),
(296, 225, 8, '0193-457412'),
(297, 225, 10, '1991-02-20'),
(298, 225, 11, '1'),
(300, 226, 1, 'Blunck'),
(301, 226, 2, 'Daphne'),
(302, 226, 3, 'Blumenwiese 47'),
(303, 226, 4, '12345'),
(304, 226, 5, 'Müllerhausen'),
(305, 226, 6, 'DEU'),
(306, 226, 7, '0899-2255773'),
(307, 226, 8, '0194-457412'),
(308, 226, 10, '1963-02-22'),
(309, 226, 11, '2'),
(310, 226, 12, 'blunck.daphne@example.com'),
(312, 227, 1, 'Bobsien'),
(313, 227, 2, 'Daria'),
(314, 227, 3, 'Blumenwiese 48'),
(315, 227, 4, '12345'),
(316, 227, 5, 'Müllerhausen'),
(317, 227, 6, 'DEU'),
(318, 227, 7, '0900-2255773'),
(319, 227, 8, '0195-457412'),
(320, 227, 10, '2001-02-24'),
(321, 227, 11, '2'),
(322, 227, 12, 'bobsien.daria@example.com'),
(324, 228, 1, 'Boddin'),
(325, 228, 2, 'Darin'),
(326, 228, 3, 'Blumenwiese 49'),
(327, 228, 4, '12345'),
(328, 228, 5, 'Müllerhausen'),
(329, 228, 6, 'DEU'),
(330, 228, 7, '0901-2255773'),
(331, 228, 8, '0196-457412'),
(332, 228, 10, '1961-02-26'),
(333, 228, 11, '1'),
(334, 228, 12, 'boddin.darin@example.com'),
(336, 229, 1, 'Bohlen'),
(337, 229, 2, 'Dario'),
(338, 229, 3, 'Blumenwiese 50'),
(339, 229, 4, 'B5563'),
(340, 229, 5, 'Brüssel'),
(341, 229, 6, 'BEL'),
(342, 229, 7, '0902-2255773'),
(343, 229, 8, '0197-457412'),
(344, 229, 10, '1962-02-28'),
(345, 229, 11, '1'),
(346, 229, 12, 'bohlen.dario@example.com'),
(348, 230, 1, 'Bohmgohren'),
(349, 230, 2, 'Darius'),
(350, 230, 3, 'Blumenwiese 51'),
(351, 230, 4, '12345'),
(352, 230, 5, 'Müllerhausen'),
(353, 230, 6, 'DEU'),
(354, 230, 7, '0903-2255773'),
(355, 230, 8, '0198-457412'),
(356, 230, 10, '2015-03-02'),
(357, 230, 11, '1'),
(358, 230, 12, 'bohmgohren.darius@example.com'),
(360, 231, 1, 'Borchers'),
(361, 231, 2, 'Darla'),
(362, 231, 3, 'Blumenwiese 52'),
(363, 231, 4, 'B5563'),
(364, 231, 5, 'Brüssel'),
(365, 231, 6, 'BEL'),
(366, 231, 7, '0904-2255773'),
(367, 231, 8, '0199-457412'),
(368, 231, 10, '1978-03-04'),
(369, 231, 11, '2'),
(370, 231, 12, 'borchers.darla@example.com'),
(372, 232, 1, 'Bornholdt'),
(373, 232, 2, 'Darleen'),
(374, 232, 3, 'Blumenwiese 53'),
(375, 232, 4, '12345'),
(376, 232, 5, 'Müllerhausen'),
(377, 232, 6, 'DEU'),
(378, 232, 7, '0905-2255773'),
(379, 232, 8, '0200-457412'),
(380, 232, 10, '1968-03-05'),
(381, 232, 11, '2'),
(382, 232, 12, 'bornholdt.darleen@example.com'),
(384, 233, 1, 'Borstelmann'),
(385, 233, 2, 'Darlene'),
(386, 233, 3, 'Blumenwiese 54'),
(387, 233, 4, '12345'),
(388, 233, 5, 'Müllerhausen'),
(389, 233, 6, 'DEU'),
(390, 233, 7, '0906-2255773'),
(391, 233, 8, '0201-457412'),
(392, 233, 10, '1984-03-07'),
(393, 233, 11, '2'),
(394, 233, 12, 'borstelmann.darlene@example.com'),
(396, 234, 1, 'Böckler'),
(397, 234, 2, 'Darnell'),
(398, 234, 3, 'Blumenwiese 55'),
(399, 234, 4, '12345'),
(400, 234, 5, 'Müllerhausen'),
(401, 234, 6, 'DEU'),
(402, 234, 7, '0907-2255773'),
(403, 234, 8, '0202-457412'),
(404, 234, 10, '1964-03-09'),
(405, 234, 11, '1'),
(407, 235, 1, 'Böttger'),
(408, 235, 2, 'Darrell'),
(409, 235, 3, 'Blumenwiese 56'),
(410, 235, 4, '12345'),
(411, 235, 5, 'Müllerhausen'),
(412, 235, 6, 'DEU'),
(413, 235, 7, '0908-2255773'),
(414, 235, 8, '0203-457412'),
(415, 235, 10, '1986-03-12'),
(416, 235, 11, '1'),
(418, 236, 1, 'Brandão'),
(419, 236, 2, 'Darren'),
(420, 236, 3, 'Blumenwiese 57'),
(421, 236, 4, '12345'),
(422, 236, 5, 'Müllerhausen'),
(423, 236, 6, 'DEU'),
(424, 236, 7, '0909-2255773'),
(425, 236, 8, '0204-457412'),
(426, 236, 10, '1976-03-13'),
(427, 236, 11, '1'),
(429, 237, 1, 'Brandt'),
(430, 237, 2, 'Darrin'),
(431, 237, 3, 'Blumenwiese 58'),
(432, 237, 4, '12345'),
(433, 237, 5, 'Müllerhausen'),
(434, 237, 6, 'DEU'),
(435, 237, 7, '0910-2255773'),
(436, 237, 8, '0205-457412'),
(437, 237, 10, '1971-03-16'),
(438, 237, 11, '1'),
(439, 237, 12, 'brandt.darrin@example.com'),
(441, 238, 1, 'Brill'),
(442, 238, 2, 'Darrlyn'),
(443, 238, 3, 'Blumenwiese 59'),
(444, 238, 4, '12345'),
(445, 238, 5, 'Müllerhausen'),
(446, 238, 6, 'DEU'),
(447, 238, 7, '0911-2255773'),
(448, 238, 8, '0206-457412'),
(449, 238, 10, '1974-03-18'),
(450, 238, 11, '2'),
(451, 238, 12, 'brill.darrlyn@example.com'),
(453, 239, 1, 'Broska'),
(454, 239, 2, 'Darryl'),
(455, 239, 3, 'Blumenwiese 60'),
(456, 239, 4, '12345'),
(457, 239, 5, 'Müllerhausen'),
(458, 239, 6, 'DEU'),
(459, 239, 7, '0912-2255773'),
(460, 239, 8, '0207-457412'),
(461, 239, 10, '1975-03-20'),
(462, 239, 11, '1'),
(463, 239, 12, 'broska.darryl@example.com'),
(465, 240, 1, 'Brufau'),
(466, 240, 2, 'Darwin'),
(467, 240, 3, 'Blumenwiese 61'),
(468, 240, 4, '12345'),
(469, 240, 5, 'Müllerhausen'),
(470, 240, 6, 'DEU'),
(471, 240, 7, '0913-2255773'),
(472, 240, 8, '0208-457412'),
(473, 240, 10, '1977-03-22'),
(474, 240, 11, '1'),
(475, 240, 12, 'brufau.darwin@example.com'),
(477, 241, 1, 'Bruhns'),
(478, 241, 2, 'Daryl'),
(479, 241, 3, 'Blumenwiese 62'),
(480, 241, 4, '25487'),
(481, 241, 5, 'Senfblumendorf'),
(482, 241, 6, 'DEU'),
(483, 241, 7, '0914-2255773'),
(484, 241, 8, '0209-457412'),
(485, 241, 10, '1975-03-24'),
(486, 241, 11, '2'),
(487, 241, 12, 'bruhns.daryl@example.com'),
(489, 242, 1, 'Brügmann'),
(490, 242, 2, 'Dave'),
(491, 242, 3, 'Blumenwiese 63'),
(492, 242, 4, '25487'),
(493, 242, 5, 'Senfblumendorf'),
(494, 242, 6, 'DEU'),
(495, 242, 7, '0915-2255773'),
(496, 242, 8, '0210-457412'),
(497, 242, 10, '2011-03-26'),
(498, 242, 11, '1'),
(500, 243, 1, 'Bubert'),
(501, 243, 2, 'David'),
(502, 243, 3, 'Blumenwiese 64'),
(503, 243, 4, '25487'),
(504, 243, 5, 'Senfblumendorf'),
(505, 243, 6, 'DEU'),
(506, 243, 7, '0916-2255773'),
(507, 243, 8, '0211-457412'),
(508, 243, 10, '1992-03-27'),
(509, 243, 11, '1'),
(510, 243, 12, 'bubert.david@example.com'),
(512, 244, 1, 'Buck'),
(513, 244, 2, 'Davis'),
(514, 244, 3, 'Blumenwiese 65'),
(515, 244, 4, '25487'),
(516, 244, 5, 'Senfblumendorf'),
(517, 244, 6, 'DEU'),
(518, 244, 7, '0917-2255773'),
(519, 244, 8, '0212-457412'),
(520, 244, 10, '1990-03-30'),
(521, 244, 11, '1'),
(522, 244, 12, 'buck.davis@example.com'),
(524, 245, 1, 'Buddenbohm'),
(525, 245, 2, 'Dawn'),
(526, 245, 3, 'Blumenwiese 66'),
(527, 245, 4, '25487'),
(528, 245, 5, 'Senfblumendorf'),
(529, 245, 6, 'DEU'),
(530, 245, 7, '0918-2255773'),
(531, 245, 8, '0213-457412'),
(532, 245, 10, '1985-04-01'),
(533, 245, 11, '1'),
(534, 245, 12, 'buddenbohm.dawn@example.com'),
(536, 246, 1, 'Burmeister'),
(537, 246, 2, 'Dawson'),
(538, 246, 3, 'Blumenwiese 67'),
(539, 246, 4, '25487'),
(540, 246, 5, 'Senfblumendorf'),
(541, 246, 6, 'DEU'),
(542, 246, 7, '0919-2255773'),
(543, 246, 8, '0214-457412'),
(544, 246, 10, '2009-04-03'),
(545, 246, 11, '1'),
(546, 246, 12, 'burmeister.dawson@example.com'),
(548, 247, 1, 'Busch'),
(549, 247, 2, 'Dayana'),
(550, 247, 3, 'Blumenwiese 68'),
(551, 247, 4, '25487'),
(552, 247, 5, 'Senfblumendorf'),
(553, 247, 6, 'DEU'),
(554, 247, 7, '0920-2255773'),
(555, 247, 8, '0215-457412'),
(556, 247, 10, '2002-04-05'),
(557, 247, 11, '2'),
(558, 247, 12, 'busch.dayana@example.com'),
(560, 248, 1, 'Cardoso'),
(561, 248, 2, 'Dean'),
(562, 248, 3, 'Blumenwiese 69'),
(563, 248, 4, '25487'),
(564, 248, 5, 'Senfblumendorf'),
(565, 248, 6, 'DEU'),
(566, 248, 7, '0921-2255773'),
(567, 248, 8, '0216-457412'),
(568, 248, 10, '1976-04-06'),
(569, 248, 11, '1'),
(570, 248, 12, 'cardoso.dean@example.com'),
(572, 249, 1, 'Carstens'),
(573, 249, 2, 'Deana'),
(574, 249, 3, 'Blumenwiese 70'),
(575, 249, 4, '25487'),
(576, 249, 5, 'Senfblumendorf'),
(577, 249, 6, 'DEU'),
(578, 249, 7, '0922-2255773'),
(579, 249, 8, '0217-457412'),
(580, 249, 10, '1994-04-09'),
(581, 249, 11, '2'),
(582, 249, 12, 'carstens.deana@example.com'),
(584, 250, 1, 'Christier'),
(585, 250, 2, 'Deandre'),
(586, 250, 3, 'Blumenwiese 71'),
(587, 250, 4, '25487'),
(588, 250, 5, 'Senfblumendorf'),
(589, 250, 6, 'DEU'),
(590, 250, 7, '0923-2255773'),
(591, 250, 8, '0218-457412'),
(592, 250, 10, '1997-04-11'),
(593, 250, 11, '1'),
(594, 250, 12, 'christier.deandre@example.com'),
(596, 251, 1, 'Cölle'),
(597, 251, 2, 'Deanna'),
(598, 251, 3, 'Blumenwiese 72'),
(599, 251, 4, '25487'),
(600, 251, 5, 'Senfblumendorf'),
(601, 251, 6, 'DEU'),
(602, 251, 7, '0924-2255773'),
(603, 251, 8, '0219-457412'),
(604, 251, 10, '1964-04-12'),
(605, 251, 11, '2'),
(607, 252, 1, 'Cornelsen'),
(608, 252, 2, 'Debbie'),
(609, 252, 3, 'Blumenwiese 73'),
(610, 252, 4, '25487'),
(611, 252, 5, 'Senfblumendorf'),
(612, 252, 6, 'DEU'),
(613, 252, 7, '0925-2255773'),
(614, 252, 8, '0220-457412'),
(615, 252, 10, '1991-04-15'),
(616, 252, 11, '2'),
(617, 252, 12, 'cornelsen.debbie@example.com'),
(619, 253, 1, 'Dabelstein'),
(620, 253, 2, 'Debora'),
(621, 253, 3, 'Blumenwiese 74'),
(622, 253, 4, '25487'),
(623, 253, 5, 'Senfblumendorf'),
(624, 253, 6, 'DEU'),
(625, 253, 7, '0926-2255773'),
(626, 253, 8, '0221-457412'),
(627, 253, 10, '1966-04-17'),
(628, 253, 11, '2'),
(629, 253, 12, 'dabelstein.debora@example.com'),
(631, 254, 1, 'Danielsen'),
(632, 254, 2, 'Deborah'),
(633, 254, 3, 'Blumenwiese 75'),
(634, 254, 4, '25487'),
(635, 254, 5, 'Senfblumendorf'),
(636, 254, 6, 'DEU'),
(637, 254, 7, '0927-2255773'),
(638, 254, 8, '0222-457412'),
(639, 254, 10, '2015-04-19'),
(640, 254, 11, '2'),
(641, 254, 12, 'danielsen.deborah@example.com'),
(643, 255, 1, 'Danisch'),
(644, 255, 2, 'Debra'),
(645, 255, 3, 'Blumenwiese 76'),
(646, 255, 4, '25487'),
(647, 255, 5, 'Senfblumendorf'),
(648, 255, 6, 'DEU'),
(649, 255, 7, '0928-2255773'),
(650, 255, 8, '0223-457412'),
(651, 255, 10, '1993-04-21'),
(652, 255, 11, '2'),
(653, 255, 12, 'danisch.debra@example.com'),
(655, 256, 1, 'Dassau'),
(656, 256, 2, 'Dee'),
(657, 256, 3, 'Blumenwiese 77'),
(658, 256, 4, '25487'),
(659, 256, 5, 'Senfblumendorf'),
(660, 256, 6, 'DEU'),
(661, 256, 7, '0929-2255773'),
(662, 256, 8, '0224-457412'),
(663, 256, 10, '2008-04-22'),
(664, 256, 11, '1'),
(665, 256, 12, 'dassau.dee@example.com'),
(667, 257, 1, 'Dautert'),
(668, 257, 2, 'Degenhard'),
(669, 257, 3, 'Blumenwiese 78'),
(670, 257, 4, '25487'),
(671, 257, 5, 'Senfblumendorf'),
(672, 257, 6, 'DEU'),
(673, 257, 7, '0930-2255773'),
(674, 257, 8, '0225-457412'),
(675, 257, 10, '2008-04-24'),
(676, 257, 11, '1'),
(677, 257, 12, 'dautert.degenhard@example.com'),
(679, 258, 1, 'Pode'),
(680, 258, 2, 'Freitas'),
(681, 258, 3, 'Blumenwiese 79'),
(682, 258, 4, '25487'),
(683, 258, 5, 'Senfblumendorf'),
(684, 258, 6, 'DEU'),
(685, 258, 7, '0931-2255773'),
(686, 258, 8, '0226-457412'),
(687, 258, 10, '1985-04-27'),
(688, 258, 11, '1'),
(689, 258, 12, 'pode.freitas@example.com'),
(691, 259, 1, 'Pode'),
(692, 259, 2, 'Paulo'),
(693, 259, 3, 'Blumenwiese 80'),
(694, 259, 4, '25487'),
(695, 259, 5, 'Senfblumendorf'),
(696, 259, 6, 'DEU'),
(697, 259, 7, '0932-2255773'),
(698, 259, 8, '0227-457412'),
(699, 259, 10, '2003-04-29'),
(700, 259, 11, '1'),
(701, 259, 12, 'pode.paulo@example.com'),
(703, 260, 1, 'Derwaldt'),
(704, 260, 2, 'Delia'),
(705, 260, 3, 'Blumenwiese 81'),
(706, 260, 4, '25487'),
(707, 260, 5, 'Senfblumendorf'),
(708, 260, 6, 'DEU'),
(709, 260, 7, '0933-2255773'),
(710, 260, 8, '0228-457412'),
(711, 260, 10, '1999-05-01'),
(712, 260, 11, '2'),
(713, 260, 12, 'derwaldt.delia@example.com'),
(715, 261, 1, 'Dettenborn'),
(716, 261, 2, 'Della'),
(717, 261, 3, 'Blumenwiese 82'),
(718, 261, 4, '25487'),
(719, 261, 5, 'Senfblumendorf'),
(720, 261, 6, 'DEU'),
(721, 261, 7, '0934-2255773'),
(722, 261, 8, '0229-457412'),
(723, 261, 10, '1977-05-03'),
(724, 261, 11, '2'),
(725, 261, 12, 'dettenborn.della@example.com'),
(727, 262, 1, 'Dibbern'),
(728, 262, 2, 'Delores'),
(729, 262, 3, 'Blumenwiese 83'),
(730, 262, 4, '25487'),
(731, 262, 5, 'Senfblumendorf'),
(732, 262, 6, 'DEU'),
(733, 262, 7, '0935-2255773'),
(734, 262, 8, '0230-457412'),
(735, 262, 10, '1992-05-04'),
(736, 262, 11, '2'),
(737, 262, 12, 'dibbern.delores@example.com'),
(739, 263, 1, 'Dieckvoß'),
(740, 263, 2, 'Deloris'),
(741, 263, 3, 'Blumenwiese 84'),
(742, 263, 4, '25487'),
(743, 263, 5, 'Senfblumendorf'),
(744, 263, 6, 'DEU'),
(745, 263, 7, '0936-2255773'),
(746, 263, 8, '0231-457412'),
(747, 263, 10, '1991-05-07'),
(748, 263, 11, '2'),
(750, 264, 1, 'Diestel'),
(751, 264, 2, 'Demetrius'),
(752, 264, 3, 'Blumenwiese 85'),
(753, 264, 4, '25487'),
(754, 264, 5, 'Senfblumendorf'),
(755, 264, 6, 'DEU'),
(756, 264, 7, '0937-2255773'),
(757, 264, 8, '0232-457412'),
(758, 264, 10, '1966-05-09'),
(759, 264, 11, '1'),
(760, 264, 12, 'diestel.demetrius@example.com'),
(762, 265, 1, 'Dittmer'),
(763, 265, 2, 'Dena'),
(764, 265, 3, 'Blumenwiese 86'),
(765, 265, 4, '25487'),
(766, 265, 5, 'Senfblumendorf'),
(767, 265, 6, 'DEU'),
(768, 265, 7, '0938-2255773'),
(769, 265, 8, '0233-457412'),
(770, 265, 10, '2013-05-11'),
(771, 265, 11, '2'),
(772, 265, 12, 'dittmer.dena@example.com'),
(774, 266, 1, 'Donamore'),
(775, 266, 2, 'Denise'),
(776, 266, 3, 'Blumenwiese 87'),
(777, 266, 4, '25487'),
(778, 266, 5, 'Senfblumendorf'),
(779, 266, 6, 'DEU'),
(780, 266, 7, '0939-2255773'),
(781, 266, 8, '0234-457412'),
(782, 266, 10, '1971-05-13'),
(783, 266, 11, '2'),
(784, 266, 12, 'donamore.denise@example.com'),
(786, 267, 1, 'Dorka'),
(787, 267, 2, 'Dennis'),
(788, 267, 3, 'Blumenwiese 88'),
(789, 267, 4, '25487'),
(790, 267, 5, 'Senfblumendorf'),
(791, 267, 6, 'DEU'),
(792, 267, 7, '0940-2255773'),
(793, 267, 8, '0235-457412'),
(794, 267, 10, '1974-05-15'),
(795, 267, 11, '1'),
(796, 267, 12, 'dorka.dennis@example.com'),
(798, 268, 1, 'Drews'),
(799, 268, 2, 'Désiré'),
(800, 268, 3, 'Blumenwiese 89'),
(801, 268, 4, '25487'),
(802, 268, 5, 'Senfblumendorf'),
(803, 268, 6, 'DEU'),
(804, 268, 7, '0941-2255773'),
(805, 268, 8, '0236-457412'),
(806, 268, 10, '2002-05-17'),
(807, 268, 11, '2'),
(809, 269, 1, 'Dümon'),
(810, 269, 2, 'Desiree'),
(811, 269, 3, 'Blumenwiese 90'),
(812, 269, 4, '25487'),
(813, 269, 5, 'Senfblumendorf'),
(814, 269, 6, 'DEU'),
(815, 269, 7, '0942-2255773'),
(816, 269, 8, '0237-457412'),
(817, 269, 10, '2003-05-19'),
(818, 269, 11, '2'),
(820, 270, 1, 'Düren'),
(821, 270, 2, 'Destiny'),
(822, 270, 3, 'Blumenwiese 91'),
(823, 270, 4, '25487'),
(824, 270, 5, 'Senfblumendorf'),
(825, 270, 6, 'DEU'),
(826, 270, 7, '0943-2255773'),
(827, 270, 8, '0238-457412'),
(828, 270, 10, '1994-05-21'),
(829, 270, 11, '2'),
(831, 271, 1, 'Dwenger'),
(832, 271, 2, 'Detlev'),
(833, 271, 3, 'Blumenwiese 92'),
(834, 271, 4, '25487'),
(835, 271, 5, 'Senfblumendorf'),
(836, 271, 6, 'DEU'),
(837, 271, 7, '0944-2255773'),
(838, 271, 8, '0239-457412'),
(839, 271, 10, '2010-05-23'),
(840, 271, 11, '1'),
(841, 271, 12, 'dwenger.detlev@example.com'),
(843, 272, 1, 'Eckholt'),
(844, 272, 2, 'Devan'),
(845, 272, 3, 'Blumenwiese 93'),
(846, 272, 4, '25487'),
(847, 272, 5, 'Senfblumendorf'),
(848, 272, 6, 'DEU'),
(849, 272, 7, '0945-2255773'),
(850, 272, 8, '0240-457412'),
(851, 272, 10, '1971-05-25'),
(852, 272, 11, '1'),
(853, 272, 12, 'eckholt.devan@example.com'),
(855, 273, 1, 'Eckmann'),
(856, 273, 2, 'Deven'),
(857, 273, 3, 'Blumenwiese 94'),
(858, 273, 4, '25487'),
(859, 273, 5, 'Senfblumendorf'),
(860, 273, 6, 'DEU'),
(861, 273, 7, '0946-2255773'),
(862, 273, 8, '0241-457412'),
(863, 273, 10, '2009-05-27'),
(864, 273, 11, '1'),
(865, 273, 12, 'eckmann.deven@example.com'),
(867, 274, 1, 'Eggers'),
(868, 274, 2, 'Devin'),
(869, 274, 3, 'Blumenwiese 95'),
(870, 274, 4, '25487'),
(871, 274, 5, 'Senfblumendorf'),
(872, 274, 6, 'DEU'),
(873, 274, 7, '0947-2255773'),
(874, 274, 8, '0242-457412'),
(875, 274, 10, '1967-05-29'),
(876, 274, 11, '1'),
(877, 274, 12, 'eggers.devin@example.com'),
(879, 275, 1, 'Eggerstedt'),
(880, 275, 2, 'Devon'),
(881, 275, 3, 'Blumenwiese 96'),
(882, 275, 4, '25487'),
(883, 275, 5, 'Senfblumendorf'),
(884, 275, 6, 'DEU'),
(885, 275, 7, '0948-2255773'),
(886, 275, 8, '0243-457412'),
(887, 275, 10, '1970-05-31'),
(888, 275, 11, '1'),
(889, 275, 12, 'eggerstedt.devon@example.com'),
(891, 276, 1, 'Ehlers'),
(892, 276, 2, 'Dewayne'),
(893, 276, 3, 'Blumenwiese 97'),
(894, 276, 4, '25487'),
(895, 276, 5, 'Senfblumendorf'),
(896, 276, 6, 'DEU'),
(897, 276, 7, '0949-2255773'),
(898, 276, 8, '0244-457412'),
(899, 276, 10, '1989-06-02'),
(900, 276, 11, '1'),
(901, 276, 12, 'ehlers.dewayne@example.com'),
(903, 277, 1, 'Ehmling'),
(904, 277, 2, 'Dewey'),
(905, 277, 3, 'Blumenwiese 98'),
(906, 277, 4, '25487'),
(907, 277, 5, 'Senfblumendorf'),
(908, 277, 6, 'DEU'),
(909, 277, 7, '0950-2255773'),
(910, 277, 8, '0245-457412'),
(911, 277, 10, '1961-06-04'),
(912, 277, 11, '1'),
(913, 277, 12, 'ehmling.dewey@example.com'),
(915, 278, 1, 'Engel'),
(916, 278, 2, 'Dewitt'),
(917, 278, 3, 'Blumenwiese 99'),
(918, 278, 4, '25487'),
(919, 278, 5, 'Senfblumendorf'),
(920, 278, 6, 'DEU'),
(921, 278, 7, '0951-2255773'),
(922, 278, 8, '0246-457412'),
(923, 278, 10, '1992-06-05'),
(924, 278, 11, '1'),
(925, 278, 12, 'engel.dewitt@example.com'),
(927, 279, 1, 'Feldhusen'),
(928, 279, 2, 'Dexter'),
(929, 279, 3, 'Blumenwiese 100'),
(930, 279, 4, '25487'),
(931, 279, 5, 'Senfblumendorf'),
(932, 279, 6, 'DEU'),
(933, 279, 7, '0952-2255773'),
(934, 279, 8, '0247-457412'),
(935, 279, 10, '2006-06-08'),
(936, 279, 11, '1'),
(937, 279, 12, 'feldhusen.dexter@example.com'),
(939, 280, 1, 'Fischeder'),
(940, 280, 2, 'Dharma'),
(941, 280, 3, 'Blumenwiese 101'),
(942, 280, 4, '25487'),
(943, 280, 5, 'Senfblumendorf'),
(944, 280, 6, 'DEU'),
(945, 280, 7, '0953-2255773'),
(946, 280, 8, '0248-457412'),
(947, 280, 10, '1980-06-09'),
(948, 280, 11, '2'),
(949, 280, 12, 'fischeder.dharma@example.com'),
(951, 281, 1, 'Fischer'),
(952, 281, 2, 'Diamond'),
(953, 281, 3, 'Blumenwiese 102'),
(954, 281, 4, '25487'),
(955, 281, 5, 'Senfblumendorf'),
(956, 281, 6, 'DEU'),
(957, 281, 7, '0954-2255773'),
(958, 281, 8, '0249-457412'),
(959, 281, 10, '2013-06-12'),
(960, 281, 11, '2'),
(961, 281, 12, 'fischer.diamond@example.com'),
(963, 282, 1, 'Flint'),
(964, 282, 2, 'Diana'),
(965, 282, 3, 'Blumenwiese 103'),
(966, 282, 4, '25487'),
(967, 282, 5, 'Senfblumendorf'),
(968, 282, 6, 'DEU'),
(969, 282, 7, '0955-2255773'),
(970, 282, 8, '0250-457412'),
(971, 282, 10, '1977-06-14'),
(972, 282, 11, '2'),
(973, 282, 12, 'flint.diana@example.com'),
(975, 283, 1, 'Fuhrmann'),
(976, 283, 2, 'Diane'),
(977, 283, 3, 'Blumenwiese 104'),
(978, 283, 4, '25487'),
(979, 283, 5, 'Senfblumendorf'),
(980, 283, 6, 'DEU'),
(981, 283, 7, '0956-2255773'),
(982, 283, 8, '0251-457412'),
(983, 283, 10, '2004-06-15'),
(984, 283, 11, '2'),
(985, 283, 12, 'fuhrmann.diane@example.com'),
(987, 284, 1, 'Furtado'),
(988, 284, 2, 'Dianna'),
(989, 284, 3, 'Blumenwiese 105'),
(990, 284, 4, '25487'),
(991, 284, 5, 'Senfblumendorf'),
(992, 284, 6, 'DEU'),
(993, 284, 7, '0957-2255773'),
(994, 284, 8, '0252-457412'),
(995, 284, 10, '1996-06-17'),
(996, 284, 11, '2'),
(997, 284, 12, 'furtado.dianna@example.com'),
(999, 285, 1, 'Galle'),
(1000, 285, 2, 'Dianne'),
(1001, 285, 3, 'Blumenwiese 106'),
(1002, 285, 4, '25487'),
(1003, 285, 5, 'Senfblumendorf'),
(1004, 285, 6, 'DEU'),
(1005, 285, 7, '0958-2255773'),
(1006, 285, 8, '0253-457412'),
(1007, 285, 10, '1990-06-20'),
(1008, 285, 11, '2'),
(1009, 285, 12, 'galle.dianne@example.com'),
(1011, 286, 1, 'Gardeleben'),
(1012, 286, 2, 'Dick'),
(1013, 286, 3, 'Blumenwiese 107'),
(1014, 286, 4, '25487'),
(1015, 286, 5, 'Senfblumendorf'),
(1016, 286, 6, 'DEU'),
(1017, 286, 7, '0959-2255773'),
(1018, 286, 8, '0254-457412'),
(1019, 286, 10, '2015-06-22'),
(1020, 286, 11, '1'),
(1021, 286, 12, 'gardeleben.dick@example.com'),
(1023, 287, 1, 'Östermann'),
(1024, 287, 2, 'Diego'),
(1025, 287, 3, 'Blumenwiese 108'),
(1026, 287, 4, '25487'),
(1027, 287, 5, 'Senfblumendorf'),
(1028, 287, 6, 'DEU'),
(1029, 287, 7, '0960-2255773'),
(1030, 287, 8, '0255-457412'),
(1031, 287, 10, '1990-06-24'),
(1032, 287, 11, '1'),
(1033, 287, 12, 'oestermann.diego@example.com'),
(1035, 288, 1, 'Geertzen'),
(1036, 288, 2, 'Diégo'),
(1037, 288, 3, 'Blumenwiese 109'),
(1038, 288, 4, '98956'),
(1039, 288, 5, 'Berghaintal'),
(1040, 288, 6, 'DEU'),
(1041, 288, 7, '0961-2255773'),
(1042, 288, 8, '0256-457412'),
(1043, 288, 10, '1988-06-25'),
(1044, 288, 11, '1'),
(1046, 289, 1, 'Gerdau'),
(1047, 289, 2, 'Dietbert'),
(1048, 289, 3, 'Blumenwiese 110'),
(1049, 289, 4, '98956'),
(1050, 289, 5, 'Berghaintal'),
(1051, 289, 6, 'DEU'),
(1052, 289, 7, '0962-2255773'),
(1053, 289, 8, '0257-457412'),
(1054, 289, 10, '2011-06-28'),
(1055, 289, 11, '1'),
(1056, 289, 12, 'gerdau.dietbert@example.com'),
(1058, 290, 1, 'Gerken'),
(1059, 290, 2, 'Dieter'),
(1060, 290, 3, 'Blumenwiese 111'),
(1061, 290, 4, '98956'),
(1062, 290, 5, 'Berghaintal'),
(1063, 290, 6, 'DEU'),
(1064, 290, 7, '0963-2255773'),
(1065, 290, 8, '0258-457412'),
(1066, 290, 10, '2014-06-30'),
(1067, 290, 11, '1'),
(1068, 290, 12, 'gerken.dieter@example.com'),
(1070, 291, 1, 'Gerstenkorn'),
(1071, 291, 2, 'Dietger'),
(1072, 291, 3, 'Blumenwiese 112'),
(1073, 291, 4, '98956'),
(1074, 291, 5, 'Berghaintal'),
(1075, 291, 6, 'DEU'),
(1076, 291, 7, '0964-2255773'),
(1077, 291, 8, '0259-457412'),
(1078, 291, 10, '2009-07-02'),
(1079, 291, 11, '1'),
(1080, 291, 12, 'gerstenkorn.dietger@example.com'),
(1082, 292, 1, 'Giesemann'),
(1083, 292, 2, 'Diethard'),
(1084, 292, 3, 'Blumenwiese 113'),
(1085, 292, 4, '98956'),
(1086, 292, 5, 'Berghaintal'),
(1087, 292, 6, 'DEU'),
(1088, 292, 7, '0965-2255773'),
(1089, 292, 8, '0260-457412'),
(1090, 292, 10, '1987-07-04'),
(1091, 292, 11, '1'),
(1092, 292, 12, 'giesemann.diethard@example.com'),
(1094, 293, 1, 'Göben'),
(1095, 293, 2, 'Diethild'),
(1096, 293, 3, 'Blumenwiese 114'),
(1097, 293, 4, '98956'),
(1098, 293, 5, 'Berghaintal'),
(1099, 293, 6, 'DEU'),
(1100, 293, 7, '0966-2255773'),
(1101, 293, 8, '0261-457412'),
(1102, 293, 10, '1998-07-06'),
(1103, 293, 11, '2'),
(1105, 294, 1, 'Gollmann'),
(1106, 294, 2, 'Dietlinde'),
(1107, 294, 3, 'Blumenwiese 115'),
(1108, 294, 4, '98956'),
(1109, 294, 5, 'Berghaintal'),
(1110, 294, 6, 'DEU'),
(1111, 294, 7, '0967-2255773'),
(1112, 294, 8, '0262-457412'),
(1113, 294, 10, '1967-07-08'),
(1114, 294, 11, '2'),
(1115, 294, 12, 'gollmann.dietlinde@example.com'),
(1117, 295, 1, 'Griem'),
(1118, 295, 2, 'Dietmar'),
(1119, 295, 3, 'Blumenwiese 116'),
(1120, 295, 4, '98956'),
(1121, 295, 5, 'Berghaintal'),
(1122, 295, 6, 'DEU'),
(1123, 295, 7, '0968-2255773'),
(1124, 295, 8, '0263-457412'),
(1125, 295, 10, '1998-07-10'),
(1126, 295, 11, '1'),
(1127, 295, 12, 'griem.dietmar@example.com'),
(1129, 296, 1, 'Grivot'),
(1130, 296, 2, 'Dietrich'),
(1131, 296, 3, 'Blumenwiese 117'),
(1132, 296, 4, '98956'),
(1133, 296, 5, 'Berghaintal'),
(1134, 296, 6, 'DEU'),
(1135, 296, 7, '0969-2255773'),
(1136, 296, 8, '0264-457412'),
(1137, 296, 10, '1991-07-12'),
(1138, 296, 11, '1'),
(1139, 296, 12, 'grivot.dietrich@example.com'),
(1141, 297, 1, 'Groskopf'),
(1142, 297, 2, 'Dillon'),
(1143, 297, 3, 'Blumenwiese 118'),
(1144, 297, 4, '98956'),
(1145, 297, 5, 'Berghaintal'),
(1146, 297, 6, 'DEU'),
(1147, 297, 7, '0970-2255773'),
(1148, 297, 8, '0265-457412'),
(1149, 297, 10, '1967-07-14'),
(1150, 297, 11, '1'),
(1151, 297, 12, 'groskopf.dillon@example.com'),
(1153, 298, 1, 'Groth'),
(1154, 298, 2, 'Dina'),
(1155, 298, 3, 'Blumenwiese 119'),
(1156, 298, 4, '98956'),
(1157, 298, 5, 'Berghaintal'),
(1158, 298, 6, 'DEU'),
(1159, 298, 7, '0971-2255773'),
(1160, 298, 8, '0266-457412'),
(1161, 298, 10, '2001-07-16'),
(1162, 298, 11, '2'),
(1163, 298, 12, 'groth.dina@example.com'),
(1165, 299, 1, 'Grube'),
(1166, 299, 2, 'Dinoysius'),
(1167, 299, 3, 'Blumenwiese 120'),
(1168, 299, 4, '98956'),
(1169, 299, 5, 'Berghaintal'),
(1170, 299, 6, 'DEU'),
(1171, 299, 7, '0972-2255773'),
(1172, 299, 8, '0267-457412'),
(1173, 299, 10, '2008-07-17'),
(1174, 299, 11, '1'),
(1175, 299, 12, 'grube.dinoysius@example.com'),
(1177, 300, 1, 'Grunwald'),
(1178, 300, 2, 'Dion'),
(1179, 300, 3, 'Blumenwiese 121'),
(1180, 300, 4, '98956'),
(1181, 300, 5, 'Berghaintal'),
(1182, 300, 6, 'DEU'),
(1183, 300, 7, '0973-2255773'),
(1184, 300, 8, '0268-457412'),
(1185, 300, 10, '1991-07-20'),
(1186, 300, 11, '1'),
(1187, 300, 12, 'grunwald.dion@example.com'),
(1189, 301, 1, 'Grutschus'),
(1190, 301, 2, 'Diona'),
(1191, 301, 3, 'Blumenwiese 122'),
(1192, 301, 4, '98956'),
(1193, 301, 5, 'Berghaintal'),
(1194, 301, 6, 'DEU'),
(1195, 301, 7, '0974-2255773'),
(1196, 301, 8, '0269-457412'),
(1197, 301, 10, '1963-07-22'),
(1198, 301, 11, '2'),
(1199, 301, 12, 'grutschus.diona@example.com'),
(1201, 302, 1, 'Hack'),
(1202, 302, 2, 'Dione'),
(1203, 302, 3, 'Blumenwiese 123'),
(1204, 302, 4, '98956'),
(1205, 302, 5, 'Berghaintal'),
(1206, 302, 6, 'DEU'),
(1207, 302, 7, '0975-2255773'),
(1208, 302, 8, '0270-457412'),
(1209, 302, 10, '2003-07-24'),
(1210, 302, 11, '2'),
(1211, 302, 12, 'hack.dione@example.com'),
(1213, 303, 1, 'Hacker'),
(1214, 303, 2, 'Dionysia'),
(1215, 303, 3, 'Blumenwiese 124'),
(1216, 303, 4, '98956'),
(1217, 303, 5, 'Berghaintal'),
(1218, 303, 6, 'DEU'),
(1219, 303, 7, '0976-2255773'),
(1220, 303, 8, '0271-457412'),
(1221, 303, 10, '1963-07-26'),
(1222, 303, 11, '2'),
(1223, 303, 12, 'hacker.dionysia@example.com'),
(1225, 304, 1, 'Häfner'),
(1226, 304, 2, 'Dionysius'),
(1227, 304, 3, 'Blumenwiese 125'),
(1228, 304, 4, '98956'),
(1229, 304, 5, 'Berghaintal'),
(1230, 304, 6, 'DEU'),
(1231, 304, 7, '0977-2255773'),
(1232, 304, 8, '0272-457412'),
(1233, 304, 10, '1971-07-28'),
(1234, 304, 11, '1'),
(1236, 305, 1, 'Hamann'),
(1237, 305, 2, 'Dixie'),
(1238, 305, 3, 'Blumenwiese 126'),
(1239, 305, 4, '98956'),
(1240, 305, 5, 'Berghaintal'),
(1241, 305, 6, 'DEU'),
(1242, 305, 7, '0978-2255773'),
(1243, 305, 8, '0273-457412'),
(1244, 305, 10, '1995-07-30'),
(1245, 305, 11, '2'),
(1246, 305, 12, 'hamann.dixie@example.com'),
(1248, 306, 1, 'Hamdorf'),
(1249, 306, 2, 'Dolly'),
(1250, 306, 3, 'Blumenwiese 127'),
(1251, 306, 4, '98956'),
(1252, 306, 5, 'Berghaintal'),
(1253, 306, 6, 'DEU'),
(1254, 306, 7, '0979-2255773'),
(1255, 306, 8, '0274-457412'),
(1256, 306, 10, '2010-08-01'),
(1257, 306, 11, '2'),
(1258, 306, 12, 'hamdorf.dolly@example.com'),
(1260, 307, 1, 'Hansel'),
(1261, 307, 2, 'Dolores'),
(1262, 307, 3, 'Blumenwiese 128'),
(1263, 307, 4, '98956'),
(1264, 307, 5, 'Berghaintal'),
(1265, 307, 6, 'DEU'),
(1266, 307, 7, '0980-2255773'),
(1267, 307, 8, '0275-457412'),
(1268, 307, 10, '2005-08-03'),
(1269, 307, 11, '2'),
(1270, 307, 12, 'hansel.dolores@example.com'),
(1272, 308, 1, 'Harder'),
(1273, 308, 2, 'Domingo'),
(1274, 308, 3, 'Blumenwiese 129'),
(1275, 308, 4, '75357'),
(1276, 308, 5, 'Traumort'),
(1277, 308, 6, 'DEU'),
(1278, 308, 7, '0981-2255773'),
(1279, 308, 8, '0276-457412'),
(1280, 308, 10, '1974-08-05'),
(1281, 308, 11, '1'),
(1282, 308, 12, 'harder.domingo@example.com'),
(1284, 309, 1, 'Harms'),
(1285, 309, 2, 'Dominic'),
(1286, 309, 3, 'Blumenwiese 130'),
(1287, 309, 4, '75357'),
(1288, 309, 5, 'Traumort'),
(1289, 309, 6, 'DEU'),
(1290, 309, 7, '0982-2255773'),
(1291, 309, 8, '0277-457412'),
(1292, 309, 10, '1982-08-07'),
(1293, 309, 11, '1'),
(1294, 309, 12, 'harms.dominic@example.com'),
(1296, 310, 1, 'Harten'),
(1297, 310, 2, 'Dominick'),
(1298, 310, 3, 'Blumenwiese 131'),
(1299, 310, 4, '75357'),
(1300, 310, 5, 'Traumort'),
(1301, 310, 6, 'DEU'),
(1302, 310, 7, '0983-2255773'),
(1303, 310, 8, '0278-457412'),
(1304, 310, 10, '1993-08-09'),
(1305, 310, 11, '1'),
(1306, 310, 12, 'harten.dominick@example.com'),
(1308, 311, 1, 'Hartkop'),
(1309, 311, 2, 'Dominika'),
(1310, 311, 3, 'Blumenwiese 132'),
(1311, 311, 4, '75357'),
(1312, 311, 5, 'Traumort'),
(1313, 311, 6, 'DEU'),
(1314, 311, 7, '0984-2255773'),
(1315, 311, 8, '0279-457412'),
(1316, 311, 10, '2000-08-10'),
(1317, 311, 11, '2'),
(1318, 311, 12, 'hartkop.dominika@example.com'),
(1320, 312, 1, 'Hasenkämper'),
(1321, 312, 2, 'Dominikus'),
(1322, 312, 3, 'Blumenwiese 133'),
(1323, 312, 4, '75357'),
(1324, 312, 5, 'Traumort'),
(1325, 312, 6, 'DEU'),
(1326, 312, 7, '0985-2255773'),
(1327, 312, 8, '0280-457412'),
(1328, 312, 10, '1989-08-13'),
(1329, 312, 11, '1'),
(1331, 313, 1, 'Heerde'),
(1332, 313, 2, 'Dominique'),
(1333, 313, 3, 'Blumenwiese 134'),
(1334, 313, 4, '75357'),
(1335, 313, 5, 'Traumort'),
(1336, 313, 6, 'DEU'),
(1337, 313, 7, '0986-2255773'),
(1338, 313, 8, '0281-457412'),
(1339, 313, 10, '2011-08-15'),
(1340, 313, 11, '1'),
(1341, 313, 12, 'heerde.dominique@example.com'),
(1343, 314, 1, 'Heinrich'),
(1344, 314, 2, 'Domitian'),
(1345, 314, 3, 'Blumenwiese 135'),
(1346, 314, 4, '75357'),
(1347, 314, 5, 'Traumort'),
(1348, 314, 6, 'DEU'),
(1349, 314, 7, '0987-2255773'),
(1350, 314, 8, '0282-457412'),
(1351, 314, 10, '1990-08-17'),
(1352, 314, 11, '1'),
(1353, 314, 12, 'heinrich.domitian@example.com'),
(1355, 315, 1, 'Heitmann'),
(1356, 315, 2, 'Don'),
(1357, 315, 3, 'Blumenwiese 136'),
(1358, 315, 4, '75357'),
(1359, 315, 5, 'Traumort'),
(1360, 315, 6, 'DEU'),
(1361, 315, 7, '0988-2255773'),
(1362, 315, 8, '0283-457412'),
(1363, 315, 10, '1975-08-19'),
(1364, 315, 11, '1'),
(1365, 315, 12, 'heitmann.don@example.com'),
(1367, 316, 1, 'Helmke'),
(1368, 316, 2, 'Donald'),
(1369, 316, 3, 'Blumenwiese 137'),
(1370, 316, 4, '75357'),
(1371, 316, 5, 'Traumort'),
(1372, 316, 6, 'DEU'),
(1373, 316, 7, '0989-2255773'),
(1374, 316, 8, '0284-457412'),
(1375, 316, 10, '2013-08-21'),
(1376, 316, 11, '1'),
(1377, 316, 12, 'helmke.donald@example.com'),
(1379, 317, 1, 'Hemsath'),
(1380, 317, 2, 'Donatus'),
(1381, 317, 3, 'Blumenwiese 138'),
(1382, 317, 4, '75357'),
(1383, 317, 5, 'Traumort'),
(1384, 317, 6, 'DEU'),
(1385, 317, 7, '0990-2255773'),
(1386, 317, 8, '0285-457412'),
(1387, 317, 10, '1982-08-23'),
(1388, 317, 11, '1'),
(1389, 317, 12, 'hemsath.donatus@example.com'),
(1391, 318, 1, 'Henning'),
(1392, 318, 2, 'Donna'),
(1393, 318, 3, 'Blumenwiese 139'),
(1394, 318, 4, '75357'),
(1395, 318, 5, 'Traumort'),
(1396, 318, 6, 'DEU'),
(1397, 318, 7, '0991-2255773'),
(1398, 318, 8, '0286-457412'),
(1399, 318, 10, '1984-08-24'),
(1400, 318, 11, '2'),
(1401, 318, 12, 'henning.donna@example.com'),
(1403, 319, 1, 'Heruth'),
(1404, 319, 2, 'Donnie'),
(1405, 319, 3, 'Blumenwiese 140'),
(1406, 319, 4, '75357'),
(1407, 319, 5, 'Traumort'),
(1408, 319, 6, 'DEU'),
(1409, 319, 7, '0992-2255773'),
(1410, 319, 8, '0287-457412'),
(1411, 319, 10, '1999-08-27'),
(1412, 319, 11, '2'),
(1413, 319, 12, 'heruth.donnie@example.com'),
(1415, 320, 1, 'Heuck'),
(1416, 320, 2, 'Donovan'),
(1417, 320, 3, 'Blumenwiese 141'),
(1418, 320, 4, '75357'),
(1419, 320, 5, 'Traumort'),
(1420, 320, 6, 'DEU'),
(1421, 320, 7, '0993-2255773'),
(1422, 320, 8, '0288-457412'),
(1423, 320, 10, '2011-08-29'),
(1424, 320, 11, '1'),
(1425, 320, 12, 'heuck.donovan@example.com'),
(1427, 321, 1, 'Heuer'),
(1428, 321, 2, 'Dora'),
(1429, 321, 3, 'Blumenwiese 142'),
(1430, 321, 4, '75357'),
(1431, 321, 5, 'Traumort'),
(1432, 321, 6, 'DEU'),
(1433, 321, 7, '0994-2255773'),
(1434, 321, 8, '0289-457412'),
(1435, 321, 10, '2000-08-30'),
(1436, 321, 11, '2'),
(1437, 321, 12, 'heuer.dora@example.com'),
(1439, 322, 1, 'Heyn'),
(1440, 322, 2, 'Doreen'),
(1441, 322, 3, 'Blumenwiese 143'),
(1442, 322, 4, '75357'),
(1443, 322, 5, 'Traumort'),
(1444, 322, 6, 'DEU'),
(1445, 322, 7, '0995-2255773'),
(1446, 322, 8, '0290-457412'),
(1447, 322, 10, '1972-09-01'),
(1448, 322, 11, '2'),
(1449, 322, 12, 'heyn.doreen@example.com'),
(1451, 323, 1, 'Hildenbrandt'),
(1452, 323, 2, 'Doris'),
(1453, 323, 3, 'Blumenwiese 144'),
(1454, 323, 4, '75357'),
(1455, 323, 5, 'Traumort'),
(1456, 323, 6, 'DEU'),
(1457, 323, 7, '0996-2255773'),
(1458, 323, 8, '0291-457412'),
(1459, 323, 10, '1971-09-04'),
(1460, 323, 11, '2'),
(1461, 323, 12, 'hildenbrandt.doris@example.com'),
(1463, 324, 1, 'Hinsch'),
(1464, 324, 2, 'Dorothea'),
(1465, 324, 3, 'Blumenwiese 145'),
(1466, 324, 4, '75357'),
(1467, 324, 5, 'Traumort'),
(1468, 324, 6, 'DEU'),
(1469, 324, 7, '0997-2255773'),
(1470, 324, 8, '0292-457412'),
(1471, 324, 10, '1979-09-06'),
(1472, 324, 11, '2'),
(1473, 324, 12, 'hinsch.dorothea@example.com'),
(1475, 325, 1, 'Hochbruck'),
(1476, 325, 2, 'Dorothy'),
(1477, 325, 3, 'Blumenwiese 146'),
(1478, 325, 4, '75357'),
(1479, 325, 5, 'Traumort'),
(1480, 325, 6, 'DEU'),
(1481, 325, 7, '0998-2255773'),
(1482, 325, 8, '0293-457412'),
(1483, 325, 10, '1973-09-08'),
(1484, 325, 11, '2'),
(1485, 325, 12, 'hochbruck.dorothy@example.com'),
(1487, 326, 1, 'Hoff'),
(1488, 326, 2, 'Dorthy'),
(1489, 326, 3, 'Blumenwiese 147'),
(1490, 326, 4, '75357'),
(1491, 326, 5, 'Traumort'),
(1492, 326, 6, 'DEU'),
(1493, 326, 7, '0999-2255773'),
(1494, 326, 8, '0294-457412'),
(1495, 326, 10, '1985-09-10'),
(1496, 326, 11, '2'),
(1497, 326, 12, 'hoff.dorthy@example.com'),
(1499, 327, 1, 'Holst'),
(1500, 327, 2, 'Doug'),
(1501, 327, 3, 'Blumenwiese 148'),
(1502, 327, 4, '75357'),
(1503, 327, 5, 'Traumort'),
(1504, 327, 6, 'DEU'),
(1505, 327, 7, '1000-2255773'),
(1506, 327, 8, '0295-457412'),
(1507, 327, 10, '1980-09-11'),
(1508, 327, 11, '1'),
(1509, 327, 12, 'holstholz.doug@example.com'),
(1511, 328, 1, 'Homann'),
(1512, 328, 2, 'Douglas'),
(1513, 328, 3, 'Blumenwiese 149'),
(1514, 328, 4, '75357'),
(1515, 328, 5, 'Traumort'),
(1516, 328, 6, 'DEU'),
(1517, 328, 7, '1001-2255773'),
(1518, 328, 8, '0296-457412'),
(1519, 328, 10, '1992-09-13'),
(1520, 328, 11, '1'),
(1521, 328, 12, 'hohmann.douglas@example.com'),
(1523, 329, 1, 'Hoopt'),
(1524, 329, 2, 'Doyle'),
(1525, 329, 3, 'Blumenwiese 150'),
(1526, 329, 4, '75357'),
(1527, 329, 5, 'Traumort'),
(1528, 329, 6, 'DEU'),
(1529, 329, 7, '1002-2255773'),
(1530, 329, 8, '0297-457412'),
(1531, 329, 10, '1969-09-16'),
(1532, 329, 11, '2'),
(1533, 329, 12, 'hoopt.doyle@example.com'),
(1535, 330, 1, 'Hübenbecker'),
(1536, 330, 2, 'Drake'),
(1537, 330, 3, 'Blumenwiese 151'),
(1538, 330, 4, '75357'),
(1539, 330, 5, 'Traumort'),
(1540, 330, 6, 'DEU'),
(1541, 330, 7, '1003-2255773'),
(1542, 330, 8, '0298-457412'),
(1543, 330, 10, '1990-09-18'),
(1544, 330, 11, '1'),
(1546, 331, 1, 'Hüttmann'),
(1547, 331, 2, 'Drew'),
(1548, 331, 3, 'Blumenwiese 152'),
(1549, 331, 4, '75357'),
(1550, 331, 5, 'Traumort'),
(1551, 331, 6, 'DEU'),
(1552, 331, 7, '1004-2255773'),
(1553, 331, 8, '0299-457412'),
(1554, 331, 10, '1973-09-20'),
(1555, 331, 11, '1'),
(1557, 332, 1, 'Ilse'),
(1558, 332, 2, 'Drutmar'),
(1559, 332, 3, 'Blumenwiese 153'),
(1560, 332, 4, '75357'),
(1561, 332, 5, 'Traumort'),
(1562, 332, 6, 'DEU'),
(1563, 332, 7, '1005-2255773'),
(1564, 332, 8, '0300-457412'),
(1565, 332, 10, '1981-09-22'),
(1566, 332, 11, '2'),
(1567, 332, 12, 'ilse.drutmar@example.com'),
(1569, 333, 1, 'Jachs'),
(1570, 333, 2, 'Duane'),
(1571, 333, 3, 'Blumenwiese 154'),
(1572, 333, 4, '75357'),
(1573, 333, 5, 'Traumort'),
(1574, 333, 6, 'DEU'),
(1575, 333, 7, '1006-2255773'),
(1576, 333, 8, '0301-457412'),
(1577, 333, 10, '1992-09-23'),
(1578, 333, 11, '2'),
(1579, 333, 12, 'jachs.duane@example.com'),
(1581, 334, 1, 'Jacobsen'),
(1582, 334, 2, 'Dustin'),
(1583, 334, 3, 'Blumenwiese 155'),
(1584, 334, 4, '75357'),
(1585, 334, 5, 'Traumort'),
(1586, 334, 6, 'DEU'),
(1587, 334, 7, '1007-2255773'),
(1588, 334, 8, '0302-457412'),
(1589, 334, 10, '2002-09-26'),
(1590, 334, 11, '1'),
(1591, 334, 12, 'jacobsen.dustin@example.com'),
(1593, 335, 1, 'Jagelowicz'),
(1594, 335, 2, 'Dwayne'),
(1595, 335, 3, 'Blumenwiese 156'),
(1596, 335, 4, '75357'),
(1597, 335, 5, 'Traumort'),
(1598, 335, 6, 'DEU'),
(1599, 335, 7, '1008-2255773'),
(1600, 335, 8, '0303-457412'),
(1601, 335, 10, '1966-09-28'),
(1602, 335, 11, '1'),
(1603, 335, 12, 'jagelowicz.dwayne@example.com'),
(1605, 336, 1, 'Jens'),
(1606, 336, 2, 'Dwight'),
(1607, 336, 3, 'Blumenwiese 157'),
(1608, 336, 4, '75357'),
(1609, 336, 5, 'Traumort'),
(1610, 336, 6, 'DEU'),
(1611, 336, 7, '1009-2255773'),
(1612, 336, 8, '0304-457412'),
(1613, 336, 10, '1998-09-30'),
(1614, 336, 11, '1'),
(1615, 336, 12, 'jens.dwight@example.com'),
(1617, 337, 1, 'Jenschke'),
(1618, 337, 2, 'Dylan'),
(1619, 337, 3, 'Blumenwiese 158'),
(1620, 337, 4, '75357'),
(1621, 337, 5, 'Traumort'),
(1622, 337, 6, 'DEU'),
(1623, 337, 7, '1010-2255773'),
(1624, 337, 8, '0305-457412'),
(1625, 337, 10, '2010-10-02'),
(1626, 337, 11, '1'),
(1628, 338, 1, 'Jensen'),
(1629, 338, 2, 'Reba'),
(1630, 338, 3, 'Blumenwiese 159'),
(1631, 338, 4, '75357'),
(1632, 338, 5, 'Traumort'),
(1633, 338, 6, 'DEU'),
(1634, 338, 7, '1011-2255773'),
(1635, 338, 8, '0306-457412'),
(1636, 338, 10, '1964-10-03'),
(1637, 338, 11, '2'),
(1638, 338, 12, 'jensen.reba@example.com'),
(1640, 339, 1, 'Jessulat'),
(1641, 339, 2, 'Rebeca'),
(1642, 339, 3, 'Blumenwiese 160'),
(1643, 339, 4, '75357'),
(1644, 339, 5, 'Traumort'),
(1645, 339, 6, 'DEU'),
(1646, 339, 7, '1012-2255773'),
(1647, 339, 8, '0307-457412'),
(1648, 339, 10, '2008-10-05'),
(1649, 339, 11, '2'),
(1650, 339, 12, 'jessulat.rebeca@example.com'),
(1652, 340, 1, 'Jürs'),
(1653, 340, 2, 'Rebecca'),
(1654, 340, 3, 'Blumenwiese 161'),
(1655, 340, 4, '75357'),
(1656, 340, 5, 'Traumort'),
(1657, 340, 6, 'DEU'),
(1658, 340, 7, '1013-2255773'),
(1659, 340, 8, '0308-457412'),
(1660, 340, 10, '2015-10-08'),
(1661, 340, 11, '2'),
(1663, 341, 1, 'Juret'),
(1664, 341, 2, 'Rebekah'),
(1665, 341, 3, 'Blumenwiese 162'),
(1666, 341, 4, '75357'),
(1667, 341, 5, 'Traumort'),
(1668, 341, 6, 'DEU'),
(1669, 341, 7, '1014-2255773'),
(1670, 341, 8, '0309-457412'),
(1671, 341, 10, '2015-10-10'),
(1672, 341, 11, '2'),
(1673, 341, 12, 'juret.rebekah@example.com'),
(1675, 342, 1, 'Kähler'),
(1676, 342, 2, 'Rebekka'),
(1677, 342, 3, 'Blumenwiese 163'),
(1678, 342, 4, '75357'),
(1679, 342, 5, 'Traumort'),
(1680, 342, 6, 'DEU'),
(1681, 342, 7, '1015-2255773'),
(1682, 342, 8, '0310-457412'),
(1683, 342, 10, '1970-10-12'),
(1684, 342, 11, '2'),
(1686, 343, 1, 'Kälberer'),
(1687, 343, 2, 'Regan'),
(1688, 343, 3, 'Blumenwiese 164'),
(1689, 343, 4, '75357'),
(1690, 343, 5, 'Traumort'),
(1691, 343, 6, 'DEU'),
(1692, 343, 7, '1016-2255773'),
(1693, 343, 8, '0311-457412'),
(1694, 343, 10, '2001-10-14'),
(1695, 343, 11, '1'),
(1697, 344, 1, 'Kahns'),
(1698, 344, 2, 'Regina'),
(1699, 344, 3, 'Blumenwiese 165'),
(1700, 344, 4, '75357'),
(1701, 344, 5, 'Traumort'),
(1702, 344, 6, 'DEU'),
(1703, 344, 7, '1017-2255773'),
(1704, 344, 8, '0312-457412'),
(1705, 344, 10, '1976-10-15'),
(1706, 344, 11, '2'),
(1707, 344, 12, 'kahns.regina@example.com'),
(1709, 345, 1, 'Kamm'),
(1710, 345, 2, 'Reginald'),
(1711, 345, 3, 'Blumenwiese 166'),
(1712, 345, 4, '75357'),
(1713, 345, 5, 'Traumort'),
(1714, 345, 6, 'DEU'),
(1715, 345, 7, '1018-2255773'),
(1716, 345, 8, '0313-457412'),
(1717, 345, 10, '1960-10-17'),
(1718, 345, 11, '1'),
(1719, 345, 12, 'kamm.reginald@example.com'),
(1721, 346, 1, 'Kassebaum'),
(1722, 346, 2, 'Reginbald'),
(1723, 346, 3, 'Blumenwiese 167'),
(1724, 346, 4, '75357'),
(1725, 346, 5, 'Traumort'),
(1726, 346, 6, 'DEU'),
(1727, 346, 7, '1019-2255773'),
(1728, 346, 8, '0314-457412'),
(1729, 346, 10, '1962-10-20'),
(1730, 346, 11, '1'),
(1731, 346, 12, 'kassebaum.reginbald@example.com'),
(1733, 347, 1, 'Kaths'),
(1734, 347, 2, 'Regine'),
(1735, 347, 3, 'Blumenwiese 168'),
(1736, 347, 4, '75357'),
(1737, 347, 5, 'Traumort'),
(1738, 347, 6, 'DEU'),
(1739, 347, 7, '1020-2255773'),
(1740, 347, 8, '0315-457412'),
(1741, 347, 10, '1991-10-22'),
(1742, 347, 11, '2'),
(1743, 347, 12, 'kaths.regine@example.com'),
(1745, 348, 1, 'Kauffmann'),
(1746, 348, 2, 'Reginhard'),
(1747, 348, 3, 'Blumenwiese 169'),
(1748, 348, 4, '75357'),
(1749, 348, 5, 'Traumort'),
(1750, 348, 6, 'DEU'),
(1751, 348, 7, '1021-2255773'),
(1752, 348, 8, '0316-457412'),
(1753, 348, 10, '1993-10-24'),
(1754, 348, 11, '1'),
(1755, 348, 12, 'kauffmann.reginhard@example.com'),
(1757, 349, 1, 'Kerl'),
(1758, 349, 2, 'Reginlind'),
(1759, 349, 3, 'Blumenwiese 170'),
(1760, 349, 4, '75357'),
(1761, 349, 5, 'Traumort'),
(1762, 349, 6, 'DEU'),
(1763, 349, 7, '1022-2255773'),
(1764, 349, 8, '0317-457412'),
(1765, 349, 10, '2000-10-25'),
(1766, 349, 11, '2'),
(1767, 349, 12, 'kerl.reginlind@example.com'),
(1769, 350, 1, 'Keßler'),
(1770, 350, 2, 'Reilly'),
(1771, 350, 3, 'Blumenwiese 171'),
(1772, 350, 4, '44665'),
(1773, 350, 5, 'Möllerhausen'),
(1774, 350, 6, 'DEU'),
(1775, 350, 7, '1023-2255773'),
(1776, 350, 8, '0318-457412'),
(1777, 350, 10, '1997-10-28'),
(1778, 350, 11, '2'),
(1780, 351, 1, 'Demo'),
(1781, 351, 2, 'User'),
(1782, 351, 3, 'Unter den Linden 12'),
(1783, 351, 4, '10117'),
(1784, 351, 5, 'Berlin'),
(1785, 351, 6, 'DEU'),
(1786, 351, 7, '030-85858585'),
(1787, 351, 10, '2004-10-29'),
(1788, 351, 11, '1'),
(1789, 351, 12, 'demo@admidio.org'),
(1790, 351, 13, 'https://www.admidio.org/demo'),
(1792, 351, 20, '4711'),
(1793, 352, 1, 'Suppenkasper'),
(1794, 352, 2, 'Walther'),
(1795, 352, 3, 'Kurfürstendamm 21'),
(1796, 352, 4, '10707'),
(1797, 352, 5, 'Berlin'),
(1798, 352, 6, 'DEU'),
(1799, 352, 10, '1968-10-31'),
(1800, 352, 11, '1'),
(1801, 352, 12, 'kasper@example.com'),
(1802, 352, 20, '025789'),
(1803, 352, 21, 'grün'),
(1804, 353, 1, 'Groth'),
(1805, 353, 2, 'Dina'),
(1806, 353, 6, 'DEU'),
(1807, 353, 11, '2'),
(1808, 353, 12, 'dina@example.com'),
(1809, 354, 1, 'Chairman'),
(1810, 354, 2, 'Eric'),
(1811, 354, 6, 'DEU'),
(1812, 354, 11, '1'),
(1813, 354, 12, 'Chairman@example.com'),
(1814, 355, 1, 'Schmidt'),
(1815, 355, 2, 'Jennifer'),
(1816, 355, 3, 'Unter den Linden 45'),
(1817, 355, 4, '10117'),
(1818, 355, 5, 'Berlin'),
(1819, 355, 6, 'DEU'),
(1820, 355, 11, '2'),
(1821, 355, 12, 'Member@example.com'),
(1824, 355, 14, '1'),
(1900, 356, 1, 'Schmidt'),
(1901, 356, 2, 'Silke'),
(1902, 356, 3, 'Unter den Linden 45'),
(1903, 356, 4, '10117'),
(1904, 356, 5, 'Berlin'),
(1905, 356, 6, 'DEU'),
(1906, 356, 10, '1969-11-03'),
(1907, 356, 11, '2'),
(1910, 357, 1, 'Schmidt'),
(1911, 357, 2, 'Stefan'),
(1912, 357, 3, 'Unter den Linden 45'),
(1913, 357, 4, '10117'),
(1914, 357, 5, 'Berlin'),
(1915, 357, 6, 'DEU'),
(1916, 357, 10, '2012-11-04'),
(1917, 357, 11, '1'),
(2000, 358, 1, 'Suppenkasper'),
(2001, 358, 2, 'Walter'),
(2002, 358, 3, 'Heinrichsallee 254'),
(2003, 358, 4, '10118'),
(2004, 358, 5, 'Berlin'),
(2005, 358, 6, 'DEU'),
(2100, 359, 1, 'Bakers'),
(2101, 359, 2, 'Colin'),
(2102, 359, 3, 'Wallingtonstreet 73'),
(2103, 359, 4, '3845'),
(2104, 359, 5, 'Birmingham'),
(2105, 359, 6, 'GBR'),
(2106, 359, 12, 'c.bakers@example.com'),
(2107, 359, 14, '1'),
(2200, 360, 1, 'Smith'),
(2201, 360, 2, 'Barbara'),
(2202, 360, 3, 'Mainstreet 876'),
(2203, 360, 4, '573545'),
(2204, 360, 5, 'Boston'),
(2205, 360, 6, 'USA'),
(2206, 360, 12, 'bab.smith@example.com'),
(2207, 360, 14, '1'),
(13000, 1, 22, 'Admidio'),
(13001, 1, 26, 'AdmidioApp'),
(13002, 1, 28, '@admidio'),
(13004, 1, 14, '1'),
(13100, 2, 1, 'System'),
(18011, 352, 14, '1'),
(18081, 353, 14, '1'),
(18131, 354, 22, 'Admidio'),
(18133, 354, 14, '1'),
(18134, 354, 26, 'AdmidioApp'),
(18135, 354, 28, '@admidio'),
(18136, 355, 10, '1994-02-09'),
(18137, 355, 21, 'red'),
(18138, 218, 21, 'green');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_user_fields`
--

CREATE TABLE `%PREFIX%_user_fields` (
  `usf_id` int UNSIGNED NOT NULL,
  `usf_cat_id` int UNSIGNED NOT NULL,
  `usf_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `usf_type` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `usf_name_intern` varchar(110) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `usf_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `usf_description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `usf_default_value` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `usf_regex` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `usf_icon` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `usf_url` varchar(2000) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `usf_system` tinyint(1) NOT NULL DEFAULT '0',
  `usf_disabled` tinyint(1) NOT NULL DEFAULT '0',
  `usf_hidden` tinyint(1) NOT NULL DEFAULT '0',
  `usf_registration` tinyint(1) NOT NULL DEFAULT '0',
  `usf_required_input` smallint NOT NULL DEFAULT '0',
  `usf_sequence` smallint NOT NULL,
  `usf_usr_id_create` int UNSIGNED DEFAULT NULL,
  `usf_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usf_usr_id_change` int UNSIGNED DEFAULT NULL,
  `usf_timestamp_change` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_user_fields`
--

INSERT INTO `%PREFIX%_user_fields` (`usf_id`, `usf_cat_id`, `usf_uuid`, `usf_type`, `usf_name_intern`, `usf_name`, `usf_description`, `usf_default_value`, `usf_regex`, `usf_icon`, `usf_url`, `usf_system`, `usf_disabled`, `usf_hidden`, `usf_registration`, `usf_required_input`, `usf_sequence`, `usf_usr_id_create`, `usf_timestamp_create`, `usf_usr_id_change`, `usf_timestamp_change`) VALUES
(1, 1, '8bd39525-1bb0-4306-85b0-a08c7c71faad', 'TEXT', 'LAST_NAME', 'SYS_LASTNAME', NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 1, 1, 1, 1, '2012-01-08 10:12:05', NULL, NULL),
(2, 1, '424592e0-5abc-4abe-ab18-c4088cfb17fa', 'TEXT', 'FIRST_NAME', 'SYS_FIRSTNAME', NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 1, 1, 2, 1, '2012-01-08 10:12:05', NULL, NULL),
(3, 1, '34c57527-f0e6-426f-9ff7-4cf51c5b0238', 'TEXT', 'STREET', 'SYS_STREET', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 1, 0, 3, 1, '2012-01-08 10:12:05', NULL, NULL),
(4, 1, '4d07edd9-44c0-4c85-9f66-1a521447fb74', 'TEXT', 'POSTCODE', 'SYS_POSTCODE', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 1, 0, 4, 1, '2012-01-08 10:12:05', NULL, NULL),
(5, 1, '9b0b5f84-7d18-4df0-91ea-7eaca46cbfc4', 'TEXT', 'CITY', 'SYS_CITY', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 1, 0, 5, 1, '2012-01-08 10:12:05', NULL, NULL),
(6, 1, '75dcb582-231f-4b24-81fc-5cdce79a3069', 'TEXT', 'COUNTRY', 'SYS_COUNTRY', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 1, 0, 6, 1, '2012-01-08 10:12:05', NULL, NULL),
(7, 1, '88c73af3-dea9-4d5c-b8d3-b2c743da5b14', 'PHONE', 'PHONE', 'SYS_PHONE', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 1, 0, 7, 1, '2012-01-08 10:12:05', NULL, NULL),
(8, 1, '6d85e436-4edd-4d7d-b9d7-df17ea4de1fb', 'PHONE', 'MOBILE', 'SYS_MOBILE', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 1, 0, 8, 1, '2012-01-08 10:12:05', NULL, NULL),
(10, 1, 'f3dca1e4-d439-4501-967e-e87545060b03', 'DATE', 'BIRTHDAY', 'SYS_BIRTHDAY', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 1, 0, 10, 1, '2012-01-08 10:12:05', NULL, NULL),
(11, 1, '944252e6-7275-42bd-9d7a-03ff294080fb', 'RADIO_BUTTON', 'GENDER', 'SYS_GENDER', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 1, 0, 11, 1, '2012-01-08 10:12:05', NULL, NULL),
(12, 1, '09556bd3-0bc5-4e97-800a-4ed347f6327e', 'EMAIL', 'EMAIL', 'SYS_EMAIL', NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 1, 2, 12, 1, '2012-01-08 10:12:05', NULL, NULL),
(13, 1, '627c57a6-f17b-44df-9d31-3d668634eb97', 'URL', 'WEBSITE', 'SYS_WEBSITE', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 1, 0, 13, 1, '2012-01-08 10:12:05', NULL, NULL),
(14, 1, 'd1b9314d-953c-4198-8250-d10f4661abe7', 'CHECKBOX', 'DATA_PROTECTION_PERMISSION', 'SYS_DATA_PROTECTION_PERMISSION', 'I have read the information provided by the organization. As far as the personal data provided by me are not necessary data for the fulfillment of the contract, I declare my consent to the processing of these data. My data will be stored exclusively for the purposes of the organization and treated confidentially.', NULL, NULL, NULL, NULL, 0, 0, 0, 1, 2, 14, 1, '2012-01-08 10:12:05', NULL, NULL),
(20, 8, '89b33bc0-913a-404c-9899-e53ad5080fec', 'NUMBER', 'MEMBERSHIP_NUMBER', 'Membership number', NULL, NULL, NULL, NULL, NULL, 0, 1, 0, 0, 0, 1, 1, '2011-04-06 20:05:20', NULL, NULL),
(21, 8, '15b324bc-29d8-4b79-bee9-10072b8d7489', 'TEXT', 'FAVORITE_COLOR', 'Favorite color', 'Any member may enter his favorite color', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 2, 1, '2011-04-06 20:05:20', NULL, NULL),
(22, 2, '041f1bb5-4305-47d7-8538-c1e7163339a6', 'TEXT', 'FACEBOOK', 'SYS_FACEBOOK', 'Would you like to set a link to your profile of this social network? Your login name is required. Log in to your account of the social network and go to your profile. Now copy the URL in this field and save your profile here. Visitors of your profile are now able to open your profile directly.', NULL, NULL, 'facebook', 'https://www.facebook.com/#user_content#', 0, 0, 0, 0, 0, 1, 1, '2012-01-08 10:11:40', NULL, NULL),
(24, 2, '1b2045a6-bae3-4948-91fa-f0e669c488b4', 'TEXT', 'XING', 'SYS_XING', 'Would you like to set a link to your profile of this social network? Your login name is required. Log in to your account of the social network and go to your profile. Now copy the URL in this field and save your profile here. Visitors of your profile are now able to open your profile directly.', NULL, NULL, NULL, 'https://www.xing.com/profile/#user_content#', 0, 0, 0, 0, 0, 6, 1, '2012-01-08 10:11:40', NULL, NULL),
(26, 2, '1b204526-bae3-4948-91fa-f0e669c48826', 'TEXT', 'INSTAGRAM', 'SYS_INSTAGRAM', 'Would you like to set a link to your profile of this social network? Your login name is required. Log in to your account of the social network and go to your profile. Now copy the URL in this field and save your profile here. Visitors of your profile are now able to open your profile directly.', NULL, NULL, 'instagram', 'https://www.instagram.com/#user_content#', 0, 0, 0, 0, 0, 2, 1, '2012-01-08 10:11:40', NULL, NULL),
(27, 2, '1b204527-bae3-4948-91fa-f0e669c48827', 'TEXT', 'LINKEDIN', 'SYS_LINKEDIN', 'Would you like to set a link to your profile of this social network? Your login name is required. Log in to your account of the social network and go to your profile. Now copy the URL in this field and save your profile here. Visitors of your profile are now able to open your profile directly.', NULL, NULL, 'linkedin', 'https://www.linkedin.com/in/#user_content#', 0, 0, 0, 0, 0, 3, 1, '2012-01-08 10:11:40', NULL, NULL),
(28, 2, '1b204528-bae3-4948-91fa-f0e669c48828', 'TEXT', 'MASTODON', 'SYS_MASTODON', 'Would you like to set a link to your profile of this social network? Your login name is required. Log in to your account of the social network and go to your profile. Now copy the URL in this field and save your profile here. Visitors of your profile are now able to open your profile directly.', NULL, NULL, 'mastodon', 'https://mastodon.social/#user_content#', 0, 0, 0, 0, 0, 4, 1, '2012-01-08 10:11:40', NULL, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_user_field_select_options`
--

CREATE TABLE `%PREFIX%_user_field_select_options` (
  `ufo_id` int UNSIGNED NOT NULL,
  `ufo_usf_id` int UNSIGNED NOT NULL,
  `ufo_value` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ufo_system` tinyint(1) NOT NULL DEFAULT '0',
  `ufo_sequence` smallint NOT NULL,
  `ufo_obsolete` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_user_field_select_options`
--

INSERT INTO `%PREFIX%_user_field_select_options` (`ufo_id`, `ufo_usf_id`, `ufo_value`, `ufo_system`, `ufo_sequence`, `ufo_obsolete`) VALUES
(1, 11, 'gender-male|Männlich', 0, 1, 0),
(2, 11, 'gender-female|Weiblich', 0, 2, 0),
(3, 11, 'gender-trans|Divers', 0, 3, 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_user_relations`
--

CREATE TABLE `%PREFIX%_user_relations` (
  `ure_id` int UNSIGNED NOT NULL,
  `ure_urt_id` int UNSIGNED NOT NULL,
  `ure_usr_id1` int UNSIGNED NOT NULL,
  `ure_usr_id2` int UNSIGNED NOT NULL,
  `ure_usr_id_create` int UNSIGNED DEFAULT NULL,
  `ure_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ure_usr_id_change` int UNSIGNED DEFAULT NULL,
  `ure_timestamp_change` timestamp NULL DEFAULT NULL,
  `ure_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_user_relations`
--

INSERT INTO `%PREFIX%_user_relations` (`ure_id`, `ure_urt_id`, `ure_usr_id1`, `ure_usr_id2`, `ure_usr_id_create`, `ure_timestamp_create`, `ure_usr_id_change`, `ure_timestamp_change`, `ure_uuid`) VALUES
(1, 4, 1, 355, 1, '2016-12-06 19:02:09', NULL, NULL, 'a88952e5-77d8-4503-87b2-5f5251aca5b2'),
(2, 4, 355, 1, 1, '2016-12-06 19:02:09', NULL, NULL, 'e4aa38fa-ba34-4bf6-aec1-cc5daa4d1cef'),
(3, 2, 1, 356, 1, '2016-12-06 19:02:23', NULL, NULL, 'f08fc1a9-27e2-4f21-a3f7-099d566d2503'),
(4, 1, 356, 1, 1, '2016-12-06 19:02:23', NULL, NULL, 'b951c1db-4057-44ff-887b-8f31e33e24d9'),
(5, 2, 1, 357, 1, '2016-12-06 19:02:35', NULL, NULL, '01613e4d-3863-4c50-ab77-88da663ed4f4'),
(6, 1, 357, 1, 1, '2016-12-06 19:02:35', NULL, NULL, 'a02e52c8-68e4-4a73-ad86-37c54de89715'),
(7, 1, 357, 355, 1, '2016-12-06 19:02:58', NULL, NULL, '7b3649e4-2424-4467-ad55-1653d7f6e828'),
(8, 2, 355, 357, 1, '2016-12-06 19:02:58', NULL, NULL, '39a5005d-231b-42c4-ad5c-8b1cf511c112'),
(9, 3, 357, 356, 1, '2016-12-06 19:03:15', NULL, NULL, '0e6157a2-d591-484d-8de4-2cd22c60030d'),
(10, 3, 356, 357, 1, '2016-12-06 19:03:15', NULL, NULL, '0fb5ea2f-9d3a-425c-8f8e-da6be8e45073'),
(11, 2, 355, 356, 1, '2016-12-06 19:04:32', NULL, NULL, 'fa04f9d2-3af1-43bc-8b43-01da6c95b486'),
(12, 1, 356, 355, 1, '2016-12-06 19:04:32', NULL, NULL, 'bf1a4557-b0a7-414b-bfa7-89f1ab00bea8');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `%PREFIX%_user_relation_types`
--

CREATE TABLE `%PREFIX%_user_relation_types` (
  `urt_id` int UNSIGNED NOT NULL,
  `urt_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `urt_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `urt_name_male` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `urt_name_female` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `urt_edit_user` tinyint(1) NOT NULL DEFAULT '0',
  `urt_id_inverse` int UNSIGNED DEFAULT NULL,
  `urt_usr_id_create` int UNSIGNED DEFAULT NULL,
  `urt_timestamp_create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `urt_usr_id_change` int UNSIGNED DEFAULT NULL,
  `urt_timestamp_change` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Daten für Tabelle `%PREFIX%_user_relation_types`
--

INSERT INTO `%PREFIX%_user_relation_types` (`urt_id`, `urt_uuid`, `urt_name`, `urt_name_male`, `urt_name_female`, `urt_edit_user`, `urt_id_inverse`, `urt_usr_id_create`, `urt_timestamp_create`, `urt_usr_id_change`, `urt_timestamp_change`) VALUES
(1, '3f716ce8-f05e-4eb9-a89e-20a8a277aa18', 'SYS_PARENT', 'SYS_FATHER', 'SYS_MOTHER', 0, 2, 2, '2016-11-22 04:29:56', NULL, NULL),
(2, 'e728bdd5-cc1f-46a3-aef3-a3a12cc8aad6', 'SYS_CHILD', 'SYS_SON', 'SYS_DAUGHTER', 0, 1, 2, '2016-11-22 04:29:56', NULL, NULL),
(3, '7d31dd74-77a4-461d-9402-37ae8fe2cfcf', 'SYS_SIBLING', 'SYS_BROTHER', 'SYS_SISTER', 0, 3, 2, '2016-11-22 04:29:56', NULL, NULL),
(4, '91b3c6a0-2559-4e7a-bfcc-4f47e56efd35', 'SYS_SPOUSE', 'SYS_HUSBAND', 'SYS_WIFE', 0, 4, 2, '2016-11-22 04:29:56', NULL, NULL),
(5, 'be687ee3-bd7d-499e-b388-b0387fb89389', 'SYS_COHABITANT', 'SYS_COHABITANT_MALE', 'SYS_COHABITANT_FEMALE', 0, 5, 2, '2016-11-22 04:29:56', NULL, NULL),
(6, '7a7acc74-7c9f-404f-bd74-abe7482c4126', 'SYS_COMPANION', 'SYS_BOYFRIEND', 'SYS_GIRLFRIEND', 0, 6, 2, '2016-11-22 04:29:56', NULL, NULL),
(7, '7169f73a-49a9-4ce3-981a-102c6eb2c3c9', 'SYS_SUPERIOR', 'SYS_SUPERIOR_MALE', 'SYS_SUPERIOR_FEMALE', 0, 8, 2, '2016-11-22 04:29:56', NULL, NULL),
(8, 'a7ab66fb-67c1-4828-aeda-dc31d09050dc', 'SYS_SUBORDINATE', 'SYS_SUBORDINATE_MALE', 'SYS_SUBORDINATE_FEMALE', 0, 7, 2, '2016-11-22 04:29:56', NULL, NULL);

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `%PREFIX%_announcements`
--
ALTER TABLE `%PREFIX%_announcements`
  ADD PRIMARY KEY (`ann_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_ann_uuid` (`ann_uuid`),
  ADD KEY `%PREFIX%_fk_ann_cat` (`ann_cat_id`),
  ADD KEY `%PREFIX%_fk_ann_usr_create` (`ann_usr_id_create`),
  ADD KEY `%PREFIX%_fk_ann_usr_change` (`ann_usr_id_change`);

--
-- Indizes für die Tabelle `%PREFIX%_auto_login`
--
ALTER TABLE `%PREFIX%_auto_login`
  ADD PRIMARY KEY (`atl_id`),
  ADD KEY `%PREFIX%_fk_atl_usr` (`atl_usr_id`),
  ADD KEY `%PREFIX%_fk_atl_org` (`atl_org_id`);

--
-- Indizes für die Tabelle `%PREFIX%_categories`
--
ALTER TABLE `%PREFIX%_categories`
  ADD PRIMARY KEY (`cat_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_cat_uuid` (`cat_uuid`),
  ADD KEY `%PREFIX%_fk_cat_org` (`cat_org_id`),
  ADD KEY `%PREFIX%_fk_cat_usr_create` (`cat_usr_id_create`),
  ADD KEY `%PREFIX%_fk_cat_usr_change` (`cat_usr_id_change`);

--
-- Indizes für die Tabelle `%PREFIX%_category_report`
--
ALTER TABLE `%PREFIX%_category_report`
  ADD PRIMARY KEY (`crt_id`),
  ADD KEY `%PREFIX%_fk_crt_org` (`crt_org_id`);

--
-- Indizes für die Tabelle `%PREFIX%_components`
--
ALTER TABLE `%PREFIX%_components`
  ADD PRIMARY KEY (`com_id`);

--
-- Indizes für die Tabelle `%PREFIX%_events`
--
ALTER TABLE `%PREFIX%_events`
  ADD PRIMARY KEY (`dat_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_dat_uuid` (`dat_uuid`),
  ADD KEY `%PREFIX%_fk_dat_cat` (`dat_cat_id`),
  ADD KEY `%PREFIX%_fk_dat_rol` (`dat_rol_id`),
  ADD KEY `%PREFIX%_fk_dat_room` (`dat_room_id`),
  ADD KEY `%PREFIX%_fk_dat_usr_create` (`dat_usr_id_create`),
  ADD KEY `%PREFIX%_fk_dat_usr_change` (`dat_usr_id_change`);

--
-- Indizes für die Tabelle `%PREFIX%_files`
--
ALTER TABLE `%PREFIX%_files`
  ADD PRIMARY KEY (`fil_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_fil_uuid` (`fil_uuid`),
  ADD KEY `%PREFIX%_fk_fil_fol` (`fil_fol_id`),
  ADD KEY `%PREFIX%_fk_fil_usr` (`fil_usr_id`);

--
-- Indizes für die Tabelle `%PREFIX%_folders`
--
ALTER TABLE `%PREFIX%_folders`
  ADD PRIMARY KEY (`fol_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_fol_uuid` (`fol_uuid`),
  ADD KEY `%PREFIX%_fk_fol_org` (`fol_org_id`),
  ADD KEY `%PREFIX%_fk_fol_fol_parent` (`fol_fol_id_parent`),
  ADD KEY `%PREFIX%_fk_fol_usr` (`fol_usr_id`);

--
-- Indizes für die Tabelle `%PREFIX%_forum_posts`
--
ALTER TABLE `%PREFIX%_forum_posts`
  ADD PRIMARY KEY (`fop_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_fop_uuid` (`fop_uuid`),
  ADD KEY `%PREFIX%_fk_fop_fot` (`fop_fot_id`),
  ADD KEY `%PREFIX%_fk_fop_usr_create` (`fop_usr_id_create`),
  ADD KEY `%PREFIX%_fk_fop_usr_change` (`fop_usr_id_change`);

--
-- Indizes für die Tabelle `%PREFIX%_forum_topics`
--
ALTER TABLE `%PREFIX%_forum_topics`
  ADD PRIMARY KEY (`fot_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_fot_uuid` (`fot_uuid`),
  ADD KEY `%PREFIX%_fk_fot_cat` (`fot_cat_id`),
  ADD KEY `%PREFIX%_fk_fot_first_fop` (`fot_fop_id_first_post`),
  ADD KEY `%PREFIX%_fk_fot_usr_create` (`fot_usr_id_create`);

--
-- Indizes für die Tabelle `%PREFIX%_ids`
--
ALTER TABLE `%PREFIX%_ids`
  ADD KEY `%PREFIX%_fk_ids_usr_id` (`ids_usr_id`);

--
-- Indizes für die Tabelle `%PREFIX%_inventory_fields`
--
ALTER TABLE `%PREFIX%_inventory_fields`
  ADD PRIMARY KEY (`inf_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_inf_name_intern` (`inf_org_id`,`inf_name_intern`),
  ADD UNIQUE KEY `%PREFIX%_idx_inf_uuid` (`inf_uuid`),
  ADD KEY `%PREFIX%_fk_inf_usr_create` (`inf_usr_id_create`),
  ADD KEY `%PREFIX%_fk_inf_usr_change` (`inf_usr_id_change`);

--
-- Indizes für die Tabelle `%PREFIX%_inventory_field_select_options`
--
ALTER TABLE `%PREFIX%_inventory_field_select_options`
  ADD PRIMARY KEY (`ifo_id`),
  ADD KEY `%PREFIX%_fk_ifo_inf` (`ifo_inf_id`);

--
-- Indizes für die Tabelle `%PREFIX%_inventory_items`
--
ALTER TABLE `%PREFIX%_inventory_items`
  ADD PRIMARY KEY (`ini_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_ini_uuid` (`ini_uuid`),
  ADD KEY `%PREFIX%_fk_ini_cat` (`ini_cat_id`),
  ADD KEY `%PREFIX%_fk_ini_status` (`ini_status`),
  ADD KEY `%PREFIX%_fk_ini_usr_create` (`ini_usr_id_create`),
  ADD KEY `%PREFIX%_fk_ini_usr_change` (`ini_usr_id_change`);

--
-- Indizes für die Tabelle `%PREFIX%_inventory_item_borrow_data`
--
ALTER TABLE `%PREFIX%_inventory_item_borrow_data`
  ADD PRIMARY KEY (`inb_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_inb_ini_id` (`inb_ini_id`);

--
-- Indizes für die Tabelle `%PREFIX%_inventory_item_data`
--
ALTER TABLE `%PREFIX%_inventory_item_data`
  ADD PRIMARY KEY (`ind_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_ind_inf_ini_id` (`ind_inf_id`,`ind_ini_id`),
  ADD KEY `%PREFIX%_fk_ind_ini` (`ind_ini_id`);

--
-- Indizes für die Tabelle `%PREFIX%_links`
--
ALTER TABLE `%PREFIX%_links`
  ADD PRIMARY KEY (`lnk_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_lnk_uuid` (`lnk_uuid`),
  ADD KEY `%PREFIX%_fk_lnk_cat` (`lnk_cat_id`),
  ADD KEY `%PREFIX%_fk_lnk_usr_create` (`lnk_usr_id_create`),
  ADD KEY `%PREFIX%_fk_lnk_usr_change` (`lnk_usr_id_change`);

--
-- Indizes für die Tabelle `%PREFIX%_lists`
--
ALTER TABLE `%PREFIX%_lists`
  ADD PRIMARY KEY (`lst_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_lst_uuid` (`lst_uuid`),
  ADD KEY `%PREFIX%_fk_lst_usr` (`lst_usr_id`),
  ADD KEY `%PREFIX%_fk_lst_org` (`lst_org_id`);

--
-- Indizes für die Tabelle `%PREFIX%_list_columns`
--
ALTER TABLE `%PREFIX%_list_columns`
  ADD PRIMARY KEY (`lsc_id`),
  ADD KEY `%PREFIX%_fk_lsc_lst` (`lsc_lst_id`),
  ADD KEY `%PREFIX%_fk_lsc_usf` (`lsc_usf_id`);

--
-- Indizes für die Tabelle `%PREFIX%_log_changes`
--
ALTER TABLE `%PREFIX%_log_changes`
  ADD PRIMARY KEY (`log_id`);

--
-- Indizes für die Tabelle `%PREFIX%_members`
--
ALTER TABLE `%PREFIX%_members`
  ADD PRIMARY KEY (`mem_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_mem_uuid` (`mem_uuid`),
  ADD KEY `%PREFIX%_idx_mem_rol_usr_id` (`mem_rol_id`,`mem_usr_id`),
  ADD KEY `%PREFIX%_fk_mem_usr` (`mem_usr_id`),
  ADD KEY `%PREFIX%_fk_mem_usr_create` (`mem_usr_id_create`),
  ADD KEY `%PREFIX%_fk_mem_usr_change` (`mem_usr_id_change`);

--
-- Indizes für die Tabelle `%PREFIX%_menu`
--
ALTER TABLE `%PREFIX%_menu`
  ADD PRIMARY KEY (`men_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_men_uuid` (`men_uuid`),
  ADD KEY `%PREFIX%_idx_men_men_id_parent` (`men_men_id_parent`),
  ADD KEY `%PREFIX%_fk_men_com_id` (`men_com_id`);

--
-- Indizes für die Tabelle `%PREFIX%_messages`
--
ALTER TABLE `%PREFIX%_messages`
  ADD PRIMARY KEY (`msg_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_msg_uuid` (`msg_uuid`),
  ADD KEY `%PREFIX%_fk_msg_usr_sender` (`msg_usr_id_sender`);

--
-- Indizes für die Tabelle `%PREFIX%_messages_attachments`
--
ALTER TABLE `%PREFIX%_messages_attachments`
  ADD PRIMARY KEY (`msa_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_msa_uuid` (`msa_uuid`),
  ADD KEY `%PREFIX%_fk_msa_msg_id` (`msa_msg_id`);

--
-- Indizes für die Tabelle `%PREFIX%_messages_content`
--
ALTER TABLE `%PREFIX%_messages_content`
  ADD PRIMARY KEY (`msc_id`),
  ADD KEY `%PREFIX%_fk_msc_msg_id` (`msc_msg_id`),
  ADD KEY `%PREFIX%_fk_msc_usr_id` (`msc_usr_id`);

--
-- Indizes für die Tabelle `%PREFIX%_messages_recipients`
--
ALTER TABLE `%PREFIX%_messages_recipients`
  ADD PRIMARY KEY (`msr_id`),
  ADD KEY `%PREFIX%_fk_msr_msg_id` (`msr_msg_id`),
  ADD KEY `%PREFIX%_fk_msr_rol_id` (`msr_rol_id`),
  ADD KEY `%PREFIX%_fk_msr_usr_id` (`msr_usr_id`);

--
-- Indizes für die Tabelle `%PREFIX%_oidc_access_tokens`
--
ALTER TABLE `%PREFIX%_oidc_access_tokens`
  ADD PRIMARY KEY (`oat_id`),
  ADD KEY `%PREFIX%_fk_oat_usr_id` (`oat_usr_id`),
  ADD KEY `%PREFIX%_fk_oat_ocl_id` (`oat_ocl_id`),
  ADD KEY `%PREFIX%_fk_oat_usr_create` (`oat_usr_id_create`);

--
-- Indizes für die Tabelle `%PREFIX%_oidc_auth_codes`
--
ALTER TABLE `%PREFIX%_oidc_auth_codes`
  ADD PRIMARY KEY (`oac_id`),
  ADD KEY `%PREFIX%_fk_oac_usr_id` (`oac_usr_id`),
  ADD KEY `%PREFIX%_fk_oac_ocl_id` (`oac_ocl_id`),
  ADD KEY `%PREFIX%_fk_oac_usr_create` (`oac_usr_id_create`);

--
-- Indizes für die Tabelle `%PREFIX%_oidc_clients`
--
ALTER TABLE `%PREFIX%_oidc_clients`
  ADD PRIMARY KEY (`ocl_id`),
  ADD KEY `%PREFIX%_fk_ocl_usr_create` (`ocl_usr_id_create`),
  ADD KEY `%PREFIX%_fk_ocl_usr_change` (`ocl_usr_id_change`);

--
-- Indizes für die Tabelle `%PREFIX%_oidc_refresh_tokens`
--
ALTER TABLE `%PREFIX%_oidc_refresh_tokens`
  ADD PRIMARY KEY (`ort_id`),
  ADD KEY `%PREFIX%_fk_ort_usr_id` (`ort_usr_id`),
  ADD KEY `%PREFIX%_fk_ort_ocl_id` (`ort_ocl_id`),
  ADD KEY `%PREFIX%_fk_ort_usr_create` (`ort_usr_id_create`);

--
-- Indizes für die Tabelle `%PREFIX%_organizations`
--
ALTER TABLE `%PREFIX%_organizations`
  ADD PRIMARY KEY (`org_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_org_shortname` (`org_shortname`),
  ADD UNIQUE KEY `%PREFIX%_idx_org_uuid` (`org_uuid`),
  ADD KEY `%PREFIX%_fk_org_org_parent` (`org_org_id_parent`);

--
-- Indizes für die Tabelle `%PREFIX%_photos`
--
ALTER TABLE `%PREFIX%_photos`
  ADD PRIMARY KEY (`pho_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_pho_uuid` (`pho_uuid`),
  ADD KEY `%PREFIX%_fk_pho_pho_parent` (`pho_pho_id_parent`),
  ADD KEY `%PREFIX%_fk_pho_org` (`pho_org_id`),
  ADD KEY `%PREFIX%_fk_pho_usr_create` (`pho_usr_id_create`),
  ADD KEY `%PREFIX%_fk_pho_usr_change` (`pho_usr_id_change`);

--
-- Indizes für die Tabelle `%PREFIX%_preferences`
--
ALTER TABLE `%PREFIX%_preferences`
  ADD PRIMARY KEY (`prf_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_prf_org_id_name` (`prf_org_id`,`prf_name`);

--
-- Indizes für die Tabelle `%PREFIX%_registrations`
--
ALTER TABLE `%PREFIX%_registrations`
  ADD PRIMARY KEY (`reg_id`),
  ADD KEY `%PREFIX%_fk_reg_org` (`reg_org_id`),
  ADD KEY `%PREFIX%_fk_reg_usr` (`reg_usr_id`);

--
-- Indizes für die Tabelle `%PREFIX%_roles`
--
ALTER TABLE `%PREFIX%_roles`
  ADD PRIMARY KEY (`rol_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_rol_uuid` (`rol_uuid`),
  ADD KEY `%PREFIX%_fk_rol_cat` (`rol_cat_id`),
  ADD KEY `%PREFIX%_fk_rol_lst_id` (`rol_lst_id`),
  ADD KEY `%PREFIX%_fk_rol_usr_create` (`rol_usr_id_create`),
  ADD KEY `%PREFIX%_fk_rol_usr_change` (`rol_usr_id_change`);

--
-- Indizes für die Tabelle `%PREFIX%_roles_rights`
--
ALTER TABLE `%PREFIX%_roles_rights`
  ADD PRIMARY KEY (`ror_id`),
  ADD KEY `%PREFIX%_fk_ror_ror_parent` (`ror_ror_id_parent`);

--
-- Indizes für die Tabelle `%PREFIX%_roles_rights_data`
--
ALTER TABLE `%PREFIX%_roles_rights_data`
  ADD PRIMARY KEY (`rrd_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_rrd_ror_rol_object_id` (`rrd_ror_id`,`rrd_rol_id`,`rrd_object_id`),
  ADD KEY `%PREFIX%_fk_rrd_rol` (`rrd_rol_id`),
  ADD KEY `%PREFIX%_fk_rrd_usr_create` (`rrd_usr_id_create`);

--
-- Indizes für die Tabelle `%PREFIX%_role_dependencies`
--
ALTER TABLE `%PREFIX%_role_dependencies`
  ADD PRIMARY KEY (`rld_rol_id_parent`,`rld_rol_id_child`),
  ADD KEY `%PREFIX%_fk_rld_rol_child` (`rld_rol_id_child`),
  ADD KEY `%PREFIX%_fk_rld_usr` (`rld_usr_id`);

--
-- Indizes für die Tabelle `%PREFIX%_rooms`
--
ALTER TABLE `%PREFIX%_rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_room_uuid` (`room_uuid`),
  ADD KEY `%PREFIX%_fk_room_usr_create` (`room_usr_id_create`),
  ADD KEY `%PREFIX%_fk_room_usr_change` (`room_usr_id_change`);

--
-- Indizes für die Tabelle `%PREFIX%_saml_clients`
--
ALTER TABLE `%PREFIX%_saml_clients`
  ADD PRIMARY KEY (`smc_id`),
  ADD UNIQUE KEY `smc_client_id` (`smc_client_id`),
  ADD KEY `%PREFIX%_fk_smc_usr_create` (`smc_usr_id_create`),
  ADD KEY `%PREFIX%_fk_smc_usr_change` (`smc_usr_id_change`);

--
-- Indizes für die Tabelle `%PREFIX%_sessions`
--
ALTER TABLE `%PREFIX%_sessions`
  ADD PRIMARY KEY (`ses_id`),
  ADD KEY `%PREFIX%_idx_session_id` (`ses_session_id`),
  ADD KEY `%PREFIX%_fk_ses_org` (`ses_org_id`),
  ADD KEY `%PREFIX%_fk_ses_usr` (`ses_usr_id`);

--
-- Indizes für die Tabelle `%PREFIX%_sso_keys`
--
ALTER TABLE `%PREFIX%_sso_keys`
  ADD PRIMARY KEY (`key_id`),
  ADD KEY `%PREFIX%_fk_key_org` (`key_org_id`),
  ADD KEY `%PREFIX%_fk_key_usr_change` (`key_usr_id_change`),
  ADD KEY `%PREFIX%_fk_key_usr_create` (`key_usr_id_create`);

--
-- Indizes für die Tabelle `%PREFIX%_texts`
--
ALTER TABLE `%PREFIX%_texts`
  ADD PRIMARY KEY (`txt_id`),
  ADD KEY `%PREFIX%_fk_txt_org` (`txt_org_id`);

--
-- Indizes für die Tabelle `%PREFIX%_users`
--
ALTER TABLE `%PREFIX%_users`
  ADD PRIMARY KEY (`usr_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_usr_uuid` (`usr_uuid`),
  ADD UNIQUE KEY `%PREFIX%_idx_usr_login_name` (`usr_login_name`),
  ADD KEY `%PREFIX%_fk_usr_usr_create` (`usr_usr_id_create`),
  ADD KEY `%PREFIX%_fk_usr_usr_change` (`usr_usr_id_change`);

--
-- Indizes für die Tabelle `%PREFIX%_user_data`
--
ALTER TABLE `%PREFIX%_user_data`
  ADD PRIMARY KEY (`usd_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_usd_usr_usf_id` (`usd_usr_id`,`usd_usf_id`),
  ADD KEY `%PREFIX%_fk_usd_usf` (`usd_usf_id`);

--
-- Indizes für die Tabelle `%PREFIX%_user_fields`
--
ALTER TABLE `%PREFIX%_user_fields`
  ADD PRIMARY KEY (`usf_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_usf_name_intern` (`usf_name_intern`),
  ADD UNIQUE KEY `%PREFIX%_idx_usf_uuid` (`usf_uuid`),
  ADD KEY `%PREFIX%_fk_usf_cat` (`usf_cat_id`),
  ADD KEY `%PREFIX%_fk_usf_usr_create` (`usf_usr_id_create`),
  ADD KEY `%PREFIX%_fk_usf_usr_change` (`usf_usr_id_change`);

--
-- Indizes für die Tabelle `%PREFIX%_user_field_select_options`
--
ALTER TABLE `%PREFIX%_user_field_select_options`
  ADD PRIMARY KEY (`ufo_id`),
  ADD KEY `%PREFIX%_fk_ufo_usf` (`ufo_usf_id`);

--
-- Indizes für die Tabelle `%PREFIX%_user_relations`
--
ALTER TABLE `%PREFIX%_user_relations`
  ADD PRIMARY KEY (`ure_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_ure_urt_usr` (`ure_urt_id`,`ure_usr_id1`,`ure_usr_id2`),
  ADD UNIQUE KEY `%PREFIX%_idx_ure_uuid` (`ure_uuid`),
  ADD KEY `%PREFIX%_fk_ure_usr1` (`ure_usr_id1`),
  ADD KEY `%PREFIX%_fk_ure_usr2` (`ure_usr_id2`),
  ADD KEY `%PREFIX%_fk_ure_usr_change` (`ure_usr_id_change`),
  ADD KEY `%PREFIX%_fk_ure_usr_create` (`ure_usr_id_create`);

--
-- Indizes für die Tabelle `%PREFIX%_user_relation_types`
--
ALTER TABLE `%PREFIX%_user_relation_types`
  ADD PRIMARY KEY (`urt_id`),
  ADD UNIQUE KEY `%PREFIX%_idx_ure_urt_name` (`urt_name`),
  ADD UNIQUE KEY `%PREFIX%_idx_urt_uuid` (`urt_uuid`),
  ADD KEY `%PREFIX%_fk_urt_id_inverse` (`urt_id_inverse`),
  ADD KEY `%PREFIX%_fk_urt_usr_change` (`urt_usr_id_change`),
  ADD KEY `%PREFIX%_fk_urt_usr_create` (`urt_usr_id_create`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_announcements`
--
ALTER TABLE `%PREFIX%_announcements`
  MODIFY `ann_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_auto_login`
--
ALTER TABLE `%PREFIX%_auto_login`
  MODIFY `atl_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_categories`
--
ALTER TABLE `%PREFIX%_categories`
  MODIFY `cat_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=307;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_category_report`
--
ALTER TABLE `%PREFIX%_category_report`
  MODIFY `crt_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_components`
--
ALTER TABLE `%PREFIX%_components`
  MODIFY `com_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=223;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_events`
--
ALTER TABLE `%PREFIX%_events`
  MODIFY `dat_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_files`
--
ALTER TABLE `%PREFIX%_files`
  MODIFY `fil_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_folders`
--
ALTER TABLE `%PREFIX%_folders`
  MODIFY `fol_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_forum_posts`
--
ALTER TABLE `%PREFIX%_forum_posts`
  MODIFY `fop_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_forum_topics`
--
ALTER TABLE `%PREFIX%_forum_topics`
  MODIFY `fot_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_inventory_fields`
--
ALTER TABLE `%PREFIX%_inventory_fields`
  MODIFY `inf_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_inventory_field_select_options`
--
ALTER TABLE `%PREFIX%_inventory_field_select_options`
  MODIFY `ifo_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_inventory_items`
--
ALTER TABLE `%PREFIX%_inventory_items`
  MODIFY `ini_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_inventory_item_borrow_data`
--
ALTER TABLE `%PREFIX%_inventory_item_borrow_data`
  MODIFY `inb_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_inventory_item_data`
--
ALTER TABLE `%PREFIX%_inventory_item_data`
  MODIFY `ind_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_links`
--
ALTER TABLE `%PREFIX%_links`
  MODIFY `lnk_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_lists`
--
ALTER TABLE `%PREFIX%_lists`
  MODIFY `lst_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_list_columns`
--
ALTER TABLE `%PREFIX%_list_columns`
  MODIFY `lsc_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=157;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_log_changes`
--
ALTER TABLE `%PREFIX%_log_changes`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_members`
--
ALTER TABLE `%PREFIX%_members`
  MODIFY `mem_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=544;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_menu`
--
ALTER TABLE `%PREFIX%_menu`
  MODIFY `men_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_messages`
--
ALTER TABLE `%PREFIX%_messages`
  MODIFY `msg_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_messages_attachments`
--
ALTER TABLE `%PREFIX%_messages_attachments`
  MODIFY `msa_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_messages_content`
--
ALTER TABLE `%PREFIX%_messages_content`
  MODIFY `msc_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_messages_recipients`
--
ALTER TABLE `%PREFIX%_messages_recipients`
  MODIFY `msr_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_oidc_access_tokens`
--
ALTER TABLE `%PREFIX%_oidc_access_tokens`
  MODIFY `oat_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_oidc_auth_codes`
--
ALTER TABLE `%PREFIX%_oidc_auth_codes`
  MODIFY `oac_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_oidc_clients`
--
ALTER TABLE `%PREFIX%_oidc_clients`
  MODIFY `ocl_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_oidc_refresh_tokens`
--
ALTER TABLE `%PREFIX%_oidc_refresh_tokens`
  MODIFY `ort_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_organizations`
--
ALTER TABLE `%PREFIX%_organizations`
  MODIFY `org_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_photos`
--
ALTER TABLE `%PREFIX%_photos`
  MODIFY `pho_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_preferences`
--
ALTER TABLE `%PREFIX%_preferences`
  MODIFY `prf_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94374;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_registrations`
--
ALTER TABLE `%PREFIX%_registrations`
  MODIFY `reg_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_roles`
--
ALTER TABLE `%PREFIX%_roles`
  MODIFY `rol_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_roles_rights`
--
ALTER TABLE `%PREFIX%_roles_rights`
  MODIFY `ror_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_roles_rights_data`
--
ALTER TABLE `%PREFIX%_roles_rights_data`
  MODIFY `rrd_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_rooms`
--
ALTER TABLE `%PREFIX%_rooms`
  MODIFY `room_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_saml_clients`
--
ALTER TABLE `%PREFIX%_saml_clients`
  MODIFY `smc_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_sessions`
--
ALTER TABLE `%PREFIX%_sessions`
  MODIFY `ses_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_sso_keys`
--
ALTER TABLE `%PREFIX%_sso_keys`
  MODIFY `key_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_texts`
--
ALTER TABLE `%PREFIX%_texts`
  MODIFY `txt_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_users`
--
ALTER TABLE `%PREFIX%_users`
  MODIFY `usr_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=361;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_user_data`
--
ALTER TABLE `%PREFIX%_user_data`
  MODIFY `usd_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18139;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_user_fields`
--
ALTER TABLE `%PREFIX%_user_fields`
  MODIFY `usf_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_user_field_select_options`
--
ALTER TABLE `%PREFIX%_user_field_select_options`
  MODIFY `ufo_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_user_relations`
--
ALTER TABLE `%PREFIX%_user_relations`
  MODIFY `ure_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT für Tabelle `%PREFIX%_user_relation_types`
--
ALTER TABLE `%PREFIX%_user_relation_types`
  MODIFY `urt_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `%PREFIX%_announcements`
--
ALTER TABLE `%PREFIX%_announcements`
  ADD CONSTRAINT `%PREFIX%_fk_ann_cat` FOREIGN KEY (`ann_cat_id`) REFERENCES `%PREFIX%_categories` (`cat_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_ann_usr_change` FOREIGN KEY (`ann_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_ann_usr_create` FOREIGN KEY (`ann_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_auto_login`
--
ALTER TABLE `%PREFIX%_auto_login`
  ADD CONSTRAINT `%PREFIX%_fk_atl_org` FOREIGN KEY (`atl_org_id`) REFERENCES `%PREFIX%_organizations` (`org_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_atl_usr` FOREIGN KEY (`atl_usr_id`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_categories`
--
ALTER TABLE `%PREFIX%_categories`
  ADD CONSTRAINT `%PREFIX%_fk_cat_org` FOREIGN KEY (`cat_org_id`) REFERENCES `%PREFIX%_organizations` (`org_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_cat_usr_change` FOREIGN KEY (`cat_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_cat_usr_create` FOREIGN KEY (`cat_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_category_report`
--
ALTER TABLE `%PREFIX%_category_report`
  ADD CONSTRAINT `%PREFIX%_fk_crt_org` FOREIGN KEY (`crt_org_id`) REFERENCES `%PREFIX%_organizations` (`org_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_events`
--
ALTER TABLE `%PREFIX%_events`
  ADD CONSTRAINT `%PREFIX%_fk_dat_cat` FOREIGN KEY (`dat_cat_id`) REFERENCES `%PREFIX%_categories` (`cat_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_dat_rol` FOREIGN KEY (`dat_rol_id`) REFERENCES `%PREFIX%_roles` (`rol_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_dat_room` FOREIGN KEY (`dat_room_id`) REFERENCES `%PREFIX%_rooms` (`room_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_dat_usr_change` FOREIGN KEY (`dat_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_dat_usr_create` FOREIGN KEY (`dat_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_files`
--
ALTER TABLE `%PREFIX%_files`
  ADD CONSTRAINT `%PREFIX%_fk_fil_fol` FOREIGN KEY (`fil_fol_id`) REFERENCES `%PREFIX%_folders` (`fol_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_fil_usr` FOREIGN KEY (`fil_usr_id`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_folders`
--
ALTER TABLE `%PREFIX%_folders`
  ADD CONSTRAINT `%PREFIX%_fk_fol_fol_parent` FOREIGN KEY (`fol_fol_id_parent`) REFERENCES `%PREFIX%_folders` (`fol_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_fol_org` FOREIGN KEY (`fol_org_id`) REFERENCES `%PREFIX%_organizations` (`org_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_fol_usr` FOREIGN KEY (`fol_usr_id`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_forum_posts`
--
ALTER TABLE `%PREFIX%_forum_posts`
  ADD CONSTRAINT `%PREFIX%_fk_fop_fot` FOREIGN KEY (`fop_fot_id`) REFERENCES `%PREFIX%_forum_topics` (`fot_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_fop_usr_change` FOREIGN KEY (`fop_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_fop_usr_create` FOREIGN KEY (`fop_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_forum_topics`
--
ALTER TABLE `%PREFIX%_forum_topics`
  ADD CONSTRAINT `%PREFIX%_fk_fot_cat` FOREIGN KEY (`fot_cat_id`) REFERENCES `%PREFIX%_categories` (`cat_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_fot_first_fop` FOREIGN KEY (`fot_fop_id_first_post`) REFERENCES `%PREFIX%_forum_posts` (`fop_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_fot_usr_create` FOREIGN KEY (`fot_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_ids`
--
ALTER TABLE `%PREFIX%_ids`
  ADD CONSTRAINT `%PREFIX%_fk_ids_usr_id` FOREIGN KEY (`ids_usr_id`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_inventory_fields`
--
ALTER TABLE `%PREFIX%_inventory_fields`
  ADD CONSTRAINT `%PREFIX%_fk_inf_org` FOREIGN KEY (`inf_org_id`) REFERENCES `%PREFIX%_organizations` (`org_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_inf_usr_change` FOREIGN KEY (`inf_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_inf_usr_create` FOREIGN KEY (`inf_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_inventory_field_select_options`
--
ALTER TABLE `%PREFIX%_inventory_field_select_options`
  ADD CONSTRAINT `%PREFIX%_fk_ifo_inf` FOREIGN KEY (`ifo_inf_id`) REFERENCES `%PREFIX%_inventory_fields` (`inf_id`) ON DELETE CASCADE ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_inventory_items`
--
ALTER TABLE `%PREFIX%_inventory_items`
  ADD CONSTRAINT `%PREFIX%_fk_ini_cat` FOREIGN KEY (`ini_cat_id`) REFERENCES `%PREFIX%_categories` (`cat_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_ini_status` FOREIGN KEY (`ini_status`) REFERENCES `%PREFIX%_inventory_field_select_options` (`ifo_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_ini_usr_change` FOREIGN KEY (`ini_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_ini_usr_create` FOREIGN KEY (`ini_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_inventory_item_borrow_data`
--
ALTER TABLE `%PREFIX%_inventory_item_borrow_data`
  ADD CONSTRAINT `%PREFIX%_fk_inb_ini` FOREIGN KEY (`inb_ini_id`) REFERENCES `%PREFIX%_inventory_items` (`ini_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_inventory_item_data`
--
ALTER TABLE `%PREFIX%_inventory_item_data`
  ADD CONSTRAINT `%PREFIX%_fk_ind_inf` FOREIGN KEY (`ind_inf_id`) REFERENCES `%PREFIX%_inventory_fields` (`inf_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_ind_ini` FOREIGN KEY (`ind_ini_id`) REFERENCES `%PREFIX%_inventory_items` (`ini_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_links`
--
ALTER TABLE `%PREFIX%_links`
  ADD CONSTRAINT `%PREFIX%_fk_lnk_cat` FOREIGN KEY (`lnk_cat_id`) REFERENCES `%PREFIX%_categories` (`cat_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_lnk_usr_change` FOREIGN KEY (`lnk_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_lnk_usr_create` FOREIGN KEY (`lnk_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_lists`
--
ALTER TABLE `%PREFIX%_lists`
  ADD CONSTRAINT `%PREFIX%_fk_lst_org` FOREIGN KEY (`lst_org_id`) REFERENCES `%PREFIX%_organizations` (`org_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_lst_usr` FOREIGN KEY (`lst_usr_id`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_list_columns`
--
ALTER TABLE `%PREFIX%_list_columns`
  ADD CONSTRAINT `%PREFIX%_fk_lsc_lst` FOREIGN KEY (`lsc_lst_id`) REFERENCES `%PREFIX%_lists` (`lst_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_lsc_usf` FOREIGN KEY (`lsc_usf_id`) REFERENCES `%PREFIX%_user_fields` (`usf_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_members`
--
ALTER TABLE `%PREFIX%_members`
  ADD CONSTRAINT `%PREFIX%_fk_mem_rol` FOREIGN KEY (`mem_rol_id`) REFERENCES `%PREFIX%_roles` (`rol_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_mem_usr` FOREIGN KEY (`mem_usr_id`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_mem_usr_change` FOREIGN KEY (`mem_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_mem_usr_create` FOREIGN KEY (`mem_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_menu`
--
ALTER TABLE `%PREFIX%_menu`
  ADD CONSTRAINT `%PREFIX%_fk_men_com_id` FOREIGN KEY (`men_com_id`) REFERENCES `%PREFIX%_components` (`com_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_men_men_parent` FOREIGN KEY (`men_men_id_parent`) REFERENCES `%PREFIX%_menu` (`men_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_messages`
--
ALTER TABLE `%PREFIX%_messages`
  ADD CONSTRAINT `%PREFIX%_fk_msg_usr_sender` FOREIGN KEY (`msg_usr_id_sender`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_messages_attachments`
--
ALTER TABLE `%PREFIX%_messages_attachments`
  ADD CONSTRAINT `%PREFIX%_fk_msa_msg_id` FOREIGN KEY (`msa_msg_id`) REFERENCES `%PREFIX%_messages` (`msg_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_messages_content`
--
ALTER TABLE `%PREFIX%_messages_content`
  ADD CONSTRAINT `%PREFIX%_fk_msc_msg_id` FOREIGN KEY (`msc_msg_id`) REFERENCES `%PREFIX%_messages` (`msg_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_msc_usr_id` FOREIGN KEY (`msc_usr_id`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_messages_recipients`
--
ALTER TABLE `%PREFIX%_messages_recipients`
  ADD CONSTRAINT `%PREFIX%_fk_msr_msg_id` FOREIGN KEY (`msr_msg_id`) REFERENCES `%PREFIX%_messages` (`msg_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_msr_rol_id` FOREIGN KEY (`msr_rol_id`) REFERENCES `%PREFIX%_roles` (`rol_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_msr_usr_id` FOREIGN KEY (`msr_usr_id`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_oidc_access_tokens`
--
ALTER TABLE `%PREFIX%_oidc_access_tokens`
  ADD CONSTRAINT `%PREFIX%_fk_oat_ocl_id` FOREIGN KEY (`oat_ocl_id`) REFERENCES `%PREFIX%_oidc_clients` (`ocl_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `%PREFIX%_fk_oat_usr_create` FOREIGN KEY (`oat_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_oat_usr_id` FOREIGN KEY (`oat_usr_id`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `%PREFIX%_oidc_auth_codes`
--
ALTER TABLE `%PREFIX%_oidc_auth_codes`
  ADD CONSTRAINT `%PREFIX%_fk_oac_ocl_id` FOREIGN KEY (`oac_ocl_id`) REFERENCES `%PREFIX%_oidc_clients` (`ocl_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `%PREFIX%_fk_oac_usr_create` FOREIGN KEY (`oac_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_oac_usr_id` FOREIGN KEY (`oac_usr_id`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `%PREFIX%_oidc_clients`
--
ALTER TABLE `%PREFIX%_oidc_clients`
  ADD CONSTRAINT `%PREFIX%_fk_ocl_usr_change` FOREIGN KEY (`ocl_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_ocl_usr_create` FOREIGN KEY (`ocl_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_oidc_refresh_tokens`
--
ALTER TABLE `%PREFIX%_oidc_refresh_tokens`
  ADD CONSTRAINT `%PREFIX%_fk_ort_ocl_id` FOREIGN KEY (`ort_ocl_id`) REFERENCES `%PREFIX%_oidc_clients` (`ocl_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `%PREFIX%_fk_ort_usr_create` FOREIGN KEY (`ort_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_ort_usr_id` FOREIGN KEY (`ort_usr_id`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `%PREFIX%_organizations`
--
ALTER TABLE `%PREFIX%_organizations`
  ADD CONSTRAINT `%PREFIX%_fk_org_org_parent` FOREIGN KEY (`org_org_id_parent`) REFERENCES `%PREFIX%_organizations` (`org_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_photos`
--
ALTER TABLE `%PREFIX%_photos`
  ADD CONSTRAINT `%PREFIX%_fk_pho_org` FOREIGN KEY (`pho_org_id`) REFERENCES `%PREFIX%_organizations` (`org_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_pho_pho_parent` FOREIGN KEY (`pho_pho_id_parent`) REFERENCES `%PREFIX%_photos` (`pho_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_pho_usr_change` FOREIGN KEY (`pho_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_pho_usr_create` FOREIGN KEY (`pho_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_preferences`
--
ALTER TABLE `%PREFIX%_preferences`
  ADD CONSTRAINT `%PREFIX%_fk_prf_org` FOREIGN KEY (`prf_org_id`) REFERENCES `%PREFIX%_organizations` (`org_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_registrations`
--
ALTER TABLE `%PREFIX%_registrations`
  ADD CONSTRAINT `%PREFIX%_fk_reg_org` FOREIGN KEY (`reg_org_id`) REFERENCES `%PREFIX%_organizations` (`org_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_reg_usr` FOREIGN KEY (`reg_usr_id`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_roles`
--
ALTER TABLE `%PREFIX%_roles`
  ADD CONSTRAINT `%PREFIX%_fk_rol_cat` FOREIGN KEY (`rol_cat_id`) REFERENCES `%PREFIX%_categories` (`cat_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_rol_lst_id` FOREIGN KEY (`rol_lst_id`) REFERENCES `%PREFIX%_lists` (`lst_id`) ON DELETE SET NULL ON UPDATE SET NULL,
  ADD CONSTRAINT `%PREFIX%_fk_rol_usr_change` FOREIGN KEY (`rol_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_rol_usr_create` FOREIGN KEY (`rol_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_roles_rights`
--
ALTER TABLE `%PREFIX%_roles_rights`
  ADD CONSTRAINT `%PREFIX%_fk_ror_ror_parent` FOREIGN KEY (`ror_ror_id_parent`) REFERENCES `%PREFIX%_roles_rights` (`ror_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_roles_rights_data`
--
ALTER TABLE `%PREFIX%_roles_rights_data`
  ADD CONSTRAINT `%PREFIX%_fk_rrd_rol` FOREIGN KEY (`rrd_rol_id`) REFERENCES `%PREFIX%_roles` (`rol_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_rrd_ror` FOREIGN KEY (`rrd_ror_id`) REFERENCES `%PREFIX%_roles_rights` (`ror_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_rrd_usr_create` FOREIGN KEY (`rrd_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_role_dependencies`
--
ALTER TABLE `%PREFIX%_role_dependencies`
  ADD CONSTRAINT `%PREFIX%_fk_rld_rol_child` FOREIGN KEY (`rld_rol_id_child`) REFERENCES `%PREFIX%_roles` (`rol_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_rld_rol_parent` FOREIGN KEY (`rld_rol_id_parent`) REFERENCES `%PREFIX%_roles` (`rol_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_rld_usr` FOREIGN KEY (`rld_usr_id`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_rooms`
--
ALTER TABLE `%PREFIX%_rooms`
  ADD CONSTRAINT `%PREFIX%_fk_room_usr_change` FOREIGN KEY (`room_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_room_usr_create` FOREIGN KEY (`room_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_saml_clients`
--
ALTER TABLE `%PREFIX%_saml_clients`
  ADD CONSTRAINT `%PREFIX%_fk_smc_usr_change` FOREIGN KEY (`smc_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_smc_usr_create` FOREIGN KEY (`smc_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_sessions`
--
ALTER TABLE `%PREFIX%_sessions`
  ADD CONSTRAINT `%PREFIX%_fk_ses_org` FOREIGN KEY (`ses_org_id`) REFERENCES `%PREFIX%_organizations` (`org_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_ses_usr` FOREIGN KEY (`ses_usr_id`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_sso_keys`
--
ALTER TABLE `%PREFIX%_sso_keys`
  ADD CONSTRAINT `%PREFIX%_fk_key_org` FOREIGN KEY (`key_org_id`) REFERENCES `%PREFIX%_organizations` (`org_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_key_usr_change` FOREIGN KEY (`key_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_key_usr_create` FOREIGN KEY (`key_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_texts`
--
ALTER TABLE `%PREFIX%_texts`
  ADD CONSTRAINT `%PREFIX%_fk_txt_org` FOREIGN KEY (`txt_org_id`) REFERENCES `%PREFIX%_organizations` (`org_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_users`
--
ALTER TABLE `%PREFIX%_users`
  ADD CONSTRAINT `%PREFIX%_fk_usr_usr_change` FOREIGN KEY (`usr_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_usr_usr_create` FOREIGN KEY (`usr_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_user_data`
--
ALTER TABLE `%PREFIX%_user_data`
  ADD CONSTRAINT `%PREFIX%_fk_usd_usf` FOREIGN KEY (`usd_usf_id`) REFERENCES `%PREFIX%_user_fields` (`usf_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_usd_usr` FOREIGN KEY (`usd_usr_id`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_user_fields`
--
ALTER TABLE `%PREFIX%_user_fields`
  ADD CONSTRAINT `%PREFIX%_fk_usf_cat` FOREIGN KEY (`usf_cat_id`) REFERENCES `%PREFIX%_categories` (`cat_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_usf_usr_change` FOREIGN KEY (`usf_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_usf_usr_create` FOREIGN KEY (`usf_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_user_field_select_options`
--
ALTER TABLE `%PREFIX%_user_field_select_options`
  ADD CONSTRAINT `%PREFIX%_fk_ufo_usf` FOREIGN KEY (`ufo_usf_id`) REFERENCES `%PREFIX%_user_fields` (`usf_id`) ON DELETE CASCADE ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_user_relations`
--
ALTER TABLE `%PREFIX%_user_relations`
  ADD CONSTRAINT `%PREFIX%_fk_ure_urt` FOREIGN KEY (`ure_urt_id`) REFERENCES `%PREFIX%_user_relation_types` (`urt_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_ure_usr1` FOREIGN KEY (`ure_usr_id1`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_ure_usr2` FOREIGN KEY (`ure_usr_id2`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_ure_usr_change` FOREIGN KEY (`ure_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_ure_usr_create` FOREIGN KEY (`ure_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints der Tabelle `%PREFIX%_user_relation_types`
--
ALTER TABLE `%PREFIX%_user_relation_types`
  ADD CONSTRAINT `%PREFIX%_fk_urt_id_inverse` FOREIGN KEY (`urt_id_inverse`) REFERENCES `%PREFIX%_user_relation_types` (`urt_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_urt_usr_change` FOREIGN KEY (`urt_usr_id_change`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT `%PREFIX%_fk_urt_usr_create` FOREIGN KEY (`urt_usr_id_create`) REFERENCES `%PREFIX%_users` (`usr_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
