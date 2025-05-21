<?php
// Iniciar sessão para gerenciar login do usuário
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar se é uma requisição AJAX
if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die("Acesso não permitido");
}

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Verificar se o ID da conversa foi fornecido
if (!isset($_GET['conversation'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID da conversa não fornecido']);
    exit;
}

// Conectar ao banco de dados SQLite
$db_file = '../../database/TesteOlga.db'; // Nome do arquivo do banco de dados SQLite
try {
    $conn = new PDO("sqlite:$db_file");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Falha na conexão com o banco de dados: ' . $e->getMessage()]);
    exit;
}

// Obter detalhes do usuário logado
$user_id = $_SESSION['user_id'];
$conversation_id = $_GET['conversation'];

// Obter role do usuário
$role_query = "SELECT r.role_name FROM UserRoles ur
               JOIN Roles r ON ur.role_id = r.role_id
               WHERE ur.user_id = :user_id";
$stmt = $conn->prepare($role_query);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$role = $stmt->fetch(PDO::FETCH_ASSOC)['role_name'];

// Determinar perfil do usuário
$profile_id = null;

if ($role == 'freelancer') {
    $profile_query = "SELECT profile_id FROM FreelancerProfiles WHERE user_id = :user_id";
} elseif ($role == 'restaurant') {
    $profile_query = "SELECT restaurant_id as profile_id FROM RestaurantProfiles WHERE user_id = :user_id";
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Tipo de usuário inválido']);
    exit;
}

$stmt = $conn->prepare($profile_query);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$profile_id = $stmt->fetch(PDO::FETCH_ASSOC)['profile_id'];

// Verificar se a conversa pertence ao usuário
$check_query = "";
if ($role == 'freelancer') {
    $check_query = "SELECT * FROM Conversations WHERE conversation_id = :conversation_id AND freelancer_id = :profile_id";
} else {
    $check_query = "SELECT * FROM Conversations WHERE conversation_id = :conversation_id AND restaurant_id = :profile_id";
}

$stmt = $conn->prepare($check_query);
$stmt->bindParam(':conversation_id', $conversation_id, PDO::PARAM_INT);
$stmt->bindParam(':profile_id', $profile_id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso negado a esta conversa']);
    exit;
}

// Obter mensagens da conversa
$messages_query = "SELECT m.message_id, m.message_text, m.created_at,
                  (m.sender_id = :user_id) as is_sender
                  FROM Messages m
                  WHERE m.conversation_id = :conversation_id
                  ORDER BY m.created_at ASC";

$stmt = $conn->prepare($messages_query);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindParam(':conversation_id', $conversation_id, PDO::PARAM_INT);
$stmt->execute();
$messages_result = $stmt->fetchAll(PDO::FETCH_ASSOC);

$messages = [];
foreach ($messages_result as $message) {
    $messages[] = [
        'id' => $message['message_id'],
        'message_text' => nl2br(htmlspecialchars($message['message_text'])),
        'is_sender' => (bool)$message['is_sender'],
        'time' => date('H:i', strtotime($message['created_at']))
    ];
}

// Marcar mensagens como lidas
$mark_read_query = "UPDATE Messages SET is_read = 1
                   WHERE conversation_id = :conversation_id AND sender_id != :user_id AND is_read = 0";
$stmt = $conn->prepare($mark_read_query);
$stmt->bindParam(':conversation_id', $conversation_id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();

// Enviar resposta
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'messages' => $messages
]);
?>
