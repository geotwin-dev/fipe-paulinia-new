import base64
import mimetypes
import zlib
import os
import json
import threading
import uuid
from pathlib import Path
from math import ceil
from datetime import datetime
from flask import Flask, request, jsonify, Response, abort

app = Flask(__name__)

# CORS manual (sem biblioteca externa)
@app.after_request
def after_request(response):
    response.headers.add('Access-Control-Allow-Origin', '*')
    response.headers.add('Access-Control-Allow-Headers', 'Content-Type,Authorization')
    response.headers.add('Access-Control-Allow-Methods', 'GET,PUT,POST,DELETE,OPTIONS')
    return response

@app.get("/ping")
def ping():
    return jsonify(ok=True, msg="pong")

@app.route('/analisar_imagem', methods=['POST', 'GET'])
def analisar_imagem():
    """
    Rota para testar recebimento de dados do PHP
    """
    # Obter dados da requisição
    data = request.get_json()
    return jsonify(data)

#----------------------- APPs Paulinia - criado por Matheus em 26-03-2025 ---------------------------------

# Tamanho de cada bloco para transferência (em bytes)
BLOCK_SIZE = 1024 * 1024  # 1 MB por bloco

@app.route('/obter_estrutura_arquivos', methods=['GET'])
def obter_estrutura_arquivos():
    caminho = request.args.get('caminho')
    if not caminho or not os.path.exists(caminho):
        return jsonify({'erro': 'Caminho inválido ou não encontrado'}), 400

    print(f"--> Rota 'obter_estrutura_arquivos' ativada com caminho = {caminho}")

    estrutura = []
    for root, dirs, files in os.walk(caminho):
        for file in files:
            full_path = os.path.join(root, file)
            try:
                stat = os.stat(full_path)
                estrutura.append({
                    'caminho': os.path.relpath(full_path, caminho),
                    'tamanho_bytes': stat.st_size,
                    'data_hora': datetime.fromtimestamp(stat.st_mtime).strftime('%d/%m/%Y %H:%M:%S')
                })
            except Exception as e:
                print(f'Erro ao processar {full_path}: {e}')
                continue

    return Response(json.dumps(estrutura, indent=4, ensure_ascii=False), mimetype='application/json')

@app.route('/obter_info_arquivo', methods=['GET'])
def obter_info_arquivo():
    caminho = request.args.get('caminho')
    if not caminho or not os.path.isfile(caminho):
        return jsonify({'erro': 'Arquivo não encontrado'}), 404

    try:
        stat = os.stat(caminho)
        tamanho = stat.st_size
        total_blocos = ceil(tamanho / BLOCK_SIZE)

        with open(caminho, 'rb') as f:
            crc = zlib.crc32(f.read()) & 0xFFFFFFFF

        info = {
            'caminho': caminho,
            'tamanho_bytes': tamanho,
            'data_hora': datetime.fromtimestamp(stat.st_mtime).strftime('%d/%m/%Y %H:%M:%S'),
            'crc32': format(crc, '08X'),
            'total_blocos': total_blocos
        }
        return Response(json.dumps(info, indent=4, ensure_ascii=False), mimetype='application/json')
    except Exception as e:
        print(f'Erro ao obter info de {caminho}: {e}')
        abort(500)

@app.route('/obter_conteudo_arquivo', methods=['GET'])
def obter_conteudo_arquivo():
    caminho = request.args.get('caminho')
    numero_da_parte = int(request.args.get('numero_da_parte', 0))

    print( f"--> Rota 'obter_conteudo_arquivo' ativada com 'caminho = {caminho}' - parte {numero_da_parte}" )    

    if not caminho or not os.path.isfile(caminho):
        return jsonify({'erro': 'Arquivo não encontrado'}), 404

    try:
        with open(caminho, 'rb') as f:
            f.seek(numero_da_parte * BLOCK_SIZE)
            dados = f.read(BLOCK_SIZE)
            base64_data = base64.b64encode(dados).decode('utf-8')

        estrutura = {
            'caminho': caminho,
            'numero_da_parte': numero_da_parte,
            'base64_conteudo': base64_data
        }
        return Response( json.dumps( estrutura, indent=4, ensure_ascii=False ), mimetype='application/json' )

    except Exception as e:
        print(f'Erro ao ler bloco {numero_da_parte} do arquivo {caminho}: {e}')
        abort(500)
        
