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
        add_action('wp_ajax_create_ticket', array($this, 'ajax_create_ticket'));
        add_action('wp_ajax_nopriv_create_ticket', array($this, 'ajax_create_ticket'));
        add_action('wp_ajax_update_ticket_status', array($this, 'ajax_update_ticket_status'));
        add_action('wp_ajax_add_ticket_comment', array($this, 'ajax_add_ticket_comment'));
        
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
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_tickets);
        dbDelta($sql_comments);
    }
    
    public function admin_menu() {
        add_menu_page(
            'Sistema de Tickets TI',
            'Tickets TI',
            'read',
            'ti-tickets',
            array($this, 'admin_page'),
            'dashicons-tickets-alt',
            30
        );
        
        add_submenu_page(
            'ti-tickets',
            'Todos os Tickets',
            'Todos os Tickets',
            'view_all_tickets',
            'ti-tickets',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'ti-tickets',
            'Meus Tickets',
            'Meus Tickets',
            'read',
            'ti-my-tickets',
            array($this, 'my_tickets_page')
        );
        
        add_submenu_page(
            'ti-tickets',
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
    }
    
    public function admin_page() {
        $current_user = wp_get_current_user();
        $can_view_all = current_user_can('view_all_tickets');
        
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
            wp_die('Acesso negado');
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
            wp_die('Acesso negado');
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
            wp_die('Acesso negado');
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
        $message .= "Seu ticket #{$ticket_id} teve o status atualizado para: " . strtoupper($new_status) . "\n\n";
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
        $message .= "Prioridade: {$ticket->priority}\n";
        $message .= "Categoria: {$ticket->category}\n\n";
        $message .= "Descrição: {$ticket->description}\n\n";
        $message .= "Acesse o painel administrativo para mais detalhes.";
        
        foreach ($supervisors as $supervisor) {
            wp_mail($supervisor->user_email, $subject, $message);
        }
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