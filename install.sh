#!/bin/bash

BOLD_F=$(tput bold)
NORMAL_F=$(tput sgr0)
UNDERLINE_F=$(tput smul)
INV_F=$(tput rev)


function dockerVolumeExists {
    if [ "$(docker volume ls -f name=$1 | awk '{print $NF}' | grep -E '^'$1'$')" ]; then
        return 0
    else
        return 1
    fi
}

function showHelp {
    # Display Help
    echo "Installs and builds Auxilium with given options for testing and deployment"
    echo
    echo "Syntax: ./install.sh [options]"
    echo "-h, --help                Print this Help."
    echo "-l, --local               Install locally. THIS WILL MODIFY YOUR HOST."
    echo "-b, --build-only          Build only, do not test."
    echo "-n <hostname>,            Set the fully qualified domain name the"
    echo "    --hostname <name>     server will self-identify as."
    echo "-i <name>,                Set the identifier name of the container."
    echo "    --identifier <name>   "
    echo "-c <dir>, --certs <dir>   Point to real certificates, and don't "
    echo "    create self-signed certs."
    echo "-y                        Skip questions."
}

if [ ! -d bin ]; then
    mkdir bin
fi

echo ""
tput setaf 4
tput bold
echo "H4sICAd/eWYAA2FzY2lpLWFydC50eHQAfZCxDcMwDAR7TfFFiqTiBtnAXUoBXITDh3xaoiAoeYAvyy8daQPyoHCUh6NamCpwPmuqYp7Fema5np/r1YyEjhtdTeaTodrW2xAXElLJYl/cl3KKpUbu02MYEfkxdcRnIljwG5Fz94FYgkTgHeb1BzGtbeNn8/iGju165TuC/5velZLcxVZsqS2f1r72zncjBAIAAA==" | base64 -d | gzip -dc
tput sgr0

echo ""
echo "$(tput bold)Welcome to the Auxilium docker package build tool$(tput sgr0)"
echo ""

#echo "Writing default config files"
HOSTNAME=$(hostname --fqdn)
INSTANCE_IDENITIFIER=$(echo $HOSTNAME | cut -d"." -f1)
INSTALL_ID=$(cat /dev/urandom | base32 | cut -c-16 | head -n 1)
HTTP_PORT=8080
HTTPS_PORT=8081
DEEGRAPH_PORT=8880
CERT_LOC=$(pwd)/certs
CREATE_SSC=1
INSTALL=1
LOCAL_INSTALL=0
SKIP_QUESTIONS=0
PREEXISTING_VOLUME=0

# Transform long options to short ones
for arg in "$@"; do
    shift
    case "$arg" in
        '--help')           set -- "$@" '-h'   ;;
        '--hostname')       set -- "$@" '-n'   ;;
        '--identifier')     set -- "$@" '-i'   ;;
        '--build-only')     set -- "$@" '-b'   ;;
        '--local')          set -- "$@" '-l'   ;;
        '--certs')          set -- "$@" '-c'   ;;
        *)                  set -- "$@" "$arg" ;;
    esac
done

OPTIND=1
while getopts "hylbi:n:c:" opt; do
    case $opt in
        h) # display Help
            showHelp
            exit;;
        b) # Enter a name
            LOCAL_INSTALL=0
            INSTALL=0;;
        y) # Enter a name
            SKIP_QUESTIONS=1;;
        l) # Enter a name
            LOCAL_INSTALL=1;;
        i) # Enter a name
            INSTANCE_IDENITIFIER=$OPTARG;;
        n) # Enter a name
            HOSTNAME=$OPTARG;;
        c)
            CERT_LOC=$OPTARG
            CREATE_SSC=0;;
        \?) # Invalid option
            echo "Error: Invalid option"
            exit;;
    esac
done
shift $(expr $OPTIND - 1) # remove options from positional parameters

if [ "$INSTALL" -eq 1 ]; then
    if [ "$LOCAL_INSTALL" -eq 0 ]; then
        if dockerVolumeExists auxilium-volume-$INSTANCE_IDENITIFIER; then
            PREEXISTING_VOLUME=1
        fi
    fi
fi


