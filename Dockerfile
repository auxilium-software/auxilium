FROM debian:bookworm
WORKDIR /app

SHELL ["/bin/bash", "-c"]

RUN apt-get update
RUN apt-get -y install supervisor wget grep curl openjdk-17-jre-headless
RUN apt-get -y install apache2 apache2-utils libapache2-mod-php php-gd php-mysql mariadb-server mariadb-client php-simplexml php-mysql php-curl php-bcmath php-json php-imap php-mbstring
RUN apt-get -y install composer ssl-cert git jq
RUN apt-get -y install iputils-ping

RUN export PHP_VER=`dpkg -l 'php*' | grep ^ii | grep -oP "php[0-9]+\\.[0-9]*" | cut -c 4- | head -1 | tr -d $'\n'`; a2enmod php$PHP_VER;
RUN a2enmod headers
RUN a2enmod rewrite
RUN a2enmod ssl
RUN a2enmod mime
RUN chmod +x /etc/apache2/envvars

RUN useradd deegraph -d /store/deegraph




RUN rm /etc/apache2/sites-enabled/*
COPY config/apache2 /etc/apache2

COPY config/php.ini /etc/php/php.ini.tmp
RUN export PHP_VER=`dpkg -l 'php*' | grep ^ii | grep -oP "php[0-9]+\\.[0-9]*" | cut -c 4- | head -1 | tr -d $'\n'`; mv /etc/php/php.ini.tmp /etc/php/$PHP_VER/apache2/php.ini;

COPY Auxilium/composer.json /var/www/composer.json
RUN chown www-data:www-data /var/www -R

WORKDIR /var/www
USER www-data

RUN composer config allow-plugins.endroid/installer true
RUN composer install

COPY Auxilium /var/www

COPY templates/Environment.php /var/www/Configuration/Configuration/Environment.php

USER root
WORKDIR /app

COPY scripts/new-keys.php /app/new-keys.php

COPY config/mariadb-50-server.cnf /etc/mysql/mariadb.conf.d/50-server.cnf
COPY config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY bin/deegraph.jar /app/deegraph.jar
COPY config/launch.sh /app/launch.sh

RUN chmod +x /app/deegraph.jar
RUN chmod +x /app/launch.sh

RUN mkdir /var/ecs
RUN chown www-data:www-data /var/ecs -R

RUN mkdir /store
RUN chown www-data:www-data /store -R

RUN usermod -d /var/lib/mysql/ mysql

ENTRYPOINT ["/usr/bin/supervisord"]

STOPSIGNAL SIGQUIT
