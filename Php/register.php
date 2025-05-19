<?php
session_start();
// Configuração da base de dados SQLite
$db_path = '../database/TesteOlga.db'; // Ajuste este caminho conforme necessário

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Coletar e sanitizar dados básicos do usuário
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $contact = trim($_POST['contact'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $role = trim($_POST['role'] ?? '');
    
    // Validação básica
    if (empty($first_name)) $errors[] = "Nome é obrigatório";
    if (empty($last_name)) $errors[] = "Apelido é obrigatório";
    if (empty($email)) {
        $errors[] = "Email é obrigatório";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Formato de email inválido";
    }
    if (empty($password)) $errors[] = "Password é obrigatória";
    if (empty($contact)) $errors[] = "Contacto é obrigatório";
    if (empty($role)) $errors[] = "Perfil é obrigatório";
    
    // Validação específica de acordo com o role
    if ($role === 'freelancer') {
        $hourly_rate = isset($_POST['hourly_rate']) ? floatval($_POST['hourly_rate']) : 0;
        $availability = trim($_POST['availability'] ?? 'flexible');
        $experience_years = isset($_POST['experience_years']) ? intval($_POST['experience_years']) : 0;
        
        if ($hourly_rate <= 0) $errors[] = "Valor por hora deve ser maior que zero";
        if (empty($availability)) $errors[] = "Disponibilidade é obrigatória";
        if ($experience_years < 0) $errors[] = "Anos de experiência não pode ser negativo";
    } 
    elseif ($role === 'manager') {
        $stmt = $db->prepare("
            INSERT INTO RestaurantProfiles
            (user_id, restaurant_name, restaurant_type, description, avg_rating)
            VALUES
            (:user_id, :restaurant_name, :restaurant_type, :description, 0)
        ");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(':restaurant_name', $restaurant_name, SQLITE3_TEXT);
        $stmt->bindValue(':restaurant_type', $restaurant_type, SQLITE3_TEXT);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        
        if (!$stmt->execute()) {
            $errors[] = "Erro ao criar perfil de restaurante: " . $db->lastErrorMsg();
            $db->exec('ROLLBACK');
        }
    }
    else {
        $errors[] = "Perfil inválido selecionado";
    }
    
    // Se não houver erros, prosseguir com o registro
    if (empty($errors)) {
        try {
            $db = new SQLite3($db_path);
            
            // Habilitar chaves estrangeiras
            $db->exec('PRAGMA foreign_keys = ON;');
            
            // Iniciar transação
            $db->exec('BEGIN TRANSACTION');
            
            // Verificar se email já existe
            $stmt = $db->prepare("SELECT user_id FROM Users WHERE email = :email");
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $result = $stmt->execute();
            
            if ($result->fetchArray()) {
                $errors[] = "Este email já está registado";
                $db->exec('ROLLBACK');
            } else {
                // Hash da password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // 1. Inserir novo utilizador
                $stmt = $db->prepare("
                    INSERT INTO Users
                    (first_name, last_name, email, password_hash, contact, country, city)
                    VALUES
                    (:first_name, :last_name, :email, :password_hash, :contact, :country, :city)
                ");
                $stmt->bindValue(':first_name', $first_name, SQLITE3_TEXT);
                $stmt->bindValue(':last_name', $last_name, SQLITE3_TEXT);
                $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                $stmt->bindValue(':password_hash', $password_hash, SQLITE3_TEXT);
                $stmt->bindValue(':contact', $contact, SQLITE3_TEXT);
                $stmt->bindValue(':country', $country, SQLITE3_TEXT);
                $stmt->bindValue(':city', $city, SQLITE3_TEXT);
                
                if (!$stmt->execute()) {
                    $errors[] = "Erro ao criar usuário: " . $db->lastErrorMsg();
                    $db->exec('ROLLBACK');
                } else {
                    $user_id = $db->lastInsertRowID();
                    
                    // 2. Obter o ID do role
                    $role_name = ($role === 'manager') ? 'restaurant' : 'freelancer';
                    $stmt = $db->prepare("SELECT role_id FROM Roles WHERE role_name = :role_name");
                    $stmt->bindValue(':role_name', $role_name, SQLITE3_TEXT);
                    $result = $stmt->execute();
                    $role_row = $result->fetchArray(SQLITE3_ASSOC);
                    
                    if (!$role_row) {
                        // Se o role não existe, criar
                        $stmt = $db->prepare("INSERT INTO Roles (role_name) VALUES (:role_name)");
                        $stmt->bindValue(':role_name', $role_name, SQLITE3_TEXT);
                        $stmt->execute();
                        $role_id = $db->lastInsertRowID();
                    } else {
                        $role_id = $role_row['role_id'];
                    }
                    
                    // 3. Associar usuário ao role
                    $stmt = $db->prepare("INSERT INTO UserRoles (user_id, role_id) VALUES (:user_id, :role_id)");
                    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                    $stmt->bindValue(':role_id', $role_id, SQLITE3_INTEGER);
                    
                    if (!$stmt->execute()) {
                        $errors[] = "Erro ao associar usuário ao perfil: " . $db->lastErrorMsg();
                        $db->exec('ROLLBACK');
                    } else {
                        // 4. Criar perfil específico baseado no role
                        if ($role === 'freelancer') {
                            $stmt = $db->prepare("
                                INSERT INTO FreelancerProfiles
                                (user_id, hourly_rate, availability, experience_years, avg_rating)
                                VALUES
                                (:user_id, :hourly_rate, :availability, :experience_years, 0)
                            ");
                            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                            $stmt->bindValue(':hourly_rate', $hourly_rate, SQLITE3_FLOAT);
                            $stmt->bindValue(':availability', $availability, SQLITE3_TEXT);
                            $stmt->bindValue(':experience_years', $experience_years, SQLITE3_INTEGER);
                            
                            if (!$stmt->execute()) {
                                $errors[] = "Erro ao criar perfil de freelancer: " . $db->lastErrorMsg();
                                $db->exec('ROLLBACK');
                            }
                        } elseif ($role === 'manager') {
                            $stmt = $db->prepare("
                                INSERT INTO RestaurantProfiles
                                (user_id, restaurant_name, restaurant_type, description, avg_rating)
                                VALUES
                                (:user_id, :restaurant_name, :restaurant_type, :description, 0)
                            ");
                            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                            $stmt->bindValue(':restaurant_name', $restaurant_name, SQLITE3_TEXT);
                            $stmt->bindValue(':restaurant_type', $restaurant_type, SQLITE3_TEXT);
                            $stmt->bindValue(':description', $description, SQLITE3_TEXT);
                            
                            if (!$stmt->execute()) {
                                $errors[] = "Erro ao criar perfil de restaurante: " . $db->lastErrorMsg();
                                $db->exec('ROLLBACK');
                            }
                        }
                        
                        // Se chegou até aqui, todas as operações foram bem-sucedidas
                        if (empty($errors)) {
                            $db->exec('COMMIT');
                            $_SESSION['user_id'] = $user_id;
                            $_SESSION['user_email'] = $email;
                            $_SESSION['user_role'] = $role;
                            $_SESSION['success'] = "Registo realizado com sucesso!";
                            
                            // Redirecionar para a página apropriada após o registro
<<<<<<< HEAD
                            header("Location: ../../../Html/Services/main_service/index.php");
=======
                            header("Location: ../../index.php");
>>>>>>> 3afb210c91108a714c84a4104e29caca18fdfd36
                            exit;
                        } else {
                            $db->exec('ROLLBACK');
                        }
                    }
                }
            }
            $db->close();
        } catch (Exception $e) {
            $errors[] = "Erro na base de dados: " . $e->getMessage();
        }
    }
    
    // Se houver erros, armazena na sessão e redireciona de volta para o formulário
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        // Armazena os dados do formulário para preencher novamente
        $_SESSION['form_data'] = $_POST;
        header("Location: ../../Html/Authentication/register.html");
        exit;
    }
} else {
    // Se não for um POST, redireciona para o formulário
    header("Location: ../../Html/Authentication/register.html");
    exit;
}
?>