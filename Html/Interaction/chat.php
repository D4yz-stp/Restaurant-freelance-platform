<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $pdo = new PDO("sqlite: ../../../../database/TesteOlga.db"); // Altere conforme sua configuração
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];

// Processar requisições AJAX
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_conversations':
            echo json_encode(getConversations($pdo, $current_user_id, $current_user_role));
            exit();
            
        case 'get_messages':
            $conversation_id = $_POST['conversation_id'];
            echo json_encode(getMessages($pdo, $conversation_id, $current_user_id));
            exit();
            
        case 'send_message':
            $conversation_id = $_POST['conversation_id'];
            $message_text = $_POST['message_text'];
            echo json_encode(sendMessage($pdo, $conversation_id, $current_user_id, $message_text));
            exit();
            
        case 'create_conversation':
            $target_user_id = $_POST['target_user_id'];
            echo json_encode(createConversation($pdo, $current_user_id, $target_user_id, $current_user_role));
            exit();
            
        case 'mark_as_read':
            $conversation_id = $_POST['conversation_id'];
            markMessagesAsRead($pdo, $conversation_id, $current_user_id);
            echo json_encode(['success' => true]);
            exit();
            
        case 'set_typing':
            $conversation_id = $_POST['conversation_id'];
            $is_typing = $_POST['is_typing'];
            setTypingIndicator($pdo, $conversation_id, $current_user_id, $is_typing);
            echo json_encode(['success' => true]);
            exit();
            
        case 'get_typing':
            $conversation_id = $_POST['conversation_id'];
            echo json_encode(getTypingIndicator($pdo, $conversation_id, $current_user_id));
            exit();
            
        case 'search_users':
            $search_term = $_POST['search_term'];
            echo json_encode(searchUsers($pdo, $search_term, $current_user_id, $current_user_role));
            exit();
    }
}

