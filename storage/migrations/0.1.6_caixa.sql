-- Staging para Montar DFC a partir de extrato (OFX) + recibos.
-- Destino final continua sendo projeto_lancamento (tipo dfc).

CREATE TABLE IF NOT EXISTS `caixa_sessao` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_projeto` INT NOT NULL,
  `periodo_inicio` DATE NULL,
  `periodo_fim` DATE NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'rascunho',
  `banco_nome` VARCHAR(120) NULL,
  `banco_id` VARCHAR(32) NULL,
  `agencia` VARCHAR(32) NULL,
  `conta` VARCHAR(64) NULL,
  `arquivo_extrato` VARCHAR(255) NULL,
  `arquivo_original` VARCHAR(255) NULL,
  `total_movimentos` INT NOT NULL DEFAULT 0,
  `trash` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_caixa_sessao_projeto` (`id_projeto`, `trash`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `caixa_movimento` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_sessao` INT NOT NULL,
  `fitid` VARCHAR(64) NOT NULL,
  `data_posted` DATE NOT NULL,
  `tipo` VARCHAR(16) NOT NULL DEFAULT 'other',
  `valor` DECIMAL(15,2) NOT NULL,
  `memo` VARCHAR(500) NOT NULL DEFAULT '',
  `id_dre_conta` INT NULL,
  `confianca_conta` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `motivo_conta` VARCHAR(255) NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'novo',
  `id_lancamento` INT NULL,
  `trash` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_caixa_mov_sessao_fitid` (`id_sessao`, `fitid`),
  KEY `idx_caixa_mov_sessao_status` (`id_sessao`, `trash`, `status`),
  KEY `idx_caixa_mov_data` (`id_sessao`, `data_posted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `caixa_recibo` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_sessao` INT NOT NULL,
  `arquivo_path` VARCHAR(255) NOT NULL,
  `nome_original` VARCHAR(255) NOT NULL,
  `data_doc` DATE NULL,
  `valor` DECIMAL(15,2) NULL,
  `texto_extraido` MEDIUMTEXT NULL,
  `contraparte` VARCHAR(255) NULL,
  `status_extracao` VARCHAR(16) NOT NULL DEFAULT 'pendente',
  `trash` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_caixa_recibo_sessao` (`id_sessao`, `trash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `caixa_vinculo` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_movimento` INT NOT NULL,
  `id_recibo` INT NOT NULL,
  `confianca_match` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `origem` VARCHAR(16) NOT NULL DEFAULT 'auto',
  `status` VARCHAR(16) NOT NULL DEFAULT 'sugerido',
  `motivo` VARCHAR(255) NULL,
  `trash` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_caixa_vinculo_mov_rec` (`id_movimento`, `id_recibo`),
  KEY `idx_caixa_vinculo_status` (`id_movimento`, `trash`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
