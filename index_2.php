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
        echo 'erro';
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

    <!-- PDF.js para o leitor integrado -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        // Configure PDF.js worker to avoid deprecated warning
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>

    <!-- Fabric.js para canvas do PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>

    <!-- Interact.js para manipulação -->
    <script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>

    <!-- PDF Viewer Integrado -->
    <script src="pdfViewerIntegrado.js"></script>

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

        /* Estilos para o leitor PDF integrado */
        #divLeitorPDF {
            display: none;
            width: 100%;
            height: 100vh;
            background-color: rgb(63, 63, 63);
            position: relative;
            z-index: 1000;
            /* Garantir que ocupe a tela toda logo abaixo do mapa */
            top: 0;
            left: 0;
        }

        #divLeitorPDF .toolbar-buttons {
            gap: 10px;
        }

        #divLeitorPDF .toolbar-buttons .btn {
            min-width: 80px;
        }

        #divLeitorPDF .mode-indicator {
            position: absolute;
            top: 70px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px;
            border-radius: 5px;
            z-index: 1001;
            display: none;
        }

        gmp-internal-camera-control {
            display: none !important;
        }

        .divContainerMap {
            width: 100%;
            height: 100vh;
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
            top: 70px;
            left: 10px;
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
            display: flex;
            gap: 20px;
            justify-content: center;
            align-items: flex-start;
            min-height: 30px;
        }

        .range-control {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .range-control label {
            font-size: 12px;
            font-weight: 500;
            color: white;
            white-space: nowrap;
            margin: 0;
        }

        .range-control input[type="range"] {
            width: 150px;
        }

        #customRange2 {
            -webkit-appearance: none;
            appearance: none;
        }

        /* Chrome, Edge, Safari */
        #customRange2::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            background: #4CAF50;
        }

        /* Firefox */
        #customRange2::-moz-range-thumb {
            background: #4CAF50;
        }

        /* IE (se precisar) */
        #customRange2::-ms-thumb {
            background: #4CAF50;
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

        /* Estilos para o controle de desenhos da prefeitura */
        #controleDesenhosPrefeitura {
            position: absolute;
            top: 70px;
            left: 5px;
            /* Posicionado ao lado do controle de quadrículas */
            z-index: 1000;
            display: none;
            /* Inicialmente oculto */
            flex-direction: column;
            background-color: rgba(0, 0, 0, 1);
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 0 10px 0 rgba(0, 0, 0, 0.5);
            transition: all 0.3s ease;
            color: white;
            min-width: 200px;
        }

        #controleDesenhosPrefeitura.show {
            display: flex;
        }

        #controleDesenhosPrefeitura .grade-direcoes {
            display: grid;
            grid-template-columns: 40px 40px 40px;
            grid-template-rows: 30px 30px 30px;
            gap: 3px;
            margin-bottom: 10px;
            justify-content: center;
            align-items: center;
        }

        #controleDesenhosPrefeitura .grade-botoes {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 3px;
            margin-bottom: 10px;
        }

        .controle-desenhos-btn {
            width: 40px;
            height: 30px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .controle-desenhos-btn:hover {
            background-color: rgba(255, 255, 255, 0.8);
            color: #000;
        }

        .controle-desenhos-btn-direcao {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .controle-desenhos-btn-resetar {
            background-color: #ffc107;
            color: #000;
        }

        .controle-desenhos-btn-salvar {
            background-color: #28a745;
            color: white;
        }

        .controle-desenhos-btn-cancelar {
            background-color: #dc3545;
            color: white;
        }

        .controle-desenhos-btn.vazio {
            background-color: transparent;
            cursor: default;
        }

        .controle-desenhos-btn.vazio:hover {
            background-color: transparent;
            color: white;
        }

        #controleDesenhosPrefeitura .selecao-distancia {
            margin-bottom: 10px;
            padding: 8px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
        }

        #controleDesenhosPrefeitura .selecao-distancia label {
            font-size: 12px;
            margin-right: 10px;
            cursor: pointer;
        }

        #controleDesenhosPrefeitura .selecao-distancia input[type="radio"] {
            margin-right: 5px;
        }

        /* Estilos para controles de rotação */
        #controleDesenhosPrefeitura .controles-rotacao {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-bottom: 10px;
        }

        .controle-desenhos-btn-rotacao {
            width: 60px;
            height: 30px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .controle-desenhos-btn-rotacao-individual {
            background-color: #17a2b8;
            color: white;
            font-size: 10px;
        }

        .controle-desenhos-btn-rotacao-coletiva {
            background-color: #6f42c1;
            color: white;
            font-size: 10px;
        }

        .controle-desenhos-btn-rotacao:hover {
            background-color: rgba(255, 255, 255, 0.8);
            color: #000;
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

        /* Estilos para os radio buttons dos PDFs */
        .pdf-option {
            margin: 4px 0;
            display: flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 3px;
            transition: background-color 0.2s;
        }

        .pdf-option:hover {
            background-color: #f0f0f0;
        }

        .pdf-option input[type="radio"] {
            margin-right: 6px;
            margin: 0;
        }

        .pdf-option label {
            margin: 0 !important;
            font-weight: normal !important;
            cursor: pointer;
            flex: 1;
            font-size: 11px !important;
            line-height: 1.2;
        }

        .pdf-option input[type="radio"]:checked+label {
            color: #007bff;
            font-weight: 600 !important;
        }

        .pdf-option input[type="radio"]:disabled+label {
            color: #999 !important;
            opacity: 0.6;
        }

        .pdf-option input[type="radio"]:disabled {
            opacity: 0.4;
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

        /* Estilos para as opções de quadras */
        .div-cadastro-body .opcao-quadra {
            margin-bottom: 3px;
            padding-left: 20px;
            cursor: pointer;
        }

        .div-cadastro-body .opcao-quadra input[type="checkbox"] {
            margin-right: 6px;
            margin-top: 0;
        }

        .div-cadastro-body .opcao-quadra label {
            font-weight: normal;
            color: #555;
            cursor: pointer;
            margin: 0;
            display: inline-block;
            font-size: 11px;
            line-height: 1.2;
        }

        .div-cadastro-body .opcao-quadra.selected label {
            color: #007bff;
            font-weight: 500;
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

        /* Estilo para os inputs quando estiverem no modo de inserção */
        #inputLoteAtual.modo-insercao,
        #inputQuadraAtual.modo-insercao {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
        }

        #inputLoteAtual.modo-insercao:focus,
        #inputQuadraAtual.modo-insercao:focus {
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

        .marker-imagem-aerea{
            width: 15px;
            height: 15px;
            cursor: pointer;
            transform: translate(0, 10px);
        }

        /* Estilos para as labels de medição */
        .measurement-label {
            background-color: white;
            padding: 4px 8px;
            border: 2px solid #333;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: #333;
            white-space: nowrap;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            pointer-events: none;
        }

        .measurement-area-label {
            background-color: #4CAF50;
            color: white;
            padding: 6px 12px;
            border: 2px solid #2E7D32;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            white-space: nowrap;
            box-shadow: 0 3px 8px rgba(0,0,0,0.4);
            pointer-events: none;
        }

        .measurement-distance-label {
            background-color: #2196F3;
            color: white;
            padding: 6px 12px;
            border: 2px solid #1565C0;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            white-space: nowrap;
            box-shadow: 0 3px 8px rgba(0,0,0,0.4);
            pointer-events: none;
        }
    </style>
</head>

<body>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); z-index: 9999;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: white;">
            <div class="spinner-border" role="status" style="width: 4rem; height: 4rem; margin-bottom: 20px;">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <h4>Salvando alterações...</h4>
            <p>Por favor, aguarde.</p>
        </div>
    </div>

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
                    <button onclick="MapFramework.navegarQuadricula(this)" data-quadricula="" id="btn_noroeste" class="controleNavegacaoQuadriculas-btn btn btn-light">NO</button>
                </div>
                <div class="subDivControle">
                    <button onclick="MapFramework.navegarQuadricula(this)" data-quadricula="" id="btn_norte" class="controleNavegacaoQuadriculas-btn btn btn-light">N</button>
                </div>
                <div class="subDivControle">
                    <button onclick="MapFramework.navegarQuadricula(this)" data-quadricula="" id="btn_nordeste" class="controleNavegacaoQuadriculas-btn btn btn-light">NE</button>
                </div>
            </div>
            <div>
                <div class="subDivControle">
                    <button onclick="MapFramework.navegarQuadricula(this)" data-quadricula="" id="btn_oeste" class="controleNavegacaoQuadriculas-btn btn btn-light">O</button>
                </div>
                <div class="subDivControle">
                    <button id="btn_centro" class="controleNavegacaoQuadriculas-btn2 btn btn-light">C</button>
                </div>
                <div class="subDivControle">
                    <button onclick="MapFramework.navegarQuadricula(this)" data-quadricula="" id="btn_leste" class="controleNavegacaoQuadriculas-btn btn btn-light">E</button>
                </div>
            </div>
            <div>
                <div class="subDivControle">
                    <button onclick="MapFramework.navegarQuadricula(this)" data-quadricula="" id="btn_sudoeste" class="controleNavegacaoQuadriculas-btn btn btn-light">SW</button>
                </div>
                <div class="subDivControle">
                    <button onclick="MapFramework.navegarQuadricula(this)" data-quadricula="" id="btn_sul" class="controleNavegacaoQuadriculas-btn btn btn-light">S</button>
                </div>
                <div class="subDivControle">
                    <button onclick="MapFramework.navegarQuadricula(this)" data-quadricula="" id="btn_sudeste" class="controleNavegacaoQuadriculas-btn btn btn-light">SE</button>
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

    <!-- Controle de desenhos da prefeitura -->
    <div id="controleDesenhosPrefeitura">
        <div style="text-align: center; margin-bottom: 10px; font-weight: bold; font-size: 14px;">
            Controle Desenhos
        </div>

        <!-- Seleção de distância -->
        <div class="selecao-distancia">
            <div style="margin-bottom: 5px; font-weight: bold; font-size: 11px;">Distância (metros):</div>
            <label><input type="radio" name="distancia" value="1" checked> 1m</label>
        </div>

        <!-- Grade de direções 3x3 -->
        <div class="grade-direcoes">
            <button class="controle-desenhos-btn controle-desenhos-btn-direcao" onclick="moverDesenhosPrefeitura('noroeste')" title="Noroeste">↖</button>
            <button class="controle-desenhos-btn controle-desenhos-btn-direcao" onclick="moverDesenhosPrefeitura('norte')" title="Norte">↑</button>
            <button class="controle-desenhos-btn controle-desenhos-btn-direcao" onclick="moverDesenhosPrefeitura('nordeste')" title="Nordeste">↗</button>

            <button class="controle-desenhos-btn controle-desenhos-btn-direcao" onclick="moverDesenhosPrefeitura('oeste')" title="Oeste">←</button>
            <button class="controle-desenhos-btn vazio"></button>
            <button class="controle-desenhos-btn controle-desenhos-btn-direcao" onclick="moverDesenhosPrefeitura('leste')" title="Leste">→</button>

            <button class="controle-desenhos-btn controle-desenhos-btn-direcao" onclick="moverDesenhosPrefeitura('sudoeste')" title="Sudoeste">↙</button>
            <button class="controle-desenhos-btn controle-desenhos-btn-direcao" onclick="moverDesenhosPrefeitura('sul')" title="Sul">↓</button>
            <button class="controle-desenhos-btn controle-desenhos-btn-direcao" onclick="moverDesenhosPrefeitura('sudeste')" title="Sudeste">↘</button>
        </div>

        <!-- Controles de rotação 
        <div class="controles-rotacao">
            <div style="display: flex; gap: 5px; margin-bottom: 5px;">
                <button class="controle-desenhos-btn-rotacao controle-desenhos-btn-rotacao-individual" onclick="rotacionarDesenhosPrefeitura('individual-esquerda')" title="Rotação Individual Esquerda">↶ Ind</button>
                <button class="controle-desenhos-btn-rotacao controle-desenhos-btn-rotacao-individual" onclick="rotacionarDesenhosPrefeitura('individual-direita')" title="Rotação Individual Direita">Ind ↷</button>
            </div>
            <div style="display: flex; gap: 5px;">
                <button class="controle-desenhos-btn-rotacao controle-desenhos-btn-rotacao-coletiva" onclick="rotacionarDesenhosPrefeitura('coletiva-esquerda')" title="Rotação Coletiva Esquerda">↶ Col</button>
                <button class="controle-desenhos-btn-rotacao controle-desenhos-btn-rotacao-coletiva" onclick="rotacionarDesenhosPrefeitura('coletiva-direita')" title="Rotação Coletiva Direita">Col ↷</button>
            </div>
        </div>
        -->

        <!-- Botões de ação -->
        <div class="grade-botoes">
            <button class="controle-desenhos-btn controle-desenhos-btn-resetar" onclick="resetarDesenhosPrefeitura()" title="Resetar">Reset</button>
            <button class="controle-desenhos-btn controle-desenhos-btn-salvar" onclick="salvarDesenhosPrefeitura()" title="Salvar">Salvar</button>
            <button class="controle-desenhos-btn controle-desenhos-btn-cancelar" onclick="cancelarControleDesenhos()" title="Cancelar">Cancel</button>
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

    <div class="divContainerMap">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">

                <!-- Botao voltar para o painel -->
                <button id="btnVoltarVisualizador" class="btn btn-light" onclick="voltarParaVisualizador()">Voltar</button>

                <!-- Título -->
                <a style="margin-left: 10px;" class="navbar-brand" href="#">Modo Editores</a>

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
                                    <input class="form-check-input" type="checkbox" id="chkUnidades" checked>
                                    <label class="form-check-label" for="chkUnidades">
                                        Edificações
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkPiscinas" checked>
                                    <label class="form-check-label" for="chkPiscinas">
                                        Piscinas
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkPoligono_lote" checked>
                                    <label class="form-check-label" for="chkPoligono_lote">
                                        Lotes Ortofoto
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkLotes" checked>
                                    <label class="form-check-label" for="chkLotes">
                                        Cortes dos lotes
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
                                    <input class="form-check-input" type="checkbox" id="chkCondominiosVerticais">
                                    <label class="form-check-label" for="chkCondominiosVerticais">
                                        Condomínios Verticais
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkCondominiosHorizontais">
                                    <label class="form-check-label" for="chkCondominiosHorizontais">
                                        Condomínios Horizontais
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkAreasPublicas">
                                    <label class="form-check-label" for="chkAreasPublicas">
                                        Áreas Públicas
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
                                        Marcadores
                                    </label>
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
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkImagensAereas">
                                    <label class="form-check-label" for="chkImagensAereas">
                                        Imagens Aéreas
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkStreetview">
                                    <label class="form-check-label" for="chkStreetview">
                                        Streetview
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkModoCadastro">
                                    <label class="form-check-label" for="chkModoCadastro">
                                        Loteamentos
                                    </label>
                                </div>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                        </ul>
                    </div>

                    <button id="btnIncluirPoligono" class="btn btn-primary">Quadra</button>
                    <button id="btnIncluirUnidade" class="btn" style="background-color: #ff00ff; color: white;">Edificação</button>
                    <button id="btnIncluirPiscina" class="btn" style="background-color: #00ffff; color: black;">Piscina</button>
                    <button id="btnIncluirLinha" class="btn btn-success">Lote</button>

                    <!-- Botão para finalizar desenho (aparece quando está em modo de desenho) -->
                    <button id="btnFinalizarDesenho" class="btn btn-secondary d-none">Sair do modo desenho</button>

                    <!-- Botão específico para sair do modo inserção de marcadores -->
                    <button id="btnSairModoMarcador" class="btn btn-secondary d-none">Sair do modo marcador</button>

                    <!-- Botões condicionais (aparecem se há seleção) -->
                    <button id="btnEditar" class="btn btn-warning d-none">Editar</button>
                    <button id="btnExcluir" class="btn btn-danger d-none">Excluir</button>

                    <!-- Botão Sair da Edição (aparece quando está em modo de edição) -->
                    <button id="btnSairEdicao" class="btn btn-secondary d-none">Sair da Edição</button>

                    <div class="divControle">
                        <div class="range-control">
                            <label for="customRange1">Opacidade</label>
                            <input min="0" max="1" step="0.1" type="range" class="form-range" id="customRange1" value="0.3" title="Opacidade dos desenhos">
                        </div>
                        <div class="range-control">
                            <label for="customRange2">Espessura Lotes</label>
                            <input min="0.1" max="1" step="0.1" type="range" class="form-range" id="customRange2" value="0.5" title="Espessura das linhas dos lotes">
                        </div>
                    </div>

                    <!-- Botão Régua -->
                    <div class="btn-group">
                        <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-ruler-combined"></i> Régua
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="iniciarMedicaoArea(); event.preventDefault();">
                                <i class="fas fa-draw-polygon"></i> Medir Área
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="iniciarMedicaoDistancia(); event.preventDefault();">
                                <i class="fas fa-ruler"></i> Medir Distância
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="iniciarMedicaoCirculo(); event.preventDefault();">
                                <i class="fas fa-circle"></i> Medir Círculo
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-warning d-none" href="#" id="btnLimparMedicoes" onclick="limparTodasMedicoes(); event.preventDefault();">
                                <i class="fas fa-trash"></i> Limpar Todas as Medições
                            </a></li>
                            <li><a class="dropdown-item text-danger d-none" href="#" id="btnCancelarMedicao" onclick="cancelarMedicao(); event.preventDefault();">
                                <i class="fas fa-times"></i> Sair do Modo Régua
                            </a></li>
                        </ul>
                    </div>


                    <button data-loteamento="" data-arquivos="" data-quadricula="" onclick="desenharNoPDF(this)" id="btnLerPDF" class="btn btn-warning d-none">Desenhar no PDF</button>

                    <!-- Botões Cadastro removidos - agora é uma camada no dropdown -->
                    <!-- <button id="btnCadastro" class="btn btn-info">Cadastro</button> -->

                    <!-- Botão Sair do Cadastro (aparece quando entra no modo cadastro) -->
                    <!-- <button id="btnSairCadastro" class="btn btn-secondary d-none">Sair do Cadastro</button> -->

                    <!-- Botão Marcador e inputs text -->
                    <button id="btnIncluirMarcador" class="btn btn-danger d-none">Marcador</button>
                    <input type="text" id="inputLoteAtual" class="form-control" style="width: 80px; display: none;" placeholder="Lote">
                    <input type="text" id="inputQuadraAtual" class="form-control" style="width: 80px; display: none;" placeholder="Quadra">

                </div>

                <!-- Botão Sair -->
                <div class="d-flex">
                    <a href="logout.php" class="btn btn-danger">Sair</a>
                </div>
            </div>
        </nav>

        <div id="map"></div>
    </div>

    <!-- Div do Leitor de PDF integrado - Fora da divContainerMap -->
    <div id="divLeitorPDF" style="display: none; width: 100%; height: 100vh; background-color: rgb(63, 63, 63); position: relative;">
        <!-- Carregamento dinâmico gerenciado pelo JavaScript -->

        <!-- Cabeçalho com botão fechar -->
        <div id="cabecalhoLeitorPDF" style="position: absolute; top: 0; left: 0; right: 0; height: 60px; background-color: #212529; z-index: 1001; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; opacity: 1;">
            <div class="d-flex align-items-center flex-grow-1 gap-2 toolbar-buttons">
                <!-- Botão Fechar -->
                <button id="btnFecharLeitorPDF" class="btn btn-danger">
                    <i class="fas fa-times"></i> Fechar
                </button>

                <!-- Carregar PDFs - Oculto (apenas automático) -->
                <input type="file" id="pdfInputIntegrado" accept=".pdf" multiple style="display: none;">

                <!-- Ferramentas de desenho -->
                <button id="btnIncluirPoligonoIntegrado" class="btn btn-secondary" style="display: none;">
                    <i class="fas fa-draw-polygon"></i> Polígono
                </button>
                <button id="btnIncluirMarcadorIntegrado" class="btn btn-secondary" style="display: none;">
                    <i class="fas fa-map-marker-alt"></i> Marcador
                </button>
                <button id="btnDeleteDesenhoIntegrado" class="btn btn-secondary" style="display: none;">
                    <i class="fas fa-trash-alt"></i> Deletar
                </button>

                <!-- Controles de modo -->
                <button id="btnTravamentoIntegrado" class="btn btn-info">
                    <i class="fas fa-lock-open"></i> Travar PDF
                </button>



                <!-- Controles de rotação -->
                <div class="btn-group">
                    <button id="btnRotateLeftIntegrado" class="btn btn-outline-light" title="Rotacionar 90° Esquerda">
                        <i class="fas fa-undo"></i>
                    </button>
                    <button id="btnRotateRightIntegrado" class="btn btn-outline-light" title="Rotacionar 90° Direita">
                        <i class="fas fa-redo"></i>
                    </button>
                </div>



                <!-- Zoom controls -->
                <div class="btn-group">
                    <button id="btnFindPDFsIntegrado" class="btn btn-outline-light" title="Localizar PDF">
                        <i class="fas fa-crosshairs"></i>
                    </button>
                </div>

                <input type="text" id="inputLoteAtualIntegrado" class="form-control" style="width: 80px;" placeholder="Lote">
            </div>
        </div>

        <!-- Indicador de modo -->
        <div id="modeIndicatorIntegrado" class="mode-indicator" style="display: none;">
            Modo: Visualização
        </div>

        <!-- Container do canvas principal -->
        <div class="canvas-container" style="position: absolute; top: 60px; left: 0; width: 100%; height: calc(100vh - 60px); background: #2a2a2a; overflow: hidden;">
            <canvas id="mainCanvasIntegrado"></canvas>
        </div>

        <!-- Controles de seleção integrados -->

        <!-- Cópia do divCadastro para loteamentos na área do PDF -->
        <div id="divCadastroIntegrado" style="display:none; position: absolute; top: 70px; right: 60px; z-index: 1002; width: 280px; max-height: 300px; background-color: white; border: 1px solid #ddd; border-radius: 6px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); overflow: hidden;">
            <div class="div-cadastro-header">
                <h6>Loteamentos da Quadrícula <span id="quadriculaAtualIntegrado"></span></h6>
            </div>
            <div class="div-cadastro-body">
                <div id="opcoesLoteamentosIntegrado">
                    <!-- Os botões radio serão criados dinamicamente aqui -->
                </div>
            </div>
        </div>

        <!-- Cópia do divCadastro2 para quarteirões na área do PDF -->
        <div id="divCadastro2Integrado" style="display:none; position: absolute; top: 390px; right: 60px; z-index: 1002; width: 280px; max-height: 300px; background-color: white; border: 1px solid #ddd; border-radius: 6px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); overflow: hidden;">
            <div class="div-cadastro-header">
                <h6>Quarteirões do <span id="quarteiraoSelecionadoIntegrado"></span></h6>
            </div>
            <div class="div-cadastro-body">
                <div id="opcoesQuarteiresIntegrado">
                    <!-- Os botões radio serão criados dinamicamente aqui -->
                </div>
            </div>
        </div>

        <!-- Indicador de PDF removido: informações visíveis nos controles integrados -->

        <style>
            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }

            /* Estilo para botões ativos */
            .active-tool {
                background-color: #28a745 !important;
                border-color: #20c997 !important;
                color: white !important;
                box-shadow: 0 0 10px rgba(40, 167, 69, 0.5) !important;
                transform: scale(1.05);
            }

            .active-tool:hover {
                background-color: #218838 !important;
                border-color: #1e7e34 !important;
            }
        </style>

        <!-- Legenda removida -->

    </div>
    </div>

    <script>
        const paginaAtual = 'index_2';

        const arrayCamadas = {
            prefeitura: [],
            limite: [],
            marcador: [],
            marcador_quadra: [],
            quadriculas: [],
            ortofoto: [],
            quadra: [],
            unidade: [],
            piscina: [],
            lote: [],
            poligono_lote: [],
            lote_ortofoto: [],
            quarteirao: [],
            streetview: [],
            semCamadas: []
        };

        function voltarParaVisualizador(){
            window.location.href = `index_3.php?quadricula=${dadosOrto[0]['quadricula']}`;
        }

        // Sistema de medição de régua
        const Medicao = {
            ativa: false,
            tipo: null, // 'area', 'distancia' ou 'circulo'
            pontos: [],
            poligono: null,
            polilinha: null,
            circulo: null,
            centroCirculo: null,
            labels: [],
            medicoesSalvas: [], // Array para armazenar todas as medições
            listenerClick: null,
            listenerRightClick: null,
            listenerMouseMove: null,
            linhaTemporaria: null,
            labelTemporaria: null,

            limpar: function() {
                // Remove polígono/polilinha/círculo temporários
                if (this.poligono) {
                    this.poligono.setMap(null);
                    this.poligono = null;
                }
                if (this.polilinha) {
                    this.polilinha.setMap(null);
                    this.polilinha = null;
                }
                if (this.circulo) {
                    this.circulo.setMap(null);
                    this.circulo = null;
                }
                if (this.linhaTemporaria) {
                    this.linhaTemporaria.setMap(null);
                    this.linhaTemporaria = null;
                }
                if (this.labelTemporaria) {
                    this.labelTemporaria.setMap(null);
                    this.labelTemporaria = null;
                }
                this.centroCirculo = null;

                // Remove labels temporárias
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                // Remove todas as medições salvas
                this.medicoesSalvas.forEach(medicao => {
                    if (medicao.objeto) {
                        medicao.objeto.setMap(null);
                    }
                    if (medicao.labels) {
                        medicao.labels.forEach(l => l.setMap(null));
                    }
                });
                this.medicoesSalvas = [];

                // Remove listeners
                if (this.listenerClick) {
                    google.maps.event.removeListener(this.listenerClick);
                    this.listenerClick = null;
                }
                if (this.listenerRightClick) {
                    google.maps.event.removeListener(this.listenerRightClick);
                    this.listenerRightClick = null;
                }
                if (this.listenerMouseMove) {
                    google.maps.event.removeListener(this.listenerMouseMove);
                    this.listenerMouseMove = null;
                }

                this.pontos = [];
                this.ativa = false;
                this.tipo = null;
                MapFramework.map.setOptions({ draggableCursor: 'default' });
                
                // Reabilita interatividade de todos os objetos do mapa
                if (MapFramework && MapFramework.atualizarInteratividadeObjetos) {
                    MapFramework.atualizarInteratividadeObjetos(true);
                }
            },

            limparMedicaoAtual: function() {
                // Remove apenas a medição atual (temporária)
                if (this.poligono) {
                    this.poligono.setMap(null);
                    this.poligono = null;
                }
                if (this.polilinha) {
                    this.polilinha.setMap(null);
                    this.polilinha = null;
                }
                if (this.circulo) {
                    this.circulo.setMap(null);
                    this.circulo = null;
                }
                if (this.linhaTemporaria) {
                    this.linhaTemporaria.setMap(null);
                    this.linhaTemporaria = null;
                }
                if (this.labelTemporaria) {
                    this.labelTemporaria.setMap(null);
                    this.labelTemporaria = null;
                }
                
                // Remove labels temporárias
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];
                
                this.pontos = [];
                this.centroCirculo = null;
            },

            calcularDistancia: function(ponto1, ponto2) {
                const from = turf.point([ponto1.lng(), ponto1.lat()]);
                const to = turf.point([ponto2.lng(), ponto2.lat()]);
                return turf.distance(from, to, { units: 'kilometers' }) * 1000;
            },

            formatarDistancia: function(metros) {
                if (metros >= 1000) {
                    return (metros / 1000).toFixed(1) + ' km';
                }
                return metros.toFixed(1) + ' m';
            },

            formatarArea: function(metrosQuadrados) {
                if (metrosQuadrados >= 10000) {
                    return (metrosQuadrados / 10000).toFixed(1) + ' ha';
                }
                return metrosQuadrados.toFixed(1) + ' m²';
            },

            adicionarLabelAresta: function(ponto1, ponto2, distancia) {
                const lat = (ponto1.lat() + ponto2.lat()) / 2;
                const lng = (ponto1.lng() + ponto2.lng()) / 2;
                
                const offsetVertical = 0.0000015;
                const posicao = new google.maps.LatLng(lat + offsetVertical, lng);

                const el = document.createElement('div');
                el.className = 'measurement-label';
                el.textContent = this.formatarDistancia(distancia);

                const label = new google.maps.marker.AdvancedMarkerElement({
                    position: posicao,
                    content: el,
                    map: MapFramework.map,
                    zIndex: 1000,
                    gmpClickable: false
                });

                this.labels.push(label);
            },

            adicionarLabelCentral: function(texto, posicao, classe) {
                const el = document.createElement('div');
                el.className = classe;
                el.textContent = texto;

                const label = new google.maps.marker.AdvancedMarkerElement({
                    position: posicao,
                    content: el,
                    map: MapFramework.map,
                    zIndex: 1001,
                    gmpClickable: false
                });

                this.labels.push(label);
            },

            calcularCentroide: function(pontos) {
                if (pontos.length === 0) return null;

                const coords = pontos.map(p => [p.lng(), p.lat()]);
                coords.push([pontos[0].lng(), pontos[0].lat()]);

                const polygon = turf.polygon([coords]);
                const centroid = turf.centroid(polygon);
                
                return new google.maps.LatLng(
                    centroid.geometry.coordinates[1],
                    centroid.geometry.coordinates[0]
                );
            },

            atualizarDesenho: function() {
                if (this.tipo === 'area') {
                    this.atualizarPoligono();
                } else if (this.tipo === 'distancia') {
                    this.atualizarPolilinha();
                } else if (this.tipo === 'circulo') {
                    this.atualizarCirculo();
                }
            },

            atualizarPoligono: function() {
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                if (this.pontos.length < 2) return;

                if (!this.poligono) {
                    this.poligono = new google.maps.Polygon({
                        paths: this.pontos,
                        strokeColor: '#FF0000',
                        strokeOpacity: 0.8,
                        strokeWeight: 2,
                        fillColor: '#FF0000',
                        fillOpacity: 0.2,
                        map: MapFramework.map,
                        zIndex: 999,
                        editable: false,
                        draggable: false,
                        clickable: false
                    });
                } else {
                    this.poligono.setPath(this.pontos);
                }

                for (let i = 0; i < this.pontos.length; i++) {
                    const proximoIndice = (i + 1) % this.pontos.length;
                    const distancia = this.calcularDistancia(this.pontos[i], this.pontos[proximoIndice]);
                    this.adicionarLabelAresta(this.pontos[i], this.pontos[proximoIndice], distancia);
                }

                if (this.pontos.length >= 3) {
                    const coords = this.pontos.map(p => [p.lng(), p.lat()]);
                    coords.push([this.pontos[0].lng(), this.pontos[0].lat()]);

                    const polygon = turf.polygon([coords]);
                    const area = turf.area(polygon);
                    
                    const centroide = this.calcularCentroide(this.pontos);
                    this.adicionarLabelCentral(area.toFixed(1) + ' m²', centroide, 'measurement-area-label');
                }
            },

            atualizarPolilinha: function() {
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                if (this.pontos.length < 1) return;

                if (!this.polilinha) {
                    this.polilinha = new google.maps.Polyline({
                        path: this.pontos,
                        strokeColor: '#0000FF',
                        strokeOpacity: 0.8,
                        strokeWeight: 3,
                        map: MapFramework.map,
                        zIndex: 999,
                        editable: false,
                        draggable: false,
                        clickable: false
                    });
                } else {
                    this.polilinha.setPath(this.pontos);
                }

                // Adiciona apenas as labels nas arestas (sem total)
                for (let i = 0; i < this.pontos.length - 1; i++) {
                    const distancia = this.calcularDistancia(this.pontos[i], this.pontos[i + 1]);
                    this.adicionarLabelAresta(this.pontos[i], this.pontos[i + 1], distancia);
                }
            },

            iniciar: function(tipo) {
                // Se já está ativo, limpa apenas a medição atual (não as salvas)
                if (this.ativa) {
                    this.limparMedicaoAtual();
                } else {
                    // Se está iniciando pela primeira vez, limpa tudo
                    this.limpar();
                }
                
                // Remove listeners antigos antes de criar novos
                if (this.listenerClick) {
                    google.maps.event.removeListener(this.listenerClick);
                    this.listenerClick = null;
                }
                if (this.listenerRightClick) {
                    google.maps.event.removeListener(this.listenerRightClick);
                    this.listenerRightClick = null;
                }
                if (this.listenerMouseMove) {
                    google.maps.event.removeListener(this.listenerMouseMove);
                    this.listenerMouseMove = null;
                }
                
                this.ativa = true;
                this.tipo = tipo;
                MapFramework.map.setOptions({ draggableCursor: 'crosshair' });

                MapFramework.atualizarInteratividadeObjetos(false);

                this.listenerClick = MapFramework.map.addListener('click', (e) => {
                    if (this.tipo === 'circulo') {
                        if (this.pontos.length === 0) {
                            // Primeiro clique: define o centro
                            this.centroCirculo = e.latLng;
                            this.pontos.push(e.latLng);
                        } else if (this.pontos.length === 1) {
                            // Segundo clique: define o raio e finaliza
                            this.pontos.push(e.latLng);
                            this.atualizarDesenho();
                            this.finalizar();
                        }
                    } else {
                        this.pontos.push(e.latLng);
                        this.atualizarDesenho();
                    }
                });

                this.listenerRightClick = MapFramework.map.addListener('rightclick', (e) => {
                    if (this.tipo === 'area' && this.pontos.length < 3) {
                        alert('É necessário pelo menos 3 pontos para criar uma área.');
                        return;
                    }
                    if (this.tipo === 'distancia' && this.pontos.length < 2) {
                        alert('É necessário pelo menos 2 pontos para medir distância.');
                        return;
                    }
                    
                    this.finalizar();
                });

                this.listenerMouseMove = MapFramework.map.addListener('mousemove', (e) => {
                    if (this.pontos.length === 0) return;

                    if (this.linhaTemporaria) {
                        this.linhaTemporaria.setMap(null);
                    }
                    if (this.labelTemporaria) {
                        this.labelTemporaria.setMap(null);
                    }

                    const ultimoPonto = this.pontos[this.pontos.length - 1];

                    // Preview para círculo
                    if (this.tipo === 'circulo' && this.centroCirculo) {
                        const raioAtual = this.calcularDistancia(this.centroCirculo, e.latLng);
                        
                        // Círculo temporário
                        if (this.circulo) {
                            this.circulo.setMap(null);
                        }
                        this.circulo = new google.maps.Circle({
                            center: this.centroCirculo,
                            radius: raioAtual,
                            strokeColor: '#FF0000',
                            strokeOpacity: 0.5,
                            strokeWeight: 2,
                            fillColor: '#FF0000',
                            fillOpacity: 0.1,
                            map: MapFramework.map,
                            zIndex: 998,
                            clickable: false
                        });

                        // Label temporária com raio e área
                        const lat = (this.centroCirculo.lat() + e.latLng.lat()) / 2;
                        const lng = (this.centroCirculo.lng() + e.latLng.lng()) / 2;
                        const offsetVertical = 0.0000015;
                        const posicao = new google.maps.LatLng(lat + offsetVertical, lng);

                        const area = Math.PI * raioAtual * raioAtual;
                        const el = document.createElement('div');
                        el.className = 'measurement-label';
                        el.style.opacity = '0.8';
                        el.innerHTML = 'Raio: ' + this.formatarDistancia(raioAtual) + '<br>Área: ' + area.toFixed(1) + ' m²';

                        this.labelTemporaria = new google.maps.marker.AdvancedMarkerElement({
                            position: posicao,
                            content: el,
                            map: MapFramework.map,
                            zIndex: 1000,
                            gmpClickable: false
                        });
                    } else {
                        // Preview para polígono/polilinha
                        this.linhaTemporaria = new google.maps.Polyline({
                            path: [ultimoPonto, e.latLng],
                            strokeColor: this.tipo === 'area' ? '#FF0000' : '#0000FF',
                            strokeOpacity: 0.5,
                            strokeWeight: 2,
                            map: MapFramework.map,
                            zIndex: 998,
                            clickable: false
                        });

                        // Para distância, mostra a medida em tempo real
                        if (this.tipo === 'distancia') {
                            const distanciaAtual = this.calcularDistancia(ultimoPonto, e.latLng);
                            
                            const lat = (ultimoPonto.lat() + e.latLng.lat()) / 2;
                            const lng = (ultimoPonto.lng() + e.latLng.lng()) / 2;
                            const offsetVertical = 0.0000015;
                            const posicao = new google.maps.LatLng(lat + offsetVertical, lng);

                            const el = document.createElement('div');
                            el.className = 'measurement-label';
                            el.style.opacity = '0.8';
                            el.textContent = this.formatarDistancia(distanciaAtual);

                            this.labelTemporaria = new google.maps.marker.AdvancedMarkerElement({
                                position: posicao,
                                content: el,
                                map: MapFramework.map,
                                zIndex: 1000,
                                gmpClickable: false
                            });
                        }
                    }
                });
            },

            finalizar: function() {
                if (this.linhaTemporaria) {
                    this.linhaTemporaria.setMap(null);
                    this.linhaTemporaria = null;
                }
                if (this.labelTemporaria) {
                    this.labelTemporaria.setMap(null);
                    this.labelTemporaria = null;
                }

                // Salva a medição atual antes de limpar
                const medicaoSalva = {
                    tipo: this.tipo,
                    objeto: null,
                    labels: []
                };

                // Torna o polígono/polilinha/círculo editável
                if (this.poligono) {
                    this.poligono.setOptions({ 
                        editable: true,
                        draggable: false,
                        clickable: false
                    });
                    
                    // Recria as labels uma vez
                    this.atualizarMedidasPoligono();
                    medicaoSalva.labels = [...this.labels];
                    medicaoSalva.objeto = this.poligono;
                    
                    const poligonoSalvo = this.poligono;
                    const medicaoRef = medicaoSalva;
                    const path = this.poligono.getPath();
                    google.maps.event.addListener(path, 'set_at', () => {
                        this.atualizarMedidasObjetoSalvo(medicaoRef, poligonoSalvo, 'poligono');
                    });
                    google.maps.event.addListener(path, 'insert_at', () => {
                        this.atualizarMedidasObjetoSalvo(medicaoRef, poligonoSalvo, 'poligono');
                    });
                    google.maps.event.addListener(path, 'remove_at', () => {
                        this.atualizarMedidasObjetoSalvo(medicaoRef, poligonoSalvo, 'poligono');
                    });
                }

                if (this.polilinha) {
                    this.polilinha.setOptions({ 
                        editable: true,
                        draggable: false,
                        clickable: false
                    });
                    
                    // Recria as labels uma vez
                    this.atualizarMedidasPolilinha();
                    medicaoSalva.labels = [...this.labels];
                    medicaoSalva.objeto = this.polilinha;
                    
                    const polilinhaSalva = this.polilinha;
                    const medicaoRef = medicaoSalva;
                    const path = this.polilinha.getPath();
                    google.maps.event.addListener(path, 'set_at', () => {
                        this.atualizarMedidasObjetoSalvo(medicaoRef, polilinhaSalva, 'polilinha');
                    });
                    google.maps.event.addListener(path, 'insert_at', () => {
                        this.atualizarMedidasObjetoSalvo(medicaoRef, polilinhaSalva, 'polilinha');
                    });
                    google.maps.event.addListener(path, 'remove_at', () => {
                        this.atualizarMedidasObjetoSalvo(medicaoRef, polilinhaSalva, 'polilinha');
                    });
                }

                if (this.circulo) {
                    this.circulo.setOptions({ 
                        editable: true,
                        draggable: true,
                        clickable: false
                    });
                    
                    // Recria as labels uma vez
                    this.atualizarMedidasCirculo();
                    medicaoSalva.labels = [...this.labels];
                    medicaoSalva.objeto = this.circulo;
                    
                    const circuloSalvo = this.circulo;
                    const medicaoRef = medicaoSalva;
                    google.maps.event.addListener(this.circulo, 'radius_changed', () => {
                        this.atualizarMedidasObjetoSalvo(medicaoRef, circuloSalvo, 'circulo');
                    });
                    google.maps.event.addListener(this.circulo, 'center_changed', () => {
                        this.atualizarMedidasObjetoSalvo(medicaoRef, circuloSalvo, 'circulo');
                    });
                }

                // Adiciona a medição ao array de salvas
                if (medicaoSalva.objeto) {
                    this.medicoesSalvas.push(medicaoSalva);
                }

                // Reseta variáveis para nova medição
                this.poligono = null;
                this.polilinha = null;
                this.circulo = null;
                this.centroCirculo = null;
                this.pontos = [];
                this.labels = [];

                // Mantém o modo ativo para permitir nova medição
                // Reinicia os listeners mantendo this.ativa = true
                const tipoAtual = this.tipo;
                // NÃO zera this.ativa para que iniciar() saiba que está continuando
                this.iniciar(tipoAtual);

                // Atualiza UI
                $('#btnCancelarMedicao').removeClass('d-none');
                $('#btnLimparMedicoes').removeClass('d-none');
            },

            atualizarMedidasObjetoSalvo: function(medicao, objeto, tipo) {
                // Remove labels antigas da medição
                if (medicao.labels) {
                    medicao.labels.forEach(l => l.setMap(null));
                }

                const labelsNovas = [];

                // Recria labels baseado no tipo
                if (tipo === 'poligono') {
                    const path = objeto.getPath();
                    const pontos = [];
                    
                    for (let i = 0; i < path.getLength(); i++) {
                        pontos.push(path.getAt(i));
                    }

                    if (pontos.length >= 2) {
                        for (let i = 0; i < pontos.length; i++) {
                            const proximoIndice = (i + 1) % pontos.length;
                            const distancia = this.calcularDistancia(pontos[i], pontos[proximoIndice]);
                            
                            const lat = (pontos[i].lat() + pontos[proximoIndice].lat()) / 2;
                            const lng = (pontos[i].lng() + pontos[proximoIndice].lng()) / 2;
                            const offsetVertical = 0.0000015;
                            const posicao = new google.maps.LatLng(lat + offsetVertical, lng);

                            const el = document.createElement('div');
                            el.className = 'measurement-label';
                            el.textContent = this.formatarDistancia(distancia);

                            const label = new google.maps.marker.AdvancedMarkerElement({
                                position: posicao,
                                content: el,
                                map: MapFramework.map,
                                zIndex: 1000,
                                gmpClickable: false
                            });

                            labelsNovas.push(label);
                        }

                        if (pontos.length >= 3) {
                            const coords = pontos.map(p => [p.lng(), p.lat()]);
                            coords.push([pontos[0].lng(), pontos[0].lat()]);
                            const polygon = turf.polygon([coords]);
                            const area = turf.area(polygon);
                            
                            const centroide = this.calcularCentroide(pontos);
                            const elArea = document.createElement('div');
                            elArea.className = 'measurement-area-label';
                            elArea.textContent = area.toFixed(1) + ' m²';

                            const labelArea = new google.maps.marker.AdvancedMarkerElement({
                                position: centroide,
                                content: elArea,
                                map: MapFramework.map,
                                zIndex: 1001,
                                gmpClickable: false
                            });

                            labelsNovas.push(labelArea);
                        }
                    }
                } else if (tipo === 'polilinha') {
                    const path = objeto.getPath();
                    const pontos = [];
                    
                    for (let i = 0; i < path.getLength(); i++) {
                        pontos.push(path.getAt(i));
                    }

                    for (let i = 0; i < pontos.length - 1; i++) {
                        const distancia = this.calcularDistancia(pontos[i], pontos[i + 1]);
                        
                        const lat = (pontos[i].lat() + pontos[i + 1].lat()) / 2;
                        const lng = (pontos[i].lng() + pontos[i + 1].lng()) / 2;
                        const offsetVertical = 0.0000015;
                        const posicao = new google.maps.LatLng(lat + offsetVertical, lng);

                        const el = document.createElement('div');
                        el.className = 'measurement-label';
                        el.textContent = this.formatarDistancia(distancia);

                        const label = new google.maps.marker.AdvancedMarkerElement({
                            position: posicao,
                            content: el,
                            map: MapFramework.map,
                            zIndex: 1000,
                            gmpClickable: false
                        });

                        labelsNovas.push(label);
                    }
                } else if (tipo === 'circulo') {
                    const centro = objeto.getCenter();
                    const raio = objeto.getRadius();

                    const pontoRaio = google.maps.geometry.spherical.computeOffset(centro, raio, 45);
                    const lat = (centro.lat() + pontoRaio.lat()) / 2;
                    const lng = (centro.lng() + pontoRaio.lng()) / 2;
                    const offsetVertical = 0.0000015;
                    const posicaoRaio = new google.maps.LatLng(lat + offsetVertical, lng);

                    const elRaio = document.createElement('div');
                    elRaio.className = 'measurement-label';
                    elRaio.textContent = 'Raio: ' + this.formatarDistancia(raio);

                    const labelRaio = new google.maps.marker.AdvancedMarkerElement({
                        position: posicaoRaio,
                        content: elRaio,
                        map: MapFramework.map,
                        zIndex: 1000,
                        gmpClickable: false
                    });

                    labelsNovas.push(labelRaio);

                    const area = Math.PI * raio * raio;
                    const elArea = document.createElement('div');
                    elArea.className = 'measurement-area-label';
                    elArea.style.backgroundColor = '#FF0000';
                    elArea.style.borderColor = '#CC0000';
                    elArea.textContent = area.toFixed(1) + ' m²';

                    const labelArea = new google.maps.marker.AdvancedMarkerElement({
                        position: centro,
                        content: elArea,
                        map: MapFramework.map,
                        zIndex: 1001,
                        gmpClickable: false
                    });

                    labelsNovas.push(labelArea);
                }

                medicao.labels = labelsNovas;
            },

            atualizarMedidasPoligono: function() {
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                if (!this.poligono) return;
                
                const path = this.poligono.getPath();
                const pontos = [];
                
                for (let i = 0; i < path.getLength(); i++) {
                    pontos.push(path.getAt(i));
                }

                if (pontos.length < 2) return;

                for (let i = 0; i < pontos.length; i++) {
                    const proximoIndice = (i + 1) % pontos.length;
                    const distancia = this.calcularDistancia(pontos[i], pontos[proximoIndice]);
                    this.adicionarLabelAresta(pontos[i], pontos[proximoIndice], distancia);
                }

                if (pontos.length >= 3) {
                    const coords = pontos.map(p => [p.lng(), p.lat()]);
                    coords.push([pontos[0].lng(), pontos[0].lat()]);

                    const polygon = turf.polygon([coords]);
                    const area = turf.area(polygon);
                    
                    const centroide = this.calcularCentroide(pontos);
                    this.adicionarLabelCentral(area.toFixed(1) + ' m²', centroide, 'measurement-area-label');
                }
            },

            atualizarMedidasPolilinha: function() {
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                if (!this.polilinha) return;
                
                const path = this.polilinha.getPath();
                const pontos = [];
                
                for (let i = 0; i < path.getLength(); i++) {
                    pontos.push(path.getAt(i));
                }

                if (pontos.length < 1) return;

                // Adiciona apenas as labels nas arestas (sem total)
                for (let i = 0; i < pontos.length - 1; i++) {
                    const distancia = this.calcularDistancia(pontos[i], pontos[i + 1]);
                    this.adicionarLabelAresta(pontos[i], pontos[i + 1], distancia);
                }
            },

            atualizarCirculo: function() {
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                if (!this.centroCirculo || this.pontos.length < 2) return;

                const raio = this.calcularDistancia(this.centroCirculo, this.pontos[1]);

                if (!this.circulo) {
                    this.circulo = new google.maps.Circle({
                        center: this.centroCirculo,
                        radius: raio,
                        strokeColor: '#FF0000',
                        strokeOpacity: 0.8,
                        strokeWeight: 2,
                        fillColor: '#FF0000',
                        fillOpacity: 0.2,
                        map: MapFramework.map,
                        zIndex: 999,
                        editable: false,
                        draggable: false,
                        clickable: false
                    });
                } else {
                    this.circulo.setRadius(raio);
                }

                // Label com o raio
                const pontoRaio = this.pontos[1];
                const lat = (this.centroCirculo.lat() + pontoRaio.lat()) / 2;
                const lng = (this.centroCirculo.lng() + pontoRaio.lng()) / 2;
                const offsetVertical = 0.0000015;
                const posicaoRaio = new google.maps.LatLng(lat + offsetVertical, lng);

                const elRaio = document.createElement('div');
                elRaio.className = 'measurement-label';
                elRaio.textContent = 'Raio: ' + this.formatarDistancia(raio);

                const labelRaio = new google.maps.marker.AdvancedMarkerElement({
                    position: posicaoRaio,
                    content: elRaio,
                    map: MapFramework.map,
                    zIndex: 1000,
                    gmpClickable: false
                });

                this.labels.push(labelRaio);

                // Label com a área no centro
                const area = Math.PI * raio * raio;
                const elArea = document.createElement('div');
                elArea.className = 'measurement-area-label';
                elArea.style.backgroundColor = '#FF0000';
                elArea.style.borderColor = '#CC0000';
                elArea.textContent = area.toFixed(1) + ' m²'; // Sempre em m² para círculos

                const labelArea = new google.maps.marker.AdvancedMarkerElement({
                    position: this.centroCirculo,
                    content: elArea,
                    map: MapFramework.map,
                    zIndex: 1001,
                    gmpClickable: false
                });

                this.labels.push(labelArea);
            },

            atualizarMedidasCirculo: function() {
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                if (!this.circulo) return;

                const centro = this.circulo.getCenter();
                const raio = this.circulo.getRadius();

                // Calcula um ponto na borda do círculo para posicionar a label do raio
                const pontoRaio = google.maps.geometry.spherical.computeOffset(centro, raio, 45);
                
                const lat = (centro.lat() + pontoRaio.lat()) / 2;
                const lng = (centro.lng() + pontoRaio.lng()) / 2;
                const offsetVertical = 0.0000015;
                const posicaoRaio = new google.maps.LatLng(lat + offsetVertical, lng);

                const elRaio = document.createElement('div');
                elRaio.className = 'measurement-label';
                elRaio.textContent = 'Raio: ' + this.formatarDistancia(raio);

                const labelRaio = new google.maps.marker.AdvancedMarkerElement({
                    position: posicaoRaio,
                    content: elRaio,
                    map: MapFramework.map,
                    zIndex: 1000,
                    gmpClickable: false
                });

                this.labels.push(labelRaio);

                // Label com a área no centro
                const area = Math.PI * raio * raio;
                const elArea = document.createElement('div');
                elArea.className = 'measurement-area-label';
                elArea.style.backgroundColor = '#FF0000';
                elArea.style.borderColor = '#CC0000';
                elArea.textContent = area.toFixed(1) + ' m²'; // Sempre em m² para círculos

                const labelArea = new google.maps.marker.AdvancedMarkerElement({
                    position: centro,
                    content: elArea,
                    map: MapFramework.map,
                    zIndex: 1001,
                    gmpClickable: false
                });

                this.labels.push(labelArea);
            }
        };

        // Funções para os botões de medição
        function iniciarMedicaoArea() {
            if (Medicao.ativa && Medicao.tipo !== 'area') {
                Medicao.limparMedicaoAtual();
            }
            Medicao.iniciar('area');
            $('#btnCancelarMedicao').removeClass('d-none');
            if (Medicao.medicoesSalvas.length > 0) {
                $('#btnLimparMedicoes').removeClass('d-none');
            }
        }

        function iniciarMedicaoDistancia() {
            if (Medicao.ativa && Medicao.tipo !== 'distancia') {
                Medicao.limparMedicaoAtual();
            }
            Medicao.iniciar('distancia');
            $('#btnCancelarMedicao').removeClass('d-none');
            if (Medicao.medicoesSalvas.length > 0) {
                $('#btnLimparMedicoes').removeClass('d-none');
            }
        }

        function iniciarMedicaoCirculo() {
            if (Medicao.ativa && Medicao.tipo !== 'circulo') {
                Medicao.limparMedicaoAtual();
            }
            Medicao.iniciar('circulo');
            $('#btnCancelarMedicao').removeClass('d-none');
            if (Medicao.medicoesSalvas.length > 0) {
                $('#btnLimparMedicoes').removeClass('d-none');
            }
        }

        function limparTodasMedicoes() {
            // Remove todas as medições salvas
            Medicao.medicoesSalvas.forEach(medicao => {
                if (medicao.objeto) {
                    medicao.objeto.setMap(null);
                }
                if (medicao.labels) {
                    medicao.labels.forEach(l => l.setMap(null));
                }
            });
            Medicao.medicoesSalvas = [];
            $('#btnLimparMedicoes').addClass('d-none');
        }

        function cancelarMedicao() {
            Medicao.limpar();
            $('#btnCancelarMedicao').addClass('d-none');
            $('#btnLimparMedicoes').addClass('d-none');
        }

        $('#btnCloseTooltip').on('click', function() {
            $('#tooltipMarcador').hide();
        });

        MapFramework.carregarControleNavegacaoQuadriculas(dadosOrto[0]['quadricula']);


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

        // Botões de inclusão
        $('#btnIncluirPoligono').on('click', function() {
            MapFramework.iniciarDesenhoQuadra();
            controlarVisibilidadeBotoes('quadra');
        });

        $('#btnIncluirUnidade').on('click', function() {
            MapFramework.iniciarDesenhoUnidade();
            controlarVisibilidadeBotoes('unidade');
        });

        $('#btnIncluirPiscina').on('click', function() {
            MapFramework.iniciarDesenhoPiscina();
            controlarVisibilidadeBotoes('piscina');
        });

        $('#btnIncluirLinha').on('click', function() {
            MapFramework.iniciarDesenhoLote();
            controlarVisibilidadeBotoes('lote');
        });

        $('#btnIncluirMarcador').on('click', function() {
            MapFramework.iniciarDesenhoMarcador();
            controlarVisibilidadeBotoes('marcador');
        });

        // Botão para finalizar desenho
        $('#btnFinalizarDesenho').on('click', function() {
            MapFramework.finalizarDesenho();
            controlarVisibilidadeBotoes('normal');
        });

        // Botão específico para sair do modo marcador
        $('#btnSairModoMarcador').on('click', function() {
            MapFramework.sairModoMarcador();
            voltarModoCadastro(); // Volta para o modo cadastro
        });

        // Modal: SALVAR quadra
        $('#btnSalvarCamada').on('click', function() {
            const identificador = $('#inputNumeroQuadra').val().trim();
            if (!identificador) {
                alert('Informe o identificador da quadra.');
                return;
            }
            MapFramework.salvarDesenho('Quadra', identificador);
        });

        // Modal: CANCELAR / sair do modo desenho
        $('#btnCancelarCamada').on('click', function() {
            MapFramework.finalizarDesenho({
                descartarTemporario: true
            });
        });

        $('#btnExcluir').on('click', function() {
            if (!dadosOrto || dadosOrto.length === 0) {
                alert('Erro: Dados da ortofoto não estão disponíveis.');
                return;
            }
            MapFramework.excluirDesenhoSelecionado2('paulinia', dadosOrto[0]['quadricula']);
        });

        // Botão Editar - Entra no modo de edição
        $('#btnEditar').on('click', function() {
            MapFramework.entrarModoEdicao();
        });

        // Botão Sair da Edição - Salva e sai do modo de edição
        $('#btnSairEdicao').on('click', function() {
            MapFramework.sairModoEdicao();
        });

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

        // Checkbox das Unidades
        $('#chkUnidades').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('unidade', visivel);
        });

        // Checkbox das Piscinas
        $('#chkPiscinas').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('piscina', visivel);
        });

        // Checkbox dos Lotes feito por desenhinhas (linhas)
        $('#chkLotes').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('lote', visivel);
        });

        // Checkbox dos Lotes
        $('#chkPoligono_lote').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('lote_ortofoto', visivel);
        });

        $('#chkPrefeitura').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('prefeitura', visivel);
        });

        $('#chkLimite').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('limite', visivel);
        });

        $('#chkCondominiosVerticais').on('change', function() {
            const visivel = $(this).is(':checked');
            if (visivel && (!arrayCamadas.condominios_verticais || arrayCamadas.condominios_verticais.length === 0)) {
                MapFramework.carregarCondominiosVerticaisKML();
            } else {
                MapFramework.alternarVisibilidadeCamada('condominios_verticais', visivel);
            }
        });

        $('#chkCondominiosHorizontais').on('change', function() {
            const visivel = $(this).is(':checked');
            if (visivel && (!arrayCamadas.condominios_horizontais || arrayCamadas.condominios_horizontais.length === 0)) {
                MapFramework.carregarCondominiosHorizontaisKML();
            } else {
                MapFramework.alternarVisibilidadeCamada('condominios_horizontais', visivel);
            }
        });

        $('#chkAreasPublicas').on('change', function() {
            const visivel = $(this).is(':checked');
            if (visivel && (!arrayCamadas.areas_publicas || arrayCamadas.areas_publicas.length === 0)) {
                MapFramework.carregarAreasPublicasKML();
            } else {
                MapFramework.alternarVisibilidadeCamada('areas_publicas', visivel);
            }
        });

        $('#chkQuadriculas').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('quadriculas', visivel);
        });

        $('#chkMarcadores').on('change', function() {
            const visivel = $(this).is(':checked');
            if (visivel) {
                // Checkbox marcado = mostra TODOS os marcadores do mapa
                MapFramework.alternarVisibilidadeTodosMarcadores(true);
            } else {
                // Checkbox desmarcado = volta a mostrar apenas os do quarteirão selecionado (se houver)
                if (quarteiraoAtualSelecionado) {
                    MapFramework.mostrarMarcadoresDoQuarteirao(quarteiraoAtualSelecionado);
                } else {
                    // Se não há quarteirão selecionado, oculta todos
                    MapFramework.alternarVisibilidadeTodosMarcadores(false);
                }
            }
        });

        $('#chkQuarteiroes').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('quarteirao', visivel);
        });

        $('#chkImagensAereas').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('imagens_aereas', visivel);
        });

        $('#chkStreetview').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('streetview', visivel);
        });

        $('#customRange1').on('input', function() {
            MapFramework.controlarOpacidade(this.value);
        })

        $('#customRange2').on('input', function() {
            MapFramework.controlarEspessuraLotes(this.value);
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

        // Checkbox Modo Cadastro (Loteamentos)
        let processandoModoCadastro = false;

        $('#chkModoCadastro').on('change', function() {
            if (processandoModoCadastro) return;

            const ativado = $(this).is(':checked');

            if (ativado) {
                // Ativar modo cadastro
                if (!dadosOrto || dadosOrto.length === 0) {
                    alert('Erro: Dados da ortofoto não estão disponíveis.');
                    $(this).prop('checked', false);
                    return;
                }

                // Controla visibilidade dos botões
                controlarVisibilidadeBotoes('cadastro');

                //aqui desabilita o clique no poligonos quadra, unidade e lote
                arrayCamadas.quadra.forEach(quadra => {
                    quadra.setOptions({
                        clickable: false
                    });
                });
                arrayCamadas.unidade.forEach(unidade => {
                    unidade.setOptions({
                        clickable: false
                    });
                });
                if (arrayCamadas.piscina) {
                    arrayCamadas.piscina.forEach(piscina => {
                        piscina.setOptions({
                            clickable: false
                        });
                    });
                }
                arrayCamadas.lote.forEach(lote => {
                    lote.setOptions({
                        clickable: false
                    });
                });
                arrayCamadas.poligono_lote.forEach(pol_lote => {
                    pol_lote.setOptions({
                        clickable: false
                    });
                });

                const quadricula = dadosOrto[0]['quadricula'];
                carregarLoteamentosQuadricula(quadricula);
            } else {
                // Desativar modo cadastro
                sairModoCadastro();
            }
        });

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
                const response = await fetch(`loteamentos_quadriculas/json/resultados_quadricula_${quadricula}.json`);
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
            const nomeDecodificado = normalizarString(nomeArquivo);
            window.open('loteamentos_quadriculas/pdf/' + nomeDecodificado, '_blank');
        }

        // Função para abrir PDF em nova aba (nova função para os botões)
        function abrirPDFNovaAba(nomeArquivo) {
            const nomeDecodificado = normalizarString(nomeArquivo);
            window.open('loteamentos_quadriculas/pdf/' + nomeDecodificado, '_blank');
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
                            <input style="margin-top: 2px;" type="radio" id="loteamento_${index}" name="loteamento" data-loteamento="${loteamento.nome}" data-arquivos="${loteamento.arquivos_associados}" value="${index}">
                            <label for="loteamento_${index}">
                                ${loteamento.nome}
                                <small class="d-block ${statusClass}">${statusText}</small>
                                ${loteamento.subpasta ? `<small class="d-block text-muted">${''}</small>` : ''}
                            </label>
                        </div>
                        ${temArquivos ? 
                            `<div class="submenu-pdfs" id="pdfs_loteamento_${index}" style="margin-left: 20px; margin-top: 8px;">
                                ${loteamento.arquivos_associados.map((arquivo, pdfIndex) => {
                                    const pdfId = `pdf_${index}_${pdfIndex}`;
                                    const isFirst = pdfIndex === 0;
                                    return `<div class="pdf-option d-flex align-items-center justify-content-between" style="margin-bottom: 5px;">
                                        <div class="d-flex align-items-center">
                                            <input type="radio" id="${pdfId}" name="pdf_loteamento_${index}" 
                                                   data-loteamento="${loteamento.nome}" 
                                                   data-arquivo="${arquivo}" 
                                                   data-quadricula="" 
                                                   value="${pdfIndex}"
                                                   disabled>
                                            <label for="${pdfId}" style="margin-left: 5px; font-size: 12px; margin-bottom: 0;">
                                                <i class="fas fa-file-pdf text-danger"></i> 
                                                ${arquivo}
                                            </label>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                style="font-size: 10px; padding: 2px 6px;" 
                                                onclick="abrirPDFNovaAba('${arquivo}')" 
                                                title="Abrir PDF em nova aba">
                                            <i class="fas fa-external-link-alt"></i>
                                        </button>
                                    </div>`;
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

                let selecoesDados_loteamento = $(this).data('loteamento');
                let selecoesDados_documentos = $(this).data('arquivos');

                // Remove destaque anterior
                removerDestaques();

                // Destaca o loteamento selecionado e desenhos relacionados
                destacarLoteamentoSelecionado(indexSelecionado, selecoesDados_loteamento, selecoesDados_documentos);

                // Adiciona classe visual para destacar a opção selecionada
                $('.opcao-loteamento').removeClass('selected');
                $(this).closest('.opcao-loteamento').addClass('selected');

                // Habilitar apenas os PDFs do loteamento selecionado (apenas nos controles originais)
                $('input[name^="pdf_loteamento_"]:not([name*="integrado"])').prop('disabled', true); // Desabilita todos exceto integrados
                $(`input[name="pdf_loteamento_${indexSelecionado}"]:not([name*="integrado"])`).prop('disabled', false); // Habilita apenas do loteamento selecionado (original)

                // CORREÇÃO: Selecionar automaticamente o primeiro PDF do loteamento selecionado
                //const primeiroPDF = $(`input[name="pdf_loteamento_${indexSelecionado}"]:not([name*="integrado"]):first`);
                //if (primeiroPDF.length > 0) {
                //    primeiroPDF.prop('checked', true);
                //}

                // Abre a divCadastro2 com os quarteirões do loteamento selecionado
                abrirDivCadastro2(indexSelecionado);

                // Fecha a divCadastro3 se estiver aberta
                $('#divCadastro3').fadeOut(150);

                // Atualiza dados do botão desenhar no PDF com o PDF selecionado
                atualizarBotaoDesenharPDF(indexSelecionado);

                // CORREÇÃO: Sincronizar loteamento com o modal integrado em tempo real
                sincronizarLoteamentoComIntegrado(indexSelecionado);
            });

            // Adiciona eventos para os radio buttons dos PDFs
            $('input[name^="pdf_loteamento_"]').on('change', function() {
                const nomeInput = $(this).attr('name');
                const indexLoteamento = nomeInput.match(/\d+/)[0];

                // CORREÇÃO: Desmarcar todos os outros radio buttons quando um for selecionado
                if ($(this).is(':checked')) {
                    // Desmarcar todos os outros PDFs
                    $('input[name^="pdf_loteamento_"]').not(this).prop('checked', false);

                    // Atualizar variável global com o PDF selecionado
                    window.pdfSelecionadoGlobal = {
                        loteamento: $(this).data('loteamento'),
                        arquivoPdf: $(this).data('arquivo'),
                        indexLoteamento: parseInt(indexLoteamento)
                    };
                }

                atualizarBotaoDesenharPDF(parseInt(indexLoteamento));

                // CORREÇÃO: Sincronizar com o modal integrado em tempo real
                sincronizarPDFComIntegrado(parseInt(indexLoteamento));
            });
        }

        // Função para sincronizar loteamento selecionado com o modal integrado
        function sincronizarLoteamentoComIntegrado(indexLoteamento) {
            // Verificar se o modal integrado está visível
            if (!$('#divCadastroIntegrado').is(':visible')) {
                return; // Modal integrado não está visível, não precisa sincronizar
            }

            // Selecionar o mesmo loteamento no modal integrado
            $(`#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"][value="${indexLoteamento}"]`).prop('checked', true);
            $(`#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"][value="${indexLoteamento}"]`).closest('.opcao-loteamento').addClass('selected');

            // Limpar seleções de outros loteamentos
            $('#opcoesLoteamentosIntegrado .opcao-loteamento').removeClass('selected');
            $(`#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"][value="${indexLoteamento}"]`).closest('.opcao-loteamento').addClass('selected');

            // Sincronizar o PDF selecionado também
            sincronizarPDFComIntegrado(indexLoteamento);
        }

        // Função para sincronizar PDF selecionado com o modal integrado
        // Variável global para armazenar o PDF selecionado
        window.pdfSelecionadoGlobal = null;

        function sincronizarPDFComIntegrado(indexLoteamento) {
            // Verificar se o modal integrado está visível
            if (!$('#divCadastroIntegrado').is(':visible')) {
                return; // Modal integrado não está visível, não precisa sincronizar
            }

            // Usar a variável global para sincronizar
            if (window.pdfSelecionadoGlobal) {
                const {
                    loteamento,
                    arquivoPdf,
                    indexLoteamento: indexLoteamentoGlobal
                } = window.pdfSelecionadoGlobal;


                // Sincronizar no modal integrado
                const pdfIntegrado = $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexLoteamentoGlobal}"][data-arquivo="${arquivoPdf}"]`);
                if (pdfIntegrado.length > 0) {
                    // CORREÇÃO: Desmarcar todos os outros PDFs no modal integrado
                    $('#opcoesLoteamentosIntegrado input[name^="pdf_loteamento_integrado_"]').prop('checked', false);
                    // Marcar o PDF correto
                    pdfIntegrado.prop('checked', true);
                }
            }
        }

        // Função para atualizar o botão "Desenhar no PDF" com o PDF selecionado
        function atualizarBotaoDesenharPDF(indexLoteamento) {
            const loteamento = window.loteamentosSelecionados[indexLoteamento];
            if (!loteamento || !loteamento.arquivos_associados || loteamento.arquivos_associados.length === 0) {
                $("#btnLerPDF").addClass("d-none");
                return;
            }

            // CORREÇÃO: Pega o PDF selecionado corretamente
            const pdfSelecionado = $(`input[name="pdf_loteamento_${indexLoteamento}"]:checked`);
            let arquivoSelecionado;

            if (pdfSelecionado.length > 0) {
                // Se há um PDF selecionado, usar ele
                arquivoSelecionado = pdfSelecionado.data('arquivo');
            } else {
                // Se não há PDF selecionado, selecionar o primeiro automaticamente
                const primeiroPDF = $(`input[name="pdf_loteamento_${indexLoteamento}"]:first`);
                if (primeiroPDF.length > 0) {
                    primeiroPDF.prop('checked', true);
                    arquivoSelecionado = primeiroPDF.data('arquivo');
                } else {
                    // Fallback para o primeiro arquivo da lista
                    arquivoSelecionado = loteamento.arquivos_associados[0];
                }
            }

            $("#btnLerPDF").attr('data-loteamento', loteamento.nome);
            $("#btnLerPDF").attr('data-arquivos', arquivoSelecionado);
            $("#btnLerPDF").attr('data-quadricula', dadosOrto[0]['quadricula']);
            $("#btnLerPDF").removeClass("d-none");

            /*
            console.log('Botão atualizado para:', {
                loteamento: loteamento.nome,
                arquivo: arquivoSelecionado,
                quadricula: dadosOrto[0]['quadricula']
            });
            */
        }

        // Função para adicionar desenhos no mapa
        function adicionarDesenhosNoMapa(loteamentos, quadricula) {

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


                    // CORREÇÃO: Processar apenas o primeiro conjunto de coordenadas (índice 0)
                    const primeiraCoordenada = loteamento.coordenadas[0];

                    if (primeiraCoordenada.type === 'Polygon' && primeiraCoordenada.coordinates) {
                        try {
                            // Converte as coordenadas para o formato do Google Maps
                            const path = primeiraCoordenada.coordinates[0].map(coord => {
                                return {
                                    lat: coord[1],
                                    lng: coord[0]
                                }; // {lat, lng} para Google Maps
                            });

                            // Verificar se temos coordenadas suficientes
                            if (path.length < 3) {
                                console.error(`❌ Polígono ${loteamento.nome} tem apenas ${path.length} pontos - insuficiente para formar polígono`);
                                return;
                            }

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


                        } catch (error) {
                            console.error(`Erro ao criar polígono para ${loteamento.nome}:`, error);
                        }

                    } else if (primeiraCoordenada.type === 'MultiPolygon' && primeiraCoordenada.coordinates) {

                        try {
                            // CORREÇÃO: Processar TODOS os polígonos do MultiPolygon como UM ÚNICO loteamento
                            const polygonosDoLoteamento = []; // Array para armazenar todos os polígonos deste loteamento

                            primeiraCoordenada.coordinates.forEach((polygonCoords, polygonIndex) => {
                                // Converte as coordenadas para o formato do Google Maps
                                const path = polygonCoords[0].map(coord => {
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

                                // Armazena o polígono para referência futura
                                polygonosDoLoteamento.push(polygon);
                            });

                            // Armazena a referência dos polígonos deste loteamento para uso posterior
                            if (!window.loteamentosPolygons) {
                                window.loteamentosPolygons = {};
                            }
                            window.loteamentosPolygons[loteamento.nome] = polygonosDoLoteamento;

                        } catch (error) {
                            console.error(`Erro ao criar MultiPolygon para ${loteamento.nome}:`, error);
                        }
                    }
                } else {}
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

        // Função para sair do modo cadastro
        function sairModoCadastro() {
            processandoModoCadastro = true;

            $('#divCadastro').fadeOut(150);

            // Fecha também a divCadastro2 se estiver aberta
            $('#divCadastro2').fadeOut(150);

            // Fecha também a divCadastro3 se estiver aberta
            $('#divCadastro3').fadeOut(150);

            // Volta ao modo normal
            controlarVisibilidadeBotoes('normal');

            // Remove todos os destaques
            removerDestaques();

            // Limpa os desenhos dos loteamentos do mapa
            if (window.loteamentosLayer && window.loteamentosLayer.length > 0) {
                window.loteamentosLayer.forEach(polygon => {
                    polygon.setMap(null);
                });
                window.loteamentosLayer = [];
            }

            //aqui desabilita o clique no poligonos quadra e lote
            arrayCamadas.quadra.forEach(quadra => {
                quadra.setOptions({
                    clickable: true
                });
            });
            arrayCamadas.unidade.forEach(unidade => {
                unidade.setOptions({
                    clickable: true
                });
            });
            if (arrayCamadas.piscina) {
                arrayCamadas.piscina.forEach(piscina => {
                    piscina.setOptions({
                        clickable: true
                    });
                });
            }
            arrayCamadas.lote.forEach(lote => {
                lote.setOptions({
                    clickable: true
                });
            });
            arrayCamadas.poligono_lote.forEach(pol_lote => {
                pol_lote.setOptions({
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

            // Limpa os inputs text
            $('#inputLoteAtual').val('');
            $('#inputQuadraAtual').val('');

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

            $("#btnLerPDF").addClass('d-none');

            // Desmarca o checkbox do modo cadastro
            $('#chkModoCadastro').prop('checked', false);

            processandoModoCadastro = false;
        }

        // Evento para fechar a div de cadastro (botão X)
        $('#btnFecharCadastro').on('click', function() {
            sairModoCadastro();
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

            // Garante que os z-index estejam corretos após carregamento
            MapFramework.aplicarZIndexCorreto();

            await MapFramework.carregarDesenhosPrefeitura(dadosOrto[0]['quadricula']);

            await MapFramework.carregarMarcadoresSalvos(dadosOrto[0]['quadricula']);

            // Garante que os z-index estejam corretos após todos os carregamentos
            MapFramework.aplicarZIndexCorreto();

            MapFramework.carregarLimiteKML();

            MapFramework.carregarQuadriculasKML();

            // Carrega os quarteirões da quadrícula atual
            MapFramework.carregaQuarteiroes(dadosOrto[0]['quadricula']);

            await MapFramework.carregarPlanilha();

            await MapFramework.carregarImagensAereas(dadosOrto[0]['quadricula']);

            // Carrega os trajetos Streetview da quadrícula no mapa
            MapFramework.carregarStreets(dadosOrto[0]['quadricula']);


            // Inicializa o modo normal (mostra botões principais)
            controlarVisibilidadeBotoes('normal');

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
        function destacarLoteamentoSelecionado(indexLoteamento, selecoesDados_loteamento, selecoesDados_documentos) {

            if (selecoesDados_loteamento != "") {
                //console.log(selecoesDados_loteamento);
                //console.log(selecoesDados_documentos);
                $("#btnLerPDF").attr('data-loteamento', selecoesDados_loteamento);
                $("#btnLerPDF").attr('data-arquivos', selecoesDados_documentos);
                $("#btnLerPDF").attr('data-quadricula', dadosOrto[0]['quadricula']);

                $("#btnLerPDF").removeClass("d-none");
            }

            // Remove destaque anterior
            if (window.loteamentosLayer) {
                window.loteamentosLayer.forEach((polygon, i) => {
                    // Verifica se este polígono pertence ao loteamento selecionado
                    let pertenceAoSelecionado = false;

                    if (window.loteamentosSelecionados && window.loteamentosSelecionados[indexLoteamento]) {
                        const nomeLoteamento = window.loteamentosSelecionados[indexLoteamento].nome;

                        // Verifica se este polígono está no array de polígonos deste loteamento
                        if (window.loteamentosPolygons && window.loteamentosPolygons[nomeLoteamento]) {
                            pertenceAoSelecionado = window.loteamentosPolygons[nomeLoteamento].includes(polygon);
                        } else {
                            // Para loteamentos com Polygon simples, verifica pelo índice
                            pertenceAoSelecionado = (i === indexLoteamento);
                        }
                    }

                    if (pertenceAoSelecionado) {
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
                    // CORREÇÃO: Processar apenas o primeiro conjunto de coordenadas (índice 0)
                    let coordenadasPoligono = [];

                    // Pegar apenas o primeiro conjunto de coordenadas
                    const primeiraCoordenada = loteamento.coordenadas[0];

                    if (primeiraCoordenada.type === 'Polygon' && primeiraCoordenada.coordinates) {
                        // Polygon simples - usar apenas o primeiro conjunto
                        const coords = primeiraCoordenada.coordinates[0].map(coord => ({
                            lat: coord[1],
                            lng: coord[0]
                        }));
                        coordenadasPoligono = coords;
                    } else if (primeiraCoordenada.type === 'MultiPolygon' && primeiraCoordenada.coordinates) {
                        // MultiPolygon - processar todos os polígonos do primeiro conjunto
                        primeiraCoordenada.coordinates.forEach(polygonCoords => {
                            const coords = polygonCoords[0].map(coord => ({
                                lat: coord[1],
                                lng: coord[0]
                            }));
                            coordenadasPoligono = coordenadasPoligono.concat(coords);
                        });
                    }

                    // CORREÇÃO: Função para verificar se uma quadra está dentro do loteamento (Polygon ou MultiPolygon)
                    function quadraEstaDentroDoLoteamento(quadra, loteamentoCoordenadas) {
                        let coordenadasQuadra = null;

                        // Tenta diferentes formas de obter coordenadas da quadra
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
                            // CORREÇÃO: Verificar apenas o primeiro conjunto de coordenadas (índice 0)
                            const primeiraCoordenada = loteamentoCoordenadas[0];

                            if (primeiraCoordenada.type === 'Polygon' && primeiraCoordenada.coordinates) {
                                const coords = primeiraCoordenada.coordinates[0].map(coord => ({
                                    lat: coord[1],
                                    lng: coord[0]
                                }));
                                return linhaIntersectaPoligono(coordenadasQuadra, coords);
                            } else if (primeiraCoordenada.type === 'MultiPolygon' && primeiraCoordenada.coordinates) {
                                // Verificar se está dentro de qualquer polígono do MultiPolygon
                                return primeiraCoordenada.coordinates.some(polygonCoords => {
                                    const coords = polygonCoords[0].map(coord => ({
                                        lat: coord[1],
                                        lng: coord[0]
                                    }));
                                    return linhaIntersectaPoligono(coordenadasQuadra, coords);
                                });
                            }
                        }

                        return false;
                    }

                    // Função para ativar as linhas e unidades que pertencem a uma quadra
                    function ativarLinhasDaQuadra(quadra) {
                        if (arrayCamadas["lote"]) {
                            arrayCamadas["lote"].forEach(lote => {
                                // Verifica se o lote pertence a esta quadra
                                // lote.id_desenho = ID da quadra pai, quadra.identificador = ID único da quadra
                                if (parseInt(lote.id_desenho) === parseInt(quadra.identificador)) {
                                    // Restaura cor azul do lote, mantendo grossura original
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

                        if (arrayCamadas["unidade"]) {
                            arrayCamadas["unidade"].forEach(unidade => {
                                // Verifica se a unidade pertence a esta quadra
                                if (parseInt(unidade.id_desenho) === parseInt(quadra.identificador)) {
                                    // Restaura cor magenta da unidade
                                    unidade.setOptions({
                                        strokeColor: '#ff00ff',
                                        fillColor: '#ff00ff',
                                        fillOpacity: 0.30
                                    });
                                    unidade.desativado = false;
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

                    if (arrayCamadas["unidade"]) {
                        arrayCamadas["unidade"].forEach(unidade => {
                            // Deixa cinza por padrão, mantendo grossura original
                            unidade.setOptions({
                                strokeColor: 'gray',
                                fillColor: 'gray',
                                fillOpacity: 0.3
                                // strokeWeight não é alterado - mantém o original
                            });
                            unidade.desativado = true;
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
                            if (quadraEstaDentroDoLoteamento(quadra, [loteamento.coordenadas[0]])) {
                                // Quadra está dentro do loteamento - ativa ela com cor azul
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

        function desenharNoPDF(element) {

            let lotBtn = element.getAttribute('data-loteamento');
            let arqBtn = element.getAttribute('data-arquivos');
            let quadBtn = element.getAttribute('data-quadricula');

            let url = `desenhar_pdf.php?quadricula=${quadBtn}&loteamento=${lotBtn}&pdf=${arqBtn}`;

            window.open(url, '_blank');
        }

        /*
        function desenharNoPDF(element) {
           // console.log(element);
            let loteamento = element.getAttribute('data-loteamento');
            let arquivo = element.getAttribute('data-arquivos'); // Agora é um único arquivo
            let quadricula = element.getAttribute('data-quadricula');

            // Ao invés de abrir nova janela, abrir div na mesma página
            abrirLeitorPDF(loteamento, arquivo, quadricula);
        }
        */

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

                // CORREÇÃO: Processar apenas o primeiro conjunto de coordenadas (índice 0)
                let coordenadasLoteamento = [];

                // Pegar apenas o primeiro conjunto de coordenadas
                const primeiraCoordenada = loteamento.coordenadas[0];

                if (primeiraCoordenada.type === 'Polygon' && primeiraCoordenada.coordinates) {
                    // Polygon simples - usar apenas o primeiro conjunto
                    const coords = primeiraCoordenada.coordinates[0].map(coord => ({
                        lat: coord[1],
                        lng: coord[0]
                    }));
                    coordenadasLoteamento = coords;
                } else if (primeiraCoordenada.type === 'MultiPolygon' && primeiraCoordenada.coordinates) {
                    // MultiPolygon - processar todos os polígonos do primeiro conjunto
                    primeiraCoordenada.coordinates.forEach(polygonCoords => {
                        const coords = polygonCoords[0].map(coord => ({
                            lat: coord[1],
                            lng: coord[0]
                        }));
                        coordenadasLoteamento = coordenadasLoteamento.concat(coords);
                    });
                }

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
                        strokeColor: 'yellow',
                    });

                    // Calcula o centro do polígono para centralizá-lo
                    const path = quarteirao.polygon.getPath();
                    const bounds = new google.maps.LatLngBounds();

                    // Adiciona todos os pontos aos bounds
                    for (let i = 0; i < path.getLength(); i++) {
                        bounds.extend(path.getAt(i));
                    }

                    // Centraliza no quarteirão e aplica zoom 18
                    MapFramework.map.setCenter(bounds.getCenter());
                    MapFramework.map.setZoom(18);

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
            carregarDadosQuarteiroes().then(function() {
                // Cria os botões radio para cada quarteirão
                quarteiroesDoLoteamento.forEach((quarteirao, index) => {
                    // Obtém o nome do quarteirão (impreciso_name ou id)
                    const nomeQuarteirao = quarteirao.properties.impreciso_name || quarteirao.id;

                    // Busca informações complementares
                    const infoComplementar = obterInfoComplementarQuarteirao(loteamento.nome, nomeQuarteirao);

                    // Cria o texto do small baseado nas informações disponíveis
                    let textoSmall = ''; //`ID: ${quarteirao.id}`;
                    if (infoComplementar) {
                        textoSmall += `Quadras: ${infoComplementar}`;
                    }

                    const opcao = $(`
                        <div class="opcao-quarteirao">
                            <div class="d-flex align-items-start">
                                <input style="margin-top: 2px;" type="radio" id="quarteirao_${quarteirao.id}" data-nome="${nomeQuarteirao}" name="quarteirao" value="${quarteirao.id}">
                                <label for="quarteirao_${quarteirao.id}">
                                    Quarteirão ${nomeQuarteirao}
                                    <small class="d-block text-muted">${textoSmall}</small>
                                </label>
                            </div>
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


                    if (quarteiraoId) {
                        // Destaca o quarteirão selecionado passando apenas o ID
                        destacarQuarteiraoSelecionado(nomeQuarteirao, quarteiraoId);
                    }
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

            // Primeiro, redefine TODOS os quarteirões visíveis para cor branca
            if (arrayCamadas.quarteirao) {
                arrayCamadas.quarteirao.forEach(obj => {
                    // Só mexe nos quarteirões que estão visíveis no mapa
                    if (obj.polygon && obj.polygon.getMap()) {
                        obj.polygon.setOptions({
                            strokeColor: '#1275C3',
                            strokeWeight: 2,
                            zIndex: 10,
                        });
                    }
                });
            }

            // Obtém o quarteirão pelo ID usando a função do framework
            const quarteirao = MapFramework.obterQuarteiraoPorId(idQuarteirao);

            if (!quarteirao) {
                return;
            }

            // Destaca APENAS o quarteirão selecionado em amarelo
            if (quarteirao.polygon) {
                quarteirao.polygon.setOptions({
                    strokeColor: 'white',
                    strokeWeight: 5,
                    zIndex: 15
                });
                quarteirao.polygon.setMap(MapFramework.map);
            }

            if (quarteirao.marker) {
                quarteirao.marker.setMap(MapFramework.map);
            }

            // Adiciona classe visual para destacar a opção selecionada
            $('.opcao-quarteirao').removeClass('selected');
            $(`#quarteirao_${idQuarteirao}`).closest('.opcao-quarteirao').addClass('selected');

            // Automaticamente mostra os marcadores do quarteirão selecionado
            // MAS NÃO marca o checkbox - deixa o usuário decidir se quer ver todos
            MapFramework.mostrarMarcadoresDoQuarteirao(nomeQuarteirao);

            // Faz a requisição AJAX para buscar os lotes do quarteirão
            $.ajax({
                url: 'index_procurar_lotes.php',
                type: 'POST',
                async: false,
                cache: false,
                data: {
                    quarteirao: nomeQuarteirao
                },
                success: function(response) {

                    // Parse da resposta JSON
                    let dadosLotes = [];
                    try {
                        if (typeof response === 'string') {
                            dadosLotes = JSON.parse(response);
                        } else {
                            dadosLotes = response;
                        }
                    } catch (e) {
                        console.error('Erro ao fazer parse da resposta:', e);
                        return;
                    }

                    // Popula a divCadastro3 com os lotes
                    popularLotesQuarteirao(dadosLotes);

                    // Mostra o botão Marcador e inputs text
                    $('#btnIncluirMarcador').removeClass('d-none');
                    $('#inputLoteAtual').show();
                    $('#inputQuadraAtual').show();

                    // Abre a divCadastro3
                    $('#divCadastro3').fadeIn(150);
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao buscar lotes:', error);
                }
            });
        }

        // Função para ordenar lotes numericamente respeitando sufixos alfabéticos
        function ordenarLotesNumericamente(lotes) {
            return lotes.sort((a, b) => {
                const loteA = a.lote.toString();
                const loteB = b.lote.toString();

                // Extrai número e sufixo de cada lote
                const matchA = loteA.match(/^(\d+)([A-Za-z]*)$/);
                const matchB = loteB.match(/^(\d+)([A-Za-z]*)$/);

                if (!matchA || !matchB) {
                    // Se não conseguir extrair, usa ordenação alfabética como fallback
                    return loteA.localeCompare(loteB);
                }

                const numeroA = parseInt(matchA[1]);
                const numeroB = parseInt(matchB[1]);
                const sufixoA = matchA[2] || '';
                const sufixoB = matchB[2] || '';

                // Primeiro compara os números
                if (numeroA !== numeroB) {
                    return numeroA - numeroB;
                }

                // Se os números são iguais, compara os sufixos alfabeticamente
                return sufixoA.localeCompare(sufixoB);
            });
        }

        // Função para popular a divCadastro3 com os lotes do quarteirão
        function popularLotesQuarteirao(dadosLotes) {

            const container = $('#opcoesLotes');
            container.empty();

            $('#quarteiraoSelecionado2').text(quarteiraoAtualSelecionado);
            $('#qtdLotes').text(dadosLotes.length);

            if (!dadosLotes || dadosLotes.length === 0) {
                container.html('<div class="alert alert-info">Nenhum lote encontrado para este quarteirão.</div>');
                return;
            }

            // Agrupa os lotes por quadra
            const lotesPorQuadra = {};

            dadosLotes.forEach(lote => {
                const quadra = lote.quadra;

                if (!lotesPorQuadra[quadra]) {
                    lotesPorQuadra[quadra] = [];
                }
                lotesPorQuadra[quadra].push(lote);
            });

            // Função para verificar se um lote já foi inserido no mapa
            function verificarLoteJaInserido(quadra, numeroLote) {
                if (!arrayCamadas.marcador_quadra || arrayCamadas.marcador_quadra.length === 0) {
                    return false;
                }

                // Procura por um marcador que tenha a mesma quadra, número de lote E quarteirão
                return arrayCamadas.marcador_quadra.some(marker => {
                    return marker.quadra == quadra &&
                        marker.numeroMarcador == numeroLote &&
                        marker.quarteirao == quarteiraoAtualSelecionado;
                });
            }

            // Cria as opções para cada quadra
            Object.keys(lotesPorQuadra).forEach(quadra => {
                const lotes = ordenarLotesNumericamente(lotesPorQuadra[quadra]);

                lotes.forEach((lote, index) => {
                    // Verifica se este lote já foi inserido no mapa
                    const jaInserido = verificarLoteJaInserido(quadra, lote.lote);

                    const opcao = $(`
                        <div class="opcao-lote" data-quadra="${quadra}" data-lote="${lote.lote}">
                            <div class="d-flex align-items-center">
                                <span class="lote-flecha me-2" style="color: #007bff; font-weight: bold;">${index === 0 && !jaInserido ? '>' : '&nbsp;&nbsp;'}</span>
                                <span class="lote-texto">
                                    Quadra: ${quadra} | Lote: ${lote.lote}
                                </span>
                            </div>
                        </div>
                    `);

                    // Se o lote já foi inserido, marca como verde
                    if (jaInserido) {
                        opcao.css({
                            'background-color': '#d4edda',
                            'border-color': '#c3e6cb',
                            'color': '#155724'
                        }).addClass('lote-inserido');
                    }

                    // Adiciona evento de clique
                    opcao.on('click', function() {
                        // Verifica se este lote já foi inserido
                        if ($(this).hasClass('lote-inserido')) {
                            alert('Este lote já foi inserido!');
                            return;
                        }

                        // Remove a flecha de todos os lotes
                        $('.lote-flecha').html('&nbsp;&nbsp;');

                        // Adiciona a flecha ao lote clicado
                        $(this).find('.lote-flecha').html('>');

                        const lote = $(this).data('lote');
                        const quadra = $(this).data('quadra');

                        //console.log('Lote clicado:', lote);
                        //console.log('Quadra clicada:', quadra);

                        // Atualiza os inputs text com o lote e quadra selecionados
                        $('#inputLoteAtual').val(lote);
                        $('#inputQuadraAtual').val(quadra);

                        // Adiciona classe visual para destacar a opção selecionada
                        $('.opcao-lote').removeClass('selected');
                        $(this).addClass('selected');
                    });

                    container.append(opcao);
                });
            });

            // Seleciona o primeiro lote NÃO INSERIDO por padrão
            const lotesDisponiveis = container.find('.opcao-lote:not(.lote-inserido)');
            if (lotesDisponiveis.length > 0) {
                const primeiroLoteDisponivel = lotesDisponiveis.first();
                primeiroLoteDisponivel.trigger('click');
            }
        }

        // Variáveis globais para controle do tooltip
        let marcadorAtualTooltip = null;
        let marcadorIdAtual = null;

        // Variáveis globais para controle do quarteirão e quadra selecionados
        let quarteiraoAtualSelecionado = null;
        let quarteiraoIdAtualSelecionado = null;

        // Função para atualizar a lista de lotes na divCadastro3
        function atualizarListaLotes() {
            // Verifica se a divCadastro3 está visível
            if (!$('#divCadastro3').is(':visible')) {
                return; // Não faz nada se a div não estiver aberta
            }

            // Re-aplica a verificação de lotes já inseridos para todos os itens da lista
            $('.opcao-lote').each(function() {
                const $elemento = $(this);
                const quadra = $elemento.data('quadra');
                const lote = $elemento.data('lote');

                // Verifica se este lote ainda está no mapa (inclui verificação do quarteirão)
                const jaInserido = arrayCamadas.marcador_quadra.some(marker => {
                    return marker.quadra == quadra &&
                        marker.numeroMarcador == lote &&
                        marker.quarteirao == quarteiraoAtualSelecionado;
                });

                if (jaInserido) {
                    // Marca como inserido (verde)
                    $elemento.css({
                        'background-color': '#d4edda',
                        'border-color': '#c3e6cb',
                        'color': '#155724'
                    }).addClass('lote-inserido');
                } else {
                    // Remove marcação de inserido (volta ao normal)
                    $elemento.css({
                        'background-color': '#fafafa',
                        'border-color': '#eee',
                        'color': '#333'
                    }).removeClass('lote-inserido');
                }
            });
        }

        // Função específica para atualizar a lista após deletar marcadores novos
        function atualizarListaAposDeletarMarcadorNovo(quadraDeletada, loteDeletado) {

            // Procura o elemento específico na lista e libera ele
            $(`.opcao-lote`).each(function() {
                const $elemento = $(this);
                const quadraLista = $elemento.data('quadra');
                const loteLista = $elemento.data('lote');

                // Compara convertendo ambos para string para garantir match
                if (String(quadraLista) === String(quadraDeletada) && String(loteLista) === String(loteDeletado)) {

                    // Remove marcação de inserido (volta ao normal)
                    $elemento.css({
                        'background-color': '#fafafa',
                        'border-color': '#eee',
                        'color': '#333'
                    }).removeClass('lote-inserido');

                    // Se este era o lote selecionado, mantém a seleção
                    if ($elemento.hasClass('selected')) {
                        $elemento.addClass('selected');
                    }

                    return false; // Para o loop quando encontra
                }
            });
        }

        // Função para mostrar InfoWindow do marcador
        function mostrarTooltipMarcador(marker, event) {
            marcadorAtualTooltip = marker;
            marcadorIdAtual = marker.identificadorBanco; // ID no banco

            // Pega o loteamento selecionado na tela
            const loteamentoSelecionado = $('input[name="loteamento"]:checked').data('loteamento') || 'N/A';

            // Mostra loading no InfoWindow
            const infoWindow = new google.maps.InfoWindow({
                content: '<div style="padding: 10px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> Carregando dados...</div>'
            });

            infoWindow.open(MapFramework.map, marker);

            // Busca dados do marcador
            $.ajax({
                url: 'buscar_dados_marcador.php',
                method: 'GET',
                data: {
                    id_marcador: marker.identificadorBanco
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'sucesso') {
                        const dados = response.dados;
                        let content = '<div style="padding: 15px; min-width: 250px;">';

                        // Dados da tabela desenhos
                        content += '<h6 style="margin: 0 0 10px 0; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Desenhos</h6>';
                        content += '<div style="margin-bottom: 15px;">';
                        content += '<div style="margin-bottom: 3px;"><strong>Quadricula:</strong> ' + (dados.desenhos.quadricula || 'N/A') + '</div>';
                        content += '<div style="margin-bottom: 3px;"><strong>Loteamento:</strong> ' + loteamentoSelecionado + '</div>';
                        content += '<div style="margin-bottom: 3px;"><strong>Quarteirão:</strong> ' + (dados.desenhos.quarteirao || 'N/A') + '</div>';
                        content += '<div style="margin-bottom: 3px;"><strong>Quadra:</strong> ' + (dados.desenhos.quadra || 'N/A') + '</div>';
                        content += '<div style="margin-bottom: 3px;"><strong>Lote:</strong> ' + (dados.desenhos.lote || 'N/A') + '</div>';
                        content += '<div style="margin-bottom: 3px;"><strong>Desenho:</strong> ' + (dados.desenhos.id || 'N/A') + '</div>';
                        content += '</div>';

                        // Dados da tabela cadastro (se existir)
                        if (dados.cadastro) {
                            content += '<h6 style="margin: 0 0 10px 0; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Cadastro</h6>';
                            content += '<div style="margin-bottom: 15px;">';
                            content += '<div style="margin-bottom: 3px;"><strong>ID Imobiliário:</strong> ' + (dados.cadastro.imob_id || 'N/A') + '</div>';
                            content += '<div style="margin-bottom: 3px;"><strong>Rua:</strong> ' + (dados.cadastro.logradouro || 'N/A') + '</div>';
                            content += '<div style="margin-bottom: 3px;"><strong>Número:</strong> ' + (dados.cadastro.numero || 'N/A') + '</div>';
                            content += '<div style="margin-bottom: 3px;"><strong>Bairro:</strong> ' + (dados.cadastro.bairro || 'N/A') + '</div>';
                            content += '</div>';
                        } else {
                            content += '<div style="margin-bottom: 15px; color: #666; font-style: italic;">Nenhum dado encontrado na tabela cadastro</div>';
                        }

                        // Botões de ação
                        content += '<div style="text-align: center; margin-top: 10px;">';
                        content += '<button id="btnEditMarcadorInfoWindow" class="btn btn-warning btn-sm" style="background-color: #ffc107; color: black; border: none; padding: 5px 15px; border-radius: 3px; cursor: pointer; margin-right: 10px;">';
                        content += '<i class="fas fa-edit"></i> Editar';
                        content += '</button>';
                        content += '<button id="btnDeleteMarcadorInfoWindow" class="btn btn-danger btn-sm" style="background-color: #dc3545; color: white; border: none; padding: 5px 15px; border-radius: 3px; cursor: pointer;">';
                        content += '<i class="fas fa-trash"></i> Deletar';
                        content += '</button>';
                        content += '</div>';

                        // Botão salvar (inicialmente oculto)
                        content += '<div id="divSalvarMarcador" style="text-align: center; margin-top: 10px; display: none;">';
                        content += '<button id="btnSalvarMarcadorInfoWindow" class="btn btn-success btn-sm" style="background-color: #28a745; color: white; border: none; padding: 5px 15px; border-radius: 3px; cursor: pointer;">';
                        content += '<i class="fas fa-save"></i> Salvar';
                        content += '</button>';
                        content += '</div>';

                        content += '</div>';

                        // Atualiza o InfoWindow com os dados
                        infoWindow.setContent(content);

                        // Adiciona eventos aos botões
                        setTimeout(() => {
                            // Evento do botão deletar
                            $('#btnDeleteMarcadorInfoWindow').on('click', function() {
                                if (confirm('Tem certeza que deseja deletar este marcador?')) {
                                    deletarMarcador(marcadorIdAtual, marker);
                                    infoWindow.close();
                                }
                            });

                            // Evento do botão editar
                            $('#btnEditMarcadorInfoWindow').on('click', function() {
                                entrarModoEdicaoMarcador(dados.desenhos, infoWindow, dados.desenhos.id);
                            });

                            // Evento do botão salvar
                            $('#btnSalvarMarcadorInfoWindow').on('click', function() {
                                salvarEdicaoMarcador(marcadorIdAtual, infoWindow, marker);
                            });
                        }, 100);

                    } else {
                        infoWindow.setContent('<div style="padding: 10px; color: red;">Erro ao carregar dados: ' + response.mensagem + '</div>');
                    }
                },
                error: function() {
                    infoWindow.setContent('<div style="padding: 10px; color: red;">Erro ao carregar dados do marcador</div>');
                }
            });
        }

        // Função para esconder tooltip
        function esconderTooltipMarcador() {
            $('#tooltipMarcador').hide();
            marcadorAtualTooltip = null;
            marcadorIdAtual = null;
        }

        // Função para entrar no modo de edição do marcador
        function entrarModoEdicaoMarcador(dadosDesenhos, infoWindow, idDesenho) {
            // Pega o loteamento selecionado na tela
            const loteamentoSelecionado = $('input[name="loteamento"]:checked').data('loteamento') || 'N/A';

            // Monta o conteúdo em modo de edição
            let content = '<div style="padding: 15px; min-width: 250px;">';

            // Dados da tabela desenhos (em modo edição)
            content += '<h6 style="margin: 0 0 10px 0; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Desenhos</h6>';
            content += '<div style="margin-bottom: 15px;">';
            content += '<div style="margin-bottom: 3px;"><strong>Quadrícula:</strong> ' + (dadosDesenhos.quadricula || 'N/A') + '</div>';
            content += '<div style="margin-bottom: 3px;"><strong>Loteamento:</strong> ' + loteamentoSelecionado + '</div>';
            content += '<div style="margin-bottom: 3px;"><strong>Quarteirão:</strong> <input type="text" id="editQuarteirao" value="' + (dadosDesenhos.quarteirao || '') + '" style="width: 100px; padding: 2px; border: 1px solid #ccc; border-radius: 3px;"></div>';
            content += '<div style="margin-bottom: 3px;"><strong>Quadra:</strong> <input type="text" id="editQuadra" value="' + (dadosDesenhos.quadra || '') + '" style="width: 100px; padding: 2px; border: 1px solid #ccc; border-radius: 3px;"></div>';
            content += '<div style="margin-bottom: 3px;"><strong>Lote:</strong> <input type="text" id="editLote" value="' + (dadosDesenhos.lote || '') + '" style="width: 100px; padding: 2px; border: 1px solid #ccc; border-radius: 3px;"></div>';
            content += '<div style="margin-bottom: 3px;"><strong>Desenho:</strong> ' + (dadosDesenhos.id || 'N/A') + '</div>';
            content += '</div>';

            // Dados da tabela cadastro (se existir) - apenas visualização
            content += '<h6 style="margin: 0 0 10px 0; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Cadastro</h6>';
            content += '<div style="margin-bottom: 15px; color: #666; font-style: italic;">Dados do cadastro não podem ser editados aqui</div>';

            // Botão salvar
            content += '<div id="divSalvarMarcador" style="text-align: center; margin-top: 10px;">';
            content += '<button id="btnSalvarMarcadorInfoWindow" class="btn btn-success btn-sm" style="background-color: #28a745; color: white; border: none; padding: 5px 15px; border-radius: 3px; cursor: pointer;">';
            content += '<i class="fas fa-save"></i> Salvar';
            content += '</button>';
            content += '</div>';

            content += '</div>';

            // Atualiza o InfoWindow
            infoWindow.setContent(content);

            // Adiciona evento ao botão salvar
            setTimeout(() => {
                $('#btnSalvarMarcadorInfoWindow').on('click', function() {
                    salvarEdicaoMarcador(idDesenho, infoWindow, marcadorAtualTooltip);
                });
            }, 100);
        }

        // Função para salvar a edição do marcador
        function salvarEdicaoMarcador(idMarcador, infoWindow, marker) {
            console.log('ID do marcador para edição:', idMarcador);

            const quarteirao = $('#editQuarteirao').val().trim();
            const quadra = $('#editQuadra').val().trim();
            const lote = $('#editLote').val().trim();

            if (!quarteirao || !quadra || !lote) {
                alert('Todos os campos são obrigatórios!');
                return;
            }

            // Salva a posição do marcador antes de enviar
            let posicaoMarcador = null;
            if (arrayCamadas.marcador_quadra) {
                for (let i = 0; i < arrayCamadas.marcador_quadra.length; i++) {
                    if (arrayCamadas.marcador_quadra[i].identificadorBanco == idMarcador) {
                        posicaoMarcador = {
                            lat: arrayCamadas.marcador_quadra[i].position.lat,
                            lng: arrayCamadas.marcador_quadra[i].position.lng
                        };
                        break;
                    }
                }
            }

            // Verifica se o lote existe no divCadastro3 para definir a cor
            let correspondeAoLoteSelecionado = false;
            $('#divCadastro3 .opcao-lote').each(function() {
                const loteItem = $(this).data('lote');
                if (loteItem == lote) {
                    correspondeAoLoteSelecionado = true;
                    return false; // break
                }
            });

            // Define a cor baseada na verificação
            const corFinal = correspondeAoLoteSelecionado ? '#32CD32' : '#FF0000'; // Verde ou Vermelho

            // Envia dados para o servidor incluindo a cor
            $.ajax({
                url: 'editar_marcador.php',
                method: 'POST',
                data: {
                    id_marcador: idMarcador,
                    quarteirao: quarteirao,
                    quadra: quadra,
                    lote: lote,
                    cor: corFinal
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'sucesso') {
                        // Remove o marcador antigo do array e do mapa
                        if (arrayCamadas.marcador_quadra) {
                            for (let i = arrayCamadas.marcador_quadra.length - 1; i >= 0; i--) {
                                if (arrayCamadas.marcador_quadra[i].identificadorBanco == idMarcador) {
                                    arrayCamadas.marcador_quadra[i].setMap(null); // Remove do mapa
                                    arrayCamadas.marcador_quadra.splice(i, 1); // Remove do array
                                    break;
                                }
                            }
                        }

                        // Recria o marcador com os novos dados
                        if (posicaoMarcador) {
                            MapFramework.recriarMarcadorEditado({
                                id: idMarcador,
                                quarteirao: quarteirao,
                                quadra: quadra,
                                lote: lote,
                                cor: corFinal,
                                lat: posicaoMarcador.lat,
                                lng: posicaoMarcador.lng
                            });
                        }

                        infoWindow.close();
                    } else {
                        alert('Erro ao editar marcador: ' + (response.mensagem || 'Erro desconhecido'));
                    }
                },
                error: function() {
                    alert('Erro ao editar marcador no servidor.');
                }
            });
        }

        // Sistema de controle de visibilidade dos botões por modo
        function controlarVisibilidadeBotoes(modoAtivo) {
            // Lista de todos os botões de modos
            const botoesModos = [
                'btnIncluirPoligono', // Modo Quadra
                'btnIncluirUnidade', // Modo Unidade
                'btnIncluirPiscina', // Modo Piscina
                'btnIncluirLinha', // Modo Lote
                'btnIncluirMarcador', // Modo Marcador
                'btnLerPDF', // Modo PDF
                'btnFinalizarDesenho', // Botão de sair do desenho
                'btnSairModoMarcador', // Botão de sair do marcador
                'btnEditar', // Botão de editar
                'btnExcluir', // Botão de excluir
                'btnSairEdicao' // Botão de sair da edição
            ];

            // Oculta todos os botões primeiro
            botoesModos.forEach(botaoId => {
                $(`#${botaoId}`).addClass('d-none');
            });

            // Mostra apenas os botões do modo ativo
            switch (modoAtivo) {
                case 'quadra':
                    $('#btnFinalizarDesenho').removeClass('d-none');
                    break;

                case 'unidade':
                    $('#btnFinalizarDesenho').removeClass('d-none');
                    break;

                case 'piscina':
                    $('#btnFinalizarDesenho').removeClass('d-none');
                    break;

                case 'lote':
                    $('#btnFinalizarDesenho').removeClass('d-none');
                    break;

                case 'marcador':
                    // Modo marcador é um submodo do cadastro
                    $('#btnSairModoMarcador').removeClass('d-none');
                    break;

                case 'cadastro':
                    // Modo cadastro agora é controlado pelo checkbox
                    // Não há botão específico a mostrar
                    break;

                case 'pdf':
                    // No modo PDF, não oculta outros botões pois é um modo especial
                    break;

                case 'normal':
                default:
                    // Modo normal - mostra botões principais
                    $('#btnIncluirPoligono').removeClass('d-none');
                    $('#btnIncluirUnidade').removeClass('d-none');
                    $('#btnIncluirPiscina').removeClass('d-none');
                    $('#btnIncluirLinha').removeClass('d-none');
                    // btnCadastro foi removido - agora é checkbox
                    // Botões de editar/excluir só aparecem se há quadra selecionada
                    // (serão controlados pelo framework.js)
                    break;
            }
        }

        // Função para voltar ao modo cadastro (usado quando sai do modo marcador)
        function voltarModoCadastro() {
            controlarVisibilidadeBotoes('cadastro');
            // Oculta os inputs text do marcador
            $('#inputLoteAtual').hide();
            $('#inputQuadraAtual').hide();
        }

        // Evento do botão deletar no tooltip
        $('#btnDeleteMarcador').on('click', function() {
            if (!marcadorIdAtual || !marcadorAtualTooltip) return;

            deletarMarcador(marcadorIdAtual, marcadorAtualTooltip);
        });

        // Função para deletar marcador via AJAX
        function deletarMarcador(idMarcador, marcadorElement) {
            $.ajax({
                url: 'deletarMarcador.php',
                method: 'POST',
                data: {
                    id: idMarcador
                },
                success: function(response) {
                    try {
                        let resultado = response;
                        if (typeof response === 'string') {
                            resultado = JSON.parse(response);
                        }

                        if (resultado.status === 'sucesso') {
                            // Remove o marcador do mapa
                            marcadorElement.setMap(null);

                            // Remove da camada
                            // Guarda os dados do marcador antes de remover
                            const quadraMarcador = marcadorElement.quadra;
                            const loteMarcador = marcadorElement.numeroMarcador;

                            const index = arrayCamadas['marcador_quadra'].indexOf(marcadorElement);
                            if (index > -1) {
                                arrayCamadas['marcador_quadra'].splice(index, 1);
                            }

                            // Usa a função específica para marcadores novos
                            atualizarListaAposDeletarMarcadorNovo(quadraMarcador, loteMarcador);

                            // Esconde o tooltip
                            esconderTooltipMarcador();

                        } else {
                            alert('Erro ao deletar marcador: ' + (resultado.mensagem || 'Erro desconhecido'));
                        }
                    } catch (e) {
                        alert('Erro ao processar resposta do servidor');
                        console.error('Erro:', e);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Erro na comunicação com o servidor');
                    console.error('Erro AJAX:', error);
                }
            });
        }

        // Esconde tooltip quando clicar fora
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#tooltipMarcador').length && !$(e.target).hasClass('marcador-personalizado')) {
                esconderTooltipMarcador();
            }
        });

        // Função para popular os controles integrados com dados do divCadastro principal
        function popularControlesIntegrados(quadricula) {

            // Atualizar título da quadrícula
            $('#quadriculaAtualIntegrado').text(quadricula);

            // Copiar loteamentos do divCadastro principal
            const loteamentosHtml = $('#opcoesLoteamentos').html();
            $('#opcoesLoteamentosIntegrado').html(loteamentosHtml);

            // Ajustar IDs para evitar conflitos
            ajustarIDsControlesIntegrados();

            // Mostrar o divCadastro integrado
            $('#divCadastroIntegrado').show();

            // Sincronizar com as seleções originais
            sincronizarSelecaoInicial();

            // Adicionar eventos para os controles integrados
            adicionarEventosControlesIntegrados();

        }

        // Função para sincronizar seleção inicial com os controles originais
        function sincronizarSelecaoInicial() {

            // Encontrar loteamento selecionado no original
            const loteamentoOriginal = $('input[name="loteamento"]:checked');
            if (loteamentoOriginal.length > 0) {
                const indexLoteamento = loteamentoOriginal.val();
                const nomeLoteamento = loteamentoOriginal.data('loteamento');


                // Selecionar o mesmo loteamento no integrado
                $(`#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"][value="${indexLoteamento}"]`).prop('checked', true);
                $(`#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"][value="${indexLoteamento}"]`).closest('.opcao-loteamento').addClass('selected');

                // Encontrar PDF selecionado no original
                const pdfOriginal = $(`input[name="pdf_loteamento_${indexLoteamento}"]:checked`);
                if (pdfOriginal.length > 0) {
                    const arquivoPdf = pdfOriginal.data('arquivo');
                    const pdfIndex = pdfOriginal.val(); // Índice do PDF na lista

                    // CORREÇÃO: Selecionar o PDF correto no modal integrado sem desmarcar outros
                    const pdfIntegrado = $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexLoteamento}"][data-arquivo="${arquivoPdf}"]`);
                    if (pdfIntegrado.length > 0) {
                        // Desmarcar apenas os PDFs do mesmo loteamento
                        $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexLoteamento}"]`).prop('checked', false);
                        // Marcar o PDF correto
                        pdfIntegrado.prop('checked', true);
                    } else {
                        // Fallback: tentar por índice
                        const pdfIntegradoFallback = $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexLoteamento}"][value="${pdfIndex}"]`);
                        if (pdfIntegradoFallback.length > 0) {
                            $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexLoteamento}"]`).prop('checked', false);
                            pdfIntegradoFallback.prop('checked', true);
                        }
                    }

                    // CORREÇÃO: Não carregar PDF aqui para evitar carregamento duplo
                    // O PDF será carregado pela função abrirLeitorPDF

                    // SEMPRE abrir divCadastro2Integrado se há um PDF selecionado
                    abrirDivCadastro2Integrado(parseInt(indexLoteamento));
                } else {
                    // CORREÇÃO: Não selecionar automaticamente o primeiro PDF para evitar sobrescrever a sincronização
                }
            } else {
                // CORREÇÃO: Não carregar primeiro PDF automaticamente se há um PDF sendo carregado via abrirLeitorPDF
                if (window.carregandoPDFViaAbrirLeitorPDF || (window.dadosLeitorPDF && window.dadosLeitorPDF.arquivo)) {} else {
                    // CORREÇÃO: Não carregar primeiro PDF automaticamente para evitar conflitos
                    // carregarPrimeiroPDFAutomatico();
                }
            }
        }

        // Função para ajustar IDs dos controles integrados para evitar conflitos
        function ajustarIDsControlesIntegrados() {

            // Mudar IDs e names dos loteamentos integrados
            $('#opcoesLoteamentosIntegrado input[name="loteamento"]').each(function(index) {
                const novoId = `loteamento_integrado_${index}`;
                $(this).attr('id', novoId);
                $(this).attr('name', 'loteamentoIntegrado');
                $(this).next('label').attr('for', novoId);
            });

            // Mudar IDs e names dos PDFs integrados
            $('#opcoesLoteamentosIntegrado input[name^="pdf_loteamento_"]').each(function() {
                const name = $(this).attr('name');
                const index = name.match(/\d+/)[0];
                const pdfIndex = $(this).val();
                const novoId = `pdf_integrado_${index}_${pdfIndex}`;
                const novoName = `pdf_loteamento_integrado_${index}`;

                $(this).attr('id', novoId);
                $(this).attr('name', novoName);
                $(this).next('label').attr('for', novoId);
            });

            // Ajustar IDs dos containers de PDFs
            $('#opcoesLoteamentosIntegrado .submenu-pdfs').each(function(index) {
                $(this).attr('id', `pdfs_loteamento_integrado_${index}`);
            });

            // Todos os PDFs começam habilitados nos controles integrados
            $('#opcoesLoteamentosIntegrado input[name^="pdf_loteamento_integrado_"]').prop('disabled', false);

            // CORREÇÃO: Não limpar seleções dos PDFs para manter a seleção do usuário
            // $('#opcoesLoteamentosIntegrado input[name^="pdf_loteamento_integrado_"]').prop('checked', false);

            // CORREÇÃO: Não selecionar automaticamente o primeiro PDF
            // const primeiroPDF = $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexLoteamento}"]:first`);
            // if (primeiroPDF.length > 0) {
            //     primeiroPDF.prop('checked', true);
            // }

            // CORREÇÃO: Não limpar seleções dos loteamentos para manter a seleção do usuário
            // $('#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"]').prop('checked', false);

        }

        // Função para carregar automaticamente o primeiro PDF
        function carregarPrimeiroPDFAutomatico() {

            // Aguardar um pouco para garantir que os IDs foram ajustados
            setTimeout(() => {
                // Selecionar primeiro loteamento integrado
                const primeiroLoteamento = $('#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"]:first');
                if (primeiroLoteamento.length > 0) {

                    // Garantir que apenas este loteamento está selecionado
                    $('#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"]').prop('checked', false);
                    $('#opcoesLoteamentosIntegrado .opcao-loteamento').removeClass('selected');

                    primeiroLoteamento.prop('checked', true);
                    primeiroLoteamento.closest('.opcao-loteamento').addClass('selected');

                    const indexLoteamento = primeiroLoteamento.val();

                    // Garantir que apenas o primeiro PDF está selecionado
                    $('#opcoesLoteamentosIntegrado input[name^="pdf_loteamento_integrado_"]').prop('checked', false);

                    // Selecionar primeiro PDF do primeiro loteamento
                    const primeiroPDF = $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexLoteamento}"]:first`);
                    if (primeiroPDF.length > 0) {
                        primeiroPDF.prop('checked', true);


                        // Carregar o PDF automaticamente
                        const loteamento = primeiroPDF.data('loteamento');
                        const arquivo = primeiroPDF.data('arquivo');
                        const quadricula = primeiroPDF.data('quadricula') || window.dadosLeitorPDF.quadricula;


                        // Aguardar um pouco para o viewer estar pronto
                        setTimeout(async () => {
                            if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.loadSpecificPDF) {
                                await window.pdfViewerIntegrado.loadSpecificPDF(loteamento, arquivo, quadricula);
                            }
                        }, 500);
                    }
                }
            }, 200);
        }

        // Função removida: sincronização não é mais necessária - controles são independentes

        // Função para interceptar e modificar o HTML dos quarteirões
        function modificarHtmlQuarteires(html) {

            // Criar um elemento temporário para manipular o HTML
            const tempDiv = $('<div>').html(html);

            // Para cada quarteirão, modificar a estrutura das quadras (mantendo duplicidades para sincronização)
            tempDiv.find('.opcao-quarteirao').each(function() {
                const quarteiraoElement = $(this);
                const inputElement = quarteiraoElement.find('input');
                const smallText = quarteiraoElement.find('small').text();

                // Preservar o ID único do quarteirão
                const quarteiraoId = inputElement.attr('id');
                const quarteiraoValue = inputElement.val();
                const quarteiraoNome = inputElement.data('nome');


                // Extrair quadras do texto "Quadras: A, B, C"
                const quadrasMatch = smallText.match(/Quadras:\s*(.+)/);
                if (quadrasMatch && quadrasMatch[1]) {
                    const quadrasText = quadrasMatch[1].trim();
                    const quadras = quadrasText.split(',').map(q => q.trim()).filter(q => q.length > 0);

                    if (quadras.length > 0) {
                        // Remover o texto small original
                        quarteiraoElement.find('small').remove();

                        // Adicionar as quadras como radio buttons
                        quadras.forEach((quadra) => {
                            const quadraHtml = `
                                <div class="opcao-quadra" style="margin-bottom: 3px; padding-left: 20px;">
                                    <input type="radio" name="quadraIntegrado_${quarteiraoValue}" value="${quadra}" data-quarteirao="${quarteiraoNome}" data-quarteirao-id="${quarteiraoValue}" style="margin-right: 6px;">
                                    <label style="font-size: 11px; color: #555; cursor: pointer; margin: 0;">
                                        ${quadra}
                                    </label>
                                </div>
                            `;
                            quarteiraoElement.append(quadraHtml);
                        });
                    }
                }
            });

            return tempDiv.html();
        }

        // Função para copiar o estado do divCadastro2 para o integrado
        function copiarDivCadastro2ParaIntegrado() {

            // Copiar HTML do divCadastro2
            const titulo = $('#quarteiraoSelecionado').text();
            let quarteiresHtml = $('#opcoesQuarteiroes').html();

            // Interceptar e modificar o HTML antes de inserir
            quarteiresHtml = modificarHtmlQuarteires(quarteiresHtml);

            $('#quarteiraoSelecionadoIntegrado').text(titulo);
            $('#opcoesQuarteiresIntegrado').html(quarteiresHtml);

            // Ajustar IDs dos inputs copiados - PRESERVAR IDs ÚNICOS DOS QUARTEIRÕES
            $('#opcoesQuarteiresIntegrado input[name="quarteirao"]').attr('name', 'quarteiraoIntegrado');
            $('#opcoesQuarteiresIntegrado input[id]').each(function() {
                const oldId = $(this).attr('id');
                const newId = oldId + 'Integrado';
                $(this).attr('id', newId);

                // Atualizar labels correspondentes
                $(`#opcoesQuarteiresIntegrado label[for="${oldId}"]`).attr('for', newId);
            });


            // Verificar se há quarteirão selecionado no original e sincronizar
            const quarteiraoOriginal = $('input[name="quarteirao"]:checked');
            if (quarteiraoOriginal.length > 0) {
                const quarteiraoNome = quarteiraoOriginal.data('nome');
                const quarteiraoId = quarteiraoOriginal.val(); // ID único do quarteirão

                // Selecionar o mesmo no integrado usando o ID único
                $(`#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"][value="${quarteiraoId}"]`).prop('checked', true);
                $(`#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"][value="${quarteiraoId}"]`).closest('.opcao-quarteirao').addClass('selected');

                // Atualizar variáveis globais com informações completas
                window.quarteiraoAtualDesenho = quarteiraoId; // ID único
                window.quarteiraoIdAtualDesenho = quarteiraoId; // ID único (mesmo valor)
                window.quarteiraoNumeroAtualDesenho = quarteiraoNome; // Número do quarteirão

                // Resetar modos de desenho ao trocar de quarteirão
                if (window.pdfViewerIntegrado) {
                    window.pdfViewerIntegrado.onQuarteiraoQuadraChanged();
                }
            } else {
                // Limpar seleções no integrado
                $('#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"]').prop('checked', false);
                $('#opcoesQuarteiresIntegrado .opcao-quarteirao').removeClass('selected');
                window.quarteiraoAtualDesenho = null;
                window.quarteiraoIdAtualDesenho = null;
                window.quarteiraoNumeroAtualDesenho = null;

                // Resetar modos de desenho ao limpar quarteirão
                if (window.pdfViewerIntegrado) {
                    window.pdfViewerIntegrado.onQuarteiraoQuadraChanged();
                }
            }

            // Adicionar eventos para os quarteirões integrados
            adicionarEventosQuarteiresIntegrados();

            // Quadras já foram adicionadas durante a interceptação do HTML
            // Adicionar eventos para as quadras
            adicionarEventosQuadrasIntegradas();

            // Adicionar eventos para sincronização bidirecional
            adicionarSincronizacaoQuarteiroes();

            // Atualizar botões baseado no estado atual
            if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.updateButtonsVisibility) {
                window.pdfViewerIntegrado.updateButtonsVisibility();
            }

            // Mostrar divCadastro2 integrado
            $('#divCadastro2Integrado').show();
        }

        // Função para adicionar eventos das quadras (agora integrada no HTML interceptado)
        function adicionarEventosQuadrasIntegradas() {

            // Eventos para quadras integradas (radio buttons)
            $('#opcoesQuarteiresIntegrado input[name^="quadraIntegrado_"]').off('change').on('change', function(e) {
                const quadra = $(this).val();
                const quarteirao = $(this).data('quarteirao');
                const quarteiraoId = $(this).data('quarteirao-id');
                const isChecked = $(this).is(':checked');

                // Verificar se o quarteirão está selecionado
                const quarteiraoSelecionado = $(`#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"][value="${quarteiraoId}"]`).is(':checked');

                if (!quarteiraoSelecionado) {
                    // Se o quarteirão não está selecionado, desmarcar o radio silenciosamente
                    $(this).prop('checked', false);
                    return; // Sair da função sem executar o resto
                }


                // Desativar modo de desenho ao trocar quadra
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.deactivateDrawingMode) {
                    window.pdfViewerIntegrado.deactivateDrawingMode();
                }

                if (isChecked) {
                    // Atualizar variável global de quadra
                    window.quadraAtualDesenho = quadra;

                    // Remover seleção visual de outras quadras do mesmo quarteirão
                    $(`input[name="quadraIntegrado_${quarteiraoId}"]`).closest('.opcao-quadra').removeClass('selected');
                    // Adicionar seleção visual à quadra atual
                    $(this).closest('.opcao-quadra').addClass('selected');

                } else {
                    // Se desmarcou, limpar a quadra atual
                    window.quadraAtualDesenho = null;
                    $(this).closest('.opcao-quadra').removeClass('selected');
                }

                // Resetar modos de desenho ao trocar de quadra
                if (window.pdfViewerIntegrado) {
                    window.pdfViewerIntegrado.onQuarteiraoQuadraChanged();
                }

                // Atualizar visibilidade dos botões de desenho
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.updateButtonsVisibility) {
                    window.pdfViewerIntegrado.updateButtonsVisibility();
                }
            });

            // Permitir click em toda a opcao-quadra para selecionar
            $('#opcoesQuarteiresIntegrado .opcao-quadra').off('click').on('click', function(e) {
                const input = $(this).find('input[type="radio"]');
                if (input.length > 0) {
                    const quarteirao = input.data('quarteirao');
                    const quarteiraoId = input.data('quarteirao-id');

                    // Verificar se o quarteirão está selecionado
                    const quarteiraoSelecionado = $(`#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"][value="${quarteiraoId}"]`).is(':checked');

                    if (!quarteiraoSelecionado) {
                        // Se o quarteirão não está selecionado, não fazer nada
                        return;
                    }

                    // Só prevenir comportamento padrão se NÃO for clique direto no radio
                    if (!$(e.target).is('input[type="radio"]')) {
                        e.preventDefault();
                    }
                    e.stopPropagation();

                    input.prop('checked', true);
                    input.trigger('change');
                }
            });
        }

        // Função para adicionar eventos aos quarteirões integrados
        function adicionarEventosQuarteiresIntegrados() {
            $('#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"]').off('change').on('change', function(e) {
                // NÃO usar preventDefault nem stopPropagation para permitir comportamento nativo do radio

                const nomeQuarteirao = $(this).data('nome');
                const quarteiraoId = $(this).val(); // ID único do quarteirão

                // Desativar modo de desenho ao trocar quarteirão
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.deactivateDrawingMode) {
                    window.pdfViewerIntegrado.deactivateDrawingMode();
                }

                // Atualizar variáveis globais de quarteirão
                window.quarteiraoAtualDesenho = quarteiraoId;
                window.quarteiraoIdAtualDesenho = quarteiraoId;
                window.quarteiraoNumeroAtualDesenho = nomeQuarteirao;

                // Resetar modos de desenho ao trocar de quarteirão
                if (window.pdfViewerIntegrado) {
                    window.pdfViewerIntegrado.onQuarteiraoQuadraChanged();
                }

                // Limpar todos os radio buttons das quadras ao trocar de quarteirão
                $('#opcoesQuarteiresIntegrado input[name^="quadraIntegrado_"]').prop('checked', false);
                $('#opcoesQuarteiresIntegrado .opcao-quadra').removeClass('selected');
                window.quadraAtualDesenho = null;

                // Resetar modos de desenho ao limpar quadra
                if (window.pdfViewerIntegrado) {
                    window.pdfViewerIntegrado.onQuarteiraoQuadraChanged();
                }

                // Remover seleção visual de outros quarteirões
                $('#opcoesQuarteiresIntegrado .opcao-quarteirao').removeClass('selected');
                // Adicionar seleção visual ao quarteirão atual
                $(this).closest('.opcao-quarteirao').addClass('selected');

                // Destacar o quarteirão selecionado no mapa usando o ID único
                if (typeof destacarQuarteiraoSelecionado === 'function') {
                    destacarQuarteiraoSelecionado(nomeQuarteirao, quarteiraoId);
                }

                // Atualizar visibilidade dos botões de desenho
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.updateButtonsVisibility) {
                    window.pdfViewerIntegrado.updateButtonsVisibility();
                }

            });

            // Evitar scroll ao clicar em labels e elementos da lista
            $('#opcoesQuarteiresIntegrado label').off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const input = $(this).prev('input[type="radio"]');
                if (input.length > 0) {
                    input.prop('checked', true);
                    input.trigger('change');
                }
            });

            // Permitir click em toda a opcao-quarteirao para selecionar
            $('#opcoesQuarteiresIntegrado .opcao-quarteirao').off('click').on('click', function(e) {
                // Só prevenir comportamento padrão se NÃO for clique direto no radio
                if (!$(e.target).is('input[type="radio"]')) {
                    e.preventDefault();
                }
                e.stopPropagation();

                const input = $(this).find('input[type="radio"]');
                if (input.length > 0) {
                    input.prop('checked', true);
                    input.trigger('change');
                }
            });
        }

        // Função para adicionar sincronização bidirecional entre quarteirões
        function adicionarSincronizacaoQuarteiroes() {

            // Sincronizar do original para o integrado
            $('#opcoesQuarteiroes input[name="quarteirao"]').off('change.sync').on('change.sync', function() {
                const quarteiraoNome = $(this).data('nome');
                const quarteiraoId = $(this).val(); // ID único do quarteirão

                // Limpar seleções no integrado
                $('#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"]').prop('checked', false);
                $('#opcoesQuarteiresIntegrado .opcao-quarteirao').removeClass('selected');

                // Limpar todos os radio buttons das quadras
                $('#opcoesQuarteiresIntegrado input[name^="quadraIntegrado_"]').prop('checked', false);
                $('#opcoesQuarteiresIntegrado .opcao-quadra').removeClass('selected');
                window.quadraAtualDesenho = null;

                // Resetar modos de desenho ao limpar quadra
                if (window.pdfViewerIntegrado) {
                    window.pdfViewerIntegrado.onQuarteiraoQuadraChanged();
                }

                // Selecionar o mesmo no integrado usando o ID único
                if (quarteiraoId) {
                    $(`#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"][value="${quarteiraoId}"]`).prop('checked', true);
                    $(`#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"][value="${quarteiraoId}"]`).closest('.opcao-quarteirao').addClass('selected');
                    window.quarteiraoAtualDesenho = quarteiraoId;
                    window.quarteiraoIdAtualDesenho = quarteiraoId;
                    window.quarteiraoNumeroAtualDesenho = quarteiraoNome;
                } else {
                    window.quarteiraoAtualDesenho = null;
                    window.quarteiraoIdAtualDesenho = null;
                    window.quarteiraoNumeroAtualDesenho = null;
                }

                // Atualizar botões
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.updateButtonsVisibility) {
                    window.pdfViewerIntegrado.updateButtonsVisibility();
                }
            });

            // Sincronizar do integrado para o original
            $('#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"]').off('change.sync').on('change.sync', function() {
                const quarteiraoNome = $(this).data('nome');
                const quarteiraoId = $(this).val(); // ID único do quarteirão

                // IMPORTANTE: Limpar seleções no integrado primeiro (corrige problema visual)
                $('#opcoesQuarteiresIntegrado input[name="quarteiraoIntegrado"]').prop('checked', false);
                $('#opcoesQuarteiresIntegrado .opcao-quarteirao').removeClass('selected');

                // Limpar todos os radio buttons das quadras
                $('#opcoesQuarteiresIntegrado input[name^="quadraIntegrado_"]').prop('checked', false);
                $('#opcoesQuarteiresIntegrado .opcao-quadra').removeClass('selected');
                window.quadraAtualDesenho = null;

                // Resetar modos de desenho ao limpar quadra
                if (window.pdfViewerIntegrado) {
                    window.pdfViewerIntegrado.onQuarteiraoQuadraChanged();
                }

                // Marcar apenas o quarteirão atual no integrado
                $(this).prop('checked', true);
                $(this).closest('.opcao-quarteirao').addClass('selected');

                // Limpar seleções no original
                $('#opcoesQuarteiroes input[name="quarteirao"]').prop('checked', false);
                $('#opcoesQuarteiroes .opcao-quarteirao').removeClass('selected');

                // Selecionar o mesmo no original usando o ID único
                if (quarteiraoId) {
                    $(`#opcoesQuarteiroes input[name="quarteirao"][value="${quarteiraoId}"]`).prop('checked', true);
                    $(`#opcoesQuarteiroes input[name="quarteirao"][value="${quarteiraoId}"]`).closest('.opcao-quarteirao').addClass('selected');
                    window.quarteiraoAtualDesenho = quarteiraoId;
                    window.quarteiraoIdAtualDesenho = quarteiraoId;
                    window.quarteiraoNumeroAtualDesenho = quarteiraoNome;
                } else {
                    window.quarteiraoAtualDesenho = null;
                    window.quarteiraoIdAtualDesenho = null;
                    window.quarteiraoNumeroAtualDesenho = null;
                }

                // Atualizar botões
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.updateButtonsVisibility) {
                    window.pdfViewerIntegrado.updateButtonsVisibility();
                }
            });
        }

        // Função para adicionar eventos aos controles integrados
        function adicionarEventosControlesIntegrados() {
            // Eventos para loteamentos integrados (novos IDs)
            $('#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"]').off('change').on('change', function() {
                const indexSelecionado = parseInt($(this).val());

                // Desativar modo de desenho ao trocar loteamento
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.deactivateDrawingMode) {
                    window.pdfViewerIntegrado.deactivateDrawingMode();
                }

                // Destacar visualmente
                $('#opcoesLoteamentosIntegrado .opcao-loteamento').removeClass('selected');
                $(this).closest('.opcao-loteamento').addClass('selected');

                // CORREÇÃO: Não limpar seleções de PDFs para manter a seleção do usuário
                // $('#opcoesLoteamentosIntegrado input[name^="pdf_loteamento_integrado_"]').prop('checked', false);

                // Limpar quarteirão atual
                window.quarteiraoAtualDesenho = null;
                window.quarteiraoIdAtualDesenho = null;
                window.quarteiraoNumeroAtualDesenho = null;

                // CORREÇÃO: Usar variável global para selecionar o PDF correto
                if (window.pdfSelecionadoGlobal && window.pdfSelecionadoGlobal.indexLoteamento === indexSelecionado) {
                    const pdfCorreto = $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexSelecionado}"][data-arquivo="${window.pdfSelecionadoGlobal.arquivoPdf}"]`);
                    if (pdfCorreto.length > 0) {
                        pdfCorreto.prop('checked', true);
                        pdfCorreto.trigger('change'); // Dispara o evento para carregar o PDF
                    }
                } else {
                    // Fallback: selecionar o primeiro PDF apenas se não há variável global
                    const primeiroPDF = $(`#opcoesLoteamentosIntegrado input[name="pdf_loteamento_integrado_${indexSelecionado}"]:first`);
                    if (primeiroPDF.length > 0) {
                        primeiroPDF.prop('checked', true);
                        primeiroPDF.trigger('change'); // Dispara o evento para carregar o PDF
                    }
                }

                // CORREÇÃO: Abrir divCadastro2Integrado automaticamente

                // Sincronizar com divCadastro original para popular divCadastro2
                const loteamentoOriginal = $(`input[name="loteamento"][value="${indexSelecionado}"]`);
                if (loteamentoOriginal.length > 0) {
                    // Selecionar o mesmo loteamento no original
                    loteamentoOriginal.prop('checked', true);
                    loteamentoOriginal.trigger('change'); // Dispara o evento para popular divCadastro2

                    // Aguardar um pouco e depois copiar para integrado
                    setTimeout(() => {
                        abrirDivCadastro2Integrado(indexSelecionado);
                    }, 300);
                } else {
                    // CORREÇÃO: Mesmo se não encontrar no original, tentar abrir divCadastro2Integrado
                    setTimeout(() => {
                        abrirDivCadastro2Integrado(indexSelecionado);
                    }, 300);
                }
            });

            // Eventos para PDFs integrados (novos IDs)
            $('#opcoesLoteamentosIntegrado input[name^="pdf_loteamento_integrado_"]').off('change').on('change', async function() {
                const loteamento = $(this).data('loteamento');
                const arquivo = $(this).data('arquivo');
                const quadricula = $(this).data('quadricula') || window.dadosLeitorPDF.quadricula;


                // Desativar modo de desenho ao trocar PDF
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.deactivateDrawingMode) {
                    window.pdfViewerIntegrado.deactivateDrawingMode();
                }

                // IMPORTANTE: Desmarcar todos os outros PDFs primeiro
                $('#opcoesLoteamentosIntegrado input[name^="pdf_loteamento_integrado_"]').prop('checked', false);
                // Marcar apenas o PDF selecionado
                $(this).prop('checked', true);

                // Encontrar e selecionar o loteamento correspondente
                const nomeInput = $(this).attr('name');
                const indexLoteamento = nomeInput.match(/pdf_loteamento_integrado_(\d+)/)[1];

                // Selecionar o loteamento do PDF
                $('#opcoesLoteamentosIntegrado .opcao-loteamento').removeClass('selected');
                $(`#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"][value="${indexLoteamento}"]`).prop('checked', true);
                $(`#opcoesLoteamentosIntegrado input[name="loteamentoIntegrado"][value="${indexLoteamento}"]`).closest('.opcao-loteamento').addClass('selected');

                // Limpar quarteirão atual
                window.quarteiraoAtualDesenho = null;
                window.quarteiraoIdAtualDesenho = null;
                window.quarteiraoNumeroAtualDesenho = null;

                // Carregar o PDF no viewer
                if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.loadSpecificPDF) {
                    await window.pdfViewerIntegrado.loadSpecificPDF(loteamento, arquivo, quadricula);
                }

                // Abrir quarteirões se necessário
                abrirDivCadastro2Integrado(parseInt(indexLoteamento));
            });
        }

        // Função para abrir divCadastro2 integrado  
        function abrirDivCadastro2Integrado(indexLoteamento) {

            // Verificar se divCadastro2 está visível
            if ($('#divCadastro2').is(':visible')) {
                copiarDivCadastro2ParaIntegrado();
            } else {}
        }

        // Variáveis globais para o leitor de PDF integrado
        let pdfViewerIntegrado = null;
        let leitorPDFAtivo = false;

        // Variáveis globais para controle do quarteirão e quadra atual para desenho
        window.quarteiraoAtualDesenho = null;
        window.quarteiraoIdAtualDesenho = null;
        window.quarteiraoNumeroAtualDesenho = null;
        window.quadraAtualDesenho = null;

        // Inicializar input de lote com valor 1
        $(document).ready(function() {
            $('#inputLoteAtualIntegrado').val('1');

            // Permitir texto no input (não apenas números)
            $('#inputLoteAtualIntegrado').on('input', function() {
                const value = $(this).val();
                // Permitir qualquer texto, mas remover caracteres especiais perigosos
                const cleanValue = value.replace(/[<>]/g, '');
                if (value !== cleanValue) {
                    $(this).val(cleanValue);
                }
            });
        });

        // Função para abrir o leitor de PDF integrado
        function abrirLeitorPDF(loteamento, arquivo, quadricula) {

            // Preparar dados globais para o PDF viewer
            window.dadosLeitorPDF = {
                loteamento: loteamento,
                arquivo: arquivo, // Agora é um único arquivo
                quadricula: quadricula
            };

            // Exibir a div do leitor PDF
            $('#divLeitorPDF').show();

            // Auto-scroll para baixo para mostrar o leitor
            $('html, body').animate({
                scrollTop: $('#divLeitorPDF').offset().top
            }, 500);

            // Marcar como ativo
            leitorPDFAtivo = true;

            // CORREÇÃO: Definir flag para evitar carregamento automático
            window.carregandoPDFViaAbrirLeitorPDF = true;

            // Inicializar o PDF viewer PRIMEIRO (sempre criar nova instância para evitar problemas)
            console.log('Inicializando PDF viewer integrado...');
            pdfViewerIntegrado = new PDFViewerIntegrado();

            // Aguardar inicialização e depois mostrar controles integrados
            setTimeout(() => {
                popularControlesIntegrados(quadricula);

                // CORREÇÃO: Aguardar mais um pouco para garantir que a sincronização aconteça
                setTimeout(() => {
                    // Forçar sincronização com o PDF selecionado no modal original
                    const loteamentoOriginal = $('input[name="loteamento"]:checked');
                    if (loteamentoOriginal.length > 0) {
                        const indexLoteamento = loteamentoOriginal.val();
                        console.log('🔄 Forçando sincronização final:', {
                            indexLoteamento,
                            arquivo
                        });
                        sincronizarPDFComIntegrado(parseInt(indexLoteamento));
                    }

                    // CORREÇÃO: Carregar o PDF correto diretamente
                    setTimeout(() => {
                        if (window.pdfViewerIntegrado && window.pdfViewerIntegrado.loadSpecificPDF) {
                            console.log('📄 Carregando PDF final:', {
                                loteamento,
                                arquivo,
                                quadricula
                            });
                            window.pdfViewerIntegrado.loadSpecificPDF(loteamento, arquivo, quadricula);
                        }

                        // Resetar flag
                        window.carregandoPDFViaAbrirLeitorPDF = false;
                    }, 200);
                }, 500);
            }, 300);

            // Expor globalmente para acesso externo
            window.pdfViewerIntegrado = pdfViewerIntegrado;

            // Carregamento gerenciado pelo pdfViewerIntegrado.js
        }

        // Função removida: controles sempre visíveis agora

        // Função para fechar o leitor de PDF
        function fecharLeitorPDF() {
            //console.log'Fechando leitor PDF integrado');

            // Desativar modo de desenho antes de fechar
            if (pdfViewerIntegrado && pdfViewerIntegrado.deactivateDrawingMode) {
                pdfViewerIntegrado.deactivateDrawingMode();
            }

            // Limpar recursos do PDF viewer se necessário
            if (pdfViewerIntegrado && pdfViewerIntegrado.cleanup) {
                pdfViewerIntegrado.cleanup();
            }

            // Ocultar a div
            $('#divLeitorPDF').hide();

            // Esconder controles integrados
            $('#divCadastroIntegrado').hide();
            $('#divCadastro2Integrado').hide();

            // Controles permanecem sempre visíveis

            // Auto-scroll de volta para o mapa
            $('html, body').animate({
                scrollTop: $('#map').offset().top
            }, 500);

            // Marcar como inativo
            leitorPDFAtivo = false;

            // Limpar dados globais
            window.dadosLeitorPDF = null;
            window.quarteiraoAtualDesenho = null;
            window.quarteiraoIdAtualDesenho = null;
            window.quarteiraoNumeroAtualDesenho = null;

            // Resetar variável global para permitir nova inicialização
            pdfViewerIntegrado = null;
            window.pdfViewerIntegrado = null;
        }

        // Event listeners para o leitor PDF integrado
        $(document).ready(function() {
            // Botão fechar leitor PDF
            $('#btnFecharLeitorPDF').on('click', function() {
                fecharLeitorPDF();
            });
        });

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

            // Restaura cores originais de todas as unidades (mantendo grossuras originais)
            if (arrayCamadas["unidade"]) {
                arrayCamadas["unidade"].forEach(unidade => {
                    unidade.setOptions({
                        strokeColor: unidade.corOriginal || '#ff00ff',
                        fillColor: unidade.corOriginal || '#ff00ff',
                        fillOpacity: 0.30
                        // strokeWeight não é alterado - mantém o original
                    });
                    unidade.desativado = false;
                });
            }

            // Restaura cores originais de todas as piscinas (mantendo grossuras originais)
            if (arrayCamadas["piscina"]) {
                arrayCamadas["piscina"].forEach(piscina => {
                    piscina.setOptions({
                        strokeColor: piscina.corOriginal || '#00ffff',
                        fillColor: piscina.corOriginal || '#00ffff',
                        fillOpacity: 0.30
                    });
                    piscina.desativado = false;
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

            // Esconde o botão Marcador e inputs text
            $('#btnIncluirMarcador').addClass('d-none');
            $('#inputLoteAtual').hide();
            $('#inputQuadraAtual').hide();

            // Oculta todos os marcadores quando não há quarteirão selecionado
            // MAS respeita o estado do checkbox - se estiver marcado, mantém todos visíveis
            if (!$('#chkMarcadores').is(':checked')) {
                MapFramework.mostrarMarcadoresDoQuarteirao(null);
            }
        }

        // ============================================================================
        // FUNÇÕES DOS LOTES DA PREFEITURA MOVIDAS PARA O FRAMEWORK.JS
        // ============================================================================
        // As funções carregarLotesPrefeitura() e toggleLotesPrefeitura() foram 
        // movidas para o framework.js como MapFramework.carregarLotesGeojson() e 
        // MapFramework.toggleLotesGeojson() respectivamente.
        // 
        // Isso garante que os polígonos sejam criados corretamente na instância 
        // do Google Maps e sigam o padrão arquitetural do sistema.
        // ============================================================================

        // ============================================================================
        // FUNÇÕES DO CONTROLE DE DESENHOS DA PREFEITURA
        // ============================================================================

        // Variáveis para armazenar as coordenadas originais dos desenhos
        let coordenadasOriginaisDesenhos = [];
        let desenhosCarregados = false;
        
        // Variáveis para acumular o offset de movimentação
        let offsetAcumuladoLat = 0;
        let offsetAcumuladoLng = 0;

        // Função para obter a distância selecionada
        function obterDistancia() {
            const distanciaSelecionada = $('input[name="distancia"]:checked').val();
            return parseFloat(distanciaSelecionada);
        }

        // Função para converter metros para graus WGS84
        function metrosParaGraus(metros, latitude) {
            // Aproximação para conversão de metros para graus
            const grausLat = metros / 111320; // 1 grau de latitude ≈ 111.32 km
            const grausLng = metros / (111320 * Math.cos(latitude * Math.PI / 180));
            return {
                lat: grausLat,
                lng: grausLng
            };
        }

        // Função para mover desenhos em uma direção específica
        function moverDesenhosPrefeitura(direcao) {
            const distancia = obterDistancia();
            const camadaLotes = "lotesPref";
            const destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';

            if (!arrayCamadas[destinoLotes] || arrayCamadas[destinoLotes].length === 0) {
                alert('Nenhum desenho carregado para mover.');
                return;
            }

            // Salva coordenadas originais na primeira movimentação
            if (!desenhosCarregados) {
                salvarCoordenadasOriginais();
                desenhosCarregados = true;
            }

            // Define os offsets baseados na direção (em metros)
            let offsetLatMetros = 0,
                offsetLngMetros = 0;

            switch (direcao) {
                case 'norte':
                    offsetLatMetros = distancia;
                    break;
                case 'sul':
                    offsetLatMetros = -distancia;
                    break;
                case 'leste':
                    offsetLngMetros = distancia;
                    break;
                case 'oeste':
                    offsetLngMetros = -distancia;
                    break;
                case 'nordeste':
                    offsetLatMetros = distancia;
                    offsetLngMetros = distancia;
                    break;
                case 'noroeste':
                    offsetLatMetros = distancia;
                    offsetLngMetros = -distancia;
                    break;
                case 'sudeste':
                    offsetLatMetros = -distancia;
                    offsetLngMetros = distancia;
                    break;
                case 'sudoeste':
                    offsetLatMetros = -distancia;
                    offsetLngMetros = -distancia;
                    break;
            }

            // Acumula os offsets
            offsetAcumuladoLat += offsetLatMetros;
            offsetAcumuladoLng += offsetLngMetros;
            
            console.log(`Offset acumulado: Lat=${offsetAcumuladoLat}m, Lng=${offsetAcumuladoLng}m`);

            // Move cada polígono
            arrayCamadas[destinoLotes].forEach(function(polygon) {
                if (polygon.getPath && polygon.setPath) {
                    const path = polygon.getPath();
                    const newPath = [];

                    for (let i = 0; i < path.getLength(); i++) {
                        const point = path.getAt(i);
                        const grausOffset = metrosParaGraus(1, point.lat());

                        const newPoint = new google.maps.LatLng(
                            point.lat() + (offsetLatMetros * grausOffset.lat),
                            point.lng() + (offsetLngMetros * grausOffset.lng)
                        );
                        newPath.push(newPoint);
                    }

                    polygon.setPath(newPath);
                }
            });
        }

        // Função para salvar coordenadas originais
        function salvarCoordenadasOriginais() {
            const camadaLotes = "lotesPref";
            const destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';

            if (arrayCamadas[destinoLotes] && arrayCamadas[destinoLotes].length > 0) {
                coordenadasOriginaisDesenhos = [];

                arrayCamadas[destinoLotes].forEach(function(polygon, index) {
                    if (polygon.getPath) {
                        const path = polygon.getPath();
                        const coordinates = [];

                        for (let i = 0; i < path.getLength(); i++) {
                            const point = path.getAt(i);
                            coordinates.push({
                                lat: point.lat(),
                                lng: point.lng()
                            });
                        }

                        coordenadasOriginaisDesenhos[index] = coordinates;
                    }
                });
            }
        }

        // Função para resetar desenhos para coordenadas originais e DELETAR o arquivo _offset.json
        function resetarDesenhosPrefeitura() {
            const camadaLotes = "lotesPref";
            const destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';

            if (!coordenadasOriginaisDesenhos || coordenadasOriginaisDesenhos.length === 0) {
                alert('Nenhuma coordenada original salva para resetar.');
                return;
            }

            // Obter quadrícula atual
            const quadricula = dadosOrto && dadosOrto[0] && dadosOrto[0]['quadricula'] ? dadosOrto[0]['quadricula'] : null;

            if (!quadricula) {
                alert('Erro: Quadrícula não identificada.');
                return;
            }

            // Deleta o arquivo _offset.json
            $.ajax({
                url: 'deletar_offset_desenhos.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ quadricula: quadricula }),
                success: function(response) {
                    if (response.success) {
                        console.log('Arquivo _offset.json deletado:', response);
                        
                        // Volta os desenhos para posição original
                        if (arrayCamadas[destinoLotes] && arrayCamadas[destinoLotes].length > 0) {
                            arrayCamadas[destinoLotes].forEach(function(polygon, index) {
                                if (polygon.setPath && coordenadasOriginaisDesenhos[index]) {
                                    const originalCoords = coordenadasOriginaisDesenhos[index];
                                    const newPath = originalCoords.map(coord =>
                                        new google.maps.LatLng(coord.lat, coord.lng)
                                    );
                                    polygon.setPath(newPath);
                                }
                            });
                            
                            // Zera os offsets acumulados
                            offsetAcumuladoLat = 0;
                            offsetAcumuladoLng = 0;
                            
                            alert('Reset concluído!\n\nArquivo de offset deletado.\nDesenhos voltaram para posição original.');
                        }
                    } else {
                        alert('Aviso: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao deletar offset:', error);
                    alert('Erro ao deletar arquivo de offset.');
                }
            });
        }

        // Função para salvar offset de movimentação
        function salvarDesenhosPrefeitura() {
            // Obter quadrícula atual
            const quadricula = dadosOrto && dadosOrto[0] && dadosOrto[0]['quadricula'] ? dadosOrto[0]['quadricula'] : null;

            if (!quadricula) {
                alert('Erro: Quadrícula não identificada.');
                return;
            }

            // Preparar dados para envio (apenas o offset!)
            const dadosParaSalvar = {
                quadricula: quadricula,
                offset_lat: offsetAcumuladoLat,  // em metros
                offset_lng: offsetAcumuladoLng   // em metros
            };

            console.log('Salvando offset:', dadosParaSalvar);

            // Enviar dados via AJAX
            $.ajax({
                url: 'salvar_offset_desenhos.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(dadosParaSalvar),
                success: function(response) {
                    if (response.success) {
                        alert(`Offset salvo com sucesso!\n\nLat: ${offsetAcumuladoLat}m\nLng: ${offsetAcumuladoLng}m`);
                        console.log('Offset salvo:', response);
                    } else {
                        alert('Erro ao salvar: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro na requisição:', error);
                    alert('Erro ao salvar offset. Tente novamente.');
                }
            });
        }

        // Função para rotacionar desenhos (individual ou coletiva)
        function rotacionarDesenhosPrefeitura(tipoRotacao) {
            const distancia = obterDistancia();
            const camadaLotes = "lotesPref";
            const destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';

            if (!arrayCamadas[destinoLotes] || arrayCamadas[destinoLotes].length === 0) {
                alert('Nenhum desenho carregado para rotacionar.');
                return;
            }

            // Salva coordenadas originais na primeira movimentação
            if (!desenhosCarregados) {
                salvarCoordenadasOriginais();
                desenhosCarregados = true;
            }

            // Converter distância em metros para ângulo em graus
            // Usando uma conversão mais intuitiva: 1 metro ≈ 0.01 graus de rotação
            const anguloGraus = distancia * 0.01;
            const anguloRad = anguloGraus * Math.PI / 180;

            // Definir direção da rotação
            const fatorRotacao = (tipoRotacao.includes('esquerda')) ? 1 : -1;
            const anguloFinal = fatorRotacao * anguloRad;

            if (tipoRotacao.includes('individual')) {
                // ROTAÇÃO INDIVIDUAL: Cada desenho rotaciona em torno do seu próprio centro
                rotacionarDesenhosIndividual(arrayCamadas[destinoLotes], anguloFinal);
            } else if (tipoRotacao.includes('coletiva')) {
                // ROTAÇÃO COLETIVA: Todos os desenhos rotacionam em torno de um centro comum
                rotacionarDesenhosColetiva(arrayCamadas[destinoLotes], anguloFinal);
            }
        }

        // Função para rotação individual (cada desenho em torno do seu centro)
        function rotacionarDesenhosIndividual(polygons, anguloRad) {
            polygons.forEach(function(polygon) {
                if (polygon.getPath && polygon.setPath) {
                    const path = polygon.getPath();

                    // Calcular o centro do polígono individual
                    let centroLat = 0,
                        centroLng = 0;
                    for (let i = 0; i < path.getLength(); i++) {
                        const point = path.getAt(i);
                        centroLat += point.lat();
                        centroLng += point.lng();
                    }
                    centroLat /= path.getLength();
                    centroLng /= path.getLength();

                    // Aplicar rotação em torno do centro individual
                    const newPath = [];
                    for (let i = 0; i < path.getLength(); i++) {
                        const point = path.getAt(i);
                        const lat = point.lat() - centroLat;
                        const lng = point.lng() - centroLng;

                        // Aplicar matriz de rotação
                        const newLat = lat * Math.cos(anguloRad) - lng * Math.sin(anguloRad);
                        const newLng = lat * Math.sin(anguloRad) + lng * Math.cos(anguloRad);

                        const rotatedPoint = new google.maps.LatLng(
                            newLat + centroLat,
                            newLng + centroLng
                        );
                        newPath.push(rotatedPoint);
                    }

                    polygon.setPath(newPath);
                }
            });
        }

        // Função para rotação coletiva (todos os desenhos em torno de um centro comum)
        function rotacionarDesenhosColetiva(polygons, anguloRad) {
            // Calcular o centro comum de todos os desenhos
            let centroLatTotal = 0,
                centroLngTotal = 0;
            let totalPontos = 0;

            polygons.forEach(function(polygon) {
                if (polygon.getPath) {
                    const path = polygon.getPath();
                    for (let i = 0; i < path.getLength(); i++) {
                        const point = path.getAt(i);
                        centroLatTotal += point.lat();
                        centroLngTotal += point.lng();
                        totalPontos++;
                    }
                }
            });

            const centroLat = centroLatTotal / totalPontos;
            const centroLng = centroLngTotal / totalPontos;

            // Rotacionar cada polígono em torno do centro comum
            polygons.forEach(function(polygon) {
                if (polygon.getPath && polygon.setPath) {
                    const path = polygon.getPath();
                    const newPath = [];

                    for (let i = 0; i < path.getLength(); i++) {
                        const point = path.getAt(i);
                        const lat = point.lat() - centroLat;
                        const lng = point.lng() - centroLng;

                        // Aplicar matriz de rotação em torno do centro comum
                        const newLat = lat * Math.cos(anguloRad) - lng * Math.sin(anguloRad);
                        const newLng = lat * Math.sin(anguloRad) + lng * Math.cos(anguloRad);

                        const rotatedPoint = new google.maps.LatLng(
                            newLat + centroLat,
                            newLng + centroLng
                        );
                        newPath.push(rotatedPoint);
                    }

                    polygon.setPath(newPath);
                }
            });
        }

        // Função para cancelar controle e ocultar
        // Função para cancelar e sair do modo controle (sem mensagens)
        function cancelarControleDesenhos() {
            // Ocultar controle
            $('#controleDesenhosPrefeitura').removeClass('show');

            // Desmarcar checkbox
            $('#new_checkLotes').prop('checked', false);

            // Ocultar desenhos
            if (MapFramework && MapFramework.toggleLotesGeojson) {
                MapFramework.toggleLotesGeojson(false);
            }
        }

        // Função para carregar offset salvo (se existir)
        function carregarOffsetSalvo() {
            const quadricula = dadosOrto && dadosOrto[0] && dadosOrto[0]['quadricula'] ? dadosOrto[0]['quadricula'] : null;
            
            if (!quadricula) return;
            
            $.ajax({
                url: `cartografia_prefeitura/${quadricula}_offset.json`,
                type: 'GET',
                cache: false,
                dataType: 'json',
                success: function(offsetData) {
                    // Carrega os offsets salvos para as variáveis
                    offsetAcumuladoLat = offsetData.offset_lat_metros || 0;
                    offsetAcumuladoLng = offsetData.offset_lng_metros || 0;
                    console.log(`Offset carregado: Lat=${offsetAcumuladoLat}m, Lng=${offsetAcumuladoLng}m`);
                },
                error: function() {
                    // Sem offset salvo, inicia zerado
                    offsetAcumuladoLat = 0;
                    offsetAcumuladoLng = 0;
                }
            });
        }

        // ============================================================================
        // EVENT LISTENER PARA CHECKBOX DOS LOTES DA PREFEITURA
        // ============================================================================
        // Conecta o checkbox com as funções do MapFramework para carregar/mostrar lotes
        // ============================================================================
        $(document).ready(function() {
            $('#new_checkLotes').change(function() {
                const isChecked = $(this).is(':checked');

                // Controla a camada poligono_lote
                if (MapFramework && MapFramework.alternarVisibilidadeCamada) {
                    MapFramework.alternarVisibilidadeCamada('poligono_lote', isChecked);
                }

                /*
                // Mostra/oculta o controle de desenhos da prefeitura
                const controle = $('#controleDesenhosPrefeitura');
                if (isChecked) {
                    // Carrega o offset salvo (se existir)
                    carregarOffsetSalvo();
                    controle.addClass('show');
                } else {
                    controle.removeClass('show');
                }
                */
            });
        });
    </script>
</body>

</html>