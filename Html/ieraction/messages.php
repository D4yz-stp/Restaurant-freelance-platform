<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Mensagens - Freelancer & Restaurante</title>
    <style>
        :root {
            --primary: #ff6b35;
            --primary-light: #ff8a5c;
            --secondary: #2a9d8f;
            --dark: #264653;
            --light: #f8f9fa;
            --gray: #e9ecef;
            --text: #333;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background-color: var(--light);
            color: var(--text);
        }
        
        .main-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .messages-container {
            display: flex;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            height: 80vh;
        }
        
        /* Lista de conversas */
        .conversations-list {
            width: 30%;
            border-right: 1px solid var(--gray);
            overflow-y: auto;
            background-color: white;
        }
        
        .conversations-header {
            padding: 20px;
            border-bottom: 1px solid var(--gray);
            background-color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .conversations-header h2 {
            font-size: 1.2rem;
            color: var(--dark);
        }
        
        .search-bar {
            margin-top: 10px;
            padding: 10px 15px;
            width: 100%;
            border: 1px solid var(--gray);
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .conversation-item {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--gray);
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .conversation-item:hover, .conversation-item.active {
            background-color: var(--gray);
        }
        
        .profile-pic {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--gray);
            margin-right: 15px;
            overflow: hidden;
        }
        
        .profile-pic img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .conversation-info {
            flex: 1;
        }
        
        .conversation-name {
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .conversation-last-msg {
            font-size: 0.8rem;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversation-time {
            font-size: 0.7rem;
            color: #999;
        }
        
        .unread-indicator {
            width: 10px;
            height: 10px;
            background-color: var(--primary);
            border-radius: 50%;
            margin-left: 10px;
        }
        
        /* Área de mensagens */
        .messages-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: #f5f7f9;
        }
        
        .messages-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--gray);
            display: flex;
            align-items: center;
            background-color: white;
        }
        
        .contact-info {
            flex: 1;
        }
        
        .contact-name {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .contact-status {
            font-size: 0.8rem;
            color: #5cb85c;
        }
        
        .messages-header-actions button {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--dark);
            margin-left: 15px;
            cursor: pointer;
        }
        
        .message-list {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .message {
            max-width: 70%;
            margin-bottom: 15px;
            padding: 12px 15px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
        }
        
        .message-time {
            font-size: 0.7rem;
            color: #999;
            margin-top: 5px;
            text-align: right;
        }
        
        .message.sent {
            align-self: flex-end;
            background-color: var(--primary);
            color: white;
            border-bottom-right-radius: 5px;
        }
        
        .message.received {
            align-self: flex-start;
            background-color: white;
            border-bottom-left-radius: 5px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .message-input-container {
            padding: 15px;
            background-color: white;
            border-top: 1px solid var(--gray);
            display: flex;
            align-items: center;
        }
        
        .message-input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid var(--gray);
            border-radius: 25px;
            font-size: 0.95rem;
            resize: none;
            max-height: 120px;
            min-height: 45px;
        }
        
        .message-actions {
            display: flex;
            margin-left: 10px;
        }
        
        .message-actions button {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: none;
            background-color: var(--primary);
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }
        
        .message-actions button:hover {
            background-color: var(--primary-light);
        }
        
        /* Empty state */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            color: #666;
            padding: 20px;
        }
        
        .empty-state svg {
            width: 100px;
            height: 100px;
            margin-bottom: 20px;
            fill: #ccc;
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 0.9rem;
            max-width: 300px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .conversations-list {
                width: 40%;
            }
            
            .message {
                max-width: 85%;
            }
        }
        
        @media (max-width: 576px) {
            .main-container {
                padding: 0;
                margin: 0;
            }
            
            .messages-container {
                height: 100vh;
                border-radius: 0;
            }
            
            .conversations-list {
                display: none;
            }
            
            .messages-container.show-conversations .conversations-list {
                display: block;
                width: 100%;
            }
            
            .messages-container.show-conversations .messages-area {
                display: none;
            }
            
            .back-to-list {
                display: block;
                margin-right: 15px;
            }
        }

        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 120px;
            background-color: var(--dark);
            color: white;
            text-align: center;
            border-radius: 6px;
            padding: 5px 0;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -60px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.8rem;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        /* Contract view */
        .contract-view {
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .contract-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .contract-details {
            font-size: 0.85rem;
            color: #666;
        }

        .contract-price {
            font-weight: 600;
            color: var(--secondary);
        }

        .load-more {
            text-align: center;
            padding: 10px;
            color: var(--primary);
            cursor: pointer;
            font-size: 0.9rem;
        }

        .load-more:hover {
            text-decoration: underline;
        }

        /* Botões de carregamento */
        #loadMoreConversations, #loadMoreMessages {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            padding: 10px;
            width: 100%;
            text-align: center;
            font-size: 0.9rem;
        }

        #loadMoreConversations:hover, #loadMoreMessages:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="messages-container" id="messagesContainer">
            <!-- Lista de conversas -->
            <div class="conversations-list">
                <div class="conversations-header">
                    <h2>Mensagens</h2>
                    <input type="text" class="search-bar" placeholder="Pesquisar conversas...">
                </div>
                
                <div id="conversationsList">
                    <!-- Exemplos de conversas -->
                    <div class="conversation-item active" data-conversation-id="1" onclick="loadConversation(1)">
                        <div class="profile-pic">
                            <img src="/api/placeholder/100/100" alt="Restaurante Bom Sabor">
                        </div>
                        <div class="conversation-info">
                            <div class="conversation-name">Restaurante Bom Sabor</div>
                            <div class="conversation-last-msg">Pode trabalhar no sábado?</div>
                        </div>
                        <div class="conversation-time">14:30</div>
                        <div class="unread-indicator"></div>
                    </div>
                    
                    <div class="conversation-item" data-conversation-id="2" onclick="loadConversation(2)">
                        <div class="profile-pic">
                            <img src="/api/placeholder/100/100" alt="Cozinha Italiana">
                        </div>
                        <div class="conversation-info">
                            <div class="conversation-name">Cozinha Italiana</div>
                            <div class="conversation-last-msg">Tudo confirmado para amanhã!</div>
                        </div>
                        <div class="conversation-time">Ontem</div>
                    </div>
                    
                    <div class="conversation-item" data-conversation-id="3" onclick="loadConversation(3)">
                        <div class="profile-pic">
                            <img src="/api/placeholder/100/100" alt="Café Central">
                        </div>
                        <div class="conversation-info">
                            <div class="conversation-name">Café Central</div>
                            <div class="conversation-last-msg">Obrigado pelo serviço excelente!</div>
                        </div>
                        <div class="conversation-time">23/05</div>
                    </div>
                </div>
                
                <button id="loadMoreConversations">Carregar mais</button>
            </div>
            
            <!-- Área de mensagens -->
            <div class="messages-area">
                <div class="messages-header">
                    <div class="contact-info">
                        <div class="contact-name">Restaurante Bom Sabor</div>
                        <div class="contact-status">Online agora</div>
                    </div>
                    <div class="messages-header-actions">
                        <button class="tooltip">
                            <i>📋</i>
                            <span class="tooltiptext">Ver Contrato</span>
                        </button>
                        <button class="tooltip">
                            <i>📞</i>
                            <span class="tooltiptext">Ligar</span>
                        </button>
                        <button class="tooltip" id="mobileBackBtn">
                            <i>⋮</i>
                            <span class="tooltiptext">Mais</span>
                        </button>
                    </div>
                </div>
                
                <div class="message-list" id="messagesList">
                    <!-- Contrato associado -->
                    <div class="contract-view">
                        <div class="contract-title">Contrato: Chef para Evento Especial</div>
                        <div class="contract-details">
                            Data: 25/05/2025 • Duração: 6 horas • <span class="contract-price">€350</span>
                        </div>
                    </div>
                    
                    <!-- Mensagens -->
                    <div class="message received">
                        Olá! Precisamos de um chef para um evento especial no próximo sábado. Está disponível?
                        <div class="message-time">14:20</div>
                    </div>
                    
                    <div class="message received">
                        O evento será das 18h às 00h e precisamos de um menu para aproximadamente 50 pessoas.
                        <div class="message-time">14:21</div>
                    </div>
                    
                    <div class="message sent">
                        Olá! Sim, estou disponível nesse horário. Qual tipo de cozinha você está pensando para o evento?
                        <div class="message-time">14:25</div>
                    </div>
                    
                    <div class="message received">
                        Estamos pensando em um menu de culinária mediterrânea. Você tem experiência?
                        <div class="message-time">14:28</div>
                    </div>
                    
                    <div class="message sent">
                        Sim, tenho bastante experiência com culinária mediterrânea. Posso enviar algumas sugestões de menu para você avaliar.
                        <div class="message-time">14:29</div>
                    </div>
                    
                    <div class="message received">
                        Ótimo! Pode trabalhar no sábado então?
                        <div class="message-time">14:30</div>
                    </div>
                    
                    <div class="load-more" id="loadMoreMessages">Carregar mensagens anteriores</div>
                </div>
                
                <div class="message-input-container">
                    <textarea class="message-input" placeholder="Escreva uma mensagem..." rows="1"></textarea>
                    <div class="message-actions">
                        <button>
                            <i>📤</i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-resize textarea
            const textarea = document.querySelector('.message-input');
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
                if (this.scrollHeight > 120) {
                    this.style.overflowY = 'auto';
                } else {
                    this.style.overflowY = 'hidden';
                }
            });
            
            // Envio de mensagem com Enter (mas permite nova linha com Shift+Enter)
            textarea.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
            
            // Botão de enviar
            document.querySelector('.message-actions button').addEventListener('click', sendMessage);
            
            // Toggle mobile view
            const mobileBackBtn = document.getElementById('mobileBackBtn');
            if (mobileBackBtn) {
                mobileBackBtn.addEventListener('click', function() {
                    document.getElementById('messagesContainer').classList.toggle('show-conversations');
                });
            }
            
            // Simular carregamento de mensagens anteriores
            document.getElementById('loadMoreMessages').addEventListener('click', function() {
                loadOlderMessages();
            });
            
            // Simular carregamento de mais conversas
            document.getElementById('loadMoreConversations').addEventListener('click', function() {
                loadMoreConversations();
            });
            
            // Marcar como lida ao clicar
            const conversationItems = document.querySelectorAll('.conversation-item');
            conversationItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Remove active class from all items
                    conversationItems.forEach(i => i.classList.remove('active'));
                    // Add active class to clicked item
                    this.classList.add('active');
                    // Remove unread indicator
                    const unreadIndicator = this.querySelector('.unread-indicator');
                    if (unreadIndicator) {
                        unreadIndicator.remove();
                    }
                    
                    // On mobile, hide conversations list and show messages
                    if (window.innerWidth <= 576) {
                        document.getElementById('messagesContainer').classList.remove('show-conversations');
                    }
                });
            });
            
            // Rolar para a mensagem mais recente ao carregar
            const messageList = document.getElementById('messagesList');
            messageList.scrollTop = messageList.scrollHeight;
        });
        
        // Função para enviar mensagem
        function sendMessage() {
            const textarea = document.querySelector('.message-input');
            const messageText = textarea.value.trim();
            
            if (messageText) {
                const messageList = document.getElementById('messagesList');
                
                // Criar nova mensagem
                const messageDiv = document.createElement('div');
                messageDiv.className = 'message sent';
                
                // Conteúdo da mensagem
                messageDiv.textContent = messageText;
                
                // Horário da mensagem
                const timeDiv = document.createElement('div');
                timeDiv.className = 'message-time';
                const now = new Date();
                timeDiv.textContent = `${now.getHours()}:${now.getMinutes().toString().padStart(2, '0')}`;
                
                messageDiv.appendChild(timeDiv);
                messageList.appendChild(messageDiv);
                
                // Limpar textarea e redefinir altura
                textarea.value = '';
                textarea.style.height = 'auto';
                
                // Rolar para a nova mensagem
                messageList.scrollTop = messageList.scrollHeight;
                
                // Atualizar última mensagem na lista de conversas
                updateLastMessage(1, messageText);
                
                // Aqui você chamaria a função para enviar a mensagem para o servidor
                // sendMessageToServer(messageText, currentConversationId);
            }
        }
        
        // Função para carregar mensagens anteriores
        function loadOlderMessages() {
            const messageList = document.getElementById('messagesList');
            const loadMoreBtn = document.getElementById('loadMoreMessages');
            
            // Simular carregamento
            loadMoreBtn.textContent = 'Carregando...';
            
            // Simular atraso de rede
            setTimeout(() => {
                // Exemplos de mensagens antigas
                const olderMessages = [
                    {text: "Olá, estou procurando um chef para um evento especial.", time: "12:05", type: "received"},
                    {text: "Boa tarde! Estou disponível para ajudar. Que tipo de evento seria?", time: "12:10", type: "sent"},
                    {text: "É um jantar corporativo para cerca de 50 pessoas.", time: "12:15", type: "received"}
                ];
                
                // Criar e inserir mensagens antigas no início da lista
                olderMessages.forEach(msg => {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = `message ${msg.type}`;
                    messageDiv.textContent = msg.text;
                    
                    const timeDiv = document.createElement('div');
                    timeDiv.className = 'message-time';
                    timeDiv.textContent = msg.time;
                    
                    messageDiv.appendChild(timeDiv);
                    
                    // Inserir no início, após o elemento do contrato
                    messageList.insertBefore(messageDiv, messageList.children[1]);
                });
                
                // Restaurar texto do botão
                loadMoreBtn.textContent = 'Carregar mensagens anteriores';
                
                // Se não houver mais mensagens, remover botão
                if (Math.random() > 0.3) {
                    loadMoreBtn.remove();
                }
            }, 1000);
        }
        
        // Função para carregar mais conversas
        function loadMoreConversations() {
            const conversationsList = document.getElementById('conversationsList');
            const loadMoreBtn = document.getElementById('loadMoreConversations');
            
            // Simular carregamento
            loadMoreBtn.textContent = 'Carregando...';
            
            // Simular atraso de rede
            setTimeout(() => {
                // Exemplos de conversas adicionais
                const additionalConversations = [
                    {id: 4, name: "Pizzaria Napoli", lastMsg: "Quando podemos marcar uma reunião?", time: "20/05", pic: "/api/placeholder/100/100"},
                    {id: 5, name: "Bar do Carlos", lastMsg: "Vamos precisar de um bartender para sexta.", time: "19/05", pic: "/api/placeholder/100/100"}
                ];
                
                // Criar e adicionar conversas à lista
                additionalConversations.forEach(conv => {
                    const convDiv = document.createElement('div');
                    convDiv.className = 'conversation-item';
                    convDiv.setAttribute('data-conversation-id', conv.id);
                    convDiv.onclick = function() { loadConversation(conv.id); };
                    
                    convDiv.innerHTML = `
                        <div class="profile-pic">
                            <img src="${conv.pic}" alt="${conv.name}">
                        </div>
                        <div class="conversation-info">
                            <div class="conversation-name">${conv.name}</div>
                            <div class="conversation-last-msg">${conv.lastMsg}</div>
                        </div>
                        <div class="conversation-time">${conv.time}</div>
                    `;
                    
                    conversationsList.appendChild(convDiv);
                });
                
                // Restaurar texto do botão
                loadMoreBtn.textContent = 'Carregar mais';
                
                // Se não houver mais conversas, remover botão
                if (Math.random() > 0.3) {
                    loadMoreBtn.remove();
                }
            }, 1000);
        }
        
        // Função para carregar uma conversa específica
        function loadConversation(conversationId) {
            // Aqui você faria uma requisição ao servidor para carregar os detalhes da conversa
            // e as mensagens associadas a ela
            console.log(`Carregando conversa ${conversationId}`);
            
            // Atualizar a interface para mostrar que a conversa está sendo carregada
            document.querySelector('.contact-name').textContent = 'Carregando...';
            document.querySelector('.contact-status').textContent = '';
            document.getElementById('messagesList').innerHTML = '<div class="load-more">Carregando mensagens...</div>';
            
            // Simular carregamento (em um app real, isto seria uma chamada ajax)
            setTimeout(() => {
                // Dados simulados das conversas
                const conversations = {
                    1: {
                        name: "Restaurante Bom Sabor",
                        status: "Online agora",
                        contract: {
                            title: "Chef para Evento Especial",
                            details: "Data: 25/05/2025 • Duração: 6 horas • €350"
                        },
                        messages: [
                            {text: "Olá! Precisamos de um chef para um evento especial no próximo sábado. Está disponível?", time: "14:20", type: "received"},
                            {text: "O evento será das 18h às 00h e precisamos de um menu para aproximadamente 50 pessoas.", time: "14:21", type: "received"},
                            {text: "Olá! Sim, estou disponível nesse horário. Qual tipo de cozinha você está pensando para o evento?", time: "14:25", type: "sent"},
                            {text: "Estamos pensando em um menu de culinária mediterrânea. Você tem experiência?", time: "14:28", type: "received"},
                            {text: "Sim, tenho bastante experiência com culinária mediterrânea. Posso enviar algumas sugestões de menu para você avaliar.", time: "14:29", type: "sent"},
                            {text: "Ótimo! Pode trabalhar no sábado então?", time: "14:30", type: "received"}
                        ]
                    },
                    2: {
                        name: "Cozinha Italiana",
                        status: "Visto há 2h",
                        contract: {
                            title: "Serviço de Garçom para Evento",
                            details: "Data: 27/05/2025 • Duração: 5 horas • €200"
                        },
                        messages: [
                            {text: "Conforme conversamos, preciso de um garçom para evento na próxima semana.", time: "10:05", type: "received"},
                            {text: "Certo, tenho disponibilidade. Qual será o horário exato?", time: "10:30", type: "sent"},
                            {text: "Das 19h às 00h. Traje formal preto.", time: "10:45", type: "received"},
                            {text: "Perfeito. Estarei lá.", time: "11:00", type: "sent"},
                            {text: "Tudo confirmado para amanhã!", time: "16:20", type: "received"}
                        ]
                    },
                    3: {
                        name: "Café Central",
                        status: "Offline",
                        contract: {
                            title: "Barista para Treinamento",
                            details: "Data: 22/05/2025 • Duração: 3 horas • €180"
                        },
                        messages: [
                            {text: "Precisamos de um barista para treinar nossa equipe sobre cafés especiais.", time: "09:15", type: "received"},
                            {text: "Tenho experiência com treinamentos. Que tipo de café vocês trabalham?", time: "09:30", type: "sent"},
                            {text: "Principalmente grãos da América do Sul e África.", time: "09:45", type: "received"},
                            {text: "Ótimo, tenho bastante conhecimento nessas origens. Podemos agendar para a próxima semana?", time: "10:00", type: "sent"},
                            {text: "Sim, na quarta-feira às 14h.", time: "10:15", type: "received"},
                            {text: "Confirmado então!", time: "10:20", type: "sent"},
                            {text: "Obrigado pelo serviço excelente!", time: "18:30", type: "received"}
                        ]
                    },
                    4: {
                        name: "Pizzaria Napoli",
                        status: "Online agora",
                        contract: null,
                        messages: [
                            {text: "Olá! Estamos procurando um pizzaiolo para cobrir folgas.", time: "13:10", type: "received"},
                            {text: "Olá! Tenho experiência como pizzaiolo. Quais dias seriam?", time: "13:25", type: "sent"},
                            {text: "Principalmente aos domingos.", time: "13:40", type: "received"},
                            {text: "Tenho disponibilidade. Qual o valor da diária?", time: "13:55", type: "sent"},
                            {text: "€120 por dia, das 17h às 23h.", time: "14:10", type: "received"},
                            {text: "Quando podemos marcar uma reunião?", time: "14:15", type: "received"}
                        ]
                    },
                    5: {
                        name: "Bar do Carlos",
                        status: "Visto ontem",
                        contract: null,
                        messages: [
                            {text: "Boa tarde! Você faz drinks especiais?", time: "18:20", type: "received"},
                            {text: "Sim, tenho especialização em coquetelaria clássica e moderna.", time: "18:35", type: "sent"},
                            {text: "Precisamos de um bartender para um evento corporativo na próxima sexta.", time: "18:45", type: "received"},
                            {text: "Vamos precisar de um bartender para sexta.", time: "19:00", type: "received"}
                        ]
                    }
                };
                
                // Atualizar a interface com os dados da conversa selecionada
                const conversation = conversations[conversationId];
                if (conversation) {
                    // Atualizar cabeçalho
                    document.querySelector('.contact-name').textContent = conversation.name;
                    document.querySelector('.contact-status').textContent = conversation.status;
                    
                    // Limpar e adicionar mensagens
                    const messageList = document.getElementById('messagesList');
                    messageList.innerHTML = '';
                    
                    // Adicionar contrato se existir
                    if (conversation.contract) {
                        const contractDiv = document.createElement('div');
                        contractDiv.className = 'contract-view';
                        contractDiv.innerHTML = `
                            <div class="contract-title">Contrato: ${conversation.contract.title}</div>
                            <div class="contract-details">${conversation.contract.details}</div>
                        `;
                        messageList.appendChild(contractDiv);
                    }
                    
                    // Adicionar mensagens
                    conversation.messages.forEach(msg => {
                        const messageDiv = document.createElement('div');
                        messageDiv.className = `message ${msg.type}`;
                        messageDiv.textContent = msg.text;
                        
                        const timeDiv = document.createElement('div');
                        timeDiv.className = 'message-time';
                        timeDiv.textContent = msg.time;
                        
                        messageDiv.appendChild(timeDiv);
                        messageList.appendChild(messageDiv);
                    });
                    
                    // Adicionar botão de carregar mais mensagens se tiver muitas
                    if (conversation.messages.length >= 5) {
                        const loadMoreBtn = document.createElement('div');
                        loadMoreBtn.id = 'loadMoreMessages';
                        loadMoreBtn.className = 'load-more';
                        loadMoreBtn.textContent = 'Carregar mensagens anteriores';
                        loadMoreBtn.addEventListener('click', loadOlderMessages);
                        
                        messageList.insertBefore(loadMoreBtn, messageList.firstChild);
                    }
                    
                    // Rolar para a mensagem mais recente
                    messageList.scrollTop = messageList.scrollHeight;
                }
            }, 300);
        }
        
        // Função para atualizar a última mensagem na lista de conversas
        function updateLastMessage(conversationId, messageText) {
            const conversationItem = document.querySelector(`.conversation-item[data-conversation-id="${conversationId}"]`);
            if (conversationItem) {
                const lastMsgElement = conversationItem.querySelector('.conversation-last-msg');
                if (lastMsgElement) {
                    lastMsgElement.textContent = messageText;
                }
                
                const timeElement = conversationItem.querySelector('.conversation-time');
                if (timeElement) {
                    const now = new Date();
                    timeElement.textContent = `${now.getHours()}:${now.getMinutes().toString().padStart(2, '0')}`;
                }
                
                // Mover conversa para o topo da lista
                const parent = conversationItem.parentNode;
                parent.insertBefore(conversationItem, parent.firstChild);
            }
        }
    </script>
</body>
</html>