tput bold
tput rev
if [ "$INSTALL" -eq 1 ]; then
    echo "About to build and install Auxilium with the following options:"
    tput sgr0
    echo ""
    echo "External hostname is $HOSTNAME"
    echo "Instance identifier is $INSTANCE_IDENITIFIER"
    echo "HTTP port on $HTTP_PORT"
    echo "HTTPS port on $HTTPS_PORT"
    echo "Deegraph server on port $DEEGRAPH_PORT"
    if [ "$PREEXISTING_VOLUME" -eq 1 ]; then
        echo "Reinstalling with existing user data"
    else
        echo "Creating a new instance"
    fi
    if [ "$CREATE_SSC" -eq 1 ]; then
        echo "Creating new self-signed certs for development purposes"
    else
        echo "Using SSL certificates at $CERT_LOC"
    fi
    if [ "$LOCAL_INSTALL" -eq 1 ]; then
        echo ""
        tput bold
        tput setaf 1
        tput rev
        echo "ABOUT TO INSTALL TO HOST MACHINE"
        echo "THIS MAY BREAK YOUR APACHE/MYSQL CONFIGURATION"
        tput sgr0
    fi
else
    echo "About to build Auxilium"
    tput sgr0
    CREATE_SSC=0
    SKIP_QUESTIONS=1
fi

if [ "$SKIP_QUESTIONS" -eq 0 ]; then
    echo ""
    tput bold
    read -p "Do you want to continue with the installation? [Y/n] " CONFIRM_VALUES
    tput sgr0

    if [[ $CONFIRM_VALUES = [Nn] ]]; then
        echo "Aborting installation"
        exit
    fi
fi

