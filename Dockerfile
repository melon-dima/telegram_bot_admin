FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    curl \
    ca-certificates \
    libcurl4-openssl-dev \
    libzip-dev \
    libpq-dev \
    unzip \
    nginx \
    supervisor \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    nodejs \
    npm && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql zip gd bcmath curl

RUN usermod -u 1000 www-data
RUN groupmod -g 1000 www-data

# Composer
RUN curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php && \
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    rm -f /tmp/composer-setup.php && \
    composer --version

# Копируем конфигурацию Nginx (относительно папки docker в контексте ../)
COPY nginx.conf /etc/nginx/conf.d/localhost.conf

# Копируем конфигурацию Supervisor (относительно папки docker в контексте ../)
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf


# Копируем entrypoint-скрипт запуска
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

# Задаем рабочую директорию
WORKDIR /var/www/html

# Runtime-шаги выполняем в entrypoint после старта контейнера
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]