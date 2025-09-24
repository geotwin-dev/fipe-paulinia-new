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
        }

        .filtro-campos {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filtro-remove {
            margin-left: auto;
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
                    <button id="btnIncluirFiltro" class="btn btn-primary">+Filtro</button>
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

    <script>
        let table;

        function voltarConsulta() {
            window.location.href = `../painel.php`;
        }

        function realizarConsulta(tabela, consultaId) {
            console.log('Pesquisando...')
            
            $('#avisoInicial').removeClass('d-flex');
            $('#avisoInicial').addClass('d-none');
            // Mostrar loading e esconder tabela
            $('#loadingDiv').show();
            $('#tableResult').hide();

            $.ajax({
                url: 'consultar_dados.php',
                method: 'POST',
                data: {
                    tabela: tabela,
                    consulta_id: consultaId,
                },
                dataType: 'json',
                success: function(response) {
                    //console.log('Resposta recebida:', response);
                    
                    if (response.success && response.data && response.colunas) {
                        //console.log('Total de registros encontrados:', response.recordsTotal);
                        //console.log('Registros carregados:', response.recordsShown);
                        //console.log('Tipos das colunas:', response.tipos_colunas);
                        
                        // Chamar função para exibir na tabela (client-side)
                        chamarTabela(response.data, response.colunas, response.tipos_colunas);
                    } else {
                        console.error('Erro na consulta:', response.mensagem || 'Erro desconhecido');
                        alert('Erro na consulta: ' + (response.mensagem || 'Erro desconhecido'));
                        // Esconder loading em caso de erro
                        $('#loadingDiv').hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro AJAX:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    alert('Erro na comunicação com o servidor: ' + error);
                    // Esconder loading em caso de erro
                    $('#loadingDiv').hide();
                }
            });
        }

        function chamarTabela(dados, colunas, tiposColunas) {
            //console.log('=== NOVA CONSULTA ===');
            //console.log('Configurando tabela client-side com', dados.length, 'registros');
            //console.log('Colunas da nova consulta:', colunas);
            
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
                //console.log('Destruindo tabela existente...');
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

            // DataTable com limitação simples de colunas
            table = $('#tableResult').DataTable({
                "data": dados,
                "columns": columnsConfig,
                "ordering": true,
                "pageLength": 10,
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
                    "processing": "Processando...",
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
                    console.log('DataTable com colunas limitadas inicializado');
                }
            });

            // Esconder loading e mostrar tabela
            $('#loadingDiv').hide();
            $('#tableResult').show();
            
            // Mostrar div de filtros após carregar a tabela
            $('#filtros').show();
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

        function adicionarFiltro() {
            contadorFiltros++;
            const filtroId = 'filtro_' + contadorFiltros;
            
            // Criar options do select com as colunas disponíveis
            let options = '<option value="">Selecione uma coluna...</option>';
            window.todasColunas.forEach(function(coluna) {
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
                               onchange="aplicarFiltro('${filtroId}', '${coluna}', 'data')"
                               id="${filtroId}_de">
                        <label class="form-label">Até:</label>
                        <input type="date" class="form-control" style="width: 150px;"
                               onchange="aplicarFiltro('${filtroId}', '${coluna}', 'data')"
                               id="${filtroId}_ate">
                    `;
                    break;
                case 'numero':
                    camposHTML = `
                        <input type="number" class="form-control" placeholder="De" style="width: 100px;"
                               oninput="aplicarFiltro('${filtroId}', '${coluna}', 'numero')"
                               id="${filtroId}_de">
                        <span>até</span>
                        <input type="number" class="form-control" placeholder="Até" style="width: 100px;"
                               oninput="aplicarFiltro('${filtroId}', '${coluna}', 'numero')"
                               id="${filtroId}_ate">
                    `;
                    break;
                default: // texto
                    camposHTML = `
                        <input type="text" class="form-control" placeholder="Digite para filtrar..." style="width: 250px;"
                               oninput="aplicarFiltro('${filtroId}', '${coluna}', 'texto')"
                               id="${filtroId}_valor">
                    `;
                    break;
            }

            camposDiv.html(camposHTML);
        }

        function aplicarFiltro(filtroId, coluna, tipo) {
            // Encontrar índice da coluna
            const colunaIndex = window.todasColunas.indexOf(coluna);
            if (colunaIndex === -1) return;

            let valorFiltro = '';

            switch (tipo) {
                case 'texto':
                    valorFiltro = $(`#${filtroId}_valor`).val();
                    break;
                    
                case 'data':
                    const dataInicio = $(`#${filtroId}_de`).val();
                    const dataFim = $(`#${filtroId}_ate`).val();
                    
                    // Lógica inteligente para filtro de datas
                    if (!dataInicio && !dataFim) {
                        // Ambos vazios = mostrar tudo
                        valorFiltro = '';
                    } else if (dataInicio && dataFim) {
                        // Ambos preenchidos = intervalo entre as datas
                        valorFiltro = function(settings, data, dataIndex) {
                            const valorColuna = data[colunaIndex];
                            if (!valorColuna) return false;
                            
                            // Extrair apenas a parte da data (YYYY-MM-DD) do datetime
                            const dataValor = valorColuna.split(' ')[0];
                            return dataValor >= dataInicio && dataValor <= dataFim;
                        };
                    } else if (dataInicio) {
                        // Só data início = a partir desta data
                        valorFiltro = function(settings, data, dataIndex) {
                            const valorColuna = data[colunaIndex];
                            if (!valorColuna) return false;
                            
                            const dataValor = valorColuna.split(' ')[0];
                            return dataValor >= dataInicio;
                        };
                    } else if (dataFim) {
                        // Só data fim = até esta data
                        valorFiltro = function(settings, data, dataIndex) {
                            const valorColuna = data[colunaIndex];
                            if (!valorColuna) return false;
                            
                            const dataValor = valorColuna.split(' ')[0];
                            return dataValor <= dataFim;
                        };
                    }
                    break;
                    
                case 'numero':
                    const valorInicio = $(`#${filtroId}_de`).val();
                    const valorFim = $(`#${filtroId}_ate`).val();
                    
                    if (!valorInicio && !valorFim) {
                        valorFiltro = '';
                    } else if (valorInicio && valorFim) {
                        // Intervalo numérico
                        valorFiltro = function(settings, data, dataIndex) {
                            const valorColuna = parseFloat(data[colunaIndex]);
                            if (isNaN(valorColuna)) return false;
                            return valorColuna >= parseFloat(valorInicio) && valorColuna <= parseFloat(valorFim);
                        };
                    } else if (valorInicio) {
                        // A partir do valor
                        valorFiltro = function(settings, data, dataIndex) {
                            const valorColuna = parseFloat(data[colunaIndex]);
                            if (isNaN(valorColuna)) return false;
                            return valorColuna >= parseFloat(valorInicio);
                        };
                    } else if (valorFim) {
                        // Até o valor
                        valorFiltro = function(settings, data, dataIndex) {
                            const valorColuna = parseFloat(data[colunaIndex]);
                            if (isNaN(valorColuna)) return false;
                            return valorColuna <= parseFloat(valorFim);
                        };
                    }
                    break;
            }

            // Aplicar filtro na coluna específica do DataTables
            if (table) {
                // Remover filtro anterior desta coluna se existir
                if (filtrosAtivos[filtroId]) {
                    const index = $.fn.dataTable.ext.search.indexOf(filtrosAtivos[filtroId]);
                    if (index > -1) {
                        $.fn.dataTable.ext.search.splice(index, 1);
                    }
                }
                
                if (typeof valorFiltro === 'function') {
                    // Para filtros customizados (datas e números)
                    filtrosAtivos[filtroId] = valorFiltro;
                    $.fn.dataTable.ext.search.push(valorFiltro);
                    table.draw();
                } else {
                    // Para filtros de texto simples
                    if (valorFiltro === '') {
                        // Limpar filtro de texto
                        delete filtrosAtivos[filtroId];
                    }
                    table.column(colunaIndex).search(valorFiltro).draw();
                }
            }
        }

        function removerFiltro(filtroId) {
            // Obter informações do filtro antes de remover
            const selectFiltro = $(`#${filtroId} select`);
            const coluna = selectFiltro.val();
            
            if (coluna && table) {
                // Encontrar índice da coluna
                const colunaIndex = window.todasColunas.indexOf(coluna);
                
                if (colunaIndex !== -1) {
                    // Resetar filtro da coluna específica (para filtros de texto)
                    table.column(colunaIndex).search('');
                }
                
                // Remover filtro customizado se existir (para datas e números)
                if (filtrosAtivos[filtroId]) {
                    const index = $.fn.dataTable.ext.search.indexOf(filtrosAtivos[filtroId]);
                    if (index > -1) {
                        $.fn.dataTable.ext.search.splice(index, 1);
                    }
                    delete filtrosAtivos[filtroId];
                }
                
                // Redesenhar tabela
                table.draw();
            }
            
            // Remover o elemento visual do filtro
            $(`#${filtroId}`).remove();
        }

        function plotarNoMapa() {
            // Verificar se há dados na tabela
            if (!table || !table.data().count()) {
                alert('Nenhum dado para plotar no mapa. Execute uma consulta primeiro.');
                return;
            }

            console.log('=== PLOTAR NO MAPA ===');
            
            // Obter dados FILTRADOS da tabela (apenas os visíveis)
            const dadosFiltrados = table.rows({ search: 'applied' }).data().toArray();
            console.log('Total de registros na tabela:', table.data().count());
            console.log('Registros após filtros:', dadosFiltrados.length);
            console.log('Dados filtrados que serão enviados:', dadosFiltrados);

            // Coletar informações dos filtros aplicados para referência
            const filtrosParaMapa = [];
            
            // Percorrer todos os filtros no containerFiltros
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
                                filtrosParaMapa.push(filtroObj);
                            }
                            break;
                            
                        case 'data':
                            const dataInicio = $(`#${filtroId}_de`).val();
                            const dataFim = $(`#${filtroId}_ate`).val();
                            if (dataInicio || dataFim) {
                                if (dataInicio) filtroObj.valor1 = dataInicio;
                                if (dataFim) filtroObj.valor2 = dataFim;
                                filtrosParaMapa.push(filtroObj);
                            }
                            break;
                            
                        case 'numero':
                            const numeroInicio = $(`#${filtroId}_de`).val();
                            const numeroFim = $(`#${filtroId}_ate`).val();
                            if (numeroInicio || numeroFim) {
                                if (numeroInicio) filtroObj.valor1 = numeroInicio;
                                if (numeroFim) filtroObj.valor2 = numeroFim;
                                filtrosParaMapa.push(filtroObj);
                            }
                            break;
                    }
                }
            });

            // Verificar se há dados para enviar
            if (dadosFiltrados.length === 0) {
                alert('Nenhum registro encontrado com os filtros aplicados.');
                return;
            }

            try {
                // Preparar dados para envio
                const dadosJSON = JSON.stringify(dadosFiltrados);
                const filtrosJSON = JSON.stringify(filtrosParaMapa);
                
                console.log('Dados enviados para o mapa:');
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
                
                console.log('Dados enviados via POST com sucesso!');
                
            } catch (error) {
                console.error('Erro ao preparar dados para o mapa:', error);
                alert('Erro ao processar dados para o mapa: ' + error.message);
            }
        }

    </script>

</body>

</html>