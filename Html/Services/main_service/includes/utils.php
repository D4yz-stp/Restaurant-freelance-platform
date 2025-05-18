<?php
/**
 * Funções utilitárias para a aplicação
 */

/**
 * Formata a disponibilidade para exibição
 * 
 * @param string $availability String com disponibilidades separadas por vírgula
 * @return string Disponibilidades formatadas
 */
function formatAvailability($availability) {
    if (empty($availability)) {
        return 'Não especificado';
    }
    
    $availList = explode(',', $availability);
    $formatted = [];
    
    foreach ($availList as $avail) {
        $avail = trim($avail);
        if (isset(AVAILABILITY_MAP[$avail])) {
            $formatted[] = AVAILABILITY_MAP[$avail];
        } else {
            $formatted[] = $avail;
        }
    }
    
    return implode(', ', $formatted);
}

/**
 * Gera URLs para paginação e filtros
 * 
 * @param int|null $page Número da página
 * @param array $additionalParams Parâmetros adicionais para a URL
 * @return string URL formatada
 */
function buildQueryURL($page = null, $additionalParams = []) {
    $params = $_GET;
    
    if ($page !== null) {
        $params['page'] = $page;
    }
    
    // Adicionar parâmetros adicionais ou substituir existentes
    foreach ($additionalParams as $key => $value) {
        $params[$key] = $value;
    }
    
    return '?' . http_build_query($params);
}

/**
 * Trunca um texto até um comprimento específico
 * 
 * @param string $text Texto a ser truncado
 * @param int $maxLength Comprimento máximo
 * @return string Texto truncado
 */
function truncateText($text, $maxLength = 120) {
    if (strlen($text) > $maxLength) {
        return substr($text, 0, $maxLength - 3) . '...';
    }
    return $text;
}

/**
 * Formata um valor para exibição como moeda
 * 
 * @param float $value Valor a ser formatado
 * @return string Valor formatado como moeda
 */
function formatCurrency($value) {
    return number_format($value, 2, ',', '.');
}

/**
 * Obtém parâmetros de filtro da requisição
 * 
 * @return array Parâmetros de filtro
 */
function getFilterParams() {
    return [
        'experienceMin' => isset($_GET['exp_min']) ? intval($_GET['exp_min']) : 0,
        'priceMin' => isset($_GET['price_min']) && $_GET['price_min'] !== '' ? floatval($_GET['price_min']) : null,
        'priceMax' => isset($_GET['price_max']) && $_GET['price_max'] !== '' ? floatval($_GET['price_max']) : null,
        'availability' => isset($_GET['availability']) && is_array($_GET['availability']) ? $_GET['availability'] : [],
        'search' => isset($_GET['search']) ? trim($_GET['search']) : '',
        'sort' => isset($_GET['sort']) ? $_GET['sort'] : 'relevance',
        'page' => isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1
    ];
}

/**
 * Retorna o caminho da imagem ou um placeholder
 * 
 * @param string $imagePath Caminho da imagem
 * @param string $placeholder URL do placeholder
 * @return string URL da imagem ou placeholder
 */
function getImageUrl($imagePath, $placeholder = '/api/placeholder/600/400') {
    return !empty($imagePath) ? htmlspecialchars($imagePath) : $placeholder;
}

/**
 * Escapa texto para exibição segura em HTML
 * 
 * @param string $text Texto a ser escapado
 * @return string Texto escapado
 */
function safeHtml($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}