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

if [ ! -d bin ]; then
    mkdir bin
fi

echo ""
tput setaf 4
tput bold
echo "H4sICAd/eWYAA2FzY2lpLWFydC50eHQAfZCxDcMwDAR7TfFFiqTiBtnAXUoBXITDh3xaoiAoeYAvyy8daQPyoHCUh6NamCpwPmuqYp7Fema5np/r1YyEjhtdTeaTodrW2xAXElLJYl/cl3KKpUbu02MYEfkxdcRnIljwG5Fz94FYgkTgHeb1BzGtbeNn8/iGju165TuC/5velZLcxVZsqS2f1r72zncjBAIAAA==" | base64 -d | gzip -dc
tput sgr0

echo ""
echo "$(tput bold)Welcome to the Auxilium docker package installer$(tput sgr0)"
echo ""

echo "Writing default config files"
HOSTNAME=$(hostname --fqdn)
INSTANCE_IDENITIFIER=$(echo $HOSTNAME | cut -d"." -f1)
INSTALL_ID=$(uuidgen)
HTTP_PORT=8080
HTTPS_PORT=8081
DEEGRAPH_PORT=8880
CERT_LOC=$(pwd)/certs
CREATE_SSC=1
PREEXISTING_VOLUME=0
if dockerVolumeExists auxilium-volume-$INSTANCE_IDENITIFIER; then
    PREEXISTING_VOLUME=1
fi

echo ""
tput bold
tput rev
echo "About to install Auxilium with the following options:"
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
echo ""
tput bold
read -p "Do you want to continue with the installation? [Y/n] " CONFIRM_VALUES
tput sgr0

if [[ $CONFIRM_VALUES = [Nn] ]]; then
    echo "Aborting installation"
    exit
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


echo "Downloading Deegraph"
wget -O bin/deegraph.jar https://github.com/owoalex/deegraph/releases/download/v0.8/deegraph.jar


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

echo "Building docker image"
docker build -t auxilium .
if [ $? -eq 0 ]; then
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
        echo "https://$HOSTNAME:$HTTPS_PORT/login" | qrencode -o - -t ANSI256
        echo ""
    else
        echo "Created blank auxilium instance"
        echo "Go to <https://$HOSTNAME:$HTTPS_PORT/system/init> to setup this new instance"
        echo ""
        echo "https://$HOSTNAME:$HTTPS_PORT/system/init" | qrencode -o - -t ANSI256
        echo ""
    fi
    echo "Run ./scripts/reset.sh to reset to a new instance"
    echo "Run ./scripts/shutdown.sh to close the instance cleanly"
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

