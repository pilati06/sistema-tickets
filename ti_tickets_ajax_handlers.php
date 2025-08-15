<?php
// Adicionar ao arquivo principal ti-tickets-plugin.php

// Handlers AJAX adicionais
add_action('wp_ajax_get_ticket_details', array($this, 'ajax_get_ticket_details'));
add_action('wp_ajax_nopriv_get_ticket_details', array($this, 'ajax_get_ticket_details'));
add_action('wp_ajax_get_ticket_comments', array($this, 'ajax_get_ticket_comments'));
add_action('wp_ajax_delete_ticket', array($this, 'ajax_delete_ticket'));
add_action('wp_ajax_bulk_action_tickets', array($this, 'ajax_bulk_action_tickets'));
add_action('wp_ajax_get_dashboard_stats', array($this, 'ajax_get_dashboard_stats'));
add_action('wp_ajax_assign_ticket', array($this, 'ajax_assign_ticket'));
add_action('wp_ajax_get_ticket_history', array($this, 'ajax_get_ticket_history'));

/**
 * Obter detalhes completos do ticket
 */
public function ajax_get_ticket_details() {
    check_ajax_referer('ti_tickets_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Acesso negado');
    }
    
    $ticket_id = intval($_POST['ticket_id']);
    
    global $wpdb;
    $table_tickets = $wpdb->prefix . 'ti_tickets';
    $table_comments = $wpdb->prefix . 'ti_ticket_comments';
    
    // Buscar ticket
    $ticket = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_tickets WHERE id = %d",
        $ticket_id
    ));
    
    if (!$ticket) {
        wp_send_json_error('Ticket não encontrado');
    }
    
    // Verificar permissões
    $current_user = wp_get_current_user();
    $can_view = false;
    
    if (current_user_can('manage_ti_tickets') || current_user_can('view_all_tickets')) {
        $can_view = true;
    } elseif ($ticket->requester_id == $current_user->ID) {
        $can_view = true;
    } elseif (current_user_can('manage_assigned_tickets') && $ticket->assigned_to == $current_user->ID) {
        $can_view = true;
    }
    
    if (!$can_view) {
        wp_send_json_error('Sem permissão para ver este ticket');
    }
    
    // Buscar dados adicionais
    $requester = get_user_by('ID', $ticket->requester_id);
    $analyst = $ticket->assigned_to ? get_user_by('ID', $ticket->assigned_to) : null;
    
    // Buscar comentários
    $comments_query = "SELECT c.*, u.display_name as user_name 
                      FROM $table_comments c 
                      LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID 
                      WHERE c.ticket_id = %d 
                      ORDER BY c.created_at ASC";
    
    $comments = $wpdb->get_results($wpdb->prepare($comments_query, $ticket_id));
    
    // Se não é admin, filtrar comentários internos
    if (!current_user_can('manage_ti_tickets')) {
        $comments = array_filter($comments, function($comment) {
            return $comment->is_internal == 0;
        });
    }
    
    // Formatar dados do ticket
    $ticket_data = array(
        'id' => $ticket->id,
        'title' => $ticket->title,
        'description' => $ticket->description,
        'status' => $ticket->status,
        'priority' => $ticket->priority,
        'category' => $ticket->category,
        'created_at' => $this->format_date($ticket->created_at),
        'updated_at' => $this->format_date($ticket->updated_at),
        'requester_name' => $requester ? $requester->display_name : 'N/A',
        'analyst_name' => $analyst ? $analyst->display_name : null,
        'assigned_to' => $ticket->assigned_to,
        'status_label' => ti_get_status_label($ticket->status),
        'priority_label' => ti_get_priority_label($ticket->priority),
        'status_color' => ti_get_status_color($ticket->status),
        'priority_color' => ti_get_priority_color($ticket->priority),
    );
    
    // Formatar comentários
    $comments_data = array();
    foreach ($comments as $comment) {
        $comments_data[] = array(
            'id' => $comment->id,
            'comment' => $comment->comment,
            'user_name' => $comment->user_name,
            'is_internal' => $comment->is_internal,
            'created_at' => $this->format_date($comment->created_at)
        );
    }
    
    wp_send_json_success(array(
        'ticket' => $ticket_data,
        'comments' => $comments_data
    ));
}

