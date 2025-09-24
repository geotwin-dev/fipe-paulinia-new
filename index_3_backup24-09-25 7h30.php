<?php
session_start();

include("verifica_login.php");
include("connection.php");

if (isset($_GET['quadricula'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM ortofotos WHERE quadricula = :a");
        $stmt->bindParam(':a', $_GET['quadricula']);
        $stmt->execute();

        $dadosOrto = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $dadosOrto = [];
    }
} else {
    $dadosOrto = [];
}

echo "<script>let dadosOrto = " . json_encode($dadosOrto) . ";</script>";
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa com Framework</title>

    <!-- jQuery -->
    <script src="jquery.min.js"></script>
    <!-- Bootstrap 5.3 -->
    <script src="bootstrap.bundle.min.js"></script>
    <link href="bootstrap.min.css" rel="stylesheet">

    <!--Conexão com fonts do Google-->
    <link href='https://fonts.googleapis.com/css?family=Muli' rel='stylesheet'>

    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!--Conexão com biblioteca de BUFFER para poligono-->
    <script src="https://unpkg.com/@turf/turf@6.5.0/turf.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.11.0/proj4.js"></script>

    <!-- Google Maps API -->
    <script src="apiGoogle.js"></script>

    <!-- toGeoJSON -->
    <script src="https://unpkg.com/togeojson@0.16.0/togeojson.js"></script>
    <!-- TURF.js para operações geoespaciais -->
    <script src="https://unpkg.com/@turf/turf@6.5.0/turf.min.js"></script>
    <!-- Nosso framework -->
    <script src="framework.js"></script>

    <!--CSS GERAL DA PAGINA DE MAPA -->
    <link href="styleMap.css" rel="stylesheet">

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

        #map {
            width: 100%;
            height: 100%;
            border-top: 0px solid black;
            border-left: 1px solid black;
            border-right: 1px solid black;
            border-bottom: 1px solid black;
        }

        gmp-internal-camera-control {
            display: none !important;
        }

        .divContainerMap {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        #modalCamada {
            background-color: rgba(0, 0, 0, 0.5)
        }

        #dropCamadas {
            width: 230px;
        }

        .map-label-text {
            font: 700 14px/1.1 Roboto, Arial, sans-serif;
            color: #fff;
            /* texto branco */
            white-space: nowrap;
            pointer-events: none;
            /* não intercepta cliques do mapa */
            background: transparent;
            /* contorno preto usando múltiplas sombras (efeito stroke) */
            text-shadow:
                -1px -1px 0 #000,
                0px -1px 0 #000,
                1px -1px 0 #000,
                -1px 0px 0 #000,
                1px 0px 0 #000,
                -1px 1px 0 #000,
                0px 1px 0 #000,
                1px 1px 0 #000;
        }

        #controleNavegacaoQuadriculas {
            position: absolute;
            top: 60px;
            left: 5px;
            z-index: 1000;
            display: flex;
            gap: 5px;
            flex-direction: column;
            background-color: rgba(0, 0, 0, 0.9);
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 0 10px 0 rgba(0, 0, 0, 0.5);
            transition: all 0.3s ease;
        }

        #controleNavegacaoQuadriculas.expandido {
            width: auto;
            overflow-y: auto;
        }

        #controleNavegacaoQuadriculas div {
            display: flex;
            gap: 5px;
        }

        .controleNavegacaoQuadriculas-btn {
            width: 30px;
            height: 30px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        .controleNavegacaoQuadriculas-btn2 {
            width: 30px;
            height: 30px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
        }

        .subDivControle {
            min-width: 30px;
        }

        .divControle {
            min-height: 30px;
        }

        /* Estilos para a grade expandida - Regras mais específicas */
        #controleNavegacaoQuadriculas #gradeExpandida {
            margin-top: 10px !important;
            border-top: 1px solid rgba(255, 255, 255, 0.3) !important;
            padding-top: 10px !important;
            overflow-y: auto !important;
            display: none !important;
        }

        #controleNavegacaoQuadriculas #gradeExpandida.show {
            display: block !important;
        }

        #controleNavegacaoQuadriculas .grade-expandida-linha {
            display: flex !important;
            gap: 3px !important;
            justify-content: center !important;
            margin-bottom: 3px !important;
            align-items: center !important;
            flex-direction: row !important;
        }

        #controleNavegacaoQuadriculas .grade-expandida-celula {
            width: 30px !important;
            height: 30px !important;
            text-align: center !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 10px !important;
            font-weight: bold !important;
            border-radius: 3px !important;
            transition: all 0.2s ease !important;
            box-sizing: border-box !important;
        }

        #controleNavegacaoQuadriculas .grade-expandida-celula.vazia {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: rgba(255, 255, 255, 0.3) !important;
            cursor: default !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
        }

        /* Cabeçalhos da grade */
        #controleNavegacaoQuadriculas .grade-expandida-celula.cabecalho {
            background-color: rgba(255, 255, 255, 0.2) !important;
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: bold !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
        }

        /* Botão de expansão */
        #controleExpansao {
            text-align: center;
            margin-top: 5px;
        }

        #btnExpandir {
            width: 100%;
            font-size: 10px;
            padding: 4px 8px;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            transition: all 0.2s ease;
        }

        #btnExpandir:hover {
            background-color: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
        }

        #btnExpandir.expandido {
            background-color: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
        }

        #grade3x3 {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        /* Estilos para a div flutuante de cadastro */
        #divCadastro {
            position: absolute;
            top: 70px;
            right: 60px;
            z-index: 1000;
            width: 280px;
            max-height: 300px;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            color: #333;
            overflow: hidden;
            display: none;
        }

        #divCadastro2 {
            position: absolute;
            top: 390px;
            right: 60px;
            z-index: 1000;
            width: 280px;
            max-height: 300px;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            color: #333;
            overflow: hidden;
            display: none;
        }

        #divCadastro3 {
            position: absolute;
            top: 220px;
            left: 5px;
            z-index: 1000;
            width: 240px;
            max-height: 300px;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            color: #333;
            overflow: hidden;
            display: none;
        }

        .div-cadastro-header {
            background-color: #f8f9fa;
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .div-cadastro-header h6 {
            margin: 0;
            color: #555;
            font-weight: 600;
            font-size: 13px;
        }

        .btn-close-cadastro {
            background: none;
            border: none;
            color: #666;
            font-size: 14px;
            cursor: pointer;
            padding: 2px;
            border-radius: 3px;
            transition: background-color 0.2s ease;
        }

        .btn-close-cadastro:hover {
            background-color: #e9ecef;
        }

        .div-cadastro-body {
            padding: 10px;
            max-height: calc(300px - 50px);
            overflow-y: auto;
        }

        .div-cadastro-body .opcao-loteamento {
            margin-bottom: 6px;
            padding: 8px 10px;
            background-color: #fafafa;
            border-radius: 4px;
            border: 1px solid #eee;
            transition: background-color 0.2s ease;
        }

        .div-cadastro-body .opcao-loteamento:hover {
            background-color: #f0f0f0;
        }

        .div-cadastro-body .opcao-loteamento input[type="radio"] {
            margin-right: 8px;
        }

        .div-cadastro-body .opcao-loteamento label {
            font-weight: 500;
            color: #333;
            cursor: pointer;
            margin: 0;
            display: block;
            font-size: 13px;
            line-height: 1.3;
        }

        .div-cadastro-body .opcao-loteamento label small {
            color: #666;
            font-weight: normal;
            font-size: 11px;
            display: block;
            margin-top: 2px;
        }

        .div-cadastro-body .submenu-pdfs {
            margin-left: 20px;
            margin-top: 5px;
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
        }

        .div-cadastro-body .submenu-pdfs a {
            display: inline-block;
            color: #666;
            text-decoration: none;
            padding: 2px 6px;
            font-size: 10px;
            background-color: #e9ecef;
            border-radius: 10px;
            transition: background-color 0.2s ease;
        }

        .div-cadastro-body .submenu-pdfs a:hover {
            background-color: #007bff;
            color: white;
        }

        .div-cadastro-body .submenu-pdfs a i {
            margin-right: 3px;
            font-size: 9px;
        }

        .div-cadastro-body .opcao-loteamento.selected {
            background-color: #e3f2fd;
            border-color: #007bff;
        }

        .div-cadastro-body .opcao-loteamento.selected label {
            color: #007bff;
        }

        /* Estilos para as opções de quarteirões */
        .div-cadastro-body .opcao-quarteirao {
            margin-bottom: 6px;
            padding: 8px 10px;
            background-color: #fafafa;
            border-radius: 4px;
            border: 1px solid #eee;
            transition: background-color 0.2s ease;
        }

        .div-cadastro-body .opcao-quarteirao:hover {
            background-color: #f0f0f0;
        }

        .div-cadastro-body .opcao-quarteirao input[type="radio"] {
            margin-right: 8px;
        }

        .div-cadastro-body .opcao-quarteirao label {
            font-weight: 500;
            color: #333;
            cursor: pointer;
            margin: 0;
            display: block;
            font-size: 13px;
            line-height: 1.3;
        }

        .div-cadastro-body .opcao-quarteirao label small {
            color: #666;
            font-weight: normal;
            font-size: 11px;
            display: block;
            margin-top: 2px;
        }

        .div-cadastro-body .opcao-quarteirao.selected {
            background-color: #e3f2fd;
            border-color: #007bff;
        }

        .div-cadastro-body .opcao-quarteirao.selected label {
            color: #007bff;
        }

        /* Estilos para as opções de lotes */
        .div-cadastro-body .opcao-lote {
            margin-bottom: 6px;
            padding: 8px 10px;
            background-color: #fafafa;
            border-radius: 4px;
            border: 1px solid #eee;
            transition: background-color 0.2s ease;
            cursor: pointer;
        }

        .div-cadastro-body .opcao-lote:hover {
            background-color: #f0f0f0;
        }

        .div-cadastro-body .opcao-lote.selected {
            background-color: #e3f2fd;
            border-color: #007bff;
        }

        .div-cadastro-body .opcao-lote .lote-texto {
            font-weight: 500;
            color: #333;
            font-size: 13px;
            line-height: 1.3;
        }

        .div-cadastro-body .opcao-lote .lote-flecha {
            font-family: monospace;
            font-size: 14px;
        }

        /* Scrollbar simples */
        .div-cadastro-body::-webkit-scrollbar {
            width: 4px;
        }

        .div-cadastro-body::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .div-cadastro-body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 2px;
        }

        .div-cadastro-body::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Estilo para o input quando estiver no modo de inserção */
        #inputLoteAtual.modo-insercao {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
        }

        #inputLoteAtual.modo-insercao:focus {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
        }

        /* Estilo para lotes inseridos com sucesso */
        .div-cadastro-body .opcao-lote.lote-inserido {
            background-color: #d4edda !important;
            border-color: #c3e6cb !important;
            color: #155724 !important;
        }

        .div-cadastro-body .opcao-lote.lote-inserido:hover {
            background-color: #c3e6cb !important;
        }

        .div-cadastro-body .opcao-lote.lote-inserido .lote-texto {
            color: #155724 !important;
            font-weight: 600 !important;
        }

        /* Estilo para o tooltip do marcador */
        #tooltipMarcador {
            position: absolute;
            background: white;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            display: none;
            min-width: 120px;
        }

        #tooltipMarcador .tooltip-header {
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
            font-size: 12px;
        }

        #tooltipMarcador .tooltip-content {
            text-align: center;
        }

        #tooltipMarcador .btn-delete-marcador {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
            transition: background-color 0.2s;
        }

        #tooltipMarcador .btn-delete-marcador:hover {
            background-color: #c82333;
        }

        .btn-close-tooltip {
            background: none;
            border: none;
            color: #666;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-close-tooltip:hover {
            color: red;
        }

        #labelRadioLote, #labelRadioPredial {
            font-size: 13px !important;
        }

        /* Botão dropdown de filtros de cores */
        #btnFiltroCores {
            position: absolute;
            bottom: 20px;
            left: 5px;
            z-index: 1000;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: none;
            transition: all 0.3s ease;
        }

        #btnFiltroCores:hover {
            background-color: rgba(0, 0, 0, 0.9);
        }

        #btnFiltroCores i {
            margin-left: 8px;
            transition: transform 0.3s ease;
        }

        #btnFiltroCores.aberto i {
            transform: rotate(180deg);
        }

        /* Div de filtros de cores */
        #divFiltroCores {
            position: absolute;
            bottom: 70px;
            left: 5px;
            z-index: 1000;
            background-color: rgba(0, 0, 0, 0.7);
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 15px;
            min-width: 200px;
            display: none;
        }

        #divFiltroCores h6 {
            margin: 0px 0 20px 0;
            color: white;
            font-size: 16px;
            font-weight: 600;
        }

        #divFiltroCores .form-check {
            margin-bottom: 8px;
        }

        #divFiltroCores .form-check-label {
            font-size: 13px;
            color: #333;
            cursor: pointer;
        }

        /* Checkboxes coloridos customizados */
        #divFiltroCores .form-check-input {
            width: 18px;
            height: 18px;
            border: 2px solid #000;
            border-radius: 3px;
            background-color: white;
            position: relative;
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            margin-top: 0;
            vertical-align: middle;
        }

        #divFiltroCores .form-check-input:checked {
            background-color: currentColor;
            border-color: white;
            border-width: 1px;
        }


        #divFiltroCores .form-check-label {
            color: white;
            vertical-align: middle;
            margin-left: 8px;
            line-height: 1.2;
        }

        /* Cores específicas para cada checkbox */
        #chkVermelho {
            color:rgb(255, 0, 0);
        }

        #chkAmarelo {
            color:rgb(255, 234, 0);
        }

        #chkLaranja {
            color:rgb(255, 102, 0);
        }

        #chkVerde {
            color:rgb(43, 160, 43);
        }

        #chkAzul {
            color:rgb(16, 161, 197);
        }

        #chkCinza {
            color:rgb(124, 124, 124);
        }
    </style>

