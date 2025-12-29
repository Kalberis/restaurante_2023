FROM php:8.2-apache

# Instala extensões necessárias
RUN apt-get update && apt-get install -y \
    libpq-dev \
    default-mysql-client \
    git \
    curl \
    unzip \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Instala Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Define diretório de trabalho
WORKDIR /var/www/html

# Copia arquivos do projeto
COPY . .

# Instala dependências do Composer
RUN composer install --no-interaction --optimize-autoloader

# Cria diretório de logs
RUN mkdir -p storage/logs && chmod -R 777 storage/logs

# Configura DocumentRoot do Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Permissões
RUN chown -R www-data:www-data /var/www/html

# Expõe porta
EXPOSE 80

# Inicia Apache
CMD ["apache2-foreground"]
