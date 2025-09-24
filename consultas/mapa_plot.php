<?php
//var_dump($_POST);
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: ../logout.php');
    exit();
}

include("../connection.php");

// Debug: Verificar dados recebidos
$dados_recebidos = false;
$metodo_recebimento = '';
$tamanho_dados = 0;

if (isset($_POST['dados']) && isset($_POST['filtros'])) {
    $dados_recebidos = true;
    $metodo_recebimento = 'POST';
    $tamanho_dados = strlen($_POST['dados']);
} elseif (isset($_GET['dados']) && isset($_GET['filtros'])) {
    $dados_recebidos = true;
    $metodo_recebimento = 'GET';
    $tamanho_dados = strlen($_GET['dados']);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de Dados Filtrados</title>
    
    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- jQuery -->
    <script src="../jquery.min.js"></script>
    <!-- Bootstrap 5.3 -->
    <script src="../bootstrap.bundle.min.js"></script>
    <link href="../bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Maps API -->
    <script src="../apiGoogle.js"></script>

    <style>
        html, body {
            width: 100%;
            height: 100vh;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }

        .container-fluid {
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            flex-shrink: 0;
        }

        .map-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin: 0;
            padding: 0;
        }

        #map {
            flex: 1;
            min-height: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            background-color: #f0f0f0;
        }

        .info-panel {
            background: white;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
        }

        .stats-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 6px 10px;
            border-radius: 6px;
            border-left: 3px solid #007bff;
            min-width: 100px;
        }

        .stat-number {
            font-size: 1.2em;
            font-weight: bold;
            color: #007bff;
        }

        .stat-label {
            font-size: 0.8em;
            color: #666;
        }

        .marker-cluster {
            background: rgba(0, 123, 255, 0.8);
            border: 2px solid white;
            border-radius: 50%;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            cursor: pointer;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 6px solid #f3f3f3;
            border-top: 6px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Estilos para o menu dropdown de camadas na topbar */
        .dropdown-menu-topbar {
            position: relative;
            display: inline-block;
        }

        .dropdown-content-topbar {
            position: absolute;
            top: 100%;
            left: 0;
            margin-top: 4px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 8px;
            min-width: 180px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            display: none;
        }

        .dropdown-content-topbar.show {
            display: block;
            animation: fadeInDown 0.2s ease;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .camada-item-topbar {
            margin-bottom: 6px;
        }

        .camada-item-topbar:last-child {
            margin-bottom: 0;
        }

        .camada-label-topbar {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #333;
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
            font-size: 13px;
            user-select: none;
        }

        .camada-label-topbar:hover {
            background: rgba(0, 123, 255, 0.1);
        }

        .camada-label-topbar input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .camada-label-topbar .fa-square,
        .camada-label-topbar .fa-map-marker-alt {
            font-size: 14px;
        }

        /* Estilos para o controle de opacidade */
        .opacity-control-topbar {
            background: rgba(248, 249, 250, 0.8);
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 6px 10px;
        }

        .opacity-control-topbar .form-range {
            margin: 0;
        }

        .opacity-control-topbar .form-range::-webkit-slider-thumb {
            background-color: #007bff;
        }

        .opacity-control-topbar .form-range::-moz-range-thumb {
            background-color: #007bff;
            border: none;
        }

    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
        <h4 style="margin-top: 20px; color: #666;">Carregando dados do mapa...</h4>
        <p style="color: #888;">Processando coordenadas e criando marcadores</p>
    </div>


    <div class="container-fluid"
        style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; padding: 10px; display: flex; flex-direction: column;">
        
        <!-- Botões de controle compactos -->
        <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.9); 
                    padding: 8px 15px; border-radius: 5px; margin-bottom: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h6 style="margin: 0; color: #333;">
                <i class="fas fa-map-marked-alt"></i> Mapa de Dados Filtrados
            </h6>
            
            <div class="d-flex gap-3 align-items-center">
                <button class="btn btn-sm btn-outline-primary" onclick="voltarConsultas()">
                    <i class="fas fa-arrow-left"></i> Voltar
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="centralizarMapa()">
                    <i class="fas fa-crosshairs"></i> Centralizar
                </button>
                
                <!-- Menu Dropdown Camadas -->
                <div class="dropdown-menu-topbar">
                    <button id="btnCamadas" class="btn btn-sm btn-outline-dark dropdown-toggle" onclick="toggleMenuCamadas()">
                        <i class="fas fa-layer-group"></i> Camadas
                    </button>
                    <div id="dropdownCamadas" class="dropdown-content-topbar">
                        <div class="camada-item-topbar">
                            <label class="camada-label-topbar">
                                <input type="checkbox" id="toggleQuadras" checked onchange="toggleQuadras(this.checked)">
                                <i class="fas fa-square" style="color: #0078D7;"></i>
                                <span>Quadras</span>
                            </label>
                        </div>
                        <div class="camada-item-topbar">
                            <label class="camada-label-topbar">
                                <input type="checkbox" id="toggleLotes" checked onchange="toggleLotes(this.checked)">
                                <i class="fas fa-square" style="color: #FF6B6B;"></i>
                                <span>Lotes</span>
                            </label>
                        </div>
                        <div class="camada-item-topbar">
                            <label class="camada-label-topbar">
                                <input type="checkbox" id="toggleMarcadores" checked onchange="toggleMarcadores(this.checked)">
                                <i class="fas fa-map-marker-alt" style="color: #32CD32;"></i>
                                <span>Marcadores</span>
                            </label>
                        </div>
                        <div class="camada-item-topbar">
                            <label class="camada-label-topbar">
                                <input type="checkbox" id="toggleLotesPrefeitura" checked onchange="toggleLotesPrefeitura(this.checked)">
                                <i class="fas fa-square" style="color: #FF6B35;"></i>
                                <span>Lotes Prefeitura</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Controle de Opacidade -->
                <div class="d-flex align-items-center gap-2 opacity-control-topbar">
                    <label for="opacidadeInput" class="form-label mb-0 small text-muted">Opacidade:</label>
                    <input type="range" id="opacidadeInput" class="form-range" min="0.1" max="1" step="0.1" value="0.6" style="width: 80px;" onchange="atualizarOpacidade(this.value)">
                    <span class="small text-muted" id="opacidadeValue">60%</span>
                </div>
                
                
                <a href="../logout.php" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </div>

        <!-- Painel de Informações -->
        <div class="map-container">
            <div class="info-panel">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-bar"></i> Estatísticas dos Dados
                    </h6>
                    <div class="text-end">
                        <?php if ($dados_recebidos): ?>
                            <small class="text-success">
                                <i class="fas fa-check-circle"></i> Dados recebidos via <?php echo $metodo_recebimento; ?>
                                (<?php echo number_format($tamanho_dados); ?> caracteres)
                            </small><br>
                        <?php else: ?>
                            <small class="text-warning">
                                <i class="fas fa-exclamation-triangle"></i> Nenhum dado recebido
                            </small><br>
                        <?php endif; ?>
                        <small class="text-muted" id="ultimaAtualizacao"></small>
                    </div>
                </div>
                
                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-number" id="totalRegistros">0</div>
                        <div class="stat-label">Total de Registros</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" id="filtrosAtivos">0</div>
                        <div class="stat-label">Filtros Aplicados</div>
                    </div>
                </div>

                <div id="filtrosAplicados" class="mt-3" style="display: none;">
                    <h6><i class="fas fa-filter"></i> Filtros Aplicados:</h6>
                    <div id="listaFiltros" class="d-flex flex-wrap gap-2"></div>
                </div>
            </div>

            <!-- Mapa -->
            <div id="map"></div>
        </div>
    </div>

    <!-- InfoWindow será usado em vez de modal para mostrar detalhes -->

    <script>
        let map;
        let markers = [];
        let polygons = [];
        let polylines = [];
        let lotesPolygons = [];
        let quadrasPolygons = [];
        let quarteiraoPolygons = [];
        let lotesPrefeituraPolygons = [];
        let dadosOriginais = [];
        let filtrosRecebidos = [];
        let coordenadasDesenhos = [];
        let poligonosLotesQuadras = [];
        let infoWindow;

        // Configurações do mapa
        const MAP_CONFIG = {
            center: { lat: -22.7594, lng: -47.1532 }, // Paulínia, SP
            zoom: 13,
            mapTypeId: 'hybrid'
        };

        async function initMap() {
            console.log('Inicializando Google Maps...');
            
            // Verificar se o elemento do mapa existe
            const mapElement = document.getElementById("map");
            if (!mapElement) {
                console.error('Elemento #map não encontrado!');
                mostrarErroMapa('Elemento do mapa não foi encontrado na página.');
                return;
            }
            
            console.log('Elemento do mapa encontrado:', mapElement);
            console.log('Dimensões do elemento:', {
                width: mapElement.offsetWidth,
                height: mapElement.offsetHeight,
                clientWidth: mapElement.clientWidth,
                clientHeight: mapElement.clientHeight
            });
            
            try {
                console.log('Carregando bibliotecas do Google Maps...');
                
                // Carregar bibliotecas necessárias do Google Maps (mesmo padrão do framework.js)
                const { Map } = await google.maps.importLibrary("maps");
                const { geometry } = await google.maps.importLibrary("geometry");
                const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");

                console.log('Bibliotecas carregadas, criando mapa...');
                console.log('Configuração do mapa:', MAP_CONFIG);

                // Criar o mapa com configurações similares ao framework.js
                map = new Map(mapElement, {
                    center: MAP_CONFIG.center,
                    zoom: MAP_CONFIG.zoom,
                    mapTypeId: MAP_CONFIG.mapTypeId,
                    zoomControl: true,
                    scaleControl: true,
                    streetViewControl: true,
                    fullscreenControl: false,
                    mapTypeControl: true,
                    styles: [{ featureType: "poi", stylers: [{ visibility: "off" }] }]
                });
                
                console.log('Mapa criado com sucesso:', map);
                
                // Criar InfoWindow global
                infoWindow = new google.maps.InfoWindow();
                console.log('InfoWindow criado com sucesso');
                
                // Adicionar evento para fechar InfoWindow ao clicar no mapa
                google.maps.event.addListener(map, "click", () => {
                    infoWindow.close();
                });
                
                // Aguardar um pouco para o mapa ser renderizado
                setTimeout(() => {
                    console.log('Forçando resize do mapa...');
                    google.maps.event.trigger(map, 'resize');
                    map.setCenter(MAP_CONFIG.center);
                }, 500);
                
                // Processar dados recebidos
                await processarDados();
                
            } catch (error) {
                console.error('Erro ao inicializar o mapa:', error);
                console.error('Stack trace:', error.stack);
                mostrarErroMapa('Erro ao carregar o mapa: ' + error.message + '. Verifique o console para mais detalhes.');
            }
        }

        async function processarDados() {
            console.log('Processando dados recebidos...');
            
            try {
                // Verificar se os dados vieram via POST (preferência) ou GET (fallback)
                let dadosParam = '';
                let filtrosParam = '';
                
                // Primeiro tentar obter do PHP (POST)
                <?php if (isset($_POST['dados']) && isset($_POST['filtros'])): ?>
                    dadosParam = <?php echo json_encode($_POST['dados']); ?>;
                    filtrosParam = <?php echo json_encode($_POST['filtros']); ?>;
                    console.log('Dados recebidos via POST');
                <?php else: ?>
                    // Fallback para GET se POST não estiver disponível
                    const urlParams = new URLSearchParams(window.location.search);
                    dadosParam = urlParams.get('dados');
                    filtrosParam = urlParams.get('filtros');
                    console.log('Dados recebidos via GET (fallback)');
                <?php endif; ?>
                
                console.log('Parâmetros recebidos:');
                console.log('- Dados:', dadosParam ? 'Presente' : 'Ausente');
                console.log('- Filtros:', filtrosParam ? 'Presente' : 'Ausente');

                if (dadosParam) {
                    try {
                        // Se veio do POST, já está decodificado. Se veio do GET, precisa decodificar
                        if (typeof dadosParam === 'string' && dadosParam.startsWith('%')) {
                            dadosOriginais = JSON.parse(decodeURIComponent(dadosParam));
                        } else if (typeof dadosParam === 'string') {
                            dadosOriginais = JSON.parse(dadosParam);
                        } else {
                            dadosOriginais = dadosParam; // Já é objeto
                        }
                        console.log('Dados processados:', dadosOriginais);
                        console.log('Total de registros recebidos:', dadosOriginais.length);
                    } catch (e) {
                        console.error('Erro ao processar dados:', e);
                        dadosOriginais = [];
                    }
                }

                if (filtrosParam) {
                    try {
                        // Se veio do POST, já está decodificado. Se veio do GET, precisa decodificar
                        if (typeof filtrosParam === 'string' && filtrosParam.startsWith('%')) {
                            filtrosRecebidos = JSON.parse(decodeURIComponent(filtrosParam));
                        } else if (typeof filtrosParam === 'string') {
                            filtrosRecebidos = JSON.parse(filtrosParam);
                        } else {
                            filtrosRecebidos = filtrosParam; // Já é objeto
                        }
                        console.log('Filtros processados:', filtrosRecebidos);
                    } catch (e) {
                        console.error('Erro ao processar filtros:', e);
                        filtrosRecebidos = [];
                    }
                }

                // Atualizar estatísticas
                atualizarEstatisticas();
                
                // Buscar coordenadas dos desenhos no banco
                await buscarCoordenadasDesenhos();
                
                esconderLoading();
                
            } catch (error) {
                console.error('Erro ao processar dados:', error);
                esconderLoading();
            }
        }

        async function buscarCoordenadasDesenhos() {
            console.log('Buscando coordenadas dos desenhos no banco...');
            
            if (!dadosOriginais || dadosOriginais.length === 0) {
                console.log('Nenhum dado para buscar coordenadas');
                return;
            }

            try {
                console.log('Enviando dados para buscar_coordenadas.php:', {
                    //registros: dadosOriginais.slice(0, 2) // Mostrar apenas 2 primeiros para debug
                });

                // Fazer requisição AJAX para buscar coordenadas
                const response = await fetch('buscar_coordenadas.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        registros: dadosOriginais
                    })
                });

                console.log('Status da resposta:', response.status);
                console.log('Headers da resposta:', [...response.headers.entries()]);

                if (!response.ok) {
                    const responseText = await response.text();
                    console.error('Resposta de erro:', responseText);
                    throw new Error(`Erro HTTP ${response.status}: ${responseText}`);
                }

                // Tentar obter texto primeiro para debug
                const responseText = await response.text();
                console.log('Texto bruto da resposta:', responseText);

                // Tentar fazer parse do JSON
                let resultado;
                try {
                    resultado = JSON.parse(responseText);
                } catch (jsonError) {
                    console.error('Erro ao fazer parse do JSON:', jsonError);
                    console.error('Resposta que causou erro:', responseText.substring(0, 500));
                    throw new Error('Resposta inválida do servidor: ' + jsonError.message);
                }
                
                console.log('Resposta da busca de coordenadas:', resultado);

                if (resultado.success) {
                    coordenadasDesenhos = resultado.coordenadas;
                    poligonosLotesQuadras = resultado.poligonos || [];
                    
                    console.log(`Encontradas ${coordenadasDesenhos.length} coordenadas`);
                    console.log(`Encontrados ${poligonosLotesQuadras.length} polígonos`);
                    console.log('Estatísticas:', resultado.stats);
                    
                    // DEBUG: Verificar quadrículas nos marcadores
                    if (coordenadasDesenhos.length > 0) {
                        const marcadorExemplo = coordenadasDesenhos[0];
                        console.log('🔍 EXEMPLO MARCADOR:', {
                            quarteirao: marcadorExemplo.quarteirao,
                            quadra: marcadorExemplo.quadra,
                            lote: marcadorExemplo.lote,
                            quadricula: marcadorExemplo.dados_completos_desenho?.quadricula || 'SEM QUADRÍCULA'
                        });
                        
                        const quadriculas = [...new Set(coordenadasDesenhos.map(m => 
                            m.dados_completos_desenho?.quadricula).filter(q => q))];
                        console.log('🗺️ QUADRÍCULAS ENCONTRADAS NOS MARCADORES:', quadriculas);
                    }
                    
                    // DEBUG: Mostrar queries SQL usadas
                    if (resultado.debug_queries) {
                        console.log('=== QUERIES SQL EXECUTADAS ===');
                        resultado.debug_queries.forEach((query, index) => {
                            if (query.sql_debug) {
                                console.log(`Query ${index + 1}:`, query.sql_debug);
                                console.log(`Parâmetros ${index + 1}:`, query.params_debug);
                                console.log(`Tipo: ${query.tipo}`);
                                console.log(`Resultados: ${query.resultados_encontrados || 0}`);
                                if (query.poligonos_proximos !== undefined) {
                                    console.log(`🎯 Filtro Proximidade (500m):`);
                                    console.log(`  - Próximos: ${query.poligonos_proximos}`);
                                    console.log(`  - Distantes: ${query.poligonos_distantes}`);
                                    console.log(`  - Taxa: ${query.resultados_encontrados > 0 ? ((query.poligonos_proximos / query.resultados_encontrados) * 100).toFixed(1) : 0}%`);
                                }
                                console.log('---');
                            }
                        });
                        console.log('===============================');
                    }
                    
                    // Debug detalhado dos polígonos recebidos
                    console.log('=== DEBUG POLÍGONOS RECEBIDOS ===');
                    poligonosLotesQuadras.forEach((poligono, index) => {
                        console.log(`Polígono ${index + 1}:`, {
                            camada: poligono.camada,
                            tipo: poligono.tipo,
                            quarteirao: poligono.quarteirao,
                            quadra: poligono.quadra,
                            lote: poligono.lote,
                            relevante: poligono.relevante,
                            coordenadas_tipo: typeof poligono.coordenadas,
                            coordenadas_length: Array.isArray(poligono.coordenadas) ? poligono.coordenadas.length : 'N/A',
                            coordenadas_sample: Array.isArray(poligono.coordenadas) ? poligono.coordenadas.slice(0, 2) : poligono.coordenadas
                        });
                    });
                    console.log('==================================');
                    
                    // Atualizar estatísticas na interface
                    atualizarEstatisticasDesenhos(resultado.stats);
                    
                    // Criar elementos no mapa
                    await criarElementosNoMapa();
                    
                    // Criar polígonos de quadras
                    await criarPoligonosQuadras();
                    
                    // Carregar lotes da prefeitura
                    await carregarLotesPrefeitura();
                    
                    // Inicializar estado dos botões de camadas
                    inicializarBotoesCamadas();
                    
                    // Centralizar mapa se houver elementos
                    const totalElementos = markers.length + polygons.length + polylines.length + 
                                          lotesPolygons.length + quadrasPolygons.length + quarteiraoPolygons.length + 
                                          lotesPrefeituraPolygons.length;
                    
                    if (totalElementos > 0) {
                        console.log('Centralizando mapa nos elementos encontrados...');
                        centralizarMapa();
                    } else {
                        console.log('Nenhum elemento encontrado para centralizar');
                    }
                    
                } else {
                    console.error('Erro ao buscar coordenadas:', resultado.error);
                    alert('Erro ao buscar coordenadas: ' + (resultado.mensagem || resultado.error));
                }

            } catch (error) {
                console.error('Erro na requisição de coordenadas:', error);
                alert('Erro ao buscar coordenadas no banco de dados: ' + error.message);
            }
        }

        async function criarElementosNoMapa() {
            console.log('Criando elementos no mapa...');
            
            let marcadoresCriados = 0;
            let poligonosCriados = 0;
            let polilinhasCriadas = 0;
            let marcadoresDuplicados = 0;

            // IMPORTANTE: Limpar e repovoar a variável global dos marcadores
            coordenadasMarcadoresGlobal = [];
            
            // Set para controlar marcadores únicos (evitar duplicação nas bordas das quadrículas)
            const marcadoresUnicos = new Set();

            for (const item of coordenadasDesenhos) {
                try {
                    const tipo = item.tipo.toLowerCase();
                    const coordenadas = item.coordenadas;
                    
                    console.log(`Processando ${tipo}:`, item);

                    switch (tipo) {
                        case 'marcador':
                            // Verificar duplicação usando ID único baseado em quarteirao/quadra/lote
                            const idMarcador = `${item.quarteirao || 'null'}/${item.quadra || 'null'}/${item.lote || 'null'}`;
                            
                            if (marcadoresUnicos.has(idMarcador)) {
                                marcadoresDuplicados++;
                                console.log(`🔄 Marcador duplicado ignorado: ${idMarcador}`);
                                break; // Pular este marcador duplicado
                            }
                            
                            // Marcar como processado
                            marcadoresUnicos.add(idMarcador);
                            
                            // Adicionar à variável global para uso nos filtros
                            coordenadasMarcadoresGlobal.push(item);
                            await criarMarcador(item);
                            marcadoresCriados++;
                            break;
                            
                        case 'poligono':
                        case 'polígono':
                            await criarPoligono(item);
                            poligonosCriados++;
                            break;
                            
                        case 'polilinha':
                            // Verificar se é lote (deve ser tratado como polígono) ou polilinha normal
                            if (item.camada === 'lote') {
                                console.log(`Processando polilinha de lote como polígono: ${item.id_desenho}`);
                                await criarPoligono(item);
                                poligonosCriados++;
                            } else {
                                await criarPolilinha(item);
                                polilinhasCriadas++;
                            }
                            break;
                            
                        default:
                            console.warn('Tipo de desenho desconhecido:', tipo);
                    }
                    
                } catch (error) {
                    console.error('Erro ao criar elemento:', error, item);
                }
            }

            console.log(`=== RESUMO DE CRIAÇÃO DE ELEMENTOS ===`);
            console.log(`✅ Marcadores únicos criados: ${marcadoresCriados}`);
            console.log(`🔄 Marcadores duplicados ignorados: ${marcadoresDuplicados}`);
            console.log(`📐 Polígonos criados: ${poligonosCriados}`);
            console.log(`📏 Polilinhas criadas: ${polilinhasCriadas}`);
            console.log(`🔍 Marcadores carregados para filtro: ${coordenadasMarcadoresGlobal.length}`);
            console.log(`=====================================`);
        }

        async function criarPoligonosQuadras() {
            console.log('Criando polígonos da quadrícula (COM filtro de proximidade 50m)...');
            
            let poligonosCreated = 0;
            let poligonosRejeitados = 0;
            let totalProcessados = 0;
            let quadrasCreated = 0;
            let lotesCreated = 0;
            let poligonosDuplicados = 0; // Contador de duplicados

            console.log(`Iniciando processamento de ${poligonosLotesQuadras.length} polígonos...`);

            // Criar Set para controlar IDs únicos e evitar duplicação
            const idsProcessados = new Set();

            // Debug: verificar tipos de camadas disponíveis
            const tiposCamadas = {};
            poligonosLotesQuadras.forEach(item => {
                const camada = item.camada ? item.camada.toLowerCase() : 'undefined';
                tiposCamadas[camada] = (tiposCamadas[camada] || 0) + 1;
            });
            console.log('📊 Tipos de camadas encontradas:', tiposCamadas);

            for (const item of poligonosLotesQuadras) {
                totalProcessados++;
                try {
                    // Verificar se já processamos este ID para evitar duplicação
                    const idDesenho = item.id_desenho;
                    if (idsProcessados.has(idDesenho)) {
                        poligonosDuplicados++;
                        console.log(`🔄 Polígono ID ${idDesenho} ignorado (duplicação de quadrícula)`);
                        continue;
                    }
                    
                    // Marcar como processado
                    idsProcessados.add(idDesenho);

                    const camada = item.camada.toLowerCase();
                    const coordenadas = item.coordenadas;
                    
                    // APLICAR FILTRO POR QUADRA: apenas polígonos das quadras e lotes que têm marcadores
                    const pertenceAQuadraComMarcadores = poligonoPerteniceAQuadraComMarcadores(item, coordenadasMarcadoresGlobal);
                    
                    if (!pertenceAQuadraComMarcadores) {
                        poligonosRejeitados++;
                        continue;
                    }
                    
                    // Log detalhado já é feito na função de filtro
                    
                    console.log(`Processando ${camada}:`, item);

                    // Processar coordenadas do polígono
                    let paths = [];
                    
                    console.log(`Processando coordenadas para ${camada}:`, coordenadas);
                    
                    if (Array.isArray(coordenadas)) {
                        paths = coordenadas.map(coord => {
                            if (typeof coord === 'object' && coord.lat && coord.lng) {
                                return { lat: parseFloat(coord.lat), lng: parseFloat(coord.lng) };
                            } else if (Array.isArray(coord) && coord.length >= 2) {
                                return { lat: parseFloat(coord[1]), lng: parseFloat(coord[0]) };
                            }
                            return null;
                        }).filter(coord => coord !== null);
                    } else if (typeof coordenadas === 'string') {
                        try {
                            const coordenadasParsed = JSON.parse(coordenadas);
                            if (Array.isArray(coordenadasParsed)) {
                                paths = coordenadasParsed.map(coord => {
                                    if (typeof coord === 'object' && coord.lat && coord.lng) {
                                        return { lat: parseFloat(coord.lat), lng: parseFloat(coord.lng) };
                                    } else if (Array.isArray(coord) && coord.length >= 2) {
                                        return { lat: parseFloat(coord[1]), lng: parseFloat(coord[0]) };
                                    }
                                    return null;
                                }).filter(coord => coord !== null);
                            }
                        } catch (e) {
                            console.error('Erro ao fazer parse das coordenadas:', e, coordenadas);
                        }
                    }
                    
                    console.log(`Paths processados para ${camada}:`, paths);

                    if (paths.length > 0) {
                        // Definir cores e estilos por camada
                        let cor, strokeWeight, fillOpacity, zIndex;
                        if (camada === 'quadra') {
                            cor = item.cor || '#0078D7'; // Azul para quadras
                            strokeWeight = 3;
                            fillOpacity = 0.25;
                            zIndex = 2;
                        } else if (camada === 'lote') {
                            cor = item.cor || '#FF6B6B'; // Vermelho para lotes
                            strokeWeight = 2;
                            fillOpacity = 0.2;
                            zIndex = 3; // Lotes por cima das quadras
                        } else {
                            cor = item.cor || '#9E9E9E'; // Cinza para outros
                            strokeWeight = 1;
                            fillOpacity = 0.15;
                            zIndex = 1;
                        }
                        
                        const polygon = new google.maps.Polygon({
                            paths: paths,
                            strokeColor: cor,
                            strokeOpacity: 1,
                            strokeWeight: strokeWeight,
                            fillColor: cor,
                            fillOpacity: fillOpacity,
                            map: map, // Garantir que seja exibido no mapa imediatamente
                            zIndex: zIndex,
                            visible: true // Forçar visibilidade
                        });

                        // Armazenar informações adicionais no polígono
                        polygon.camada = camada;
                        polygon.dadosOriginais = item;
                        
                        console.log(`✅ Polígono ${camada} criado e adicionado ao mapa:`, {
                            id: item.id_desenho,
                            quarteirao: item.quarteirao,
                            quadra: item.quadra,
                            lote: item.lote,
                            pathsCount: paths.length,
                            cor: cor,
                            visible: polygon.getVisible(),
                            map: polygon.getMap() !== null
                        });
                        
                        // Adicionar evento de clique
                        google.maps.event.addListener(polygon, "click", (event) => {
                            mostrarDetalhesPoligono(item, event.latLng);
                        });

                        // Adicionar polígonos da quadrícula
                        quadrasPolygons.push(polygon);
                        poligonosCreated++;
                        
                        // Contar por tipo
                        if (camada === 'quadra') {
                            quadrasCreated++;
                        } else if (camada === 'lote') {
                            lotesCreated++;
                        }
                        
                        console.log(`✅ Polígono ${camada} criado: ${item.quarteirao || 'N/A'}/${item.quadra || 'N/A'}/${item.lote || 'N/A'}`);
                    }
                    
                } catch (error) {
                    console.error('Erro ao criar polígono:', error, item);
                }
            }

            console.log(`=== RESUMO FILTRO PROXIMIDADE 50m ===`);
            console.log(`Total recebidos: ${totalProcessados}`);
            console.log(`Duplicados ignorados: ${poligonosDuplicados}`);
            console.log(`Únicos processados: ${totalProcessados - poligonosDuplicados}`);
            console.log(`Polígonos aprovados (≤50m): ${poligonosCreated}`);
            console.log(`  - Quadras: ${quadrasCreated}`);
            console.log(`  - Lotes: ${lotesCreated}`);
            console.log(`Polígonos rejeitados (>50m): ${poligonosRejeitados}`);
            console.log(`Taxa de proximidade: ${totalProcessados > 0 ? ((poligonosCreated / (totalProcessados - poligonosDuplicados)) * 100).toFixed(1) : 0}%`);
            console.log(`===================================`);
            
            // Salvar estatísticas globalmente para exibição
            window.quadrasRejeitadas = poligonosRejeitados;
        }

        async function carregarLotesPrefeitura() {
            console.log('Carregando lotes da prefeitura (otimizado)...');
            
            // Extrair dados específicos dos registros para filtro otimizado
            const filtroEspecifico = extrairDadosEspecificosParaLotes();
            
            console.log('Filtro específico gerado:', filtroEspecifico);

            // Carregar lotes apenas para as quadrículas que têm dados
            for (const quadricula of filtroEspecifico.quadriculas) {
                await carregarLotesPrefeituraQuadriculaOtimizada(quadricula, filtroEspecifico);
            }

            console.log(`=== RESUMO DOS FILTROS INTELIGENTES ===`);
            console.log(`Total de lotes da prefeitura carregados: ${lotesPrefeituraPolygons.length}`);
            console.log(`Filtro baseado em ${filtroEspecifico.coordenadasMarcadores ? filtroEspecifico.coordenadasMarcadores.length : 0} marcadores encontrados`);
            console.log(`Quarteirões únicos: ${filtroEspecifico.quarteiroes.length}`);
            console.log(`Quadrículas: ${filtroEspecifico.quadriculas.join(', ')}`);
            console.log(`========================================`);
        }

        function extrairDadosEspecificosParaLotes() {
            const quadriculas = new Set();
            const quarteiroes = new Set();
            const coordenadasMarcadores = [];
            
            // Extrair coordenadas dos marcadores encontrados para filtro geoespacial
            coordenadasDesenhos.forEach(item => {
                if (item.tipo.toLowerCase() === 'marcador' && item.coordenadas) {
                    let lat, lng;
                    
                    // Processar coordenadas do marcador
                    if (Array.isArray(item.coordenadas) && item.coordenadas.length > 0) {
                        const coord = item.coordenadas[0];
                        if (coord && coord.lat && coord.lng) {
                            lat = parseFloat(coord.lat);
                            lng = parseFloat(coord.lng);
                        }
                    }
                    
                    if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
                        coordenadasMarcadores.push({
                            lat: lat,
                            lng: lng,
                            quarteirao: item.quarteirao,
                            quadra: item.quadra,
                            lote: item.lote
                        });
                    }
                }
            });
            
            // Extrair dados dos registros originais (busca mais ampla)
            dadosOriginais.forEach(registro => {
                // Extrair quadrícula
                const quadricula_candidatos = ['quadricula', 'ortofoto', 'codigo_quadricula'];
                let quadricula = null;
                for (const campo of quadricula_candidatos) {
                    if (registro[campo]) {
                        quadricula = registro[campo];
                        quadriculas.add(quadricula);
                        break;
                    }
                }
                
                // Extrair quarteirão
                let quarteirao = registro['cara_quarteirao'] || registro['quarteirao'] || registro['quarteirao_cara'];
                if (quarteirao) {
                    // Aplicar mesmo padding que no backend
                    quarteirao = quarteirao.toString().padStart(4, '0');
                    quarteiroes.add(quarteirao);
                }
            });
            
            // Se não encontrou quadrículas nos dados originais, tentar dos polígonos
            if (quadriculas.size === 0) {
                poligonosLotesQuadras.forEach(item => {
                    if (item.quadricula) {
                        quadriculas.add(item.quadricula);
                    }
                });
            }
            
            // Se não encontrou quarteirões dos dados originais, tentar dos polígonos
            if (quarteiroes.size === 0) {
                poligonosLotesQuadras.forEach(item => {
                    if (item.quarteirao) {
                        quarteiroes.add(item.quarteirao);
                    }
                });
            }
            
            return {
                quadriculas: Array.from(quadriculas),
                quarteiroes: Array.from(quarteiroes),
                coordenadasMarcadores: coordenadasMarcadores
            };
        }

        async function carregarLotesPrefeituraQuadriculaOtimizada(quadricula, filtroEspecifico) {
            try {
                const url = `../loteamentos_quadriculas/geojson/lotes_prefeitura_quadricula_${quadricula}.geojson`;
                console.log(`Carregando lotes da prefeitura (otimizado) para quadrícula ${quadricula}:`, url);
                
                const response = await fetch(url, {
                    cache: 'no-store'
                });

                if (!response.ok) {
                    if (response.status === 404) {
                        console.log(`Arquivo de lotes da prefeitura não encontrado para quadrícula ${quadricula}`);
                        return;
                    }
                    throw new Error(`Erro HTTP ${response.status}: ${response.statusText}`);
                }

                const geojsonData = await response.json();
                console.log(`GeoJSON carregado para ${quadricula}:`, geojsonData.features?.length || 0, 'features total');

                if (geojsonData && geojsonData.features) {
                    let lotesCarregados = 0;
                    let lotesDescartados = 0;

                    geojsonData.features.forEach((feature, index) => {
                        if (feature.geometry && feature.geometry.type === 'Polygon' && feature.geometry.coordinates) {
                            // FILTRO OTIMIZADO: verificar se este lote é relevante
                            const deveCarregar = verificarSeDeveCarregarLote(feature, filtroEspecifico);
                            
                            if (!deveCarregar) {
                                lotesDescartados++;
                                return; // Pular este lote
                            }

                            try {
                                // Converter coordenadas do GeoJSON para formato Google Maps
                                const coordinates = feature.geometry.coordinates[0].map(coord => ({
                                    lat: coord[1],  // latitude é o segundo elemento
                                    lng: coord[0]   // longitude é o primeiro elemento
                                }));

                                // Criar polígono da prefeitura
                                const polygon = new google.maps.Polygon({
                                    paths: coordinates,
                                    strokeColor: '#FF6B35',    // Cor laranja
                                    strokeOpacity: 0.8,
                                    strokeWeight: 3,
                                    fillColor: '#FF6B35',
                                    fillOpacity: 0.3,
                                    map: map,                   // Visível por padrão
                                    clickable: true,
                                    zIndex: 15,                 // Z-index alto para ficar por cima
                                    visible: true               // Forçar visibilidade
                                });
                                
                                console.log(`✅ Lote da prefeitura criado:`, {
                                    quadricula: quadricula,
                                    coordenadas: coordinates.length,
                                    visible: polygon.getVisible(),
                                    map: polygon.getMap() !== null,
                                    properties: feature.properties
                                });

                                // Adicionar dados extras ao polígono
                                polygon.quadricula = quadricula;
                                polygon.feature = feature;
                                polygon.camada = 'lotesPrefeitura';

                                // Adicionar evento de clique
                                polygon.addListener('click', (event) => {
                                    mostrarDetalhesLotePrefeitura(feature, event.latLng, quadricula);
                                });

                                lotesPrefeituraPolygons.push(polygon);
                                lotesCarregados++;

                            } catch (error) {
                                console.error('Erro ao processar feature do lote da prefeitura:', error);
                            }
                        }
                    });

                    console.log(`Quadrícula ${quadricula}: ${lotesCarregados} lotes da prefeitura carregados, ${lotesDescartados} descartados (filtro inteligente)`);
                }

            } catch (error) {
                console.error(`Erro ao carregar lotes da prefeitura para quadrícula ${quadricula}:`, error);
            }
        }

        function verificarSeDeveCarregarLote(feature, filtroEspecifico) {
            // Se não há marcadores específicos, usar filtro por quarteirão (fallback)
            if (!filtroEspecifico.coordenadasMarcadores || filtroEspecifico.coordenadasMarcadores.length === 0) {
                if (!filtroEspecifico.quarteiroes.length) {
                    return true;
                }

                // Tentar extrair informações do lote do GeoJSON
                const props = feature.properties || {};
                
                // Possíveis campos que podem conter informações de quarteirão
                const quarteiraoCandidatos = ['quarteirao', 'QUARTEIRAO', 'quarteirao_id', 'bairro', 'distrito'];
                
                let quarteirao = null;
                
                // Tentar extrair quarteirão
                for (const campo of quarteiraoCandidatos) {
                    if (props[campo]) {
                        quarteirao = props[campo].toString().padStart(4, '0');
                        break;
                    }
                }
                
                // Verificar se o quarteirão está na lista dos filtrados
                if (quarteirao && filtroEspecifico.quarteiroes.includes(quarteirao)) {
                    return true; // Lote pertence a um quarteirão filtrado
                }
                
                // Estratégia alternativa: se não conseguiu identificar quarteirão específico,
                // carregar o lote (melhor mostrar demais que faltar)
                return !quarteirao;
            }

            // FILTRO GEOESPACIAL: Verificar se o lote está próximo a algum marcador
            if (feature.geometry && feature.geometry.type === 'Polygon' && feature.geometry.coordinates) {
                try {
                    // Calcular centro do polígono do lote
                    const coordenadas = feature.geometry.coordinates[0];
                    let somaDeLat = 0;
                    let somaDeLng = 0;
                    let pontos = 0;
                    
                    coordenadas.forEach(coord => {
                        if (Array.isArray(coord) && coord.length >= 2) {
                            somaDeLng += coord[0]; // longitude
                            somaDeLat += coord[1]; // latitude
                            pontos++;
                        }
                    });
                    
                    if (pontos > 0) {
                        const centroLote = {
                            lat: somaDeLat / pontos,
                            lng: somaDeLng / pontos
                        };
                        
                        // Verificar proximidade com marcadores (raio de ~500 metros)
                        const RAIO_PROXIMIDADE = 0.005; // aproximadamente 500m em graus
                        
                        for (const marcador of filtroEspecifico.coordenadasMarcadores) {
                            const distancia = calcularDistanciaSimples(centroLote, marcador);
                            
                            if (distancia <= RAIO_PROXIMIDADE) {
                                console.log(`Lote da prefeitura aprovado por proximidade (${(distancia * 111000).toFixed(0)}m) com marcador ${marcador.quarteirao}/${marcador.quadra}/${marcador.lote}`);
                                return true;
                            }
                        }
                        
                        console.log(`Lote da prefeitura rejeitado - muito distante dos marcadores (min: ${(Math.min(...filtroEspecifico.coordenadasMarcadores.map(m => calcularDistanciaSimples(centroLote, m))) * 111000).toFixed(0)}m)`);
                        return false;
                    }
                } catch (error) {
                    console.error('Erro ao calcular proximidade do lote:', error);
                    return true; // Em caso de erro, mostrar o lote
                }
            }
            
            return true; // Fallback: mostrar se não conseguir determinar
        }
        
        // Função auxiliar para calcular distância simples entre dois pontos
        function calcularDistanciaSimples(ponto1, ponto2) {
            const deltaLat = ponto1.lat - ponto2.lat;
            const deltaLng = ponto1.lng - ponto2.lng;
            return Math.sqrt(deltaLat * deltaLat + deltaLng * deltaLng);
        }

        // Variáveis globais para filtros
        let quadrasComMarcadores = null;
        let coordenadasMarcadoresGlobal = [];

        /**
         * Extrai as quadras que possuem marcadores
         */
        function extrairQuadrasComMarcadores(marcadores) {
            if (quadrasComMarcadores !== null) {
                return quadrasComMarcadores;
            }

            quadrasComMarcadores = new Set();
            
            if (!marcadores || marcadores.length === 0) {
                return quadrasComMarcadores;
            }

            marcadores.forEach(marcador => {
                if (marcador.quarteirao && marcador.quadra) {
                    const chaveQuadra = `${marcador.quarteirao}/${marcador.quadra}`;
                    quadrasComMarcadores.add(chaveQuadra);
                }
            });

            console.log('🔍 Quadras com marcadores encontradas:', Array.from(quadrasComMarcadores));
            return quadrasComMarcadores;
        }

        /**
         * Verifica se um polígono está próximo aos marcadores (filtro por proximidade geográfica)
         */
        function poligonoPerteniceAQuadraComMarcadores(poligono, marcadores, raioMaximo = 50) {
            try {
                // Se não há marcadores, não mostrar nenhum polígono
                if (!marcadores || marcadores.length === 0) {
                    return false;
                }

                // Se o polígono tem informação de quarteirão/quadra, usar filtro tradicional
                if (poligono.quarteirao && poligono.quadra) {
                    const quadrasMarcadores = extrairQuadrasComMarcadores(marcadores);
                    const chavePoligono = `${poligono.quarteirao}/${poligono.quadra}`;
                    const pertence = quadrasMarcadores.has(chavePoligono);
                    
                    if (pertence) {
                        console.log(`✅ Polígono aprovado por correspondência exata: ${chavePoligono}`);
                    }
                    
                    return pertence;
                }

                // Se não tem informação de quarteirão/quadra, usar filtro por proximidade
                console.log(`🌍 Aplicando filtro por proximidade (${raioMaximo}m) para polígono ID ${poligono.id_desenho}`);
                
                // Calcular centróide do polígono
                const coordenadas = poligono.coordenadas;
                if (!Array.isArray(coordenadas) || coordenadas.length === 0) {
                    return false;
                }

                let totalLat = 0;
                let totalLng = 0;
                let totalPontos = 0;

                coordenadas.forEach(coord => {
                    if (coord && typeof coord.lat === 'number' && typeof coord.lng === 'number') {
                        totalLat += coord.lat;
                        totalLng += coord.lng;
                        totalPontos++;
                    }
                });

                if (totalPontos === 0) {
                    return false;
                }

                const centroPoligono = {
                    lat: totalLat / totalPontos,
                    lng: totalLng / totalPontos
                };

                // Verificar distância para cada marcador
                for (const marcador of marcadores) {
                    if (marcador.coordenadas && Array.isArray(marcador.coordenadas) && marcador.coordenadas.length > 0) {
                        const coordMarcador = marcador.coordenadas[0];
                        if (coordMarcador && typeof coordMarcador.lat === 'number' && typeof coordMarcador.lng === 'number') {
                            const distancia = calcularDistanciaMetros(
                                centroPoligono.lat,
                                centroPoligono.lng,
                                coordMarcador.lat,
                                coordMarcador.lng
                            );

                            if (distancia <= raioMaximo) {
                                console.log(`✅ Polígono ID ${poligono.id_desenho} aprovado por proximidade: ${distancia.toFixed(0)}m do marcador ${marcador.quarteirao}/${marcador.quadra}/${marcador.lote}`);
                                return true;
                            }
                        }
                    }
                }

                // Calcular distância mínima para o log
                let distanciaMinima = Infinity;
                for (const marcador of marcadores) {
                    if (marcador.coordenadas && Array.isArray(marcador.coordenadas) && marcador.coordenadas.length > 0) {
                        const coordMarcador = marcador.coordenadas[0];
                        if (coordMarcador && typeof coordMarcador.lat === 'number' && typeof coordMarcador.lng === 'number') {
                            const distancia = calcularDistanciaMetros(
                                centroPoligono.lat,
                                centroPoligono.lng,
                                coordMarcador.lat,
                                coordMarcador.lng
                            );
                            distanciaMinima = Math.min(distanciaMinima, distancia);
                        }
                    }
                }

                console.log(`❌ Polígono ID ${poligono.id_desenho} rejeitado por distância: ${distanciaMinima.toFixed(0)}m (>${raioMaximo}m)`);
                return false;

            } catch (error) {
                console.error('Erro ao verificar proximidade do polígono:', error);
                return false;
            }
        }

        /**
         * Calcula a distância em metros entre duas coordenadas GPS usando fórmula de Haversine
         */
        function calcularDistanciaMetros(lat1, lng1, lat2, lng2) {
            const R = 6371000; // Raio da Terra em metros
            const lat1Rad = lat1 * Math.PI / 180;
            const lat2Rad = lat2 * Math.PI / 180;
            const deltaLatRad = (lat2 - lat1) * Math.PI / 180;
            const deltaLngRad = (lng2 - lng1) * Math.PI / 180;

            const a = Math.sin(deltaLatRad / 2) * Math.sin(deltaLatRad / 2) +
                     Math.cos(lat1Rad) * Math.cos(lat2Rad) *
                     Math.sin(deltaLngRad / 2) * Math.sin(deltaLngRad / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

            return R * c; // Distância em metros
        }

        /**
         * Função para ajustar dinamicamente o raio de proximidade
         * Para usar no console: ajustarRaioProximidade(50) - define raio de 50m
         */
        function ajustarRaioProximidade(novoRaio) {
            if (typeof novoRaio !== 'number' || novoRaio <= 0) {
                console.error('❌ Raio deve ser um número positivo');
                return;
            }
            
            console.log(`🎯 Ajustando raio de proximidade de 50m para ${novoRaio}m`);
            console.log('🔄 Reprocessando polígonos com novo raio...');
            
            // Atualizar o input na interface
            const inputRaio = document.getElementById('raioProximidade');
            if (inputRaio) {
                inputRaio.value = novoRaio;
            }
            
            // Reprocessar polígonos com novo raio
            criarPoligonosQuadrasComRaio(novoRaio);
        }

        /**
         * Função para alternar visibilidade dos lotes da quadrícula
         */
        function alternarVisibilidadeLotesQuadricula(visivel) {
            console.log(`${visivel ? '👁️ Mostrando' : '🙈 Ocultando'} lotes da quadrícula...`);
            
            if (!window.poligonosQuadras) {
                console.warn('⚠️ Nenhum polígono da quadrícula encontrado');
                return;
            }
            
            let lotesAfetados = 0;
            
            window.poligonosQuadras.forEach(polygon => {
                if (polygon.camada === 'lote') {
                    polygon.setMap(visivel ? map : null);
                    lotesAfetados++;
                }
            });
            
            console.log(`📊 ${lotesAfetados} lotes da quadrícula ${visivel ? 'exibidos' : 'ocultados'}`);
        }

        /**
         * Função para alternar visibilidade das quadras da quadrícula
         */
        function alternarVisibilidadeQuadrasQuadricula(visivel) {
            console.log(`${visivel ? '👁️ Mostrando' : '🙈 Ocultando'} quadras da quadrícula...`);
            
            if (!window.poligonosQuadras) {
                console.warn('⚠️ Nenhum polígono da quadrícula encontrado');
                return;
            }
            
            let quadrasAfetadas = 0;
            
            window.poligonosQuadras.forEach(polygon => {
                if (polygon.camada === 'quadra') {
                    polygon.setMap(visivel ? map : null);
                    quadrasAfetadas++;
                }
            });
            
            console.log(`📊 ${quadrasAfetadas} quadras da quadrícula ${visivel ? 'exibidas' : 'ocultadas'}`);
        }

        /**
         * Função para toggle do menu de camadas
         */
        function toggleMenuCamadas() {
            const dropdown = document.getElementById('dropdownCamadas');
            const btnCamadas = document.getElementById('btnCamadas');
            
            if (dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
                btnCamadas.classList.remove('open');
            } else {
                dropdown.classList.add('show');
                btnCamadas.classList.add('open');
            }
        }

        /**
         * Função para toggle das quadras
         */
        function toggleQuadras(visivel) {
            console.log(`🔲 Toggle Quadras: ${visivel ? 'Ativado' : 'Desativado'}`);
            alternarVisibilidadeQuadrasQuadricula(visivel);
        }

        /**
         * Função para toggle dos lotes
         */
        function toggleLotes(visivel) {
            console.log(`🔴 Toggle Lotes: ${visivel ? 'Ativado' : 'Desativado'}`);
            alternarVisibilidadeLotesQuadricula(visivel);
        }

        /**
         * Função para toggle dos marcadores
         */
        function toggleMarcadores(visivel) {
            console.log(`🟢 Toggle Marcadores: ${visivel ? 'Ativado' : 'Desativado'}`);
            
            // Verificar se há marcadores no array global
            if (!markers || markers.length === 0) {
                console.warn('⚠️ Nenhum marcador encontrado no array global');
                return;
            }
            
            let marcadoresAfetados = 0;
            
            // Usar o array global de marcadores do Google Maps
            markers.forEach(marker => {
                if (marker && marker.setMap) {
                    marker.setMap(visivel ? map : null);
                    marcadoresAfetados++;
                }
            });
            
            console.log(`📊 ${marcadoresAfetados} marcadores ${visivel ? 'exibidos' : 'ocultados'}`);
        }

        /**
         * Função para toggle dos lotes da prefeitura
         */
        function toggleLotesPrefeitura(visivel) {
            console.log(`🏢 Toggle Lotes Prefeitura: ${visivel ? 'Ativado' : 'Desativado'}`);
            
            if (visivel && (!window.lotesPrefeituraPolygons || window.lotesPrefeituraPolygons.length === 0)) {
                // Se não há lotes carregados e quer mostrar, carregar primeiro
                carregarLotesPrefeitura();
                return;
            }
            
            if (!window.lotesPrefeituraPolygons) {
                console.warn('⚠️ Nenhum lote da prefeitura encontrado');
                return;
            }
            
            let lotesAfetados = 0;
            
            window.lotesPrefeituraPolygons.forEach(polygon => {
                if (polygon.setMap) {
                    polygon.setMap(visivel ? map : null);
                    lotesAfetados++;
                }
            });
            
            console.log(`📊 ${lotesAfetados} lotes da prefeitura ${visivel ? 'exibidos' : 'ocultados'}`);
        }


        /**
         * Carregar lotes da prefeitura
         */
        async function carregarLotesPrefeitura() {
            console.log('🏢 Carregando lotes da prefeitura...');
            
            // Descobrir quadrículas dos marcadores encontrados para carregar lotes
            const quadriculas = new Set();
            
            // Extrair quadrículas dos marcadores
            if (coordenadasDesenhos && coordenadasDesenhos.length > 0) {
                coordenadasDesenhos.forEach(item => {
                    if (item.dados_completos_desenho && item.dados_completos_desenho.quadricula) {
                        quadriculas.add(item.dados_completos_desenho.quadricula);
                    }
                });
            }
            
            // Fallback: tentar dos polígonos se não encontrou nos marcadores
            if (quadriculas.size === 0 && poligonosLotesQuadras && poligonosLotesQuadras.length > 0) {
                poligonosLotesQuadras.forEach(item => {
                    if (item.quadricula) {
                        quadriculas.add(item.quadricula);
                    }
                });
            }
            
            if (quadriculas.size === 0) {
                console.warn('⚠️ Nenhuma quadrícula encontrada para carregar lotes da prefeitura');
                return;
            }
            
            console.log(`🗺️ Carregando lotes da prefeitura para quadrículas: ${Array.from(quadriculas).join(', ')}`);
            
            // Limpar lotes existentes
            if (window.lotesPrefeituraPolygons) {
                window.lotesPrefeituraPolygons.forEach(polygon => {
                    if (polygon.setMap) polygon.setMap(null);
                });
            }
            window.lotesPrefeituraPolygons = [];
            
            let totalLotesCarregados = 0;
            
            // Carregar lotes para cada quadrícula
            for (const quadricula of quadriculas) {
                await carregarLotesPrefeituraQuadricula(quadricula);
            }
            
            console.log(`=== RESUMO LOTES PREFEITURA ===`);
            console.log(`✅ Total de lotes carregados (≤30m): ${window.lotesPrefeituraPolygons.length}`);
            console.log(`🎯 Filtro de proximidade aplicado: 30 metros`);
            console.log(`🗺️ Quadrículas processadas: ${Array.from(quadriculas).join(', ')}`);
            console.log(`===============================`);
        }

        /**
         * Carregar lotes da prefeitura de uma quadrícula específica
         */
        async function carregarLotesPrefeituraQuadricula(quadricula) {
            try {
                const url = `../loteamentos_quadriculas/geojson/lotes_prefeitura_quadricula_${quadricula}.geojson`;
                console.log(`📁 Carregando lotes da prefeitura para quadrícula ${quadricula}:`, url);
                
                const response = await fetch(url, {
                    cache: 'no-store'
                });

                if (!response.ok) {
                    if (response.status === 404) {
                        console.log(`ℹ️ Arquivo de lotes da prefeitura não encontrado para quadrícula ${quadricula}`);
                        return;
                    }
                    throw new Error(`Erro HTTP ${response.status}: ${response.statusText}`);
                }

                const geojsonData = await response.json();
                console.log(`📊 GeoJSON carregado para ${quadricula}:`, geojsonData.features?.length || 0, 'features total');

                if (geojsonData && geojsonData.features) {
                    let lotesCarregados = 0;
                    let lotesRejeitados = 0;

                    geojsonData.features.forEach((feature, index) => {
                        if (feature.geometry && feature.geometry.type === 'Polygon' && feature.geometry.coordinates) {
                            try {
                                // FILTRO DE PROXIMIDADE: Verificar se o lote está dentro do raio de 10m dos marcadores
                                const coordenadas = feature.geometry.coordinates[0];
                                
                                // Calcular centróide do lote da prefeitura
                                let somaDeLat = 0;
                                let somaDeLng = 0;
                                let pontos = 0;
                                
                                coordenadas.forEach(coord => {
                                    if (Array.isArray(coord) && coord.length >= 2) {
                                        somaDeLng += coord[0]; // longitude
                                        somaDeLat += coord[1]; // latitude
                                        pontos++;
                                    }
                                });
                                
                                if (pontos === 0) {
                                    console.warn(`⚠️ Lote da prefeitura sem coordenadas válidas na quadrícula ${quadricula}`);
                                    return;
                                }
                                
                                const centroLote = {
                                    lat: somaDeLat / pontos,
                                    lng: somaDeLng / pontos
                                };
                                
                                // Verificar proximidade com marcadores (raio de 30m)
                                let dentroDoRaio = false;
                                let distanciaMinima = Infinity;
                                
                                if (coordenadasMarcadoresGlobal && coordenadasMarcadoresGlobal.length > 0) {
                                    for (const marcador of coordenadasMarcadoresGlobal) {
                                        if (marcador.coordenadas && Array.isArray(marcador.coordenadas) && marcador.coordenadas.length > 0) {
                                            const coordMarcador = marcador.coordenadas[0];
                                            if (coordMarcador && typeof coordMarcador.lat === 'number' && typeof coordMarcador.lng === 'number') {
                                                const distancia = calcularDistanciaMetros(
                                                    centroLote.lat,
                                                    centroLote.lng,
                                                    coordMarcador.lat,
                                                    coordMarcador.lng
                                                );
                                                
                                                distanciaMinima = Math.min(distanciaMinima, distancia);
                                                
                                                if (distancia <= 30) { // Raio de 30m
                                                    dentroDoRaio = true;
                                                    console.log(`✅ Lote da prefeitura aprovado por proximidade: ${distancia.toFixed(0)}m do marcador ${marcador.quarteirao}/${marcador.quadra}/${marcador.lote}`);
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                                
                                if (!dentroDoRaio) {
                                    lotesRejeitados++;
                                    console.log(`❌ Lote da prefeitura rejeitado por distância: ${distanciaMinima.toFixed(0)}m (>30m)`);
                                    return; // Pular este lote - está fora do raio
                                }
                                // Converter coordenadas do GeoJSON para formato Google Maps
                                const coordinates = feature.geometry.coordinates[0].map(coord => ({
                                    lat: coord[1],  // latitude é o segundo elemento
                                    lng: coord[0]   // longitude é o primeiro elemento
                                }));

                                // Criar polígono da prefeitura
                                const polygon = new google.maps.Polygon({
                                    paths: coordinates,
                                    strokeColor: '#FF6B35',    // Cor laranja
                                    strokeOpacity: 0.8,
                                    strokeWeight: 3,
                                    fillColor: '#FF6B35',
                                    fillOpacity: 0.3,
                                    map: map,                   // Visível por padrão
                                    clickable: true,
                                    zIndex: 15,                 // Z-index alto para ficar por cima
                                    visible: true               // Forçar visibilidade
                                });
                                
                                console.log(`✅ Lote da prefeitura criado:`, {
                                    quadricula: quadricula,
                                    coordenadas: coordinates.length,
                                    visible: polygon.getVisible(),
                                    map: polygon.getMap() !== null,
                                    properties: feature.properties
                                });

                                // Adicionar dados extras ao polígono
                                polygon.quadricula = quadricula;
                                polygon.feature = feature;
                                polygon.camada = 'lotesPrefeitura';

                                // Adicionar evento de clique
                                polygon.addListener('click', (event) => {
                                    mostrarDetalhesLotePrefeitura(feature, event.latLng, quadricula);
                                });

                                window.lotesPrefeituraPolygons.push(polygon);
                                lotesCarregados++;

                            } catch (error) {
                                console.error('Erro ao processar feature do lote da prefeitura:', error);
                            }
                        }
                    });

                    console.log(`📈 Quadrícula ${quadricula}:`);
                    console.log(`  ✅ Lotes aprovados (≤30m): ${lotesCarregados}`);
                    console.log(`  ❌ Lotes rejeitados (>30m): ${lotesRejeitados}`);
                    console.log(`  📊 Taxa de proximidade: ${(geojsonData.features.length > 0 ? ((lotesCarregados / geojsonData.features.length) * 100).toFixed(1) : 0)}%`);
                }

            } catch (error) {
                console.error(`❌ Erro ao carregar lotes da prefeitura para quadrícula ${quadricula}:`, error);
            }
        }

        /**
         * Fechar menu ao clicar fora
         */
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('dropdownCamadas');
            const menuCamadas = document.querySelector('.dropdown-menu-topbar');
            
            if (dropdown && menuCamadas && !menuCamadas.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });


        /**
         * Função para atualizar a opacidade dos polígonos
         */
        function atualizarOpacidade(novaOpacidade) {
            const opacidade = parseFloat(novaOpacidade);
            
            if (isNaN(opacidade) || opacidade < 0.1 || opacidade > 1) {
                console.warn('⚠️ Opacidade inválida:', opacidade);
                return;
            }
            
            console.log(`🎨 Atualizando opacidade para: ${(opacidade * 100).toFixed(0)}%`);
            
            // Atualizar display da porcentagem
            const opacidadeValue = document.getElementById('opacidadeValue');
            if (opacidadeValue) {
                opacidadeValue.textContent = `${(opacidade * 100).toFixed(0)}%`;
            }
            
            let poligonosAtualizados = 0;
            
            // Atualizar opacidade dos polígonos da quadrícula
            if (window.poligonosQuadras) {
                window.poligonosQuadras.forEach(polygon => {
                    if (polygon.setOptions) {
                        polygon.setOptions({
                            fillOpacity: opacidade,
                            strokeOpacity: Math.min(opacidade + 0.2, 1) // Stroke um pouco mais opaco
                        });
                        poligonosAtualizados++;
                    }
                });
            }
            
            // Atualizar outros polígonos se existirem
            const outrosPolygons = [
                window.quadrasPolygons,
                window.lotesPolygons,
                window.quarteiraoPolygons,
                window.lotesPrefeituraPolygons
            ];
            
            outrosPolygons.forEach(polygonArray => {
                if (Array.isArray(polygonArray)) {
                    polygonArray.forEach(polygon => {
                        if (polygon && polygon.setOptions) {
                            polygon.setOptions({
                                fillOpacity: opacidade,
                                strokeOpacity: Math.min(opacidade + 0.2, 1)
                            });
                            poligonosAtualizados++;
                        }
                    });
                }
            });
            
            // Salvar no localStorage
            localStorage.setItem('opacidadePoligonos', opacidade);
            
            console.log(`✅ Opacidade atualizada em ${poligonosAtualizados} polígonos`);
        }

        /**
         * Carregar opacidade salva do localStorage
         */
        function carregarOpacidadeSalva() {
            const opacidadeSalva = localStorage.getItem('opacidadePoligonos');
            if (opacidadeSalva) {
                const opacidade = parseFloat(opacidadeSalva);
                if (opacidade >= 0.1 && opacidade <= 1) {
                    const opacidadeInput = document.getElementById('opacidadeInput');
                    const opacidadeValue = document.getElementById('opacidadeValue');
                    
                    if (opacidadeInput) opacidadeInput.value = opacidade;
                    if (opacidadeValue) opacidadeValue.textContent = `${(opacidade * 100).toFixed(0)}%`;
                    
                    console.log(`📁 Opacidade carregada: ${(opacidade * 100).toFixed(0)}%`);
                    return opacidade;
                }
            }
            return 0.6; // Valor padrão
        }

        /**
         * Calcular área de um polígono usando coordenadas geográficas
         */
        function calcularAreaPoligono(coordenadas) {
            if (!coordenadas || !Array.isArray(coordenadas) || coordenadas.length < 3) {
                return 0;
            }
            
            // Fórmula de Shoelace para calcular área de polígono
            let area = 0;
            for (let i = 0; i < coordenadas.length; i++) {
                const j = (i + 1) % coordenadas.length;
                const lat1 = coordenadas[i].lat || 0;
                const lng1 = coordenadas[i].lng || 0;
                const lat2 = coordenadas[j].lat || 0;
                const lng2 = coordenadas[j].lng || 0;
                
                area += (lng1 * lat2) - (lng2 * lat1);
            }
            
            return Math.abs(area) / 2;
        }

        /**
         * Filtrar quadras duplicadas mantendo apenas a de maior extensão
         */
        function filtrarQuadrasDuplicadas(poligonos) {
            if (!poligonos || !Array.isArray(poligonos)) {
                return poligonos;
            }
            
            console.log('🔍 Iniciando filtro de quadras duplicadas...');
            
            // Agrupar quadras por identificador único (quarteirao + quadra)
            const quadrasAgrupadas = new Map();
            
            poligonos.forEach((item, index) => {
                if (item.camada === 'quadra' && item.quarteirao && item.quadra) {
                    const chaveQuadra = `${item.quarteirao}/${item.quadra}`;
                    
                    if (!quadrasAgrupadas.has(chaveQuadra)) {
                        quadrasAgrupadas.set(chaveQuadra, []);
                    }
                    
                    // Adicionar informações extras para análise
                    const itemComInfo = {
                        ...item,
                        indiceOriginal: index,
                        chaveQuadra: chaveQuadra
                    };
                    
                    quadrasAgrupadas.get(chaveQuadra).push(itemComInfo);
                }
            });
            
            // Processar duplicatas
            const indicesParaRemover = new Set();
            let quadrasDuplicadas = 0;
            let quadrasRemovidas = 0;
            
            quadrasAgrupadas.forEach((quadrasGrupo, chaveQuadra) => {
                if (quadrasGrupo.length > 1) {
                    quadrasDuplicadas++;
                    console.log(`🔄 Quadra duplicada encontrada: ${chaveQuadra} (${quadrasGrupo.length} instâncias)`);
                    
                    // Calcular área de cada instância
                    quadrasGrupo.forEach(quadra => {
                        try {
                            let coordenadas = quadra.coordenadas;
                            
                            // Se coordenadas são string JSON, decodificar
                            if (typeof coordenadas === 'string') {
                                coordenadas = JSON.parse(coordenadas);
                            }
                            
                            quadra.area = calcularAreaPoligono(coordenadas);
                            quadra.quadricula = quadra.quadricula || 'desconhecida';
                            
                            console.log(`  - Instância em quadrícula ${quadra.quadricula}: área = ${quadra.area.toFixed(6)}`);
                        } catch (error) {
                            console.warn(`⚠️ Erro ao calcular área da quadra ${chaveQuadra}:`, error);
                            quadra.area = 0;
                        }
                    });
                    
                    // Encontrar a instância com maior área
                    const quadraMaiorArea = quadrasGrupo.reduce((maior, atual) => 
                        atual.area > maior.area ? atual : maior
                    );
                    
                    console.log(`✅ Mantendo quadra ${chaveQuadra} da quadrícula ${quadraMaiorArea.quadricula} (área: ${quadraMaiorArea.area.toFixed(6)})`);
                    
                    // Marcar outras instâncias para remoção
                    quadrasGrupo.forEach(quadra => {
                        if (quadra.indiceOriginal !== quadraMaiorArea.indiceOriginal) {
                            indicesParaRemover.add(quadra.indiceOriginal);
                            quadrasRemovidas++;
                            console.log(`❌ Removendo quadra ${chaveQuadra} da quadrícula ${quadra.quadricula} (área menor: ${quadra.area.toFixed(6)})`);
                        }
                    });
                }
            });
            
            // Criar array filtrado
            const poligonosFiltrados = poligonos.filter((item, index) => 
                !indicesParaRemover.has(index)
            );
            
            console.log(`📊 Filtro de quadras duplicadas concluído:`);
            console.log(`  - Quadras duplicadas encontradas: ${quadrasDuplicadas}`);
            console.log(`  - Instâncias removidas: ${quadrasRemovidas}`);
            console.log(`  - Polígonos antes: ${poligonos.length}`);
            console.log(`  - Polígonos depois: ${poligonosFiltrados.length}`);
            
            return poligonosFiltrados;
        }

        /**
         * Atualizar estatísticas dos polígonos e sincronizar checkboxes
         */
        function atualizarEstatisticasPoligonos() {
            if (!window.poligonosQuadras) {
                console.warn('⚠️ Nenhum polígono disponível para estatísticas');
                return;
            }
            
            // Contar polígonos por tipo
            const stats = {
                quadras: 0,
                lotes: 0,
                total: 0
            };
            
            window.poligonosQuadras.forEach(polygon => {
                if (polygon.camada === 'quadra') {
                    stats.quadras++;
                } else if (polygon.camada === 'lote') {
                    stats.lotes++;
                }
                stats.total++;
            });
            
            // Sincronizar checkboxes do menu dropdown com disponibilidade
            const toggleQuadrasCheckbox = document.getElementById('toggleQuadras');
            const toggleLotesCheckbox = document.getElementById('toggleLotes');
            const toggleMarcadoresCheckbox = document.getElementById('toggleMarcadores');
            
            if (toggleQuadrasCheckbox) {
                // Manter checkbox marcado se há quadras disponíveis
                if (stats.quadras === 0) {
                    toggleQuadrasCheckbox.checked = false;
                }
            }
            
            if (toggleLotesCheckbox) {
                // Manter checkbox marcado se há lotes disponíveis
                if (stats.lotes === 0) {
                    toggleLotesCheckbox.checked = false;
                }
            }
            
            if (toggleMarcadoresCheckbox) {
                // Manter marcadores sempre disponíveis se existirem
                const temMarcadores = markers && markers.length > 0;
                if (!temMarcadores) {
                    toggleMarcadoresCheckbox.checked = false;
                }
            }
            
            // Sincronizar checkbox dos lotes da prefeitura
            const toggleLotesPrefeituraCheckbox = document.getElementById('toggleLotesPrefeitura');
            if (toggleLotesPrefeituraCheckbox) {
                const temLotesPrefeitura = window.lotesPrefeituraPolygons && window.lotesPrefeituraPolygons.length > 0;
                if (!temLotesPrefeitura) {
                    toggleLotesPrefeituraCheckbox.checked = false;
                }
            }
            
            // Info na topbar removida - elemento não existe mais
            
            console.log(`📊 Estatísticas atualizadas: ${stats.quadras} quadras, ${stats.lotes} lotes`);
        }

        /**
         * Versão da função que permite especificar o raio
         */
        async function criarPoligonosQuadras() {
            console.log(`Criando polígonos da quadrícula (COM filtro de proximidade 10m)...`);
            
            // Limpar polígonos existentes das quadras
            if (window.poligonosQuadras) {
                window.poligonosQuadras.forEach(polygon => {
                    if (polygon.setMap) polygon.setMap(null);
                });
                window.poligonosQuadras = [];
            }
            
            let poligonosCreated = 0;
            let poligonosRejeitados = 0;
            let totalProcessados = 0;
            let quadrasCreated = 0;
            let lotesCreated = 0;
            let poligonosDuplicados = 0; // Contador de duplicados evitados

            console.log(`Iniciando processamento de ${poligonosLotesQuadras.length} polígonos...`);

            // 🔄 APLICAR FILTRO DE QUADRAS DUPLICADAS
            const poligonosOriginais = [...poligonosLotesQuadras];
            const poligonosFiltrados = filtrarQuadrasDuplicadas(poligonosOriginais);
            
            console.log(`📊 Filtro aplicado: ${poligonosOriginais.length} → ${poligonosFiltrados.length} polígonos`);

            // Criar Set para controlar IDs únicos e evitar duplicação
            const idsProcessados = new Set();

            for (const item of poligonosFiltrados) {
                totalProcessados++;
                try {
                    // Verificar se já processamos este ID para evitar duplicação
                    const idDesenho = item.id_desenho;
                    if (idsProcessados.has(idDesenho)) {
                        poligonosDuplicados++;
                        console.log(`🔄 Polígono ID ${idDesenho} ignorado (duplicação de quadrícula)`);
                        continue;
                    }
                    
                    // Marcar como processado
                    idsProcessados.add(idDesenho);

                    const camada = item.camada.toLowerCase();
                    const coordenadas = item.coordenadas;
                    
                    // APLICAR FILTRO COM RAIO PERSONALIZADO
                    const pertenceAQuadraComMarcadores = poligonoPerteniceAQuadraComMarcadoresComRaio(item, coordenadasMarcadoresGlobal, 32);
                    
                    if (!pertenceAQuadraComMarcadores) {
                        poligonosRejeitados++;
                        continue;
                    }
                    
                    // Criar o polígono - CÓDIGO COMPLETO DE CRIAÇÃO
                    console.log(`Processando ${camada}:`, item);

                    // Processar coordenadas do polígono
                    let paths = [];
                    if (Array.isArray(coordenadas)) {
                        paths = coordenadas.map(coord => ({
                            lat: parseFloat(coord.lat),
                            lng: parseFloat(coord.lng)
                        }));
                    }

                    if (paths.length > 2) {
                        // Definir cores e estilos por camada
                        let cor, strokeWeight, fillOpacity;
                        if (camada === 'quadra') {
                            cor = '#0078D7'; // Azul para quadras
                            strokeWeight = 2;
                            fillOpacity = 0.25;
                        } else if (camada === 'lote') {
                            cor = '#FF6B6B'; // Vermelho para lotes
                            strokeWeight = 1.5;
                            fillOpacity = 0.2;
                        } else {
                            cor = '#9E9E9E'; // Cinza para outros
                            strokeWeight = 1;
                            fillOpacity = 0.15;
                        }

                        // Criar polígono
                        const polygon = new google.maps.Polygon({
                            paths: paths,
                            strokeColor: cor,
                            strokeOpacity: 0.8,
                            strokeWeight: strokeWeight,
                            fillColor: cor,
                            fillOpacity: fillOpacity,
                            clickable: true,
                            map: map
                        });

                        // Armazenar referências por tipo
                        if (!window.poligonosQuadras) window.poligonosQuadras = [];
                        window.poligonosQuadras.push(polygon);
                        
                        // Adicionar dados extras ao polígono
                        polygon.quarteirao = item.quarteirao;
                        polygon.quadra = item.quadra;
                        polygon.lote = item.lote;
                        polygon.id_desenho = item.id_desenho;
                        polygon.camada = camada;
                        
                        // Adicionar ao sistema de camadas
                        adicionarObjetoNaCamada(camada, polygon);
                        
                        poligonosCreated++;
                        
                        console.log(`✅ Polígono ${camada} criado: ${item.quarteirao || 'N/A'}/${item.quadra || 'N/A'}/${item.lote || 'N/A'}`);
                    }
                    
                } catch (error) {
                    console.error('Erro ao criar polígono:', error, item);
                }
            }

            console.log(`=== RESUMO FILTRO PROXIMIDADE 10m ===`);
            console.log(`Total originais: ${poligonosOriginais.length}`);
            console.log(`Após filtro quadras duplicadas: ${poligonosFiltrados.length}`);
            console.log(`Total processados: ${totalProcessados}`);
            console.log(`Duplicados ignorados: ${poligonosDuplicados}`);
            console.log(`Únicos processados: ${totalProcessados - poligonosDuplicados}`);
            console.log(`Polígonos aprovados (≤10m): ${poligonosCreated}`);
            console.log(`  - Quadras: ${quadrasCreated}`);
            console.log(`  - Lotes: ${lotesCreated}`);
            console.log(`Polígonos rejeitados (>10m): ${poligonosRejeitados}`);
            console.log(`Taxa de proximidade: ${totalProcessados > 0 ? ((poligonosCreated / (totalProcessados - poligonosDuplicados)) * 100).toFixed(1) : 0}%`);
            console.log(`====================================`);
            
            // Atualizar estatísticas dos botões
            atualizarEstatisticasPoligonos();
        }

        /**
         * Versão da função de filtro que aceita raio personalizado
         */
        function poligonoPerteniceAQuadraComMarcadoresComRaio(poligono, marcadores, raioMaximo) {
            try {
                // Se não há marcadores, não mostrar nenhum polígono
                if (!marcadores || marcadores.length === 0) {
                    return false;
                }

                // Se o polígono tem informação de quarteirão/quadra, usar filtro tradicional
                if (poligono.quarteirao && poligono.quadra) {
                    const quadrasMarcadores = extrairQuadrasComMarcadores(marcadores);
                    const chavePoligono = `${poligono.quarteirao}/${poligono.quadra}`;
                    const pertence = quadrasMarcadores.has(chavePoligono);
                    
                    if (pertence) {
                        console.log(`✅ Polígono aprovado por correspondência exata: ${chavePoligono}`);
                    }
                    
                    return pertence;
                }

                // Se não tem informação de quarteirão/quadra, usar filtro por proximidade
                console.log(`🌍 Aplicando filtro por proximidade (${raioMaximo}m) para polígono ID ${poligono.id_desenho}`);
                
                // Calcular centróide do polígono
                const coordenadas = poligono.coordenadas;
                if (!Array.isArray(coordenadas) || coordenadas.length === 0) {
                    return false;
                }

                let totalLat = 0;
                let totalLng = 0;
                let totalPontos = 0;

                coordenadas.forEach(coord => {
                    if (coord && typeof coord.lat === 'number' && typeof coord.lng === 'number') {
                        totalLat += coord.lat;
                        totalLng += coord.lng;
                        totalPontos++;
                    }
                });

                if (totalPontos === 0) {
                    return false;
                }

                const centroPoligono = {
                    lat: totalLat / totalPontos,
                    lng: totalLng / totalPontos
                };

                // Verificar distância para cada marcador
                for (const marcador of marcadores) {
                    if (marcador.coordenadas && Array.isArray(marcador.coordenadas) && marcador.coordenadas.length > 0) {
                        const coordMarcador = marcador.coordenadas[0];
                        if (coordMarcador && typeof coordMarcador.lat === 'number' && typeof coordMarcador.lng === 'number') {
                            const distancia = calcularDistanciaMetros(
                                centroPoligono.lat,
                                centroPoligono.lng,
                                coordMarcador.lat,
                                coordMarcador.lng
                            );

                            if (distancia <= raioMaximo) {
                                console.log(`✅ Polígono ID ${poligono.id_desenho} aprovado por proximidade: ${distancia.toFixed(0)}m do marcador ${marcador.quarteirao}/${marcador.quadra}/${marcador.lote}`);
                                return true;
                            }
                        }
                    }
                }

                // Calcular distância mínima para o log
                let distanciaMinima = Infinity;
                for (const marcador of marcadores) {
                    if (marcador.coordenadas && Array.isArray(marcador.coordenadas) && marcador.coordenadas.length > 0) {
                        const coordMarcador = marcador.coordenadas[0];
                        if (coordMarcador && typeof coordMarcador.lat === 'number' && typeof coordMarcador.lng === 'number') {
                            const distancia = calcularDistanciaMetros(
                                centroPoligono.lat,
                                centroPoligono.lng,
                                coordMarcador.lat,
                                coordMarcador.lng
                            );
                            distanciaMinima = Math.min(distanciaMinima, distancia);
                        }
                    }
                }

                console.log(`❌ Polígono ID ${poligono.id_desenho} rejeitado por distância: ${distanciaMinima.toFixed(0)}m (>${raioMaximo}m)`);
                return false;

            } catch (error) {
                console.error('Erro ao verificar proximidade do polígono:', error);
                return false;
            }
        }

        async function criarMarcador(item) {
            const coordenadas = item.coordenadas;
            
            let lat, lng;

            // As coordenadas vêm como array de objetos: [{"lat": -22.xx, "lng": -47.xx}]
            if (Array.isArray(coordenadas) && coordenadas.length > 0) {
                const coord = coordenadas[0]; // Pegar primeiro elemento do array
                if (coord && coord.lat && coord.lng) {
                    lat = parseFloat(coord.lat);
                    lng = parseFloat(coord.lng);
                }
            } else if (typeof coordenadas === 'object' && coordenadas.lat && coordenadas.lng) {
                lat = parseFloat(coordenadas.lat);
                lng = parseFloat(coordenadas.lng);
            } else if (typeof coordenadas === 'string') {
                try {
                    const parsed = JSON.parse(coordenadas);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        lat = parseFloat(parsed[0].lat);
                        lng = parseFloat(parsed[0].lng);
                    }
                } catch (e) {
                    // Tentar parsing simples "lat,lng"
                    const parts = coordenadas.split(',');
                    if (parts.length >= 2) {
                        lat = parseFloat(parts[0]);
                        lng = parseFloat(parts[1]);
                    }
                }
            }


            if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
                try {
                    
                    // Usar marcador simples primeiro para testar
                    const marker = new google.maps.Marker({
                        map: map,
                        position: { lat: lat, lng: lng },
                        title: `${item.quarteirao}/${item.quadra}/${item.lote}`,
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 8,
                            fillColor: item.cor || '#32CD32',
                            fillOpacity: 1,
                            strokeColor: '#000000',
                            strokeWeight: 2
                        }
                    });

                    // Adicionar evento de clique
                    google.maps.event.addListener(marker, "click", () => {
                        mostrarDetalhesDesenho(item, marker);
                    });

                    markers.push(marker);
                } catch (error) {
                    console.error('❌ Erro ao criar marcador:', error);
                }
            } else {
                console.error('❌ Coordenadas inválidas:', { lat, lng, coordenadas });
            }
            
        }

        async function criarPoligono(item) {
            const coordenadas = item.coordenadas;
            let paths = [];

            try {
                // Processar coordenadas do polígono
                if (Array.isArray(coordenadas)) {
                    paths = coordenadas.map(coord => {
                        if (typeof coord === 'object' && coord.lat && coord.lng) {
                            return { lat: parseFloat(coord.lat), lng: parseFloat(coord.lng) };
                        } else if (Array.isArray(coord) && coord.length >= 2) {
                            return { lat: parseFloat(coord[0]), lng: parseFloat(coord[1]) };
                        }
                        return null;
                    }).filter(coord => coord !== null);
                }

                if (paths.length > 0) {
                    // Usar cor do banco ou cor padrão
                    const cor = item.cor || '#FF0000';
                    
                    const polygon = new google.maps.Polygon({
                        paths: paths,
                        strokeColor: cor,
                        strokeOpacity: 1,
                        strokeWeight: 4,
                        fillColor: cor,
                        fillOpacity: 0.2,
                        map: map,
                        zIndex: 1
                    });

                    // Armazenar cor original para seleção
                    polygon.corOriginal = cor;

                    // Adicionar evento de clique
                    google.maps.event.addListener(polygon, "click", (event) => {
                        mostrarDetalhesDesenho(item, null);
                        // Posicionar InfoWindow no local do clique
                        if (event.latLng) {
                            infoWindow.setPosition(event.latLng);
                        }
                    });

                    polygons.push(polygon);
                    console.log(`Polígono criado com ${paths.length} pontos`);
                }
            } catch (error) {
                console.error('Erro ao criar polígono:', error, item);
            }
        }

        async function criarPolilinha(item) {
            const coordenadas = item.coordenadas;
            let path = [];

            try {
                // Processar coordenadas da polilinha
                if (Array.isArray(coordenadas)) {
                    path = coordenadas.map(coord => {
                        if (typeof coord === 'object' && coord.lat && coord.lng) {
                            return { lat: parseFloat(coord.lat), lng: parseFloat(coord.lng) };
                        } else if (Array.isArray(coord) && coord.length >= 2) {
                            return { lat: parseFloat(coord[0]), lng: parseFloat(coord[1]) };
                        }
                        return null;
                    }).filter(coord => coord !== null);
                }

                if (path.length > 0) {
                    // Usar cor do banco ou cor padrão
                    const cor = item.cor || '#0000FF';
                    
                    const polyline = new google.maps.Polyline({
                        path: path,
                        geodesic: true,
                        strokeColor: cor,
                        strokeOpacity: 1.0,
                        strokeWeight: 3,
                        map: map,
                        zIndex: 2
                    });

                    // Armazenar cor original para seleção
                    polyline.corOriginal = cor;

                    // Adicionar evento de clique
                    google.maps.event.addListener(polyline, "click", (event) => {
                        mostrarDetalhesDesenho(item, null);
                        // Posicionar InfoWindow no local do clique
                        if (event.latLng) {
                            infoWindow.setPosition(event.latLng);
                        }
                    });

                    polylines.push(polyline);
                    console.log(`Polilinha criada com ${path.length} pontos`);
                }
            } catch (error) {
                console.error('Erro ao criar polilinha:', error, item);
            }
        }

        function mostrarDetalhesDesenho(item, marker) {
            console.log('Abrindo InfoWindow para item:', item);
            
            // Criar conteúdo do InfoWindow com todas as colunas da tabela
            let conteudo = `
                <div style="max-width: 350px; max-height: 400px; overflow-y: auto; font-family: Arial, sans-serif; font-size: 12px;">
                    <div style="background: #f8f9fa; padding: 8px; margin: -8px -8px 8px -8px; border-radius: 3px;">
                        <strong>${item.quarteirao}/${item.quadra}/${item.lote}</strong>
                    </div>
                    
                    <ul style="margin: 0; padding: 0; list-style: none; line-height: 1.3;">
            `;

            // Mostrar TODAS as colunas do registro original
            if (item.registro_origem) {
                const registro = item.registro_origem;
                
                // Iterar por todas as propriedades do registro
                Object.keys(registro).forEach(campo => {
                    const valor = registro[campo];
                    // Mostrar todos os campos, mesmo os nulos ou vazios
                    const valorExibido = (valor === null || valor === undefined) ? 'null' : valor.toString();
                    
                    conteudo += `
                        <li style="border-bottom: 1px solid #eee; padding: 3px 0;">
                            <strong>${campo.toUpperCase()}:</strong> ${valorExibido}
                        </li>
                    `;
                });
            }

            conteudo += `
                    </ul>
                    
                    <div style="text-align: center; padding-top: 8px; border-top: 2px solid #ddd; margin-top: 8px;">
                        <small style="color: #666;">Clique no mapa para fechar</small>
                    </div>
                </div>
            `;

            // Definir conteúdo e posição do InfoWindow
            infoWindow.setContent(conteudo);
            
            // Se for um marcador, usar sua posição, senão usar uma posição padrão
            if (marker && marker.getPosition) {
                infoWindow.setPosition(marker.getPosition());
            } else {
                // Para polígonos e polilinhas, usar primeira coordenada
                const coords = item.coordenadas;
                if (Array.isArray(coords) && coords.length > 0) {
                    const firstCoord = coords[0];
                    infoWindow.setPosition({ lat: firstCoord.lat, lng: firstCoord.lng });
                }
            }
            
            // Abrir InfoWindow
            infoWindow.open(map);
            console.log('InfoWindow aberto com sucesso');
        }

        function mostrarDetalhesPoligono(item, position) {
            console.log('Abrindo InfoWindow para polígono:', item);
            
            // Criar conteúdo do InfoWindow para polígono
            let conteudo = `
                <div style="max-width: 350px; max-height: 400px; overflow-y: auto; font-family: Arial, sans-serif; font-size: 12px;">
                    <div style="background: #f8f9fa; padding: 8px; margin: -8px -8px 8px -8px; border-radius: 3px;">
                        <strong>${item.camada.toUpperCase()}: ${item.quarteirao}/${item.quadra}/${item.lote}</strong>
                    </div>
                    
                    <ul style="margin: 0; padding: 0; list-style: none; line-height: 1.3;">
                        <li style="border-bottom: 1px solid #eee; padding: 3px 0;">
                            <strong>ID DESENHO:</strong> ${item.id_desenho}
                        </li>
                        <li style="border-bottom: 1px solid #eee; padding: 3px 0;">
                            <strong>QUARTEIRÃO:</strong> ${item.quarteirao || 'N/A'}
                        </li>
                        <li style="border-bottom: 1px solid #eee; padding: 3px 0;">
                            <strong>QUADRA:</strong> ${item.quadra || 'N/A'}
                        </li>
                        <li style="border-bottom: 1px solid #eee; padding: 3px 0;">
                            <strong>LOTE:</strong> ${item.lote || 'N/A'}
                        </li>
                        <li style="border-bottom: 1px solid #eee; padding: 3px 0;">
                            <strong>TIPO:</strong> ${item.tipo}
                        </li>
                        <li style="border-bottom: 1px solid #eee; padding: 3px 0;">
                            <strong>CAMADA:</strong> ${item.camada}
                        </li>
                        <li style="border-bottom: 1px solid #eee; padding: 3px 0;">
                            <strong>COR:</strong> <span style="background: ${item.cor}; padding: 2px 8px; border-radius: 3px; color: white;">${item.cor || 'N/A'}</span>
                        </li>
                        <li style="border-bottom: 1px solid #eee; padding: 3px 0;">
                            <strong>QUADRÍCULA:</strong> ${item.quadricula || 'N/A'}
                        </li>
                        <li style="border-bottom: 1px solid #eee; padding: 3px 0;">
                            <strong>STATUS:</strong> ${item.status || 'N/A'}
                        </li>
                    </ul>
                    
                    <div style="text-align: center; padding-top: 8px; border-top: 2px solid #ddd; margin-top: 8px;">
                        <small style="color: #666;">Clique no mapa para fechar</small>
                    </div>
                </div>
            `;

            // Definir conteúdo e posição do InfoWindow
            infoWindow.setContent(conteudo);
            infoWindow.setPosition(position);
            
            // Abrir InfoWindow
            infoWindow.open(map);
            console.log('InfoWindow de polígono aberto com sucesso');
        }

        function mostrarDetalhesLotePrefeitura(feature, position, quadricula) {
            console.log('Abrindo InfoWindow para lote da prefeitura:', feature);
            
            // Criar conteúdo do InfoWindow para lote da prefeitura
            let conteudo = `
                <div style="max-width: 350px; max-height: 400px; overflow-y: auto; font-family: Arial, sans-serif; font-size: 12px;">
                    <div style="background: #ff6b35; padding: 8px; margin: -8px -8px 8px -8px; border-radius: 3px; color: white;">
                        <strong>🏢 LOTE DA PREFEITURA - ${quadricula}</strong>
                    </div>
                    
                    <ul style="margin: 0; padding: 0; list-style: none; line-height: 1.3;">
            `;

            // Processar propriedades do GeoJSON
            if (feature.properties && Object.keys(feature.properties).length > 0) {
                // Definir ordem desejada para exibir propriedades importantes primeiro
                const ordemPropriedades = ['name', 'ENDERECO', 'INSCRICAO'];
                const propriedadesExibidas = new Set();
                
                // Primeiro, exibir propriedades na ordem específica
                ordemPropriedades.forEach(key => {
                    if (feature.properties[key] !== null && 
                        feature.properties[key] !== '' && 
                        feature.properties[key] !== undefined) {
                        
                        let labelFormatada;
                        switch(key) {
                            case 'name':
                                labelFormatada = 'Nome/Inscrição';
                                break;
                            case 'ENDERECO':
                                labelFormatada = 'Endereço';
                                break;
                            case 'INSCRICAO':
                                labelFormatada = 'Inscrição';
                                break;
                            default:
                                labelFormatada = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                        }
                        
                        conteudo += `
                            <li style="border-bottom: 1px solid #eee; padding: 3px 0;">
                                <strong>${labelFormatada}:</strong> ${feature.properties[key]}
                            </li>
                        `;
                        propriedadesExibidas.add(key);
                    }
                });
                
                // Depois, exibir outras propriedades não mostradas ainda
                Object.keys(feature.properties).forEach(key => {
                    const value = feature.properties[key];
                    if (value !== null && value !== '' && value !== undefined && 
                        !propriedadesExibidas.has(key) && 
                        key !== 'fill_color') {
                        
                        const keyFormatted = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                        conteudo += `
                            <li style="border-bottom: 1px solid #eee; padding: 3px 0;">
                                <strong>${keyFormatted}:</strong> ${value}
                            </li>
                        `;
                    }
                });
            } else {
                conteudo += `
                    <li style="padding: 3px 0; color: #666; font-style: italic;">
                        Sem dados adicionais disponíveis
                    </li>
                `;
            }

            conteudo += `
                        <li style="border-bottom: 1px solid #eee; padding: 3px 0;">
                            <strong>QUADRÍCULA:</strong> ${quadricula}
                        </li>
                    </ul>
                    
                    <div style="text-align: center; padding-top: 8px; border-top: 2px solid #ff6b35; margin-top: 8px;">
                        <small style="color: #666;">Clique no mapa para fechar</small>
                    </div>
                </div>
            `;

            // Definir conteúdo e posição do InfoWindow
            infoWindow.setContent(conteudo);
            infoWindow.setPosition(position);
            
            // Abrir InfoWindow
            infoWindow.open(map);
            console.log('InfoWindow de lote da prefeitura aberto com sucesso');
        }

        function atualizarEstatisticasDesenhos(stats) {
            // Função simplificada - não adiciona mais estatísticas dinâmicas
            // Apenas mantém o total de registros e filtros aplicados no HTML estático
            console.log('📊 Estatísticas dos desenhos carregadas:', stats);
            
            // Remover estatísticas antigas de desenhos se existirem
            const statsContainer = document.querySelector('.stats-row');
            const existingStats = statsContainer.querySelectorAll('.stat-desenho');
            existingStats.forEach(stat => stat.remove());
            
            // Log para debug sem exibir na interface
            console.log(`Total de coordenadas encontradas: ${stats.coordenadas_encontradas || 0}`);
            console.log(`Total de polígonos encontrados: ${stats.poligonos_encontrados || 0}`);
            console.log('Tipos encontrados:', stats.tipos_encontrados || {});
            console.log('Camadas encontradas:', stats.camadas_encontradas || {});
        }

        async function criarMarcadores() {
            console.log('Criando marcadores no mapa...');
            
            let marcadoresCriados = 0;
            let registrosComCoordenadas = 0;
            let registrosSemCoordenadas = 0;
            let marcadoresUnicosSet = new Set(); // Para controlar duplicação por ID
            let marcadoresDuplicados = 0;

            for (let i = 0; i < dadosOriginais.length; i++) {
                const registro = dadosOriginais[i];
                
                // Buscar coordenadas nos possíveis campos
                let lat = null;
                let lng = null;
                
                // Tentar diferentes nomes de campos para latitude e longitude
                const camposLat = ['latitude', 'lat', 'y', 'coord_y', 'coordenada_y'];
                const camposLng = ['longitude', 'lng', 'lon', 'x', 'coord_x', 'coordenada_x'];
                
                for (const campo of camposLat) {
                    if (registro[campo] && !isNaN(parseFloat(registro[campo]))) {
                        lat = parseFloat(registro[campo]);
                        break;
                    }
                }
                
                for (const campo of camposLng) {
                    if (registro[campo] && !isNaN(parseFloat(registro[campo]))) {
                        lng = parseFloat(registro[campo]);
                        break;
                    }
                }

                if (lat && lng && lat !== 0 && lng !== 0) {
                    registrosComCoordenadas++;
                    
                    // Verificar duplicação por ID
                    const idMarcador = registro.id || registro.id_desenho || registro.id_registro || `${i}_${lat}_${lng}`;
                    
                    if (marcadoresUnicosSet.has(idMarcador)) {
                        marcadoresDuplicados++;
                        console.log(`🔄 Marcador ID ${idMarcador} ignorado (duplicação)`);
                        continue;
                    }
                    
                    // Marcar como processado
                    marcadoresUnicosSet.add(idMarcador);
                    
                    try {
                        // Criar marcador
                        const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");
                        
                        const marker = new AdvancedMarkerElement({
                            map: map,
                            position: { lat: lat, lng: lng },
                            title: `Registro ${i + 1}`
                        });

                        // Adicionar evento de clique
                        marker.addListener("click", () => {
                            mostrarDetalhesMarker(registro);
                        });

                        markers.push(marker);
                        marcadoresCriados++;
                        
                    } catch (error) {
                        console.error('Erro ao criar marcador:', error);
                    }
                } else {
                    registrosSemCoordenadas++;
                }
            }

            console.log(`Marcadores criados: ${marcadoresCriados}`);
            console.log(`Registros com coordenadas: ${registrosComCoordenadas}`);
            console.log(`Registros sem coordenadas: ${registrosSemCoordenadas}`);
            console.log(`Marcadores únicos criados: ${marcadoresCriados}`);
            console.log(`Marcadores duplicados ignorados: ${marcadoresDuplicados}`);
            
            // Estatísticas simplificadas - apenas total de registros e filtros
            // (removido: registrosComCoordenadas e registrosSemCoordenadas)
        }

        function mostrarDetalhesMarker(registro) {
            let conteudo = '<div class="row">';
            
            Object.keys(registro).forEach(function(campo) {
                const valor = registro[campo] || '-';
                conteudo += `
                    <div class="col-md-6 mb-3">
                        <strong>${campo.toUpperCase()}:</strong><br>
                        <span class="text-muted">${valor}</span>
                    </div>
                `;
            });
            
            conteudo += '</div>';
            
            document.getElementById('modalDetalhesMarkerContent').innerHTML = conteudo;
            
            const modal = new bootstrap.Modal(document.getElementById('modalDetalhesMarker'));
            modal.show();
        }

        function atualizarEstatisticas() {
            document.getElementById('totalRegistros').textContent = dadosOriginais.length;
            document.getElementById('filtrosAtivos').textContent = filtrosRecebidos.length;
            
            // Mostrar filtros aplicados se houver
            if (filtrosRecebidos.length > 0) {
                document.getElementById('filtrosAplicados').style.display = 'block';
                
                const listaFiltros = document.getElementById('listaFiltros');
                listaFiltros.innerHTML = '';
                
                filtrosRecebidos.forEach(filtro => {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-primary';
                    badge.innerHTML = `${filtro.campo}: ${filtro.valor1 || 'N/A'}`;
                    listaFiltros.appendChild(badge);
                });
            }
            
            // Atualizar hora
            const agora = new Date();
            document.getElementById('ultimaAtualizacao').textContent = 
                `Atualizado em: ${agora.toLocaleString('pt-BR')}`;
        }

        function centralizarMapa() {
            const bounds = new google.maps.LatLngBounds();
            let totalElementos = 0;

            console.log('Iniciando centralização...', {
                markers: markers.length,
                polygons: polygons.length, 
                polylines: polylines.length,
                lotesPolygons: lotesPolygons.length,
                quadrasPolygons: quadrasPolygons.length,
                quarteiraoPolygons: quarteiraoPolygons.length
            });

            // Adicionar marcadores ao bounds
            markers.forEach((marker, index) => {
                console.log(`Adicionando marcador ${index + 1}:`, marker.position);
                bounds.extend(marker.position);
                totalElementos++;
            });

            // Adicionar polígonos ao bounds
            polygons.forEach(polygon => {
                const path = polygon.getPath();
                path.forEach(point => {
                    bounds.extend(point);
                    totalElementos++;
                });
            });

            // Adicionar polilinhas ao bounds
            polylines.forEach(polyline => {
                const path = polyline.getPath();
                path.forEach(point => {
                    bounds.extend(point);
                    totalElementos++;
                });
            });

            // Adicionar polígonos de lotes ao bounds
            lotesPolygons.forEach(polygon => {
                const path = polygon.getPath();
                path.forEach(point => {
                    bounds.extend(point);
                    totalElementos++;
                });
            });

            // Adicionar polígonos de quadras ao bounds
            quadrasPolygons.forEach(polygon => {
                const path = polygon.getPath();
                path.forEach(point => {
                    bounds.extend(point);
                    totalElementos++;
                });
            });

            // Adicionar polígonos de quarteirões ao bounds
            quarteiraoPolygons.forEach(polygon => {
                const path = polygon.getPath();
                path.forEach(point => {
                    bounds.extend(point);
                    totalElementos++;
                });
            });

            // Adicionar polígonos de lotes da prefeitura ao bounds
            lotesPrefeituraPolygons.forEach(polygon => {
                const path = polygon.getPath();
                path.forEach(point => {
                    bounds.extend(point);
                    totalElementos++;
                });
            });

            if (totalElementos === 0) {
                console.log('Nenhum elemento para centralizar');
                return;
            }

            console.log('Bounds calculado:', {
                northeast: bounds.getNorthEast().toJSON(),
                southwest: bounds.getSouthWest().toJSON()
            });

            map.fitBounds(bounds);
            
            // Se houver poucos elementos, definir zoom específico
            if (totalElementos <= 10) {
                setTimeout(() => {
                    const currentZoom = map.getZoom();
                    const newZoom = Math.max(currentZoom, 16);
                    console.log(`Ajustando zoom de ${currentZoom} para ${newZoom}`);
                    map.setZoom(newZoom);
                }, 1000);
            }

            console.log(`Mapa centralizado com ${totalElementos} elementos`);
        }

        // Função para inicializar o estado visual dos botões de camadas
        function inicializarBotoesCamadas() {
            console.log('Inicializando botões de camadas...');
            
            // Contar quadras e lotes separadamente
            let totalQuadras = 0;
            let totalLotes = 0;
            
            if (window.poligonosQuadras) {
                window.poligonosQuadras.forEach(polygon => {
                    if (polygon.camada === 'quadra') {
                        totalQuadras++;
                    } else if (polygon.camada === 'lote') {
                        totalLotes++;
                    }
                });
            }
            
            // Fallback para array antigo
            if (totalQuadras === 0) {
                totalQuadras = quadrasPolygons.length;
            }
            
            const stats = {
                quadras: totalQuadras,
                lotes: totalLotes
            };
            
            console.log('Estatísticas dos polígonos:', stats);
            
            // Ocultar botões desnecessários
            const botoesParaOcultar = ['btnLotes', 'btnQuarteiroes', 'btnLotesPrefeitura'];
            botoesParaOcultar.forEach(btnId => {
                const btn = document.getElementById(btnId);
                if (btn) {
                    btn.style.display = 'none';
                }
            });
            
            // Configurar apenas o botão de quadras
            const btnQuadras = document.getElementById('btnQuadras');
            if (btnQuadras && stats.quadras > 0 && camadasVisiveis.quadras) {
                btnQuadras.classList.remove('btn-outline-secondary');
                btnQuadras.classList.add('btn-outline-warning');
                btnQuadras.style.display = 'inline-block';
                console.log(`Botão quadras visível (${stats.quadras} quadras)`);
            } else if (btnQuadras) {
                btnQuadras.style.display = 'none';
                console.log('Botão quadras oculto (sem quadras)');
            }
            
            // Controlar botão lotes da quadrícula
            const btnLotesQuadricula = document.getElementById('chkLotesQuadricula');
            if (btnLotesQuadricula && stats.lotes > 0) {
                btnLotesQuadricula.checked = true;
                console.log(`Botão lotes quadrícula disponível (${stats.lotes} lotes)`);
            } else if (btnLotesQuadricula) {
                btnLotesQuadricula.checked = false;
                console.log('Botão lotes quadrícula desmarcado (sem lotes)');
            }

            // Sincronizar com checkboxes do menu dropdown
            const toggleQuadrasCheckbox = document.getElementById('toggleQuadras');
            const toggleLotesCheckbox = document.getElementById('toggleLotes');
            
            if (toggleQuadrasCheckbox) {
                toggleQuadrasCheckbox.checked = stats.quadras > 0;
            }
            
            if (toggleLotesCheckbox) {
                toggleLotesCheckbox.checked = stats.lotes > 0;
            }

            // Log final sobre estado dos polígonos
            console.log(`Total de polígonos no mapa: ${stats.quadras} quadras, ${stats.lotes} lotes`);
        }

        // Estado de visibilidade das camadas
        const camadasVisiveis = {
            lotes: true,
            quadras: true,
            quarteiroes: true,
            lotesPrefeitura: true
        };

        function toggleCamada(tipo) {
            camadasVisiveis[tipo] = !camadasVisiveis[tipo];
            const visivel = camadasVisiveis[tipo];
            
            console.log(`${tipo} ${visivel ? 'mostrado' : 'ocultado'}`);
            
            // Atualizar botão
            const btn = document.getElementById(`btn${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
            if (visivel) {
                btn.classList.remove('btn-outline-secondary');
                switch(tipo) {
                    case 'lotes':
                        btn.classList.add('btn-outline-success');
                        break;
                    case 'quadras':
                        btn.classList.add('btn-outline-warning');
                        break;
                    case 'quarteiroes':
                        btn.classList.add('btn-outline-info');
                        break;
                    case 'lotesPrefeitura':
                        btn.classList.add('btn-outline-danger');
                        break;
                }
            } else {
                btn.classList.remove('btn-outline-success', 'btn-outline-warning', 'btn-outline-info', 'btn-outline-danger');
                btn.classList.add('btn-outline-secondary');
            }
            
            // Mostrar/ocultar polígonos correspondentes
            switch(tipo) {
                case 'lotes':
                    lotesPolygons.forEach(polygon => {
                        polygon.setMap(visivel ? map : null);
                    });
                    break;
                case 'quadras':
                    quadrasPolygons.forEach(polygon => {
                        polygon.setMap(visivel ? map : null);
                    });
                    break;
                case 'quarteiroes':
                    quarteiraoPolygons.forEach(polygon => {
                        polygon.setMap(visivel ? map : null);
                    });
                    break;
                case 'lotesPrefeitura':
                    lotesPrefeituraPolygons.forEach(polygon => {
                        polygon.setMap(visivel ? map : null);
                    });
                    break;
            }
        }

        function forcarVisualizacao() {
            console.log('Forçando visualização dos marcadores...');
            
            if (markers.length === 0) {
                alert('Nenhum marcador encontrado para visualizar.');
                console.log('Lista de marcadores vazia:', markers);
                return;
            }
            
            console.log('Total de marcadores para visualizar:', markers.length);
            
            // Pegar primeiro marcador como referência
            const primeiroMarcador = markers[0];
            console.log('Primeiro marcador:', primeiroMarcador);
            console.log('Posição do primeiro marcador:', primeiroMarcador.getPosition());
            
            // Centralizar no primeiro marcador com zoom alto
            const position = primeiroMarcador.getPosition();
            map.setCenter(position);
            map.setZoom(19);
            
            console.log('Mapa centralizado em:', position.toJSON());
            
            // Criar um marcador temporário maior para indicar onde estamos olhando
            const indicador = new google.maps.Marker({
                map: map,
                position: position,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 15,
                    fillColor: '#FF0000',
                    fillOpacity: 0.5,
                    strokeColor: '#FF0000',
                    strokeWeight: 3
                },
                title: 'Centro dos marcadores'
            });
            
            // Remover indicador após 3 segundos
            setTimeout(() => {
                indicador.setMap(null);
            }, 3000);
            
            // Forçar redraw e listar todos os marcadores
            setTimeout(() => {
                google.maps.event.trigger(map, 'resize');
                
                console.log('=== LISTA DE TODOS OS MARCADORES ===');
                markers.forEach((marker, index) => {
                    console.log(`Marcador ${index + 1}:`, marker.getPosition().toJSON());
                });
                console.log('=====================================');
                
            }, 500);
        }

        function debugPoligonos() {
            console.log('=== DEBUG SIMPLES DOS POLÍGONOS ===');
            
            const stats = {
                marcadores: markers.length,
                quadrasPolygons: quadrasPolygons.length
            };
            
            console.log('Estatísticas dos elementos:', stats);
            
            // Debug apenas de quadras
            console.log('--- QUADRAS ---');
            quadrasPolygons.forEach((polygon, index) => {
                console.log(`Quadra ${index + 1}:`, {
                    camada: polygon.camada,
                    dadosOriginais: polygon.dadosOriginais,
                    visible: polygon.getVisible(),
                    map: polygon.getMap() !== null,
                    pathLength: polygon.getPath() ? polygon.getPath().getLength() : 'N/A'
                });
            });
            
            // console.log('--- LOTES ---');
            lotesPolygons.forEach((polygon, index) => {
                console.log(`Lote ${index + 1}:`, {
                    camada: polygon.camada,
                    dadosOriginais: polygon.dadosOriginais,
                    visible: polygon.getVisible(),
                    map: polygon.getMap() !== null,
                    pathLength: polygon.getPath() ? polygon.getPath().getLength() : 'N/A'
                });
            });
            
            console.log('--- QUADRAS ---');
            quadrasPolygons.forEach((polygon, index) => {
                console.log(`Quadra ${index + 1}:`, {
                    camada: polygon.camada,
                    dadosOriginais: polygon.dadosOriginais,
                    visible: polygon.getVisible(),
                    map: polygon.getMap() !== null,
                    pathLength: polygon.getPath() ? polygon.getPath().getLength() : 'N/A'
                });
            });
            
            console.log('--- QUARTEIRÕES ---');
            quarteiraoPolygons.forEach((polygon, index) => {
                console.log(`Quarteirão ${index + 1}:`, {
                    camada: polygon.camada,
                    dadosOriginais: polygon.dadosOriginais,
                    visible: polygon.getVisible(),
                    map: polygon.getMap() !== null,
                    pathLength: polygon.getPath() ? polygon.getPath().getLength() : 'N/A'
                });
            });
            
            console.log('--- LOTES DA PREFEITURA ---');
            lotesPrefeituraPolygons.forEach((polygon, index) => {
                console.log(`Lote Prefeitura ${index + 1}:`, {
                    quadricula: polygon.quadricula,
                    camada: polygon.camada,
                    visible: polygon.getVisible(),
                    map: polygon.getMap() !== null,
                    pathLength: polygon.getPath() ? polygon.getPath().getLength() : 'N/A'
                });
            });
            
            // Testar forçar visibilidade de todos os polígonos
            console.log('Forçando visibilidade de todos os polígonos...');
            [...lotesPolygons, ...quadrasPolygons, ...quarteiraoPolygons, ...lotesPrefeituraPolygons].forEach(polygon => {
                if (polygon.getMap() === null) {
                    polygon.setMap(map);
                    console.log('Polígono adicionado ao mapa:', polygon.camada);
                }
                if (!polygon.getVisible()) {
                    polygon.setVisible(true);
                    console.log('Polígono tornado visível:', polygon.camada);
                }
            });
            
            console.log('=== FIM DEBUG POLÍGONOS ===');
            
            // Mostrar alerta com resumo
            alert(`Debug Polígonos:
                Lotes: ${stats.lotesPolygons}
                Quadras: ${stats.quadrasPolygons}
                Quarteirões: ${stats.quarteiraoPolygons}
                Lotes Prefeitura: ${stats.lotesPrefeituraPolygons}
                
                Todos foram forçados a serem visíveis.
                Verifique o console para detalhes.`);
        }

        function voltarConsultas() {
            window.close();
            // Se não conseguir fechar (bloqueado pelo navegador), redirecionar
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 100);
        }

        function esconderLoading() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'none';
                console.log('Loading overlay escondido');
            }
        }

        function mostrarErroMapa(mensagem) {
            esconderLoading();
            const mapElement = document.getElementById('map');
            if (mapElement) {
                mapElement.innerHTML = `
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; background: #f8f9fa; border-radius: 10px;">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h4>Erro ao Carregar Mapa</h4>
                        <p class="text-muted text-center">${mensagem}</p>
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-refresh"></i> Tentar Novamente
                        </button>
                    </div>
                `;
            }
        }

        // Inicializar quando a página carregar
        window.onload = function() {
            console.log('Página carregada, inicializando mapa...');
            
            // Aguardar um pouco para garantir que tudo foi carregado
            setTimeout(() => {
                // Verificar se Google Maps API está disponível
                if (typeof google === 'undefined') {
                    console.error('Google Maps API não carregado!');
                    mostrarErroMapa('Google Maps API não foi carregada. Verifique sua conexão com a internet.');
                    return;
                }
                
                console.log('Google Maps API disponível, iniciando...');
                initMap();
            }, 1000);
            
            // Timeout de segurança para evitar loading infinito
            setTimeout(() => {
                const loadingOverlay = document.getElementById('loadingOverlay');
                if (loadingOverlay && loadingOverlay.style.display !== 'none') {
                    console.warn('Timeout na inicialização do mapa');
                    mostrarErroMapa('Timeout na inicialização. O mapa demorou muito para carregar.');
                }
            }, 15000);
        };

        // Debug: Log completo dos dados recebidos
        window.addEventListener('load', function() {
            console.log('=== DEBUG COMPLETO ===');
            console.log('URL atual:', window.location.href);
            console.log('Dados originais completos:', dadosOriginais);
            console.log('Filtros recebidos completos:', filtrosRecebidos);
            console.log('=====================');
            
            // Carregar opacidade salva na inicialização
            setTimeout(() => {
                carregarOpacidadeSalva();
            }, 1500);
            
        });
    </script>
</body>
</html>
