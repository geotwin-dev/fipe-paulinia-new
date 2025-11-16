<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'connection.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

try {
	// Aceita POST (preferencial) e GET como fallback
	$idParam = null;
	if (isset($_POST['id'])) $idParam = $_POST['id'];
	elseif (isset($_GET['id'])) $idParam = $_GET['id'];

	if ($idParam === null) {
		echo json_encode(['status' => 'erro', 'mensagem' => 'Parâmetro id ausente.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$id = intval($idParam);
	if ($id <= 0) {
		echo json_encode(['status' => 'erro', 'mensagem' => 'Parâmetro id inválido.'], JSON_UNESCAPED_UNICODE);
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

	// Soft delete: status=0, registra alterado e usuario2
	$stmt = $pdo->prepare("UPDATE enderecos_alternativos 
	                       SET status = 0, alterado = NOW(), usuario2 = :usuario2 
	                       WHERE id = :id");
	$stmt->bindValue(':id', $id, PDO::PARAM_INT);
	$stmt->bindValue(':usuario2', $usuarioLogado, PDO::PARAM_STR);
	$stmt->execute();

	if ($stmt->rowCount() < 1) {
		echo json_encode(['status' => 'erro', 'mensagem' => 'Registro não encontrado para exclusão.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	echo json_encode(['status' => 'sucesso'], JSON_UNESCAPED_UNICODE);
	exit;
} catch (PDOException $e) {
	error_log('Erro PDO deletarEnderecoAlternativo: ' . $e->getMessage());
	echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao excluir registro.'], JSON_UNESCAPED_UNICODE);
	exit;
} catch (Exception $e) {
	error_log('Exceção deletarEnderecoAlternativo: ' . $e->getMessage());
	echo json_encode(['status' => 'erro', 'mensagem' => 'Exceção: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
	exit;
}


