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
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        #modalCamada {
            background-color: rgba(0, 0, 0, 0.5)
        }

        #dropCamadas {
            width: 320px;
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

        /* Estilos para o controle de desenhos da prefeitura */
        #controleDesenhosPrefeitura {
            position: absolute;
            top: 60px;
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
            color: rgb(255, 0, 0);
        }

        #chkAmarelo {
            color: rgb(255, 234, 0);
        }

        #chkLaranja {
            color: rgb(255, 102, 0);
        }

        #chkVerde {
            color: rgb(43, 160, 43);
        }

        #chkAzul {
            color: rgb(71, 204, 237);
        }

        #chkCinza {
            color: rgb(124, 124, 124);
        }

        .marker-imagem-aerea {
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

        #dropCamadas{
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

        #rangeOpacidadeCamadas {
            cursor: pointer;
        }

        #valorOpacidadeCamadas {
            font-weight: 600;
            color: #007bff;
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

                <!-- Botao voltar para o painel -->
                <button id="btnVoltarPainel" class="btn btn-light" onclick="voltarParaPainel()">Voltar para o Painel</button>

                <!-- Título -->
                <a class="navbar-brand" style="margin-left: 10px;" href="#">Visualizador</a>

                <!-- Botões -->
                <div id="divBots" class="d-flex align-items-center flex-grow-1 gap-2">

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
                                <div class="ms-3 mt-2" id="submenuLotes">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipoLotes" id="radioLote_todos" value="todos" checked>
                                        <label id="labelRadioLote_todos" class="form-check-label" for="radioLote_todos">
                                            Todos
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipoLotes" id="radioLote_impacto" value="impacto">
                                        <label id="labelRadioLote_impacto" class="form-check-label" for="radioLote_impacto">
                                            Impacto IPTU
                                        </label>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkLotes" checked>
                                    <label class="form-check-label" for="chkLotes">
                                        Cortes dos Lotes
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
                                    <input class="form-check-input" type="checkbox" id="chkModoCadastro">
                                    <label class="form-check-label" for="chkModoCadastro">
                                        Loteamentos
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
                                        Streetview vídeos
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

                    <!--
                    <button id="btnIncluirPoligono" class="btn btn-primary">Quadra</button>
                    <button id="btnIncluirLinha" class="btn btn-success">Lote</button>-->

                    <!-- Botão para finalizar desenho (aparece quando está em modo de desenho) -->
                    <!--<button id="btnFinalizarDesenho" class="btn btn-secondary d-none">Sair do modo desenho</button>-->

                    <!-- Botão específico para sair do modo inserção de marcadores -->
                    <!--<button id="btnSairModoMarcador" class="btn btn-secondary d-none">Sair do modo marcador</button>-->

                    <!-- Botões condicionais (aparecem se há seleção) -->
                    <!--<button id="btnEditar" class="btn btn-warning d-none">Editar</button>-->
                    <!--<button id="btnExcluir" class="btn btn-danger d-none">Excluir</button>-->

                    <!-- Botão Sair da Edição (aparece quando está em modo de edição) -->
                    <!--<button id="btnSairEdicao" class="btn btn-secondary d-none">Sair da Edição</button>-->

                    <div class="divControle">
                        <input min="0" max="1" step="0.1" type="range" class="form-range" id="customRange1" value="0.3">
                    </div>

                    <!-- Botão de Navegação para Quadrícula Selecionada -->
                    <button class="btn btn-warning" id="btnIrConsulta" onclick="irConsulta()">
                        <i class="fas fa-external-link-alt"></i> Consultas
                    </button>

                    <!-- Botão para editar trajetos de Streetview Fotos 
                    <button class="btn btn-info d-none" id="btnEditarStreetviewFotos">
                        <i class="fas fa-edit"></i> Corrigir Trajeto
                    </button>-->

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

                    <!--<button data-loteamento="" data-arquivos="" data-quadricula="" onclick="desenharNoPDF(this)" id="btnLerPDF" class="btn btn-warning d-none">Desenhar no PDF</button>-->

                    <!-- Botões Cadastro removidos - agora é uma camada no dropdown -->
                    <!-- <button id="btnCadastro" class="btn btn-info">Cadastro</button> -->

                    <!-- Botão Sair do Cadastro (aparece quando entra no modo cadastro) -->
                    <!-- <button id="btnSairCadastro" class="btn btn-secondary d-none">Sair do Cadastro</button> -->

                    <!-- Botão Marcador e inputs text -->
                    <!--<button id="btnIncluirMarcador" class="btn btn-danger d-none">Marcador</button>-->
                    <!--<input type="text" id="inputLoteAtual" class="form-control" style="width: 80px; display: none;" placeholder="Lote">
                    <input type="text" id="inputQuadraAtual" class="form-control" style="width: 80px; display: none;" placeholder="Quadra">-->

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
        const paginaAtual = 'index_3';
        const userControl = <?php echo json_encode($_SESSION['usuario'][2]); ?>;

        function criaBotAdm() {

            let botAdm = document.createElement('button');
            botAdm.className = 'btn btn-primary';
            botAdm.innerHTML = 'Desenhar';
            botAdm.onclick = function() {
                window.location.href = `index_2.php?quadricula=${dadosOrto[0]['quadricula']}`;
            };

            if (userControl == 'true') {
                document.getElementById('divBots').appendChild(botAdm);
            }

        }

        function voltarParaPainel() {
            window.location.href = `painel.php`;
        }

        function irConsulta() {
            window.location.href = `consultas`;
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
                // Usa a biblioteca Turf.js para calcular distância em metros
                const from = turf.point([ponto1.lng(), ponto1.lat()]);
                const to = turf.point([ponto2.lng(), ponto2.lat()]);
                return turf.distance(from, to, { units: 'kilometers' }) * 1000; // Converte km para metros
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
                // Calcula o ponto médio entre os dois pontos
                const lat = (ponto1.lat() + ponto2.lat()) / 2;
                const lng = (ponto1.lng() + ponto2.lng()) / 2;
                
                // Aplica um pequeno offset vertical (sobe a label)
                const offsetVertical = 0.0000015; // Aproximadamente 1.5 metros para cima
                const posicao = new google.maps.LatLng(lat + offsetVertical, lng);

                // Cria elemento HTML para a label
                const el = document.createElement('div');
                el.className = 'measurement-label';
                el.textContent = this.formatarDistancia(distancia);

                // Cria marcador avançado para a label
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
                coords.push([pontos[0].lng(), pontos[0].lat()]); // Fecha o polígono

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
                // Remove labels antigas
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                if (this.pontos.length < 2) return;

                // Atualiza ou cria polígono
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

                // Adiciona labels nas arestas
                for (let i = 0; i < this.pontos.length; i++) {
                    const proximoIndice = (i + 1) % this.pontos.length;
                    const distancia = this.calcularDistancia(this.pontos[i], this.pontos[proximoIndice]);
                    this.adicionarLabelAresta(this.pontos[i], this.pontos[proximoIndice], distancia);
                }

                // Se tiver pelo menos 3 pontos, calcula a área
                if (this.pontos.length >= 3) {
                    const coords = this.pontos.map(p => [p.lng(), p.lat()]);
                    coords.push([this.pontos[0].lng(), this.pontos[0].lat()]); // Fecha o polígono

                    const polygon = turf.polygon([coords]);
                    const area = turf.area(polygon);
                    
                    const centroide = this.calcularCentroide(this.pontos);
                    this.adicionarLabelCentral(area.toFixed(1) + ' m²', centroide, 'measurement-area-label');
                }
            },

            atualizarPolilinha: function() {
                // Remove labels antigas
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                if (this.pontos.length < 1) return;

                // Atualiza ou cria polilinha
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

                // Adiciona labels nas arestas e calcula distância total
                let distanciaTotal = 0;
                for (let i = 0; i < this.pontos.length - 1; i++) {
                    const distancia = this.calcularDistancia(this.pontos[i], this.pontos[i + 1]);
                    distanciaTotal += distancia;
                    this.adicionarLabelAresta(this.pontos[i], this.pontos[i + 1], distancia);
                }

                // Adiciona label com distância total no último ponto
                if (this.pontos.length >= 2) {
                    const ultimoPonto = this.pontos[this.pontos.length - 1];
                    this.adicionarLabelCentral(
                        'Total: ' + this.formatarDistancia(distanciaTotal),
                        ultimoPonto,
                        'measurement-distance-label'
                    );
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

                // Desabilita interatividade de todos os objetos do mapa
                MapFramework.atualizarInteratividadeObjetos(false);

                // Listener para adicionar pontos
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

                // Listener para finalizar (botão direito)
                this.listenerRightClick = MapFramework.map.addListener('rightclick', (e) => {
                    if (this.tipo === 'area' && this.pontos.length < 3) {
                        alert('É necessário pelo menos 3 pontos para criar uma área.');
                        return;
                    }
                    if (this.tipo === 'distancia' && this.pontos.length < 2) {
                        alert('É necessário pelo menos 2 pontos para medir distância.');
                        return;
                    }
                    
                    // Finaliza a medição
                    this.finalizar();
                });

                // Listener para mostrar linha temporária (preview)
                this.listenerMouseMove = MapFramework.map.addListener('mousemove', (e) => {
                    if (this.pontos.length === 0) return;

                    // Remove temporários anteriores
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
                    }
                });
            },

            finalizar: function() {
                // Remove linha temporária
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

                    // Adiciona label com distância total no último ponto
                    if (pontos.length >= 2) {
                        let distanciaTotal = 0;
                        for (let i = 0; i < pontos.length - 1; i++) {
                            distanciaTotal += this.calcularDistancia(pontos[i], pontos[i + 1]);
                        }
                        
                        const ultimoPonto = pontos[pontos.length - 1];
                        const elTotal = document.createElement('div');
                        elTotal.className = 'measurement-distance-label';
                        elTotal.textContent = 'Total: ' + this.formatarDistancia(distanciaTotal);

                        const labelTotal = new google.maps.marker.AdvancedMarkerElement({
                            position: ultimoPonto,
                            content: elTotal,
                            map: MapFramework.map,
                            zIndex: 1001,
                            gmpClickable: false
                        });

                        labelsNovas.push(labelTotal);
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
                // Remove labels antigas
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                if (!this.poligono) return;
                
                const path = this.poligono.getPath();
                const pontos = [];
                
                for (let i = 0; i < path.getLength(); i++) {
                    pontos.push(path.getAt(i));
                }

                if (pontos.length < 2) return;

                // Adiciona labels nas arestas
                for (let i = 0; i < pontos.length; i++) {
                    const proximoIndice = (i + 1) % pontos.length;
                    const distancia = this.calcularDistancia(pontos[i], pontos[proximoIndice]);
                    this.adicionarLabelAresta(pontos[i], pontos[proximoIndice], distancia);
                }

                // Se tiver pelo menos 3 pontos, calcula a área
                if (pontos.length >= 3) {
                    const coords = pontos.map(p => [p.lng(), p.lat()]);
                    coords.push([pontos[0].lng(), pontos[0].lat()]); // Fecha o polígono

                    const polygon = turf.polygon([coords]);
                    const area = turf.area(polygon);
                    
                    const centroide = this.calcularCentroide(pontos);
                    this.adicionarLabelCentral(area.toFixed(1) + ' m²', centroide, 'measurement-area-label');
                }
            },

            atualizarMedidasPolilinha: function() {
                // Remove labels antigas
                this.labels.forEach(l => l.setMap(null));
                this.labels = [];

                if (!this.polilinha) return;
                
                const path = this.polilinha.getPath();
                const pontos = [];
                
                for (let i = 0; i < path.getLength(); i++) {
                    pontos.push(path.getAt(i));
                }

                if (pontos.length < 1) return;

                // Adiciona labels nas arestas e calcula distância total
                let distanciaTotal = 0;
                for (let i = 0; i < pontos.length - 1; i++) {
                    const distancia = this.calcularDistancia(pontos[i], pontos[i + 1]);
                    distanciaTotal += distancia;
                    this.adicionarLabelAresta(pontos[i], pontos[i + 1], distancia);
                }

                // Adiciona label com distância total no último ponto
                if (pontos.length >= 2) {
                    const ultimoPonto = pontos[pontos.length - 1];
                    this.adicionarLabelCentral(
                        'Total: ' + this.formatarDistancia(distanciaTotal),
                        ultimoPonto,
                        'measurement-distance-label'
                    );
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

        const arrayCamadas = {
            prefeitura: [],
            limite: [],
            marcador: [],
            marcador_quadra: [],
            quadriculas: [],
            ortofoto: [],
            quadra: [],
            lotesPref: [],
            lote: [],
            poligono_lote: [],
            quarteirao: [],
            imagens_aereas: [],
            streetview: [],
            streetview_fotos: [],
            semCamadas: []
        };

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

        // Checkbox dos Lotes
        $('#chkLotes').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('lote', visivel);
        });

        // Checkbox dos Lotes
        $('#chkPoligono_lote').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('poligono_lote', visivel);
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

        $('#chkImagensAereas').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('imagens_aereas', visivel);
        });

        $('#chkStreetview').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('streetview', visivel);
        });

        $('#chkStreetviewFotos').on('change', function() {
            const visivel = $(this).is(':checked');
            MapFramework.alternarVisibilidadeCamada('streetview_fotos', visivel);

            // Mostra/oculta o botão de edição de trajeto
            if (visivel) {
                $('#btnEditarStreetviewFotos').removeClass('d-none');
            } else {
                $('#btnEditarStreetviewFotos').addClass('d-none');
                // Se estava no modo de edição, sai dele
                if (MapFramework.modoEdicaoStreetviewFotos) {
                    MapFramework.desativarEdicaoStreetviewFotos();
                    $('#btnEditarStreetviewFotos').removeClass('btn-danger').addClass('btn-info');
                    $('#btnEditarStreetviewFotos').html('<i class="fas fa-edit"></i> Corrigir Trajeto');
                }
            }
        });

        // Evento do botão de edição de trajeto
        $('#btnEditarStreetviewFotos').on('click', function() {
            if (MapFramework.modoEdicaoStreetviewFotos) {
                // Se já está no modo de edição, sai dele
                MapFramework.desativarEdicaoStreetviewFotos();
                $(this).removeClass('btn-danger').addClass('btn-info');
                $(this).html('<i class="fas fa-edit"></i> Corrigir Trajeto');
            } else {
                // Se não está no modo de edição, entra nele
                MapFramework.ativarEdicaoStreetviewFotos();
                $(this).removeClass('btn-info').addClass('btn-danger');
                $(this).html('<i class="fas fa-save"></i> Finalizar Edição');
            }
        });

        // Eventos para os radio buttons de tipo de marcador
        $('input[name="tipoMarcador"]').on('change', function() {
            const tipoSelecionado = $(this).val();
            atualizarRotulosMarcadores(tipoSelecionado);
        });

        // Eventos para os radio buttons de tipo de marcador
        $('input[name="tipoLotes"]').on('change', function() {
            const tipoSelecionado2 = $(this).val();
            atualizarCorPoligonos_lotes(tipoSelecionado2);
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
                    } else if (corMarcador.includes('#7c7c7c') || corMarcador.includes('rgb(124, 124, 124)')) { // Azul
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
                    } else if (corMarcador.includes('#7c7c7c') || corMarcador.includes('rgb(124, 124, 124)')) { // Azul
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

        function atualizarCorPoligonos_lotes(tipo) {
            console.log(arrayCamadas.poligono_lote);
            if (tipo === 'todos') {
                arrayCamadas.poligono_lote.forEach(poligono => {
                    poligono.setOptions({
                        fillColor: 'purple',
                        strokeColor: 'purple'
                    });
                });
            } else if (tipo === 'impacto') {
                arrayCamadas.poligono_lote.forEach(poligono => {
                    poligono.setOptions({
                        fillColor: 'red',
                        strokeColor: 'red'
                    });
                });
            }

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
            const nomeDecodificado = normalizarString(nomeArquivo);
            window.open('loteamentos_quadriculas/pdf/' + nomeDecodificado, '_blank');
        }

        // Função para abrir PDF em nova aba (nova função para os botões)
        function abrirPDFNovaAba(nomeArquivo) {
            const nomeDecodificado = normalizarString(nomeArquivo);
            window.open('loteamentos_quadriculas/pdf/' + nomeDecodificado, '_blank');
        }

        // Função para abrir PDF de quarteirão com tratamento de erro
        async function abrirPDFQuarteirao(nomeArquivo) {
            console.log('Nome original PDF quarteirão:', nomeArquivo);
            const nomeDecodificado = normalizarString(nomeArquivo);

            // Se o caminho já inclui a pasta, usa diretamente
            if (nomeArquivo.includes('/')) {
                window.open('loteamentos_quadriculas/pdfs_quarteiroes/' + nomeDecodificado, '_blank');
            } else {
                // Caso contrário, usa o caminho padrão
                window.open('loteamentos_quadriculas/pdfs_quarteiroes/' + nomeDecodificado, '_blank');
            }
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

            await MapFramework.carregarDesenhosPrefeitura(dadosOrto[0]['quadricula']);

            MapFramework.carregarLimiteKML();

            MapFramework.carregarQuadriculasKML();

            // Carrega os quarteirões da quadrícula atual
            await MapFramework.carregaQuarteiroes(dadosOrto[0]['quadricula']);

            await MapFramework.carregarPlanilha();

            await MapFramework.carregarMarcadoresSalvos(dadosOrto[0]['quadricula']);

            await MapFramework.carregarImagensAereas(dadosOrto[0]['quadricula']);

            // Carrega os trajetos Streetview da quadrícula no mapa
            MapFramework.carregarStreets(dadosOrto[0]['quadricula']);

            // Carrega as fotos do Streetview da quadrícula no mapa
            MapFramework.carregarStreetviewFotos(dadosOrto[0]['quadricula']);

            // Carrega camadas dinâmicas adicionais de KML
            MapFramework.carregarMaisCamadas();

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

                    // Função para ativar as linhas que pertencem a uma quadra
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
        function carregarDadosPDFsQuarteiroes(numeroQuarteirao = null) {
            console.log('carregarDadosPDFsQuarteiroes', numeroQuarteirao);
            // Se um número de quarteirão foi especificado, tenta carregar o JSON específico
            if (numeroQuarteirao) {
                return $.ajax({
                    url: `loteamentos_quadriculas/pdfs_quarteiroes/${numeroQuarteirao}/quarteiroes.json`,
                    method: 'GET',
                    dataType: 'json'
                }).then(function(data) {
                    return data;
                }).catch(function(error) {
                    // Se falhar, carrega o arquivo geral e filtra apenas os dados deste quarteirão
                    //console.log(`Arquivo específico não encontrado para quarteirão ${numeroQuarteirao}, usando arquivo geral`);
                    return carregarDadosPDFsQuarteiroes().then(function(dadosGerais) {
                        if (dadosGerais && Array.isArray(dadosGerais)) {
                            // Filtra apenas os dados relacionados ao quarteirão específico
                            return dadosGerais.filter(item =>
                                item.quarteiroes && item.quarteiroes.includes(numeroQuarteirao)
                            );
                        }
                        return [];
                    });
                });
            }

            // Comportamento original para carregamento geral
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
        function obterPDFsQuarteirao(nomeQuarteirao, dadosPDFs = null) {
            // Usa os dados passados como parâmetro ou a variável global como fallback
            const dadosParaUsar = dadosPDFs || dadosPDFsQuarteiroes;

            if (!dadosParaUsar || !Array.isArray(dadosParaUsar)) {
                return [];
            }

            // Procura por arquivos que contenham este quarteirão
            const arquivosComQuarteirao = dadosParaUsar.filter(item => {
                return item.quarteiroes && item.quarteiroes.includes(nomeQuarteirao);
            });

            // Retorna os nomes dos arquivos encontrados
            return arquivosComQuarteirao.map(item => {
                // Se os dados vieram de um arquivo específico (pasta do quarteirão), adiciona o caminho
                if (dadosPDFs && dadosPDFs.length > 0 && dadosPDFs[0] && dadosPDFs[0].nome_arquivo) {
                    // Se o nome do arquivo não começa com o caminho da pasta, adiciona o caminho
                    if (!item.nome_arquivo.startsWith(`${nomeQuarteirao}/`)) {
                        return `${nomeQuarteirao}/${item.nome_arquivo}`;
                    }
                }
                // Para a estrutura atual (arquivo geral), usa o caminho padrão
                return `pdfs_quarteiroes/${item.nome_arquivo}`;
            });
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
            console.log('🗺️ Mostrando', quarteiroesDoLoteamento.length, 'quarteirões no mapa');
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
                console.log('✅ Quarteirão mostrado no mapa - ID:', quarteirao.id, 'Nome:', quarteirao.properties.impreciso_name);
            });

            if (quarteiroesDoLoteamento.length === 0) {
                container.html('<div class="alert alert-info">Nenhum quarteirão encontrado dentro deste loteamento.</div>');
                return;
            }

            // Carrega os dados complementares e depois cria os botões
            carregarDadosQuarteiroes().then(function() {
                // Ordena os quarteirões numericamente
                quarteiroesDoLoteamento.sort((a, b) => {
                    const nomeA = a.properties.impreciso_name || a.id;
                    const nomeB = b.properties.impreciso_name || b.id;

                    // Extrai números dos nomes dos quarteirões
                    const numeroA = parseInt(nomeA.replace(/\D/g, '')) || 0;
                    const numeroB = parseInt(nomeB.replace(/\D/g, '')) || 0;

                    //console.log(`Comparando: ${nomeA} (${numeroA}) vs ${nomeB} (${numeroB})`);

                    // Ordenação numérica
                    return numeroA - numeroB;
                });

                //console.log('Quarteirões ordenados:', quarteiroesDoLoteamento.map(q => q.properties.impreciso_name || q.id));

                // Array para armazenar todos os elementos criados
                const elementosQuarteiroes = [];
                // Cria os botões radio para cada quarteirão
                quarteiroesDoLoteamento.forEach((quarteirao, index) => {
                    // Obtém o nome do quarteirão (impreciso_name ou id)
                    const nomeQuarteirao = quarteirao.properties.impreciso_name || quarteirao.id;

                    // Busca informações complementares
                    const infoComplementar = obterInfoComplementarQuarteirao(loteamento.nome, nomeQuarteirao);

                    // Carrega os dados dos PDFs específicos para este quarteirão
                    carregarDadosPDFsQuarteiroes(nomeQuarteirao).then(function(dadosPDFs) {
                        // Busca PDFs do quarteirão passando os dados carregados
                        const pdfsQuarteirao = obterPDFsQuarteirao(nomeQuarteirao, dadosPDFs);
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
                                            // Extrai apenas o nome do arquivo para exibição
                                            const nomeArquivo = arquivo.split('/').pop();
                                            return `<a href="javascript:void(0)" onclick="abrirPDFQuarteirao('${arquivo}')" title="${arquivo}">
                                                <i class="fas fa-file-pdf"></i>${nomeArquivo.length > 20 ? nomeArquivo.substring(0, 20) + '...' : nomeArquivo}
                                            </a>`;
                                        }).join('')}
                                    </div>` : 
                                    '<div class="submenu-pdfs"><em class="text-muted">Sem PDFs</em></div>'
                                }
                            </div>
                        `);

                        // Armazena o elemento no array com o índice correto
                        elementosQuarteiroes[index] = opcao;

                        // Verifica se todos os elementos foram criados
                        if (elementosQuarteiroes.filter(el => el !== undefined).length === quarteiroesDoLoteamento.length) {
                            // Adiciona todos os elementos ao container na ordem correta
                            elementosQuarteiroes.forEach(elemento => {
                                if (elemento) {
                                    container.append(elemento);
                                }
                            });

                            // Adiciona evento para o botão Docs de cada quarteirão usando delegação
                            // IMPORTANTE: Adicionar APÓS os elementos estarem no DOM
                            $(document).off('click', '.btn-docs-quarteirao').on('click', '.btn-docs-quarteirao', function(e) {
                                e.preventDefault();
                                e.stopPropagation();

                                const nomeQuarteirao = $(this).data('quarteirao');
                                console.log('Botão Docs clicado para quarteirão:', nomeQuarteirao);
                                abrirModalGerenciarDocs(nomeQuarteirao);
                            });

                            // Adiciona evento para destacar seleção de quarteirão
                            // IMPORTANTE: Adicionar APÓS os radio buttons estarem no DOM
                            $('input[name="quarteirao"]').off('change').on('change', function() {
                                const quarteiraoId = $(this).val();
                                const nomeQuarteirao = $(this).data('nome');

                                console.log('📻 Radio quarteirão clicado:', nomeQuarteirao, 'ID:', quarteiraoId);

                                // Define as variáveis globais do quarteirão
                                quarteiraoAtualSelecionado = nomeQuarteirao;
                                quarteiraoIdAtualSelecionado = quarteiraoId;

                                if (quarteiraoId) {
                                    // Destaca o quarteirão selecionado passando apenas o ID
                                    destacarQuarteiraoSelecionado(nomeQuarteirao, quarteiraoId);
                                }
                            });
                        }
                    });

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
            console.log('🎯 destacarQuarteiraoSelecionado chamado:', nomeQuarteirao, idQuarteirao);
            console.log('📊 Total de quarteirões em arrayCamadas:', arrayCamadas.quarteirao ? arrayCamadas.quarteirao.length : 0);

            // Primeiro, redefine TODOS os quarteirões visíveis para cor branca
            if (arrayCamadas.quarteirao) {
                let quarteiresVisiveis = 0;
                arrayCamadas.quarteirao.forEach(obj => {
                    // Só mexe nos quarteirões que estão visíveis no mapa
                    if (obj.polygon && obj.polygon.getMap()) {
                        quarteiresVisiveis++;
                        obj.polygon.setOptions({
                            strokeColor: '#1275C3',
                            strokeWeight: 2,
                            zIndex: 10,
                        });
                    }
                });
                console.log('👁️ Quarteirões visíveis no mapa:', quarteiresVisiveis);
            }

            // Obtém o quarteirão pelo ID usando a função do framework
            const quarteirao = MapFramework.obterQuarteiraoPorId(idQuarteirao);
            console.log('🔍 Quarteirão encontrado:', quarteirao ? 'SIM' : 'NÃO');

            if (!quarteirao) {
                console.error('❌ Quarteirão não encontrado com ID:', idQuarteirao);
                return;
            }

            // Destaca APENAS o quarteirão selecionado em amarelo
            if (quarteirao.polygon) {
                console.log('🎨 Destacando quarteirão com cor branca e peso 5');
                quarteirao.polygon.setOptions({
                    strokeColor: 'white',
                    strokeWeight: 5,
                    zIndex: 15
                });
                quarteirao.polygon.setMap(MapFramework.map);
                console.log('✅ Quarteirão destacado com sucesso!');
            } else {
                console.error('❌ Quarteirão não tem polygon!');
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
                    //$('#divCadastro3').fadeIn(150);
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
        // Variáveis para armazenar as coordenadas originais dos desenhos
        let coordenadasOriginaisDesenhos = [];
        let desenhosCarregados = false;

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

            // Define os offsets baseados na direção
            let offsetLat = 0,
                offsetLng = 0;

            switch (direcao) {
                case 'norte':
                    offsetLat = distancia;
                    break;
                case 'sul':
                    offsetLat = -distancia;
                    break;
                case 'leste':
                    offsetLng = distancia;
                    break;
                case 'oeste':
                    offsetLng = -distancia;
                    break;
                case 'nordeste':
                    offsetLat = distancia;
                    offsetLng = distancia;
                    break;
                case 'noroeste':
                    offsetLat = distancia;
                    offsetLng = -distancia;
                    break;
                case 'sudeste':
                    offsetLat = -distancia;
                    offsetLng = distancia;
                    break;
                case 'sudoeste':
                    offsetLat = -distancia;
                    offsetLng = -distancia;
                    break;
            }

            // Move cada polígono
            arrayCamadas[destinoLotes].forEach(function(polygon) {
                if (polygon.getPath && polygon.setPath) {
                    const path = polygon.getPath();
                    const newPath = [];

                    for (let i = 0; i < path.getLength(); i++) {
                        const point = path.getAt(i);
                        const grausOffset = metrosParaGraus(1, point.lat());

                        const newPoint = new google.maps.LatLng(
                            point.lat() + (offsetLat * grausOffset.lat),
                            point.lng() + (offsetLng * grausOffset.lng)
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

        // Função para resetar desenhos para coordenadas originais
        function resetarDesenhosPrefeitura() {
            const camadaLotes = "lotesPref";
            const destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';

            if (!coordenadasOriginaisDesenhos || coordenadasOriginaisDesenhos.length === 0) {
                alert('Nenhuma coordenada original salva para resetar.');
                return;
            }

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
            }
        }

        // Função para salvar desenhos modificados
        function salvarDesenhosPrefeitura() {
            const camadaLotes = "lotesPref";
            const destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';

            if (!arrayCamadas[destinoLotes] || arrayCamadas[destinoLotes].length === 0) {
                alert('Nenhum desenho para salvar.');
                return;
            }

            // Coletar coordenadas atuais de todos os desenhos
            const coordenadasAtuais = [];

            arrayCamadas[destinoLotes].forEach(function(polygon) {
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

                    coordenadasAtuais.push(coordinates);
                }
            });

            // Obter quadrícula atual
            const quadricula = dadosOrto && dadosOrto[0] && dadosOrto[0]['quadricula'] ? dadosOrto[0]['quadricula'] : null;

            if (!quadricula) {
                alert('Erro: Quadrícula não identificada.');
                return;
            }

            // Preparar dados para envio
            const dadosParaSalvar = {
                quadricula: quadricula,
                coordenadas: coordenadasAtuais
            };

            // Enviar dados via AJAX
            $.ajax({
                url: 'salvar_coordenadas_desenhos_prefeitura.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(dadosParaSalvar),
                success: function(response) {
                    if (response.success) {
                        alert(`Coordenadas salvas com sucesso! ${response.features_atualizadas} desenhos atualizados.`);
                        // Atualiza as coordenadas originais com as novas posições
                        salvarCoordenadasOriginais();
                    } else {
                        alert('Erro ao salvar: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro na requisição:', error);
                    alert('Erro ao salvar coordenadas. Tente novamente.');
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
        function cancelarControleDesenhos() {
            // Resetar para coordenadas originais
            resetarDesenhosPrefeitura();

            // Ocultar controle
            $('#controleDesenhosPrefeitura').removeClass('show');

            // Desmarcar checkbox
            $('#new_checkLotes').prop('checked', false);

            // Ocultar desenhos
            if (MapFramework && MapFramework.toggleLotesGeojson) {
                MapFramework.toggleLotesGeojson(false);
            }
        }

        // ============================================================================
        // EVENT LISTENER PARA CHECKBOX DOS LOTES DA PREFEITURA
        // ============================================================================
        // Conecta o checkbox com as funções do MapFramework para carregar/mostrar lotes
        // ============================================================================
        $(document).ready(function() {
            $('#new_checkLotes').change(function() {
                const isChecked = $(this).is(':checked');

                // Usa a função do framework para mostrar/ocultar lotes
                if (MapFramework && MapFramework.toggleLotesGeojson) {
                    MapFramework.toggleLotesGeojson(isChecked);
                }

                // Mostra/oculta o controle de desenhos da prefeitura
                const controle = $('#controleDesenhosPrefeitura');
                if (isChecked) {
                    controle.addClass('show');
                } else {
                    controle.removeClass('show');
                }
            });

            criaBotAdm()
        });
        // ============================================================================

        let quarteiraoAtualModal = null;
        let imovelAtualModal = null;
        let dadosImovelAtualModal = null;

        // Função para abrir o modal de gerenciamento de documentos
        function abrirModalGerenciarDocs(nomeQuarteirao) {
            console.log('Função abrirModalGerenciarDocs chamada para:', nomeQuarteirao);
            quarteiraoAtualModal = nomeQuarteirao;

            // Atualiza as informações do modal
            $('#nomeQuarteiraoModal').text(`Quarteirão ${nomeQuarteirao}`);
            $('#caminhoPastaModal').text(`Pasta: loteamentos_quadriculas/pdfs_quarteiroes/`);

            // Limpa a lista de arquivos
            $('#listaArquivos').empty();
            $('#inputArquivos').val('');
            $('#btnUploadArquivos').prop('disabled', true);

            // Carrega a lista de arquivos
            carregarListaArquivosQuarteirao(nomeQuarteirao);

            // Verifica se o modal existe
            const modal = $('#modalGerenciarDocs');
            console.log('Modal encontrado:', modal.length > 0);

            // Mostra o modal
            if (modal.length > 0) {
                modal.modal('show');
                console.log('Modal.show() executado');
            } else {
                console.error('Modal não encontrado!');
            }
        }

        // Função para carregar a lista de arquivos do quarteirão
        function carregarListaArquivosQuarteirao(nomeQuarteirao) {
            console.log('Carregando lista de arquivos para quarteirão:', nomeQuarteirao);

            // Tenta carregar arquivos da pasta física do quarteirão
            $.ajax({
                url: 'consultas/listar_arquivos_quarteirao.php',
                method: 'POST',
                data: {
                    quarteirao: nomeQuarteirao
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Resposta da listagem:', response);
                    if (response.success && response.arquivos.length > 0) {
                        exibirListaArquivos(response.arquivos);
                    } else {
                        // Se não encontrar arquivos na pasta, tenta carregar do JSON
                        carregarDadosPDFsQuarteiroes(nomeQuarteirao).then(function(dadosPDFs) {
                            if (dadosPDFs && Array.isArray(dadosPDFs)) {
                                const arquivos = dadosPDFs.map(item => {
                                    return item.nome_arquivo.split('/').pop();
                                });
                                exibirListaArquivos(arquivos);
                            } else {
                                $('#listaArquivos').html('<div class="alert alert-warning">Nenhum arquivo encontrado.</div>');
                            }
                        }).catch(function(error) {
                            console.error('Erro ao carregar dados dos PDFs:', error);
                            $('#listaArquivos').html('<div class="alert alert-warning">Nenhum arquivo encontrado.</div>');
                        });
                    }
                },
                error: function() {
                    $('#listaArquivos').html('<div class="alert alert-danger">Erro ao carregar arquivos.</div>');
                }
            });
        }

        // Função para exibir a lista de arquivos
        function exibirListaArquivos(arquivos) {
            const container = $('#listaArquivos');
            container.empty();

            if (arquivos.length === 0) {
                container.html('<div class="alert alert-info">Nenhum arquivo encontrado nesta pasta.</div>');
                return;
            }

            arquivos.forEach(function(arquivo) {
                const extensao = arquivo.split('.').pop().toLowerCase();
                const icone = getIconeArquivo(extensao);

                const item = $(`
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="${icone} me-2"></i>
                            <span>${arquivo}</span>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-primary me-2" onclick="visualizarArquivo('${arquivo}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="excluirArquivoQuarteirao('${arquivo}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `);

                container.append(item);
            });
        }

        // Função para obter o ícone baseado na extensão do arquivo
        function getIconeArquivo(extensao) {
            const icones = {
                'pdf': 'fas fa-file-pdf text-danger',
                'doc': 'fas fa-file-word text-primary',
                'docx': 'fas fa-file-word text-primary',
                'jpg': 'fas fa-file-image text-success',
                'jpeg': 'fas fa-file-image text-success',
                'png': 'fas fa-file-image text-success',
                'gif': 'fas fa-file-image text-success'
            };
            return icones[extensao] || 'fas fa-file text-secondary';
        }

        // Função para visualizar um arquivo
        function visualizarArquivo(nomeArquivo) {
            const caminho = `loteamentos_quadriculas/pdfs_quarteiroes/${quarteiraoAtualModal}/${nomeArquivo}`;
            window.open(caminho, '_blank');
        }

        // Função para excluir um arquivo
        function excluirArquivoQuarteirao(nomeArquivo) {
            if (!confirm(`Tem certeza que deseja excluir o arquivo "${nomeArquivo}"?`)) {
                return;
            }

            $.ajax({
                url: 'consultas/excluir_arquivo_quarteirao.php',
                method: 'POST',
                data: {
                    quarteirao: quarteiraoAtualModal,
                    arquivo: nomeArquivo
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Arquivo excluído com sucesso!');
                        // Atualiza a lista do modal
                        carregarListaArquivosQuarteirao(quarteiraoAtualModal);
                        // Atualiza a lista de PDFs na interface principal
                        atualizarListaPDFsQuarteirao(quarteiraoAtualModal);
                    } else {
                        alert('Erro ao excluir arquivo: ' + response.message);
                    }
                },
                error: function() {
                    alert('Erro ao excluir arquivo.');
                }
            });
        }

        // Função para atualizar a lista de PDFs na divCadastro2
        function atualizarListaPDFsQuarteirao(nomeQuarteirao) {
            console.log('Atualizando lista de PDFs para quarteirão:', nomeQuarteirao);

            // Carrega arquivos da pasta física do quarteirão
            $.ajax({
                url: 'consultas/listar_arquivos_quarteirao.php',
                method: 'POST',
                data: {
                    quarteirao: nomeQuarteirao
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Resposta da atualização:', response);

                    // Encontra o elemento do quarteirão na divCadastro2
                    const radioSelector = `input[name="quarteirao"][data-nome="${nomeQuarteirao}"]`;
                    const radioElement = $(radioSelector);

                    if (radioElement.length > 0) {
                        const opcaoQuarteirao = radioElement.closest('.opcao-quarteirao');
                        const submenuPdfs = opcaoQuarteirao.find('.submenu-pdfs');

                        // Atualiza o conteúdo do submenu de PDFs
                        if (response.success && response.arquivos.length > 0) {
                            const novosPDFs = response.arquivos.map(arquivo => {
                                const caminhoCompleto = `${nomeQuarteirao}/${arquivo}`;
                                return `<a href="javascript:void(0)" onclick="abrirPDFQuarteirao('${caminhoCompleto}')" title="${caminhoCompleto}">
                                    <i class="fas fa-file-pdf"></i>${arquivo.length > 20 ? arquivo.substring(0, 20) + '...' : arquivo}
                                </a>`;
                            }).join('');

                            submenuPdfs.html(novosPDFs);
                        } else {
                            submenuPdfs.html('<em class="text-muted">Sem PDFs</em>');
                        }
                    }
                },
                error: function() {
                    console.error('Erro ao atualizar lista de PDFs');
                }
            });
        }

        // Event listeners para o modal
        $(document).ready(function() {
            // Habilita o botão de upload quando arquivos são selecionados
            $('#inputArquivos').on('change', function() {
                const files = this.files;
                $('#btnUploadArquivos').prop('disabled', files.length === 0);
            });

            // Upload de arquivos
            $('#btnUploadArquivos').on('click', function() {
                const files = $('#inputArquivos')[0].files;
                if (files.length === 0) return;

                const formData = new FormData();
                formData.append('quarteirao', quarteiraoAtualModal);

                for (let i = 0; i < files.length; i++) {
                    formData.append('arquivos[]', files[i]);
                }

                // Mostra loading
                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Enviando...');

                console.log('Enviando arquivos para quarteirão:', quarteiraoAtualModal);
                console.log('FormData:', formData);

                $.ajax({
                    url: 'consultas/upload_arquivos_quarteirao.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        console.log('Resposta do servidor:', response);
                        if (response.success) {
                            alert('Arquivos enviados com sucesso!');
                            $('#inputArquivos').val('');

                            // Atualiza a lista do modal
                            carregarListaArquivosQuarteirao(quarteiraoAtualModal);

                            // Atualiza a lista de PDFs na divCadastro2
                            atualizarListaPDFsQuarteirao(quarteiraoAtualModal);
                        } else {
                            alert('Erro ao enviar arquivos: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro na requisição:', xhr, status, error);
                        console.error('Resposta do servidor:', xhr.responseText);
                        alert('Erro ao enviar arquivos.');
                    },
                    complete: function() {
                        $('#btnUploadArquivos').prop('disabled', true).html('Adicionar Arquivos');
                    }
                });
            });
        });

        // ============================================================================
        // FUNÇÕES PARA GERENCIAR DOCUMENTOS DE IMÓVEIS
        // ============================================================================

        // Função para abrir o modal de gerenciamento de documentos de imóveis
        function abrirModalGerenciarDocsImovel(imobId, dadosImovel) {
            console.log('Função abrirModalGerenciarDocsImovel chamada para:', imobId);
            imovelAtualModal = imobId;
            dadosImovelAtualModal = dadosImovel;

            // Atualiza as informações do modal
            $('#nomeImovelModal').text(`Imóvel ID: ${imobId}`);
            $('#caminhoPastaImovelModal').text(`Pasta: loteamentos_quadriculas/imoveis/${imobId}/`);

            // Adiciona informações do imóvel
            let infoTexto = '';
            if (dadosImovel.cadastro) {
                infoTexto += `Logradouro: ${dadosImovel.cadastro.logradouro || 'N/A'}, `;
                infoTexto += `Nº: ${dadosImovel.cadastro.numero || 'N/A'} `;
                infoTexto += `- ${dadosImovel.cadastro.bairro || 'N/A'}`;
            }
            if (dadosImovel.desenhos) {
                infoTexto += ` | Quadra: ${dadosImovel.desenhos.quadra || 'N/A'}, Lote: ${dadosImovel.desenhos.lote || 'N/A'}`;
            }
            $('#infoImovelModal').text(infoTexto);

            // Limpa a lista de arquivos
            $('#listaArquivosImovel').empty();
            $('#inputArquivosImovel').val('');
            $('#btnUploadArquivosImovel').prop('disabled', true);

            // Carrega a lista de arquivos
            carregarListaArquivosImovel(imobId);

            // Mostra o modal
            const modal = $('#modalGerenciarDocsImovel');
            if (modal.length > 0) {
                modal.modal('show');
                console.log('Modal de imóvel exibido');
            } else {
                console.error('Modal de imóvel não encontrado!');
            }
        }

        // Função para carregar a lista de arquivos do imóvel
        function carregarListaArquivosImovel(imobId) {
            console.log('Carregando lista de arquivos para imóvel:', imobId);

            $.ajax({
                url: 'consultas/listar_arquivos_imovel.php',
                method: 'POST',
                data: {
                    imob_id: imobId
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Resposta da listagem de imóvel:', response);
                    if (response.success && response.arquivos && response.arquivos.length > 0) {
                        exibirListaArquivosImovel(response.arquivos);
                    } else {
                        $('#listaArquivosImovel').html('<div class="alert alert-info">Nenhum arquivo encontrado para este imóvel.</div>');
                    }
                },
                error: function() {
                    $('#listaArquivosImovel').html('<div class="alert alert-danger">Erro ao carregar arquivos.</div>');
                }
            });
        }

        // Função para exibir a lista de arquivos do imóvel
        function exibirListaArquivosImovel(arquivos) {
            const container = $('#listaArquivosImovel');
            container.empty();

            if (arquivos.length === 0) {
                container.html('<div class="alert alert-info">Nenhum arquivo encontrado nesta pasta.</div>');
                return;
            }

            arquivos.forEach(function(arquivo) {
                const extensao = arquivo.split('.').pop().toLowerCase();
                const icone = getIconeArquivo(extensao);

                const item = $(`
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="${icone} me-2"></i>
                            <span>${arquivo}</span>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-primary me-2" onclick="visualizarArquivoImovel('${arquivo}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="excluirArquivoImovel('${arquivo}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `);

                container.append(item);
            });
        }

        // Função para visualizar um arquivo do imóvel
        function visualizarArquivoImovel(nomeArquivo) {
            const caminho = `loteamentos_quadriculas/imoveis/${imovelAtualModal}/${nomeArquivo}`;
            window.open(caminho, '_blank');
        }

        // Função para excluir um arquivo do imóvel
        function excluirArquivoImovel(nomeArquivo) {
            if (!confirm(`Tem certeza que deseja excluir o arquivo "${nomeArquivo}"?`)) {
                return;
            }

            $.ajax({
                url: 'consultas/excluir_arquivo_imovel.php',
                method: 'POST',
                data: {
                    imob_id: imovelAtualModal,
                    arquivo: nomeArquivo
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Arquivo excluído com sucesso!');
                        carregarListaArquivosImovel(imovelAtualModal);
                    } else {
                        alert('Erro ao excluir arquivo: ' + response.message);
                    }
                },
                error: function() {
                    alert('Erro ao excluir arquivo.');
                }
            });
        }

        // Event listeners para o modal de imóveis
        $(document).ready(function() {
            // Habilita o botão de upload quando arquivos são selecionados
            $('#inputArquivosImovel').on('change', function() {
                const files = this.files;
                $('#btnUploadArquivosImovel').prop('disabled', files.length === 0);
            });

            // Upload de arquivos de imóveis
            $('#btnUploadArquivosImovel').on('click', function() {
                const files = $('#inputArquivosImovel')[0].files;
                if (files.length === 0) return;

                const formData = new FormData();
                formData.append('imob_id', imovelAtualModal);

                for (let i = 0; i < files.length; i++) {
                    formData.append('arquivos[]', files[i]);
                }

                // Mostra loading
                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Enviando...');

                console.log('Enviando arquivos para imóvel:', imovelAtualModal);

                $.ajax({
                    url: 'consultas/upload_arquivos_imovel.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        console.log('Resposta do servidor:', response);
                        if (response.success) {
                            alert('Arquivos enviados com sucesso!');
                            $('#inputArquivosImovel').val('');
                            carregarListaArquivosImovel(imovelAtualModal);
                        } else {
                            alert('Erro ao enviar arquivos: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro na requisição:', xhr, status, error);
                        console.error('Resposta do servidor:', xhr.responseText);
                        alert('Erro ao enviar arquivos.');
                    },
                    complete: function() {
                        $('#btnUploadArquivosImovel').prop('disabled', true).html('Adicionar Arquivos');
                    }
                });
            });
        });
    </script>

    <!-- Modal para gerenciar arquivos dos quarteirões -->
    <div class="modal fade" id="modalGerenciarDocs" tabindex="-1" aria-labelledby="modalGerenciarDocsLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalGerenciarDocsLabel">Gerenciar Documentos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="infoQuarteirao" class="mb-3">
                        <h6 id="nomeQuarteiraoModal"></h6>
                        <small class="text-muted" id="caminhoPastaModal"></small>
                    </div>

                    <!-- Área de upload -->
                    <div class="mb-4">
                        <label for="inputArquivos" class="form-label">Adicionar Arquivos</label>
                        <input type="file" class="form-control" id="inputArquivos" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif">
                        <div class="form-text">Selecione um ou mais arquivos para adicionar à pasta do quarteirão.</div>
                    </div>

                    <!-- Lista de arquivos -->
                    <div>
                        <h6>Arquivos Existentes</h6>
                        <div id="listaArquivos" class="list-group">
                            <!-- Arquivos serão carregados aqui dinamicamente -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" id="btnUploadArquivos" disabled>Adicionar Arquivos</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para gerenciar arquivos dos imóveis -->
    <div class="modal fade" id="modalGerenciarDocsImovel" tabindex="-1" aria-labelledby="modalGerenciarDocsImovelLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalGerenciarDocsImovelLabel">Gerenciar Documentos do Imóvel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="infoImovel" class="mb-3">
                        <h6 id="nomeImovelModal"></h6>
                        <small class="text-muted d-block" id="infoImovelModal"></small>
                        <small class="text-muted d-block" id="caminhoPastaImovelModal"></small>
                    </div>

                    <!-- Área de upload -->
                    <div class="mb-4">
                        <label for="inputArquivosImovel" class="form-label">Adicionar Arquivos</label>
                        <input type="file" class="form-control" id="inputArquivosImovel" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif">
                        <div class="form-text">Selecione um ou mais arquivos para adicionar à pasta do imóvel.</div>
                    </div>

                    <!-- Lista de arquivos -->
                    <div>
                        <h6>Arquivos Existentes</h6>
                        <div id="listaArquivosImovel" class="list-group">
                            <!-- Arquivos serão carregados aqui dinamicamente -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" id="btnUploadArquivosImovel" disabled>Adicionar Arquivos</button>
                </div>
            </div>
        </div>
    </div>

</body>

</html>