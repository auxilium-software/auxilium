#!/usr/bin/env bash
set -Eeuo pipefail

export DOTNET_CLI_TELEMETRY_OPTOUT=1
export DOTNET_NOLOGO=1

readonly SCRIPT_DIR="$(
    cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1
    pwd
)"

cd "$SCRIPT_DIR"


build_dotnet_project() {
    local project_path="$1"

    echo
    echo "=================================================="
    echo "Building .NET project: $project_path"
    echo "=================================================="

    dotnet clean "$project_path"
    dotnet build "$project_path" --no-restore
}


ensure_dotnet_ef() {
    if dotnet tool list --global | awk '{print $1}' | grep -qx "dotnet-ef"; then
        echo "dotnet-ef is already installed."
    else
        echo "Installing dotnet-ef..."
        dotnet tool install --global dotnet-ef
    fi
}


echo
echo "=================================================="
echo "Preparing repository"
echo "=================================================="

ensure_dotnet_ef

git reset --hard HEAD
git pull --ff-only
git submodule sync --recursive
git submodule update --init --recursive


build_dotnet_project "auxilium-services--admin-tools/AuxiliumSoftware.AuxiliumServices.AdministrationTools/AuxiliumSoftware.AuxiliumServices.AdministrationTools.csproj"
build_dotnet_project "auxilium-services--api/AuxiliumSoftware.AuxiliumServices.API/AuxiliumSoftware.AuxiliumServices.API.csproj"
build_dotnet_project "auxilium-services--task-runner/AuxiliumSoftware.AuxiliumServices.BackgroundTaskRunner/AuxiliumSoftware.AuxiliumServices.BackgroundTaskRunner.csproj"


echo
echo "=================================================="
echo "Building Docker images"
echo "=================================================="

sudo docker compose build


echo
echo "=================================================="
echo "Build completed successfully"
echo "=================================================="
