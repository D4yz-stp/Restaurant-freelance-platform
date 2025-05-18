<?php
/**
 * Funções de utilidade para o sistema de freelancers
 */

/**
 * Sanitiza strings para evitar ataques XSS
 *
 * @param string $str String a ser sanitizada
 * @return string String sanitizada
 */
function safeHtml($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Trunca uma string para o comprimento especificado
 *
 * @param string $text Texto a ser truncado
 * @param int $length Comprimento máximo
 * @param string $append String a ser anexada ao final do texto truncado
 * @return string Texto truncado
 */
function truncateText($text, $length = 100, $append = '...') {
    $text = strip_tags($text);
    if (strlen($text) > $length) {
        $text = substr($text, 0, $length);
        $text = substr($text, 0, strrpos($text, ' '));
        $text .= $append;
    }
    return $text;
}

/**
 * Formata datas para o formato brasileiro
 *
 * @param string $date Data no formato MySQL (YYYY-MM-DD)
 * @return string Data no formato DD/MM/YYYY
 */
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

/**
 * Verifica se o usuário está logado
 *
 * @return bool True se o usuário estiver logado, False caso contrário
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Verifica se o usuário tem o papel especificado
 *
 * @param string $role Papel a ser verificado ('freelancer', 'restaurant', 'admin')
 * @return bool True se o usuário tiver o papel, False caso contrário
 */
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == $role;
}

/**
 * Gera um token CSRF para proteção contra ataques
 *
 * @return string Token CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica se o token CSRF é válido
 *
 * @param string $token Token a ser verificado
 * @return bool True se o token for válido, False caso contrário
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Redireciona o usuário para uma URL com uma mensagem flash
 *
 * @param string $url URL para redirecionamento
 * @param string $message Mensagem a ser exibida
 * @param string $type Tipo de mensagem ('success', 'danger', 'warning', 'info')
 */
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit();
}

/**
 * Exibe mensagens flash
 *
 * @return string HTML com a mensagem flash, se existir
 */
function displayFlashMessages() {
    $output = '';
    if (isset($_SESSION['flash_message'])) {
        $type = isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : 'success';
        $output = '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
        $output .= $_SESSION['flash_message'];
        $output .= '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
        $output .= '<span aria-hidden="true">&times;</span></button></div>';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
    return $output;
}

/**
 * Calcula tempo decorrido em formato legível
 *
 * @param string $datetime Data e hora no formato MySQL
 * @return string Tempo decorrido em formato legível
 */
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) {
        return $diff->y . ' ano' . ($diff->y > 1 ? 's' : '') . ' atrás';
    }
    if ($diff->m > 0) {
        return $diff->m . ' mês' . ($diff->m > 1 ? 'es' : '') . ' atrás';
    }
    if ($diff->d > 0) {
        return $diff->d . ' dia' . ($diff->d > 1 ? 's' : '') . ' atrás';
    }
    if ($diff->h > 0) {
        return $diff->h . ' hora' . ($diff->h > 1 ? 's' : '') . ' atrás';
    }
    if ($diff->i > 0) {
        return $diff->i . ' minuto' . ($diff->i > 1 ? 's' : '') . ' atrás';
    }
    
    return 'agora mesmo';
}

/**
 * Gera uma URL amigável (slug) a partir de um texto
 *
 * @param string $text Texto para gerar o slug
 * @return string Slug gerado
 */
function generateSlug($text) {
    // Remove acentos
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    
    // Converte para minúsculas
    $text = strtolower($text);
    
    // Remove caracteres especiais
    $text = preg_replace('/[^a-z0-9\s]/', '', $text);
    
    // Substitui espaços por hífens
    $text = preg_replace('/\s+/', '-', $text);
    
    // Remove hífens duplicados
    $text = preg_replace('/-+/', '-', $text);
    
    // Remove hífens no início e fim
    $text = trim($text, '-');
    
    return $text;
}

/**
 * Verifica se o email é válido
 *
 * @param string $email Email a ser verificado
 * @return bool True se o email for válido, False caso contrário
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validação de força da senha
 *
 * @param string $password Senha a ser validada
 * @return bool True se a senha for forte, False caso contrário
 */
function isStrongPassword($password) {
    // Pelo menos 8 caracteres, 1 letra maiúscula, 1 número e 1 caractere especial
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password);
}

/**
 * Formata valores monetários para o padrão brasileiro
 *
 * @param float $value Valor a ser formatado
 * @return string Valor formatado
 */
function formatCurrency($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}