<?php

session_start();

if(!isset($_SESSION['usuario'])){
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

    <!--Conexão com fonts do Google-->
    <link href='https://fonts.googleapis.com/css?family=Muli' rel='stylesheet'>

    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <!--Conexão com biblioteca de BUFFER para poligono-->
    <script src="https://unpkg.com/@turf/turf@6.5.0/turf.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.11.0/proj4.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <style>
        html,
        body {
            width: 100%;
            height: 100vh;
            margin: 0;
            padding: 0;
            background-color: white;
            box-sizing: border-box;
        }

        #containerNew {
            width: 100%;
            min-height: 500px;
        }

        .divContainerMap {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .dropdown-menu {
            padding: 0 30px;
        }

        /* Estilo personalizado para controles do DataTables - igual ao table-dark */
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            background: #212529 !important;
            color: white !important;
            border: 1px solid #495057 !important;
            margin: 0 2px !important;
            border-radius: 4px !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #495057 !important;
            color: white !important;
            border: 1px solid #6c757d !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #495057 !important;
            color: white !important;
            border: 1px solid #6c757d !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            background: #212529 !important;
            color: #6c757d !important;
            border: 1px solid #495057 !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            background: #212529 !important;
            color: #6c757d !important;
            border: 1px solid #495057 !important;
        }

        /* Estilo para informações da tabela */
        .dataTables_info {
            color: white !important;
            font-weight: 500;
        }

        /* Estilo para seletor de registros por página */
        .dataTables_length select {
            background: #212529 !important;
            color: white !important;
            border: 1px solid #495057 !important;
            border-radius: 4px !important;
        }

        .dataTables_length select option {
            background: #212529 !important;
            color: white !important;
        }

        .dataTables_length label {
            color: white !important;
        }

        /* Estilo para campo de busca */
        .dataTables_filter input {
            background: #212529 !important;
            color: white !important;
            border: 1px solid #495057 !important;
            border-radius: 4px !important;
        }

        .dataTables_filter input::placeholder {
            color: #adb5bd !important;
        }

        .dataTables_filter label {
            color: white !important;
        }

        /* Estilo para botões de exportação */
        .dt-buttons .btn {
            background: #212529 !important;
            color: white !important;
            border: 1px solid #495057 !important;
        }

        .dt-buttons .btn:hover {
            background: #495057 !important;
            color: white !important;
            border: 1px solid #6c757d !important;
        }

        /* Estilo para o wrapper geral */
        .dataTables_wrapper {
            background: #212529 !important;
            color: white !important;
        }

        /* Estilo para o footer da tabela */
        .dataTables_wrapper .dataTables_paginate,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            background: #212529 !important;
            color: white !important;
            padding: 10px !important;
        }

        .paginate_button.page-item.active .page-link {
            background-color: #198754 !important;
            color: white !important;
            border: none !important;
        }

        .paginate_button.page-item .page-link {
            background-color: white;
            color: black;
            border: none !important;
        }

        .paginate_button.page-item .page-link:hover {
            background-color: rgb(133, 133, 133) !important;
            color: white !important;
            border: none !important;
        }

        .divBotoesConsulta {
            width: 100%;
            padding: 10px 24px 0 24px;
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

    </style>

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
                        <i class="fas fa-external-link-alt"></i> Voltar para Painel
                    </button>
                    <a style="margin-left: 10px;" href="../logout.php" class="btn btn-secondary">Sair</a>
                </div>
            </div>
        </nav>

        <div class="divBotoesConsulta">
            <!-- Botões de Consulta -->
            <button class="btn btn-warning" id="btnConsulta1" onclick="realizarConsulta('cadastro', 1)">
                <i class="fas fa-search"></i> Imóveis que precisam ser desdobrados
            </button>

            <button class="btn btn-warning" style="background-color:rgb(255, 102, 0); border-color: rgb(255, 102, 0);" id="btnConsulta2" onclick="realizarConsulta('cadastro', 2)">
                <i class="fas fa-search"></i> Imóveis que precisam ser agrupados
            </button>

            <button class="btn btn-danger" id="btnConsulta3" onclick="realizarConsulta('desenhos', 3)">
                <i class="fas fa-search"></i> Imóveis que precisam ser cadastrados
            </button>

            <button class="btn btn-success" id="btnConsulta4" onclick="realizarConsulta('cadastro', 4)">
                <i class="fas fa-search"></i> Imóveis já geolocalizados
            </button>

            <button class="btn btn-primary" style="background-color:rgb(174, 0, 255); border-color: rgb(174, 0, 255);" id="btnConsulta5" onclick="realizarConsulta('cadastro', 5)">
                <i class="fas fa-search"></i> Imóveis não geolocalizados
            </button>
        </div>

        <div id="containerNew">

        </div>
    </div>

    <script>
        let dataTable = null;

        function voltarConsulta() {
            window.location.href = `../painel.php`;
        }

        // Função genérica para realizar consultas
        function realizarConsulta(tabela, consultaId) {
            // Mostrar loading
            $('#containerNew').html(`
                <div class="d-flex justify-content-center align-items-center" style="height: 100%;">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2">Carregando dados...</p>
                    </div>
                </div>
            `);

            // Inicializar DataTable com paginação do servidor
            exibirTabelaComPaginacao(tabela, consultaId);
        }

        // Função para exibir a tabela com paginação do servidor
        function exibirTabelaComPaginacao(tabela, consultaId) {
            // Primeiro, carregar as colunas para configurar o DataTable
            carregarColunasEInicializar(tabela, consultaId);
        }

        // Função para carregar colunas e inicializar DataTable
        function carregarColunasEInicializar(tabela, consultaId) {
            $.ajax({
                url: 'consultar_dados.php',
                method: 'POST',
                data: {
                    tabela: tabela,
                    consulta_id: consultaId,
                    start: 0,
                    length: 1
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.colunas) {
                        // Configurar colunas dinamicamente
                        let colunas = response.colunas.map(function(coluna) {
                            return {
                                data: coluna,
                                title: coluna.replace(/_/g, ' ').toUpperCase()
                            };
                        });

                        // Criar HTML da tabela
                        let html = `
                            <div class="container-fluid p-3">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">
                                                    <i class="fas fa-table"></i> 
                                                    Consulta ${consultaId} - Tabela: ${tabela.toUpperCase()}
                                                    <span id="infoRegistros" class="badge bg-secondary ms-2">Carregando...</span>
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table id="tabelaResultado" class="table table-striped table-hover">
                                                        <thead class="table-dark">
                                                            <tr>
                        `;

                        // Adicionar cabeçalhos
                        response.colunas.forEach(function(coluna) {
                            html += `<th>${coluna.replace(/_/g, ' ').toUpperCase()}</th>`;
                        });

                        html += `
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <!-- Dados serão carregados via AJAX -->
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;

                        $('#containerNew').html(html);

                        // Destruir DataTable anterior se existir
                        if (dataTable) {
                            dataTable.destroy();
                        }

                        // Inicializar DataTable com paginação do servidor
                        dataTable = $('#tabelaResultado').DataTable({
                            processing: true,
                            serverSide: true,
                            ajax: {
                                url: 'consultar_dados.php',
                                type: 'POST',
                                data: function(d) {
                                    d.tabela = tabela;
                                    d.consulta_id = consultaId;
                                },
                                error: function(xhr, error, thrown) {
                                    $('#containerNew').html(`
                                        <div class="alert alert-danger" role="alert">
                                            <h4 class="alert-heading">Erro de Conexão!</h4>
                                            <p>Erro ao carregar os dados: ${thrown}</p>
                                        </div>
                                    `);
                                }
                            },
                            columns: colunas,
                            responsive: true,
                            language: {
                                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json',
                                processing: "Processando...",
                                loadingRecords: "Carregando registros...",
                                emptyTable: "Nenhum registro encontrado"
                            },
                            pageLength: 25,
                            lengthMenu: [
                                [10, 25, 50, 100, 500],
                                [10, 25, 50, 100, 500]
                            ],
                            order: tabela === 'cadastro' ? [
                                [7, 'asc'],
                                [8, 'asc'],
                                [9, 'asc'],
                                [10, 'asc']
                            ] : [
                                [6, 'asc'],
                                [7, 'asc'],
                                [8, 'asc'],
                                [9, 'asc']
                            ],
                            dom: 'Bfrtip',
                            buttons: [
                                'copy', 'csv', 'excel', 'pdf', 'print'
                            ],
                            drawCallback: function(settings) {
                                let info = this.api().page.info();
                                $('#infoRegistros').text(`${info.recordsTotal} registros total`);
                            }
                        });
                    } else {
                        $('#containerNew').html(`
                            <div class="alert alert-danger" role="alert">
                                <h4 class="alert-heading">Erro!</h4>
                                <p>Não foi possível carregar as colunas da tabela</p>
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    $('#containerNew').html(`
                        <div class="alert alert-danger" role="alert">
                            <h4 class="alert-heading">Erro de Conexão!</h4>
                            <p>Erro ao carregar as colunas: ${error}</p>
                        </div>
                    `);
                }
            });
        }

        // Inicializar página
        $(document).ready(function() {
            $('#containerNew').html(`
                <div class="d-flex justify-content-center align-items-center" style="height: 100%;">
                    <div class="text-center">
                        <i class="fas fa-database fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">Selecione uma consulta para começar</h4>
                        <p class="text-muted">Use os botões acima para realizar consultas no banco de dados</p>
                    </div>
                </div>
            `);
        });
    </script>

</body>

</html>