</head>

<body>

    <!-- Modal personalizado de escolha de camada -->
    <div id="modalCamada" class="modal" style="display:none">
        <div class="modal-dialog">
            <div class="modal-content p-3">
                <label for="inputNumeroQuadra" class="form-label mb-1">Identificador da quadra</label>
                <input id="inputNumeroQuadra" class="form-control mb-3" placeholder="" />
                <div class="d-flex gap-2 justify-content-end">
                    <button id="btnCancelarCamada" class="btn btn-outline-secondary">Cancelar</button>
                    <button id="btnSalvarCamada" class="btn btn-primary">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Div flutuante de Cadastro de Loteamentos -->
    <div id="divCadastro" style="display:none">
        <div class="div-cadastro-header">
            <h6>Loteamentos da Quadrícula <span id="quadriculaAtual"></span></h6>
            <button type="button" class="btn-close-cadastro" id="btnFecharCadastro">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="div-cadastro-body">
            <div id="opcoesLoteamentos">
                <!-- Os botões radio serão criados dinamicamente aqui -->
            </div>
        </div>
    </div>

    <div id="divCadastro2" style="display:none">
        <div class="div-cadastro-header">
            <h6>Quarteirões do <span id="quarteiraoSelecionado"></span></h6>

        </div>
        <div class="div-cadastro-body">
            <div id="opcoesQuarteiroes">
                <!-- Os botões radio serão criados dinamicamente aqui -->
            </div>
        </div>
    </div>

    <div id="divCadastro3" style="display:none">
        <div class="div-cadastro-header">
            <h6>Imóveirs do Quarteirão: <span id="quarteiraoSelecionado2"></span>
                <br>
                Quantidade de Lotes: <span id="qtdLotes"></span>
            </h6>

        </div>
        <div class="div-cadastro-body">
            <div id="opcoesLotes">
                <!-- Os botões radio serão criados dinamicamente aqui -->
            </div>
        </div>
    </div>

    <!-- Aqui vai ter um controle de navegação entre as quadriculas -->
    <div id="controleNavegacaoQuadriculas">
        <!-- Grade 3x3 padrão -->
        <div id="grade3x3">
            <div>
                <div class="subDivControle">
                    <button onclick="navegarQuadricula(this)" data-quadricula="" id="btn_noroeste" class="controleNavegacaoQuadriculas-btn btn btn-light">NO</button>
                </div>
                <div class="subDivControle">
                    <button onclick="navegarQuadricula(this)" data-quadricula="" id="btn_norte" class="controleNavegacaoQuadriculas-btn btn btn-light">N</button>
                </div>
                <div class="subDivControle">
                    <button onclick="navegarQuadricula(this)" data-quadricula="" id="btn_nordeste" class="controleNavegacaoQuadriculas-btn btn btn-light">NE</button>
                </div>
            </div>
            <div>
                <div class="subDivControle">
                    <button onclick="navegarQuadricula(this)" data-quadricula="" id="btn_oeste" class="controleNavegacaoQuadriculas-btn btn btn-light">O</button>
                </div>
                <div class="subDivControle">
                    <button id="btn_centro" class="controleNavegacaoQuadriculas-btn2 btn btn-light">C</button>
                </div>
                <div class="subDivControle">
                    <button onclick="navegarQuadricula(this)" data-quadricula="" id="btn_leste" class="controleNavegacaoQuadriculas-btn btn btn-light">E</button>
                </div>
            </div>
            <div>
                <div class="subDivControle">
                    <button onclick="navegarQuadricula(this)" data-quadricula="" id="btn_sudoeste" class="controleNavegacaoQuadriculas-btn btn btn-light">SW</button>
                </div>
                <div class="subDivControle">
                    <button onclick="navegarQuadricula(this)" data-quadricula="" id="btn_sul" class="controleNavegacaoQuadriculas-btn btn btn-light">S</button>
                </div>
                <div class="subDivControle">
                    <button onclick="navegarQuadricula(this)" data-quadricula="" id="btn_sudeste" class="controleNavegacaoQuadriculas-btn btn btn-light">SE</button>
                </div>
            </div>
        </div>

        <!-- Grade expandida com todas as quadrículas -->
        <div id="gradeExpandida" style="display: none;">
            <!-- Será preenchida dinamicamente pelo JavaScript -->
        </div>

        <!-- Botão de expansão no canto inferior direito -->
        <div id="controleExpansao" style="text-align: center; margin-top: 5px;">
            <button id="btnExpandir" class="btn btn-sm btn-outline-light" style="width: 100%; font-size: 10px;">
                <i class="fas fa-expand"></i> Expandir
            </button>
        </div>
    </div>

    <!-- Tooltip para marcadores -->
    <div id="tooltipMarcador">
        <div class="tooltip-header" style="display: flex; justify-content: space-between; align-items: center;">
            <span id="tooltipNumero"></span>
            <button id="btnCloseTooltip" class="btn-close-tooltip">x</button>
        </div>
        <div class="tooltip-info" style="font-size: 11px; color: #666; margin-bottom: 8px; display: none;">
            Quadra: <span id="tooltipQuadra"></span>
        </div>
        <div class="tooltip-content">
            <button id="btnDeleteMarcador" class="btn-delete-marcador">
                <i class="fas fa-trash"></i> Deletar
            </button>
        </div>
    </div>

    <!-- Botão de filtro de cores -->
    <button id="btnFiltroCores">
        <i class="fas fa-filter"></i> Filtros
    </button>

    <!-- Div de filtros de cores -->
    <div id="divFiltroCores">
        <h6>Visualizar Imóvel</h6>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="chkVermelho" checked>
            <label class="form-check-label" for="chkVermelho">
                Checar cadastro
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="chkAmarelo" checked>
            <label class="form-check-label" for="chkAmarelo">
                A desdobrar
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="chkLaranja" checked>
            <label class="form-check-label" for="chkLaranja">
                A unificar
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="chkCinza" checked>
            <label class="form-check-label" for="chkCinza">
                A cadastrar
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="chkVerde" checked>
            <label class="form-check-label" for="chkVerde">
                Cadastrado (privado)
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="chkAzul" checked>
            <label class="form-check-label" for="chkAzul">
                Próprios Públicos
            </label>
        </div>
    </div>

    <div class="divContainerMap">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">

                <!-- Título -->
                <a class="navbar-brand" href="#">Plataforma Geo</a>

                <!-- Botões -->
                <div class="d-flex align-items-center flex-grow-1 gap-2">

                    <!-- Botão Tipo de Mapa -->
                    <button id="btnTipoMapa" class="btn btn-light">Mapa</button>

                    <!-- Botão Camadas (Dropdown com Checkboxes) -->
                    <div class="btn-group">
                        <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            Camadas
                        </button>
                        <ul id="dropCamadas" class="dropdown-menu p-2">
                            <!-- Checkbox da Ortofoto fixo -->
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkOrtofoto" checked>
                                    <label class="form-check-label" for="chkOrtofoto">
                                        Ortofoto
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkQuadras" checked>
                                    <label class="form-check-label" for="chkQuadras">
                                        Quadras
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkLotes" checked>
                                    <label class="form-check-label" for="chkLotes">
                                        Lotes Ortofoto
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="new_checkLotes">
                                    <label class="form-check-label" for="new_checkLotes">
                                        Lotes Prefeitura
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkLimite" checked>
                                    <label class="form-check-label" for="chkLimite">
                                        Limite do Município
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkQuadriculas" checked>
                                    <label class="form-check-label" for="chkQuadriculas">
                                        Limite das Quadriculas
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkPrefeitura">
                                    <label class="form-check-label" for="chkPrefeitura">
                                        Cartografia Prefeitura
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkMarcadores">
                                    <label class="form-check-label" for="chkMarcadores">
                                        Imóveis
                                    </label>
                                </div>
                                <div class="ms-3 mt-2" id="submenuMarcadores">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipoMarcador" id="radioLote" value="lote" checked>
                                        <label id="labelRadioLote" class="form-check-label" for="radioLote">
                                            Número do Lote
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipoMarcador" id="radioPredial" value="predial">
                                        <label id="labelRadioPredial" class="form-check-label" for="radioPredial">
                                            Número Predial
                                        </label>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkQuarteiroes">
                                    <label class="form-check-label" for="chkQuarteiroes">
                                        Quarteirões
                                    </label>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="divControle">
                        <input min="0" max="1" step="0.1" type="range" class="form-range" id="customRange1" value="0.3">
                    </div>

                    <button id="btnCadastro" class="btn btn-info">Cadastro</button>

                </div>

                <!-- Botão Sair -->
                <div class="d-flex">
                    <a href="logout.php" class="btn btn-danger">Sair</a>
                </div>
            </div>
        </nav>

        <div id="map"></div>
    </div>

    <script>
        function navegarQuadricula(btn) {
            const quadricula = btn.getAttribute('data-quadricula');
            //console.log(quadricula);
            window.location.href = `index_3.php?quadricula=${quadricula}`;
        }

        const arrayCamadas = {
            prefeitura: [],
            limite: [],
            marcador: [],
            marcador_quadra: [],
            quadriculas: [],
            ortofoto: [],
            quadra: [],
            lote: [],
            quarteirao: [],
            semCamadas: []
        };

        $('#btnCloseTooltip').on('click', function() {
            $('#tooltipMarcador').hide();
        });

        function extrairCoordenadasQuadricula(quadricula) {
            // Extrai as coordenadas da quadrícula (ex: C10 -> col=2, lin=10)
            const match = quadricula.match(/^([A-Z])(\d+)$/);
            if (match) {
                const col = match[1].charCodeAt(0) - 65; // A=0, B=1, C=2, etc.
                const lin = parseInt(match[2]);
                return { col, lin };
            }
            return null;
        }

        function carregarGradeExpandida(todasQuadriculas, quadriculaAtual) {
            try {
                // Encontra os limites da grade
                let minCol = Infinity,
                    maxCol = -Infinity,
                    minLin = Infinity,
                    maxLin = -Infinity;

                todasQuadriculas.forEach(q => {
                    const coords = this.extrairCoordenadasQuadricula(q.centro);
                    if (coords) {
                        minCol = Math.min(minCol, coords.col);
                        maxCol = Math.max(maxCol, coords.col);
                        minLin = Math.min(minLin, coords.lin);
                        maxLin = Math.max(maxLin, coords.lin);
                    }
                });

                // Cria a grade expandida
                const gradeExpandida = document.getElementById('gradeExpandida');
                if (!gradeExpandida) {
                    console.warn('Elemento gradeExpandida não encontrado');
                    return;
                }

                gradeExpandida.innerHTML = '';

                // Adiciona cabeçalho com letras das colunas
                const headerRow = document.createElement('div');
                headerRow.className = 'grade-expandida-linha';
                headerRow.style.marginBottom = '8px';

                // Célula vazia para alinhar com as linhas
                const emptyHeader = document.createElement('div');
                emptyHeader.className = 'grade-expandida-celula cabecalho';
                emptyHeader.style.width = '30px';
                headerRow.appendChild(emptyHeader);

                // Letras das colunas
                for (let col = minCol; col <= maxCol; col++) {
                    const colHeader = document.createElement('div');
                    colHeader.className = 'grade-expandida-celula cabecalho';
                    colHeader.style.width = '30px';
                    colHeader.style.fontSize = '9px';
                    colHeader.textContent = String.fromCharCode(65 + col); // A, B, C, D, etc.
                    headerRow.appendChild(colHeader);
                }
                gradeExpandida.appendChild(headerRow);

                // Cria as linhas da grade
                for (let linha = maxLin; linha >= minLin; linha--) {
                    const linhaDiv = document.createElement('div');
                    linhaDiv.className = 'grade-expandida-linha';

                    // Número da linha à esquerda
                    const linhaHeader = document.createElement('div');
                    linhaHeader.className = 'grade-expandida-celula cabecalho';
                    linhaHeader.style.width = '30px';
                    linhaHeader.style.fontSize = '9px';
                    linhaHeader.textContent = linha.toString();
                    linhaDiv.appendChild(linhaHeader);

                    for (let col = minCol; col <= maxCol; col++) {
                        // Procura se existe uma quadrícula nesta posição
                        const quadriculaEncontrada = todasQuadriculas.find(q => {
                            const coords = this.extrairCoordenadasQuadricula(q.centro);
                            return coords && coords.col === col && coords.lin === linha;
                        });

                        if (quadriculaEncontrada) {
                            // Cria um botão em vez de div, usando as mesmas classes dos botões existentes
                            const btn = document.createElement('button');

                            // Marca como quadrícula atual
                            if (quadriculaEncontrada.centro === quadriculaAtual) {
                                btn.className = 'controleNavegacaoQuadriculas-btn2 btn btn-danger';
                                btn.title = `Quadrícula atual: ${quadriculaEncontrada.centro}`;
                            } else {
                                btn.className = 'controleNavegacaoQuadriculas-btn btn btn-light';
                                btn.title = `Ir para ${quadriculaEncontrada.centro}`;
                                // Adiciona evento de clique para navegação
                                btn.addEventListener('click', () => {
                                    window.location.href = `index_3.php?quadricula=${quadriculaEncontrada.centro}`;
                                });
                            }

                            btn.textContent = quadriculaEncontrada.centro;
                            btn.style.width = '30px';
                            btn.style.height = '30px';
                            btn.style.fontSize = '12px';
                            btn.style.fontWeight = 'bold';

                            linhaDiv.appendChild(btn);
                        } else {
                            // Célula vazia
                            const celulaVazia = document.createElement('div');
                            celulaVazia.className = 'grade-expandida-celula vazia';
                            celulaVazia.style.width = '30px';
                            celulaVazia.style.height = '30px';
                            linhaDiv.appendChild(celulaVazia);
                        }
                    }

                    gradeExpandida.appendChild(linhaDiv);
                }
            } catch (error) {
                console.error('Erro ao carregar grade expandida:', error);
            }
        }

        function carregarControleNavegacaoQuadriculas(quadricula) {

            $.ajax({
                url: 'mapeamento_quadriculas.json',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    //console.log(response);

                    //abaixo o codigo proxura o objeto.centro que é igual ao parametro quadricula e retorna o objeto todo
                    const quadricula_central = response.find(obj => obj.centro === quadricula);

                    // lista de direções que podem existir
                    const direcoes = [
                        'noroeste', 'norte', 'nordeste',
                        'oeste', 'centro', 'leste',
                        'sudoeste', 'sul', 'sudeste'
                    ];

                    // percorre cada direção dinamicamente
                    direcoes.forEach(dir => {
                        const btn = $(`#btn_${dir}`);
                        const valor = quadricula_central[dir];

                        if (valor == null) {
                            btn.hide();
                        } else {
                            btn.show().text(valor).attr('data-quadricula', valor);
                        }
                    });

                    // Carrega a grade expandida com todas as quadrículas
                    carregarGradeExpandida(response, quadricula);

                },
                error: function(xhr, status, error) {
                    console.error('Erro ao carregar mapeamento de quadriculas:', error);
                }

            });

        }

        carregarControleNavegacaoQuadriculas(dadosOrto[0]['quadricula']);

        //console.log('arrayCamadas inicializado:', arrayCamadas);

        function adicionarObjetoNaCamada(nome, objeto) {
            try {
                const chave = (nome || 'semCamadas').toLowerCase();
                if (!arrayCamadas[chave]) {
                    arrayCamadas[chave] = [];
                }
                arrayCamadas[chave].push(objeto);
                //`Objeto adicionado à camada: ${chave}`, objeto);
            } catch (e) {
                console.error('Erro ao adicionar objeto na camada:', e);
                throw e;
            }
        }

        function abrirModalCamada() {
            this.bloqueiaRightClick = true; // trava cliques direitos no mapa
            $('#modalCamada').fadeIn(150);
        }

        function fecharModalCamada() {
            this.bloqueiaRightClick = false; // libera de novo
            $('#modalCamada').fadeOut(150);
        }

        // Checkbox da Ortofoto
        $('#chkOrtofoto').on('change', function() {
            if (!dadosOrto || dadosOrto.length === 0) {
                alert('Erro: Dados da ortofoto não estão disponíveis.');
                return;
            }

            const visivel = $(this).is(':checked');
            if (visivel) {
                MapFramework.inserirOrtofoto2(dadosOrto[0]["quadricula"]); // Se estava desativada, reinsere
            } else {
                MapFramework.limparOrtofoto(); // Remove a ortofoto do mapa
            }
        });

        // Checkbox das Quadras
        $('#chkQuadras').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('quadra', visivel);
        });

        // Checkbox dos Lotes
        $('#chkLotes').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('lote', visivel);
        });

        $('#chkPrefeitura').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('prefeitura', visivel);
        });

        $('#chkLimite').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('limite', visivel);
        });

        $('#chkQuadriculas').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('quadriculas', visivel);
        });

        $('#chkMarcadores').on('change', function() {
            const visivel = $(this).is(':checked');
            
            // Mostra/oculta o botão de filtros
            if (visivel) {
                $('#btnFiltroCores').fadeIn(200);
                // Aplica o filtro atual quando ativa os marcadores
                aplicarFiltroCores();
            } else {
                $('#btnFiltroCores').fadeOut(200);
                $('#divFiltroCores').fadeOut(150);
                $('#btnFiltroCores').removeClass('aberto');
                // Oculta todos os marcadores quando desativa
                if (arrayCamadas.marcador_quadra) {
                    arrayCamadas.marcador_quadra.forEach(marker => {
                        marker.setMap(null);
                    });
                }
            }
        });

        $('#chkQuarteiroes').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('quarteirao', visivel);
        });

        // Eventos para os radio buttons de tipo de marcador
        $('input[name="tipoMarcador"]').on('change', function() {
            const tipoSelecionado = $(this).val();
            atualizarRotulosMarcadores(tipoSelecionado);
        });

        // Eventos para o botão de filtro de cores
        $('#btnFiltroCores').on('click', function() {
            const divFiltro = $('#divFiltroCores');
            const btn = $(this);
            
            if (divFiltro.is(':visible')) {
                divFiltro.fadeOut(150);
                btn.removeClass('aberto');
            } else {
                divFiltro.fadeIn(150);
                btn.addClass('aberto');
            }
        });

        // Eventos para os checkboxes de filtro de cores
        $('#chkVermelho, #chkAmarelo, #chkLaranja, #chkVerde, #chkAzul, #chkCinza').on('change', function() {
            aplicarFiltroCores();
        });

        // Função para aplicar filtro de cores nos marcadores
        function aplicarFiltroCores() {
            if (!arrayCamadas.marcador_quadra || arrayCamadas.marcador_quadra.length === 0) {
                return;
            }

            // Verifica se o checkbox de marcadores está ativo
            const marcadoresAtivos = $('#chkMarcadores').is(':checked');
            
            // Se o checkbox principal não está ativo, oculta todos os marcadores
            if (!marcadoresAtivos) {
                arrayCamadas.marcador_quadra.forEach(marker => {
                    marker.setMap(null);
                });
                return;
            }

            // Verifica se estamos no modo cadastro (quarteirão selecionado)
            const modoCadastro = quarteiraoAtualSelecionado !== null;
            
            if (modoCadastro) {
                // No modo cadastro, se checkbox "Imóveis" está marcado, mostra TODOS os marcadores
                // Senão, mostra apenas os do quarteirão selecionado
                arrayCamadas.marcador_quadra.forEach(marker => {
                    const corMarcador = marker.content.style.backgroundColor;
                    let deveMostrar = false;

                    // Mapeia cores para checkboxes (suporta RGB e hexadecimal)
                    if (corMarcador.includes('#e84646') || corMarcador.includes('rgb(232, 70, 70)') || corMarcador === 'red') { // Vermelho
                        deveMostrar = $('#chkVermelho').is(':checked');
                    } else if (corMarcador.includes('#eddf47') || corMarcador.includes('rgb(237, 223, 71)')) { // Amarelo
                        deveMostrar = $('#chkAmarelo').is(':checked');
                    } else if (corMarcador.includes('#ed7947') || corMarcador.includes('rgb(237, 121, 71)')) { // Laranja
                        deveMostrar = $('#chkLaranja').is(':checked');
                    } else if (corMarcador.includes('#32CD32') || corMarcador.includes('rgb(50, 205, 50)')) { // Verde
                        deveMostrar = $('#chkVerde').is(':checked');
                    } else if (corMarcador.includes('#47cced') || corMarcador.includes('rgb(71, 204, 237)')) { // Azul
                        deveMostrar = $('#chkAzul').is(':checked');
                    }else if (corMarcador.includes('#7c7c7c') || corMarcador.includes('rgb(124, 124, 124)')) { // Azul
                        deveMostrar = $('#chkCinza').is(':checked');
                    }

                    // Mostra ou oculta o marcador baseado no filtro
                    if (deveMostrar) {
                        marker.setMap(MapFramework.map);
                    } else {
                        marker.setMap(null);
                    }
                });
            } else {
                // No modo normal, filtra todos os marcadores
                arrayCamadas.marcador_quadra.forEach(marker => {
                    const corMarcador = marker.content.style.backgroundColor;
                    let deveMostrar = false;

                    // Mapeia cores para checkboxes (suporta RGB e hexadecimal)
                    if (corMarcador.includes('#e84646') || corMarcador.includes('rgb(232, 70, 70)') || corMarcador === 'red') { // Vermelho
                        deveMostrar = $('#chkVermelho').is(':checked');
                    } else if (corMarcador.includes('#eddf47') || corMarcador.includes('rgb(237, 223, 71)')) { // Amarelo
                        deveMostrar = $('#chkAmarelo').is(':checked');
                    } else if (corMarcador.includes('#ed7947') || corMarcador.includes('rgb(237, 121, 71)')) { // Laranja
                        deveMostrar = $('#chkLaranja').is(':checked');
                    } else if (corMarcador.includes('#32CD32') || corMarcador.includes('rgb(50, 205, 50)')) { // Verde
                        deveMostrar = $('#chkVerde').is(':checked');
                    } else if (corMarcador.includes('#47cced') || corMarcador.includes('rgb(71, 204, 237)')) { // Azul
                        deveMostrar = $('#chkAzul').is(':checked');
                    }else if (corMarcador.includes('#7c7c7c') || corMarcador.includes('rgb(124, 124, 124)')) { // Azul
                        deveMostrar = $('#chkCinza').is(':checked');
                    }

                    // Mostra ou oculta o marcador baseado no filtro
                    if (deveMostrar) {
                        marker.setMap(MapFramework.map);
                    } else {
                        marker.setMap(null);
                    }
                });
            }
        }


        // Função para atualizar os rótulos dos marcadores
        function atualizarRotulosMarcadores(tipo) {
            if (!arrayCamadas.marcador_quadra || arrayCamadas.marcador_quadra.length === 0) {
                return;
            }

            arrayCamadas.marcador_quadra.forEach(marker => {
                let novoRotulo = '';
                
                if (tipo === 'lote') {
                    // Usa o rótulo atual (número do lote)
                    novoRotulo = marker.numeroMarcador || '-';
                } else if (tipo === 'predial') {
                    // Busca o número predial nos dados do morador
                    const dadosMorador = MapFramework.dadosMoradores.find(morador => 
                        morador.lote == marker.numeroMarcador && 
                        morador.quadra == marker.quadra && 
                        morador.cara_quarteirao == marker.quarteirao
                    );
                    novoRotulo = dadosMorador ? (dadosMorador.numero || '-') : '-';
                }
                
                // Atualiza o texto do elemento HTML do marcador
                if (marker.content && marker.content.textContent !== undefined) {
                    marker.content.textContent = novoRotulo;
                }
            });
        }

        $('#customRange1').on('input', function() {
            MapFramework.controlarOpacidade(this.value);
            
            // Controla também a opacidade dos loteamentos
            if (window.loteamentosLayer) {
                window.loteamentosLayer.forEach(polygon => {
                    polygon.setOptions({
                        fillOpacity: this.value
                    });
                });
            }
        })

        // Controle de expansão do navegador de quadrículas
        $('#btnExpandir').on('click', function() {
            const controle = $('#controleNavegacaoQuadriculas');
            const grade3x3 = $('#grade3x3');
            const gradeExpandida = $('#gradeExpandida');
            const btn = $(this);

            if (controle.hasClass('expandido')) {
                // Contrai
                controle.removeClass('expandido');
                gradeExpandida.removeClass('show');
                grade3x3.show();
                btn.html('<i class="fas fa-expand"></i> Expandir');
                btn.removeClass('expandido');
            } else {
                // Expande
                controle.addClass('expandido');
                grade3x3.hide();
                gradeExpandida.addClass('show');
                btn.html('<i class="fas fa-compress"></i> Contrair');
                btn.addClass('expandido');
            }
        });

        // Botão de Cadastro
        $('#btnCadastro').on('click', function() {
            if (!dadosOrto || dadosOrto.length === 0) {
                alert('Erro: Dados da ortofoto não estão disponíveis.');
                return;
            }

            //aqui desabilita o clique no poligonos quadra e lote
            arrayCamadas.quadra.forEach(quadra => {
                quadra.setOptions({
                    clickable: false
                });
            });
            arrayCamadas.lote.forEach(lote => {
                lote.setOptions({
                    clickable: false
                });
            });

            const quadricula = dadosOrto[0]['quadricula'];
            carregarLoteamentosQuadricula(quadricula);
            
            // Muda o botão para "Sair do modo cadastro"
            $(this).text('Sair do modo cadastro').removeClass('btn-info').addClass('btn-warning');
            
            // Remove o evento anterior e adiciona novo evento
            $(this).off('click').on('click', function() {
                sairModoCadastro();
            });
        });

        // Função para sair do modo cadastro
        function sairModoCadastro() {
            // Fecha todas as divs de cadastro
            $('#divCadastro').fadeOut(150);
            $('#divCadastro2').fadeOut(150);
            $('#divCadastro3').fadeOut(150);

            // Remove todos os destaques
            removerDestaques();

            // Limpa os desenhos dos loteamentos do mapa
            if (window.loteamentosLayer && window.loteamentosLayer.length > 0) {
                window.loteamentosLayer.forEach(polygon => {
                    polygon.setMap(null);
                });
                window.loteamentosLayer = [];
                console.log('Desenhos dos loteamentos removidos do mapa');
            }

            // Reabilita o clique nos polígonos quadra e lote
            arrayCamadas.quadra.forEach(quadra => {
                quadra.setOptions({
                    clickable: true
                });
            });
            arrayCamadas.lote.forEach(lote => {
                lote.setOptions({
                    clickable: true
                });
            });

            // Limpa a seleção dos radio buttons
            $('input[name="loteamento"]').prop('checked', false);
            $('.opcao-loteamento').removeClass('selected');
            $('input[name="quarteirao"]').prop('checked', false);
            $('.opcao-quarteirao').removeClass('selected');

            // Limpa a variável global dos loteamentos
            window.loteamentosSelecionados = null;

            // Limpa o input text
            $('#inputLoteAtual').val('');

            // Sai do modo de inserção de marcador se estiver ativo
            if (MapFramework.modoInsercaoMarcador) {
                MapFramework.finalizarDesenho();
            }

            // Limpa a seleção dos lotes
            $('.opcao-lote').removeClass('selected');
            $('.lote-flecha').html('&nbsp;&nbsp;');

            // Limpa variáveis globais do quarteirão
            quarteiraoAtualSelecionado = null;
            quarteiraoIdAtualSelecionado = null;
            
            // Verifica se a camada de marcadores está desativada e oculta os marcadores se necessário
            if (!$('#chkMarcadores').is(':checked')) {
                if (arrayCamadas.marcador_quadra) {
                    arrayCamadas.marcador_quadra.forEach(marker => {
                        marker.setMap(null);
                    });
                }
            }

            // Oculta o botão de filtros quando sair do modo cadastro
            $('#btnFiltroCores').fadeOut(200);
            $('#divFiltroCores').fadeOut(150);
            $('#btnFiltroCores').removeClass('aberto');
            
            // Restaura o botão original
            $('#btnCadastro').text('Cadastro').removeClass('btn-warning').addClass('btn-info');
            
            // Remove o evento atual e restaura o evento original
            $('#btnCadastro').off('click').on('click', function() {
                if (!dadosOrto || dadosOrto.length === 0) {
                    alert('Erro: Dados da ortofoto não estão disponíveis.');
                    return;
                }

                //aqui desabilita o clique no poligonos quadra e lote
                arrayCamadas.quadra.forEach(quadra => {
                    quadra.setOptions({
                        clickable: false
                    });
                });
                arrayCamadas.lote.forEach(lote => {
                    lote.setOptions({
                        clickable: false
                    });
                });

                const quadricula = dadosOrto[0]['quadricula'];
                carregarLoteamentosQuadricula(quadricula);
                
                // Muda o botão para "Sair do modo cadastro"
                $(this).text('Sair do modo cadastro').removeClass('btn-info').addClass('btn-warning');
                
                // Remove o evento anterior e adiciona novo evento
                $(this).off('click').on('click', function() {
                    sairModoCadastro();
                });
            });
        }

        // Função para carregar loteamentos de uma quadrícula específica
        async function carregarLoteamentosQuadricula(quadricula) {
            try {
                // Valida a quadrícula
                if (!quadricula || quadricula.trim() === '') {
                    throw new Error('Quadrícula inválida');
                }

                // Atualiza o título da div
                $('#quadriculaAtual').text(quadricula);

                // Mostra indicador de carregamento
                $('#opcoesLoteamentos').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Carregando loteamentos...</p></div>');

                // Carrega o JSON da quadrícula
                //const response = await fetch(`loteamentos_quadriculas/json/resultados_quadricula_${quadricula}.json`);

                const response = await fetch(`loteamentos_quadriculas/json/resultados_quadricula_${quadricula}.json`, {
                    cache: "no-store"
                });

                if (!response.ok) {
                    if (response.status === 404) {
                        throw new Error(`Arquivo de loteamentos não encontrado para a quadrícula ${quadricula}`);
                    } else {
                        throw new Error(`Erro HTTP: ${response.status} - ${response.statusText}`);
                    }
                }

                const dados = await response.json();

                // Valida a estrutura dos dados
                if (!dados || typeof dados !== 'object') {
                    throw new Error('Formato de dados inválido');
                }

                // Verifica se há loteamentos
                if (!dados.resultados || !dados.resultados.loteamentos || dados.resultados.loteamentos.length === 0) {
                    $('#opcoesLoteamentos').html(`
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Nenhum loteamento encontrado para a quadrícula <strong>${quadricula}</strong>.
                        </div>
                    `);
                } else {
                    // Cria os botões radio dinamicamente
                    criarOpcoesLoteamentos(dados.resultados.loteamentos);

                    // Adiciona os desenhos no mapa
                    adicionarDesenhosNoMapa(dados.resultados.loteamentos, quadricula);
                }

                // Abre a div flutuante
                $('#divCadastro').fadeIn(150);

            } catch (error) {
                console.error('Erro ao carregar loteamentos:', error);

                let mensagemErro = 'Erro ao carregar os dados dos loteamentos.';
                if (error.message.includes('não encontrado')) {
                    mensagemErro = error.message;
                } else if (error.message.includes('Formato de dados inválido')) {
                    mensagemErro = 'O arquivo de dados está corrompido ou em formato inválido.';
                }

                $('#opcoesLoteamentos').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        ${mensagemErro}
                    </div>
                `);

                // Abre a div flutuante mesmo com erro para mostrar a mensagem
                $('#divCadastro').fadeIn(150);
            }
        }

        // Função para verificar se um arquivo existe
        async function verificarArquivoExiste(caminho) {
            try {
                const response = await fetch(caminho, {
                    method: 'HEAD'
                });
                return response.ok;
            } catch (error) {
                console.log('Erro ao verificar arquivo:', caminho, error);
                return false;
            }
        }

        // Função para decodificar caracteres especiais
        function normalizarString(str) {
            // Converte para Normalization Form C (NFC)
            return str.normalize("NFC");
        }

        // Função para abrir PDF com tratamento de erro
        async function abrirPDF(nomeArquivo) {
            console.log('Nome original:', nomeArquivo);
            const nomeDecodificado = normalizarString(nomeArquivo);
            window.open('loteamentos_quadriculas/pdf/' + nomeDecodificado, '_blank');
        }

        // Função para abrir PDF de quarteirão com tratamento de erro
        async function abrirPDFQuarteirao(nomeArquivo) {
            console.log('Nome original PDF quarteirão:', nomeArquivo);
            const nomeDecodificado = normalizarString(nomeArquivo);
            window.open('loteamentos_quadriculas/pdfs_quarteiroes/' + nomeDecodificado, '_blank');
        }

        // Função para criar as opções de loteamentos na div
        function criarOpcoesLoteamentos(loteamentos) {
            const container = $('#opcoesLoteamentos');
            container.empty();

            // Salva os loteamentos em variável global para uso posterior
            window.loteamentosSelecionados = loteamentos;

            loteamentos.forEach((loteamento, index) => {
                const temArquivos = loteamento.arquivos_associados && loteamento.arquivos_associados.length > 0;
                const statusClass = loteamento.status_planilha === 'ok' ? 'text-success' : 'text-warning';
                const statusText = loteamento.status_planilha === 'ok' ? "" : ""; //'✓ Com arquivos' : '⚠ Sem arquivos';

                const opcao = $(`
                    <div class="opcao-loteamento">
                        <div class="d-flex align-items-start">
                            <input style="margin-top: 2px;" type="radio" id="loteamento_${index}" name="loteamento" value="${index}">
                            <label for="loteamento_${index}">
                                ${loteamento.nome}
                                <small class="d-block ${statusClass}">${statusText}</small>
                                ${loteamento.subpasta ? `<small class="d-block text-muted">${''}</small>` : ''}
                            </label>
                        </div>
                        ${temArquivos ? 
                            `<div class="submenu-pdfs">
                                ${loteamento.arquivos_associados.map(arquivo => {
                                    //console.log(arquivo);
                                    return `<a href="javascript:void(0)" onclick="abrirPDF('${arquivo}')" title="${arquivo}">
                                        <i class="fas fa-file-pdf"></i>${arquivo.length > 20 ? arquivo.substring(0, 20) + '...' : arquivo}
                                    </a>`;
                                }).join('')}
                            </div>` : 
                            '<div class="submenu-pdfs"><em class="text-muted">Sem PDFs</em></div>'
                        }
                    </div>
                `);

                container.append(opcao);
            });

            // Adiciona evento para destacar seleção
            $('input[name="loteamento"]').on('change', function() {
                const indexSelecionado = parseInt($(this).val());
                //console.log('Loteamento selecionado:', indexSelecionado);

                // Remove destaque anterior
                removerDestaques();

                // Destaca o loteamento selecionado e desenhos relacionados
                destacarLoteamentoSelecionado(indexSelecionado);

                // Adiciona classe visual para destacar a opção selecionada
                $('.opcao-loteamento').removeClass('selected');
                $(this).closest('.opcao-loteamento').addClass('selected');

                // Abre a divCadastro2 com os quarteirões do loteamento selecionado
                abrirDivCadastro2(indexSelecionado);

            });
        }

        // Função para adicionar desenhos no mapa
        function adicionarDesenhosNoMapa(loteamentos, quadricula) {
            //console.log(`Adicionando ${loteamentos.length} loteamentos da quadrícula ${quadricula} no mapa`);

            // Limpa desenhos anteriores se existirem
            if (window.loteamentosLayer) {
                window.loteamentosLayer.forEach(polygon => {
                    polygon.setMap(null);
                });
            }

            // Cria uma nova camada para os loteamentos
            window.loteamentosLayer = [];

            loteamentos.forEach((loteamento, index) => {
                if (loteamento.coordenadas && loteamento.coordenadas.length > 0) {
                    loteamento.coordenadas.forEach((coordenada, coordIndex) => {
                        if (coordenada.type === 'Polygon' && coordenada.coordinates) {
                            try {
                                // Converte as coordenadas para o formato do Google Maps
                                const path = coordenada.coordinates[0].map(coord => {
                                    return {
                                        lat: coord[1],
                                        lng: coord[0]
                                    }; // {lat, lng} para Google Maps
                                });

                                // Cria o polígono
                                const polygon = new google.maps.Polygon({
                                    paths: path,
                                    strokeColor: '#FF8C00',
                                    strokeOpacity: 0.8,
                                    strokeWeight: 7,
                                    fillColor: '#FF8C00',
                                    fillOpacity: 0.2,
                                    clickable: false,
                                    map: MapFramework.map
                                });

                                // Adiciona à camada
                                window.loteamentosLayer.push(polygon);

                                //console.log(`Polígono adicionado para: ${loteamento.nome}`);

                            } catch (error) {
                                console.error(`Erro ao criar polígono para ${loteamento.nome}:`, error);
                            }
                        }
                    });
                } else {
                    console.log(`Loteamento ${loteamento.nome} não tem coordenadas`);
                }
            });

            /*
            // Ajusta o zoom para mostrar todos os loteamentos
            if (window.loteamentosLayer.length > 0) {
                const bounds = new google.maps.LatLngBounds();
                window.loteamentosLayer.forEach(polygon => {
                    polygon.getPath().forEach(latLng => {
                        bounds.extend(latLng);
                    });
                });
                MapFramework.map.fitBounds(bounds, {
                    padding: 20
                });
            }
            */
        }

        // Evento para fechar a div de cadastro
        $('#btnFecharCadastro').on('click', function() {
            $('#divCadastro').fadeOut(150);

            // Fecha também a divCadastro2 se estiver aberta
            $('#divCadastro2').fadeOut(150);


            // Remove todos os destaques
            removerDestaques();

            // Limpa os desenhos dos loteamentos do mapa
            if (window.loteamentosLayer && window.loteamentosLayer.length > 0) {
                window.loteamentosLayer.forEach(polygon => {
                    polygon.setMap(null);
                });
                window.loteamentosLayer = [];
                console.log('Desenhos dos loteamentos removidos do mapa');
            }

            //aqui desabilita o clique no poligonos quadra e lote
            arrayCamadas.quadra.forEach(quadra => {
                quadra.setOptions({
                    clickable: true
                });
            });
            arrayCamadas.lote.forEach(lote => {
                lote.setOptions({
                    clickable: true
                });
            });

            // Limpa a seleção dos radio buttons
            $('input[name="loteamento"]').prop('checked', false);
            $('.opcao-loteamento').removeClass('selected');
            $('input[name="quarteirao"]').prop('checked', false);
            $('.opcao-quarteirao').removeClass('selected');

            // Limpa a variável global dos loteamentos
            window.loteamentosSelecionados = null;

            // Limpa o input text
            $('#inputLoteAtual').val('');

            // Sai do modo de inserção de marcador se estiver ativo
            if (MapFramework.modoInsercaoMarcador) {
                MapFramework.finalizarDesenho();
            }

            // Limpa variáveis globais do quarteirão
            quarteiraoAtualSelecionado = null;
            quarteiraoIdAtualSelecionado = null;
        });

        $(document).ready(async function() {
            // Verifica se dadosOrto está disponível antes de usar
            if (!dadosOrto || dadosOrto.length === 0) {
                alert('Erro: Dados da ortofoto não estão disponíveis. Verifique se a quadrícula foi passada corretamente na URL.');
                return;
            }

            let coordsInitial = {
                lat: JSON.parse(dadosOrto[0]['latitude']),
                lng: JSON.parse(dadosOrto[0]['longitude'])
            }

            await MapFramework.iniciarMapa('map', coordsInitial, 16);

            // Insere a ortofoto
            await MapFramework.inserirOrtofoto2(dadosOrto[0]["quadricula"]);

            await MapFramework.carregarDesenhosSalvos('paulinia', dadosOrto[0]['quadricula']);

            await MapFramework.carregarDesenhosPrefeitura(dadosOrto[0]['quadricula']);

            MapFramework.carregarLimiteKML();

            MapFramework.carregarQuadriculasKML();

            // Carrega os quarteirões da quadrícula atual
            await MapFramework.carregaQuarteiroes(dadosOrto[0]['quadricula']);

            await MapFramework.carregarPlanilha();

            await MapFramework.carregarMarcadoresSalvos(dadosOrto[0]['quadricula']);

            // Agora que o mapa foi criado, pode adicionar o listener
            MapFramework.map.getDiv().addEventListener('contextmenu', function(event) {
                if (MapFramework.desenho.modo === 'poligono') {
                    if (MapFramework.cliqueEmVertice) {
                        MapFramework.cliqueEmVertice = false;
                        return;
                    }

                    if (MapFramework.desenho.temporario &&
                        MapFramework.desenho.temporario.getPath().getLength() >= 3) {
                        event.preventDefault();
                        //MapFramework.abrirModalCamada();
                    }
                }
            });

            // no ready, uma vez só
            $('#modalCamada').on('contextmenu', function(e) {
                e.preventDefault();
            });

            // Outros inits
            $('#btnTipoMapa').on('click', function() {
                MapFramework.alternarTipoMapa();
            });

            $('#dropCamadas').on('click', function(e) {
                e.stopPropagation();
            });
        });

        // Função para verificar se um ponto está dentro de um polígono
        function pontoDentroDoPoligono(ponto, coordenadasPoligono) {
            // Algoritmo ray casting para verificar se ponto está dentro do polígono
            let dentro = false;
            const x = ponto.lng;
            const y = ponto.lat;

            for (let i = 0, j = coordenadasPoligono.length - 1; i < coordenadasPoligono.length; j = i++) {
                const xi = coordenadasPoligono[i].lng;
                const yi = coordenadasPoligono[i].lat;
                const xj = coordenadasPoligono[j].lng;
                const yj = coordenadasPoligono[j].lat;

                if (((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi)) {
                    dentro = !dentro;
                }
            }
            return dentro;
        }

        // Função para verificar se uma linha intersecta com um polígono
        function linhaIntersectaPoligono(linha, coordenadasPoligono) {
            // Verifica se algum ponto da linha está dentro do polígono
            for (let i = 0; i < linha.length; i++) {
                if (pontoDentroDoPoligono(linha[i], coordenadasPoligono)) {
                    return true;
                }
            }

            // Verifica se alguma aresta da linha cruza com alguma aresta do polígono
            for (let i = 0; i < linha.length - 1; i++) {
                const segmentoLinha = [linha[i], linha[i + 1]];

                for (let j = 0; j < coordenadasPoligono.length - 1; j++) {
                    const segmentoPoligono = [coordenadasPoligono[j], coordenadasPoligono[j + 1]];

                    if (segmentosSeCruzam(segmentoLinha, segmentoPoligono)) {
                        return true;
                    }
                }
            }

            return false;
        }

        // Função para verificar se dois segmentos de linha se cruzam
        function segmentosSeCruzam(seg1, seg2) {
            const p1 = seg1[0];
            const p2 = seg1[1];
            const p3 = seg2[0];
            const p4 = seg2[1];

            const d = (p2.lng - p1.lng) * (p4.lat - p3.lat) - (p2.lat - p1.lat) * (p4.lng - p3.lng);

            if (Math.abs(d) < 1e-10) return false; // Linhas paralelas

            const ua = ((p4.lng - p3.lng) * (p1.lat - p3.lat) - (p4.lat - p3.lat) * (p1.lng - p3.lng)) / d;
            const ub = ((p2.lng - p1.lng) * (p1.lat - p3.lat) - (p2.lat - p1.lat) * (p1.lng - p3.lng)) / d;

            return ua >= 0 && ua <= 1 && ub >= 0 && ub <= 1;
        }

        // Função para destacar loteamento selecionado e desenhos relacionados
        function destacarLoteamentoSelecionado(indexLoteamento) {
            // Remove destaque anterior
            if (window.loteamentosLayer) {
                window.loteamentosLayer.forEach((polygon, i) => {
                    if (i === indexLoteamento) {
                        // Mantém o loteamento selecionado com cor original e grossura 5
                        polygon.setOptions({
                            strokeColor: '#FF8C00',
                            fillColor: '#FF8C00',
                            strokeWeight: 7,
                            fillOpacity: 0.3,
                            zIndex: 4
                        });
                    } else {
                        // Deixa os outros loteamentos cinza com grossura 5
                        polygon.setOptions({
                            strokeColor: '#666666',
                            fillColor: '#cccccc',
                            strokeWeight: 7,
                            strokeOpacity: 1,
                            fillOpacity: 0.0,
                            zIndex: 3
                        });
                    }
                });
            }

            // Obtém as coordenadas do loteamento selecionado
            if (window.loteamentosSelecionados && window.loteamentosSelecionados[indexLoteamento]) {
                const loteamento = window.loteamentosSelecionados[indexLoteamento];

                if (loteamento.coordenadas && loteamento.coordenadas.length > 0) {
                    const coordenadasPoligono = loteamento.coordenadas[0].coordinates[0].map(coord => ({
                        lat: coord[1],
                        lng: coord[0]
                    }));

                    // Função para verificar se uma quadra está dentro do loteamento
                    function quadraEstaDentroDoLoteamento(quadra, coordenadasLoteamento) {
                        let coordenadasQuadra = null;

                        // Tenta diferentes formas de obter coordenadas
                        if (quadra.coordenadasGeoJSON && quadra.coordenadasGeoJSON.coordinates) {
                            coordenadasQuadra = quadra.coordenadasGeoJSON.coordinates[0].map(coord => ({
                                lng: coord[0],
                                lat: coord[1]
                            }));
                        } else if (quadra.getPath) {
                            const path = quadra.getPath();
                            coordenadasQuadra = [];

                            for (let i = 0; i < path.getLength(); i++) {
                                const latLng = path.getAt(i);
                                coordenadasQuadra.push({
                                    lng: latLng.lng(),
                                    lat: latLng.lat()
                                });
                            }
                        } else if (quadra.getBounds) {
                            const bounds = quadra.getBounds();
                            const ne = bounds.getNorthEast();
                            const sw = bounds.getSouthWest();
                            coordenadasQuadra = [{
                                    lng: sw.lng(),
                                    lat: sw.lat()
                                },
                                {
                                    lng: ne.lng(),
                                    lat: sw.lat()
                                },
                                {
                                    lng: ne.lng(),
                                    lat: ne.lat()
                                },
                                {
                                    lng: sw.lng(),
                                    lat: ne.lat()
                                }
                            ];
                        }

                        if (coordenadasQuadra && coordenadasQuadra.length > 0) {
                            return linhaIntersectaPoligono(coordenadasQuadra, coordenadasLoteamento);
                        }

                        return false;
                    }

                    // Função para ativar as linhas que pertencem a uma quadra
                    function ativarLinhasDaQuadra(quadra) {
                        if (arrayCamadas["lote"]) {
                            arrayCamadas["lote"].forEach(lote => {
                                // Verifica se o lote pertence a esta quadra
                                // lote.id_desenho = ID da quadra pai, quadra.identificador = ID único da quadra
                                if (parseInt(lote.id_desenho) === parseInt(quadra.identificador)) {
                                    // Restaura cor vermelha do lote, mantendo grossura original
                                    lote.setOptions({
                                        strokeColor: '#0078D7',
                                        fillColor: '#0078D7',
                                        fillOpacity: 0.30
                                        // strokeWeight não é alterado - mantém o original
                                    });
                                    lote.desativado = false;
                                }
                            });
                        }
                    }

                    // Primeiro, deixa TODOS os desenhos cinza (mantendo grossuras originais)
                    if (arrayCamadas["quadra"]) {
                        arrayCamadas["quadra"].forEach(quadra => {
                            // Deixa cinza por padrão, mantendo grossura original
                            quadra.setOptions({
                                strokeColor: 'gray',
                                fillColor: 'gray',
                                fillOpacity: 0.3
                                // strokeWeight não é alterado - mantém o original
                            });
                            quadra.desativado = true;
                        });
                    }

                    if (arrayCamadas["lote"]) {
                        arrayCamadas["lote"].forEach(lote => {
                            // Deixa cinza por padrão, mantendo grossura original
                            lote.setOptions({
                                strokeColor: 'gray',
                                fillColor: 'gray',
                                fillOpacity: 0.3
                                // strokeWeight não é alterado - mantém o original
                            });
                            lote.desativado = true;
                        });
                    }

                    // Agora, verifica cada quadra e ativa se estiver dentro do loteamento
                    if (arrayCamadas["quadra"]) {
                        arrayCamadas["quadra"].forEach(quadra => {
                            if (quadraEstaDentroDoLoteamento(quadra, coordenadasPoligono)) {
                                // Quadra está dentro do loteamento - ativa ela com cor vermelha
                                quadra.setOptions({
                                    strokeColor: '#0078D7',
                                    fillColor: '#0078D7',
                                    fillOpacity: 0.30
                                    // strokeWeight não é alterado - mantém o original
                                });
                                quadra.desativado = false;

                                // Ativa todas as linhas que pertencem a esta quadra
                                ativarLinhasDaQuadra(quadra);
                            }
                        });
                    }
                }
            }
        }

        // Função para abrir a divCadastro2 com os quarteirões do loteamento selecionado
        function abrirDivCadastro2(indexLoteamento) {
            if (window.loteamentosSelecionados && window.loteamentosSelecionados[indexLoteamento]) {
                const loteamento = window.loteamentosSelecionados[indexLoteamento];

                // Atualiza o título da div
                $('#quarteiraoSelecionado').text(loteamento.nome);

                // Popula a lista de quarteirões
                popularQuarteiroes(loteamento);

                // Abre a div flutuante
                $('#divCadastro2').fadeIn(150);

                // IMPORTANTE: NÃO mostra quarteirões automaticamente
                // Só mostra quando o usuário selecionar um radio na divCadastro2
            }
        }

        // Variável global para armazenar os dados dos quarteirões
        let dadosQuarteiroesLoteamentos = null;
        
        // Variável global para armazenar os dados dos PDFs dos quarteirões
        let dadosPDFsQuarteiroes = null;

        // Função para carregar os dados complementares dos quarteirões
        function carregarDadosQuarteiroes() {
            if (dadosQuarteiroesLoteamentos) {
                return Promise.resolve(dadosQuarteiroesLoteamentos);
            }

            return $.ajax({
                url: 'correspondencias_quarteiroes/resultado_quarteiroes_loteamentos.json',
                method: 'GET',
                dataType: 'json'
            }).then(function(data) {
                dadosQuarteiroesLoteamentos = data;
                return data;
            }).catch(function(error) {
                console.error('Erro ao carregar dados dos quarteirões:', error);
                return null;
            });
        }

        // Função para carregar os dados dos PDFs dos quarteirões
        function carregarDadosPDFsQuarteiroes() {
            if (dadosPDFsQuarteiroes) {
                return Promise.resolve(dadosPDFsQuarteiroes);
            }

            return $.ajax({
                url: 'loteamentos_quadriculas/quarteiroes.json',
                method: 'GET',
                dataType: 'json'
            }).then(function(data) {
                dadosPDFsQuarteiroes = data;
                return data;
            }).catch(function(error) {
                console.error('Erro ao carregar dados dos PDFs dos quarteirões:', error);
                return null;
            });
        }

        // Função para obter informações complementares de um quarteirão
        function obterInfoComplementarQuarteirao(nomeLoteamento, nomeQuarteirao) {
            if (!dadosQuarteiroesLoteamentos || !dadosQuarteiroesLoteamentos[nomeLoteamento]) {
                return null;
            }

            const loteamento = dadosQuarteiroesLoteamentos[nomeLoteamento];
            const quarteirao = loteamento.quarteiroes.find(q => q.nome === nomeQuarteirao);

            if (quarteirao && quarteirao.quadras_unicas) {
                return quarteirao.quadras_unicas.join(', ');
            }

            return null;
        }

        // Função para obter PDFs de um quarteirão específico
        function obterPDFsQuarteirao(nomeQuarteirao) {
            if (!dadosPDFsQuarteiroes || !Array.isArray(dadosPDFsQuarteiroes)) {
                return [];
            }

            // Procura por arquivos que contenham este quarteirão
            const arquivosComQuarteirao = dadosPDFsQuarteiroes.filter(item => {
                return item.quarteiroes && item.quarteiroes.includes(nomeQuarteirao);
            });

            // Retorna os nomes dos arquivos encontrados
            return arquivosComQuarteirao.map(item => item.nome_arquivo);
        }

        // Função para popular a lista de quarteirões
        function popularQuarteiroes(loteamento) {
            const container = $('#opcoesQuarteiroes');
            container.empty();

            if (!arrayCamadas.quarteirao || arrayCamadas.quarteirao.length === 0) {
                container.html('<div class="alert alert-info">Nenhum quarteirão encontrado para este loteamento.</div>');
                return;
            }

            // Filtra quarteirões que estão dentro do loteamento selecionado
            const quarteiroesDoLoteamento = arrayCamadas.quarteirao.filter(quarteirao => {
                // Só considera quarteirões que têm polígono (não linhas separadas)
                if (!quarteirao.polygon) return false;

                // Obtém as coordenadas do loteamento
                const coordenadasLoteamento = loteamento.coordenadas[0].coordinates[0].map(coord => ({
                    lat: coord[1],
                    lng: coord[0]
                }));

                // Verifica se o quarteirão está dentro do loteamento
                return quarteiraoEstaDentroDoLoteamento(quarteirao, coordenadasLoteamento);
            });

            //mostra os marcadores dos quarteirões de dentro do loteamento
            quarteiroesDoLoteamento.forEach(quarteirao => {
                if (quarteirao.marker) {
                    quarteirao.marker.setMap(MapFramework.map);
                }
            });

            //mostra todos os quarteirões de dentro do loteamento
            quarteiroesDoLoteamento.forEach(quarteirao => {

                quarteirao.polygon.setOptions({
                    clickable: true
                });

                quarteirao.polygon.addListener('click', function() {
                    
                    // Destaca o quarteirão clicado
                    quarteirao.polygon.setOptions({
                        strokeColor: 'white',
                    });

                    // Calcula o centro do polígono para centralizá-lo
                    const path = quarteirao.polygon.getPath();
                    const bounds = new google.maps.LatLngBounds();

                    // Adiciona todos os pontos aos bounds
                    for (let i = 0; i < path.getLength(); i++) {
                        bounds.extend(path.getAt(i));
                    }

                    // Centraliza no quarteirão e aplica zoom 18
                    //MapFramework.map.setCenter(bounds.getCenter());
                    //MapFramework.map.setZoom(18);

                    // Seleciona o radio correspondente no divCadastro2
                    const nomeQuarteirao = quarteirao.properties.impreciso_name || quarteirao.id;
                    const radioSelector = `input[name="quarteirao"][data-nome="${nomeQuarteirao}"]`;
                    const radioElement = $(radioSelector);

                    if (radioElement.length > 0) {
                        radioElement.prop('checked', true).trigger('change');

                        // Faz scroll automático para o radio selecionado
                        const radioContainer = $('#divCadastro2 .div-cadastro-body');
                        const radioOption = radioElement.closest('.opcao-quarteirao');

                        if (radioContainer.length > 0 && radioOption.length > 0) {
                            const containerScrollTop = radioContainer.scrollTop();
                            const containerHeight = radioContainer.height();
                            const optionTop = radioOption.position().top;
                            const optionHeight = radioOption.outerHeight();

                            // Calcula se o elemento está visível
                            const isVisible = (optionTop >= 0) && (optionTop + optionHeight <= containerHeight);

                            if (!isVisible) {
                                // Scroll para centralizar o elemento selecionado
                                const targetScrollTop = containerScrollTop + optionTop - (containerHeight / 2) + (optionHeight / 2);
                                radioContainer.animate({
                                    scrollTop: targetScrollTop
                                }, 300);
                            }
                        }
                    }
                });

                //mostra o poligono do quarteirão
                quarteirao.polygon.setMap(MapFramework.map);
            });

            if (quarteiroesDoLoteamento.length === 0) {
                container.html('<div class="alert alert-info">Nenhum quarteirão encontrado dentro deste loteamento.</div>');
                return;
            }

            // Carrega os dados complementares e depois cria os botões
            Promise.all([
                carregarDadosQuarteiroes(),
                carregarDadosPDFsQuarteiroes()
            ]).then(function() {
                // Ordena os quarteirões numericamente
                quarteiroesDoLoteamento.sort((a, b) => {
                    const nomeA = a.properties.impreciso_name || a.id;
                    const nomeB = b.properties.impreciso_name || b.id;
                    
                    // Extrai números dos nomes dos quarteirões
                    const numeroA = parseInt(nomeA.replace(/\D/g, '')) || 0;
                    const numeroB = parseInt(nomeB.replace(/\D/g, '')) || 0;
                    
                    return numeroA - numeroB;
                });
                
                // Cria os botões radio para cada quarteirão
                quarteiroesDoLoteamento.forEach((quarteirao, index) => {
                    // Obtém o nome do quarteirão (impreciso_name ou id)
                    const nomeQuarteirao = quarteirao.properties.impreciso_name || quarteirao.id;

                    // Busca informações complementares
                    const infoComplementar = obterInfoComplementarQuarteirao(loteamento.nome, nomeQuarteirao);

                    // Busca PDFs do quarteirão
                    const pdfsQuarteirao = obterPDFsQuarteirao(nomeQuarteirao);
                    const temPDFs = pdfsQuarteirao && pdfsQuarteirao.length > 0;

                    // Cria o texto do small baseado nas informações disponíveis
                    let textoSmall = ''; //`ID: ${quarteirao.id}`;
                    if (infoComplementar) {
                        textoSmall += `Quadras: ${infoComplementar}`;
                    }

                    const opcao = $(`
                        <div class="opcao-quarteirao">
                            <div class="d-flex align-items-start">
                                <input style="margin-top: 2px;" type="radio" id="quarteirao_${quarteirao.id}" data-nome="${nomeQuarteirao}" name="quarteirao" value="${quarteirao.id}">
                                <label for="quarteirao_${quarteirao.id}" class="flex-grow-1">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            Quarteirão ${nomeQuarteirao}
                                            <small class="d-block text-muted">${textoSmall}</small>
                                        </div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm ms-2 btn-docs-quarteirao" data-quarteirao="${nomeQuarteirao}" style="margin-top: -10px; font-size: 10px; padding: 2px 6px; border-radius: 3px;">
                                            Docs
                                        </button>
                                    </div>
                                </label>
                            </div>
                            ${temPDFs ? 
                                `<div class="submenu-pdfs">
                                    ${pdfsQuarteirao.map(arquivo => {
                                        return `<a href="javascript:void(0)" onclick="abrirPDFQuarteirao('${arquivo}')" title="${arquivo}">
                                            <i class="fas fa-file-pdf"></i>${arquivo.length > 20 ? arquivo.substring(0, 20) + '...' : arquivo}
                                        </a>`;
                                    }).join('')}
                                </div>` : 
                                '<div class="submenu-pdfs"><em class="text-muted">Sem PDFs</em></div>'
                            }
                        </div>
                    `);

                    container.append(opcao);

                });

                // Adiciona evento para destacar seleção de quarteirão
                $('input[name="quarteirao"]').on('change', function() {
                    const quarteiraoId = $(this).val();
                    const nomeQuarteirao = $(this).data('nome');

                    // Define as variáveis globais do quarteirão
                    quarteiraoAtualSelecionado = nomeQuarteirao;
                    quarteiraoIdAtualSelecionado = quarteiraoId;

                    //console.log('Quarteirão selecionado:', nomeQuarteirao, 'ID:', quarteiraoId);

                    if (quarteiraoId) {
                        // Destaca o quarteirão selecionado passando apenas o ID
                        destacarQuarteiraoSelecionado(nomeQuarteirao, quarteiraoId);
                    }
                });

                // Adiciona evento para o botão Docs de cada quarteirão
                $('.btn-docs-quarteirao').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const nomeQuarteirao = $(this).data('quarteirao');
                    alert(`Aqui você poderá incluir, alterar e excluir documentos relacionados com o quarteirão ${nomeQuarteirao}`);
                });
            });

        }

        // Função para verificar se um quarteirão está dentro de um loteamento
        function quarteiraoEstaDentroDoLoteamento(quarteirao, coordenadasLoteamento) {
            // Só trabalha com quarteirões que têm polígono
            if (!quarteirao.polygon || !quarteirao.polygon.getPath) return false;

            let coordenadasQuarteirao = [];

            // Obtém as coordenadas do polígono
            const path = quarteirao.polygon.getPath();
            for (let i = 0; i < path.getLength(); i++) {
                const latLng = path.getAt(i);
                coordenadasQuarteirao.push({
                    lng: latLng.lng(),
                    lat: latLng.lat()
                });
            }

            if (coordenadasQuarteirao.length === 0) return false;

            // Verifica se pelo menos um ponto do quarteirão está dentro do loteamento
            return coordenadasQuarteirao.some(ponto =>
                pontoDentroDoPoligono(ponto, coordenadasLoteamento)
            );
        }

        // Função para destacar o quarteirão selecionado
        function destacarQuarteiraoSelecionado(nomeQuarteirao, idQuarteirao) {
            //console.log(nomeQuarteirao, idQuarteirao);

            // Primeiro, oculta TODOS os marcadores
            if (arrayCamadas.marcador_quadra) {
                arrayCamadas.marcador_quadra.forEach(marker => {
                    marker.setMap(null);
                });
            }

            // Primeiro, redefine TODOS os quarteirões visíveis para cor branca
            if (arrayCamadas.quarteirao) {
                arrayCamadas.quarteirao.forEach(obj => {
                    // Só mexe nos quarteirões que estão visíveis no mapa
                    if (obj.polygon && obj.polygon.getMap()) {
                        obj.polygon.setOptions({
                            strokeColor: '#1276C3'
                        });
                    }
                });
            }

            // Obtém o quarteirão pelo ID usando a função do framework
            const quarteirao = MapFramework.obterQuarteiraoPorId(idQuarteirao);

            if (!quarteirao) {
                return;
            }

            // Destaca APENAS o quarteirão selecionado em branco
            if (quarteirao.polygon) {
                quarteirao.polygon.setOptions({
                    strokeColor: 'white',
                    zIndex: 15
                });
                quarteirao.polygon.setMap(MapFramework.map);
            }

            if (quarteirao.marker) {
                quarteirao.marker.setMap(MapFramework.map);
            }

            // Mostra o botão de filtros quando um quarteirão é selecionado
            $('#btnFiltroCores').fadeIn(200);

            // Se o checkbox "Imóveis" não está marcado, mostra apenas os marcadores do quarteirão
            if (!$('#chkMarcadores').is(':checked')) {
                // Mostra apenas os marcadores que pertencem a este quarteirão e respeitam os filtros
                if (arrayCamadas.marcador_quadra) {
                    arrayCamadas.marcador_quadra.forEach(marker => {
                        if (marker.quarteirao === nomeQuarteirao) {
                            const corMarcador = marker.content.style.backgroundColor;
                            let deveMostrar = false;

                            // Mapeia cores para checkboxes (suporta RGB e hexadecimal)
                            if (corMarcador.includes('#e84646') || corMarcador.includes('rgb(232, 70, 70)') || corMarcador === 'red') { // Vermelho
                                deveMostrar = $('#chkVermelho').is(':checked');
                            } else if (corMarcador.includes('#eddf47') || corMarcador.includes('rgb(237, 223, 71)')) { // Amarelo
                                deveMostrar = $('#chkAmarelo').is(':checked');
                            } else if (corMarcador.includes('#ed7947') || corMarcador.includes('rgb(237, 121, 71)')) { // Laranja
                                deveMostrar = $('#chkLaranja').is(':checked');
                            } else if (corMarcador.includes('#32CD32') || corMarcador.includes('rgb(50, 205, 50)')) { // Verde
                                deveMostrar = $('#chkVerde').is(':checked');
                            } else if (corMarcador.includes('#47cced') || corMarcador.includes('rgb(71, 204, 237)')) { // Azul
                                deveMostrar = $('#chkAzul').is(':checked');
                            }else if (corMarcador.includes('#7c7c7c') || corMarcador.includes('rgb(124, 124, 124)')) { // Azul
                                deveMostrar = $('#chkCinza').is(':checked');
                            }

                            if (deveMostrar) {
                                marker.setMap(MapFramework.map);
                            }
                        }
                    });
                }
            } else {
                // Se o checkbox "Imóveis" está marcado, aplica os filtros normalmente (mostra todos)
                aplicarFiltroCores();
            }

            // Adiciona classe visual para destacar a opção selecionada
            $('.opcao-quarteirao').removeClass('selected');
            $(`#quarteirao_${idQuarteirao}`).closest('.opcao-quarteirao').addClass('selected');
        }


        // Variáveis globais para controle do tooltip
        let marcadorAtualTooltip = null;
        let marcadorIdAtual = null;

        // Variáveis globais para controle do quarteirão e quadra selecionados
        let quarteiraoAtualSelecionado = null;
        let quarteiraoIdAtualSelecionado = null;


        // Função para remover todos os destaques
        function removerDestaques() {
            // Remove destaque dos loteamentos
            if (window.loteamentosLayer) {
                window.loteamentosLayer.forEach(polygon => {
                    polygon.setOptions({
                        strokeColor: 'gray',
                        fillColor: 'gray',
                        strokeWeight: 2,
                        fillOpacity: 0.4
                    });
                });
            }

            // Restaura cores originais de todas as quadras (mantendo grossuras originais)
            if (arrayCamadas["quadra"]) {
                arrayCamadas["quadra"].forEach(quadra => {
                    quadra.setOptions({
                        strokeColor: quadra.corOriginal || 'red',
                        fillColor: quadra.corOriginal || 'red',
                        fillOpacity: 0.30
                        // strokeWeight não é alterado - mantém o original
                    });
                    quadra.desativado = false;
                });
            }

            // Restaura cores originais de todos os lotes (mantendo grossuras originais)
            if (arrayCamadas["lote"]) {
                arrayCamadas["lote"].forEach(lote => {
                    lote.setOptions({
                        strokeColor: lote.corOriginal || 'red',
                        fillColor: lote.corOriginal || 'red',
                        fillOpacity: 0.30
                        // strokeWeight não é alterado - mantém o original
                    });
                    lote.desativado = false;
                });
            }

            // OCULTA COMPLETAMENTE todos os quarteirões do mapa
            if (arrayCamadas.quarteirao) {
                arrayCamadas.quarteirao.forEach(obj => {
                    if (obj.polygon) obj.polygon.setMap(null);
                    if (obj.marker) obj.marker.setMap(null);
                    if (obj.polyline) obj.polyline.setMap(null);
                });
            }

        }

        // Função para mostrar tooltip/infowindow do marcador com dados da planilha
        function mostrarTooltipMarcador(marker, event) {
            // Conteúdo inicial com loading
            let conteudo = `
                <div style="padding: 15px; min-width: 300px; font-family: Arial, sans-serif;">
                    <h3 style="margin: 0 0 15px 0; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px;">Desenho</h3>
                    <p style="margin: 5px 0;"><strong>ID:</strong> ${marker.identificadorBanco || 'N/A'}</p>
                    <p style="margin: 5px 0;"><strong>Quarteirão:</strong> ${marker.quarteirao || 'N/A'}</p>
                    <p style="margin: 5px 0;"><strong>Quadra:</strong> ${marker.quadra || 'N/A'}</p>
                    <p style="margin: 5px 0;"><strong>Lote:</strong> ${marker.numeroMarcador || 'N/A'}</p>
                    
                    <hr style="margin: 15px 0; border: 1px solid #ddd;">
                    <h3 style="margin: 0 0 15px 0; color: #333; border-bottom: 2px solid #28a745; padding-bottom: 5px;">Cadastro</h3>
                    
                    <div id="loading-cadastro" style="text-align: center; padding: 20px;">
                        <div style="display: inline-block; width: 20px; height: 20px; border: 3px solid #f3f3f3; border-top: 3px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                        <p style="margin: 10px 0 0 0; color: #666;">Carregando dados...</p>
                    </div>
                    
                    <style>
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
                </div>
            `;
            
            // Cria ou atualiza o infowindow
            if (!window.infoWindowMarcador) {
                window.infoWindowMarcador = new google.maps.InfoWindow();
            }
            
            window.infoWindowMarcador.setContent(conteudo);
            window.infoWindowMarcador.setPosition(marker.position);
            window.infoWindowMarcador.open(MapFramework.map);
            
            // Faz a busca AJAX dos dados do cadastro
            buscarDadosCadastroMarcador(marker.quarteirao, marker.quadra, marker.numeroMarcador);
        }

        // Função AJAX para buscar dados do cadastro
        function buscarDadosCadastroMarcador(quarteirao, quadra, lote) {
            console.log('Iniciando busca AJAX com:', {quarteirao, quadra, lote});
            
            $.ajax({
                url: 'buscar_dados_marcador_cadastro.php',
                type: 'POST',
                data: {
                    quarteirao: quarteirao,
                    quadra: quadra,
                    lote: lote
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Resposta recebida:', response);
                    // Remove o loading
                    $('#loading-cadastro').remove();
                    
                    let conteudoCadastro = '';
                    
                    if (response && !response.erro) {
                        // Exibe todas as colunas da tabela cadastro
                        Object.keys(response).forEach(chave => {
                            if (chave !== 'id') { // Pula o ID do banco para evitar duplicação
                                let valor = response[chave];
                                
                                // Formata valores específicos
                                if (chave === 'valor_venal' && valor) {
                                    valor = 'R$ ' + parseFloat(valor).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                                } else if ((chave === 'area_terreno' || chave === 'area_construida') && valor) {
                                    valor = valor + ' m²';
                                } else if (chave === 'data_cadastro' || chave === 'data_atualizacao') {
                                    if (valor) {
                                        valor = new Date(valor).toLocaleDateString('pt-BR');
                                    }
                                }
                                
                                // Converte chave para formato legível
                                const chaveFormatada = chave.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                
                                conteudoCadastro += `<p style="margin: 5px 0;"><strong>${chaveFormatada}:</strong> ${valor || 'Não informado'}</p>`;
                            }
                        });
                    } else {
                        conteudoCadastro = `<p style="color: #666; font-style: italic; margin: 10px 0;">${response.erro || 'Nenhum dado encontrado na planilha de cadastro para este lote.'}</p>`;
                    }
                    
                    // Atualiza o conteúdo do infowindow
                    const conteudoAtual = window.infoWindowMarcador.getContent();
                    const novoConteudo = conteudoAtual.replace(
                        '<div id="loading-cadastro" style="text-align: center; padding: 20px;"><div style="display: inline-block; width: 20px; height: 20px; border: 3px solid #f3f3f3; border-top: 3px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite;"></div><p style="margin: 10px 0 0 0; color: #666;">Carregando dados...</p></div>',
                        conteudoCadastro
                    );
                    
                    window.infoWindowMarcador.setContent(novoConteudo);
                },
                error: function(xhr, status, error) {
                    console.error('Erro na requisição:', error);
                    
                    // Remove o loading e mostra erro
                    $('#loading-cadastro').remove();
                    
                    const conteudoAtual = window.infoWindowMarcador.getContent();
                    const novoConteudo = conteudoAtual.replace(
                        '<div id="loading-cadastro" style="text-align: center; padding: 20px;"><div style="display: inline-block; width: 20px; height: 20px; border: 3px solid #f3f3f3; border-top: 3px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite;"></div><p style="margin: 10px 0 0 0; color: #666;">Carregando dados...</p></div>',
                        '<p style="color: #e74c3c; font-style: italic; margin: 10px 0;">Erro ao carregar dados do cadastro.</p>'
                    );
                    
                    window.infoWindowMarcador.setContent(novoConteudo);
                }
            });
        }

        
    </script>
</body>

</html>