/**
 * Buscar comentários específicos do ticket
 */
public function ajax_get_ticket_comments() {
    check_ajax_referer('ti_tickets_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Acesso negado');
    }
    
    $ticket_id = intval($_POST['ticket_id']);
    
    global $wpdb;
    $table_comments = $wpdb->prefix . 'ti_ticket_comments';
    
    $comments = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*, u.display_name as user_name 
         FROM $table_comments c 
         LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID 
         WHERE c.ticket_id = %d 
         ORDER BY c.created_at DESC",
        $ticket_id
    ));
    
    wp_send_json_success(array('comments' => $comments));
}

/**
 * Excluir ticket (apenas admins)
 */
public function ajax_delete_ticket() {
    check_ajax_referer('ti_tickets_nonce', 'nonce');
    
    if (!current_user_can('manage_ti_tickets')) {
        wp_send_json_error('Acesso negado');
    }
    
    $ticket_id = intval($_POST['ticket_id']);
    
    global $wpdb;
    $table_tickets = $wpdb->prefix . 'ti_tickets';
    $table_comments = $wpdb->prefix . 'ti_ticket_comments';
    
    // Excluir comentários primeiro
    $wpdb->delete($table_comments, array('ticket_id' => $ticket_id), array('%d'));
    
    // Excluir ticket
    $result = $wpdb->delete($table_tickets, array('id' => $ticket_id), array('%d'));
    
    if ($result !== false) {
        wp_send_json_success('Ticket excluído com sucesso');
    } else {
        wp_send_json_error('Erro ao excluir ticket');
    }
}

/**
 * Ações em lote para tickets
 */
