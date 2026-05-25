#!/bin/bash
set -euo pipefail

export DOTNET_CLI_TELEMETRY_OPTOUT=1

dotnet tool install --global dotnet-ef


cd ${pwd}/auxilium-services--admin-tools/AuxiliumSoftware.AuxiliumServices.AdministrationTools
dotnet clean
dotnet build

cd ${pwd}/auxilium-services--api/AuxiliumSoftware.AuxiliumServices.API
dotnet clean
dotnet build

cd ${pwd}/auxilium-services--task-runner/AuxiliumSoftware.AuxiliumServices.BackgroundTaskRunner
dotnet clean
dotnet build

exit 0

