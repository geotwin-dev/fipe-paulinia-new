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
                        <i class="fas fa-external-link-alt"></i> Voltar para Painel
                    </button>
                    <a style="margin-left: 10px;" href="../logout.php" class="btn btn-secondary">Sair</a>
                </div>
            </div>
        </nav>

        <div id="btns_queries">
            <!-- Botões de Consulta -->
            <button class="btn btn-warning btnAut" id="btnConsulta1" onclick="realizarConsulta('cadastro', 1)">
                <i class="fas fa-search"></i> Imóveis que precisam ser desdobrados
            </button>

            <button class="btn btn-warning btnAut" style="background-color:rgb(255, 102, 0); border-color: rgb(255, 102, 0);" id="btnConsulta2" onclick="realizarConsulta('cadastro', 2)">
                <i class="fas fa-search"></i> Imóveis que precisam ser agrupados
            </button>

            <button class="btn btn-danger btnAut" id="btnConsulta3" onclick="realizarConsulta('desenhos', 3)">
                <i class="fas fa-search"></i> Imóveis que precisam ser cadastrados
            </button>

            <button class="btn btn-success btnAut" id="btnConsulta4" onclick="realizarConsulta('cadastro', 4)">
                <i class="fas fa-search"></i> Imóveis já geolocalizados
            </button>

            <button class="btn btn-primary btnAut" style="background-color:rgb(174, 0, 255); border-color: rgb(174, 0, 255);" id="btnConsulta5" onclick="realizarConsulta('cadastro', 5)">
                <i class="fas fa-search"></i> Imóveis não geolocalizados
            </button>
        </div>

        <div id="filtros">
            <div id="subDivFiltros">
                <button onclick="incluir_filtro()" class="btn btn-primary">+Filtro</button>

                <div id="containerFiltros">

                </div>
            </div>
        </div>

        <div id="tables-container">
            <div id="subDivTables">
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

        function incluir_filtro() {

            let selectBtn = `<div class='divSelectCreate'>
                <select style='width: 260px' class='form-select selectDinamicEntry'>
                    <option selected data-tabela=0 data-campo=0 value=0></option>
                    <option data-tabela='cadastro' data-campo='texto' value='id'>ID</option>
                    <option data-tabela='cadastro' data-campo='texto' value='inscricao'>INSCRICAO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='imob_id'>IMOB_ID</option>
                    <option data-tabela='cadastro' data-campo='texto' value='cod_reduzido'>COD_REDUZIDO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='matricula'>MATRICULA</option>
                    <option data-tabela='cadastro' data-campo='texto' value='logradouro'>LOGRADOURO</option>
                    <option data-tabela='cadastro' data-campo='intervalo' value='numero'>NUMERO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='bairro'>BAIRRO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='cara_quarteirao'>CARA_QUARTEIRAO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='quadra'>QUADRA</option>
                    <option data-tabela='cadastro' data-campo='texto' value='lote'>LOTE</option>
                    <option data-tabela='cadastro' data-campo='texto' value='lote_base'>LOTE_BASE</option>
                    <option data-tabela='cadastro' data-campo='texto' value='qdra_ins_parametro'>QDRA_INS_PARAMETRO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='situacao'>SITUACAO</option>
                    <option data-tabela='cadastro' data-campo='intervalo' value='total_construido'>TOTAL_CONSTRUIDO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='cep_segmento'>CEP_SEGMENTO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='cod_pessoa'>COD_PESSOA</option>
                    <option data-tabela='cadastro' data-campo='texto' value='nome_pessoa'>NOME_PESSOA</option>
                    <option data-tabela='cadastro' data-campo='texto' value='cpf'>CPF</option>
                    <option data-tabela='cadastro' data-campo='texto' value='cnpj'>CNPJ</option>
                    <option data-tabela='cadastro' data-campo='texto' value='frac_ideal'>FRAC_IDEAL</option>
                    <option data-tabela='cadastro' data-campo='intervalo' value='area_terreno'>AREA_TERRENO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='complemento'>COMPLEMENTO</option>
                    <option data-tabela='cadastro' data-campo='data' value='inclusao'>INCLUSAO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='tipo_edificacao'>TIPO_EDIFICACAO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='tipo_utilizacao'>TIPO_UTILIZACAO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='categoria_propriedade'>CATEGORIA_PROPRIEDADE</option>
                    <option data-tabela='cadastro' data-campo='intervalo' value='area_excedente'>AREA_EXCEDENTE</option>
                    <option data-tabela='cadastro' data-campo='texto' value='cara_quarteirao_alt'>CARA_QUARTEIRAO_ALT</option>
                    <option data-tabela='cadastro' data-campo='texto' value='cod_valor'>COD_VALOR</option>
                    <option data-tabela='cadastro' data-campo='texto' value='zona'>ZONA</option>
                    <option data-tabela='cadastro' data-campo='texto' value='cat_via'>CAT_VIA</option>
                    <option data-tabela='cadastro' data-campo='texto' value='coef_aprov'>COEF_APROV</option>
                    <option data-tabela='cadastro' data-campo='boleano' value='terreo'>TERREO</option>
                    <option data-tabela='cadastro' data-campo='boleano' value='demais'>DEMAIS</option>
                    <option data-tabela='cadastro' data-campo='boleano' value='fator_prof_gleba'>FATOR_PROF_GLEBA</option>
                    <option data-tabela='cadastro' data-campo='boleano' value='viela_sanit'>VIELA_SANIT</option>
                    <option data-tabela='cadastro' data-campo='boleano' value='segundo_pavimento'>SEGUNDO_PAVIMENTO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='nome_loteamento'>NOME_LOTEAMENTO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='face_quadra'>FACE_QUADRA</option>
                    <option data-tabela='cadastro' data-campo='texto' value='demais_faces'>DEMAIS_FACES</option>
                    <option data-tabela='cadastro' data-campo='texto' value='face'>FACE</option>
                    <option data-tabela='cadastro' data-campo='texto' value='testada_principal'>TESTADA_PRINCIPAL</option>
                    <option data-tabela='cadastro' data-campo='texto' value='testada_2'>TESTADA_2</option>
                    <option data-tabela='cadastro' data-campo='texto' value='testada_3'>TESTADA_3</option>
                    <option data-tabela='cadastro' data-campo='texto' value='testada'>TESTADA</option>
                    <option data-tabela='cadastro' data-campo='select' value='utilizacao_area_a'>UTILIZACAO_AREA_A</option>
                    <option data-tabela='cadastro' data-campo='select' value='utilizacao_area_b'>UTILIZACAO_AREA_B</option>
                    <option data-tabela='cadastro' data-campo='select' value='utilizacao_area_c'>UTILIZACAO_AREA_C</option>
                    <option data-tabela='cadastro' data-campo='texto' value='num_apro_proj_1'>NUM_APRO_PROJ_1</option>
                    <option data-tabela='cadastro' data-campo='data' value='dt_apro_proj_1'>DT_APRO_PROJ_1</option>
                    <option data-tabela='cadastro' data-campo='intervalo' value='area_apro_proj_1'>AREA_APRO_PROJ_1</option>
                    <option data-tabela='cadastro' data-campo='texto' value='num_habitese_1'>NUM_HABITESE_1</option>
                    <option data-tabela='cadastro' data-campo='data' value='dt_habitese_1'>DT_HABITESE_1</option>
                    <option data-tabela='cadastro' data-campo='intervalo' value='area_habitese_1'>AREA_HABITESE_1</option>
                    <option data-tabela='cadastro' data-campo='texto' value='num_apro_proj_2'>NUM_APRO_PROJ_2</option>
                    <option data-tabela='cadastro' data-campo='data' value='dt_apro_proj_2'>DT_APRO_PROJ_2</option>
                    <option data-tabela='cadastro' data-campo='intervalo' value='area_apro_proj_2'>AREA_APRO_PROJ_2</option>
                    <option data-tabela='cadastro' data-campo='texto' value='num_habitese_2'>NUM_HABITESE_2</option>
                    <option data-tabela='cadastro' data-campo='data' value='dt_habitese_2'>DT_HABITESE_2</option>
                    <option data-tabela='cadastro' data-campo='intervalo' value='area_habitese_2'>AREA_HABITESE_2</option>
                    <option data-tabela='cadastro' data-campo='texto' value='num_apro_proj_3'>NUM_APRO_PROJ_3</option>
                    <option data-tabela='cadastro' data-campo='data' value='dt_apro_proj_3'>DT_APRO_PROJ_3</option>
                    <option data-tabela='cadastro' data-campo='intervalo' value='area_apro_proj_3'>AREA_APRO_PROJ_3</option>
                    <option data-tabela='cadastro' data-campo='texto' value='num_habitese_3'>NUM_HABITESE_3</option>
                    <option data-tabela='cadastro' data-campo='data' value='dt_habitese_3'>DT_HABITESE_3</option>
                    <option data-tabela='cadastro' data-campo='intervalo' value='area_habitese_3'>AREA_HABITESE_3</option>
                    <option data-tabela='cadastro' data-campo='texto' value='num_apro_proj_4'>NUM_APRO_PROJ_4</option>
                    <option data-tabela='cadastro' data-campo='data' value='dt_apro_proj_4'>DT_APRO_PROJ_4</option>
                    <option data-tabela='cadastro' data-campo='intervalo' value='area_apro_proj_4'>AREA_APRO_PROJ_4</option>
                    <option data-tabela='cadastro' data-campo='texto' value='num_habitese_4'>NUM_HABITESE_4</option>
                    <option data-tabela='cadastro' data-campo='data' value='dt_habitese_4'>DT_HABITESE_4</option>
                    <option data-tabela='cadastro' data-campo='intervalo' value='area_habitese_4'>AREA_HABITESE_4</option>
                    <option data-tabela='cadastro' data-campo='intervalo' value='area_construida_a'>AREA_CONSTRUIDA_A</option>
                    <option data-tabela='cadastro' data-campo='intervalo' value='area_construida_b'>AREA_CONSTRUIDA_B</option>
                    <option data-tabela='cadastro' data-campo='intervalo' value='area_construida_c'>AREA_CONSTRUIDA_C</option>
                    <option data-tabela='cadastro' data-campo='texto' value='cartorio'>CARTORIO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='cartorio_2'>CARTORIO_2</option>
                    <option data-tabela='cadastro' data-campo='texto' value='matricula_2'>MATRICULA_2</option>
                    <option data-tabela='cadastro' data-campo='texto' value='cep'>CEP</option>
                    <option data-tabela='cadastro' data-campo='texto' value='apto_casa'>APTO_CASA</option>
                    <option data-tabela='cadastro' data-campo='texto' value='loja_sala'>LOJA_SALA</option>
                    <option data-tabela='cadastro' data-campo='texto' value='bloco'>BLOCO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='galpao'>GALPAO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='condominio_edificio'>CONDOMINIO_EDIFICIO</option>
                    <option data-tabela='cadastro' data-campo='texto' value='unidade_condominio'>UNIDADE_CONDOMINIO</option>
                </select>
            </div>`

            $("#containerFiltros").append(selectBtn);
        }

        function realizarConsulta(tabela, consultaId) {
            console.log('Pesquisando...')
            
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
                    console.log('Resposta recebida:', response);
                    
                    if (response.success && response.data && response.colunas) {
                        console.log('Total de registros encontrados:', response.recordsTotal);
                        console.log('Registros carregados:', response.recordsShown);
                        
                        // Chamar função para exibir na tabela (client-side)
                        chamarTabela(response.data, response.colunas);
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

        function chamarTabela(dados, colunas) {
            console.log('=== NOVA CONSULTA ===');
            console.log('Configurando tabela client-side com', dados.length, 'registros');
            console.log('Colunas da nova consulta:', colunas);
            
            // Armazenar todas as colunas globalmente para o modal
            window.todasColunas = colunas;
            
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

    </script>

</body>

</html>