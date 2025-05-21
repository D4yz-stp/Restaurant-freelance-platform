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
                    setTimeout(updateConversationsList, 500);
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
            }, 2000);
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
        setInterval(fetchNewMessages, 3000);
    }

    // Atualizar lista de conversas a cada 5 segundos
    setInterval(updateConversationsList, 5000);

    // Verificar status de digitação a cada 2 segundos
    if (currentConversationId) {
        setInterval(checkTypingStatus, 2000);
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

                data.messages.forEach(msg => {
                    // Atualizar o último ID de mensagem
                    if (parseInt(msg.message_id) > lastMessageId) {
                        lastMessageId = parseInt(msg.message_id);
                        hasNewMessages = true;
                    }

                    // Verificar se é um novo dia
                    const messageDate = new Date(msg.created_at).toISOString().split('T')[0];
                    if (currentDate !== messageDate) {
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
                        currentDate = messageDate;
                    }

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
        }, 5000);
    }
});
