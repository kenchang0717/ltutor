FROM php:8.2-apache

# 安裝系統依賴和 PHP 擴展
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libicu-dev \
    libjpeg-dev \
    libpng-dev \
    libfreetype6-dev \
    libwebp-dev \
    zip unzip \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install \
        pdo_mysql \
        mysqli \
        zip \
        intl \
        gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 安裝 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 啟用 Apache 模組
RUN a2enmod rewrite

# 設定工作目錄
WORKDIR /var/www/html

# 複製並安裝 PHP 依賴
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --verbose

# 複製應用程式檔案
COPY . .

# 設定權限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/writable

# 複製 Apache 配置
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# 安裝 cron 和 nano 等
RUN apt-get update && apt-get install -y cron nano curl

# 複製 crontab 檔案
COPY mycron /etc/cron.d/mycron

# 設定 cron 檔案權限
RUN chmod 0644 /etc/cron.d/mycron && \
    crontab /etc/cron.d/mycron

COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
