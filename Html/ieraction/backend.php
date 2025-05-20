<?php
/*
 * Arquivo de backend para o sistema de mensagens
 * Este arquivo contém as funções PHP para gerenciar mensagens entre freelancers e restaurantes
 */

// Configuração da conexão com banco de dados
$dbConfig = [
    'host' => 'localhost',
    'username' => 'seu_usuario',
    'password' => 'sua_senha',
    'database' => 'freelance_db'
];

// Estabelecer conexão com o banco de dados
function dbConnect() {
    global $dbConfig;
    
    try {
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // Em produção, não exiba o erro completo para o usuário
        error_log("Erro de conexão: " . $e->getMessage());
        return false;
    }
}

// Iniciar sessão e verificar autenticação
session_start();

function checkAuth() {
    // Verifica se o usuário está logado e retorna seus dados
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    return [
        'user_id' => $_SESSION['user_id'],
        'role' => $_SESSION['role']
    ];
}

// Obter o ID do perfil com base no ID do usuário e função
function getProfileId($userId, $role) {
    $db = dbConnect();
    
    if ($role == 'freelancer') {
        $stmt = $db->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = ?");
    } else if ($role == 'restaurant') {
        $stmt = $db->prepare("SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = ?");
    } else {
        return false;
    }
    
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    return $result ? ($role == 'freelancer' ? $result['profile_id'] : $result['restaurant_id']) : false;
}

// Obter todas as conversas do usuário atual
function getUserConversations($limit = 10, $offset = 0) {
    $user = checkAuth();
    $db = dbConnect();
    
    $profileId = getProfileId($user['user_id'], $user['role']);
    if (!$profileId) return [];
    
    $field = ($user['role'] == 'freelancer') ? 'freelancer_id' : 'restaurant_id';
    
    $query = "
        SELECT c.conversation_id, c.created_at,
               CASE 
                   WHEN {$field} = ? THEN
                       CASE 
                           WHEN '{$user['role']}' = 'freelancer' THEN r.restaurant_name
                           ELSE CONCAT(u.first_name, ' ', u.last_name)
                       END
               END as contact_name,
               (SELECT message_text FROM Messages 
                WHERE conversation_id = c.conversation_id 
                ORDER BY created_at DESC LIMIT 1) as last_message,
               (SELECT created_at FROM Messages 
                WHERE conversation_id = c.conversation_id 
                ORDER BY created_at DESC LIMIT 1) as last_message_time,
               (SELECT COUNT(*) FROM Messages 
                WHERE conversation_id = c.conversation_id 
                AND sender_id != ? 
                AND is_read = 0) as unread_count,
               CASE
                   WHEN '{$user['role']}' = 'freelancer' THEN r.user_id
                   ELSE fp.user_id
               END as contact_user_id,
               CASE
                   WHEN '{$user['role']}' = 'freelancer' THEN r.restaurant_id
                   ELSE fp.profile_id
               END as contact_profile_id
        FROM Conversations c
        LEFT JOIN FreelancerProfiles fp ON c.freelancer_id = fp.profile_id
        LEFT JOIN RestaurantProfiles r ON c.restaurant_id = r.restaurant_id
        LEFT JOIN Users u ON (
            CASE 
                WHEN '{$user['role']}' = 'freelancer' THEN r.user_id = u.user_id
                ELSE fp.user_id = u.user_id
            END
        )
        WHERE {$field} = ?
        ORDER BY last_message_time DESC
        LIMIT ? OFFSET ?
    ";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute([$profileId, $user['user_id'], $profileId, $limit, $offset]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erro ao buscar conversas: " . $e->getMessage());
        return [];
    }
}

