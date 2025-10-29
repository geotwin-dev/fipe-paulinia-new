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
        echo 'erro';
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

    <!-- PDF Viewer Integrado -->
    <script src="pdfViewerIntegrado.js"></script>

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
    </style>
</head>

<body>
    <div class="divContainer">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">

                <!-- Botões -->
                <div class="d-flex align-items-center flex-grow-1 gap-2">

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
                                        Blocos
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkLotes" checked>
                                    <label class="form-check-label" for="chkLotes">
                                        Lotes Ortofoto
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
                                        Marcadores
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
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkModoCadastro">
                                    <label class="form-check-label" for="chkModoCadastro">
                                        Loteamentos
                                    </label>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <button id="btnIncluirPoligono" class="btn btn-primary">Quadra</button>
                    <button id="btnIncluirBloco" class="btn" style="background-color: #ff00ff; color: white;">Bloco</button>
                    <button id="btnIncluirLinha" class="btn btn-success">Linha</button>
                      
                    <button id="btnIncluirMarcador" class="btn btn-danger d-none">Marcador</button>
                    <input type="text" id="inputLoteAtual" class="form-control" style="width: 80px; display: none;" placeholder="Lote">
                    <input type="text" id="inputQuadraAtual" class="form-control" style="width: 80px; display: none;" placeholder="Quadra">

                </div>
            </div>
        </nav>
        <div id="map"></div>
    </div>


    <script>

    </script>
</body>

</html>