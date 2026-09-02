FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install mbstring \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/* \
    && printf '%s\n' \
        'upload_max_filesize=9M' \
        'post_max_size=64M' \
        'max_file_uploads=20' \
        'expose_php=Off' \
        'session.cookie_httponly=1' \
        'session.cookie_samesite=Lax' \
        > /usr/local/etc/php/conf.d/maringotka.ini \
    && printf '%s\n' \
        '<Directory /var/www/html>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        'SetEnvIf Request_URI "^/" MARINGOTKA_LOCAL_PREVIEW=1' \
        > /etc/apache2/conf-available/maringotka-local-preview.conf \
    && a2enconf maringotka-local-preview

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .

RUN mkdir -p data uploads/gallery \
    && chown -R www-data:www-data data uploads/gallery

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php -r '$socket = @fsockopen("127.0.0.1", 80, $errno, $error, 3); exit($socket ? 0 : 1);'

CMD ["apache2-foreground"]
