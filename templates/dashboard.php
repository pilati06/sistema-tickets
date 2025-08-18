<?php
// templates/dashboard.php
if (!defined('ABSPATH')) exit;

$current_user = wp_get_current_user();
$can_manage = current_user_can('manage_ti_tickets');
$can_view_all = current_user_can('view_all_tickets');

global $wpdb;
$table_tickets = $wpdb->prefix . 'ti_tickets';

// Estatísticas básicas
$stats = array(
    'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets"),
    'aberto' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE status = 'aberto'"),
    'em_andamento' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE status = 'em_andamento'"),
    'aguardando_teste' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE status = 'aguardando_teste'"),
    'concluido' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE status = 'concluido'"),
    'cancelado' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE status = 'cancelado'")
);

// Estatísticas por prioridade
$priority_stats = array(
    'urgente' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE priority = 'urgente' AND status NOT IN ('concluido', 'cancelado')"),
    'alta' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE priority = 'alta' AND status NOT IN ('concluido', 'cancelado')"),
    'media' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE priority = 'media' AND status NOT IN ('concluido', 'cancelado')"),
    'baixa' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE priority = 'baixa' AND status NOT IN ('concluido', 'cancelado')")
);

// Tickets recentes
$recent_tickets = $wpdb->get_results("
    SELECT t.*, u.display_name as requester_name 
    FROM $table_tickets t 
    LEFT JOIN {$wpdb->users} u ON t.requester_id = u.ID 
    ORDER BY t.created_at DESC 
    LIMIT 10
");

// Meus tickets (se for analista)
$my_tickets = array();
if (current_user_can('manage_assigned_tickets')) {
    $my_tickets = $wpdb->get_results($wpdb->prepare("
        SELECT t.*, u.display_name as requester_name 
        FROM $table_tickets t 
        LEFT JOIN {$wpdb->users} u ON t.requester_id = u.ID 
        WHERE t.assigned_to = %d AND t.status NOT IN ('concluido', 'cancelado')
        ORDER BY t.priority = 'urgente' DESC, t.priority = 'alta' DESC, t.created_at ASC
        LIMIT 5
    ", $current_user->ID));
}

// Analistas disponíveis
$analysts = get_users(array('role' => 'ti_analyst'));
?>

<div class="wrap ti-dashboard">
    <h1>Dashboard - Sistema de Tickets TI</h1>
    
    <?php if ($can_manage || $can_view_all): ?>
    <!-- Estatísticas Principais -->
    <div class="ti-stats-overview">
        <div class="ti-stats-cards">
            <div class="ti-stat-card ti-stat-total">
                <div class="ti-stat-icon">🎫</div>
                <div class="ti-stat-content">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total de Tickets</p>
                </div>
            </div>
            
            <div class="ti-stat-card ti-stat-open">
                <div class="ti-stat-icon">📨</div>
                <div class="ti-stat-content">
                    <h3><?php echo $stats['aberto']; ?></h3>
                    <p>Abertos</p>
                </div>
            </div>
            
            <div class="ti-stat-card ti-stat-progress">
                <div class="ti-stat-icon">⚙️</div>
                <div class="ti-stat-content">
                    <h3><?php echo $stats['em_andamento']; ?></h3>
                    <p>Em Andamento</p>
                </div>
            </div>
            
            <div class="ti-stat-card ti-stat-testing">
                <div class="ti-stat-icon">🧪</div>
                <div class="ti-stat-content">
                    <h3><?php echo $stats['aguardando_teste']; ?></h3>
                    <p>Aguardando Teste</p>
                </div>
            </div>
            
            <div class="ti-stat-card ti-stat-closed">
                <div class="ti-stat-icon">✅</div>
                <div class="ti-stat-content">
                    <h3><?php echo $stats['concluido']; ?></h3>
                    <p>Concluídos</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alertas de Prioridade -->
    <div class="ti-priority-alerts">
        <h2>Alertas por Prioridade</h2>
        <div class="ti-priority-cards">
            <?php if ($priority_stats['urgente'] > 0): ?>
            <div class="ti-priority-card ti-urgent">
                <div class="ti-priority-count"><?php echo $priority_stats['urgente']; ?></div>
                <div class="ti-priority-label">Tickets Urgentes</div>
                <div class="ti-priority-action">
                    <a href="<?php echo admin_url('admin.php?page=ti-tickets&priority=urgente'); ?>" class="button button-primary">Ver Tickets</a>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($priority_stats['alta'] > 0): ?>
            <div class="ti-priority-card ti-high">
                <div class="ti-priority-count"><?php echo $priority_stats['alta']; ?></div>
                <div class="ti-priority-label">Prioridade Alta</div>
                <div class="ti-priority-action">
                    <a href="<?php echo admin_url('admin.php?page=ti-tickets&priority=alta'); ?>" class="button">Ver Tickets</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Meus Tickets (para analistas) -->
    <?php if (!empty($my_tickets)): ?>
    <div class="ti-my-assigned-tickets">
        <h2>Meus Tickets Atribuídos</h2>
        <div class="ti-quick-tickets">
            <?php foreach ($my_tickets as $ticket): ?>
            <div class="ti-quick-ticket-card" data-priority="<?php echo $ticket->priority; ?>">
                <div class="ti-quick-ticket-header">
                    <span class="ti-ticket-id">#<?php echo $ticket->id; ?></span>
                    <span class="ti-priority-badge" style="background-color: <?php echo ti_get_priority_color($ticket->priority); ?>">
                        <?php echo ti_get_priority_label($ticket->priority); ?>
                    </span>
                </div>
                <div class="ti-quick-ticket-title">
                    <h4><?php echo esc_html($ticket->title); ?></h4>
                </div>
                <div class="ti-quick-ticket-meta">
                    <small>Por: <?php echo $ticket->requester_name; ?></small>
                    <small><?php echo date('d/m/Y H:i', strtotime($ticket->created_at)); ?></small>
                </div>
                <div class="ti-quick-ticket-actions">
                    <button class="button button-small ti-view-ticket-details" data-ticket-id="<?php echo $ticket->id; ?>">
                        Ver Detalhes
                    </button>
                    <select class="ti-quick-status-change" data-ticket-id="<?php echo $ticket->id; ?>">
                        <option value="">Alterar Status</option>
                        <option value="em_andamento" <?php selected($ticket->status, 'em_andamento'); ?>>Em Andamento</option>
                        <option value="aguardando_teste">Aguardando Teste</option>
                        <option value="concluido">Concluído</option>
                    </select>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Tickets Recentes -->
    <?php if ($can_view_all): ?>
    <div class="ti-recent-tickets">
        <div class="ti-section-header">
            <h2>Tickets Recentes</h2>
            <div class="ti-section-actions">
                <a href="<?php echo admin_url('admin.php?page=ti-tickets'); ?>" class="button">Ver Todos</a>
                <a href="<?php echo admin_url('admin.php?page=ti-new-ticket'); ?>" class="button button-primary">Novo Ticket</a>
            </div>
        </div>
        
        <div class="ti-recent-tickets-table">
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Solicitante</th>
                        <th>Prioridade</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_tickets as $ticket): ?>
                    <tr>
                        <td><strong>#<?php echo $ticket->id; ?></strong></td>
                        <td>
                            <strong><?php echo esc_html($ticket->title); ?></strong>
                            <div class="ti-ticket-excerpt">
                                <?php echo wp_trim_words(esc_html($ticket->description), 10); ?>
                            </div>
                        </td>
                        <td><?php echo $ticket->requester_name; ?></td>
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
                        <td><?php echo date('d/m/Y H:i', strtotime($ticket->created_at)); ?></td>
                        <td>
                            <button class="button button-small ti-view-ticket-details" data-ticket-id="<?php echo $ticket->id; ?>">
                                Ver
                            </button>
                            <?php if ($can_manage): ?>
                            <button class="button button-small ti-quick-assign" data-ticket-id="<?php echo $ticket->id; ?>">
                                Atribuir
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Widget de Ações Rápidas -->
    <div class="ti-quick-actions">
        <h2>Ações Rápidas</h2>
        <div class="ti-quick-actions-grid">
            <div class="ti-quick-action-card">
                <div class="ti-action-icon">➕</div>
                <h3>Novo Ticket</h3>
                <p>Criar um novo ticket de suporte</p>
                <a href="<?php echo admin_url('admin.php?page=ti-new-ticket'); ?>" class="button button-primary">Criar</a>
            </div>
            
            <div class="ti-quick-action-card">
                <div class="ti-action-icon">📊</div>
                <h3>Relatórios</h3>
                <p>Visualizar relatórios e estatísticas</p>
                <button class="button" onclick="showReportsModal()">Abrir</button>
            </div>
            
            <?php if ($can_manage): ?>
            <div class="ti-quick-action-card">
                <div class="ti-action-icon">⚙️</div>
                <h3>Configurações</h3>
                <p>Gerenciar usuários e configurações</p>
                <a href="<?php echo admin_url('users.php'); ?>" class="button">Configurar</a>
            </div>
            
            <div class="ti-quick-action-card">
                <div class="ti-action-icon">📤</div>
                <h3>Exportar Dados</h3>
                <p>Exportar tickets para CSV</p>
                <button class="button" onclick="exportTickets()">Exportar</button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para detalhes do ticket -->
<div id="ti-ticket-modal" class="ti-modal" style="display: none;">
    <div class="ti-modal-content">
        <div class="ti-modal-header">
            <h2>Ticket #<span id="ti-modal-ticket-id"></span></h2>
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
            
            <!-- <div class="ti-ticket-comments">
                <h3>Comentários</h3>
                <div id="ti-comments-list"></div>
            </div> -->

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

<!-- Modal para Relatórios -->
<div id="ti-reports-modal" class="ti-modal" style="display: none;">
    <div class="ti-modal-content ti-reports-modal-content">
        <div class="ti-modal-header">
            <h2>Gerar Relatório</h2>
            <span class="ti-modal-close">&times;</span>
        </div>
        <div class="ti-modal-body">
            <form id="ti-reports-form">
                <div class="ti-form-row">
                    <label>Tipo de Relatório:</label>
                    <select id="report-type" required>
                        <option value="">Selecionar tipo</option>
                        <option value="status_summary">Resumo por Status</option>
                        <option value="priority_summary">Resumo por Prioridade</option>
                        <option value="category_summary">Resumo por Categoria</option>
                        <option value="analyst_performance">Performance dos Analistas</option>
                    </select>
                </div>
                
                <div class="ti-form-row">
                    <label>Data Início:</label>
                    <input type="date" id="report-date-from" required>
                </div>
                
                <div class="ti-form-row">
                    <label>Data Fim:</label>
                    <input type="date" id="report-date-to" required>
                </div>
                
                <div class="ti-form-row">
                    <button type="submit" class="button button-primary">Gerar Relatório</button>
                </div>
            </form>
            
            <div id="ti-report-results" style="display: none;">
                <h3>Resultados do Relatório</h3>
                <div id="ti-report-content"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para atribuição rápida -->
<div id="ti-quick-assign-modal" class="ti-modal" style="display: none;">
    <div class="ti-modal-content">
        <div class="ti-modal-header">
            <h2>Atribuir Ticket</h2>
            <span class="ti-modal-close">&times;</span>
        </div>
        <div class="ti-modal-body">
            <form id="ti-quick-assign-form">
                <div class="ti-form-row">
                    <label for="quick-assign-analyst">Selecionar Analista:</label>
                    <select id="quick-assign-analyst" required>
                        <option value="">Selecionar analista</option>
                        <?php foreach ($analysts as $analyst): ?>
                        <option value="<?php echo $analyst->ID; ?>"><?php echo $analyst->display_name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ti-form-row">
                    <button type="submit" class="button button-primary">Atribuir</button>
                    <button type="button" class="button" onclick="jQuery('#ti-quick-assign-modal').fadeOut(300); jQuery('body').removeClass('ti-modal-open')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var currentAssignTicketId = null;
    var currentTicketId = null;
    
    // Modal de relatórios
    function showReportsModal() {
        $('#ti-reports-modal').show();
    }
    
    window.showReportsModal = showReportsModal;
    
    // Fechar modais
    $('.ti-modal-close').on('click', function() {
        $(this).closest('.ti-modal').fadeOut(300);
        $('body').removeClass('ti-modal-open');
    });
    
    // Ver detalhes do ticket
    $('.ti-view-ticket-details').on('click', function() {
        var ticketId = $(this).data('ticket-id');
        currentTicketId = ticketId;
        loadTicketDetails(ticketId);
        $('#ti-ticket-modal').fadeIn(300);
        $('body').addClass('ti-modal-open');
    });
    
    // Fechar modal clicando fora
    $(window).on('click', function(e) {
        if ($(e.target).is('.ti-modal')) {
            $('.ti-modal').fadeOut(300);
            $('body').removeClass('ti-modal-open');
        }
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
            nonce: $('#ti-nonce').val()
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
    
    // Carregar detalhes do ticket
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
                $('#ti-detail-title').text(ticket.title);
                $('#ti-detail-requester').text(ticket.requester_name);
                $('#ti-detail-priority').html('<span class="ti-priority-badge" style="background-color: ' + ticket.priority_color + '">' + ticket.priority_label + '</span>');
                $('#ti-detail-status').html('<span class="ti-status-badge" style="background-color: ' + ticket.status_color + '">' + ticket.status_label + '</span>');
                $('#ti-detail-analyst').text(ticket.analyst_name || 'Não atribuído');
                $('#ti-detail-category').text(ticket.category || 'N/A');
                $('#ti-detail-description').text(ticket.description);
                
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
                $('#ti-comments-list').html(commentsHtml);
            } else {
                alert('Erro ao carregar detalhes do ticket.');
            }
        });
    }
    
    // Quick assign
    $('.ti-quick-assign').on('click', function() {
        currentAssignTicketId = $(this).data('ticket-id');
        $('#ti-quick-assign-modal').fadeIn(300);
        $('body').addClass('ti-modal-open');
    });
    
    // Submissão do formulário de atribuição
    $('#ti-quick-assign-form').on('submit', function(e) {
        e.preventDefault();
        
        var analystId = $('#quick-assign-analyst').val();
        
        $.post(ajaxurl, {
            action: 'update_ticket_status',
            ticket_id: currentAssignTicketId,
            status: 'em_andamento',
            assigned_to: analystId,
            nonce: '<?php echo wp_create_nonce('ti_tickets_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                alert('Ticket atribuído com sucesso!');
                location.reload();
            } else {
                alert('Erro ao atribuir ticket: ' + response.data);
            }
        });
    });
    
    // Mudança rápida de status
    $('.ti-quick-status-change').on('change', function() {
        var ticketId = $(this).data('ticket-id');
        var newStatus = $(this).val();
        
        if (newStatus) {
            $.post(ajaxurl, {
                action: 'update_ticket_status',
                ticket_id: ticketId,
                status: newStatus,
                nonce: '<?php echo wp_create_nonce('ti_tickets_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Status atualizado com sucesso!');
                    location.reload();
                } else {
                    alert('Erro ao atualizar status: ' + response.data);
                }
            });
        }
    });
    
    // Exportar tickets
    function exportTickets() {
        window.location.href = ajaxurl + '?action=export_tickets&nonce=<?php echo wp_create_nonce('ti_tickets_nonce'); ?>';
    }
    
    window.exportTickets = exportTickets;
    
    // Formulário de relatórios
    $('#ti-reports-form').on('submit', function(e) {
        e.preventDefault();
        
        var reportType = $('#report-type').val();
        var dateFrom = $('#report-date-from').val();
        var dateTo = $('#report-date-to').val();
        
        $.post(ajaxurl, {
            action: 'generate_report',
            report_type: reportType,
            date_from: dateFrom,
            date_to: dateTo,
            nonce: '<?php echo wp_create_nonce('ti_tickets_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                var data = response.data.report_data;
                var html = '<table class="wp-list-table widefat striped"><tbody>';
                
                data.forEach(function(item) {
                    html += '<tr>';
                    Object.values(item).forEach(function(value) {
                        html += '<td>' + value + '</td>';
                    });
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                $('#ti-report-content').html(html);
                $('#ti-report-results').show();
            } else {
                alert('Erro ao gerar relatório: ' + response.data);
            }
        });
    });
});
</script>