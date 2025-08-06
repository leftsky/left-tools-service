FROM leftsky/php:8.3
LABEL maintainer="leftsky <leftsky@vip.qq.com>"

COPY ./ /var/www
COPY ./supervisord.conf /etc/supervisord.conf

RUN apk add ffmpeg
RUN rm -rf composer.lock
RUN chmod -R 777 /var/www/storage
#RUN composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
#RUN composer config -g repos.packagist composer https://mirrors.tencent.com/composer/
RUN composer install --no-dev
RUN php artisan telescope:install

# 生成API文档
RUN php artisan l5-swagger:generate

RUN echo "* * * * * php /var/www/artisan schedule:run >> /dev/null 2>&1" >> /etc/crontabs/root
#RUN php artisan key:generate
RUN php artisan storage:link
