FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql \
    && a2enmod headers
WORKDIR /var/www/html/gorepa_blockchain
COPY . .
COPY deploy/database.php config/database.php
RUN mkdir -p storage/documentos \
    && printf 'Require all denied\n' > storage/.htaccess \
    && chown -R www-data:www-data storage \
    && printf '<Directory /var/www/html>\nAllowOverride All\n</Directory>\nRedirectMatch ^/$ /gorepa_blockchain/\n<Directory /var/www/html/gorepa_blockchain/deploy>\nRequire all denied\n</Directory>\n' > /etc/apache2/conf-enabled/gorepa.conf \
    && printf 'upload_max_filesize=5M\npost_max_size=6M\ndisplay_errors=Off\nlog_errors=On\n' > /usr/local/etc/php/conf.d/gorepa.ini
ENV PORT=10000
CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
