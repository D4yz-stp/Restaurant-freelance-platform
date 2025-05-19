<!-- Aba de Habilidades e Especializações -->
<div class="tab-pane fade" id="skills" role="tabpanel">
    <!-- Habilidades relevantes para este serviço -->
    <?php if (!empty($skills)): ?>
        <div class="skills-section">
            <h3>Habilidades para este Serviço</h3>
            <div class="skills-list">
                <?php foreach ($skills as $skill): ?>
                    <div class="skill-item">
                        <span class="skill-name"><?php echo safeHtml($skill['skill_name']); ?></span>
                        <?php if (!empty($skill['proficiency_level'])): ?>
                            <span class="proficiency-level"><?php echo safeHtml($skill['proficiency_level']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <p>Não há habilidades específicas cadastradas para este serviço.</p>
    <?php endif; ?>
    
    <!-- Idiomas do prestador -->
    <?php if (!empty($languages)): ?>
        <div class="languages-section">
            <h3>Idiomas</h3>
            <div class="languages-list">
                <?php foreach ($languages as $language): ?>
                    <div class="language-item">
                        <span class="language-name"><?php echo safeHtml($language['language_name']); ?></span>
                        <?php if (!empty($language['proficiency'])): ?>
                            <span class="proficiency-level"><?php echo safeHtml($language['proficiency']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Especializações relevantes para este serviço -->
    <?php if (!empty($specializations)): ?>
        <div class="specializations-section">
            <h3>Especializações para <?php echo safeHtml($service['category_name']); ?></h3>
            
            <?php if (isset($specializations['chef'])): ?>
                <div class="specialization-group">
                    <h4>Especialização como Chef</h4>
                    <ul class="specialization-list">
                        <li><strong>Tipo de Culinária:</strong> <?php echo safeHtml($specializations['chef']['cuisine_type']); ?></li>
                        
                        <?php if (!empty($specializations['chef']['certifications'])): ?>
                            <li><strong>Certificações:</strong> <?php echo safeHtml($specializations['chef']['certifications']); ?></li>
                        <?php endif; ?>
                        
                        <?php if (!empty($specializations['chef']['dietary_specialties'])): ?>
                            <li><strong>Especialidades Dietéticas:</strong> <?php echo safeHtml($specializations['chef']['dietary_specialties']); ?></li>
                        <?php endif; ?>
                        
                        <li><strong>Planejamento de Menu:</strong> <?php echo $specializations['chef']['menu_planning'] ? 'Sim' : 'Não'; ?></li>
                        <li><strong>Experiência em Catering:</strong> <?php echo $specializations['chef']['catering_experience'] ? 'Sim' : 'Não'; ?></li>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (isset($specializations['cleaning'])): ?>
                <div class="specialization-group">
                    <h4>Especialização em Limpeza</h4>
                    <ul class="specialization-list">
                        <li><strong>Limpeza de Cozinha:</strong> <?php echo $specializations['cleaning']['kitchen_cleaning'] ? 'Sim' : 'Não'; ?></li>
                        <li><strong>Limpeza de Área de Jantar:</strong> <?php echo $specializations['cleaning']['dining_area_cleaning'] ? 'Sim' : 'Não'; ?></li>
                        
                        <?php if (!empty($specializations['cleaning']['equipment_experience'])): ?>
                            <li><strong>Experiência com Equipamentos:</strong> <?php echo safeHtml($specializations['cleaning']['equipment_experience']); ?></li>
                        <?php endif; ?>
                        
                        <li><strong>Métodos Ecológicos:</strong> <?php echo $specializations['cleaning']['eco_friendly'] ? 'Sim' : 'Não'; ?></li>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (isset($specializations['bartender'])): ?>
                <div class="specialization-group">
                    <h4>Especialização como Bartender</h4>
                    <ul class="specialization-list">
                        <li><strong>Especialista em Coquetéis:</strong> <?php echo $specializations['bartender']['cocktail_specialist'] ? 'Sim' : 'Não'; ?></li>
                        <li><strong>Conhecimento em Vinhos:</strong> <?php echo $specializations['bartender']['wine_knowledge'] ? 'Sim' : 'Não'; ?></li>
                        <li><strong>Conhecimento em Cervejas:</strong> <?php echo $specializations['bartender']['beer_knowledge'] ? 'Sim' : 'Não'; ?></li>
                        <li><strong>Flair Bartending:</strong> <?php echo $specializations['bartender']['flair_bartending'] ? 'Sim' : 'Não'; ?></li>
                        
                        <?php if (!empty($specializations['bartender']['certifications'])): ?>
                            <li><strong>Certificações:</strong> <?php echo safeHtml($specializations['bartender']['certifications']); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (isset($specializations['service_staff'])): ?>
                <div class="specialization-group">
                    <h4>Especialização em Atendimento</h4>
                    <ul class="specialization-list">
                        <li><strong>Serviço de mesa:</strong> <?php echo $specializations['service_staff']['table_service'] ? 'Sim' : 'Não'; ?></li>
                        <li><strong>Experiência em eventos:</strong> <?php echo $specializations['service_staff']['event_experience'] ? 'Sim' : 'Não'; ?></li>
                        
                        <?php if (!empty($specializations['service_staff']['serving_style'])): ?>
                            <li><strong>Estilo de Serviço:</strong> <?php echo safeHtml($specializations['service_staff']['serving_style']); ?></li>
                        <?php endif; ?>
                        
                        <li><strong>Conhecimento de Etiqueta:</strong> <?php echo $specializations['service_staff']['etiquette_knowledge'] ? 'Sim' : 'Não'; ?></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p>Não há especializações registradas para este tipo de serviço.</p>
    <?php endif; ?>
</div>