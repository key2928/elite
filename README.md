# Elite Thai — Sistema de Gestão

Sistema de gerenciamento para academia de Muay Thai (Elite Thai / Konex).

## Tecnologias

- PHP 8+
- MySQL / MariaDB
- HTML/CSS (sem frameworks externos)

## Instalação

1. Importe o arquivo `schema.sql` no seu banco de dados MySQL.
2. Configure as credenciais do banco em `config.php`.
3. Acesse `login.php` no navegador.

## Acesso ao Admin

| Campo | Valor       |
|-------|-------------|
| Login | `konex`     |
| Senha | `konex2026` |

> **Recomendação:** Altere a senha do administrador após o primeiro acesso.

## Painéis disponíveis

| Painel       | Arquivo         | Tipos de usuário                          |
|--------------|-----------------|-------------------------------------------|
| Admin        | `admin.php`     | admin                                     |
| Treinador    | `treinador.php` | treinador, professor, instrutor           |
| Aluno        | `index.php`     | aluno                                     |
| Convite      | `convite.php`   | aluno (indicação de novos alunos)         |
