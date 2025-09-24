<?php

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
    </style>

<body>

    <div class="divContainerMap">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">

                <!-- Título -->
                <a class="navbar-brand" href="#">Plataforma Geo</a>

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
                    <a href="logout.php" class="btn btn-danger">Sair</a>
                </div>
            </div>
        </nav>

        <div id="map"></div>
    </div>

    <script>
        let coordsLocal = { lat: -22.754690200587653, lng: -47.157327848672836 }; 
        let map;
        let quadriculasData = [];
        let polygons = [];
        let markers = [];
        let selectedPolygon = null;
        let selectedQuadricula = null;
        let kmlLayer = null;
        let limitePolyline = null;

        // Carregar dados das quadrículas
        $.ajax({
            url: "loteamentos_quadriculas/mapa_quadriculas.json",
            dataType: "json",
            async: false,
            type: "GET",
            success: function(response) {
                quadriculasData = response;
                //console.log("Dados carregados:", quadriculasData);
            }
        });

        // Função para converter bounds em polígono
        function boundsToPolygon(bounds) {
            const [lng1, lat1, lng2, lat2] = bounds;
            return [
                { lat: lat1, lng: lng1 },
                { lat: lat1, lng: lng2 },
                { lat: lat2, lng: lng2 },
                { lat: lat2, lng: lng1 },
                { lat: lat1, lng: lng1 } // Fechar o polígono
            ];
        }

        // Função para calcular o centro de um polígono
        function getPolygonCenter(bounds) {
            const [lng1, lat1, lng2, lat2] = bounds;
            return {
                lat: (lat1 + lat2) / 2,
                lng: (lng1 + lng2) / 2
            };
        }

        // Função para criar polígonos das quadrículas
        function createQuadriculasPolygons() {
            quadriculasData.forEach((quadricula, index) => {
                const polygonPath = boundsToPolygon(quadricula.bounds);
                
                // Criar polígono
                const polygon = new google.maps.Polygon({
                    paths: polygonPath,
                    strokeColor: '#000000',
                    strokeOpacity: 1.0,
                    strokeWeight: 2,
                    fillColor: 'transparent',
                    fillOpacity: 0,
                    clickable: true,
                    zIndex: 1
                });

                // Armazenar referência da quadrícula no polígono
                polygon.quadriculaData = quadricula;

                // Adicionar eventos do polígono
                polygon.addListener('mouseover', function() {
                    if (polygon !== selectedPolygon) {
                        polygon.setOptions({
                            strokeWeight: 3,
                            strokeColor: '#FF0000',
                            fillColor: '#FF0000',
                            fillOpacity: 0.5,
                            zIndex: 3
                        });
                    }
                });

                polygon.addListener('mouseout', function() {
                    if (polygon !== selectedPolygon) {
                        polygon.setOptions({
                            strokeWeight: 2,
                            strokeColor: '#000000',
                            fillColor: 'transparent',
                            fillOpacity: 0,
                            zIndex: 2
                        });
                    }
                });

                polygon.addListener('click', function() {
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
                    selectedPolygon = polygon;
                    selectedQuadricula = polygon.quadriculaData;
                    polygon.setOptions({
                        strokeWeight: 3,
                        strokeColor: '#0066FF',
                        fillColor: '#0066FF',
                        fillOpacity: 0.5,
                        zIndex: 4
                    });

                    // Mostrar botão de navegação
                    showNavigationButton();
                });

                // Adicionar polígono ao mapa
                polygon.setMap(map);
                polygons.push(polygon);

                // Criar marker personalizado no centro
                const center = getPolygonCenter(quadricula.bounds);
                const markerElement = document.createElement('div');
                markerElement.innerHTML = `
                    <div style="
                        background: rgb(255, 255, 255);
                        border: 1px solid #000;
                        border-radius: 4px;
                        padding: 4px 8px;
                        font-weight: bold;
                        font-size: 12px;
                        color: #000;
                        text-align: center;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                        white-space: nowrap;
                    ">
                        ${quadricula.nome}
                    </div>
                `;

                const marker = new google.maps.marker.AdvancedMarkerElement({
                    position: center,
                    content: markerElement,
                    map: map,
                    zIndex: 10
                });

                markers.push(marker);
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
            polygons.forEach(polygon => {
                polygon.setVisible(show);
            });
            markers.forEach(marker => {
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
                    window.location.href = `index_3.php?quadricula=${selectedQuadricula.nome}`;
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

            // Criar polígonos das quadrículas após o mapa ser inicializado
            createQuadriculasPolygons();
            
            // Carregar Polyline do limite do município
            loadLimitePolyline();
        }

        initMap();
    </script>

</body>

</head>