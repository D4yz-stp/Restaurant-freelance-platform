<?php
session_start();

// Variáveis para o formulário
$email = '';
$error_message = '';

// Configuração de debug
$debug_info = [];

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adicionar informação de debug - POST recebido
    $debug_info[] = "Método POST recebido";
    
    // Recuperar os dados do formulário
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Adicionar informação de debug - dados do formulário
    $debug_info[] = "Email recebido: " . $email;
    $debug_info[] = "Senha recebida: " . (empty($password) ? "Vazia" : "$password");
    
    // Validar os campos
    $errors = [];
    
    if (empty($email)) {
        $errors[] = "Email é obrigatório";
    }
    
    if (empty($password)) {
        $errors[] = "Senha é obrigatória";
    }
    
    // Adicionar informação de debug - validação
    if (!empty($errors)) {
        $debug_info[] = "Erros de validação: " . implode(", ", $errors);
    } else {
        $debug_info[] = "Validação bem-sucedida";
    }
    
    // Se não houver erros de validação, verificar no banco de dados
    if (empty($errors)) {
        try {
            // Conectar ao banco de dados
            $db_path = '../../database/TesteOlga.db';
            $debug_info[] = "Tentando conectar ao banco de dados: " . $db_path;
            
            if (!file_exists($db_path)) {
                $debug_info[] = "ERRO: Arquivo de banco de dados não encontrado!";
                $errors[] = "Banco de dados não encontrado";
            } else {
                $db = new SQLite3($db_path);
                $debug_info[] = "Conexão com banco de dados estabelecida";
                
                // Preparar a query
                $stmt = $db->prepare("
                    SELECT u.user_id, u.first_name, u.last_name, u.email, u.password_hash,
                           u.contact, u.country, u.city, r.role_name
                    FROM Users u
                    JOIN UserRoles ur ON u.user_id = ur.user_id
                    JOIN Roles r ON ur.role_id = r.role_id
                    WHERE u.email = :email
                ");
                $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                $result = $stmt->execute();
                $user = $result->fetchArray(SQLITE3_ASSOC);
                
                if (!$user) {
                    $debug_info[] = "Usuário não encontrado para o email: " . $email;
                    $errors[] = "Email ou senha inválidos";
                } else {
                    $debug_info[] = "Usuário encontrado: " . $user['first_name'] . " " . $user['last_name'];
                    $debug_info[] = "Papel do usuário: " . $user['role_name'];

                    
                    $debug_info[] = "Senha : " . $user['password_hash'];
                    // Verificar a senha
                    if (password_verify($password, $user['password_hash'])) {
                        $debug_info[] = "Senha verificada com sucesso!";
                        
                        // Autenticação bem-sucedida
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_first_name'] = $user['first_name'];
                        $_SESSION['user_last_name'] = $user['last_name'];
                        $_SESSION['user_role'] = $user['role_name'];
                        
                        // Atualizar o timestamp de último login
                        $stmt = $db->prepare("UPDATE Users SET last_login = CURRENT_TIMESTAMP WHERE user_id = :user_id");
                        $stmt->bindValue(':user_id', $user['user_id'], SQLITE3_INTEGER);
                        $stmt->execute();
                        
                        $debug_info[] = "Última data de login atualizada";
                        $debug_info[] = "Redirecionando para: ../Services/index.php";
                        
                        // Redirecionar para a página principal após login
                        header("Location: ../Services/main_service/index.php");
                        exit;
                    } else {
                        $debug_info[] = "Verificação de senha falhou!";
                        $errors[] = "Email ou senha inválidos";
                    }
                }
                
                $db->close();
                $debug_info[] = "Conexão com banco de dados fechada";
            }
        } catch (Exception $e) {
            $debug_info[] = "EXCEÇÃO: " . $e->getMessage();
            $errors[] = "Erro na base de dados: " . $e->getMessage();
        }
    }
    
    // Se houver erros, preparar a mensagem de erro
    if (!empty($errors)) {
        $error_message = implode(", ", $errors);
        $debug_info[] = "Mensagem de erro final: " . $error_message;
    }
}

// Determinar se vamos mostrar informações de debug (remova isso em produção)
$show_debug = true;
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="../../css/auth.css">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <h2>Entrar</h2>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="buttons-container">
                <button type="submit">Entrar</button>
            </div>
        </form>
        
        <p class="form-link">
            Ainda não tem conta? <a href="register.html">Registar</a>
        </p>
    </div>
    
   

    
    
</body>
</html>

