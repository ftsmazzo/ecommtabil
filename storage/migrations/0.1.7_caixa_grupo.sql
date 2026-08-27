-- Grupo DFC (operacional / investimento / financiamento) e identificação de recibo no extrato.

ALTER TABLE `caixa_movimento`
  ADD COLUMN `grupo_dfc` VARCHAR(20) NULL AFTER `motivo_conta`;

ALTER TABLE `caixa_recibo`
  ADD COLUMN `ident_extrato` VARCHAR(120) NULL AFTER `contraparte`;
