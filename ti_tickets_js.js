// assets/ti-tickets.js

jQuery(document).ready(function($) {
    
    // Função para mostrar notificações
    function showNotification(message, type) {
        var notification = $('<div class="ti-notification ti-notification-' + type + '">' + message + '</div>');
        $('body').append(notification);
        
        setTimeout(function() {
            notification.addClass('ti-notification-show');
        }, 100);
        
        setTimeout(function() {
            notification.removeClass('ti-notification-show');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 4000);
    }
    
    // Submissão do formulário de ticket via AJAX
    $('#ti-frontend-ticket-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var btnText = submitBtn.find('.ti-btn-text');
        var btnLoading = submitBtn.find('.ti-btn-loading');
        var messageDiv = $('#ti-form-message');
        
        // Validação básica
        var title = $('#fe-ticket-title').val().trim();
        var description = $('#fe-ticket-description').val().trim();
        
        if (!title || !description) {
            showNotification('Por favor, preencha todos os campos obrigatórios.', 'error');
            return;
        }
        
        // Estado de loading
        submitBtn.prop('disabled', true);
        btnText.hide();
        btnLoading.show();
        messageDiv.hide();
        
        // Envio via AJAX
        $.ajax({
            url: ti_tickets_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'create_ticket',
                title: title,
                category: $('#fe-ticket-category').val(),
                priority: $('#fe-ticket-priority').val(),
                description: description,
                nonce: ti_tickets_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    messageDiv.removeClass('ti-error').addClass('ti-success')
                           .html('✓ ' + response.data)
                           .fadeIn();
                    
                    // Reset do formulário
                    form[0].reset();
                    
                    // Scroll para a mensagem
                    $('html, body').animate({
                        scrollTop: messageDiv.offset().top - 100
                    }, 500);
                    
                    showNotification('Ticket criado com sucesso!', 'success');
                } else {
                    messageDiv.removeClass('ti-success').addClass('ti-error')
                           .html('✗ ' + (response.data || 'Erro ao criar ticket'))
                           .fadeIn();
                    
                    showNotification('Erro ao criar ticket: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                messageDiv.removeClass('ti-success').addClass('ti-error')
                       .html('✗ Erro de conexão. Tente novamente.')
                       .fadeIn();
                
                showNotification('Erro de conexão. Tente novamente.', 'error');
                console.error('Erro AJAX:', error);
            },
            complete: function() {
                // Restaura o botão
                submitBtn.prop('disabled', false);
                btnText.show();
                btnLoading.hide();
            }
        });
    });
    
    // Gerenciamento do modal de detalhes
    var detailsModal = $('#ti-ticket-details-modal');
    
    // Abrir modal de detalhes
    $('.ti-view-ticket-details').on('click', function() {
        var ticketId = $(this).data('ticket-id');
        loadTicketDetails(ticketId);
        detailsModal.fadeIn(300);
        $('body').addClass('ti-modal-open');
    });
    
    // Fechar modal
    $('.ti-modal-close').on('click', function() {
        closeModal();
    });
    
    // Fechar modal clicando no overlay
    detailsModal.on('click', function(e) {
        if ($(e.target).is(detailsModal)) {
            closeModal();
        }
    });
    
    // Fechar modal com ESC
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27 && detailsModal.is(':visible')) {
            closeModal();
        }
    });
    
    function closeModal() {
        detailsModal.fadeOut(300);
        $('body').removeClass('ti-modal-open');
    }
    
    // Carregar detalhes do ticket
    function loadTicketDetails(ticketId) {
        // Mostrar loading
        detailsModal.find('.ti-modal-body').html('<div class="ti-loading-spinner">Carregando...</div>');
        
        $.ajax({
            url: ti_tickets_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_ticket_details',
                ticket_id: ticketId,
                nonce: ti_tickets_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    populateModalWithTicketData(response.data);
                } else {
                    detailsModal.find('.ti-modal-body').html('<div class="ti-error-message">Erro ao carregar detalhes do ticket.</div>');
                    showNotification('Erro ao carregar detalhes do ticket.', 'error');
                }
            },
            error: function() {
                detailsModal.find('.ti-modal-body').html('<div class="ti-error-message">Erro de conexão.</div>');
                showNotification('Erro de conexão ao carregar ticket.', 'error');
            }
        });
    }
    
    // Preencher modal com dados do ticket
    function populateModalWithTicketData(data) {
        var ticket = data.ticket;
        var comments = data.comments || [];
        
        // Atualizar cabeçalho do modal
        $('#ti-modal-ticket-id').text(ticket.id);
        $('#ti-modal-ticket-title').text(ticket.title);
        
        // Atualizar detalhes
        $('#ti-detail-status').html('<span class="ti-status-badge" style="background-color: ' + getStatusColor(ticket.status) + '">' + getStatusLabel(ticket.status) + '</span>');
        $('#ti-detail-priority').html('<span class="ti-priority-badge" style="background-color: ' + getPriorityColor(ticket.priority) + '">' + getPriorityLabel(ticket.priority) + '</span>');
        $('#ti-detail-category').text(ticket.category || 'N/A');
        $('#ti-detail-analyst').text(ticket.analyst_name || 'Não atribuído');
        $('#ti-detail-created').text(formatDate(ticket.created_at));
        $('#ti-detail-updated').text(formatDate(ticket.updated_at));
        $('#ti-detail-description-content').text(ticket.description);
        
        // Carregar comentários
        var commentsHtml = '';
        if (comments.length === 0) {
            commentsHtml = '<div class="ti-no-comments">Nenhum comentário ainda.</div>';
        } else {
            comments.forEach(function(comment) {
                if (!comment.is_internal) { // Só mostra comentários públicos
                    commentsHtml += buildCommentHtml(comment);
                }
            });
            
            if (commentsHtml === '') {
                commentsHtml = '<div class="ti-no-comments">Nenhum comentário público ainda.</div>';
            }
        }
        
        $('#ti-ticket-comments').html(commentsHtml);
        
        // Restaurar conteúdo do modal
        restoreModalContent();
    }
    
    // Restaurar estrutura do modal
    function restoreModalContent() {
        var modalBody = detailsModal.find('.ti-modal-body');
        modalBody.html(`
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
        `);
    }
    
    // Construir HTML do comentário
    function buildCommentHtml(comment) {
        return `
            <div class="ti-comment-item">
                <div class="ti-comment-header">
                    <strong>${comment.user_name}</strong>
                    <span class="ti-comment-date">${formatDate(comment.created_at)}</span>
                </div>
                <div class="ti-comment-text">${comment.comment.replace(/\n/g, '<br>')}</div>
            </div>
        `;
    }
    
    // Funções auxiliares para labels e cores
    function getStatusLabel(status) {
        var labels = {
            'aberto': 'Aberto',
            'em_andamento': 'Em Andamento',
            'aguardando_teste': 'Aguardando Teste',
            'concluido': 'Concluído',
            'cancelado': 'Cancelado'
        };
        return labels[status] || status;
    }
    
    function getStatusColor(status) {
        var colors = {
            'aberto': '#17a2b8',
            'em_andamento': '#ffc107',
            'aguardando_teste': '#fd7e14',
            'concluido': '#28a745',
            'cancelado': '#dc3545'
        };
        return colors[status] || '#6c757d';
    }

    function getPriorityLabel(priority) {
        var labels = {
            'baixa': 'Baixa',
            'media': 'Média',
            'alta': 'Alta',
            'urgente': 'Urgente'
        };
        return labels[priority] || priority;
    }
    
    function getPriorityColor(priority) {
        var colors = {
            'baixa': '#28a745',
            'media': '#ffc107',
            'alta': '#fd7e14',
            'urgente': '#dc3545'
        };
        return colors[priority] || '#6c757d';
    }
    
    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    // Sistema de notificações toast
    function createNotificationStyles() {
        if ($('#ti-notification-styles').length > 0) return;
        
        var styles = `
            <style id="ti-notification-styles">
                .ti-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 20px;
                    border-radius: 4px;
                    color: white;
                    font-weight: 500;
                    z-index: 10001;
                    transform: translateX(400px);
                    transition: transform 0.3s ease;
                    max-width: 350px;
                    word-wrap: break-word;
                }
                
                .ti-notification-show {
                    transform: translateX(0);
                }
                
                .ti-notification-success {
                    background-color: #28a745;
                }
                
                .ti-notification-error {
                    background-color: #dc3545;
                }
                
                .ti-notification-info {
                    background-color: #17a2b8;
                }
                
                .ti-loading-spinner {
                    text-align: center;
                    padding: 40px;
                    color: #666;
                }
                
                .ti-loading-spinner::after {
                    content: '';
                    display: inline-block;
                    width: 20px;
                    height: 20px;
                    margin-left: 10px;
                    border: 2px solid #ddd;
                    border-top: 2px solid #0073aa;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                }
                
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                .ti-error-message {
                    text-align: center;
                    padding: 40px;
                    color: #dc3545;
                    font-weight: 500;
                }
                
                body.ti-modal-open {
                    overflow: hidden;
                }
                
                /* Filtros avançados */
                .ti-tickets-filters {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    border: 1px solid #dee2e6;
                }
                
                .ti-filters-row {
                    display: flex;
                    gap: 15px;
                    align-items: center;
                    flex-wrap: wrap;
                }
                
                .ti-filter-group {
                    display: flex;
                    flex-direction: column;
                    gap: 5px;
                }
                
                .ti-filter-group label {
                    font-size: 12px;
                    font-weight: 600;
                    color: #666;
                    text-transform: uppercase;
                }
                
                .ti-filter-group select,
                .ti-filter-group input {
                    padding: 8px 12px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: 14px;
                    min-width: 150px;
                }
                
                /* Estatísticas rápidas */
                .ti-quick-stats {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 20px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    display: flex;
                    justify-content: space-around;
                    text-align: center;
                }
                
                .ti-quick-stats .stat-item h4 {
                    margin: 0;
                    font-size: 24px;
                    font-weight: 700;
                }
                
                .ti-quick-stats .stat-item p {
                    margin: 5px 0 0 0;
                    opacity: 0.9;
                    font-size: 12px;
                    text-transform: uppercase;
                }
                
                /* Melhorias no card de ticket */
                .ti-ticket-card {
                    position: relative;
                    overflow: hidden;
                }
                
                .ti-ticket-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 4px;
                    height: 100%;
                    background: var(--priority-color, #ddd);
                }
                
                .ti-ticket-card[data-priority="urgente"] {
                    --priority-color: #dc3545;
                }
                
                .ti-ticket-card[data-priority="alta"] {
                    --priority-color: #fd7e14;
                }
                
                .ti-ticket-card[data-priority="media"] {
                    --priority-color: #ffc107;
                }
                
                .ti-ticket-card[data-priority="baixa"] {
                    --priority-color: #28a745;
                }
                
                /* Animações aprimoradas */
                .ti-ticket-card {
                    animation: slideInUp 0.5s ease forwards;
                    opacity: 0;
                    transform: translateY(30px);
                }
                
                .ti-ticket-card:nth-child(1) { animation-delay: 0.1s; }
                .ti-ticket-card:nth-child(2) { animation-delay: 0.2s; }
                .ti-ticket-card:nth-child(3) { animation-delay: 0.3s; }
                .ti-ticket-card:nth-child(4) { animation-delay: 0.4s; }
                
                @keyframes slideInUp {
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                /* Responsividade melhorada */
                @media (max-width: 768px) {
                    .ti-tickets-grid {
                        grid-template-columns: 1fr;
                        gap: 15px;
                    }
                    
                    .ti-filters-row {
                        flex-direction: column;
                        align-items: stretch;
                    }
                    
                    .ti-filter-group select,
                    .ti-filter-group input {
                        min-width: 100%;
                    }
                    
                    .ti-quick-stats {
                        flex-direction: column;
                        gap: 15px;
                    }
                    
                    .ti-notification {
                        right: 10px;
                        left: 10px;
                        max-width: none;
                        transform: translateY(-100px);
                    }
                    
                    .ti-notification-show {
                        transform: translateY(0);
                    }
                }
            </style>
        `;
        
        $('head').append(styles);
    }
    
    // Inicializar estilos de notificação
    createNotificationStyles();
    
    // Sistema de filtros avançado
    function initAdvancedFilters() {
        var filterContainer = $('.ti-my-tickets-container');
        
        if (filterContainer.length && $('.ti-tickets-filters').length === 0) {
            var filtersHtml = `
                <div class="ti-tickets-filters">
                    <div class="ti-filters-row">
                        <div class="ti-filter-group">
                            <label>Status</label>
                            <select id="ti-filter-status">
                                <option value="">Todos</option>
                                <option value="aberto">Aberto</option>
                                <option value="em_andamento">Em Andamento</option>
                                <option value="aguardando_teste">Aguardando Teste</option>
                                <option value="concluido">Concluído</option>
                                <option value="cancelado">Cancelado</option>
                            </select>
                        </div>
                        
                        <div class="ti-filter-group">
                            <label>Prioridade</label>
                            <select id="ti-filter-priority">
                                <option value="">Todas</option>
                                <option value="baixa">Baixa</option>
                                <option value="media">Média</option>
                                <option value="alta">Alta</option>
                                <option value="urgente">Urgente</option>
                            </select>
                        </div>
                        
                        <div class="ti-filter-group">
                            <label>Buscar</label>
                            <input type="text" id="ti-filter-search" placeholder="Título ou descrição...">
                        </div>
                        
                        <div class="ti-filter-group">
                            <label>&nbsp;</label>
                            <button type="button" id="ti-clear-filters" class="ti-btn ti-btn-outline">Limpar</button>
                        </div>
                    </div>
                </div>
            `;
            
            filterContainer.find('h3').after(filtersHtml);
            
            // Eventos dos filtros
            $('#ti-filter-status, #ti-filter-priority').on('change', applyFilters);
            $('#ti-filter-search').on('keyup', debounce(applyFilters, 300));
            $('#ti-clear-filters').on('click', clearFilters);
        }
    }
    
    // Aplicar filtros
    function applyFilters() {
        var statusFilter = $('#ti-filter-status').val();
        var priorityFilter = $('#ti-filter-priority').val();
        var searchFilter = $('#ti-filter-search').val().toLowerCase();
        
        $('.ti-ticket-card').each(function() {
            var card = $(this);
            var show = true;
            
            // Filtro de status
            if (statusFilter && card.find('.ti-status-badge').text().toLowerCase().indexOf(getStatusLabel(statusFilter).toLowerCase()) === -1) {
                show = false;
            }
            
            // Filtro de prioridade
            if (priorityFilter && card.find('.ti-priority-badge').text().toLowerCase().indexOf(getPriorityLabel(priorityFilter).toLowerCase()) === -1) {
                show = false;
            }
            
            // Filtro de busca
            if (searchFilter) {
                var title = card.find('.ti-ticket-title h4').text().toLowerCase();
                var description = card.find('.ti-ticket-description').text().toLowerCase();
                
                if (title.indexOf(searchFilter) === -1 && description.indexOf(searchFilter) === -1) {
                    show = false;
                }
            }
            
            card.toggle(show);
        });
        
        // Mostrar mensagem se nenhum ticket for encontrado
        var visibleTickets = $('.ti-ticket-card:visible').length;
        var noResultsMsg = $('.ti-no-results');
        
        if (visibleTickets === 0 && $('.ti-ticket-card').length > 0) {
            if (noResultsMsg.length === 0) {
                $('.ti-tickets-grid').after('<div class="ti-no-results" style="text-align: center; padding: 40px; color: #666;"><p>Nenhum ticket encontrado com os filtros aplicados.</p></div>');
            }
        } else {
            noResultsMsg.remove();
        }
    }
    
    // Limpar filtros
    function clearFilters() {
        $('#ti-filter-status, #ti-filter-priority').val('');
        $('#ti-filter-search').val('');
        $('.ti-ticket-card').show();
        $('.ti-no-results').remove();
    }
    
    // Função debounce para otimizar a busca
    function debounce(func, wait) {
        var timeout;
        return function executedFunction() {
            var later = function() {
                clearTimeout(timeout);
                func.apply(this, arguments);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Auto-refresh para tickets (opcional)
    function initAutoRefresh() {
        if (typeof ti_tickets_ajax.auto_refresh !== 'undefined' && ti_tickets_ajax.auto_refresh) {
            setInterval(function() {
                // Verificar se há atualizações (implementar conforme necessário)
                checkForUpdates();
            }, 60000); // 1 minuto
        }
    }
    
    function checkForUpdates() {
        // Implementar verificação de atualizações via AJAX
        // Esta função pode verificar timestamps de última atualização
    }
    
    // Melhorar acessibilidade
    function enhanceAccessibility() {
        // Adicionar navegação por teclado nos modais
        $(document).on('keydown', function(e) {
            if ($('.ti-modal:visible').length > 0) {
                if (e.keyCode === 9) { // Tab
                    // Manter foco dentro do modal
                    var focusableElements = $('.ti-modal:visible').find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                    var firstFocusable = focusableElements.first();
                    var lastFocusable = focusableElements.last();
                    
                    if (e.shiftKey) {
                        if ($(e.target).is(firstFocusable)) {
                            e.preventDefault();
                            lastFocusable.focus();
                        }
                    } else {
                        if ($(e.target).is(lastFocusable)) {
                            e.preventDefault();
                            firstFocusable.focus();
                        }
                    }
                }
            }
        });
        
        // Adicionar aria-labels
        $('.ti-view-ticket-details').attr('aria-label', 'Ver detalhes do ticket');
        $('.ti-modal-close').attr('aria-label', 'Fechar modal');
    }
    
    // Salvar preferências do usuário (filtros, ordenação, etc.)
    function saveUserPreferences() {
        var preferences = {
            statusFilter: $('#ti-filter-status').val(),
            priorityFilter: $('#ti-filter-priority').val()
        };
        
        localStorage.setItem('ti_tickets_preferences', JSON.stringify(preferences));
    }
    
    function loadUserPreferences() {
        try {
            var preferences = JSON.parse(localStorage.getItem('ti_tickets_preferences') || '{}');
            
            if (preferences.statusFilter) {
                $('#ti-filter-status').val(preferences.statusFilter);
            }
            
            if (preferences.priorityFilter) {
                $('#ti-filter-priority').val(preferences.priorityFilter);
            }
            
            // Aplicar filtros salvos
            if (preferences.statusFilter || preferences.priorityFilter) {
                setTimeout(applyFilters, 100);
            }
        } catch (e) {
            console.log('Erro ao carregar preferências do usuário');
        }
    }
    
    // Sistema de atalhos de teclado
    function initKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Ctrl/Cmd + K para abrir busca rápida
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 75 && !e.target.matches('input, textarea')) {
                e.preventDefault();
                $('#ti-filter-search').focus();
            }
            
            // Esc para limpar busca
            if (e.keyCode === 27 && $(e.target).is('#ti-filter-search')) {
                $(e.target).val('').trigger('keyup');
            }
        });
    }
    
    // Inicializar todas as funcionalidades
    function initializeEnhancements() {
        initAdvancedFilters();
        enhanceAccessibility();
        loadUserPreferences();
        initKeyboardShortcuts();
        
        // Salvar preferências ao mudar filtros
        $(document).on('change', '#ti-filter-status, #ti-filter-priority', saveUserPreferences);
    }
    
    // Executar melhorias após carregamento
    setTimeout(initializeEnhancements, 500);
    
    // Adicionar indicador visual para tickets não lidos/novos
    function addNewTicketIndicators() {
        $('.ti-ticket-card').each(function() {
            var card = $(this);
            var createdDate = new Date(card.find('.ti-ticket-date').text().split('/').reverse().join('-'));
            var threeDaysAgo = new Date();
            threeDaysAgo.setDate(threeDaysAgo.getDate() - 3);
            
            if (createdDate > threeDaysAgo) {
                card.addClass('ti-ticket-new');
                card.find('.ti-ticket-header').append('<span class="ti-new-badge">NOVO</span>');
            }
        });
    }
    
    // Adicionar estilos para tickets novos
    var newTicketStyles = `
        .ti-ticket-new {
            box-shadow: 0 2px 8px rgba(0,115,170,0.2);
            border: 2px solid #0073aa;
        }
        
        .ti-new-badge {
            background: #0073aa;
            color: white;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    `;
    
    if ($('#ti-new-ticket-styles').length === 0) {
        $('head').append('<style id="ti-new-ticket-styles">' + newTicketStyles + '</style>');
    }
    
    // Executar indicadores de tickets novos
    setTimeout(addNewTicketIndicators, 600);
});