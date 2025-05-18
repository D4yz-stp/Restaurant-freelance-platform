<?php
/**
 * Componente de card de serviço
 * 
 * @param array $service Dados do serviço
 * @param int $delay Delay para animação AOS
 */

// Tratamento para imagens
$serviceImage = getImageUrl($service['service_image_url'], "/api/placeholder/600/400");
$profileImage = getImageUrl($service['profile_image_url'], "/api/placeholder/100/100");

// Formatações
$fullName = safeHtml($service['first_name'] . ' ' . $service['last_name']);
$price = formatCurrency($service['base_price']);
$priceType = $service['price_type'] === 'hourly' ? '/hora' : '';
$availability = formatAvailability($service['availability']);
$rating = number_format($service['avg_rating'], 1, '.', '');

// Limitar tamanho da descrição
$description = safeHtml($service['service_description']);
$description = truncateText($description);
?>

<div class="service-card" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
    <div class="service-image">
        <img src="<?php echo $serviceImage; ?>" alt="<?php echo safeHtml($service['service_title']); ?>">
        <div class="service-provider">
            <img src="<?php echo $profileImage; ?>" alt="Avatar" class="provider-avatar">
            <div class="provider-name"><?php echo $fullName; ?></div>
        </div>
    </div>
    <div class="service-details">
        <h3 class="service-title"><?php echo safeHtml($service['service_title']); ?></h3>
        <p class="service-description"><?php echo $description; ?></p>
        <div class="service-meta">
            <div class="meta-item">
                <i class="fas fa-briefcase"></i> <?php echo $service['experience_years']; ?> anos exp.
            </div>
            <div class="meta-item">
                <i class="fas fa-clock"></i> <?php echo $availability; ?>
            </div>
        </div>
        <div class="service-footer">
            <div class="service-price">
                R$ <?php echo $price; ?> <span><?php echo $priceType; ?></span>
            </div>
            <div class="service-rating">
                <i class="fas fa-star rating-star"></i>
                <?php echo $rating; ?> 
                <span class="review-count">(<?php echo $service['review_count']; ?>)</span>
            </div>
        </div>
        <a href="../service/service-details.php?id=<?php echo $service['service_id']; ?>" class="service-link">zaaaa</a>
    </div>
</div>