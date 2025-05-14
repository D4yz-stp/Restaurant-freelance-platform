<?php
// Conexão com o banco de dados SQLite
try {
    $db = new PDO('sqlite:OlgaRJ.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Obtenção dos filtros da URL (se houver)
$experienceMin = isset($_GET['exp_min']) ? intval($_GET['exp_min']) : 0;
$priceMin = isset($_GET['price_min']) ? floatval($_GET['price_min']) : null;
$priceMax = isset($_GET['price_max']) ? floatval($_GET['price_max']) : null;
$availability = isset($_GET['availability']) ? $_GET['availability'] : [];
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'relevance';

// Construção da consulta SQL com filtros
$query = "SELECT
    u.user_id,
    u.first_name,
    u.last_name,
    fp.hourly_rate,
    fp.availability,
    fp.experience_years,
    fp.avg_rating,
    fp.profile_image,
    s.service_id,
    s.title,
    s.description,
    s.price_type,
    s.base_price,
    s.service_image,
    sc.name AS category_name,
    (SELECT COUNT(*) FROM Reviews r WHERE r.service_id = s.service_id) as review_count,
    GROUP_CONCAT(DISTINCT sk.skill_name) AS skills,
    GROUP_CONCAT(DISTINCT l.language_name) AS languages,
    CASE
        WHEN cs.freelancer_id IS NOT NULL THEN 'Chef'
        WHEN cls.cleaning_id IS NOT NULL THEN 'Limpeza'
        WHEN bts.bartender_id IS NOT NULL THEN 'Bar'
        WHEN sss.service_staff_id IS NOT NULL THEN 'Atendimento'
        ELSE 'Outro'
    END AS specialization_type
FROM Users u
JOIN FreelancerProfiles fp ON u.user_id = fp.user_id
JOIN Services s ON fp.profile_id = s.freelancer_id
LEFT JOIN ServiceCategories sc ON s.category_id = sc.category_id
LEFT JOIN FreelancerSkills fs ON fp.profile_id = fs.freelancer_id
LEFT JOIN Skills sk ON fs.skill_id = sk.skill_id
LEFT JOIN FreelancerLanguages fl ON fp.profile_id = fl.freelancer_id
LEFT JOIN Languages l ON fl.language_id = l.language_id
LEFT JOIN ChefSpecializations cs ON fp.profile_id = cs.freelancer_id
LEFT JOIN CleaningSpecializations cls ON fp.profile_id = cls.cleaning_id
LEFT JOIN BartenderSpecializations bts ON fp.profile_id = bts.freelancer_id
LEFT JOIN ServiceStaffSpecializations sss ON fp.profile_id = sss.service_staff_id
WHERE 1=1";

// Adiciona condições para filtros
if ($experienceMin > 0) {
    $query .= " AND fp.experience_years >= :exp_min";
}

if ($priceMin !== null) {
    $query .= " AND s.base_price >= :price_min";
}

if ($priceMax !== null) {
    $query .= " AND s.base_price <= :price_max";
}

if (!empty($availability)) {
    $availabilityConditions = [];
    foreach ($availability as $time) {
        $availabilityConditions[] = "fp.availability LIKE :availability_$time";
    }
    if (!empty($availabilityConditions)) {
        $query .= " AND (" . implode(" OR ", $availabilityConditions) . ")";
    }
}

if (!empty($search)) {
    $query .= " AND (s.title LIKE :search OR s.description LIKE :search OR sk.skill_name LIKE :search)";
}

// Agrupar resultados por usuário e serviço
$query .= " GROUP BY u.user_id, fp.profile_id, s.service_id";

// Adicionar ordenação
switch ($sort) {
    case 'rating':
        $query .= " ORDER BY fp.avg_rating DESC";
        break;
    case 'price-asc':
        $query .= " ORDER BY s.base_price ASC";
        break;
    case 'price-desc':
        $query .= " ORDER BY s.base_price DESC";
        break;
    case 'experience':
        $query .= " ORDER BY fp.experience_years DESC";
        break;
    case 'relevance':
    default:
        // Ordenação padrão por relevância
        if (!empty($search)) {
            // Se houver busca, prioriza correspondências no título
            $query .= " ORDER BY 
                       CASE WHEN s.title LIKE :search_order THEN 1
                            WHEN s.description LIKE :search_order THEN 2
                            ELSE 3 END,
                       fp.avg_rating DESC";
        } else {
            $query .= " ORDER BY fp.avg_rating DESC";
        }
        break;
}

// Paginação
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 9; // Serviços por página
$offset = ($page - 1) * $perPage;

// Consulta para contar o total de registros
$countQuery = "SELECT COUNT(*) as total FROM ($query) as subquery";

// Adicionar limites para paginação
$query .= " LIMIT :limit OFFSET :offset";

// Preparar e executar a consulta de contagem
$stmt = $db->prepare($countQuery);

// Vincular parâmetros de filtro à consulta de contagem
if ($experienceMin > 0) {
    $stmt->bindParam(':exp_min', $experienceMin, PDO::PARAM_INT);
}

if ($priceMin !== null) {
    $stmt->bindParam(':price_min', $priceMin, PDO::PARAM_STR);
}

if ($priceMax !== null) {
    $stmt->bindParam(':price_max', $priceMax, PDO::PARAM_STR);
}

if (!empty($availability)) {
    foreach ($availability as $time) {
        $availParam = "%$time%";
        $stmt->bindParam(":availability_$time", $availParam, PDO::PARAM_STR);
    }
}

if (!empty($search)) {
    $searchParam = "%$search%";
    $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
}

$stmt->execute();
$totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalCount / $perPage);

// Preparar e executar a consulta principal
$stmt = $db->prepare($query);

// Vincular parâmetros de filtro à consulta principal
if ($experienceMin > 0) {
    $stmt->bindParam(':exp_min', $experienceMin, PDO::PARAM_INT);
}

if ($priceMin !== null) {
    $stmt->bindParam(':price_min', $priceMin, PDO::PARAM_STR);
}

if ($priceMax !== null) {
    $stmt->bindParam(':price_max', $priceMax, PDO::PARAM_STR);
}

if (!empty($availability)) {
    foreach ($availability as $time) {
        $availParam = "%$time%";
        $stmt->bindParam(":availability_$time", $availParam, PDO::PARAM_STR);
    }
}

if (!empty($search)) {
    $searchParam = "%$search%";
    $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
    
    // Adicional para ordenação por relevância
    if ($sort === 'relevance') {
        $stmt->bindParam(':search_order', $searchParam, PDO::PARAM_STR);
    }
}

// Limites para paginação
$stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Função para formatar disponibilidade
function formatAvailability($availability) {
    $availList = explode(',', $availability);
    $availMap = [
        'morning' => 'Manhãs',
        'afternoon' => 'Tardes',
        'evening' => 'Noites',
        'weekend' => 'Fins de semana',
        'flexible' => 'Flexível'
    ];
    
    $formatted = [];
    foreach ($availList as $avail) {
        $avail = trim($avail);
        if (isset($availMap[$avail])) {
            $formatted[] = $availMap[$avail];
        } else {
            $formatted[] = $avail;
        }
    }
    
    return implode(', ', $formatted);
}

// Função para gerar URLs para paginação e filtros
function buildQueryURL($page = null, $additionalParams = []) {
    $params = $_GET;
    
    if ($page !== null) {
        $params['page'] = $page;
    }
    
    // Adicionar parâmetros adicionais ou substituir existentes
    foreach ($additionalParams as $key => $value) {
        $params[$key] = $value;
    }
    
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serviços</title>
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link rel="stylesheet" href="../../Css/global.css">
    <link rel="stylesheet" href="../../Css/service.css">
</head>
<body>
    <main>
        <section class="services-hero">
            <div class="container">
                <h1>Descubra os melhores serviços</h1>
                <p>Encontre profissionais qualificados para realizar o trabalho que você precisa</p>
                <form action="" method="GET" class="search-form">
                    <div class="search-container">
                        <input type="text" name="search" class="search-input" placeholder="Procure por serviços, palavras-chave ou habilidades..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="search-btn">Buscar</button>
                    </div>
                </form>
            </div>
        </section>
        
        <div class="container">
            <div class="main-content">
                <aside class="filters">
                    <h3 class="filter-title" style="margin-top: 0;">Filtros</h3>
                    
                    <form action="" method="GET" id="filter-form">
                        <!-- Mantém a pesquisa atual ao aplicar filtros -->
                        <?php if (!empty($search)): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <?php endif; ?>
                        
                        <div class="filter-section">
                            <h4 class="filter-title">Experiência</h4>
                            <input type="range" class="range-slider" name="exp_min" min="0" max="15" step="1" value="<?php echo $experienceMin; ?>" id="experience-slider">
                            <div class="range-values">
                                <span>0 anos</span>
                                <span id="experience-value"><?php echo $experienceMin; ?> anos</span>
                            </div>
                        </div>
                        
                        <div class="filter-section">
                            <h4 class="filter-title">Valor por hora</h4>
                            <div class="price-inputs">
                                <input type="number" name="price_min" placeholder="Min" id="price-min" min="0" value="<?php echo $priceMin ?? ''; ?>">
                                <span>-</span>
                                <input type="number" name="price_max" placeholder="Max" id="price-max" min="0" value="<?php echo $priceMax ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="filter-section">
                            <h4 class="filter-title">Horário disponível</h4>
                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="availability[]" value="morning" <?php echo in_array('morning', $availability) ? 'checked' : ''; ?>> Manhãs
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="availability[]" value="afternoon" <?php echo in_array('afternoon', $availability) ? 'checked' : ''; ?>> Tardes
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="availability[]" value="evening" <?php echo in_array('evening', $availability) ? 'checked' : ''; ?>> Noites
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="availability[]" value="weekend" <?php echo in_array('weekend', $availability) ? 'checked' : ''; ?>> Fins de semana
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="availability[]" value="flexible" <?php echo in_array('flexible', $availability) ? 'checked' : ''; ?>> Horário flexível
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="filter-btn">Aplicar filtros</button>
                    </form>
                </aside>
                
                <div class="services-container">
                    <div class="services-header">
                        <div class="found-count">
                            <strong><?php echo $totalCount; ?></strong> serviços encontrados
                        </div>
                        
                        <div class="sort-dropdown">
                            <div class="sort-button">
                                Ordenar por: <strong>
                                    <?php
                                    $sortLabels = [
                                        'relevance' => 'Relevância',
                                        'rating' => 'Avaliações',
                                        'price-asc' => 'Preço (menor-maior)',
                                        'price-desc' => 'Preço (maior-menor)',
                                        'experience' => 'Experiência'
                                    ];
                                    echo $sortLabels[$sort] ?? 'Relevância';
                                    ?>
                                </strong>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="sort-dropdown-content">
                                <a href="<?php echo buildQueryURL(1, ['sort' => 'relevance']); ?>" class="sort-option <?php echo $sort === 'relevance' ? 'active' : ''; ?>">Relevância</a>
                                <a href="<?php echo buildQueryURL(1, ['sort' => 'rating']); ?>" class="sort-option <?php echo $sort === 'rating' ? 'active' : ''; ?>">Avaliações</a>
                                <a href="<?php echo buildQueryURL(1, ['sort' => 'price-asc']); ?>" class="sort-option <?php echo $sort === 'price-asc' ? 'active' : ''; ?>">Preço (menor-maior)</a>
                                <a href="<?php echo buildQueryURL(1, ['sort' => 'price-desc']); ?>" class="sort-option <?php echo $sort === 'price-desc' ? 'active' : ''; ?>">Preço (maior-menor)</a>
                                <a href="<?php echo buildQueryURL(1, ['sort' => 'experience']); ?>" class="sort-option <?php echo $sort === 'experience' ? 'active' : ''; ?>">Experiência</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="services-grid">
                        <?php 
                        if (count($services) > 0):
                            $delay = 0;
                            foreach ($services as $service):
                                $delay += 100;
                                
                                // Use imagem padrão se não houver imagem do serviço
                                $serviceImage = !empty($service['service_image']) ? $service['service_image'] : "/api/placeholder/600/400";
                                
                                // Use imagem padrão se não houver imagem de perfil
                                $profileImage = !empty($service['profile_image']) ? $service['profile_image'] : "/api/placeholder/100/100";
                                
                                // Formatações
                                $fullName = $service['first_name'] . ' ' . $service['last_name'];
                                $price = number_format($service['base_price'], 2, ',', '.');
                                $priceType = $service['price_type'] === 'hourly' ? '/hora' : '';
                                $availability = formatAvailability($service['availability']);
                                $rating = number_format($service['avg_rating'], 1, '.', '');
                        ?>
                        <div class="service-card" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                            <div class="service-image">
                                <img src="<?php echo htmlspecialchars($serviceImage); ?>" alt="<?php echo htmlspecialchars($service['title']); ?>">
                                <div class="service-provider">
                                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Avatar" class="provider-avatar">
                                    <div class="provider-name"><?php echo htmlspecialchars($fullName); ?></div>
                                </div>
                            </div>
                            <div class="service-details">
                                <h3 class="service-title"><?php echo htmlspecialchars($service['title']); ?></h3>
                                <p class="service-description"><?php echo htmlspecialchars($service['description']); ?></p>
                                <div class="service-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-briefcase"></i> <?php echo $service['experience_years']; ?> anos exp.
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-clock"></i> <?php echo htmlspecialchars($availability); ?>
                                    </div>
                                </div>
                                <div class="service-footer">
                                    <div class="service-price">
                                        R$ <?php echo $price; ?> <span><?php echo $priceType; ?></span>
                                    </div>
                                    <div class="service-rating">
                                        <i class="fas fa-star rating-star"></i>
                                        <?php echo $rating; ?> (<?php echo $service['review_count']; ?>)
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php 
                            endforeach;
                        else:
                        ?>
                        <div class="no-results">
                            <p>Nenhum serviço encontrado com os filtros selecionados.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                        <a href="<?php echo buildQueryURL($page - 1); ?>" class="page-link prev"><i class="fas fa-chevron-left"></i></a>
                        <?php else: ?>
                        <span class="page-link prev disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>
                        
                        <?php
                        // Determine intervalo de páginas a mostrar
                        $startPage = max(1, $page - 2);
                        $endPage = min($startPage + 4, $totalPages);
                        
                        if ($endPage - $startPage < 4) {
                            $startPage = max(1, $endPage - 4);
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                        <a href="<?php echo buildQueryURL($i); ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <a href="<?php echo buildQueryURL($page + 1); ?>" class="page-link next"><i class="fas fa-chevron-right"></i></a>
                        <?php else: ?>
                        <span class="page-link next disabled"><i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar AOS
            AOS.init({
                duration: 800,
                easing: 'ease-in-out',
                once: true
            });
            
            // Atualizar valor do slider de experiência
            const expSlider = document.getElementById('experience-slider');
            const expValue = document.getElementById('experience-value');
            
            if (expSlider && expValue) {
                expSlider.addEventListener('input', function() {
                    expValue.textContent = this.value + ' anos';
                });
            }
            
            // Dropdown de ordenação
            const sortButton = document.querySelector('.sort-button');
            const sortDropdown = document.querySelector('.sort-dropdown-content');
            
            if (sortButton && sortDropdown) {
                sortButton.addEventListener('click', function() {
                    sortDropdown.classList.toggle('show');
                });
                
                // Fechar dropdown ao clicar fora
                document.addEventListener('click', function(event) {
                    if (!event.target.closest('.sort-dropdown')) {
                        sortDropdown.classList.remove('show');
                    }
                });
            }
        });
    </script>
</body>
</html>