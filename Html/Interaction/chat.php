<?php
session_start();

// Incluir as classes necessárias
require_once '../Services/components/database.php';
require_once 'components/MessagesRepository.php';

$conversation_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : null;

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../Log/login.php');
    exit();
}

// Inicializar conexão com banco
$db = Database::getInstance();
$messagesRepo = new MessagesRepository($db);

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];

// Processar requisições AJAX
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_conversations':
            echo json_encode($messagesRepo->getConversations($current_user_id, $current_user_role));
            exit();
            
        case 'get_messages':
            $conversation_id = $_POST['conversation_id'];
            echo json_encode($messagesRepo->getMessages($conversation_id));
            exit();
            
        case 'send_message':
            $conversation_id = $_POST['conversation_id'];
            $message_text = $_POST['message_text'];
            echo json_encode($messagesRepo->sendMessage($conversation_id, $current_user_id, $message_text));
            exit();
            
        case 'create_conversation':
            $target_user_id = $_POST['target_user_id'];
            echo json_encode($messagesRepo->createConversation($current_user_id, $target_user_id, $current_user_role));
            exit();
            
        case 'mark_as_read':
            $conversation_id = $_POST['conversation_id'];
            $result = $messagesRepo->markMessagesAsRead($conversation_id, $current_user_id);
            echo json_encode(['success' => $result]);
            exit();
            
        case 'set_typing':
            $conversation_id = $_POST['conversation_id'];
            $is_typing = $_POST['is_typing'];
            $result = $messagesRepo->setTypingIndicator($conversation_id, $current_user_id, $is_typing);
            echo json_encode(['success' => $result]);
            exit();
            
        case 'get_typing':
            $conversation_id = $_POST['conversation_id'];
            echo json_encode($messagesRepo->getTypingIndicator($conversation_id, $current_user_id));
            exit();
            
        case 'search_users':
            $search_term = $_POST['search_term'];
            echo json_encode($messagesRepo->searchUsers($search_term, $current_user_id, $current_user_role));
            exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensagens - RestaurantConnect</title>
    <link rel="stylesheet" href="../../Css/chat.css">
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Mensagens</h2>
                <div class="user-info">
                    <?php echo $_SESSION['user_first_name'] . ' ' . $_SESSION['user_last_name']; ?>
                    <span>(<?php echo ucfirst($_SESSION['user_role']); ?>)</span>
                </div>
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Procurar utilizadores..." class="search-input">
                    <button id="searchButton" class="search-button">🔍</button>
                </div>
            </div>
            <div class="conversations-list" id="conversationsList">
                <div class="loading">
                    <div class="spinner"></div>
                </div>
            </div>
        </div>

        <div class="chat-area">
            <div class="chat-header" id="chatHeader">
                <h3>Selecione uma conversa</h3>
            </div>
            
            <div class="messages-container" id="messagesContainer">
                <div class="empty-state">
                    Selecione uma conversa para começar a trocar mensagens
                </div>
            </div>
            
            <div class="typing-indicator" id="typingIndicator" style="display: none;">
            </div>
            
            <div class="message-input-area" id="messageInputArea" style="display: none;">
                <div class="message-input-container">
                    <textarea 
                        class="message-input" 
                        id="messageInput" 
                        placeholder="Digite sua mensagem..."
                        rows="1"
                    ></textarea>
                    <button class="send-button" id="sendButton" type="button">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Definir variável global antes de carregar o script
        window.currentUserId = <?php echo $_SESSION['user_id']; ?>;
    </script>
    <script src="../../JavaScript/chat.js"></script>
</body>
</html>