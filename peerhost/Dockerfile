# Optimized single-stage production Dockerfile
# Uses multi-stage concept but since no composer deps, combines into efficient single stage
FROM php:8.2-apache

# Install all system deps and PHP extensions in ONE layer to minimize image size
RUN apt-get update && apt-get install -y --no-install-recommends \
    apache2 \
    apache2-utils \
    libcurl4-openssl-dev \
    libzip-dev \
    zlib1g-dev \
    libonig-dev \
    libxml2-dev \
    curl \
    && docker-php-ext-install curl zip mbstring xml \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
           /var/cache/apt/archives/* \
           /tmp/* \
           /var/tmp/*

# Copy application with correct ownership
COPY --chown=www-data:www-data peerhost/ /var/www/html/libremesh/

# Create data directories
RUN mkdir -p /var/www/html/libremesh/data/archives \
    && chown -R www-data:www-data /var/www/html/libremesh/data

EXPOSE 80
CMD ["apache2-foreground"]