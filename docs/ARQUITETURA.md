# Arquitetura do Sistema — SAGA

## Visão Geral

Sistema de gestão financeira gerencial (DRE, DFC, BP) multi-empresa, multi-projeto.
Permite importar planilhas de qualquer ERP sem padronização prévia, através de um
mecanismo de mapeamento de colunas (de-para).

---

## Entidades Principais

```
Grupo de Clientes (cliente)
    └── Empresa
            └── Projeto
                    ├── Mapeamento de Colunas  ← por projeto + tipo de demonstrativo
                    │       (ex: mapeamento DRE, mapeamento DFC)
                    └── Lançamentos importados
                            └── vinculados a uma conta do Plano de Contas
```

---

## Tipos de Demonstrativo

O sistema suporta múltiplos tipos, cada um com seu próprio plano de contas:

| Sigla | Nome                                 |
|-------|--------------------------------------|
| DRE   | Demonstração do Resultado do Exercício |
| DFC   | Demonstração do Fluxo de Caixa       |
| BP    | Balanço Patrimonial                  |

**Regras:**
- Cada tipo tem seu plano de contas independente
- Um projeto pode ter importações para mais de um tipo
- O mapeamento de colunas é salvo por `(id_projeto, tipo_demonstrativo)`

---

## Módulo: Plano de Contas

### Conceito

Estrutura hierárquica com até 3 níveis. Existe um plano de contas por tipo de demonstrativo.

```
Nível 1 — Categoria (sintética, não recebe valores diretamente)
    Nível 2 — Grupo (sintético ou analítico)
        Nível 3 — Conta (analítica, recebe os valores importados)
```

### Exemplo — DRE

```
(+) RECEITA OPERACIONAL          ← nível 1, categoria
      (+) Receita Bruta          ← nível 2, grupo
            Vendas à Vista       ← nível 3, conta analítica
            Vendas a Prazo       ← nível 3, conta analítica
      (-) Deduções               ← nível 2, grupo
            Devoluções           ← nível 3, conta analítica
            Impostos s/ Venda    ← nível 3, conta analítica
(=) RECEITA LÍQUIDA              ← nível 1, linha de resultado (calculada)

(-) CUSTOS
      (-) CMV
(=) LUCRO BRUTO                  ← calculado

(-) DESPESAS OPERACIONAIS
      (-) Administrativas
      (-) Comerciais
(=) EBITDA                       ← calculado
```

### Tabela: `dre_conta`

| Campo              | Tipo         | Descrição                                           |
|--------------------|--------------|-----------------------------------------------------|
| id                 | int PK       |                                                     |
| tipo_demonstrativo | enum         | dre / dfc / bp                                      |
| id_pai             | int FK null  | null = nível 1 (categoria raiz)                     |
| nivel              | tinyint      | 1, 2 ou 3                                           |
| codigo             | varchar(20)  | Ex: 1.1.2 — gerado automaticamente                 |
| nome               | varchar(255) | Nome da conta                                       |
| tipo               | enum         | sintetica / analitica                               |
| natureza           | enum         | receita / despesa / neutro                          |
| sinal              | tinyint      | +1 ou -1 (define se soma ou subtrai no demonstrativo) |
| eh_resultado       | tinyint(1)   | 1 = linha calculada (ex: Receita Líquida, EBITDA)   |
| ordem              | int          | posição dentro do pai                               |
| trash              | tinyint(1)   | soft delete                                         |
| created_by         | int FK null  |                                                     |
| updated_by         | int FK null  |                                                     |

### Regras

- Contas **sintéticas** agrupam filhas, não recebem valor diretamente
- Contas **analíticas** recebem valores dos lançamentos importados
- Linhas de **resultado** (`eh_resultado = 1`) são calculadas automaticamente em PHP — não recebem importação
- O código é gerado no Model: pai `1.2` com 3 filhos existentes → próximo filho = `1.2.4`
- `natureza = receita` → `sinal = +1`; `natureza = despesa` → `sinal = -1`

---

## Módulo: Importação — Fluxo Completo

### Passo 1 — Upload do arquivo

O usuário sobe o arquivo (.xlsx, .xls, .csv) na tela de importação do projeto.
O sistema salva o arquivo e redireciona para a tela de mapeamento.

Se o projeto já tem um mapeamento salvo para aquele tipo de demonstrativo,
os campos vêm pré-preenchidos — usuário só confirma e processa.

### Passo 2 — Tela de Mapeamento

```
┌─ Mapeamento de Colunas ──────────────────────────────────────┐
│ Arquivo: relatorio_omie_jan25.xlsx   [Ver Planilha ↗]        │
│                                      [Cancelar Mapeamento]   │
│                                                              │
│ ┌─ O arquivo tem 3 abas com dados: [Aba 1 ▼] ─────────────┐ │  ← só aparece se > 1 aba
│ └──────────────────────────────────────────────────────────┘ │
│                                                              │
│ Coluna do Excel          Mapear para                         │
│ ─────────────────────    ─────────────────────────────────── │
│ Período                  [— Não mapear —         ▼]          │
│ Jan/25 | Fev/25          (helper: 2-3 primeiros valores)     │
│                                                              │
│ Receita Bruta            [Conta do plano         ▼]          │
│ 100000 | 110000          [  Receita Bruta        ▼] ←2º select aparece
│                                                              │
│ CMV                      [Conta do plano         ▼]          │
│ 40000 | 44000            [  CMV                  ▼]          │
│                                                              │
│                                     [Salvar e Processar]     │
└──────────────────────────────────────────────────────────────┘
```

