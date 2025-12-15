<?php

// Inclui a conexão com o banco de dados
require_once 'connection.php';

// Recebe os quarteirões - pode vir como array ou como string JSON
$quarteiroes = isset($_POST['quarteiroes']) ? $_POST['quarteiroes'] : [];

// Se vier como string JSON, decodifica
if (is_string($quarteiroes) && !empty($quarteiroes)) {
    $quarteiroes = json_decode($quarteiroes, true);
}

// Se vier como array associativo (quarteiroes[]), converte para array simples
if (isset($_POST['quarteiroes']) && is_array($_POST['quarteiroes'])) {
    $quarteiroes = $_POST['quarteiroes'];
}

// Garante que seja um array
if (!is_array($quarteiroes)) {
    $quarteiroes = [];
}

// Remove espaços em branco e garante que sejam strings
$quarteiroes = array_map(function($q) {
    return trim(strval($q));
}, $quarteiroes);

// Remove valores vazios
$quarteiroes = array_filter($quarteiroes, function($q) {
    return !empty($q);
});

// Reindexa o array
$quarteiroes = array_values($quarteiroes);

$dadosPlanilha = [];

// Verifica se a conexão com o banco está ativa
if ($pdo) {
    try {
        // Se não há quarteirões enviados, retorna todos os dados
        if (empty($quarteiroes)) {
            $stmt = $pdo->prepare("SELECT * FROM cadastro");
            $stmt->execute();
        } else {
            // Prepara arrays para busca: valores originais e normalizados (sem zeros à esquerda)
            $valoresBusca = [];
            
            foreach ($quarteiroes as $q) {
                $q = trim(strval($q));
                if (empty($q)) continue;
                
                // Adiciona o valor original (pode ter zeros à esquerda como '0308')
                $valoresBusca[] = $q;
                
                // Adiciona o valor normalizado (sem zeros à esquerda como '308')
                $num = intval($q);
                if ($num > 0) {
                    $normalizado = strval($num);
                    // Só adiciona se for diferente do original
                    if ($normalizado !== $q) {
                        $valoresBusca[] = $normalizado;
                    }
                }
            }
            
            // Remove duplicatas
            $valoresBusca = array_unique($valoresBusca);
            $valoresBusca = array_values($valoresBusca);
            
            if (empty($valoresBusca)) {
                $dadosPlanilha = [];
            } else {
                // Cria placeholders para a consulta IN
                $placeholders = str_repeat('?,', count($valoresBusca) - 1) . '?';
                
                // Consulta os registros onde cara_quarteirao está no array de quarteirões
                // Compara tanto com valores originais quanto normalizados
                $stmt = $pdo->prepare("SELECT * FROM cadastro WHERE TRIM(cara_quarteirao) IN ($placeholders)");
                $stmt->execute($valoresBusca);
            }
        }

        $dadosPlanilha = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: log temporário para verificar quantos registros foram retornados
        error_log("Registros retornados: " . count($dadosPlanilha) . " para quarteirões: " . json_encode($quarteiroes));
        
    } catch (PDOException $e) {
        // Em caso de erro, retorna array vazio e log do erro
        error_log("Erro na consulta: " . $e->getMessage());
        $dadosPlanilha = ['erro' => $e->getMessage()];
    }
} else {
    // Se não há conexão com o banco, retorna erro
    $dadosPlanilha = ['erro' => 'Conexão com banco de dados não disponível'];
}

echo json_encode($dadosPlanilha);