// Funções do sistema de mensagens
function getConversations($pdo, $user_id, $user_role) {
    try {
        if ($user_role == 'restaurant') {
            $stmt = $pdo->prepare("
                SELECT c.conversation_id, c.created_at,
                       u.first_name, u.last_name, u.profile_image_url,
                       fp.profile_id as freelancer_id,
                       (SELECT message_text FROM Messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM Messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_time,
                       (SELECT COUNT(*) FROM Messages WHERE conversation_id = c.conversation_id AND sender_id != ? AND is_read = 0) as unread_count
                FROM Conversations c
                JOIN FreelancerProfiles fp ON c.freelancer_id = fp.profile_id
                JOIN Users u ON fp.user_id = u.user_id
                WHERE c.restaurant_id = (SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = ?)
                ORDER BY last_message_time DESC
            ");
            $stmt->execute([$user_id, $user_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT c.conversation_id, c.created_at,
                       u.first_name, u.last_name, u.profile_image_url,
                       rp.restaurant_id,
                       rp.restaurant_name,
                       (SELECT message_text FROM Messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM Messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_time,
                       (SELECT COUNT(*) FROM Messages WHERE conversation_id = c.conversation_id AND sender_id != ? AND is_read = 0) as unread_count
                FROM Conversations c
                JOIN RestaurantProfiles rp ON c.restaurant_id = rp.restaurant_id
                JOIN Users u ON rp.user_id = u.user_id
                WHERE c.freelancer_id = (SELECT profile_id FROM FreelancerProfiles WHERE user_id = ?)
                ORDER BY last_message_time DESC
            ");
            $stmt->execute([$user_id, $user_id]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['error' => $e->getMessage()];
    }
}

function getMessages($pdo, $conversation_id, $current_user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, u.first_name, u.last_name, u.profile_image_url
            FROM Messages m
            JOIN Users u ON m.sender_id = u.user_id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$conversation_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['error' => $e->getMessage()];
    }
}

function sendMessage($pdo, $conversation_id, $sender_id, $message_text) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Messages (conversation_id, sender_id, message_text, is_delivered, created_at)
            VALUES (?, ?, ?, 1, datetime('now'))
        ");
        $stmt->execute([$conversation_id, $sender_id, $message_text]);
        return ['success' => true, 'message_id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        return ['error' => $e->getMessage()];
    }
}

function createConversation($pdo, $current_user_id, $target_user_id, $current_user_role) {
    try {
        if ($current_user_role == 'restaurant') {
            $restaurant_stmt = $pdo->prepare("SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = ?");
            $restaurant_stmt->execute([$current_user_id]);
            $restaurant_id = $restaurant_stmt->fetchColumn();
            
            $freelancer_stmt = $pdo->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = ?");
            $freelancer_stmt->execute([$target_user_id]);
            $freelancer_id = $freelancer_stmt->fetchColumn();
        } else {
            $freelancer_stmt = $pdo->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = ?");
            $freelancer_stmt->execute([$current_user_id]);
            $freelancer_id = $freelancer_stmt->fetchColumn();
            
            $restaurant_stmt = $pdo->prepare("SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = ?");
            $restaurant_stmt->execute([$target_user_id]);
            $restaurant_id = $restaurant_stmt->fetchColumn();
        }
        
        // Verificar se a conversa já existe
        $check_stmt = $pdo->prepare("
            SELECT conversation_id FROM Conversations 
            WHERE restaurant_id = ? AND freelancer_id = ?
        ");
        $check_stmt->execute([$restaurant_id, $freelancer_id]);
        $existing = $check_stmt->fetchColumn();
        
        if ($existing) {
            return ['success' => true, 'conversation_id' => $existing];
        }
        
        // Criar nova conversa
        $stmt = $pdo->prepare("
            INSERT INTO Conversations (restaurant_id, freelancer_id, created_at)
            VALUES (?, ?, datetime('now'))
        ");
        $stmt->execute([$restaurant_id, $freelancer_id]);
        
        return ['success' => true, 'conversation_id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        return ['error' => $e->getMessage()];
    }
}

function markMessagesAsRead($pdo, $conversation_id, $user_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE Messages 
            SET is_read = 1, read_at = datetime('now')
            WHERE conversation_id = ? AND sender_id != ? AND is_read = 0
        ");
        $stmt->execute([$conversation_id, $user_id]);
    } catch (PDOException $e) {
        // Log error
    }
}

function setTypingIndicator($pdo, $conversation_id, $user_id, $is_typing) {
    try {
        $stmt = $pdo->prepare("
            INSERT OR REPLACE INTO TypingIndicators (conversation_id, user_id, is_typing, last_activity)
            VALUES (?, ?, ?, datetime('now'))
        ");
        $stmt->execute([$conversation_id, $user_id, $is_typing]);
    } catch (PDOException $e) {
        // Log error
    }
}

function getTypingIndicator($pdo, $conversation_id, $current_user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.first_name, ti.is_typing
            FROM TypingIndicators ti
            JOIN Users u ON ti.user_id = u.user_id
            WHERE ti.conversation_id = ? AND ti.user_id != ? AND ti.is_typing = 1
            AND ti.last_activity > datetime('now', '-5 seconds')
        ");
        $stmt->execute([$conversation_id, $current_user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function searchUsers($pdo, $search_term, $current_user_id, $current_user_role) {
    try {
        if ($current_user_role == 'restaurant') {
            // Restaurante procura freelancers
            $stmt = $pdo->prepare("
                SELECT u.user_id, u.first_name, u.last_name, u.profile_image_url,
                       fp.profile_id, fp.hourly_rate, fp.avg_rating
                FROM Users u
                JOIN UserRoles ur ON u.user_id = ur.user_id
                JOIN Roles r ON ur.role_id = r.role_id
                JOIN FreelancerProfiles fp ON u.user_id = fp.user_id
                WHERE r.role_name = 'freelancer'
                AND u.user_id != ?
                AND (u.first_name LIKE ? OR u.last_name LIKE ?)
                LIMIT 10
            ");
            $search_pattern = "%{$search_term}%";
            $stmt->execute([$current_user_id, $search_pattern, $search_pattern]);
        } else {
            // Freelancer procura restaurantes
            $stmt = $pdo->prepare("
                SELECT u.user_id, u.first_name, u.last_name, u.profile_image_url,
                       rp.restaurant_id, rp.restaurant_name, rp.avg_rating
                FROM Users u
                JOIN UserRoles ur ON u.user_id = ur.user_id
                JOIN Roles r ON ur.role_id = r.role_id
                JOIN RestaurantProfiles rp ON u.user_id = rp.user_id
                WHERE r.role_name = 'restaurant'
                AND u.user_id != ?
                AND (u.first_name LIKE ? OR u.last_name LIKE ? OR rp.restaurant_name LIKE ?)
                LIMIT 10
            ");
            $search_pattern = "%{$search_term}%";
            $stmt->execute([$current_user_id, $search_pattern, $search_pattern, $search_pattern]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['error' => $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensagens - RestaurantConnect</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            height: 80vh;
            display: flex;
        }

        .sidebar {
            width: 350px;
            background: #f8f9fa;
            border-right: 1px solid #e9ecef;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 20px;
            background: #343a40;
            color: white;
        }

        .search-container {
            margin-top: 15px;
            display: flex;
            gap: 5px;
        }

        .search-input {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 20px;
            outline: none;
            font-size: 0.9rem;
        }

        .search-button {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .search-results {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-top: 5px;
            max-height: 200px;
            overflow-y: auto;
            position: absolute;
            width: calc(100% - 40px);
            z-index: 1000;
        }

        .search-result-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f8f9fa;
        }

        .search-result-item:hover {
            background: #f8f9fa;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .user-info {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
        }

        .conversation-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: background-color 0.2s;
            position: relative;
        }

        .conversation-item:hover {
            background: #e9ecef;
        }

        .conversation-item.active {
            background: #007bff;
            color: white;
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .conversation-name {
            font-weight: 600;
            font-size: 1rem;
        }

        .conversation-time {
            font-size: 0.8rem;
            opacity: 0.7;
        }

        .last-message {
            font-size: 0.9rem;
            opacity: 0.8;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .unread-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            padding: 20px;
            background: #343a40;
            color: white;
            border-bottom: 1px solid #e9ecef;
        }

        .chat-header h3 {
            font-size: 1.3rem;
        }

        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }

        .message {
            margin-bottom: 15px;
            max-width: 70%;
        }

        .message.sent {
            margin-left: auto;
        }

        .message.received {
            margin-right: auto;
        }

        .message-content {
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
        }

        .message.sent .message-content {
            background: #007bff;
            color: white;
        }

        .message.received .message-content {
            background: white;
            border: 1px solid #e9ecef;
        }

        .message-info {
            font-size: 0.8rem;
            margin-top: 5px;
            opacity: 0.7;
        }

        .message.sent .message-info {
            text-align: right;
        }

        .typing-indicator {
            padding: 10px 20px;
            font-style: italic;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .message-input-area {
            padding: 20px;
            background: white;
            border-top: 1px solid #e9ecef;
        }

        .message-input-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #e9ecef;
            border-radius: 25px;
            outline: none;
            font-size: 1rem;
            resize: none;
            min-height: 50px;
            max-height: 100px;
        }

        .message-input:focus {
            border-color: #007bff;
        }

        .send-button {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }

        .send-button:hover {
            background: #0056b3;
        }

        .send-button:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .empty-state {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6c757d;
            font-size: 1.1rem;
        }

        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .container {
                height: 90vh;
                margin: 10px;
            }
            
            .sidebar {
                width: 100%;
                display: none;
            }
            
            .sidebar.show {
                display: flex;
            }
            
            .chat-area {
                width: 100%;
            }
            
            .message {
                max-width: 85%;
            }
        }
    </style>
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
        class MessagingSystem {
            constructor() {
                this.currentConversationId = null;
                this.messagePollingInterval = null;
                this.conversationPollingInterval = null;
                this.typingTimeout = null;
                this.isTyping = false;
                this.searchTimeout = null;
                
                this.initializeElements();
                this.bindEvents();
                this.loadConversations();
                this.startPolling();
            }
            
            initializeElements() {
                this.conversationsList = document.getElementById('conversationsList');
                this.chatHeader = document.getElementById('chatHeader');
                this.messagesContainer = document.getElementById('messagesContainer');
                this.messageInput = document.getElementById('messageInput');
                this.sendButton = document.getElementById('sendButton');
                this.messageInputArea = document.getElementById('messageInputArea');
                this.typingIndicator = document.getElementById('typingIndicator');
                this.searchInput = document.getElementById('searchInput');
                this.searchButton = document.getElementById('searchButton');
            }
            
            bindEvents() {
                this.sendButton.addEventListener('click', () => this.sendMessage());
                this.messageInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.sendMessage();
                    }
                });
                
                // Indicador de digitação
                this.messageInput.addEventListener('input', () => {
                    this.handleTyping();
                });
                
                // Auto-resize textarea
                this.messageInput.addEventListener('input', () => {
                    this.messageInput.style.height = 'auto';
                    this.messageInput.style.height = Math.min(this.messageInput.scrollHeight, 100) + 'px';
                });
                
                // Eventos de pesquisa
                this.searchButton.addEventListener('click', () => this.performSearch());
                this.searchInput.addEventListener('input', () => {
                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => this.performSearch(), 500);
                });
                this.searchInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        this.performSearch();
                    }
                });
            }
            
            async loadConversations() {
                try {
                    const formData = new FormData();
                    formData.append('action', 'get_conversations');
                    
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const conversations = await response.json();
                    this.renderConversations(conversations);
                } catch (error) {
                    console.error('Erro ao carregar conversas:', error);
                }
            }
            
            renderConversations(conversations) {
                if (!Array.isArray(conversations) || conversations.length === 0) {
                    this.conversationsList.innerHTML = '<div class="empty-state">Nenhuma conversa encontrada</div>';
                    return;
                }
                
                const html = conversations.map(conv => `
                    <div class="conversation-item" data-conversation-id="${conv.conversation_id}" onclick="messaging.selectConversation(${conv.conversation_id}, '${conv.first_name} ${conv.last_name}${conv.restaurant_name ? ' - ' + conv.restaurant_name : ''}')">
                        <div class="conversation-header">
                            <div class="conversation-name">
                                ${conv.first_name} ${conv.last_name}
                                ${conv.restaurant_name ? '<br><small>' + conv.restaurant_name + '</small>' : ''}
                            </div>
                            <div class="conversation-time">
                                ${conv.last_message_time ? this.formatTime(conv.last_message_time) : ''}
                            </div>
                        </div>
                        <div class="last-message">
                            ${conv.last_message || 'Nenhuma mensagem ainda'}
                        </div>
                        ${conv.unread_count > 0 ? `<div class="unread-badge">${conv.unread_count}</div>` : ''}
                    </div>
                `).join('');
                
                this.conversationsList.innerHTML = html;
            }
            
            async selectConversation(conversationId, contactName) {
                // Remover classe active de todas as conversas
                document.querySelectorAll('.conversation-item').forEach(item => {
                    item.classList.remove('active');
                });
                
                // Adicionar classe active à conversa selecionada
                document.querySelector(`[data-conversation-id="${conversationId}"]`).classList.add('active');
                
                this.currentConversationId = conversationId;
                this.chatHeader.innerHTML = `<h3>${contactName}</h3>`;
                this.messageInputArea.style.display = 'block';
                
                await this.loadMessages();
                await this.markAsRead();
            }
            
            async loadMessages() {
                try {
                    const formData = new FormData();
                    formData.append('action', 'get_messages');
                    formData.append('conversation_id', this.currentConversationId);
                    
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const messages = await response.json();
                    this.renderMessages(messages);
                } catch (error) {
                    console.error('Erro ao carregar mensagens:', error);
                }
            }
            
            renderMessages(messages) {
                if (!Array.isArray(messages)) {
                    this.messagesContainer.innerHTML = '<div class="empty-state">Erro ao carregar mensagens</div>';
                    return;
                }
                
                const currentUserId = <?php echo $_SESSION['user_id']; ?>;
                
                const html = messages.map(msg => {
                    const isSent = msg.sender_id == currentUserId;
                    return `
                        <div class="message ${isSent ? 'sent' : 'received'}">
                            <div class="message-content">
                                ${this.escapeHtml(msg.message_text)}
                            </div>
                            <div class="message-info">
                                ${isSent ? 'Você' : msg.first_name} • ${this.formatTime(msg.created_at)}
                                ${msg.is_read && isSent ? ' • Lida' : ''}
                            </div>
                        </div>
                    `;
                }).join('');
                
                this.messagesContainer.innerHTML = html;
                this.scrollToBottom();
            }
            
            async sendMessage() {
                const messageText = this.messageInput.value.trim();
                if (!messageText || !this.currentConversationId) return;
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'send_message');
                    formData.append('conversation_id', this.currentConversationId);
                    formData.append('message_text', messageText);
                    
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.messageInput.value = '';
                        this.messageInput.style.height = 'auto';
                        await this.loadMessages();
                        await this.loadConversations(); // Atualizar lista de conversas
                        
                        // Parar indicador de digitação
                        this.setTypingIndicator(false);
                    }
                } catch (error) {
                    console.error('Erro ao enviar mensagem:', error);
                }
            }
            
            async markAsRead() {
                if (!this.currentConversationId) return;
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'mark_as_read');
                    formData.append('conversation_id', this.currentConversationId);
                    
                    await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                } catch (error) {
                    console.error('Erro ao marcar como lida:', error);
                }
            }
            
            handleTyping() {
                if (!this.currentConversationId) return;
                
                if (!this.isTyping) {
                    this.isTyping = true;
                    this.setTypingIndicator(true);
                }
                
                clearTimeout(this.typingTimeout);
                this.typingTimeout = setTimeout(() => {
                    this.isTyping = false;
                    this.setTypingIndicator(false);
                }, 2000);
            }
            
            async setTypingIndicator(isTyping) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'set_typing');
                    formData.append('conversation_id', this.currentConversationId);
                    formData.append('is_typing', isTyping ? 1 : 0);
                    
                    await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                } catch (error) {
                    console.error('Erro ao definir indicador de digitação:', error);
                }
            }
            
            async checkTypingIndicator() {
                if (!this.currentConversationId) return;
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'get_typing');
                    formData.append('conversation_id', this.currentConversationId);
                    
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const typingUsers = await response.json();
                    
                    if (Array.isArray(typingUsers) && typingUsers.length > 0) {
                        const names = typingUsers.map(user => user.first_name).join(', ');
                        this.typingIndicator.textContent = `${names} está digitando...`;
                        this.typingIndicator.style.display = 'block';
                    } else {
                        this.typingIndicator.style.display = 'none';
                    }
                } catch (error) {
                    console.error('Erro ao verificar indicador de digitação:', error);
                }
            }
            
            async performSearch() {
                const searchTerm = this.searchInput.value.trim();
                if (searchTerm.length < 2) {
                    this.hideSearchResults();
                    return;
                }
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'search_users');
                    formData.append('search_term', searchTerm);
                    
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const users = await response.json();
                    this.showSearchResults(users);
                } catch (error) {
                    console.error('Erro ao pesquisar utilizadores:', error);
                }
            }
            
            showSearchResults(users) {
                // Remover resultados anteriores
                this.hideSearchResults();
                
                if (!Array.isArray(users) || users.length === 0) {
                    return;
                }
                
                const searchResults = document.createElement('div');
                searchResults.className = 'search-results';
                searchResults.id = 'searchResults';
                
                const html = users.map(user => `
                    <div class="search-result-item" onclick="messaging.startConversationWithUser(${user.user_id}, '${user.first_name} ${user.last_name}${user.restaurant_name ? ' - ' + user.restaurant_name : ''}')">
                        <div style="font-weight: 600;">
                            ${user.first_name} ${user.last_name}
                            ${user.restaurant_name ? '<br><small>' + user.restaurant_name + '</small>' : ''}
                        </div>
                        ${user.avg_rating ? `<small>⭐ ${user.avg_rating}/5</small>` : ''}
                    </div>
                `).join('');
                
                searchResults.innerHTML = html;
                this.searchInput.parentNode.appendChild(searchResults);
            }
            
            hideSearchResults() {
                const existing = document.getElementById('searchResults');
                if (existing) {
                    existing.remove();
                }
            }
            
            async startConversationWithUser(userId, userName) {
                this.hideSearchResults();
                this.searchInput.value = '';
                
                try {
                    const conversationId = await createNewConversation(userId);
                    if (conversationId) {
                        await this.loadConversations();
                        await this.selectConversation(conversationId, userName);
                    }
                } catch (error) {
                    console.error('Erro ao iniciar conversa:', error);
                }
            }
            
            startPolling() {
                // Polling para novas mensagens
                this.messagePollingInterval = setInterval(() => {
                    if (this.currentConversationId) {
                        this.loadMessages();
                        this.checkTypingIndicator();
                    }
                }, 2000);
                
                // Polling para novas conversas
                this.conversationPollingInterval = setInterval(() => {
                    this.loadConversations();
                }, 5000);
            }
            
            formatTime(timestamp) {
                const date = new Date(timestamp);
                const now = new Date();
                const diffInMinutes = Math.floor((now - date) / (1000 * 60));
                
                if (diffInMinutes < 1) return 'Agora';
                if (diffInMinutes < 60) return `${diffInMinutes}min`;
                if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)}h`;
                
                return date.toLocaleDateString('pt-PT', {
                    day: '2-digit',
                    month: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
            
            scrollToBottom() {
                this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
            }
            
            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            stopPolling() {
                if (this.messagePollingInterval) {
                    clearInterval(this.messagePollingInterval);
                }
                if (this.conversationPollingInterval) {
                    clearInterval(this.conversationPollingInterval);
                }
            }
        }
        
        // Inicializar sistema de mensagens
        let messaging;
        document.addEventListener('DOMContentLoaded', () => {
            messaging = new MessagingSystem();
        });
        
        // Limpar intervalos quando sair da página
        window.addEventListener('beforeunload', () => {
            if (messaging) {
                messaging.stopPolling();
            }
        });
        
        // Função global para selecionar conversa (chamada pelos elementos HTML)
        function selectConversation(conversationId, contactName) {
            messaging.selectConversation(conversationId, contactName);
        }
        
        // Função para criar nova conversa (se necessário)
        async function createNewConversation(targetUserId) {
            try {
                const formData = new FormData();
                formData.append('action', 'create_conversation');
                formData.append('target_user_id', targetUserId);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    messaging.loadConversations();
                    return result.conversation_id;
                }
            } catch (error) {
                console.error('Erro ao criar conversa:', error);
            }
            return null;
        }
        
        // Notificação de som para novas mensagens (opcional)
        function playNotificationSound() {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmUSBjqT2fPJeSsFJnvN8tuNOggSYrjq2JZKCgxOqOT0t2AeBDySz+GhXR0LYKjl7aJWFApBmeP1xGYYBzJ+GAAA');
            audio.volume = 0.3;
            audio.play();
        }
        
        // Sistema de notificações do navegador (opcional)
        function requestNotificationPermission() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        }
        
        function showNotification(title, message) {
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(title, {
                    body: message,
                    icon: '/favicon.ico'
                });
            }
        }
        
        // Solicitar permissão para notificações quando página carregar
        document.addEventListener('DOMContentLoaded', () => {
            requestNotificationPermission();
        });
    </script>
</body>
</html>