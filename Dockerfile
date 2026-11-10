FROM php:8.4.27-cli-alpine
RUN apk upgrade --no-cache
RUN printf '%s\n' 'session.upload_progress.enabled=0' > /usr/local/etc/php/conf.d/zz-appjet-security.ini
WORKDIR /app
COPY . .
ENV PORT=3000
EXPOSE 3000
CMD php -S 0.0.0.0:"$PORT"
