<?php
// Arquivo principal service-details.php
// Iniciar sessão
session_start();

// Incluir arquivo de conexão com o banco de dados
require_once '../components/database.php';
// Incluir funções úteis
require_once 'functions.php';
require_once '../components/utils.php';

// Incluir os componentes necessários
require_once '../components/services-repository.php';  // Repositório expandido
require_once 'components/ConversationHandler.php'; // Novo componente para conversas
require_once 'components/serviceDetailsController.php'; // Novo componente para controle



// Obter instância do banco de dados
$db = Database::getInstance();
$pdo = $db->getConnection();

// Verificar se o ID do serviço foi fornecido
if (!isset($_GET['id'])) {
    // Redirecionar para a página de pesquisa de serviços se não houver ID
    header("Location: search-services.php");
    exit();
}

$service_id = intval($_GET['id']);

// Inicializar o controller
$serviceController = new ServiceDetailsController($pdo);

// Processar detalhes do serviço
$data = $serviceController->processServiceDetails($service_id);

// Verificar se precisa redirecionar
if (isset($data['redirect'])) {
    header("Location: " . $data['redirect']);
    exit();
}

// Processar formulário de conversa, se enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['start_conversation'])) {
    $conversationResult = $serviceController->handleConversationStart($data['service']);
    
    // Atualizar os dados com o resultado da conversa
    $data = array_merge($data, $conversationResult);
}

// Extrair variáveis para uso no template
extract($data);

// Incluir o cabeçalho do site
require_once '../components/header.php';

?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="OlgaRJ - Plataforma de perfis profissionais para restauração">
    <title>OlgaRJ | <?php echo safeHtml($service['service_title']); ?></title>
    
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <link rel="stylesheet" href="../../../Css/global.css">
    <link rel="stylesheet" href="../../../Css/main_service.css">
    <link rel="stylesheet" href="../../../Css/header+button.css">
    <link rel="stylesheet" href="../../../Css/footer.css">
</head>
<body>
    
<main class="service-details-page">
    <div class="container">
        <?php if (!empty($conversationError)): ?>
            <div class="alert alert-danger"><?php echo $conversationError; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($conversationSuccess)): ?>
            <div class="alert alert-success"><?php echo $conversationSuccess; ?></div>
        <?php endif; ?>
        
        <div class="service-details-container">
            
            <!-- Seção de imagem e informação principal do serviço -->
            <?php include 'components/main-info.php'; ?>

            
            <!-- Conteúdo detalhado em abas -->
            <div class="service-content-tabs">
                <ul class="nav nav-tabs" id="serviceDetailsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="description-tab" data-toggle="tab" href="#description" role="tab">
                            Descrição do Serviço
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="skills-tab" data-toggle="tab" href="#skills" role="tab">
                            Habilidades para este Serviço
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="reviews-tab" data-toggle="tab" href="#reviews" role="tab">
                            Avaliações (<?php echo $serviceStats['total_reviews']; ?>)
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="provider-tab" data-toggle="tab" href="#provider" role="tab">
                            Sobre o Prestador
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content" id="serviceDetailsTabsContent">
                    
                    <!-- Aba de Descrição -->
                    <?php include 'components/description-tab.php'; ?>

                    
                    <!-- Aba de Habilidades e Especializações -->
                    <?php include 'components/skills-tab.php'; ?>

                    
                    <!-- Aba de Avaliações para ESTE SERVIÇO ESPECÍFICO -->
                    <?php include 'components/reviews-tab.php'; ?>

                    
                    <!-- Aba Sobre o Prestador -->
                    <?php include 'components/provider-tab.php'; ?>

                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal para iniciar conversa -->
<?php include 'components/messages-modal.php'; ?>


<!-- Seção de serviços semelhantes - Recomendações baseadas na categoria -->
<?php include 'components/similar-services.php' ?>

<?php include '../components/footer.php'; ?>


<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="../../../JavaScript/main.js"></script>
<?php
?>
</body>
</html>