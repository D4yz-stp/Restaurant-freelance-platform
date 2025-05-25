<?php
session_start();

// Verificar se o usuário está logado e é admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Conectar à database
$db_path = realpath(__DIR__ . '/../../database/TesteOlga.db');
if ($db_path === false) {
    die("Arquivo de banco de dados não encontrado!");
}
try {
    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erro de conexão: ' . $e->getMessage());
}

// Processar ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'elevate_to_admin':
                $user_id = (int)$_POST['user_id'];
                // Primeiro, verificar se o usuário já tem role de admin
                $check_admin = $pdo->prepare("
                    SELECT ur.id FROM UserRoles ur 
                    JOIN Roles r ON ur.role_id = r.role_id 
                    WHERE ur.user_id = ? AND r.role_name = 'admin'
                ");
                $check_admin->execute([$user_id]);
                
                if (!$check_admin->fetch()) {
                    // Obter role_id do admin
                    $admin_role = $pdo->prepare("SELECT role_id FROM Roles WHERE role_name = 'admin'");
                    $admin_role->execute();
                    $admin_role_id = $admin_role->fetchColumn();
                    
                    // Adicionar role de admin
                    $stmt = $pdo->prepare("INSERT INTO UserRoles (user_id, role_id) VALUES (?, ?)");
                    $stmt->execute([$user_id, $admin_role_id]);
                    $success_msg = "Usuário promovido a administrador com sucesso!";
                } else {
                    $error_msg = "Usuário já é administrador!";
                }
                break;
                
            case 'add_category':
                $name = trim($_POST['category_name']);
                $description = trim($_POST['category_description']);
                if (!empty($name)) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO ServiceCategories (name, description) VALUES (?, ?)");
                        $stmt->execute([$name, $description]);
                        $success_msg = "Categoria adicionada com sucesso!";
                    } catch (PDOException $e) {
                        $error_msg = "Erro ao adicionar categoria: " . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_category':
                $category_id = (int)$_POST['category_id'];
                try {
                    $stmt = $pdo->prepare("DELETE FROM ServiceCategories WHERE category_id = ?");
                    $stmt->execute([$category_id]);
                    $success_msg = "Categoria deletada com sucesso!";
                } catch (PDOException $e) {
                    $error_msg = "Erro ao deletar categoria: " . $e->getMessage();
                }
                break;
                
            case 'send_message':
                $recipient_id = (int)$_POST['recipient_id'];
                $message_text = trim($_POST['message_text']);
                if (!empty($message_text)) {
                    // Criar ou encontrar conversa (assumindo conversa admin-user)
                    $stmt = $pdo->prepare("
                        INSERT INTO Messages (conversation_id, sender_id, message_text) 
                        VALUES (1, ?, ?)
                    ");
                    $stmt->execute([$_SESSION['user_id'], $message_text]);
                    $success_msg = "Mensagem enviada com sucesso!";
                }
                break;
        }
    }
}

// Obter estatísticas gerais
$stats = [];

// Total de usuários
$stmt = $pdo->query("SELECT COUNT(*) FROM Users");
$stats['total_users'] = $stmt->fetchColumn();

// Freelancers ativos
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT fp.user_id) 
    FROM FreelancerProfiles fp 
    JOIN UserRoles ur ON fp.user_id = ur.user_id 
    JOIN Roles r ON ur.role_id = r.role_id 
    WHERE r.role_name = 'freelancer'
");
$stats['active_freelancers'] = $stmt->fetchColumn();

// Restaurantes cadastrados
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT rp.user_id) 
    FROM RestaurantProfiles rp 
    JOIN UserRoles ur ON rp.user_id = ur.user_id 
    JOIN Roles r ON ur.role_id = r.role_id 
    WHERE r.role_name = 'restaurant'
");
$stats['registered_restaurants'] = $stmt->fetchColumn();

// Contratos/serviços realizados
$stmt = $pdo->query("SELECT COUNT(*) FROM Contracts");
$stats['total_contracts'] = $stmt->fetchColumn();