if [ "$LOCAL_INSTALL" -eq 1 ]; then
    echo ""
    tput bold
    echo "Installing dependencies"
    tput sgr0
    
    sudo apt-get update
    sudo apt-get -y install supervisor wget grep curl openjdk-17-jre-headless
    sudo apt-get -y install apache2 apache2-utils libapache2-mod-php php-gd php-mysql mariadb-server mariadb-client php-simplexml php-mysql php-curl php-bcmath php-json php-imap php-mbstring
    sudo apt-get -y install composer ssl-cert git jq
    
    export PHP_VER=`dpkg -l 'php*' | grep ^ii | grep -oP "php[0-9]+\\.[0-9]*" | cut -c 4- | head -1 | tr -d $'\n'`
    sudo a2enmod php$PHP_VER
    sudo a2enmod headers
    sudo a2enmod rewrite
    sudo a2enmod ssl
    sudo a2enmod mime
    sudo mkdir /etc/deegraph
    sudo mkdir /etc/deegraph/store
    sudo mkdir /opt/deegraph
    
    sudo useradd deegraph -d /etc/deegraph/store
    sudo chown deegraph -R /etc/deegraph
    
    sudo rm /etc/apache2/sites-enabled/*
    sudo cp -r config/apache2/* /etc/apache2/

    sudo cp config/php.ini /etc/php/php.ini.tmp
    export PHP_VER=`dpkg -l 'php*' | grep ^ii | grep -oP "php[0-9]+\\.[0-9]*" | cut -c 4- | head -1 | tr -d $'\n'`
    sudo mv /etc/php/php.ini.tmp /etc/php/$PHP_VER/apache2/php.ini;

    sudo cp src/composer.json /var/www/composer.json
    
    cd src
    composer config allow-plugins.endroid/installer true
    composer install
    cd ..
    
    cp templates/environment-local.php src/environment.php
    
    sudo chown www-data:www-data src/ -R
    f=$(pwd)/src
    while [[ $f != / ]]; do sudo chmod +rx "$f"; f=$(dirname "$f"); done;
    sudo ln -s $(pwd)/src/ /var/www/auxilium2 
    
    #cp scripts/new-keys.php /var/www/new-keys.php

    sudo mkdir /var/auxilium
    
    sudo mkdir /var/auxilium/ecs
    sudo chown www-data:www-data /var/auxilium/ecs -R

    sudo mkdir /var/auxilium/dgdata
    sudo chown deegraph:deegraph /var/auxilium/dgdata -R
    
    sudo mkdir /var/auxilium/www-data
    sudo chown www-data:www-data /var/auxilium/www-data -R

fi

if [ "$CREATE_SSC" -eq 1 ]; then

CERT_LOC="$(pwd)/certs/$INSTALL_ID"

INSTALL_DIR=$(pwd)
if [ ! -d $CERT_LOC ]; then
    mkdir -p $CERT_LOC
fi
cd $CERT_LOC

cat > ssc-ca-csr.conf << EOF

[req]
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = no

[req_distinguished_name]
C = GB
ST = Ceredigion
L = Aberystwyth
O = Aberystwyth University
OU = Auxilium
CN = Development CA

[v3_req]
keyUsage = keyCertSign, cRLSign

EOF

openssl req -x509 -sha256 -days 1825 -config ssc-ca-csr.conf -newkey rsa:2048 -passout pass:auxilium -keyout rootCA.key -out rootCA.crt
openssl genrsa -out privkey.pem 2048

cat > ssc-csr.conf << EOF

[req]
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = no

[req_distinguished_name]
C = GB
ST = Ceredigion
L = Aberystwyth
O = Aberystwyth University
OU = Auxilium
CN = $HOSTNAME

[v3_req]
keyUsage = keyEncipherment, dataEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt_names

[alt_names]
DNS.1 = $INSTANCE_IDENITIFIER

EOF

cat > csr.ext << EOF

authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
subjectAltName = @alt_names
[alt_names]
DNS.1 = $HOSTNAME
DNS.2 = $INSTANCE_IDENITIFIER

EOF


openssl req -new -key privkey.pem -out ssc.csr -config ssc-csr.conf
openssl x509 -req -days 365 -passin pass:auxilium -CA rootCA.crt -CAkey rootCA.key -in ssc.csr -out fullchain.pem -CAcreateserial -extfile csr.ext

cd $INSTALL_DIR

fi


if [ ! -f bin/deegraph-v0.8.jar ]; then
    if [ ! -f bin/deegraph.jar ]; then
        rm bin/deegraph.jar
    fi
    echo "Downloading Deegraph"
    wget -O bin/deegraph-v0.8.jar https://github.com/owoalex/deegraph/releases/download/v0.8/deegraph.jar
    cp bin/deegraph-v0.8.jar bin/deegraph.jar 
fi


if [ $? -ne 0 ]; then
    echo ""
    tput bold
    tput setaf 1
    tput rev
    echo "FAILED TO DOWNLOAD DEEGRAPH"
    tput sgr0
    echo ""
    exit 2
fi

if [ "$LOCAL_INSTALL" -eq 1 ]; then

    sudo su - root -c "echo \"127.0.0.1     $HOSTNAME\" >> /etc/hosts"

    pwd
    ls -lah bin
    sudo cp bin/deegraph.jar /opt/deegraph/deegraph.jar
    sudo chmod +x /opt/deegraph/deegraph.jar

    JSON_KEYS=$(php /app/new-keys.php --user=nobody)

    MYSQL_PASSWORD=$(echo $JSON_KEYS | jq -r '.mysqlPassword')
    DEEGRAPH_ROOT_AUTH_TOKEN=$(echo $JSON_KEYS | jq -r '.deegraphRootToken')
    LOCAL_ONLY_API_KEY=$(echo $JSON_KEYS | jq -r '.localOnlyApiKey')
    URL_METADATA_JWT_SECRET=$(echo $JSON_KEYS | jq -r '.urlMetadataJwtSecret')
    AUTH_JWT_SECRET=$(echo $JSON_KEYS | jq -r '.jwtSecret')
    AUTH_JWT_PUBLIC=$(echo $JSON_KEYS | jq -r '.jwtPublic')
    
    cat > pre-init-db.sql << EOF
DROP USER IF EXISTS auxilium2@localhost;
CREATE USER auxilium2@localhost IDENTIFIED BY '$MYSQL_PASSWORD';
DROP DATABASE IF EXISTS auxilium2;
CREATE DATABASE auxilium2 DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;
GRANT ALL PRIVILEGES ON auxilium2.* TO auxilium2@localhost;
EOF
    sudo mysql < pre-init-db.sql
    rm pre-init-db.sql
    
    if [ -f $CERT_LOC/rootCA.crt ]; then
        sudo su - root -c "cat $CERT_LOC/rootCA.crt >> /etc/ssl/certs/ca-certificates.crt"
        #update-ca-certificates
    fi
    
cat > apache-auxilium.conf << EOF

<VirtualHost *:80>
        DocumentRoot /var/www/auxilium2

        ServerName $HOSTNAME

        ErrorLog \${APACHE_LOG_DIR}/www.error.log
        CustomLog \${APACHE_LOG_DIR}/www.access.log combined

        RewriteEngine On
        RewriteCond %{REQUEST_URI} !^/.well-known/
        RewriteRule ^(.*) https://$HOSTNAME\$1 [R=301,L]
</VirtualHost>


<VirtualHost *:443>
        DocumentRoot /var/www/auxilium2

        ServerName $HOSTNAME

        ErrorLog \${APACHE_LOG_DIR}/www.error.log
        CustomLog \${APACHE_LOG_DIR}/www.access.log combined

        SSLEngine On
        SSLCertificateFile      /var/auxilium/ecs/certs/apache/fullchain.pem
        SSLCertificateKeyFile   /var/auxilium/ecs/certs/apache/privkey.pem

        RewriteEngine On

        Include auxilium-custom.conf
</VirtualHost>

EOF
    sudo cp apache-auxilium.conf /etc/apache2/sites-enabled/default.conf

    cat config/apache2/apache2.conf > apache-auxilium.conf
    
    cat > deegraph-config.json << EOF
{
    "fqdn": "$CONTAINER_FQDN",
    "data_directory": "/var/auxilium/dgdata/",
    "ssl_certs": {
        "private_key": "/var/auxilium/ecs/certs/deegraph/privkey.pem",
        "full_chain": "/var/auxilium/ecs/certs/deegraph/fullchain.pem"
    },
    "port": 8880,
    "root_auth_tokens": ["$DEEGRAPH_ROOT_AUTH_TOKEN"],
    "journal_lifetime": 60
}
EOF
    sudo mv deegraph-config.json /etc/deegraph/deegraph-config.json

    cat > deegraph.service << EOF
[Unit]
Description=Deegraph Graph Database
After=network.target auditd.service

[Service]
Type=simple
User=deegraph
Group=deegraph
ExecStart=/usr/bin/java -jar /opt/deegraph/deegraph.jar /etc/deegraph/deegraph-config.json
TimeoutStartSec=0
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF
    sudo systemctl stop deegraph.service
    sudo mv deegraph.service /etc/systemd/system/deegraph.service
    sudo systemctl daemon-reload
    sudo systemctl enable deegraph.service
    sudo systemctl start deegraph.service
    sudo systemctl enable apache2
    sudo systemctl start apache2
    
    QUICK_RETURN=$(pwd)
    cd /var/auxilium/dgdata
    lines=0
    while [ $lines -eq 0 ]; do
            echo "Waiting for Deegraph server to come online"
            sleep 0.25
            lines=$(find . -maxdepth 1 -name "*.private.jwk" | wc -l)
    done
    DDS_ROOT_NODE=$(find . -maxdepth 1 -name "*.private.jwk" | cut -d '/' -f2 | cut -d '.' -f1)
    cd $QUICK_RETURN
    
    cat > credentials.php << EOF
<?php
const INSTANCE_DOMAIN_NAME = "$CONTAINER_FQDN:$HTTPS_PORT";
const INSTANCE_UUID = "$DDS_ROOT_NODE";

const INSTANCE_CREDENTIAL_SQL_HOST = "localhost";
const INSTANCE_CREDENTIAL_SQL_USERNAME = "auxilium2";
const INSTANCE_CREDENTIAL_SQL_PASSWORD = '$MYSQL_PASSWORD';
const INSTANCE_CREDENTIAL_SQL_DATABASE = "auxilium2";

const INSTANCE_CREDENTIAL_DDS_PORT = 8880;
const INSTANCE_CREDENTIAL_DDS_HOST = "$CONTAINER_FQDN";
const INSTANCE_CREDENTIAL_DDS_LOGIN_NODE = "$DDS_ROOT_NODE";
const INSTANCE_CREDENTIAL_DDS_TOKEN = '$DEEGRAPH_ROOT_AUTH_TOKEN';

const INSTANCE_CREDENTIAL_LOCAL_ONLY_API_KEY = "$LOCAL_ONLY_API_KEY"; // b64 random data > 32 chars

const INSTANCE_CREDENTIAL_URL_METADATA_JWT_SECRET = "$URL_METADATA_JWT_SECRET"; // b64 random data > 64 chars
const INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PRIVATE_KEY = "$AUTH_JWT_SECRET";
const INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PUBLIC_KEY = "$AUTH_JWT_PUBLIC";

const INSTANCE_CREDENTIAL_LOCAL_IP_RANGES = [
    "192.168.0.0/24",
    "127.0.0.0/8"
];

const INSTANCE_CREDENTIAL_OPENID_SOURCES = [];
const INSTANCE_CREDENTIAL_OPENID_CACHE_TIME = 60; // Cache trusted JWKs for this time in seconds.

const INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS = [
    "primary" => [
        "type" => "MS_APP_GRAPH",
        "username" => "REDACTED",
        "external_smtp_address" => "REDACTED",
        "password" => 'REDACTED',
        "user_guid" => "REDACTED",
        "tenant_guid" => "REDACTED",
        "client_guid" => "REDACTED",
        "client_secret" => 'REDACTED'
    ]
];
?>
EOF
    sudo mv credentials.php /var/auxilium/credentials.php
    sudo chown www-data:www-data /var/auxilium/credentials.php

    sudo chown www-data:www-data /var/auxilium/ecs -R

    sudo mkdir -p /var/auxilium/ecs/certs/deegraph/
    sudo cp $CERT_LOC/* /var/auxilium/ecs/certs/deegraph/
    sudo chown deegraph:deegraph /var/auxilium/ecs/certs/deegraph -R
    
    sudo mkdir -p /var/auxilium/ecs/certs/apache/
    sudo cp $CERT_LOC/* /var/auxilium/ecs/certs/apache/
    sudo chown www-data:www-data /var/auxilium/ecs/certs/apache -R
    
    sudo systemctl restart apache2.service

else
    echo "Building docker image"
    docker build -t auxilium .
    if [ $? -eq 0 ]; then
        if [ "$INSTALL" -eq 1 ]; then
            echo "Running new image"
            docker stop auxilium-$INSTANCE_IDENITIFIER
            docker rm auxilium-$INSTANCE_IDENITIFIER

            #docker run -d -p 5097:8085 --name auxilium-dev auxilium
            docker run -dit -p $HTTP_PORT:80 -p $HTTPS_PORT:443 -p $DEEGRAPH_PORT:8880 -v $CERT_LOC:/etc/ssl/ext-certs --mount source=auxilium-volume-$INSTANCE_IDENITIFIER,target=/store -e CONTAINER_FQDN="$HOSTNAME" -e HTTPS_PORT="$HTTPS_PORT" --name auxilium-$INSTANCE_IDENITIFIER auxilium
            #docker run -it -p 8080:80 --name auxilium-dev auxilium
            #docker exec -it auxilium-dev /bin/bash
            #docker exec -it auxilium-dev watch cat /var/log/apache2/error.log
            #docker exec -it auxilium-dev watch -n 0.1 ps -A
            echo ""
            tput bold
            tput rev
            echo "Successfully installed Auxilium!"
            tput sgr0
            echo ""

            if [ "$PREEXISTING_VOLUME" -eq 1 ]; then
                echo "Data restored from disk"
                echo "Go to <https://$HOSTNAME:$HTTPS_PORT/login> to examine this instance"
                echo ""
                #echo "https://$HOSTNAME:$HTTPS_PORT/login" | qrencode -o - -t ANSI256
                #echo ""
            else
                echo "Created blank auxilium instance"
                echo "Go to <https://$HOSTNAME:$HTTPS_PORT/system/init> to setup this new instance"
                echo ""
                #echo "https://$HOSTNAME:$HTTPS_PORT/system/init" | qrencode -o - -t ANSI256
                #echo ""
            fi
            echo "Run './scripts/reset.sh $INSTANCE_IDENITIFIER' to reset to a new instance"
            echo "Run './scripts/shutdown.sh $INSTANCE_IDENITIFIER' to close the instance cleanly"
        else
            tput bold
            tput rev
            echo "Successfully built Auxilium!"
            tput sgr0
            echo ""
        fi
    else
        echo ""
        tput bold
        tput setaf 1
        tput rev
        echo "BUILD FAILED :("
        tput sgr0
        echo ""
        exit 1
    fi
fi
