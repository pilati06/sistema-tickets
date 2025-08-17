<?php
/**
 * Plugin Name: Sistema de Tickets TI
 * Description: Sistema completo de gerenciamento de tickets para demandas de TI
 * Version: 1.0.0
 * Author: Sistema TI
 */

// Previne acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Define constantes do plugin
define('TI_TICKETS_VERSION', '1.0.0');
define('TI_TICKETS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TI_TICKETS_PLUGIN_URL', plugin_dir_url(__FILE__));

class TI_Tickets_System {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Adiciona roles personalizados
        $this->add_custom_roles();
        
        // Cria tabelas necessárias
        $this->create_tables();
        
        // Adiciona hooks
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_create_ticket', array($this, 'ajax_create_ticket'));
        add_action('wp_ajax_nopriv_create_ticket', array($this, 'ajax_create_ticket'));
        add_action('wp_ajax_update_ticket_status', array($this, 'ajax_update_ticket_status'));
        add_action('wp_ajax_add_ticket_comment', array($this, 'ajax_add_ticket_comment'));
        add_action('wp_ajax_get_ticket_details', array($this, 'ajax_get_ticket_details'));
        add_action('wp_ajax_nopriv_get_ticket_details', array($this, 'ajax_get_ticket_details'));
        add_action('wp_ajax_get_ticket_comments', array($this, 'ajax_get_ticket_comments'));
        add_action('wp_ajax_export_tickets', array($this, 'ajax_export_tickets'));
        add_action('wp_ajax_generate_report', array($this, 'ajax_generate_report'));
        
        // AJAX handlers adicionais
        add_action('wp_ajax_delete_ticket', array($this, 'ajax_delete_ticket'));
        add_action('wp_ajax_bulk_action_tickets', array($this, 'ajax_bulk_action_tickets'));
        add_action('wp_ajax_get_dashboard_stats', array($this, 'ajax_get_dashboard_stats'));
        add_action('wp_ajax_assign_ticket', array($this, 'ajax_assign_ticket'));
        add_action('wp_ajax_get_ticket_history', array($this, 'ajax_get_ticket_history'));
        add_action('wp_ajax_subscribe_notifications', array($this, 'ajax_subscribe_notifications'));
        
        // Shortcodes
        add_shortcode('ti_ticket_form', array($this, 'ticket_form_shortcode'));
        add_shortcode('ti_my_tickets', array($this, 'my_tickets_shortcode'));
        