public function ajax_bulk_action_tickets() {
    check_ajax_referer('ti_tickets_nonce', 'nonce');
    
    if (!current_user_can('manage_ti_tickets')) {
        wp_send_json_error('Acesso negado');
    }
    
    $action = sanitize_text_field($_POST['action_type']);
    $ticket_ids = array_map('intval', $_POST['ticket_ids']);
    $value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';
    
    if (empty($ticket_ids)) {
        wp_send_json_error('Nenhum ticket selecionado');
    }
    
    global $wpdb;
    $table_tickets = $wpdb->prefix . 'ti_tickets';
    
    $updated = 0;
    
    foreach ($ticket_ids as $ticket_id) {
        $update_data = array();
        
        switch ($action) {
            case 'change_status':
                if (in_array($value, array('aberto', 'em_andamento', 'aguardando_teste', 'concluido', 'cancelado'))) {
                    $update_data['status'] = $value;
                }
                break;
                
            case 'change_priority':
                if (in_array($value, array('baixa', 'media', 'alta', 'urgente'))) {
                    $update_data['priority'] = $value;
                }
                break;
                
            case 'assign_analyst':
                if (is_numeric($value) && $value > 0) {
                    $update_data['assigned_to'] = intval($value);
                }
                break;
                
            case 'delete':
                $wpdb->delete($table_tickets, array('id' => $ticket_id), array('%d'));
                $updated++;
                continue 2;
        }
        
        if (!empty($update_data)) {
            $result = $wpdb->update(
                $table_tickets,
                $update_data,
                array('id' => $ticket_id),
                array('%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $updated++;
                
                // Trigger hook para notificações
                if (isset($update_data['status'])) {
                    do_action('ti_ticket_status_changed', $ticket_id, $update_data['status']);
                }
            }
        }
    }
    
    wp_send_json_success("$updated tickets atualizados com sucesso");
}

/**
 * Obter estatísticas do dashboard
 */
public function ajax_get_dashboard_stats() {
    check_ajax_referer('ti_tickets_nonce', 'nonce');
    
    if (!current_user_can('view_all_tickets') && !current_user_can('manage_ti_tickets')) {
        wp_send_json_error('Acesso negado');
    }
    
    global $wpdb;
    $table_tickets = $wpdb->prefix . 'ti_tickets';
    
    // Estatísticas básicas
    $stats = array(
        'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets"),
        'aberto' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE status = 'aberto'"),
        'em_andamento' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE status = 'em_andamento'"),
        'aguardando_teste' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE status = 'aguardando_teste'"),
        'concluido' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE status = 'concluido'"),
        'cancelado' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE status = 'cancelado'"),
    );
    
    // Estatísticas por prioridade
    $priority_stats = array(
        'baixa' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE priority = 'baixa'"),
        'media' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE priority = 'media'"),
        'alta' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE priority = 'alta'"),
        'urgente' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tickets WHERE priority = 'urgente'"),
    );
    
    // Tickets por analista
    $analyst_stats = $wpdb->get_results(
        "SELECT assigned_to, COUNT(*) as count 
         FROM $table_tickets 
         WHERE assigned_to IS NOT NULL 
         GROUP BY assigned_to"
    );
    
    $analyst_data = array();
    foreach ($analyst_stats as $stat) {
        $user = get_user_by('ID', $stat->assigned_to);
        if ($user) {
            $analyst_data[] = array(
                'name' => $user->display_name,
                'count' => $stat->count
            );
        }
    }
    
    // Tickets criados nos últimos 30 dias
    $recent_tickets = $wpdb->get_var(
        "SELECT COUNT(*) FROM $table_tickets 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    
    // Tempo médio de resolução (em dias)
    $avg_resolution_time = $wpdb->get_var(
        "SELECT AVG(DATEDIFF(updated_at, created_at)) 
         FROM $table_tickets 
         WHERE status = 'concluido'"
    );
    
    wp_send_json_success(array(
        'basic_stats' => $stats,
        'priority_stats' => $priority_stats,
        'analyst_stats' => $analyst_data,
        'recent_tickets' => $recent_tickets,
        'avg_resolution_time' => round($avg_resolution_time, 1)
    ));
}

/**
 * Atribuir ticket a um analista
 */
public function ajax_assign_ticket() {
    check_ajax_referer('ti_tickets_nonce', 'nonce');
    
    if (!current_user_can('assign_tickets') && !current_user_can('manage_ti_tickets')) {
        wp_send_json_error('Acesso negado');
    }
    
    $ticket_id = intval($_POST['ticket_id']);
    $analyst_id = intval($_POST['analyst_id']);
    
    global $wpdb;
    $table_tickets = $wpdb->prefix . 'ti_tickets';
    
    // Verificar se o analista existe e tem a role correta
    $analyst = get_user_by('ID', $analyst_id);
    if (!$analyst || !in_array('ti_analyst', $analyst->roles) && !in_array('ti_supervisor', $analyst->roles)) {
        wp_send_json_error('Analista inválido');
    }
    
    $result = $wpdb->update(
        $table_tickets,
        array(
            'assigned_to' => $analyst_id,
            'status' => 'em_andamento'
        ),
        array('id' => $ticket_id),
        array('%d', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        // Adicionar comentário automático
        $table_comments = $wpdb->prefix . 'ti_ticket_comments';
        $wpdb->insert(
            $table_comments,
            array(
                'ticket_id' => $ticket_id,
                'user_id' => get_current_user_id(),
                'comment' => "Ticket atribuído para {$analyst->display_name}",
                'is_internal' => 0
            ),
            array('%d', '%d', '%s', '%d')
        );
        
        // Notificar o analista por email
        $this->notify_analyst_assignment($ticket_id, $analyst_id);
        
        wp_send_json_success('Ticket atribuído com sucesso');
    } else {
        wp_send_json_error('Erro ao atribuir ticket');
    }
}

/**
 * Obter histórico de alterações do ticket
 */
public function ajax_get_ticket_history() {
    check_ajax_referer('ti_tickets_nonce', 'nonce');
    
    if (!current_user_can('manage_ti_tickets')) {
        wp_send_json_error('Acesso negado');
    }
    
    $ticket_id = intval($_POST['ticket_id']);
    
    global $wpdb;
    $table_history = $wpdb->prefix . 'ti_ticket_history';
    
    $history = $wpdb->get_results($wpdb->prepare(
        "SELECT h.*, u.display_name as user_name 
         FROM $table_history h 
         LEFT JOIN {$wpdb->users} u ON h.user_id = u.ID 
         WHERE h.ticket_id = %d 
         ORDER BY h.created_at DESC",
        $ticket_id
    ));
    
    wp_send_json_success(array('history' => $history));
}

/**
 * Formatar data para exibição
 */
private function format_date($date) {
    return date('d/m/Y H:i', strtotime($date));
}

/**
 * Notificar analista sobre atribuição
 */
private function notify_analyst_assignment($ticket_id, $analyst_id) {
    global $wpdb;
    $table_tickets = $wpdb->prefix . 'ti_tickets';
    
    $ticket = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_tickets WHERE id = %d",
        $ticket_id
    ));
    
    $analyst = get_user_by('ID', $analyst_id);
    $requester = get_user_by('ID', $ticket->requester_id);
    
    if (!$ticket || !$analyst) return;
    
    $subject = "Ticket #{$ticket_id} atribuído para você - {$ticket->title}";
    $message = "Olá {$analyst->display_name},\n\n";
    $message .= "Um novo ticket foi atribuído para você:\n\n";
    $message .= "Ticket #: {$ticket_id}\n";
    $message .= "Título: {$ticket->title}\n";
    $message .= "Solicitante: {$requester->display_name}\n";
    $message .= "Prioridade: " . ti_get_priority_label($ticket->priority) . "\n";
    $message .= "Categoria: {$ticket->category}\n\n";
    $message .= "Descrição:\n{$ticket->description}\n\n";
    $message .= "Acesse o painel administrativo para mais detalhes e para atualizar o status.";
    
    wp_mail($analyst->user_email, $subject, $message);
}

/**
 * Criar tabela de histórico de alterações
 */
private function create_history_table() {
    global $wpdb;
    
    $table_history = $wpdb->prefix . 'ti_ticket_history';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_history (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        ticket_id mediumint(9) NOT NULL,
        user_id bigint(20) NOT NULL,
        field_changed varchar(50) NOT NULL,
        old_value text,
        new_value text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ticket_id (ticket_id),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Registrar alteração no histórico
 */
public function log_ticket_change($ticket_id, $field, $old_value, $new_value, $user_id = null) {
    if ($old_value === $new_value) return;
    
    global $wpdb;
    $table_history = $wpdb->prefix . 'ti_ticket_history';
    
    $wpdb->insert(
        $table_history,
        array(
            'ticket_id' => $ticket_id,
            'user_id' => $user_id ?: get_current_user_id(),
            'field_changed' => $field,
            'old_value' => $old_value,
            'new_value' => $new_value
        ),
        array('%d', '%d', '%s', '%s', '%s')
    );
}

/**
 * Sistema de notificações push (usando WebSockets ou Server-Sent Events)
 */
public function ajax_subscribe_notifications() {
    check_ajax_referer('ti_tickets_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Acesso negado');
    }
    
    // Implementar sistema de notificações em tempo real
    // Pode usar tecnologias como Socket.IO, Pusher, ou Server-Sent Events
    
    wp_send_json_success('Inscrito nas notificações');
}

/**
 * Exportar tickets para CSV
 */
public function ajax_export_tickets() {
    check_ajax_referer('ti_tickets_nonce', 'nonce');
    
    if (!current_user_can('manage_ti_tickets')) {
        wp_send_json_error('Acesso negado');
    }
    
    global $wpdb;
    $table_tickets = $wpdb->prefix . 'ti_tickets';
    
    $tickets = $wpdb->get_results(
        "SELECT t.*, 
                u1.display_name as requester_name,
                u2.display_name as analyst_name
         FROM $table_tickets t
         LEFT JOIN {$wpdb->users} u1 ON t.requester_id = u1.ID
         LEFT JOIN {$wpdb->users} u2 ON t.assigned_to = u2.ID
         ORDER BY t.created_at DESC"
    );
    
    // Preparar dados para CSV
    $csv_data = array();
    $csv_data[] = array('ID', 'Título', 'Descrição', 'Solicitante', 'Analista', 'Prioridade', 'Status', 'Categoria', 'Criado em', 'Atualizado em');
    
    foreach ($tickets as $ticket) {
        $csv_data[] = array(
            $ticket->id,
            $ticket->title,
            $ticket->description,
            $ticket->requester_name,
            $ticket->analyst_name ?: 'Não atribuído',
            ti_get_priority_label($ticket->priority),
            ti_get_status_label($ticket->status),
            $ticket->category ?: 'N/A',
            $ticket->created_at,
            $ticket->updated_at
        );
    }
    
    // Gerar arquivo CSV
    $filename = 'tickets_' . date('Y-m-d_H-i-s') . '.csv';
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/' . $filename;
    
    $file = fopen($file_path, 'w');
    
    foreach ($csv_data as $row) {
        fputcsv($file, $row);
    }
    
    fclose($file);
    
    wp_send_json_success(array(
        'file_url' => $upload_dir['url'] . '/' . $filename,
        'filename' => $filename
    ));
}

/**
 * Relatório avançado de tickets
 */
public function ajax_generate_report() {
    check_ajax_referer('ti_tickets_nonce', 'nonce');
    
    if (!current_user_can('manage_ti_tickets')) {
        wp_send_json_error('Acesso negado');
    }
    
    $date_from = sanitize_text_field($_POST['date_from']);
    $date_to = sanitize_text_field($_POST['date_to']);
    $report_type = sanitize_text_field($_POST['report_type']);
    
    global $wpdb;
    $table_tickets = $wpdb->prefix . 'ti_tickets';
    
    $where_clause = "WHERE created_at BETWEEN %s AND %s";
    $params = array($date_from . ' 00:00:00', $date_to . ' 23:59:59');
    
    switch ($report_type) {
        case 'status_summary':
            $query = "SELECT status, COUNT(*) as count 
                     FROM $table_tickets $where_clause 
                     GROUP BY status";
            break;
            
        case 'priority_summary':
            $query = "SELECT priority, COUNT(*) as count 
                     FROM $table_tickets $where_clause 
                     GROUP BY priority";
            break;
            
        case 'category_summary':
            $query = "SELECT category, COUNT(*) as count 
                     FROM $table_tickets $where_clause 
                     GROUP BY category";
            break;
            
        case 'analyst_performance':
            $query = "SELECT u.display_name as analyst, 
                            COUNT(*) as total_tickets,
                            SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as completed,
                            AVG(CASE WHEN status = 'concluido' 
                                THEN DATEDIFF(updated_at, created_at) 
                                ELSE NULL END) as avg_resolution_days
                     FROM $table_tickets t
                     LEFT JOIN {$wpdb->users} u ON t.assigned_to = u.ID
                     $where_clause AND assigned_to IS NOT NULL
                     GROUP BY assigned_to, u.display_name";
            break;
            
        default:
            wp_send_json_error('Tipo de relatório inválido');
    }
    
    $results = $wpdb->get_results($wpdb->prepare($query, $params));
    
    wp_send_json_success(array(
        'report_data' => $results,
        'report_type' => $report_type,
        'date_range' => array('from' => $date_from, 'to' => $date_to)
    ));
}
?>