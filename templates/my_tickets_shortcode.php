<?php
// templates/my-tickets-shortcode.php
if (!defined('ABSPATH')) exit;
?>

<div class="ti-my-tickets-container">
    <h3>Meus Tickets</h3>
    
    <?php if (empty($tickets)): ?>
        <div class="ti-no-tickets">
            <p>Você ainda não criou nenhum ticket.</p>
        </div>
    <?php else: ?>
        <div class="ti-tickets-grid">
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
                <div class="ti-ticket-card">
                    <div class="ti-ticket-header">
                        <div class="ti-ticket-id">#<?php echo $ticket->id; ?></div>
                        <div class="ti-ticket-date"><?php echo date('d/m/Y', strtotime($ticket->created_at)); ?></div>
                    </div>
                    
                    <div class="ti-ticket-title">
                        <h4><?php echo esc_html($ticket->title); ?></h4>
                    </div>
                    
                    <div class="ti-ticket-meta">
                        <span class="ti-priority-badge" style="background-color: <?php echo ti_get_priority_color($ticket->priority); ?>">
                            <?php echo ti_get_priority_label($ticket->priority); ?>
                        </span>
                        
                        <span class="ti-status-badge" style="background-color: <?php echo ti_get_status_color($ticket->status); ?>">
                            <?php echo ti_get_status_label($ticket->status); ?>
                        </span>
                    </div>
                    
                    <?php if ($ticket->category): ?>
                    <div class="ti-ticket-category">
                        <small>Categoria: <?php echo esc_html($ticket->category); ?></small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="ti-ticket-description">
                        <?php echo wp_trim_words(esc_html($ticket->description), 20); ?>
                    </div>
                    
                    <div class="ti-ticket-info">
                        <?php if ($analyst): ?>
                        <div class="ti-analyst-info">
                            <small>Analista: <?php echo $analyst->display_name; ?></small>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($comments_count > 0): ?>
                        <div class="ti-comments-count">
                            <small><?php echo $comments_count; ?> comentário(s)</small>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="ti-ticket-actions">
                        <button class="ti-btn ti-btn-outline ti-view-ticket-details" 
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
    // Abrir modal de detalhes
    $('.ti-view-ticket-details').on('click', function() {
        var ticketId = $(this).data('ticket-id');
        loadTicketDetails(ticketId);
        $('#ti-ticket-details-modal').show();
    });
    
    // Fechar modal
    $('.ti-modal-close').on('click', function() {
        $('#ti-ticket-details-modal').hide();
    });
    
    // Fechar modal clicando fora
    $(window).on('click', function(e) {
        if ($(e.target).is('#ti-ticket-details-modal')) {
            $('#ti-ticket-details-modal').hide();
        }
    });
    
    function loadTicketDetails(ticketId) {
        $.post(ti_tickets_ajax.ajax_url, {
            action: 'get_ticket_details',
            ticket_id: ticketId,
            nonce: ti_tickets_ajax.nonce
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
</script>