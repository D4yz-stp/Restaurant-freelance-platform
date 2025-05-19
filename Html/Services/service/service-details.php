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

// Obter detalhes completos do serviço específico
$stmt = $db->prepare("
    SELECT
        s.service_id, s.title AS service_title, s.description AS service_description,
        s.price_type, s.base_price, s.service_image_url, s.created_at AS service_created,
        s.category_id, 
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

// Obter habilidades relevantes para este serviço específico
$stmt = $db->prepare("
    SELECT s.skill_name, fs.proficiency_level
    FROM FreelancerSkills fs
    JOIN Skills s ON fs.skill_id = s.skill_id
    WHERE fs.freelancer_id = ?
    AND s.skill_id IN (
        -- Subquery para encontrar habilidades geralmente relacionadas a esta categoria 
        -- baseado em padrões históricos de oferta de serviços
        SELECT DISTINCT fs2.skill_id
        FROM Services sv
        JOIN FreelancerSkills fs2 ON sv.freelancer_id = fs2.freelancer_id
        WHERE sv.category_id = ?
    )
");
$stmt->execute([$service['freelancer_id'], $service['category_id']]);
$skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter idiomas do freelancer - isso é relevante para qualquer serviço
$stmt = $db->prepare("
    SELECT l.language_name, fl.proficiency
    FROM FreelancerLanguages fl
    JOIN Languages l ON fl.language_id = l.language_id
    WHERE fl.freelancer_id = ?
");
$stmt->execute([$service['freelancer_id']]);
$languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter especializações do freelancer relacionadas à categoria do serviço atual
$specializations = [];

// Verificar qual especialização está relacionada à categoria do serviço atual
switch (strtolower($service['category_name'] ?? '')) {
    case 'chef':
    case 'culinária':
    case 'cozinha':
        $stmt = $db->prepare("SELECT * FROM ChefSpecializations WHERE freelancer_id = ?");
        $stmt->execute([$service['freelancer_id']]);
        $chefSpecs = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($chefSpecs) {
            $specializations['chef'] = $chefSpecs;
        }
        break;
        
    case 'limpeza':
    case 'higienização':
        $stmt = $pdo->prepare("SELECT * FROM CleaningSpecializations WHERE freelancer_id = ?");
        $stmt->execute([$service['freelancer_id']]);
        $cleaningSpecs = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cleaningSpecs) {
            $specializations['cleaning'] = $cleaningSpecs;
        }
        break;
        
    case 'bar':
    case 'bartender':
    case 'bebidas':
        $stmt = $pdo->prepare("SELECT * FROM BartenderSpecializations WHERE freelancer_id = ?");
        $stmt->execute([$service['freelancer_id']]);
        $bartenderSpecs = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($bartenderSpecs) {
            $specializations['bartender'] = $bartenderSpecs;
        }
        break;
        
    case 'atendimento':
    case 'garçom':
    case 'serviço':
        $stmt = $pdo->prepare("SELECT * FROM ServiceStaffSpecializations WHERE freelancer_id = ?");
        $stmt->execute([$service['freelancer_id']]);
        $serviceStaffSpecs = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($serviceStaffSpecs) {
            $specializations['service_staff'] = $serviceStaffSpecs;
        }
        break;
}

// Obter avaliações APENAS para este serviço específico
$stmt = $pdo->prepare("
    SELECT 
        r.review_id, r.overall_rating, r.comment, r.created_at,
        u.first_name, u.last_name, u.profile_image_url,
        c.contract_id, c.title AS contract_title
    FROM Reviews r
    JOIN Contracts c ON r.contract_id = c.contract_id
    JOIN Users u ON r.reviewer_id = u.user_id
    JOIN RestaurantProfiles rp ON u.user_id = rp.user_id
    WHERE c.service_id = ? -- Filtrando especificamente por este serviço
    AND r.reviewee_id = (SELECT user_id FROM FreelancerProfiles WHERE profile_id = ?)
    ORDER BY r.created_at DESC
    LIMIT 10
");
$stmt->execute([$service_id, $service['freelancer_id']]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estatísticas específicas para este serviço com base nas avaliações
$serviceStats = [
    'avg_rating' => 0,
    'total_reviews' => count($reviews),
    'rating_distribution' => [
        '5' => 0,
        '4' => 0,
        '3' => 0,
        '2' => 0, 
        '1' => 0
    ]
];

if (count($reviews) > 0) {
    $totalRating = 0;
    foreach ($reviews as $review) {
        $totalRating += $review['overall_rating'];
        $serviceStats['rating_distribution'][$review['overall_rating']]++;
    }
    $serviceStats['avg_rating'] = $totalRating / count($reviews);
}

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
        JOIN Services s ON fp.profile_id = s.freelancer_id
        WHERE s.service_id = ? AND fp.user_id = ?
    ");
    $stmt->execute([$service_id, $_SESSION['user_id']]);
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
            
            // Adicionar mensagem inicial com referência ao serviço específico
            if (isset($_POST['message']) && !empty(trim($_POST['message']))) {
                $serviceReference = "Referente ao serviço: " . $service['service_title'];
                $fullMessage = $serviceReference . "\n\n" . $_POST['message'];
                
                $stmt = $pdo->prepare("
                    INSERT INTO Messages (conversation_id, sender_id, message_text)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$conversation_id, $_SESSION['user_id'], $fullMessage]);
            }
            
            $pdo->commit();
            $hasConversation = true;
            $conversationSuccess = 'Conversa iniciada com sucesso para o serviço: ' . $service['service_title'];
            
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
                                    <?php if ($i <= round($serviceStats['avg_rating'])): ?>
                                        <i class="fas fa-star"></i>
                                    <?php elseif ($i - 0.5 <= $serviceStats['avg_rating']): ?>
                                        <i class="fas fa-star-half-alt"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            <span class="rating-value"><?php echo number_format($serviceStats['avg_rating'], 1); ?></span>
                            <span class="review-count">(<?php echo $serviceStats['total_reviews']; ?> avaliações para este serviço)</span>
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
                                        <i class="fas fa-comments"></i> Falar sobre este serviço
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
                        <!-- Informações específicas deste serviço -->
                        <div class="meta-item">
                            <i class="fas fa-tag"></i>
                            <div class="meta-content">
                                <span class="meta-label">Tipo de Preço</span>
                                <span class="meta-value"><?php echo ($service['price_type'] == 'hourly') ? 'Por hora' : 'Preço fixo'; ?></span>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <i class="fas fa-calendar-check"></i>
                            <div class="meta-content">
                                <span class="meta-label">Disponibilidade para este serviço</span>
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
                    <div class="tab-pane fade show active" id="description" role="tabpanel">
                        <div class="service-description">
                            <h3>Sobre este serviço</h3>
                            <?php echo nl2br(safeHtml($service['service_description'])); ?>
                        </div>
                    </div>
                    
                    <!-- Aba de Habilidades e Especializações -->
                    <div class="tab-pane fade" id="skills" role="tabpanel">
                        <!-- Habilidades relevantes para este serviço -->
                        <?php if (!empty($skills)): ?>
                            <div class="skills-section">
                                <h3>Habilidades para este Serviço</h3>
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
                        <?php else: ?>
                            <p>Não há habilidades específicas cadastradas para este serviço.</p>
                        <?php endif; ?>
                        
                        <!-- Idiomas do prestador -->
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
                        
                        <!-- Especializações relevantes para este serviço -->
                        <?php if (!empty($specializations)): ?>
                            <div class="specializations-section">
                                <h3>Especializações para <?php echo safeHtml($service['category_name']); ?></h3>
                                
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
                                            <li><strong>Serviço de mesa:</strong> <?php echo $specializations['service_staff']['table_service'] ? 'Sim' : 'Não'; ?></li>
                                            <li><strong>Experiência em eventos:</strong> <?php echo $specializations['service_staff']['event_experience'] ? 'Sim' : 'Não'; ?></li>
                                            
                                            <?php if (!empty($specializations['service_staff']['serving_style'])): ?>
                                                <li><strong>Estilo de Serviço:</strong> <?php echo safeHtml($specializations['service_staff']['serving_style']); ?></li>
                                            <?php endif; ?>
                                            
                                            <li><strong>Conhecimento de Etiqueta:</strong> <?php echo $specializations['service_staff']['etiquette_knowledge'] ? 'Sim' : 'Não'; ?></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p>Não há especializações registradas para este tipo de serviço.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Aba de Avaliações para ESTE SERVIÇO ESPECÍFICO -->
                    <div class="tab-pane fade" id="reviews" role="tabpanel">
                        <div class="reviews-section">
                            <h3>Avaliações deste Serviço</h3>
                            
                            <!-- Estatísticas das avaliações -->
                            <div class="review-stats">
                                <div class="overall-rating">
                                    <span class="rating-number"><?php echo number_format($serviceStats['avg_rating'], 1); ?></span>
                                    <div class="stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= round($serviceStats['avg_rating'])): ?>
                                                <i class="fas fa-star"></i>
                                            <?php elseif ($i - 0.5 <= $serviceStats['avg_rating']): ?>
                                                <i class="fas fa-star-half-alt"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="review-count"><?php echo $serviceStats['total_reviews']; ?> avaliações</span>
                                </div>
                                
                                <div class="rating-bars">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <?php 
                                        $count = $serviceStats['rating_distribution'][$i];
                                        $percentage = ($serviceStats['total_reviews'] > 0) ? 
                                            ($count / $serviceStats['total_reviews']) * 100 : 0;
                                        ?>
                                        <div class="rating-bar-item">
                                            <div class="rating-stars">
                                                <span><?php echo $i; ?> <i class="fas fa-star"></i></span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%" 
                                                    aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <div class="rating-count">
                                                <span><?php echo $count; ?></span>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <!-- Lista de avaliações -->
                            <div class="reviews-list">
                                <?php if (empty($reviews)): ?>
                                    <div class="empty-reviews">
                                        <p>Este serviço ainda não possui avaliações. Seja o primeiro a avaliar!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($reviews as $review): ?>
                                        <?php 
                                        $reviewerImg = !empty($review['profile_image_url']) ? $review['profile_image_url'] : 'assets/images/default-profile.jpg';
                                        $reviewerName = $review['first_name'] . ' ' . $review['last_name'];
                                        $reviewDate = new DateTime($review['created_at']);
                                        $formattedDate = $reviewDate->format('d/m/Y');
                                        ?>
                                        <div class="review-item">
                                            <div class="reviewer-info">
                                                <img src="<?php echo $reviewerImg; ?>" alt="<?php echo safeHtml($reviewerName); ?>" class="reviewer-avatar">
                                                <div class="reviewer-details">
                                                    <h4 class="reviewer-name"><?php echo safeHtml($reviewerName); ?></h4>
                                                    <span class="review-date"><?php echo $formattedDate; ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="review-content">
                                                <div class="review-rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <?php if ($i <= $review['overall_rating']): ?>
                                                            <i class="fas fa-star"></i>
                                                        <?php else: ?>
                                                            <i class="far fa-star"></i>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </div>
                                                
                                                <?php if (!empty($review['contract_title'])): ?>
                                                    <div class="service-context">
                                                        <span class="contract-reference">Projeto: <?php echo safeHtml($review['contract_title']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="review-text">
                                                    <?php echo nl2br(safeHtml($review['comment'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Aba Sobre o Prestador -->
                    <div class="tab-pane fade" id="provider" role="tabpanel">
                        <div class="provider-section">
                            <div class="provider-profile-detailed">
                                <div class="provider-header">
                                    <img src="<?php echo $profileImage; ?>" alt="<?php echo safeHtml($fullName); ?>" class="provider-avatar-large">
                                    <div class="provider-headline">
                                        <h3 class="provider-name"><?php echo safeHtml($fullName); ?></h3>
                                        <div class="provider-meta">
                                            <span class="provider-experience">
                                                <i class="fas fa-briefcase"></i> <?php echo $service['experience_years']; ?> anos de experiência
                                            </span>
                                            <span class="provider-overall-rating">
                                                <i class="fas fa-star"></i> <?php echo $rating; ?> (<?php echo $service['review_count']; ?> avaliações totais)
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Disponibilidade do freelancer -->
                                <div class="provider-availability">
                                    <h4><i class="fas fa-calendar-alt"></i> Disponibilidade Geral</h4>
                                    <p><?php echo $availability; ?></p>
                                    
                                    <?php if (!empty($service['availability_details'])): ?>
                                        <div class="availability-details">
                                            <h5>Detalhes de Disponibilidade:</h5>
                                            <p><?php echo nl2br(safeHtml($service['availability_details'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Outros serviços do mesmo freelancer -->
                                <?php
                                $stmt = $pdo->prepare("
                                    SELECT service_id, title, base_price, price_type, service_image_url, category_id
                                    FROM Services
                                    WHERE freelancer_id = ? 
                                    AND service_id != ? 
                                    AND is_active = 1
                                    LIMIT 4
                                ");
                                $stmt->execute([$service['freelancer_id'], $service_id]);
                                $otherServices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (!empty($otherServices)): ?>
                                    <div class="other-services-section">
                                        <h4>Outros Serviços deste Prestador</h4>
                                        <div class="other-services-grid">
                                            <?php foreach ($otherServices as $otherService):
                                                $otherServiceImg = !empty($otherService['service_image_url']) ? $otherService['service_image_url'] : 'assets/images/default-service.jpg';
                                                $otherServicePrice = number_format($otherService['base_price'], 2, ',', '.');
                                                $otherServicePriceType = ($otherService['price_type'] == 'hourly') ? '/hora' : '';
                                                
                                                // Obter nome da categoria
                                                $stmt = $pdo->prepare("SELECT name FROM ServiceCategories WHERE category_id = ?");
                                                $stmt->execute([$otherService['category_id']]);
                                                $categoryName = $stmt->fetchColumn() ?: 'Categoria';
                                            ?>
                                                <div class="other-service-card">
                                                    <a href="service-details.php?id=<?php echo $otherService['service_id']; ?>" class="service-link">
                                                        <div class="other-service-image">
                                                            <img src="<?php echo $otherServiceImg; ?>" alt="<?php echo safeHtml($otherService['title']); ?>">
                                                        </div>
                                                        <div class="other-service-info">
                                                            <h5 class="other-service-title"><?php echo safeHtml($otherService['title']); ?></h5>
                                                            <span class="other-service-category"><?php echo safeHtml($categoryName); ?></span>
                                                            <div class="other-service-price">
                                                                <span>R$ <?php echo $otherServicePrice; ?></span>
                                                                <span class="price-type"><?php echo $otherServicePriceType; ?></span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="see-all-services">
                                            <a href="freelancer-profile.php?id=<?php echo $service['freelancer_id']; ?>" class="btn btn-outline-primary">
                                                Ver Todos os Serviços deste Prestador
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal para iniciar conversa -->
<?php if ($isRestaurant && !$hasConversation && !$isOwner): ?>
<div class="modal fade" id="contactModal" tabindex="-1" role="dialog" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contactModalLabel">Falar sobre: <?php echo safeHtml($service['service_title']); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="message">Sua mensagem para <?php echo safeHtml($fullName); ?>:</label>
                        <textarea class="form-control" id="message" name="message" rows="5" placeholder="Olá, gostaria de conversar sobre este serviço..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" name="start_conversation" class="btn btn-primary">Iniciar Conversa</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Seção de serviços semelhantes - Recomendações baseadas na categoria -->
<section class="similar-services-section">
    <div class="container">
        <h2 class="section-title">Serviços Semelhantes</h2>
        <div class="services-grid">
            <?php
            // Buscar serviços semelhantes da mesma categoria (exceto este)
            $stmt = $pdo->prepare("
                SELECT 
                    s.service_id, s.title, s.base_price, s.price_type, s.service_image_url,
                    u.first_name, u.last_name, u.profile_image_url,
                    fp.avg_rating, fp.review_count, fp.profile_id
                FROM Services s
                JOIN FreelancerProfiles fp ON s.freelancer_id = fp.profile_id
                JOIN Users u ON fp.user_id = u.user_id
                WHERE s.category_id = ? 
                AND s.service_id != ? 
                AND s.is_active = 1
                ORDER BY fp.avg_rating DESC
                LIMIT 4
            ");
            $stmt->execute([$service['category_id'], $service_id]);
            $similarServices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($similarServices)):
                foreach ($similarServices as $similarService):
                    $similarServiceImg = !empty($similarService['service_image_url']) ? $similarService['service_image_url'] : 'assets/images/default-service.jpg';
                    $similarProviderImg = !empty($similarService['profile_image_url']) ? $similarService['profile_image_url'] : 'assets/images/default-profile.jpg';
                    $similarServicePrice = number_format($similarService['base_price'], 2, ',', '.');
                    $similarServicePriceType = ($similarService['price_type'] == 'hourly') ? '/hora' : '';
                    $similarProviderName = $similarService['first_name'] . ' ' . $similarService['last_name'];
                    $similarServiceRating = number_format($similarService['avg_rating'], 1);
            ?>
                <div class="service-card">
                    <a href="service-details.php?id=<?php echo $similarService['service_id']; ?>" class="service-link">
                        <div class="service-image">
                            <img src="<?php echo $similarServiceImg; ?>" alt="<?php echo safeHtml($similarService['title']); ?>">
                        </div>
                        <div class="service-info">
                            <h3 class="service-title"><?php echo safeHtml($similarService['title']); ?></h3>
                            <div class="service-provider">
                                <img src="<?php echo $similarProviderImg; ?>" alt="<?php echo safeHtml($similarProviderName); ?>" class="provider-avatar-small">
                                <span class="provider-name"><?php echo safeHtml($similarProviderName); ?></span>
                            </div>
                            <div class="service-meta">
                                <div class="service-rating">
                                    <i class="fas fa-star"></i>
                                    <span><?php echo $similarServiceRating; ?></span>
                                    <span class="review-count">(<?php echo $similarService['review_count']; ?>)</span>
                                </div>
                                <div class="service-price">
                                    <span>R$ <?php echo $similarServicePrice; ?></span>
                                    <span class="price-type"><?php echo $similarServicePriceType; ?></span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php
                endforeach;
            else:
            ?>
                <div class="no-similar-services">
                    <p>Não encontramos serviços semelhantes no momento.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="view-more-section">
            <a href="search-services.php?category=<?php echo $service['category_id']; ?>" class="btn btn-outline-primary">
                Ver Mais Serviços de <?php echo safeHtml($service['category_name']); ?>
            </a>
        </div>
    </div>
</section>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="../../../JavaScript/main.js"></script>
<?php
// Incluir o rodapé do site
include '../components/footer.php';
?>
</body>
</html>