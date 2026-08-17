ALTER TABLE `empresa_coresponsavel`
	ADD COLUMN `ordem` INT NULL DEFAULT '1' AFTER `id_usuario`;

ALTER TABLE `empresa` DROP COLUMN `id_movimentacao`;
ALTER TABLE `empresa` ADD COLUMN `id_responsavel_financeiro` INT NULL AFTER `id_responsavel`;
ALTER TABLE `empresa` CHANGE COLUMN `id_responsavel` `id_responsavel_folha` TEXT NULL DEFAULT NULL COLLATE 'utf8mb4_0900_ai_ci' AFTER `id_sindicato`;
ALTER TABLE `empresa` CHANGE COLUMN `id_responsavel_folha` `id_responsavel_folha` INT NULL DEFAULT NULL COLLATE 'utf8mb4_0900_ai_ci' AFTER `id_sindicato`;



ALTER TABLE `configuracao` COMMENT='0.1.1';



