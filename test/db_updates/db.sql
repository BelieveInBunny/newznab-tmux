ALTER TABLE  `predb`
  ADD  `hash` VARCHAR( 32 ) NULL,
  ADD INDEX (  `hash` ( 32 ) );

ALTER TABLE `releases`
  ADD `dehashstatus` TINYINT( 1 ) NOT NULL DEFAULT '0' after `haspreview`,
  ADD `nfostatus` TINYINT NOT NULL DEFAULT 0 after `dehashstatus`,
  ADD `relnamestatus` TINYINT NOT NULL DEFAULT 1 after `nfostatus`,
  ADD `hashed` BOOL DEFAULT false after `relnamestatus`,
  ADD `nzbstatus` TINYINT NOT NULL DEFAULT 0 after `hashed`,
  ADD `reqidstatus` TINYINT(1) NOT NULL DEFAULT '0' after `relnamestatus`;

ALTER TABLE `releases`
  ADD INDEX `ix_releases_hashed` (`hashed`),
  ADD INDEX `ix_releases_mergedreleases` (`dehashstatus`, `relnamestatus`, `passwordstatus`),
  ADD INDEX `ix_releases_nzbstatus` (`nzbstatus`),
  ADD INDEX `ix_releases_nfostatus` (`nfostatus` ASC) USING HASH,
  ADD INDEX `ix_releases_reqidstatus` (`reqidstatus` ASC) USING HASH;
  
UPDATE releases SET hashed = true WHERE searchname REGEXP '[a-fA-F0-9]{32}' OR name REGEXP '[a-fA-F0-9]{32}'; 
UPDATE releases SET nzbstatus = 1;
UPDATE releases SET reqidstatus = -1 WHERE reqidstatus = 0 AND nzbstatus = 1 AND relnamestatus IN (0, 1) AND name REGEXP '^\\[[[:digit:]]+\\]' = 0;
delimiter //
CREATE TRIGGER check_insert BEFORE INSERT ON releases FOR EACH ROW BEGIN IF NEW.name REGEXP '^\\[[[:digit:]]+\\]' = 0 THEN SET NEW.reqidstatus = -1; ELSEIF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.hashed = true; END IF; END;//
CREATE TRIGGER check_update BEFORE UPDATE ON releases FOR EACH ROW BEGIN IF NEW.name REGEXP '^\\[[[:digit:]]+\\]' = 0 THEN SET NEW.reqidstatus = -1; ELSEIF NEW.searchname REGEXP '[a-fA-F0-9]{32}' OR NEW.name REGEXP '[a-fA-F0-9]{32}' THEN SET NEW.hashed = true; END IF; END;//
delimiter ;
		




