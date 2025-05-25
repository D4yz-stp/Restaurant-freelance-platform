<?php
/**
 * Repositório de Mensagens - Responsável por todas as consultas relacionadas ao sistema de mensagens
 */
class MessagesRepository {
    private $db;
    
    /**
     * Construtor
     */
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Busca todas as conversas de um usuário
     *
     * @param int $user_id ID do usuário
     * @param string $user_role Papel do usuário (restaurant/freelancer)
     * @return array Lista de conversas
     */
    public function getConversations($user_id, $user_role) {
        try {
            if ($user_role == 'restaurant') {
                $stmt = $this->db->prepare("
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
                $stmt = $this->db->prepare("
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
    
    /**
     * Busca todas as mensagens de uma conversa
     *
     * @param int $conversation_id ID da conversa
     * @return array Lista de mensagens
     */
    public function getMessages($conversation_id) {
        try {
            $stmt = $this->db->prepare("
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
    
    /**
     * Envia uma nova mensagem
     *
     * @param int $conversation_id ID da conversa
     * @param int $sender_id ID do remetente
     * @param string $message_text Texto da mensagem
     * @return array Resultado da operação
     */
    public function sendMessage($conversation_id, $sender_id, $message_text) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO Messages (conversation_id, sender_id, message_text, is_delivered, created_at)
                VALUES (?, ?, ?, 1, datetime('now'))
            ");
            $stmt->execute([$conversation_id, $sender_id, $message_text]);
            return ['success' => true, 'message_id' => $this->db->getConnection()->lastInsertId()];
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Cria uma nova conversa entre usuários
     *
     * @param int $current_user_id ID do usuário atual
     * @param int $target_user_id ID do usuário alvo
     * @param string $current_user_role Papel do usuário atual
     * @return array Resultado da operação
     */
    public function createConversation($current_user_id, $target_user_id, $current_user_role) {
        try {
            if ($current_user_role == 'restaurant') {
                $restaurant_stmt = $this->db->prepare("SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = ?");
                $restaurant_stmt->execute([$current_user_id]);
                $restaurant_id = $restaurant_stmt->fetchColumn();
                
                $freelancer_stmt = $this->db->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = ?");
                $freelancer_stmt->execute([$target_user_id]);
                $freelancer_id = $freelancer_stmt->fetchColumn();
            } else {
                $freelancer_stmt = $this->db->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = ?");
                $freelancer_stmt->execute([$current_user_id]);
                $freelancer_id = $freelancer_stmt->fetchColumn();
                
                $restaurant_stmt = $this->db->prepare("SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = ?");
                $restaurant_stmt->execute([$target_user_id]);
                $restaurant_id = $restaurant_stmt->fetchColumn();
            }
            
            // Verificar se a conversa já existe
            $check_stmt = $this->db->prepare("
                SELECT conversation_id FROM Conversations 
                WHERE restaurant_id = ? AND freelancer_id = ?
            ");
            $check_stmt->execute([$restaurant_id, $freelancer_id]);
            $existing = $check_stmt->fetchColumn();
            
            if ($existing) {
                return ['success' => true, 'conversation_id' => $existing];
            }
            
            // Criar nova conversa
            $stmt = $this->db->prepare("
                INSERT INTO Conversations (restaurant_id, freelancer_id, created_at)
                VALUES (?, ?, datetime('now'))
            ");
            $stmt->execute([$restaurant_id, $freelancer_id]);
            
            return ['success' => true, 'conversation_id' => $this->db->getConnection()->lastInsertId()];
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Marca mensagens como lidas
     *
     * @param int $conversation_id ID da conversa
     * @param int $user_id ID do usuário
     * @return bool Sucesso da operação
     */
    public function markMessagesAsRead($conversation_id, $user_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE Messages 
                SET is_read = 1, read_at = datetime('now')
                WHERE conversation_id = ? AND sender_id != ? AND is_read = 0
            ");
            $stmt->execute([$conversation_id, $user_id]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Define indicador de digitação
     *
     * @param int $conversation_id ID da conversa
     * @param int $user_id ID do usuário
     * @param bool $is_typing Status de digitação
     * @return bool Sucesso da operação
     */
    public function setTypingIndicator($conversation_id, $user_id, $is_typing) {
        try {
            $stmt = $this->db->prepare("
                INSERT OR REPLACE INTO TypingIndicators (conversation_id, user_id, is_typing, last_activity)
                VALUES (?, ?, ?, datetime('now'))
            ");
            $stmt->execute([$conversation_id, $user_id, $is_typing]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Busca indicadores de digitação ativos
     *
     * @param int $conversation_id ID da conversa
     * @param int $current_user_id ID do usuário atual
     * @return array Lista de usuários digitando
     */
    public function getTypingIndicator($conversation_id, $current_user_id) {
        try {
            $stmt = $this->db->prepare("
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
    
    /**
     * Busca usuários por termo de pesquisa
     *
     * @param string $search_term Termo de busca
     * @param int $current_user_id ID do usuário atual
     * @param string $current_user_role Papel do usuário atual
     * @return array Lista de usuários encontrados
     */
    public function searchUsers($search_term, $current_user_id, $current_user_role) {
        try {
            if ($current_user_role == 'restaurant') {
                // Restaurante procura freelancers
                $stmt = $this->db->prepare("
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
                $stmt = $this->db->prepare("
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
}
?>