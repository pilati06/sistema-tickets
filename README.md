# Sistema de Tickets TI - WordPress Plugin

## Instalação

1. Faça upload dos arquivos do plugin para a pasta `/wp-content/plugins/ti-tickets/`
2. Ative o plugin através do painel administrativo do WordPress
3. O plugin criará automaticamente as tabelas necessárias no banco de dados

## Estrutura de Arquivos

```
ti-tickets/
├── ti-tickets-plugin.php (arquivo principal)
├── templates/
│   ├── admin-tickets.php
│   ├── dashboard.php
│   ├── my-tickets.php
│   ├── my-tickets-shortcode.php
│   ├── new-ticket.php
│   └── ticket-form.php
└── assets/
    ├── ti-tickets.css (seus arquivos CSS)
    ├── ti-tickets.js (seus arquivos JS)
    ├── ti-tickets-admin.css
    └── ti-tickets-admin.js
```

## Configuração Inicial

### 1. Criar Usuários de TI

Após a instalação, crie usuários com as seguintes roles:

- **Supervisor de TI**: Role `ti_supervisor` - pode gerenciar todos os tickets
- **Analista de TI**: Role `ti_analyst` - pode gerenciar tickets atribuídos

### 2. Capabilities (Permissões)

- `manage_ti_tickets`: Gerenciar todos os tickets
- `view_all_tickets`: Ver todos os tickets
- `assign_tickets`: Atribuir tickets
- `update_ticket_status`: Atualizar status de tickets
- `comment_on_tickets`: Comentar em tickets
- `manage_assigned_tickets`: Gerenciar tickets atribuídos

## Uso

### Menu Administrativo

O plugin adiciona um menu "Tickets TI" com:

- **Dashboard**: Visão geral do sistema
- **Todos os Tickets**: Lista completa (apenas supervisores)
- **Meus Tickets**: Tickets do usuário atual
- **Novo Ticket**: Formulário para criar tickets

### Shortcodes Disponíveis

1. **[ti_ticket_form]** - Formulário para criar tickets no frontend
2. **[ti_my_tickets]** - Lista dos tickets do usuário logado

### Status dos Tickets

- **Aberto**: Ticket recém-criado
- **Em Andamento**: Sendo trabalhado por um analista
- **Aguardando Teste**: Implementação pronta para teste
- **Concluído**: Ticket finalizado
- **Cancelado**: Ticket cancelado

### Prioridades

- **Baixa**: Solicitações não urgentes
- **Média**: Solicitações normais
- **Alta**: Problemas que impactam o trabalho
- **Urgente**: Problemas críticos

### Categorias

- Hardware
- Software
- Rede/Internet
- E-mail
- Sistema
- Desenvolvimento
- Manutenção
- Outro

## Funcionalidades

### Para Usuários Finais
- Criar tickets via frontend ou admin
- Acompanhar status dos seus tickets
- Ver comentários e atualizações
- Receber notificações por email

### Para Analistas
- Ver tickets atribuídos
- Atualizar status
- Adicionar comentários
- Dashboard com tickets prioritários

### Para Supervisores
- Dashboard completo com estatísticas
- Gerenciar todos os tickets
- Atribuir tickets para analistas
- Gerar relatórios
- Exportar dados para CSV
- Comentários internos (não visíveis aos solicitantes)

## Notificações por Email

O sistema envia emails automaticamente para:
- Solicitantes quando há mudança de status
- Supervisores quando um novo ticket é criado
- Analistas quando um ticket é atribuído

## Relatórios Disponíveis

- Resumo por Status
- Resumo por Prioridade  
- Resumo por Categoria
- Performance dos Analistas

## Banco de Dados

O plugin cria duas tabelas:

### wp_ti_tickets
- Dados principais dos tickets
- Relacionamento com usuários WordPress

### wp_ti_ticket_comments
- Comentários dos tickets
- Suporte a comentários internos

## Customização

O plugin foi desenvolvido com templates separados para facilitar customizações. Os arquivos CSS e JS podem ser editados conforme necessário.

## Suporte

Para suporte, verifique:
1. Se todos os arquivos foram enviados corretamente
2. Se as permissões de usuário estão configuradas
3. Se os emails estão funcionando no WordPress
4. Logs de erro do WordPress para debugging

## Atualizações Futuras

Funcionalidades que podem ser implementadas:
- Sistema de anexos
- Integração com sistemas externos
- API REST
- Notificações push em tempo real
- Campos customizados
- SLA (Service Level Agreement)
- Base de conhecimento integrada