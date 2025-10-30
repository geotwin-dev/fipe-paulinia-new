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

    // Modo de edição
    modoEdicao: false,
    desenhosEditados: [], // Array para armazenar desenhos que foram editados

    selecionarDesenho: function (objeto) {
        if (this.desenho.temporario) return; // Não selecionar durante desenho

        // Remove destaque anterior
        if (this.selecionado) {
            if (this.selecionado instanceof google.maps.Polygon) {
                this.selecionado.setOptions({
                    strokeColor: this.selecionado.corOriginal,
                    fillColor: this.selecionado.corOriginal,
                    zIndex: this.selecionado.zIndexOriginal || 5 // Restaura z-index original
                });
            } else if (this.selecionado instanceof google.maps.Polyline) {
                this.selecionado.setOptions({
                    strokeColor: this.selecionado.corOriginal,
                    zIndex: this.selecionado.zIndexOriginal || 7 // Restaura z-index original
                });
            }

            // Fecha o InfoWindow se estiver aberto
            if (this.infoWindow) {
                this.infoWindow.close();
            }
        }

        // Define novo selecionado
        this.selecionado = objeto;

        //console.log(objeto)

        if (objeto instanceof google.maps.Polygon) {
            // Armazena o z-index original antes de alterar
            objeto.zIndexOriginal = objeto.zIndex || 5;
            objeto.setOptions({
                strokeColor: 'yellow',
                fillColor: 'yellow',
                zIndex: 8 // Fica por cima de tudo quando selecionado
            });
        } else if (objeto instanceof google.maps.Polyline) {
            // Armazena o z-index original antes de alterar
            objeto.zIndexOriginal = objeto.zIndex || 7;
            objeto.setOptions({
                strokeColor: 'yellow',
                zIndex: 8 // Fica por cima de tudo quando selecionado
            });
        }

        // Se estiver no modo de edição, torna o objeto editável e adiciona listeners
        if (this.modoEdicao) {
            objeto.setOptions({ editable: true });
            this.adicionarListenersEdicao(objeto);
        }

        // Controla visibilidade dos botões
        if (this.modoEdicao) {
            // No modo edição, não mostra editar e excluir
            $('#btnExcluir').addClass('d-none');
            $('#btnEditar').addClass('d-none');
        } else {
            // Modo normal, mostra os botões
            $('#btnExcluir').removeClass('d-none');
            $('#btnEditar').removeClass('d-none');
        }
    },

    desselecionarDesenho: function () {
        if (this.selecionado) {
            const obj = this.selecionado;

            if (obj instanceof google.maps.Polygon) {
                obj.setOptions({
                    strokeColor: obj.corOriginal || '#0000FF', // azul como fallback
                    fillColor: obj.corOriginal || '#0000FF',
                    zIndex: obj.zIndexOriginal || 5 // Restaura z-index original
                });
            } else if (obj instanceof google.maps.Polyline) {
                obj.setOptions({
                    strokeColor: obj.corOriginal || '#FF0000', // vermelho como fallback
                    zIndex: obj.zIndexOriginal || 7 // Restaura z-index original
                });
            }

            this.selecionado = null;

            // Fecha o InfoWindow se estiver aberto
            if (this.infoWindow) {
                this.infoWindow.close();
            }

            // Aqui garantimos que o botão some
            $('#btnExcluir').addClass('d-none');
            $('#btnEditar').addClass('d-none');
        }
    },

    abrirInfoWindowCores: function (poligono, posicao, idDesenho) {
        // Fecha InfoWindow anterior se existir
        if (this.infoWindow) {
            this.infoWindow.close();
        }

        // Define as cores dos botões com seus respectivos valores hexadecimais
        const cores = [
            { classe: 'btn btn-primary', cor: 'blue', nome: 'A revisar' },
            { classe: 'btn btn-warning', cor: 'orange', nome: 'Revisado parcialmente' },
            { classe: 'btn btn-danger', cor: 'red', nome: 'Não conformidade' },
            { classe: 'btn btn-success', cor: '#198754', nome: 'Revisão OK' }
        ];

        // Cria o conteúdo HTML do InfoWindow com os botões
        let conteudoHTML = '<div style="padding: 10px;">';
        conteudoHTML += '<h6 style="margin-bottom: 10px;">Situação da revisão:</h6>';
        conteudoHTML += '<div style="display: flex; flex-direction: column; gap: 8px;">';

        cores.forEach(cor => {
            conteudoHTML += `<button class="${cor.classe}" data-cor="${cor.cor}" data-id="${idDesenho}" style="width: 100%;">${cor.nome}</button>`;
        });

        conteudoHTML += '</div></div>';

        // Cria o InfoWindow
        this.infoWindow = new google.maps.InfoWindow({
            content: conteudoHTML,
            position: posicao
        });

        // Abre o InfoWindow
        this.infoWindow.open(this.map);

        // Adiciona event listeners aos botões após o InfoWindow ser aberto
        google.maps.event.addListenerOnce(this.infoWindow, 'domready', () => {
            $('.btn[data-cor]').off('click').on('click', function () {
                const novaCor = $(this).data('cor');
                const idDesenho = $(this).data('id');

                // Atualiza a cor do polígono visualmente
                poligono.setOptions({
                    strokeColor: novaCor,
                    fillColor: novaCor
                });

                // Atualiza a cor original para manter a nova cor
                poligono.corOriginal = novaCor;

                // Envia a atualização para o servidor via AJAX
                $.ajax({
                    url: 'atualizar_cor_desenho.php',
                    method: 'POST',
                    data: {
                        id_desenho: idDesenho,
                        cor: novaCor
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'sucesso') {
                            console.log('Cor atualizada com sucesso!');
                            // Fecha o InfoWindow após atualizar
                            MapFramework.infoWindow.close();
                        } else {
                            console.error('Erro ao atualizar cor:', response.mensagem);
                            //alert('Erro ao atualizar a cor: ' + response.mensagem);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Erro na requisição AJAX:', error);
                        //alert('Erro ao comunicar com o servidor');
                    }
                });
            });
        });
    },

    abrirInfoWindowUnidade: function (poligono, posicao, idDesenho) {
        // Fecha InfoWindow anterior se existir
        if (this.infoWindow) {
            this.infoWindow.close();
        }

        // Calcula a área do polígono em metros quadrados (float)
        let areaPoligono = 0;
        if (poligono.coordenadasGeoJSON && turf) {
            try {
                areaPoligono = turf.area(poligono.coordenadasGeoJSON);
                // Mantém como float, não arredonda
            } catch (e) {
                console.error('Erro ao calcular área:', e);
            }
        }

        // Mostra loading no InfoWindow
        this.infoWindow = new google.maps.InfoWindow({
            content: '<div style="padding: 10px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> Carregando dados...</div>',
            position: posicao
        });

        this.infoWindow.open(this.map);

        // Armazena referência ao polígono para uso nos event listeners
        const self = this;
        const poligonoRef = poligono;
        const idDesenhoRef = idDesenho;

        // Busca dados da unidade
        $.ajax({
            url: 'buscar_dados_unidade.php',
            method: 'GET',
            data: {
                id_desenho: idDesenho
            },
            dataType: 'json',
            success: (response) => {
                if (response.status === 'sucesso') {
                    const dados = response.dados || {};
                    const revisadoAtual = dados.revisado || 0;

                    // Cores para revisado/não revisado
                    const corRevisado = '#90EE90'; // Verde limão
                    const corNaoRevisado = '#ff00ff'; // Magenta

                    let conteudoHTML = '<div style="padding: 15px; min-width: 400px; max-width: 500px; font-size: 13px;">';
                    conteudoHTML += '<h6 style="margin: 0 0 15px 0; color: #333; border-bottom: 2px solid #ff00ff; padding-bottom: 8px;">Informações do Bloco</h6>';

                    // Qtde. Pavimentos
                    conteudoHTML += '<div style="margin-bottom: 12px;">';
                    conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Qtde. Pavimentos:</label>';
                    conteudoHTML += '<input type="number" id="inputPavimentosUnidade" value="' + (dados.pavimentos || '') + '" min="1" step="1" style="width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 3px;" onkeypress="return event.charCode >= 48 && event.charCode <= 57">';
                    conteudoHTML += '</div>';

                    // Utilização
                    conteudoHTML += '<div style="margin-bottom: 12px;">';
                    conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Utilização:</label>';
                    conteudoHTML += '<select id="selectUtilizacaoUnidade" style="width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 3px;">';
                    conteudoHTML += '<option value="">Selecione...</option>';
                    conteudoHTML += '<option value="Residencial"' + (dados.utilizacao === 'Residencial' ? ' selected' : '') + '>Residencial</option>';
                    conteudoHTML += '<option value="Comercial"' + (dados.utilizacao === 'Comercial' ? ' selected' : '') + '>Comercial</option>';
                    conteudoHTML += '<option value="Mista"' + (dados.utilizacao === 'Mista' ? ' selected' : '') + '>Mista</option>';
                    conteudoHTML += '</select>';
                    conteudoHTML += '</div>';

                    // Pavimento Térreo
                    conteudoHTML += '<div style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #ddd;">';
                    conteudoHTML += '<h6 style="margin: 0 0 12px 0; color: #666; font-weight: bold;">Pavimento Térreo</h6>';

                    // Térreo - Usos
                    //conteudoHTML += '<div style="margin-bottom: 12px;">';
                    //conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Usos:</label>';
                    //conteudoHTML += '<select id="selectTerreoUsoUnidade" style="width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 3px;">';
                    //conteudoHTML += '<option value="">Selecione...</option>';
                    //conteudoHTML += '<option value="Area A Residencial"' + (dados.terreo_uso === 'Area A Residencial' ? ' selected' : '') + '>Area A Residencial</option>';
                    //conteudoHTML += '<option value="Area A Comercial"' + (dados.terreo_uso === 'Area A Comercial' ? ' selected' : '') + '>Area A Comercial</option>';
                    //conteudoHTML += '<option value="Área A Residencial, Área B Comercial"' + (dados.terreo_uso === 'Área A Residencial, Área B Comercial' ? ' selected' : '') + '>Área A Residencial, Área B Comercial</option>';
                    //conteudoHTML += '<option value="Área A Comercial, Área B Residencial"' + (dados.terreo_uso === 'Área A Comercial, Área B Residencial' ? ' selected' : '') + '>Área A Comercial, Área B Residencial</option>';
                    //conteudoHTML += '<option value="Área A Residencial, Área B Residencial"' + (dados.terreo_uso === 'Área A Residencial, Área B Residencial' ? ' selected' : '') + '>Área A Residencial, Área B Residencial</option>';
                    //conteudoHTML += '</select>';
                    //conteudoHTML += '</div>';

                    // Térreo - Tipo da Construção
                    conteudoHTML += '<div style="margin-bottom: 12px;">';
                    conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Tipo da Construção:</label>';
                    conteudoHTML += '<select id="selectTerreoTipoUnidade" style="width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 3px;">';
                    conteudoHTML += '<option value="">Selecione...</option>';
                    conteudoHTML += '<option value="Casa / Sobrado"' + (dados.terreo_tipo === 'Casa / Sobrado' ? ' selected' : '') + '>Casa / Sobrado</option>';
                    conteudoHTML += '<option value="Apartamento"' + (dados.terreo_tipo === 'Apartamento' ? ' selected' : '') + '>Apartamento</option>';
                    conteudoHTML += '<option value="Comercial ou prestação de Serviços"' + (dados.terreo_tipo === 'Comercial ou prestação de Serviços' ? ' selected' : '') + '>Comercial ou prestação de Serviços</option>';
                    conteudoHTML += '<option value="Industrial"' + (dados.terreo_tipo === 'Industrial' ? ' selected' : '') + '>Industrial</option>';
                    conteudoHTML += '<option value="Galpão / Telheiro"' + (dados.terreo_tipo === 'Galpão / Telheiro' ? ' selected' : '') + '>Galpão / Telheiro</option>';
                    conteudoHTML += '<option value="Outro tipo"' + (dados.terreo_tipo === 'Outro tipo' ? ' selected' : '') + '>Outro tipo</option>';
                    conteudoHTML += '</select>';
                    conteudoHTML += '</div>';

                    // Térreo - Classificação
                    conteudoHTML += '<div style="margin-bottom: 12px;">';
                    conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Classificação:</label>';
                    conteudoHTML += '<select id="selectTerreoClassificacaoUnidade" style="width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 3px;">';
                    conteudoHTML += '<option value="">Selecione...</option>';
                    conteudoHTML += '<option value="Luxo"' + (dados.terreo_classificacao === 'Luxo' ? ' selected' : '') + '>Luxo</option>';
                    conteudoHTML += '<option value="Boa"' + (dados.terreo_classificacao === 'Boa' ? ' selected' : '') + '>Boa</option>';
                    conteudoHTML += '<option value="Média"' + (dados.terreo_classificacao === 'Média' ? ' selected' : '') + '>Média</option>';
                    conteudoHTML += '<option value="Popular"' + (dados.terreo_classificacao === 'Popular' ? ' selected' : '') + '>Popular</option>';
                    conteudoHTML += '<option value="Rústica / Precária"' + (dados.terreo_classificacao === 'Rústica / Precária' ? ' selected' : '') + '>Rústica / Precária</option>';
                    conteudoHTML += '</select>';
                    conteudoHTML += '</div>';

                    // Térreo - Área construída (sempre calculada, não busca do banco)
                    conteudoHTML += '<div style="margin-bottom: 15px;">';
                    conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Área construída:</label>';
                    conteudoHTML += '<input type="text" id="inputTerreoAreaUnidade" value="' + areaPoligono.toFixed(2) + '" readonly style="width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 3px; background-color: #f5f5f5;">';
                    conteudoHTML += '</div>';
                    conteudoHTML += '</div>';

                    // Demais Pavimentos (só aparece se pavimentos > 1)
                    const pavimentosAtual = parseInt(dados.pavimentos) || 1;
                    const mostrarDemaisPavimentos = pavimentosAtual > 1;
                    conteudoHTML += '<div id="divDemaisPavimentosUnidade" style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #ddd; ' + (mostrarDemaisPavimentos ? '' : 'display: none;') + '">';
                    conteudoHTML += '<h6 style="margin: 0 0 12px 0; color: #666; font-weight: bold;">Demais Pavimentos</h6>';

                    // Demais - Usos
                    //conteudoHTML += '<div style="margin-bottom: 12px;">';
                    //conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Usos:</label>';
                    //conteudoHTML += '<select id="selectDemaisUsoUnidade" style="width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 3px;">';
                    //conteudoHTML += '<option value="">Selecione...</option>';
                    //conteudoHTML += '<option value="Area A Residencial"' + (dados.demais_uso === 'Area A Residencial' ? ' selected' : '') + '>Area A Residencial</option>';
                    //conteudoHTML += '<option value="Area A Comercial"' + (dados.demais_uso === 'Area A Comercial' ? ' selected' : '') + '>Area A Comercial</option>';
                    //conteudoHTML += '<option value="Área A Residencial, Área B Comercial"' + (dados.demais_uso === 'Área A Residencial, Área B Comercial' ? ' selected' : '') + '>Área A Residencial, Área B Comercial</option>';
                    //conteudoHTML += '<option value="Área A Comercial, Área B Residencial"' + (dados.demais_uso === 'Área A Comercial, Área B Residencial' ? ' selected' : '') + '>Área A Comercial, Área B Residencial</option>';
                    //conteudoHTML += '<option value="Área A Residencial, Área B Residencial"' + (dados.demais_uso === 'Área A Residencial, Área B Residencial' ? ' selected' : '') + '>Área A Residencial, Área B Residencial</option>';
                    //conteudoHTML += '</select>';
                    //conteudoHTML += '</div>';

                    // Demais - Tipo da Construção
                    conteudoHTML += '<div style="margin-bottom: 12px;">';
                    conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Tipo da Construção:</label>';
                    conteudoHTML += '<select id="selectDemaisTipoUnidade" style="width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 3px;">';
                    conteudoHTML += '<option value="">Selecione...</option>';
                    conteudoHTML += '<option value="Casa / Sobrado"' + (dados.demais_tipo === 'Casa / Sobrado' ? ' selected' : '') + '>Casa / Sobrado</option>';
                    conteudoHTML += '<option value="Apartamento"' + (dados.demais_tipo === 'Apartamento' ? ' selected' : '') + '>Apartamento</option>';
                    conteudoHTML += '<option value="Comercial ou prestação de Serviços"' + (dados.demais_tipo === 'Comercial ou prestação de Serviços' ? ' selected' : '') + '>Comercial ou prestação de Serviços</option>';
                    conteudoHTML += '<option value="Industrial"' + (dados.demais_tipo === 'Industrial' ? ' selected' : '') + '>Industrial</option>';
                    conteudoHTML += '<option value="Galpão / Telheiro"' + (dados.demais_tipo === 'Galpão / Telheiro' ? ' selected' : '') + '>Galpão / Telheiro</option>';
                    conteudoHTML += '<option value="Outro tipo"' + (dados.demais_tipo === 'Outro tipo' ? ' selected' : '') + '>Outro tipo</option>';
                    conteudoHTML += '</select>';
                    conteudoHTML += '</div>';

                    // Demais - Classificação
                    conteudoHTML += '<div style="margin-bottom: 12px;">';
                    conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Classificação:</label>';
                    conteudoHTML += '<select id="selectDemaisClassificacaoUnidade" style="width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 3px;">';
                    conteudoHTML += '<option value="">Selecione...</option>';
                    conteudoHTML += '<option value="Luxo"' + (dados.demais_classificacao === 'Luxo' ? ' selected' : '') + '>Luxo</option>';
                    conteudoHTML += '<option value="Boa"' + (dados.demais_classificacao === 'Boa' ? ' selected' : '') + '>Boa</option>';
                    conteudoHTML += '<option value="Média"' + (dados.demais_classificacao === 'Média' ? ' selected' : '') + '>Média</option>';
                    conteudoHTML += '<option value="Popular"' + (dados.demais_classificacao === 'Popular' ? ' selected' : '') + '>Popular</option>';
                    conteudoHTML += '<option value="Rústica / Precária"' + (dados.demais_classificacao === 'Rústica / Precária' ? ' selected' : '') + '>Rústica / Precária</option>';
                    conteudoHTML += '</select>';
                    conteudoHTML += '</div>';

                    // Demais - Área construída (calculada: área × (pavimentos - 1))
                    const areaDemaisPavimentos = mostrarDemaisPavimentos ? (areaPoligono * (pavimentosAtual - 1)) : 0;
                    conteudoHTML += '<div style="margin-bottom: 15px;">';
                    conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Área construída:</label>';
                    conteudoHTML += '<input type="text" id="inputDemaisAreaUnidade" value="' + areaDemaisPavimentos.toFixed(2) + '" readonly style="width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 3px; background-color: #f5f5f5;">';
                    conteudoHTML += '</div>';
                    conteudoHTML += '</div>';

                    // Botões Revisado / Não Revisado
                    conteudoHTML += '<div style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #ddd; display: flex; gap: 10px;">';
                    conteudoHTML += '<button id="btnRevisadoUnidade" style="flex: 1; padding: 8px; border: none; border-radius: 3px; cursor: pointer; font-weight: bold; background-color: ' + (revisadoAtual == 1 ? corRevisado : '#ccc') + '; color: ' + (revisadoAtual == 1 ? '#000' : '#666') + ';">REVISADO</button>';
                    conteudoHTML += '<button id="btnNaoRevisadoUnidade" style="flex: 1; padding: 8px; border: none; border-radius: 3px; cursor: pointer; font-weight: bold; background-color: ' + (revisadoAtual == 0 ? corNaoRevisado : '#ccc') + '; color: ' + (revisadoAtual == 0 ? '#fff' : '#666') + ';">NÃO REVISADO</button>';
                    conteudoHTML += '</div>';

                    // Botão Salvar
                    conteudoHTML += '<div style="margin-top: 15px; text-align: center;">';
                    conteudoHTML += '<button id="btnSalvarUnidade" style="padding: 10px 30px; background-color: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer; font-weight: bold; font-size: 14px;"><i class="fas fa-save"></i> Salvar</button>';
                    conteudoHTML += '</div>';

                    conteudoHTML += '</div>';

                    this.infoWindow.setContent(conteudoHTML);

                    // Variável para armazenar estado de revisado
                    let revisadoEstado = revisadoAtual;

                    // Define a cor inicial do polígono baseado no estado de revisado
                    if (revisadoAtual == 1) {
                        poligonoRef.setOptions({
                            strokeColor: corRevisado,
                            fillColor: corRevisado
                        });
                        poligonoRef.corOriginal = corRevisado;
                    } else {
                        poligonoRef.setOptions({
                            strokeColor: corNaoRevisado,
                            fillColor: corNaoRevisado
                        });
                        poligonoRef.corOriginal = corNaoRevisado;
                    }

                    // Adiciona event listeners após o InfoWindow ser aberto
                    google.maps.event.addListenerOnce(this.infoWindow, 'domready', () => {
                        // Função para atualizar área de demais pavimentos e mostrar/ocultar seção
                        const atualizarAreaDemaisPavimentos = function () {
                            const pavimentos = parseInt($('#inputPavimentosUnidade').val()) || 1;
                            const divDemaisPavimentos = $('#divDemaisPavimentosUnidade');

                            if (pavimentos > 1) {
                                divDemaisPavimentos.show();
                                const areaDemais = areaPoligono * (pavimentos - 1);
                                $('#inputDemaisAreaUnidade').val(areaDemais.toFixed(2));
                            } else {
                                divDemaisPavimentos.hide();
                                $('#inputDemaisAreaUnidade').val('0.00');
                            }
                        };

                        // Event listener no campo de pavimentos
                        $('#inputPavimentosUnidade').on('input change', function () {
                            atualizarAreaDemaisPavimentos();
                        });

                        // Botão Revisado
                        $('#btnRevisadoUnidade').on('click', function () {
                            revisadoEstado = 1;
                            $(this).css('background-color', corRevisado).css('color', '#000');
                            $('#btnNaoRevisadoUnidade').css('background-color', '#ccc').css('color', '#666');
                            poligonoRef.setOptions({
                                strokeColor: corRevisado,
                                fillColor: corRevisado
                            });
                            poligonoRef.corOriginal = corRevisado;
                        });

                        // Botão Não Revisado
                        $('#btnNaoRevisadoUnidade').on('click', function () {
                            revisadoEstado = 0;
                            $(this).css('background-color', corNaoRevisado).css('color', '#fff');
                            $('#btnRevisadoUnidade').css('background-color', '#ccc').css('color', '#666');
                            poligonoRef.setOptions({
                                strokeColor: corNaoRevisado,
                                fillColor: corNaoRevisado
                            });
                            poligonoRef.corOriginal = corNaoRevisado;
                        });

                        // Botão Salvar
                        $('#btnSalvarUnidade').on('click', function () {
                            const btnSalvar = $(this);
                            btnSalvar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');

                            const dadosSalvar = {
                                id_desenho: idDesenhoRef,
                                revisado: revisadoEstado,
                                pavimentos: $('#inputPavimentosUnidade').val(),
                                utilizacao: $('#selectUtilizacaoUnidade').val(),
                                terreo_uso: $('#selectTerreoUsoUnidade').val(),
                                terreo_tipo: $('#selectTerreoTipoUnidade').val(),
                                terreo_classificacao: $('#selectTerreoClassificacaoUnidade').val(),
                                terreo_area: $('#inputTerreoAreaUnidade').val(),
                                demais_uso: $('#selectDemaisUsoUnidade').val(),
                                demais_tipo: $('#selectDemaisTipoUnidade').val(),
                                demais_classificacao: $('#selectDemaisClassificacaoUnidade').val(),
                                demais_area: $('#inputDemaisAreaUnidade').val(),
                                cor: poligonoRef.corOriginal,
                                quadricula: dadosOrto[0]['quadricula']
                            };

                            $.ajax({
                                url: 'salvar_dados_unidade.php',
                                method: 'POST',
                                data: dadosSalvar,
                                dataType: 'json',
                                success: function (response) {
                                    if (response.status === 'sucesso') {
                                        btnSalvar.prop('disabled', false).html('<i class="fas fa-check"></i> Salvo!');
                                        setTimeout(() => {
                                            self.infoWindow.close();
                                        }, 1000);
                                    } else {
                                        alert('Erro ao salvar: ' + response.mensagem);
                                        btnSalvar.prop('disabled', false).html('<i class="fas fa-save"></i> Salvar');
                                    }
                                },
                                error: function () {
                                    alert('Erro ao comunicar com o servidor');
                                    btnSalvar.prop('disabled', false).html('<i class="fas fa-save"></i> Salvar');
                                }
                            });
                        });
                    });
                } else {
                    this.infoWindow.setContent('<div style="padding: 10px; color: red;">Erro ao carregar dados: ' + (response.mensagem || 'Erro desconhecido') + '</div>');
                }
            },
            error: () => {
                this.infoWindow.setContent('<div style="padding: 10px; color: red;">Erro ao carregar dados da unidade</div>');
            }
        });
    },

    abrirInfoWindowUnidade2: function (poligono, posicao, idDesenho) {
        // Fecha InfoWindow anterior se existir
        if (this.infoWindow) {
            this.infoWindow.close();
        }

        // Calcula a área do polígono em metros quadrados (float)
        let areaPoligono = 0;
        if (poligono.coordenadasGeoJSON && turf) {
            try {
                areaPoligono = turf.area(poligono.coordenadasGeoJSON);
            } catch (e) {
                console.error('Erro ao calcular área:', e);
            }
        }

        // Mostra loading no InfoWindow
        this.infoWindow = new google.maps.InfoWindow({
            content: '<div style="padding: 10px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> Carregando dados...</div>',
            position: posicao
        });

        this.infoWindow.open(this.map);

        // Busca dados da unidade
        $.ajax({
            url: 'buscar_dados_unidade.php',
            method: 'GET',
            data: {
                id_desenho: idDesenho
            },
            dataType: 'json',
            success: (response) => {
                if (response.status === 'sucesso') {
                    const dados = response.dados || {};
                    const revisadoAtual = dados.revisado || 0;

                    // Cores para revisado/não revisado
                    const corRevisado = '#90EE90'; // Verde limão
                    const corNaoRevisado = '#ff00ff'; // Magenta

                    let conteudoHTML = '<div style="padding: 15px; min-width: 400px; max-width: 500px; font-size: 13px;">';
                    conteudoHTML += '<h6 style="margin: 0 0 15px 0; color: #333; border-bottom: 2px solid #ff00ff; padding-bottom: 8px;">Informações do Bloco (Somente Leitura)</h6>';

                    // Qtde. Pavimentos
                    conteudoHTML += '<div style="margin-bottom: 12px;">';
                    conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Qtde. Pavimentos:</label>';
                    conteudoHTML += '<div style="padding: 8px; border: 1px solid #ccc; border-radius: 3px; background-color: #f5f5f5; color: #333;">' + (dados.pavimentos || 'Não informado') + '</div>';
                    conteudoHTML += '</div>';

                    // Utilização
                    conteudoHTML += '<div style="margin-bottom: 12px;">';
                    conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Utilização:</label>';
                    conteudoHTML += '<div style="padding: 8px; border: 1px solid #ccc; border-radius: 3px; background-color: #f5f5f5; color: #333;">' + (dados.utilizacao || 'Não informado') + '</div>';
                    conteudoHTML += '</div>';

                    // Pavimento Térreo
                    conteudoHTML += '<div style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #ddd;">';
                    conteudoHTML += '<h6 style="margin: 0 0 12px 0; color: #666; font-weight: bold;">Pavimento Térreo</h6>';

                    // Térreo - Usos
                    //conteudoHTML += '<div style="margin-bottom: 12px;">';
                    //conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Usos:</label>';
                    //conteudoHTML += '<div style="padding: 8px; border: 1px solid #ccc; border-radius: 3px; background-color: #f5f5f5; color: #333;">' + (dados.terreo_uso || 'Não informado') + '</div>';
                    //conteudoHTML += '</div>';

                    // Térreo - Tipo da Construção
                    conteudoHTML += '<div style="margin-bottom: 12px;">';
                    conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Tipo da Construção:</label>';
                    conteudoHTML += '<div style="padding: 8px; border: 1px solid #ccc; border-radius: 3px; background-color: #f5f5f5; color: #333;">' + (dados.terreo_tipo || 'Não informado') + '</div>';
                    conteudoHTML += '</div>';

                    // Térreo - Classificação
                    conteudoHTML += '<div style="margin-bottom: 12px;">';
                    conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Classificação:</label>';
                    conteudoHTML += '<div style="padding: 8px; border: 1px solid #ccc; border-radius: 3px; background-color: #f5f5f5; color: #333;">' + (dados.terreo_classificacao || 'Não informado') + '</div>';
                    conteudoHTML += '</div>';

                    // Térreo - Área construída
                    conteudoHTML += '<div style="margin-bottom: 15px;">';
                    conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Área construída:</label>';
                    conteudoHTML += '<div style="padding: 8px; border: 1px solid #ccc; border-radius: 3px; background-color: #f5f5f5; color: #333;">' + areaPoligono.toFixed(2) + ' m²</div>';
                    conteudoHTML += '</div>';
                    conteudoHTML += '</div>';

                    // Demais Pavimentos (só aparece se pavimentos > 1)
                    const pavimentosAtual = parseInt(dados.pavimentos) || 1;
                    const mostrarDemaisPavimentos = pavimentosAtual > 1;
                    if (mostrarDemaisPavimentos) {
                        conteudoHTML += '<div id="divDemaisPavimentosUnidade" style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #ddd;">';
                        conteudoHTML += '<h6 style="margin: 0 0 12px 0; color: #666; font-weight: bold;">Demais Pavimentos</h6>';

                        // Demais - Usos
                        //conteudoHTML += '<div style="margin-bottom: 12px;">';
                        //conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Usos:</label>';
                        //conteudoHTML += '<div style="padding: 8px; border: 1px solid #ccc; border-radius: 3px; background-color: #f5f5f5; color: #333;">' + (dados.demais_uso || 'Não informado') + '</div>';
                        //conteudoHTML += '</div>';

                        // Demais - Tipo da Construção
                        conteudoHTML += '<div style="margin-bottom: 12px;">';
                        conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Tipo da Construção:</label>';
                        conteudoHTML += '<div style="padding: 8px; border: 1px solid #ccc; border-radius: 3px; background-color: #f5f5f5; color: #333;">' + (dados.demais_tipo || 'Não informado') + '</div>';
                        conteudoHTML += '</div>';

                        // Demais - Classificação
                        conteudoHTML += '<div style="margin-bottom: 12px;">';
                        conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Classificação:</label>';
                        conteudoHTML += '<div style="padding: 8px; border: 1px solid #ccc; border-radius: 3px; background-color: #f5f5f5; color: #333;">' + (dados.demais_classificacao || 'Não informado') + '</div>';
                        conteudoHTML += '</div>';

                        // Demais - Área construída (calculada: área × (pavimentos - 1))
                        const areaDemaisPavimentos = areaPoligono * (pavimentosAtual - 1);
                        conteudoHTML += '<div style="margin-bottom: 15px;">';
                        conteudoHTML += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Área construída:</label>';
                        conteudoHTML += '<div style="padding: 8px; border: 1px solid #ccc; border-radius: 3px; background-color: #f5f5f5; color: #333;">' + areaDemaisPavimentos.toFixed(2) + ' m²</div>';
                        conteudoHTML += '</div>';
                        conteudoHTML += '</div>';
                    }

                    // Status de Revisão (somente visualização)
                    conteudoHTML += '<div style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #ddd; display: flex; gap: 10px; pointer-events: none;">';
                    conteudoHTML += '<div style="flex: 1; padding: 8px; border: 2px solid ' + (revisadoAtual == 1 ? corRevisado : '#ddd') + '; border-radius: 3px; font-weight: bold; text-align: center; background-color: ' + (revisadoAtual == 1 ? corRevisado : '#fff') + '; color: ' + (revisadoAtual == 1 ? '#000' : '#999') + ';">REVISADO</div>';
                    conteudoHTML += '<div style="flex: 1; padding: 8px; border: 2px solid ' + (revisadoAtual == 0 ? corNaoRevisado : '#ddd') + '; border-radius: 3px; font-weight: bold; text-align: center; background-color: ' + (revisadoAtual == 0 ? corNaoRevisado : '#fff') + '; color: ' + (revisadoAtual == 0 ? '#fff' : '#999') + ';">NÃO REVISADO</div>';
                    conteudoHTML += '</div>';

                    conteudoHTML += '</div>';

                    this.infoWindow.setContent(conteudoHTML);

                    // Define a cor do polígono baseado no estado de revisado
                    if (revisadoAtual == 1) {
                        poligono.setOptions({
                            strokeColor: corRevisado,
                            fillColor: corRevisado
                        });
                        poligono.corOriginal = corRevisado;
                    } else {
                        poligono.setOptions({
                            strokeColor: corNaoRevisado,
                            fillColor: corNaoRevisado
                        });
                        poligono.corOriginal = corNaoRevisado;
                    }
                } else {
                    this.infoWindow.setContent('<div style="padding: 10px; color: red;">Nenhum dado cadastrado para esta unidade</div>');
                }
            },
            error: () => {
                this.infoWindow.setContent('<div style="padding: 10px; color: red;">Erro ao carregar dados da unidade</div>');
            }
        });
    },

    obterCoordenadasObjeto: function (objeto) {
        // Extrai as coordenadas de um Polygon ou Polyline
        const path = objeto.getPath();
        const coordenadas = [];

        for (let i = 0; i < path.getLength(); i++) {
            const ponto = path.getAt(i);
            coordenadas.push({ lat: ponto.lat(), lng: ponto.lng() });
        }

        return coordenadas;
    },

    adicionarListenersEdicao: function (objeto) {
        // Adiciona listeners para detectar mudanças no desenho
        if (!objeto.editListenerAdded) {
            const addToEditedList = () => {
                if (!this.desenhosEditados.includes(objeto)) {
                    this.desenhosEditados.push(objeto);
                    console.log('📝 Desenho adicionado à lista de edição');
                    console.log('   - ID:', objeto.identificador);
                    console.log('   - Total na lista:', this.desenhosEditados.length);
                }
            };

            if (objeto instanceof google.maps.Polygon) {
                // Listeners para detectar mudanças
                google.maps.event.addListener(objeto.getPath(), 'set_at', addToEditedList);
                google.maps.event.addListener(objeto.getPath(), 'insert_at', addToEditedList);
                google.maps.event.addListener(objeto.getPath(), 'remove_at', addToEditedList);

                // Listener para deletar vértice com botão direito
                google.maps.event.addListener(objeto, 'rightclick', (e) => {
                    if (typeof e.vertex === 'number') {
                        const path = objeto.getPath();
                        const totalVertices = path.getLength();

                        if (totalVertices <= 3) {
                            alert('⚠️ Não é possível remover este vértice.\nO polígono precisa ter pelo menos 3 vértices.');
                            console.log('❌ Tentativa de remover vértice bloqueada (mínimo: 3)');
                        } else {
                            path.removeAt(e.vertex);
                            console.log('🗑️ Vértice removido do polígono');
                            console.log('   - Vértices restantes:', path.getLength());
                            addToEditedList();
                        }
                    }
                });

            } else if (objeto instanceof google.maps.Polyline) {
                // Listeners para detectar mudanças
                google.maps.event.addListener(objeto.getPath(), 'set_at', addToEditedList);
                google.maps.event.addListener(objeto.getPath(), 'insert_at', addToEditedList);
                google.maps.event.addListener(objeto.getPath(), 'remove_at', addToEditedList);

                // Listener para deletar vértice com botão direito
                google.maps.event.addListener(objeto, 'rightclick', (e) => {
                    if (typeof e.vertex === 'number') {
                        const path = objeto.getPath();
                        const totalVertices = path.getLength();

                        if (totalVertices <= 2) {
                            alert('⚠️ Não é possível remover este vértice.\nA linha precisa ter pelo menos 2 pontos.');
                            console.log('❌ Tentativa de remover vértice bloqueada (mínimo: 2)');
                        } else {
                            path.removeAt(e.vertex);
                            console.log('🗑️ Vértice removido da polilinha');
                            console.log('   - Vértices restantes:', path.getLength());
                            addToEditedList();
                        }
                    }
                });
            }

            objeto.editListenerAdded = true;
            console.log('✅ Listeners de edição adicionados ao desenho ID:', objeto.identificador);
        }
    },

    entrarModoEdicao: function () {
        this.modoEdicao = true;
        this.desenhosEditados = [];

        // Oculta botões editar e excluir
        $('#btnEditar').addClass('d-none');
        $('#btnExcluir').addClass('d-none');

        // Mostra botão sair da edição
        $('#btnSairEdicao').removeClass('d-none');

        // Se houver objeto selecionado, torna editável e adiciona listeners
        if (this.selecionado) {
            this.selecionado.setOptions({ editable: true });
            this.adicionarListenersEdicao(this.selecionado);
        }

        console.log('🔧 Modo de edição ativado');
    },

    sairModoEdicao: async function () {
        // Mostra loading
        $('#loadingOverlay').fadeIn(200);
        console.log('=== INICIANDO SAÍDA DO MODO EDIÇÃO ===');
        console.log('Total de desenhos editados:', this.desenhosEditados.length);

        try {
            // Se houver desenhos editados, salva todos
            if (this.desenhosEditados.length > 0) {
                console.log(`Salvando ${this.desenhosEditados.length} desenhos editados...`);

                // Prepara array com dados para salvar
                const dadosParaSalvar = this.desenhosEditados.map((desenho, index) => {
                    console.log(`Processando desenho ${index + 1}:`, desenho);
                    let coordenadas;

                    if (desenho instanceof google.maps.Polygon) {
                        coordenadas = this.obterCoordenadasObjeto(desenho);
                        console.log(`  - Tipo: Polígono`);
                    } else if (desenho instanceof google.maps.Polyline) {
                        coordenadas = this.obterCoordenadasObjeto(desenho);
                        console.log(`  - Tipo: Polilinha`);
                    }

                    console.log(`  - ID: ${desenho.identificador}`);
                    console.log(`  - Coordenadas extraídas: ${coordenadas.length} pontos`);

                    return {
                        id: desenho.identificador,
                        coordenadas: JSON.stringify(coordenadas)
                    };
                });

                console.log('Dados preparados para salvar:', dadosParaSalvar);

                // Salva de forma síncrona
                const response = await $.ajax({
                    url: 'atualizar_coordenadas_desenhos.php',
                    method: 'POST',
                    data: {
                        desenhos: JSON.stringify(dadosParaSalvar)
                    },
                    dataType: 'json'
                });

                if (response.status === 'sucesso') {
                    console.log('✅ Todos os desenhos foram salvos com sucesso!');
                    console.log('Resposta do servidor:', response);
                } else {
                    console.error('❌ Erro ao salvar:', response.mensagem);
                    alert('Erro ao salvar as alterações: ' + response.mensagem);
                }
            } else {
                console.log('ℹ️ Nenhum desenho foi editado, nada para salvar');
            }

            // Torna todos os desenhos não editáveis
            this.desenhosEditados.forEach(desenho => {
                desenho.setOptions({ editable: false });
            });

            // Limpa array de editados
            this.desenhosEditados = [];

            // Sai do modo de edição
            this.modoEdicao = false;

            // Oculta botão sair da edição
            $('#btnSairEdicao').addClass('d-none');

            // Se houver objeto selecionado, mostra botões normais
            if (this.selecionado) {
                $('#btnEditar').removeClass('d-none');
                $('#btnExcluir').removeClass('d-none');
            }

            console.log('=== MODO DE EDIÇÃO DESATIVADO ===');

        } catch (error) {
            console.error('❌ Erro ao sair do modo de edição:', error);
            alert('Erro ao salvar as alterações: ' + error.message);
        } finally {
            // Esconde loading
            $('#loadingOverlay').fadeOut(200);
            console.log('=== FIM DO PROCESSO ===');
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
                if ($('#new_checkLotes').is(':checked')) {
                    $("#controleDesenhosPrefeitura").hide();
                }
            } else {
                $("#controleNavegacaoQuadriculas").show();
                if ($('#new_checkLotes').is(':checked')) {
                    $("#controleDesenhosPrefeitura").show();
                }
            }
        });
    },

    // Função para mostrar marcadores apenas do quarteirão selecionado
    mostrarMarcadoresDoQuarteirao: function (nomeQuarteirao) {
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
                // Fecha o infowindow se estiver aberto
                if (this.infoWindow) {
                    this.infoWindow.close();
                }
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

    // Função para mostrar/ocultar todos os marcadores (usado pelo checkbox)
    alternarVisibilidadeTodosMarcadores: function (mostrar) {
        if (!arrayCamadas.marcador_quadra) return;

        arrayCamadas.marcador_quadra.forEach(marker => {
            if (mostrar) {
                marker.setMap(MapFramework.map);
            } else {
                marker.setMap(null);
            }
        });
    },

    atualizarInteratividadeObjetos: function (interativo) {
        const camadasInterativas = ['quadra', 'unidade', 'piscina', 'lote', 'quarteirao', 'semCamadas']; // adicione outras se necessário
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

                        if (desenho.cor_usuario) {
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
                            let zIndexValue = 5; // Padrão para quadras
                            let strokeLine = 4;
                            let strokeColorLine = cores;
                            
                            if (camadaNome === 'unidade') {
                                zIndexValue = 6; // Unidades acima das quadras
                            } else if (camadaNome === 'piscina') {
                                zIndexValue = 6; // Piscinas no mesmo nível das unidades
                                strokeLine = 1;
                                strokeColorLine = "black";
                            }

                            objeto = new google.maps.Polygon({
                                paths: coords,
                                strokeColor: strokeColorLine,
                                strokeOpacity: 1,
                                strokeWeight: strokeLine,
                                fillColor: cores,
                                fillOpacity: 0.30,
                                editable: false,
                                map: MapFramework.map,
                                zIndex: zIndexValue
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
                                zIndex: 7 // Lotes acima de tudo
                            });

                            objeto.identificador = desenho.id;
                            objeto.id_desenho = desenho.id_desenho;
                            objeto.id_quadricula = desenho.quadricula;
                        }


                        if (objeto) {
                            objeto.corOriginal = cores;
                            // Armazena o z-index original para uso posterior na seleção
                            objeto.zIndexOriginal = objeto.zIndex;

                            const destino = arrayCamadas[camadaNome] ? camadaNome : 'semCamadas';

                            adicionarObjetoNaCamada(destino, objeto);

                            google.maps.event.addListener(objeto, 'click', (event) => {
                                MapFramework.selecionarDesenho(objeto);
                                console.log(objeto.identificador)

                                if (paginaAtual == 'index_2') {
                                    // Se for polígono E for unidade, abre InfoWindow com dados da tabela informacoes_blocos
                                    if (tipo === 'poligono' && camadaNome === 'unidade' && event.latLng) {
                                        MapFramework.abrirInfoWindowUnidade(objeto, event.latLng, objeto.identificador);
                                    }
                                    // Se for polígono E não for unidade, abre InfoWindow com botões de cores
                                    else if (tipo === 'poligono' && camadaNome !== 'unidade' && camadaNome !== 'piscina' && event.latLng) {
                                        console.log(paginaAtual)
                                        MapFramework.abrirInfoWindowCores(objeto, event.latLng, desenho.id);
                                    } else {
                                        if (MapFramework.infoWindow) {
                                            MapFramework.infoWindow.close();
                                        }
                                    }
                                } else {
                                    if (tipo === 'poligono' && camadaNome === 'unidade' && event.latLng) {
                                        // Se estiver na index_2, usa versão editável; na index_3, usa versão somente leitura
                                        if (paginaAtual == 'index_3') {
                                            MapFramework.abrirInfoWindowUnidade2(objeto, event.latLng, objeto.identificador);
                                        }
                                    }
                                }
                            });
                        }
                    });

                    console.log('Desenhos carregados.');
                } else {
                    console.warn('Erro ao carregar desenhos:', response.mensagem);
                }

                // Garante que todos os objetos tenham o z-index correto após carregamento
                MapFramework.aplicarZIndexCorreto();
            },
            error: function (xhr, status, error) {
                console.error('Erro na requisição de desenhos:', error);
            }
        });
    },

    aplicarZIndexCorreto: function () {
        // Aplica z-index correto para quadras
        if (arrayCamadas['quadra']) {
            arrayCamadas['quadra'].forEach(quadra => {
                quadra.setOptions({ zIndex: 5 });
                quadra.zIndexOriginal = 5;
            });
        }

        // Aplica z-index correto para unidades
        if (arrayCamadas['unidade']) {
            arrayCamadas['unidade'].forEach(unidade => {
                unidade.setOptions({ zIndex: 6 });
                unidade.zIndexOriginal = 6;
            });
        }

        // Aplica z-index correto para piscinas
        if (arrayCamadas['piscina']) {
            arrayCamadas['piscina'].forEach(piscina => {
                piscina.setOptions({ zIndex: 6 });
                piscina.zIndexOriginal = 6;
            });
        }

        // Aplica z-index correto para lotes
        if (arrayCamadas['lote']) {
            arrayCamadas['lote'].forEach(lote => {
                lote.setOptions({ zIndex: 7 });
                lote.zIndexOriginal = 7;
            });
        }
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
            console.log("Salvando...");
            this.salvarDesenho('Quadra', 0);
        });
    },

    iniciarDesenhoUnidade: function () {
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
                    strokeColor: "#ff00ff",
                    strokeOpacity: 0.8,
                    strokeWeight: 2,
                    fillColor: "#ff00ff",
                    fillOpacity: 0.35,
                    editable: true,
                    map: this.map,
                    clickable: false,
                    zIndex: 6 // Unidades acima das quadras
                });

                this.desenho.cor = "#ff00ff";
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

            // Verifica se o polígono está sobre alguma quadra
            const resultado = this.verificarPoligonoDentroDeQuadra(this.desenho.temporario, 3);

            // Aplica a cor de validação apenas visualmente (verde/vermelho),
            // mas mantém a cor de salvamento como ciano
            this.desenho.temporario.setOptions({
                strokeColor: resultado.cor,
                fillColor: resultado.cor,
                editable: false
            });
            //seta o id_desenho
            this.desenho.temporario.id_desenho = resultado.identificador;

            // Define o identificador se encontrou uma quadra
            this.desenho.temporario.identificador = resultado.identificador;
            console.log("Salvando...");
            // Salva o desenho
            this.salvarDesenho('Unidade');
        });
    },

    iniciarDesenhoPiscina: function () {
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
                    strokeColor: "black",
                    strokeOpacity: 0.8,
                    strokeWeight: 1,
                    fillColor: "#00ffff",
                    fillOpacity: 0.35,
                    editable: true,
                    map: this.map,
                    clickable: false,
                    zIndex: 6 // similar às unidades
                });

                this.desenho.cor = "#00ffff";
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

            // Verifica se o polígono está sobre alguma quadra
            const resultado = this.verificarPoligonoDentroDeQuadra(this.desenho.temporario, 3);

            // Aplica a cor baseada no resultado
            this.desenho.temporario.setOptions({
                fillColor: resultado.cor,
                editable: false
            });

            // Mantém a cor padrão da piscina (#00ffff) para salvar
            //seta o id_desenho
            this.desenho.temporario.id_desenho = resultado.identificador;

            // Define o identificador se encontrou uma quadra
            this.desenho.temporario.identificador = resultado.identificador;
            console.log("Salvando...");
            // Salva o desenho
            this.salvarDesenho('Piscina');
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
                    zIndex: 10
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
            console.log("Salvando...");
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
        console.log("Chamando ajax...");
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
                console.log("Resposta positiva");
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
                        // Armazena o z-index original para uso posterior na seleção
                        objetoSalvo.zIndexOriginal = objetoSalvo.zIndex;

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

                        console.log("Deu tudo certo");
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

    verificarPoligonoDentroDeQuadra: function (poligonoGoogleMaps, toleranciaMetros = 3) {
        const path = poligonoGoogleMaps.getPath();
        const pontosPoligono = [];

        for (let i = 0; i < path.getLength(); i++) {
            pontosPoligono.push([path.getAt(i).lng(), path.getAt(i).lat()]);
        }

        // Fecha o polígono adicionando o primeiro ponto no final
        pontosPoligono.push(pontosPoligono[0]);

        const poligonoGeoJSON = turf.polygon([pontosPoligono]);

        const quadras = arrayCamadas["quadra"] || [];

        console.log(" Verificando polígono sobre quadras...");

        const poligonosDentro = [];

        for (let i = 0; i < quadras.length; i++) {
            const quadra = quadras[i];

            if (!quadra.coordenadasGeoJSON || !quadra.identificador) {
                console.warn("Quadra inválida (sem geojson ou identificador):", quadra);
                continue;
            }

            const buffer = turf.buffer(quadra.coordenadasGeoJSON, toleranciaMetros, { units: 'meters' });

            // Verifica se o polígono está dentro da quadra usando intersect
            const intersecao = turf.intersect(poligonoGeoJSON, buffer);

            if (intersecao) {
                // Calcula a área da interseção vs área do polígono original
                const areaIntersecao = turf.area(intersecao);
                const areaOriginal = turf.area(poligonoGeoJSON);
                const percentualIntersecao = (areaIntersecao / areaOriginal) * 100;

                // Se mais de 80% do polígono está dentro da quadra, considera válido
                if (percentualIntersecao > 80) {
                    poligonosDentro.push(quadra);
                }
            }
        }

        if (poligonosDentro.length === 1) {
            var corDesenho = poligonosDentro[0].cor;

            console.log(poligonosDentro[0])
            console.log(" Polígono está dentro da quadra:", poligonosDentro[0].identificador);
            return {
                encontrado: true,
                identificador: poligonosDentro[0].identificador,
                cor: corDesenho
            };
        } else {
            console.warn(" Polígono está fora ou sobre múltiplas quadras.");
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

            // 2. Apaga todas as linhas e polígonos com o mesmo identificador
            Object.keys(arrayCamadas).forEach(nomeCamada => {
                const novaLista = [];

                arrayCamadas[nomeCamada].forEach(obj => {
                    if ((obj instanceof google.maps.Polyline || obj instanceof google.maps.Polygon) && obj.identificador === identificador) {
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

        // Verifica se é uma unidade (polígono com camada unidade)
        const ehUnidade = objeto instanceof google.maps.Polygon &&
            arrayCamadas['unidade'] &&
            arrayCamadas['unidade'].includes(objeto);

        if (tipo === 'polilinha' || ehUnidade) {
            // Remove do mapa e da camada
            objeto.setMap(null);
            this.removerObjetoDasCamadas(objeto);

            // Remove do banco
            $.post('excluirDesenho.php', {
                cliente: cliente,
                ortofoto: ortofoto,
                identificador: identificador,
                tipo: ehUnidade ? 'poligono' : 'polilinha'
            }, function (response) {
                console.log('Resposta ao excluir:', response);
            });

            this.desselecionarDesenho();

        } else if (tipo === 'poligono') {
            if (!identificador) {
                alert("Este polígono não possui um identificador válido para exclusão.");
                return;
            }

            const confirmar = confirm(`Tem certeza que deseja excluir esta quadra ${identificador}?\n\nTodas as linhas e unidades associadas a ela também serão removidas!`);
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

            // 2. Apaga todas as unidades que pertencem a esta quadra
            console.log('Procurando unidades que pertencem à quadra:', identificador);

            arrayCamadas['unidade']
                .filter(unidade => parseInt(unidade.id_desenho) === identificador)
                .forEach(unidade => {
                    unidade.setMap(null);
                    this.removerObjetoDasCamadas(unidade);
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

    carregarCondominiosVerticaisKML: function (urlKML = 'cartografia_prefeitura/Condominios_Verticais.kml') {
        if (!window.toGeoJSON) {
            alert('toGeoJSON não está carregado!');
            return;
        }
        if (!this.map) {
            alert('O mapa ainda não foi inicializado!');
            return;
        }
        // Garante que a camada existe
        if (!arrayCamadas.condominios_verticais) arrayCamadas.condominios_verticais = [];
        // Remove linhas antigas
        arrayCamadas.condominios_verticais.forEach(obj => { if (obj.setMap) obj.setMap(null); });
        arrayCamadas.condominios_verticais = [];
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
                            strokeColor: '#d900ff',
                            clickable: false,
                            strokeOpacity: 1.0,
                            strokeWeight: 3,
                            map: this.map,
                            zIndex: 10
                        });
                        arrayCamadas.condominios_verticais.push(polyline);
                    }
                });
            });
    },

    carregarCondominiosHorizontaisKML: function (urlKML = 'cartografia_prefeitura/Condominios_Horizontais.kml') {
        if (!window.toGeoJSON) {
            alert('toGeoJSON não está carregado!');
            return;
        }
        if (!this.map) {
            alert('O mapa ainda não foi inicializado!');
            return;
        }
        // Garante que a camada existe
        if (!arrayCamadas.condominios_horizontais) arrayCamadas.condominios_horizontais = [];
        // Remove linhas antigas
        arrayCamadas.condominios_horizontais.forEach(obj => { if (obj.setMap) obj.setMap(null); });
        arrayCamadas.condominios_horizontais = [];
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
                            strokeColor: '#d900ff',
                            clickable: false,
                            strokeOpacity: 1.0,
                            strokeWeight: 3,
                            map: this.map,
                            zIndex: 10
                        });
                        arrayCamadas.condominios_horizontais.push(polyline);
                    }
                });
            });
    },

    carregarAreasPublicasKML: function (urlKML = 'cartografia_prefeitura/Areas_Publicas.kml') {
        if (!window.toGeoJSON) {
            alert('toGeoJSON não está carregado!');
            return;
        }
        if (!this.map) {
            alert('O mapa ainda não foi inicializado!');
            return;
        }
        // Garante que a camada existe
        if (!arrayCamadas.areas_publicas) arrayCamadas.areas_publicas = [];
        // Remove linhas antigas
        arrayCamadas.areas_publicas.forEach(obj => { if (obj.setMap) obj.setMap(null); });
        arrayCamadas.areas_publicas = [];
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
                            strokeColor: '#d900ff',
                            clickable: false,
                            strokeOpacity: 1.0,
                            strokeWeight: 3,
                            map: this.map,
                            zIndex: 10
                        });
                        arrayCamadas.areas_publicas.push(polyline);
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

    controlarEspessuraLotes: function (value) {
        // Converte o valor do range (0-1) para uma espessura de linha apropriada (1-10 pixels)
        const espessura = 1 + (value * 9); // Range de 1 a 10 pixels

        if (arrayCamadas['lote'] && arrayCamadas['lote'].length > 0) {
            arrayCamadas['lote'].forEach(lote => {
                lote.setOptions({
                    strokeWeight: espessura
                });
            });
        }
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

    carregarIptus: function (parametro_imod_id) {
        const self = this; // Salva referência ao MapFramework
        $.ajax({
            url: 'carregarIptus.php',
            method: 'POST',
            data: {
                imob_id: parametro_imod_id
            },
            dataType: 'json',
            success: function (response) {
                self.dadosIptus = response;
            },
            error: function (xhr, status, error) {
                console.error(error);
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
        el.addEventListener('click', function (event) {
            event.stopPropagation();
            mostrarTooltipMarcador(marker, event);
        });

        if (!arrayCamadas["marcador_quadra"]) arrayCamadas["marcador_quadra"] = [];
        arrayCamadas["marcador_quadra"].push(marker);
        adicionarObjetoNaCamada("marcador_quadra", marker);
        // Salva no banco e passa a informação se corresponde ao lote selecionado
        this.salvarMarcadorNoBanco(latLng, idQuadra, numeroLote, marker, correspondeAoLoteSelecionado, corMarcador);
    },

    salvarMarcadorNoBanco: function (latLng, idQuadra, numeroMarcador, marcadorElement, correspondeAoLoteSelecionado, corMarcador) {
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
            success: function (response) {
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
            error: function () {
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
    sairModoMarcador: function () {
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
                        el.addEventListener('click', function (event) {
                            // Busca dados do morador baseado em lote, quadra e quarteirão
                            const dadosMorador = MapFramework.dadosMoradores.find(morador =>
                                morador.lote == desenho.lote &&
                                morador.quadra == desenho.quadra &&
                                morador.cara_quarteirao == desenho.quarteirao
                            );

                            // Cria conteúdo do infowindow
                            let conteudoInfoWindow = '';

                            let tituloInicialHtml = `
                                <div style="display: flex; align-items: flex-start; margin-bottom: 15px; min-width: 550px;">
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
                                const imobId = dadosMorador.imob_id || null;

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

                                // Sistema de abas para Cadastro e IPTU
                                const infoWindowId = 'iw_' + desenho.id;
                                const abasHTML = `
                                    <div style="display: flex; border-bottom: 2px solid #ddd; margin-bottom: 10px; margin-top: 10px;">
                                        <button class="info-tab-cadastro" data-tab="cadastro" data-iwid="${infoWindowId}" style="flex: 1; padding: 8px 15px; text-align: center; cursor: pointer; background-color: #f8f9fa; border: none; border-bottom: 2px solid transparent; font-weight: 600; color: #007bff; transition: all 0.3s ease;">
                                            Cadastro
                                        </button>
                                        <button class="info-tab-iptu" data-tab="iptu" data-iwid="${infoWindowId}" style="flex: 1; padding: 8px 15px; text-align: center; cursor: pointer; background-color: #f8f9fa; border: none; border-bottom: 2px solid transparent; font-weight: 500; color: #666; transition: all 0.3s ease;">
                                            IPTU
                                        </button>
                                    </div>
                                `;

                                const conteudoCadastroHTML = `
                                    <div id="tab-cadastro-${infoWindowId}" class="tab-cadastro-content" style="display: block; line-height: 1.4;">
                                        ${camposHTML}
                                    </div>
                                `;

                                const conteudoIptuHTML = `
                                    <div id="tab-iptu-${infoWindowId}" class="tab-iptu-content" style="display: none; line-height: 1.4;">
                                        <div style="text-align: center; padding: 20px; color: #666;">
                                            <i class="fas fa-spinner fa-spin"></i> Clique na aba IPTU para carregar os dados
                                        </div>
                                    </div>
                                `;

                                conteudoInfoWindow = `
                                    <div style="padding: 0 10px 10px 10px; font-family: Arial, sans-serif;">
                                        ${tituloInicialHtml}
                                        ${dadosDesenhoHTML}
                                        <div>
                                            <h4 style="margin: 0 0 8px 0; color: #333; font-size: 14px; font-weight: bold;">Cadastro</h4>
                                            <div style="border-bottom: 1px solid #ddd; margin-bottom: 8px;"></div>
                                            ${abasHTML}
                                            ${conteudoCadastroHTML}
                                            ${conteudoIptuHTML}
                                        </div>
                                    </div>
                                `;
                            } else {
                                // Se não encontrou dados do cadastro, mostra apenas os dados do desenho
                                conteudoInfoWindow = `
                                    <div style="padding: 10px; font-family: Arial, sans-serif;">
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

                            // Adiciona eventos quando o InfoWindow estiver pronto
                            google.maps.event.addListener(MapFramework.infoWindow, 'domready', function () {
                                // Event listeners para as abas (apenas se houver dados do morador)
                                if (dadosMorador) {
                                    const currentInfoWindowId = 'iw_' + desenho.id;
                                    const imobId = dadosMorador.imob_id || null;

                                    // Remove listeners anteriores deste InfoWindow (se houver)
                                    const eventNamespace = '.infowindow-' + currentInfoWindowId;
                                    document.removeEventListener('click', null);

                                    // Event listeners para as abas usando querySelector no documento
                                    // O InfoWindow injeta o conteúdo no DOM, então podemos buscar diretamente
                                    setTimeout(function() {
                                        const tabButtons = document.querySelectorAll(`[data-iwid="${currentInfoWindowId}"]`);
                                        
                                        tabButtons.forEach(btn => {
                                            btn.addEventListener('click', function(e) {
                                                e.preventDefault();
                                                e.stopPropagation();
                                                
                                                const tabName = this.getAttribute('data-tab');
                                                const iwid = this.getAttribute('data-iwid');
                                                
                                                // Remove classe active de todas as abas deste InfoWindow
                                                const allTabs = document.querySelectorAll(`[data-iwid="${iwid}"]`);
                                                allTabs.forEach(tab => {
                                                    tab.style.fontWeight = '500';
                                                    tab.style.color = '#666';
                                                    tab.style.borderBottomColor = 'transparent';
                                                });
                                                
                                                // Adiciona classe active na aba clicada
                                                this.style.fontWeight = '600';
                                                this.style.color = '#007bff';
                                                this.style.borderBottomColor = '#007bff';
                                                
                                                // Mostra/oculta conteúdo das abas
                                                const tabCadastro = document.querySelector(`#tab-cadastro-${iwid}`);
                                                const tabIptu = document.querySelector(`#tab-iptu-${iwid}`);
                                                
                                                if (tabName === 'cadastro') {
                                                    if (tabCadastro) tabCadastro.style.display = 'block';
                                                    if (tabIptu) tabIptu.style.display = 'none';
                                                } else if (tabName === 'iptu') {
                                                    if (tabCadastro) tabCadastro.style.display = 'none';
                                                    if (tabIptu) tabIptu.style.display = 'block';
                                                    
                                                    // Carrega dados do IPTU se ainda não foram carregados
                                                    if (tabIptu && !tabIptu.dataset.loaded && imobId) {
                                                        tabIptu.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;"><i class="fas fa-spinner fa-spin"></i> Carregando dados do IPTU...</div>';
                                                        
                                                        $.ajax({
                                                            url: 'carregarIptus.php',
                                                            method: 'GET',
                                                            data: { imob_id: imobId },
                                                            dataType: 'json',
                                                            success: function(response) {
                                                                tabIptu.dataset.loaded = 'true';
                                                                
                                                                // Verifica se a resposta veio com o novo formato (objeto com dados e dicionario)
                                                                // ou no formato antigo (array direto) para manter compatibilidade
                                                                let dadosArray = [];
                                                                let dadosArray2 = [];
                                                                let dicionario = {};
                                                                let dicionario2 = {};
                                                                
                                                                if (response && typeof response === 'object') {
                                                                    if (response.dados && Array.isArray(response.dados)) {
                                                                        // Novo formato: { dados: [...], dados2: [...], dicionario: {...}, dicionario2: {...} }
                                                                        dadosArray = response.dados;
                                                                        dadosArray2 = response.dados2 || [];
                                                                        dicionario = response.dicionario || {};
                                                                        dicionario2 = response.dicionario2 || {};
                                                                    } else if (Array.isArray(response)) {
                                                                        // Formato antigo: array direto (mantém compatibilidade)
                                                                        dadosArray = response;
                                                                        dadosArray2 = [];
                                                                        dicionario = {};
                                                                        dicionario2 = {};
                                                                    }
                                                                }
                                                                
                                                                if (dadosArray && dadosArray.length > 0) {
                                                                    let content = '<div style="margin-bottom: 15px;">';
                                                                    
                                                                    dadosArray.forEach(function(iptu, index) {
                                                                        if (index > 0) {
                                                                            content += '<hr style="margin: 15px 0; border: 0; border-top: 1px solid #ddd;">';
                                                                        }
                                                                        
                                                                        Object.keys(iptu).forEach(function(key) {
                                                                            const value = iptu[key];
                                                                            if (value !== null && value !== '' && key !== 'erro') {
                                                                                // Usa o dicionário se existir, senão usa a formatação automática como fallback
                                                                                const fieldName = dicionario[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                                                                content += `<div style="margin-bottom: 3px;"><strong style="font-weight: bold; color: #333;">${fieldName}:</strong> <span style="color: #666;">${value}</span></div>`;
                                                                            }
                                                                        });
                                                                    });
                                                                    
                                                                    content += '</div>';
                                                                    
                                                                    // Adiciona seção de Composição da área construída
                                                                    if (dadosArray2 && dadosArray2.length > 0) {
                                                                        content += '<div style="margin-top: 20px; margin-bottom: 15px;">';
                                                                        content += '<h4 style="margin-bottom: 10px; font-size: 14px; font-weight: bold; color: #333;">Composição da área construída</h4>';
                                                                        content += '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">';
                                                                        
                                                                        // Cabeçalho da tabela
                                                                        content += '<thead><tr style="background-color: #f5f5f5; border-bottom: 2px solid #ddd;">';
                                                                        const colunas = ['area', 'area_construida', 'utilizacao', 'construcao', 'classificacao'];
                                                                        colunas.forEach(function(col) {
                                                                            const label = dicionario2[col] || col.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                                                            content += `<th style="padding: 8px; text-align: left; font-weight: bold; color: #333; border: 1px solid #ddd;">${label}</th>`;
                                                                        });
                                                                        content += '</tr></thead>';
                                                                        
                                                                        // Corpo da tabela
                                                                        content += '<tbody>';
                                                                        dadosArray2.forEach(function(area) {
                                                                            content += '<tr>';
                                                                            colunas.forEach(function(col) {
                                                                                const value = area[col] !== null && area[col] !== '' ? area[col] : '';
                                                                                content += `<td style="padding: 8px; border: 1px solid #ddd; color: #666;">${value}</td>`;
                                                                            });
                                                                            content += '</tr>';
                                                                        });
                                                                        content += '</tbody>';
                                                                        
                                                                        content += '</table>';
                                                                        content += '</div>';
                                                                    } else {
                                                                        // Tabela vazia se não houver dados
                                                                        content += '<div style="margin-top: 20px; margin-bottom: 15px;">';
                                                                        content += '<h4 style="margin-bottom: 10px; font-size: 14px; font-weight: bold; color: #333;">Composição da área construída</h4>';
                                                                        content += '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">';
                                                                        
                                                                        // Cabeçalho da tabela
                                                                        content += '<thead><tr style="background-color: #f5f5f5; border-bottom: 2px solid #ddd;">';
                                                                        const colunasVazias = ['area', 'area_construida', 'utilizacao', 'construcao', 'classificacao'];
                                                                        colunasVazias.forEach(function(col) {
                                                                            const label = dicionario2[col] || col.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                                                            content += `<th style="padding: 8px; text-align: left; font-weight: bold; color: #333; border: 1px solid #ddd;">${label}</th>`;
                                                                        });
                                                                        content += '</tr></thead>';
                                                                        
                                                                        // Linhas vazias
                                                                        content += '<tbody>';
                                                                        for (let i = 0; i < 3; i++) {
                                                                            content += '<tr>';
                                                                            colunasVazias.forEach(function(col) {
                                                                                content += `<td style="padding: 8px; border: 1px solid #ddd;"></td>`;
                                                                            });
                                                                            content += '</tr>';
                                                                        }
                                                                        content += '</tbody>';
                                                                        
                                                                        content += '</table>';
                                                                        content += '</div>';
                                                                    }
                                                                    
                                                                    tabIptu.innerHTML = content;
                                                                } else {
                                                                    // Verifica se houve erro na resposta
                                                                    if (response && response.erro) {
                                                                        tabIptu.innerHTML = '<div style="color: #dc3545; padding: 10px;">Erro: ' + response.erro + '<br><strong>ID buscado: ' + imobId + '</strong></div>';
                                                                    } else {
                                                                        tabIptu.innerHTML = '<div style="color: #666; font-style: italic; padding: 10px;">Nenhum dado encontrado na tabela IPTU para este imóvel<br><strong>ID buscado: ' + imobId + '</strong></div>';
                                                                    }
                                                                }
                                                            },
                                                            error: function(xhr, status, error) {
                                                                console.error('Erro ao carregar dados do IPTU:', error);
                                                                tabIptu.innerHTML = '<div style="color: #dc3545; padding: 10px;">Erro ao carregar dados do IPTU. Tente novamente.<br><strong>ID buscado: ' + imobId + '</strong></div>';
                                                            }
                                                        });
                                                    } else if (!imobId) {
                                                        tabIptu.innerHTML = '<div style="color: #666; font-style: italic; padding: 10px;">ID Imobiliário não disponível para carregar dados do IPTU</div>';
                                                    }
                                                }
                                            });
                                        });
                                    }, 100); // Pequeno delay para garantir que o DOM foi injetado
                                }

                                const btnDocs = document.querySelector('.btn-docs-morador');
                                if (btnDocs) {
                                    btnDocs.addEventListener('click', function (e) {
                                        e.preventDefault();
                                        e.stopPropagation();

                                        // Obtém o imob_id do cadastro se existir
                                        const imobId = dadosMorador ? dadosMorador.imob_id : null;

                                        if (imobId) {
                                            // Se tem imob_id, usa ele como identificador
                                            if (typeof abrirModalGerenciarDocsImovel === 'function') {
                                                abrirModalGerenciarDocsImovel(imobId, {
                                                    desenhos: desenho,
                                                    cadastro: dadosMorador
                                                });
                                            }
                                        } else {
                                            // Se não tem imob_id, cria um identificador único baseado em quarteirao_quadra_lote
                                            const identificadorUnico = `${desenho.quarteirao}_${desenho.quadra}_${desenho.lote}`;
                                            if (typeof abrirModalGerenciarDocsImovel === 'function') {
                                                abrirModalGerenciarDocsImovel(identificadorUnico, {
                                                    desenhos: desenho,
                                                    cadastro: dadosMorador
                                                });
                                            }
                                        }
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
    marcarLoteComoInserido: function (numeroLote) {
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
    passarParaProximoLote: function () {
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

        $('.opcao-lote').each(function () {
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
            $('.opcao-lote').each(function () {
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
    carregarLotesGeojson: function () {
        // Define a camada de destino - seguindo o padrão das outras funções
        var camadaLotes = "lotesPref";
        let destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';

        // Limpa a camada antes de carregar novos dados
        if (arrayCamadas[destinoLotes]) {
            arrayCamadas[destinoLotes].forEach(function (objeto) {
                if (objeto.setMap) {
                    objeto.setMap(null); // Remove do mapa
                }
            });
            arrayCamadas[destinoLotes] = []; // Limpa o array
        }

        const quadricula = dadosOrto[0]['quadricula'];
        const urlOffset = `cartografia_prefeitura/${quadricula}_offset.json`;

        // Primeiro tenta carregar o arquivo de offset
        $.ajax({
            url: urlOffset,
            type: 'GET',
            cache: false,
            dataType: 'json',
            success: function (offsetData) {
                // Arquivo de offset encontrado!
                console.log(`✓ Offset encontrado para ${quadricula}: Lat=${offsetData.offset_lat_metros}m, Lng=${offsetData.offset_lng_metros}m`);
                // Carrega o GeoJSON e aplica o offset
                MapFramework.carregarLotesComOffset(offsetData);
            },
            error: function () {
                // Sem offset salvo, carrega normalmente
                console.log(`✗ Sem offset salvo para ${quadricula}. Carregando coordenadas originais.`);
                MapFramework.carregarLotesSemOffset();
            }
        });
    },

    // Função auxiliar para carregar lotes SEM offset (coordenadas originais)
    carregarLotesSemOffset: function () {
        var camadaLotes = "lotesPref";
        let destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';

        // Requisição AJAX para carregar o arquivo GeoJSON
        $.ajax({
            url: `loteamentos_quadriculas/geojson/lotes_prefeitura_quadricula_${dadosOrto[0]['quadricula']}.geojson`,
            type: 'GET',
            cache: false,
            dataType: 'json',
            success: function (geojsonData) {
                console.log('GeoJSON dos lotes da prefeitura carregado com sucesso');
                console.log(`Número de features encontradas: ${geojsonData.features ? geojsonData.features.length : 0}`);

                // Processa cada feature do GeoJSON
                if (geojsonData && geojsonData.features) {
                    let lotesCarregados = 0;

                    geojsonData.features.forEach(function (feature, index) {
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
                                    zIndex: 5                 // Z-index mais alto para ficar por cima
                                });

                                // Adiciona InfoWindow ao polígono com os dados do GeoJSON
                                polygon.addListener('click', function (event) {
                                    // Cria conteúdo da InfoWindow formatado
                                    let conteudo = '<div style="max-width: 300px; font-family: Arial, sans-serif; line-height: 1.4;">';
                                    conteudo += '<h4 style="margin: 0 0 10px 0; color: #333; border-bottom: 2px solid #FF6B35; padding-bottom: 5px; font-size: 16px;">📍 Lote da Prefeitura</h4>';

                                    // Adiciona as propriedades do GeoJSON formatadas
                                    if (feature.properties && Object.keys(feature.properties).length > 0) {
                                        // Define a ordem desejada: Inscrição primeiro, depois Endereço
                                        const ordemPropriedades = ['name', 'ENDERECO'];
                                        const propriedadesExibidas = new Set();

                                        // Primeiro, exibe as propriedades na ordem específica
                                        ordemPropriedades.forEach(function (key) {
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
                                        Object.keys(feature.properties).forEach(function (key) {
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
            error: function (xhr, status, error) {
                console.error('Erro ao carregar lotes da prefeitura:', error);
            }
        });
    },

    // Função auxiliar para carregar lotes COM offset (aplicando deslocamento)
    carregarLotesComOffset: function (offsetData) {
        var camadaLotes = "lotesPref";
        let destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';

        const offsetLatMetros = offsetData.offset_lat_metros;
        const offsetLngMetros = offsetData.offset_lng_metros;

        console.log(`Aplicando offset: Lat=${offsetLatMetros}m, Lng=${offsetLngMetros}m`);

        // Requisição AJAX para carregar o arquivo GeoJSON original
        $.ajax({
            url: `loteamentos_quadriculas/geojson/lotes_prefeitura_quadricula_${dadosOrto[0]['quadricula']}.geojson`,
            type: 'GET',
            cache: false,
            dataType: 'json',
            success: function (geojsonData) {
                console.log('GeoJSON original carregado. Aplicando offset...');

                // Função auxiliar para converter metros para graus
                function metrosParaGraus(metros, latitude) {
                    const grausLat = metros / 111320;
                    const grausLng = metros / (111320 * Math.cos(latitude * Math.PI / 180));
                    return { lat: grausLat, lng: grausLng };
                }

                // Processa cada feature do GeoJSON
                if (geojsonData && geojsonData.features) {
                    let lotesCarregados = 0;

                    geojsonData.features.forEach(function (feature, index) {
                        if (feature.geometry && feature.geometry.type === 'Polygon' && feature.geometry.coordinates) {
                            try {
                                // Converte coordenadas e APLICA O OFFSET
                                const coordinates = feature.geometry.coordinates[0].map(coord => {
                                    const lat = coord[1];
                                    const lng = coord[0];

                                    // Calcula o offset em graus para esta coordenada
                                    const grausOffset = metrosParaGraus(1, lat);

                                    return {
                                        lat: lat + (offsetLatMetros * grausOffset.lat),
                                        lng: lng + (offsetLngMetros * grausOffset.lng)
                                    };
                                });

                                // Cria o polígono com as coordenadas ajustadas
                                const polygon = new google.maps.Polygon({
                                    paths: coordinates,
                                    strokeColor: '#FF6B35',
                                    strokeOpacity: 0.8,
                                    strokeWeight: 3,
                                    fillColor: '#FF6B35',
                                    fillOpacity: 0.3,
                                    map: null,
                                    clickable: true,
                                    zIndex: 5
                                });

                                // Adiciona InfoWindow com indicação de ajuste
                                polygon.addListener('click', function (event) {
                                    let conteudo = '<div style="max-width: 300px; font-family: Arial, sans-serif; line-height: 1.4;">';
                                    conteudo += '<h4 style="margin: 0 0 10px 0; color: #333; border-bottom: 2px solid #FF6B35; padding-bottom: 5px; font-size: 16px;">📍 Lote da Prefeitura (Ajustado)</h4>';

                                    if (feature.properties && Object.keys(feature.properties).length > 0) {
                                        const ordemPropriedades = ['name', 'ENDERECO'];
                                        const propriedadesExibidas = new Set();

                                        ordemPropriedades.forEach(function (key) {
                                            if (feature.properties[key] !== null &&
                                                feature.properties[key] !== '' &&
                                                feature.properties[key] !== undefined) {

                                                let labelFormatada;
                                                if (key === 'name') {
                                                    labelFormatada = 'Inscrição';
                                                } else if (key === 'ENDERECO') {
                                                    labelFormatada = 'Endereço';
                                                } else {
                                                    labelFormatada = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                                }

                                                conteudo += `<p style="margin: 5px 0; font-size: 13px;"><strong>${labelFormatada}:</strong> ${feature.properties[key]}</p>`;
                                                propriedadesExibidas.add(key);
                                            }
                                        });
                                    }

                                    conteudo += '<hr style="margin: 10px 0; border: none; border-top: 1px solid #ddd;">';
                                    conteudo += `<p style="margin: 5px 0; font-size: 11px; color: #28a745;"><strong>✓ Offset aplicado:</strong> ${offsetLatMetros}m (Lat), ${offsetLngMetros}m (Lng)</p>`;
                                    conteudo += '</div>';

                                    if (MapFramework.infoWindow) {
                                        MapFramework.infoWindow.setContent(conteudo);
                                        MapFramework.infoWindow.setPosition(event.latLng);
                                        MapFramework.infoWindow.open(MapFramework.map);
                                    }
                                });

                                // Adiciona à camada
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

                    console.log(`✓ ${lotesCarregados} lotes carregados COM OFFSET aplicado!`);
                }
            },
            error: function (xhr, status, error) {
                console.error('Erro ao carregar GeoJSON:', error);
            }
        });
    },

    // ============================================================================
    // FUNÇÃO PARA MOSTRAR/OCULTAR LOTES DA PREFEITURA
    // ============================================================================
    // Esta função controla a visibilidade dos lotes carregados do GeoJSON
    // ============================================================================
    toggleLotesGeojson: function (mostrar) {
        var camadaLotes = "lotesPref";
        let destinoLotes = arrayCamadas[camadaLotes] ? camadaLotes : 'semCamadas';

        // Se não há lotes carregados e o usuário quer mostrar, carrega primeiro
        if ((!arrayCamadas[destinoLotes] || arrayCamadas[destinoLotes].length === 0) && mostrar) {
            this.carregarLotesGeojson();

            // Aguarda um pouco para os lotes serem carregados e depois mostra
            setTimeout(() => {
                if (arrayCamadas[destinoLotes] && arrayCamadas[destinoLotes].length > 0) {
                    arrayCamadas[destinoLotes].forEach(function (polygon) {
                        if (polygon.setMap) {
                            polygon.setMap(mostrar ? MapFramework.map : null);
                        }
                    });
                }
            }, 2000); // Aguarda 2 segundos para o carregamento
        } else if (arrayCamadas[destinoLotes]) {
            // Se já há lotes carregados, apenas mostra/oculta
            arrayCamadas[destinoLotes].forEach(function (polygon) {
                if (polygon.setMap) {
                    polygon.setMap(mostrar ? MapFramework.map : null);
                }
            });
        }
    },

    carregarImagensAereas: function (quadricula) {

        let paramsTxt = "";
        let caminhoUndistorted = "";

        $.ajax({
            url: `buscaJsonImagens.php?quadricula=${quadricula}`,
            //url: `imagens_aereas/${quadricula}_imagens.json`,
            method: 'GET',
            cache: false,
            dataType: 'json',
            success: (response) => {
                //console.log(response);

                if (response?.pastas_especiais?.pasta_params?.caminho !== undefined) {
                    paramsTxt = response.pastas_especiais.pasta_params.caminho;
                }

                if (response?.pastas_especiais?.pasta_undistorted?.caminho !== undefined) {
                    caminhoUndistorted = response.pastas_especiais.pasta_undistorted.caminho;
                }

                if ((paramsTxt || paramsTxt != "") && (caminhoUndistorted || caminhoUndistorted != "")) {
                    this.carregarImagensAereas2(paramsTxt, quadricula, caminhoUndistorted);
                }

            },
            error: (error) => {
                console.error('Erro ao carregar imagens aéreas:', error);
            }
        });
    },

    carregarImagensAereas2: function (paramsTxt, quadricula, caminhoUndistorted) {

        // Limpa a camada antes de carregar novos dados
        if (arrayCamadas['imagens_aereas'] && arrayCamadas['imagens_aereas'].length > 0) {
            arrayCamadas['imagens_aereas'].forEach(marker => {
                if (marker.setMap) {
                    marker.setMap(null);
                }
            });
        }

        arrayCamadas['imagens_aereas'] = [];

        $.ajax({
            url: `buscarImagensAereas.php`,
            method: 'GET',
            cache: false,
            dataType: 'json',
            data: {
                caminho: `${paramsTxt}\\${quadricula}_calibrated_external_camera_parameters_wgs84.txt`
            },
            success: (response) => {
                console.log(response);
                // Para cada objeto, cria um marker
                response.forEach((imagem, index) => {
                    try {
                        // Verifica se tem latitude e longitude
                        if (!imagem.latitude || !imagem.longitude) {
                            return;
                        }

                        // Cria o elemento HTML do marcador (flecha rotacionável pelo Kappa)
                        const markerElement = document.createElement('div');
                        markerElement.className = 'marker-imagem-aerea';
                        markerElement.style.width = '24px';
                        markerElement.style.height = '24px';
                        markerElement.style.display = 'flex';
                        markerElement.style.alignItems = 'center';
                        markerElement.style.justifyContent = 'center';
                        markerElement.style.cursor = 'pointer';
                        markerElement.style.filter = 'drop-shadow(2px 2px 2px rgba(0,0,0,0.5))';
                        markerElement.title = imagem.imageName || 'Imagem Aérea';

                        // Calcula o ângulo em graus a partir do Kappa (auto detect radianos x graus)
                        const kappaValor = parseFloat(imagem.Kappa);
                        const kappaEhValido = Number.isFinite(kappaValor);
                        const kappaEmGraus = kappaEhValido
                            ? (Math.abs(kappaValor) <= (2 * Math.PI) ? (kappaValor * 180 / Math.PI) : kappaValor)
                            : 0;
                        // Define se Kappa já está em graus (padrão: true)
                        const KAPPA_EH_GRAUS = true;
                        // Converte para graus somente se optar por autodetecção
                        const graus = kappaEhValido
                            ? (KAPPA_EH_GRAUS ? kappaValor : (Math.abs(kappaValor) <= (2 * Math.PI) ? (kappaValor * 180 / Math.PI) : kappaValor))
                            : 0;
                        const heading = ((graus % 360) + 360) % 360; // 0..360
                        // CSS rotate(+) é horário; para respeitar + anti-horário, usamos 360 - heading
                        const baseOffsetDeg = 0; // ajuste fino se necessário (ex.: 90, -90, 180)
                        let anguloFinalDeg = (360 - heading + baseOffsetDeg) % 360;

                        // SVG da flecha apontando para cima (0deg = Norte), rotacionada pelo Kappa
                        const svgNs = 'http://www.w3.org/2000/svg';
                        const svg = document.createElementNS(svgNs, 'svg');
                        svg.setAttribute('viewBox', '0 0 396.433 396.433');
                        svg.setAttribute('width', '24');
                        svg.setAttribute('height', '24');
                        svg.style.transform = `rotate(${anguloFinalDeg}deg)`;
                        svg.style.transformOrigin = '50% 50%';

                        const path = document.createElementNS(svgNs, 'path');
                        path.setAttribute('d', 'M 178.308 45.906 C 184.803 32.955 191.297 26.479 197.792 26.479 C 204.287 26.479 210.781 32.955 217.276 45.906 L 293.302 197.51 L 369.328 349.114 C 375.823 362.065 377.446 371.778 374.199 378.254 C 370.952 384.729 361.933 395.483 349.844 387.967 C 298.158 355.834 194.411 292.175 194.411 292.175 L 45.74 387.967 C 32.751 387.967 24.632 384.729 21.385 378.254 C 18.138 371.778 19.761 362.065 26.256 349.114 L 102.282 197.51 L 178.308 45.906 Z');
                        path.setAttribute('style', 'stroke: rgb(0, 0, 0); fill: rgb(71, 153, 255); stroke-width: 10px;');
                        svg.appendChild(path);
                        markerElement.appendChild(svg);

                        // Cria o Advanced Marker
                        const marker = new google.maps.marker.AdvancedMarkerElement({
                            position: {
                                lat: parseFloat(imagem.latitude),
                                lng: parseFloat(imagem.longitude)
                            },
                            content: markerElement,
                            gmpClickable: true,
                            gmpDraggable: false,
                            title: imagem.imageName || 'Imagem Aérea',
                            zIndex: 100
                        });

                        // Armazena dados da imagem no marker
                        marker.dadosImagem = imagem;
                        // Usa barra invertida do Windows
                        marker.caminhoImagem = `${caminhoUndistorted}\\${imagem.imageName}`;

                        // Adiciona evento de clique para abrir InfoWindow
                        markerElement.addEventListener('click', function (event) {
                            event.stopPropagation();
                            MapFramework.abrirInfoWindowImagemAerea(marker);
                        });

                        // Adiciona o marker à camada (inicialmente não visível)
                        marker.setMap(null);
                        arrayCamadas['imagens_aereas'].push(marker);

                    } catch (error) {
                        // Silenciosamente ignora erros
                    }
                });
            },
            error: (error) => {
                // Silenciosamente ignora erros
            }
        });

    },

    abrirInfoWindowImagemAerea: function (marker) {
        // Fecha InfoWindow anterior se existir
        if (this.infoWindow) {
            this.infoWindow.close();
        }

        const imagem = marker.dadosImagem;
        const caminhoImagem = marker.caminhoImagem;

        // Cria conteúdo HTML inicial com loading
        let conteudoLoading = `
            <div style="padding: 10px; font-family: Arial, sans-serif; min-width: 500px; min-height: 100px; text-align: center;">
                <div style="padding: 40px 0;">
                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p style="margin-top: 10px; color: #666; font-size: 14px;">Carregando imagem...</p>
                </div>
            </div>
        `;

        // Cria e abre o InfoWindow imediatamente com loading
        this.infoWindow = new google.maps.InfoWindow({
            content: conteudoLoading,
            position: marker.position,
            maxWidth: 520
        });

        this.infoWindow.open(this.map);

        // Busca a imagem via AJAX usando o script PHP
        $.ajax({
            url: 'buscarImagemAerea.php',
            method: 'GET',
            data: {
                caminho: caminhoImagem
            },
            xhrFields: {
                responseType: 'blob'
            },
            success: function (blob) {
                // Converte blob para URL
                const imageUrl = URL.createObjectURL(blob);

                // Atualiza o conteúdo do InfoWindow com a imagem e botões de rotação
                let conteudoImagem = `
                    <div style="padding: 5px; font-family: Arial, sans-serif;">
                        <div style="position: relative; display: inline-block;">
                            <img 
                                id="imagemAerea" 
                                src="${imageUrl}" 
                                style="width: 400px; height: auto; cursor: pointer; border-radius: 5px; display: block; transform-origin: 50% 50%; transition: transform 120ms ease;"
                                title="Clique para abrir em tamanho real"
                            />
                            <button id="btnRotateLeft" type="button" title="Girar 90° à esquerda" 
                                style="position:absolute; top:6px; left:6px; background:#ffffffdd; border:1px solid #999; border-radius:4px; padding:4px 6px; font-size:12px; cursor:pointer;">
                                ⟲
                            </button>
                            <button id="btnRotateRight" type="button" title="Girar 90° à direita" 
                                style="position:absolute; top:6px; right:6px; background:#ffffffdd; border:1px solid #999; border-radius:4px; padding:4px 6px; font-size:12px; cursor:pointer;">
                                ⟳
                            </button>
                        </div>
                    </div>
                `;

                // Atualiza o conteúdo diretamente
                MapFramework.infoWindow.setContent(conteudoImagem);

                // Adiciona evento de clique na imagem após atualizar
                setTimeout(function () {
                    const imgElement = document.getElementById('imagemAerea');
                    const btnLeft = document.getElementById('btnRotateLeft');
                    const btnRight = document.getElementById('btnRotateRight');
                    if (imgElement) {
                        let rotationDeg = 0; // estado local de rotação

                        imgElement.addEventListener('click', function () {
                            const urlVisualizar = `visualizarImagem.php?caminho=${encodeURIComponent(caminhoImagem)}&rot=${encodeURIComponent(rotationDeg)}`;
                            window.open(urlVisualizar, '_blank');
                        });

                        const applyRotation = function () {
                            // rotação acumulativa sem normalizar (permite "infinito")
                            imgElement.style.transform = `rotate(${rotationDeg}deg)`;
                        };

                        if (btnLeft) {
                            btnLeft.addEventListener('click', function (e) {
                                e.stopPropagation();
                                rotationDeg -= 90;
                                applyRotation();
                            });
                        }
                        if (btnRight) {
                            btnRight.addEventListener('click', function (e) {
                                e.stopPropagation();
                                rotationDeg += 90;
                                applyRotation();
                            });
                        }
                    }
                }, 100);
            },
            error: function (xhr, status, error) {
                // Em caso de erro, atualiza com mensagem de erro
                let conteudoErro = `
                    <div style="padding: 20px; text-align: center; color: #dc3545; font-family: Arial, sans-serif;">
                        <p><strong>❌ Erro ao carregar a imagem</strong></p>
                    </div>
                `;

                MapFramework.infoWindow.setContent(conteudoErro);
            }
        });
    }
};