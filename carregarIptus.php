<?php

// Inclui a conexão com o banco de dados
require_once 'connection.php';

// Aceita tanto imob_id (correto) quanto imod_id (legado)
$imob_id = null;
if (isset($_GET['imob_id'])) { $imob_id = $_GET['imob_id']; }
elseif (isset($_POST['imob_id'])) { $imob_id = $_POST['imob_id']; }
elseif (isset($_GET['imod_id'])) { $imob_id = $_GET['imod_id']; }
elseif (isset($_POST['imod_id'])) { $imob_id = $_POST['imod_id']; }

// Dicionário de mapeamento de colunas para labels descritivos
$dicionarioLabels = [
    'bcim_data' => 'Data do Lançamento do IPTU',
    'bcim_cod_isencao' => 'Código de Isenção',
    'bcim_mtq_area_terreno' => 'Área do Terreno (m²)',
    'bcim_cod_valor' => 'Código de Valor (PGV)',
    'bcim_vlr_venal_terreno' => 'Valor Venal do Terreno (R$)',
    'bcim_vlr_desconto' => 'Valor dos Descontos (R$)',
    'bcim_vlr_correcao' => 'Valor da Correção (R$)',
    'bcim_vlr_venal_construcao' => 'Valor Venal da Construção (R$)',
    'bcim_mtq_area_construida' => 'Área Total Construída (m²)',
    'bcim_vlr_mtq_construcao' => 'Valor por m² da Construção (R$)',
    'bcim_profundidade' => 'Profundidade do Terreno (m)',
    'caim_muro' => 'Muro Existente',
    'caim_passeio' => 'Passeio (Calçada)',
    'caim_utilizacao' => 'Utilização do Imóvel',
    'caim_ocupacao' => 'Ocupação do Imóvel',
    'cara_coleta' => 'Possui Coleta de Lixo',
    'cara_vlcoleta' => 'Valor da Taxa de Coleta de Lixo (R$)',
    'cara_refcoleta' => 'Referência da Coleta de Lixo'
];

$dicionarioLabels2 = [
    'area' => "Identificação",
    'area_construida' => "Área",
    'utilizacao' => "Utilização",
    'construcao' => "Tipo Construção",
    'classificacao' => "Classificação"
];

$dadosIptus = [];
$dadosIptus2 = [];

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

    try {
        $stmt = $pdo->prepare("SELECT area, utilizacao, construcao, classificacao, area_construida
                             FROM areas_iptu_sirf WHERE imob_id = :imob_id");
        $stmt->bindValue(':imob_id', $imob_id, PDO::PARAM_STR);
        $stmt->execute();

        $dadosIptus2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        // Em caso de erro, retorna array vazio e log do erro
        error_log("Erro na consulta: " . $e->getMessage());
        $dadosIptus2 = [];
    }
} else {
    // Se não há conexão com o banco, retorna erro
    $dadosIptus = ['erro' => 'Conexão com banco de dados não disponível'];
    $dadosIptus2 = ['erro' => 'Conexão com banco de dados não disponível'];
}


// Retorna os dados junto com o dicionário de labels
$resultado = [
    'dados' => $dadosIptus,
    'dados2' => $dadosIptus2,
    'dicionario' => $dicionarioLabels,
    'dicionario2' => $dicionarioLabels2
];

echo json_encode($resultado);
