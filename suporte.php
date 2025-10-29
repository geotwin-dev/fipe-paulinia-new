<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario']) || !is_array($_SESSION['usuario']) || count($_SESSION['usuario']) < 2) {
    header("Location: login.php");
    exit();
}

include("connection.php");

$usuario_nome = $_SESSION['usuario'][0];
$usuario_email = $_SESSION['usuario'][1];

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Suporte - FIPE Paulínia</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-primary: #0a0a0a;
            --bg-secondary: #1a1a1a;
            --bg-card: #2a2a2a;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --accent-color: #6366f1;
            --accent-hover: #5855eb;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --border-color: #404040;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
        }

        .navbar {
            background-color: var(--bg-secondary) !important;
            border-bottom: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1rem;
            min-height: 60px;
        }

        .navbar-brand {
            color: var(--text-primary) !important;
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
        }

        .navbar-nav .btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }

        .container-fluid {
            padding: 2rem 3rem;
        }

        .card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            border-radius: 12px 12px 0 0 !important;
        }

        .btn-primary {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            border-radius: 8px;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background-color: var(--accent-hover);
            border-color: var(--accent-hover);
            transform: translateY(-1px);
        }

        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
            border-radius: 8px;
        }

        .btn-warning {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
            border-radius: 8px;
        }

        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
            border-radius: 8px;
        }

        .table-dark {
            --bs-table-bg: var(--bg-card);
            --bs-table-striped-bg: var(--bg-secondary);
            --bs-table-hover-bg: var(--bg-secondary);
            --bs-table-border-color: var(--border-color);
        }

        .table-dark td, .table-dark th {
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-aberto {
            background-color: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-em_andamento {
            background-color: rgba(245, 158, 11, 0.2);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-resolvido {
            background-color: rgba(99, 102, 241, 0.2);
            color: var(--accent-color);
            border: 1px solid rgba(99, 102, 241, 0.3);
        }

        .status-fechado {
            background-color: rgba(107, 114, 128, 0.2);
            color: #9ca3af;
            border: 1px solid rgba(107, 114, 128, 0.3);
        }

        .prioridade-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .prioridade-baixa {
            background-color: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
        }

        .prioridade-media {
            background-color: rgba(245, 158, 11, 0.2);
            color: var(--warning-color);
        }

        .prioridade-alta {
            background-color: rgba(239, 68, 68, 0.2);
            color: var(--danger-color);
        }

        .prioridade-critica {
            background-color: rgba(139, 69, 19, 0.2);
            color: #dc2626;
        }

        .modal-content {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
        }

        .form-control, .form-select {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 8px;
        }

        .form-control:focus, .form-select:focus {
            background-color: var(--bg-secondary);
            border-color: var(--accent-color);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }

        .form-label {
            color: var(--text-primary);
            font-weight: 500;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            color: var(--text-primary);
        }

        .dataTables_wrapper .dataTables_filter input {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 6px;
        }

        .dataTables_wrapper .dataTables_length select {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 6px;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--accent-color), var(--accent-hover));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-secondary));
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-color);
        }

        .stats-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .loading-spinner {
            display: none;
        }

        .loading-spinner.show {
            display: inline-block;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            
            <h1 class="page-title">
                <i class="fas fa-headset me-2"></i>
                Central de Suporte
            </h1>
            <div class="navbar-nav ms-auto">
                <button onclick="window.location.href='painel.php'" type="button" class="btn btn-primary">
                    Voltar
                </button>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <!-- Header da Página -->
        <div class="page-header">
            <p class="page-subtitle">Gerencie seus chamados e solicitações de suporte</p>
        </div>

        <!-- Estatísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="stats-number" id="total-chamados">-</div>
                    <div class="stats-label">Total de Chamados</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="stats-number" id="chamados-abertos">-</div>
                    <div class="stats-label">Em Aberto</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="stats-number" id="chamados-resolvidos">-</div>
                    <div class="stats-label">Resolvidos</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="stats-number" id="chamados-fechados">-</div>
                    <div class="stats-label">Fechados</div>
                </div>
            </div>
        </div>

        <!-- Card Principal -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-ticket-alt me-2"></i>
                    Meus Chamados
                </h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoChamado">
                    <i class="fas fa-plus me-2"></i>
                    Novo Chamado
                </button>
            </div>
            <div class="card-body">
                <table id="tabelaChamados" class="table table-dark table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Categoria</th>
                            <th>Prioridade</th>
                            <th>Status</th>
                            <th>Data Criação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dados carregados via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Novo Chamado -->
    <div class="modal fade" id="modalNovoChamado" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>
                        Criar Novo Chamado
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formNovoChamado" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="titulo" class="form-label">Título do Chamado *</label>
                                <input type="text" class="form-control" id="titulo" name="titulo" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="categoria" class="form-label">Categoria *</label>
                                <select class="form-select" id="categoria" name="categoria" required>
                                    <option value="">Selecione uma categoria</option>
                                    <option value="Bug/Erro">Bug/Erro</option>
                                    <option value="Melhoria">Melhoria</option>
                                    <option value="Dúvida">Dúvida</option>
                                    <option value="Solicitação">Solicitação</option>
                                    <option value="Outros">Outros</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="prioridade" class="form-label">Prioridade *</label>
                                <select class="form-select" id="prioridade" name="prioridade" required>
                                    <option value="">Selecione a prioridade</option>
                                    <option value="baixa">Baixa</option>
                                    <option value="media">Média</option>
                                    <option value="alta">Alta</option>
                                    <option value="critica">Crítica</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="descricao" class="form-label">Descrição *</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="5" required 
                                      placeholder="Descreva detalhadamente sua solicitação, problema ou dúvida..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="anexo" class="form-label">Anexos (Opcional)</label>
                            <input type="file" class="form-control" id="anexo" name="anexo[]" multiple>
                            <div class="form-text">Você pode selecionar múltiplos arquivos de qualquer tipo</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>
                            Enviar Chamado
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Visualizar Chamado -->
    <div class="modal fade" id="modalVisualizarChamado" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>
                        Detalhes do Chamado
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detalhesChamado">
                    <!-- Conteúdo carregado via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            // Inicializar DataTable
            var tabela = $('#tabelaChamados').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                },
                responsive: false,
                scrollX: false,
                autoWidth: true,
                order: [[5, 'desc']], // Ordenar por data de criação (mais recente primeiro)
                columnDefs: [
                    { orderable: false, targets: [6] }, // Coluna de ações não ordenável
                    { width: "5%", targets: [0] }, // ID
                    { width: "30%", targets: [1] }, // Título
                    { width: "15%", targets: [2] }, // Categoria
                    { width: "10%", targets: [3] }, // Prioridade
                    { width: "10%", targets: [4] }, // Status
                    { width: "15%", targets: [5] }, // Data
                    { width: "10%", targets: [6] }  // Ações
                ],
                ajax: {
                    url: 'ajax/listar_chamados.php',
                    type: 'GET'
                },
                columns: [
                    { data: 'id' },
                    { data: 'titulo' },
                    { data: 'categoria' },
                    { 
                        data: 'prioridade',
                        render: function(data, type, row) {
                            return '<span class="prioridade-badge prioridade-' + data + '">' + data.charAt(0).toUpperCase() + data.slice(1) + '</span>';
                        }
                    },
                    { 
                        data: 'status',
                        render: function(data, type, row) {
                            return '<span class="status-badge status-' + data + '">' + data.replace('_', ' ').charAt(0).toUpperCase() + data.replace('_', ' ').slice(1) + '</span>';
                        }
                    },
                    { 
                        data: 'data_criacao',
                        render: function(data, type, row) {
                            return new Date(data).toLocaleString('pt-BR');
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            return '<button class="btn btn-sm btn-outline-primary me-1" onclick="visualizarChamado(' + row.id + ')">' +
                                   '<i class="fas fa-eye"></i>' +
                                   '</button>';
                        }
                    }
                ]
            });

            // Carregar estatísticas
            carregarEstatisticas();

            // Submeter formulário de novo chamado
            $('#formNovoChamado').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                
                $.ajax({
                    url: 'ajax/criar_chamado.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    beforeSend: function() {
                        $('#formNovoChamado button[type="submit"]').prop('disabled', true);
                        $('#formNovoChamado button[type="submit"]').html('<i class="fas fa-spinner fa-spin me-2"></i>Enviando...');
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#modalNovoChamado').modal('hide');
                            $('#formNovoChamado')[0].reset();
                            tabela.ajax.reload();
                            carregarEstatisticas();
                            
                            // Mostrar notificação de sucesso
                            alert('Chamado criado com sucesso!');
                        } else {
                            alert('Erro ao criar chamado: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Erro ao criar chamado. Tente novamente.');
                    },
                    complete: function() {
                        $('#formNovoChamado button[type="submit"]').prop('disabled', false);
                        $('#formNovoChamado button[type="submit"]').html('<i class="fas fa-paper-plane me-2"></i>Enviar Chamado');
                    }
                });
            });
        });

        function visualizarChamado(id) {
            // Mostrar loading
            $('#detalhesChamado').html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="text-light mt-3">Carregando detalhes do chamado...</p>
                </div>
            `);
            
            // Mostrar modal imediatamente
            $('#modalVisualizarChamado').modal('show');
            
            $.ajax({
                url: 'ajax/detalhes_chamado.php',
                type: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#detalhesChamado').html(response.html);
                    } else {
                        $('#detalhesChamado').html(`
                            <div class="text-center py-5">
                                <i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i>
                                <p class="text-light">Erro ao carregar detalhes do chamado.</p>
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#detalhesChamado').html(`
                        <div class="text-center py-5">
                            <i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i>
                            <p class="text-light">Erro ao carregar detalhes do chamado.</p>
                        </div>
                    `);
                }
            });
        }

        function carregarEstatisticas() {
            $.ajax({
                url: 'ajax/estatisticas_chamados.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#total-chamados').text(response.data.total);
                        $('#chamados-abertos').text(response.data.abertos);
                        $('#chamados-resolvidos').text(response.data.resolvidos);
                        $('#chamados-fechados').text(response.data.fechados);
                    }
                }
            });
        }
    </script>
</body>
</html>

