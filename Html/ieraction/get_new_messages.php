<?php
// Iniciar sessão
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit();
}

// Verificar se os parâmetros necessários foram fornecidos
if (!isset($_GET['conversation_id']) || !isset($_GET['last_message_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Parâmetros insuficientes']);
    exit();
}

$conversation_id = (int)$_GET['conversation_id'];
$last_message_id = (int)$_GET['last_message_id'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$databasePath = '../../database/TesteOlga.db';
if (!file_exists($databasePath)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Banco de dados não encontrado']);
    exit();
}

// Conexão com o banco de dados
try {
    $db = new SQLite3($databasePath);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA foreign_keys = ON;');
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro na conexão com o banco de dados: ' . $e->getMessage()]);
    exit();
}

// Verificar se o usuário tem acesso à conversa
$valid_conversation = false;
$profile_id = null;

if ($user_role === 'freelancer') {
    $stmt = $db->prepare("SELECT f.profile_id FROM FreelancerProfiles f WHERE f.user_id = :user_id");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $profile_id = $row['profile_id'];
        
        $stmt = $db->prepare("SELECT conversation_id FROM Conversations 
                            WHERE conversation_id = :conversation_id AND freelancer_id = :profile_id");
        $stmt->bindValue(':conversation_id', $conversation_id, SQLITE3_INTEGER);
        $stmt->bindValue(':profile_id', $profile_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if ($result->fetchArray(SQLITE3_ASSOC)) {
            $valid_conversation = true;
        }
    }
} else if ($user_role === 'restaurant') {
    $stmt = $db->prepare("SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = :user_id");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $profile_id = $row['restaurant_id'];
        
        $stmt = $db->prepare("SELECT conversation_id FROM Conversations 
                            WHERE conversation_id = :conversation_id AND restaurant_id = :profile_id");
        $stmt->bindValue(':conversation_id', $conversation_id, SQLITE3_INTEGER);
        $stmt->bindValue(':profile_id', $profile_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if ($result->fetchArray(SQLITE3_ASSOC)) {
            $valid_conversation = true;
        }
    }
}

// Se o usuário não tiver acesso à conversa, retornar erro
if (!$valid_conversation) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Acesso negado a esta conversa']);
    exit();
}

// Buscar mensagens mais recentes que last_message_id
$messages = [];

try {
    $stmt = $db->prepare("SELECT m.message_id, m.sender_id, m.message_text, m.is_read, m.created_at,
                        u.first_name, u.last_name, u.profile_image_url
                        FROM Messages m
                        JOIN Users u ON m.sender_id = u.user_id
                        WHERE m.conversation_id = :conversation_id AND m.message_id > :last_message_id
                        ORDER BY m.created_at ASC");
    $stmt->bindValue(':conversation_id', $conversation_id, SQLITE3_INTEGER);
    $stmt->bindValue(':last_message_id', $last_message_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $messages[] = $row;
    }
    
    // Marcar mensagens como lidas se o remetente não for o usuário atual
    if (!empty($messages)) {
        $stmt = $db->prepare("UPDATE Messages SET is_read = 1 
                            WHERE conversation_id = :conversation_id AND sender_id != :user_id AND is_read = 0");
        $stmt->bindValue(':conversation_id', $conversation_id, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro ao buscar mensagens: ' . $e->getMessage()]);
}

// Fechar a conexão com o banco de dados
$db = null;
?>