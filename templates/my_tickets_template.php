<?php
// templates/my-tickets.php
if (!defined('ABSPATH')) exit;

$current_user = wp_get_current_user();
?>

<div class="wrap">
    <h1>Meus Tickets</h1>
    
    <?php if (empty($tickets)): ?>
        <div class="ti-no-tickets">
            <div class="ti-no-tickets-icon">🎫</div>
            <h2>Você ainda não criou nenhum ticket</h2>
            <p>Quando precisar de suporte de TI, você pode criar um novo ticket.</p>
            <a href="<?php echo admin_url('admin.php?page=ti-new-ticket'); ?>" class="button button-primary button-large">
                Criar Primeiro Ticket
            </a>
        </div>
    <?php else: ?>
        
        <div class="ti-tickets-header">
            <div class="ti-tickets-summary">
                <p>Você tem <strong><?php echo count($tickets); ?></strong> tickets criados.</p>
            </div>
            <div class="ti-tickets-actions">
                <a href="<?php echo admin_url('admin.php?page=ti-new-ticket'); ?>" class="button button-primary">
                    Novo Ticket
                </a>
            </div>
        </div>
        
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
        
        <div class="ti-my-tickets-list">
            <?php foreach ($tickets as $ticket): ?>
                <?php
                global $wpdb;
                $table_comments = $wpdb->prefix . 'ti_ticket_comments';
                $comments_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_comments WHERE ticket_id = %d AND is_internal = 0",
                    $ticket->id
                ));
                $analyst = $ticket->assigned_to ? get_user_by('ID', $ticket->assigned_to) : null;
                ?>
                
                <div class="ti-ticket-item" data-status="<?php echo $ticket->status; ?>" data-priority="<?php echo $ticket->priority; ?>">
                    <div class="ti-ticket-item-header">
                        <div class="ti-ticket-item-id">
                            <strong>#<?php echo $ticket->id; ?></strong>
                        </div>
                        <div class="ti-ticket-item-date">
                            <?php echo date('d/m/Y H:i', strtotime($ticket->created_at)); ?>
                        </div>
                    </div>
                    
                    <div class="ti-ticket-item-content">
                        <h3 class="ti-ticket-item-title">
                            <?php echo esc_html($ticket->title); ?>
                        </h3>
                        
                        <div class="ti-ticket-item-meta">
                            <span class="ti-priority-badge" style="background-color: <?php echo ti_get_priority_color($ticket->priority); ?>">
                                <?php echo ti_get_priority_label($ticket->priority); ?>
                            </span>
                            
                            <span class="ti-status-badge" style="background-color: <?php echo ti_get_status_color($ticket->status); ?>">
                                <?php echo ti_get_status_label($ticket->status); ?>
                            </span>
                            
                            <?php if ($ticket->category): ?>
                            <span class="ti-category-badge">
                                <?php echo esc_html($ticket->category); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="ti-ticket-item-description">
                            <?php echo wp_trim_words(esc_html($ticket->description), 30); ?>
                        </div>
                        
                        <div class="ti-ticket-item-info">
                            <?php if ($analyst): ?>
                            <div class="ti-analyst-assigned">
                                <strong>Analista:</strong> <?php echo $analyst->display_name; ?>
                            </div>
                            <?php else: ?>
                            <div class="ti-analyst-assigned">
                                <strong>Status:</strong> Aguardando atribuição
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($comments_count > 0): ?>
                            <div class="ti-comments-count">
                                <?php echo $comments_count; ?> comentário(s)
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($ticket->updated_at !== $ticket->created_at): ?>
                            <div class="ti-last-update">
                                Última atualização: <?php echo date('d/m/Y H:i', strtotime($ticket->updated_at)); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="ti-ticket-item-actions">
                        <button class="button button-small ti-view-ticket-details" 
                                data-ticket-id="<?php echo $ticket->id; ?>">
                            Ver Detalhes
                        </button>
                    </div>
                </div>
                
            <?php endforeach; ?>
        </div>
        
    <?php endif; ?>
</div>

