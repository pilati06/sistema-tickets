<?php
// templates/new-ticket.php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1>Novo Ticket</h1>
    
    <form id="ti-new-ticket-form" class="ti-ticket-form">
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ticket-title">Título *</label>
                </th>
                <td>
                    <input type="text" id="ticket-title" name="title" class="regular-text" required>
                    <p class="description">Descreva brevemente o problema ou solicitação</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="ticket-category">Categoria</label>
                </th>
                <td>
                    <select id="ticket-category" name="category" class="regular-text">
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
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="ticket-priority">Prioridade *</label>
                </th>
                <td>
                    <select id="ticket-priority" name="priority" class="regular-text" required>
                        <option value="media">Média</option>
                        <option value="baixa">Baixa</option>
                        <option value="alta">Alta</option>
                        <option value="urgente">Urgente</option>
                    </select>
                    <p class="description">
                        <strong>Baixa:</strong> Solicitações não urgentes<br>
                        <strong>Média:</strong> Solicitações normais<br>
                        <strong>Alta:</strong> Problemas que impactam o trabalho<br>
                        <strong>Urgente:</strong> Problemas críticos que impedem o trabalho
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="ticket-description">Descrição *</label>
                </th>
                <td>
                    <textarea id="ticket-description" name="description" rows="8" class="large-text" required></textarea>
                    <p class="description">
                        Descreva detalhadamente o problema ou solicitação. Inclua:<br>
                        - Passos para reproduzir o problema (se aplicável)<br>
                        - Mensagens de erro<br>
                        - O que você estava fazendo quando ocorreu<br>
                        - Qualquer informação adicional relevante
                    </p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Criar Ticket">
            <span id="ti-form-loading" style="display: none;">Criando ticket...</span>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#ti-new-ticket-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = $('#submit');
        var loading = $('#ti-form-loading');
        
        // Desabilita o botão e mostra loading
        submitBtn.prop('disabled', true);
        loading.show();
        
        $.post(ajaxurl, {
            action: 'create_ticket',
            title: $('#ticket-title').val(),
            category: $('#ticket-category').val(),
            priority: $('#ticket-priority').val(),
            description: $('#ticket-description').val(),
            nonce: '<?php echo wp_create_nonce('ti_tickets_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                alert('Ticket criado com sucesso! Você receberá notificações por email sobre o andamento.');
                form[0].reset();
            } else {
                alert('Erro ao criar ticket: ' + response.data);
            }
        }).always(function() {
            submitBtn.prop('disabled', false);
            loading.hide();
        });
    });
});
</script>