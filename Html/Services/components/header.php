<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Verifica se o usuário está logado
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
// Variável para verificar se é admin
$is_admin = false;
$user_data = null;

if ($is_logged_in) {
    // Usa prioritariamente os dados já salvos na sessão (mais eficiente)
    if (isset($_SESSION['user_first_name'])) {
        $user_data = [
            'first_name' => $_SESSION['user_first_name'],
            'last_name' => $_SESSION['user_last_name'],
            'email' => $_SESSION['user_email'],
            'role_name' => $_SESSION['user_role']
        ];
        
        // Verifica se é admin pela sessão
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            $is_admin = true;
        }
    } else {
        // Fallback: busca do banco apenas se não houver dados na sessão
        try {
            $db_path = __DIR__ . '../../../database/TesteOlga.db'; // Ajuste este caminho conforme a estrutura real
            
            if (!file_exists($db_path)) {
                error_log("Arquivo de banco de dados não encontrado: " . $db_path);
            } else {
                $db = new SQLite3($db_path);
               
                // Busca informações básicas do usuário
                $stmt = $db->prepare("
                    SELECT u.first_name, u.last_name, u.email, ur.role_id, r.role_name
                    FROM Users u
                    JOIN UserRoles ur ON u.user_id = ur.user_id
                    JOIN Roles r ON ur.role_id = r.role_id
                    WHERE u.user_id = :user_id
                ");
                $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                $result = $stmt->execute();
                $user_data = $result->fetchArray(SQLITE3_ASSOC);
                
                // Verifica se o usuário é admin
                if ($user_data && $user_data['role_name'] === 'admin') {
                    $is_admin = true;
                }
                
                // Para debug - verificar os dados obtidos
                if (!$user_data) {
                    error_log("Nenhuma informação de usuário encontrada para ID: " . $_SESSION['user_id']);
                }
                
                // Fecha a conexão com o banco de dados
                $db->close();
            }
        } catch (Exception $e) {
            // Em caso de erro, registra o erro e continua sem as informações adicionais
            error_log("Erro ao buscar informações do usuário: " . $e->getMessage());
        }
    }
}

// Para debug - verificar variáveis da sessão
error_log("Sessão user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'não definido'));
error_log("Sessão user_first_name: " . (isset($_SESSION['user_first_name']) ? $_SESSION['user_first_name'] : 'não definido'));
error_log("É admin: " . ($is_admin ? 'sim' : 'não'));
?>
<header class="main-header">
    <div id='top' class="container header-container">
        <!-- Logo e Nome do Site -->
        <div class="header-brand">
            <a href="/" aria-label="OlgaRJ Homepage">
                <h1> <a style="text-decoration: none; color: inherit;" href='/Html/index.php'>   OlgaRJ   </a>   </h1>
            </a>
        </div>
        <nav class="header-nav">
            <?php if ($is_logged_in && $user_data): ?>
                <!-- Dropdown do usuário logado -->
                <div class="user-dropdown">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user_data['first_name'], 0, 1)); ?>
                        </div>
                        <span class="user-name"><?php echo htmlspecialchars($user_data['first_name']); ?></span>
                        <span class="dropdown-icon">▼</span>
                    </div>
                    <div class="user-dropdown-content">
                        <a href="../../Log/perfil.php">Meu Perfil</a>
                        <?php if ($user_data['role_name'] === 'freelancer'): ?>
                            <a href="/Html/Profile/freelancer.php">Perfil Freelancer</a>
                        <?php elseif ($user_data['role_name'] === 'restaurant'): ?>
                            <a href="/Html/Profile/restaurant.php">Perfil Restaurante</a>
                        <?php endif; ?>
                        
                        <?php if ($is_admin): ?>
                            <a href="/Html/Admin/painel.php">Painel de Controle</a>
                        <?php endif; ?>
                        
                        <a href="/Html/Services/main_service/index.php">Serviços</a>
                        <a href="/Html/Log/logout.html">Sair</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Links para não logados -->
                <a href="/Html/Log/login.php" class="btn btn-primary">Entrar</a>
                <a href="/Html/Log/register.html" class="btn btn-secondary">Cadastrar</a>
            <?php endif; ?>
        </nav>
    </div>
</header>