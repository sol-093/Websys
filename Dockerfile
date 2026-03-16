FROM php:8.4-cli

# Install MySQL PDO driver required by src/core/db.php
RUN docker-php-ext-install pdo_mysql

WORKDIR /app
COPY . /app

# Ensure runtime-writable paths exist in container.
RUN mkdir -p /app/public/uploads /app/storage \
    && chmod -R 0777 /app/public/uploads /app/storage

ENV PORT=8080
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t ."]
