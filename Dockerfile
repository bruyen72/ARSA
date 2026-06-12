FROM php:8.2-apache

# Extensão SQLite (banco usado pelo site)
RUN docker-php-ext-install pdo pdo_sqlite \
    && a2enmod rewrite \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copia o código do site para dentro do container
COPY . /var/www/html/

# Permissão de escrita para o banco (data/) e uploads (uploads/)
RUN chown -R www-data:www-data /var/www/html/data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/data /var/www/html/uploads

# Script que ajusta a porta do Apache conforme a variável $PORT do Render
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 10000
ENTRYPOINT ["/entrypoint.sh"]
