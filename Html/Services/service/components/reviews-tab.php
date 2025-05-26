<?php
// reviews-tab.php - Atualizado para verificar contratos
?>
<div class="tab-pane fade" id="reviews" role="tabpanel">
    <div class="reviews-section">
        <h3>Avaliações deste Serviço</h3>

        <?php
        // Verificar se o usuário pode avaliar este serviço
        $canReview = false;
        $userContract = null;
        $reviewMessage = '';

        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
            
            // Verificar se já existe uma avaliação do usuário para este serviço
            $checkExistingReview = $pdo->prepare("
                SELECT r.review_id 
                FROM Reviews r
                INNER JOIN Contracts c ON r.contract_id = c.contract_id
                WHERE c.service_id = ? AND r.reviewer_id = ?
            ");
            $checkExistingReview->execute([$service_id, $userId]);
            $existingReview = $checkExistingReview->fetch();

            if (!$existingReview) {
                // Buscar contrato ativo ou concluído do usuário com este serviço
                $contractQuery = $pdo->prepare("
                    SELECT c.*, rp.restaurant_name
                    FROM Contracts c
                    INNER JOIN RestaurantProfiles rp ON c.restaurant_id = rp.restaurant_id
                    WHERE c.service_id = ? 
                    AND rp.user_id = ? 
                    AND c.status IN ('ativo', 'concluído')
                    ORDER BY c.created_at DESC
                    LIMIT 1
                ");
                $contractQuery->execute([$service_id, $userId]);
                $userContract = $contractQuery->fetch();

                if ($userContract) {
                    $canReview = true;
                    $reviewMessage = 'Você pode avaliar este serviço baseado no seu contrato: ' . htmlspecialchars($userContract['title']);
                } else {
                    $reviewMessage = 'Para avaliar este serviço, você precisa ter um contrato ativo ou concluído.';
                }
            } else {
                $reviewMessage = 'Você já avaliou este serviço.';
            }
        } else {
            $reviewMessage = 'Faça login para avaliar este serviço.';
        }
        ?>

        <!-- Formulário de avaliação (se permitido) -->
        <?php if ($canReview): ?>
            <div class="review-form-section">
                <h4>Avaliar este Serviço</h4>
                <p class="contract-info"><?php echo $reviewMessage; ?></p>
                
                <form method="POST" action="submit-review.php" class="review-form">
                    <input type="hidden" name="service_id" value="<?php echo $service_id; ?>">
                    <input type="hidden" name="contract_id" value="<?php echo $userContract['contract_id']; ?>">
                    <input type="hidden" name="reviewee_id" value="<?php echo $service['user_id']; ?>">
                    
                    <div class="rating-input">
                        <label>Sua Avaliação:</label>
                        <div class="star-rating">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" required>
                                <label for="star<?php echo $i; ?>" title="<?php echo $i; ?> estrelas">★</label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="comment-input">
                        <label for="comment">Comentário (opcional):</label>
                        <textarea name="comment" id="comment" rows="4" placeholder="Descreva sua experiência com este serviço..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn-submit-review">Enviar Avaliação</button>
                </form>
            </div>
        <?php else: ?>
            <div class="review-info">
                <p><?php echo $reviewMessage; ?></p>
            </div>
        <?php endif; ?>

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

<?php
// submit-review.php - Arquivo separado para processar o envio da avaliação
?>

<style>
.review-form-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.review-form-section h4 {
    color: #333;
    margin-bottom: 10px;
}

.contract-info {
    color: #666;
    font-size: 14px;
    margin-bottom: 20px;
}

.review-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.rating-input label {
    font-weight: 600;
    margin-bottom: 10px;
    display: block;
}

.star-rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 5px;
}

.star-rating input[type="radio"] {
    display: none;
}

.star-rating label {
    font-size: 30px;
    color: #ddd;
    cursor: pointer;
    transition: color 0.3s;
}

.star-rating label:hover,
.star-rating label:hover ~ label,
.star-rating input[type="radio"]:checked ~ label {
    color: #ffc107;
}

.comment-input label {
    font-weight: 600;
    margin-bottom: 8px;
    display: block;
}

.comment-input textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: inherit;
    resize: vertical;
}

.btn-submit-review {
    background: #007bff;
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.3s;
    align-self: flex-start;
}

.btn-submit-review:hover {
    background: #0056b3;
}

.review-info {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 30px;
}

.review-stats {
    margin-bottom: 30px;
}
</style>