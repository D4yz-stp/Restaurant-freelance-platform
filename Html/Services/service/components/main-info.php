<!-- service-main-info.php -->
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
                <span class="price-value">  <?php echo $price; ?>   </span>
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
