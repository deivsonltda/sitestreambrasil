# PHP 8.3 + Nginx (Alpine) in one container
FROM php:8.3-fpm-alpine

# System deps
RUN apk add --no-cache \
    nginx \
    supervisor \
    bash \
    curl \
    tzdata \
    icu-dev \
    oniguruma-dev \
    libzip-dev \
    && docker-php-ext-install \
      intl \
      mbstring \
      zip \
      opcache

# Optional: set timezone (change if you want)
ENV TZ=America/Recife

# Workdir
WORKDIR /var/www/app

# Copy project
COPY . /var/www/app

# Nginx config
RUN mkdir -p /run/nginx /var/log/supervisor /var/lib/nginx/tmp /var/www/app/_deploy
COPY _deploy/nginx.conf /etc/nginx/nginx.conf

# PHP-FPM basic tuning (optional)
RUN { \
  echo "opcache.enable=1"; \
  echo "opcache.enable_cli=0"; \
  echo "opcache.memory_consumption=128"; \
  echo "opcache.interned_strings_buffer=16"; \
  echo "opcache.max_accelerated_files=10000"; \
  echo "opcache.validate_timestamps=1"; \
  echo "opcache.revalidate_freq=2"; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Permissions (adjust if needed)
RUN chown -R www-data:www-data /var/www/app \
  && chmod -R 755 /var/www/app

# Supervisor config
RUN printf "%s\n" \
"[supervisord]" \
"nodaemon=true" \
"logfile=/var/log/supervisor/supervisord.log" \
\
"[program:php-fpm]" \
"command=php-fpm -F" \
"autostart=true" \
"autorestart=true" \
"stdout_logfile=/dev/stdout" \
"stdout_logfile_maxbytes=0" \
"stderr_logfile=/dev/stderr" \
"stderr_logfile_maxbytes=0" \
\
"[program:nginx]" \
"command=nginx -g 'daemon off;'" \
"autostart=true" \
"autorestart=true" \
"stdout_logfile=/dev/stdout" \
"stdout_logfile_maxbytes=0" \
"stderr_logfile=/dev/stderr" \
"stderr_logfile_maxbytes=0" \
> /etc/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]