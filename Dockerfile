FROM debian:bookworm
WORKDIR /app

SHELL ["/bin/bash", "-c"]

# install packages
RUN apt-get update
RUN apt-get -y install supervisor wget grep curl openjdk-17-jre-headless
RUN apt-get -y install nginx libapache2-mod-php php-gd php-mysql mariadb-server mariadb-client php-simplexml php-mysql php-curl php-bcmath php-json php-imap php-mbstring php-zip
RUN apt-get -y install composer ssl-cert git jq
RUN apt-get -y install iputils-ping

#RUN export PHP_VER=`dpkg -l 'php*' | grep ^ii | grep -oP "php[0-9]+\\.[0-9]*" | cut -c 4- | head -1 | tr -d $'\n'`; a2enmod php$PHP_VER;
#RUN a2enmod headers
#RUN a2enmod rewrite
#RUN a2enmod ssl
#RUN a2enmod mime
#RUN chmod +x /etc/apache2/envvars

RUN useradd deegraph -d /store/deegraph



# copy over the nginx config
RUN rm /etc/nginx/sites-enabled/*
RUN rm /etc/nginx/sites-available/*
COPY Config/nginx /etc/nginx

# copy over the php config
COPY Config/php.ini /etc/php/php.ini.tmp
RUN export PHP_VER=`dpkg -l 'php*' | grep ^ii | grep -oP "php[0-9]+\\.[0-9]*" | cut -c 4- | head -1 | tr -d $'\n'`; mv /etc/php/php.ini.tmp /etc/php/$PHP_VER/apache2/php.ini;

# copy over composer config
COPY Auxilium/composer.json /srv/Auxilium/composer.json

# set web perms on the auxilium directory
RUN chown www-data:www-data /srv/Auxilium -R

# cd & su
WORKDIR /srv/Auxilium
USER www-data

# install composer packages
RUN composer config allow-plugins.endroid/installer true
RUN composer install

COPY Auxilium /srv/Auxilium

COPY ConfigTemplates/Environment.php /srv/Auxilium/Configuration/Configuration/Environment.php

USER root
WORKDIR /app

COPY Scripts/new-keys.php /app/new-keys.php

COPY Config/mariadb-50-server.cnf /etc/mysql/mariadb.conf.d/50-server.cnf
COPY Config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY bin/deegraph.jar /app/deegraph.jar
COPY Config/launch.sh /app/launch.sh

RUN chmod +x /app/deegraph.jar
RUN chmod +x /app/launch.sh

RUN mkdir /var/ecs
RUN chown www-data:www-data /var/ecs -R

RUN mkdir /store
RUN chown www-data:www-data /store -R

RUN usermod -d /var/lib/mysql/ mysql

ENTRYPOINT ["/usr/bin/supervisord"]

STOPSIGNAL SIGQUIT
