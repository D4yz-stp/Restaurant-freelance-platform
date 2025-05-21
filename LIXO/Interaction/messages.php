<?php
// Iniciar sessão para gerenciar login do usuário
session_start();
include '../Services/components/header.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Log/login.php");
    exit;
}

// Conectar ao banco de dados SQLite
$db_file = '../../database/TesteOlga.db'; // Nome do arquivo do banco de dados SQLite
try {
    $conn = new PDO("sqlite:$db_file");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Falha na conexão: " . $e->getMessage());
}

// Obter detalhes do usuário logado
$user_id = $_SESSION['user_id'];
$role_query = "SELECT r.role_name FROM UserRoles ur
               JOIN Roles r ON ur.role_id = r.role_id
               WHERE ur.user_id = :user_id";
$stmt = $conn->prepare($role_query);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$role = $stmt->fetch(PDO::FETCH_ASSOC)['role_name'];

// Determinar perfil com base na função
$profile_id = null;
$other_role = null;

if ($role == 'freelancer') {
    $profile_query = "SELECT profile_id FROM FreelancerProfiles WHERE user_id = :user_id";
    $other_role = 'restaurant';
} elseif ($role == 'restaurant') {
    $profile_query = "SELECT restaurant_id as profile_id FROM RestaurantProfiles WHERE user_id = :user_id";
    $other_role = 'freelancer';
} else {
    die("Acesso não autorizado");
}

// Preparar e executar a consulta para obter o perfil
$stmt = $conn->prepare($profile_query);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$profile_result = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_id = $profile_result['profile_id'];

// Obter conversas do usuário
$conversations_query = "";
if ($role == 'freelancer') {
    $conversations_query = "SELECT c.conversation_id, r.restaurant_name, u.profile_image_url, c.created_at,
                           (SELECT COUNT(*) FROM Messages WHERE conversation_id = c.conversation_id AND is_read = 0 AND sender_id != :user_id) as unread_count
                           FROM Conversations c
                           JOIN RestaurantProfiles r ON c.restaurant_id = r.restaurant_id
                           JOIN Users u ON r.user_id = u.user_id
                           WHERE c.freelancer_id = :profile_id
                           ORDER BY c.created_at DESC";
} else {
    $conversations_query = "SELECT c.conversation_id, CONCAT(u.first_name, ' ', u.last_name) as freelancer_name, u.profile_image_url, c.created_at,
                           (SELECT COUNT(*) FROM Messages WHERE conversation_id = c.conversation_id AND is_read = 0 AND sender_id != :user_id) as unread_count
                           FROM Conversations c
                           JOIN FreelancerProfiles f ON c.freelancer_id = f.profile_id
                           JOIN Users u ON f.user_id = u.user_id
                           WHERE c.restaurant_id = :profile_id
                           ORDER BY c.created_at DESC";
}

$stmt = $conn->prepare($conversations_query);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindParam(':profile_id', $profile_id, PDO::PARAM_INT);
$stmt->execute();
$conversations_result = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processar nova mensagem quando enviada
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $conversation_id = $_POST['conversation_id'];
    $message_text = $_POST['message_text'];
    
    // Verificar se a conversa pertence ao usuário
    $check_query = "";
    if ($role == 'freelancer') {
        $check_query = "SELECT * FROM Conversations WHERE conversation_id = ? AND freelancer_id = ?";
    } else {
        $check_query = "SELECT * FROM Conversations WHERE conversation_id = ? AND restaurant_id = ?";
    }
    
    $stmt = $conn->prepare($check_query);
    $stmt->execute([$conversation_id, $profile_id]); // Passa parâmetros diretamente no execute()
    $selected_conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($check_result->num_rows > 0) {
        // Inserir nova mensagem
        $insert_message = "INSERT INTO Messages (conversation_id, sender_id, message_text) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_message);
        $insert_message = "INSERT INTO Messages ...";
        $stmt = $conn->prepare($insert_message);
        $stmt->execute([
            ':conversation_id' => $conversation_id,
            ':sender_id' => $user_id,
            ':message_text' => $message_text
        ]);
        $stmt->execute();
    }
    
    // Redirecionar para evitar reenvio do formulário
    header("Location: ".$_SERVER['PHP_SELF']."?conversation=".$conversation_id);
    exit;
}

