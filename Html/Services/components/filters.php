<?php
/**
 * Componente de filtros
 * 
 * @param array $filterParams Parâmetros de filtro atuais
 */

// Extrair os parâmetros de filtro
extract($filterParams);
?>
<aside class="filters">
    <h3 class="filter-title" style="margin-top: 0;">Filtros</h3>
    
    <form action="" method="GET" id="filter-form">
        <!-- Mantém a pesquisa atual ao aplicar filtros -->
        <?php if (!empty($search)): ?>
            <input type="hidden" name="search" value="<?php echo safeHtml($search); ?>">
        <?php endif; ?>
        
        <!-- Mantém a ordenação atual ao aplicar filtros -->
        <?php if (!empty($sort)): ?>
            <input type="hidden" name="sort" value="<?php echo safeHtml($sort); ?>">
        <?php endif; ?>
        
        <div class="filter-section">
            <h4 class="filter-title">Experiência</h4>
            <input type="range" class="range-slider" name="exp_min" min="0" max="15" step="1" 
                   value="<?php echo $experienceMin; ?>" id="experience-slider">
            <div class="range-values">
                <span>0 anos</span>
                <span id="experience-value"><?php echo $experienceMin; ?> anos</span>
            </div>
        </div>
        
        <div class="filter-section">
            <h4 class="filter-title">Valor por hora</h4>
            <div class="price-inputs">
                <input type="number" name="price_min" placeholder="Min" id="price-min" min="0" 
                       value="<?php echo $priceMin !== null ? $priceMin : ''; ?>">
                <span>-</span>
                <input type="number" name="price_max" placeholder="Max" id="price-max" min="0" 
                       value="<?php echo $priceMax !== null ? $priceMax : ''; ?>">
            </div>
        </div>
        
        <div class="filter-section">
            <h4 class="filter-title">Horário disponível</h4>
            <div class="checkbox-group">
                <?php foreach (AVAILABILITY_MAP as $key => $label): ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="availability[]" value="<?php echo $key; ?>" 
                           <?php echo in_array($key, $availability) ? 'checked' : ''; ?>> <?php echo $label; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="filter-buttons">
            <button type="submit" class="filter-btn">Aplicar filtros</button>
            <a href="?" class="reset-filters-btn">Limpar filtros</a>
        </div>
    </form>
</aside>