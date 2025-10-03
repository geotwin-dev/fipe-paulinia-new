<?php
session_start();

//include("verifica_login.php");
include("connection.php");

$loteamento = $_GET['loteamento'];
$arquivos = $_GET['arquivos'];
$quadricula = $_GET['quadricula'];

echo "<script>let dadosPdf = { 
    'loteamento': '$loteamento', 
    'arquivos': '$arquivos', 
    'quadricula': '$quadricula' };</script>";

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

    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <!-- Fabric.js para canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>

    <!-- Interact.js para manipulação -->
    <script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>

    <style>
        html,
        body {
            width: 100%;
            height: 100vh;
            margin: 0;
            padding: 0;
            background-color: rgb(63, 63, 63);
            box-sizing: border-box;
            overflow: hidden;
        }

        .canvas-container {
            position: fixed;
            top: 60px;
            left: 0;
            width: 100vw;
            height: calc(100vh - 60px);
            background: #2a2a2a;
            overflow: hidden;
        }

        #mainCanvas {
            display: block;
            background: #2a2a2a;
        }

        .toolbar-buttons {
            gap: 10px;
        }

        .toolbar-buttons .btn {
            min-width: 80px;
        }

        .mode-indicator {
            position: fixed;
            top: 70px;
            right: 20px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px;
            border-radius: 5px;
            z-index: 1001;
            display: none;
        }

        .controls-info {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 15px;
            border-radius: 5px;
            z-index: 1001;
            font-size: 12px;
            max-width: 300px;
        }

        .controls-info h6 {
            margin: 0 0 10px 0;
            color: #007bff;
        }

        .controls-info ul {
            margin: 0;
            padding-left: 15px;
        }

        .controls-info li {
            margin-bottom: 5px;
        }

        .controls-info.minimized {
            width: 40px;
            height: 40px;
            padding: 8px;
            cursor: pointer;
            overflow: hidden;
        }

        .controls-info.minimized .controls-content {
            display: none;
        }

        .controls-info.minimized::before {
            content: "🎮";
            font-size: 20px;
            display: block;
            text-align: center;
        }

        .btn.active-tool {
            background-color: #ffc107 !important;
            border-color: #ffc107 !important;
            color: #000 !important;
        }

        .controls-toggle {
            display: block;
            text-align: right;
            margin-bottom: 10px;
            cursor: pointer;
            color: #007bff;
            font-size: 12px;
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
                <div class="d-flex align-items-center flex-grow-1 gap-2 toolbar-buttons">
                    
                    <!-- Carregar PDFs -->
                    <input type="file" id="pdfInput" accept=".pdf" multiple style="display: none;">
                    <button id="btnCarregarPDF" class="btn btn-primary">
                        <i class="fas fa-file-pdf"></i> Carregar PDF
                    </button>

                    <!-- Ferramentas de desenho -->
                    <button id="btnIncluirLinha" class="btn btn-secondary">
                        <i class="fas fa-pencil-alt"></i> Polígono
                    </button>
                    <button id="btnIncluirMarcador" class="btn btn-secondary">
                        <i class="fas fa-map-marker-alt"></i> Marcador
                    </button>

                    <!-- Controles de modo -->
                    <button id="btnTravamento" class="btn btn-info">
                        <i class="fas fa-lock-open"></i> Travar PDFs
                    </button>
                    <button id="btnLimparDesenhos" class="btn btn-danger">
                        <i class="fas fa-eraser"></i> Limpar
                    </button>

                    <!-- Zoom controls -->
                    <div class="btn-group">
                        <button id="btnZoomIn" class="btn btn-outline-light">
                            <i class="fas fa-search-plus"></i>
                        </button>
                        <button id="btnZoomOut" class="btn btn-outline-light">
                            <i class="fas fa-search-minus"></i>
                        </button>
                        <button id="btnZoomReset" class="btn btn-outline-light">
                            <i class="fas fa-expand-arrows-alt"></i>
                        </button>
                        <button id="btnFindPDFs" class="btn btn-outline-light" title="Localizar PDFs">
                            <i class="fas fa-crosshairs"></i>
                        </button>
                    </div>

                    <input type="text" id="inputLoteAtual" class="form-control" style="width: 80px;" placeholder="Lote">

                </div>

            </div>
        </nav>

    </div>

    <!-- Indicador de modo -->
    <div id="modeIndicator" class="mode-indicator">
        Modo: Visualização
    </div>

    <!-- Container do canvas principal -->
    <div class="canvas-container">
        <canvas id="mainCanvas"></canvas>
    </div>

    <!-- Informações de controles -->
    <div id="controlsInfo" class="controls-info" onclick="toggleControls()">
        <div class="controls-content">
            <div class="controls-toggle">[ - ]</div>
            <h6>🎮 Controles do Canvas:</h6>
            <ul>
                <li><strong>Scroll:</strong> Zoom in/out (infinito)</li>
                <li><strong>Ctrl + Arrastar:</strong> Mover câmera</li>
                <li><strong>Botão direito + Arrastar:</strong> Mover câmera</li>
                <li><strong>🎯 Localizar:</strong> Encontra PDFs perdidos</li>
            </ul>
            <h6>📄 Controles do PDF:</h6>
            <ul>
                <li><strong>Quinas:</strong> Redimensionar (mantém proporção)</li>
                <li><strong>Centro:</strong> Mover PDF</li>
                <li><strong>R:</strong> Rotacionar 15° (PDF selecionado)</li>
                <li><strong>Shift+R:</strong> Rotacionar -15° (PDF selecionado)</li>
            </ul>
            <h6>🖊️ Ferramentas de Desenho:</h6>
            <ul>
                <li><strong>Polígono:</strong> Clique para adicionar vértices</li>
                <li><strong>Fechar:</strong> Duplo-clique no último ponto</li>
                <li><strong>Marcador:</strong> Clique para adicionar pontos</li>
            </ul>
        </div>
    </div>

    <!-- Script principal do visualizador de PDF -->
    <script src="pdfViewer.js"></script>
    
    <script>
        // Função para minimizar/expandir controles
        function toggleControls() {
            const controlsInfo = document.getElementById('controlsInfo');
            controlsInfo.classList.toggle('minimized');
        }
    </script>
</body>

</html>