**Offcanvas "Ver Planilha"** — abre uma visualização da planilha em uma sidebar
usando plugin JS (SheetJS / x-spreadsheet) carregado sob demanda.

**Seleção de aba** — se o arquivo tem mais de 1 aba com dados, aparece um card
com select. Ao mudar a aba, a tabela de colunas atualiza via AJAX.

### Tipos de mapeamento (select "Mapear para")

| Valor             | Label na tela         | Descrição                                              |
|-------------------|-----------------------|--------------------------------------------------------|
| `ignorar`         | — Não mapear —        | Coluna ignorada no processamento                       |
| `periodo`         | Período               | Data/mês de referência (ex: "Jan/25", "2025-01")       |
| `conta`           | Conta do plano        | Valor financeiro → abre 2º select com contas analíticas |
| `unidade_negocio` | Unidade de Negócio    | Filial / centro de resultado                           |
| `centro_custo`    | Centro de Custo       |                                                        |
| `descricao`       | Descrição             | Texto livre do lançamento                              |
| `personalizado`   | Coluna personalizada  | Nome livre definido pelo usuário                       |

Quando `tipo = conta`, um segundo select aparece listando as contas **analíticas**
do plano de contas do tipo de demonstrativo do projeto, agrupadas por categoria.

### Passo 3 — Salvamento do Mapeamento

O mapeamento é salvo em `projeto_mapeamento_coluna` vinculado a
`(id_projeto, tipo_demonstrativo)`. Na próxima importação do mesmo projeto
para o mesmo tipo, o sistema busca o mapeamento salvo e pré-preenche a tela.

### Passo 4 — Processamento

```
Para cada linha de dados do arquivo:
  → lê a coluna mapeada como "periodo" → extrai a data
  → para cada coluna mapeada como "conta":
      → cria um projeto_lancamento com:
           id_projeto, tipo_demonstrativo, id_dre_conta, periodo, valor
  → para colunas personalizadas:
      → salva em projeto_lancamento_extra (chave-valor flexível)
```

---

## Tabelas do Módulo de Importação

### `projeto_mapeamento_coluna`

Mapeamento salvo por `(projeto + tipo_demonstrativo)`. Reutilizado nas próximas importações.

| Campo              | Tipo         | Descrição                                            |
|--------------------|--------------|------------------------------------------------------|
| id                 | int PK       |                                                      |
| id_projeto         | int FK       |                                                      |
| tipo_demonstrativo | enum         | dre / dfc / bp                                       |
| indice_coluna      | tinyint      | posição da coluna no arquivo (0, 1, 2...)            |
| nome_header        | varchar(255) | texto do cabeçalho detectado no arquivo              |
| tipo               | enum         | ignorar / periodo / conta / unidade_negocio / ...    |
| id_dre_conta       | int FK null  | preenchido quando tipo = conta                       |
| nome_personalizado | varchar(255) | preenchido quando tipo = personalizado               |
| updated_at         | datetime     | atualizado a cada re-mapeamento                      |

### `projeto_importacao`

Registro de cada importação (histórico, status, rastreabilidade).

| Campo              | Tipo         | Descrição                                          |
|--------------------|--------------|-----------------------------------------------------|
| id                 | int PK       |                                                     |
| id_projeto         | int FK       |                                                     |
| tipo_demonstrativo | enum         | dre / dfc / bp                                      |
| arquivo_nome       | varchar(255) | nome original do arquivo                            |
| arquivo_path       | varchar(500) | caminho no servidor (storage/uploads/importacoes/)  |
| aba_nome           | varchar(255) | nome da aba selecionada pelo usuário                |
| total_linhas       | int          | total de linhas processadas                         |
| total_lancamentos  | int          | total de lançamentos criados                        |
| status             | enum         | aguardando_mapeamento / processando / concluido / erro |
| erro_msg           | text null    | detalhes do erro se status=erro                     |
| created_by         | int FK       |                                                     |
| created_at         | datetime     |                                                     |

### `projeto_lancamento`

Um registro por valor financeiro importado. É a fonte primária do demonstrativo.

| Campo              | Tipo          | Descrição                                        |
|--------------------|---------------|--------------------------------------------------|
| id                 | int PK        |                                                  |
| id_projeto         | int FK        |                                                  |
| id_importacao      | int FK        | qual importação gerou este lançamento            |
| tipo_demonstrativo | enum          | dre / dfc / bp                                   |
| id_dre_conta       | int FK        | conta do plano de contas                         |
| periodo            | date          | primeiro dia do mês (ex: 2025-01-01)             |
| valor              | decimal(15,2) |                                                  |
| trash              | tinyint(1)    | permite reverter/desfazer uma importação inteira |
| created_at         | datetime      |                                                  |

