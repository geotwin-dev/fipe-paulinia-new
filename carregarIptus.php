<?php

// Inclui a conexão com o banco de dados
require_once 'connection.php';

// Aceita tanto imob_id (correto) quanto imod_id (legado)
$imob_id = null;
if (isset($_GET['imob_id'])) { $imob_id = $_GET['imob_id']; }
elseif (isset($_POST['imob_id'])) { $imob_id = $_POST['imob_id']; }
elseif (isset($_GET['imod_id'])) { $imob_id = $_GET['imod_id']; }
elseif (isset($_POST['imod_id'])) { $imob_id = $_POST['imod_id']; }

$dadosIptus = [];

// Verifica se a conexão com o banco está ativa
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT bcim_data, bcim_cod_isencao, bcim_mtq_area_terreno, 
                                        bcim_cod_valor, bcim_vlr_venal_terreno, bcim_vlr_desconto, 
                                        bcim_vlr_correcao, bcim_vlr_venal_construcao, bcim_mtq_area_construida, 
                                        bcim_vlr_mtq_construcao, bcim_profundidade, caim_muro, caim_passeio, 
                                        caim_utilizacao, caim_ocupacao, cara_coleta, cara_vlcoleta, cara_refcoleta
                             FROM iptu_sirf WHERE imob_id = :imob_id");
        $stmt->bindValue(':imob_id', $imob_id, PDO::PARAM_STR);
        $stmt->execute();

        $dadosIptus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        // Em caso de erro, retorna array vazio e log do erro
        error_log("Erro na consulta: " . $e->getMessage());
        $dadosIptus = [];
    }
} else {
    // Se não há conexão com o banco, retorna erro
    $dadosIptus = ['erro' => 'Conexão com banco de dados não disponível'];
}

echo json_encode($dadosIptus);
