#!/bin/bash
echo "Loading prerequisites"

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
if [ ! -d auxilium2 ]; then
    git clone git@gitlab.aber.ac.uk:auxilium-software/auxilium2.git
fi
if [ ! -d auxilium2 ]; then
    echo "FAILED TO DOWNLOAD AUXILIUM2, CHECK GIT SSH KEYS"
    exit 2
fi
if [ -f bin/deegraph-0.7.jar ]; then
    echo "Using cached deegraph image"
else
    wget -O bin/deegraph-0.7.jar https://github.com/owoalex/deegraph/releases/download/v0.7/deegraph.jar
fi
cd auxilium2
git pull
cd ..
echo "Writing default config files"
HOSTNAME=$(hostname --fqdn)
HTTP_PORT=8080
HTTPS_PORT=8081
DEEGRAPH_PORT=8880
CERT_LOC=$(pwd)/certs
echo "Building docker image"
docker build -t auxilium .
if [ $? -eq 0 ]; then
    echo "Running new image"
    docker stop auxilium-dev
    docker rm auxilium-dev

    PREEXISTING_VOLUME=0
    if dockerVolumeExists auxilium-dev-volume; then
        PREEXISTING_VOLUME=1
    fi
    #docker run -d -p 5097:8085 --name auxilium-dev auxilium
    docker run -dit -p $HTTP_PORT:80 -p $HTTPS_PORT:443 -p $DEEGRAPH_PORT:8880 -v $CERT_LOC:/etc/ssl/ext-certs --mount source=auxilium-dev-volume,target=/store -e CONTAINER_FQDN="$HOSTNAME" -e HTTPS_PORT="$HTTPS_PORT" --name auxilium-dev auxilium
    #docker run -it -p 8080:80 --name auxilium-dev auxilium
    #docker exec -it auxilium-dev /bin/bash
    #docker exec -it auxilium-dev watch cat /var/log/apache2/error.log
    #docker exec -it auxilium-dev watch -n 0.1 ps -A
    if [ "$PREEXISTING_VOLUME" -eq 1 ]; then
        echo "Restored data from disk"
        echo "Go to <https://$HOSTNAME:$HTTPS_PORT/login> to examine this instance"
    else
        echo "Created blank auxilium instance"
        echo "Go to <https://$HOSTNAME:$HTTPS_PORT/system/init> to setup this new instance"
    fi
    echo "Run ./scripts/dev-reset.sh to reset to a new instance"
    echo "Run ./scripts/dev-shutdown.sh to close the instance cleanly"
else
    echo "Build failed!!!"
    exit 1
fi

