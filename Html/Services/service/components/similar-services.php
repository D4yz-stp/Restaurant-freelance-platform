<?php


// Obter instância do banco de dados
$db = Database::getInstance();
$pdo = $db->getConnection();

// Obter o ID do serviço atual (assumindo que ele está disponível no contexto)
$service_id = $_GET['id'] ?? 0;

// Inicializar o repositório de serviços
$servicesRepository = new ServicesRepository($pdo);

// Obter detalhes do serviço atual (se necessário)
$service = $servicesRepository->getServiceDetails($service_id);

// Obter serviços semelhantes
$similarServices = $servicesRepository->getSimilarServices($service['category_id'], $service_id);
$categoryName = $service['category_name']; // Certifique-se de que $service['category_name'] está definido
?>

<!-- Conteúdo do componente similar-services.php -->
<section class="similar-services-section">
    <div class="container">
        <h2 class="section-title">Serviços Semelhantes</h2>
        <div class="services-grid">
            <?php if (!empty($similarServices)): ?>
                <?php foreach ($similarServices as $similarService):
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
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-similar-services">
                    <p>Não encontramos serviços semelhantes no momento.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="view-more-section">
            <a href="search-services.php?category=<?php echo $service['category_id']; ?>" class="btn btn-outline-primary">
                Ver Mais Serviços de <?php echo safeHtml($categoryName); ?>
            </a>
        </div>
    </div>
</section>
