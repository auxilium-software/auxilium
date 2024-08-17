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
INSTALL_ID=$(uuidgen)
HTTP_PORT=8080
HTTPS_PORT=8081
DEEGRAPH_PORT=8880
CERT_LOC=$(pwd)/certs
CREATE_SSC=1
INSTALL=1
SKIP_QUESTIONS=0
PREEXISTING_VOLUME=0
if dockerVolumeExists auxilium-volume-$INSTANCE_IDENITIFIER; then
    PREEXISTING_VOLUME=1
fi

# Transform long options to short ones
for arg in "$@"; do
    shift
    case "$arg" in
        '--help')           set -- "$@" '-h'   ;;
        '--hostname')       set -- "$@" '-n'   ;;
        '--identifier')     set -- "$@" '-i'   ;;
        '--build-only')     set -- "$@" '-b'   ;;
        '--certs')          set -- "$@" '-c'   ;;
        *)                  set -- "$@" "$arg" ;;
    esac
done

OPTIND=1
while getopts "hybi:n:c:" opt; do
    case $opt in
        h) # display Help
            showHelp
            exit;;
        b) # Enter a name
            INSTALL=0;;
        y) # Enter a name
            SKIP_QUESTIONS=1;;
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
    ln -s bin/deegraph-v0.8.jar bin/deegraph.jar 
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

