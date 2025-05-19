<?php
/**
 * Componente de paginação
 * 
 * @param int $page Página atual
 * @param int $totalPages Total de páginas
 */
?>
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