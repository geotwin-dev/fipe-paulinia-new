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

    desenho: {
        modo: null,
        tipoAtual: null,
        pontos: [],
        temporario: null,
        listenerClick: null,
        listenerRightClick: null,
        cliqueEmVertice: false
    },

    selecionado: null,

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

        this.map.addListener('click', () => {
            this.desselecionarDesenho();
        });
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

        // remove listeners
        if (this.desenho.listenerClick) this.desenho.listenerClick.remove();
        if (this.desenho.listenerRightClick) this.desenho.listenerRightClick.remove();
        this.desenho.listenerClick = null;
        this.desenho.listenerRightClick = null;

        this.map.setOptions({ draggableCursor: 'default' });

        // UI
        this.fecharModalCamada();
        $('#inputNumeroQuadra').val(''); // se quiser limpar o input
        this.atualizarInteratividadeObjetos(true);
        
        // Oculta o botão de finalizar desenho
        $('#btnFinalizarDesenho').addClass('d-none');
    },
    abrirModalCamada: function () {
        $('#modalCamada').fadeIn(150);
    },
    
    fecharModalCamada: function () {
        $('#modalCamada').fadeOut(150);
    },
    alternarVisibilidadeCamada: function (camada, visivel) {
        if (!arrayCamadas[camada]) return;

        arrayCamadas[camada].forEach(obj => {
            if (typeof obj.setMap === 'function') {
                obj.setMap(visivel ? this.map : null);
            }
        });
    },

    atualizarInteratividadeObjetos: function (interativo) {
        Object.keys(arrayCamadas).forEach(nomeCamada => {
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

                if (response.status === 'sucesso') {

                    response.dados.forEach(desenho => {

                        const camadaNome = (desenho.camada || 'semCamadas').toLowerCase();
                        const tipo = desenho.tipo;
                        const coords = JSON.parse(desenho.coordenadas);
                        const cores = desenho.cor;

                        //console.log(coords)

                        if (!arrayCamadas[camadaNome]) {
                            arrayCamadas[camadaNome] = [];
                        }

                        let objeto = null;

                        if (tipo === 'poligono') {
                            objeto = new google.maps.Polygon({
                                paths: coords,
                                strokeColor: cores,
                                strokeOpacity: 0.8,
                                strokeWeight: 1,
                                fillColor: cores,
                                fillOpacity: 0.35,
                                editable: false,
                                map: MapFramework.map,
                                zIndex: 2
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
                                strokeWeight: 2,
                                editable: false,
                                map: MapFramework.map,
                                zIndex: 3
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
            url: `jsons/${quadricula}.json`,
            method: 'GET',
            dataType: 'json',
            success: function (response) {

                //console.log(response)
                Object.values(response.plot).forEach(desenho => {
                    //console.log(desenho)
                    if (desenho.type == "arc") {
                        let centerArc = utmToLatLng(desenho.center[0], desenho.center[1]);
                        drawArc(map, centerArc, desenho.radius, desenho.start_angle, desenho.end_angle, "#ff0000ff");
                        
                    }else if(desenho.type == "line"){
                        
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

                    }else if(desenho.type == "polyline"){

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

                    }else if(desenho.type == "text"){

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

                    }else if(desenho.type == "circle"){

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
                        
                    }else{
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
        // Limpa seleção antes de começar novo desenho
        this.desselecionarDesenho();

        this.atualizarInteratividadeObjetos(false);

        this.desenho.modo = 'polilinha';
        this.desenho.tipoAtual = 'polilinha';
        this.map.setOptions({ draggableCursor: 'crosshair' });
        this.desenho.pontos = [];
        
        // Mostra o botão de finalizar desenho
        $('#btnFinalizarDesenho').removeClass('d-none');

        if (this.desenho.listenerClick) this.desenho.listenerClick.remove();
        if (this.desenho.listenerRightClick) this.desenho.listenerRightClick.remove();

        this.desenho.listenerClick = this.map.addListener('click', (e) => {
            const ponto = e.latLng;

            if (!this.desenho.temporario) {
                this.desenho.pontos = [ponto];

                this.desenho.temporario = new google.maps.Polyline({
                    path: this.desenho.pontos,
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
                this.desenho.pontos.push(ponto);
                this.desenho.temporario.setPath(this.desenho.pontos);
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

        if(tipo != 'polilinha'){
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
                        
                        if(tipo === 'poligono'){
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
            
            arrayCamadas['lote'].forEach(lote => {
                
                if(parseInt(lote['id_desenho']) ==  identificador){
                    lote.setMap(null);
                    this.removerObjetoDasCamadas(lote);
                }
                
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
    }
};