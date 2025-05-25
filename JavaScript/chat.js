class MessagingSystem {
    constructor() {
        this.currentConversationId = null;
        this.messagePollingInterval = null;
        this.conversationPollingInterval = null;
        this.typingTimeout = null;
        this.isTyping = false;
        this.searchTimeout = null;
        this.currentUserId = null; // Será definido após inicialização

        this.initializeElements();
        this.getCurrentUserId(); // Buscar ID do usuário
        this.bindEvents();
        this.loadConversations();
        this.startPolling();

        // Verificar se há um conversation_id na URL
        const urlParams = new URLSearchParams(window.location.search);
        const conversationId = urlParams.get('conversation_id');
        if (conversationId) {
            // Carregar a conversa automaticamente
            this.selectConversation(conversationId, 'Conversa Carregada');
        }
    }
    
    getCurrentUserId() {
        // Buscar do atributo data ou variável global
        const appElement = document.getElementById('app') || document.body;
        this.currentUserId = appElement.dataset.userId || window.currentUserId;
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
        const conversationElement = document.querySelector(`[data-conversation-id="${conversationId}"]`);
        if (conversationElement) {
            conversationElement.classList.add('active');
        }

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
        
        const html = messages.map(msg => {
            const isSent = msg.sender_id == this.currentUserId;
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