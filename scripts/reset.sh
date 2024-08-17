#!/bin/bash
docker kill auxilium-$1
docker rm auxilium-$1
docker volume rm auxilium-volume-$1
