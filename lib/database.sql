CREATE SCHEMA `vaccinator` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
 
CREATE TABLE `vaccinator`.`data` (
  `PID` BINARY(32) NOT NULL COMMENT 'PID 128 bit in hex encoding (always 32 characters)',
  `PAYLOAD` MEDIUMTEXT NOT NULL COMMENT 'PAYLOAD as text (no matter encoding, max 16MB)',
  `PROVIDERID` INT NOT NULL,
  `CREATIONDATE` DATETIME NOT NULL,
  PRIMARY KEY (`PID`)
) ENGINE=InnoDB;

CREATE TABLE `vaccinator`.`provider` (
  `PROVIDERID` INT NOT NULL auto_increment,
  `NAME` VARCHAR(45) NOT NULL COMMENT 'service provider name',
  `PASSWORD` VARCHAR(45) NOT NULL COMMENT 'service provider password',
  `IP` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'service provider IP',
  `CREATIONDATE` DATETIME NOT NULL,
  PRIMARY KEY (`PROVIDERID`)
) ENGINE=InnoDB;
  
CREATE TABLE `vaccinator`.`log` (
  `LOGID` BIGINT NOT NULL auto_increment,
  `LOGTYPE` INT NOT NULL,
  `LOGDATE` DATETIME NOT NULL,
  `PROVIDERID` INT NOT NULL,
  `LOGCOMMENT` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`LOGID`)
) ENGINE=InnoDB;

CREATE TABLE `vaccinator`.`search` (
  `PID` binary(32) NOT NULL COMMENT 'PID 128 bit in hex encoding (always 32 characters)',
  `WORD` varchar(30) NOT NULL COMMENT 'HEX encoded SearchHash values only',
  KEY (`WORD`)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;