// Crescimento de usuários por mês (últimos 6 meses)
$monthly_growth = $pdo->query("
    SELECT 
        strftime('%Y-%m', created_at) as month,
        COUNT(*) as new_users
    FROM Users 
    WHERE created_at >= date('now', '-6 months')
    GROUP BY strftime('%Y-%m', created_at)
    ORDER BY month DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// Serviços mais solicitados
$popular_services = $pdo->query("
    SELECT 
        sc.name as category_name,
        COUNT(c.contract_id) as contract_count
    FROM ServiceCategories sc
    LEFT JOIN Services s ON sc.category_id = s.category_id
    LEFT JOIN Contracts c ON s.service_id = c.service_id
    GROUP BY sc.category_id, sc.name
    ORDER BY contract_count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Atividades recentes - últimos cadastros
$recent_users = $pdo->query("
    SELECT u.first_name, u.last_name, u.email, u.created_at, r.role_name
    FROM Users u
    LEFT JOIN UserRoles ur ON u.user_id = ur.user_id
    LEFT JOIN Roles r ON ur.role_id = r.role_id
    ORDER BY u.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Últimos serviços contratados
$recent_contracts = $pdo->query("
    SELECT 
        c.title,
        c.agreed_price,
        c.created_at,
        u1.first_name as restaurant_name,
        u2.first_name as freelancer_name
    FROM Contracts c
    JOIN RestaurantProfiles rp ON c.restaurant_id = rp.restaurant_id
    JOIN FreelancerProfiles fp ON c.freelancer_id = fp.profile_id
    JOIN Users u1 ON rp.user_id = u1.user_id
    JOIN Users u2 ON fp.user_id = u2.user_id
    ORDER BY c.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Lista de usuários para gerenciamento
$filter_role = $_GET['filter_role'] ?? '';
$filter_date = $_GET['filter_date'] ?? '';

$user_query = "
    SELECT DISTINCT
        u.user_id,
        u.first_name,
        u.last_name,
        u.email,
        u.created_at,
        u.last_login,
        GROUP_CONCAT(r.role_name) as roles
    FROM Users u
    LEFT JOIN UserRoles ur ON u.user_id = ur.user_id
    LEFT JOIN Roles r ON ur.role_id = r.role_id
    WHERE 1=1
";

$params = [];
if (!empty($filter_role)) {
    $user_query .= " AND r.role_name = ?";
    $params[] = $filter_role;
}
if (!empty($filter_date)) {
    $user_query .= " AND DATE(u.created_at) >= ?";
    $params[] = $filter_date;
}

$user_query .= " GROUP BY u.user_id ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($user_query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter categorias para gerenciamento
$categories = $pdo->query("
    SELECT category_id, name, description 
    FROM ServiceCategories 
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo</title>
    <link rel="stylesheet" href="../../Css/Painel.css">
    <link rel="stylesheet" href="../../Css/global.css">
    <link rel="stylesheet" href="../../Css/header+button.css">
</head>
<body>
    <div id="admin-dashboard">
        <?php include __DIR__ . '/../Services/components/header.php'; ?>

        <div class="spacer" style="height: 40px;"></div>
        
        <?php if (isset($success_msg)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if (isset($error_msg)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <nav class="dashboard-nav">
            <ul>
                <li><a href="#dashboard" class="nav-link active" data-section="dashboard">Dashboard</a></li>
                <li><a href="#users" class="nav-link" data-section="users">Gerenciar Usuários</a></li>
                <li><a href="#categories" class="nav-link" data-section="categories">Categorias</a></li>
                <li><a href="#messages" class="nav-link" data-section="messages">Mensagens</a></li>
            </ul>
        </nav>

        <!-- Dashboard Principal -->
        <section id="dashboard" class="dashboard-section active">
            <h2>Dashboard Principal</h2>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total de Usuários</h3>
                    <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Freelancers Ativos</h3>
                    <div class="stat-number"><?php echo $stats['active_freelancers']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Restaurantes Cadastrados</h3>
                    <div class="stat-number"><?php echo $stats['registered_restaurants']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Contratos Realizados</h3>
                    <div class="stat-number"><?php echo $stats['total_contracts']; ?></div>
                </div>
            </div>

            <div class="dashboard-charts">
                <div class="chart-container">
                    <h3>Crescimento de Usuários (Últimos 6 Meses)</h3>
                    <div class="chart-data">
                        <?php foreach (array_reverse($monthly_growth) as $month_data): ?>
                            <div class="chart-bar">
                                <span class="month"><?php echo $month_data['month']; ?></span>
                                <div class="bar" style="height: <?php echo ($month_data['new_users'] * 10); ?>px;"></div>
                                <span class="value"><?php echo $month_data['new_users']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="popular-services">
                    <h3>Serviços Mais Solicitados</h3>
                    <ul class="services-list">
                        <?php foreach ($popular_services as $service): ?>
                            <li>
                                <span class="service-name"><?php echo htmlspecialchars($service['category_name']); ?></span>
                                <span class="service-count"><?php echo $service['contract_count']; ?> contratos</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="recent-activities">
                <div class="recent-section">
                    <h3>Últimos Cadastros</h3>
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Tipo</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role_name'] ?? 'Sem role'); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="recent-section">
                    <h3>Últimos Serviços Contratados</h3>
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Serviço</th>
                                <th>Restaurante</th>
                                <th>Freelancer</th>
                                <th>Valor</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_contracts as $contract): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($contract['title']); ?></td>
                                    <td><?php echo htmlspecialchars($contract['restaurant_name']); ?></td>
                                    <td><?php echo htmlspecialchars($contract['freelancer_name']); ?></td>
                                    <td>€<?php echo number_format($contract['agreed_price'], 2); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($contract['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Gerenciamento de Usuários -->
        <section id="users" class="dashboard-section">
            <h2>Gerenciamento de Usuários</h2>
            
            <div class="filters">
                <form method="GET" class="filter-form">
                    <select name="filter_role" id="filter-role">
                        <option value="">Todos os tipos</option>
                        <option value="freelancer" <?php echo $filter_role === 'freelancer' ? 'selected' : ''; ?>>Freelancers</option>
                        <option value="restaurant" <?php echo $filter_role === 'restaurant' ? 'selected' : ''; ?>>Restaurantes</option>
                        <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Administradores</option>
                    </select>
                    
                    <input type="date" name="filter_date" id="filter-date" value="<?php echo htmlspecialchars($filter_date); ?>" placeholder="Data mínima">
                    
                    <button type="submit" class="filter-btn">Filtrar</button>
                    <a href="?" class="clear-filters">Limpar</a>
                </form>
            </div>

            <div class="users-table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Roles</th>
                            <th>Cadastro</th>
                            <th>Último Login</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['roles'] ?? 'Sem role'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca'; ?></td>
                                <td class="user-actions">
                                    <?php if (strpos($user['roles'], 'admin') === false): ?>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="action" value="elevate_to_admin">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" class="action-btn promote-btn" onclick="return confirm('Promover usuário a administrador?')">Promover Admin</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="action-btn message-btn" onclick="openMessageModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')">Enviar Mensagem</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Gerenciamento de Categorias -->
        <section id="categories" class="dashboard-section">
            <h2>Gerenciamento de Categorias</h2>
            
            <div class="add-category-form">
                <h3>Adicionar Nova Categoria</h3>
                <form method="POST" class="category-form">
                    <input type="hidden" name="action" value="add_category">
                    <div class="form-group">
                        <label for="category-name">Nome da Categoria:</label>
                        <input type="text" id="category-name" name="category_name" required>
                    </div>
                    <div class="form-group">
                        <label for="category-description">Descrição:</label>
                        <textarea id="category-description" name="category_description" rows="3"></textarea>
                    </div>
                    <button type="submit" class="add-btn">Adicionar Categoria</button>
                </form>
            </div>

            <div class="categories-list">
                <h3>Categorias Existentes</h3>
                <table class="categories-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo $category['category_id']; ?></td>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                                <td>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?php echo $category['category_id']; ?>">
                                        <button type="submit" class="action-btn delete-btn" onclick="return confirm('Deletar categoria? Esta ação não pode ser desfeita.')">Deletar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Sistema de Mensagens -->
        <section id="messages" class="dashboard-section">
            <h2>Sistema de Mensagens</h2>
            <p>Funcionalidade de mensagens em desenvolvimento...</p>
        </section>
    </div>

    <!-- Modal para enviar mensagem -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeMessageModal()">&times;</span>
            <h3>Enviar Mensagem</h3>
            <form method="POST" id="messageForm">
                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="recipient_id" id="recipient_id">
                <div class="form-group">
                    <label>Para: <span id="recipient_name"></span></label>
                </div>
                <div class="form-group">
                    <label for="message_text">Mensagem:</label>
                    <textarea name="message_text" id="message_text" rows="5" required></textarea>
                </div>
                <button type="submit" class="send-btn">Enviar Mensagem</button>
            </form>
        </div>
    </div>

    <script>
        // Atualiza a classe active ao rolar a página
        window.addEventListener('scroll', () => {
            const sections = document.querySelectorAll('.dashboard-section');
            const navLinks = document.querySelectorAll('.nav-link');
            
            sections.forEach(section => {
                const rect = section.getBoundingClientRect();
                if (rect.top <= 100 && rect.bottom >= 100) {
                    const id = section.id;
                    navLinks.forEach(link => {
                        link.classList.toggle('active', link.dataset.section === id);
                    });
                }
            });
        });
        // Navegação entre seções
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remover classe active de todos os links e seções
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                document.querySelectorAll('.dashboard-section').forEach(s => s.classList.remove('active'));
                
                // Adicionar classe active ao link clicado
                this.classList.add('active');
                
                // Mostrar seção correspondente
                const sectionId = this.getAttribute('data-section');
                document.getElementById(sectionId).classList.add('active');
            });
        });

        // Modal para mensagens
        function openMessageModal(userId, userName) {
            document.getElementById('recipient_id').value = userId;
            document.getElementById('recipient_name').textContent = userName;
            document.getElementById('messageModal').style.display = 'block';
        }

        function closeMessageModal() {
            document.getElementById('messageModal').style.display = 'none';
            document.getElementById('message_text').value = '';
        }

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('messageModal');
            if (event.target === modal) {
                closeMessageModal();
            }
        }
    </script>
</body>
</html>