        // Hook para notificações por email
        add_action('ti_ticket_status_changed', array($this, 'send_status_notification'), 10, 2);
    }
    
    public function activate() {
        $this->add_custom_roles();
        $this->create_tables();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    public function add_custom_roles() {
        // Adiciona role de Supervisor de TI
        add_role('ti_supervisor', 'Supervisor de TI', array(
            'read' => true,
            'manage_ti_tickets' => true,
            'assign_tickets' => true,
            'view_all_tickets' => true,
        ));
        
        // Adiciona role de Analista de TI
        add_role('ti_analyst', 'Analista de TI', array(
            'read' => true,
            'manage_assigned_tickets' => true,
            'update_ticket_status' => true,
            'comment_on_tickets' => true,
        ));
        
        // Adiciona capabilities para admin
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_ti_tickets');
            $admin_role->add_cap('view_all_tickets');
            $admin_role->add_cap('assign_tickets');
            $admin_role->add_cap('update_ticket_status');
            $admin_role->add_cap('comment_on_tickets');
        }
    }
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabela de tickets
        $table_tickets = $wpdb->prefix . 'ti_tickets';
        $sql_tickets = "CREATE TABLE $table_tickets (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text NOT NULL,
            requester_id bigint(20) NOT NULL,
            assigned_to bigint(20) DEFAULT NULL,
            priority enum('baixa','media','alta','urgente') DEFAULT 'media',
            status enum('aberto','em_andamento','aguardando_teste','concluido','cancelado') DEFAULT 'aberto',
            category varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Tabela de comentários
        $table_comments = $wpdb->prefix . 'ti_ticket_comments';
        $sql_comments = "CREATE TABLE $table_comments (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            ticket_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            comment text NOT NULL,
            is_internal tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id)
        ) $charset_collate;";
        
        // Tabela de histórico de alterações
        $table_history = $wpdb->prefix . 'ti_ticket_history';
        $sql_history = "CREATE TABLE $table_history (
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
        dbDelta($sql_tickets);
        dbDelta($sql_comments);
        dbDelta($sql_history);
    }
    
    public function admin_menu() {
        // Menu principal com dashboard
        add_menu_page(
            'Sistema de Tickets TI',
            'Tickets TI',
            'read',
            'ti-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-tickets-alt',
            30
        );
        
        // Dashboard como primeira opção
        add_submenu_page(
            'ti-dashboard',
            'Dashboard',
            'Dashboard',
            'read',
            'ti-dashboard',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'ti-dashboard',
            'Todos os Tickets',
            'Todos os Tickets',
            'view_all_tickets',
            'ti-tickets',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'ti-dashboard',
            'Meus Tickets',
            'Meus Tickets',
            'read',
            'ti-my-tickets',
            array($this, 'my_tickets_page')
        );
        
        add_submenu_page(
            'ti-dashboard',
            'Novo Ticket',
            'Novo Ticket',
            'read',
            'ti-new-ticket',
            array($this, 'new_ticket_page')
        );
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('ti-tickets-js', TI_TICKETS_PLUGIN_URL . 'assets/ti-tickets.js', array('jquery'), TI_TICKETS_VERSION, true);
        wp_enqueue_style('ti-tickets-css', TI_TICKETS_PLUGIN_URL . 'assets/ti-tickets.css', array(), TI_TICKETS_VERSION);
        
        wp_localize_script('ti-tickets-js', 'ti_tickets_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ti_tickets_nonce')
        ));
    }
    
    public function admin_enqueue_scripts() {
        wp_enqueue_script('ti-tickets-admin-js', TI_TICKETS_PLUGIN_URL . 'assets/ti-tickets-admin.js', array('jquery'), TI_TICKETS_VERSION, true);
        wp_enqueue_style('ti-tickets-admin-css', TI_TICKETS_PLUGIN_URL . 'assets/ti-tickets-admin.css', array(), TI_TICKETS_VERSION);
        
        wp_localize_script('ti-tickets-admin-js', 'ti_tickets_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ti_tickets_nonce')
        ));
    }
    
    public function dashboard_page() {
        include TI_TICKETS_PLUGIN_DIR . 'templates/dashboard.php';
    }
    
    public function admin_page() {
        $current_user = wp_get_current_user();
        $can_view_all = current_user_can('view_all_tickets') || current_user_can('manage_ti_tickets');
        
        global $wpdb;
        $table_tickets = $wpdb->prefix . 'ti_tickets';
        
        if ($can_view_all) {
            $tickets = $wpdb->get_results("SELECT * FROM $table_tickets ORDER BY created_at DESC");
        } else {
            $user_id = $current_user->ID;
            $tickets = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_tickets WHERE assigned_to = %d ORDER BY created_at DESC",
                $user_id
            ));
        }
        
        include TI_TICKETS_PLUGIN_DIR . 'templates/admin-tickets.php';
    }
    
    public function my_tickets_page() {
        $current_user = wp_get_current_user();
        global $wpdb;
        $table_tickets = $wpdb->prefix . 'ti_tickets';
        
        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_tickets WHERE requester_id = %d ORDER BY created_at DESC",
            $current_user->ID
        ));
        
        include TI_TICKETS_PLUGIN_DIR . 'templates/my-tickets.php';
    }
    
    public function new_ticket_page() {
        include TI_TICKETS_PLUGIN_DIR . 'templates/new-ticket.php';
    }
    
    public function ticket_form_shortcode($atts) {
        ob_start();
        include TI_TICKETS_PLUGIN_DIR . 'templates/ticket-form.php';
        return ob_get_clean();
    }
    
    public function my_tickets_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Você precisa estar logado para ver seus tickets.</p>';
        }
        
        ob_start();
        $current_user = wp_get_current_user();
        global $wpdb;
        $table_tickets = $wpdb->prefix . 'ti_tickets';
        
        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_tickets WHERE requester_id = %d ORDER BY created_at DESC",
            $current_user->ID
        ));
        
        include TI_TICKETS_PLUGIN_DIR . 'templates/my-tickets-shortcode.php';
        return ob_get_clean();
    }
    
    public function ajax_create_ticket() {
        check_ajax_referer('ti_tickets_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Acesso negado');
        }
        
        $title = sanitize_text_field($_POST['title']);
        $description = sanitize_textarea_field($_POST['description']);
        $priority = sanitize_text_field($_POST['priority']);
        $category = sanitize_text_field($_POST['category']);
        
        global $wpdb;
        $table_tickets = $wpdb->prefix . 'ti_tickets';
        
        $result = $wpdb->insert(
            $table_tickets,
            array(
                'title' => $title,
                'description' => $description,
                'requester_id' => get_current_user_id(),
                'priority' => $priority,
                'category' => $category,
                'status' => 'aberto'
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Notifica supervisores por email
            $this->notify_supervisors_new_ticket($wpdb->insert_id);
            wp_send_json_success('Ticket criado com sucesso!');
        } else {
            wp_send_json_error('Erro ao criar ticket');
        }
    }
    
    public function ajax_update_ticket_status() {
        check_ajax_referer('ti_tickets_nonce', 'nonce');
        
        if (!current_user_can('update_ticket_status') && !current_user_can('manage_ti_tickets')) {
            wp_send_json_error('Acesso negado');
        }
        
        $ticket_id = intval($_POST['ticket_id']);
        $new_status = sanitize_text_field($_POST['status']);
        $assigned_to = isset($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
        
        global $wpdb;
        $table_tickets = $wpdb->prefix . 'ti_tickets';
        
        $update_data = array('status' => $new_status);
        if ($assigned_to) {
            $update_data['assigned_to'] = $assigned_to;
        }
        
        $result = $wpdb->update(
            $table_tickets,
            $update_data,
            array('id' => $ticket_id),
            array('%s', '%d'),
            array('%d')
        );
        
        if ($result !== false) {
            do_action('ti_ticket_status_changed', $ticket_id, $new_status);
            wp_send_json_success('Status atualizado com sucesso!');
        } else {
            wp_send_json_error('Erro ao atualizar status');
        }
    }
    
    public function ajax_add_ticket_comment() {
        check_ajax_referer('ti_tickets_nonce', 'nonce');
        
        if (!current_user_can('comment_on_tickets') && !current_user_can('manage_ti_tickets')) {
            wp_send_json_error('Acesso negado');
        }
        
        $ticket_id = intval($_POST['ticket_id']);
        $comment = sanitize_textarea_field($_POST['comment']);
        $is_internal = isset($_POST['is_internal']) ? 1 : 0;
        
        global $wpdb;
        $table_comments = $wpdb->prefix . 'ti_ticket_comments';
        
        $result = $wpdb->insert(
            $table_comments,
            array(
                'ticket_id' => $ticket_id,
                'user_id' => get_current_user_id(),
                'comment' => $comment,
                'is_internal' => $is_internal
            ),
            array('%d', '%d', '%s', '%d')
        );
        
        if ($result) {
            wp_send_json_success('Comentário adicionado com sucesso!');
        } else {
            wp_send_json_error('Erro ao adicionar comentário');
        }
    }
    
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
    
    public function ajax_export_tickets() {
        check_ajax_referer('ti_tickets_nonce', 'nonce');
        
        if (!current_user_can('manage_ti_tickets')) {
            wp_die('Acesso negado');
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
        
        // Headers para download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="tickets_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Cabeçalhos
        fputcsv($output, array('ID', 'Título', 'Descrição', 'Solicitante', 'Analista', 'Prioridade', 'Status', 'Categoria', 'Criado em', 'Atualizado em'));
        
        // Dados
        foreach ($tickets as $ticket) {
            fputcsv($output, array(
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
            ));
        }
        
        fclose($output);
        exit;
    }
    
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
                $query = "SELECT status as 'Status', COUNT(*) as 'Quantidade' 
                         FROM $table_tickets $where_clause 
                         GROUP BY status";
                break;
                
            case 'priority_summary':
                $query = "SELECT priority as 'Prioridade', COUNT(*) as 'Quantidade' 
                         FROM $table_tickets $where_clause 
                         GROUP BY priority";
                break;
                
            case 'category_summary':
                $query = "SELECT COALESCE(category, 'Sem categoria') as 'Categoria', COUNT(*) as 'Quantidade' 
                         FROM $table_tickets $where_clause 
                         GROUP BY category";
                break;
                
            case 'analyst_performance':
                $query = "SELECT u.display_name as 'Analista', 
                                COUNT(*) as 'Total de Tickets',
                                SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as 'Concluídos',
                                ROUND(AVG(CASE WHEN status = 'concluido' 
                                    THEN DATEDIFF(updated_at, created_at) 
                                    ELSE NULL END), 1) as 'Média de Dias para Conclusão'
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
     * Notificar analista sobre atribuição
     */
    public function notify_analyst_assignment($ticket_id, $analyst_id) {
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
    
    public function send_status_notification($ticket_id, $new_status) {
        global $wpdb;
        $table_tickets = $wpdb->prefix . 'ti_tickets';
        
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_tickets WHERE id = %d",
            $ticket_id
        ));
        
        if (!$ticket) return;
        
        $requester = get_user_by('ID', $ticket->requester_id);
        if (!$requester) return;
        
        $status_messages = array(
            'aberto' => 'Seu ticket foi aberto e está aguardando atribuição.',
            'em_andamento' => 'Seu ticket está sendo analisado por nossa equipe.',
            'aguardando_teste' => 'Sua solicitação foi implementada e está pronta para teste.',
            'concluido' => 'Sua solicitação foi concluída com sucesso!',
            'cancelado' => 'Seu ticket foi cancelado.'
        );
        
        $subject = "Atualização do Ticket #{$ticket_id} - {$ticket->title}";
        $message = "Olá {$requester->display_name},\n\n";
        $message .= "Seu ticket #{$ticket_id} teve o status atualizado para: " . ti_get_status_label($new_status) . "\n\n";
        $message .= $status_messages[$new_status] . "\n\n";
        $message .= "Detalhes do ticket:\n";
        $message .= "Título: {$ticket->title}\n";
        $message .= "Descrição: {$ticket->description}\n\n";
        $message .= "Atenciosamente,\nEquipe de TI";
        
        wp_mail($requester->user_email, $subject, $message);
    }
    
    public function notify_supervisors_new_ticket($ticket_id) {
        $supervisors = get_users(array('role' => 'ti_supervisor'));
        
        if (empty($supervisors)) return;
        
        global $wpdb;
        $table_tickets = $wpdb->prefix . 'ti_tickets';
        
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_tickets WHERE id = %d",
            $ticket_id
        ));
        
        $requester = get_user_by('ID', $ticket->requester_id);
        
        $subject = "Novo Ticket Criado #{$ticket_id} - {$ticket->title}";
        $message = "Um novo ticket foi criado no sistema:\n\n";
        $message .= "Ticket #: {$ticket_id}\n";
        $message .= "Título: {$ticket->title}\n";
        $message .= "Solicitante: {$requester->display_name}\n";
        $message .= "Prioridade: " . ti_get_priority_label($ticket->priority) . "\n";
        $message .= "Categoria: {$ticket->category}\n\n";
        $message .= "Descrição: {$ticket->description}\n\n";
        $message .= "Acesse o painel administrativo para mais detalhes.";
        
        foreach ($supervisors as $supervisor) {
            wp_mail($supervisor->user_email, $subject, $message);
        }
    }
    
    /**
     * Formatar data para exibição
     */
    private function format_date($date) {
        return date('d/m/Y H:i', strtotime($date));
    }
}

