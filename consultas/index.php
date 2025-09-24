<?php

session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: ../logout.php');
    exit();
}

include("../connection.php");

?>


<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa com Framework</title>

    <!-- jQuery -->
    <script src="../jquery.min.js"></script>
    <!-- Bootstrap 5.3 -->
    <script src="../bootstrap.bundle.min.js"></script>
    <link href="../bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- DataTables CSS e JS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <style>
        html,
        body {
            width: 100%;
            height: 100vh;
            margin: 0;
            padding: 0;
            background-color: rgb(255, 255, 255);
            box-sizing: border-box;
        }

        .divContainerMap {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            gap: 10px;
        }

        #btns_queries {
            padding: 10px;
            width: 100%;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        #filtros {
            padding: 10px 20px;
            width: 100%;
        }

        #subDivFiltros{
            padding: 15px 20px 10px 20px;
            display: flex;
            flex-direction: column;
            gap: 5px;
            align-items: flex-start;
            width: 100%;
            border: 1px solid rgb(180, 180, 180);
            border-radius: 10px;
        }

        #containerFiltros {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }

        #tables-container {
            width: 100%;
            padding: 10px 20px;
        }

        #subDivTables{
            padding: 5px;
            width: 100%;
            height: 100%;
            border: 1px solid rgb(180, 180, 180);
            border-radius: 10px;
        }

        .divSelectCreate {
            width: 100%;
            display: flex;
        }

        .btnAut {
            width: 200px !important;
        }

        /* Forçar exibição do cabeçalho */
        #tableResult thead {
            display: table-header-group !important;
        }

        #tableResult thead tr {
            display: table-row !important;
            height: auto !important;
            min-height: 40px !important;
        }

        #tableResult thead th {
            display: table-cell !important;
            height: auto !important;
            min-height: 40px !important;
            padding: 12px 8px !important;
            vertical-align: middle !important;
            border-bottom: 2px solid #dee2e6 !important;
        }

        /* Forçar exibição das divs internas do DataTables */
        #tableResult thead th > div {
            height: auto !important;
            min-height: 20px !important;
            display: block !important;
        }

        /* Estilos para filtros dinâmicos */
        .filtro-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }

        .filtro-item.filtro-ativo {
            border-color: #28a745;
            background-color: #e6f7e6;
        }

        .filtro-campos {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filtro-remove {
            margin-left: auto;
        }

        .btn-aplicar-filtros {
            position: relative;
        }

        .filtros-aplicados-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        /* Modal de carregamento com fundo borrado */
        .modal-backdrop {
            backdrop-filter: blur(5px);
            background-color: rgba(0, 0, 0, 0.3);
        }

        #modalCarregamento .modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
    </style>
</head>

<body>

    <div class="divContainerMap">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">

                <!-- Título -->
                <a class="navbar-brand" href="#">Plataforma Geo</a>

                <!-- Botões -->
                <div class="d-flex align-items-center flex-grow-1 gap-2">

                </div>

                <!-- Botão Sair -->
                <div class="d-flex">
                    <!-- Botão de Navegação para Quadrícula Selecionada -->
                    <button class="btn btn-secondary" id="btnIrConsulta" onclick="voltarConsulta()">
                        <i class="fas fa-external-link-alt"></i> Voltar para o Painel
                    </button>
                    <a style="margin-left: 10px;" href="../logout.php" class="btn btn-secondary">Sair</a>
                </div>
            </div>
        </nav>

        <div id="btns_queries">
            <!-- Botões de Consulta -->
            <button class="btn btn-primary btnAut" id="btnConsulta0" onclick="realizarConsulta('cadastro', 0)">
                <i class="fas fa-search"></i> Imóveis filtrados abaixo
            </button>

            <button disabled class="btn btn-secondary btnAut" id="btnConsulta1" onclick="realizarConsulta('cadastro', 1)">
                <i class="fas fa-search"></i> Imóveis que precisam ser desdobrados
            </button>

            <button disabled class="btn btn-secondary btnAut" id="btnConsulta2" onclick="realizarConsulta('cadastro', 2)">
                <i class="fas fa-search"></i> Imóveis que precisam ser agrupados
            </button>

            <button disabled class="btn btn-secondary btnAut" id="btnConsulta3" onclick="realizarConsulta('desenhos', 3)">
                <i class="fas fa-search"></i> Imóveis que precisam ser cadastrados
            </button>

            <button disabled class="btn btn-secondary btnAut" id="btnConsulta4" onclick="realizarConsulta('cadastro', 4)">
                <i class="fas fa-search"></i> Imóveis já geolocalizados
            </button>

            <button disabled class="btn btn-secondary btnAut" id="btnConsulta5" onclick="realizarConsulta('cadastro', 5)">
                <i class="fas fa-search"></i> Imóveis não geolocalizados
            </button>
        </div>

        <div id="filtros" style="display: none;">
            <div id="subDivFiltros">
                <div style="width: 100%;" class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex gap-2">
                        <button id="btnIncluirFiltro" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Filtro
                        </button>
                        <button id="btnAplicarFiltros" class="btn btn-warning btn-aplicar-filtros" onclick="aplicarFiltrosCustomizados()">
                            <i class="fas fa-filter"></i> Aplicar Filtros
                            <span id="badgeFiltros" class="filtros-aplicados-badge" style="display: none;">0</span>
                        </button>
                        <button id="btnLimparFiltros" class="btn btn-secondary" onclick="limparTodosFiltros()">
                            <i class="fas fa-eraser"></i> Limpar Filtros
                        </button>
                    </div>
                    <button id="btnPlotarMapa" class="btn btn-success" onclick="plotarNoMapa()">
                        <i class="fas fa-map-marked-alt"></i> Plotar no mapa
                    </button>
                </div>

                <div id="containerFiltros">

                </div>
            </div>
        </div>

        <div id="tables-container">
            <div id="subDivTables">
                <!-- Aviso inicial -->
                <div id="avisoInicial" class="d-flex justify-content-center align-items-center" style="height: 100%;">
                    <div class="text-center">
                        <i class="fas fa-database fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">Selecione uma consulta para começar</h4>
                        <p class="text-muted">Use os botões acima para realizar consultas no banco de dados</p>
                    </div>
                </div>
                
                <!-- Loading -->
                <div id="loadingDiv" style="display: none; text-align: center; padding: 50px;">
                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <div style="margin-top: 15px; font-size: 16px; color: #666;">
                        Carregando dados...
                    </div>
                </div>
                
                <!-- Tabela -->
                <table id="tableResult" class="table table-striped table-hover" style="width:100%; display: none;">
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para mostrar todos os dados da linha -->
    <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-labelledby="modalDetalhesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetalhesLabel">Detalhes do Registro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="modalDetalhesContent">
                        <!-- Conteúdo será preenchido dinamicamente -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Carregamento -->
    <div class="modal fade" id="modalCarregamento" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-body text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <h5 class="text-muted mb-2" id="modalCarregamentoTitulo">Processando dados...</h5>
                    <p class="text-muted mb-0" id="modalCarregamentoDescricao">Aguarde enquanto buscamos as informações</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let table;

        function voltarConsulta() {
            window.location.href = `../painel.php`;
        }

        // Funções para controlar o modal de carregamento
        function mostrarModalCarregamento(titulo = 'Processando dados...', descricao = 'Aguarde enquanto buscamos as informações') {
            $('#modalCarregamentoTitulo').text(titulo);
            $('#modalCarregamentoDescricao').text(descricao);
            $('#modalCarregamento').modal('show');
        }

        function esconderModalCarregamento() {
            $('#modalCarregamento').modal('hide');
        }

        function realizarConsulta(tabela, consultaId) {
            console.log('Iniciando consulta server-side...')
            
            // Mostrar modal de carregamento
            mostrarModalCarregamento('Executando consulta...', 'Buscando dados no banco de dados');
            
            $('#avisoInicial').removeClass('d-flex');
            $('#avisoInicial').addClass('d-none');
            
            // Armazenar parâmetros da consulta atual
            window.consultaAtual = {
                tabela: tabela,
                consulta_id: consultaId
            };
            
            // Fazer uma requisição inicial para obter metadados (colunas e tipos)
            $.ajax({
                url: 'consultar_dados.php',
                method: 'POST',
                data: {
                    tabela: tabela,
                    consulta_id: consultaId,
                    draw: 1,
                    start: 0,
                    length: 1, // Só queremos os metadados
                    search: { value: '' }
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.colunas) {
                        console.log('Metadados recebidos, inicializando DataTable server-side...');
                        // Chamar função para configurar tabela server-side
                        chamarTabelaServerSide(response.colunas, response.tipos_colunas);
                    } else {
                        console.error('Erro na consulta inicial:', response.mensagem || 'Erro desconhecido');
                        alert('Erro na consulta: ' + (response.mensagem || 'Erro desconhecido'));
                        $('#loadingDiv').hide();
                        esconderModalCarregamento();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro AJAX:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    alert('Erro na comunicação com o servidor: ' + error);
                    $('#loadingDiv').hide();
                    esconderModalCarregamento();
                }
            });
        }

        function chamarTabelaServerSide(colunas, tiposColunas) {
            console.log('=== NOVA CONSULTA SERVER-SIDE ===');
            console.log('Configurando tabela server-side processing');
            console.log('Colunas da nova consulta:', colunas);
            
            // Limpar filtros existentes
            $('#containerFiltros').empty();
            contadorFiltros = 0;
            
            // Limpar todos os filtros customizados do DataTables
            $.fn.dataTable.ext.search.length = 0;
            filtrosAtivos = {};
            
            // Armazenar todas as colunas e tipos globalmente
            window.todasColunas = colunas;
            window.tiposColunasAtual = tiposColunas;
            
            // Destruir completamente a tabela existente
            if ($.fn.DataTable.isDataTable('#tableResult')) {
                console.log('Destruindo tabela existente...');
                $('#tableResult').DataTable().clear().destroy();
            }
            
            // Recriar estrutura HTML limpa da tabela
            console.log('Recriando estrutura HTML da tabela...');
            $('#tableResult').remove();
            $('#subDivTables').append('<table id="tableResult" class="table table-striped table-hover" style="width:100%;"></table>');

            // Criar configuração das colunas (todas)
            let columnsConfig = [];
            colunas.forEach(function(coluna) {
                columnsConfig.push({
                    "data": coluna,
                    "title": coluna.toUpperCase()
                });
            });
            
            // Adicionar coluna de ação
            columnsConfig.push({
                "data": null,
                "title": "AÇÕES",
                "orderable": false,
                "render": function(data, type, row) {
                    return '<button class="btn btn-sm btn-info" onclick="mostrarDetalhes(' + JSON.stringify(row).replace(/"/g, '&quot;') + ')">Ver Mais</button>';
                }
            });
            
            // Criar array de colunas para esconder (da 11ª em diante, exceto a última que é ações)
            let colunasParaEsconder = [];
            for(let i = 10; i < colunas.length; i++) {
                colunasParaEsconder.push(i);
            }

            // DataTable com server-side processing
            table = $('#tableResult').DataTable({
                "serverSide": true,
                "processing": true,
                "ajax": {
                    "url": "consultar_dados.php",
                    "type": "POST",
                    "data": function(d) {
                        // Adicionar parâmetros da consulta atual
                        d.tabela = window.consultaAtual.tabela;
                        d.consulta_id = window.consultaAtual.consulta_id;
                        
                        // Adicionar filtros customizados
                        d.filtros_customizados = JSON.stringify(coletarFiltrosCustomizados());
                        
                        return d;
                    }
                },
                "columns": columnsConfig,
                "ordering": true,
                "pageLength": 25,
                "lengthMenu": [10, 25, 50, 100, 250, 500],
                "paging": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "destroy": true,
                "language": {
                    "decimal": ",",
                    "thousands": ".",
                    "lengthMenu": "Mostrar _MENU_ registros por página",
                    "zeroRecords": "Nenhum registro encontrado",
                    "info": "Mostrando de _START_ até _END_ de _TOTAL_ registros",
                    "infoEmpty": "Mostrando 0 até 0 de 0 registros",
                    "infoFiltered": "(filtrado de _MAX_ registros no total)",
                    "search": "Buscar:",
                    "paginate": {
                        "first": "Primeiro",
                        "last": "Último",
                        "next": "Próximo",
                        "previous": "Anterior"
                    },
                    "emptyTable": "Nenhum dado disponível na tabela"
                },
                "order": [[0, 'asc']],
                "columnDefs": [
                    {
                        "targets": colunasParaEsconder,
                        "visible": false
                    },
                    {
                        "targets": -1,
                        "orderable": false
                    }
                ],
                "initComplete": function() {
                    console.log('DataTable server-side inicializado com sucesso');
                    // Esconder loading e mostrar tabela
                    $('#loadingDiv').hide();
                    $('#tableResult').show();
                    
                    // Mostrar div de filtros após carregar a tabela
                    $('#filtros').show();
                    
                    // Esconder modal de carregamento
                    esconderModalCarregamento();
                }
            });
        }

        // Manter função original para compatibilidade (caso seja chamada em algum lugar)
        function chamarTabela(dados, colunas, tiposColunas) {
            console.log('Função chamarTabela original chamada - redirecionando para server-side...');
            chamarTabelaServerSide(colunas, tiposColunas);
        }

        // Função para mostrar detalhes no modal
        function mostrarDetalhes(dadosLinha) {
            let conteudo = '<div class="row">';
            
            window.todasColunas.forEach(function(coluna, index) {
                const valor = dadosLinha[coluna] || '-';
                conteudo += `
                    <div class="col-md-6 mb-3">
                        <strong>${coluna.toUpperCase()}:</strong><br>
                        <span class="text-muted">${valor}</span>
                    </div>
                `;
            });
            
            conteudo += '</div>';
            
            $('#modalDetalhesContent').html(conteudo);
            
            // Mostrar o modal
            const modal = new bootstrap.Modal(document.getElementById('modalDetalhes'));
            modal.show();
        }

        // Tipos de colunas agora vêm dinamicamente do banco de dados

        // Contador para IDs únicos dos filtros
        let contadorFiltros = 0;
        
        // Array para controlar filtros customizados ativos
        let filtrosAtivos = {};

        // Event listener para o botão de incluir filtro
        $(document).ready(function() {
            $('#btnIncluirFiltro').on('click', function() {
                adicionarFiltro();
            });
        });

        // Função para coletar todos os filtros customizados ativos
        function coletarFiltrosCustomizados() {
            const filtros = [];
            
            $('#containerFiltros .filtro-item').each(function() {
                const filtroId = $(this).attr('id');
                const selectFiltro = $(this).find('select');
                const coluna = selectFiltro.val();
                
                if (coluna && window.tiposColunasAtual && window.tiposColunasAtual[coluna]) {
                    const tipoCampo = window.tiposColunasAtual[coluna];
                    const filtroObj = {
                        campo: coluna,
                        tipo: tipoCampo
                    };
                    
                    // Coletar valores baseado no tipo
                    switch (tipoCampo) {
                        case 'texto':
                            const valorTexto = $(`#${filtroId}_valor`).val();
                            if (valorTexto) {
                                filtroObj.valor1 = valorTexto;
                                filtros.push(filtroObj);
                            }
                            break;
                            
                        case 'data':
                            const dataInicio = $(`#${filtroId}_de`).val();
                            const dataFim = $(`#${filtroId}_ate`).val();
                            if (dataInicio || dataFim) {
                                if (dataInicio) filtroObj.valor1 = dataInicio;
                                if (dataFim) filtroObj.valor2 = dataFim;
                                filtros.push(filtroObj);
                            }
                            break;
                            
                        case 'numero':
                            const numeroInicio = $(`#${filtroId}_de`).val();
                            const numeroFim = $(`#${filtroId}_ate`).val();
                            if (numeroInicio || numeroFim) {
                                if (numeroInicio) filtroObj.valor1 = numeroInicio;
                                if (numeroFim) filtroObj.valor2 = numeroFim;
                                filtros.push(filtroObj);
                            }
                            break;
                    }
                }
            });
            
            return filtros;
        }

        // Função para aplicar filtros customizados (reconstrói a tabela)
        function aplicarFiltrosCustomizados() {
            if (!table || !window.consultaAtual) {
                alert('Nenhuma consulta ativa para aplicar filtros.');
                return;
            }

            console.log('=== APLICANDO FILTROS CUSTOMIZADOS ===');
            
            // Mostrar modal de carregamento
            mostrarModalCarregamento('Aplicando filtros...', 'Processando dados com os filtros selecionados');
            
            const filtros = coletarFiltrosCustomizados();
            console.log('Filtros coletados:', filtros);
            
            // Atualizar badge com número de filtros
            atualizarBadgeFiltros(filtros.length);
            
            // Destacar filtros ativos visualmente
            destacarFiltrosAtivos();
            
            if (filtros.length === 0) {
                console.log('Nenhum filtro customizado ativo');
            }
            
            // Recarregar dados da tabela com novos filtros
            table.ajax.reload(null, false); // false = manter página atual
            
            // Esconder modal após um pequeno delay para permitir que a requisição seja processada
            setTimeout(() => {
                esconderModalCarregamento();
            }, 500);
            
            console.log('Tabela recarregada com filtros aplicados');
        }

        // Função para limpar todos os filtros
        function limparTodosFiltros() {
            if (!table) {
                return;
            }

            console.log('=== LIMPANDO TODOS OS FILTROS ===');
            
            // Mostrar modal de carregamento
            mostrarModalCarregamento('Limpando filtros...', 'Removendo todos os filtros aplicados');
            
            // Limpar filtros customizados
            $('#containerFiltros').empty();
            contadorFiltros = 0;
            
            // Limpar busca global do DataTables
            table.search('');
            
            // Atualizar badge
            atualizarBadgeFiltros(0);
            
            // Recarregar dados da tabela
            table.ajax.reload(null, false); // false = manter página atual
            
            // Esconder modal após um pequeno delay
            setTimeout(() => {
                esconderModalCarregamento();
            }, 500);
            
            console.log('Todos os filtros foram limpos');
        }

        // Função para atualizar o badge de filtros
        function atualizarBadgeFiltros(quantidade) {
            const badge = $('#badgeFiltros');
            if (quantidade > 0) {
                badge.text(quantidade);
                badge.show();
            } else {
                badge.hide();
            }
        }

        // Função para destacar filtros ativos visualmente
        function destacarFiltrosAtivos() {
            $('#containerFiltros .filtro-item').each(function() {
                const filtroId = $(this).attr('id');
                const selectFiltro = $(this).find('select');
                const coluna = selectFiltro.val();
                
                if (coluna && window.tiposColunasAtual && window.tiposColunasAtual[coluna]) {
                    const tipoCampo = window.tiposColunasAtual[coluna];
                    let temValor = false;
                    
                    // Verificar se o filtro tem valores
                    switch (tipoCampo) {
                        case 'texto':
                            temValor = $(`#${filtroId}_valor`).val() !== '';
                            break;
                        case 'data':
                            temValor = $(`#${filtroId}_de`).val() !== '' || $(`#${filtroId}_ate`).val() !== '';
                            break;
                        case 'numero':
                            temValor = $(`#${filtroId}_de`).val() !== '' || $(`#${filtroId}_ate`).val() !== '';
                            break;
                    }
                    
                    // Aplicar classe visual
                    if (temValor) {
                        $(this).addClass('filtro-ativo');
                    } else {
                        $(this).removeClass('filtro-ativo');
                    }
                }
            });
        }

        function adicionarFiltro() {
            contadorFiltros++;
            const filtroId = 'filtro_' + contadorFiltros;
            
            // Criar options do select com as colunas disponíveis em ordem alfabética
            let options = '<option value="">Selecione uma coluna...</option>';
            
            // Ordenar colunas alfabeticamente
            const colunasOrdenadas = [...window.todasColunas].sort((a, b) => {
                return a.toLowerCase().localeCompare(b.toLowerCase(), 'pt-BR');
            });
            
            colunasOrdenadas.forEach(function(coluna) {
                options += `<option value="${coluna}">${coluna.toUpperCase()}</option>`;
            });

            const filtroHTML = `
                <div class="filtro-item" id="${filtroId}">
                    <select class="form-select" style="width: 200px;" onchange="criarCamposFiltro('${filtroId}', this.value)">
                        ${options}
                </select>
                    <div class="filtro-campos" id="${filtroId}_campos">
                        <!-- Campos de filtro serão inseridos aqui -->
                    </div>
                    <button class="btn btn-sm btn-danger filtro-remove" onclick="removerFiltro('${filtroId}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;

            $('#containerFiltros').append(filtroHTML);
        }

        function criarCamposFiltro(filtroId, coluna) {
            if (!coluna) {
                $(`#${filtroId}_campos`).empty();
                return;
            }

            const camposDiv = $(`#${filtroId}_campos`);
            camposDiv.empty();

            // Determinar tipo do campo usando os tipos dinâmicos do banco
            let tipoCampo = 'texto'; // padrão
            if (window.tiposColunasAtual && window.tiposColunasAtual[coluna]) {
                tipoCampo = window.tiposColunasAtual[coluna];
            }

            let camposHTML = '';

            switch (tipoCampo) {
                case 'data':
                    camposHTML = `
                        <label class="form-label">De:</label>
                        <input type="date" class="form-control" style="width: 150px;" 
                               id="${filtroId}_de">
                        <label class="form-label">Até:</label>
                        <input type="date" class="form-control" style="width: 150px;"
                               id="${filtroId}_ate">
                    `;
                    break;
                case 'numero':
                    camposHTML = `
                        <input type="number" class="form-control" placeholder="De" style="width: 100px;"
                               id="${filtroId}_de">
                        <span>até</span>
                        <input type="number" class="form-control" placeholder="Até" style="width: 100px;"
                               id="${filtroId}_ate">
                    `;
                    break;
                default: // texto
                    camposHTML = `
                        <input type="text" class="form-control" placeholder="Digite para filtrar..." style="width: 250px;"
                               id="${filtroId}_valor">
                    `;
                    break;
            }

            camposDiv.html(camposHTML);
        }

        // OBSERVAÇÃO: A função aplicarFiltro foi removida pois agora usamos server-side processing
        // Os filtros são aplicados no servidor quando o botão "Aplicar Filtros" é clicado

        function removerFiltro(filtroId) {
            console.log('Removendo filtro:', filtroId);
            
            // Remover o elemento visual do filtro
            $(`#${filtroId}`).remove();
            
            // Aplicar filtros restantes automaticamente
            if (table) {
                aplicarFiltrosCustomizados();
            }
        }

        function plotarNoMapa() {
            // Verificar se há uma consulta ativa
            if (!table || !window.consultaAtual) {
                alert('Nenhuma consulta ativa. Execute uma consulta primeiro.');
                return;
            }

            // Mostrar modal de carregamento
            mostrarModalCarregamento('Preparando mapa...', 'Coletando dados filtrados para visualização no mapa');

            // Desabilitar o botão por 10 segundos
            const btnPlotarMapa = document.getElementById('btnPlotarMapa');
            
            btnPlotarMapa.disabled = true;
            
            // Reabilitar após 10 segundos
            setTimeout(() => {
                btnPlotarMapa.disabled = false;
            }, 10000);

            console.log('=== PLOTAR NO MAPA (SERVER-SIDE) ===');
            
            // Com server-side processing, precisamos fazer uma requisição especial
            // para obter TODOS os dados filtrados (não apenas a página atual)
            
            // Obter filtro de busca global do DataTables
            const buscaGlobal = table.search();
            
            // Coletar filtros customizados para o mapa
            const filtrosParaMapa = coletarFiltrosCustomizados();

            console.log('Buscando TODOS os dados filtrados para o mapa...');
            
            // Fazer requisição para obter TODOS os dados filtrados (sem paginação)
            $.ajax({
                url: 'consultar_dados.php',
                method: 'POST',
                data: {
                    tabela: window.consultaAtual.tabela,
                    consulta_id: window.consultaAtual.consulta_id,
                    draw: 999, // Não importa para o mapa
                    start: 0,
                    length: 999999, // Pegar todos os registros
                    search: { value: buscaGlobal },
                    filtros_customizados: JSON.stringify(filtrosParaMapa)
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        const dadosFiltrados = response.data;
                        
                        console.log('Dados recebidos para o mapa:');
                        console.log('- Total de registros:', response.recordsTotal);
                        console.log('- Registros filtrados:', response.recordsFiltered);
                        console.log('- Registros recebidos:', dadosFiltrados.length);
                        
                        if (dadosFiltrados.length === 0) {
                            alert('Nenhum registro encontrado com os filtros aplicados.');
                            esconderModalCarregamento();
                            return;
                        }

                        try {
                            // Preparar dados para envio
                            const dadosJSON = JSON.stringify(dadosFiltrados);
                            const filtrosJSON = JSON.stringify(filtrosParaMapa);
                            
                            console.log('Enviando para o mapa:');
                            console.log('- Registros:', dadosFiltrados.length);
                            console.log('- Filtros aplicados:', filtrosParaMapa.length);
                            console.log('- Tamanho dos dados (caracteres):', dadosJSON.length);
                            
                            // Criar formulário oculto para envio via POST
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = 'mapa_plot.php';
                            form.target = '_blank';
                            form.style.display = 'none';
                            
                            // Campo para dados
                            const inputDados = document.createElement('input');
                            inputDados.type = 'hidden';
                            inputDados.name = 'dados';
                            inputDados.value = dadosJSON;
                            form.appendChild(inputDados);
                            
                            // Campo para filtros
                            const inputFiltros = document.createElement('input');
                            inputFiltros.type = 'hidden';
                            inputFiltros.name = 'filtros';
                            inputFiltros.value = filtrosJSON;
                            form.appendChild(inputFiltros);
                            
                            // Adicionar ao DOM e submeter
                            document.body.appendChild(form);
                            form.submit();
                            
                            // Remover formulário após envio
                            setTimeout(() => {
                                document.body.removeChild(form);
                            }, 100);
                            
                            // Esconder modal após envio bem-sucedido
                            esconderModalCarregamento();
                            
                            console.log('Dados enviados via POST com sucesso!');
                            
                        } catch (error) {
                            console.error('Erro ao preparar dados para o mapa:', error);
                            alert('Erro ao processar dados para o mapa: ' + error.message);
                            esconderModalCarregamento();
                        }
                    } else {
                        console.error('Erro ao obter dados para o mapa:', response.mensagem);
                        alert('Erro ao obter dados para o mapa: ' + (response.mensagem || 'Erro desconhecido'));
                        esconderModalCarregamento();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro AJAX ao obter dados para o mapa:', error);
                    alert('Erro na comunicação com o servidor: ' + error);
                    esconderModalCarregamento();
                }
            });
        }

    </script>

</body>

</html>