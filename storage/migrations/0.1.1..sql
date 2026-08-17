ALTER TABLE `configuracao`
	CHANGE COLUMN `id` `id` INT NOT NULL AUTO_INCREMENT FIRST;

ALTER TABLE `user_preferencia`
	DROP COLUMN `despesa_visualizacao`,
	DROP COLUMN `despesa_filtro`,
	DROP COLUMN `receita_visualizacao`,
	DROP COLUMN `receita_filtro`,
	DROP COLUMN `despesa_resumo`,
	DROP COLUMN `receita_resumo`;

ALTER TABLE `user_preferencia`
	ADD COLUMN `dre_busca` TEXT NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci' AFTER `tema`,
	ADD COLUMN `meta_lucro` VARCHAR(50) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci' AFTER `dre_busca`;

ALTER TABLE `venda`
	ADD INDEX `idx_venda_estab_ano_mes_dia_hora` (`id_estabelecimento`, `ano`, `mes`, `dia_mes`, `hora_int`, `valor_liquido`);

ALTER TABLE `configuracao` COMMENT='0.1.1';



