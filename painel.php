<?php

session_start();
//include("verifica_login.php");
include("connection.php");

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
    
    <!-- Biblioteca para conversão KML para GeoJSON -->
    <script src="https://unpkg.com/@mapbox/togeojson@0.16.2/togeojson.js"></script>

    <!-- Google Maps API -->
    <script src="apiGoogle.js"></script>

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

        .dropdown-menu{
            padding: 0 30px;
        }

        /* Modal de carregamento */
        .modal-loading {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            backdrop-filter: blur(3px);
        }

        .modal-content-loading {
            background-color: #fff;
            margin: 15% auto;
            padding: 30px;
            border-radius: 15px;
            width: 400px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .loading-subtitle {
            font-size: 14px;
            color: #666;
        }
    </style>

<body>

    <div class="divContainerMap">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">

                <!-- Título -->
                <a class="navbar-brand" href="#">Visão geral das quadrículas</a>

                <!-- Botões -->
                <div class="d-flex align-items-center flex-grow-1 gap-2">
                    <!-- Dropdown de Camadas -->
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" id="camadasDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-layer-group"></i> Camadas
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="camadasDropdown">
                            <li>
                                <div class="form-check px-3 py-2">
                                    <input class="form-check-input" type="checkbox" value="" id="checkboxQuadriculas" checked>
                                    <label class="form-check-label" for="checkboxQuadriculas">
                                        Quadrículas
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check px-3 py-2">
                                    <input class="form-check-input" type="checkbox" value="" id="checkboxLimiteMunicipio" checked>
                                    <label class="form-check-label" for="checkboxLimiteMunicipio">
                                        Limite do Município
                                    </label>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Botão de Navegação para Quadrícula Selecionada -->
                    <button class="btn btn-warning" id="btnIrConsulta" onclick="irConsulta()">
                        <i class="fas fa-external-link-alt"></i> Consultas
                    </button>

                    <!-- Botão de Navegação para Quadrícula Selecionada -->
                    <button class="btn btn-primary" id="btnIrQuadricula" style="display: none;">
                        <i class="fas fa-external-link-alt"></i> Ir para Quadrícula
                    </button>
                </div>

                <!-- Botão Sair -->
                <div class="d-flex">
                    <a href="suporte_redirect.php" class="btn btn-primary">Suporte</a>
                    &nbsp;&nbsp;&nbsp;
                    <a href="logout.php" class="btn btn-danger">Sair</a>
                </div>
            </div>
        </nav>

        <div id="map"></div>
    </div>

    <!-- Modal de carregamento -->
    <div id="modalLoading" class="modal-loading">
        <div class="modal-content-loading">
            <div class="loading-spinner"></div>
            <div class="loading-text">Carregando Quadrícula...</div>
            <div class="loading-subtitle" id="loadingSubtitle">Aguarde um momento</div>
        </div>
    </div>

    <script>
        let coordsLocal = { lat: -22.754690200587653, lng: -47.157327848672836 }; 
        let map;
        let quadriculasPolygons = [];
        let quadriculasRotulos = [];
        let selectedPolygon = null;
        let selectedQuadricula = null;
        let limitePolyline = null;

        // Inicializar arrays para quadrículas
        if (!quadriculasPolygons) quadriculasPolygons = [];
        if (!quadriculasRotulos) quadriculasRotulos = [];

        // Função para mostrar modal de carregamento
        function showLoadingModal(quadriculaNome) {
            const modal = document.getElementById('modalLoading');
            const subtitle = document.getElementById('loadingSubtitle');
            
            subtitle.textContent = `Redirecionando para ${quadriculaNome}...`;
            modal.style.display = 'block';
            
            // Prevenir scroll da página
            document.body.style.overflow = 'hidden';
        }

        // Função para esconder modal de carregamento
        function hideLoadingModal() {
            const modal = document.getElementById('modalLoading');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Função para navegar para quadrícula com modal
        function navigateToQuadricula(quadriculaNome) {
            showLoadingModal(quadriculaNome);
            
            // Pequeno delay para mostrar o modal antes do redirecionamento
            setTimeout(() => {
                window.location.href = `index_3.php?quadricula=${quadriculaNome}`;
            }, 500);
        }

        // Função para carregar quadrículas do KML
        function carregarQuadriculasKML(urlKML = 'quadriculas_paulinia.kml') {
            if (!window.toGeoJSON) {
                console.error('toGeoJSON não está carregado!');
                return;
            }
            if (!map) {
                console.error('O mapa ainda não foi inicializado!');
                return;
            }
            
            // Remove quadrículas antigas
            quadriculasPolygons.forEach(obj => { if (obj.setMap) obj.setMap(null); });
            quadriculasPolygons = [];
            
            // Remove rótulos antigos
            quadriculasRotulos.forEach(obj => { if (obj.setMap) obj.setMap(null); });
            quadriculasRotulos = [];
            
            // Carrega o KML
            fetch(urlKML)
                .then(res => res.text())
                .then(kmlText => {
                    const parser = new DOMParser();
                    const kml = parser.parseFromString(kmlText, 'text/xml');
                    const geojson = toGeoJSON.kml(kml);
                    
                    geojson.features.forEach(f => {
                        let obj = null;
                        let centro = null;
                        
                        if (f.geometry.type === 'Polygon' || f.geometry.type === 'MultiPolygon') {
                            let paths = [];
                            if (f.geometry.type === 'Polygon') {
                                paths = f.geometry.coordinates[0].map(([lng, lat]) => ({ lat, lng }));
                            } else if (f.geometry.type === 'MultiPolygon') {
                                f.geometry.coordinates.forEach(poly => {
                                    paths = paths.concat(poly[0].map(([lng, lat]) => ({ lat, lng })));
                                });
                            }
                            
                            obj = new google.maps.Polygon({
                                paths: paths,
                                strokeColor: '#000000',
                                strokeOpacity: 1.0,
                                strokeWeight: 2,
                                fillColor: 'transparent',
                                fillOpacity: 0,
                                clickable: true,
                                zIndex: 1
                            });
                            
                            // Calcular centro do polígono
                            const bounds = new google.maps.LatLngBounds();
                            paths.forEach(path => bounds.extend(path));
                            centro = bounds.getCenter();
                        }
                        
                        if (obj) {
                            // Armazenar dados da quadrícula no polígono
                            obj.quadriculaData = {
                                nome: f.properties ? f.properties.name : 'Quadrícula',
                                centro: centro
                            };
                            
                            // Adicionar eventos do polígono
                            obj.addListener('mouseover', function() {
                                if (obj !== selectedPolygon) {
                                    obj.setOptions({
                                        strokeWeight: 3,
                                        strokeColor: '#FF0000',
                                        fillColor: '#FF0000',
                                        fillOpacity: 0.5,
                                        zIndex: 3
                                    });
                                }
                            });
                            
                            obj.addListener('mouseout', function() {
                                if (obj !== selectedPolygon) {
                                    obj.setOptions({
                                        strokeWeight: 2,
                                        strokeColor: '#000000',
                                        fillColor: 'transparent',
                                        fillOpacity: 0,
                                        zIndex: 2
                                    });
                                }
                            });
                            
                            obj.addListener('click', function() {
                                // Desselecionar polígono anterior se existir
                                if (selectedPolygon) {
                                    selectedPolygon.setOptions({
                                        strokeWeight: 2,
                                        strokeColor: '#000000',
                                        fillColor: 'transparent',
                                        fillOpacity: 0,
                                        zIndex: 2
                                    });
                                }
                                
                                // Selecionar novo polígono
                                selectedPolygon = obj;
                                selectedQuadricula = obj.quadriculaData;
                                obj.setOptions({
                                    strokeWeight: 3,
                                    strokeColor: '#0066FF',
                                    fillColor: '#0066FF',
                                    fillOpacity: 0.5,
                                    zIndex: 4
                                });
                                
                                // Mostrar botão de navegação
                                showNavigationButton();
                            });
                            
                            // Adicionar evento de duplo clique para navegação direta
                            obj.addListener('dblclick', function(event) {
                                // Prevenir o zoom do mapa no duplo clique
                                event.stop();
                                
                                // Selecionar a quadrícula primeiro
                                if (selectedPolygon) {
                                    selectedPolygon.setOptions({
                                        strokeWeight: 2,
                                        strokeColor: '#000000',
                                        fillColor: 'transparent',
                                        fillOpacity: 0,
                                        zIndex: 2
                                    });
                                }
                                
                                selectedPolygon = obj;
                                selectedQuadricula = obj.quadriculaData;
                                obj.setOptions({
                                    strokeWeight: 3,
                                    strokeColor: '#0066FF',
                                    fillColor: '#0066FF',
                                    fillOpacity: 0.5,
                                    zIndex: 4
                                });
                                
                                // Navegar com modal de carregamento
                                if (selectedQuadricula) {
                                    navigateToQuadricula(selectedQuadricula.nome);
                                }
                            });
                            
                            quadriculasPolygons.push(obj);
                            obj.setMap(map);
                        }
                        
                        // Adiciona rótulo se houver nome e centro
                        if (f.properties && f.properties.name && centro) {
                            const labelDiv = document.createElement('div');
                            labelDiv.style.fontSize = '12px';
                            labelDiv.style.color = '#000';
                            labelDiv.style.background = 'rgba(255,255,255,0.7)';
                            labelDiv.style.padding = '4px 8px';
                            labelDiv.style.borderRadius = '4px';
                            labelDiv.style.border = '1px solid #000';
                            labelDiv.style.fontWeight = 'bold';
                            labelDiv.style.textAlign = 'center';
                            labelDiv.style.boxShadow = '0 2px 4px rgba(0,0,0,0.3)';
                            labelDiv.style.whiteSpace = 'nowrap';
                            labelDiv.innerText = f.properties.name;
                            
                            let marker;
                            if (google.maps.marker && google.maps.marker.AdvancedMarkerElement) {
                                marker = new google.maps.marker.AdvancedMarkerElement({
                                    position: centro,
                                    content: labelDiv,
                                    gmpClickable: false,
                                    zIndex: 10
                                });
                                marker.setMap(map);
                            } else {
                                marker = new google.maps.Marker({
                                    position: centro,
                                    map: map,
                                    icon: {
                                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><rect width="1" height="1" fill="transparent"/></svg>'),
                                        scaledSize: new google.maps.Size(1, 1)
                                    },
                                    label: {
                                        text: f.properties.name,
                                        color: '#000',
                                        fontSize: '12px',
                                        fontWeight: 'bold'
                                    },
                                    zIndex: 10
                                });
                            }
                            quadriculasRotulos.push(marker);
                        }
                    });
                    
                    console.log('Quadrículas carregadas do KML:', quadriculasPolygons.length);
                })
                .catch(error => {
                    console.error('Erro ao carregar quadrículas KML:', error);
                });
        }


        // Função para mostrar/esconder botão de navegação
        function showNavigationButton() {
            const btn = document.getElementById('btnIrQuadricula');
            if (selectedQuadricula) {
                btn.style.display = 'inline-block';
                btn.innerHTML = `<i class="fas fa-external-link-alt"></i> Ir para ${selectedQuadricula.nome}`;
            } else {
                btn.style.display = 'none';
            }
        }

        function hideNavigationButton() {
            const btn = document.getElementById('btnIrQuadricula');
            btn.style.display = 'none';
        }

        function irConsulta(){
            window.location.href = `consultas`;
        }

        // Função para controlar visibilidade das quadrículas
        function toggleQuadriculas(show) {
            quadriculasPolygons.forEach(polygon => {
                polygon.setVisible(show);
            });
            quadriculasRotulos.forEach(marker => {
                if (show) {
                    marker.map = map;
                } else {
                    marker.map = null;
                }
            });
        }

        // Função para controlar visibilidade do limite do município
        function toggleLimiteMunicipio(show) {
            if (limitePolyline) {
                limitePolyline.setVisible(show);
            }
        }

        // Função para carregar KML e extrair coordenadas
        function loadLimitePolyline() {
            console.log('Carregando KML para extrair coordenadas...');
            
            $.ajax({
                url: 'limite_paulinia.kml',
                dataType: 'xml',
                success: function(xml) {
                    console.log('KML carregado com sucesso!');
                    
                    // Extrair coordenadas do KML
                    const coordenadas = [];
                    $(xml).find('coordinates').each(function() {
                        const coordText = $(this).text().trim();
                        const coordPairs = coordText.split(/\s+/);
                        
                        coordPairs.forEach(function(coord) {
                            if (coord.trim()) {
                                const parts = coord.split(',');
                                if (parts.length >= 2) {
                                    const lng = parseFloat(parts[0]);
                                    const lat = parseFloat(parts[1]);
                                    if (!isNaN(lat) && !isNaN(lng)) {
                                        coordenadas.push({ lat: lat, lng: lng });
                                    }
                                }
                            }
                        });
                    });
                    
                    console.log('Coordenadas extraídas:', coordenadas.length);
                    
                    if (coordenadas.length > 0) {
                        // Criar Polyline com as coordenadas reais
                        limitePolyline = new google.maps.Polyline({
                            path: coordenadas,
                            geodesic: true,
                            strokeColor: '#FF0000',  // Vermelho
                            strokeOpacity: 1.0,
                            strokeWeight: 4,        // 4px de espessura
                            clickable: false,
                            zIndex: 1
                        });
                        
                        // Adicionar ao mapa
                        limitePolyline.setMap(map);
                        console.log('Limite do município criado com coordenadas reais do KML');
                    } else {
                        console.log('Nenhuma coordenada encontrada no KML');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Erro ao carregar KML:', error);
                    console.log('Criando limite com coordenadas padrão...');
                    
                    // Fallback com coordenadas aproximadas
                    const coordenadasLimite = [
                        { lat: -22.780000, lng: -47.200000 },
                        { lat: -22.780000, lng: -47.100000 },
                        { lat: -22.720000, lng: -47.100000 },
                        { lat: -22.720000, lng: -47.200000 },
                        { lat: -22.780000, lng: -47.200000 }
                    ];
                    
                    limitePolyline = new google.maps.Polyline({
                        path: coordenadasLimite,
                        geodesic: true,
                        strokeColor: '#FF0000',
                        strokeOpacity: 1.0,
                        strokeWeight: 4,
                        clickable: false,
                        zIndex: 1
                    });
                    
                    limitePolyline.setMap(map);
                }
            });
        }

        // Event listeners para os checkboxes e botão de navegação
        $(document).ready(function() {
            $('#checkboxQuadriculas').change(function() {
                toggleQuadriculas(this.checked);
            });

            $('#checkboxLimiteMunicipio').change(function() {
                toggleLimiteMunicipio(this.checked);
            });

            // Event listener para o botão de navegação
            $('#btnIrQuadricula').click(function() {
                if (selectedQuadricula) {
                    navigateToQuadricula(selectedQuadricula.nome);
                }
            });
        });

        async function initMap() {

            // Request needed libraries.
            const {
                Map
            } = await google.maps.importLibrary("maps");

            const {
                geometry
            } = await google.maps.importLibrary("geometry");

            const {
                Draw
            } = await google.maps.importLibrary("drawing");

            const {
                AdvancedMarkerElement
            } = await google.maps.importLibrary("marker");

            // The map, centered at Uluru
            map = new Map(document.getElementById("map"), {

                //configuração do botão de mapa e tipo
                mapTypeControl: true,

                //tipo do mapa
                mapTypeId: 'roadmap',

                mapTypeControlOptions: {
                    mapTypeIds: ['roadmap', 'satellite']
                },

                //configuração do botão de zoom
                zoomControl: true,
                zoomControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_BOTTOM
                },

                //configuração do botão de escala                      
                scaleControl: true,

                //configuração do botão de tela cheia                                  
                fullscreenControl: true,
                fullscreenControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_BOTTOM
                },

                //configuração do botão de street view  
                streetViewControl: true,
                streetViewControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_BOTTOM
                },

                zoom: 14,
                center: coordsLocal,
                mapId: "mapImovel",
            });

            // Carregar quadrículas do KML após o mapa ser inicializado
            carregarQuadriculasKML();
            
            // Carregar Polyline do limite do município
            loadLimitePolyline();
        }

        initMap();
    </script>

</body>

</head>