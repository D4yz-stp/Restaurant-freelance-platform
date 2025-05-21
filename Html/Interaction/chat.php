<?php
// chat.php
require_once 'config.php'; // config.php já chama session_start()

// --- Gestão do Utilizador Atual ---
$current_user_id = null;

// Prioridade para sessão
if (isset($_SESSION['user_id'])) {
    $current_user_id = (int)$_SESSION['user_id'];
} 
// Permitir ?current_user_id_test=X para facilitar testes sem login completo por enquanto
// Numa aplicação real, esta parte seria removida após implementar o login.php
elseif (isset($_GET['current_user_id_test'])) { 
    $test_user_id = (int)$_GET['current_user_id_test'];
    // Validar se este user_id de teste existe
    $tempPdo = getDbConnection();
    if ($tempPdo){
        $userCheckStmt = $tempPdo->prepare("SELECT user_id FROM Users WHERE user_id = ?");
        $userCheckStmt->execute([$test_user_id]);
        if($userCheckStmt->fetch()){
            $_SESSION['user_id'] = $test_user_id;
            $current_user_id = $test_user_id;
             // Redirecionar para remover o parâmetro de teste da URL
            $queryString = http_build_query(array_diff_key($_GET, ['current_user_id_test' => '']));
            header("Location: chat.php" . ($queryString ? "?" . $queryString : ""));
            exit;
        }
    }
}

if (!$current_user_id) {
    // Se não houver utilizador, redirecionar para login.php (a implementar)
    // header('Location: login.php');
    // exit;
    // Para este exemplo, se ainda não houver ID, fixamos um para demonstração
    $_SESSION['user_id'] = 1; // João Silva (freelancer) por defeito para teste
    $current_user_id = 1;
    // Se estiver a testar com ?current_user_id_test, comente a linha acima.
    // E descomente as linhas abaixo para forçar um login se não houver sessão.
    // echo "Autenticação necessária. Implemente login.php e redirecione.";
    // exit;
}


// (Resto das suas funções PHP como getConversationsForUser, getMessagesForConversation, etc.
//  Lembre-se que algumas foram movidas para config.php, ajuste os 'includes' ou chamadas.)
//  A função sendMessage original não é mais necessária aqui, pois usamos a versão AJAX.
//  As funções getParticipantDisplayInfo, getUserDetails, getUserProfileIds estão agora em config.php

$pdo = getDbConnection();
if (!$pdo) exit; // getDbConnection já trata o erro

$current_user_details = getUserDetails($pdo, $current_user_id);
if (!$current_user_details) {
    unset($_SESSION['user_id']); // Limpar sessão inválida
    // header('Location: login.php'); // Idealmente redirecionar
    die("Utilizador com ID $current_user_id não encontrado. Limpe os cookies ou faça login novamente.");
}

// ... (Lógica para $active_conversation_id, getConversationsForUser, getMessagesForConversation) ...
// A função getMessagesForConversation ainda é útil para o carregamento inicial.
// A função sendMessage que estava aqui foi substituída pela versão AJAX.

/**
 * Obtém as conversas de um utilizador (com contagem de não lidas).
 * @param PDO $pdo
 * @param int $currentUserId
 * @return array
 */
