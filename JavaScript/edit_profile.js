document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('perfilForm');
    const userData = JSON.parse(localStorage.getItem('user')) || {};
  
    document.getElementById('nome').value = userData.nome || '';
    document.getElementById('email').value = userData.email || '';
    document.getElementById('profissao').value = userData.profissao || '';
    document.getElementById('localizacao').value = userData.localizacao || '';
  
    form.addEventListener('submit', function (e) {
      e.preventDefault();
  
      const updatedData = {
        nome: document.getElementById('nome').value,
        email: document.getElementById('email').value,
        profissao: document.getElementById('profissao').value,
        localizacao: document.getElementById('localizacao').value
      };
  
      localStorage.setItem('user', JSON.stringify(updatedData));
      alert('Dados atualizados com sucesso!');
      window.location.href = 'perfil.html';
    });
  });
  