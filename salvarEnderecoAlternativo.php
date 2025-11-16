<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'connection.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

try {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$id_marcador = isset($_POST['id_marcador']) ? intval($_POST['id_marcador']) : 0;
	$endereco = isset($_POST['endereco']) ? trim($_POST['endereco']) : '';
	$numero = isset($_POST['numero']) ? trim($_POST['numero']) : '';
	$complemento = isset($_POST['complemento']) ? trim($_POST['complemento']) : '';
	$observacao = isset($_POST['observacao']) ? trim($_POST['observacao']) : '';

	if ($id_marcador <= 0) {
		echo json_encode(['status' => 'erro', 'mensagem' => 'id_marcador inválido.'], JSON_UNESCAPED_UNICODE);
		exit;
	}
	if ($endereco === '') {
		echo json_encode(['status' => 'erro', 'mensagem' => 'O campo endereço é obrigatório.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	if (!$pdo) {
		echo json_encode(['status' => 'erro', 'mensagem' => 'Conexão com banco de dados não disponível.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	// Usuário na sessão pode ser array; usamos o nome no índice 0
	$usuarioLogado = null;
	if (isset($_SESSION['usuario'])) {
		$usuarioLogado = is_array($_SESSION['usuario']) ? ($_SESSION['usuario'][0] ?? null) : $_SESSION['usuario'];
	}

	$sql = "INSERT INTO enderecos_alternativos (id_marcador, endereco, numero, complemento, observacao, status, criado, usuario1) 
	        VALUES (:id_marcador, :endereco, :numero, :complemento, :observacao, 1, NOW(), :usuario1)";

	$stmt = $pdo->prepare($sql);
	$stmt->bindValue(':id_marcador', $id_marcador, PDO::PARAM_INT);
	$stmt->bindValue(':endereco', $endereco, PDO::PARAM_STR);
	$stmt->bindValue(':numero', $numero, PDO::PARAM_STR);
	$stmt->bindValue(':complemento', $complemento, PDO::PARAM_STR);
	$stmt->bindValue(':observacao', $observacao, PDO::PARAM_STR);
	$stmt->bindValue(':usuario1', $usuarioLogado, PDO::PARAM_STR);
	$stmt->execute();

	$novoId = $pdo->lastInsertId();

	echo json_encode([
		'status' => 'sucesso',
		'id' => $novoId
	], JSON_UNESCAPED_UNICODE);
	exit;
} catch (PDOException $e) {
	error_log('Erro PDO salvarEnderecoAlternativo: ' . $e->getMessage());
	echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao salvar registro.'], JSON_UNESCAPED_UNICODE);
	exit;
} catch (Exception $e) {
	error_log('Exceção salvarEnderecoAlternativo: ' . $e->getMessage());
	echo json_encode(['status' => 'erro', 'mensagem' => 'Exceção: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
	exit;
}

