<?php
$router->namespace("\App\Controllers\Admin")->group(null);

    // rota dinâmica para erros
    $router->get('/erro/{code}', 'ErrorController:index', 'admin.error');

    $router->get("/", "LoginController:index", "admin.login");
    $router->post("/login", "LoginController:login", "admin.logar");
    $router->get("/esqueci-minha-senha", "LoginController:password", "admin.password");
    $router->post("/esqueci-minha-senha", "LoginController:passwordRequest", "admin.password.request");
    $router->get("/redefinir-senha/{token}", "LoginController:passwordReset", "admin.password.reset");
    $router->post("/redefinir-senha", "LoginController:passwordUpdate", "admin.password.update");
    $router->get("/logout", "LoginController:logout", "admin.logout");
    $router->get("/home", "HomeController:index", "admin.home");
    $router->post("/home/atalho/recarga", "HomeController:atalhoRecarga", "admin.home.atalho.recarga");
    $router->post("/tema/{tema}", "UsuarioPreferenciaController:theme", "admin.tema");
    $router->post("/cnpj", "EstabelecimentoController:getCnpj");

    $router->get("/alterar-senha", "UsuarioController:pass", "admin.pass");
    $router->get("/pass/find", "UsuarioController:verifyPass", "admin.pass.find");
    $router->post("/alterar-senha/update", "UsuarioController:updatePass", "admin.pass.update");

    // USUÁRIOS
    $router->get("/usuarios", "UsuarioController:index", "admin.usuario.index");
    $router->get("/usuarios/find", "UsuarioController:checkLogin", "admin.usuario.find");
    $router->get("/usuarios/novo", "UsuarioController:new", "admin.usuario.novo");
    $router->get("/usuarios/editar/{id}", "UsuarioController:edit", "admin.usuario.editar");
    $router->get("/usuarios/historico", "UsuarioController:history", "admin.usuario.historico");
    $router->get("/usuarios/historico/{id}", "UsuarioController:history", "admin.usuario.historicos");
    $router->get("/usuarios/delete/{id}", "UsuarioController:delete", "admin.usuario.deletar");
    $router->post("/usuarios/insert", "UsuarioController:create", "admin.usuario.insert");
    $router->post("/usuarios/update", "UsuarioController:update", "admin.usuario.update");
    $router->get("/usuarios/editar/{id}/delete/foto", "UsuarioController:deletePhoto", "admin.usuario.delete.photo");

    // PERFIS DE ACESSO
    $router->get("/perfis", "UsuarioPerfilController:index", "admin.perfil.index");
    $router->get("/perfis/novo", "UsuarioPerfilController:new", "admin.perfil.novo");
    $router->get("/perfis/editar/{id}", "UsuarioPerfilController:edit", "admin.perfil.editar");
    $router->post("/perfis/insert", "UsuarioPerfilController:create", "admin.perfil.insert");
    $router->post("/perfis/update", "UsuarioPerfilController:update", "admin.perfil.update");
    $router->get("/perfis/delete/{id}", "UsuarioPerfilController:delete", "admin.perfil.delete");
    $router->get("/perfis/{id}/permissoes", "UsuarioPerfilController:permissions", "admin.perfil.permissions");

    // CLIENTES
    $router->get("/clientes", "ClienteController:index", "admin.cliente.index");
    $router->get("/clientes/novo", "ClienteController:new", "admin.cliente.novo");
    $router->get("/clientes/editar/{id}", "ClienteController:edit", "admin.cliente.editar");
    $router->get("/clientes/{id}/cartoes", "ClienteController:cartoes", "admin.cliente.cartoes");
    $router->post("/clientes/importar/upload", "ClienteController:importUpload", "admin.cliente.import.upload");
    $router->post("/clientes/find", "ClienteController:find", "admin.cliente.find");
    $router->post("/clientes/quick-insert", "ClienteController:quickCreate", "admin.cliente.quick.insert");
    $router->post("/clientes/insert", "ClienteController:create", "admin.cliente.insert");
    $router->post("/clientes/update", "ClienteController:update", "admin.cliente.update");
    $router->get("/clientes/delete/{id}", "ClienteController:delete", "admin.cliente.deletar");

    // CARTÕES
    $router->get("/cartoes", "CartaoController:index", "admin.cartao.index");
    $router->get("/cartoes/relatorio/bonus", "RelatorioController:relatorioBonus", "admin.cartao.relatorio.bonus");
    $router->get("/cartoes/relatorio/gastos", "RelatorioController:relatorioGastos", "admin.cartao.relatorio.gastos");
    $router->get("/cartoes/relatorio/recargas", "RelatorioController:relatorioRecargas", "admin.cartao.relatorio.recargas");
    $router->get("/cartoes/relatorio/tops", "RelatorioController:relatorioTops", "admin.cartao.relatorio.tops");
    $router->get("/cartoes/buscar", "CartaoController:buscar", "admin.cartao.buscar");
    $router->get("/cartoes/novo", "CartaoController:new", "admin.cartao.novo");
    $router->get("/cartoes/editar/{id}", "CartaoController:edit", "admin.cartao.editar");
    $router->get("/cartoes/codigo/disponivel", "CartaoController:codigoDisponivel", "admin.cartao.codigo.disponivel");
    $router->get("/cartoes/token-nfc/disponivel", "CartaoController:tokenNfcDisponivel", "admin.cartao.token.nfc.disponivel");
    $router->get("/cartoes/{id}/hub", "CartaoController:hub", "admin.cartao.hub");
    $router->post("/cartoes/{id}/recarregar", "CartaoController:recarregar", "admin.cartao.recarregar");
    $router->post("/cartoes/{id}/lancar-venda", "CartaoController:lancarVenda", "admin.cartao.lancar.venda");
    $router->post("/cartoes/{id}/consumo", "CartaoController:consumo", "admin.cartao.consumo");
    $router->post("/cartoes/{id}/ajuste", "CartaoController:ajuste", "admin.cartao.ajuste");
    $router->post("/cartoes/{id}/estornar", "CartaoController:estornar", "admin.cartao.estornar");
    $router->get("/cartoes/acoes/{id}", "CartaoController:actions", "admin.cartao.acoes");
    $router->post("/cartoes/insert", "CartaoController:create", "admin.cartao.insert");
    $router->post("/cartoes/update", "CartaoController:update", "admin.cartao.update");
    $router->get("/cartoes/{id}/extrato/filtro", "CartaoController:extratoFiltro", "admin.cartao.extrato.filtro");
    $router->get("/cartoes/{id}/extrato/xls", "CartaoController:extratoXls", "admin.cartao.extrato.xls");
    $router->get("/cartoes/{id}/extrato/pdf", "CartaoController:extratoPdf", "admin.cartao.extrato.pdf");
    $router->get("/cartoes/{id}/aplicar-fidelidade", "CartaoController:aplicarFidelidade", "admin.cartao.aplicar.fidelidade");
    $router->get("/cashback/regras", "CashbackRegraController:index", "admin.cashback.regra.index");
    $router->get("/cashback/regras/novo", "CashbackRegraController:new", "admin.cashback.regra.novo");
    $router->get("/cashback/regras/editar/{id}", "CashbackRegraController:edit", "admin.cashback.regra.editar");
    $router->post("/cashback/regras/insert", "CashbackRegraController:create", "admin.cashback.regra.insert");
    $router->post("/cashback/regras/update", "CashbackRegraController:update", "admin.cashback.regra.update");
    $router->get("/cashback/regras/delete/{id}", "CashbackRegraController:delete", "admin.cashback.regra.delete");
    $router->get("/fidelidade/regras", "FidelidadeRegraController:index", "admin.fidelidade.regra.index");
    $router->get("/fidelidade/regras/novo", "FidelidadeRegraController:new", "admin.fidelidade.regra.novo");
    $router->get("/fidelidade/regras/editar/{id}", "FidelidadeRegraController:edit", "admin.fidelidade.regra.editar");
    $router->post("/fidelidade/regras/insert", "FidelidadeRegraController:create", "admin.fidelidade.regra.insert");
    $router->post("/fidelidade/regras/update", "FidelidadeRegraController:update", "admin.fidelidade.regra.update");
    $router->get("/fidelidade/regras/delete/{id}", "FidelidadeRegraController:delete", "admin.fidelidade.regra.delete");
    $router->get("/fidelidade/regras/{id}/aplicar-bonus", "FidelidadeRegraController:aplicarBonus", "admin.fidelidade.regra.aplicar.bonus");
    $router->get("/cartao/configuracoes", "CartaoConfiguracaoController:index", "admin.cartao.config.index");
    $router->post("/cartao/configuracoes/update", "CartaoConfiguracaoController:update", "admin.cartao.config.update");

    // CONFIGURAÇÕES
    $router->get("/configuracoes/planilha-modelo", "ConfiguracaoController:planilha", "admin.configuracao.planilha");
    $router->post("/configuracoes/planilha-modelo/save", "ConfiguracaoController:planilhaSave", "admin.configuracao.planilha.save");
    $router->get("/configuracoes/planilha-modelo/download", "ConfiguracaoController:planilhaDownload", "admin.configuracao.planilha.download");
    $router->get("/configuracoes/ipca", "ConfiguracaoController:ipca", "admin.configuracao.ipca");
    $router->post("/configuracoes/ipca/sincronizar", "ConfiguracaoController:ipcaSincronizar", "admin.configuracao.ipca.sincronizar");
    $router->get("/configuracoes/selic", "ConfiguracaoController:selic", "admin.configuracao.selic");
    $router->post("/configuracoes/selic/sincronizar", "ConfiguracaoController:selicSincronizar", "admin.configuracao.selic.sincronizar");
    $router->get("/configuracoes/igpm", "ConfiguracaoController:igpm", "admin.configuracao.igpm");
    $router->post("/configuracoes/igpm/sincronizar", "ConfiguracaoController:igpmSincronizar", "admin.configuracao.igpm.sincronizar");

    // TIPOS DE DEMONSTRATIVO
    $router->get("/configuracoes/tipos-demonstrativo", "TipoDemonstrativoController:index", "admin.dre.tipo.index");
    $router->get("/configuracoes/tipos-demonstrativo/novo", "TipoDemonstrativoController:new", "admin.dre.tipo.novo");
    $router->get("/configuracoes/tipos-demonstrativo/editar/{id}", "TipoDemonstrativoController:edit", "admin.dre.tipo.editar");
    $router->post("/configuracoes/tipos-demonstrativo/insert", "TipoDemonstrativoController:create", "admin.dre.tipo.insert");
    $router->post("/configuracoes/tipos-demonstrativo/update", "TipoDemonstrativoController:update", "admin.dre.tipo.update");
    $router->get("/configuracoes/tipos-demonstrativo/delete/{id}", "TipoDemonstrativoController:delete", "admin.dre.tipo.delete");

    // PLANO DE CONTAS (DRE)
    $router->get("/configuracoes/plano-de-contas", "DreContaController:index", "admin.dre.conta.index");
    $router->get("/configuracoes/plano-de-contas/novo", "DreContaController:new", "admin.dre.conta.novo");
    $router->get("/configuracoes/plano-de-contas/editar/{id}", "DreContaController:edit", "admin.dre.conta.editar");
    $router->post("/configuracoes/plano-de-contas/insert", "DreContaController:create", "admin.dre.conta.insert");
    $router->post("/configuracoes/plano-de-contas/update", "DreContaController:update", "admin.dre.conta.update");
    $router->get("/configuracoes/plano-de-contas/delete/{id}", "DreContaController:delete", "admin.dre.conta.delete");
    $router->post("/configuracoes/plano-de-contas/reorder", "DreContaController:reorder", "admin.dre.conta.reorder");
    $router->get("/configuracoes/plano-de-contas/modelo/download", "DreContaController:downloadModelo", "admin.dre.conta.download.modelo");
    $router->post("/configuracoes/plano-de-contas/importar", "DreContaController:importar", "admin.dre.conta.importar");

    // MODELO DE DEMONSTRATIVO (árvore de organização do demonstrativo)
    $router->get("/configuracoes/modelo-demonstrativo", "ModeloDemonstrativoController:index", "admin.modelo.demonstrativo.index");
    $router->get("/configuracoes/modelo-demonstrativo/novo", "ModeloDemonstrativoController:novo", "admin.modelo.demonstrativo.novo");
    $router->post("/configuracoes/modelo-demonstrativo/insert", "ModeloDemonstrativoController:create", "admin.modelo.demonstrativo.insert");
    $router->get("/configuracoes/modelo-demonstrativo/{id}/editar", "ModeloDemonstrativoController:editar", "admin.modelo.demonstrativo.editar");
    $router->post("/configuracoes/modelo-demonstrativo/salvar", "ModeloDemonstrativoController:salvar", "admin.modelo.demonstrativo.salvar");
    $router->get("/configuracoes/modelo-demonstrativo/delete/{id}", "ModeloDemonstrativoController:delete", "admin.modelo.demonstrativo.delete");

    // PROJETOS
    $router->get("/projetos", "ProjetoController:index", "admin.projeto.index");
    $router->get("/projetos/novo", "ProjetoController:new", "admin.projeto.novo");
    $router->get("/projetos/editar/{id}", "ProjetoController:edit", "admin.projeto.editar");
    $router->post("/projetos/insert", "ProjetoController:create", "admin.projeto.insert");
    $router->post("/projetos/update", "ProjetoController:update", "admin.projeto.update");
    $router->get("/projetos/delete/{id}", "ProjetoController:delete", "admin.projeto.delete");
    $router->get("/projetos/{id}", "ProjetoController:open", "admin.projeto.abrir");
    $router->get("/projetos/{id}/importacao", "ProjetoController:importacao", "admin.projeto.importacao");
    $router->post("/projetos/{id}/importacao/limpar", "ProjetoController:limparImportacao", "admin.projeto.importacao.limpar");
    $router->post("/projetos/{id}/importacao/upload", "ProjetoController:uploadPlanilha", "admin.projeto.importacao.upload");
    $router->get("/projetos/{id}/importacao/mapear", "ProjetoController:mapeamento", "admin.projeto.importacao.mapear");
    $router->post("/projetos/{id}/importacao/mapear/preview", "ProjetoController:mapeamentoPreview", "admin.projeto.importacao.mapear.preview");
    $router->post("/projetos/{id}/importacao/mapear/salvar", "ProjetoController:salvarMapeamento", "admin.projeto.importacao.mapear.salvar");
    $router->post("/projetos/{id}/importacao/mapear/sugerir", "ProjetoController:sugerirMapeamento", "admin.projeto.importacao.mapear.sugerir");
    $router->post("/projetos/{id}/importacao/mapear/simular", "ProjetoController:simularDados", "admin.projeto.importacao.mapear.simular");
    $router->post("/projetos/{id}/importacao/processar", "ProjetoController:processarDados", "admin.projeto.importacao.processar");

    // MONTAR DFC (extrato OFX + recibos → conferência → DFC)
    $router->get("/projetos/{id}/caixa", "ProjetoCaixaController:index", "admin.projeto.caixa");
    $router->post("/projetos/{id}/caixa/extrato", "ProjetoCaixaController:uploadExtrato", "admin.projeto.caixa.upload");
    $router->post("/projetos/{id}/caixa/arquivar", "ProjetoCaixaController:arquivarSessao", "admin.projeto.caixa.arquivar");
    $router->post("/projetos/{id}/caixa/classificar", "ProjetoCaixaController:classificar", "admin.projeto.caixa.classificar");
    $router->post("/projetos/{id}/caixa/recibos", "ProjetoCaixaController:uploadRecibos", "admin.projeto.caixa.recibos");
    $router->post("/projetos/{id}/caixa/aprovar", "ProjetoCaixaController:aprovar", "admin.projeto.caixa.aprovar");
    $router->post("/projetos/{id}/caixa/ignorar", "ProjetoCaixaController:ignorar", "admin.projeto.caixa.ignorar");
    $router->post("/projetos/{id}/caixa/editar", "ProjetoCaixaController:editar", "admin.projeto.caixa.editar");
    $router->post("/projetos/{id}/caixa/aprovar-altas", "ProjetoCaixaController:aprovarAltas", "admin.projeto.caixa.aprovar_altas");

    // EMPRESAS
    $router->get("/empresas", "EmpresaController:index", "admin.empresa.index");
    $router->get("/empresas/novo", "EmpresaController:new", "admin.empresa.novo");
    $router->get("/empresas/editar/{id}", "EmpresaController:edit", "admin.empresa.editar");
    $router->post("/empresas/insert", "EmpresaController:create", "admin.empresa.insert");
    $router->post("/empresas/update", "EmpresaController:update", "admin.empresa.update");
    $router->get("/empresas/delete/{id}", "EmpresaController:delete", "admin.empresa.delete");
    $router->post("/empresas/find", "EmpresaController:getCnpj", "admin.empresa.find");
    $router->post("/empresas/importar/upload", "EmpresaController:importUpload", "admin.empresa.import.upload");

    // CANAIS DE VENDA
    $router->get("/canais-venda", "CanalVendaController:index", "admin.canal.venda.index");
    $router->get("/canais-venda/novo", "CanalVendaController:new", "admin.canal.venda.novo");
    $router->get("/canais-venda/editar/{id}", "CanalVendaController:edit", "admin.canal.venda.editar");
    $router->post("/canais-venda/insert", "CanalVendaController:create", "admin.canal.venda.insert");
    $router->post("/canais-venda/update", "CanalVendaController:update", "admin.canal.venda.update");
    $router->get("/canais-venda/delete/{id}", "CanalVendaController:delete", "admin.canal.venda.delete");
    $router->post("/canais-venda/delete-logo", "CanalVendaController:deleteLogo", "admin.canal.venda.delete.logo");

    // BANDEIRAS DE CARTÃO
    $router->get("/cartoes/bandeiras", "CartaoBandeiraController:index", "admin.cartao.bandeira.index");
    $router->get("/cartoes/bandeiras/novo", "CartaoBandeiraController:new", "admin.cartao.bandeira.novo");
    $router->get("/cartoes/bandeiras/editar/{id}", "CartaoBandeiraController:edit", "admin.cartao.bandeira.editar");
    $router->post("/cartoes/bandeiras/insert", "CartaoBandeiraController:create", "admin.cartao.bandeira.insert");
    $router->post("/cartoes/bandeiras/update", "CartaoBandeiraController:update", "admin.cartao.bandeira.update");
    $router->get("/cartoes/bandeiras/delete/{id}", "CartaoBandeiraController:delete", "admin.cartao.bandeira.delete");
    $router->post("/cartoes/bandeiras/delete-logo", "CartaoBandeiraController:deleteLogo", "admin.cartao.bandeira.delete.logo");

    // BANCOS
    $router->get("/bancos", "BancoController:index", "admin.banco.index");
    $router->get("/bancos/novo", "BancoController:new", "admin.banco.novo");
    $router->get("/bancos/editar/{id}", "BancoController:edit", "admin.banco.editar");
    $router->post("/bancos/insert", "BancoController:create", "admin.banco.insert");
    $router->post("/bancos/update", "BancoController:update", "admin.banco.update");
    $router->get("/bancos/delete/{id}", "BancoController:delete", "admin.banco.delete");
    $router->post("/bancos/delete-logo", "BancoController:deleteLogo", "admin.banco.delete.logo");

    // RESPONSÁVEIS DE EMPRESA
    $router->get("/empresas/responsaveis/novo", "EmpresaResponsavelController:new", "admin.empresa.responsavel.novo");
    $router->get("/empresas/responsaveis/editar/{id}", "EmpresaResponsavelController:edit", "admin.empresa.responsavel.editar");
    $router->post("/empresas/responsaveis/insert", "EmpresaResponsavelController:create", "admin.empresa.responsavel.insert");
    $router->post("/empresas/responsaveis/update", "EmpresaResponsavelController:update", "admin.empresa.responsavel.update");
    $router->post("/empresas/responsaveis/delete", "EmpresaResponsavelController:delete", "admin.empresa.responsavel.delete");

    // ÍNDICES FINANCEIROS
    $router->get("/indices-financeiros", "IndiceFinanceiroController:index", "admin.indice.financeiro.index");
    $router->get("/indices-financeiros/novo", "IndiceFinanceiroController:new", "admin.indice.financeiro.novo");
    $router->get("/indices-financeiros/editar/{id}", "IndiceFinanceiroController:edit", "admin.indice.financeiro.editar");
    $router->post("/indices-financeiros/insert", "IndiceFinanceiroController:create", "admin.indice.financeiro.insert");
    $router->post("/indices-financeiros/update", "IndiceFinanceiroController:update", "admin.indice.financeiro.update");
    $router->get("/indices-financeiros/delete/{id}", "IndiceFinanceiroController:delete", "admin.indice.financeiro.delete");
    $router->get("/indices-financeiros/historico/novo", "IndiceFinanceiroController:historicoNew", "admin.indice.financeiro.historico.novo");
    $router->get("/indices-financeiros/historico/editar/{id}", "IndiceFinanceiroController:historicoEdit", "admin.indice.financeiro.historico.editar");
    $router->post("/indices-financeiros/historico/insert", "IndiceFinanceiroController:historicoCreate", "admin.indice.financeiro.historico.insert");
    $router->post("/indices-financeiros/historico/update", "IndiceFinanceiroController:historicoUpdate", "admin.indice.financeiro.historico.update");
    $router->post("/indices-financeiros/historico/delete", "IndiceFinanceiroController:historicoDelete", "admin.indice.financeiro.historico.delete");

    // CLIENTES SITUAÇÕES
    $router->get("/clientes/situacoes", "ClienteSituacaoController:index", "admin.cliente.situacao.index");
    $router->get("/clientes/situacoes/novo", "ClienteSituacaoController:new", "admin.cliente.situacao.novo");
    $router->get("/clientes/situacoes/editar/{id}", "ClienteSituacaoController:edit", "admin.cliente.situacao.editar");
    $router->post("/clientes/situacoes/insert", "ClienteSituacaoController:create", "admin.cliente.situacao.insert");
    $router->post("/clientes/situacoes/update", "ClienteSituacaoController:update", "admin.cliente.situacao.update");
    $router->get("/clientes/situacoes/delete/{id}", "ClienteSituacaoController:delete", "admin.cliente.situacao.delete");

    // CLIENTES RAMOS
    $router->get("/clientes/ramos", "ClienteRamoController:index", "admin.cliente.ramo.index");
    $router->get("/clientes/ramos/novo", "ClienteRamoController:new", "admin.cliente.ramo.novo");
    $router->get("/clientes/ramos/editar/{id}", "ClienteRamoController:edit", "admin.cliente.ramo.editar");
    $router->post("/clientes/ramos/insert", "ClienteRamoController:create", "admin.cliente.ramo.insert");
    $router->post("/clientes/ramos/update", "ClienteRamoController:update", "admin.cliente.ramo.update");
    $router->get("/clientes/ramos/delete/{id}", "ClienteRamoController:delete", "admin.cliente.ramo.delete");

    /*
    // GRUPOS
    $router->get("/grupos", "GrupoController:index", "admin.grupo.index");
    $router->post("/grupos/find", "GrupoController:find", "admin.grupo.find");
    $router->post("/grupos/insert", "GrupoController:insert", "admin.grupo.insert");
    $router->post("/grupos/update", "GrupoController:update", "admin.grupo.update");
    $router->get("/grupos/delete/{id}", "GrupoController:delete", "admin.grupo.delete");
    // ALIASES
    $router->post("/grupos/alias/find", "GrupoAliasController:find", "admin.grupo.find");
    $router->post("/grupos/alias/insert", "GrupoAliasController:insert", "admin.grupo.alias.insert");
    $router->post("/grupos/alias/update", "GrupoAliasController:update", "admin.grupo.alias.update");
    $router->get("/grupos/alias/delete/{id}", "GrupoAliasController:delete", "admin.grupo.alias.delete");

    // TIPOS DE CONTA
    $router->get("/tipos-conta", "TipoContaController:index", "admin.tipoconta.index");
    $router->post("/tipos-conta/find", "TipoContaController:find", "admin.tipoconta.find");
    $router->post("/tipos-conta/insert", "TipoContaController:insert", "admin.tipoconta.insert");
    $router->post("/tipos-conta/update", "TipoContaController:update", "admin.tipoconta.update");
    $router->get("/tipos-conta/delete/{id}", "TipoContaController:delete", "admin.tipoconta.delete");

    // BANCOS
    $router->get("/bancos", "BancoController:index", "admin.banco.index");
    $router->post("/bancos/find", "BancoController:find", "admin.banco.find");
    $router->post("/bancos/insert", "BancoController:insert", "admin.banco.insert");
    $router->post("/bancos/update", "BancoController:update", "admin.banco.update");
    $router->get("/bancos/delete/{id}", "BancoController:delete", "admin.banco.delete");

    // DRE
    $router->get("/dre", "DreController:index", "admin.dre.index");
    $router->post("/dre/find", "DreController:find", "admin.dre.find");
    $router->post("/dre/insert", "DreController:insert", "admin.dre.insert");
    $router->post("/dre/update", "DreController:update", "admin.dre.update");
    $router->get("/dre/delete/{id}", "DreController:delete", "admin.dre.delete");
    // $router->post("/dre/detalhamento/listar-anos", "DreController:listYears");
    $router->get("/dre/detalhamento", "DreController:detalhamento", "admin.dre.relatorio");
    $router->post("/dre/detalhamento/gerar", "DreController:detalhamentoData");
    $router->get("/dre/demonstrativo/visao-geral", "DreController:geral", "admin.dre.demonstrativo.geral");
    $router->post("/dre/demonstrativo/visao-geral/gerar", "DreController:geralData", "admin.dre.demonstrativo.geral.gerar");
    // $router->get("/dre/demonstrativo/lucro", "DreController:demonstrativoLucro", "admin.dre.demonstrativo.lucro");
    // $router->post("/dre/demonstrativo/lucro/gerar", "DreController:demonstrativoLucroData", "admin.dre.demonstrativo.lucro.gerar");
    $router->get("/dre/configuracoes", "DreController:configuracao", "admin.dre.configuracao");
    $router->post("/dre/configuracoes/update", "DreController:configuracaoUpdate", "admin.dre.configuracao.update");

    // VENDAS
    $router->get("/vendas/dados", "VendaController:index", "admin.venda.dados.index");
    $router->get("/vendas/dados/importar", "VendaController:import", "admin.venda.dados.import");
    $router->post("/vendas/dados/upload", "VendaController:upload", "admin.venda.dados.upload");
    $router->post("/vendas/dados/processar", "VendaController:process", "admin.venda.dados.process");
    $router->post("/vendas/dados/listar", "VendaController:list", "admin.venda.dados.list");
    $router->get("/vendas/dados/delete/{id}", "VendaController:delete", "admin.venda.dados.delete");
    $router->get("/vendas/relatorio/visao-geral", "VendaController:relatorioGeral", "admin.venda.relatorio.geral");
    $router->post("/vendas/relatorio/visao-geral/gerar", "VendaController:relatorioGeralData", "admin.venda.relatorio.geral.gerar");
    $router->get("/vendas/relatorio/analise-faturamento", "VendaController:relatorioFaturamento", "admin.venda.relatorio.faturamento");
    $router->post("/vendas/relatorio/analise-faturamento/gerar", "VendaController:relatorioFaturamentoData", "admin.venda.relatorio.faturamento.gerar");
    $router->post("/vendas/relatorio/analise-faturamento/semanal/gerar", "VendaController:relatorioFaturamentoSemanalData", "admin.venda.relatorio.faturamento.semanal.gerar");
    $router->post("/vendas/relatorio/analise-faturamento/diario/gerar", "VendaController:relatorioFaturamentoDiarioData", "admin.venda.relatorio.faturamento.diario.gerar");
    $router->get("/vendas/relatorio/analise-escala", "VendaController:relatorioEscala", "admin.venda.relatorio.escala");
    $router->post("/vendas/relatorio/analise-escala/gerar", "VendaController:relatorioEscalaData", "admin.venda.relatorio.escala.gerar");
    $router->post("/vendas/relatorio/analise-escala/semanal/gerar", "VendaController:relatorioEscalaSemanalData", "admin.venda.relatorio.escala.semanal.gerar");
    $router->post("/vendas/relatorio/analise-escala/diario/gerar", "VendaController:relatorioEscalaDiarioData", "admin.venda.relatorio.escala.diario.gerar");

    // ESTABELECIMENTOS
    $router->get("/estabelecimentos", "EstabelecimentoController:index", "admin.estabelecimento.index");
    $router->get("/estabelecimentos/novo", "EstabelecimentoController:new", "admin.estabelecimento.novo");
    $router->get("/estabelecimentos/editar/{id}", "EstabelecimentoController:edit", "admin.estabelecimento.editar");
    $router->post("/estabelecimentos/find", "EstabelecimentoController:find", "admin.estabelecimento.find");
    $router->post("/estabelecimentos/insert", "EstabelecimentoController:insert", "admin.estabelecimento.insert");
    $router->post("/estabelecimentos/update", "EstabelecimentoController:update", "admin.estabelecimento.update");
    $router->get("/estabelecimentos/delete/{id}", "EstabelecimentoController:delete", "admin.estabelecimento.deletar");
    $router->post("/estabelecimentos/produto", "EstabelecimentoController:product");

    // ESTABELECIMENTOS USUÁRIOS
    $router->get("/estabelecimentos/usuarios", "UserController:index", "admin.user.index");
    $router->get("/estabelecimentos/usuarios/find", "UserController:find", "admin.user.find");
    $router->get("/estabelecimentos/usuarios/novo", "UserController:new", "admin.user.novo");
    $router->get("/estabelecimentos/usuarios/editar/{id}", "UserController:edit", "admin.user.editar");
    $router->get("/estabelecimentos/usuarios/historico", "UserController:history", "admin.user.historico");
    $router->get("/estabelecimentos/usuarios/historico/{id}", "UserController:history", "admin.user.historicos");
    $router->get("/estabelecimentos/usuarios/delete/{id}", "UserController:delete", "admin.user.deletar");
    $router->post("/estabelecimentos/usuarios/insert", "UserController:create", "admin.user.insert");
    $router->post("/estabelecimentos/usuarios/update", "UserController:update", "admin.user.update");
    $router->get("/estabelecimentos/usuarios/editar/{id}/delete/foto", "UserController:deletePhoto", "admin.user.delete.photo");

    // FORNECEDORES
    $router->get("/franqueados", "FranqueadoController:index", "admin.franqueado.index");
    $router->get("/franqueados/novo", "FranqueadoController:new", "admin.franqueado.novo");
    $router->get("/franqueados/editar/{id}", "FranqueadoController:edit", "admin.franqueado.editar");
    $router->post("/franqueados/insert", "FranqueadoController:create", "admin.franqueado.insert");
    $router->get("/franqueados/search", "FranqueadoController:search", "admin.franqueado.search");
    $router->post("/franqueados/update", "FranqueadoController:update", "admin.franqueado.update");
    $router->get("/franqueados/delete/{id}", "FranqueadoController:delete", "admin.franqueado.deletar");
    $router->post("/franqueados/enviar-mensagem", "FranqueadoController:sendMessage");

    // MODALIDADES
    $router->get("/modalidades", "ModalidadeController:index", "admin.modalidade.index");
    $router->post("/modalidades/find", "ModalidadeController:find", "admin.modalidade.find");
    $router->post("/modalidades/insert", "ModalidadeController:insert", "admin.modalidade.insert");
    $router->post("/modalidades/update", "ModalidadeController:update", "admin.modalidade.update");
    $router->get("/modalidades/delete/{id}", "ModalidadeController:delete", "admin.modalidade.delete");

    // PRODUTOS
    $router->get("/produtos", "ProdutoController:index", "admin.produto.index");
    $router->get("/produtos/novo", "ProdutoController:new", "admin.produto.novo");
    $router->get("/produtos/editar/{id}", "ProdutoController:edit", "admin.produto.editar");
    $router->post("/produtos/insert", "ProdutoController:create", "admin.produto.insert");
    $router->post("/produtos/update", "ProdutoController:update", "admin.produto.update");
    $router->get("/produtos/delete/{id}", "ProdutoController:delete", "admin.produto.deletar");

    // TIPOS DE MOVIMENTAÇÃO
    $router->get("/tipos-movimentacao", "TipoMovimentacaoController:index", "admin.tipomovimentacao.index");
    $router->post("/tipos-movimentacao/find", "TipoMovimentacaoController:find", "admin.tipomovimentacao.find");
    $router->post("/tipos-movimentacao/insert", "TipoMovimentacaoController:insert", "admin.tipomovimentacao.insert");
    $router->post("/tipos-movimentacao/update", "TipoMovimentacaoController:update", "admin.tipomovimentacao.update");
    $router->get("/tipos-movimentacao/delete/{id}", "TipoMovimentacaoController:delete", "admin.tipomovimentacao.delete");

    // DRE
    $router->get("/dre/dados", "DadoController:index", "admin.dre.dados");
    $router->get("/dre/dados/importar", "DadoController:import", "admin.dre.dados.import");
    $router->post("/dre/dados/listar", "DadoController:list", "admin.dre.dados.list");
    $router->post("/dre/dados/upload", "DadoController:upload", "admin.dre.dados.upload");
    $router->post("/dre/dados/processar", "DadoController:process", "admin.dre.dados.process");
    $router->get("/dre/dados/delete/{id}", "DadoController:delete", "admin.dre.dados.delete");

    // OUTROS CADASTROS
    $router->get("/cadastros", "CadastroController:index", "admin.cadastro.index");
    $router->post("/cadastros/find", "CadastroController:find", "admin.cadastro.find");
    $router->post("/cadastros/insert", "CadastroController:insert", "admin.cadastro.insert");
    $router->post("/cadastros/update", "CadastroController:update", "admin.cadastro.update");
    $router->get("/cadastros/delete/{list}/{idx}", "CadastroController:delete", "admin.cadastro.delete");

    // INVENTÁRIO
    $router->get("/inventario/auditoria", "InventarioController:auditoria", "admin.inventario.auditoria");
    $router->post("/inventario/auditoria", "InventarioController:auditoriaReport", "admin.inventario.auditoria.search");
    $router->post("/inventario/auditoria/exportar/{tipo}", "InventarioController:auditoriaExport", "admin.inventario.auditoria.exportar");
    $router->get("/inventario/auditoria/contagem", "InventarioController:contagem", "admin.inventario.contagem");
    $router->post("/inventario/auditoria/contagem/busca", "ProdutoController:search");
    $router->post("/inventario/auditoria/contagem/update", "InventarioController:contagemUpdate", "admin.inventario.contagem.update");
    $router->get("/inventario/auditoria/{estabelecimento}", "InventarioController:auditoria", "admin.inventario.auditoria.estabelecimento");

    $router->post("/inventario/listar-datas", "InventarioController:listDates", "admin.inventario.buscar_datas");
    $router->get("/inventario/dados", "InventarioController:index", "admin.inventario.index");
    $router->post("/inventario/dados/listar", "InventarioController:list", "admin.inventario.dados.list");
    $router->get("/inventario/dados/importar", "InventarioController:import", "admin.inventario.dados.import");
    $router->post("/inventario/dados/upload", "InventarioController:upload", "admin.inventario.dados.upload");
    $router->post("/inventario/dados/processar", "InventarioController:process", "admin.inventario.dados.process");
    $router->get("/inventario/dados/delete/{id}", "InventarioController:delete", "admin.inventario.dados.delete");
    $router->get("/inventario/dados/processar/status", "InventarioController:processStatus");

    // SINDICATOS
    $router->get("/sindicatos", "SindicatoController:index", "admin.sindicato.index");
    $router->get("/sindicatos/novo", "SindicatoController:new", "admin.sindicato.novo");
    $router->get("/sindicatos/editar/{id}", "SindicatoController:edit", "admin.sindicato.editar");
    $router->post("/sindicatos/find", "SindicatoController:find", "admin.sindicato.find");
    $router->post("/sindicatos/insert", "SindicatoController:insert", "admin.sindicato.insert");
    $router->post("/sindicatos/update", "SindicatoController:update", "admin.sindicato.update");
    $router->get("/sindicatos/delete/{id}", "SindicatoController:delete", "admin.sindicato.deletar");

    // CONVENÇÕES
    $router->get("/convencoes", "ConvencaoController:index", "admin.convencao.index");
    $router->get("/convencoes/novo", "ConvencaoController:new", "admin.convencao.novo");
    $router->get("/convencoes/editar/{id}", "ConvencaoController:edit", "admin.convencao.editar");
    $router->post("/convencoes/find", "ConvencaoController:find", "admin.convencao.find");
    $router->post("/convencoes/insert", "ConvencaoController:insert", "admin.convencao.insert");
    $router->post("/convencoes/update", "ConvencaoController:update", "admin.convencao.update");
    $router->get("/convencoes/delete/{id}", "ConvencaoController:delete", "admin.convencao.deletar");
    $router->get("/convencoes/editar/{id}/delete/{file}", "ConvencaoController:deleteFile");
    $router->get("/convencoes/{id}/imprimir", "ConvencaoController:print", "convencao.print");

    // ITENS CONVENÇÕES
    $router->get("/convencoes/itens", "CadastroController:convencao", "admin.convencao.item.index");
    $router->get("/convencoes/itens/delete/{list}/{idx}", "CadastroController:delete");
    $router->post("/convencoes/itens/register", "ConvencaoController:registerItem");
    $router->post("/convencoes/itens/find", "ConvencaoController:findItem");
    $router->post("/convencoes/itens/delete", "ConvencaoController:deleteItem");

    // EMPRESAS
    $router->get("/empresas/conciliar-cnpj", "EmpresaController:conciliarCnpj");
    $router->get("/empresas", "EmpresaController:index", "admin.empresa.index");
    $router->get("/empresas/novo", "EmpresaController:new", "admin.empresa.novo");
    $router->get("/empresas/editar/{id}", "EmpresaController:edit", "admin.empresa.editar");
    $router->post("/empresas/find", "EmpresaController:find", "admin.empresa.find");
    $router->post("/empresas/insert", "EmpresaController:insert", "admin.empresa.insert");
    $router->post("/empresas/update", "EmpresaController:update", "admin.empresa.update");
    $router->get("/empresas/delete/{id}", "EmpresaController:delete", "admin.empresa.deletar");

    // -- CONTATOS
    $router->post("/empresas/contato/find", "EmpresaContatoController:find");
    $router->post("/empresas/contato/insert", "EmpresaContatoController:insert");
    $router->post("/empresas/contato/update", "EmpresaContatoController:update");
    $router->get("/empresas/contato/delete/{id}", "EmpresaContatoController:delete");

    // -- EXTINTORES
    $router->post("/empresas/extintor/new", "ExtintorController:newModal");
    // $router->post("/empresas/extintor/find", "EmpresaContatoController:find");
    $router->post("/empresas/extintor/insert", "ExtintorController:create");
    // $router->post("/empresas/extintor/update", "EmpresaContatoController:update");
    // $router->get("/empresas/extintor/delete/{id}", "EmpresaContatoController:d;elete");



    $router->post("/clientes/servico/update", "ClienteController:updateServico", "admin.cliente.servico.valor");
    $router->post("/clientes/servico/historico", "ClienteController:historicoServico", "admin.cliente.servico.historico");




    // MOVIMENTAÇÕES INTERNOS
    $router->get("/movimentacao-interna", "MovimentacaoInternaController:index", "admin.empresa.movimentacao.index");
    $router->post("/movimentacao-interna/find", "MovimentacaoInternaController:find", "admin.empresa.movimentacao.find");
    $router->post("/movimentacao-interna/insert", "MovimentacaoInternaController:insert", "admin.empresa.movimentacao.insert");
    $router->post("/movimentacao-interna/update", "MovimentacaoInternaController:update", "admin.empresa.movimentacao.update");
    $router->get("/movimentacao-interna/delete/{id}", "MovimentacaoInternaController:delete", "admin.empresa.movimentacao.delete");


    // BLOQUEIOS CLIENTES
    $router->post("/clientes/controle/bloquear", "ClienteController:bloquear", "admin.cliente.bloquear");

    // CONTROLE MODELOS
    // $router->post("/controles/modelos/find", "ControleModeloController:find");
    // $router->get("/controles/modelos/{modelo}", "ControleModeloController:index", "admin.controle.modelo.index");
    // $router->post("/controles/modelos/{modelo}insert", "ControleModeloController:insert", "admin.controle.modelo.insert");
    // $router->get("/controles/modelos/delete/{id}", "ControleModeloController:delete", "admin.controle.modelo.delete");
    // $router->post("/controles/modelos/update", "ControleModeloController:update", "admin.controle.modelo.update");
    // $router->post("/controles/modelos/order", "ControleModeloController:order", "admin.controle.modelo.order");
    // $router->get("/controles/modelos/{modelo}/servico/delete/{id}", "ControleModeloController:deleteServico");

    // CONTROLES
    $router->get("/controles/{tipo}", "ControleController:index", "admin.controle.index");
    $router->get("/controles/{tipo}/novo", "ControleController:new", "admin.controle.novo");
    $router->get("/controles/{tipo}/editar/{id}", "ControleController:edit", "admin.controle.editar");
    $router->post("/controles/insert", "ControleController:insert", "admin.controle.insert");

    // INSERIR SERVIÇO
    $router->post("/controle/servico/valor-update", "ControleController:updateValor");
    $router->post("/controle/servico/inline-salvar", "ControleController:servicoInlineSalvar");

    // ENVIAR MENSAGEM RESPONSÁVEL
    $router->post("/controle/responsavel/enviar-mensagem", "ControleController:enviarMensagemResponsavel", "admin.controle.mensagem.responsavel");

    // ENVIAR DOCUMENTOS
    $router->post("/controle/listar-documentos", "ControleController:docsListarAjax");


    // $router->post("/controles/{tipo}/insert", "ControleController:insert", "admin.controle.modelo.insert");
    // $router->post("/controle/servico/form-adicionar", "ControleController:servicoFormAdd");
    // $router->post("/controle/servico/adicionar", "ControleController:servicoAdd");
    // $router->post("/controle/servico/historico", "ControleController:historicoList");
    // $router->post("/controle/servico/estornar", "ControleController:servicoEstornar");
    // $router->post("/controle/servicos/adicionar", "ControleController:addServicos", "admin.controle.add.servicos");
    // $router->post("/controle/clientes/adicionar", "ControleController:addClientes", "admin.controle.add.clientes");


    // SERVIÇOS
    $router->get("/servicos/{tipo}", "ServicoController:index", "admin.servico.index");
    // $router->post("/servicos/find", "ServicoController:find", "admin.servico.find");
    $router->post("/servicos/insert", "ServicoController:insert", "admin.servico.insert");
    // $router->post("/servicos/update", "ServicoController:update", "admin.servico.update");
    // $router->get("/servicos/{tipo}/delete/{id}", "ServicoController:delete", "admin.servico.deletar");
    $router->post("/servicos/{tipo}/order", "ServicoController:order");





    // CONFIGURAÇÕES
    // $router->get("/configuracoes", "ConfiguracaoController:index", "admin.configuracao.index");
    $router->get("/configuracoes/whatsapp", "ConfiguracaoController:whatsapp", "admin.configuracao.whatsapp");
    $router->post("/configuracoes/whatsapp/update", "ConfiguracaoController:whatsappUpdate", "admin.configuracao.whatsapp.update");

    $router->get("/configuracoes/emails", "ConfiguracaoController:emails", "admin.configuracao.emails");
    $router->post("/configuracoes/update", "ConfiguracaoController:update", "admin.configuracao.update");

    // EXTINTORES
    $router->get("/{cadastro}/extintores", "ExtintorController:index", "admin.extintor.index");
    $router->get("/{cadastro}/extintores/novo", "ExtintorController:new", "admin.extintor.novo");
    $router->get("/{cadastro}/extintores/editar/{id}", "ExtintorController:edit", "admin.extintor.editar");
    $router->post("/{cadastro}/extintores/insert", "ExtintorController:create", "admin.extintor.insert");
    $router->post("/{cadastro}/extintores/update", "ExtintorController:update", "admin.extintor.update");
    $router->get("/{cadastro}/extintores/delete/{id}", "ExtintorController:delete", "admin.extintor.deletar");

    // TAREFAS
    $router->get("/quadros", "QuadroController:index", "admin.quadro.index");

    // INTRANET
    $router->get("intranet/atalhos", "IntranetAtalhoController:index", "admin.intranet.atalho.index");



    $router->get("intranet/banners", "IntranetBannerController:index", "admin.intranet.banner.index");

    $router->get("intranet/calendario", "IntranetCalendarioController:index", "admin.intranet.calendario.index");

    $router->get("intranet/calendario/categoria", "IntranetCalendarioController:index", "admin.intranet.calendario.categoria.index");

    $router->get("intranet/comunicados", "IntranetComunicadoController:index", "admin.intranet.comunicado.index");

    $router->get("intranet/empregados", "IntranetEmpregadoController:index", "admin.intranet.empregado.index");

    $router->get("intranet/empregados/setor", "IntranetEmpregadoController:index", "admin.intranet.empregado.setor.index");

    $router->get("intranet/galerias", "IntranetGaleriaController:index", "admin.intranet.galeria.index");

    // INTRANET MENSAGENS (SUGESTÕES E OUVIDORIA)
    $router->get("intranet/sugestoes", "IntranetMensagemController:sugestoes", "admin.intranet.sugestao.index");
    $router->get("intranet/ouvidoria", "IntranetMensagemController:ouvidoria", "admin.intranet.ouvidoria.index");
    $router->get("intranet/mensagens/visualizar/{id}", "IntranetMensagemController:edit", "admin.intranet.mensagem.editar");
    $router->post("intranet/mensagem/tema/{action}", "IntranetMensagemController:changeTheme");
    $router->post("intranet/mensagem/categoria/{action}", "IntranetMensagemController:changeCategory");
    $router->post("intranet/mensagem/atualizar", "IntranetMensagemController:update", "intranet.mensagem.update");




    $router->get("intranet/politicas", "IntranetPoliticaController:index", "admin.intranet.politica.index");
    */
