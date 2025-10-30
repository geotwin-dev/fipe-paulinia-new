<?php
// Parâmetros: caminho (string), rot (graus, pode ser qualquer número, inclusive negativo)
$caminho = isset($_GET['caminho']) ? $_GET['caminho'] : '';
$rot = isset($_GET['rot']) ? floatval($_GET['rot']) : 0.0;
// Monta URL do servidor que entrega a imagem bruta
$imgUrl = 'buscarImagemAerea.php?caminho=' . urlencode($caminho);
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Visualizar imagem</title>
    <style>
        html,
        body {
            height: 100%;
            margin: 0;
            background: #111;
            color: #eee;
            font-family: Arial, sans-serif;
            overflow: hidden;
            /* sem scroll da página; zoom via wheel */
        }

        .viewport {
            position: fixed;
            inset: 0;
            user-select: none;
        }

        .canvas {
            position: relative;
            transform-origin: 0 0;
            will-change: transform;
        }

        #img {
            display: block;
            width: auto;
            height: auto;
            image-rendering: auto;
            cursor: grab;
            -webkit-user-drag: none;
            user-select: none;
        }

        .hud {
            position: fixed;
            left: 12px;
            bottom: 12px;
            background: #00000080;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="viewport">
        <div class="canvas">
            <img id="img" alt="imagem" src="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES); ?>" draggable="false" />
        </div>
    </div>
    <div class="hud" id="hud">Rotação: <span id="rotVal">0</span>°, Zoom: <span id="zoomVal">1.00x</span></div>

    <script>
        (function() {
            const img = document.getElementById('img');
            const hudRot = document.getElementById('rotVal');
            const hudZoom = document.getElementById('zoomVal');
            const canvas = document.querySelector('.canvas');

            // Le parâmetros vindos da URL
            const params = new URLSearchParams(window.location.search);
            let rotationDeg = Number(params.get('rot') || 0);
            let scale = 1; // zoom inicial
            let MIN_SCALE = 1; // mínimo dinâmico (ajustado no carregamento para caber na tela)
            let tx = 0, ty = 0; // translação do canvas

            function applyTransform() {
                canvas.style.transform = `translate(${tx}px, ${ty}px) rotate(${rotationDeg}deg) scale(${scale})`;
                hudRot.textContent = rotationDeg.toFixed(2);
                hudZoom.textContent = scale.toFixed(2) + 'x';
            }

            // Zoom com roda do mouse centrado no cursor
            window.addEventListener('wheel', function(e) {
                e.preventDefault();
                const delta = e.deltaY;
                // fator suave
                const factor = Math.pow(1.0015, Math.abs(delta));

                const prevScale = scale;
                if (delta < 0) {
                    // zoom in
                    scale *= factor;
                } else {
                    // zoom out
                    scale /= factor;
                    if (scale < MIN_SCALE) scale = MIN_SCALE;
                }

                // Calcula q (coordenada da imagem sob o cursor) usando a inversa da transform atual
                const px = e.clientX;
                const py = e.clientY;
                const r = rotationDeg * Math.PI / 180;
                const cosInv = Math.cos(-r);
                const sinInv = Math.sin(-r);
                const qx = (cosInv * (px - tx) - sinInv * (py - ty)) / prevScale;
                const qy = (sinInv * (px - tx) + cosInv * (py - ty)) / prevScale;

                // Novo tx,ty para manter p = T'(q) = [tx,ty] + R(rot)*(scale*q)
                const cos = Math.cos(r);
                const sin = Math.sin(r);
                const vx = cos * (scale * qx) - sin * (scale * qy);
                const vy = sin * (scale * qx) + cos * (scale * qy);
                tx = px - vx;
                ty = py - vy;

                applyTransform();
            }, {
                passive: false
            });

            // Pan simples com arrastar (opcional, útil com zoom alto)
            let isDragging = false;
            let startX = 0,
                startY = 0;
            let lastTx = 0,
                lastTy = 0;

            img.addEventListener('mousedown', (e) => {
                isDragging = true;
                img.style.cursor = 'grabbing';
                startX = e.clientX;
                startY = e.clientY;
                lastTx = tx;
                lastTy = ty;
                e.preventDefault(); // impede arrastar padrão do navegador
            });
            img.addEventListener('dragstart', (e) => {
                e.preventDefault();
            });
            window.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                const dx = e.clientX - startX;
                const dy = e.clientY - startY;
                tx = lastTx + dx;
                ty = lastTy + dy;
                applyTransform();
            });
            window.addEventListener('mouseup', () => {
                isDragging = false;
                img.style.cursor = 'grab';
            });

            // Centraliza a imagem ao carregar
            function centerOnLoad() {
                const vw = window.innerWidth;
                const vh = window.innerHeight;
                const iw = img.naturalWidth || img.width;
                const ih = img.naturalHeight || img.height;
                const r = rotationDeg * Math.PI / 180;
                const cos = Math.cos(r);
                const sin = Math.sin(r);

                // BBox do retângulo rotacionado (antes do scale)
                const bboxW = Math.abs(iw * cos) + Math.abs(ih * sin);
                const bboxH = Math.abs(iw * sin) + Math.abs(ih * cos);

                // Escala para caber na viewport inteira; prioriza ver tudo
                const fitScale = Math.min(vw / bboxW, vh / bboxH);
                scale = fitScale;
                MIN_SCALE = fitScale; // não permitir zoom-out menor que caber a imagem inteira

                // Centraliza o centro geométrico
                const cx = vw / 2;
                const cy = vh / 2;
                const hx = (iw * scale) / 2;
                const hy = (ih * scale) / 2;
                const vx = cos * hx - sin * hy;
                const vy = sin * hx + cos * hy;
                tx = cx - vx;
                ty = cy - vy;
                applyTransform();
            }
            if (img.complete) centerOnLoad();
            img.addEventListener('load', centerOnLoad);
        })();
    </script>
</body>

</html>