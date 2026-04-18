# Stage 1: Builder - Install dependencies and verify
FROM php:8.2-cli AS builder

# Install build dependencies in single layer
RUN apt-get update && apt-get install -y --no-install-recommends \
    libcurl4-openssl-dev \
    libonig-dev \
    libxml2-dev \
    apache2-dev \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Pre-install PHP extensions
RUN docker-php-ext-install curl mbstring xml

# Stage 2: Production runtime
FROM php:8.2-apache

# Copy only required files from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/ /usr/local/etc/php/

# Install minimal runtime dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    apache2 \
    apache2-utils \
    curl \
    libonig5 \
    libxml2 \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && a2enmod rewrite headers \
    && apt-get clean && rm -rf /var/cache/apt/archives/*

# Copy application with correct ownership
COPY --chown=www-data:www-data gateway/ /var/www/html/

# Create data directories with correct permissions
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data

# Expose port
EXPOSE 80

# Use exec form to avoid shell overhead
CMD ["apache2-foreground"]