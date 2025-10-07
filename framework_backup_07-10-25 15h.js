// Define a zona 23 Sul (WGS84) apenas uma vez
proj4.defs("EPSG:32723", "+proj=utm +zone=23 +south +datum=WGS84 +units=m +no_defs");

function utmToLatLng(easting, northing) {
    const [lng, lat] = proj4("EPSG:32723", "EPSG:4326", [easting, northing]);
    return { lat, lng };
}

// Converte uma lista de pares [E, N] para [{lat, lng}, ...]
function utmCoordsToLatLngList(coords) {
    // coords: ex. [[275743.45, 7483962.14], [275523.86, 7483891.58], ...]
    return coords.map(([e, n]) => utmToLatLng(e, n));
}

const MapFramework = {
    map: null,
    ortofotos: [],
    settings: {},
    quarteiroesNumeros: [],
    dadosMoradores: [],
    infoWindow: null,

    desenho: {
        modo: null,
        tipoAtual: null,
        pontos: [],
        temporario: null,
        listenerClick: null,
        listenerRightClick: null,
        cliqueEmVertice: false
    },

    // Contador de marcadores por quadra (em memória)
    marcadoresPorQuadra: {},

    selecionado: null,

    listenerGlobalClick: null,

    selecionarDesenho: function (objeto) {
        if (this.desenho.temporario) return; // Não selecionar durante desenho

        // Remove destaque anterior
        if (this.selecionado) {
            if (this.selecionado instanceof google.maps.Polygon) {
                this.selecionado.setOptions({
                    strokeColor: this.selecionado.corOriginal,
                    fillColor: this.selecionado.corOriginal
                });
            } else if (this.selecionado instanceof google.maps.Polyline) {
                this.selecionado.setOptions({
                    strokeColor: this.selecionado.corOriginal
                });
            }
        }

        // Define novo selecionado
        this.selecionado = objeto;

        //console.log(objeto)

        if (objeto instanceof google.maps.Polygon) {
            objeto.setOptions({
                strokeColor: 'yellow',
                fillColor: 'yellow'
            });
        } else if (objeto instanceof google.maps.Polyline) {
            objeto.setOptions({
                strokeColor: 'yellow'
            });
        }

        // Aqui garantimos que o botão aparece
        $('#btnExcluir').removeClass('d-none');
        $('#btnEditar').removeClass('d-none');
    },

    desselecionarDesenho: function () {
        if (this.selecionado) {
            const obj = this.selecionado;

            if (obj instanceof google.maps.Polygon) {
                obj.setOptions({
                    strokeColor: obj.corOriginal || '#0000FF', // azul como fallback
                    fillColor: obj.corOriginal || '#0000FF',
                    zIndex: 1
                });
            } else if (obj instanceof google.maps.Polyline) {
                obj.setOptions({
                    strokeColor: obj.corOriginal || '#FF0000', // vermelho como fallback
                    zIndex: 2
                });
            }

            this.selecionado = null;

            // Aqui garantimos que o botão some
            $('#btnExcluir').addClass('d-none');
            $('#btnEditar').addClass('d-none');
        }
    },

    iniciarMapa: async function (divId, center, zoom = 16) {

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

        const {
            places
        } = await google.maps.importLibrary("places");

        this.map = new Map(document.getElementById(divId), {
            center: center,
            zoom: zoom,
            mapTypeId: 'roadmap',
            zoomControl: true,
            scaleControl: true,
            
            streetViewControl: true,
            fullscreenControl: false,
            mapTypeControl: false,
            styles: [{ featureType: "poi", stylers: [{ visibility: "off" }] }],
            mapId: "7b2f242ba6401996"
        });

        this.map.setOptions({ draggableCursor: 'default' });

        this.listenerGlobalClick = this.map.addListener('click', () => {
            this.desselecionarDesenho();
            // Fecha o infowindow se estiver aberto
            if (this.infoWindow) {
                this.infoWindow.close();
            }
        });

        const streetView = this.map.getStreetView();
        streetView.addListener("visible_changed", function () {
            if (streetView.getVisible()) {
                $("#controleNavegacaoQuadriculas").hide();
            } else {
                $("#controleNavegacaoQuadriculas").show();
            }
        });
    },

    // Função para mostrar marcadores apenas do quarteirão selecionado
    mostrarMarcadoresDoQuarteirao: function(nomeQuarteirao) {
        if (!arrayCamadas.marcador_quadra) return;
        
        //console.log('🔍 Buscando marcadores para quarteirão:', nomeQuarteirao, '(tipo:', typeof nomeQuarteirao, ')');
        
        // Primeiro, oculta TODOS os marcadores
        arrayCamadas.marcador_quadra.forEach(marker => {
            marker.setMap(null);
        });
        
        // Se nomeQuarteirao for null/undefined, apenas oculta todos e retorna
        if (!nomeQuarteirao) return;
        
        let encontrados = 0;
        
        // Mostra apenas os marcadores do quarteirão especificado
        arrayCamadas.marcador_quadra.forEach(marker => {
            //console.log('🔍 Marcador:', marker.numeroMarcador, 'quarteirao:', marker.quarteirao, '(tipo:', typeof marker.quarteirao, ')');
            
            // Tenta comparação com string e número
            if (marker.quarteirao == nomeQuarteirao || marker.quarteirao === nomeQuarteirao) {
                marker.setMap(MapFramework.map);
                encontrados++;
                //console.log('✅ Marcador encontrado:', marker.numeroMarcador);
            }
        });
        
        //console.log('📊 Total encontrado:', encontrados);
    },

    inserirOrtofoto2: async function (local) {
        //pasta das fotos
        var url_ortofoto = `quadriculas/${local}/google_tiles`;

        var ortofotoLayer = new google.maps.ImageMapType({
            getTileUrl: (coord, zoom) => {
                var proj = this.map.getProjection();
                var tileSize = 256 / Math.pow(2, zoom);

                var tileBounds = new google.maps.LatLngBounds(
                    proj.fromPointToLatLng(new google.maps.Point(coord.x * tileSize, (coord.y + 1) * tileSize)),
                    proj.fromPointToLatLng(new google.maps.Point((coord.x + 1) * tileSize, coord.y * tileSize))
                );

                var invertedY = Math.pow(2, zoom) - coord.y - 1;

                var tileUrl = `${url_ortofoto}/${zoom}/${coord.x}/${invertedY}.png`;

                return tileUrl;
            },
            tileSize: new google.maps.Size(256, 256),
            maxZoom: 30,
            minZoom: 0,
            name: 'Ortofoto'
        });
        arrayCamadas.ortofoto.push(ortofotoLayer);
        this.map.overlayMapTypes.push(ortofotoLayer);
    },

    inserirOrtofoto: async function (dados) {
        const { Size } = await google.maps.importLibrary("core");

        for (let i = 1; i <= dados.ortofotos; i++) {
            const camada = new google.maps.ImageMapType({
                getTileUrl: function (coord, zoom) {
                    const proj = MapFramework.map.getProjection();
                    const tileSize = 256 / Math.pow(2, zoom);

                    // Determina os limites do tile atual
                    var tileBounds = new google.maps.LatLngBounds(
                        proj.fromPointToLatLng(new google.maps.Point(coord.x * tileSize, (coord.y + 1) * tileSize)),
                        proj.fromPointToLatLng(new google.maps.Point((coord.x + 1) * tileSize, coord.y * tileSize))
                    );

                    const invertedY = dados.inverter ? Math.pow(2, zoom) - coord.y - 1 : coord.y;

                    //return ortofotos/${dados.cidade}/${dados.card}/${i}/${zoom}/${coord.x}/${invertedY}.png;
                    return `tile.php ? cidade = ${dados.cidade}& card=${dados.card}& layer=${i}& z=${zoom}& x=${coord.x}& y=${invertedY}`;

                },

                tileSize: new Size(256, 256),
                maxZoom: 30,
                minZoom: 0,
                name: 'Ortofoto_' + i
            });

            MapFramework.map.overlayMapTypes.push(camada);
            MapFramework.ortofotos.push(camada);
        }
    },

    limparOrtofoto: function () {
        this.map.overlayMapTypes.clear();
        arrayCamadas.ortofoto = [];
        this.ortofotos = [];
    },

    alternarTipoMapa: function () {
        const tipoAtual = this.map.getMapTypeId();

        if (tipoAtual === 'roadmap') {
            this.map.setMapTypeId('satellite');
            $('#btnTipoMapa').text('Satélite');
        } else {
            this.map.setMapTypeId('roadmap');
            $('#btnTipoMapa').text('Mapa');
        }
    },

    ativarModoDesenho: function (tipo) {

        this.desenho.modo = tipo;
        this.desenho.tipoAtual = tipo;
        this.desenho.pontos = [];

        this.map.setOptions({ draggableCursor: 'crosshair' });

        // Remove listeners antigos
        if (this.desenho.listenerClick) this.desenho.listenerClick.remove();
        if (this.desenho.listenerRightClick) this.desenho.listenerRightClick.remove();

        // Clique esquerdo no mapa → adiciona ponto
        this.desenho.listenerClick = this.map.addListener('click', (e) => {
            const latLng = e.latLng;
            this.desenho.pontos.push(latLng);

            if (!this.desenho.temporario) {

                this.desenho.temporario = new google.maps.Polygon({
                    paths: this.desenho.pontos,
                    strokeColor: this.desenho.cor,
                    fillColor: this.desenho.cor,
                    strokeOpacity: 0.8,
                    strokeWeight: 1,
                    fillOpacity: 0.35,
                    editable: true,
                    map: this.map,
                    clickable: true
                });

                // Clique no vértice do polígono
                google.maps.event.addListener(this.desenho.temporario, 'rightclick', (e) => {
                    const path = this.desenho.temporario.getPath();
                    if (typeof e.vertex === 'number') {
                        if (path.getLength() <= 3) {
                            alert("Não é possível remover. O polígono precisa de pelo menos 3 pontos.");
                        } else {
                            this.cliqueEmVertice = true;
                            path.removeAt(e.vertex);
                        }
                    }
                });


            } else {
                this.desenho.temporario.setPath(this.desenho.pontos);
            }
        });

        // Listener no mapa (finalizar se não for vértice)
        this.desenho.listenerRightClick = this.map.addListener('rightclick', (e) => {
            // Esse clique não terá e.vertex, pois não é em vértice
            // Aqui é para finalizar o desenho
            const pathLength = this.desenho.temporario.getPath().getLength();
            if (pathLength < 3) {
                alert("Você precisa de pelo menos 3 pontos.");
                return;
            }

            this.abrirModalCamada();
        });
    },

    finalizarDesenho: function (opts = { descartarTemporario: false }) {

        // se for pra descartar, remove o temporário do mapa
        if (opts.descartarTemporario && this.desenho.temporario) {
            this.desenho.temporario.setMap(null);
            this.desenho.temporario = null;
        }

        // zera estado do desenho
        this.desenho.modo = null;
        this.desenho.tipoAtual = null;
        this.desenho.pontos = [];

        // Remove listeners também para modo marcador
        if (this.desenho.listenerClick) this.desenho.listenerClick.remove();
        if (this.desenho.listenerRightClick) this.desenho.listenerRightClick.remove();
        this.desenho.listenerClick = null;
        this.desenho.listenerRightClick = null;

        this.map.setOptions({ draggableCursor: 'default' });

        // Remove modo de inserção de marcador
        $('#inputLoteAtual').removeClass('modo-insercao');
        this.modoInsercaoMarcador = false;

        // UI
        this.fecharModalCamada();
        $('#inputNumeroQuadra').val(''); // se quiser limpar o input
        this.atualizarInteratividadeObjetos(true);

        // Oculta o botão de finalizar desenho
        $('#btnFinalizarDesenho').addClass('d-none');

        // Restaura o listener global de clique
        if (!this.listenerGlobalClick && this.map) {
            this.listenerGlobalClick = this.map.addListener('click', () => {
                this.desselecionarDesenho();
            });
        }
    },

    abrirModalCamada: function () {
        $('#modalCamada').fadeIn(150);
    },

    fecharModalCamada: function () {
        $('#modalCamada').fadeOut(150);
    },

    alternarVisibilidadeCamada: function (camada, visivel) {
        if (!arrayCamadas[camada]) return;

        // Tratamento especial para a camada de quarteirões
        if (camada === 'quarteirao') {
            arrayCamadas[camada].forEach(obj => {
                // Para quarteirões, temos polígonos, marcadores e polylines
                if (obj.polygon && typeof obj.polygon.setMap === 'function') {
                    obj.polygon.setMap(visivel ? this.map : null);
                }
                if (obj.marker && typeof obj.marker.setMap === 'function') {
                    obj.marker.setMap(visivel ? this.map : null);
                }
                if (obj.polyline && typeof obj.polyline.setMap === 'function') {
                    obj.polyline.setMap(visivel ? this.map : null);
                }
            });
        } else {
            // Para outras camadas, usa o comportamento padrão
            arrayCamadas[camada].forEach(obj => {
                if (typeof obj.setMap === 'function') {
                    obj.setMap(visivel ? this.map : null);
                }
            });
        }

        // Controle dos rótulos das quadriculas junto com a camada
        if (camada === 'quadriculas' && arrayCamadas.quadriculas_rotulos) {
            arrayCamadas.quadriculas_rotulos.forEach(obj => {
                if (typeof obj.setMap === 'function') {
                    obj.setMap(visivel ? this.map : null);
                }
            });
        }
    },

    atualizarInteratividadeObjetos: function (interativo) {
        const camadasInterativas = ['quadra', 'lote', 'quarteirao', 'semCamadas']; // adicione outras se necessário
        Object.keys(arrayCamadas).forEach(nomeCamada => {
            if (!camadasInterativas.includes(nomeCamada)) return;
            arrayCamadas[nomeCamada].forEach(obj => {
                if (obj instanceof google.maps.Polygon || obj instanceof google.maps.Polyline) {
                    obj.setOptions({ clickable: interativo });
                }
            });
        });
    },

    carregarDesenhosSalvos: function (cliente, ortofoto) {
        $.ajax({
            url: 'carregarDesenhos.php',
            method: 'GET',
            dataType: 'json',
            data: {
                cliente: cliente,
                ortofoto: ortofoto
            },
            success: function (response) {
                //console.log(response);
                if (response.status === 'sucesso') {

                    response.dados.forEach(desenho => {

                        const camadaNome = (desenho.camada || 'semCamadas').toLowerCase();
                        const tipo = desenho.tipo;
                        const coords = JSON.parse(desenho.coordenadas);
                        
                        var cores = "black";

                        if(desenho.cor_usuario) {
                            cores = desenho.cor_usuario;
                        } else {
                            cores = desenho.cor;
                        }

                        //console.log(coords)

                        if (!arrayCamadas[camadaNome]) {
                            arrayCamadas[camadaNome] = [];
                        }

                        let objeto = null;

                        if (tipo === 'poligono') {
                            objeto = new google.maps.Polygon({
                                paths: coords,
                                strokeColor: cores,
                                strokeOpacity: 1,
                                strokeWeight: 4,
                                fillColor: cores,
                                fillOpacity: 0.30,
                                editable: false,
                                map: MapFramework.map,
                                zIndex: 5
                            });

                            const coordsGeo = coords.map(p => [p.lng, p.lat]);
                            coordsGeo.push(coordsGeo[0]); // fecha o polígono

                            objeto.coordenadasGeoJSON = turf.polygon([coordsGeo]);
                            objeto.identificador = desenho.id;
                            objeto.id_desenho = desenho.id_desenho;
                            objeto.id_quadricula = desenho.quadricula;

                        } else if (tipo === 'polilinha') {
                            objeto = new google.maps.Polyline({
                                path: coords,
                                strokeColor: cores,
                                strokeOpacity: 1.0,
                                strokeWeight: 3,
                                editable: false,
                                map: MapFramework.map,
                                zIndex: 6
                            });

                            objeto.identificador = desenho.id;
                            objeto.id_desenho = desenho.id_desenho;
                            objeto.id_quadricula = desenho.quadricula;
                        }


                        if (objeto) {
                            objeto.corOriginal = cores;

                            const destino = arrayCamadas[camadaNome] ? camadaNome : 'semCamadas';

                            adicionarObjetoNaCamada(destino, objeto);

                            google.maps.event.addListener(objeto, 'click', () => {
                                MapFramework.selecionarDesenho(objeto);
                            });
                        }
                    });

                    console.log('Desenhos carregados.');
                } else {
                    console.warn('Erro ao carregar desenhos:', response.mensagem);
                }
            },
            error: function (xhr, status, error) {
                console.error('Erro na requisição de desenhos:', error);
            }
        });
    },

    carregarDesenhosPrefeitura: function (quadricula) {
        var camadaPref = "prefeitura";
        let destinoPref = arrayCamadas[camadaPref] ? camadaPref : 'semCamadas';

        function drawArc(map, centerLatLng, radiusMeters, startAngle, endAngle, color) {
            let points = [];
            let step = 2; // passo em graus

            // Normaliza ângulos
            startAngle = (startAngle + 360) % 360;
            endAngle = (endAngle + 360) % 360;

            // Se o arco atravessa o 360°, ajusta o endAngle
            if (endAngle < startAngle) {
                endAngle += 360;
            }

            for (let angle = startAngle; angle <= endAngle; angle += step) {
                let latLng = google.maps.geometry.spherical.computeOffset(centerLatLng, radiusMeters, angle);
                points.push(latLng);
            }

            // Desenha no mapa
            var arcPref = new google.maps.Polyline({
                path: points,
                strokeColor: color,
                strokeOpacity: 1.0,
                strokeWeight: 3,
                zIndex: 1
            });

            adicionarObjetoNaCamada(destinoPref, arcPref);

        }

        //console.log(quadricula)
        $.ajax({
            url: `cartografia_prefeitura/${quadricula}.json`,
            method: 'GET',
            dataType: 'json',
            success: function (response) {

                //console.log(response)
                Object.values(response.plot).forEach(desenho => {
                    //console.log(desenho)
                    if (desenho.type == "arc") {
                        let centerArc = utmToLatLng(desenho.center[0], desenho.center[1]);
                        drawArc(map, centerArc, desenho.radius, desenho.start_angle, desenho.end_angle, "#ff0000ff");

                    } else if (desenho.type == "line") {

                        let coordsLine = utmCoordsToLatLngList(desenho.coords);

                        var prefLine = new google.maps.Polyline({
                            path: coordsLine,
                            strokeColor: "#ff0000ff",
                            strokeOpacity: 1.0,
                            strokeWeight: 3,
                            editable: false,
                            clickable: false,
                            zIndex: 1
                        });

                        adicionarObjetoNaCamada(destinoPref, prefLine);

                    } else if (desenho.type == "polyline") {

                        let coordsLine = utmCoordsToLatLngList(desenho.coords);

                        var prefPolyline = new google.maps.Polyline({
                            path: coordsLine,
                            strokeColor: "#ff0000ff",
                            strokeOpacity: 1.0,
                            strokeWeight: 3,
                            editable: false,
                            clickable: false,
                            zIndex: 1
                        });

                        adicionarObjetoNaCamada(destinoPref, prefPolyline);

                    } else if (desenho.type == "text") {

                        //console.log(desenho)
                        const el = document.createElement('div');
                        el.className = 'map-label-text';
                        el.textContent = desenho.value;

                        let centerMarker = utmToLatLng(desenho.point[0], desenho.point[1]);

                        var prefMarker = new google.maps.marker.AdvancedMarkerElement({
                            position: centerMarker,   // {lat: ..., lng: ...} em WGS84
                            content: el,                // só o HTML; sem PinElement => sem pin
                            gmpClickable: false,        // não clicável
                            zIndex: 1
                        });

                        adicionarObjetoNaCamada(destinoPref, prefMarker);

                    } else if (desenho.type == "circle") {

                        let centerCircle = utmToLatLng(desenho.center[0], desenho.center[1]);

                        var prefCircle = new google.maps.Circle({
                            center: centerCircle, // centro do círculo
                            radius: desenho.radius, // raio em metros
                            strokeColor: "#ff0000ff",
                            strokeOpacity: 1.0,
                            strokeWeight: 3,
                            fillColor: "#ff0000ff",
                            fillOpacity: 0.35,
                            editable: false,
                            clickable: false,
                            zIndex: 1
                        });

                        adicionarObjetoNaCamada(destinoPref, prefCircle);

                    } else {
                        //console.log(desenho)
                    }

                });


            },
            error: function (xhr, status, error) {
                console.error('Erro na requisição de desenhos:', error);
            }
        });
    },

    iniciarDesenhoQuadra: function () {
        if (this.listenerGlobalClick) { this.listenerGlobalClick.remove(); this.listenerGlobalClick = null; }
        // Limpa seleção antes de começar novo desenho
        this.desselecionarDesenho();

        this.atualizarInteratividadeObjetos(false);

        this.desenho.modo = 'poligono';
        this.desenho.tipoAtual = 'poligono';
        this.map.setOptions({ draggableCursor: 'crosshair' });

        // Mostra o botão de finalizar desenho
        $('#btnFinalizarDesenho').removeClass('d-none');

        if (this.desenho.listenerClick) this.desenho.listenerClick.remove();
        if (this.desenho.listenerRightClick) this.desenho.listenerRightClick.remove();

        this.desenho.listenerClick = this.map.addListener('click', (e) => {

            const ponto = e.latLng;

            if (!this.desenho.temporario) {
                this.desenho.temporario = new google.maps.Polygon({
                    paths: [ponto],
                    strokeColor: "blue",
                    strokeOpacity: 0.8,
                    strokeWeight: 2,
                    fillColor: "blue",
                    fillOpacity: 0.35,
                    editable: true,
                    map: this.map,
                    clickable: false,
                    zIndex: 2
                });

                this.desenho.cor = "blue";
                google.maps.event.addListener(this.desenho.temporario, 'rightclick', (e) => {
                    const path = this.desenho.temporario.getPath();
                    if (typeof e.vertex === 'number') {
                        if (path.getLength() > 3) {
                            this.cliqueEmVertice = true;
                            path.removeAt(e.vertex);
                        } else {
                            alert("Polígono precisa de pelo menos 3 pontos.");
                        }
                    }
                });

            } else {
                this.desenho.temporario.getPath().push(ponto);
            }
        });

        this.desenho.listenerRightClick = this.map.addListener('rightclick', (e) => {
            if (this.cliqueEmVertice) {
                this.cliqueEmVertice = false;
                return;
            }

            if (!this.desenho.temporario) return;

            const pathLength = this.desenho.temporario.getPath().getLength();
            if (pathLength < 3) {
                alert("Você precisa de pelo menos 3 pontos.");
                return;
            }

            this.salvarDesenho('Quadra', 0);
        });
    },

    iniciarDesenhoLote: function () {
        if (this.listenerGlobalClick) { this.listenerGlobalClick.remove(); this.listenerGlobalClick = null; }
        // Limpa seleção antes de começar novo desenho
        this.desselecionarDesenho();

        this.atualizarInteratividadeObjetos(false);

        this.desenho.modo = 'polilinha';
        this.desenho.tipoAtual = 'polilinha';
        this.map.setOptions({ draggableCursor: 'crosshair' });
        //this.desenho.pontos = [];

        // Mostra o botão de finalizar desenho
        $('#btnFinalizarDesenho').removeClass('d-none');

        if (this.desenho.listenerClick) this.desenho.listenerClick.remove();
        if (this.desenho.listenerRightClick) this.desenho.listenerRightClick.remove();

        this.desenho.listenerClick = this.map.addListener('click', (e) => {

            const ponto = e.latLng;

            if (!this.desenho.temporario) {
                //this.desenho.pontos = [ponto];
                this.desenho.temporario = new google.maps.Polyline({
                    path: [ponto],//this.desenho.pontos,
                    strokeColor: "red",
                    strokeOpacity: 1.0,
                    strokeWeight: 3,
                    editable: true,
                    map: this.map,
                    zIndex: 3
                });


                google.maps.event.addListener(this.desenho.temporario, 'rightclick', (e) => {
                    const path = this.desenho.temporario.getPath();
                    if (typeof e.vertex === 'number') {
                        if (path.getLength() > 2) {
                            this.cliqueEmVertice = true;
                            path.removeAt(e.vertex);
                        } else {
                            alert("A linha deve ter pelo menos 2 pontos.");
                        }
                    }
                });

            } else {
                //this.desenho.pontos.push(ponto);
                //this.desenho.temporario.setPath(this.desenho.pontos);
                this.desenho.temporario.getPath().push(ponto);
            }
        });

        this.desenho.listenerRightClick = this.map.addListener('rightclick', (e) => {
            if (this.cliqueEmVertice) {
                this.cliqueEmVertice = false;
                return;
            }

            if (!this.desenho.temporario) return;

            const path = this.desenho.temporario.getPath();
            if (path.getLength() < 2) {
                alert("A linha deve ter pelo menos 2 pontos.");
                return;
            }

            // Verifica se a linha está sobre alguma quadra
            const resultado = this.verificarLinhaDentroDeQuadra(this.desenho.temporario, 3);

            //console.log(resultado)
            // Aplica a cor baseada no resultado
            this.desenho.temporario.setOptions({
                strokeColor: resultado.cor,
                editable: false
            });

            this.desenho.cor = resultado.cor;
            //seta o id_desenho
            this.desenho.temporario.id_desenho = resultado.identificador;

            // Define o identificador se encontrou uma quadra
            this.desenho.temporario.identificador = resultado.identificador;

            // Salva o desenho
            this.salvarDesenho("lote");
        });
    },

    salvarDesenho: function (camada, identificador = '') {
        const path = this.desenho.temporario.getPath();
        const coordenadas = [];

        for (let i = 0; i < path.getLength(); i++) {
            const ponto = path.getAt(i);
            coordenadas.push({ lat: ponto.lat(), lng: ponto.lng() });
        }

        const coordenadasStr = JSON.stringify(coordenadas);
        const tipo = this.desenho.tipoAtual; // <- captura tipo aqui
        const cor = this.desenho.cor || '#0000FF'; // Cor padrão se não estiver definida

        if (tipo != 'polilinha') {
            this.desenho.temporario.id_desenho = this.desenho.temporario.id_desenho || identificador;
        }

        $.ajax({
            url: 'salvarDesenho.php',
            method: 'POST',
            data: {
                coordenadas: coordenadasStr,
                camada: camada,
                //cliente: dadosOrto[0].cidade,
                //ortofoto: dadosOrto[0].card,
                ortofoto: dadosOrto[0]['quadricula'],
                tipo: tipo,
                cor: cor,
                identificador: this.desenho.temporario.id_desenho
            },
            success: (response) => {

                try {
                    const resultado = response;
                    if (resultado.status === 'sucesso') {
                        const objetoSalvo = this.desenho.temporario;

                        objetoSalvo.setOptions({
                            strokeColor: cor || '#0000FF',
                            fillColor: tipo === 'poligono' ? (cor || '#0000FF') : undefined,
                            clickable: false,
                            editable: false
                        });

                        objetoSalvo.corOriginal = cor;

                        if (tipo === 'poligono') {
                            let coordsPego = JSON.parse(coordenadasStr);
                            const coordsGeo1 = coordsPego.map(p => [p.lng, p.lat]);
                            coordsGeo1.push(coordsGeo1[0]); // fecha o polígono
                            objetoSalvo.coordenadasGeoJSON = turf.polygon([coordsGeo1]);
                        }

                        objetoSalvo.identificador = parseInt(response.id);

                        google.maps.event.addListener(objetoSalvo, 'click', () => {
                            MapFramework.selecionarDesenho(objetoSalvo);
                        });

                        adicionarObjetoNaCamada(camada, objetoSalvo);

                        this.desenho.temporario = null;
                        this.desenho.pontos = [];

                        // Para lotes, mantém o modo de desenho ativo para permitir desenhar mais lotes
                        if (this.desenho.tipoAtual === 'polilinha') {
                            // Limpa o temporário mas mantém o modo de desenho
                            this.desenho.temporario = null;
                            this.desenho.pontos = [];
                            // Não chama finalizarDesenho() para manter o modo ativo
                        }
                    } else {
                        alert('Erro ao salvar: ' + resultado.mensagem);
                        this.desenho.temporario.setMap(null);
                        this.desenho.temporario = null;
                        this.desenho.pontos = [];
                    }
                } catch (e) {
                    alert('Erro inesperado: ' + e.message + '\n\nVerifique o console para mais detalhes.');
                    this.desenho.temporario.setMap(null);
                    this.desenho.temporario = null;
                    this.desenho.pontos = [];
                }

                //console.log(arrayCamadas)
            },
            error: () => {
                alert('Erro na comunicação com o servidor.');
                this.desenho.temporario.setMap(null);
                this.desenho.temporario = null;
                this.desenho.pontos = [];
            }
        });
    },

    verificarLinhaDentroDeQuadra: function (linhaGoogleMaps, toleranciaMetros = 3) {
        const path = linhaGoogleMaps.getPath();
        const pontosLinha = [];

        for (let i = 0; i < path.getLength(); i++) {
            pontosLinha.push([path.getAt(i).lng(), path.getAt(i).lat()]);
        }

        const linhaGeoJSON = turf.lineString(pontosLinha);

        const quadras = arrayCamadas["quadra"] || [];

        console.log(" Verificando linha sobre quadras...");

        const poligonosDentro = [];

        for (let i = 0; i < quadras.length; i++) {
            const poligono = quadras[i];

            //console.log(poligono)

            if (!poligono.coordenadasGeoJSON || !poligono.identificador) {
                console.warn("Polígono inválido (sem geojson ou identificador):", poligono);
                continue;
            }

            const buffer = turf.buffer(poligono.coordenadasGeoJSON, toleranciaMetros, { units: 'meters' });

            let dentro = true;

            for (let j = 0; j < pontosLinha.length; j++) {
                const ponto = turf.point(pontosLinha[j]);
                if (!turf.booleanPointInPolygon(ponto, buffer)) {
                    dentro = false;
                    break;
                }
            }

            if (dentro) {
                poligonosDentro.push(poligono);
            }
        }

        if (poligonosDentro.length === 1) {
            console.log(" Linha está dentro da quadra:", poligonosDentro[0].identificador);
            return {
                encontrado: true,
                identificador: poligonosDentro[0].identificador,
                cor: 'lime'
            };
        } else {
            console.warn(" Linha está fora ou sobre múltiplas quadras.");
            return {
                encontrado: false,
                identificador: '',
                cor: '#FF0000'
            };
        }
    },

    excluirDesenhoSelecionado: function (cliente, ortofoto) {
        if (!this.selecionado) return;

        const objeto = this.selecionado;
        const tipo = objeto instanceof google.maps.Polygon ? 'poligono' : 'linha';
        const identificador = objeto.identificador || null;

        if (tipo === 'linha') {
            // Remove do mapa e da camada
            objeto.setMap(null);
            this.removerObjetoDasCamadas(objeto);

            // Remove do banco
            $.post('excluirDesenho.php', {
                cliente: cliente,
                ortofoto: ortofoto,
                identificador: identificador,
                tipo: 'linha'
            }, function (response) {
                console.log('Resposta ao excluir linha:', response);
            });

            this.desselecionarDesenho();

        } else if (tipo === 'poligono') {
            if (!identificador) {
                alert("Este polígono não possui um identificador válido para exclusão.");
                return;
            }

            const confirmar = confirm("Tem certeza que deseja excluir esta quadra?\n\nTodas as linhas associadas a ela também serão removidas!");
            if (!confirmar) return;

            // 1. Apaga visualmente o polígono
            objeto.setMap(null);
            this.removerObjetoDasCamadas(objeto);

            // 2. Apaga todas as linhas com o mesmo identificador
            Object.keys(arrayCamadas).forEach(nomeCamada => {
                const novaLista = [];

                arrayCamadas[nomeCamada].forEach(obj => {
                    if (obj instanceof google.maps.Polyline && obj.identificador === identificador) {
                        obj.setMap(null); // remove do mapa
                        // não adiciona à nova lista → será removido do array
                    } else {
                        novaLista.push(obj); // mantém
                    }
                });

                arrayCamadas[nomeCamada] = novaLista; // substitui o array da camada
            });

            // 3. Exclui do banco: polígono + linhas associadas
            $.post('excluirDesenho.php', {
                cliente: cliente,
                ortofoto: ortofoto,
                identificador: identificador,
                tipo: 'poligono'
            }, function (response) {
                console.log('Resposta ao excluir polígono + linhas:', response);
            });

            this.desselecionarDesenho();
        }
    },

    excluirDesenhoSelecionado2: function (cliente, ortofoto) {
        if (!this.selecionado) return;

        const objeto = this.selecionado;
        const tipo = objeto instanceof google.maps.Polygon ? 'poligono' : 'polilinha';
        //console.log(tipo)   
        const identificador = objeto.identificador || null;

        if (tipo === 'polilinha') {
            // Remove do mapa e da camada
            objeto.setMap(null);
            this.removerObjetoDasCamadas(objeto);

            // Remove do banco
            $.post('excluirDesenho.php', {
                cliente: cliente,
                ortofoto: ortofoto,
                identificador: identificador,
                tipo: 'polilinha'
            }, function (response) {
                console.log('Resposta ao excluir linha:', response);
            });

            this.desselecionarDesenho();

        } else if (tipo === 'poligono') {
            if (!identificador) {
                alert("Este polígono não possui um identificador válido para exclusão.");
                return;
            }

            const confirmar = confirm(`Tem certeza que deseja excluir esta quadra ${identificador}?\n\nTodas as linhas associadas a ela também serão removidas!`);
            if (!confirmar) return;

            // 1. Apaga visualmente o polígono
            objeto.setMap(null);
            this.removerObjetoDasCamadas(objeto);

            // 2. Apaga todas as linhas que pertencem a esta quadra
            console.log('Procurando linhas que pertencem à quadra:', identificador);

            arrayCamadas['lote']
                .filter(lote => parseInt(lote.id_desenho) === identificador)
                .forEach(lote => {
                    lote.setMap(null);
                    this.removerObjetoDasCamadas(lote);
                });

            console.log("preparando para excluir...")

            // 3. Exclui do banco: polígono + linhas associadas
            $.post('excluirDesenho.php', {
                cliente: cliente,
                ortofoto: ortofoto,
                identificador: identificador,
                tipo: 'poligono'
            }, function (response) {
                console.log('Resposta ao excluir polígono + linhas:', response);
                // Recarrega a página após a exclusão:
                //location.reload();
            });

            console.log("Terminou.")

            this.desselecionarDesenho();
        }
    },

    removerObjetoDasCamadas: function (objeto) {
        Object.keys(arrayCamadas).forEach(camada => {
            const lista = arrayCamadas[camada];
            const index = lista.indexOf(objeto);
            if (index > -1) {
                lista.splice(index, 1);
            }
        });
    },

    // Adiciona no final do objeto MapFramework:
    carregarLimiteKML: function (urlKML = 'limite_paulinia.kml') {
        if (!window.toGeoJSON) {
            alert('toGeoJSON não está carregado!');
            return;
        }
        if (!this.map) {
            alert('O mapa ainda não foi inicializado!');
            return;
        }
        // Garante que a camada existe
        if (!arrayCamadas.limite) arrayCamadas.limite = [];
        // Remove linhas antigas
        arrayCamadas.limite.forEach(obj => { if (obj.setMap) obj.setMap(null); });
        arrayCamadas.limite = [];
        // Carrega o KML
        fetch(urlKML)
            .then(res => res.text())
            .then(kmlText => {
                const parser = new DOMParser();
                const kml = parser.parseFromString(kmlText, 'text/xml');
                const geojson = toGeoJSON.kml(kml);
                geojson.features.forEach(f => {
                    if (f.geometry.type === 'LineString' || f.geometry.type === 'MultiLineString' || f.geometry.type === 'Polygon' || f.geometry.type === 'MultiPolygon') {
                        let paths = [];
                        if (f.geometry.type === 'LineString') {
                            paths = f.geometry.coordinates.map(([lng, lat]) => ({ lat, lng }));
                        } else if (f.geometry.type === 'MultiLineString') {
                            f.geometry.coordinates.forEach(line => {
                                paths = paths.concat(line.map(([lng, lat]) => ({ lat, lng })));
                            });
                        } else if (f.geometry.type === 'Polygon') {
                            // Para Polygon, desenha o contorno
                            paths = f.geometry.coordinates[0].map(([lng, lat]) => ({ lat, lng }));
                        } else if (f.geometry.type === 'MultiPolygon') {
                            f.geometry.coordinates.forEach(poly => {
                                paths = paths.concat(poly[0].map(([lng, lat]) => ({ lat, lng })));
                            });
                        }
                        const polyline = new google.maps.Polyline({
                            path: paths,
                            strokeColor: 'red',
                            clickable: false,
                            strokeOpacity: 1.0,
                            strokeWeight: 4,
                            map: this.map,
                            zIndex: 10
                        });
                        arrayCamadas.limite.push(polyline);
                    }
                });
            });
    },

    carregarQuadriculasKML: function (urlKML = 'quadriculas_paulinia.kml') {
        if (!window.toGeoJSON) {
            alert('toGeoJSON não está carregado!');
            return;
        }
        if (!this.map) {
            alert('O mapa ainda não foi inicializado!');
            return;
        }
        if (!arrayCamadas.quadriculas) arrayCamadas.quadriculas = [];
        if (!arrayCamadas.quadriculas_rotulos) arrayCamadas.quadriculas_rotulos = [];
        // Remove desenhos antigos
        arrayCamadas.quadriculas.forEach(obj => { if (obj.setMap) obj.setMap(null); });
        arrayCamadas.quadriculas = [];
        // Remove rótulos antigos
        arrayCamadas.quadriculas_rotulos.forEach(obj => { if (obj.setMap) obj.setMap(null); });
        arrayCamadas.quadriculas_rotulos = [];
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
                            strokeColor: 'black',
                            strokeOpacity: 1.0,
                            strokeWeight: 1,
                            fillColor: 'black',
                            fillOpacity: 0,
                            clickable: false,
                            map: this.map,
                            zIndex: 9
                        });
                        // Calcular centro do polígono
                        if (paths.length > 0) {
                            let latSum = 0, lngSum = 0;
                            paths.forEach(p => { latSum += p.lat; lngSum += p.lng; });
                            centro = { lat: latSum / paths.length, lng: lngSum / paths.length };
                        }
                    } else if (f.geometry.type === 'LineString' || f.geometry.type === 'MultiLineString') {
                        let paths = [];
                        if (f.geometry.type === 'LineString') {
                            paths = f.geometry.coordinates.map(([lng, lat]) => ({ lat, lng }));
                        } else if (f.geometry.type === 'MultiLineString') {
                            f.geometry.coordinates.forEach(line => {
                                paths = paths.concat(line.map(([lng, lat]) => ({ lat, lng })));
                            });
                        }
                        obj = new google.maps.Polyline({
                            path: paths,
                            strokeColor: 'black',
                            strokeOpacity: 1.0,
                            strokeWeight: 1,
                            clickable: false,
                            map: this.map,
                            zIndex: 9
                        });
                        // Centro da linha: ponto do meio
                        if (paths.length > 0) {
                            centro = paths[Math.floor(paths.length / 2)];
                        }
                    }
                    if (obj) arrayCamadas.quadriculas.push(obj);
                    // Adiciona rótulo se houver nome e centro
                    if (f.properties && f.properties.name && centro) {
                        const labelDiv = document.createElement('div');
                        labelDiv.style.fontSize = '16px';
                        labelDiv.style.color = '#000';
                        labelDiv.style.background = 'rgba(255,255,255,0.7)';
                        labelDiv.style.padding = '2px 6px';
                        labelDiv.style.borderRadius = '6px';
                        labelDiv.style.border = '1px solid #888';
                        labelDiv.innerText = f.properties.name;
                        let marker;
                        if (google.maps.marker && google.maps.marker.AdvancedMarkerElement) {
                            marker = new google.maps.marker.AdvancedMarkerElement({
                                position: centro,
                                content: labelDiv,
                                gmpClickable: false,
                                zIndex: 20
                            });
                            marker.setMap(this.map);
                        } else {
                            marker = new google.maps.Marker({
                                position: centro,
                                map: this.map,
                                icon: {
                                    url: 'data:image/svg+xml;utf-8,<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>',
                                    labelOrigin: new google.maps.Point(0, 0)
                                },
                                label: {
                                    text: f.properties.name,
                                    color: '#000',
                                    fontSize: '13px'
                                },
                                zIndex: 20
                            });
                        }
                        arrayCamadas.quadriculas_rotulos.push(marker);
                    }
                });
            });
    },

    carregarControleNavegacaoQuadriculas: function (quadricula) {

        $.ajax({
            url: 'mapeamento_quadriculas.json',
            method: 'GET',
            dataType: 'json',
            success: function (response) {
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
                MapFramework.carregarGradeExpandida(response, quadricula);

            },
            error: function (xhr, status, error) {
                console.error('Erro ao carregar mapeamento de quadriculas:', error);
            }

        });

    },

    carregarGradeExpandida: function (todasQuadriculas, quadriculaAtual) {
        try {
            // Encontra os limites da grade
            let minCol = Infinity, maxCol = -Infinity, minLin = Infinity, maxLin = -Infinity;

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
                                window.location.href = `index_2.php?quadricula=${quadriculaEncontrada.centro}`;
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
    },

    extrairCoordenadasQuadricula: function (quadricula) {
        // Extrai as coordenadas da quadrícula (ex: C10 -> col=2, lin=10)
        const match = quadricula.match(/^([A-Z])(\d+)$/);
        if (match) {
            const col = match[1].charCodeAt(0) - 65; // A=0, B=1, C=2, etc.
            const lin = parseInt(match[2]);
            return { col, lin };
        }
        return null;
    },

    navegarQuadricula: function (btn) {
        const quadricula = btn.getAttribute('data-quadricula');
        //console.log(quadricula);
        window.location.href = `index_2.php?quadricula=${quadricula}`;
    },

    controlarOpacidade: function (value) {

        arrayCamadas['quadra'].forEach(pol => {
            pol.setOptions({
                fillOpacity: value
            });
        });
    },

    carregaQuarteiroes: function (quadricula) {
        // Verifica se a quadrícula foi fornecida
        if (!quadricula || quadricula.trim() === '') {
            return Promise.resolve();
        }

        // Limpa quarteirões anteriores se existirem
        if (arrayCamadas['quarteirao'] && arrayCamadas['quarteirao'].length > 0) {
            arrayCamadas['quarteirao'].forEach(obj => {
                if (obj.polygon) obj.polygon.setMap(null);
                if (obj.marker) obj.marker.setMap(null);
            });
            arrayCamadas['quarteirao'] = [];
        }

        // Inicializa o array se não existir
        if (!arrayCamadas['quarteirao']) {
            arrayCamadas['quarteirao'] = [];
        }

        // Limpa o array de números de quarteirões
        this.quarteiroesNumeros = [];

        // Retorna uma Promise para permitir o uso de await
        return new Promise((resolve, reject) => {
            // Faz a requisição AJAX para carregar o JSON dos quarteirões
            $.ajax({
            url: `correspondencias_quarteiroes/correspondencia_${quadricula}_quarteiroes.json`,
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                // Carregando quarteirões da quadrícula

                if (response.features && response.features.length > 0) {
                    response.features.forEach((feature, index) => {
                        try {
                            const geometry = feature.geometry;
                            const properties = feature.properties;

                            if (geometry.type === 'Polygon' && geometry.coordinates) {
                                // Converte as coordenadas para o formato do Google Maps
                                const path = geometry.coordinates[0].map(coord => {
                                    return {
                                        lat: coord[1],
                                        lng: coord[0]
                                    };
                                });

                                // Cria o polígono do quarteirão
                                const polygon = new google.maps.Polygon({
                                    paths: path,
                                    strokeColor: 'white',
                                    strokeOpacity: 1,
                                    strokeWeight: 3,
                                    fillColor: 'white',
                                    fillOpacity: 0,
                                    clickable: false,
                                    zIndex: 10
                                });

                                // Calcula o centro do polígono para o marcador
                                const bounds = new google.maps.LatLngBounds();
                                path.forEach(point => bounds.extend(point));
                                const center = bounds.getCenter();

                                MapFramework.quarteiroesNumeros.push(properties.impreciso_name);

                                // Cria o marcador personalizado com o rótulo
                                const markerElement = document.createElement('div');
                                markerElement.className = 'map-label-text';
                                markerElement.textContent = properties.impreciso_name || 'N/A';
                                markerElement.style.fontSize = '12px';
                                markerElement.style.fontWeight = 'bold';
                                markerElement.style.color = '#000';
                                markerElement.style.textShadow = 'none';
                                markerElement.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
                                markerElement.style.padding = '2px 6px';
                                markerElement.style.borderRadius = '3px';
                                markerElement.style.border = '1px solid #000';

                                // Cria o marcador avançado com o rótulo
                                const marker = new google.maps.marker.AdvancedMarkerElement({
                                    position: center,
                                    content: markerElement,
                                    gmpClickable: false,
                                    zIndex: 11
                                });

                                // Armazena tanto o polígono quanto o marcador no array
                                const quarteiraoObj = {
                                    polygon: polygon,
                                    marker: marker,
                                    properties: properties,
                                    id: properties.id,
                                    quadricula: properties.quadricula
                                };

                                arrayCamadas['quarteirao'].push(quarteiraoObj);

                            }

                        } catch (error) {
                            // Erro ao processar feature
                        }
                    });

                    // Quarteirões carregados com sucesso
                    resolve();
                } else {
                    // Nenhum quarteirão encontrado para a quadrícula
                    resolve();
                }
            },
            error: function (xhr, status, error) {
                // Erro ao carregar quarteirões
                console.error('Erro ao carregar quarteirões:', error);
                reject(error);
            }
        });
        });
    },

    carregarPlanilha: function () {
        const self = this; // Salva referência ao MapFramework
        $.ajax({
            url: 'carregarPlanilha.php',
            method: 'POST',
            async: false,
            data: {
                quarteiroes: this.quarteiroesNumeros
            },
            dataType: 'json',
            success: function (response) {
                //console.log('Dados carregados da planilha:', response);
                self.dadosMoradores = response;
                
            },
            error: function (xhr, status, error) {
                console.error('Erro ao carregar dados da planilha:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
            }
        });
    },

    // Função simples para obter elementos do quarteirão pelo ID
    obterQuarteiraoPorId: function (id) {
        if (!arrayCamadas['quarteirao']) return null;

        return arrayCamadas['quarteirao'].find(quarteirao => quarteirao.id === id);
    },

    inserirMarcadorPersonalizado: function (latLng) {
        // Verifica se o clique está dentro de alguma quadra
        let quadraEncontrada = null;
        let quadras = arrayCamadas["quadra"] || [];
        for (let i = 0; i < quadras.length; i++) {
            let poligono = quadras[i];
            if (poligono.coordenadasGeoJSON && turf.booleanPointInPolygon(
                turf.point([latLng.lng(), latLng.lat()]),
                poligono.coordenadasGeoJSON
            )) {
                quadraEncontrada = poligono;
                break;
            }
        }
        
        if (!quadraEncontrada) {
            alert("Clique dentro de uma quadra para inserir o marcador.");
            return;
        }
        // Descobre o id da quadra
        let idQuadra = quadraEncontrada.identificador;
        if (!idQuadra) {
            alert("Quadra sem identificador válido.");
            return;
        }
        
        // Pega o número do lote do input em vez de usar sequência automática
        let numeroLote = $('#inputLoteAtual').val().trim();
        if (!numeroLote) {
            alert("Por favor, informe o número do lote no campo correspondente.");
            return;
        }
        
        // Verifica se o marcador corresponde ao lote selecionado da divCadastro3
        loteElementoSelecionado = $('.opcao-lote.selected');
        let correspondeAoLoteSelecionado = false;
        
        console.log('=== VERIFICAÇÃO DE CORRESPONDÊNCIA INICIAL ===');
        console.log('numeroLote do input:', numeroLote);
        console.log('loteElementoSelecionado encontrado:', loteElementoSelecionado.length > 0);
        
        if (loteElementoSelecionado.length > 0) {
            const loteTexto = loteElementoSelecionado.find('.lote-texto').text();
            console.log('loteTexto:', loteTexto);
            
            const match = loteTexto.match(/Lote: ([^|]+)/);
            console.log('match encontrado:', match);
            
            if (match) {
                const numeroLoteSelecionado = match[1].trim();
                console.log('numeroLoteSelecionado:', numeroLoteSelecionado);
                console.log('Comparação:', numeroLote, '===', numeroLoteSelecionado);
                
                correspondeAoLoteSelecionado = (numeroLote === numeroLoteSelecionado);
                console.log('correspondeAoLoteSelecionado:', correspondeAoLoteSelecionado);
            } else {
                console.log('ERRO: Não conseguiu extrair número do lote do texto');
            }
        } else {
            console.log('ERRO: Nenhum lote selecionado encontrado');
        }

        // Define a cor baseada se corresponde ao lote da lista
        const corMarcador = correspondeAoLoteSelecionado ? '#32CD32' : 'red'; // Verde limão ou vermelho

        // Cria HTML do marcador
        let el = document.createElement('div');
        el.style.height = '32px';
        el.style.padding = '0 10px';
        el.style.background = corMarcador;
        el.style.borderRadius = '10px';
        el.style.display = 'flex';
        el.style.alignItems = 'center';
        el.style.justifyContent = 'center';
        el.style.color = 'white';
        el.style.fontWeight = 'bold';
        el.style.fontSize = '16px';
        el.style.border = '2px solid #fff';
        el.style.transform = 'translate(0, 15px)'; // Centraliza o marcador no ponto clicado
        el.style.position = 'relative';
        el.style.cursor = 'pointer';
        el.className = 'marcador-personalizado';
        el.textContent = numeroLote;
        
        // Cria marcador avançado
        let marker = new google.maps.marker.AdvancedMarkerElement({
            position: latLng,
            content: el,
            gmpClickable: true
        });
        marker.setMap(this.map);

        marker.idQuadra = idQuadra;
        marker.numeroMarcador = numeroLote;
        
        // Obtém a quadra do lote selecionado para que verificarLoteJaInserido funcione
        loteElementoSelecionado = $('.opcao-lote.selected');
        const quadraSelecionada = loteElementoSelecionado.data('quadra') || idQuadra;
        marker.quadra = quadraSelecionada;
        
        // Adiciona a propriedade quarteirao ao marcador
        marker.quarteirao = quarteiraoAtualSelecionado;
        
        console.log('=== PROPRIEDADES DO MARCADOR ===');
        console.log('marker.quadra:', marker.quadra);
        console.log('marker.numeroMarcador:', marker.numeroMarcador);
        console.log('marker.quarteirao:', marker.quarteirao);
        console.log('quarteiraoAtualSelecionado:', quarteiraoAtualSelecionado);
        
        // Adiciona evento de clique para mostrar tooltip
        el.addEventListener('click', function(event) {
            event.stopPropagation();
            mostrarTooltipMarcador(marker, event);
        });
        
        if (!arrayCamadas["marcador_quadra"]) arrayCamadas["marcador_quadra"] = [];
        arrayCamadas["marcador_quadra"].push(marker);
        adicionarObjetoNaCamada("marcador_quadra", marker);
        // Salva no banco e passa a informação se corresponde ao lote selecionado
        this.salvarMarcadorNoBanco(latLng, idQuadra, numeroLote, marker, correspondeAoLoteSelecionado, corMarcador);
    },

    salvarMarcadorNoBanco: function(latLng, idQuadra, numeroMarcador, marcadorElement, correspondeAoLoteSelecionado, corMarcador) {
        // Obtém informações do lote selecionado
        loteElementoSelecionado = $('.opcao-lote.selected');
        const quadraSelecionada = loteElementoSelecionado.data('quadra') || idQuadra;
        
        //console.log('Dados para salvar marcador:');
        //console.log('- Quarteirão:', quarteiraoAtualSelecionado);
        //console.log('- Quadra:', quadraSelecionada);
        //console.log('- Lote:', numeroMarcador);
        
        $.ajax({
            url: 'salvarMarcador.php',
            method: 'POST',
            data: {
                lat: latLng.lat(),
                lng: latLng.lng(),
                id_quadra: idQuadra,
                numero: numeroMarcador,
                ortofoto: dadosOrto[0]['quadricula'],
                tipo: numeroMarcador, // <- aqui vai o número do lote do input!
                quarteirao: quarteiraoAtualSelecionado || '',
                quadra: quadraSelecionada || idQuadra,
                cor: corMarcador
            },
            success: function(response) {
                try {
                    let resultado = response;
                    if (typeof response === 'string') {
                        resultado = JSON.parse(response);
                    }
                    
                    if (resultado.status === 'sucesso' && resultado.id) {
                        // Guarda o ID do banco no marcador para poder deletar depois
                        marcadorElement.identificadorBanco = resultado.id;
                        //console.log('Marcador salvo com sucesso, ID:', resultado.id);
                        
                        // Se o input corresponde ao lote selecionado da divCadastro3:
                        // - Marca como inserido (verde/travado)
                        // - Pula para o próximo
                        console.log('=== VERIFICAÇÃO DE CORRESPONDÊNCIA ===');
                        console.log('correspondeAoLoteSelecionado:', correspondeAoLoteSelecionado);
                        console.log('numeroMarcador:', numeroMarcador);
                        
                        if (correspondeAoLoteSelecionado) {
                            console.log('CORRESPONDE! Chamando marcarLoteComoInserido...');
                            MapFramework.marcarLoteComoInserido(numeroMarcador);
                            console.log('Chamando passarParaProximoLote...');
                            MapFramework.passarParaProximoLote();
                        } else {
                            console.log('NÃO CORRESPONDE! Marcador salvo mas divCadastro3 não muda');
                        }
                        // Se não corresponde: marcador é salvo mas divCadastro3 não muda
                        
                    } else {
                        console.error('Erro ao salvar marcador:', resultado.mensagem);
                        alert('Erro ao salvar marcador: ' + (resultado.mensagem || 'Erro desconhecido'));
                    }
                } catch (e) {
                    console.error('Erro ao processar resposta:', e);
                }
            },
            error: function() {
                alert('Erro ao salvar marcador no banco.');
            }
        });
    },

    iniciarDesenhoMarcador: function () {
        if (this.listenerGlobalClick) { this.listenerGlobalClick.remove(); this.listenerGlobalClick = null; }
        // NÃO chamar this.desselecionarDesenho() aqui!
        this.atualizarInteratividadeObjetos(false);
        this.desenho.modo = 'marcador';
        this.desenho.tipoAtual = 'marcador';
        this.map.setOptions({ draggableCursor: 'crosshair' });
        $('#btnSairModoMarcador').removeClass('d-none');

        // Adiciona classe visual para indicar modo de inserção
        $('#inputLoteAtual').addClass('modo-insercao');
        this.modoInsercaoMarcador = true;

        if (this.desenho.listenerClick) this.desenho.listenerClick.remove();
        if (this.desenho.listenerRightClick) this.desenho.listenerRightClick.remove();

        //aqui vai bloquear os clicks nos quarteirões
        arrayCamadas.quarteirao.forEach(quarteirao => {
            quarteirao.polygon.setOptions({
                clickable: false
            });
        });

        this.desenho.listenerClick = this.map.addListener('click', (e) => {
            MapFramework.inserirMarcadorPersonalizado(e.latLng);
        });
    },

    // Função específica para sair do modo marcador e voltar ao estado anterior
    sairModoMarcador: function() {
        // Salva as variáveis globais atuais antes de sair
        const quarteiraoAtual = window.quarteiraoSelecionadoAtual;
        const quarteiraoIdAtual = window.quarteiraoIdAtualSelecionado;
        const divCadastro2Visivel = $('#divCadastro2').is(':visible');
        const divCadastro3Visivel = $('#divCadastro3').is(':visible');
        const radioSelecionado = $('input[name="quarteirao"]:checked').val();

        // Limpa o modo marcador
        this.desenho.modo = null;
        this.desenho.tipoAtual = null;
        this.desenho.pontos = [];

        // Remove listeners do modo marcador
        if (this.desenho.listenerClick) this.desenho.listenerClick.remove();
        if (this.desenho.listenerRightClick) this.desenho.listenerRightClick.remove();
        this.desenho.listenerClick = null;
        this.desenho.listenerRightClick = null;

        this.map.setOptions({ draggableCursor: 'default' });

        // Remove classe visual e flag do modo marcador
        $('#inputLoteAtual').removeClass('modo-insercao');
        this.modoInsercaoMarcador = false;

        // Oculta o botão específico do modo marcador
        $('#btnSairModoMarcador').addClass('d-none');

        // Reativa interatividade dos objetos
        this.atualizarInteratividadeObjetos(true);

        // Reativa os cliques nos quarteirões (que foram bloqueados no modo marcador)
        if (arrayCamadas.quarteirao) {
            arrayCamadas.quarteirao.forEach(quarteirao => {
                if (quarteirao.polygon) {
                    quarteirao.polygon.setOptions({
                        clickable: true
                    });
                }
            });
        }

        // IMPORTANTE: Reativa os cliques nos quarteirões se havia um selecionado
        if (quarteiraoIdAtual && typeof destacarQuarteiraoSelecionado === 'function') {
            // Restaura o quarteirão selecionado exatamente como estava
            destacarQuarteiraoSelecionado(quarteiraoAtual, quarteiraoIdAtual);
        }

        //console.log('Saiu do modo marcador e retornou ao estado anterior');
    },

    carregarMarcadoresSalvos: function (ortofoto) {
        // Limpa marcadores antigos
        arrayCamadas['marcador_quadra'] = [];
        // Remove do mapa todos os marcadores antigos
        // (caso a função seja chamada mais de uma vez)
        // Não precisa se já está limpando arrayCamadas, mas por garantia:
        // (Se quiser, pode iterar e dar setMap(null) em cada um)

        $.ajax({
            url: 'carregarDesenhosMarcador.php',
            method: 'GET',
            dataType: 'json',
            data: {
                ortofoto: ortofoto,
                camada: 'marcador_quadra'
            },
            success: (response) => {
                //console.log(response);
                
                if (response.status === 'sucesso') {
                    response.dados.forEach(desenho => {
                        //console.log(desenho.cor);
                        if ((desenho.camada || '').toLowerCase() !== 'marcador_quadra') return;
                        const coords = JSON.parse(desenho.coordenadas);
                        const numeroMarcador = desenho.lote; // Mantém como string para preservar letras (ex: 2A)
                        if (!coords[0]) return;
                        let lat = coords[0].lat;
                        let lng = coords[0].lng;
                        // Cria HTML do marcador
                        let el = document.createElement('div');
                        el.style.padding = '0 10px';
                        el.style.height = '32px';
                        el.style.background = desenho.cor;
                        el.style.borderRadius = '10px';
                        el.style.display = 'flex';
                        el.style.alignItems = 'center';
                        el.style.justifyContent = 'center';
                        el.style.color = 'white';
                        el.style.fontWeight = 'bold';
                        el.style.fontSize = '16px';
                        el.style.border = '2px solid #fff';
                        el.style.transform = 'translate(0, 10px)'; // Centraliza o marcador no ponto clicado
                        el.style.position = 'relative';
                        el.style.cursor = 'pointer';
                        el.className = 'marcador-personalizado';
                        el.textContent = numeroMarcador.toString();
                        
                        // Cria marcador avançado
                        let marker = new google.maps.marker.AdvancedMarkerElement({
                            position: { lat: parseFloat(lat), lng: parseFloat(lng) },
                            content: el,
                            gmpClickable: true
                        });

                        //marker.setMap(MapFramework.map);
                        marker.idQuadra = desenho.id_desenho;
                        marker.numeroMarcador = numeroMarcador;
                        marker.quarteirao = desenho.quarteirao;
                        marker.quadra = desenho.quadra;
                        marker.identificadorBanco = desenho.id; // ID do banco para poder deletar
                        
                        // Adiciona evento de clique para mostrar infowindow com dados do morador
                        el.addEventListener('click', function(event) {
                            // Busca dados do morador baseado em lote, quadra e quarteirão
                            const dadosMorador = MapFramework.dadosMoradores.find(morador => 
                                morador.lote == desenho.lote && 
                                morador.quadra == desenho.quadra && 
                                morador.cara_quarteirao == desenho.quarteirao
                            );

                            // Cria conteúdo do infowindow
                            let conteudoInfoWindow = '';

                            let tituloInicialHtml = `
                                <div style="display: flex; align-items: flex-start; margin-bottom: 15px;">
                                    <h5 style="margin: 0 0 8px 0; color: #333; font-weight: bold;">Dados Cadastrais</h5>
                                    <button type="button" class="btn btn-outline-secondary btn-sm ms-2 btn-docs-morador" style="font-size: 10px; padding: 2px 6px; border-radius: 3px;">Docs</button>
                                </div>
                            `;
                            
                            // Primeiro, sempre mostra os dados do desenho
                            let dadosDesenhoHTML = `
                                <div style="margin-bottom: 15px;">
                                    <h4 style="margin: 0 0 8px 0; color: #333; font-size: 14px; font-weight: bold;">Desenho</h4>
                                    <div style="border-bottom: 1px solid #ddd; margin-bottom: 8px;"></div>
                                    <div style="margin-bottom: 3px;"><strong style="font-weight: bold; color: #333;">ID:</strong> <span style="color: #666;">${desenho.id}</span></div>
                                    <div style="margin-bottom: 3px;"><strong style="font-weight: bold; color: #333;">Quarteirão:</strong> <span style="color: #666;">${desenho.quarteirao}</span></div>
                                    <div style="margin-bottom: 3px;"><strong style="font-weight: bold; color: #333;">Quadra:</strong> <span style="color: #666;">${desenho.quadra}</span></div>
                                    <div style="margin-bottom: 3px;"><strong style="font-weight: bold; color: #333;">Lote:</strong> <span style="color: #666;">${desenho.lote}</span></div>
                                </div>
                            `;
                            
                            if (dadosMorador) {
                                // Se encontrou dados do morador, exibe TODOS os campos dinamicamente
                                let camposHTML = '';
                                
                                // Itera sobre todos os campos do objeto dadosMorador
                                Object.keys(dadosMorador).forEach(campo => {
                                    const valor = dadosMorador[campo];
                                    // Só exibe campos que não são null, undefined ou string vazia
                                    if (valor !== null && valor !== undefined && valor !== '') {
                                        // Formata o nome do campo (remove underscores e capitaliza)
                                        const nomeCampo = campo.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                        camposHTML += `<div style="margin-bottom: 3px;"><strong style="font-weight: bold; color: #333;">${nomeCampo}:</strong> <span style="color: #666;">${valor}</span></div>`;
                                    }
                                });
                                
                                conteudoInfoWindow = `
                                    <div style="padding: 0 10px 10px 10px; font-family: Arial, sans-serif; max-width: 350px;">
                                        ${tituloInicialHtml}
                                        ${dadosDesenhoHTML}
                                        <div>
                                            <h4 style="margin: 0 0 8px 0; color: #333; font-size: 14px; font-weight: bold;">Cadastro</h4>
                                            <div style="border-bottom: 1px solid #ddd; margin-bottom: 8px;"></div>
                                            <div style="line-height: 1.4;">
                                                ${camposHTML}
                                            </div>
                                        </div>
                                    </div>
                                `;
                            } else {
                                // Se não encontrou dados do cadastro, mostra apenas os dados do desenho
                                conteudoInfoWindow = `
                                    <div style="padding: 10px; font-family: Arial, sans-serif; max-width: 350px;">
                                        ${dadosDesenhoHTML}
                                        <div>
                                            <h4 style="margin: 0 0 8px 0; color: #333; font-size: 14px; font-weight: bold;">Cadastro</h4>
                                            <div style="border-bottom: 1px solid #ddd; margin-bottom: 8px;"></div>
                                            <p style="margin: 0; color: #888; font-style: italic;">
                                                Dados do cadastro não encontrados
                                            </p>
                                        </div>
                                    </div>
                                `;
                            }

                            // Remove infowindow anterior se existir
                            if (MapFramework.infoWindow) {
                                MapFramework.infoWindow.close();
                            }

                            // Cria novo infowindow
                            MapFramework.infoWindow = new google.maps.InfoWindow({
                                content: conteudoInfoWindow,
                                position: { lat: parseFloat(lat), lng: parseFloat(lng) }
                            });

                            // Abre o infowindow
                            MapFramework.infoWindow.open(MapFramework.map);
                            
                            // Adiciona evento para o botão Docs quando o InfoWindow estiver pronto
                            google.maps.event.addListener(MapFramework.infoWindow, 'domready', function() {
                                const btnDocs = document.querySelector('.btn-docs-morador');
                                if (btnDocs) {
                                    btnDocs.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        
                                        const loteInfo = desenho ? desenho.id : "atual.";
                                        alert(`Aqui você poderá incluir, alterar e excluir documentos relacionados com o imóvel ${loteInfo}`);
                                    });
                                }
                            });
                        });
                        
                        arrayCamadas['marcador_quadra'].push(marker);
                        // Comentado: controle automático de sequência não funciona com lotes alfanuméricos (ex: 2A)
                        // const idQuadra = parseInt(desenho.id_desenho);
                        // if (!this.marcadoresPorQuadra[idQuadra] || this.marcadoresPorQuadra[idQuadra] < numeroMarcador) {
                        //     this.marcadoresPorQuadra[idQuadra] = numeroMarcador;
                        // }
                        
                    });
                } else {
                    console.warn('Erro ao carregar marcadores:', response.mensagem);
                }
            },
            error: function (xhr, status, error) {
                console.error('Erro na requisição de marcadores:', error);
            }
        });
    },

    // Marca o lote atual como inserido com sucesso (cor verde)
    marcarLoteComoInserido: function(numeroLote) {
        console.log('=== INICIANDO marcarLoteComoInserido ===');
        console.log('Número do lote:', numeroLote);
        
        // Encontra o lote SELECIONADO (com classe 'selected') e marca como inserido
        const loteElemento = $('.opcao-lote.selected');
        console.log('Lote elemento encontrado:', loteElemento.length > 0);
        
        if (loteElemento.length > 0) {
            console.log('Lote selecionado:', loteElemento.find('.lote-texto').text());
            
            // Adiciona classe e estilos de lote inserido
            loteElemento.addClass('lote-inserido').css({
                'background-color': '#d4edda !important',
                'border-color': '#c3e6cb !important',
                'color': '#155724 !important'
            });
            
            // Marca o texto também
            loteElemento.find('.lote-texto').css({
                'color': '#155724 !important',
                'font-weight': '600 !important'
            });
            
            console.log('Lote marcado como inserido:', numeroLote);
        } else {
            console.log('ERRO: Nenhum lote selecionado encontrado!');
        }
        
        // NÃO recarrega toda a lista - isso causa problemas de dupla verificação
        // Apenas chama a função de atualização se existir
        if (typeof atualizarListaLotes === 'function') {
            console.log('Chamando atualizarListaLotes...');
            atualizarListaLotes();
        } else {
            console.log('Função atualizarListaLotes não encontrada');
        }
        
        console.log('=== FIM marcarLoteComoInserido ===');
    },

    // Passa para o próximo lote automaticamente
    passarParaProximoLote: function() {
        // Obtém a quadra do lote atualmente selecionado
        const loteAtual = $('.opcao-lote.selected');
        if (loteAtual.length === 0) {
            console.log('Nenhum lote selecionado para passar para o próximo.');
            return;
        }
        
        const quadraAtual = loteAtual.data('quadra');
        const numeroLoteAtual = loteAtual.data('lote');
        
        // Converte o número do lote atual para número para comparação
        const numeroAtual = parseInt(numeroLoteAtual.toString().match(/^\d+/)[0]);
        
        // Procura o próximo lote na MESMA QUADRA com número sequencial
        let proximoLoteElement = null;
        let menorDiferenca = Infinity;
        
        $('.opcao-lote').each(function() {
            const $lote = $(this);
            const quadraLote = $lote.data('quadra');
            const numeroLote = $lote.data('lote');
            
            // Só considera lotes da mesma quadra que não foram inseridos
            if (quadraLote === quadraAtual && !$lote.hasClass('lote-inserido')) {
                // Converte o número do lote para comparação
                const numeroLoteInt = parseInt(numeroLote.toString().match(/^\d+/)[0]);
                
                // Procura o próximo número na sequência (maior que o atual)
                if (numeroLoteInt > numeroAtual) {
                    const diferenca = numeroLoteInt - numeroAtual;
                    if (diferenca < menorDiferenca) {
                        menorDiferenca = diferenca;
                        proximoLoteElement = $lote;
                    }
                }
            }
        });
        
        // Se não encontrou próximo na mesma quadra, procura o primeiro disponível da quadra
        if (!proximoLoteElement) {
            $('.opcao-lote').each(function() {
                const $lote = $(this);
                const quadraLote = $lote.data('quadra');
                
                if (quadraLote === quadraAtual && !$lote.hasClass('lote-inserido')) {
                    proximoLoteElement = $lote;
                    return false; // break do loop
                }
            });
        }

        // Se encontrou um próximo lote, seleciona ele
        if (proximoLoteElement) {
            const numeroLote = proximoLoteElement.data('lote');
            
            // Remove a flecha de todos os lotes
            $('.lote-flecha').html('&nbsp;&nbsp;');
            
            // Adiciona a flecha ao próximo lote
            proximoLoteElement.find('.lote-flecha').html('>');
            
            // Atualiza o input text com o próximo lote
            $('#inputLoteAtual').val(numeroLote);
            
            // Adiciona classe visual para destacar a opção selecionada
            $('.opcao-lote').removeClass('selected');
            proximoLoteElement.addClass('selected');
            
            // Faz scroll para o lote se necessário
            const container = $('#opcoesLotes');
            const itemOffset = proximoLoteElement.offset().top - container.offset().top + container.scrollTop();
            const containerHeight = container.height();
            const itemHeight = proximoLoteElement.outerHeight();
            
            if (itemOffset < container.scrollTop() || itemOffset + itemHeight > container.scrollTop() + containerHeight) {
                container.animate({
                    scrollTop: itemOffset - containerHeight / 2 + itemHeight / 2
                }, 300);
            }
        } else {
            // Se não há mais lotes na quadra, pode mostrar uma mensagem
            console.log('Todos os lotes da quadra foram processados.');
        }
    },

    // ============================================================================
    // FUNÇÃO PARA CARREGAR LOTES DA PREFEITURA DO ARQUIVO GEOJSON
    // ============================================================================
    // Esta função carrega os lotes da prefeitura de um arquivo GeoJSON e os 
    // adiciona como polígonos no mapa. Segue o padrão das outras funções do framework.
    // ============================================================================
    carregarLotesGeojson: function() {
        // Define a camada de destino - seguindo o padrão das outras funções
        var camadaLotes = "lotesPref";
        let destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';
        
        // Limpa a camada antes de carregar novos dados
        if (arrayCamadas[destinoLotes]) {
            arrayCamadas[destinoLotes].forEach(function(objeto) {
                if (objeto.setMap) {
                    objeto.setMap(null); // Remove do mapa
                }
            });
            arrayCamadas[destinoLotes] = []; // Limpa o array
        }
        
        // Requisição AJAX para carregar o arquivo GeoJSON
        $.ajax({
            url: `loteamentos_quadriculas/geojson/lotes_prefeitura_quadricula_${dadosOrto[0]['quadricula']}.geojson`,
            type: 'GET',
            cache: false, // Evita cache para sempre pegar a versão mais recente
            dataType: 'json',
            success: function(geojsonData) {
                console.log('GeoJSON dos lotes da prefeitura carregado com sucesso');
                console.log(`Número de features encontradas: ${geojsonData.features ? geojsonData.features.length : 0}`);
                
                // Processa cada feature do GeoJSON
                if (geojsonData && geojsonData.features) {
                    let lotesCarregados = 0;
                    
                    geojsonData.features.forEach(function(feature, index) {
                        // Verifica se é um polígono válido
                        if (feature.geometry && feature.geometry.type === 'Polygon' && feature.geometry.coordinates) {
                            try {
                                // Converte coordenadas do GeoJSON (longitude, latitude) para formato do Google Maps (lat, lng)
                                const coordinates = feature.geometry.coordinates[0].map(coord => ({
                                    lat: coord[1],  // latitude é o segundo elemento
                                    lng: coord[0]   // longitude é o primeiro elemento
                                }));
                                
                                // Cria o polígono seguindo o padrão do framework
                                const polygon = new google.maps.Polygon({
                                    paths: coordinates,
                                    strokeColor: '#FF6B35',    // Cor laranja para distinguir dos outros polígonos
                                    strokeOpacity: 0.8,
                                    strokeWeight: 3,           // Aumentei a espessura para ser mais visível
                                    fillColor: '#FF6B35',
                                    fillOpacity: 0.3,          // Aumentei a opacidade para ser mais visível
                                    map: null,                 // Inicialmente não visível no mapa
                                    clickable: true,
                                    zIndex: 10                 // Z-index mais alto para ficar por cima
                                });
                                
                                // Adiciona InfoWindow ao polígono com os dados do GeoJSON
                                polygon.addListener('click', function(event) {
                                    // Cria conteúdo da InfoWindow formatado
                                    let conteudo = '<div style="max-width: 300px; font-family: Arial, sans-serif; line-height: 1.4;">';
                                    conteudo += '<h4 style="margin: 0 0 10px 0; color: #333; border-bottom: 2px solid #FF6B35; padding-bottom: 5px; font-size: 16px;">📍 Lote da Prefeitura</h4>';
                                    
                                    // Adiciona as propriedades do GeoJSON formatadas
                                    if (feature.properties && Object.keys(feature.properties).length > 0) {
                                        // Define a ordem desejada: Inscrição primeiro, depois Endereço
                                        const ordemPropriedades = ['name', 'ENDERECO'];
                                        const propriedadesExibidas = new Set();
                                        
                                        // Primeiro, exibe as propriedades na ordem específica
                                        ordemPropriedades.forEach(function(key) {
                                            if (feature.properties[key] !== null && 
                                                feature.properties[key] !== '' && 
                                                feature.properties[key] !== undefined) {
                                                
                                                let labelFormatada;
                                                // Personaliza as labels específicas
                                                if (key === 'name') {
                                                    labelFormatada = 'Inscrição';
                                                } else if (key === 'ENDERECO') {
                                                    labelFormatada = 'Endereço';
                                                } else {
                                                    // Para outras propriedades, usa formatação padrão
                                                    labelFormatada = key.replace(/_/g, ' ')
                                                                       .replace(/\b\w/g, l => l.toUpperCase());
                                                }
                                                
                                                conteudo += `<p style="margin: 5px 0; font-size: 13px;"><strong>${labelFormatada}:</strong> ${feature.properties[key]}</p>`;
                                                propriedadesExibidas.add(key);
                                            }
                                        });
                                        
                                        // Depois, exibe outras propriedades que não foram exibidas ainda
                                        Object.keys(feature.properties).forEach(function(key) {
                                            const value = feature.properties[key];
                                            // Só mostra propriedades que têm valor e que não foram exibidas ainda
                                            if (value !== null && value !== '' && value !== undefined && 
                                                !propriedadesExibidas.has(key) && 
                                                key !== 'fill_color') { // Exclui fill_color
                                                
                                                // Formata o nome da propriedade (remove underscores e capitaliza)
                                                const keyFormatted = key.replace(/_/g, ' ')
                                                                       .replace(/\b\w/g, l => l.toUpperCase());
                                                conteudo += `<p style="margin: 5px 0; font-size: 13px;"><strong>${keyFormatted}:</strong> ${value}</p>`;
                                            }
                                        });
                                    } else {
                                        conteudo += '<p style="margin: 5px 0; color: #666; font-style: italic;">Sem dados adicionais disponíveis</p>';
                                    }
                                    
                                    conteudo += '</div>';
                                    
                                    // Usa o InfoWindow global do framework se existir, senão cria um novo
                                    if (MapFramework.infoWindow) {
                                        MapFramework.infoWindow.setContent(conteudo);
                                        MapFramework.infoWindow.setPosition(event.latLng);
                                        MapFramework.infoWindow.open(MapFramework.map);
                                    } else {
                                        // Fallback: cria InfoWindow temporário
                                        const infoWindow = new google.maps.InfoWindow({
                                            content: conteudo,
                                            position: event.latLng
                                        });
                                        infoWindow.open(MapFramework.map);
                                    }
                                });
                                
                                // Adiciona o polígono à camada diretamente
                                if (!arrayCamadas[destinoLotes]) {
                                    arrayCamadas[destinoLotes] = [];
                                }
                                arrayCamadas[destinoLotes].push(polygon);
                                lotesCarregados++;
                                
                            } catch (error) {
                                console.error('Erro ao processar feature:', error);
                            }
                        }
                    });
                    
                    // Lotes carregados com sucesso
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro ao carregar lotes da prefeitura:', error);
            }
        });
    },

    // ============================================================================
    // FUNÇÃO PARA MOSTRAR/OCULTAR LOTES DA PREFEITURA
    // ============================================================================
    // Esta função controla a visibilidade dos lotes carregados do GeoJSON
    // ============================================================================
    toggleLotesGeojson: function(mostrar) {
        var camadaLotes = "lotesPref";
        let destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';
        
        // Se não há lotes carregados e o usuário quer mostrar, carrega primeiro
        if ((!arrayCamadas[destinoLotes] || arrayCamadas[destinoLotes].length === 0) && mostrar) {
            this.carregarLotesGeojson();
            
            // Aguarda um pouco para os lotes serem carregados e depois mostra
            setTimeout(() => {
                if (arrayCamadas[destinoLotes] && arrayCamadas[destinoLotes].length > 0) {
                    arrayCamadas[destinoLotes].forEach(function(polygon) {
                        if (polygon.setMap) {
                            polygon.setMap(mostrar ? MapFramework.map : null);
                        }
                    });
                }
            }, 2000); // Aguarda 2 segundos para o carregamento
        } else if (arrayCamadas[destinoLotes]) {
            // Se já há lotes carregados, apenas mostra/oculta
            arrayCamadas[destinoLotes].forEach(function(polygon) {
                if (polygon.setMap) {
                    polygon.setMap(mostrar ? MapFramework.map : null);
                }
            });
        }
    }
};