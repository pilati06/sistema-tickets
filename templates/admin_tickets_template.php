<?php
// templates/admin-tickets.php
if (!defined('ABSPATH')) exit;

$can_manage = current_user_can('manage_ti_tickets');
$analysts = get_users(array('role' => 'ti_analyst'));
?>

<div class="wrap">
    <h1>Sistema de Tickets TI</h1>
    
    <?php if ($can_manage): ?>
    <div class="ti-stats-cards">
        <?php
        global $wpdb;
        $table_tickets = $wpdb->prefix . 'ti_tickets';
        $stats = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets"),
            'aberto' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE status = 'aberto'"),
            'em_andamento' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE status = 'em_andamento'"),
            'concluido' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE status = 'concluido'")
        );
        ?>
        <div class="ti-stat-card">
            <h3><?php echo $stats['total']; ?></h3>
            <p>Total de Tickets</p>
        </div>
        <div class="ti-stat-card ti-stat-open">
            <h3><?php echo $stats['aberto']; ?></h3>
            <p>Abertos</p>
        </div>
        <div class="ti-stat-card ti-stat-progress">
            <h3><?php echo $stats['em_andamento']; ?></h3>
            <p>Em Andamento</p>
        </div>
        <div class="ti-stat-card ti-stat-closed">
            <h3><?php echo $stats['concluido']; ?></h3>
            <p>Concluídos</p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="ti-tickets-filters">
        <select id="filter-status">
            <option value="">Todos os Status</option>
            <option value="aberto">Aberto</option>
            <option value="em_andamento">Em Andamento</option>
            <option value="aguardando_teste">Aguardando Teste</option>
            <option value="concluido">Concluído</option>
            <option value="cancelado">Cancelado</option>
        </select>
        
        <select id="filter-priority">
            <option value="">Todas as Prioridades</option>
            <option value="baixa">Baixa</option>
            <option value="media">Média</option>
            <option value="alta">Alta</option>
            <option value="urgente">Urgente</option>
        </select>
    </div>
    
    <table class="wp-list-table widefat fixed striped ti-tickets-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Título</th>
                <th>Solicitante</th>
                <th>Prioridade</th>
                <th>Status</th>
                <th>Analista</th>
                <th>Data</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $ticket): ?>
            <?php
            $requester = get_user_by('ID', $ticket->requester_id);
            $analyst = $ticket->assigned_to ? get_user_by('ID', $ticket->assigned_to) : null;
            ?>
            <tr data-status="<?php echo $ticket->status; ?>" data-priority="<?php echo $ticket->priority; ?>">
                <td>#<?php echo $ticket->id; ?></td>
                <td>
                    <strong><?php echo esc_html($ticket->title); ?></strong>
                    <div class="ti-ticket-description">
                        <?php echo wp_trim_words(esc_html($ticket->description), 15); ?>
                    </div>
                </td>
                <td><?php echo $requester ? $requester->display_name : 'N/A'; ?></td>
                <td>
                    <span class="ti-priority-badge" style="background-color: <?php echo ti_get_priority_color($ticket->priority); ?>">
                        <?php echo ti_get_priority_label($ticket->priority); ?>
                    </span>
                </td>
                <td>
                    <span class="ti-status-badge" style="background-color: <?php echo ti_get_status_color($ticket->status); ?>">
                        <?php echo ti_get_status_label($ticket->status); ?>
                    </span>
                </td>
                <td><?php echo $analyst ? $analyst->display_name : 'Não atribuído'; ?></td>
                <td><?php echo date('d/m/Y H:i', strtotime($ticket->created_at)); ?></td>
                <td>
                    <button class="button button-small ti-view-ticket" data-ticket-id="<?php echo $ticket->id; ?>">
                        Ver
                    </button>
                    <?php if ($can_manage): ?>
                    <button class="button button-small ti-edit-ticket" data-ticket-id="<?php echo $ticket->id; ?>">
                        Editar
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal para visualizar/editar ticket -->
<div id="ti-ticket-modal" class="ti-modal" style="display: none;">
    <div class="ti-modal-content">
        <div class="ti-modal-header">
            <h2 id="ti-modal-title">Ticket #<span id="ti-modal-ticket-id"></span></h2>
            <span class="ti-modal-close">&times;</span>
        </div>
        <div class="ti-modal-body">
            <div class="ti-ticket-details">
                <div class="ti-detail-row">
                    <label>Título:</label>
                    <span id="ti-detail-title"></span>
                </div>
                <div class="ti-detail-row">
                    <label>Solicitante:</label>
                    <span id="ti-detail-requester"></span>
                </div>
                <div class="ti-detail-row">
                    <label>Prioridade:</label>
                    <span id="ti-detail-priority"></span>
                </div>
                <div class="ti-detail-row">
                    <label>Status:</label>
                    <span id="ti-detail-status"></span>
                </div>
                <div class="ti-detail-row">
                    <label>Analista:</label>
                    <span id="ti-detail-analyst"></span>
                </div>
                <div class="ti-detail-row">
                    <label>Categoria:</label>
                    <span id="ti-detail-category"></span>
                </div>
                <div class="ti-detail-row">
                    <label>Descrição:</label>
                    <div id="ti-detail-description"></div>
                </div>
            </div>
            
            <?php if ($can_manage): ?>
            <div class="ti-ticket-actions">
                <h3>Ações</h3>
                <div class="ti-action-row">
                    <label for="ti-new-status">Alterar Status:</label>
                    <select id="ti-new-status">
                        <option value="aberto">Aberto</option>
                        <option value="em_andamento">Em Andamento</option>
                        <option value="aguardando_teste">Aguardando Teste</option>
                        <option value="concluido">Concluído</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
                <div class="ti-action-row">
                    <label for="ti-assign-analyst">Atribuir Analista:</label>
                    <select id="ti-assign-analyst">
                        <option value="">Selecionar analista</option>
                        <?php foreach ($analysts as $analyst): ?>
                        <option value="<?php echo $analyst->ID; ?>"><?php echo $analyst->display_name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="button button-primary" id="ti-update-ticket">Atualizar Ticket</button>
            </div>
            <?php endif; ?>
            
            <div class="ti-ticket-comments">
                <h3>Comentários</h3>
                <div id="ti-comments-list"></div>
                
                <?php if (current_user_can('comment_on_tickets') || current_user_can('manage_ti_tickets')): ?>
                <div class="ti-add-comment">
                    <textarea id="ti-new-comment" placeholder="Adicionar comentário..."></textarea>
                    <div class="ti-comment-options">
                        <?php if ($can_manage): ?>
                        <label>
                            <input type="checkbox" id="ti-internal-comment"> Comentário interno
                        </label>
                        <?php endif; ?>
                        <button class="button button-primary" id="ti-add-comment">Adicionar Comentário</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var currentTicketId = null;
    
    // Abrir modal
    $('.ti-view-ticket, .ti-edit-ticket').on('click', function() {
        var ticketId = $(this).data('ticket-id');
        currentTicketId = ticketId;
        loadTicketDetails(ticketId);
        $('#ti-ticket-modal').show();
    });
    
    // Fechar modal
    $('.ti-modal-close').on('click', function() {
        $('#ti-ticket-modal').hide();
    });
    
    // Filtros
    $('#filter-status, #filter-priority').on('change', function() {
        var statusFilter = $('#filter-status').val();
        var priorityFilter = $('#filter-priority').val();
        
        $('.ti-tickets-table tbody tr').each(function() {
            var show = true;
            
            if (statusFilter && $(this).data('status') !== statusFilter) {
                show = false;
            }
            
            if (priorityFilter && $(this).data('priority') !== priorityFilter) {
                show = false;
            }
            
            $(this).toggle(show);
        });
    });
    
    // Atualizar ticket
    $('#ti-update-ticket').on('click', function() {
        var newStatus = $('#ti-new-status').val();
        var assignedTo = $('#ti-assign-analyst').val();
        
        $.post(ajaxurl, {
            action: 'update_ticket_status',
            ticket_id: currentTicketId,
            status: newStatus,
            assigned_to: assignedTo,
            nonce: '<?php echo wp_create_nonce('ti_tickets_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                alert('Ticket atualizado com sucesso!');
                location.reload();
            } else {
                alert('Erro ao atualizar ticket: ' + response.data);
            }
        });
    });
    
    // Adicionar comentário
    $('#ti-add-comment').on('click', function() {
        var comment = $('#ti-new-comment').val();
        var isInternal = $('#ti-internal-comment').is(':checked') ? 1 : 0;
        
        if (!comment.trim()) {
            alert('Por favor, digite um comentário.');
            return;
        }
        
        $.post(ajaxurl, {
            action: 'add_ticket_comment',
            ticket_id: currentTicketId,
            comment: comment,
            is_internal: isInternal,
            nonce: '<?php echo wp_create_nonce('ti_tickets_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $('#ti-new-comment').val('');
                $('#ti-internal-comment').prop('checked', false);
                loadComments(currentTicketId);
            } else {
                alert('Erro ao adicionar comentário: ' + response.data);
            }
        });
    });
    
    function loadTicketDetails(ticketId) {
        $.post(ajaxurl, {
            action: 'get_ticket_details',
            ticket_id: ticketId,
            nonce: '<?php echo wp_create_nonce('ti_tickets_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                var ticket = response.data.ticket;
                $('#ti-modal-ticket-id').text(ticket.id);
                $('#ti-detail-title').text(ticket.title);
                $('#ti-detail-requester').text(ticket.requester_name);
                $('#ti-detail-priority').html('<span class="ti-priority-badge" style="background-color: ' + ticket.priority_color + '">' + ticket.priority_label + '</span>');
                $('#ti-detail-status').html('<span class="ti-status-badge" style="background-color: ' + ticket.status_color + '">' + ticket.status_label + '</span>');
                $('#ti-detail-analyst').text(ticket.analyst_name || 'Não atribuído');
                $('#ti-detail-category').text(ticket.category || 'N/A');
                $('#ti-detail-description').text(ticket.description);
                
                $('#ti-new-status').val(ticket.status);
                $('#ti-assign-analyst').val(ticket.assigned_to || '');
                
                loadComments(ticketId);
            }
        });
    }
    
    function loadComments(ticketId) {
        $.post(ajaxurl, {
            action: 'get_ticket_comments',
            ticket_id: ticketId,
            nonce: '<?php echo wp_create_nonce('ti_tickets_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                var commentsHtml = '';
                response.data.comments.forEach(function(comment) {
                    var internalBadge = comment.is_internal ? '<span class="ti-internal-badge">Interno</span>' : '';
                    commentsHtml += '<div class="ti-comment">';
                    commentsHtml += '<div class="ti-comment-header">';
                    commentsHtml += '<strong>' + comment.user_name + '</strong>';
                    commentsHtml += '<span class="ti-comment-date">' + comment.created_at + '</span>';
                    commentsHtml += internalBadge;
                    commentsHtml += '</div>';
                    commentsHtml += '<div class="ti-comment-content">' + comment.comment + '</div>';
                    commentsHtml += '</div>';
                });
                $('#ti-comments-list').html(commentsHtml);
            }
        });
    }
});
</script>