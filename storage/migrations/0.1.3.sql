ALTER TABLE `intranet_mensagem`
	ADD COLUMN `tema` TEXT NULL DEFAULT NULL AFTER `mensagem`,
	ADD COLUMN `resolvido` TINYINT NULL DEFAULT '0' AFTER `situacao`,
	ADD COLUMN `resolucao_mensagem` TEXT NULL DEFAULT NULL AFTER `resolvido`,
	ADD COLUMN `resolucao_por` INT NULL DEFAULT NULL AFTER `resolucao_mensagem`,
	ADD COLUMN `resolucao_data` DATETIME NULL DEFAULT NULL AFTER `resolucao_por`;

ALTER TABLE `configuracao` COMMENT='0.1.3';