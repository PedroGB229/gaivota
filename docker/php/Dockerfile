<<<<<<< Updated upstream
# ============================================================================
# Estágio 1: Build de assets frontend (Node.js)
# Vite 8.0.10 exige Node 20.19+ ou 22.12+ — usamos LTS atual (22)
# alpine3.21 alinhado com seu PHP em alpine3.23 (ambos musl libc compatíveis)
# ============================================================================
FROM node:22-alpine3.21 AS assets

WORKDIR /build

# Copia primeiro só metadados — maximiza cache de layer Docker.
# Se package.json não mudou, npm ci é reutilizado de builds anteriores.
COPY package.json package-lock.json ./

# npm ci (não npm install) — build determinístico a partir do lockfile.
# --no-audit --no-fund acelera CI sem comprometer segurança (rode 'npm audit'
# em pipeline separado se quiser auditar).
RUN npm ci --no-audit --no-fund

# Copia config + fontes
COPY vite.config.js ./
COPY resources ./resources

# Build de produção — Rolldown gera public/assets/ com hashes
RUN npm run build

# ============================================================================
# Estágio 2: Runtime PHP (Alpine + PHP-FPM 8.5.4)
# ============================================================================
FROM php:8.5.4-fpm-alpine3.23

ENV TZ=${TZ} \
    COMPOSER_ALLOW_SUPERUSER=1

=======
# Define a imagem base do container
# PHP 8.5.2 com PHP-FPM, baseada em Alpine Linux (leve e eficiente)
FROM php:8.5.4-fpm-alpine3.23

# Define variáveis de ambiente globais do container
# TZ ajusta o fuso horário
# COMPOSER_ALLOW_SUPERUSER permite executar o Composer como root dentro do container
ENV TZ=${TZ} \
    COMPOSER_ALLOW_SUPERUSER=1

# Instala dependências do sistema operacional usando o gerenciador apk (Alpine)
# --no-cache evita armazenar cache local, reduzindo o tamanho da imagem final
>>>>>>> Stashed changes
RUN apk add --no-cache \
    bash \
    git \
    unzip \
    icu-dev \
    postgresql-dev \
    oniguruma-dev \
    tzdata

<<<<<<< Updated upstream
=======
# Compila e instala extensões PHP nativas necessárias para a aplicação
# PCOV instalado via pecl para cobertura de código nos testes (sem ext-uopz)
>>>>>>> Stashed changes
RUN apk add --no-cache $PHPIZE_DEPS \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    postgresql-dev \
    icu-dev \
    oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_pgsql \
    intl \
    mbstring \
    gd \
    && pecl install pcov \
    && docker-php-ext-enable pcov

<<<<<<< Updated upstream
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copia o código da aplicação PHP
COPY . .

# Copia APENAS os assets compilados do estágio 1 — sobrescreve public/assets/
# A imagem final NÃO contém Node, npm, /build/, nem resources/.
COPY --from=assets /build/public/assets ./public/assets

EXPOSE 9000
=======
# Copia o arquivo de configuração customizado do PHP para o container
# Ele será carregado automaticamente pelo PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Copia o binário do Composer a partir da imagem oficial
# Evita instalar o Composer manualmente e reduz complexidade
COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

# Define o diretório de trabalho padrão dentro do container
# Todos os comandos seguintes serão executados a partir desse diretório
WORKDIR /var/www

# Copia todo o código da aplicação para dentro do container
COPY . .

# Expõe a porta 9000, utilizada pelo PHP-FPM
# Essa porta será acessada pelo Nginx via rede Docker
EXPOSE 9000

# Comando padrão executado ao iniciar o container
# Inicia o PHP-FPM
>>>>>>> Stashed changes
CMD ["php-fpm"]