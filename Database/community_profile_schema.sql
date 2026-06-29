-- Community Profile — run against database `adms`
-- Adds profile_photo and bio columns to the alumni table.

SET NAMES utf8mb4;

ALTER TABLE `alumni`
  ADD COLUMN IF NOT EXISTS `profile_photo` VARCHAR(500) NULL AFTER `honor_rank`,
  ADD COLUMN IF NOT EXISTS `bio`           TEXT        NULL AFTER `profile_photo`;
