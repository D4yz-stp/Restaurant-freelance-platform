<!-- reviews-tab.php -->
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
