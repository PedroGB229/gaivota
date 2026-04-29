FROM php:8.5.5-alpine3.23

# Instalar dependências do sistema
RUN apk add --no-cache \
    nginx \
    postgresql-dev \
    postgresql-client \
    redis \
    git \
    curl \
    libzip-dev \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    autoconf \
    g++ \
    make \
    docker-php-ext-install \
    docker-php-ext-enable

# Instalar extensões do PHP
RUN docker-php-ext-install pdo pdo_pgsql pgsql zip

# Configurar Nginx
COPY nginx.conf /etc/nginx/http.d/default.conf

# Definir diretório de trabalho
WORKDIR /var/www/html

# Expor porta
EXPOSE 80

# Comando para iniciar serviços
CMD ["sh", "-c", "nginx && php-fpm"]