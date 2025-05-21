
    /**
     * Obtém o perfil do usuário por tipo (freelancer ou restaurante)
     * 
     * @param int $userId ID do usuário
     * @param string $userRole Tipo de usuário (freelancer ou restaurant)
     * @return mixed ID do perfil ou null
     */

    
    public function getUserProfileId($userId, $userRole) {
        $stmt = null;
        
        if ($userRole === 'freelancer') {
            $stmt = $this->db->prepare("SELECT profile_id FROM FreelancerProfiles WHERE user_id = :user_id");
        } else if ($userRole === 'restaurant') {
            $stmt = $this->db->prepare("SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = :user_id");
        }
        
        if ($stmt) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $userRole === 'freelancer' ? $result['profile_id'] : $result['restaurant_id'];
            }
        }
        
        return null;
    }

    /**
     * Obtém todas as conversas de um freelancer
     * 
     * @param int $profileId ID do perfil do freelancer
     * @param int $userId ID do usuário
     * @return array Lista de conversas
     */
    public function getFreelancerConversations($profileId, $userId) {
        $conversations = [];
        
        $stmt = $this->db->prepare("SELECT c.conversation_id, r.restaurant_name, u.first_name, u.last_name, u.profile_image_url, 
                        (SELECT COUNT(*) FROM Messages WHERE conversation_id = c.conversation_id AND sender_id != :user_id AND is_read = 0) as unread_count,
                        (SELECT message_text FROM Messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
                        (SELECT created_at FROM Messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_time
                        FROM Conversations c
                        JOIN RestaurantProfiles r ON c.restaurant_id = r.restaurant_id
                        JOIN Users u ON r.user_id = u.user_id
                        WHERE c.freelancer_id = :profile_id
                        ORDER BY last_message_time DESC");
        
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_INT);
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $conversations[] = $row;
        }
        
        return $conversations;
    }

    /**
     * Obtém todas as conversas de um restaurante
     * 
     * @param int $profileId ID do perfil do restaurante
     * @param int $userId ID do usuário
     * @return array Lista de conversas
     */
    public function getRestaurantConversations($profileId, $userId) {
        $conversations = [];
        
        $stmt = $this->db->prepare("SELECT c.conversation_id, u.first_name, u.last_name, u.profile_image_url, 
                        (SELECT COUNT(*) FROM Messages WHERE conversation_id = c.conversation_id AND sender_id != :user_id AND is_read = 0) as unread_count,
                        (SELECT message_text FROM Messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
                        (SELECT created_at FROM Messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_time
                        FROM Conversations c
                        JOIN FreelancerProfiles f ON c.freelancer_id = f.profile_id
                        JOIN Users u ON f.user_id = u.user_id
                        WHERE c.restaurant_id = :profile_id
                        ORDER BY last_message_time DESC");
        
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_INT);
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $conversations[] = $row;
        }
        
        return $conversations;
    }

    /**
     * Obtém informações do outro usuário em uma conversa
     * 
     * @param int $conversationId ID da conversa
     * @param string $userRole Papel do usuário atual
     * @return array Informações do outro usuário
     */
    public function getOtherUserInConversation($conversationId, $userRole) {
        $stmt = null;
        
        if ($userRole === 'freelancer') {
            $stmt = $this->db->prepare("SELECT u.user_id, u.first_name, u.last_name, u.profile_image_url, r.restaurant_name
                            FROM Conversations c
                            JOIN RestaurantProfiles r ON c.restaurant_id = r.restaurant_id
                            JOIN Users u ON r.user_id = u.user_id
                            WHERE c.conversation_id = :conversation_id");
        } else {
            $stmt = $this->db->prepare("SELECT u.user_id, u.first_name, u.last_name, u.profile_image_url
                            FROM Conversations c
                            JOIN FreelancerProfiles f ON c.freelancer_id = f.profile_id
                            JOIN Users u ON f.user_id = u.user_id
                            WHERE c.conversation_id = :conversation_id");
        }
        
        $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtém todas as mensagens de uma conversa
     * 
     * @param int $conversationId ID da conversa
     * @return array Lista de mensagens
     */
    public function getConversationMessages($conversationId) {
        $messages = [];
        
        $stmt = $this->db->prepare("SELECT m.message_id, m.sender_id, m.message_text, m.is_read, m.created_at,
                        u.first_name, u.last_name, u.profile_image_url
                        FROM Messages m
                        JOIN Users u ON m.sender_id = u.user_id
                        WHERE m.conversation_id = :conversation_id
                        ORDER BY m.created_at ASC");
        
        $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $messages[] = $row;
        }
        
        return $messages;
    }

    /**
     * Marca todas as mensagens de uma conversa como lidas
     * 
     * @param int $conversationId ID da conversa
     * @param int $userId ID do usuário atual
     * @return bool Sucesso da operação
     */
    public function markMessagesAsRead($conversationId, $userId) {
        $stmt = $this->db->prepare("UPDATE Messages SET is_read = 1 
                        WHERE conversation_id = :conversation_id AND sender_id != :user_id AND is_read = 0");
        
        $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Envia uma nova mensagem
     * 
     * @param int $conversationId ID da conversa
     * @param int $senderId ID do remetente
     * @param string $messageText Texto da mensagem
     * @return bool Sucesso da operação
     */
    public function sendMessage($conversationId, $senderId, $messageText) {
        $stmt = $this->db->prepare("INSERT INTO Messages (conversation_id, sender_id, message_text) 
                        VALUES (:conversation_id, :sender_id, :message_text)");
        
        $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':sender_id', $senderId, PDO::PARAM_INT);
        $stmt->bindValue(':message_text', $messageText, PDO::PARAM_STR);
        
        return $stmt->execute();
    }

    /**
     * Verifica se uma conversa já existe entre um restaurante e um freelancer
     * 
     * @param int $restaurantId ID do restaurante
     * @param int $freelancerId ID do freelancer
     * @return mixed ID da conversa ou false
     */
    public function checkConversationExists($restaurantId, $freelancerId) {
        $stmt = $this->db->prepare("SELECT conversation_id FROM Conversations 
                    WHERE restaurant_id = :restaurant_id AND freelancer_id = :freelancer_id");
        
        $stmt->bindValue(':restaurant_id', $restaurantId, PDO::PARAM_INT);
        $stmt->bindValue(':freelancer_id', $freelancerId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['conversation_id'] : false;
    }

    /**
     * Cria uma nova conversa entre um restaurante e um freelancer
     * 
     * @param int $restaurantId ID do restaurante
     * @param int $freelancerId ID do freelancer
     * @return int ID da nova conversa
     */
    public function createNewConversation($restaurantId, $freelancerId) {
        $stmt = $this->db->prepare("INSERT INTO Conversations (restaurant_id, freelancer_id) 
                        VALUES (:restaurant_id, :freelancer_id)");
        
        $stmt->bindValue(':restaurant_id', $restaurantId, PDO::PARAM_INT);
        $stmt->bindValue(':freelancer_id', $freelancerId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $this->db->lastInsertId();
    }

    /**
     * Obtém a lista de freelancers para iniciar uma nova conversa
     * 
     * @return array Lista de freelancers
     */
    public function getFreelancersList() {
        $freelancers = [];
        
        $stmt = $this->db->prepare("SELECT f.profile_id, u.first_name, u.last_name 
                FROM FreelancerProfiles f 
                JOIN Users u ON f.user_id = u.user_id 
                ORDER BY u.first_name, u.last_name");
        
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $freelancers[] = $row;
        }
        
        return $freelancers;
    }

    /**
     * Verifica se a conversa pertence ao usuário
     * 
     * @param int $conversationId ID da conversa
     * @param array $conversations Lista de conversas do usuário
     * @return array|null Conversa encontrada ou null
     */
    public function validateConversationBelongsToUser($conversationId, $conversations) {
        foreach ($conversations as $conv) {
            if ($conv['conversation_id'] == $conversationId) {
                return $conv;
            }
        }
        
        return null;
    }