#!/bin/bash
set -euo pipefail

export DOTNET_CLI_TELEMETRY_OPTOUT=1

dotnet tool install --global dotnet-ef



git reset --hard HEAD
git pull
git submodule update --init --recursive


cd auxilium-services--admin-tools/AuxiliumSoftware.AuxiliumServices.AdministrationTools
dotnet clean
dotnet build
cd ../../

cd auxilium-services--api/AuxiliumSoftware.AuxiliumServices.API
dotnet clean
dotnet build
cd ../../

cd auxilium-services--task-runner/AuxiliumSoftware.AuxiliumServices.BackgroundTaskRunner
dotnet clean
dotnet build
cd ../../







cd auxilium-portal
sudo docker compose build
cd ../../

cd auxilium-services--admin-tools
sudo docker compose build
cd ../../

cd auxilium-services--api
sudo docker compose build
cd ../../

cd auxilium-services--task-runner
sudo docker compose build
cd ../../




exit 0

