<?php
session_start();

// Verificar se o usuário está logado e é um restaurante
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'restaurant') {
    header('Location: login.php');
    exit();
}

// Conectar à base de dados
try {
    $pdo = new PDO('sqlite: ../../../../database/TesteOlga.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erro de conexão: ' . $e->getMessage());
}

$success_message = '';
$error_message = '';

// Obter dados do restaurante
$stmt = $pdo->prepare("
    SELECT u.*, rp.restaurant_id, rp.restaurant_name, rp.restaurant_type, 
           rp.description, rp.avg_rating
    FROM Users u 
    LEFT JOIN RestaurantProfiles rp ON u.user_id = rp.user_id 
    WHERE u.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$restaurant_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Se não existe perfil de restaurante, criar um
if (!$restaurant_data['restaurant_id']) {
    $stmt = $pdo->prepare("
        INSERT INTO RestaurantProfiles (user_id, restaurant_name, restaurant_type, description) 
        VALUES (?, ?, 'Geral', '')
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_first_name'] . ' Restaurant']);
    
    // Recarregar dados
    $stmt = $pdo->prepare("
        SELECT u.*, rp.restaurant_id, rp.restaurant_name, rp.restaurant_type, 
               rp.description, rp.avg_rating
        FROM Users u 
        LEFT JOIN RestaurantProfiles rp ON u.user_id = rp.user_id 
        WHERE u.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $restaurant_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Processar atualizações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_basic_info':
            try {
                $stmt = $pdo->prepare("
                    UPDATE Users 
                    SET first_name = ?, last_name = ?, email = ?, contact = ?, 
                        country = ?, city = ?, profile_image_url = ?, specialization = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['email'],
                    $_POST['contact'],
                    $_POST['country'],
                    $_POST['city'],
                    $_POST['profile_image_url'],
                    $_POST['specialization'],
                    $_SESSION['user_id']
                ]);
                
                $stmt = $pdo->prepare("
                    UPDATE RestaurantProfiles 
                    SET restaurant_name = ?, restaurant_type = ?, description = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $_POST['restaurant_name'],
                    $_POST['restaurant_type'],
                    $_POST['description'],
                    $_SESSION['user_id']
                ]);
                
                $success_message = 'Informações básicas atualizadas com sucesso!';
                
                // Atualizar sessão
                $_SESSION['user_first_name'] = $_POST['first_name'];
                $_SESSION['user_last_name'] = $_POST['last_name'];
                $_SESSION['user_email'] = $_POST['email'];
                
            } catch (PDOException $e) {
                $error_message = 'Erro ao atualizar informações: ' . $e->getMessage();
            }
            break;
            
        case 'change_password':
            if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
                // Verificar senha atual
                $stmt = $pdo->prepare("SELECT password_hash FROM Users WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $current_hash = $stmt->fetchColumn();
                
                if (password_verify($_POST['current_password'], $current_hash)) {
                    if ($_POST['new_password'] === $_POST['confirm_password']) {
                        $new_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE Users SET password_hash = ? WHERE user_id = ?");
                        $stmt->execute([$new_hash, $_SESSION['user_id']]);
                        $success_message = 'Senha alterada com sucesso!';
                    } else {
                        $error_message = 'As novas senhas não coincidem!';
                    }
                } else {
                    $error_message = 'Senha atual incorreta!';
                }
            }
            break;
    }
    
    // Recarregar dados após atualização
    $stmt = $pdo->prepare("
        SELECT u.*, rp.restaurant_id, rp.restaurant_name, rp.restaurant_type, 
               rp.description, rp.avg_rating
        FROM Users u 
        LEFT JOIN RestaurantProfiles rp ON u.user_id = rp.user_id 
        WHERE u.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $restaurant_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obter estatísticas do restaurante
$stmt = $pdo->prepare("
    SELECT 
        COUNT(c.contract_id) as total_contracts,
        COUNT(CASE WHEN c.status = 'ativo' THEN 1 END) as active_contracts,
        COUNT(CASE WHEN c.status = 'concluído' THEN 1 END) as completed_contracts,
        AVG(CASE WHEN r.overall_rating IS NOT NULL THEN r.overall_rating END) as avg_received_rating
    FROM Contracts c
    LEFT JOIN Reviews r ON c.contract_id = r.contract_id AND r.reviewee_id = ?
    WHERE c.restaurant_id = ?
");
$stmt->execute([$_SESSION['user_id'], $restaurant_data['restaurant_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Obter contratos recentes
$stmt = $pdo->prepare("
    SELECT c.*, u.first_name, u.last_name, fp.hourly_rate
    FROM Contracts c
    JOIN FreelancerProfiles fp ON c.freelancer_id = fp.profile_id
    JOIN Users u ON fp.user_id = u.user_id
    WHERE c.restaurant_id = ?
    ORDER BY c.created_at DESC
    LIMIT 5
");
$stmt->execute([$restaurant_data['restaurant_id']]);
$recent_contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Perfil - <?= htmlspecialchars($restaurant_data['restaurant_name']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .restaurante-dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            overflow: hidden;
        }

        .cabecalho-perfil-restaurante {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .cabecalho-perfil-restaurante::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .info-restaurante-principal {
            position: relative;
            z-index: 2;
        }

        .nome-estabelecimento {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .tipo-cozinha-info {
            font-size: 1.2em;
            opacity: 0.9;
            margin-bottom: 15px;
        }

        .classificacao-restaurante {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
        }

        .estrelas-avaliacao {
            color: #ffd700;
            font-size: 1.5em;
        }

        .navegacao-abas-gestao {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            overflow-x: auto;
        }

        .aba-gestao-item {
            padding: 15px 25px;
            cursor: pointer;
            border: none;
            background: transparent;
            font-size: 16px;
            font-weight: 500;
            color: #6c757d;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .aba-gestao-item:hover {
            background: #e9ecef;
            color: #495057;
        }

        .aba-gestao-item.ativa {
            color: #007bff;
            border-bottom-color: #007bff;
            background: white;
        }

        .conteudo-aba-gestao {
            display: none;
            padding: 30px;
            animation: fadeInUp 0.5s ease;
        }

        .conteudo-aba-gestao.ativa {
            display: block;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .formulario-edicao-perfil {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .campo-entrada-dados {
            display: flex;
            flex-direction: column;
        }

        .campo-entrada-dados.coluna-completa {
            grid-column: 1 / -1;
        }

        .rotulo-campo-entrada {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .entrada-texto-gestao, .area-texto-gestao, .selecao-opcoes {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }

        .entrada-texto-gestao:focus, .area-texto-gestao:focus, .selecao-opcoes:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .area-texto-gestao {
            resize: vertical;
            min-height: 100px;
        }

        .botao-acao-principal {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }

        .botao-acao-principal:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
        }

        .cartao-estatisticas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .item-estatistica {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.3);
        }

        .numero-estatistica {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .titulo-estatistica {
            font-size: 14px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .tabela-contratos-recentes {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .cabecalho-tabela-contratos {
            background: #f8f9fa;
            font-weight: 600;
        }

        .cabecalho-tabela-contratos th,
        .linha-tabela-contratos td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .linha-tabela-contratos:hover {
            background: #f8f9fa;
        }

        .status-contrato {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-ativo {
            background: #d4edda;
            color: #155724;
        }

        .status-pendente {
            background: #fff3cd;
            color: #856404;
        }

        .status-concluido {
            background: #d1ecf1;
            color: #0c5460;
        }

        .mensagem-sucesso, .mensagem-erro {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .mensagem-sucesso {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .mensagem-erro {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .secao-alteracao-senha {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-top: 30px;
        }

        .titulo-secao-senha {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .formulario-edicao-perfil {
                grid-template-columns: 1fr;
            }
            
            .navegacao-abas-gestao {
                flex-direction: column;
            }
            
            .cartao-estatisticas {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="restaurante-dashboard-container">
        <div class="cabecalho-perfil-restaurante">
            <div class="info-restaurante-principal">
                <h1 class="nome-estabelecimento"><?= htmlspecialchars($restaurant_data['restaurant_name']) ?></h1>
                <p class="tipo-cozinha-info"><?= htmlspecialchars($restaurant_data['restaurant_type']) ?></p>
                <div class="classificacao-restaurante">
                    <span class="estrelas-avaliacao">★★★★☆</span>
                    <span><?= number_format($restaurant_data['avg_rating'] ?: 0, 1) ?>/5.0</span>
                </div>
            </div>
        </div>

        <div class="navegacao-abas-gestao">
            <button class="aba-gestao-item ativa" onclick="mostrarAba('informacoes-basicas')">Informações Básicas</button>
            <button class="aba-gestao-item" onclick="mostrarAba('estatisticas')">Estatísticas</button>
            <button class="aba-gestao-item" onclick="mostrarAba('contratos')">Contratos</button>
            <button class="aba-gestao-item" onclick="mostrarAba('seguranca')">Segurança</button>
        </div>

        <?php if ($success_message): ?>
            <div class="mensagem-sucesso"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="mensagem-erro"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div id="informacoes-basicas" class="conteudo-aba-gestao ativa">
            <h2 style="margin-bottom: 25px; color: #333;">Informações do Restaurante</h2>
            
            <form method="POST" class="formulario-edicao-perfil">
                <input type="hidden" name="action" value="update_basic_info">
                
                <div class="campo-entrada-dados">
                    <label class="rotulo-campo-entrada">Nome do Proprietário</label>
                    <input type="text" name="first_name" class="entrada-texto-gestao" 
                           value="<?= htmlspecialchars($restaurant_data['first_name']) ?>" required>
                </div>

                <div class="campo-entrada-dados">
                    <label class="rotulo-campo-entrada">Sobrenome</label>
                    <input type="text" name="last_name" class="entrada-texto-gestao" 
                           value="<?= htmlspecialchars($restaurant_data['last_name']) ?>" required>
                </div>

                <div class="campo-entrada-dados">
                    <label class="rotulo-campo-entrada">Nome do Restaurante</label>
                    <input type="text" name="restaurant_name" class="entrada-texto-gestao" 
                           value="<?= htmlspecialchars($restaurant_data['restaurant_name']) ?>" required>
                </div>

                <div class="campo-entrada-dados">
                    <label class="rotulo-campo-entrada">Tipo de Cozinha</label>
                    <select name="restaurant_type" class="selecao-opcoes" required>
                        <option value="Italiana" <?= $restaurant_data['restaurant_type'] === 'Italiana' ? 'selected' : '' ?>>Italiana</option>
                        <option value="Chinesa" <?= $restaurant_data['restaurant_type'] === 'Chinesa' ? 'selected' : '' ?>>Chinesa</option>
                        <option value="Japonesa" <?= $restaurant_data['restaurant_type'] === 'Japonesa' ? 'selected' : '' ?>>Japonesa</option>
                        <option value="Brasileira" <?= $restaurant_data['restaurant_type'] === 'Brasileira' ? 'selected' : '' ?>>Brasileira</option>
                        <option value="Francesa" <?= $restaurant_data['restaurant_type'] === 'Francesa' ? 'selected' : '' ?>>Francesa</option>
                        <option value="Mexicana" <?= $restaurant_data['restaurant_type'] === 'Mexicana' ? 'selected' : '' ?>>Mexicana</option>
                        <option value="Vegana" <?= $restaurant_data['restaurant_type'] === 'Vegana' ? 'selected' : '' ?>>Vegana</option>
                        <option value="Fast Food" <?= $restaurant_data['restaurant_type'] === 'Fast Food' ? 'selected' : '' ?>>Fast Food</option>
                        <option value="Geral" <?= $restaurant_data['restaurant_type'] === 'Geral' ? 'selected' : '' ?>>Geral</option>
                    </select>
                </div>

                <div class="campo-entrada-dados">
                    <label class="rotulo-campo-entrada">Email</label>
                    <input type="email" name="email" class="entrada-texto-gestao" 
                           value="<?= htmlspecialchars($restaurant_data['email']) ?>" required>
                </div>

                <div class="campo-entrada-dados">
                    <label class="rotulo-campo-entrada">Contacto</label>
                    <input type="text" name="contact" class="entrada-texto-gestao" 
                           value="<?= htmlspecialchars($restaurant_data['contact']) ?>" required>
                </div>

                <div class="campo-entrada-dados">
                    <label class="rotulo-campo-entrada">País</label>
                    <input type="text" name="country" class="entrada-texto-gestao" 
                           value="<?= htmlspecialchars($restaurant_data['country'] ?: '') ?>">
                </div>

                <div class="campo-entrada-dados">
                    <label class="rotulo-campo-entrada">Cidade</label>
                    <input type="text" name="city" class="entrada-texto-gestao" 
                           value="<?= htmlspecialchars($restaurant_data['city'] ?: '') ?>">
                </div>

                <div class="campo-entrada-dados">
                    <label class="rotulo-campo-entrada">URL da Imagem de Perfil</label>
                    <input type="url" name="profile_image_url" class="entrada-texto-gestao" 
                           value="<?= htmlspecialchars($restaurant_data['profile_image_url'] ?: '') ?>">
                </div>

                <div class="campo-entrada-dados">
                    <label class="rotulo-campo-entrada">Especialização</label>
                    <input type="text" name="specialization" class="entrada-texto-gestao" 
                           value="<?= htmlspecialchars($restaurant_data['specialization'] ?: '') ?>"
                           placeholder="Ex: Comida orgânica, Eventos especiais">
                </div>

                <div class="campo-entrada-dados coluna-completa">
                    <label class="rotulo-campo-entrada">Descrição do Restaurante</label>
                    <textarea name="description" class="area-texto-gestao" 
                              placeholder="Descreva o seu restaurante, ambiente, especialidades..."><?= htmlspecialchars($restaurant_data['description'] ?: '') ?></textarea>
                </div>

                <div class="campo-entrada-dados coluna-completa">
                    <button type="submit" class="botao-acao-principal">Atualizar Informações</button>
                </div>
            </form>
        </div>

        <div id="estatisticas" class="conteudo-aba-gestao">
            <h2 style="margin-bottom: 25px; color: #333;">Estatísticas do Restaurante</h2>
            
            <div class="cartao-estatisticas">
                <div class="item-estatistica">
                    <div class="numero-estatistica"><?= $stats['total_contracts'] ?: 0 ?></div>
                    <div class="titulo-estatistica">Total de Contratos</div>
                </div>
                <div class="item-estatistica">
                    <div class="numero-estatistica"><?= $stats['active_contracts'] ?: 0 ?></div>
                    <div class="titulo-estatistica">Contratos Ativos</div>
                </div>
                <div class="item-estatistica">
                    <div class="numero-estatistica"><?= $stats['completed_contracts'] ?: 0 ?></div>
                    <div class="titulo-estatistica">Contratos Concluídos</div>
                </div>
                <div class="item-estatistica">
                    <div class="numero-estatistica"><?= number_format($stats['avg_received_rating'] ?: 0, 1) ?></div>
                    <div class="titulo-estatistica">Avaliação Média</div>
                </div>
            </div>
        </div>

        <div id="contratos" class="conteudo-aba-gestao">
            <h2 style="margin-bottom: 25px; color: #333;">Contratos Recentes</h2>
            
            <?php if (!empty($recent_contracts)): ?>
                <table class="tabela-contratos-recentes">
                    <thead class="cabecalho-tabela-contratos">
                        <tr>
                            <th>Freelancer</th>
                            <th>Título</th>
                            <th>Preço</th>
                            <th>Status</th>
                            <th>Data de Criação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_contracts as $contract): ?>
                            <tr class="linha-tabela-contratos">
                                <td><?= htmlspecialchars($contract['first_name'] . ' ' . $contract['last_name']) ?></td>
                                <td><?= htmlspecialchars($contract['title']) ?></td>
                                <td>€<?= number_format($contract['agreed_price'], 2) ?></td>
                                <td>
                                    <span class="status-contrato status-<?= $contract['status'] ?>">
                                        <?= ucfirst($contract['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($contract['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #6c757d; padding: 40px;">Ainda não tem contratos registados.</p>
            <?php endif; ?>
        </div>

        <div id="seguranca" class="conteudo-aba-gestao">
            <h2 style="margin-bottom: 25px; color: #333;">Configurações de Segurança</h2>
            
            <div class="secao-alteracao-senha">
                <h3 class="titulo-secao-senha">Alterar Senha</h3>
                
                <form method="POST" class="formulario-edicao-perfil">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="campo-entrada-dados">
                        <label class="rotulo-campo-entrada">Senha Atual</label>
                        <input type="password" name="current_password" class="entrada-texto-gestao" required>
                    </div>

                    <div class="campo-entrada-dados">
                        <label class="rotulo-campo-entrada">Nova Senha</label>
                        <input type="password" name="new_password" class="entrada-texto-gestao" required>
                    </div>

                    <div class="campo-entrada-dados coluna-completa">
                        <label class="rotulo-campo-entrada">Confirmar Nova Senha</label>
                        <input type="password" name="confirm_password" class="entrada-texto-gestao" required>
                    </div>

                    <div class="campo-entrada-dados coluna-completa">
                        <button type="submit" class="botao-acao-principal">Alterar Senha</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function mostrarAba(abaId) {
            // Remover classe ativa de todas as abas
            document.querySelectorAll('.aba-gestao-item').forEach(aba => {
                aba.classList.remove('ativa');
            });
            
            document.querySelectorAll('.conteudo-aba-gestao').forEach(conteudo => {
                conteudo.classList.remove('ativa');
            });
            
            // Adicionar classe ativa à aba selecionada
            event.target.classList.add('ativa');
            document.getElementById(abaId).classList.add('ativa');
        }
    </script>
</body>
</html>