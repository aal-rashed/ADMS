-- Community Profile (Admins) — run against database `adms`
-- Adds profile_photo and bio columns to the admins table.

SET NAMES utf8mb4;

ALTER TABLE `admins`
  ADD COLUMN IF NOT EXISTS `profile_photo` VARCHAR(500) NULL AFTER `password`,
  ADD COLUMN IF NOT EXISTS `bio`           TEXT        NULL AFTER `profile_photo`;
