<?php
/**
 * Componente cabeçalho de serviços com opções de ordenação
 * 
 * @param int $totalCount Total de serviços encontrados
 * @param string $sort Critério de ordenação atual
 */
?>
<div class="services-header">
    <div class="found-count">
        <strong><?php echo $totalCount; ?></strong> serviços encontrados
    </div>
    
    <div class="sort-dropdown">
        <div class="sort-button">
            Ordenar por: <strong>
                <?php echo SORT_LABELS[$sort] ?? 'Relevância'; ?>
            </strong>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="sort-dropdown-content">
            <?php foreach (SORT_LABELS as $sortKey => $sortLabel): ?>
            <a href="<?php echo buildQueryURL(1, ['sort' => $sortKey]); ?>" 
               class="sort-option <?php echo $sort === $sortKey ? 'active' : ''; ?>">
                <?php echo $sortLabel; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>