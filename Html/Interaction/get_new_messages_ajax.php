<?php
// get_new_messages_ajax.php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado', 'messages' => []]);
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$conversation_id = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : null;
$last_message_id = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;

if (!$conversation_id) {
    echo json_encode(['success' => false, 'error' => 'ID da conversa em falta', 'messages' => []]);
    exit;
}

$pdo = getDbConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Erro de base de dados', 'messages' => []]);
    exit;
}

try {
    // Buscar novas mensagens
    $stmt = $pdo->prepare("SELECT m.message_id, m.sender_id, m.message_text, m.created_at, m.is_read, u.first_name, u.last_name, u.profile_image_url
                           FROM Messages m
                           JOIN Users u ON m.sender_id = u.user_id
                           WHERE m.conversation_id = :conversation_id AND m.message_id > :last_message_id
                           ORDER BY m.created_at ASC");
    $stmt->execute([
        ':conversation_id' => $conversation_id,
        ':last_message_id' => $last_message_id
    ]);
    $newMessages = $stmt->fetchAll();

    $messagesToReturn = [];
    $messageIdsToMarkRead = [];

    foreach ($newMessages as $msg) {
        // Adicionar a informação se a mensagem foi enviada pelo utilizador atual
        $msg['is_sender_current_user'] = ($msg['sender_id'] == $current_user_id);
        $msg['created_at_formatted'] = date("H:i", strtotime($msg['created_at']));
        $messagesToReturn[] = $msg;

        // Marcar como lida se o destinatário for o utilizador atual e ainda não estiver lida
        if ($msg['sender_id'] != $current_user_id && $msg['is_read'] == 0) {
            $messageIdsToMarkRead[] = $msg['message_id'];
        }
    }

    // Marcar mensagens como lidas
    if (!empty($messageIdsToMarkRead)) {
        $placeholders = implode(',', array_fill(0, count($messageIdsToMarkRead), '?'));
        $updateStmt = $pdo->prepare("UPDATE Messages SET is_read = 1 WHERE message_id IN ($placeholders) AND conversation_id = ?");
        $params = array_merge($messageIdsToMarkRead, [$conversation_id]);
        $updateStmt->execute($params);
    }
    
    // Verificar se há mensagens não lidas pelo outro utilizador (para o status "lido" das suas mensagens)
    // Esta parte é para atualizar o status de "lido" das mensagens que o $current_user_id enviou
    $stmtReadStatus = $pdo->prepare(
        "SELECT message_id, MAX(is_read) as all_read_by_others
         FROM Messages
         WHERE conversation_id = :conversation_id AND sender_id = :current_user_id AND message_id > :last_message_id_for_sent_check
         GROUP BY message_id" // Agrupar por message_id não é bem o que queremos aqui.
                               // Queremos saber se as mensagens ENVIADAS pelo current_user foram lidas.
                               // Um modo mais simples é buscar todas as mensagens enviadas pelo current_user
                               // e verificar quais foram lidas (is_read = 1).
                               // Esta lógica de notificar o remetente que a mensagem foi lida pode ser complexa.
                               // Por agora, vamos focar em marcar as mensagens recebidas como lidas.
    );
    // A lógica de "duplo check" é mais complexa e pode ser adicionada depois.

    echo json_encode(['success' => true, 'messages' => $messagesToReturn]);

} catch (PDOException $e) {
    error_log("Erro ao buscar novas mensagens AJAX: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar mensagens.', 'messages' => []]);
}
?>