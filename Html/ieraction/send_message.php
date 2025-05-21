<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit();
}

if (!isset($_POST['conversation_id']) || !isset($_POST['message'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Parâmetros insuficientes']);
    exit();
}

$conversation_id = (int)$_POST['conversation_id'];
$message = trim($_POST['message']);
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

if (empty($message)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Mensagem vazia']);
    exit();
}

$databasePath = '../../database/TesteOlga.db';
if (!file_exists($databasePath) || !is_readable($databasePath)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro no banco de dados']);
    exit();
}

try {
    $db = new SQLite3($databasePath);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA foreign_keys = ON;');

    // Obter profile_id CORRETAMENTE
    $profile_id = null;
    if ($user_role === 'freelancer') {
        $stmt = $db->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = :user_id");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $profile_id = $row['profile_id'] ?? null;
    } else {
        $stmt = $db->prepare("SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = :user_id");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $profile_id = $row['restaurant_id'] ?? null;
    }

    if (!$profile_id) {
        throw new Exception("Perfil não encontrado");
    }

    // Validar acesso à conversa
    $valid_conversation = false;
    if ($user_role === 'freelancer') {
        $stmt = $db->prepare("SELECT 1 FROM Conversations 
                            WHERE conversation_id = :conversation_id 
                            AND freelancer_id = :profile_id");
    } else {
        $stmt = $db->prepare("SELECT 1 FROM Conversations 
                            WHERE conversation_id = :conversation_id 
                            AND restaurant_id = :profile_id");
    }
    
    $stmt->bindValue(':conversation_id', $conversation_id, SQLITE3_INTEGER);
    $stmt->bindValue(':profile_id', $profile_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $valid_conversation = (bool)$result->fetchArray();

    if (!$valid_conversation) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Acesso negado a esta conversa']);
        exit();
    }

    // Inserir mensagem
    $stmt = $db->prepare("INSERT INTO Messages (conversation_id, sender_id, message_text) 
                        VALUES (:conversation_id, :sender_id, :message_text)");
    $stmt->bindValue(':conversation_id', $conversation_id, SQLITE3_INTEGER);
    $stmt->bindValue(':sender_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':message_text', $message, SQLITE3_TEXT);
    
    if (!$stmt->execute()) {
        throw new Exception("Falha ao inserir mensagem");
    }

    // Atualizar status de digitação
    try {
        $db->exec("UPDATE TypingStatus SET is_typing = 0 
                 WHERE conversation_id = $conversation_id 
                 AND user_id = $user_id");
    } catch (Exception $e) {
        error_log("Erro ao atualizar status de digitação: " . $e->getMessage());
    }

    echo json_encode(['success' => true, 'message_id' => $db->lastInsertRowID()]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
} finally {
    $db = null;
}
?>