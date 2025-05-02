document.addEventListener('DOMContentLoaded', () => {
  fetch('../../Php/verPerfil.php')
    .then(response => response.json())
    .then(data => {
      document.getElementById('nome').textContent = `${data.first_name} ${data.last_name}`;
      document.getElementById('email').textContent = data.email;
      document.getElementById('profissao').textContent = data.profissao;
      document.getElementById('localizacao').textContent = data.localizacao || 'Não definida';
    })
    .catch(error => console.error('Erro ao carregar perfil:', error));
});
