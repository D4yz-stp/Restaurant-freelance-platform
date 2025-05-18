<?php
// Iniciar sessão
session_start();

// Incluir arquivo de conexão com o banco de dados
require_once '../components/database.php';
// Incluir funções úteis
require_once 'functions.php';
require_once '../components/header.php';

// Obter instância do banco de dados
$db = Database::getInstance();
$pdo = $db->getConnection();

// Verificar se o ID do serviço foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirecionar para a página de pesquisa de serviços se não houver ID válido
    header("Location: search-services.php");
    exit();
}

$service_id = intval($_GET['id']);

// Obter detalhes completos do serviço, do freelancer e suas avaliações
$stmt = $db->prepare("
    SELECT
        s.service_id, s.title AS service_title, s.description AS service_description,
        s.price_type, s.base_price, s.service_image_url, s.created_at AS service_created,
        fp.profile_id AS freelancer_id, fp.hourly_rate, fp.availability, fp.experience_years,
        fp.avg_rating, fp.review_count, fp.availability_details,
        u.user_id, u.first_name, u.last_name, u.email, u.profile_image_url, u.country, u.city,
        sc.name AS category_name
    FROM Services s
    JOIN FreelancerProfiles fp ON s.freelancer_id = fp.profile_id
    JOIN Users u ON fp.user_id = u.user_id
    JOIN ServiceCategories sc ON s.category_id = sc.category_id
    WHERE s.service_id = ? AND s.is_active = 1
");

$stmt->execute([$service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

// Se o serviço não existir ou não estiver ativo, redirecionar
if (!$service) {
    header("Location: search-services.php");
    exit();
}

// Formatar dados para exibição
$fullName = $service['first_name'] . ' ' . $service['last_name'];
$price = number_format($service['base_price'], 2, ',', '.');
$priceType = ($service['price_type'] == 'hourly') ? '/hora' : '';
$rating = number_format($service['avg_rating'], 1);
$availability = ($service['availability'] == 'flexible') ? 'Flexível' : $service['availability'];
$profileImage = !empty($service['profile_image_url']) ? $service['profile_image_url'] : 'assets/images/default-profile.jpg';
$serviceImage = !empty($service['service_image_url']) ? $service['service_image_url'] : 'assets/images/default-service.jpg';

// Obter habilidades do freelancer
$stmt = $db->prepare("
    SELECT s.skill_name, fs.proficiency_level
    FROM FreelancerSkills fs
    JOIN Skills s ON fs.skill_id = s.skill_id
    WHERE fs.freelancer_id = ?
");
$stmt->execute([$service['freelancer_id']]);
$skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter idiomas do freelancer
$stmt = $db->prepare("
    SELECT l.language_name, fl.proficiency
    FROM FreelancerLanguages fl
    JOIN Languages l ON fl.language_id = l.language_id
    WHERE fl.freelancer_id = ?
");
$stmt->execute([$service['freelancer_id']]);
$languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter especializações do freelancer com base na categoria do serviço
// Como não sabemos a categoria exata, vamos verificar em todas as tabelas de especialização
$specializations = [];

// Verificar se é um Chef
$stmt = $db->prepare("SELECT * FROM ChefSpecializations WHERE freelancer_id = ?");
$stmt->execute([$service['freelancer_id']]);
$chefSpecs = $stmt->fetch(PDO::FETCH_ASSOC);
if ($chefSpecs) {
    $specializations['chef'] = $chefSpecs;
}

// Verificar se é especializado em Limpeza
$stmt = $pdo->prepare("SELECT * FROM CleaningSpecializations WHERE freelancer_id = ?");
$stmt->execute([$service['freelancer_id']]);
$cleaningSpecs = $stmt->fetch(PDO::FETCH_ASSOC);
if ($cleaningSpecs) {
    $specializations['cleaning'] = $cleaningSpecs;
}

// Verificar se é Bartender
$stmt = $pdo->prepare("SELECT * FROM BartenderSpecializations WHERE freelancer_id = ?");
$stmt->execute([$service['freelancer_id']]);
$bartenderSpecs = $stmt->fetch(PDO::FETCH_ASSOC);
if ($bartenderSpecs) {
    $specializations['bartender'] = $bartenderSpecs;
}

// Verificar se é especializado em Atendimento
$stmt = $pdo->prepare("SELECT * FROM ServiceStaffSpecializations WHERE freelancer_id = ?");
$stmt->execute([$service['freelancer_id']]);
$serviceStaffSpecs = $stmt->fetch(PDO::FETCH_ASSOC);
if ($serviceStaffSpecs) {
    $specializations['service_staff'] = $serviceStaffSpecs;
}

// Obter avaliações para este freelancer
$stmt = $pdo->prepare("
    SELECT 
        r.review_id, r.overall_rating, r.comment, r.created_at,
        u.first_name, u.last_name, u.profile_image_url,
        c.contract_id, c.title AS contract_title
    FROM Reviews r
    JOIN Contracts c ON r.contract_id = c.contract_id
    JOIN Users u ON r.reviewer_id = u.user_id
    JOIN RestaurantProfiles rp ON u.user_id = rp.user_id
    WHERE r.reviewee_id = (SELECT user_id FROM FreelancerProfiles WHERE profile_id = ?)
    ORDER BY r.created_at DESC
    LIMIT 10
");
$stmt->execute([$service['freelancer_id']]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar se o usuário atual é um restaurante para mostrar o botão de conversa
$isRestaurant = false;
$hasConversation = false;
$conversation_id = null;

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'restaurant') {
    $isRestaurant = true;
    
    // Verificar se já existe uma conversa entre este restaurante e o freelancer
    $stmt = $pdo->prepare("
        SELECT conversation_id 
        FROM Conversations 
        WHERE restaurant_id = (SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = ?) 
        AND freelancer_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $service['freelancer_id']]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conversation) {
        $hasConversation = true;
        $conversation_id = $conversation['conversation_id'];
    }
}

// Verificar se o usuário atual é o dono deste serviço
$isOwner = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("
        SELECT 1 FROM FreelancerProfiles fp
        WHERE fp.profile_id = ? AND fp.user_id = ?
    ");
    $stmt->execute([$service['freelancer_id'], $_SESSION['user_id']]);
    $isOwner = $stmt->fetchColumn() ? true : false;
}

// Processar formulário para iniciar uma conversa
$conversationError = '';
$conversationSuccess = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['start_conversation']) && $isRestaurant && !$hasConversation) {
    // Obter ID do restaurante do usuário atual
    $stmt = $pdo->prepare("SELECT restaurant_id FROM RestaurantProfiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($restaurant) {
        try {
            $pdo->beginTransaction();
            
            // Criar nova conversa
            $stmt = $pdo->prepare("
                INSERT INTO Conversations (restaurant_id, freelancer_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$restaurant['restaurant_id'], $service['freelancer_id']]);
            $conversation_id = $pdo->lastInsertId();
            
            // Adicionar mensagem inicial
            if (isset($_POST['message']) && !empty(trim($_POST['message']))) {
                $stmt = $pdo->prepare("
                    INSERT INTO Messages (conversation_id, sender_id, message_text)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$conversation_id, $_SESSION['user_id'], $_POST['message']]);
            }
            
            $pdo->commit();
            $hasConversation = true;
            $conversationSuccess = 'Conversa iniciada com sucesso!';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $conversationError = 'Erro ao iniciar conversa. Por favor, tente novamente.';
        }
    } else {
        $conversationError = 'Perfil de restaurante não encontrado.';
    }
}

// Incluir o cabeçalho do site
include 'includes/header.php';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="OlgaRJ - Plataforma de perfis profissionais para restauração">
    <title>OlgaRJ | Perfis Profissionais para Restauração</title>
    
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../../../Css/global.css">
    <link rel="stylesheet" href="../../../Css/main_service.css">
    <link rel="stylesheet" href="../../../Css/header+button.css">
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
            <div class="service-main-info">
                <div class="service-image-large">
                    <img src="<?php echo $serviceImage; ?>" alt="<?php echo safeHtml($service['service_title']); ?>">
                </div>
                
                <div class="service-primary-info">
                    <div class="service-header">
                        <h1 class="service-title"><?php echo safeHtml($service['service_title']); ?></h1>
                        <div class="service-category">
                            <span class="category-badge"><?php echo safeHtml($service['category_name']); ?></span>
                        </div>
                    </div>
                    
                    <div class="service-rating-price">
                        <div class="service-rating">
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= round($service['avg_rating'])): ?>
                                        <i class="fas fa-star"></i>
                                    <?php elseif ($i - 0.5 <= $service['avg_rating']): ?>
                                        <i class="fas fa-star-half-alt"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            <span class="rating-value"><?php echo $rating; ?></span>
                            <span class="review-count">(<?php echo $service['review_count']; ?> avaliações)</span>
                        </div>
                        
                        <div class="service-price">
                            <span class="price-value">R$ <?php echo $price; ?></span>
                            <span class="price-type"><?php echo $priceType; ?></span>
                        </div>
                    </div>
                    
                    <div class="service-provider-info">
                        <div class="provider-profile">
                            <img src="<?php echo $profileImage; ?>" alt="<?php echo safeHtml($fullName); ?>" class="provider-avatar">
                            <div class="provider-details">
                                <h3 class="provider-name"><?php echo safeHtml($fullName); ?></h3>
                                <div class="provider-location">
                                    <?php if (!empty($service['city']) && !empty($service['country'])): ?>
                                        <i class="fas fa-map-marker-alt"></i> <?php echo safeHtml($service['city']); ?>, <?php echo safeHtml($service['country']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($isRestaurant && !$isOwner): ?>
                            <div class="contact-options">
                                <?php if ($hasConversation): ?>
                                    <a href="conversations.php?id=<?php echo $conversation_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-comments"></i> Continuar Conversa
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#contactModal">
                                        <i class="fas fa-comments"></i> Iniciar Conversa
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($isOwner): ?>
                            <div class="owner-options">
                                <a href="edit-service.php?id=<?php echo $service_id; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-edit"></i> Editar Serviço
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="service-meta-info">
                        <div class="meta-item">
                            <i class="fas fa-briefcase"></i>
                            <div class="meta-content">
                                <span class="meta-label">Experiência</span>
                                <span class="meta-value"><?php echo $service['experience_years']; ?> anos</span>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <div class="meta-content">
                                <span class="meta-label">Disponibilidade</span>
                                <span class="meta-value"><?php echo $availability; ?></span>
                            </div>
                        </div>
                        
                        <?php if (!empty($service['availability_details'])): ?>
                            <div class="meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <div class="meta-content">
                                    <span class="meta-label">Detalhes de Disponibilidade</span>
                                    <span class="meta-value"><?php echo safeHtml($service['availability_details']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Conteúdo detalhado em abas -->
            <div class="service-content-tabs">
                <ul class="nav nav-tabs" id="serviceDetailsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="description-tab" data-toggle="tab" href="#description" role="tab">
                            Descrição
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="skills-tab" data-toggle="tab" href="#skills" role="tab">
                            Habilidades e Especializações
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="reviews-tab" data-toggle="tab" href="#reviews" role="tab">
                            Avaliações (<?php echo count($reviews); ?>)
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content" id="serviceDetailsTabsContent">
                    <!-- Aba de Descrição -->
                    <div class="tab-pane fade show active" id="description" role="tabpanel">
                        <div class="service-description">
                            <?php echo nl2br(safeHtml($service['service_description'])); ?>
                        </div>
                    </div>
                    
                    <!-- Aba de Habilidades e Especializações -->
                    <div class="tab-pane fade" id="skills" role="tabpanel">
                        <!-- Habilidades -->
                        <?php if (!empty($skills)): ?>
                            <div class="skills-section">
                                <h3>Habilidades</h3>
                                <div class="skills-list">
                                    <?php foreach ($skills as $skill): ?>
                                        <div class="skill-item">
                                            <span class="skill-name"><?php echo safeHtml($skill['skill_name']); ?></span>
                                            <?php if (!empty($skill['proficiency_level'])): ?>
                                                <span class="proficiency-level"><?php echo safeHtml($skill['proficiency_level']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Idiomas -->
                        <?php if (!empty($languages)): ?>
                            <div class="languages-section">
                                <h3>Idiomas</h3>
                                <div class="languages-list">
                                    <?php foreach ($languages as $language): ?>
                                        <div class="language-item">
                                            <span class="language-name"><?php echo safeHtml($language['language_name']); ?></span>
                                            <?php if (!empty($language['proficiency'])): ?>
                                                <span class="proficiency-level"><?php echo safeHtml($language['proficiency']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Especializações -->
                        <?php if (!empty($specializations)): ?>
                            <div class="specializations-section">
                                <h3>Especializações</h3>
                                
                                <?php if (isset($specializations['chef'])): ?>
                                    <div class="specialization-group">
                                        <h4>Especialização como Chef</h4>
                                        <ul class="specialization-list">
                                            <li><strong>Tipo de Culinária:</strong> <?php echo safeHtml($specializations['chef']['cuisine_type']); ?></li>
                                            
                                            <?php if (!empty($specializations['chef']['certifications'])): ?>
                                                <li><strong>Certificações:</strong> <?php echo safeHtml($specializations['chef']['certifications']); ?></li>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($specializations['chef']['dietary_specialties'])): ?>
                                                <li><strong>Especialidades Dietéticas:</strong> <?php echo safeHtml($specializations['chef']['dietary_specialties']); ?></li>
                                            <?php endif; ?>
                                            
                                            <li><strong>Planejamento de Menu:</strong> <?php echo $specializations['chef']['menu_planning'] ? 'Sim' : 'Não'; ?></li>
                                            <li><strong>Experiência em Catering:</strong> <?php echo $specializations['chef']['catering_experience'] ? 'Sim' : 'Não'; ?></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($specializations['cleaning'])): ?>
                                    <div class="specialization-group">
                                        <h4>Especialização em Limpeza</h4>
                                        <ul class="specialization-list">
                                            <li><strong>Limpeza de Cozinha:</strong> <?php echo $specializations['cleaning']['kitchen_cleaning'] ? 'Sim' : 'Não'; ?></li>
                                            <li><strong>Limpeza de Área de Jantar:</strong> <?php echo $specializations['cleaning']['dining_area_cleaning'] ? 'Sim' : 'Não'; ?></li>
                                            
                                            <?php if (!empty($specializations['cleaning']['equipment_experience'])): ?>
                                                <li><strong>Experiência com Equipamentos:</strong> <?php echo safeHtml($specializations['cleaning']['equipment_experience']); ?></li>
                                            <?php endif; ?>
                                            
                                            <li><strong>Métodos Ecológicos:</strong> <?php echo $specializations['cleaning']['eco_friendly'] ? 'Sim' : 'Não'; ?></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($specializations['bartender'])): ?>
                                    <div class="specialization-group">
                                        <h4>Especialização como Bartender</h4>
                                        <ul class="specialization-list">
                                            <li><strong>Especialista em Coquetéis:</strong> <?php echo $specializations['bartender']['cocktail_specialist'] ? 'Sim' : 'Não'; ?></li>
                                            <li><strong>Conhecimento em Vinhos:</strong> <?php echo $specializations['bartender']['wine_knowledge'] ? 'Sim' : 'Não'; ?></li>
                                            <li><strong>Conhecimento em Cervejas:</strong> <?php echo $specializations['bartender']['beer_knowledge'] ? 'Sim' : 'Não'; ?></li>
                                            <li><strong>Flair Bartending:</strong> <?php echo $specializations['bartender']['flair_bartending'] ? 'Sim' : 'Não'; ?></li>
                                            
                                            <?php if (!empty($specializations['bartender']['certifications'])): ?>
                                                <li><strong>Certificações:</strong> <?php echo safeHtml($specializations['bartender']['certifications']); ?></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($specializations['service_staff'])): ?>
                                    <div class="specialization-group">
                                        <h4>Especialização em Atendimento</h4>
                                        <ul class="specialization-list">
                                            <li><strong>Experiência em Fine Dining:</strong> <?php echo $specializations['service_staff']['fine_dining_experience'] ? 'Sim' : 'Não'; ?></li>
                                            <li><strong>Serviço para Eventos:</strong> <?php echo $specializations['service_staff']['event_service'] ? 'Sim' : 'Não'; ?></li>
                                            <li><strong>Conhecimento de Sommelier:</strong> <?php echo $specializations['service_staff']['sommelier_knowledge'] ? 'Sim' : 'Não'; ?></li>
                                            
                                            <?php if (!empty($specializations['service_staff']['customer_service_rating'])): ?>
                                                <li><strong>Classificação de Atendimento:</strong> 
                                                    <?php echo $specializations['service_staff']['customer_service_rating']; ?>/5
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Aba de Avaliações -->
                    <div class="tab-pane fade" id="reviews" role="tabpanel">
                        <?php if (!empty($reviews)): ?>
                            <div class="reviews-section">
                                <?php foreach ($reviews as $review): ?>
                                    <div class="review-item">
                                        <div class="review-header">
                                            <div class="reviewer-info">
                                                <img src="<?php echo !empty($review['profile_image_url']) ? $review['profile_image_url'] : 'assets/images/default-profile.jpg'; ?>" 
                                                     alt="<?php echo safeHtml($review['first_name'] . ' ' . $review['last_name']); ?>" 
                                                     class="reviewer-avatar">
                                                <div class="reviewer-details">
                                                    <h4 class="reviewer-name"><?php echo safeHtml($review['first_name'] . ' ' . $review['last_name']); ?></h4>
                                                    <div class="review-date"><?php echo date('d/m/Y', strtotime($review['created_at'])); ?></div>
                                                </div>
                                            </div>
                                            <div class="review-rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <?php if ($i <= $review['overall_rating']): ?>
                                                        <i class="fas fa-star"></i>
                                                    <?php else: ?>
                                                        <i class="far fa-star"></i>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <div class="review-content">
                                            <p class="contract-title">Serviço: <?php echo safeHtml($review['contract_title']); ?></p>
                                            <p class="review-comment"><?php echo nl2br(safeHtml($review['comment'])); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-reviews">
                                <p>Este freelancer ainda não recebeu avaliações.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal para Iniciar Conversa -->
<?php if ($isRestaurant && !$hasConversation && !$isOwner): ?>
<div class="modal fade" id="contactModal" tabindex="-1" role="dialog" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="contactModalLabel">Iniciar Conversa com <?php echo safeHtml($fullName); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="message">Mensagem Inicial</label>
                        <textarea class="form-control" id="message" name="message" rows="4" placeholder="Escreva sua mensagem inicial..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" name="start_conversation" class="btn btn-primary">Enviar Mensagem</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
</body>
</html>
