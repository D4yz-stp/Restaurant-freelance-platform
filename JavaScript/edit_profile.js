document.getElementById('perfilForm').addEventListener('submit', function (e) {
  e.preventDefault();

  const nome = document.getElementById('nome').value;
  const email = document.getElementById('email').value;
  const localizacao = document.getElementById('localizacao').value;

  fetch('../../Php/editarPerfil.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ nome, email, localizacao })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert('Perfil atualizado com sucesso!');
        window.location.href = 'ver-perfil.html';
      } else {
        alert('Erro: ' + data.error);
      }
    })
    .catch(error => console.error('Erro:', error));
});
