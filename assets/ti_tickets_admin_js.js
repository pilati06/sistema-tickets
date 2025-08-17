// assets/ti-tickets-admin.js

jQuery(document).ready(function($) {
    var currentTicketId = null;
    
    // Adicionar handlers AJAX para as novas ações
    wp.ajax.post = wp.ajax.post || function(action, data) {
        return $.post(ajaxurl, $.extend({ action: action }, data));
    };
    
    // Abrir modal para visualizar/editar ticket
    $('.ti-view-ticket, .ti-edit-ticket').on('click', function() {
        var ticketId = $(this).data('ticket-id');
        currentTicketId = ticketId;
        openTicketModal(ticketId);
    });
    
    // Fechar modal
    $('.ti-modal-close, .ti-modal-overlay').on('click', function() {
        closeTicketModal();
    });
    
    // Fechar modal com ESC
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27 && $('#ti-ticket-modal').is(':visible')) {
            closeTicketModal();
        }
    });
    
    // Filtros de tickets
    $('#filter-status, #filter-priority').on('change', function() {
        applyFilters();
    });
    
    // Atualizar ticket
    $('#ti-update-ticket').on('click', function() {
        updateTicketStatus();
    });
    
    // Adicionar comentário
    $('#ti-add-comment').on('click', function() {
        addTicketComment();
    });
    
    // Submissão do formulário de novo ticket (admin)
    $('#ti-new-ticket-form').on('submit', function(e) {
        e.preventDefault();
        submitNewTicket();
    });
    
    function openTicketModal(ticketId) {
        // Mostrar modal com loading
        $('#ti-ticket-modal').show();
        $('#ti-ticket-modal .ti-modal-body').html('<div class="ti-loading">Carregando...</div>');
        
        // Carregar dados do ticket
        loadTicketData(ticketId);
    }
    
    function closeTicketModal() {
        $('#ti-ticket-modal').hide();
        currentTicketId = null;
    }
    
    function loadTicketData(ticketId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_ticket_details',
                ticket_id: ticketId,
                nonce: $('#ti-nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    populateTicketModal(response.data);
                } else {
                    showError('Erro ao carregar dados do ticket: ' + response.data);
                }
            },
            error: function() {
                showError('Erro de conexão ao carregar ticket.');
            }
        });
    }
    
    function populateTicketModal(data) {
        var ticket = data.ticket;
        var comments = data.comments || [];
        
        // Preencher dados básicos
        $('#ti-modal-ticket-id').text(ticket.id);
        $('#ti-detail-title').text(ticket.title);
        $('#ti-detail-requester').text(ticket.requester_name);
        $('#ti-detail-priority').html(createPriorityBadge(ticket.priority));
        $('#ti-detail-status').html(createStatusBadge(ticket.status));
        $('#ti-detail-analyst').text(ticket.analyst_name || 'Não atribuído');
        $('#ti-detail-category').text(ticket.category || 'N/A');
        $('#ti-detail-description').text(ticket.description);
        
        // Preencher campos de edição
        $('#ti-new-status').val(ticket.status);
        $('#ti-assign-analyst').val(ticket.assigned_to || '');
        
        // Carregar comentários
        loadComments(ticket.id);
        
        // Restaurar estrutura do modal se necessário
        restoreModalStructure();
    }
    
    function restoreModalStructure() {
        // Implementação da estrutura do modal caso tenha sido alterada
        var modalBody = $('#ti-ticket-modal .ti-modal-body');
        if (modalBody.find('.ti-ticket-details').length === 0) {
            location.reload(); // Simplificado - em produção seria melhor recriar a estrutura
        }
    }
    
    function loadComments(ticketId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_ticket_comments',
                ticket_id: ticketId,
                nonce: $('#ti-nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    renderComments(response.data.comments);
                }
            }
        });
    }
    
    function renderComments(comments) {
        var commentsHtml = '';
        
        if (comments.length === 0) {
            commentsHtml = '<div class="ti-no-comments">Nenhum comentário ainda.</div>';
        } else {
            comments.forEach(function(comment) {
                var internalBadge = comment.is_internal ? '<span class="ti-internal-badge">Interno</span>' : '';
                commentsHtml += '<div class="ti-comment">';
                commentsHtml += '<div class="ti-comment-header">';
                commentsHtml += '<strong>' + comment.user_name + '</strong>';
                commentsHtml += '<span class="ti-comment-date">' + formatDateTime(comment.created_at) + '</span>';
                commentsHtml += internalBadge;
                commentsHtml += '</div>';
                commentsHtml += '<div class="ti-comment-content">' + escapeHtml(comment.comment).replace(/\n/g, '<br>') + '</div>';
                commentsHtml += '</div>';
            });
        }
        
        $('#ti-comments-list').html(commentsHtml);
    }
    
    function updateTicketStatus() {
        if (!currentTicketId) return;
        
        var newStatus = $('#ti-new-status').val();
        var assignedTo = $('#ti-assign-analyst').val();
        var updateBtn = $('#ti-update-ticket');
        
        // Estado de loading
        updateBtn.prop('disabled', true).text('Atualizando...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'update_ticket_status',
                ticket_id: currentTicketId,
                status: newStatus,
                assigned_to: assignedTo,
                nonce: $('#ti-nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    showSuccess('Ticket atualizado com sucesso!');
                    
                    // Atualizar a linha da tabela
                    updateTicketRowInTable(currentTicketId, newStatus, assignedTo);
                    
                    // Recarregar dados do modal
                    loadTicketData(currentTicketId);
                } else {
                    showError('Erro ao atualizar ticket: ' + response.data);
                }
            },
            error: function() {
                showError('Erro de conexão ao atualizar ticket.');
            },
            complete: function() {
                updateBtn.prop('disabled', false).text('Atualizar Ticket');
            }
        });
    }
    
    function addTicketComment() {
        if (!currentTicketId) return;
        
        var comment = $('#ti-new-comment').val().trim();
        var isInternal = $('#ti-internal-comment').is(':checked');
        var addBtn = $('#ti-add-comment');
        
        if (!comment) {
            showError('Por favor, digite um comentário.');
            return;
        }
        
        // Estado de loading
        addBtn.prop('disabled', true).text('Adicionando...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'add_ticket_comment',
                ticket_id: currentTicketId,
                comment: comment,
                is_internal: isInternal ? 1 : 0,
                nonce: $('#ti-nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    showSuccess('Comentário adicionado com sucesso!');
                    
                    // Limpar formulário
                    $('#ti-new-comment').val('');
                    $('#ti-internal-comment').prop('checked', false);
                    
                    // Recarregar comentários
                    loadComments(currentTicketId);
                } else {
                    showError('Erro ao adicionar comentário: ' + response.data);
                }
            },
            error: function() {
                showError('Erro de conexão ao adicionar comentário.');
            },
            complete: function() {
                addBtn.prop('disabled', false).text('Adicionar Comentário');
            }
        });
    }
    
    function submitNewTicket() {
        var form = $('#ti-new-ticket-form');
        var submitBtn = $('#submit');
        var loading = $('#ti-form-loading');
        
        // Validação básica
        var title = $('#ticket-title').val().trim();
        var description = $('#ticket-description').val().trim();
        
        if (!title || !description) {
            showError('Por favor, preencha todos os campos obrigatórios.');
            return;
        }
        
        // Estado de loading
        submitBtn.prop('disabled', true);
        loading.show();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'create_ticket',
                title: title,
                category: $('#ticket-category').val(),
                priority: $('#ticket-priority').val(),
                description: description,
                nonce: $('#ti-nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data);
                    form[0].reset();
                    
                    // Redirecionar para lista de tickets após 2 segundos
                    setTimeout(function() {
                        window.location.href = 'admin.php?page=ti-tickets';
                    }, 2000);
                } else {
                    showError('Erro ao criar ticket: ' + response.data);
                }
            },
            error: function() {
                showError('Erro de conexão ao criar ticket.');
            },
            complete: function() {
                submitBtn.prop('disabled', false);
                loading.hide();
            }
        });
    }
    
    function applyFilters() {
        var statusFilter = $('#filter-status').val();
        var priorityFilter = $('#filter-priority').val();
        
        $('.ti-tickets-table tbody tr').each(function() {
            var row = $(this);
            var show = true;
            
            if (statusFilter && row.data('status') !== statusFilter) {
                show = false;
            }
            
            if (priorityFilter && row.data('priority') !== priorityFilter) {
                show = false;
            }
            
            row.toggle(show);
        });
        
        // Atualizar contador de resultados
        updateResultsCounter();
    }
    
    function updateResultsCounter() {
        var totalRows = $('.ti-tickets-table tbody tr').length;
        var visibleRows = $('.ti-tickets-table tbody tr:visible').length;
        
        // Adicionar ou atualizar contador se não existir
        if ($('.ti-results-counter').length === 0) {
            $('.ti-tickets-filters').append('<div class="ti-results-counter"></div>');
        }
        
        $('.ti-results-counter').text('Mostrando ' + visibleRows + ' de ' + totalRows + ' tickets');
    }
    
    function updateTicketRowInTable(ticketId, newStatus, assignedAnalystId) {
        var row = $('.ti-tickets-table tbody tr').filter(function() {
            return $(this).find('td:first').text().includes('#' + ticketId);
        });
        
        if (row.length > 0) {
            // Atualizar status
            row.attr('data-status', newStatus);
            row.find('.ti-status-badge').replaceWith(createStatusBadge(newStatus));
            
            // Atualizar analista se fornecido
            if (assignedAnalystId) {
                var analystSelect = $('#ti-assign-analyst');
                var analystName = analystSelect.find('option[value="' + assignedAnalystId + '"]').text();
                row.find('td').eq(5).text(analystName); // Coluna do analista
            }
        }
    }
    
    function createStatusBadge(status) {
        var colors = {
            'aberto': '#17a2b8',
            'em_andamento': '#ffc107',
            'aguardando_teste': '#fd7e14',
            'concluido': '#28a745',
            'cancelado': '#dc3545'
        };
        
        var labels = {
            'aberto': 'Aberto',
            'em_andamento': 'Em Andamento',
            'aguardando_teste': 'Aguardando Teste',
            'concluido': 'Concluído',
            'cancelado': 'Cancelado'
        };
        
        return '<span class="ti-status-badge" style="background-color: ' + 
               (colors[status] || '#6c757d') + '">' + 
               (labels[status] || status) + '</span>';
    }
    
    function createPriorityBadge(priority) {
        var colors = {
            'baixa': '#28a745',
            'media': '#ffc107',
            'alta': '#fd7e14',
            'urgente': '#dc3545'
        };
        
        var labels = {
            'baixa': 'Baixa',
            'media': 'Média',
            'alta': 'Alta',
            'urgente': 'Urgente'
        };
        
        return '<span class="ti-priority-badge" style="background-color: ' + 
               (colors[priority] || '#6c757d') + '">' + 
               (labels[priority] || priority) + '</span>';
    }
    
    function showSuccess(message) {
        showNotice(message, 'success');
    }
    
    function showError(message) {
        showNotice(message, 'error');
    }
    
    function showNotice(message, type) {
        // Remove notificações anteriores
        $('.ti-admin-notice').remove();
        
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ti-admin-notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notice);
        
        // Auto-remover após 5 segundos
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
        
        // Scroll para o topo para mostrar a notificação
        $('html, body').animate({ scrollTop: 0 }, 300);
    }
    
    function formatDateTime(dateString) {
        var date = new Date(dateString);
        var options = {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        return date.toLocaleString('pt-BR', options);
    }
    
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Auto-refresh para dashboard (apenas para supervisores)
    if ($('.ti-stats-cards').length > 0) {
        setInterval(function() {
            refreshDashboardStats();
        }, 300000); // Atualiza a cada 5 minutos
    }
    
    function refreshDashboardStats() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_dashboard_stats',
                nonce: $('#ti-nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    var stats = response.data;
                    $('.ti-stat-card').each(function() {
                        var card = $(this);
                        var statType = card.data('stat-type');
                        if (stats[statType] !== undefined) {
                            card.find('h3').text(stats[statType]);
                        }
                    });
                }
            }
        });
    }
    
    // Inicializar tooltips para badges
    $('.ti-status-badge, .ti-priority-badge').attr('title', function() {
        return $(this).text();
    });
    
    // Melhorar UX com confirmações
    $('.ti-delete-ticket').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Tem certeza que deseja excluir este ticket? Esta ação não pode ser desfeita.')) {
            return false;
        }
        // Implementar exclusão se necessário
    });
    
    // Atalhos de teclado
    $(document).on('keydown', function(e) {
        // Ctrl+N para novo ticket
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            window.location.href = 'admin.php?page=ti-new-ticket';
        }
        
        // Ctrl+F para focar no filtro
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            $('#filter-status').focus();
        }
    });
    
    // Adicionar campo de nonce se não existir
    if ($('#ti-nonce').length === 0) {
        $('body').append('<input type="hidden" id="ti-nonce" value="' + 
                        (window.tiTicketsNonce || '') + '">');
    }
    
    // Inicializar contador de resultados
    updateResultsCounter();
});

// Adicionar nonce global via localize_script no PHP
window.tiTicketsNonce = window.tiTicketsNonce || '';