@app.route('/registrar_log_launcher', methods=['POST'])
def registrar_log():
    dados = request.get_json()
    usuario = dados.get('usuario')
    acao = dados.get('acao')
    area_name = dados.get('area_name')
    timestamp = dados.get('timestamp')  # opcional

    print(f"--> Rota 'registrar_log_launcher' ativada por '{usuario}' - ação: {acao} - área: {area_name}")

    if not usuario or not acao or not area_name:
        return jsonify({'erro': 'Campos obrigatórios: usuario, acao, area_name'}), 400

    # Lista de usuários a serem ignorados (devs, testadores, etc.)
    USUARIOS_IGNORADOS = {'matheus.prates', 'admin', 'dev', 'teste'}

    if usuario.lower() in USUARIOS_IGNORADOS:
        print(f"--> Log ignorado para usuário '{usuario}' (usuário interno)")
        return jsonify({'mensagem': 'Log ignorado para usuário interno'}), 200

    if not timestamp:
        timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    log_dir = r"E:\GIS4D Workspace\PAULINIA\_logs"
    os.makedirs(log_dir, exist_ok=True)

    log_file_path = os.path.join(log_dir, "log_geral.txt")

    linha_log = f"{timestamp} - usuario: {usuario} - área: {area_name} - ação: {acao}\n"

    try:
        with open(log_file_path, 'a', encoding='utf-8') as f:
            f.write(linha_log)

        print(f"--> Log registrado: {linha_log.strip()}")
        return jsonify({'mensagem': 'Log registrado com sucesso'}), 200
    except Exception as e:
        print(f"Erro ao registrar log: {e}")
        return jsonify({'erro': 'Erro interno ao salvar o log'}), 500


#------------------------------------------------------- fim das inclusões em 26-03-2025 por Charles com o Matheus (novas rotas para gerenciar e fazer download de arquivos em estruturas complexas de pastas)



#----------------------- APPs Paulinia - criado por Matheus em 26-03-2025 ---------------------------------
# Basepath fixo
BASE_PATH_TCU_ARQUIVOS_GEMEOS = r"Y:\tcu2\arquivosGemeos"

@app.route('/salvar_offset', methods=['POST'])
def salvar_offset():
    dados = request.get_json()

    id_obra = dados.get('id_obra')
    id_trecho = dados.get('id_trecho')
    id_modelo = dados.get('id_modelo')
    offsetZ = dados.get('offsetZ')

    if id_obra is None or id_trecho is None or id_modelo is None or offsetZ is None:
        return jsonify({'erro': 'Campos obrigatórios: id_obra, id_trecho, id_modelo, offsetZ'}), 400

    # Monta o path do modelo
    modelo_path = os.path.join(BASE_PATH_TCU_ARQUIVOS_GEMEOS, str(id_obra), str(id_trecho), str(id_modelo))
    parametros_path = os.path.join(modelo_path, "parametros")
    os.makedirs(parametros_path, exist_ok=True)

    # Arquivo JSON de parâmetros
    parametros_file = os.path.join(parametros_path, "parametros.json")

    # Estrutura de dados
    dados_parametros = {
        "offsetZ": float(offsetZ),
        "atualizado_em": datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    }

    try:
        with open(parametros_file, 'w', encoding='utf-8') as f:
            json.dump(dados_parametros, f, indent=4, ensure_ascii=False)

        print(f"--> Offset salvo em {parametros_file}: {dados_parametros}")
        return jsonify({'mensagem': 'Offset salvo com sucesso'}), 200
    except Exception as e:
        print(f"Erro ao salvar offset: {e}")
        return jsonify({'erro': 'Erro interno ao salvar offset'}), 500