// Obter mensagens da conversa selecionada
$selected_conversation = null;
$messages = [];
$recipient_name = "";
$recipient_image = "";

if (isset($_GET['conversation'])) {
    $conversation_id = $_GET['conversation'];
    
    // Verificação com PDO
    if ($role == 'freelancer') {
        $check_query = "SELECT c.*, r.restaurant_name as recipient_name, u.profile_image_url 
                       FROM Conversations c 
                       JOIN RestaurantProfiles r ON c.restaurant_id = r.restaurant_id 
                       JOIN Users u ON r.user_id = u.user_id
                       WHERE c.conversation_id = ? AND c.freelancer_id = ?";
    } else {
        $check_query = "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as recipient_name, u.profile_image_url 
                       FROM Conversations c 
                       JOIN FreelancerProfiles f ON c.freelancer_id = f.profile_id 
                       JOIN Users u ON f.user_id = u.user_id
                       WHERE c.conversation_id = ? AND c.restaurant_id = ?";
    }
    
    $stmt = $conn->prepare($check_query);
    $stmt->execute([$conversation_id, $profile_id]);
    $selected_conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_conversation) {
        $recipient_name = $selected_conversation['recipient_name'];
        $recipient_image = $selected_conversation['profile_image_url'] ?? 'assets/default-avatar.png';
        
        // Obter mensagens da conversa
        $messages_query = "SELECT m.*, u.first_name, u.last_name, u.profile_image_url, 
                          (m.sender_id = ?) as is_sender
                          FROM Messages m
                          JOIN Users u ON m.sender_id = u.user_id
                          WHERE m.conversation_id = ?
                          ORDER BY m.created_at ASC";
        $stmt = $conn->prepare($messages_query);
        $stmt->execute([$user_id, $conversation_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Marcar mensagens como lidas
        $mark_read_query = "UPDATE Messages SET is_read = 1 
                           WHERE conversation_id = ? AND sender_id != ? AND is_read = 0";
        $stmt = $conn->prepare($mark_read_query);
        $stmt->bindParam(1, $conversation_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $user_id, PDO::PARAM_INT);
        $stmt->execute();
    }
}

// Iniciar uma nova conversa
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $conversation_id = $_POST['conversation_id'];
    $message_text = trim($_POST['message_text']);
    
    try {
        // Verificar se a conversa pertence ao usuário (PDO)
        $check_query = ($role == 'freelancer') 
            ? "SELECT * FROM Conversations WHERE conversation_id = ? AND freelancer_id = ?"
            : "SELECT * FROM Conversations WHERE conversation_id = ? AND restaurant_id = ?";
        
        $stmt = $conn->prepare($check_query);
        $stmt->execute([$conversation_id, $profile_id]);
        
        if ($stmt->rowCount() > 0) {
            // Inserir mensagem (PDO)
            $insert_message = "INSERT INTO Messages (conversation_id, sender_id, message_text) 
                              VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_message);
            $stmt->execute([
                $conversation_id, 
                $user_id, 
                $message_text
            ]);
            
            // Redirecionar com sucesso
            header("Location: ".$_SERVER['PHP_SELF']."?conversation=".$conversation_id);
            exit;
        } else {
            $_SESSION['error'] = "Conversa não encontrada ou acesso negado";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erro ao enviar mensagem: " . $e->getMessage();
    }
    
    header("Location: ".$_SERVER['REQUEST_URI']);
    exit;
}

// Buscar freelancers ou restaurantes para iniciar conversa
$available_contacts = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = "%" . $_GET['search'] . "%";

    if ($role == 'freelancer') {
        // Buscar restaurantes
        $search_query = "SELECT r.restaurant_id as profile_id, r.restaurant_name as name, u.profile_image_url
                        FROM RestaurantProfiles r
                        JOIN Users u ON r.user_id = u.user_id
                        WHERE r.restaurant_name LIKE :search_term
                        LIMIT 10";
    } else {
        // Buscar freelancers
        $search_query = "SELECT f.profile_id, CONCAT(u.first_name, ' ', u.last_name) as name, u.profile_image_url
                        FROM FreelancerProfiles f
                        JOIN Users u ON f.user_id = u.user_id
                        WHERE CONCAT(u.first_name, ' ', u.last_name) LIKE :search_term
                        LIMIT 10";
    }

    $stmt = $conn->prepare($search_query);
    $stmt->bindParam(':search_term', $search_term, PDO::PARAM_STR);
    $stmt->execute();
    $available_contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obter nome do usuário logado
