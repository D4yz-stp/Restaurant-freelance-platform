<!-- contact-modal.php -->
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
