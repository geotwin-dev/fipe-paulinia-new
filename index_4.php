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
            max-height: 600px;
            overflow-y: auto;
        }

        /* Estilos para accordion das camadas dinâmicas */
        .btn-accordion-toggle {
            transition: all 0.3s ease;
        }

        .btn-accordion-toggle:hover {
            color: #333 !important;
            transform: scale(1.1);
        }

        .btn-accordion-toggle i {
            transition: transform 0.3s ease;
        }

        /* Estilos para separar visualmente as camadas dinâmicas */
        #tituloCamadasDinamicas {
            background-color: #f8f9fa;
            margin-top: 5px;
        }

        /* Estilos para o slider de opacidade das camadas dinâmicas */
        #sliderOpacidadeCamadasDinamicas {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
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
                                        Imóveis
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
                                        Streetview videos
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkStreetviewFotos">
                                    <label class="form-check-label" for="chkStreetviewFotos">
                                        Streetview fotos
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
                            <li id="tituloCamadasDinamicas" style="display: none;">
                                <div style="padding: 8px 16px; font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                                    Camadas dos KML
                                </div>
                            </li>
                            <li id="sliderOpacidadeCamadasDinamicas" style="display: none;">
                                <div style="padding: 8px 16px;">
                                    <label for="rangeOpacidadeCamadas" class="form-label" style="font-size: 12px; font-weight: 500; color: #495057; margin-bottom: 8px; display: block;">
                                        Opacidade: <span id="valorOpacidadeCamadas">0.5</span>
                                    </label>
                                    <input type="range" class="form-range" id="rangeOpacidadeCamadas" 
                                           min="0" max="2" step="0.1" value="0.5">
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Botão Camadas Novas (Dropdown) -->
                    <div class="btn-group">
                        <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            Camadas Novas
                        </button>
                        <ul id="dropCamadasNovas" class="dropdown-menu p-2">
                            <li>
                                <a style="color: blue" class="dropdown-item" href="#" id="btnAdicionarCamada" onclick="event.preventDefault(); abrirModalAdicionarCamada();">
                                    + Adicionar Camada
                                </a>
                            </li>
                            <li>
                                <a style="color: blue" class="dropdown-item" href="#" id="btnAdicionarSubcamada" onclick="event.preventDefault(); abrirModalAdicionarSubcamada();">
                                    + Adicionar Subcamada
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li id="listaCamadasNovas">
                                <!-- Camadas serão carregadas aqui dinamicamente -->
                            </li>
                        </ul>
                    </div>

                    

                </div>

            </div>
        </nav>

        <div id="map"></div>
    </div>

    <!-- Modal Adicionar Camada -->
    <div class="modal fade" id="modalAdicionarCamada" tabindex="-1" aria-labelledby="modalAdicionarCamadaLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAdicionarCamadaLabel">Adicionar Nova Camada</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="inputNomeCamada" class="form-label">Nome da Camada</label>
                        <input type="text" class="form-control" id="inputNomeCamada" placeholder="Digite o nome da camada">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnSalvarCamada">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Adicionar Subcamada -->
    <div class="modal fade" id="modalAdicionarSubcamada" tabindex="-1" aria-labelledby="modalAdicionarSubcamadaLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAdicionarSubcamadaLabel">Adicionar Nova Subcamada</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="inputNomeSubcamada" class="form-label">Nome da Subcamada</label>
                        <input type="text" class="form-control" id="inputNomeSubcamada" placeholder="Digite o nome da subcamada">
                    </div>
                    <div class="mb-3">
                        <label for="selectCamadaPai" class="form-label">Camada Pai</label>
                        <select class="form-select" id="selectCamadaPai">
                            <option value="">Selecione uma camada</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnSalvarSubcamada">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar/Deletar Camada -->
    <div class="modal fade" id="modalEditarCamada" tabindex="-1" aria-labelledby="modalEditarCamadaLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarCamadaLabel">Editar/Excluir Camada</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="inputIdEditar">
                    <input type="hidden" id="inputTipoEditar">
                    <div class="mb-3">
                        <label for="inputNomeEditar" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="inputNomeEditar" placeholder="Digite o nome">
                    </div>
                    <div class="mb-3" id="divSelectCamadaPaiEditar" style="display: none;">
                        <label for="selectCamadaPaiEditar" class="form-label">Camada Pai</label>
                        <select class="form-select" id="selectCamadaPaiEditar">
                            <option value="">Selecione uma camada</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="btnDeletarCamada">Deletar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnSalvarEdicao">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const paginaAtual = 'index_4';

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
            quarteirao: [],
            streetview: [],
            streetview_fotos: [],
            imagens_aereas: [],
            lotesPref: [],
            condominios_verticais: [],
            condominios_horizontais: [],
            areas_publicas: [],
            semCamadas: []
        };

        function adicionarObjetoNaCamada(nome, objeto) {
            try {
                const chave = (nome || 'semCamadas').toLowerCase();
                if (!arrayCamadas[chave]) {
                    arrayCamadas[chave] = [];
                }
                arrayCamadas[chave].push(objeto);
            } catch (e) {
                console.error('Erro ao adicionar objeto na camada:', e);
            }
        }

        $(document).ready(async function() {
            // Coordenadas padrão de Paulínia
            let coordsInitial = {
                lat: -22.7594,
                lng: -47.1532
            };

            // Se tiver quadrícula na URL, usa as coordenadas dela
            if (dadosOrto && dadosOrto.length > 0) {
                coordsInitial = {
                    lat: JSON.parse(dadosOrto[0]['latitude']),
                    lng: JSON.parse(dadosOrto[0]['longitude'])
                };
            }

            // Inicializa o mapa
            await MapFramework.iniciarMapa('map', coordsInitial, 16);

            // Carrega a ortofoto se houver quadrícula
            if (dadosOrto && dadosOrto.length > 0) {
                await MapFramework.inserirOrtofoto2(dadosOrto[0]["quadricula"]);
            }

            // Carrega as quadrículas
            MapFramework.carregarQuadriculasKML();

            // Carrega o limite do município
            MapFramework.carregarLimiteKML();

            // Carrega os desenhos salvos (quadras, piscinas, lotes) se houver quadrícula
            if (dadosOrto && dadosOrto.length > 0) {
                await MapFramework.carregarDesenhosSalvos('paulinia', dadosOrto[0]['quadricula']);
                
                // Garante que os z-index estejam corretos após carregamento
                MapFramework.aplicarZIndexCorreto();

                // Carrega os marcadores
                await MapFramework.carregarMarcadoresSalvos(dadosOrto[0]['quadricula']);

                // Garante que os z-index estejam corretos após todos os carregamentos
                MapFramework.aplicarZIndexCorreto();

                // Carrega os desenhos da prefeitura
                await MapFramework.carregarDesenhosPrefeitura(dadosOrto[0]['quadricula']);

                // Carrega os quarteirões
                MapFramework.carregaQuarteiroes(dadosOrto[0]['quadricula']);

                // Carrega as imagens aéreas
                await MapFramework.carregarImagensAereas(dadosOrto[0]['quadricula']);

                // Carrega os trajetos Streetview
                MapFramework.carregarStreets(dadosOrto[0]['quadricula']);

                // Carrega as fotos do Streetview
                MapFramework.carregarStreetviewFotos(dadosOrto[0]['quadricula']);

                // Carrega camadas dinâmicas adicionais de KML
                MapFramework.carregarMaisCamadas();
            }

            // Carrega as camadas novas do banco de dados
            carregarCamadasNovas();

            // Event listener para o botão de tipo de mapa
            $('#btnTipoMapa').on('click', function() {
                MapFramework.alternarTipoMapa();
            });

            // Previne que o dropdown feche ao clicar dentro dele
            $('#dropCamadas').on('click', function(e) {
                e.stopPropagation();
            });

            // Event listeners para os checkboxes
            // Checkbox da Ortofoto
            $('#chkOrtofoto').on('change', function() {
                if (!dadosOrto || dadosOrto.length === 0) {
                    alert('Erro: Dados da ortofoto não estão disponíveis.');
                    return;
                }

                const visivel = $(this).is(':checked');
                if (visivel) {
                    MapFramework.inserirOrtofoto2(dadosOrto[0]["quadricula"]);
                } else {
                    MapFramework.limparOrtofoto();
                }
            });

            // Checkbox das Quadrículas
            $('#chkQuadriculas').on('change', function() {
                const visivel = $(this).is(':checked');
                MapFramework.alternarVisibilidadeCamada('quadriculas', visivel);
            });

            // Checkbox do Limite do Município
            $('#chkLimite').on('change', function() {
                const visivel = $(this).is(':checked');
                MapFramework.alternarVisibilidadeCamada('limite', visivel);
            });

            // Checkbox das Quadras
            $('#chkQuadras').on('change', function() {
                const visivel = $(this).is(':checked');
                MapFramework.alternarVisibilidadeCamada('quadra', visivel);
            });

            // Checkbox das Piscinas
            $('#chkPiscinas').on('change', function() {
                const visivel = $(this).is(':checked');
                MapFramework.alternarVisibilidadeCamada('piscina', visivel);
            });

            // Checkbox dos Lotes (linhas)
            $('#chkLotes').on('change', function() {
                const visivel = $(this).is(':checked');
                MapFramework.alternarVisibilidadeCamada('lote', visivel);
            });

            // Checkbox dos Marcadores
            $('#chkMarcadores').on('change', function() {
                const visivel = $(this).is(':checked');
                if (visivel) {
                    // Checkbox marcado = mostra TODOS os marcadores do mapa
                    MapFramework.alternarVisibilidadeTodosMarcadores(true);
                } else {
                    // Checkbox desmarcado = oculta todos os marcadores
                    MapFramework.alternarVisibilidadeTodosMarcadores(false);
                }
            });

            // Checkbox das Edificações (Unidades)
            $('#chkUnidades').on('change', function() {
                const visivel = $(this).is(':checked');
                MapFramework.alternarVisibilidadeCamada('unidade', visivel);
            });

            // Checkbox dos Lotes Ortofoto (Polígono Lote)
            $('#chkPoligono_lote').on('change', function() {
                const visivel = $(this).is(':checked');
                MapFramework.alternarVisibilidadeCamada('poligono_lote', visivel);
            });

            // Checkbox dos Lotes Prefeitura
            $('#new_checkLotes').on('change', function() {
                const visivel = $(this).is(':checked');
                if (MapFramework && MapFramework.toggleLotesGeojson) {
                    MapFramework.toggleLotesGeojson(visivel);
                }
            });

            // Checkbox dos Condomínios Verticais
            $('#chkCondominiosVerticais').on('change', function() {
                const visivel = $(this).is(':checked');
                if (visivel && (!arrayCamadas.condominios_verticais || arrayCamadas.condominios_verticais.length === 0)) {
                    MapFramework.carregarCondominiosVerticaisKML();
                } else {
                    MapFramework.alternarVisibilidadeCamada('condominios_verticais', visivel);
                }
            });

            // Checkbox dos Condomínios Horizontais
            $('#chkCondominiosHorizontais').on('change', function() {
                const visivel = $(this).is(':checked');
                if (visivel && (!arrayCamadas.condominios_horizontais || arrayCamadas.condominios_horizontais.length === 0)) {
                    MapFramework.carregarCondominiosHorizontaisKML();
                } else {
                    MapFramework.alternarVisibilidadeCamada('condominios_horizontais', visivel);
                }
            });

            // Checkbox das Áreas Públicas
            $('#chkAreasPublicas').on('change', function() {
                const visivel = $(this).is(':checked');
                if (visivel && (!arrayCamadas.areas_publicas || arrayCamadas.areas_publicas.length === 0)) {
                    MapFramework.carregarAreasPublicasKML();
                } else {
                    MapFramework.alternarVisibilidadeCamada('areas_publicas', visivel);
                }
            });

            // Checkbox da Cartografia Prefeitura
            $('#chkPrefeitura').on('change', function() {
                const visivel = $(this).is(':checked');
                MapFramework.alternarVisibilidadeCamada('prefeitura', visivel);
            });

            // Checkbox dos Quarteirões
            $('#chkQuarteiroes').on('change', function() {
                const visivel = $(this).is(':checked');
                MapFramework.alternarVisibilidadeCamada('quarteirao', visivel);
            });

            // Checkbox das Imagens Aéreas
            $('#chkImagensAereas').on('change', function() {
                const visivel = $(this).is(':checked');
                MapFramework.alternarVisibilidadeCamada('imagens_aereas', visivel);
            });

            // Checkbox do Streetview (trajetos/vídeos)
            $('#chkStreetview').on('change', function() {
                const visivel = $(this).is(':checked');
                MapFramework.alternarVisibilidadeCamada('streetview', visivel);
            });

            // Checkbox do Streetview Fotos
            $('#chkStreetviewFotos').on('change', function() {
                const visivel = $(this).is(':checked');
                MapFramework.alternarVisibilidadeCamada('streetview_fotos', visivel);
            });

            // Checkbox dos Loteamentos
            $('#chkModoCadastro').on('change', function() {
                const visivel = $(this).is(':checked');
                
                if (visivel) {
                    // Se está marcando e ainda não carregou, carrega os loteamentos
                    if (!dadosOrto || dadosOrto.length === 0) {
                        alert('Erro: Dados da ortofoto não estão disponíveis.');
                        $(this).prop('checked', false);
                        return;
                    }
                    
                    // Se já carregou, apenas mostra
                    if (window.loteamentosLayer && window.loteamentosLayer.length > 0) {
                        alternarVisibilidadeLoteamentos(true);
                    } else {
                        // Carrega pela primeira vez
                        carregarLoteamentosQuadricula(dadosOrto[0]['quadricula']);
                    }
                } else {
                    // Se está desmarcando, oculta
                    alternarVisibilidadeLoteamentos(false);
                }
            });
        });

        // Função para alternar visibilidade dos loteamentos
        function alternarVisibilidadeLoteamentos(visivel) {
            if (window.loteamentosLayer) {
                window.loteamentosLayer.forEach(polygon => {
                    if (polygon && polygon.setMap) {
                        polygon.setMap(visivel ? MapFramework.map : null);
                    }
                });
            }
            if (window.loteamentosLabels) {
                window.loteamentosLabels.forEach(marker => {
                    if (marker && marker.setMap) {
                        marker.setMap(visivel ? MapFramework.map : null);
                    }
                });
            }
        }

        // Função para carregar loteamentos de uma quadrícula específica (sem modal)
        async function carregarLoteamentosQuadricula(quadricula) {
            try {
                if (!quadricula || quadricula.trim() === '') {
                    throw new Error('Quadrícula inválida');
                }

                // Carrega o JSON da quadrícula
                const response = await fetch(`loteamentos_quadriculas/json/resultados_quadricula_${quadricula}.json`, {
                    cache: "no-store"
                });
                
                if (!response.ok) {
                    if (response.status === 404) {
                        console.warn(`Arquivo de loteamentos não encontrado para a quadrícula ${quadricula}`);
                        return;
                    } else {
                        throw new Error(`Erro HTTP: ${response.status} - ${response.statusText}`);
                    }
                }

                const dados = await response.json();

                if (!dados || typeof dados !== 'object') {
                    throw new Error('Formato de dados inválido');
                }

                // Verifica se há loteamentos
                if (dados.resultados && dados.resultados.loteamentos && dados.resultados.loteamentos.length > 0) {
                    // Adiciona os desenhos no mapa
                    adicionarDesenhosNoMapa(dados.resultados.loteamentos, quadricula);
                }

            } catch (error) {
                console.error('Erro ao carregar loteamentos:', error);
            }
        }

        // Função para adicionar desenhos dos loteamentos no mapa
        function adicionarDesenhosNoMapa(loteamentos, quadricula) {
            // Limpa desenhos anteriores se existirem
            if (window.loteamentosLayer) {
                window.loteamentosLayer.forEach(polygon => {
                    polygon.setMap(null);
                });
            }
            if (window.loteamentosLabels) {
                window.loteamentosLabels.forEach(marker => {
                    if (marker) {
                        marker.setMap(null);
                    }
                });
            }

            // Cria uma nova camada para os loteamentos
            window.loteamentosLayer = [];
            window.loteamentosLabels = [];

            loteamentos.forEach((loteamento, index) => {
                if (loteamento.coordenadas && loteamento.coordenadas.length > 0) {
                    // Processar apenas o primeiro conjunto de coordenadas (índice 0)
                    const primeiraCoordenada = loteamento.coordenadas[0];

                    if (primeiraCoordenada.type === 'Polygon' && primeiraCoordenada.coordinates) {
                        try {
                            // Converte as coordenadas para o formato do Google Maps
                            const path = primeiraCoordenada.coordinates[0].map(coord => {
                                return {
                                    lat: coord[1],
                                    lng: coord[0]
                                };
                            });

                            // Verificar se temos coordenadas suficientes
                            if (path.length < 3) {
                                console.error(`❌ Polígono ${loteamento.nome} tem apenas ${path.length} pontos - insuficiente para formar polígono`);
                                return;
                            }

                            // Cria o polígono
                            const polygon = new google.maps.Polygon({
                                paths: path,
                                strokeColor: '#B9E2FF',
                                strokeOpacity: 0.8,
                                strokeWeight: 7,
                                fillColor: '#B9E2FF',
                                fillOpacity: 0.2,
                                clickable: false,
                                map: MapFramework.map,
                                zIndex: 4
                            });

                            // Adiciona à camada
                            window.loteamentosLayer.push(polygon);

                            const centroid = calcularCentroidePoligono(path);
                            if (centroid) {
                                const labelElement = criarElementoRotuloLoteamento(loteamento.nome);
                                const labelMarker = new google.maps.marker.AdvancedMarkerElement({
                                    position: centroid,
                                    content: labelElement,
                                    gmpClickable: false,
                                    map: MapFramework.map,
                                    zIndex: 60
                                });
                                // Garante que o marker seja exibido
                                if (labelMarker && labelMarker.setMap) {
                                    labelMarker.setMap(MapFramework.map);
                                }
                                window.loteamentosLabels.push(labelMarker);
                                console.log(`Rótulo criado para ${loteamento.nome} em:`, centroid);
                            } else {
                                console.warn(`Não foi possível calcular centroide para ${loteamento.nome}`);
                            }

                        } catch (error) {
                            console.error(`Erro ao criar polígono para ${loteamento.nome}:`, error);
                        }

                    } else if (primeiraCoordenada.type === 'MultiPolygon' && primeiraCoordenada.coordinates) {
                        try {
                            // Processar TODOS os polígonos do MultiPolygon como UM ÚNICO loteamento
                            const polygonosDoLoteamento = [];

                            primeiraCoordenada.coordinates.forEach((polygonCoords, polygonIndex) => {
                                // Converte as coordenadas para o formato do Google Maps
                                const path = polygonCoords[0].map(coord => {
                                    return {
                                        lat: coord[1],
                                        lng: coord[0]
                                    };
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
                                    map: MapFramework.map,
                                    zIndex: 4
                                });

                                // Adiciona à camada
                                window.loteamentosLayer.push(polygon);

                                // Armazena o polígono para referência futura
                                polygonosDoLoteamento.push(polygon);
                            });

                            // Armazena a referência dos polígonos deste loteamento
                            if (!window.loteamentosPolygons) {
                                window.loteamentosPolygons = {};
                            }
                            window.loteamentosPolygons[loteamento.nome] = polygonosDoLoteamento;

                            const centroid = calcularCentroideMultiPoligono(primeiraCoordenada.coordinates);
                            if (centroid) {
                                const labelElement = criarElementoRotuloLoteamento(loteamento.nome);
                                const labelMarker = new google.maps.marker.AdvancedMarkerElement({
                                    position: centroid,
                                    content: labelElement,
                                    gmpClickable: false,
                                    map: MapFramework.map,
                                    zIndex: 60
                                });
                                // Garante que o marker seja exibido
                                if (labelMarker && labelMarker.setMap) {
                                    labelMarker.setMap(MapFramework.map);
                                }
                                window.loteamentosLabels.push(labelMarker);
                                console.log(`Rótulo criado para ${loteamento.nome} (MultiPolygon) em:`, centroid);
                            } else {
                                console.warn(`Não foi possível calcular centroide para ${loteamento.nome} (MultiPolygon)`);
                            }

                        } catch (error) {
                            console.error(`Erro ao criar MultiPolygon para ${loteamento.nome}:`, error);
                        }
                    }
                }
            });
        }

        // Função para criar elemento de rótulo do loteamento
        function criarElementoRotuloLoteamento(nomeLoteamento) {
            const el = document.createElement('div');
            el.className = 'rotulo-loteamento';
            el.style.background = 'rgba(185, 226, 255, 0.8)';
            el.style.color = '#027cff';
            el.style.padding = '4px 10px';
            el.style.borderRadius = '8px';
            el.style.fontSize = '13px';
            el.style.fontWeight = '600';
            el.style.whiteSpace = 'nowrap';
            el.style.pointerEvents = 'none';
            el.style.boxShadow = '0 2px 6px rgba(0,0,0,0.35)';
            el.textContent = nomeLoteamento;
            return el;
        }

        // Função para calcular centroide de um polígono
        function calcularCentroidePoligono(path) {
            if (!path || path.length === 0) {
                return null;
            }

            const bounds = new google.maps.LatLngBounds();
            path.forEach(coord => {
                bounds.extend(new google.maps.LatLng(coord.lat, coord.lng));
            });

            const isEmpty = (typeof bounds.isEmpty === 'function') ? bounds.isEmpty() : false;
            if (isEmpty) {
                return null;
            }

            const centro = bounds.getCenter();
            return centro ? { lat: centro.lat(), lng: centro.lng() } : null;
        }

        // Função para calcular centroide de um MultiPolygon
        function calcularCentroideMultiPoligono(multiPolygonCoordinates) {
            if (!multiPolygonCoordinates || multiPolygonCoordinates.length === 0) {
                return null;
            }

            const bounds = new google.maps.LatLngBounds();

            multiPolygonCoordinates.forEach(polygonCoords => {
                if (!Array.isArray(polygonCoords) || polygonCoords.length === 0) return;

                const anelPrincipal = polygonCoords[0] || [];
                anelPrincipal.forEach(coord => {
                    if (Array.isArray(coord) && coord.length >= 2) {
                        bounds.extend(new google.maps.LatLng(coord[1], coord[0]));
                    }
                });
            });

            const isEmpty = (typeof bounds.isEmpty === 'function') ? bounds.isEmpty() : false;
            if (isEmpty) {
                return null;
            }

            const centro = bounds.getCenter();
            return centro ? { lat: centro.lat(), lng: centro.lng() } : null;
        }

        // ========== FUNÇÕES PARA GERENCIAR CAMADAS NOVAS ==========
        
        // Carrega as camadas do banco de dados
        function carregarCamadasNovas() {
            $.ajax({
                url: 'listarCamadasNovas.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        renderizarCamadasNovas(response.camadas);
                    } else {
                        console.error('Erro ao carregar camadas:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao carregar camadas:', error);
                }
            });
        }

        // Renderiza as camadas no dropdown
        function renderizarCamadasNovas(camadas) {
            const lista = $('#listaCamadasNovas');
            lista.empty();
            
            if (camadas.length === 0) {
                lista.html('<li><span class="dropdown-item-text text-muted">Nenhuma camada cadastrada</span></li>');
                return;
            }
            
            camadas.forEach(function(camada) {
                const itemCamada = $('<li style="border-bottom: 1px solid #e9ecef;"></li>');
                const divCamada = $(`
                    <div class="d-flex align-items-center justify-content-between" style="padding: 6px 16px;">
                        <div class="form-check m-0">
                            <input class="form-check-input chk-camada-nova" type="checkbox" 
                                   id="chkCamadaNova_${camada.id}" 
                                   data-id="${camada.id}" 
                                   data-tipo="camada">
                            <label class="form-check-label" for="chkCamadaNova_${camada.id}" style="font-weight: 500; cursor: pointer;">
                                ${camada.nome}
                            </label>
                        </div>
                        <button class="btn btn-sm btn-link p-0 btn-editar-camada" data-id="${camada.id}" data-tipo="camada" style="color: #6c757d;">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                `);
                // Armazena o nome no elemento jQuery para uso posterior
                divCamada.find('.btn-editar-camada').data('nome', camada.nome);
                itemCamada.append(divCamada);
                lista.append(itemCamada);
                
                // Adiciona subcamadas se houver
                if (camada.subcamadas && camada.subcamadas.length > 0) {
                    const submenu = $('<ul class="ms-3 mt-1 mb-2" style="list-style: none;"></ul>');
                    camada.subcamadas.forEach(function(subcamada) {
                        const itemSubcamada = $(`
                            <li class="d-flex align-items-center justify-content-between" style="padding: 4px 0;">
                                <div class="form-check m-0">
                                    <input class="form-check-input chk-subcamada-nova" type="checkbox" 
                                           id="chkSubcamadaNova_${subcamada.id}" 
                                           data-id="${subcamada.id}" 
                                           data-tipo="subcamada"
                                           data-pertence="${subcamada.pertence}">
                                    <label class="form-check-label" for="chkSubcamadaNova_${subcamada.id}" style="font-size: 13px; cursor: pointer;">
                                        ${subcamada.nome}
                                    </label>
                                </div>
                                <button class="btn btn-sm btn-link p-0 btn-editar-subcamada" data-id="${subcamada.id}" data-tipo="subcamada" data-pertence="${subcamada.pertence}" style="color: #6c757d;">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </li>
                        `);
                        // Armazena o nome no elemento jQuery
                        itemSubcamada.find('.btn-editar-subcamada').data('nome', subcamada.nome);
                        submenu.append(itemSubcamada);
                    });
                    itemCamada.append(submenu);
                }
            });
            
            // Adiciona event listeners para os botões de editar
            $(document).off('click', '.btn-editar-camada, .btn-editar-subcamada').on('click', '.btn-editar-camada, .btn-editar-subcamada', function(e) {
                e.stopPropagation();
                const id = $(this).data('id');
                const tipo = $(this).data('tipo');
                const nome = $(this).data('nome');
                const pertence = $(this).data('pertence');
                abrirModalEditar(id, tipo, nome, pertence);
            });
            
            // Adiciona event listeners para os checkboxes das camadas novas
            $(document).off('change', '.chk-camada-nova, .chk-subcamada-nova').on('change', '.chk-camada-nova, .chk-subcamada-nova', function(e) {
                e.stopPropagation();
                const id = $(this).data('id');
                const tipo = $(this).data('tipo');
                const visivel = $(this).is(':checked');
                
                // TODO: Implementar lógica para mostrar/ocultar camada no mapa
                console.log('Toggle camada:', { id, tipo, visivel });
                // Por enquanto apenas log, depois implementaremos a lógica de carregar/exibir no mapa
            });
        }
        
        // Armazena as camadas novas carregadas no mapa
        window.camadasNovasMapa = {};

        // Abre modal para adicionar camada
        function abrirModalAdicionarCamada() {
            $('#inputNomeCamada').val('');
            const modal = new bootstrap.Modal(document.getElementById('modalAdicionarCamada'));
            modal.show();
        }

        // Abre modal para adicionar subcamada
        function abrirModalAdicionarSubcamada() {
            $('#inputNomeSubcamada').val('');
            $('#selectCamadaPai').html('<option value="">Selecione uma camada</option>');
            
            // Carrega as camadas disponíveis
            $.ajax({
                url: 'listarCamadasNovas.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        response.camadas.forEach(function(camada) {
                            $('#selectCamadaPai').append(`<option value="${camada.id}">${camada.nome}</option>`);
                        });
                    }
                }
            });
            
            const modal = new bootstrap.Modal(document.getElementById('modalAdicionarSubcamada'));
            modal.show();
        }

        // Abre modal para editar/deletar
        function abrirModalEditar(id, tipo, nome, pertence = null) {
            console.log('Abrindo modal editar:', { id, tipo, nome, pertence });
            
            // Se nome não foi passado, tenta buscar do elemento
            if (!nome || nome === 'undefined') {
                const btn = $(`.btn-editar-${tipo === 'subcamada' ? 'subcamada' : 'camada'}[data-id="${id}"]`);
                nome = btn.data('nome') || btn.closest('li').find('label').text().trim();
                console.log('Nome recuperado do DOM:', nome);
            }
            
            $('#inputIdEditar').val(id);
            $('#inputTipoEditar').val(tipo);
            $('#inputNomeEditar').val(nome || '');
            
            if (tipo === 'subcamada') {
                $('#divSelectCamadaPaiEditar').show();
                $('#selectCamadaPaiEditar').html('<option value="">Selecione uma camada</option>');
                
                // Carrega as camadas disponíveis
                $.ajax({
                    url: 'listarCamadasNovas.php',
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            response.camadas.forEach(function(camada) {
                                const selected = (camada.id == pertence) ? 'selected' : '';
                                $('#selectCamadaPaiEditar').append(`<option value="${camada.id}" ${selected}>${camada.nome}</option>`);
                            });
                        }
                    }
                });
            } else {
                $('#divSelectCamadaPaiEditar').hide();
            }
            
            // Atualiza o título do modal baseado no tipo
            const titulo = tipo === 'subcamada' ? 'Editar/Excluir Subcamada' : 'Editar/Excluir Camada';
            $('#modalEditarCamadaLabel').text(titulo);
            
            // Mostra o modal
            const modalElement = document.getElementById('modalEditarCamada');
            const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
            modal.show();
        }

        // Salva nova camada
        $('#btnSalvarCamada').on('click', function() {
            const nome = $('#inputNomeCamada').val().trim();
            if (!nome) {
                alert('Por favor, digite o nome da camada');
                return;
            }
            
            const btn = $(this);
            const textoOriginal = btn.html();
            
            // Desabilita o botão e mostra loading
            btn.prop('disabled', true);
            btn.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Salvando...');
            
            $.ajax({
                url: 'salvarCamadaNova.php',
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({
                    nome: nome,
                    tipo: 'camada'
                }),
                success: function(response) {
                    // Restaura o botão
                    btn.prop('disabled', false);
                    btn.html(textoOriginal);
                    
                    // Verifica se response é string (precisa fazer parse)
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.error('Erro ao fazer parse da resposta:', e);
                            console.error('Resposta recebida:', response);
                            alert('Erro: Resposta inválida do servidor. Verifique o console.');
                            return;
                        }
                    }
                    
                    if (response.success) {
                        bootstrap.Modal.getInstance(document.getElementById('modalAdicionarCamada')).hide();
                        carregarCamadasNovas();
                        alert(response.message);
                    } else {
                        console.error('Erro ao salvar camada:', response);
                        alert(response.message || 'Erro ao salvar camada');
                    }
                },
                error: function(xhr, status, error) {
                    // Restaura o botão
                    btn.prop('disabled', false);
                    btn.html(textoOriginal);
                    
                    console.error('Erro na requisição AJAX:');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    console.error('Status Code:', xhr.status);
                    
                    let mensagemErro = 'Erro ao salvar camada';
                    
                    // Tenta fazer parse da resposta de erro
                    if (xhr.responseText) {
                        try {
                            const erroResponse = JSON.parse(xhr.responseText);
                            mensagemErro = erroResponse.message || mensagemErro;
                        } catch (e) {
                            mensagemErro = 'Erro: ' + xhr.responseText.substring(0, 100);
                        }
                    }
                    
                    alert(mensagemErro + '\n\nVerifique o console para mais detalhes.');
                }
            });
        });

        // Salva nova subcamada
        $('#btnSalvarSubcamada').on('click', function() {
            const nome = $('#inputNomeSubcamada').val().trim();
            const pertence = $('#selectCamadaPai').val();
            
            if (!nome) {
                alert('Por favor, digite o nome da subcamada');
                return;
            }
            
            if (!pertence) {
                alert('Por favor, selecione uma camada pai');
                return;
            }
            
            const btn = $(this);
            const textoOriginal = btn.html();
            
            // Desabilita o botão e mostra loading
            btn.prop('disabled', true);
            btn.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Salvando...');
            
            $.ajax({
                url: 'salvarCamadaNova.php',
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({
                    nome: nome,
                    tipo: 'subcamada',
                    pertence: pertence
                }),
                success: function(response) {
                    // Restaura o botão
                    btn.prop('disabled', false);
                    btn.html(textoOriginal);
                    
                    // Verifica se response é string (precisa fazer parse)
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.error('Erro ao fazer parse da resposta:', e);
                            console.error('Resposta recebida:', response);
                            alert('Erro: Resposta inválida do servidor. Verifique o console.');
                            return;
                        }
                    }
                    
                    if (response.success) {
                        bootstrap.Modal.getInstance(document.getElementById('modalAdicionarSubcamada')).hide();
                        carregarCamadasNovas();
                        alert(response.message);
                    } else {
                        console.error('Erro ao salvar subcamada:', response);
                        alert(response.message || 'Erro ao salvar subcamada');
                    }
                },
                error: function(xhr, status, error) {
                    // Restaura o botão
                    btn.prop('disabled', false);
                    btn.html(textoOriginal);
                    
                    console.error('Erro na requisição AJAX:');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    console.error('Status Code:', xhr.status);
                    
                    let mensagemErro = 'Erro ao salvar subcamada';
                    
                    // Tenta fazer parse da resposta de erro
                    if (xhr.responseText) {
                        try {
                            const erroResponse = JSON.parse(xhr.responseText);
                            mensagemErro = erroResponse.message || mensagemErro;
                        } catch (e) {
                            mensagemErro = 'Erro: ' + xhr.responseText.substring(0, 100);
                        }
                    }
                    
                    alert(mensagemErro + '\n\nVerifique o console para mais detalhes.');
                }
            });
        });

        // Salva edição
        $('#btnSalvarEdicao').on('click', function() {
            const id = $('#inputIdEditar').val();
            const tipo = $('#inputTipoEditar').val();
            const nome = $('#inputNomeEditar').val().trim();
            const pertence = tipo === 'subcamada' ? $('#selectCamadaPaiEditar').val() : null;
            
            if (!nome) {
                alert('Por favor, digite o nome');
                return;
            }
            
            if (tipo === 'subcamada' && !pertence) {
                alert('Por favor, selecione uma camada pai');
                return;
            }
            
            const btn = $(this);
            const textoOriginal = btn.html();
            
            // Desabilita o botão e mostra loading
            btn.prop('disabled', true);
            btn.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Salvando...');
            
            $.ajax({
                url: 'editarCamadaNova.php',
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({
                    acao: 'editar',
                    id: id,
                    nome: nome,
                    pertence: pertence
                }),
                success: function(response) {
                    // Restaura o botão
                    btn.prop('disabled', false);
                    btn.html(textoOriginal);
                    
                    // Verifica se response é string (precisa fazer parse)
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.error('Erro ao fazer parse da resposta:', e);
                            console.error('Resposta recebida:', response);
                            alert('Erro: Resposta inválida do servidor. Verifique o console.');
                            return;
                        }
                    }
                    
                    if (response.success) {
                        bootstrap.Modal.getInstance(document.getElementById('modalEditarCamada')).hide();
                        carregarCamadasNovas();
                        alert(response.message);
                    } else {
                        console.error('Erro ao editar:', response);
                        alert(response.message || 'Erro ao editar');
                    }
                },
                error: function(xhr, status, error) {
                    // Restaura o botão
                    btn.prop('disabled', false);
                    btn.html(textoOriginal);
                    
                    console.error('Erro na requisição AJAX:');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    console.error('Status Code:', xhr.status);
                    
                    let mensagemErro = 'Erro ao editar';
                    
                    // Tenta fazer parse da resposta de erro
                    if (xhr.responseText) {
                        try {
                            const erroResponse = JSON.parse(xhr.responseText);
                            mensagemErro = erroResponse.message || mensagemErro;
                        } catch (e) {
                            mensagemErro = 'Erro: ' + xhr.responseText.substring(0, 100);
                        }
                    }
                    
                    alert(mensagemErro + '\n\nVerifique o console para mais detalhes.');
                }
            });
        });

        // Deleta camada/subcamada
        $('#btnDeletarCamada').on('click', function() {
            const tipo = $('#inputTipoEditar').val();
            const nome = $('#inputNomeEditar').val();
            let mensagem = '';
            
            if (tipo === 'subcamada') {
                mensagem = `Tem certeza que deseja deletar a subcamada "${nome}"?`;
            } else {
                mensagem = `Tem certeza que deseja deletar a camada "${nome}"?\n\nATENÇÃO: Todas as subcamadas desta camada também serão deletadas.`;
            }
            
            if (!confirm(mensagem)) {
                return;
            }
            
            const id = $('#inputIdEditar').val();
            const btn = $(this);
            const textoOriginal = btn.html();
            
            // Desabilita o botão e mostra loading
            btn.prop('disabled', true);
            btn.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Deletando...');
            
            $.ajax({
                url: 'editarCamadaNova.php',
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({
                    acao: 'deletar',
                    id: id
                }),
                success: function(response) {
                    // Restaura o botão
                    btn.prop('disabled', false);
                    btn.html(textoOriginal);
                    
                    // Verifica se response é string (precisa fazer parse)
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.error('Erro ao fazer parse da resposta:', e);
                            console.error('Resposta recebida:', response);
                            alert('Erro: Resposta inválida do servidor. Verifique o console.');
                            return;
                        }
                    }
                    
                    if (response.success) {
                        bootstrap.Modal.getInstance(document.getElementById('modalEditarCamada')).hide();
                        carregarCamadasNovas();
                        alert(response.message);
                    } else {
                        console.error('Erro ao deletar:', response);
                        alert(response.message || 'Erro ao deletar');
                    }
                },
                error: function(xhr, status, error) {
                    // Restaura o botão
                    btn.prop('disabled', false);
                    btn.html(textoOriginal);
                    
                    console.error('Erro na requisição AJAX:');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    console.error('Status Code:', xhr.status);
                    
                    let mensagemErro = 'Erro ao deletar';
                    
                    // Tenta fazer parse da resposta de erro
                    if (xhr.responseText) {
                        try {
                            const erroResponse = JSON.parse(xhr.responseText);
                            mensagemErro = erroResponse.message || mensagemErro;
                        } catch (e) {
                            mensagemErro = 'Erro: ' + xhr.responseText.substring(0, 100);
                        }
                    }
                    
                    alert(mensagemErro + '\n\nVerifique o console para mais detalhes.');
                }
            });
        });

        // Previne que o dropdown feche ao clicar dentro dele
        $('#dropCamadasNovas').on('click', function(e) {
            e.stopPropagation();
        });
    </script>
</body>

</html>