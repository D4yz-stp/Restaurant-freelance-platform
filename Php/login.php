<?php
session_start();

// Verificar se o formulário foi enviado via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recuperar os dados do formulário
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validar os campos
    $errors = [];
    
    if (empty($email)) {
        $errors[] = "Email é obrigatório";
    }
    
    if (empty($password)) {
        $errors[] = "Senha é obrigatória";
    }
    
    // Se não houver erros de validação, verificar no banco de dados
    if (empty($errors)) {
        try {
            // Conectar ao banco de dados
            $db = new SQLite3('../database/TesteOlga.db');
            
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
                $errors[] = "Email ou senha inválidos";
            } else {
                // Verificar a senha
                if (password_verify($password, $user['password_hash'])) {
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
                    
                    // Redirecionar para a página principal após login
                    header("Location: ../Html/Services/index.php");
                    exit;
                } else {
                    $errors[] = "Email ou senha inválidos";
                }
            }
            
            $db->close();
        } catch (Exception $e) {
            $errors[] = "Erro na base de dados: " . $e->getMessage();
        }
    }
    
    // Se houver erros, redireciona de volta para o login com mensagens de erro
    if (!empty($errors)) {
        $error_message = urlencode(implode(", ", $errors));
        $redirect_url = "../Html/Log/login.html?error=" . $error_message;
        
        // Adicionar o email para preencher o campo automaticamente
        if (!empty($email)) {
            $redirect_url .= "&email=" . urlencode($email);
        }
        
        header("Location: " . $redirect_url);
        exit;
    }
} else {
    // Se não for um POST, redireciona para o formulário
    header("Location: ../Html/Log/login.html");
    exit;
}
?>