document.addEventListener('DOMContentLoaded', () => {
    const userData = JSON.parse(localStorage.getItem('user')) || {
      nome: 'João Oliveira',
      email: 'joao@email.com',
      profissao: 'Chef de cozinha',
      localizacao: 'Albufeira, Algarve'
    };
  
    document.getElementById('nome').innerText = userData.nome;
    document.getElementById('email').innerText = userData.email;
    document.getElementById('profissao').innerText = userData.profissao;
    document.getElementById('localizacao').innerText = userData.localizacao;
  });
  