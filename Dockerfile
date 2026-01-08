FROM php:8.2-apache

# Install system dependencies for PostgreSQL
RUN apt-get update && apt-get install -y libzip-dev zip libpng-dev libsqlite3-dev libpq-dev \
    && docker-php-ext-install pdo pdo_sqlite pdo_pgsql zip gd

RUN a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

WORKDIR /var/www/html

COPY . .

# Script d'entrypoint pour lancer les migrations, créer l'admin, mettre à jour la BD, et appliquer les permissions
RUN echo '#!/bin/bash\nset -e\n\n# Detect database driver and run appropriate migrations\nif [ "$DB_DRIVER" = "pgsql" ]; then\n  echo "[info] Using PostgreSQL database"\n  php /var/www/html/bin/migrate_pg.php\nelse\n  echo "[info] Using SQLite database"\n  php /var/www/html/bin/migrate.php\nfi\n\nif [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then\n  php /var/www/html/bin/create_admin.php "$ADMIN_EMAIL" "$ADMIN_PASSWORD"\nfi\n\nphp /var/www/html/bin/update_db.php\n\nchown -R www-data:www-data /var/www/html/data /var/www/html/cache /var/www/html/public/pdfs 2>/dev/null || true\nchmod -R 775 /var/www/html/data /var/www/html/cache /var/www/html/public/pdfs 2>/dev/null || true\n\napache2-foreground' > /entrypoint.sh \
    && chmod +x /entrypoint.sh

EXPOSE 80

CMD ["/entrypoint.sh"]
