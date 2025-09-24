<?php
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
            
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" onclick="voltarConsultas()">
                    <i class="fas fa-arrow-left"></i> Voltar
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="centralizarMapa()">
                    <i class="fas fa-crosshairs"></i> Centralizar
                </button>
                <button class="btn btn-sm btn-outline-info" onclick="forcarVisualizacao()">
                    <i class="fas fa-eye"></i> Ver Marcadores
                </button>
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
                        <div class="stat-number" id="registrosComCoordenadas">0</div>
                        <div class="stat-label">Com Coordenadas</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" id="registrosSemCoordenadas">0</div>
                        <div class="stat-label">Sem Coordenadas</div>
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
        let dadosOriginais = [];
        let filtrosRecebidos = [];
        let coordenadasDesenhos = [];
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
                    registros: dadosOriginais.slice(0, 2) // Mostrar apenas 2 primeiros para debug
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
                    
                    console.log(`Encontradas ${coordenadasDesenhos.length} coordenadas`);
                    console.log('Estatísticas:', resultado.stats);
                    
                    // Atualizar estatísticas na interface
                    atualizarEstatisticasDesenhos(resultado.stats);
                    
                    // Criar elementos no mapa
                    await criarElementosNoMapa();
                    
                    // Centralizar mapa se houver elementos
                    if (markers.length > 0 || polygons.length > 0 || polylines.length > 0) {
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

            for (const item of coordenadasDesenhos) {
                try {
                    const tipo = item.tipo.toLowerCase();
                    const coordenadas = item.coordenadas;
                    
                    console.log(`Processando ${tipo}:`, item);

                    switch (tipo) {
                        case 'marcador':
                            await criarMarcador(item);
                            marcadoresCriados++;
                            break;
                            
                        case 'poligono':
                        case 'polígono':
                            await criarPoligono(item);
                            poligonosCriados++;
                            break;
                            
                        case 'polilinha':
                            await criarPolilinha(item);
                            polilinhasCriadas++;
                            break;
                            
                        default:
                            console.warn('Tipo de desenho desconhecido:', tipo);
                    }
                    
                } catch (error) {
                    console.error('Erro ao criar elemento:', error, item);
                }
            }

            console.log(`Elementos criados: ${marcadoresCriados} marcadores, ${poligonosCriados} polígonos, ${polilinhasCriadas} polilinhas`);
        }

        async function criarMarcador(item) {
            console.log('=== CRIANDO MARCADOR ===');
            console.log('Item recebido:', item);
            
            const coordenadas = item.coordenadas;
            console.log('Coordenadas brutas:', coordenadas);
            console.log('Tipo das coordenadas:', typeof coordenadas);
            
            let lat, lng;

            // As coordenadas vêm como array de objetos: [{"lat": -22.xx, "lng": -47.xx}]
            if (Array.isArray(coordenadas) && coordenadas.length > 0) {
                const coord = coordenadas[0]; // Pegar primeiro elemento do array
                if (coord && coord.lat && coord.lng) {
                    lat = parseFloat(coord.lat);
                    lng = parseFloat(coord.lng);
                    console.log('Coordenadas extraídas do array:', { lat, lng });
                }
            } else if (typeof coordenadas === 'object' && coordenadas.lat && coordenadas.lng) {
                lat = parseFloat(coordenadas.lat);
                lng = parseFloat(coordenadas.lng);
                console.log('Coordenadas extraídas do objeto:', { lat, lng });
            } else if (typeof coordenadas === 'string') {
                try {
                    const parsed = JSON.parse(coordenadas);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        lat = parseFloat(parsed[0].lat);
                        lng = parseFloat(parsed[0].lng);
                        console.log('Coordenadas extraídas da string JSON:', { lat, lng });
                    }
                } catch (e) {
                    // Tentar parsing simples "lat,lng"
                    const parts = coordenadas.split(',');
                    if (parts.length >= 2) {
                        lat = parseFloat(parts[0]);
                        lng = parseFloat(parts[1]);
                        console.log('Coordenadas extraídas da string simples:', { lat, lng });
                    }
                }
            }

            console.log('Coordenadas finais:', { lat, lng });

            if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
                try {
                    console.log('Criando marcador no Google Maps...');
                    
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
                        console.log('Marcador clicado:', item);
                        mostrarDetalhesDesenho(item, marker);
                    });

                    markers.push(marker);
                    console.log(`✅ Marcador criado com sucesso em: ${lat}, ${lng}`);
                    console.log('Total de marcadores:', markers.length);
                } catch (error) {
                    console.error('❌ Erro ao criar marcador:', error);
                }
            } else {
                console.error('❌ Coordenadas inválidas:', { lat, lng, coordenadas });
            }
            
            console.log('=== FIM CRIAÇÃO MARCADOR ===');
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

        function atualizarEstatisticasDesenhos(stats) {
            // Atualizar estatísticas com informações dos desenhos
            const totalDesenhos = stats.coordenadas_encontradas;
            const tipos = stats.tipos_encontrados;
            
            // Adicionar informações de desenhos nas estatísticas
            const statsContainer = document.querySelector('.stats-row');
            
            // Remover estatísticas antigas de desenhos se existirem
            const existingStats = statsContainer.querySelectorAll('.stat-desenho');
            existingStats.forEach(stat => stat.remove());
            
            // Adicionar novas estatísticas
            const statDesenhos = document.createElement('div');
            statDesenhos.className = 'stat-item stat-desenho';
            statDesenhos.style.borderLeftColor = '#28a745';
            statDesenhos.innerHTML = `
                <div class="stat-number">${totalDesenhos}</div>
                <div class="stat-label">Desenhos Encontrados</div>
            `;
            statsContainer.appendChild(statDesenhos);
            
            // Estatísticas por tipo
            Object.keys(tipos).forEach(tipo => {
                if (tipos[tipo] > 0) {
                    const statTipo = document.createElement('div');
                    statTipo.className = 'stat-item stat-desenho';
                    statTipo.style.borderLeftColor = tipo === 'marcador' ? '#ffc107' : tipo === 'poligono' ? '#dc3545' : '#17a2b8';
                    statTipo.innerHTML = `
                        <div class="stat-number">${tipos[tipo]}</div>
                        <div class="stat-label">${tipo.charAt(0).toUpperCase() + tipo.slice(1)}s</div>
                    `;
                    statsContainer.appendChild(statTipo);
                }
            });
        }

        async function criarMarcadores() {
            console.log('Criando marcadores no mapa...');
            
            let marcadoresCriados = 0;
            let registrosComCoordenadas = 0;
            let registrosSemCoordenadas = 0;

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
            
            // Atualizar estatísticas na interface
            document.getElementById('registrosComCoordenadas').textContent = registrosComCoordenadas;
            document.getElementById('registrosSemCoordenadas').textContent = registrosSemCoordenadas;
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
                polylines: polylines.length
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
            if (markers.length <= 10) {
                setTimeout(() => {
                    const currentZoom = map.getZoom();
                    const newZoom = Math.max(currentZoom, 16);
                    console.log(`Ajustando zoom de ${currentZoom} para ${newZoom}`);
                    map.setZoom(newZoom);
                }, 1000);
            }

            console.log(`Mapa centralizado com ${totalElementos} elementos`);
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
        });
    </script>
</body>
</html>
