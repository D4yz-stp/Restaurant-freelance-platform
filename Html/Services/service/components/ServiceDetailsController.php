<?php
/**
 * Controller para os detalhes do serviço
 * Gerencia a lógica de negócios para exibição e manipulação dos detalhes do serviço
 */
class ServiceDetailsController {
    private $serviceRepository;
    private $conversationHandler;
    private $db;
    
    /**
     * Construtor
     */
    public function __construct($db) {
        $this->db = $db;
        $this->serviceRepository = new ServicesRepository($db);
        $this->conversationHandler = new ConversationHandler($db);
    }
    
    /**
     * Processa a requisição para exibir os detalhes do serviço
     * 
     * @param int $serviceId ID do serviço
     * @return array Dados para exibição na view
     */
    public function processServiceDetails($serviceId) {
        // Verificar se o ID do serviço é válido
        if (!is_numeric($serviceId)) {
            return ['redirect' => 'search-services.php'];
        }
        
        // Obter detalhes do serviço
        $service = $this->serviceRepository->getServiceDetails($serviceId);
        
        // Se o serviço não existir ou não estiver ativo, redirecionar
        if (!$service) {
            return ['redirect' => 'search-services.php'];
        }
        
        // Obter informações adicionais
        $skills = $this->serviceRepository->getServiceRelatedSkills($service['freelancer_id'], $service['category_id']);
        $languages = $this->serviceRepository->getFreelancerLanguages($service['freelancer_id']);
        $specializations = $this->serviceRepository->getFreelancerSpecializations($service['freelancer_id'], $service['category_name']);
        $reviews = $this->serviceRepository->getServiceReviews($serviceId, $service['freelancer_id']);
        $serviceStats = $this->serviceRepository->calculateServiceRatingStats($reviews);
        
        // Formatar dados para exibição
        $viewData = $this->formatDataForView($service);
        
        // Verificar situação do usuário atual (restaurante, dono do serviço, etc.)
        $userStatus = $this->checkUserStatus($service);
        
        // Combinar todos os dados para exibição
        return array_merge($viewData, [
            'service' => $service,
            'skills' => $skills,
            'languages' => $languages,
            'specializations' => $specializations,
            'reviews' => $reviews,
            'serviceStats' => $serviceStats
        ], $userStatus);
    }
    
    /**
     * Formata os dados do serviço para exibição
     * 
     * @param array $service Dados do serviço
     * @return array Dados formatados
     */
    private function formatDataForView($service) {
        return [
            'fullName' => $service['first_name'] . ' ' . $service['last_name'],
            'price' =>  formatCurrency($service['base_price']),
            'priceType' => ($service['price_type'] == 'hourly') ? '/hora' : '',
            'rating' => number_format($service['avg_rating'], 1),
            'availability' => ($service['availability'] == 'flexible') ? 'Flexível' : $service['availability'],
            'profileImage' => !empty($service['profile_image_url']) ? $service['profile_image_url'] : 'assets/images/default-profile.jpg',
            'serviceImage' => !empty($service['service_image_url']) ? $service['service_image_url'] : 'assets/images/default-service.jpg'
        ];
    }
    
    /**
     * Verifica o status do usuário atual em relação ao serviço
     * 
     * @param array $service Dados do serviço
     * @return array Status do usuário
     */
    private function checkUserStatus($service) {
        $userStatus = [
            'isRestaurant' => false,
            'isOwner' => false,
            'hasConversation' => false,
            'conversation_id' => null,
            'conversationError' => '',
            'conversationSuccess' => ''
        ];
        
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            return $userStatus;
        }
        
        // Verificar se o usuário é um restaurante
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'restaurant') {
            $userStatus['isRestaurant'] = true;
            
            // Verificar se já existe uma conversa
            $conversation = $this->conversationHandler->checkExistingConversation($_SESSION['user_id'], $service['freelancer_id']);
            
            if ($conversation) {
                $userStatus['hasConversation'] = true;
                $userStatus['conversation_id'] = $conversation['conversation_id'];
            }
        }
        
        // Verificar se o usuário é o dono deste serviço
        $userStatus['isOwner'] = $this->serviceRepository->isServiceOwner($service['service_id'], $_SESSION['user_id']);
        
        return $userStatus;
    }
    
    /**
     * Processa o formulário para iniciar uma conversa
     * 
     * @param array $service Dados do serviço
     * @return array Resultado da operação
     */
    public function handleConversationStart($service) {
        $result = [
            'hasConversation' => false,
            'conversation_id' => null,
            'conversationError' => '',
            'conversationSuccess' => ''
        ];
        
        // Verificar se é um método POST e se tem os dados necessários
        if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['start_conversation'])) {
            return $result;
        }
        
        // Verificar se o usuário é um restaurante e não tem conversa ainda
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'restaurant') {
            $result['conversationError'] = 'Apenas restaurantes podem iniciar conversas.';
            return $result;
        }
        
        // Verificar se já existe uma conversa
        $existingConversation = $this->conversationHandler->checkExistingConversation($_SESSION['user_id'], $service['freelancer_id']);
        
        if ($existingConversation) {
            $result['hasConversation'] = true;
            $result['conversation_id'] = $existingConversation['conversation_id'];
            return $result;
        }
        
        // Iniciar nova conversa
        $message = isset($_POST['message']) ? $_POST['message'] : '';
        $conversationResult = $this->conversationHandler->startConversation(
            $_SESSION['user_id'], 
            $service['freelancer_id'], 
            $service['service_title'], 
            $message
        );
        
        if ($conversationResult['success']) {
            $result['hasConversation'] = true;
            $result['conversation_id'] = $conversationResult['conversation_id'];
            $result['conversationSuccess'] = $conversationResult['message'];
        } else {
            $result['conversationError'] = $conversationResult['error'];
        }
        
        return $result;
    }
}