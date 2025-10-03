<?php
session_start();

include("verifica_login.php");
include("connection.php");

$quadricula = $_GET['quadricula'];
$loteamento = $_GET['loteamento'];
$pdf = $_GET['pdf'];

echo "<script>
    let quadricula = '$quadricula';
    let loteamento = '$loteamento';
    let pdf = '$pdf';
</script>";

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

        .divContainerMap {
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .divContainerPDF {
            width: 100%;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        #mainCanvasIntegrado {
            width: 100%;
            height: 100%;
        }

        /* Container dos Quadros */
        .container-quadros {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        /* Quadro de PDFs */
        .quadro-pdfs {
            width: 300px;
            max-height: 400px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: block;
            margin-bottom: 20px;
        }

        .quadro-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            border-radius: 8px 8px 0 0;
        }

        .quadro-header h6 {
            margin: 0;
            font-weight: 600;
            color: #495057;
        }

        .btn-close {
            background: none;
            border: none;
            font-size: 20px;
            color: #6c757d;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-close:hover {
            color: #dc3545;
        }

        .quadro-body {
            max-height: 320px;
            overflow-y: auto;
        }

        .lista-pdfs {
            padding: 8px;
        }

        .item-pdf {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            margin: 2px 0;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .item-pdf:hover {
            background: #f8f9fa;
            border-color: #007bff;
        }

        .item-pdf.ativo {
            background: #007bff;
            color: white;
            border-color: #0056b3;
        }

        .item-pdf i {
            margin-right: 8px;
            font-size: 14px;
        }

        .item-pdf .nome-pdf {
            font-size: 13px;
            font-weight: 500;
            flex: 1;
            word-break: break-word;
        }

        /* Quadro de Quarteirões - IGUAL ao quadro de PDFs */
        .quadro-quarteiroes {
            width: 300px;
            max-height: 400px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: none;
        }

        .quadro-quarteiroes .quadro-body {
            max-height: 320px;
            overflow-y: auto;
        }

        .lista-quarteiroes {
            padding: 8px;
        }

        .item-quarteirao {
            padding: 8px 12px;
            margin: 2px 0;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .item-quarteirao:hover {
            background: #f8f9fa;
            border-color: #007bff;
        }

        .item-quarteirao input[type="radio"] {
            margin-top: 2px;
            margin-right: 8px;
        }

        .item-quarteirao label {
            margin: 0;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
        }

        .item-quarteirao small {
            display: block;
            color: #6c757d;
            font-size: 11px;
            margin-top: 2px;
        }

        /* Estilos para as quadras */
        .quadras-container {
            margin-top: 8px;
            padding-left: 20px;
            margin-left: 20px;
        }

        .quadras-label {
            font-size: 11px;
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .quadras-list {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .item-quadra {
            display: flex;
            align-items: center;
            padding: 2px 0;
        }

        .item-quadra input[type="radio"] {
            margin-right: 6px;
            margin-top: 0;
        }

        .item-quadra label {
            font-size: 12px;
            color: #495057;
            cursor: pointer;
            margin: 0;
        }

        .item-quadra:hover label {
            color: #007bff;
        }

        /* Estilo para o quarteirão principal */
        .quarteirao-main {
            display: flex;
            align-items: center;
            margin-bottom: 0;
        }

        .quarteirao-main label {
            margin-bottom: 0;
            font-weight: 500;
        }

        /* Estilo para quarteirões personalizados */
        .quarteirao-personalizado {
            border-left: 4px solid #28a745 !important;
            background: linear-gradient(90deg, rgba(40, 167, 69, 0.05) 0%, transparent 100%);
        }

        .quarteirao-personalizado:hover {
            background: linear-gradient(90deg, rgba(40, 167, 69, 0.1) 0%, rgba(233, 236, 239, 0.5) 100%) !important;
        }

        /* Estilo para informações do polígono */
        .info-item {
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }

        .info-item i {
            margin-right: 8px;
            width: 20px;
        }

        .info-item strong {
            margin-right: 8px;
        }

        /* Prevenir menu de contexto no canvas */
        #divContainerPDF,
        #divContainerPDF canvas {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            -webkit-touch-callout: none;
            -webkit-tap-highlight-color: transparent;
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
                    <button id="btnAdicionarQuarteirao" class="btn btn-success btn-sm">
                        <i class="fas fa-plus"></i> Adicionar Quarteirão
                    </button>
                    <button id="btnTravamentoSimplificado" class="btn btn-info btn-sm">
                        <i class="fas fa-lock-open"></i> Travar PDF
                    </button>
                    <button id="btnRotateLeftSimplificado" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-undo"></i> Rotacionar Esquerda
                    </button>
                    <button id="btnRotateRightSimplificado" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-redo"></i> Rotacionar Direita
                    </button>
                    <button id="btnIncluirMarcadorSimplificado" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-plus-circle"></i> Marcador
                    </button>
                    <input type="text" id="inputTextoMarcador" class="form-control form-control-sm" value="1" style="display: none; width: 80px;" placeholder="1">
                    <button id="btnPoligonoSimplificado" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-draw-polygon"></i> Polígono
                    </button>
                    <button id="btnDeletarSimplificado" class="btn btn-outline-danger btn-sm" style="display: none;">
                        <i class="fas fa-trash"></i> Deletar
                    </button>
                    <button id="btnEditarSimplificado" class="btn btn-outline-warning btn-sm" style="display: none;">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                    <button id="btnSairModoSimplificado" class="btn btn-outline-secondary btn-sm" style="display: none;">
                        <i class="fas fa-times"></i> Sair do Modo
                    </button>
                </div>

            </div>
        </nav>
        <div class="divContainerPDF" oncontextmenu="return false;">
            <canvas id="mainCanvasIntegrado" oncontextmenu="return false;"></canvas>

            <!-- Container dos Quadros -->
            <div class="container-quadros">
                <!-- Quadro de PDFs -->
                <div id="quadroPDFs" class="quadro-pdfs">
                    <div class="quadro-header">
                        <h6>PDFs do Loteamento</h6>
                        <button id="btnFecharQuadro" class="btn-close" type="button">&times;</button>
                    </div>
                    <div class="quadro-body">
                        <div id="listaPDFs" class="lista-pdfs">
                            <!-- PDFs serão carregados aqui -->
                        </div>
                    </div>
                </div>

                <!-- Quadro de Quarteirões -->
                <div id="quadroQuarteiroes" class="quadro-quarteiroes" style="display: none;">
                    <div class="quadro-header">
                        <h6>Quarteirões do <span id="nomeLoteamentoQuarteiroes"></span></h6>
                        <button id="btnFecharQuadroQuarteiroes" class="btn-close" type="button">&times;</button>
                    </div>
                    <div class="quadro-body">
                        <div id="listaQuarteiroes" class="lista-quarteiroes">
                            <!-- Quarteirões serão carregados aqui -->
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal para Adicionar Quarteirão -->
    <div class="modal fade" id="modalAdicionarQuarteirao" tabindex="-1" aria-labelledby="modalAdicionarQuarteiraoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAdicionarQuarteiraoLabel">
                        <i class="fas fa-plus-circle text-success"></i> Adicionar Novo Quarteirão
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formAdicionarQuarteirao">
                        <div class="mb-3">
                            <label for="inputNomeQuarteirao" class="form-label">
                                <i class="fas fa-map-marker-alt"></i> Nome do Quarteirão
                            </label>
                            <input type="text" class="form-control" id="inputNomeQuarteirao" 
                                   placeholder="Ex: 9999" required>
                            <div class="form-text">Digite o nome/identificador do quarteirão</div>
                        </div>
                        <div class="mb-3">
                            <label for="inputQuadras" class="form-label">
                                <i class="fas fa-th"></i> Quadras
                            </label>
                            <input type="text" class="form-control" id="inputQuadras" 
                                   placeholder="Ex: A, B, C, S/D" required>
                            <div class="form-text">Digite as quadras separadas por vírgula (ex: A, B, C, S/D)</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-success" id="btnConfirmarAdicionarQuarteirao">
                        <i class="fas fa-plus"></i> Adicionar Quarteirão
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Informações do Polígono -->
    <div class="modal fade" id="modalInfoPoligono" tabindex="-1" aria-labelledby="modalInfoPoligonoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalInfoPoligonoLabel">
                        <i class="fas fa-info-circle"></i> Informações do Polígono
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <i class="fas fa-map-marker-alt text-primary"></i>
                                <strong>Quarteirão:</strong>
                                <span id="infoQuarteirao" class="text-muted">-</span>
                                <input type="text" id="editQuarteirao" class="form-control mt-2" style="display: none;" placeholder="Digite o quarteirão">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <i class="fas fa-th text-success"></i>
                                <strong>Quadra:</strong>
                                <span id="infoQuadra" class="text-muted">-</span>
                                <input type="text" id="editQuadra" class="form-control mt-2" style="display: none;" placeholder="Digite a quadra">
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <i class="fas fa-user text-info"></i>
                                <strong>Usuário:</strong>
                                <span id="infoUsuario" class="text-muted">-</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <i class="fas fa-calendar text-warning"></i>
                                <strong>Data de Criação:</strong>
                                <span id="infoDataCriacao" class="text-muted">-</span>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <div class="info-item">
                                <i class="fas fa-file-pdf text-danger"></i>
                                <strong>PDF:</strong>
                                <span id="infoPDF" class="text-muted">-</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Fechar
                    </button>
                    <button type="button" id="btnEditarPoligono" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                    <button type="button" id="btnSalvarPoligono" class="btn btn-success" style="display: none;">
                        <i class="fas fa-save"></i> Salvar
                    </button>
                    <button type="button" id="btnCancelarEdicao" class="btn btn-warning" style="display: none;">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ===================================
        // PDF VIEWER SIMPLIFICADO - COPIADO EXATAMENTE DO PDFVIEWERINTEGRADO.JS
        // ===================================

        class PDFViewerSimplificado {
            
            constructor() {
                this.mainCanvas = null;
                
                // ===================================
                // CONFIGURAÇÕES DE BUFFER DE CLIQUE
                // ===================================
                this.BUFFER_CLIQUE_POLIGONO = 15; // Buffer para clique em polígono (em pixels)
                this.currentPDF = null;
                this.currentMode = 'view'; // view, marker
                this.isDragging = false;
                this.listaPDFs = []; // Lista de PDFs do loteamento
                this.pdfComErro = null; // PDF que teve erro ao carregar
                this.quarteiroesPersonalizados = []; // Lista de quarteirões adicionados pelo usuário

                // Obter PDF atual da URL
                const urlParams = new URLSearchParams(window.location.search);
                this.pdfAtual = urlParams.get('pdf') || '';

                // Configuração dos marcadores
                this.config_marcador = {
                    width: 30,
                    height: 25,
                    borderRadius: 5,
                    fill: '#0000FF', // Azul inicial
                    stroke: 'black',
                    strokeWidth: 2,
                    fontSize: 16,
                    textColor: 'white', // Texto branco
                    selectable: false,
                    evented: false
                };

                // Configuração dos polígonos
                this.config_poligono = {
                    // Configurações da linha do polígono
                    stroke: 'blue', // Cor da linha (azul)
                    strokeWidth: 3, // Grossura da linha (volta ao original)
                    fill: 'transparent', // Cor de fundo (transparente)
                    selectable: false, // Não selecionável quando finalizado
                    evented: false, // Não interativo quando finalizado

                    // Configurações dos vértices temporários
                    vertex: {
                        radius: 4, // Tamanho do vértice
                        fill: '#2196F3', // Cor do vértice (laranja)
                        stroke: 'blue', // Cor da borda do vértice
                        strokeWidth: 2, // Grossura da borda do vértice
                        selectable: false, // Não selecionável
                        evented: false // Não interativo
                    },

                    // Configurações das linhas temporárias
                    tempLine: {
                        stroke: 'blue', // Cor da linha temporária
                        strokeWidth: 2, // Grossura da linha temporária
                        selectable: false, // Não selecionável
                        evented: false // Não interativo
                    }
                };


                // Estado do polígono em desenho (igual ao pdfViewerIntegrado.js)
                this.isDrawingPolygon = false;
                this.polygonPoints = [];
                this.tempPolygonLines = [];

                this.init();
            }

            async init() {
                // Inicializar canvas
                this.initMainCanvas();

                // Setup eventos
                this.setupEvents();

                // Carregar lista de PDFs
                await this.carregarListaPDFs();

                // Carregar quarteirões
                await this.carregarQuarteiroes();

                // Carregar PDF
                await this.loadPDF();
            }

            initMainCanvas() {
                const canvasElement = document.getElementById('mainCanvasIntegrado');
                const container = canvasElement.parentElement;

                if (this.mainCanvas) {
                    this.mainCanvas.dispose();
                }

                // Canvas dimensions
                canvasElement.width = container.clientWidth;
                canvasElement.height = container.clientHeight;

                // Create Fabric canvas
                this.mainCanvas = new fabric.Canvas('mainCanvasIntegrado', {
                    backgroundColor: '#2a2a2a',
                    selection: false,
                    preserveObjectStacking: true,
                    width: container.clientWidth,
                    height: container.clientHeight
                });

                //console.log('✅ Fabric.js canvas inicializado');
            }

            setupEvents() {
                // Eventos de desenho
                this.mainCanvas.on('mouse:down', (options) => {
                    this.handleCanvasClick(options);
                });

                // Configurar eventos de clique direito após inicialização do canvas
                this.setupContextMenuEvents();

                // Botão fechar quadro PDFs
                document.getElementById('btnFecharQuadro')?.addEventListener('click', () => {
                    this.hideQuadroPDFs();
                });

                // Botão fechar quadro quarteirões
                document.getElementById('btnFecharQuadroQuarteiroes')?.addEventListener('click', () => {
                    this.hideQuadroQuarteiroes();
                });

                // Botão adicionar quarteirão
                document.getElementById('btnAdicionarQuarteirao')?.addEventListener('click', () => {
                    this.showModalAdicionarQuarteirao();
                });

                // Botão confirmar adicionar quarteirão
                document.getElementById('btnConfirmarAdicionarQuarteirao')?.addEventListener('click', () => {
                    this.adicionarQuarteiraoPersonalizado();
                });

                // Botão travar
                document.getElementById('btnTravamentoSimplificado')?.addEventListener('click', () => {
                    this.lockCurrentPDF();
                });

                // Botões de rotação
                document.getElementById('btnRotateLeftSimplificado')?.addEventListener('click', () => {
                    this.rotatePDF(-90);
                });

                document.getElementById('btnRotateRightSimplificado')?.addEventListener('click', () => {
                    this.rotatePDF(90);
                });

                // Botão marcador
                document.getElementById('btnIncluirMarcadorSimplificado')?.addEventListener('click', () => {
                    this.toggleMode('marker');
                });

                document.getElementById('btnPoligonoSimplificado')?.addEventListener('click', () => {
                    this.toggleMode('polygon');
                });

                // Botão deletar
                document.getElementById('btnDeletarSimplificado')?.addEventListener('click', () => {
                    this.toggleMode('delete');
                });

                // Botão editar
                document.getElementById('btnEditarSimplificado')?.addEventListener('click', () => {
                    this.toggleMode('edit');
                });

                // Botão sair do modo
                document.getElementById('btnSairModoSimplificado')?.addEventListener('click', () => {
                    // Sair do modo atual
                    if (this.currentMode === 'edit') {
                        this.toggleMode('edit'); // Sair do modo edição
                    } else if (this.currentMode === 'delete') {
                        this.toggleMode('delete'); // Sair do modo deletar
                    } else if (this.currentMode === 'marker') {
                        this.toggleMode('marker'); // Sair do modo marcador
                    } else if (this.currentMode === 'polygon') {
                        this.toggleMode('polygon'); // Sair do modo polígono
                    }
                });

                // Input de texto do marcador
                document.getElementById('inputTextoMarcador')?.addEventListener('input', (e) => {
                    this.handleInputChange(e);
                });

                // Zoom infinito com scroll
                this.setupInfiniteZoom();

                // Pan com Ctrl+Drag ou clique direito
                this.setupPanControls();
            }

            setupInfiniteZoom() {
                this.mainCanvas.on('mouse:wheel', (opt) => {
                    const delta = opt.e.deltaY;
                    let zoom = this.mainCanvas.getZoom();

                    // Zoom factor
                    zoom *= 0.999 ** delta;

                    // Limit zoom (optional - remove for truly infinite)
                    if (zoom > 20) zoom = 20;
                    if (zoom < 0.01) zoom = 0.01;

                    // Zoom to point
                    this.mainCanvas.zoomToPoint({
                        x: opt.e.offsetX,
                        y: opt.e.offsetY
                    }, zoom);

                    // Force render after zoom
                    this.mainCanvas.renderAll();

                    opt.e.preventDefault();
                    opt.e.stopPropagation();

                    //console.log(`Zoom aplicado: ${Math.round(zoom * 100)}%`);
                });
            }

            setupPanControls() {
                let lastPosX = 0;
                let lastPosY = 0;
                let startPosX = 0;
                let startPosY = 0;
                let hasMoved = false;

                this.mainCanvas.on('mouse:down.pan', (opt) => {
                    const evt = opt.e;

                    // Pan with Ctrl+Click or Right Click
                    if (evt.ctrlKey || evt.button === 2) {
                        this.isDragging = true;
                        this.mainCanvas.selection = false;
                        lastPosX = evt.clientX;
                        lastPosY = evt.clientY;
                        startPosX = evt.clientX;
                        startPosY = evt.clientY;
                        hasMoved = false;
                        opt.e.preventDefault();
                        return;
                    }
                });

                this.mainCanvas.on('mouse:move.pan', (opt) => {
                    if (this.isDragging) {
                        const e = opt.e;
                        const deltaX = e.clientX - startPosX;
                        const deltaY = e.clientY - startPosY;

                        // Se moveu mais de 5 pixels, considera como pan
                        if (Math.abs(deltaX) > 5 || Math.abs(deltaY) > 5) {
                            hasMoved = true;
                        }

                        // CORREÇÃO: Mover apenas a câmera (viewportTransform)
                        // Não mover o PDF - ele fica fixo no canvas
                        const vpt = this.mainCanvas.viewportTransform;
                        vpt[4] += e.clientX - lastPosX;
                        vpt[5] += e.clientY - lastPosY;
                        this.mainCanvas.requestRenderAll();
                        lastPosX = e.clientX;
                        lastPosY = e.clientY;
                        opt.e.preventDefault();
                    }
                });

                this.mainCanvas.on('mouse:up.pan', (opt) => {
                    if (this.isDragging) {
                        // Se moveu durante o pan, não adiciona marcador
                        if (hasMoved) {
                            // Pan detectado - não adicionando marcador
                        }

                        this.isDragging = false;
                        this.mainCanvas.selection = false;
                        this.mainCanvas.renderAll();

                        // Reset após um pequeno delay
                        setTimeout(() => {
                            hasMoved = false;
                        }, 100);
                    }
                });
            }

            // ===================================
            // SISTEMA DE DESENHO SIMPLIFICADO
            // ===================================

            handleCanvasClick(options) {
                if (!this.currentPDF) return;

                // Ignore if panning
                if (this.isDragging) return;

                // Ignore pan triggers
                const evt = options.e;
                if (evt.ctrlKey) return;

                // Verificar se está no modo deletar
                const deleteBtn = document.getElementById('btnDeletarSimplificado');
                if (deleteBtn && deleteBtn.classList.contains('active-tool')) {
                    const pointer = this.mainCanvas.getPointer(options.e);
                    this.deleteNearestObject(pointer.x, pointer.y);
                    return;
                }

                // Verificar se está no modo editar
                const editBtn = document.getElementById('btnEditarSimplificado');
                if (editBtn && editBtn.classList.contains('active-tool')) {
                    // No modo edição, não fazemos nada no clique - apenas drag & drop
                    return;
                }

                // Verificar se está no modo padrão (view) - clicar em polígono para ver informações
                if (this.currentMode === 'view') {
                    const pointer = this.mainCanvas.getPointer(options.e);
                    const nearestPolygon = this.findNearestPolygon(pointer.x, pointer.y);
                    
                    // Verificar se encontrou um polígono próximo
                    if (nearestPolygon) {
                        this.mostrarInfoPoligono(nearestPolygon);
                        return;
                    }
                }

                // Só adiciona marcador se estiver no modo correto e não estiver arrastando
                if (this.currentMode === 'marker') {
                    // Pequeno delay para verificar se vai arrastar
                    setTimeout(async () => {
                        if (!this.isDragging) {
                            const pointer = this.mainCanvas.getPointer(options.e);
                            await this.addMarker(pointer.x, pointer.y);
                        }
                    }, 50);
                } else if (this.currentMode === 'polygon') {
                    // Clique esquerdo para adicionar vértice (igual ao pdfViewerIntegrado.js)
                    if (evt.button === 0) {
                        const pointer = this.mainCanvas.getPointer(options.e);
                        this.addPolygonVertex(pointer.x, pointer.y);
                    }
                }
            }

            setupContextMenuEvents() {
                // Clique direito para fechar polígono (igual ao pdfViewerIntegrado.js)
                this.mainCanvas.wrapperEl.addEventListener('contextmenu', async (e) => {
                    e.preventDefault();
                    if (this.currentMode === 'polygon' && this.isDrawingPolygon) {
                        await this.finalizePolygon();
                    }
                });
            }

            // Função para iniciar desenho de polígono (igual ao pdfViewerIntegrado.js)
            iniciarDesenhoPoligono() {
                console.log('=== INICIANDO DESENHO DE POLÍGONO ===');

                // Resetar estado de polígono
                this.resetPolygonState();

                // Definir modo
                this.currentMode = 'polygon';

                // Atualizar botões
                this.updateButtonsState();

                console.log('Modo polígono ativado');
            }

            async addMarker(x, y) {
                // Obter texto do input
                const inputTexto = document.getElementById('inputTextoMarcador');
                const texto = inputTexto.value || '1';

                // INCREMENTAR INPUT INSTANTANEAMENTE
                this.incrementarContador();

                // Criar marcador CINZA inicialmente (pendente)
                const marker = new fabric.Rect({
                    left: x - (this.config_marcador.width / 2), // Centralizar
                    top: y - (this.config_marcador.height / 2), // Centralizar
                    width: this.config_marcador.width,
                    height: this.config_marcador.height,
                    fill: 'gray', // COR CINZA INICIAL
                    stroke: this.config_marcador.stroke,
                    strokeWidth: this.config_marcador.strokeWidth,
                    rx: this.config_marcador.borderRadius, // Cantos arredondados
                    ry: this.config_marcador.borderRadius, // Cantos arredondados
                    selectable: this.config_marcador.selectable,
                    evented: this.config_marcador.evented,
                    isCustomMarker: true,
                    markerStatus: 'pending' // Status: pending, success, error
                });

                // Adicionar texto no centro
                const text = new fabric.IText(texto, {
                    left: x,
                    top: y,
                    fontSize: this.config_marcador.fontSize,
                    fontFamily: 'Arial',
                    fontWeight: 'bold',
                    fill: this.config_marcador.textColor,
                    textAlign: 'center',
                    originX: 'center',
                    originY: 'center',
                    selectable: false,
                    evented: false,
                    isCustomMarker: true,
                    editable: false,
                    lockMovementX: true,
                    lockMovementY: true,
                    lockRotation: true,
                    lockScalingX: true,
                    lockScalingY: true
                });

                // Criar grupo com retângulo e texto
                const grupo = new fabric.Group([marker, text], {
                    left: x,
                    top: y,
                    originX: 'center',
                    originY: 'center',
                    selectable: false,
                    evented: false,
                    isCustomMarker: true,
                    markerStatus: 'pending', // Status do grupo
                    markerData: { x, y, texto, id: null }, // Dados completos
                    zIndex: 10 // Marcadores ficam por cima dos polígonos
                });

                // Ajustar tamanho dinamicamente baseado no texto
                this.ajustarTamanhoMarcador(grupo, texto);

                // Adicionar ao canvas IMEDIATAMENTE
                this.mainCanvas.add(grupo);
                
                // Garantir que marcador fique por cima de todos os polígonos
                this.mainCanvas.bringToFront(grupo);
                
                this.mainCanvas.renderAll();

                // Salvar marcador no banco de dados (ASSÍNCRONO)
                this.salvarMarcadorNoBanco(x, y, texto, grupo);
            }

            // ===================================
            // SISTEMA DE POLÍGONOS
            // ===================================

            iniciarPoligono() {
                this.poligonoEmDesenho = null;
                this.verticesPoligono = [];
                this.modoPoligono = true;
                console.log('Modo polígono ativado');
            }

            cancelarPoligono() {
                // Resetar estado de polígono (igual ao pdfViewerIntegrado.js)
                this.resetPolygonState();
                console.log('Polígono cancelado');
            }

            // Implementação igual ao pdfViewerIntegrado.js
            addPolygonVertex(x, y) {
                console.log('Adicionando vértice:', x, y);

                this.polygonPoints.push({
                    x,
                    y
                });

                // Visual feedback - temporary point
                const point = new fabric.Circle({
                    left: x - this.config_poligono.vertex.radius,
                    top: y - this.config_poligono.vertex.radius,
                    radius: this.config_poligono.vertex.radius,
                    fill: this.config_poligono.vertex.fill,
                    stroke: this.config_poligono.vertex.stroke,
                    strokeWidth: this.config_poligono.vertex.strokeWidth,
                    selectable: this.config_poligono.vertex.selectable,
                    evented: this.config_poligono.vertex.evented,
                    isPolygonVertex: true
                });

                this.mainCanvas.add(point);

                // Draw line to previous point
                if (this.polygonPoints.length > 1) {
                    const prevPoint = this.polygonPoints[this.polygonPoints.length - 2];
                    const line = new fabric.Line([prevPoint.x, prevPoint.y, x, y], {
                        stroke: this.config_poligono.tempLine.stroke,
                        strokeWidth: this.config_poligono.tempLine.strokeWidth,
                        strokeDashArray: [5, 5],
                        selectable: this.config_poligono.tempLine.selectable,
                        evented: this.config_poligono.tempLine.evented,
                        isPolygonLine: true
                    });

                    this.tempPolygonLines.push(line);
                    this.mainCanvas.add(line);
                }

                if (!this.isDrawingPolygon) {
                    this.isDrawingPolygon = true;
                    console.log('Iniciando desenho de polígono - clique direito para finalizar');
                }

                console.log(`Total de pontos: ${this.polygonPoints.length}`);
            }

            async finalizePolygon() {
                console.log('=== FINALIZANDO POLÍGONO ===');
                console.log('Pontos atuais:', this.polygonPoints.length);

                if (this.polygonPoints.length < 3) {
                    console.warn('⚠️ Polígono precisa de pelo menos 3 pontos');
                    return;
                }

                // Remove temporary vertices and lines
                const tempVertices = this.mainCanvas.getObjects().filter(obj => obj.isPolygonVertex);
                const tempLines = this.mainCanvas.getObjects().filter(obj => obj.isPolygonLine);

                tempVertices.forEach(vertex => this.mainCanvas.remove(vertex));
                tempLines.forEach(line => this.mainCanvas.remove(line));

                // Create polygon
                const polygon = new fabric.Polygon(this.polygonPoints, {
                    fill: this.config_poligono.fill,
                    stroke: this.config_poligono.stroke,
                    strokeWidth: 3, // Volta ao original
                    strokeDashArray: null, // Sem linha tracejada
                    selectable: this.config_poligono.selectable,
                    evented: this.config_poligono.evented,
                    isCustomPolygon: true,
                    polygonData: { points: this.polygonPoints, id: null }, // Dados completos
                    zIndex: 1 // Polígonos ficam atrás dos marcadores
                });

                this.mainCanvas.add(polygon);

                // Save polygon data and get ID
                const result = await this.savePolygonData();
                
                // Definir ID no objeto
                polygon.polygonData.id = result.poligono_id;

                console.log('✅ Polígono finalizado');

                // Reset for next polygon
                this.polygonPoints = [];
                this.isDrawingPolygon = false;

                // Keep polygon mode active for drawing more polygons
                console.log('Modo polígono continua ativo - pode desenhar outro');
            }

            resetPolygonState() {
                // Reset polygon drawing state
                this.isDrawingPolygon = false;
                this.polygonPoints = [];
                this.tempPolygonLines = [];

                // Remove temporary vertices and lines
                const tempVertices = this.mainCanvas.getObjects().filter(obj => obj.isPolygonVertex);
                const tempLines = this.mainCanvas.getObjects().filter(obj => obj.isPolygonLine);

                tempVertices.forEach(vertex => this.mainCanvas.remove(vertex));
                tempLines.forEach(line => this.mainCanvas.remove(line));
            }

            async savePolygonData() {
                try {
                    const urlParams = new URLSearchParams(window.location.search);
                    const selecao = this.getSelecaoAtual();

                    const dadosPoligono = {
                        quadricula: urlParams.get('quadricula'),
                        loteamento: urlParams.get('loteamento'),
                        pdf: this.pdfAtual,
                        quarteirao: selecao.quarteirao,
                        quadra: selecao.quadra,
                        pontos: JSON.stringify(this.polygonPoints)
                    };

                    const response = await fetch('salvar_poligono.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(dadosPoligono)
                    });

                    if (!response.ok) {
                        throw new Error('Erro ao salvar polígono');
                    }

                    const result = await response.json();
                    if (!result.success) {
                        throw new Error(result.message || 'Erro ao salvar polígono');
                    }

                    console.log('✅ Polígono salvo no banco:', result);
                    return result;
                } catch (error) {
                    console.error('❌ Erro ao salvar polígono:', error);
                    throw error;
                }
            }

            async salvarPoligonoNoBanco() {
                console.log('=== SALVANDO POLÍGONO NO BANCO ===');
                try {
                    const urlParams = new URLSearchParams(window.location.search);

                    // Obter seleção atual de quarteirão e quadra
                    const selecao = this.getSelecaoAtual();

                    // Obter pontos do polígono
                    const pontos = this.poligonoEmDesenho.points.map(ponto => ({
                        x: ponto.x,
                        y: ponto.y
                    }));

                    const dadosPoligono = {
                        quadricula: urlParams.get('quadricula'),
                        loteamento: urlParams.get('loteamento'),
                        pdf: this.pdfAtual,
                        quarteirao: selecao.quarteirao,
                        quadra: selecao.quadra,
                        pontos: JSON.stringify(pontos)
                    };

                    const response = await fetch('salvar_poligono.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(dadosPoligono)
                    });

                    if (!response.ok) {
                        throw new Error('Erro ao salvar polígono');
                    }

                    const result = await response.json();
                    if (!result.success) {
                        throw new Error(result.message || 'Erro ao salvar polígono');
                    }

                    console.log('Polígono salvo com sucesso:', result);
                    return result;
                } catch (error) {
                    console.error('Erro ao salvar polígono:', error);
                    throw error;
                }
            }

            ajustarTamanhoMarcador(grupo, texto) {
                // Calcular largura baseada no texto
                const textoLength = texto.length;
                const larguraBase = this.config_marcador.width;
                const larguraExtra = textoLength > 1 ? (textoLength - 1) * 8 : 0;
                const novaLargura = Math.max(larguraBase, larguraBase + larguraExtra);

                // Obter elementos do grupo
                const objetos = grupo.getObjects();
                const retangulo = objetos.find(obj => obj.type === 'rect');
                const textoObj = objetos.find(obj => obj.type === 'i-text');

                if (retangulo && textoObj) {
                    // Ajustar largura do retângulo
                    retangulo.set({
                        width: novaLargura,
                        left: -novaLargura / 2
                    });

                    // Ajustar fonte do texto se necessário
                    if (textoLength > 2) {
                        const fontSizeMinimo = Math.max(12, this.config_marcador.fontSize - (textoLength - 2) * 2);
                        textoObj.set({
                            fontSize: fontSizeMinimo
                        });
                    }
                }

                // Atualizar grupo
                grupo.setCoords();
            }

            incrementarContador() {
                const inputTexto = document.getElementById('inputTextoMarcador');
                const valorAtual = inputTexto.value;

                // Verificar se é um número inteiro
                if (/^\d+$/.test(valorAtual)) {
                    const numero = parseInt(valorAtual);
                    inputTexto.value = numero + 1;
                }
            }

            handleInputChange(e) {
                // Permitir qualquer texto no input
                // A validação de incremento é feita apenas no incrementarContador()
            }

            toggleMode(mode) {
                if (this.currentMode === mode) {
                    // Exit mode
                    this.currentMode = 'view';
                    // Se estava em modo polígono, cancelar desenho
                    if (mode === 'polygon') {
                        this.cancelarPoligono();
                    }
                    // Se estava em modo edição, sair do modo de edição
                    if (mode === 'edit') {
                        this.exitEditMode();
                    }
                    this.updateButtonsState();
                } else {
                    // Enter mode
                    this.currentMode = mode;
                    // Se entrou em modo polígono, inicializar estado
                    if (mode === 'polygon') {
                        this.resetPolygonState();
                    }
                    // Se entrou em modo edição, ativar modo de edição
                    if (mode === 'edit') {
                        this.enterEditMode();
                    }
                    this.updateButtonsState();
                }
            }

            rotatePDF(degrees) {
                if (!this.currentPDF || this.currentPDF.isLocked) {
                    return;
                }

                const obj = this.currentPDF.fabricObject;
                const currentAngle = obj.angle || 0;
                const newAngle = currentAngle + degrees;

                // Salvar estado atual do viewport (zoom e posição)
                const currentZoom = this.mainCanvas.getZoom();
                const currentVpt = this.mainCanvas.viewportTransform.slice();

                // Aplicar rotação
                obj.set({
                    angle: newAngle
                });

                // Manter o zoom atual e apenas ajustar a posição se necessário
                this.mainCanvas.setZoom(currentZoom);
                this.mainCanvas.setViewportTransform(currentVpt);

                // Renderizar
                this.mainCanvas.renderAll();
            }

            async lockCurrentPDF() {
                if (!this.currentPDF) {
                    return;
                }

                try {
                    const obj = this.currentPDF.fabricObject;

                    // PRIMEIRO: Definir como travado
                    this.currentPDF.isLocked = true;

                    // SEGUNDO: Aplicar travamento no objeto Fabric.js
                    obj.set({
                        lockMovementX: true,
                        lockMovementY: true,
                        lockRotation: true
                    });

                    // TERCEIRO: Salvar configurações no banco
                    await this.salvarConfiguracoesPDF();

                    // Atualizar UI
                    this.updateButtonsVisibility();

                } catch (error) {
                    // Reverter o estado se houver erro
                    this.currentPDF.isLocked = false;
                }
            }

            updateButtonsVisibility() {
                const isLocked = this.currentPDF?.isLocked;
                const hasPDF = !!this.currentPDF;

                // Verificar se quarteirão e quadra estão selecionados
                const selecao = this.getSelecaoAtual();
                const temSelecaoCompleta = selecao.quarteirao && selecao.quadra;

                console.log('updateButtonsVisibility - PDF:', hasPDF, 'Locked:', isLocked, 'Seleção:', selecao, 'Completa:', temSelecaoCompleta);

                const markerBtn = document.getElementById('btnIncluirMarcadorSimplificado');
                const polygonBtn = document.getElementById('btnPoligonoSimplificado');
                const deleteBtn = document.getElementById('btnDeletarSimplificado');
                const editBtn = document.getElementById('btnEditarSimplificado');
                const lockBtn = document.getElementById('btnTravamentoSimplificado');
                const rotateLeftBtn = document.getElementById('btnRotateLeftSimplificado');
                const rotateRightBtn = document.getElementById('btnRotateRightSimplificado');

                if (hasPDF) {
                    // SEMPRE remover disabled quando há PDF
                    lockBtn?.removeAttribute('disabled');
                    rotateLeftBtn?.removeAttribute('disabled');
                    rotateRightBtn?.removeAttribute('disabled');
                    
                    if (isLocked) {
                        // PDF locked: hide lock button and rotation buttons
                        lockBtn?.style.setProperty('display', 'none');
                        rotateLeftBtn?.style.setProperty('display', 'none');
                        rotateRightBtn?.style.setProperty('display', 'none');

                        // Mostrar botões de deletar e editar sempre quando travado
                        deleteBtn?.style.setProperty('display', 'inline-block');
                        editBtn?.style.setProperty('display', 'inline-block');

                        // Mostrar botões de desenho apenas se quarteirão E quadra estiverem selecionados
                        if (temSelecaoCompleta) {
                            markerBtn?.style.setProperty('display', 'inline-block');
                            polygonBtn?.style.setProperty('display', 'inline-block');
                        } else {
                            markerBtn?.style.setProperty('display', 'none');
                            polygonBtn?.style.setProperty('display', 'none');
                        }
                    } else {
                        // PDF unlocked: show lock button and rotation buttons, hide drawing tools
                        markerBtn?.style.setProperty('display', 'none');
                        polygonBtn?.style.setProperty('display', 'none');
                        deleteBtn?.style.setProperty('display', 'none');
                        editBtn?.style.setProperty('display', 'none');
                        lockBtn?.style.setProperty('display', 'inline-block');
                        rotateLeftBtn?.style.setProperty('display', 'inline-block');
                        rotateRightBtn?.style.setProperty('display', 'inline-block');
                    }
                } else {
                    // No PDF: disable all buttons
                    lockBtn?.setAttribute('disabled', 'true');
                    rotateLeftBtn?.setAttribute('disabled', 'true');
                    rotateRightBtn?.setAttribute('disabled', 'true');
                    markerBtn?.style.setProperty('display', 'none');
                    polygonBtn?.style.setProperty('display', 'none');
                }
            }

            // ===================================
            // SISTEMA DE LISTA DE PDFs
            // ===================================

            async carregarListaPDFs() {
                try {
                    // Construir caminho do JSON
                    const jsonPath = `loteamentos_quadriculas/json/resultados_quadricula_${quadricula}.json`;

                    // Carregar JSON
                    const response = await fetch(jsonPath);
                    if (!response.ok) {
                        throw new Error(`Erro ao carregar JSON: ${response.status}`);
                    }

                    const data = await response.json();

                    // Obter nome do loteamento da URL
                    const urlParams = new URLSearchParams(window.location.search);
                    const nomeLoteamento = urlParams.get('loteamento');

                    // Encontrar o loteamento
                    const loteamento = data.resultados.loteamentos.find(l =>
                        l.nome.toLowerCase() === nomeLoteamento.toLowerCase()
                    );

                    if (!loteamento) {
                        return;
                    }

                    // Extrair lista de PDFs
                    this.listaPDFs = loteamento.arquivos_associados || [];

                    // Atualizar UI
                    this.atualizarListaPDFs();

                    // Mostrar quadro (sempre visível)
                    this.showQuadroPDFs();

                } catch (error) {
                    this.listaPDFs = [];
                }
            }

            atualizarListaPDFs() {
                const listaContainer = document.getElementById('listaPDFs');
                if (!listaContainer) return;

                // Limpar lista atual
                listaContainer.innerHTML = '';

                if (this.listaPDFs.length === 0) {
                    listaContainer.innerHTML = '<div class="text-muted text-center p-3">Nenhum PDF encontrado</div>';
                    return;
                }

                // Criar itens da lista
                this.listaPDFs.forEach((nomePDF, index) => {
                    const item = document.createElement('div');
                    item.className = 'item-pdf';
                    item.dataset.pdf = nomePDF;

                    // Destacar PDF atual
                    if (nomePDF === this.pdfAtual) {
                        item.classList.add('ativo');
                    }

                    // Definir conteúdo baseado se há erro
                    if (this.pdfComErro && this.pdfComErro === nomePDF) {
                        item.classList.add('erro');
                        item.innerHTML = `
                            <i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i>
                            <span class="nome-pdf" style="color: #dc3545;">${nomePDF}</span>
                        `;
                    } else {
                        item.innerHTML = `
                            <i class="fas fa-file-pdf"></i>
                            <span class="nome-pdf">${nomePDF}</span>
                        `;
                    }

                    // Event listener para clique
                    item.addEventListener('click', () => {
                        this.carregarPDF(nomePDF);
                    });

                    listaContainer.appendChild(item);
                });
            }

            showQuadroPDFs() {
                const quadro = document.getElementById('quadroPDFs');
                quadro.style.display = 'block';
                this.atualizarListaPDFs();
            }

            hideQuadroPDFs() {
                const quadro = document.getElementById('quadroPDFs');
                quadro.style.display = 'none';
            }

            // ===================================
            // SISTEMA DE QUARTEIRÕES
            // ===================================

            async carregarQuarteiroes() {
                try {
                    // Construir caminho do JSON
                    const jsonPath = 'correspondencias_quarteiroes/resultado_quarteiroes_loteamentos.json';

                    // Carregar JSON (cache desabilitado para sempre obter a versão mais recente)
                    const response = await fetch(jsonPath, {
                        cache: 'no-cache'
                    });
                    if (!response.ok) {
                        throw new Error(`Erro ao carregar JSON: ${response.status}`);
                    }

                    const data = await response.json();

                    // Obter nome do loteamento da URL
                    const urlParams = new URLSearchParams(window.location.search);
                    const nomeLoteamento = urlParams.get('loteamento');

                    // Encontrar os quarteirões do loteamento (agora inclui os personalizados salvos no JSON)
                    const todosQuarteiroes = data[nomeLoteamento]?.quarteiroes || [];

                    if (todosQuarteiroes.length === 0) {
                        return;
                    }

                    // Ordenar quarteirões em ordem crescente por nome
                    const quarteiroesOrdenados = todosQuarteiroes.sort((a, b) => {
                        return a.nome.localeCompare(b.nome, 'pt-BR', { numeric: true });
                    });

                    // Atualizar título
                    document.getElementById('nomeLoteamentoQuarteiroes').textContent = nomeLoteamento;

                    // Popular lista com quarteirões ordenados
                    this.popularListaQuarteiroes(quarteiroesOrdenados);

                    // Mostrar quadro
                    this.showQuadroQuarteiroes();

                } catch (error) {
                    console.error('Erro ao carregar quarteirões:', error);
                }
            }

            popularListaQuarteiroes(quarteiroes) {
                const listaContainer = document.getElementById('listaQuarteiroes');
                if (!listaContainer) return;

                // Limpar lista atual
                listaContainer.innerHTML = '';

                if (quarteiroes.length === 0) {
                    listaContainer.innerHTML = '<div class="text-muted text-center p-3">Nenhum quarteirão encontrado</div>';
                    return;
                }

                // Criar itens da lista
                quarteiroes.forEach((quarteirao, index) => {
                    const item = document.createElement('div');
                    item.className = 'item-quarteirao';
                    
                    // Identificar quarteirões personalizados (têm data_insercao e usuario)
                    const isPersonalizado = quarteirao.data_insercao && quarteirao.usuario;
                    
                    // Adicionar classe especial para quarteirões personalizados
                    if (isPersonalizado) {
                        item.classList.add('quarteirao-personalizado');
                    }

                    // Criar HTML das quadras
                    const quadrasHTML = this.criarQuadrasHTML(quarteirao, index);
                    
                    // Ícone para quarteirões personalizados
                    const iconePersonalizado = isPersonalizado ? 
                        '<i class="fas fa-user-plus text-success" style="margin-right: 5px;"></i>' : '';

                    item.innerHTML = `
                        <div class="quarteirao-main">
                            <input type="radio" id="quarteirao_${index}" name="quarteirao" value="${quarteirao.nome}">
                            <label for="quarteirao_${index}">
                                ${iconePersonalizado}Quarteirão ${quarteirao.nome}
                            </label>
                        </div>
                        <div class="quadras-container">
                            ${quadrasHTML}
                        </div>
                    `;

                    // Event listener para seleção do quarteirão
                    const radio = item.querySelector('input[type="radio"]');

                    radio.addEventListener('change', (e) => {
                        if (e.target.checked) {
                            // Desmarcar outros radios de quarteirão
                            document.querySelectorAll('input[name="quarteirao"]').forEach(otherRadio => {
                                if (otherRadio !== e.target) {
                                    otherRadio.checked = false;
                                }
                            });

                            // Aplicar estilo de seleção
                            this.aplicarSelecaoQuarteirao(item);

                            // Callback para quarteirão selecionado
                            this.onQuarteiraoSelecionado(quarteirao);
                        }
                    });

                    // Event listener para clique no label do quarteirão
                    const label = item.querySelector('label');
                    label.addEventListener('click', (e) => {
                        e.preventDefault();
                        radio.checked = !radio.checked;
                        radio.dispatchEvent(new Event('change'));
                    });

                    listaContainer.appendChild(item);
                });

                // Inicializar todas as quadras como desabilitadas
                this.desabilitarTodasQuadras();

                // Adicionar event listeners para todas as quadras
                this.setupAllQuadrasEventListeners();
            }

            criarQuadrasHTML(quarteirao, quarteiraoIndex) {
                if (!quarteirao.quadras_unicas || quarteirao.quadras_unicas.length === 0) {
                    return '<div class="text-muted" style="padding-left: 20px; font-size: 12px;">Nenhuma quadra disponível</div>';
                }

                let html = '<div class="quadras-label">Quadras:</div>';
                html += '<div class="quadras-list">';

                quarteirao.quadras_unicas.forEach((quadra, quadraIndex) => {
                    html += `
                        <div class="item-quadra">
                            <input type="radio" id="quadra_${quarteiraoIndex}_${quadraIndex}" 
                                   name="quadra_${quarteirao.nome}" value="${quadra}">
                            <label for="quadra_${quarteiraoIndex}_${quadraIndex}">
                                ${quadra}
                            </label>
                        </div>
                    `;
                });

                html += '</div>';
                return html;
            }

            aplicarSelecaoQuarteirao(itemSelecionado) {
                // Remover seleção de todos os itens
                document.querySelectorAll('.item-quarteirao').forEach(item => {
                    item.style.background = '';
                    item.style.borderColor = '#e9ecef';
                });

                // Aplicar seleção ao item clicado
                itemSelecionado.style.background = '#e3f2fd';
                itemSelecionado.style.borderColor = '#2196f3';
            }

            onQuarteiraoSelecionado(quarteirao) {
                // Limpar seleções de quadras de outros quarteirões
                this.limparSelecoesQuadras();

                // Habilitar quadras do quarteirão selecionado
                this.habilitarQuadrasQuarteirao(quarteirao);

                // Desabilitar quadras de outros quarteirões
                this.desabilitarQuadrasOutrosQuarteiroes(quarteirao);

                // Atualizar visibilidade dos botões
                this.updateButtonsVisibility();

                // Aqui você pode implementar lógica adicional
                // Por exemplo: destacar no mapa, carregar dados específicos, etc.
            }

            setupQuadrasEventListeners(quarteirao) {
                // Aguardar um pouco para garantir que o DOM foi atualizado
                setTimeout(() => {
                    const quadrasRadios = document.querySelectorAll(`input[name="quadra_${quarteirao.nome}"]`);

                    quadrasRadios.forEach(radio => {
                        radio.addEventListener('change', (e) => {
                            // Verificar se o quarteirão está selecionado
                            const quarteiraoRadio = document.querySelector(`input[name="quarteirao"][value="${quarteirao.nome}"]`);
                            if (!quarteiraoRadio || !quarteiraoRadio.checked) {
                                e.target.checked = false;
                                return;
                            }

                            if (e.target.checked) {
                                // Desmarcar outras quadras do mesmo quarteirão
                                quadrasRadios.forEach(otherRadio => {
                                    if (otherRadio !== e.target) {
                                        otherRadio.checked = false;
                                    }
                                });

                                // Callback para quadra selecionada
                                this.onQuadraSelecionada(quarteirao, e.target.value);
                            }
                        });
                    });

                    // Permitir click em toda a item-quadra para selecionar
                    const quadrasDivs = document.querySelectorAll(`input[name="quadra_${quarteirao.nome}"]`);
                    quadrasDivs.forEach(radio => {
                        const quadraDiv = radio.closest('.item-quadra');
                        if (quadraDiv) {
                            quadraDiv.addEventListener('click', (e) => {
                                // Verificar se o quarteirão está selecionado
                                const quarteiraoRadio = document.querySelector(`input[name="quarteirao"][value="${quarteirao.nome}"]`);
                                if (!quarteiraoRadio || !quarteiraoRadio.checked) {
                                    return;
                                }

                                // Só prevenir comportamento padrão se NÃO for clique direto no radio
                                if (!e.target.matches('input[type="radio"]')) {
                                    e.preventDefault();
                                }
                                radio.checked = true;
                                radio.dispatchEvent(new Event('change'));
                            });
                        }
                    });
                }, 100);
            }

            onQuadraSelecionada(quarteirao, quadra) {
                // Obter seleção atual
                const selecao = this.getSelecaoAtual();

                console.log('Quadra selecionada:', quadra, 'Quarteirão:', quarteirao.nome);
                console.log('Seleção atual:', selecao);

                // Atualizar visibilidade dos botões
                this.updateButtonsVisibility();

                // Aqui você pode implementar lógica adicional
                // Por exemplo: filtrar marcadores, destacar no mapa, etc.
            }

            getSelecaoAtual() {
                const quarteiraoSelecionado = document.querySelector('input[name="quarteirao"]:checked');
                if (!quarteiraoSelecionado) {
                    return {
                        quarteirao: null,
                        quadra: null
                    };
                }

                const nomeQuarteirao = quarteiraoSelecionado.value;
                const quadraSelecionada = document.querySelector(`input[name="quadra_${nomeQuarteirao}"]:checked`);

                return {
                    quarteirao: nomeQuarteirao,
                    quadra: quadraSelecionada ? quadraSelecionada.value : null
                };
            }

            limparSelecoesQuadras() {
                // Limpar apenas seleções de quadras
                document.querySelectorAll('input[name^="quadra_"]').forEach(radio => {
                    radio.checked = false;
                });
            }

            habilitarQuadrasQuarteirao(quarteirao) {
                // Habilitar quadras do quarteirão selecionado
                const quadrasRadios = document.querySelectorAll(`input[name="quadra_${quarteirao.nome}"]`);
                quadrasRadios.forEach(radio => {
                    radio.disabled = false;
                    radio.style.opacity = '1';
                    radio.style.cursor = 'pointer';
                });

                // Habilitar labels das quadras
                const quadrasLabels = document.querySelectorAll(`label[for^="quadra_${quarteirao.nome}_"]`);
                quadrasLabels.forEach(label => {
                    label.style.opacity = '1';
                    label.style.cursor = 'pointer';
                });
            }

            desabilitarQuadrasOutrosQuarteiroes(quarteiraoSelecionado) {
                // Desabilitar quadras de outros quarteirões
                document.querySelectorAll('input[name^="quadra_"]').forEach(radio => {
                    const name = radio.name;
                    const quarteiraoNome = name.replace('quadra_', '');

                    if (quarteiraoNome !== quarteiraoSelecionado.nome) {
                        radio.disabled = true;
                        radio.checked = false;
                        radio.style.opacity = '0.5';
                        radio.style.cursor = 'not-allowed';
                    }
                });

                // Desabilitar labels das quadras de outros quarteirões
                document.querySelectorAll('label[for^="quadra_"]').forEach(label => {
                    const forAttr = label.getAttribute('for');
                    const quarteiraoNome = forAttr.split('_')[1];

                    if (quarteiraoNome !== quarteiraoSelecionado.nome) {
                        label.style.opacity = '0.5';
                        label.style.cursor = 'not-allowed';
                    }
                });
            }

            desabilitarTodasQuadras() {
                // Desabilitar todas as quadras inicialmente
                document.querySelectorAll('input[name^="quadra_"]').forEach(radio => {
                    radio.disabled = true;
                    radio.checked = false;
                    radio.style.opacity = '0.5';
                    radio.style.cursor = 'not-allowed';
                });

                // Desabilitar todos os labels das quadras
                document.querySelectorAll('label[for^="quadra_"]').forEach(label => {
                    label.style.opacity = '0.5';
                    label.style.cursor = 'not-allowed';
                });
            }

            setupAllQuadrasEventListeners() {
                // Aguardar um pouco para garantir que o DOM foi atualizado
                setTimeout(() => {
                    // Adicionar event listeners para todas as quadras
                    document.querySelectorAll('input[name^="quadra_"]').forEach(radio => {
                        radio.addEventListener('change', (e) => {
                            if (e.target.checked) {
                                // Extrair nome do quarteirão do name do radio
                                const name = e.target.name;
                                const quarteiraoNome = name.replace('quadra_', '');

                                // Verificar se o quarteirão está selecionado
                                const quarteiraoRadio = document.querySelector(`input[name="quarteirao"][value="${quarteiraoNome}"]`);
                                if (!quarteiraoRadio || !quarteiraoRadio.checked) {
                                    e.target.checked = false;
                                    return;
                                }

                                // Desmarcar outras quadras do mesmo quarteirão
                                const quadrasRadios = document.querySelectorAll(`input[name="quadra_${quarteiraoNome}"]`);
                                quadrasRadios.forEach(otherRadio => {
                                    if (otherRadio !== e.target) {
                                        otherRadio.checked = false;
                                    }
                                });

                                // Callback para quadra selecionada
                                const quarteirao = {
                                    nome: quarteiraoNome
                                };
                                this.onQuadraSelecionada(quarteirao, e.target.value);
                            }
                        });
                    });

                    // Permitir click em toda a item-quadra para selecionar
                    document.querySelectorAll('input[name^="quadra_"]').forEach(radio => {
                        const quadraDiv = radio.closest('.item-quadra');
                        if (quadraDiv) {
                            quadraDiv.addEventListener('click', (e) => {
                                // Extrair nome do quarteirão do name do radio
                                const name = radio.name;
                                const quarteiraoNome = name.replace('quadra_', '');

                                // Verificar se o quarteirão está selecionado
                                const quarteiraoRadio = document.querySelector(`input[name="quarteirao"][value="${quarteiraoNome}"]`);
                                if (!quarteiraoRadio || !quarteiraoRadio.checked) {
                                    return;
                                }

                                // Só prevenir comportamento padrão se NÃO for clique direto no radio
                                if (!e.target.matches('input[type="radio"]')) {
                                    e.preventDefault();
                                }
                                radio.checked = true;
                                radio.dispatchEvent(new Event('change'));
                            });
                        }
                    });
                }, 100);
            }

            showQuadroQuarteiroes() {
                const quadro = document.getElementById('quadroQuarteiroes');
                quadro.style.display = 'block';
            }

            hideQuadroQuarteiroes() {
                const quadro = document.getElementById('quadroQuarteiroes');
                quadro.style.display = 'none';
            }

            // ===================================
            // SISTEMA DE QUARTEIRÕES PERSONALIZADOS
            // ===================================

            showModalAdicionarQuarteirao() {
                // Limpar campos
                document.getElementById('inputNomeQuarteirao').value = '';
                document.getElementById('inputQuadras').value = '';
                
                // Mostrar modal
                const modal = new bootstrap.Modal(document.getElementById('modalAdicionarQuarteirao'));
                modal.show();
            }

            async adicionarQuarteiraoPersonalizado() {
                const nomeQuarteirao = document.getElementById('inputNomeQuarteirao').value.trim();
                const quadrasTexto = document.getElementById('inputQuadras').value.trim();

                // Validações
                if (!nomeQuarteirao) {
                    alert('Por favor, digite o nome do quarteirão.');
                    return;
                }

                if (!quadrasTexto) {
                    alert('Por favor, digite as quadras.');
                    return;
                }

                // Processar quadras (separar por vírgula e limpar espaços)
                const quadras = quadrasTexto.split(',')
                    .map(quadra => quadra.trim())
                    .filter(quadra => quadra.length > 0);

                if (quadras.length === 0) {
                    alert('Por favor, digite pelo menos uma quadra válida.');
                    return;
                }

                // Obter nome do loteamento da URL
                const urlParams = new URLSearchParams(window.location.search);
                const nomeLoteamento = urlParams.get('loteamento');

                if (!nomeLoteamento) {
                    alert('Erro: Nome do loteamento não encontrado na URL.');
                    return;
                }

                try {
                    // Desabilitar botão para evitar duplo clique
                    const btnConfirmar = document.getElementById('btnConfirmarAdicionarQuarteirao');
                    btnConfirmar.disabled = true;
                    btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';

                    // Dados para enviar
                    const dadosQuarteirao = {
                        loteamento: nomeLoteamento,
                        nome_quarteirao: nomeQuarteirao,
                        quadras: quadras
                    };

                    // Chamar página PHP para salvar no JSON
                    const response = await fetch('salvar_quarteirao_json.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(dadosQuarteirao)
                    });

                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Erro ao salvar quarteirão');
                    }

                    // Recarregar lista de quarteirões (agora vem do JSON atualizado)
                    this.carregarQuarteiroes();

                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalAdicionarQuarteirao'));
                    modal.hide();

                    // Mostrar mensagem de sucesso baseada na resposta
                    let mensagem = '';
                    
                    if (result.quadras_adicionadas && result.quadras_adicionadas.length > 0) {
                        // Quadras foram adicionadas a quarteirão existente
                        mensagem = `Quarteirão "${nomeQuarteirao}" atualizado!\n\nQuadras adicionadas: ${result.quadras_adicionadas.join(', ')}\nQuadras existentes: ${result.quadras_existentes.join(', ')}`;
                    } else if (result.quadras_adicionadas && result.quadras_adicionadas.length === 0) {
                        // Todas as quadras já existiam
                        mensagem = `Quarteirão "${nomeQuarteirao}" já existe!\nTodas as quadras informadas já estão cadastradas.`;
                    } else {
                        // Novo quarteirão criado
                        mensagem = `Quarteirão "${nomeQuarteirao}" criado com sucesso!\nQuadras: ${quadras.join(', ')}\nSalvo permanentemente no sistema.`;
                    }
                    
                    alert(mensagem);

                } catch (error) {
                    console.error('Erro ao adicionar quarteirão:', error);
                    alert(`Erro ao adicionar quarteirão: ${error.message}`);
                } finally {
                    // Reabilitar botão
                    const btnConfirmar = document.getElementById('btnConfirmarAdicionarQuarteirao');
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = '<i class="fas fa-plus"></i> Adicionar Quarteirão';
                }
            }

            // ===================================
            // SISTEMA DE INFORMAÇÕES DO POLÍGONO
            // ===================================

            mostrarInfoPoligono(poligonoObject) {
                try {
                    // Verificar se o polígono tem dados
                    if (!poligonoObject.polygonData) {
                        alert('Este polígono não possui informações disponíveis.');
                        return;
                    }

                    const dados = poligonoObject.polygonData;

                    // Formatar data se existir
                    let dataFormatada = 'Não informado';
                    if (dados.data_criacao) {
                        const data = new Date(dados.data_criacao);
                        dataFormatada = data.toLocaleString('pt-BR');
                    }

                    // Preencher modal com as informações
                    document.getElementById('infoQuarteirao').textContent = dados.quarteirao || 'Não informado';
                    document.getElementById('infoQuadra').textContent = dados.quadra || 'Não informado';
                    document.getElementById('infoUsuario').textContent = dados.usuario || 'Não informado';
                    document.getElementById('infoDataCriacao').textContent = dataFormatada;
                    document.getElementById('infoPDF').textContent = dados.pdf || 'Não informado';

                    // Preencher campos de edição com os valores atuais
                    document.getElementById('editQuarteirao').value = dados.quarteirao || '';
                    document.getElementById('editQuadra').value = dados.quadra || '';

                    // Resetar estado dos botões e campos
                    this.resetModalEditState();

                    // Armazenar referência do polígono para edição
                    this.poligonoEmEdicao = poligonoObject;

                    // Adicionar event listeners dos botões
                    this.setupModalEditListeners();

                    // Mostrar modal
                    const modal = new bootstrap.Modal(document.getElementById('modalInfoPoligono'));
                    modal.show();

                } catch (error) {
                    console.error('Erro ao mostrar informações do polígono:', error);
                    alert('Erro ao carregar informações do polígono.');
                }
            }

            /**
             * Reseta o estado do modal para modo visualização
             */
            resetModalEditState() {
                // Esconder campos de edição
                document.getElementById('editQuarteirao').style.display = 'none';
                document.getElementById('editQuadra').style.display = 'none';
                
                // Mostrar spans de informação
                document.getElementById('infoQuarteirao').style.display = 'inline';
                document.getElementById('infoQuadra').style.display = 'inline';
                
                // Mostrar botão Editar, esconder Salvar e Cancelar
                document.getElementById('btnEditarPoligono').style.display = 'inline-block';
                document.getElementById('btnSalvarPoligono').style.display = 'none';
                document.getElementById('btnCancelarEdicao').style.display = 'none';
            }

            /**
             * Configura os event listeners dos botões de edição
             */
            setupModalEditListeners() {
                // Remover listeners anteriores para evitar duplicação
                const btnEditar = document.getElementById('btnEditarPoligono');
                const btnSalvar = document.getElementById('btnSalvarPoligono');
                const btnCancelar = document.getElementById('btnCancelarEdicao');
                
                // Remover listeners existentes
                btnEditar.replaceWith(btnEditar.cloneNode(true));
                btnSalvar.replaceWith(btnSalvar.cloneNode(true));
                btnCancelar.replaceWith(btnCancelar.cloneNode(true));
                
                // Adicionar novos listeners
                document.getElementById('btnEditarPoligono').addEventListener('click', () => {
                    this.entrarModoEdicao();
                });
                
                document.getElementById('btnSalvarPoligono').addEventListener('click', () => {
                    this.salvarEdicaoPoligono();
                });
                
                document.getElementById('btnCancelarEdicao').addEventListener('click', () => {
                    this.cancelarEdicaoPoligono();
                });
            }

            /**
             * Entra no modo de edição
             */
            entrarModoEdicao() {
                // Esconder spans de informação
                document.getElementById('infoQuarteirao').style.display = 'none';
                document.getElementById('infoQuadra').style.display = 'none';
                
                // Mostrar campos de edição
                document.getElementById('editQuarteirao').style.display = 'block';
                document.getElementById('editQuadra').style.display = 'block';
                
                // Esconder botão Editar, mostrar Salvar e Cancelar
                document.getElementById('btnEditarPoligono').style.display = 'none';
                document.getElementById('btnSalvarPoligono').style.display = 'inline-block';
                document.getElementById('btnCancelarEdicao').style.display = 'inline-block';
                
                // Focar no primeiro campo
                document.getElementById('editQuarteirao').focus();
            }

            /**
             * Cancela a edição e volta ao modo visualização
             */
            cancelarEdicaoPoligono() {
                // Restaurar valores originais
                const dados = this.poligonoEmEdicao.polygonData;
                document.getElementById('editQuarteirao').value = dados.quarteirao || '';
                document.getElementById('editQuadra').value = dados.quadra || '';
                
                // Voltar ao estado de visualização
                this.resetModalEditState();
            }

            /**
             * Salva as alterações do polígono
             */
            async salvarEdicaoPoligono() {
                try {
                    const novoQuarteirao = document.getElementById('editQuarteirao').value.trim();
                    const novaQuadra = document.getElementById('editQuadra').value.trim();
                    
                    // Validar campos obrigatórios
                    if (!novoQuarteirao || !novaQuadra) {
                        alert('Por favor, preencha todos os campos obrigatórios.');
                        return;
                    }
                    
                    // Desabilitar botão para evitar duplo clique
                    const btnSalvar = document.getElementById('btnSalvarPoligono');
                    btnSalvar.disabled = true;
                    btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
                    
                    // Preparar dados para envio
                    const dadosAtualizacao = {
                        id: this.poligonoEmEdicao.polygonData.id,
                        quarteirao: novoQuarteirao,
                        quadra: novaQuadra
                    };
                    
                    console.log('Dados enviados:', dadosAtualizacao);

                    $.ajax({
                        url: 'atualizar_info_poligono.php',
                        type: 'POST',
                        data: JSON.stringify(dadosAtualizacao),
                        contentType: 'application/json',
                        dataType: 'json',
                        success: function(response) {
                            console.log('Resposta do servidor:', response);
                            
                            if (response.success) {
                                // Atualizar dados do polígono no canvas
                                this.poligonoEmEdicao.polygonData.quarteirao = novoQuarteirao;
                                this.poligonoEmEdicao.polygonData.quadra = novaQuadra;
                                
                                // Atualizar usuário e data no objeto do polígono
                                const usuarioAtual = '<?php echo $_SESSION["usuario"][0] ?? "Usuário"; ?>';
                                const dataAtual = new Date().toISOString().slice(0, 19).replace('T', ' ');
                                this.poligonoEmEdicao.polygonData.usuario = usuarioAtual;
                                this.poligonoEmEdicao.polygonData.data_criacao = dataAtual;
                                
                                // Atualizar exibição no modal
                                document.getElementById('infoQuarteirao').textContent = novoQuarteirao;
                                document.getElementById('infoQuadra').textContent = novaQuadra;
                                document.getElementById('infoUsuario').textContent = usuarioAtual;
                                document.getElementById('infoDataCriacao').textContent = new Date().toLocaleString('pt-BR');
                                
                                // Voltar ao modo visualização
                                this.resetModalEditState();
                                
                                alert('Polígono atualizado com sucesso!');
                                
                            } else {
                                alert('Erro ao atualizar polígono: ' + (response.message || 'Erro desconhecido'));
                            }
                        }.bind(this),
                        error: function(xhr, status, error) {
                            console.error('Erro AJAX:', error);
                            console.error('Status:', status);
                            console.error('Response Text:', xhr.responseText);
                            
                            // Tentar fazer parse da resposta mesmo em caso de erro
                            try {
                                const response = JSON.parse(xhr.responseText);
                                alert('Erro ao atualizar polígono: ' + (response.message || 'Erro desconhecido'));
                            } catch (parseError) {
                                console.error('Erro ao fazer parse da resposta:', parseError);
                                alert('Erro ao atualizar informações do polígono. Verifique o console para detalhes.');
                            }
                        }
                    });
                    
                } catch (error) {
                    console.error('Erro ao salvar edição do polígono:', error);
                    alert('Erro ao salvar alterações. Tente novamente.');
                } finally {
                    // Reabilitar botão
                    const btnSalvar = document.getElementById('btnSalvarPoligono');
                    btnSalvar.disabled = false;
                    btnSalvar.innerHTML = '<i class="fas fa-save"></i> Salvar';
                }
            }

            carregarPDF(nomePDF) {
                if (nomePDF === this.pdfAtual) {
                    return;
                }

                // LIMPAR CANVAS COMPLETAMENTE
                this.limparCanvas();

                // Atualizar PDF atual
                this.pdfAtual = nomePDF;

                // Atualizar URL
                const url = new URL(window.location);
                url.searchParams.set('pdf', nomePDF);
                window.history.pushState({}, '', url);

                // Recarregar PDF
                this.loadPDF();

                // Atualizar lista
                this.atualizarListaPDFs();
            }

            limparCanvas() {
                // Limpar todos os objetos do canvas
                this.mainCanvas.clear();

                // Restaurar fundo cinza
                this.mainCanvas.setBackgroundColor('#2a2a2a');

                // Resetar viewport transform
                this.mainCanvas.setViewportTransform([1, 0, 0, 1, 0, 0]);

                // Resetar zoom
                this.mainCanvas.setZoom(1);

                // Limpar referência do PDF atual
                this.currentPDF = null;

                // Resetar modo
                this.currentMode = 'view';
                this.updateButtonsState();

                // Atualizar visibilidade dos botões
                this.updateButtonsVisibility();

                // Renderizar para aplicar o fundo
                this.mainCanvas.renderAll();
            }

            updateButtonsState() {
                const markerBtn = document.getElementById('btnIncluirMarcadorSimplificado');
                const polygonBtn = document.getElementById('btnPoligonoSimplificado');
                const deleteBtn = document.getElementById('btnDeletarSimplificado');
                const editBtn = document.getElementById('btnEditarSimplificado');
                const sairBtn = document.getElementById('btnSairModoSimplificado');
                const inputTexto = document.getElementById('inputTextoMarcador');

                // Reset all buttons
                markerBtn?.classList.remove('active-tool');
                polygonBtn?.classList.remove('active-tool');
                deleteBtn?.classList.remove('active-tool');
                editBtn?.classList.remove('active-tool');
                
                // Restaurar classes originais dos botões
                deleteBtn?.classList.remove('btn-danger');
                deleteBtn?.classList.add('btn-outline-danger');
                editBtn?.classList.remove('btn-warning');
                editBtn?.classList.add('btn-outline-warning');
                
                // Habilitar todos os botões
                markerBtn?.removeAttribute('disabled');
                polygonBtn?.removeAttribute('disabled');
                deleteBtn?.removeAttribute('disabled');
                editBtn?.removeAttribute('disabled');

                if (this.currentMode === 'marker') {
                    markerBtn?.classList.add('active-tool');
                    sairBtn.style.display = 'inline-block';
                    inputTexto.style.display = 'inline-block';
                    
                    // Desabilitar outros botões
                    polygonBtn?.setAttribute('disabled', 'disabled');
                    deleteBtn?.setAttribute('disabled', 'disabled');
                    editBtn?.setAttribute('disabled', 'disabled');
                    
                } else if (this.currentMode === 'polygon') {
                    polygonBtn?.classList.add('active-tool');
                    sairBtn.style.display = 'inline-block';
                    inputTexto.style.display = 'none';
                    
                    // Desabilitar outros botões
                    markerBtn?.setAttribute('disabled', 'disabled');
                    deleteBtn?.setAttribute('disabled', 'disabled');
                    editBtn?.setAttribute('disabled', 'disabled');
                    
                } else if (this.currentMode === 'delete') {
                    deleteBtn?.classList.add('active-tool');
                    deleteBtn?.classList.remove('btn-outline-danger');
                    deleteBtn?.classList.add('btn-danger');
                    sairBtn.style.display = 'inline-block';
                    inputTexto.style.display = 'none';
                    
                    // Desabilitar outros botões
                    markerBtn?.setAttribute('disabled', 'disabled');
                    polygonBtn?.setAttribute('disabled', 'disabled');
                    editBtn?.setAttribute('disabled', 'disabled');
                    
                } else if (this.currentMode === 'edit') {
                    editBtn?.classList.add('active-tool');
                    editBtn?.classList.remove('btn-outline-warning');
                    editBtn?.classList.add('btn-warning');
                    sairBtn.style.display = 'inline-block';
                    inputTexto.style.display = 'none';
                    
                    // Desabilitar outros botões
                    markerBtn?.setAttribute('disabled', 'disabled');
                    polygonBtn?.setAttribute('disabled', 'disabled');
                    deleteBtn?.setAttribute('disabled', 'disabled');
                    
                } else {
                    sairBtn.style.display = 'none';
                    inputTexto.style.display = 'none';
                }
            }

            async loadPDF() {
                // Mostrar loading indicator
                this.showLoadingIndicator(`Carregando ${this.pdfAtual}...`);

                try {
                    // Normalizar caracteres Unicode (pode ter sido criado em Mac)
                    const nomeNormalizado = this.pdfAtual.normalize('NFC');
                    const nomeNormalizadoNFD = this.pdfAtual.normalize('NFD');
                    const nomeNormalizadoNFKC = this.pdfAtual.normalize('NFKC');
                    const nomeNormalizadoNFKD = this.pdfAtual.normalize('NFKD');

                    // Tentar diferentes codificações para o nome do arquivo
                    const tentativas = [
                        // Nome original
                        `loteamentos_quadriculas/pdf/${encodeURIComponent(this.pdfAtual)}`,
                        `loteamentos_quadriculas/pdf/${encodeURI(this.pdfAtual)}`,
                        `loteamentos_quadriculas/pdf/${this.pdfAtual}`,
                        `loteamentos_quadriculas/pdf/${this.pdfAtual.replace(/\+/g, '%2B')}`,

                        // Nome normalizado NFC
                        `loteamentos_quadriculas/pdf/${encodeURIComponent(nomeNormalizado)}`,
                        `loteamentos_quadriculas/pdf/${encodeURI(nomeNormalizado)}`,
                        `loteamentos_quadriculas/pdf/${nomeNormalizado}`,

                        // Nome normalizado NFD
                        `loteamentos_quadriculas/pdf/${encodeURIComponent(nomeNormalizadoNFD)}`,
                        `loteamentos_quadriculas/pdf/${encodeURI(nomeNormalizadoNFD)}`,
                        `loteamentos_quadriculas/pdf/${nomeNormalizadoNFD}`,

                        // Nome normalizado NFKC
                        `loteamentos_quadriculas/pdf/${encodeURIComponent(nomeNormalizadoNFKC)}`,
                        `loteamentos_quadriculas/pdf/${encodeURI(nomeNormalizadoNFKC)}`,
                        `loteamentos_quadriculas/pdf/${nomeNormalizadoNFKC}`,

                        // Nome normalizado NFKD
                        `loteamentos_quadriculas/pdf/${encodeURIComponent(nomeNormalizadoNFKD)}`,
                        `loteamentos_quadriculas/pdf/${encodeURI(nomeNormalizadoNFKD)}`,
                        `loteamentos_quadriculas/pdf/${nomeNormalizadoNFKD}`
                    ];

                    let response = null;
                    let pdfPath = '';

                    // Tentar cada caminho até encontrar um que funcione
                    for (const tentativa of tentativas) {
                        try {
                            response = await fetch(tentativa, {
                                method: 'HEAD', // Apenas verificar se existe
                                cache: 'no-cache'
                            });
                            if (response.ok) {
                                // Agora fazer o fetch real para obter o conteúdo
                                response = await fetch(tentativa);
                                pdfPath = tentativa;
                                break;
                            }
                        } catch (e) {
                            continue;
                        }
                    }

                    if (!response || !response.ok) {
                        throw new Error(`Arquivo não encontrado: ${response?.status || 'N/A'} ${response?.statusText || 'N/A'}`);
                    }

                    const arrayBuffer = await response.arrayBuffer();

                    // Render PDF
                    const pdfDoc = await pdfjsLib.getDocument(arrayBuffer).promise;
                    const page = await pdfDoc.getPage(1);
                    const scale = 2.0;
                    const viewport = page.getViewport({
                        scale
                    });

                    // Create canvas
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    // Render
                    await page.render({
                        canvasContext: context,
                        viewport
                    }).promise;

                    // Convert to Fabric image
                    const imgSrc = canvas.toDataURL();
                    const fabricImg = await new Promise((resolve) => {
                        fabric.Image.fromURL(imgSrc, (img) => {
                            img.set({
                                left: 50,
                                top: 50,
                                selectable: true,
                                lockScalingX: true,
                                lockScalingY: true,
                                hasControls: false,
                                pdfId: 'currentPDF'
                            });
                            resolve(img);
                        });
                    });

                    // Add to canvas
                    this.mainCanvas.add(fabricImg);

                    // Set as current PDF
                    this.currentPDF = {
                        fabricObject: fabricImg,
                        originalWidth: viewport.width,
                        originalHeight: viewport.height,
                        arquivo: this.pdfAtual,
                        isLocked: false
                    };

                    // Centralizar PDF no canvas
                    this.centerPDFInCanvas();

                    // Tentar carregar configurações salvas
                    console.log('🔍 Tentando carregar configurações para PDF:', this.pdfAtual);
                    const configuracoes = await this.carregarConfiguracoesPDF();
                    if (configuracoes) {
                        console.log('✅ Configurações encontradas, aplicando...');
                        this.aplicarConfiguracoesPDF(configuracoes);
                    } else {
                        console.log('❌ Nenhuma configuração encontrada para este PDF');
                    }

                    // Carregar marcadores salvos
                    await this.carregarMarcadoresSalvos();

                    // Carregar polígonos salvos
                    await this.carregarPoligonosSalvos();

                    // Atualizar visibilidade dos botões
                    this.updateButtonsVisibility();

                    // Limpar erro se PDF carregou com sucesso
                    this.pdfComErro = null;
                    this.atualizarListaPDFs();

                } catch (error) {
                    // Marcar PDF com erro
                    this.pdfComErro = this.pdfAtual;

                    // Mostrar mensagem de erro amigável
                    this.mostrarErroPDF(`Erro ao carregar PDF: ${this.pdfAtual}. O arquivo pode estar corrompido ou ter formato inválido.`);

                    // Atualizar lista para mostrar erro
                    this.atualizarListaPDFs();

                } finally {
                    // Sempre esconder o loading indicator
                    this.hideLoadingIndicator();
                }
            }

            centerPDFInCanvas() {
                if (!this.currentPDF || !this.currentPDF.fabricObject) {
                    return;
                }

                try {
                    const pdfObj = this.currentPDF.fabricObject;
                    const canvasWidth = this.mainCanvas.getWidth();
                    const canvasHeight = this.mainCanvas.getHeight();

                    // Obter dimensões originais do PDF
                    const originalWidth = pdfObj.width;
                    const originalHeight = pdfObj.height;

                    // CORREÇÃO: PDF fica fixo no centro do canvas (coordenadas absolutas)
                    const centerX = canvasWidth / 2;
                    const centerY = canvasHeight / 2;

                    // Posicionar PDF no centro do canvas (fixo)
                    pdfObj.set({
                        left: centerX,
                        top: centerY,
                        originX: 'center',
                        originY: 'center'
                    });

                    // Calcular zoom ótimo para mostrar o PDF com padding
                    const padding = 0.1; // 10% de padding
                    const zoomX = (canvasWidth * (1 - padding * 2)) / originalWidth;
                    const zoomY = (canvasHeight * (1 - padding * 2)) / originalHeight;
                    const optimalZoom = Math.min(zoomX, zoomY, 2); // Máximo 2x zoom

                    // Aplicar zoom na câmera
                    this.mainCanvas.setZoom(optimalZoom);

                    // Resetar viewport para origem (câmera no centro)
                    this.mainCanvas.setViewportTransform([1, 0, 0, 1, 0, 0]);

                    // Renderizar
                    this.mainCanvas.renderAll();

                } catch (error) {
                    // Erro silencioso
                }
            }

            // ===================================
            // SISTEMA DE EDIÇÃO COMPLETO
            // ===================================

            /**
             * Entra no modo de edição - torna todos os objetos editáveis
             */
            enterEditMode() {
                const canvasObjects = this.mainCanvas.getObjects();
                
                // Primeiro, processar polígonos
                for (const obj of canvasObjects) {
                    if (obj.isCustomPolygon === true) {
                        this.makePolygonEditable(obj);
                    }
                }
                
                // Depois, processar marcadores (para ficarem por cima)
                for (const obj of canvasObjects) {
                    if (obj.isCustomMarker === true) {
                        this.makeMarkerEditable(obj);
                        // Garantir que marcador fique por cima
                        this.mainCanvas.bringToFront(obj);
                    }
                }
            }

            /**
             * Sai do modo de edição - restaura objetos ao estado normal
             */
            exitEditMode() {
                const canvasObjects = this.mainCanvas.getObjects();
                
                for (const obj of canvasObjects) {
                    if (obj.isCustomMarker === true) {
                        this.makeMarkerNormal(obj);
                    } else if (obj.isCustomPolygon === true) {
                        this.makePolygonNormal(obj);
                    }
                }
            }

            /**
             * Torna um marcador editável (cinza, draggable)
             * @param {fabric.Group} markerGroup - Grupo do marcador
             */
            makeMarkerEditable(markerGroup) {
                markerGroup.set({
                    selectable: true,
                    evented: true,
                    hoverCursor: 'move'
                });
                
                // Tornar azul (modo edição)
                const rect = markerGroup.getObjects().find(obj => obj.type === 'rect');
                if (rect) {
                    rect.set({
                        fill: '#1276C3', // Azul para modo edição
                        stroke: 'black', // Manter borda preta
                        strokeWidth: this.config_marcador.strokeWidth // Usar largura original
                    });
                }
                
                // Adicionar event listeners para drag
                markerGroup.on('moving', () => {
                    this.onMarkerMoving(markerGroup);
                });
                
                markerGroup.on('mouseup', () => {
                    this.onMarkerMoved(markerGroup);
                });
            }

            /**
             * Restaura marcador ao estado normal
             * @param {fabric.Group} markerGroup - Grupo do marcador
             */
            makeMarkerNormal(markerGroup) {
                markerGroup.set({
                    selectable: this.config_marcador.selectable,
                    evented: this.config_marcador.evented
                });
                
                // Restaurar cor baseada no status original
                const rect = markerGroup.getObjects().find(obj => obj.type === 'rect');
                if (rect) {
                    const status = markerGroup.markerStatus || 'success';
                    if (status === 'success') {
                        rect.set({ 
                            fill: this.config_marcador.fill, // azul (#0000FF)
                            stroke: this.config_marcador.stroke, // preto
                            strokeWidth: this.config_marcador.strokeWidth // largura original
                        });
                    } else if (status === 'error') {
                        rect.set({ 
                            fill: 'red',
                            stroke: this.config_marcador.stroke, // preto
                            strokeWidth: this.config_marcador.strokeWidth // largura original
                        });
                    } else {
                        rect.set({ 
                            fill: 'gray', // pending
                            stroke: this.config_marcador.stroke, // preto
                            strokeWidth: this.config_marcador.strokeWidth // largura original
                        });
                    }
                }
                
                // Remover event listeners
                markerGroup.off('moving');
                markerGroup.off('mouseup');
            }

            /**
             * Torna um polígono editável (laranja, só bordas pontilhadas)
             * @param {fabric.Polygon} polygon - Polígono
             */
            makePolygonEditable(polygon) {
                // Mudar estilo para azul, só bordas pontilhadas
                polygon.set({
                    fill: 'transparent', // Sem fundo
                    stroke: '#1276C3', // Azul para modo edição
                    strokeWidth: 2,
                    strokeDashArray: [5, 5], // Linha pontilhada
                    selectable: true,
                    evented: true
                });
                
                // Criar vértices editáveis
                this.createPolygonVertices(polygon);
                
                // Adicionar event listeners
                polygon.on('modified', () => {
                    this.onPolygonModified(polygon);
                });
            }

            /**
             * Restaura polígono ao estado normal
             * @param {fabric.Polygon} polygon - Polígono
             */
            makePolygonNormal(polygon) {
                // Restaurar configuração padrão original
                polygon.set({
                    fill: this.config_poligono.fill,
                    stroke: this.config_poligono.stroke,
                    strokeWidth: this.config_poligono.strokeWidth,
                    strokeDashArray: null, // Remover tracejado
                    selectable: this.config_poligono.selectable,
                    evented: this.config_poligono.evented
                });
                
                // Remover vértices
                this.removePolygonVertices(polygon);
                
                // Remover event listeners
                polygon.off('modified');
            }

            /**
             * Cria vértices editáveis para um polígono
             * @param {fabric.Polygon} polygon - Polígono
             */
            createPolygonVertices(polygon) {
                const points = polygon.points;
                
                // Remover vértices existentes
                this.removePolygonVertices(polygon);
                
                // Criar novos vértices
                const vertices = [];
                for (let i = 0; i < points.length; i++) {
                    const vertex = new fabric.Circle({
                        left: points[i].x,
                        top: points[i].y,
                        radius: 6,
                        fill: '#1276C3', // Azul para modo edição
                        stroke: 'darkblue',
                        strokeWidth: 2,
                        originX: 'center',
                        originY: 'center',
                        selectable: true,
                        evented: true,
                        hoverCursor: 'move',
                        isEditVertex: true,
                        parentPolygon: polygon,
                        vertexIndex: i,
                        zIndex: 20 // Vértices ficam por cima de tudo
                    });
                    
                    // Event listeners para drag do vértice
                    vertex.on('moving', () => {
                        this.onVertexMoving(vertex);
                    });
                    
                    vertex.on('mouseup', () => {
                        this.onVertexMoved(vertex);
                    });
                    
                    vertices.push(vertex);
                    this.mainCanvas.add(vertex);
                }
                
                // Armazenar referência dos vértices no polígono
                polygon.editVertices = vertices;
            }

            /**
             * Remove vértices editáveis de um polígono
             * @param {fabric.Polygon} polygon - Polígono
             */
            removePolygonVertices(polygon) {
                if (polygon.editVertices) {
                    polygon.editVertices.forEach(vertex => {
                        this.mainCanvas.remove(vertex);
                    });
                    polygon.editVertices = null;
                }
            }

            /**
             * Event handler para quando marcador está sendo movido
             * @param {fabric.Group} markerGroup - Grupo do marcador
             */
            onMarkerMoving(markerGroup) {
                // Feedback visual durante o movimento
                const rect = markerGroup.getObjects().find(obj => obj.type === 'rect');
                if (rect) {
                    rect.set({ fill: 'orange' }); // Laranja durante movimento
                }
                // Forçar atualização visual
                this.mainCanvas.renderAll();
            }

            /**
             * Event handler para quando marcador foi movido
             * @param {fabric.Group} markerGroup - Grupo do marcador
             */
            async onMarkerMoved(markerGroup) {
                if (!markerGroup.markerData || !markerGroup.markerData.id) {
                    console.warn('⚠️ Marcador sem ID para atualizar');
                    return;
                }
                
                console.log('📝 Atualizando posição do marcador...');
                console.log('🔍 ID do marcador:', markerGroup.markerData.id);
                console.log('🔍 Tipo do ID:', typeof markerGroup.markerData.id);
                
                // Atualizar dados locais IMEDIATAMENTE
                markerGroup.markerData.x = markerGroup.left;
                markerGroup.markerData.y = markerGroup.top;
                
                // Desselecionar marcador
                markerGroup.setCoords();
                this.mainCanvas.discardActiveObject();
                this.mainCanvas.renderAll();
                
                // Enviar para o banco (assíncrono, sem await)
                this.updateMarkerInDatabase(markerGroup);
            }

            /**
             * Event handler para quando vértice está sendo movido
             * @param {fabric.Circle} vertex - Vértice
             */
            onVertexMoving(vertex) {
                // Mudar cor para laranja durante movimento
                vertex.set({ fill: 'orange' });
                
                // Atualizar ponto do polígono em tempo real
                const polygon = vertex.parentPolygon;
                const index = vertex.vertexIndex;
                
                if (polygon && polygon.points && polygon.points[index]) {
                    polygon.points[index].x = vertex.left;
                    polygon.points[index].y = vertex.top;
                    polygon.setCoords();
                    this.mainCanvas.renderAll();
                }
            }

            /**
             * Event handler para quando vértice foi movido
             * @param {fabric.Circle} vertex - Vértice
             */
            async onVertexMoved(vertex) {
                const polygon = vertex.parentPolygon;
                if (polygon && polygon.polygonData && polygon.polygonData.id) {
                    console.log('📝 Atualizando polígono após movimento do vértice...');
                    
                    // Restaurar cor azul do vértice
                    vertex.set({ fill: '#1276C3' });
                    
                    // Desselecionar vértice
                    this.mainCanvas.discardActiveObject();
                    this.mainCanvas.renderAll();
                    
                    // Enviar para o banco (assíncrono, sem await)
                    this.updatePolygonInDatabase(polygon);
                }
            }

            /**
             * Event handler para quando polígono foi modificado
             * @param {fabric.Polygon} polygon - Polígono
             */
            async onPolygonModified(polygon) {
                if (polygon.polygonData && polygon.polygonData.id) {
                    console.log('📝 Atualizando polígono após modificação...');
                    
                    // Enviar para o banco (assíncrono, sem await)
                    this.updatePolygonInDatabase(polygon);
                }
            }

            /**
             * Atualiza marcador no banco de dados
             * @param {fabric.Group} markerGroup - Grupo do marcador
             */
            async updateMarkerInDatabase(markerGroup) {
                try {
                    console.log('🔍 Enviando para o banco:');
                    console.log('🔍 ID:', markerGroup.markerData.id);
                    console.log('🔍 X:', markerGroup.left);
                    console.log('🔍 Y:', markerGroup.top);
                    
                    const response = await fetch('atualizar_marcador.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            id: markerGroup.markerData.id,
                            posicao_x: markerGroup.left,
                            posicao_y: markerGroup.top
                        })
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        if (result.success) {
                            // Sucesso: mudar para #00818c
                            const rect = markerGroup.getObjects().find(obj => obj.type === 'rect');
                            if (rect) {
                                rect.set({ fill: '#00818c' });
                            }
                            // Forçar atualização visual
                            this.mainCanvas.renderAll();
                            console.log('✅ Marcador atualizado no banco');
                        } else {
                            // Erro: mudar para vermelho
                            const rect = markerGroup.getObjects().find(obj => obj.type === 'rect');
                            if (rect) {
                                rect.set({ fill: 'red' });
                            }
                            // Forçar atualização visual
                            this.mainCanvas.renderAll();
                            console.error('❌ Erro ao atualizar marcador:', result.message);
                            console.error('❌ ID tentado:', markerGroup.markerData.id);
                        }
                    }
                } catch (error) {
                    // Erro: mudar para vermelho
                    const rect = markerGroup.getObjects().find(obj => obj.type === 'rect');
                    if (rect) {
                        rect.set({ fill: 'red' });
                    }
                    // Forçar atualização visual
                    this.mainCanvas.renderAll();
                    console.error('❌ Erro ao atualizar marcador:', error);
                    console.error('❌ ID tentado:', markerGroup.markerData.id);
                }
            }

            /**
             * Atualiza polígono no banco de dados
             * @param {fabric.Polygon} polygon - Polígono
             */
            async updatePolygonInDatabase(polygon) {
                try {
                    const response = await fetch('atualizar_poligono.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            id: polygon.polygonData.id,
                            pontos: JSON.stringify(polygon.points)
                        })
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        if (result.success) {
                            // Sucesso: mudar para #00818c
                            polygon.set({
                                fill: 'transparent',
                                stroke: '#00818c',
                                strokeDashArray: null
                            });
                            // Forçar atualização visual
                            this.mainCanvas.renderAll();
                            console.log('✅ Polígono atualizado no banco');
                        } else {
                            // Erro: mudar para vermelho
                            polygon.set({
                                fill: 'transparent',
                                stroke: 'red',
                                strokeDashArray: [5, 5]
                            });
                            // Forçar atualização visual
                            this.mainCanvas.renderAll();
                            console.error('❌ Erro ao atualizar polígono:', result.message);
                        }
                    }
                } catch (error) {
                    // Erro: mudar para vermelho
                    polygon.set({
                        fill: 'transparent',
                        stroke: 'red',
                        strokeDashArray: [5, 5]
                    });
                    // Forçar atualização visual
                    this.mainCanvas.renderAll();
                    console.error('❌ Erro ao atualizar polígono:', error);
                }
            }

            // ===================================
            // SISTEMA DE DELEÇÃO SIMPLES
            // ===================================

            /**
             * Deleta o objeto mais próximo ao clique usando ID
             * @param {number} x - Coordenada X do clique
             * @param {number} y - Coordenada Y do clique
             */
            deleteNearestObject(x, y) {
                console.log(`🗑️ Procurando objeto próximo em (${x.toFixed(1)}, ${y.toFixed(1)})`);
                
                const canvasObjects = this.mainCanvas.getObjects();
                let nearestObject = null;
                let minDistance = Infinity;
                const maxDistance = 30; // Raio pequeno para precisão
                
                for (const obj of canvasObjects) {
                    let distance = Infinity;
                    
                    // Verificar se é um marcador personalizado
                    if (obj.isCustomMarker === true) {
                        // Para grupos (marcadores), calcular distância do centro
                        distance = Math.sqrt((x - obj.left) ** 2 + (y - obj.top) ** 2);
                        
                    } else if (obj.isCustomPolygon === true) {
                        // Para polígonos, calcular distância dos vértices
                        if (obj.points && obj.points.length > 0) {
                            for (const point of obj.points) {
                                const pointDistance = Math.sqrt((x - point.x) ** 2 + (y - point.y) ** 2);
                                if (pointDistance < distance) {
                                    distance = pointDistance;
                                }
                            }
                        }
                    }
                    
                    // Verificar se está dentro da distância máxima
                    if (distance <= maxDistance && distance < minDistance) {
                        minDistance = distance;
                        nearestObject = obj;
                    }
                }
                
                if (nearestObject) {
                    console.log(`✅ Objeto encontrado a ${minDistance.toFixed(1)}px - deletando...`);
                    this.deleteObjectById(nearestObject);
                } else {
                    console.log('❌ Nenhum objeto encontrado próximo ao clique');
                }
            }
            
            /**
             * Encontra o polígono mais próximo do ponto clicado
             * @param {number} x - Coordenada X do clique
             * @param {number} y - Coordenada Y do clique
             * @returns {fabric.Object|null} - Polígono mais próximo ou null
             */
            findNearestPolygon(x, y) {
                console.log(`🔍 Procurando polígono próximo em (${x.toFixed(1)}, ${y.toFixed(1)})`);
                
                const canvasObjects = this.mainCanvas.getObjects();
                let nearestPolygon = null;
                let minDistance = Infinity;
                
                for (const obj of canvasObjects) {
                    // Verificar se é um polígono personalizado
                    if (obj.isCustomPolygon === true) {
                        let distance = Infinity;
                        
                        // Calcular distância dos vértices
                        if (obj.points && obj.points.length > 0) {
                            for (const point of obj.points) {
                                const pointDistance = Math.sqrt((x - point.x) ** 2 + (y - point.y) ** 2);
                                if (pointDistance < distance) {
                                    distance = pointDistance;
                                }
                            }
                        }
                        
                        // Verificar se está dentro da distância máxima configurável
                        if (distance <= this.BUFFER_CLIQUE_POLIGONO && distance < minDistance) {
                            minDistance = distance;
                            nearestPolygon = obj;
                        }
                    }
                }
                
                if (nearestPolygon) {
                    console.log(`✅ Polígono encontrado a ${minDistance.toFixed(1)}px`);
                } else {
                    console.log('❌ Nenhum polígono encontrado próximo ao clique');
                }
                
                return nearestPolygon;
            }
            
            /**
             * Remove objeto do canvas e deleta do banco usando ID
             * @param {fabric.Object} obj - Objeto a ser deletado
             */
            async deleteObjectById(obj) {
                try {
                    console.log('🗑️ Deletando objeto:', obj.type);
                    
                    // Mudar cor para laranja (feedback visual)
                    if (obj.isCustomMarker === true) {
                        const rect = obj.getObjects().find(obj => obj.type === 'rect');
                        if (rect) {
                            rect.set({ fill: 'orange' });
                        }
                    } else if (obj.isCustomPolygon === true) {
                        obj.set({ stroke: 'orange' });
                    }
                    this.mainCanvas.renderAll();
                    
                    // Deletar do banco usando ID
                    let success = false;
                    if (obj.isCustomMarker === true && obj.markerData && obj.markerData.id) {
                        success = await this.deleteMarkerById(obj.markerData.id);
                    } else if (obj.isCustomPolygon === true && obj.polygonData && obj.polygonData.id) {
                        success = await this.deletePolygonById(obj.polygonData.id);
                    }
                    
                    // Remover do canvas apenas após resposta do servidor
                    if (success) {
                        this.mainCanvas.remove(obj);
                        this.mainCanvas.renderAll();
                        console.log('✅ Objeto deletado com sucesso');
                    } else {
                        // Erro: mudar para vermelho
                        if (obj.isCustomMarker === true) {
                            const rect = obj.getObjects().find(obj => obj.type === 'rect');
                            if (rect) {
                                rect.set({ fill: 'red' });
                            }
                        } else if (obj.isCustomPolygon === true) {
                            obj.set({ stroke: 'red' });
                        }
                        this.mainCanvas.renderAll();
                        console.log('❌ Erro ao deletar objeto - mantendo no canvas');
                    }
                    
                } catch (error) {
                    // Erro: mudar para vermelho
                    if (obj.isCustomMarker === true) {
                        const rect = obj.getObjects().find(obj => obj.type === 'rect');
                        if (rect) {
                            rect.set({ fill: 'red' });
                        }
                    } else if (obj.isCustomPolygon === true) {
                        obj.set({ stroke: 'red' });
                    }
                    this.mainCanvas.renderAll();
                    console.error('❌ Erro ao deletar objeto:', error);
                }
            }
            
            /**
             * Deleta marcador do banco usando ID
             * @param {number} markerId - ID do marcador
             */
            async deleteMarkerById(markerId) {
                try {
                    console.log('🔍 Enviando ID do marcador para deletar:', markerId);
                    
                    const response = await fetch('deletar_marcador_por_id.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: markerId })
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        if (result.success) {
                            console.log('✅ Marcador ocultado no banco, ID:', markerId);
                            return true;
                        } else {
                            console.error('❌ Erro ao ocultar marcador:', result.message);
                            return false;
                        }
                    } else {
                        console.error('❌ HTTP Error:', response.status);
                        return false;
                    }
                } catch (error) {
                    console.error('❌ Erro ao ocultar marcador do banco:', error);
                    return false;
                }
            }
            
            /**
             * Deleta polígono do banco usando ID
             * @param {number} polygonId - ID do polígono
             */
            async deletePolygonById(polygonId) {
                try {
                    console.log('🔍 Enviando ID do polígono para deletar:', polygonId);
                    
                    const response = await fetch('deletar_poligono_por_id.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: polygonId })
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        if (result.success) {
                            console.log('✅ Polígono ocultado no banco, ID:', polygonId);
                            return true;
                        } else {
                            console.error('❌ Erro ao ocultar polígono:', result.message);
                            return false;
                        }
                    } else {
                        console.error('❌ HTTP Error:', response.status);
                        return false;
                    }
                } catch (error) {
                    console.error('❌ Erro ao ocultar polígono do banco:', error);
                    return false;
                }
            }

            // ===================================
            // SISTEMA DE SALVAMENTO/CARREGAMENTO DE MARCADORES
            // ===================================

            atualizarCorMarcador(grupo, status) {
                try {
                    // Atualizar status do grupo
                    grupo.markerStatus = status;
                    
                    // Encontrar o retângulo dentro do grupo (primeiro objeto)
                    const marker = grupo.getObjects()[0];
                    
                    if (marker && marker.type === 'rect') {
                        // Definir cor baseada no status
                        switch (status) {
                            case 'success':
                                marker.set('fill', this.config_marcador.fill); // Azul (cor original)
                                break;
                            case 'error':
                                marker.set('fill', 'red'); // Vermelho
                                break;
                            case 'pending':
                            default:
                                marker.set('fill', 'gray'); // Cinza
                                break;
                        }
                        
                        // Forçar renderização
                        this.mainCanvas.renderAll();
                        
                        console.log(`✅ Marcador atualizado para: ${status}`);
                    }
                } catch (error) {
                    console.error('Erro ao atualizar cor do marcador:', error);
                }
            }

            async salvarMarcadorNoBanco(x, y, texto, grupo) {
                try {
                    const urlParams = new URLSearchParams(window.location.search);

                    // Obter seleção atual de quarteirão e quadra
                    const selecao = this.getSelecaoAtual();

                    const dadosMarcador = {
                        quadricula: urlParams.get('quadricula'),
                        loteamento: urlParams.get('loteamento'),
                        pdf: this.pdfAtual,
                        quarteirao: selecao.quarteirao,
                        quadra: selecao.quadra,
                        posicao_x: x,
                        posicao_y: y,
                        texto: texto
                    };

                    const response = await fetch('salvar_marcador.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(dadosMarcador)
                    });

                    if (!response.ok) {
                        throw new Error('Erro ao salvar marcador');
                    }

                    const result = await response.json();
                    if (!result.success) {
                        throw new Error(result.message || 'Erro ao salvar marcador');
                    }

                    // Definir ID no objeto
                    grupo.markerData.id = result.marcador_id;
                    
                    // ✅ SUCESSO: Mudar para VERDE
                    this.atualizarCorMarcador(grupo, 'success');

                } catch (error) {
                    console.error('Erro ao salvar marcador:', error);
                    
                    // ❌ ERRO: Mudar para VERMELHO
                    this.atualizarCorMarcador(grupo, 'error');
                }
            }

            async carregarMarcadoresDoBanco() {
                if (!this.pdfAtual) {
                    return [];
                }

                try {
                    const urlParams = new URLSearchParams(window.location.search);

                    const response = await fetch('carregar_marcadores.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            pdf: this.pdfAtual
                        })
                    });

                    if (!response.ok) {
                        return [];
                    }

                    const result = await response.json();
                    return result.success ? result.marcadores : [];

                } catch (error) {
                    console.error('Erro ao carregar marcadores:', error);
                    return [];
                }
            }

            async carregarMarcadoresSalvos() {
                try {
                    const marcadores = await this.carregarMarcadoresDoBanco();

                    for (const marcador of marcadores) {
                        await this.criarMarcadorDoBanco(marcador);
                    }

                    // Renderizar após carregar todos os marcadores
                    this.mainCanvas.renderAll();

                } catch (error) {
                    console.error('Erro ao carregar marcadores salvos:', error);
                }
            }

            async criarMarcadorDoBanco(dadosMarcador) {
                const x = dadosMarcador.posicao_x;
                const y = dadosMarcador.posicao_y;
                const texto = dadosMarcador.texto;

                // Criar marcador quadrado com cantos arredondados (VERDE - já salvo)
                const marker = new fabric.Rect({
                    left: x - (this.config_marcador.width / 2),
                    top: y - (this.config_marcador.height / 2),
                    width: this.config_marcador.width,
                    height: this.config_marcador.height,
                    fill: this.config_marcador.fill, // Azul - já salvo no banco
                    stroke: this.config_marcador.stroke,
                    strokeWidth: this.config_marcador.strokeWidth,
                    rx: this.config_marcador.borderRadius,
                    ry: this.config_marcador.borderRadius,
                    selectable: this.config_marcador.selectable,
                    evented: this.config_marcador.evented,
                    isCustomMarker: true,
                    markerStatus: 'success' // Status: success (já salvo)
                });

                // Adicionar texto no centro
                const text = new fabric.IText(texto, {
                    left: x,
                    top: y,
                    fontSize: this.config_marcador.fontSize,
                    fontFamily: 'Arial',
                    fontWeight: 'bold',
                    fill: this.config_marcador.textColor,
                    textAlign: 'center',
                    originX: 'center',
                    originY: 'center',
                    selectable: false,
                    evented: false,
                    isCustomMarker: true,
                    editable: false,
                    lockMovementX: true,
                    lockMovementY: true,
                    lockRotation: true,
                    lockScalingX: true,
                    lockScalingY: true
                });

                // Criar grupo com retângulo e texto
                const grupo = new fabric.Group([marker, text], {
                    left: x,
                    top: y,
                    originX: 'center',
                    originY: 'center',
                    selectable: false,
                    evented: false,
                    isCustomMarker: true,
                    markerStatus: 'success', // Status: success (já salvo)
                    markerData: { 
                        x, y, texto,
                        id: dadosMarcador.id,
                        quadricula: dadosMarcador.quadricula,
                        loteamento: dadosMarcador.loteamento,
                        pdf: dadosMarcador.pdf,
                        quarteirao: dadosMarcador.quarteirao,
                        quadra: dadosMarcador.quadra
                    }, // Dados completos
                    zIndex: 10 // Marcadores ficam por cima dos polígonos
                });

                // Ajustar tamanho dinamicamente baseado no texto
                this.ajustarTamanhoMarcador(grupo, texto);

                // Adicionar ao canvas
                this.mainCanvas.add(grupo);
                
                // Garantir que marcador fique por cima de todos os polígonos
                this.mainCanvas.bringToFront(grupo);
            }

            // ===================================
            // SISTEMA DE CARREGAMENTO DE POLÍGONOS (IGUAL AOS MARCADORES)
            // ===================================

            async carregarPoligonosDoBanco() {
                if (!this.pdfAtual) {
                    return [];
                }

                try {
                    const urlParams = new URLSearchParams(window.location.search);

                    const response = await fetch('carregar_poligonos.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            pdf: this.pdfAtual
                        })
                    });

                    if (!response.ok) {
                        return [];
                    }

                    const result = await response.json();

                    console.log('📄 Resposta polígonos:', result);
                    console.log(`✅ Polígonos encontrados: ${result.poligonos ? result.poligonos.length : 0}`);

                    return result.success ? result.poligonos : [];

                } catch (error) {
                    console.error('Erro ao carregar polígonos:', error);
                    return [];
                }
            }

            async carregarPoligonosSalvos() {
                try {
                    const poligonos = await this.carregarPoligonosDoBanco();

                    for (const poligono of poligonos) {
                        await this.criarPoligonoDoBanco(poligono);
                    }

                    // Renderizar após carregar todos os polígonos
                    this.mainCanvas.renderAll();

                } catch (error) {
                    console.error('Erro ao carregar polígonos salvos:', error);
                }
            }

            async criarPoligonoDoBanco(dadosPoligono) {
                try {
                    //console.log('🔧 Criando polígono do banco:', dadosPoligono);

                    // Parse dos pontos
                    const pontos = typeof dadosPoligono.pontos === 'string' ?
                        JSON.parse(dadosPoligono.pontos) :
                        dadosPoligono.pontos;

                    console.log('📍 Pontos do polígono:', pontos);

                    if (!pontos || pontos.length < 3) {
                        console.warn('⚠️ Polígono inválido:', dadosPoligono);
                        return;
                    }

                    // Criar polígono
                    const polygon = new fabric.Polygon(pontos, {
                        fill: this.config_poligono.fill,
                        stroke: this.config_poligono.stroke,
                        strokeWidth: 3, // Volta ao original
                        strokeDashArray: null, // Sem linha tracejada
                        selectable: false, // Não selecionável
                        evented: true, // Permitir cliques para mostrar informações
                        isCustomPolygon: true,
                        isSavedPolygon: true, // Marcar como polígono salvo
                        polygonData: { 
                            points: pontos,
                            id: dadosPoligono.id,
                            quadricula: dadosPoligono.quadricula,
                            loteamento: dadosPoligono.loteamento,
                            pdf: dadosPoligono.pdf,
                            quarteirao: dadosPoligono.quarteirao,
                            quadra: dadosPoligono.quadra,
                            usuario: dadosPoligono.usuario,
                            data_criacao: dadosPoligono.datetime // Corrigido: usar 'datetime' que vem do banco
                        }, // Dados completos
                        zIndex: 1 // Polígonos ficam atrás dos marcadores
                    });

                    // Adicionar ao canvas
                    this.mainCanvas.add(polygon);

                    //console.log('✅ Polígono adicionado ao canvas');

                } catch (error) {
                    console.error('❌ Erro ao criar polígono do banco:', error);
                }
            }

            // ===================================
            // SISTEMA DE SALVAMENTO/CARREGAMENTO DE CONFIGURAÇÕES
            // ===================================

            async salvarConfiguracoesPDF() {
                if (!this.currentPDF) {
                    return;
                }

                try {
                    const obj = this.currentPDF.fabricObject;
                    const urlParams = new URLSearchParams(window.location.search);

                    // Capturar todas as configurações atuais
                    const configuracoes = {
                        pdf: this.pdfAtual,
                        rotacao: Math.round(obj.angle || 0),
                        zoom: this.mainCanvas.getZoom(),
                        travado: this.currentPDF.isLocked,
                        loteamento: urlParams.get('loteamento'),
                        quadriculas: [urlParams.get('quadricula')], // Array de quadrículas
                        posicao_x: obj.left || 0,
                        posicao_y: obj.top || 0,
                        viewport_transform: this.mainCanvas.viewportTransform.slice()
                    };

                    // Enviar para o servidor
                    const response = await fetch('salvar_config_pdf.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(configuracoes)
                    });

                    if (!response.ok) {
                        throw new Error('Erro ao salvar configurações');
                    }

                    const result = await response.json();
                    if (result.success) {
                        // Sucesso - configurações salvas
                    } else {
                        throw new Error(result.message || 'Erro ao salvar');
                    }

                } catch (error) {
                    console.error('Erro ao salvar configurações:', error);
                }
            }

            async carregarConfiguracoesPDF() {
                if (!this.pdfAtual) {
                    return null;
                }

                try {
                    const urlParams = new URLSearchParams(window.location.search);

                    const response = await fetch('buscar_config_pdf.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            pdf: this.pdfAtual,
                            loteamento: urlParams.get('loteamento'),
                            quadricula: urlParams.get('quadricula')
                        })
                    });

                    if (!response.ok) {
                        return null;
                    }

                    const result = await response.json();

                    console.log('📄 Resposta configurações:', result);

                    if (result.success) {
                        console.log('✅ Configurações carregadas:', result.configuracoes);
                        return result.configuracoes;
                    } else {
                        console.log('❌ Nenhuma configuração encontrada:', result.message);
                        return null;
                    }

                } catch (error) {
                    console.error('Erro ao carregar configurações:', error);
                    return null;
                }
            }

            aplicarConfiguracoesPDF(configuracoes) {
                console.log('🔧 Aplicando configurações:', configuracoes);

                if (!configuracoes || !this.currentPDF) {
                    console.log('⚠️ Não é possível aplicar configurações - dados inválidos');
                    return;
                }

                try {
                    const obj = this.currentPDF.fabricObject;
                    console.log('📋 Objeto PDF encontrado, aplicando configurações...');

                    // Aplicar rotação
                    if (configuracoes.rotacao) {
                        console.log('🔄 Aplicando rotação:', configuracoes.rotacao);
                        obj.set({
                            angle: configuracoes.rotacao
                        });
                    }

                    // Aplicar posição
                    if (configuracoes.posicao_x !== undefined && configuracoes.posicao_y !== undefined) {
                        console.log('📍 Aplicando posição:', configuracoes.posicao_x, configuracoes.posicao_y);
                        obj.set({
                            left: configuracoes.posicao_x,
                            top: configuracoes.posicao_y
                        });
                    }

                    // Aplicar zoom
                    if (configuracoes.zoom) {
                        console.log('🔍 Aplicando zoom:', configuracoes.zoom);
                        this.mainCanvas.setZoom(configuracoes.zoom);
                    }

                    // Aplicar viewport transform
                    if (configuracoes.viewport_transform && Array.isArray(configuracoes.viewport_transform)) {
                        console.log('🎯 Aplicando viewport transform:', configuracoes.viewport_transform);
                        this.mainCanvas.setViewportTransform(configuracoes.viewport_transform);
                    }

                    // Aplicar estado de travamento
                    if (configuracoes.travado) {
                        console.log('🔒 Aplicando travamento: SIM');
                        this.currentPDF.isLocked = true;
                        obj.set({
                            lockMovementX: true,
                            lockMovementY: true,
                            lockRotation: true
                        });
                    } else {
                        console.log('🔓 PDF não travado');
                    }

                    // Renderizar
                    this.mainCanvas.renderAll();

                    // Atualizar UI
                    this.updateButtonsVisibility();

                    console.log('✅ Configurações aplicadas com sucesso!');

                } catch (error) {
                    console.error('❌ Erro ao aplicar configurações:', error);
                }
            }

            // ===================================
            // INDICADORES DE CARREGAMENTO
            // ===================================

            showLoadingIndicator(message = 'Carregando...') {
                // Remove existing indicator if present
                this.hideLoadingIndicator();

                // Create loading indicator
                const indicator = document.createElement('div');
                indicator.id = 'loadingIndicatorIntegrado';
                indicator.style.cssText = `
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: rgba(0, 0, 0, 0.9);
                    color: white;
                    padding: 20px 30px;
                    border-radius: 8px;
                    z-index: 9999;
                    font-family: Arial, sans-serif;
                    font-size: 16px;
                    text-align: center;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                `;

                // Add spinner
                const spinner = document.createElement('div');
                spinner.style.cssText = `
                    width: 20px;
                    height: 20px;
                    border: 2px solid #ffffff40;
                    border-top: 2px solid #ffffff;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 10px auto;
                `;

                // Add CSS animation
                if (!document.getElementById('loadingSpinnerCSS')) {
                    const style = document.createElement('style');
                    style.id = 'loadingSpinnerCSS';
                    style.textContent = `
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    `;
                    document.head.appendChild(style);
                }

                indicator.appendChild(spinner);
                indicator.appendChild(document.createTextNode(message));

                // Add to PDF viewer div
                const pdfViewer = document.querySelector('.divContainerPDF');
                if (pdfViewer) {
                    pdfViewer.appendChild(indicator);
                }
            }

            hideLoadingIndicator() {
                const indicator = document.getElementById('loadingIndicatorIntegrado');
                if (indicator) {
                    indicator.remove();
                }
            }

            mostrarErroPDF(mensagem) {
                // Criar elemento de erro
                const erroDiv = document.createElement('div');
                erroDiv.className = 'erro-pdf';
                erroDiv.style.cssText = `
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: #dc3545;
                    color: white;
                    padding: 20px 30px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    z-index: 2000;
                    max-width: 400px;
                    text-align: center;
                    font-family: Arial, sans-serif;
                `;

                erroDiv.innerHTML = `
                    <div style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">
                        <i class="fas fa-exclamation-triangle"></i> Erro ao Carregar PDF
                    </div>
                    <div style="font-size: 14px; margin-bottom: 15px;">
                        ${mensagem}
                    </div>
                    <button onclick="this.parentElement.remove()" style="
                        background: white;
                        color: #dc3545;
                        border: none;
                        padding: 8px 16px;
                        border-radius: 4px;
                        cursor: pointer;
                        font-weight: bold;
                    ">
                        OK
                    </button>
                `;

                // Adicionar ao container
                const container = document.querySelector('.divContainerPDF');
                container.appendChild(erroDiv);

                // Remover automaticamente após 10 segundos
                setTimeout(() => {
                    if (erroDiv.parentElement) {
                        erroDiv.remove();
                    }
                }, 10000);

                // Erro exibido
            }
        }

        // Inicializar quando a página carrega
        document.addEventListener('DOMContentLoaded', () => {
            new PDFViewerSimplificado();
        });
    </script>

</body>