<?php
// Arquivo de teste para verificar o server-side processing
session_start();

// Simular login para teste
$_SESSION['usuario'] = 'teste';

echo "<h1>Teste Server-Side Processing</h1>";

// Simular parâmetros do DataTables
$_POST['tabela'] = 'cadastro';
$_POST['consulta_id'] = 0;
$_POST['draw'] = 1;
$_POST['start'] = 0;
$_POST['length'] = 10;
$_POST['search'] = ['value' => ''];

// Simular filtros customizados
$_POST['filtros_customizados'] = json_encode([
    [
        'campo' => 'nome_pessoa',
        'tipo' => 'texto',
        'valor1' => 'SILVA'
    ],
    [
        'campo' => 'area_terreno',
        'tipo' => 'numero',
        'valor1' => '100',
        'valor2' => '500'
    ]
]);

echo "<h2>Parâmetros de entrada:</h2>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

echo "<h2>Resposta do backend:</h2>";
echo "<pre>";

// Capturar a saída
ob_start();
include 'consultar_dados.php';
$output = ob_get_clean();

echo htmlspecialchars($output);
echo "</pre>";

// Tentar decodificar JSON
echo "<h2>JSON decodificado:</h2>";
$data = json_decode($output, true);
if ($data) {
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    
    echo "<h3>Informações importantes:</h3>";
    echo "Draw: " . ($data['draw'] ?? 'N/A') . "<br>";
    echo "Total de registros: " . ($data['recordsTotal'] ?? 'N/A') . "<br>";
    echo "Registros filtrados: " . ($data['recordsFiltered'] ?? 'N/A') . "<br>";
    echo "Registros retornados: " . (count($data['data'] ?? [])) . "<br>";
    echo "Sucesso: " . ($data['success'] ? 'Sim' : 'Não') . "<br>";
} else {
    echo "Erro ao decodificar JSON: " . json_last_error_msg();
}
?>
