<?php

// Inclui a conexão com o banco de dados
require_once 'connection.php';

$quarteiroes = isset($_POST['quarteiroes']) ? $_POST['quarteiroes'] : [];

$dadosPlanilha = [];

// Verifica se a conexão com o banco está ativa
if ($pdo) {
    try {
        // Se não há quarteirões enviados, retorna todos os dados
        if (empty($quarteiroes) || !is_array($quarteiroes)) {
            $stmt = $pdo->prepare("SELECT * FROM cadastro");
            $stmt->execute();
        } else {
            // Cria placeholders para a consulta IN
            $placeholders = str_repeat('?,', count($quarteiroes) - 1) . '?';
            
            // Consulta os registros onde cara_quarteirao está no array de quarteirões
            $stmt = $pdo->prepare("SELECT * FROM cadastro WHERE cara_quarteirao IN ($placeholders)");
            $stmt->execute($quarteiroes);
        }

        $dadosPlanilha = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        // Em caso de erro, retorna array vazio e log do erro
        error_log("Erro na consulta: " . $e->getMessage());
        $dadosPlanilha = [];
    }
} else {
    // Se não há conexão com o banco, retorna erro
    $dadosPlanilha = ['erro' => 'Conexão com banco de dados não disponível'];
}

echo json_encode($dadosPlanilha);
