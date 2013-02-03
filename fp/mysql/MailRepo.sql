SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL';


-- -----------------------------------------------------
-- Table `email_templates`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `email_templates` ;

CREATE  TABLE IF NOT EXISTS `email_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT ,
  `name` VARCHAR(100) NOT NULL ,
  `slug` VARCHAR(45) NOT NULL ,
  `subject` TEXT NOT NULL ,
  `body` TEXT NULL ,
  `body_html` TEXT NULL ,
  `info` TEXT NULL ,
  PRIMARY KEY (`id`) ,
  UNIQUE INDEX `slug_UNIQUE` (`slug` ASC) )
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `emails_queue`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `emails_queue` ;

CREATE  TABLE IF NOT EXISTS `emails_queue` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT ,
  `message_id` VARCHAR(100) NULL ,
  `created_on` TIMESTAMP NOT NULL ,
  `to_be_sent_on` TIMESTAMP NULL ,
  `priority` INT UNSIGNED NOT NULL DEFAULT 0 ,
  `serialized_recipient` VARCHAR(255) NOT NULL ,
  `serialized_sender` VARCHAR(255) NOT NULL ,
  `headers` TEXT NOT NULL ,
  `sent` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 ,
  `email_object` LONGBLOB NOT NULL ,
  `slug` VARCHAR(45) NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `fk_emails_queue_1` (`slug` ASC) ,
  UNIQUE INDEX `message_id_UNIQUE` (`message_id` ASC) ,
  CONSTRAINT `fk_emails_queue_1`
    FOREIGN KEY (`slug` )
    REFERENCES `email_templates` (`slug` )
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;



SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

-- -----------------------------------------------------
-- Data for table `email_templates`
-- -----------------------------------------------------
START TRANSACTION;
INSERT INTO `email_templates` (`id`, `name`, `slug`, `subject`, `body`, `body_html`, `info`) VALUES (NULL, 'FP Test Email 1', 'fp_test1', 'This is a test subject!', 'This is a test body!', '<h1>HTML Body test with H1!!</h1>', 'This email is for testing purposes');
INSERT INTO `email_templates` (`id`, `name`, `slug`, `subject`, `body`, `body_html`, `info`) VALUES (NULL, 'FP Test Email 2', 'fp_test2', 'Testing {tag1}!', 'This is a body tag test on {tag1}.', '<h1>HTML Body tag test with {tag2}!</h1>', 'This email is for testing tags and parsing while emailing');

COMMIT;
