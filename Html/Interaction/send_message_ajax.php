<?php
// send_message_ajax.php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$conversation_id = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : null;
$message_text = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';

if (!$conversation_id || empty($message_text)) {
    echo json_encode(['success' => false, 'error' => 'Dados em falta']);
    exit;
}

$pdo = getDbConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Erro de base de dados']);
    exit;
}

try {
    $sql = "INSERT INTO Messages (conversation_id, sender_id, message_text) VALUES (:conversation_id, :sender_id, :message_text)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':conversation_id' => $conversation_id,
        ':sender_id' => $current_user_id,
        ':message_text' => $message_text
    ]);
    $newMessageId = $pdo->lastInsertId();

    // Obter a mensagem completa para retornar (opcional, mas útil para UI)
    $stmtMsg = $pdo->prepare("SELECT m.message_id, m.sender_id, m.message_text, m.created_at, u.first_name, u.last_name, u.profile_image_url
                              FROM Messages m
                              JOIN Users u ON m.sender_id = u.user_id
                              WHERE m.message_id = :message_id");
    $stmtMsg->execute([':message_id' => $newMessageId]);
    $sentMessage = $stmtMsg->fetch();
    
    if ($sentMessage) {
         $sentMessage['created_at_formatted'] = date("H:i", strtotime($sentMessage['created_at']));
    }


    echo json_encode(['success' => true, 'message' => $sentMessage]);

} catch (PDOException $e) {
    error_log("Erro ao enviar mensagem AJAX: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao guardar mensagem.']);
}
?>