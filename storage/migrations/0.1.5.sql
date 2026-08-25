-- Perfil de origem reutilizável (fingerprint dos cabeçalhos + tipo DRE/DFC/BP).
-- Não depende de VPS, EasyPanel nem IDs de conta: o JSON guarda o nome da conta.

CREATE TABLE IF NOT EXISTS `projeto_origem_perfil` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `fingerprint` CHAR(64) NOT NULL,
  `tipo_demonstrativo` VARCHAR(20) NOT NULL,
  `familia` VARCHAR(64) NOT NULL DEFAULT 'desconhecida',
  `mapeamento_json` MEDIUMTEXT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_origem_fingerprint_tipo` (`fingerprint`, `tipo_demonstrativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