function getConversationsForUserWithUnreadCount(PDO $pdo, int $currentUserId): array {
    $userProfile = getUserProfileIds($pdo, $currentUserId); // Função de config.php
    if (!$userProfile) {
        return [];
    }

    $conversations = [];
    $sql = "";

    if ($userProfile['type'] === 'freelancer') {
        $sql = "SELECT c.conversation_id, c.restaurant_id, rp.user_id as other_user_id,
                       (SELECT COUNT(*) FROM Messages m WHERE m.conversation_id = c.conversation_id AND m.sender_id != :current_user_id_for_unread AND m.is_read = 0) as unread_count
                FROM Conversations c
                JOIN RestaurantProfiles rp ON c.restaurant_id = rp.restaurant_id
                WHERE c.freelancer_id = :profile_id";
    } elseif ($userProfile['type'] === 'restaurant') {
        $sql = "SELECT c.conversation_id, c.freelancer_id, fp.user_id as other_user_id,
                       (SELECT COUNT(*) FROM Messages m WHERE m.conversation_id = c.conversation_id AND m.sender_id != :current_user_id_for_unread AND m.is_read = 0) as unread_count
                FROM Conversations c
                JOIN FreelancerProfiles fp ON c.freelancer_id = fp.profile_id
                WHERE c.restaurant_id = :profile_id";
    }

    if (empty($sql)) return [];

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':profile_id' => $userProfile['profile_id'],
        ':current_user_id_for_unread' => $currentUserId // Para a subquery de contagem
    ]);

    while ($row = $stmt->fetch()) {
        $otherParticipantInfo = getParticipantDisplayInfo($pdo, $row['other_user_id']); // Função de config.php

        $lastMsgStmt = $pdo->prepare("SELECT message_text, sender_id, created_at 
                                      FROM Messages 
                                      WHERE conversation_id = :conv_id 
                                      ORDER BY created_at DESC LIMIT 1");
        $lastMsgStmt->execute([':conv_id' => $row['conversation_id']]);
        $lastMessage = $lastMsgStmt->fetch();
        $lastMessagePreview = $lastMessage ? htmlspecialchars(custom_strimwidth($lastMessage['message_text'], 0, 30, "...")) : "Nenhuma mensagem ainda.";
        $lastMessageTimestamp = $lastMessage ? date("H:i", strtotime($lastMessage['created_at'])) : "";

        $conversations[] = [
            'conversation_id' => $row['conversation_id'],
            'other_user_id' => $row['other_user_id'],
            'other_user_name' => $otherParticipantInfo['name'],
            'other_user_image' => $otherParticipantInfo['image_url'],
            'last_message_preview' => $lastMessagePreview,
            'last_message_timestamp' => $lastMessageTimestamp,
            'unread_count' => $row['unread_count'] ?? 0
        ];
    }
    // Ordenar por conversas com mensagens não lidas primeiro, depois pela última mensagem (se necessário)
    // Esta parte pode ser mais complexa, por agora a ordem é da BD.
    return $conversations;
}

function custom_strimwidth($str, $start, $width, $trimmarker = "") {
    if (strlen($str) <= $width) {
        return $str;
    }
    return substr($str, $start, $width - strlen($trimmarker)) . $trimmarker;
}



/**
 * Obtém as mensagens de uma conversa (usado para carregamento inicial).
 * @param PDO $pdo
 * @param int $conversationId
 * @param int $currentUserId
 * @return array
 */
