# 🐳 Guia Docker para Restaurante 2023

## Pré-requisitos

1. **Instalar Docker Desktop**
   - Windows: https://docs.docker.com/desktop/install/windows-install/
   - Linux: https://docs.docker.com/engine/install/
   - Mac: https://docs.docker.com/desktop/install/mac-install/

2. **Verificar instalação**
   ```bash
   docker --version
   docker-compose --version
   ```

## 🚀 Iniciar a Aplicação

### Opção 1: Com Docker Compose (Recomendado)

```bash
# 1. Navegar para a pasta do projeto
cd c:\Users\kalbe\Desktop\restaurante_2023

# 2. Iniciar containers
docker-compose up -d

# 3. Aguardar 30 segundos para banco de dados inicializar

# 4. Acessar a aplicação
# - App: http://localhost:8000
# - phpMyAdmin: http://localhost:8080
```

### Opção 2: Construir e Rodar Manualmente

```bash
# Build da imagem
docker build -t restaurante:latest .

# Executar container
docker run -p 8000:80 -v .:/var/www/html restaurante:latest
```

## 📊 Serviços Disponíveis

| Serviço | URL | Acesso |
|---------|-----|--------|
| **Aplicação** | http://localhost:8000 | - |
| **PhpMyAdmin** | http://localhost:8080 | root/root |
| **Banco de Dados** | localhost:3306 | restaurante/secret123 |

## 🛠️ Comandos Úteis

### Gerenciar Containers

```bash
# Ver containers rodando
docker-compose ps

# Ver logs
docker-compose logs -f app

# Parar containers
docker-compose stop

# Reiniciar
docker-compose restart

# Parar e remover (cuidado!)
docker-compose down

# Remover volumes também
docker-compose down -v
```

### Acessar Container

```bash
# Entrar no container da app
docker-compose exec app bash

# Executar comando PHP
docker-compose exec app php -v

# Instalar dependências (se necessário)
docker-compose exec app composer install
```

### Verificar Banco de Dados

```bash
# Acessar MySQL direto
docker-compose exec db mysql -u root -proot pweb_restaurante

# Ver status do banco
docker-compose exec db mysqladmin -u root -proot status
```

## 📝 Estrutura do Projeto no Docker

```
restaurante_2023/
├── app/                    # Código da aplicação
├── public/                 # Docroot do Apache
├── storage/logs/           # Logs (volume persistente)
├── Dockerfile              # Imagem Docker
├── docker-compose.yml      # Orquestração
├── composer.json           # Dependências PHP
└── projeto/                # Backup do banco
```

## ⚙️ Configuração do Banco de Dados

### Credenciais Padrão

```
Driver: mysql
Host: db (dentro do container) ou localhost (de fora)
Port: 3306
Database: pweb_restaurante
User: restaurante
Password: secret123
```

### Restaurar Backup

Se o arquivo `projeto/backup_pweb_restaurante.sql` existir, será importado automaticamente na inicialização.

Para importar manualmente:

```bash
docker-compose exec db mysql -u restaurante -psecret123 pweb_restaurante < projeto/backup_pweb_restaurante.sql
```

## 🐛 Troubleshooting

### Porta 8000/3306 em uso

```bash
# Mudança de porta no docker-compose.yml
# Altere de "8000:80" para "8001:80", por exemplo
```

### Permissão negada em storage/logs

```bash
docker-compose exec app chown -R www-data:www-data storage/logs
docker-compose exec app chmod -R 777 storage/logs
```

### Composer falhando

```bash
# Limpar cache
docker-compose exec app composer clear-cache

# Reinstalar
docker-compose exec app composer install
```

### Banco de dados não inicia

```bash
# Verificar logs
docker-compose logs db

# Remover volume e reconstruir
docker-compose down -v
docker-compose up -d
```

## 📦 Instalando Novas Dependências

```bash
# Adicionar pacote
docker-compose exec app composer require "vendor/package"

# Instalar todas as dependências
docker-compose exec app composer install
```

## 🔄 Atualizar Código

```bash
# Rebuild da imagem (se alterar Dockerfile)
docker-compose up -d --build

# Sem rebuild (apenas sincroniza volume)
docker-compose restart app
```

## 📊 Ver Estatísticas

```bash
# Uso de recursos
docker stats

# Tamanho das imagens
docker images

# Uso de espaço em disco
docker system df
```

## 🗑️ Limpeza

```bash
# Remover containers parados
docker container prune

# Remover imagens não usadas
docker image prune

# Limpeza completa
docker system prune -a
```

## 🔐 Segurança

### Alterar Senhas

Edite `docker-compose.yml`:

```yaml
environment:
  MYSQL_ROOT_PASSWORD: sua_senha_forte
  MYSQL_PASSWORD: sua_senha_forte
```

Depois:

```bash
docker-compose down -v
docker-compose up -d
```

## 📚 Recursos Adicionais

- [Documentação Docker](https://docs.docker.com/)
- [Docker Compose Reference](https://docs.docker.com/compose/compose-file/)
- [MySQL Docker Hub](https://hub.docker.com/_/mysql)
- [PHP Docker Hub](https://hub.docker.com/_/php)

---

**Desenvolvido em**: 28 de Dezembro de 2025