<!-- Modal para detalhes do ticket -->
<div id="ti-ticket-details-modal" class="ti-modal" style="display: none;">
    <div class="ti-modal-content">
        <div class="ti-modal-header">
            <h3>Ticket #<span id="ti-modal-ticket-id"></span> - <span id="ti-modal-ticket-title"></span></h3>
            <span class="ti-modal-close">&times;</span>
        </div>
        <div class="ti-modal-body">
            <div class="ti-ticket-detail-info">
                <div class="ti-detail-grid">
                    <div class="ti-detail-item">
                        <label>Status:</label>
                        <span id="ti-detail-status"></span>
                    </div>
                    <div class="ti-detail-item">
                        <label>Prioridade:</label>
                        <span id="ti-detail-priority"></span>
                    </div>
                    <div class="ti-detail-item">
                        <label>Categoria:</label>
                        <span id="ti-detail-category"></span>
                    </div>
                    <div class="ti-detail-item">
                        <label>Analista:</label>
                        <span id="ti-detail-analyst"></span>
                    </div>
                    <div class="ti-detail-item">
                        <label>Criado em:</label>
                        <span id="ti-detail-created"></span>
                    </div>
                    <div class="ti-detail-item">
                        <label>Atualizado em:</label>
                        <span id="ti-detail-updated"></span>
                    </div>
                </div>
                
                <div class="ti-detail-description">
                    <label>Descrição:</label>
                    <div id="ti-detail-description-content"></div>
                </div>
            </div>
            
            <div class="ti-ticket-comments-section">
                <h4>Comentários e Atualizações</h4>
                <div id="ti-ticket-comments"></div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Filtros
    $('#filter-status, #filter-priority').on('change', function() {
        var statusFilter = $('#filter-status').val();
        var priorityFilter = $('#filter-priority').val();
        
        $('.ti-ticket-item').each(function() {
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
    
    // Abrir modal de detalhes
    $('.ti-view-ticket-details').on('click', function() {
        var ticketId = $(this).data('ticket-id');
        loadTicketDetails(ticketId);
        $('#ti-ticket-details-modal').show();
    });
    
    // Fechar modal clicando fora
    $(window).on('click', function(e) {
        if ($(e.target).is('#ti-ticket-details-modal')) {
            $('#ti-ticket-details-modal').hide();
        }
    });
    
    function loadTicketDetails(ticketId) {
        $.post(ajaxurl, {
            action: 'get_ticket_details',
            ticket_id: ticketId,
            nonce: '<?php echo wp_create_nonce('ti_tickets_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                var ticket = response.data.ticket;
                var comments = response.data.comments || [];
                
                $('#ti-modal-ticket-id').text(ticket.id);
                $('#ti-modal-ticket-title').text(ticket.title);
                $('#ti-detail-status').html('<span class="ti-status-badge" style="background-color: ' + ticket.status_color + '">' + ticket.status_label + '</span>');
                $('#ti-detail-priority').html('<span class="ti-priority-badge" style="background-color: ' + ticket.priority_color + '">' + ticket.priority_label + '</span>');
                $('#ti-detail-category').text(ticket.category || 'N/A');
                $('#ti-detail-analyst').text(ticket.analyst_name || 'Não atribuído');
                $('#ti-detail-created').text(ticket.created_at);
                $('#ti-detail-updated').text(ticket.updated_at);
                $('#ti-detail-description-content').text(ticket.description);
                
                // Carregar comentários
                var commentsHtml = '';
                if (comments.length === 0) {
                    commentsHtml = '<p class="ti-no-comments">Nenhum comentário ainda.</p>';
                } else {
                    comments.forEach(function(comment) {
                        if (!comment.is_internal) { // Só mostra comentários não internos
                            commentsHtml += '<div class="ti-comment-item">';
                            commentsHtml += '<div class="ti-comment-header">';
                            commentsHtml += '<strong>' + comment.user_name + '</strong>';
                            commentsHtml += '<span class="ti-comment-date">' + comment.created_at + '</span>';
                            commentsHtml += '</div>';
                            commentsHtml += '<div class="ti-comment-text">' + comment.comment.replace(/\n/g, '<br>') + '</div>';
                            commentsHtml += '</div>';
                        }
                    });
                }
                $('#ti-ticket-comments').html(commentsHtml);
            } else {
                alert('Erro ao carregar detalhes do ticket.');
            }
        });
    }
});
</script> modal
    $('.ti-modal-close').on('click', function() {
        $('#ti-ticket-details-modal').hide();
    });
    
    // Fechar