// Obter detalhes de uma conversa específica
function getConversationDetails($conversationId) {
    $user = checkAuth();
    $db = dbConnect();
    
    $profileId = getProfileId($user['user_id'], $user['role']);
    if (!$profileId) return null;
    
    $field = ($user['role'] == 'freelancer') ? 'freelancer_id' : 'restaurant_id';
    
    $query = "
        SELECT c.conversation_id, c.freelancer_id, c.restaurant_id, c.created_at,
               CASE 
                   WHEN {$field} = ? THEN 
                       CASE WHEN '{$user['role']}' = 'freelancer' THEN r.restaurant_name
                            ELSE CONCAT(u.first_name, ' ', u.last_name)
                       END
               END as contact_name,
               CASE 
                   WHEN '{$user['role']}' = 'freelancer' THEN r.user_id
                   ELSE fp.user_id
               END as contact_user_id,
               (SELECT user_id FROM Users WHERE user_id = 
                   CASE 
                       WHEN '{$user['role']}' = 'freelancer' THEN r.user_id
                       ELSE fp.user_id
                   END
                AND last_login > DATE_SUB(NOW(), INTERVAL 10 MINUTE)) as is_online
        FROM Conversations c
        LEFT JOIN FreelancerProfiles fp ON c.freelancer_id = fp.profile_id
        LEFT JOIN RestaurantProfiles r ON c.restaurant_id = r.restaurant_id
        LEFT JOIN Users u ON (
            CASE 
                WHEN '{$user['role']}' = 'freelancer' THEN r.user_id = u.user_id
                ELSE fp.user_id = u.user_id
            END
        )
        WHERE c.conversation_id = ? AND {$field} = ?
    ";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute([$profileId, $conversationId, $profileId]);
        $conversation = $stmt->fetch();
        
        if (!$conversation) return null;
        
        // Buscar contrato associado se existir
        $contractQuery = "
            SELECT contract_id, title, description, agreed_price, payment_type, 
                  start_date, end_date, status 
            FROM Contracts 
            WHERE restaurant_id = ? AND freelancer_id = ? 
            ORDER BY created_at DESC LIMIT 1
        ";
        
        $stmtContract = $db->prepare($contractQuery);
        $stmtContract->execute([$conversation['restaurant_id'], $conversation['freelancer_id']]);
        $conversation['contract'] = $stmtContract->fetch();
        
        return $conversation;
    } catch (PDOException $e) {
        error_log("Erro ao buscar detalhes da conversa: " . $e->getMessage());
        return null;
    }
}

// Obter mensagens de uma conversa
function getConversationMessages($conversationId, $limit = 20, $before = null) {
    $user = checkAuth();
    $db = dbConnect();
    
    $profileId = getProfileId($user['user_id'], $user['role']);
    if (!$profileId) return [];
    
    $field = ($user['role'] == 'freelancer') ? 'freelancer_id' : 'restaurant_id';
    
    // Verificar se o usuário tem acesso a esta conversa
    $checkQuery = "SELECT conversation_id FROM Conversations WHERE conversation_id = ? AND {$field} = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$conversationId, $profileId]);
    
    if (!$checkStmt->fetch()) {
        return []; // Usuário não tem acesso a esta conversa
    }
    
    $params = [$conversationId];
    $beforeClause = "";
    
    if ($before) {
        $beforeClause = "AND created_at < ?";
        $params[] = $before;
    }
    
    $query = "
        SELECT m.message_id, m.sender_id, m.message_text, m.is_read, m.created_at,
               (CASE WHEN m.sender_id = ? THEN 'sent' ELSE 'received' END) as type,
               CONCAT(u.first_name, ' ', u.last_name) as sender_name
        FROM Messages m
        JOIN Users u ON m.sender_id = u.user_id
        WHERE m.conversation_id = ? {$beforeClause}
        ORDER BY m.created_at DESC
        LIMIT ?
    ";
    
    array_unshift($params, $user['user_id']); // Adicionar user_id como primeiro parâmetro
    $params[] = $limit;
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $messages = $stmt->fetchAll();
        
        // Marcar mensagens recebidas como lidas
        $updateQuery = "
            UPDATE Messages 
            SET is_read = 1 
            WHERE conversation_id = ? AND sender_id != ? AND is_read = 0
        ";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->execute([$conversationId, $user['user_id']]);
        
        return array_reverse($messages); // Reverter para ordem cronológica
    } catch (PDOException $e) {
        error_log("Erro ao buscar mensagens: " . $e->getMessage());
        return [];
    }
}

// Enviar uma nova mensagem
function sendMessage($conversationId, $messageText) {
    $user = checkAuth();
    $db = dbConnect();
    
    if (empty($messageText)) {
        return [
            'success' => false,
            'message' => 'Mensagem não pode estar vazia'
        ];
    }
    
    $profileId = getProfileId($user['user_id'], $user['role']);
    if (!$profileId) {
        return [
            'success' => false,
            'message' => 'Perfil de usuário não encontrado'
        ];
    }
    
    $field = ($user['role'] == 'freelancer') ? 'freelancer_id' : 'restaurant_id';
    
    // Verificar se o usuário tem acesso a esta conversa
    $checkQuery = "SELECT conversation_id FROM Conversations WHERE conversation_id = ? AND {$field} = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$conversationId, $profileId]);
    
    if (!$checkStmt->fetch()) {
        return [
            'success' => false,
            'message' => 'Você não tem acesso a esta conversa'
        ];
    }
    
    try {
        $query = "
            INSERT INTO Messages (conversation_id, sender_id, message_text)
            VALUES (?, ?, ?)
        ";
        $stmt = $db->prepare($query);
        $stmt->execute([$conversationId, $user['user_id'], $messageText]);
        
        $messageId = $db->lastInsertId();
        
        return [
            'success' => true,
            'message_id' => $messageId,
            'created_at' => date('Y-m-d H:i:s'),
            'type' => 'sent'
        ];
    } catch (PDOException $e) {
        error_log("Erro ao enviar mensagem: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erro ao enviar mensagem'
        ];
    }
}