// Inicializa o plugin
new TI_Tickets_System();

// Função auxiliar para obter status em português
function ti_get_status_label($status) {
    $labels = array(
        'aberto' => 'Aberto',
        'em_andamento' => 'Em Andamento',
        'aguardando_teste' => 'Aguardando Teste',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado'
    );
    return isset($labels[$status]) ? $labels[$status] : $status;
}

// Função auxiliar para obter prioridade em português
function ti_get_priority_label($priority) {
    $labels = array(
        'baixa' => 'Baixa',
        'media' => 'Média',
        'alta' => 'Alta',
        'urgente' => 'Urgente'
    );
    return isset($labels[$priority]) ? $labels[$priority] : $priority;
}

// Função para obter cor do status
function ti_get_status_color($status) {
    $colors = array(
        'aberto' => '#17a2b8',
        'em_andamento' => '#ffc107',
        'aguardando_teste' => '#fd7e14',
        'concluido' => '#28a745',
        'cancelado' => '#dc3545'
    );
    return isset($colors[$status]) ? $colors[$status] : '#6c757d';
}

// Função para obter cor da prioridade
function ti_get_priority_color($priority) {
    $colors = array(
        'baixa' => '#28a745',
        'media' => '#ffc107',
        'alta' => '#fd7e14',
        'urgente' => '#dc3545'
    );
    return isset($colors[$priority]) ? $colors[$priority] : '#6c757d';
}
?>