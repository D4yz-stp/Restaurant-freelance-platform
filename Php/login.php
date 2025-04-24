<?php
session_start();

// Configuração da base de dados SQLite
$db_path = '../database/OlgaRJ.db'; // Ajuste este caminho

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    // Coletar e sanitizar dados
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $contact = trim($_POST['contact']);
    $country = trim($_POST['country']);
    $city = trim($_POST['city']);

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

    if (empty($errors)) {
        try {
            $db = new SQLite3($db_path);

            // Verificar se email já existe
            $stmt = $db->prepare("SELECT user_id FROM Users WHERE email = :email");
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $result = $stmt->execute();
            
            if ($result->fetchArray()) {
                $errors[] = "Este email já está registado";
            } else {
                // Hash da password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // Inserir novo utilizador
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

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Registo realizado com sucesso!";
                    echo "Conta criada com sucesso!"; // Exibe a mensagem de sucesso
                } else {
                    $errors[] = "Erro ao criar conta";
                }
            }
        } catch (Exception $e) {
            $errors[] = "Erro na base de dados: " . $e->getMessage();
        }
    }

    // Se houver erros, exibe-os
    if (!empty($errors)) {
        echo "Erros: <br>";
        foreach ($errors as $error) {
            echo $error . "<br>";
        }
    }
} else {
    echo "Método de requisição inválido!";
}
?>
