<?php
// Desabilita exibição de erros/warnings para não corromper o JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Define o cabeçalho para retornar JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // Inclui a conexão com o banco de dados
    require_once 'connection.php';

    // Recebe os dados via GET (mesmo padrão usado em buscar_dados_unidade.php)
    $id_desenho = isset($_GET['id_desenho']) ? trim($_GET['id_desenho']) : null;
    
    if (!$id_desenho) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'ID do desenho é obrigatório.'
        ]);
        exit;
    }

    // Verifica se a conexão com o banco está ativa
    if (!$pdo) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Conexão com banco de dados não disponível'
        ]);
        exit;
    }

    // Array para armazenar todos os resultados
    $resultados = [];

    // Consulta 1: Busca TODAS as linhas em marcadores_lotes
    try {
        $stmt = $pdo->prepare("SELECT * FROM marcadores_lotes WHERE id_desenho_poligono_lote = :id_desenho");
        $stmt->bindValue(':id_desenho', $id_desenho, PDO::PARAM_INT);
        $stmt->execute();
        
        // Busca TODAS as linhas (não apenas uma)
        $todasLinhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($todasLinhas && count($todasLinhas) > 0) {
            // Processa cada linha retornada
            foreach ($todasLinhas as $linha) {
                // Inicializa o array de resultado para esta linha
                $resultadoLinha = [
                    "imob_id" => isset($linha['imob_id']) ? $linha['imob_id'] : "",
                    "quarteirao" => "",
                    "quadra" => "",
                    "lote" => "",
                    "area_terreno" => "",
                    "area_construida" => ""
                ];

                // Consulta 2: Busca dados em desenhos (só se tiver id_desenho_marcador)
                if (isset($linha['id_desenho_marcador']) && !empty($linha['id_desenho_marcador'])) {
                    try {
                        $stmt2 = $pdo->prepare("SELECT * FROM desenhos WHERE id = :id_marcador");
                        $stmt2->bindValue(':id_marcador', $linha['id_desenho_marcador'], PDO::PARAM_INT);
                        $stmt2->execute();
                        
                        $dados2 = $stmt2->fetch(PDO::FETCH_ASSOC);

                        if ($dados2) {
                            $resultadoLinha['quarteirao'] = isset($dados2['quarteirao']) ? $dados2['quarteirao'] : "";
                            $resultadoLinha['quadra'] = isset($dados2['quadra']) ? $dados2['quadra'] : "";
                            $resultadoLinha['lote'] = isset($dados2['lote']) ? $dados2['lote'] : "";
                        }
                    } catch (PDOException $e) {
                        error_log("Erro na consulta 2 para linha id: " . $linha['id'] . " - " . $e->getMessage());
                    }
                }

                // Consulta 3: Busca dados em cadastro (só se tiver id_cadastro)
                if (isset($linha['id_cadastro']) && !empty($linha['id_cadastro'])) {
                    try {
                        $stmt3 = $pdo->prepare("SELECT id, imob_id, total_construido, area_terreno FROM cadastro WHERE id = :id_cadastro");
                        $stmt3->bindValue(':id_cadastro', $linha['id_cadastro'], PDO::PARAM_INT);
                        $stmt3->execute();
                        
                        $dados3 = $stmt3->fetch(PDO::FETCH_ASSOC);

                        if ($dados3) {
                            $resultadoLinha['area_terreno'] = isset($dados3['area_terreno']) ? $dados3['area_terreno'] : "";
                            // CORREÇÃO: A coluna é 'total_construido', não 'area_construida'
                            $resultadoLinha['area_construida'] = isset($dados3['total_construido']) ? $dados3['total_construido'] : "";
                        }
                    } catch (PDOException $e) {
                        error_log("Erro na consulta 3 para linha id: " . $linha['id'] . " - " . $e->getMessage());
                    }
                }

                // Adiciona o resultado desta linha ao array de resultados
                $resultados[] = $resultadoLinha;
            }
        }
    } catch (PDOException $e) {
        error_log("Erro na consulta 1: " . $e->getMessage());
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao buscar dados: ' . $e->getMessage()
        ]);
        exit;
    }

    // Consulta IPTU: Busca dados para a tabela de IPTU
    $dadosIPTU = [];
    try {
        $stmtIPTU = $pdo->prepare("SELECT a.imob_id, i.bcim_mtq_area_terreno, a.area as ident, a.area_construida, a.utilizacao, a.construcao, a.classificacao 
                                    FROM marcadores_lotes m 
                                    LEFT JOIN `iptu_sirf` i ON i.imob_id = m.imob_id 
                                    LEFT JOIN areas_iptu_sirf a ON a.imob_id = i.imob_id 
                                    WHERE m.id_desenho_poligono_lote = :id_desenho");
        $stmtIPTU->bindValue(':id_desenho', $id_desenho, PDO::PARAM_INT);
        $stmtIPTU->execute();
        
        // Busca TODAS as linhas de IPTU
        $todasLinhasIPTU = $stmtIPTU->fetchAll(PDO::FETCH_ASSOC);
        
        if ($todasLinhasIPTU && count($todasLinhasIPTU) > 0) {
            foreach ($todasLinhasIPTU as $linhaIPTU) {
                // Organiza os dados de IPTU
                $linhaIPTUFormatada = [
                    "imob_id" => isset($linhaIPTU['imob_id']) ? $linhaIPTU['imob_id'] : "",
                    "area_terreno" => isset($linhaIPTU['bcim_mtq_area_terreno']) ? $linhaIPTU['bcim_mtq_area_terreno'] : "",
                    "ident" => isset($linhaIPTU['ident']) ? $linhaIPTU['ident'] : "",
                    "area_construida" => isset($linhaIPTU['area_construida']) ? $linhaIPTU['area_construida'] : "",
                    "utilizacao" => isset($linhaIPTU['utilizacao']) ? $linhaIPTU['utilizacao'] : "",
                    "tipo_construcao" => isset($linhaIPTU['construcao']) ? $linhaIPTU['construcao'] : "",
                    "classificacao" => isset($linhaIPTU['classificacao']) ? $linhaIPTU['classificacao'] : ""
                ];
                $dadosIPTU[] = $linhaIPTUFormatada;
            }
        }
    } catch (PDOException $e) {
        error_log("Erro na consulta IPTU: " . $e->getMessage());
        // Não interrompe o processo, apenas loga o erro
    }

    // Consulta Situação Atual: Busca dados para a tabela de Situação Atual
    $dadosSituacao = [];
    try {
        $stmtSituacao = $pdo->prepare("SELECT u.id_unidades_lotes, i.terreo_area + i.demais_area as area_construida, i.utilizacao, i.terreo_tipo, i.terreo_classificacao 
                                        FROM unidades_lotes u 
                                        LEFT JOIN informacoes_blocos i ON i.id_desenhos = u.id_desenho_unidade 
                                        WHERE u.id_desenho_poligono_lote = :id_desenho
                                        ORDER BY u.id_unidades_lotes ASC");
        $stmtSituacao->bindValue(':id_desenho', $id_desenho, PDO::PARAM_INT);
        $stmtSituacao->execute();
        
        // Busca TODAS as linhas de Situação Atual
        $todasLinhasSituacao = $stmtSituacao->fetchAll(PDO::FETCH_ASSOC);
        
        if ($todasLinhasSituacao && count($todasLinhasSituacao) > 0) {
            foreach ($todasLinhasSituacao as $linhaSituacao) {
                // Organiza os dados de Situação Atual
                $linhaSituacaoFormatada = [
                    "id_unidades_lotes" => isset($linhaSituacao['id_unidades_lotes']) ? $linhaSituacao['id_unidades_lotes'] : "",
                    "area_construida" => isset($linhaSituacao['area_construida']) ? $linhaSituacao['area_construida'] : "",
                    "utilizacao" => isset($linhaSituacao['utilizacao']) ? $linhaSituacao['utilizacao'] : "",
                    "tipo_construcao" => isset($linhaSituacao['terreo_tipo']) ? $linhaSituacao['terreo_tipo'] : "",
                    "classificacao" => isset($linhaSituacao['terreo_classificacao']) ? $linhaSituacao['terreo_classificacao'] : ""
                ];
                $dadosSituacao[] = $linhaSituacaoFormatada;
            }
        }
    } catch (PDOException $e) {
        error_log("Erro na consulta Situação Atual: " . $e->getMessage());
        // Não interrompe o processo, apenas loga o erro
    }

    // Consulta Piscinas: Busca todos os id_desenho_piscina da tabela piscinas_lotes
    $dadosPiscinas = [];
    try {
        $stmtPiscinas = $pdo->prepare("SELECT id_desenho_piscina FROM piscinas_lotes WHERE id_desenho_poligono_lote = :id_desenho");
        $stmtPiscinas->bindValue(':id_desenho', $id_desenho, PDO::PARAM_INT);
        $stmtPiscinas->execute();
        
        // Busca TODAS as linhas de piscinas
        $todasLinhasPiscinas = $stmtPiscinas->fetchAll(PDO::FETCH_ASSOC);
        
        if ($todasLinhasPiscinas && count($todasLinhasPiscinas) > 0) {
            foreach ($todasLinhasPiscinas as $linhaPiscina) {
                // Adiciona apenas o id_desenho_piscina ao array
                if (isset($linhaPiscina['id_desenho_piscina']) && !empty($linhaPiscina['id_desenho_piscina'])) {
                    $dadosPiscinas[] = $linhaPiscina['id_desenho_piscina'];
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Erro na consulta Piscinas: " . $e->getMessage());
        // Não interrompe o processo, apenas loga o erro
    }

    // Retorna sucesso com TODOS os resultados (array de linhas de cadastro, IPTU, Situação Atual e Piscinas)
    echo json_encode([
        'status' => 'sucesso',
        'dados' => $resultados,
        'dados_iptu' => $dadosIPTU,
        'dados_situacao' => $dadosSituacao,
        'dados_piscinas' => $dadosPiscinas
    ]);
    exit;

} catch (Throwable $e) {
    error_log("Erro geral: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Falha ao buscar dados: ' . $e->getMessage()
    ]);
    exit;
}
?>