---

## Demonstrativo Gerado (output)

Com os lançamentos gravados, o DRE/DFC/BP é gerado **na hora** por query:

```sql
SELECT
    c.codigo,
    c.nome,
    c.sinal,
    c.eh_resultado,
    c.tipo,
    SUM(l.valor) AS valor_bruto,
    SUM(l.valor) * c.sinal AS valor_sinalizado
FROM dre_conta c
LEFT JOIN projeto_lancamento l
    ON  l.id_dre_conta       = c.id
    AND l.id_projeto          = :id_projeto
    AND l.tipo_demonstrativo  = :tipo
    AND l.periodo BETWEEN :inicio AND :fim
    AND l.trash = 0
WHERE c.tipo_demonstrativo = :tipo
  AND c.trash = 0
GROUP BY c.id
ORDER BY c.ordem
```

Linhas `eh_resultado = 1` são calculadas em PHP somando os grupos acima delas.

---

## Roadmap de Implementação

### Fase 1 — Plano de Contas ✅ concluído
- [x] Migration `dre_conta` — `0030_create_dre_conta.sql`
- [x] `DreConta` model — `arvore()`, `gerarCodigo()`, `reordenar()`
- [x] `DreContaController` — CRUD completo + endpoint `reorder` (AJAX)
- [x] Views: `index.phtml` (árvore drag-and-drop) + `_arvore.phtml` (partial recursivo) + `form.phtml` (modal)
- [x] Menu: Configurações > Plano de Contas
- [x] Permissões: `plano_contas_gerenciar/inserir/editar/excluir`
- [ ] **Pendente:** adicionar coluna `tipo_demonstrativo` na tabela e na UI (seletor DRE/DFC/BP)

#### Detalhes de implementação

**Estrutura da árvore:**
- Renderizada recursivamente via partial `_arvore.phtml` (League Plates `insert`)
- Cada `<ul>` é um Sortable independente — drag-and-drop só dentro do mesmo nível/pai
- Ao soltar, dispara POST para `admin.dre.conta.reorder` com array de ids na nova ordem
- Botão "+" aparece no hover de níveis 1 e 2 — abre modal com `id_pai` pré-preenchido
- Excluir bloqueado se a conta tiver filhos ativos

**Geração de código:**
- `DreConta::gerarCodigo(null)` → conta raízes existentes → "1", "2", "3"...
- `DreConta::gerarCodigo($idPai)` → `$pai->codigo . "." . (total_filhos + 1)`

### Fase 1.1 — Plano de Contas: suporte a múltiplos tipos ← próximo passo
- [ ] Migration: adicionar `tipo_demonstrativo ENUM('dre','dfc','bp')` em `dre_conta`
- [ ] UI: seletor de tipo (DRE / DFC / BP) no topo da página — filtra a árvore
- [ ] Ao criar conta raiz, vincular ao tipo ativo
- [ ] Subcontas herdam o tipo do pai (sem escolha)

### Fase 2 — Upload e Tela de Mapeamento
- [ ] Migration `projeto_importacao` + `projeto_mapeamento_coluna`
- [ ] Upload do arquivo (dizuploader → storage/uploads/importacoes/)
- [ ] Leitura de abas e headers via PhpSpreadsheet
- [ ] Tela de mapeamento: tabela de colunas + selects de tipo + 2º select de conta
- [ ] Offcanvas "Ver Planilha" com SheetJS (preview do arquivo)
- [ ] Salvamento do mapeamento por `(id_projeto, tipo_demonstrativo)`

### Fase 3 — Processamento
- [ ] Migration `projeto_lancamento`
- [ ] Engine de processamento: lê arquivo + aplica mapeamento + grava lançamentos
- [ ] Histórico de importações por projeto
- [ ] Reversão: soft delete em lote de todos os lançamentos de uma importação

### Fase 4 — Demonstrativo
- [ ] Query de geração do DRE/DFC/BP
- [ ] Tela de visualização por projeto/período/tipo
- [ ] Exportação PDF/Excel

---

## Stack Técnica

| Camada       | Tecnologia                                              |
|--------------|---------------------------------------------------------|
| Backend      | PHP 8+ — MVC customizado (ControllerAdmin, Model, View) |
| Banco        | MySQL/MariaDB — DB `saga`                               |
| Planilhas    | `phpoffice/phpspreadsheet` (leitura e geração de .xlsx) |
| Preview      | SheetJS (`xlsx` npm ou CDN) — visualização no browser   |
| Upload       | dizuploader (já integrado)                              |
| UI           | Bootstrap 5 + SortableJS (drag-and-drop)                |
| Permissões   | tabela `usuario_permissao` + cache de sessão            |

---

*Documento vivo — atualizar conforme o desenvolvimento avança.*