// Criar uma nova conversa
function createConversation($contactId, $initialMessage = null) {
    $user = checkAuth();
    $db = dbConnect();
    
    $myProfileId = getProfileId($user['user_id'], $user['role']);
    if (!$myProfileId) {
        return [
            'success' => false,
            'message' => 'Perfil de usuário não encontrado'
        ];
    }
    
    // Determinar o tipo de perfil do contato
    $contactRole = ($user['role'] == 'freelancer') ? 'restaurant' : 'freelancer';
    
    // Obter ID do perfil do contato
    $contactProfileId = null;
    
    if ($contactRole == 'freelancer') {
        $query = "SELECT profile_id FROM FreelancerProfiles WHERE user_id = ?";
    } else {
        $query = "SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = ?";
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute([$contactId]);
    $result = $stmt->fetch();
    
    if (!$result) {
        return [
            'success' => false,
            'message' => 'Perfil do contato não encontrado'
        ];
    }
    
    $contactProfileId = ($contactRole == 'freelancer') ? $result['profile_id'] : $result['restaurant_id'];
    
    // Verificar se já existe uma conversa entre os dois
    $checkQuery = "
        SELECT conversation_id 
        FROM Conversations 
        WHERE freelancer_id = ? AND restaurant_id = ?
    ";
    
    $freelancerId = ($user['role'] == 'freelancer') ? $myProfileId : $contactProfileId;
    $restaurantId = ($user['role'] == 'restaurant') ? $myProfileId : $contactProfileId;
    
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$freelancerId, $restaurantId]);
    $existingConversation = $checkStmt->fetch();
    
    if ($existingConversation) {
        // Se a conversa já existe, apenas retornar o ID dela
        $conversationId = $existingConversation['conversation_id'];
    } else {
        // Criar nova conversa
        $createQuery = "
            INSERT INTO Conversations (freelancer_id, restaurant_id)
            VALUES (?, ?)
        ";
        $createStmt = $db->prepare($createQuery);
        $createStmt->execute([$freelancerId, $restaurantId]);
        
        $conversationId = $db->lastInsertId();
    }
    
    // Se tem mensagem inicial, enviar
    if ($initialMessage) {
        $msgQuery = "
            INSERT INTO Messages (conversation_id, sender_id, message_text)
            VALUES (?, ?, ?)
        ";
        $msgStmt = $db->prepare($msgQuery);
        $msgStmt->execute([$conversationId, $user['user_id'], $initialMessage]);
    }
    
    return [
        'success' => true,
        'conversation_id' => $conversationId
    ];
}

// API para lidar com requisições AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    
    switch ($action) {
        case 'get_conversations':
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
            $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
            echo json_encode(getUserConversations($limit, $offset));
            break;
            
        case 'get_conversation':
            if (!isset($_GET['id'])) {
                echo json_encode(['error' => 'ID da conversa não fornecido']);
                break;
            }
            $conversationId = intval($_GET['id']);
            echo json_encode(getConversationDetails($conversationId));
            break;
            
        case 'get_messages':
            if (!isset($_GET['conversation_id'])) {
                echo json_encode(['error' => 'ID da conversa não fornecido']);
                break;
            }
            $conversationId = intval($_GET['conversation_id']);
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
            $before = isset($_GET['before']) ? $_GET['before'] : null;
            echo json_encode(getConversationMessages($conversationId, $limit, $before));
            break;
            
        case 'send_message':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['error' => 'Método não permitido']);
                break;
            }
            
            if (!isset($_POST['conversation_id']) || !isset($_POST['message'])) {
                echo json_encode(['error' => 'Dados incompletos']);
                break;
            }
            
            $conversationId = intval($_POST['conversation_id']);
            $message = $_POST['message'];
            echo json_encode(sendMessage($conversationId, $message));
            break;
            
        case 'create_conversation':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['error' => 'Método não permitido']);
                break;
            }
            
            if (!isset($_POST['contact_id'])) {
                echo json_encode(['error' => 'ID do contato não fornecido']);
                break;
            }
            
            $contactId = intval($_POST['contact_id']);
            $initialMessage = isset($_POST['message']) ? $_POST['message'] : null;
            echo json_encode(createConversation($contactId, $initialMessage));
            break;
            
        default:
            echo json_encode(['error' => 'Ação desconhecida']);
    }
    
    exit;
}

// Se não for uma requisição AJAX, exibe a interface
$user = checkAuth();
$conversations = getUserConversations();
?>

<!-- 
    Esta parte seria integrada com o arquivo HTML/CSS 
    Para usar o PHP com o HTML existente, você pode incluir este arquivo no topo do HTML
    e utilizar os dados das variáveis PHP no conteúdo, ou adaptar para usar AJAX
-->