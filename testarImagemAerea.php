<?php
/**
 * Script de TESTE para verificar se as imagens aéreas são acessíveis
 * Acesse: testarImagemAerea.php?caminho=CAMINHO_DA_IMAGEM
 */

$caminho = $_GET['caminho'] ?? '';

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Teste de Imagem Aérea</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
        }
        .success {
            background: #c8e6c9;
        }
        .error {
            background: #ffcdd2;
        }
        img {
            max-width: 100%;
            border: 2px solid #ddd;
            border-radius: 5px;
            margin-top: 20px;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: monospace;
        }
        button {
            background: #2196F3;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #1976D2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Teste de Imagem Aérea</h1>
        
        <form method="GET">
            <label for="caminho"><strong>Caminho da imagem:</strong></label>
            <input type="text" name="caminho" id="caminho" 
                   value="<?php echo htmlspecialchars($caminho); ?>" 
                   placeholder="Ex: D:\caminho\para\imagem.jpg">
            <button type="submit">🔎 Testar</button>
        </form>

        <?php if ($caminho): ?>
            <hr style="margin: 30px 0;">
            
            <h3>📊 Resultados do Teste:</h3>
            
            <div class="info">
                <strong>🗂️ Caminho recebido:</strong><br>
                <?php echo htmlspecialchars($caminho); ?>
            </div>
            
            <?php
            // Sanitização básica
            $caminhoLimpo = str_replace(['..', "\0"], '', $caminho);
            ?>
            
            <div class="info">
                <strong>🔒 Caminho após sanitização:</strong><br>
                <?php echo htmlspecialchars($caminhoLimpo); ?>
            </div>
            
            <?php
            // Teste 1: Arquivo existe?
            $existe = file_exists($caminhoLimpo);
            $ehArquivo = is_file($caminhoLimpo);
            ?>
            
            <div class="info <?php echo ($existe && $ehArquivo) ? 'success' : 'error'; ?>">
                <strong>✓ file_exists():</strong> <?php echo $existe ? '✅ SIM' : '❌ NÃO'; ?><br>
                <strong>✓ is_file():</strong> <?php echo $ehArquivo ? '✅ SIM' : '❌ NÃO'; ?>
            </div>
            
            <?php if ($existe && $ehArquivo): ?>
                <?php
                // Teste 2: Tipo MIME
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $caminhoLimpo);
                finfo_close($finfo);
                
                $tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff'];
                $tipoValido = in_array($mimeType, $tiposPermitidos);
                ?>
                
                <div class="info <?php echo $tipoValido ? 'success' : 'error'; ?>">
                    <strong>🎨 Tipo MIME detectado:</strong> <?php echo htmlspecialchars($mimeType); ?><br>
                    <strong>✓ Tipo válido:</strong> <?php echo $tipoValido ? '✅ SIM' : '❌ NÃO'; ?>
                </div>
                
                <?php
                // Informações do arquivo
                $tamanho = filesize($caminhoLimpo);
                $tamanhoMB = round($tamanho / 1024 / 1024, 2);
                ?>
                
                <div class="info success">
                    <strong>📏 Tamanho:</strong> <?php echo number_format($tamanho); ?> bytes (<?php echo $tamanhoMB; ?> MB)<br>
                    <strong>📅 Última modificação:</strong> <?php echo date('d/m/Y H:i:s', filemtime($caminhoLimpo)); ?>
                </div>
                
                <?php if ($tipoValido): ?>
                    <h3>🖼️ Pré-visualização:</h3>
                    <img src="buscarImagemAerea.php?caminho=<?php echo urlencode($caminho); ?>" 
                         alt="Imagem Aérea"
                         onerror="this.style.display='none'; document.getElementById('imgError').style.display='block';">
                    <div id="imgError" style="display:none; padding: 20px; background: #ffcdd2; border-radius: 5px; margin-top: 20px;">
                        ❌ Erro ao carregar a imagem via buscarImagemAerea.php
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="info error">
                    <strong>❌ ERRO:</strong> O arquivo não foi encontrado ou não é um arquivo válido.<br><br>
                    <strong>Possíveis causas:</strong><br>
                    • O caminho está incorreto<br>
                    • O arquivo não existe neste local<br>
                    • Permissões de leitura insuficientes<br>
                    • Barras invertidas não tratadas corretamente
                </div>
            <?php endif; ?>
            
        <?php endif; ?>
        
        <hr style="margin: 30px 0;">
        
        <h3>💡 Dicas:</h3>
        <ul>
            <li>Use o caminho completo do arquivo (ex: <code>D:\pasta\imagem.jpg</code>)</li>
            <li>Verifique se o caminho tem barras duplas: <code>\\</code></li>
            <li>Confira se o PHP tem permissão para ler o arquivo</li>
            <li>Formatos suportados: JPG, PNG, GIF, WebP, BMP, TIFF</li>
        </ul>
    </div>
</body>
</html>
