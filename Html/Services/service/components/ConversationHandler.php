<?php
/**
 * Gerenciador de Conversas - Responsável por todas as operações relacionadas a conversas
 */
class ConversationHandler {
    private $db;
    
    /**
     * Construtor
     */
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Verifica se existe uma conversa entre restaurante e freelancer
     * 
     * @param int $userId ID do usuário do restaurante
     * @param int $freelancerId ID do freelancer
     * @return array|false Detalhes da conversa ou false se não encontrada
     */
    public function checkExistingConversation($userId, $freelancerId) {
        $stmt = $this->db->prepare("
            SELECT conversation_id 
            FROM Conversations 
            WHERE restaurant_id = (SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = ?) 
            AND freelancer_id = ?
        ");
        
        $stmt->execute([$userId, $freelancerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Inicia uma nova conversa entre restaurante e freelancer
     * 
     * @param int $userId ID do usuário do restaurante
     * @param int $freelancerId ID do freelancer
     * @param string $serviceTitle Título do serviço para referência
     * @param string $message Mensagem inicial
     * @return array|false Resultado da operação com ID da conversa ou false em caso de erro
     */
    public function startConversation($userId, $freelancerId, $serviceTitle, $message) {
        try {
            // Obter ID do restaurante do usuário atual
            $stmt = $this->db->prepare("SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = ?");
            $stmt->execute([$userId]);
            $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$restaurant) {
                return ['success' => false, 'error' => 'Perfil de restaurante não encontrado.'];
            }
            
            $this->db->beginTransaction();
            
            // Criar nova conversa
            $stmt = $this->db->prepare("
                INSERT INTO Conversations (restaurant_id, freelancer_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$restaurant['restaurant_id'], $freelancerId]);
            $conversationId = $this->db->lastInsertId();
            
            // Adicionar mensagem inicial com referência ao serviço específico
            if (!empty(trim($message))) {
                $serviceReference = "Referente ao serviço: " . $serviceTitle;
                $fullMessage = $serviceReference . "\n\n" . $message;
                
                $stmt = $this->db->prepare("
                    INSERT INTO Messages (conversation_id, sender_id, message_text)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$conversationId, $userId, $fullMessage]);
            }
            
            $this->db->commit();
            return [
                'success' => true, 
                'conversation_id' => $conversationId,
                'message' => 'Conversa iniciada com sucesso para o serviço: ' . $serviceTitle
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Erro ao iniciar conversa. Por favor, tente novamente.'];
        }
    }
}