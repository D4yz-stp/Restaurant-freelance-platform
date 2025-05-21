<?php
session_start();
// Verificar se o usuário está logado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: ../index.php");
    exit();
}

$databasePath = '../../database/TesteOlga.db';
if (!file_exists($databasePath)) {
    die("O arquivo de banco de dados não existe no caminho especificado: $databasePath");
}

if (!is_readable($databasePath)) {
    die("O arquivo de banco de dados não é legível. Verifique as permissões.");
}


// Conexão com o banco de dados
try {
    $db = new SQLite3($databasePath);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA foreign_keys = ON;');

    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'];

    // Obter perfil do usuário baseado no papel
    $profile_id = null;
    $conversations = [];

    // Continue com o resto do seu código...

} catch (Exception $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}

if ($user_role === 'freelancer') {
    $stmt = $db->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = :user_id");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $profile_id = $row['profile_id'];
        $stmt = $db->prepare("SELECT c.conversation_id, r.restaurant_name, u.first_name, u.last_name, u.profile_image_url, 
                            (SELECT COUNT(*) FROM Messages WHERE conversation_id = c.conversation_id AND sender_id != :user_id AND is_read = 0) as unread_count,
                            (SELECT message_text FROM Messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
                            (SELECT created_at FROM Messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_time
                            FROM Conversations c
                            JOIN RestaurantProfiles r ON c.restaurant_id = r.restaurant_id
                            JOIN Users u ON r.user_id = u.user_id
                            WHERE c.freelancer_id = :profile_id
                            ORDER BY last_message_time DESC");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(':profile_id', $profile_id, SQLITE3_INTEGER);
    }
} else if ($user_role === 'restaurant') {
    $stmt = $db->prepare("SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = :user_id");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $profile_id = $row['restaurant_id'];
        $stmt = $db->prepare("SELECT c.conversation_id, u.first_name, u.last_name, u.profile_image_url, 
                            (SELECT COUNT(*) FROM Messages WHERE conversation_id = c.conversation_id AND sender_id != :user_id AND is_read = 0) as unread_count,
                            (SELECT message_text FROM Messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
                            (SELECT created_at FROM Messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_time
                            FROM Conversations c
                            JOIN FreelancerProfiles f ON c.freelancer_id = f.profile_id
                            JOIN Users u ON f.user_id = u.user_id
                            WHERE c.restaurant_id = :profile_id
                            ORDER BY last_message_time DESC");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(':profile_id', $profile_id, SQLITE3_INTEGER);
    }
}

if (isset($stmt)) {
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $conversations[] = $row;
    }
}

// Processar a seleção de conversa
$active_conversation = null;
$messages = [];
$other_user = null;

if (isset($_GET['conversation_id']) && !empty($_GET['conversation_id'])) {
    $conversation_id = (int)$_GET['conversation_id'];
    
    // Verificar se a conversa pertence ao usuário
    $valid_conversation = false;
    
    foreach ($conversations as $conv) {
        if ($conv['conversation_id'] == $conversation_id) {
            $valid_conversation = true;
            $active_conversation = $conv;
            break;
        }
    }
    
    if ($valid_conversation) {
        // Obter informações do outro usuário na conversa
        if ($user_role === 'freelancer') {
            $stmt = $db->prepare("SELECT u.user_id, u.first_name, u.last_name, u.profile_image_url, r.restaurant_name
                                FROM Conversations c
                                JOIN RestaurantProfiles r ON c.restaurant_id = r.restaurant_id
                                JOIN Users u ON r.user_id = u.user_id
                                WHERE c.conversation_id = :conversation_id");
        } else {
            $stmt = $db->prepare("SELECT u.user_id, u.first_name, u.last_name, u.profile_image_url
                                FROM Conversations c
                                JOIN FreelancerProfiles f ON c.freelancer_id = f.profile_id
                                JOIN Users u ON f.user_id = u.user_id
                                WHERE c.conversation_id = :conversation_id");
        }
        
        $stmt->bindValue(':conversation_id', $conversation_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $other_user = $result->fetchArray(SQLITE3_ASSOC);
        
        // Obter mensagens
        $stmt = $db->prepare("SELECT m.message_id, m.sender_id, m.message_text, m.is_read, m.created_at,
                            u.first_name, u.last_name, u.profile_image_url
                            FROM Messages m
                            JOIN Users u ON m.sender_id = u.user_id
                            WHERE m.conversation_id = :conversation_id
                            ORDER BY m.created_at ASC");
        $stmt->bindValue(':conversation_id', $conversation_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $messages[] = $row;
        }
        
        // Marcar mensagens como lidas
        $stmt = $db->prepare("UPDATE Messages SET is_read = 1 
                            WHERE conversation_id = :conversation_id AND sender_id != :user_id AND is_read = 0");
        $stmt->bindValue(':conversation_id', $conversation_id, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
    }
}

// Processar envio de mensagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && isset($_POST['conversation_id'])) {
    $message = trim($_POST['message']);
    $conversation_id = (int)$_POST['conversation_id'];
    
    if (!empty($message)) {
        $stmt = $db->prepare("INSERT INTO Messages (conversation_id, sender_id, message_text) 
                            VALUES (:conversation_id, :sender_id, :message_text)");
        $stmt->bindValue(':conversation_id', $conversation_id, SQLITE3_INTEGER);
        $stmt->bindValue(':sender_id', $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(':message_text', $message, SQLITE3_TEXT);
        $stmt->execute();
        
        // Redirecionar para evitar reenvio do formulário em refresh
        header("Location: chat.php?conversation_id=$conversation_id");
        exit();
    }
}

// Iniciar nova conversa (apenas para restaurantes)
if ($user_role === 'restaurant' && isset($_POST['new_conversation']) && isset($_POST['freelancer_id'])) {
    $freelancer_id = (int)$_POST['freelancer_id'];
    
    // Verificar se a conversa já existe
    $stmt = $db->prepare("SELECT conversation_id FROM Conversations 
                        WHERE restaurant_id = :restaurant_id AND freelancer_id = :freelancer_id");
    $stmt->bindValue(':restaurant_id', $profile_id, SQLITE3_INTEGER);
    $stmt->bindValue(':freelancer_id', $freelancer_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Conversa já existe, redirecionar para ela
        header("Location: chat.php?conversation_id=" . $row['conversation_id']);
        exit();
    } else {
        // Criar nova conversa
        $stmt = $db->prepare("INSERT INTO Conversations (restaurant_id, freelancer_id) 
                            VALUES (:restaurant_id, :freelancer_id)");
        $stmt->bindValue(':restaurant_id', $profile_id, SQLITE3_INTEGER);
        $stmt->bindValue(':freelancer_id', $freelancer_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        $conversation_id = $db->lastInsertRowID();
        
        // Redirecionar para a nova conversa
        header("Location: chat.php?conversation_id=$conversation_id");
        exit();
    }
}

// Fechar a conexão com o banco de dados
$db = null;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Sistema de Freelancing</title>
    <link rel="stylesheet" href="../../Css/chat.css">
    <link rel="stylesheet" href="../../Css/global.css">
    <link rel="stylesheet" href="">
</head>
<body>
    <div id="notification" class="notification"></div>
    <div id="chat-container">
        <div id="conversations-list">
            <h2>Conversas</h2>
            <?php if ($user_role === 'restaurant'): ?>
                <div id="new-conversation">
                    <h3>Iniciar Nova Conversa</h3>
                    <form method="post" action="chat.php">
                        <input type="hidden" name="new_conversation" value="1">
                        <select name="freelancer_id" required>
                            <option value="">Selecione um freelancer</option>
                            <?php
                            $databasePath = '../../database/TesteOlga.db';
                            if (!file_exists($databasePath)) {
                                die("O arquivo de banco de dados não existe no caminho especificado: $databasePath");
                            }

                            if (!is_readable($databasePath)) {
                                die("O arquivo de banco de dados não é legível. Verifique as permissões.");
                            }


                            // Conexão com o banco de dados
                            try {
                                $db = new SQLite3($databasePath);
                                $db->busyTimeout(5000);
                                $db->exec('PRAGMA foreign_keys = ON;');

                                $user_id = $_SESSION['user_id'];
                                $user_role = $_SESSION['user_role'];

                                // Obter perfil do usuário baseado no papel
                                $profile_id = null;
                                $conversations = [];

                                // Continue com o resto do seu código...

                            } catch (Exception $e) {
                                die("Erro ao conectar ao banco de dados: " . $e->getMessage());
                            }
                            $query = "SELECT f.profile_id, u.first_name, u.last_name 
                                    FROM FreelancerProfiles f 
                                    JOIN Users u ON f.user_id = u.user_id 
                                    ORDER BY u.first_name, u.last_name";
                            $result = $db->query($query);
                            
                            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                echo "<option value=\"{$row['profile_id']}\">{$row['first_name']} {$row['last_name']}</option>";
                            }
                            $db = null;
                            ?>
                        </select>
                        <button type="submit">Iniciar Conversa</button>
                    </form>
                </div>
            <?php endif; ?>
            
            <ul id="conversations">
                <?php if (empty($conversations)): ?>
                    <li class="no-conversations">Nenhuma conversa encontrada.</li>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <?php 
                        $default_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 40 40'%3E%3Ccircle cx='20' cy='20' r='20' fill='%23ccc'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='.3em' font-size='20' fill='%23fff'%3E" . 
                                        substr($user_role === 'freelancer' ? $conv['restaurant_name'] : $conv['first_name'], 0, 1) . 
                                        "%3C/text%3E%3C/svg%3E";
                        $avatar = !empty($conv['profile_image_url']) ? $conv['profile_image_url'] : $default_avatar;
                        $name = $user_role === 'freelancer' ? $conv['restaurant_name'] : $conv['first_name'] . ' ' . $conv['last_name'];
                        $is_active = $active_conversation && $active_conversation['conversation_id'] == $conv['conversation_id'];
                        ?>
                        <li class="conversation <?php echo $is_active ? 'active' : ''; ?>">
                            <a href="chat.php?conversation_id=<?php echo $conv['conversation_id']; ?>">
                                <div class="conversation-avatar">
                                    <img src="<?php echo $avatar; ?>" alt="<?php echo $name; ?>">
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-info">
                                    <div class="conversation-name"><?php echo $name; ?></div>
                                    <?php if (!empty($conv['job_title'])): ?>
                                        <div class="job-title">Serviço: <?php echo $conv['job_title']; ?></div>
                                    <?php endif; ?>
                                    <div class="conversation-last-message"><?php echo substr($conv['last_message'], 0, 30) . (strlen($conv['last_message']) > 30 ? '...' : ''); ?></div>
                                    <div class="conversation-time">
                                        <?php 
                                        $time = strtotime($conv['last_message_time']);
                                        echo date('H:i', $time);
                                        
                                        $today = strtotime('today');
                                        if ($time < $today) {
                                            echo ' ' . date('d/m', $time);
                                        }
                                        ?>
                                    </div>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        
        <div id="chat-messages">
            <?php if ($active_conversation): ?>
                <div id="chat-header">
                    <?php
                    $default_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 40 40'%3E%3Ccircle cx='20' cy='20' r='20' fill='%23ccc'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='.3em' font-size='20' fill='%23fff'%3E" . 
                                    substr($other_user['first_name'], 0, 1) . 
                                    "%3C/text%3E%3C/svg%3E";
                    $avatar = !empty($other_user['profile_image_url']) ? $other_user['profile_image_url'] : $default_avatar;
                    $name = $user_role === 'freelancer' ? $other_user['restaurant_name'] : $other_user['first_name'] . ' ' . $other_user['last_name'];
                    ?>
                    <div class="user-avatar">
                        <img src="<?php echo $avatar; ?>" alt="<?php echo $name; ?>">
                    </div>
                    <div class="user-name"><?php echo $name; ?></div>
                </div>
                
                <div id="messages-container">
                    <?php if (empty($messages)): ?>
                        <div class="no-messages">Nenhuma mensagem encontrada. Inicie uma conversa!</div>
                    <?php else: ?>
                        <?php 
                        $last_date = null;
                        foreach ($messages as $msg): 
                            $message_date = date('Y-m-d', strtotime($msg['created_at']));
                            $is_own_message = $msg['sender_id'] == $user_id;
                            
                            // Exibir separador de data se for um novo dia
                            if ($last_date != $message_date):
                                $today = date('Y-m-d');
                                $yesterday = date('Y-m-d', strtotime('-1 day'));
                                
                                if ($message_date == $today) {
                                    $date_display = 'Hoje';
                                } else if ($message_date == $yesterday) {
                                    $date_display = 'Ontem';
                                } else {
                                    $date_display = date('d/m/Y', strtotime($msg['created_at']));
                                }
                                $last_date = $message_date;
                        ?>
                                <div class="date-separator">
                                    <span><?php echo $date_display; ?></span>
                                </div>
                        <?php endif; ?>
                            
                        <div class="message <?php echo $is_own_message ? 'sent' : 'received'; ?>">
                            <?php if (!$is_own_message): ?>
                                <?php
                                $default_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='30' height='30' viewBox='0 0 30 30'%3E%3Ccircle cx='15' cy='15' r='15' fill='%23ccc'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='.3em' font-size='15' fill='%23fff'%3E" . 
                                                substr($msg['first_name'], 0, 1) . 
                                                "%3C/text%3E%3C/svg%3E";
                                $avatar = !empty($msg['profile_image_url']) ? $msg['profile_image_url'] : $default_avatar;
                                ?>
                                <div class="message-avatar">
                                    <img src="<?php echo $avatar; ?>" alt="<?php echo $msg['first_name']; ?>">
                                </div>
                            <?php endif; ?>
                            
                            <div class="message-content">
                                <div class="message-text"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div>
                                <div class="message-time">
                                    <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                                    <?php if ($is_own_message): ?>
                                        <span class="message-status"><?php echo $msg['is_read'] ? '✓✓' : '✓'; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div id="message-form">
                    <form id="sendMessageForm">
                        <input type="hidden" name="conversation_id" value="<?php echo $active_conversation['conversation_id']; ?>">
                        <textarea name="message" id="messageInput" placeholder="Digite sua mensagem..." required></textarea>
                        <button type="submit">Enviar</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="no-conversation-selected">
                    <div class="placeholder-message">
                        <h2>Bem-vindo ao Sistema de Chat</h2>
                        <p>Selecione uma conversa para começar ou inicie uma nova.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Variáveis globais
            const messagesContainer = document.getElementById('messages-container');
            const sendMessageForm = document.getElementById('sendMessageForm');
            const messageInput = document.getElementById('messageInput');
            const notificationElement = document.getElementById('notification');
            let isTyping = false;
            let typingTimer;
            let lastMessageId = 0;
            let currentConversationId = <?php echo isset($active_conversation) ? $active_conversation['conversation_id'] : 'null'; ?>;
            let userTypingStatus = {};
            
            // Se tiver uma conversa ativa, inicializar o último ID de mensagem
            if (messagesContainer) {
                const messages = messagesContainer.querySelectorAll('.message');
                if (messages.length > 0) {
                    const lastMessage = messages[messages.length - 1];
                    if (lastMessage.dataset.messageId) {
                        lastMessageId = parseInt(lastMessage.dataset.messageId);
                    }
                }
                
                // Rolar para a última mensagem
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
            
            // Função para enviar mensagem por AJAX
            if (sendMessageForm) {
                sendMessageForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const message = messageInput.value.trim();
                    if (message === '') return;
                    
                    const formData = new FormData(sendMessageForm);
                    
                    fetch('send_message.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Limpar campo de mensagem
                            messageInput.value = '';
                            
                            // Buscar novas mensagens (incluirá a mensagem enviada)
                            fetchNewMessages();
                            
                            // Atualizar lista de conversas
                            setTimeout(updateConversationsList, 100);
                        }
                    })
                    .catch(error => console.error('Erro ao enviar mensagem:', error));
                });
            }
            
            // Eventos de digitação
            if (messageInput) {
                messageInput.addEventListener('input', function() {
                    if (!isTyping) {
                        isTyping = true;
                        sendTypingStatus(true);
                    }
                    
                    // Reset do timer
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(function() {
                        isTyping = false;
                        sendTypingStatus(false);
                    }, 100);
                });
            }
            
            // Função para enviar status de digitação
            function sendTypingStatus(isTyping) {
                if (!currentConversationId) return;
                
                fetch('typing_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `conversation_id=${currentConversationId}&is_typing=${isTyping ? 1 : 0}`
                });
            }
            
            // Verificar por novas mensagens a cada 3 segundos
            if (currentConversationId) {
                setInterval(fetchNewMessages, 100);
            }
            
            // Atualizar lista de conversas a cada 5 segundos
            setInterval(updateConversationsList, 100);
            
            // Verificar status de digitação a cada 2 segundos
            if (currentConversationId) {
                setInterval(checkTypingStatus, 100);
            }
            
            // Função para buscar novas mensagens
            function fetchNewMessages() {
                if (!currentConversationId) return;
                
                fetch(`get_new_messages.php?conversation_id=${currentConversationId}&last_message_id=${lastMessageId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.messages && data.messages.length > 0) {
                        let newMessagesHtml = '';
                        let currentDate = null;
                        let hasNewMessages = false;
                        
                        // Obter o último separador de data no container de mensagens, se existir
                        const dateSeparators = messagesContainer.querySelectorAll('.date-separator');
                        let lastDateInContainer = null;
                        
                        if (dateSeparators.length > 0) {
                            const lastSeparator = dateSeparators[dateSeparators.length - 1];
                            const dateText = lastSeparator.querySelector('span').textContent;
                            
                            // Converter o texto do separador para um formato de data padrão para comparação
                            if (dateText === 'Hoje') {
                                lastDateInContainer = new Date().toISOString().split('T')[0];
                            } else if (dateText === 'Ontem') {
                                const yesterday = new Date();
                                yesterday.setDate(yesterday.getDate() - 1);
                                lastDateInContainer = yesterday.toISOString().split('T')[0];
                            } else {
                                // Formato "dd/mm/yyyy" para "yyyy-mm-dd"
                                const parts = dateText.split('/');
                                if (parts.length === 3) {
                                    lastDateInContainer = `${parts[2]}-${parts[1]}-${parts[0]}`;
                                }
                            }
                        }
                        
                        data.messages.forEach(msg => {
                            // Atualizar o último ID de mensagem
                            if (parseInt(msg.message_id) > lastMessageId) {
                                lastMessageId = parseInt(msg.message_id);
                                hasNewMessages = true;
                            }
                            
                            // Verificar se é um novo dia
                            const messageDate = new Date(msg.created_at).toISOString().split('T')[0];
                            
                            // Adicionar separador de data apenas se for diferente do último no container
                            // E diferente do último processado nesta iteração
                            if (currentDate !== messageDate && lastDateInContainer !== messageDate) {
                                const today = new Date().toISOString().split('T')[0];
                                const yesterday = new Date(Date.now() - 86400000).toISOString().split('T')[0];
                                
                                let dateDisplay;
                                if (messageDate === today) {
                                    dateDisplay = 'Hoje';
                                } else if (messageDate === yesterday) {
                                    dateDisplay = 'Ontem';
                                } else {
                                    const dateParts = messageDate.split('-');
                                    dateDisplay = `${dateParts[2]}/${dateParts[1]}/${dateParts[0]}`;
                                }
                                
                                newMessagesHtml += `
                                    <div class="date-separator">
                                        <span>${dateDisplay}</span>
                                    </div>
                                `;
                            }
                            currentDate = messageDate;
                            
                            // Criar HTML da mensagem
                            const isOwnMessage = msg.sender_id == <?php echo $user_id; ?>;
                            const messageTime = new Date(msg.created_at).toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
                            
                            let avatarHtml = '';
                            if (!isOwnMessage) {
                                const defaultAvatar = encodeURIComponent(`<svg xmlns='http://www.w3.org/2000/svg' width='30' height='30' viewBox='0 0 30 30'><circle cx='15' cy='15' r='15' fill='%23ccc'/><text x='50%' y='50%' text-anchor='middle' dy='.3em' font-size='15' fill='%23fff'>${msg.first_name.charAt(0)}</text></svg>`);
                                const avatar = msg.profile_image_url ? msg.profile_image_url : `data:image/svg+xml,${defaultAvatar}`;
                                
                                avatarHtml = `
                                    <div class="message-avatar">
                                        <img src="${avatar}" alt="${msg.first_name}">
                                    </div>
                                `;
                            }
                            
                            newMessagesHtml += `
                                <div class="message ${isOwnMessage ? 'sent' : 'received'}" data-message-id="${msg.message_id}">
                                    ${avatarHtml}
                                    <div class="message-content">
                                        <div class="message-text">${msg.message_text.replace(/\n/g, '<br>')}</div>
                                        <div class="message-time">
                                            ${messageTime}
                                            ${isOwnMessage ? `<span class="message-status">${msg.is_read == 1 ? '✓✓' : '✓'}</span>` : ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        if (hasNewMessages) {
                            // Adicionar novas mensagens
                            messagesContainer.insertAdjacentHTML('beforeend', newMessagesHtml);
                            
                            // Rolar para a última mensagem
                            messagesContainer.scrollTop = messagesContainer.scrollHeight;
                            
                            // Notificar se a mensagem não é do usuário atual
                            const lastMessage = data.messages[data.messages.length - 1];
                            if (lastMessage.sender_id != <?php echo $user_id; ?>) {
                                showNotification(`Nova mensagem de ${lastMessage.first_name}`);
                            }
                        }
                    }
                })
                .catch(error => console.error('Erro ao buscar novas mensagens:', error));
            }
            
            // Função para atualizar lista de conversas
            function updateConversationsList() {
                fetch('get_conversations.php')
                .then(response => response.json())
                .then(data => {
                    if (data.conversations) {
                        const conversationsList = document.getElementById('conversations');
                        
                        // Verificar se há novas conversas ou atualizações
                        const currentConversations = conversationsList.querySelectorAll('.conversation');
                        const conversationIds = Array.from(currentConversations).map(el => {
                            const link = el.querySelector('a');
                            return parseInt(new URLSearchParams(link.href.split('?')[1]).get('conversation_id'));
                        });
                        
                        let hasUpdates = data.conversations.length !== currentConversations.length;
                        
                        if (!hasUpdates) {
                            // Verificar se alguma conversa foi alterada (nova mensagem, etc)
                            data.conversations.forEach(conv => {
                                if (!conversationIds.includes(conv.conversation_id)) {
                                    hasUpdates = true;
                                }
                            });
                        }
                        
                        if (hasUpdates) {
                            // Recriar a lista de conversas
                            let newConversationsHtml = '';
                            
                            if (data.conversations.length === 0) {
                                newConversationsHtml = '<li class="no-conversations">Nenhuma conversa encontrada.</li>';
                            } else {
                                data.conversations.forEach(conv => {
                                    const isActive = currentConversationId && currentConversationId == conv.conversation_id;
                                    const defaultAvatar = encodeURIComponent(`<svg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 40 40'><circle cx='20' cy='20' r='20' fill='%23ccc'/><text x='50%' y='50%' text-anchor='middle' dy='.3em' font-size='20' fill='%23fff'>${conv.name.charAt(0)}</text></svg>`);
                                    const avatar = conv.profile_image_url ? conv.profile_image_url : `data:image/svg+xml,${defaultAvatar}`;
                                    
                                    newConversationsHtml += `
                                        <li class="conversation ${isActive ? 'active' : ''}">
                                            <a href="chat.php?conversation_id=${conv.conversation_id}">
                                                <div class="conversation-avatar">
                                                    <img src="${avatar}" alt="${conv.name}">
                                                    ${conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : ''}
                                                </div>
                                                <div class="conversation-info">
                                                    <div class="conversation-name">${conv.name}</div>
                                                    <div class="conversation-last-message">${conv.last_message ? (conv.last_message.length > 30 ? conv.last_message.substring(0, 30) + '...' : conv.last_message) : ''}</div>
                                                    <div class="conversation-time">
                                                        ${formatMessageTime(conv.last_message_time)}
                                                    </div>
                                                </div>
                                            </a>
                                        </li>
                                    `;
                                });
                            }
                            
                            conversationsList.innerHTML = newConversationsHtml;
                            
                            // Adicionar eventos de clique nas novas conversas
                            document.querySelectorAll('#conversations .conversation a').forEach(link => {
                                link.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    window.location.href = this.href;
                                });
                            });
                        }
                    }
                })
                .catch(error => console.error('Erro ao atualizar conversas:', error));
            }
            
            // Função para verificar status de digitação
            function checkTypingStatus() {
                if (!currentConversationId) return;
                
                fetch(`get_typing_status.php?conversation_id=${currentConversationId}`)
                .then(response => response.json())
                .then(data => {
                    const chatHeader = document.getElementById('chat-header');
                    const typingIndicator = chatHeader.querySelector('.typing-indicator');
                    
                    if (data.is_typing) {
                        // Mostrar indicador de digitação
                        if (!typingIndicator) {
                            const indicator = document.createElement('div');
                            indicator.className = 'typing-indicator';
                            indicator.textContent = 'digitando...';
                            chatHeader.appendChild(indicator);
                        }
                    } else {
                        // Remover indicador de digitação
                        if (typingIndicator) {
                            typingIndicator.remove();
                        }
                    }
                })
                .catch(error => console.error('Erro ao verificar status de digitação:', error));
            }
            
            // Função para formatar hora da mensagem
            function formatMessageTime(timestamp) {
                if (!timestamp) return '';
                
                const messageDate = new Date(timestamp);
                const today = new Date();
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                
                const timeStr = messageDate.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
                
                if (messageDate.toDateString() === today.toDateString()) {
                    return timeStr;
                } else if (messageDate.toDateString() === yesterday.toDateString()) {
                    return 'Ontem';
                } else {
                    return messageDate.toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit'});
                }
            }
            
            // Função para mostrar notificação
            function showNotification(message) {
                notificationElement.textContent = message;
                notificationElement.style.display = 'block';
                
                setTimeout(() => {
                    notificationElement.style.display = 'none';
                }, 100);
            }
        });
    </script>
</body>
</html>