@app.route('/obter_offset', methods=['GET'])
def obter_offset():
    id_obra = request.args.get('id_obra')
    id_trecho = request.args.get('id_trecho')
    id_modelo = request.args.get('id_modelo')

    if id_obra is None or id_trecho is None or id_modelo is None:
        return jsonify({'erro': 'Parâmetros obrigatórios: id_obra, id_trecho, id_modelo'}), 400

    parametros_file = os.path.join(BASE_PATH_TCU_ARQUIVOS_GEMEOS, str(id_obra), str(id_trecho), str(id_modelo), "parametros", "parametros.json")

    if not os.path.exists(parametros_file):
        return jsonify({'offsetZ': 0.0, 'mensagem': 'Nenhum offset encontrado, retornando padrão 0.0'}), 200

    try:
        with open(parametros_file, 'r', encoding='utf-8') as f:
            dados_parametros = json.load(f)

        return jsonify(dados_parametros), 200
    except Exception as e:
        print(f"Erro ao ler offset: {e}")
        return jsonify({'erro': 'Erro interno ao ler offset'}), 500


#----------------------- Geração de GeoTIFF do Crop - criado em 2025 ---------------------------------

# Dicionário para armazenar status dos jobs de GeoTIFF
geotiff_jobs = {}

# Caminhos base
BASE_PATH_PROJETO = r"D:\Xampp_novo\htdocs\fipe-paulinia"
BASE_PATH_GEOTIFFS = os.path.join(BASE_PATH_PROJETO, "geotiffs")

@app.route('/gerar_geotiff_crop', methods=['POST'])
def gerar_geotiff_crop():
    """
    Rota para gerar GeoTIFF do crop de forma assíncrona
    Recebe: coordenadas_crop, bounds, quadricula, zoom
    Retorna: job_id para acompanhar o processamento
    """
    try:
        dados = request.get_json()
        
        coordenadas_crop = dados.get('coordenadas_crop')
        bounds = dados.get('bounds')
        quadricula = dados.get('quadricula')
        zoom = dados.get('zoom', 22)
        
        if not coordenadas_crop or not bounds or not quadricula:
            return jsonify({'erro': 'Campos obrigatórios: coordenadas_crop, bounds, quadricula'}), 400
        
        # Gera ID único para o job
        job_id = str(uuid.uuid4())
        
        # Inicializa status do job
        geotiff_jobs[job_id] = {
            'status': 'processando',
            'progresso': 0,
            'arquivo': None,
            'erro': None
        }
        
        # Inicia processamento em thread separada
        thread = threading.Thread(
            target=processar_geotiff_crop,
            args=(job_id, coordenadas_crop, bounds, quadricula, zoom)
        )
        thread.daemon = True
        thread.start()
        
        return jsonify({
            'job_id': job_id,
            'mensagem': 'Processamento iniciado',
            'status': 'processando'
        }), 202
        
    except Exception as e:
        print(f"Erro ao iniciar geração de GeoTIFF: {e}")
        return jsonify({'erro': f'Erro interno: {str(e)}'}), 500


@app.route('/status_geotiff/<job_id>', methods=['GET'])
def status_geotiff(job_id):
    """
    Rota para verificar status do processamento do GeoTIFF
    """
    if job_id not in geotiff_jobs:
        return jsonify({'erro': 'Job não encontrado'}), 404
    
    status = geotiff_jobs[job_id]
    
    resposta = {
        'job_id': job_id,
        'status': status['status'],
        'progresso': status['progresso']
    }
    
    if status['status'] == 'concluido':
        resposta['arquivo'] = status['arquivo']
        resposta['url_download'] = f'/download_geotiff/{os.path.basename(status["arquivo"])}'
    elif status['status'] == 'erro':
        resposta['erro'] = status['erro']
    
    return jsonify(resposta), 200


@app.route('/download_geotiff/<filename>', methods=['GET'])
def download_geotiff(filename):
    """
    Rota para download do GeoTIFF gerado
    """
    arquivo_path = os.path.join(BASE_PATH_GEOTIFFS, filename)
    
    if not os.path.exists(arquivo_path):
        return jsonify({'erro': 'Arquivo não encontrado'}), 404
    
    try:
        def generate():
            with open(arquivo_path, 'rb') as f:
                while True:
                    chunk = f.read(8192)
                    if not chunk:
                        break
                    yield chunk
        
        return Response(
            generate(),
            mimetype='image/tiff',
            headers={
                'Content-Disposition': f'attachment; filename={filename}'
            }
        )
    except Exception as e:
        print(f"Erro ao fazer download do GeoTIFF: {e}")
        return jsonify({'erro': 'Erro ao fazer download'}), 500


