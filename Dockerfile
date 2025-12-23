FROM php:8.2-apache

RUN apt-get update && apt-get install -y libzip-dev zip libpng-dev libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite zip gd

RUN a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

WORKDIR /var/www/html

COPY . .

RUN chown -R www-data:www-data /var/www/html && chmod -R 775 /var/www/html/data

# Script d'entrypoint pour lancer les migrations, créer l'admin et mettre à jour la BD
RUN echo '#!/bin/bash\nset -e\nphp /var/www/html/bin/migrate.php\nif [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then\n  php /var/www/html/bin/create_admin.php "$ADMIN_EMAIL" "$ADMIN_PASSWORD"\nfi\nphp /var/www/html/bin/update_db.php\napache2-foreground' > /entrypoint.sh \
    && chmod +x /entrypoint.sh

EXPOSE 80

CMD ["/entrypoint.sh"]
