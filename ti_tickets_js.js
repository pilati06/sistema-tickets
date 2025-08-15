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