function getMessagesForConversation(PDO $pdo, int $conversationId, int $currentUserId): array {
    // Marcar mensagens como lidas ao carregar a conversa
    $stmtMarkRead = $pdo->prepare("UPDATE Messages SET is_read = 1 
                                   WHERE conversation_id = :conversation_id 
                                   AND sender_id != :current_user_id 
                                   AND is_read = 0");
    $stmtMarkRead->execute([
        ':conversation_id' => $conversationId,
        ':current_user_id' => $currentUserId
    ]);

    $stmt = $pdo->prepare("SELECT m.message_id, m.sender_id, m.message_text, m.created_at, m.is_read, u.first_name, u.last_name, u.profile_image_url
                           FROM Messages m
                           JOIN Users u ON m.sender_id = u.user_id
                           WHERE m.conversation_id = :conversation_id
                           ORDER BY m.created_at ASC");
    $stmt->execute([':conversation_id' => $conversationId]);
    return $stmt->fetchAll();
}

/**
 * Obtém detalhes do outro participante numa conversa.
 * @param PDO $pdo
 * @param int $conversationId
 * @param int $currentUserId
 * @return array|null ['user_id', 'name', 'image_url']
 */
function getOtherParticipantDetailsInConversation(PDO $pdo, int $conversationId, int $currentUserId): ?array {
    $stmt = $pdo->prepare("SELECT freelancer_id, restaurant_id FROM Conversations WHERE conversation_id = :conv_id");
    $stmt->execute([':conv_id' => $conversationId]);
    $conversation = $stmt->fetch();

    if (!$conversation) return null;

    $currentUserProfile = getUserProfileIds($pdo, $currentUserId);
    if (!$currentUserProfile) return null;

    $otherParticipantUserId = null;

    if ($currentUserProfile['type'] === 'freelancer' && $currentUserProfile['profile_id'] == $conversation['freelancer_id']) {
        $stmtRest = $pdo->prepare("SELECT user_id FROM RestaurantProfiles WHERE restaurant_id = :rest_id");
        $stmtRest->execute([':rest_id' => $conversation['restaurant_id']]);
        $otherProfile = $stmtRest->fetch();
        if ($otherProfile) $otherParticipantUserId = $otherProfile['user_id'];

    } elseif ($currentUserProfile['type'] === 'restaurant' && $currentUserProfile['profile_id'] == $conversation['restaurant_id']) {
        $stmtFree = $pdo->prepare("SELECT user_id FROM FreelancerProfiles WHERE profile_id = :free_id");
        $stmtFree->execute([':free_id' => $conversation['freelancer_id']]);
        $otherProfile = $stmtFree->fetch();
        if ($otherProfile) $otherParticipantUserId = $otherProfile['user_id'];
    }

    if ($otherParticipantUserId) {
        return getParticipantDisplayInfo($pdo, $otherParticipantUserId) + ['user_id' => $otherParticipantUserId];
    }
    return null;
}

$active_conversation_id = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : null;
$messages = [];
$other_participant_chat_header = null;

// Não há mais processamento de POST para envio de mensagem aqui, será AJAX.

$conversations = getConversationsForUserWithUnreadCount($pdo, $current_user_id); // Atualizado para incluir contagem

if ($active_conversation_id) {
    $messages = getMessagesForConversation($pdo, $active_conversation_id, $current_user_id); // Passa $current_user_id para marcar como lidas
    $other_participant_chat_header = getOtherParticipantDetailsInConversation($pdo, $active_conversation_id, $current_user_id);
    if (!$other_participant_chat_header) {
        $active_conversation_id = null;
    }
}

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - <?php echo htmlspecialchars($current_user_details['first_name']); ?></title>
    <style>
        /* ... (os seus estilos CSS de antes) ... */
        /* Adicionar estilo para contador de não lidas */
        .conversation-item .unread-count {
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.75em;
            margin-left: 5px;
            font-weight: bold;
        }
        .conversation-item .info .user-name { font-weight: bold; display: inline-block; /* Para o contador ficar ao lado */ }

    
    </style>
    <link rel="stylesheet" href="../../Css/MESA2.css">
    <link rel="stylesheet" href="../../Css/global.css">
    
</head>
<body>
    <div class="chat-app">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="current-user-info">
                     <img src="<?php echo !empty($current_user_details['profile_image_url']) ? htmlspecialchars($current_user_details['profile_image_url']) : DEFAULT_AVATAR_URL; ?>" 
                          alt="<?php echo htmlspecialchars($current_user_details['first_name']); ?>" 
                          width="40" height="40">
                     <span><?php echo htmlspecialchars($current_user_details['first_name'] . ' ' . $current_user_details['last_name']); ?></span>
                </div>
                <h2>Conversas</h2>
            </div>
            <ul class="conversations-list" id="conversations-list-ul">
                <?php if (empty($conversations)): ?>
                    <li><p style="padding: 15px; text-align:center; color: #777;">Nenhuma conversa encontrada.</p></li>
                <?php endif; ?>
                <?php foreach ($conversations as $convo): ?>
                    <a href="chat.php?conversation_id=<?php echo $convo['conversation_id']; ?>" 
                       class="conversation-item <?php echo ($active_conversation_id == $convo['conversation_id']) ? 'active' : ''; ?>"
                       data-conversation-id="<?php echo $convo['conversation_id']; ?>">
                        <img src="<?php echo $convo['other_user_image']; ?>" alt="<?php echo htmlspecialchars($convo['other_user_name']); ?>">
                        <div class="info">
                            <span class="user-name"><?php echo htmlspecialchars($convo['other_user_name']); ?></span>
                            <?php if ($convo['unread_count'] > 0): ?>
                                <span class="unread-count" id="unread-count-<?php echo $convo['conversation_id']; ?>"><?php echo $convo['unread_count']; ?></span>
                            <?php else: ?>
                                 <span class="unread-count" id="unread-count-<?php echo $convo['conversation_id']; ?>" style="display:none;">0</span>
                            <?php endif; ?>
                            <span class="last-message-preview"><?php echo $convo['last_message_preview']; ?></span>
                        </div>
                        <span class="timestamp"><?php echo $convo['last_message_timestamp']; ?></span>
                    </a>
                <?php endforeach; ?>
            </ul>
        </aside>

        <main class="chat-area">
            <?php if ($active_conversation_id && $other_participant_chat_header): ?>
                <header class="chat-header">
                    <img src="<?php echo $other_participant_chat_header['image_url']; ?>" alt="<?php echo htmlspecialchars($other_participant_chat_header['name']); ?>" width="40" height="40">
                    <h3><?php echo htmlspecialchars($other_participant_chat_header['name']); ?></h3>
                </header>

                <section class="message-list" id="message-list-section">
                    <?php if (empty($messages)): ?>
                         <p class="message-placeholder">Ainda não há mensagens nesta conversa. Envie uma mensagem para começar!</p>
                    <?php endif; ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="message-bubble <?php echo ($msg['sender_id'] == $current_user_id) ? 'sent' : 'received'; ?>" data-message-id="<?php echo $msg['message_id']; ?>">
                            <p class="message-content"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></p>
                            <span class="message-timestamp"><?php echo date("H:i", strtotime($msg['created_at'])); ?></span>
                            <?php if ($msg['sender_id'] == $current_user_id && $msg['is_read']): ?>
                                <span class="read-receipt" title="Lida">&#10003;&#10003;</span> <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </section>

                <footer class="message-form">
                    <form id="ajax-message-form" style="display: flex; width: 100%;">
                        <input type="hidden" name="conversation_id_form" value="<?php echo $active_conversation_id; ?>">
                        <textarea name="message_text" placeholder="Digite sua mensagem..." rows="1" required></textarea>
                        <button type="submit">Enviar</button>
                    </form>
                </footer>

            <?php else: ?>
                <div class="message-list">
                    <p class="message-placeholder">Selecione uma conversa para começar a conversar.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        const currentUserId = <?php echo $current_user_id; ?>;
        const activeConversationId = <?php echo $active_conversation_id ? $active_conversation_id : 'null'; ?>;
        let pollingInterval;
        let lastKnownMessageId = 0;

        const messageListSection = document.getElementById('message-list-section');
        const messageForm = document.getElementById('ajax-message-form');
        const messageInput = messageForm ? messageForm.querySelector('textarea[name="message_text"]') : null;

        function scrollToBottom() {
            if (messageListSection) {
                messageListSection.scrollTop = messageListSection.scrollHeight;
            }
        }

        function appendMessageToUI(msg, isSenderCurrentUser) {
            if (!messageListSection) return;

            // Remover placeholder se existir
            const placeholder = messageListSection.querySelector('.message-placeholder');
            if (placeholder) placeholder.remove();

            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message-bubble');
            messageDiv.classList.add(isSenderCurrentUser ? 'sent' : 'received');
            messageDiv.dataset.messageId = msg.message_id;

            const contentP = document.createElement('p');
            contentP.classList.add('message-content');
            contentP.innerHTML = msg.message_text.replace(/\n/g, '<br>'); // nl2br
            messageDiv.appendChild(contentP);

            const timestampSpan = document.createElement('span');
            timestampSpan.classList.add('message-timestamp');
            timestampSpan.textContent = msg.created_at_formatted || new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            messageDiv.appendChild(timestampSpan);

            // Adicionar recibo de leitura para mensagens enviadas (simplificado)
            if (isSenderCurrentUser && msg.is_read) {
                const readReceiptSpan = document.createElement('span');
                readReceiptSpan.classList.add('read-receipt');
                readReceiptSpan.title = 'Lida';
                readReceiptSpan.innerHTML = '✓✓'; // Duplo check
                messageDiv.appendChild(readReceiptSpan);
            }


            messageListSection.appendChild(messageDiv);
            scrollToBottom();
            if (msg.message_id > lastKnownMessageId) {
                lastKnownMessageId = msg.message_id;
            }
        }

        // Atualizar contadores de não lidas na lista de conversas
        function updateUnreadCountOnSidebar(conversationId, count) {
            const unreadBadge = document.getElementById(`unread-count-${conversationId}`);
            const conversationItem = document.querySelector(`.conversation-item[data-conversation-id="${conversationId}"]`);
            if (unreadBadge && conversationItem) {
                if (count > 0) {
                    unreadBadge.textContent = count;
                    unreadBadge.style.display = 'inline-block';
                    conversationItem.classList.add('has-unread'); // Adicionar classe para possível estilização
                } else {
                    unreadBadge.textContent = '0';
                    unreadBadge.style.display = 'none';
                    conversationItem.classList.remove('has-unread');
                }
                // Atualizar preview da última mensagem também seria bom aqui
            }
        }


        async function fetchNewMessages() {
            if (!activeConversationId) return;

            try {
                const response = await fetch(`get_new_messages_ajax.php?conversation_id=<span class="math-inline">\{activeConversationId\}&last\_message\_id\=</span>{lastKnownMessageId}`);
                if (!response.ok) {
                    console.error("Erro na rede ao buscar mensagens:", response.statusText);
                    return;
                }
                const data = await response.json();

                if (data.success && data.messages && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        appendMessageToUI(msg, msg.sender_id === currentUserId);
                    });
                    // Se esta é a conversa ativa, o contador de não lidas dela deve ser zerado na sidebar
                    updateUnreadCountOnSidebar(activeConversationId, 0);
                } else if (!data.success) {
                    console.error("Erro ao buscar mensagens:", data.error);
                }
            } catch (error) {
                console.error("Erro de JavaScript ao buscar mensagens:", error);
            }
        }

        if (messageForm) {
            messageForm.addEventListener('submit', async function(event) {
                event.preventDefault();
                const messageText = messageInput.value.trim();
                const conversationIdHidden = this.querySelector('input[name="conversation_id_form"]').value;

                if (!messageText || !conversationIdHidden) return;

                const formData = new FormData();
                formData.append('conversation_id', conversationIdHidden);
                formData.append('message_text', messageText);

                try {
                    const response = await fetch('send_message_ajax.php', {
                        method: 'POST',
                        body: formData
                    });
                    if (!response.ok) {
                         console.error("Erro na rede ao enviar mensagem:", response.statusText);
                         alert("Erro ao enviar mensagem. Tente novamente.");
                         return;
                    }
                    const data = await response.json();

                    if (data.success && data.message) {
                        appendMessageToUI(data.message, true); // true porque o utilizador atual enviou
                        messageInput.value = '';
                        messageInput.style.height = 'auto'; // Reset textarea height
                        // A mensagem enviada já terá o lastKnownMessageId atualizado
                    } else {
                        alert("Erro ao enviar mensagem: " + (data.error || "Erro desconhecido"));
                    }
                } catch (error) {
                    console.error("Erro de JavaScript ao enviar mensagem:", error);
                    alert("Erro ao enviar mensagem. Verifique sua conexão.");
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            scrollToBottom(); // Scroll inicial

            // Determinar o lastKnownMessageId das mensagens já carregadas
            const existingMessages = messageListSection ? messageListSection.querySelectorAll('.message-bubble') : [];
            if (existingMessages.length > 0) {
                lastKnownMessageId = parseInt(existingMessages[existingMessages.length - 1].dataset.messageId) || 0;
            }

            if (activeConversationId) {
                pollingInterval = setInterval(fetchNewMessages, 5000); // Polling a cada 5 segundos
            }

            // Auto-ajuste da altura do textarea
            if(messageInput) {
                messageInput.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            }
        });

        // Limpar intervalo se a página for descarregada (ou mudar de conversa, mais complexo)
        window.addEventListener('beforeunload', () => {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
        });
    </script>
</body>
</html>