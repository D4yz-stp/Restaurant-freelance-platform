<?php
// Este arquivo deve ser incluído no topo do register.html usando PHP
session_start();

// Verificar se há erros na sessão
$errors = $_SESSION['errors'] ?? null;
$success = $_SESSION['success'] ?? null;
$form_data = $_SESSION['form_data'] ?? null;

// Limpar variáveis de sessão depois de usá-las
unset($_SESSION['errors']);
unset($_SESSION['success']);
unset($_SESSION['form_data']);

// Se houver mensagens, redirecionar para register.html com os parâmetros na URL
if ($errors || $success || $form_data) {
    $params = [];
    
    if ($errors) {
        $params['errors'] = urlencode(json_encode($errors));
    }
    
    if ($success) {
        $params['success'] = urlencode($success);
    }
    
    if ($form_data) {
        $params['formData'] = urlencode(json_encode($form_data));
    }
    
    // Construir URL de redirecionamento
    $redirect_url = 'register.html';
    if (!empty($params)) {
        $redirect_url .= '?' . http_build_query($params);
    }
    
    header("Location: $redirect_url");
    exit;
}
?>