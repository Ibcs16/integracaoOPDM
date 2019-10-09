# integracaoOPDM
EN-US:
Api integration between OpenProject (https://www.openproject.org/) and DeskManager (https://deskmanager.com.br/).

This project is designed to be run via cron job, and do mainly 2 things: 

- Create workpackage (tasks) in openproject upon newly created client service requests from deskmanager
- Change client service status based on OpenProject workpackages' status.

PR-BR:
Integração entre chamados do DeskManager e tarefas no OpenProject (gerenciador de projetos)

O projeto foi desenvolvido para ser rodado em um cron, e possui duas ações principais:

- Criar workpackage (tarefa) no OpenProject após criação de novos chamados no DeskManager.
- Mudar o status do chamado baseado no status do workpackage do OpenProject.
