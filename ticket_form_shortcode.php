<?php
// templates/ticket-form.php
if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    echo '<p>Você precisa estar logado para criar um ticket. <a href="' . wp_login_url(get_permalink()) . '">Fazer login</a></p>';
    return;
}
?>

<div class="ti-ticket-form-container">
    <h3>Abrir Novo Ticket</h3>
    
    <form id="ti-frontend-ticket-form" class="ti-frontend-form">
        <div class="ti-form-row">
            <label for="fe-ticket-title">Título *</label>
            <input type="text" id="fe-ticket-title" name="title" required>
        </div>
        
        <div class="ti-form-row">
            <label for="fe-ticket-category">Categoria</label>
            <select id="fe-ticket-category" name="category">
                <option value="">Selecionar categoria</option>
                <option value="hardware">Hardware</option>
                <option value="software">Software</option>
                <option value="rede">Rede/Internet</option>
                <option value="email">E-mail</option>
                <option value="sistema">Sistema</option>
                <option value="desenvolvimento">Desenvolvimento</option>
                <option value="manutencao">Manutenção</option>
                <option value="outro">Outro</option>
            </select>
        </div>
        
        <div class="ti-form-row">
            <label for="fe-ticket-priority">Prioridade *</label>
            <select id="fe-ticket-priority" name="priority" required>
                <option value="media">Média</option>
                <option value="baixa">Baixa</option>
                <option value="alta">Alta</option>
                <option value="urgente">Urgente</option>
            </select>
            <small>
                <strong>Baixa:</strong> Não urgente | 
                <strong>Média:</strong> Normal | 
                <strong>Alta:</strong> Impacta trabalho | 
                <strong>Urgente:</strong> Crítico
            </small>
        </div>
        
        <div class="ti-form-row">
            <label for="fe-ticket-description">Descrição *</label>
            <textarea id="fe-ticket-description" name="description" rows="6" required placeholder="Descreva detalhadamente o problema ou solicitação..."></textarea>
        </div>
        
        <div class="ti-form-row">
            <button type="submit" class="ti-btn ti-btn-primary">
                <span class="ti-btn-text">Criar Ticket</span>
                <span class="ti-btn-loading" style="display: none;">Criando...</span>
            </button>
        </div>
        
        <div id="ti-form-message" class="ti-form-message" style="display: none;"></div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#ti-frontend-ticket-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var btn = form.find('button[type="submit"]');
        var btnText = btn.find('.ti-btn-text');
        var btnLoading = btn.find('.ti-btn-loading');
        var message = $('#ti-form-message');
        
        // Estado de loading
        btn.prop('disabled', true);
        btnText.hide();
        btnLoading.show();
        message.hide();
        
        $.post(ti_tickets_ajax.ajax_url, {
            action: 'create_ticket',
            title: $('#fe-ticket-title').val(),
            category: $('#fe-ticket-category').val(),
            priority: $('#fe-ticket-priority').val(),
            description: $('#fe-ticket-description').val(),
            nonce: ti_tickets_ajax.nonce
        }, function(response) {
            if (response.success) {
                message.removeClass('ti-error').addClass('ti-success')
                       .html('✓ Ticket criado com sucesso! Você receberá notificações por email sobre o andamento.')
                       .show();
                form[0].reset();
            } else {
                message.removeClass('ti-success').addClass('ti-error')
                       .html('✗ Erro ao criar ticket: ' + response.data)
                       .show();
            }
        }).fail(function() {
            message.removeClass('ti-success').addClass('ti-error')
                   .html('✗ Erro de conexão. Tente novamente.')
                   .show();
        }).always(function() {
            btn.prop('disabled', false);
            btnText.show();
            btnLoading.hide();
        });
    });
});
</script>