def processar_geotiff_crop(job_id, coordenadas_crop, bounds, quadricula, zoom):
    """
    Função que processa a geração do GeoTIFF em background
    """
    try:
        # Atualiza progresso
        geotiff_jobs[job_id]['progresso'] = 10
        
        # Importa bibliotecas necessárias (dentro da função para não bloquear se não estiverem instaladas)
        try:
            from PIL import Image
            import numpy as np
            from shapely.geometry import Polygon, Point
            import rasterio
            from rasterio.transform import from_bounds
            from rasterio.crs import CRS
        except ImportError as e:
            raise Exception(f"Biblioteca não instalada: {e}. Instale: pip install pillow numpy shapely rasterio")
        
        geotiff_jobs[job_id]['progresso'] = 20
        
        # Caminho dos tiles
        tiles_path = os.path.join(BASE_PATH_PROJETO, "quadriculas", quadricula, "google_tiles", str(zoom))
        
        if not os.path.exists(tiles_path):
            raise Exception(f"Pasta de tiles não encontrada: {tiles_path}")
        
        geotiff_jobs[job_id]['progresso'] = 30
        
        # Converte bounds para coordenadas Web Mercator (EPSG:3857) para calcular tiles
        # Bounds recebidos estão em WGS84 (EPSG:4326)
        north = bounds['north']
        south = bounds['south']
        east = bounds['east']
        west = bounds['west']
        
        # Calcula quais tiles cobrem a área do crop
        # Converte bounds WGS84 para Web Mercator para calcular tiles
        import math
        
        def lat_to_y(lat, zoom):
            """Converte latitude para coordenada Y do tile"""
            return int((1 - math.log(math.tan(math.radians(lat)) + 1 / math.cos(math.radians(lat))) / math.pi) / 2 * (2 ** zoom))
        
        def lng_to_x(lng, zoom):
            """Converte longitude para coordenada X do tile"""
            return int((lng + 180) / 360 * (2 ** zoom))
        
        # Calcula range de tiles
        x_min = lng_to_x(west, zoom)
        x_max = lng_to_x(east, zoom)
        y_min = lat_to_y(north, zoom)
        y_max = lat_to_y(south, zoom)
        
        geotiff_jobs[job_id]['progresso'] = 40
        
        # Carrega e monta mosaico dos tiles
        tiles_imagens = []
        tiles_coords = []
        
        total_tiles = (x_max - x_min + 1) * (y_max - y_min + 1)
        tiles_carregados = 0
        
        for x in range(x_min, x_max + 1):
            for y in range(y_min, y_max + 1):
                # Y invertido (como no código JavaScript)
                inverted_y = (2 ** zoom) - y - 1
                
                tile_path = os.path.join(tiles_path, str(x), f"{inverted_y}.png")
                
                if os.path.exists(tile_path):
                    try:
                        img = Image.open(tile_path)
                        tiles_imagens.append((x, y, img))
                        tiles_coords.append((x, y))
                        tiles_carregados += 1
                    except Exception as e:
                        print(f"Erro ao carregar tile {tile_path}: {e}")
                        continue
        
        if not tiles_imagens:
            raise Exception("Nenhum tile encontrado para a área do crop")
        
        geotiff_jobs[job_id]['progresso'] = 60
        
        # Calcula dimensões do mosaico
        x_coords = [c[0] for c in tiles_coords]
        y_coords = [c[1] for c in tiles_coords]
        x_min_tile = min(x_coords)
        x_max_tile = max(x_coords)
        y_min_tile = min(y_coords)
        y_max_tile = max(y_coords)
        
        width_tiles = x_max_tile - x_min_tile + 1
        height_tiles = y_max_tile - y_min_tile + 1
        tile_size = 256
        
        # Cria imagem do mosaico
        mosaico = Image.new('RGB', (width_tiles * tile_size, height_tiles * tile_size))
        
        for x, y, img in tiles_imagens:
            x_offset = (x - x_min_tile) * tile_size
            y_offset = (y - y_min_tile) * tile_size
            mosaico.paste(img, (x_offset, y_offset))
        
        geotiff_jobs[job_id]['progresso'] = 70
        
        # Calcula bounds do mosaico em WGS84
        def tile_to_lat(y, zoom):
            """Converte coordenada Y do tile para latitude"""
            n = 2.0 ** zoom
            lat_rad = math.atan(math.sinh(math.pi * (1 - 2 * y / n)))
            return math.degrees(lat_rad)
        
        def tile_to_lng(x, zoom):
            """Converte coordenada X do tile para longitude"""
            n = 2.0 ** zoom
            return x / n * 360.0 - 180.0
        
        mosaico_west = tile_to_lng(x_min_tile, zoom)
        mosaico_east = tile_to_lng(x_max_tile + 1, zoom)
        mosaico_north = tile_to_lat(y_min_tile, zoom)
        mosaico_south = tile_to_lat(y_max_tile + 1, zoom)
        
        geotiff_jobs[job_id]['progresso'] = 80
        
        # Aplica máscara do crop (recorta usando o polígono)
        # Converte coordenadas do crop para pixels no mosaico
        def latlng_to_pixel(lat, lng, img_width, img_height, bounds_west, bounds_east, bounds_north, bounds_south):
            """Converte coordenada lat/lng para pixel na imagem"""
            x = int((lng - bounds_west) / (bounds_east - bounds_west) * img_width)
            y = int((bounds_north - lat) / (bounds_north - bounds_south) * img_height)
            return (x, y)
        
        # Cria máscara do polígono de crop
        from PIL import ImageDraw
        
        mask = Image.new('L', mosaico.size, 0)
        draw = ImageDraw.Draw(mask)
        
        # Converte coordenadas do crop para pixels
        polygon_pixels = []
        for coord in coordenadas_crop:
            lat, lng = coord[0], coord[1]
            pixel = latlng_to_pixel(
                lat, lng,
                mosaico.width, mosaico.height,
                mosaico_west, mosaico_east, mosaico_north, mosaico_south
            )
            polygon_pixels.append(pixel)
        
        # Desenha polígono preenchido na máscara
        if len(polygon_pixels) > 2:
            draw.polygon(polygon_pixels, fill=255)
        
        # Aplica máscara
        mosaico_array = np.array(mosaico)
        mask_array = np.array(mask)
        
        # Aplica máscara (transparente onde não está no polígono)
        mosaico_rgba = np.zeros((mosaico.height, mosaico.width, 4), dtype=np.uint8)
        mosaico_rgba[:, :, :3] = mosaico_array
        mosaico_rgba[:, :, 3] = mask_array
        
        geotiff_jobs[job_id]['progresso'] = 90
        
        # Cria pasta de GeoTIFFs se não existir
        os.makedirs(BASE_PATH_GEOTIFFS, exist_ok=True)
        
        # Gera nome do arquivo
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        filename = f"crop_{quadricula}_{timestamp}.tif"
        arquivo_path = os.path.join(BASE_PATH_GEOTIFFS, filename)
        
        # Cria GeoTIFF com georeferenciamento
        transform = from_bounds(mosaico_west, mosaico_south, mosaico_east, mosaico_north, mosaico.width, mosaico.height)
        
        with rasterio.open(
            arquivo_path,
            'w',
            driver='GTiff',
            height=mosaico.height,
            width=mosaico.width,
            count=4,  # RGBA
            dtype=mosaico_rgba.dtype,
            crs=CRS.from_epsg(4326),  # WGS84
            transform=transform,
            compress='lzw'
        ) as dst:
            # Escreve cada banda (R, G, B, A)
            for i in range(4):
                dst.write(mosaico_rgba[:, :, i], i + 1)
        
        geotiff_jobs[job_id]['progresso'] = 100
        geotiff_jobs[job_id]['status'] = 'concluido'
        geotiff_jobs[job_id]['arquivo'] = arquivo_path
        
        print(f"GeoTIFF gerado com sucesso: {arquivo_path}")
        
    except Exception as e:
        print(f"Erro ao processar GeoTIFF: {e}")
        geotiff_jobs[job_id]['status'] = 'erro'
        geotiff_jobs[job_id]['erro'] = str(e)
    