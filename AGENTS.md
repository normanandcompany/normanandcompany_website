# Norman and Company Website Instructions

## Project stack

- PHP
- HTML5
- CSS
- JavaScript
- AJAX
- MariaDB
- Apache

## Development workflow

- Development is performed locally.
- The local Git repository is the primary development copy.
- Tested changes are pushed to the private GitHub repository.
- Only tested and approved changes should be deployed to normanandcompany.com.
- Do not edit the production website directly unless specifically instructed.

## Security requirements

- Never display, commit, or push passwords, API keys, database credentials, authentication tokens, or SMTP credentials.
- Never add .env or private configuration files to Git.
- Use prepared statements for database queries.
- Do not run destructive database commands without explicit approval.
- Do not modify .gitignore to expose protected files.

## Code requirements

- Preserve the existing project structure unless a change is necessary.
- Review related files before changing shared functionality.
- Keep PHP, HTML, CSS, and JavaScript readable and maintainable.
- Maintain compatibility with MariaDB.
- Explain significant architectural changes before implementing them.
- Avoid unnecessary frameworks or dependencies.
- Do not remove working functionality unless specifically instructed.

## Git requirements

- Review git status before beginning work.
- Do not commit or push unless specifically instructed.
- Do not force-push.
- Do not rewrite Git history.
- Report every file created, modified, renamed, or deleted.
- Provide a suggested commit message after completing changes.
