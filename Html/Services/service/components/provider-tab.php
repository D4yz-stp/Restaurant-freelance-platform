<!-- provider-tab.php -->
<div class="tab-pane fade" id="provider" role="tabpanel">
    <div class="provider-section">
        <div class="provider-profile-detailed">
            <div class="provider-header">
                        <span class="provider-experience">
                            <i class="fas fa-briefcase"></i> <?php echo $service['experience_years']; ?> anos de experiência
                        </span>
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
                            $otherServicePrice = formatCurrency($otherService['base_price']);
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
                                            <span><?php echo $otherServicePrice; ?></span>
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
