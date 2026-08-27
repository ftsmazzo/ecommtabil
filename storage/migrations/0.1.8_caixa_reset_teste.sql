-- Limpa montagens de teste do Montar DFC para recomeçar do zero.
-- Roda uma vez no deploy (schema_migrations).

DELETE FROM `caixa_vinculo`;
DELETE FROM `caixa_recibo`;
DELETE FROM `caixa_movimento`;
DELETE FROM `caixa_sessao`;
