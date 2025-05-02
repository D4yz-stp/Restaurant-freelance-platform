document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const step3Freelancer = document.getElementById('step3-freelancer');
    const step3Manager = document.getElementById('step3-manager');
    
    const nextToRoleBtn = document.getElementById('nextToRoleBtn');
    const backToBasicBtn = document.getElementById('backToBasicBtn');
    const nextToDetailsBtn = document.getElementById('nextToDetailsBtn');
    const freelancerBackBtn = document.getElementById('freelancerBackBtn');
    const managerBackBtn = document.getElementById('managerBackBtn');
    
    const roleOptions = document.querySelectorAll('.role-option');
    const basicInfoForm = document.getElementById('basicInfoForm');
    const freelancerForm = document.getElementById('freelancerForm');
    const managerForm = document.getElementById('managerForm');
    
    const errorContainer = document.getElementById('error-container');
    const successContainer = document.getElementById('success-container');
    
    let selectedRole = null;
    
    // Verificar se há mensagens de erro na sessão (via URL params)
    checkSessionMessages();
    
    // Step 1 to Step 2
    nextToRoleBtn.addEventListener('click', function() {
        // Basic validation
        const requiredFields = basicInfoForm.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = 'red';
            } else {
                field.style.borderColor = '#ccc';
            }
        });
        
        if (isValid) {
            step1.classList.remove('active');
            step2.classList.add('active');
        }
    });
    
    // Step 2 to Step 1
    backToBasicBtn.addEventListener('click', function() {
        step2.classList.remove('active');
        step1.classList.add('active');
    });
    
    // Role selection
    roleOptions.forEach(option => {
        option.addEventListener('click', function() {
            selectedRole = this.getAttribute('data-role');
            
            // Reset all options and select this one
            roleOptions.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            
            // Enable continue button
            nextToDetailsBtn.removeAttribute('disabled');
        });
    });
    
    // Step 2 to Step 3
    nextToDetailsBtn.addEventListener('click', function() {
        step2.classList.remove('active');
        
        if (selectedRole === 'freelancer') {
            step3Freelancer.classList.add('active');
        } else if (selectedRole === 'manager') {
            step3Manager.classList.add('active');
        }
    });
    
    // Step 3 Freelancer to Step 2
    freelancerBackBtn.addEventListener('click', function() {
        step3Freelancer.classList.remove('active');
        step2.classList.add('active');
    });
    
    // Step 3 Manager to Step 2
    managerBackBtn.addEventListener('click', function() {
        step3Manager.classList.remove('active');
        step2.classList.add('active');
    });
    
    // Form submissions
    freelancerForm.addEventListener('submit', function(e) {
        e.preventDefault();
        submitFullForm('freelancer');
    });
    
    managerForm.addEventListener('submit', function(e) {
        e.preventDefault();
        submitFullForm('manager');
    });
    
    function submitFullForm(role) {
        // Create a hidden form to submit all data
        const fullForm = document.createElement('form');
        fullForm.method = 'POST';
        fullForm.action = '../../Php/register.php'; // Apontando para register.php em vez de login.php
        fullForm.style.display = 'none';
        
        // Add basic info fields
        const basicFields = basicInfoForm.querySelectorAll('input');
        basicFields.forEach(field => {
            const input = document.createElement('input');
            input.name = field.name;
            input.value = field.value;
            fullForm.appendChild(input);
        });
        
        // Add role
        const roleInput = document.createElement('input');
        roleInput.name = 'role';
        roleInput.value = role;
        fullForm.appendChild(roleInput);
        
        // Add role-specific fields
        const specificForm = role === 'freelancer' ? freelancerForm : managerForm;
        const specificFields = specificForm.querySelectorAll('input, select, textarea');
        specificFields.forEach(field => {
            if (field.name !== 'role') { // Avoid duplicate role field
                const input = document.createElement('input');
                input.name = field.name;
                input.value = field.value;
                fullForm.appendChild(input);
            }
        });
        
        // Append to body and submit
        document.body.appendChild(fullForm);
        fullForm.submit();
    }
    
    // Função para verificar e exibir mensagens de erro/sucesso da sessão
    function checkSessionMessages() {
        // Verificar se foram passados parâmetros de erro ou sucesso na URL
        const urlParams = new URLSearchParams(window.location.search);
        
        // Se houver erros
        if (urlParams.has('errors')) {
            const errors = JSON.parse(decodeURIComponent(urlParams.get('errors')));
            if (errors && errors.length > 0) {
                errorContainer.innerHTML = errors.join('<br>');
                errorContainer.style.display = 'block';
            }
        }
        
        // Se houver mensagem de sucesso
        if (urlParams.has('success')) {
            const successMsg = decodeURIComponent(urlParams.get('success'));
            successContainer.innerHTML = successMsg;
            successContainer.style.display = 'block';
        }
        
        // Se houver dados de formulário salvos, preencher o formulário
        if (urlParams.has('formData')) {
            try {
                const formData = JSON.parse(decodeURIComponent(urlParams.get('formData')));
                
                // Preencher campos básicos
                if (formData.first_name) document.getElementById('first_name').value = formData.first_name;
                if (formData.last_name) document.getElementById('last_name').value = formData.last_name;
                if (formData.email) document.getElementById('email').value = formData.email;
                // Não preenchemos a senha por segurança
                if (formData.contact) document.getElementById('contact').value = formData.contact;
                if (formData.country) document.getElementById('country').value = formData.country;
                if (formData.city) document.getElementById('city').value = formData.city;
                
                // Se tiver role, avançar para a tela correta
                if (formData.role) {
                    selectedRole = formData.role;
                    
                    // Simular clique nos botões de navegação
                    nextToRoleBtn.click();
                    
                    // Selecionar role
                    const roleOption = document.querySelector(`.role-option[data-role="${selectedRole}"]`);
                    if (roleOption) {
                        roleOption.click();
                        nextToDetailsBtn.click();
                        
                        // Preencher formulário específico
                        if (selectedRole === 'freelancer') {
                            if (formData.hourly_rate) document.getElementById('hourly_rate').value = formData.hourly_rate;
                            if (formData.availability) document.getElementById('availability').value = formData.availability;
                            if (formData.experience_years) document.getElementById('experience_years').value = formData.experience_years;
                        } else if (selectedRole === 'manager') {
                            if (formData.restaurant_name) document.getElementById('restaurant_name').value = formData.restaurant_name;
                            if (formData.restaurant_type) document.getElementById('restaurant_type').value = formData.restaurant_type;
                            if (formData.description) document.getElementById('description').value = formData.description;
                        }
                    }
                }
            } catch (e) {
                console.error("Erro ao processar dados do formulário:", e);
            }
        }
    }
});