$user_query = "SELECT first_name, last_name, profile_image_url FROM Users WHERE user_id = :user_id";
$stmt = $conn->prepare($user_query);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_data['first_name'] . ' ' . $user_data['last_name'];
$user_image = $user_data['profile_image_url'] ?? 'assets/default-avatar.png';

// Fechar conexão com o banco
$stmt = null; // Libera o statement
$conn = null; // Fecha a conexão
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Mensagens</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="../../Css/footer.css">
    <link rel="stylesheet" href="../../Css/header+button.css">
    <link rel="stylesheet" href="../../Css/global.css">
</head>
<body>
    <?php include '../Services/components/header.php' ?>
    <div class="containerh topoo">
        <div class="messaging-containerh">
            <!-- Sidebar com lista de conversas -->
            <div class="conversations-sidebar">
                <div class="sidebar-header">
                    <h2>Mensagens</h2>
                    <button class="new-message-btn" id="newMessageBtn">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                
                <div class="search-bar">
                    <form action="" method="GET">
                        <input type="text" name="search" placeholder="Buscar conversas..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                
                <div class="conversation-list">
                    <?php 
                    if (!empty($conversations_result)) {
                        foreach ($conversations_result as $conversation) {
                            $is_active = isset($_GET['conversation']) && $_GET['conversation'] == $conversation['conversation_id'] ? 'active' : '';
                            $name_key = $role == 'freelancer' ? 'restaurant_name' : 'freelancer_name';
                            $unread_badge = $conversation['unread_count'] > 0 ? '<span class="unread-badge">' . $conversation['unread_count'] . '</span>' : '';
                    ?>
                    <a href="?conversation=<?php echo $conversation['conversation_id']; ?>" class="conversation-item <?php echo $is_active; ?>">
                        <img src="<?php echo $conversation['profile_image_url'] ?? 'assets/default-avatar.png'; ?>" alt="Profile" class="profile-img">
                        <div class="conversation-info">
                            <div class="conversation-name">
                                <?php echo htmlspecialchars($conversation[$name_key]); ?>
                                <?php echo $unread_badge; ?>
                            </div>
                            <div class="conversation-date"><?php echo date('d/m/Y', strtotime($conversation['created_at'])); ?></div>
                        </div>
                    </a>
                    <?php 
                        }
                    } else {
                    ?>
                    <div class="no-conversations">
                        <p>Nenhuma conversa encontrada.</p>
                        <p>Inicie uma nova conversa clicando no botão +</p>
                    </div>
                    <?php 
                    }
                    ?>
                </div>
            </div>
            
            <!-- Área de mensagens -->
            <div class="message-area">
                <?php if ($selected_conversation): ?>
                <div class="message-header">
                    <div class="recipient-info">
                        <img src="<?php echo $recipient_image; ?>" alt="Profile" class="profile-img">
                        <div class="recipient-name"><?php echo htmlspecialchars($recipient_name); ?></div>
                    </div>
                    <div class="message-actions">
                        <button class="action-btn">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </div>
                </div>
                
                <div class="messages-containerh" id="messagescontainerh">
                    <?php 
                    if (!empty($messages)) {
                        foreach ($messages as $message) {
                            $message_class = ($message['sender_id'] == $user_id) ? 'sent' : 'received';
                            $time = date('H:i', strtotime($message['created_at']));
                    ?>
                    <div class="message <?php echo $message_class; ?>">
                        <?php echo nl2br(htmlspecialchars($message['message_text'])); ?>
                        <div class="message-time"><?php echo $time; ?></div>
                    </div>
                    <?php 
                        }
                    } else {
                    ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h3>Nenhuma mensagem ainda</h3>
                        <p>Envie uma mensagem para iniciar a conversa.</p>
                    </div>
                    <?php 
                    }
                    ?>
                </div>
                
                <div class="message-input">
                    <form method="POST" action="">
                        <input type="hidden" name="conversation_id" value="<?php echo $selected_conversation['conversation_id']; ?>">
                        <input type="text" name="message_text" placeholder="Digite sua mensagem..." autocomplete="off" required>
                        <button type="submit" name="send_message">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <h3>Selecione uma conversa</h3>
                    <p>Escolha uma conversa existente ou inicie uma nova para começar a trocar mensagens.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal para nova mensagem -->
    <div class="modal" id="newMessageModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Nova Mensagem</h3>
                <button class="close-modal" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="search-bar">
                    <form action="" method="GET">
                        <input type="text" name="search" placeholder="Buscar <?php echo $other_role == 'freelancer' ? 'freelancers' : 'restaurantes'; ?>..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                
                <div class="search-results">
                    <?php 
                    if (!empty($available_contacts)) {
                        foreach ($available_contacts as $contact) {
                    ?>
                    <form method="POST" action="">
                        <input type="hidden" name="other_profile_id" value="<?php echo $contact['profile_id']; ?>">
                        <button type="submit" name="start_conversation" class="contact-item" style="width: 100%; text-align: left; border: none; background: none;">
                            <img src="<?php echo $contact['profile_image_url'] ?? 'assets/default-avatar.png'; ?>" alt="Profile" class="profile-img">
                            <div class="contact-name"><?php echo htmlspecialchars($contact['name']); ?></div>
                        </button>
                    </form>
                    <?php 
                        }
                    } else if (isset($_GET['search'])) {
                    ?>
                    <div class="empty-state" style="padding: 20px 0;">
                        <p>Nenhum resultado encontrado para "<?php echo htmlspecialchars($_GET['search']); ?>"</p>
                    </div>
                    <?php 
                    } else {
                    ?>
                    <div class="empty-state" style="padding: 20px 0;">
                        <p>Busque por nome para encontrar <?php echo $other_role == 'freelancer' ? 'freelancers' : 'restaurantes'; ?></p>
                    </div>
                    <?php 
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Rolar mensagens para o final
        document.addEventListener('DOMContentLoaded', function() {
            const messagescontainerh = document.getElementById('messagescontainerh');
            if (messagescontainerh) {
                messagescontainerh.scrollTop = messagescontainerh.scrollHeight;
            }
            
            // Controle do modal
            const modal = document.getElementById('newMessageModal');
            const newMessageBtn = document.getElementById('newMessageBtn');
            const closeModal = document.getElementById('closeModal');
            
            newMessageBtn.addEventListener('click', function() {
                modal.classList.add('active');
            });
            
            closeModal.addEventListener('click', function() {
                modal.classList.remove('active');
            });
            
            // Fechar modal clicando fora
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
            
            // Manter modal aberto se houver resultados de pesquisa
            <?php if (isset($_GET['search']) && !isset($_GET['conversation'])): ?>
            modal.classList.add('active');
            <?php endif; ?>
            
            // Atualizar mensagens a cada 15 segundos sem recarregar a página
            <?php if (isset($_GET['conversation'])): ?>
            setInterval(function() {
                fetch('get_messages.php?conversation=<?php echo $_GET['conversation']; ?>', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        const currentMessages = document.querySelectorAll('.message').length;
                        
                        if (data.messages.length > currentMessages) {
                            data.messages.slice(currentMessages).forEach(message => {
                                const messageDiv = document.createElement('div');
                                messageDiv.className = `message ${message.is_sender ? 'sent' : 'received'}`;
                                
                                const messageText = document.createElement('div');
                                messageText.innerHTML = message.message_text.replace(/\n/g, '<br>');
                                
                                const messageTime = document.createElement('div');
                                messageTime.className = 'message-time';
                                messageTime.textContent = message.time;
                                
                                messageDiv.appendChild(messageText);
                                messageDiv.appendChild(messageTime);
                                messagescontainerh.appendChild(messageDiv);
                            });
                            
                            messagescontainerh.scrollTop = messagescontainerh.scrollHeight;
                        }
                    }
                })
                .catch(error => {
                    console.error('Erro ao atualizar mensagens:', error);
                });
            }, 15000);
            <?php endif; ?>
        });
    </script>
</body>
</html>