#!/bin/bash


export DOTNET_CLI_TELEMETRY_OPTOUT=1

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"





####################################################################################################
# FUNCTIONS
####################################################################################################
install_dotnet() {
    echo "==> .NET SDK not found, installing..."

    if [[ "$OSTYPE" == "darwin"* ]]; then
        if command -v brew &>/dev/null; then
            brew install --cask dotnet-sdk
        else
            echo "Homebrew not found. Please install Homebrew (https://brew.sh) or .NET manually (https://dotnet.microsoft.com/download)."
            exit 1
        fi

    elif [[ "$OSTYPE" == "linux-gnu"* ]]; then
        if command -v apt-get &>/dev/null; then
            wget https://dot.net/v1/dotnet-install.sh -O /tmp/dotnet-install.sh
            chmod +x /tmp/dotnet-install.sh
            /tmp/dotnet-install.sh --channel LTS
        elif command -v dnf &>/dev/null; then
            sudo dnf install -y dotnet-sdk-8.0
        elif command -v pacman &>/dev/null; then
            sudo pacman -Sy --noconfirm dotnet-sdk
        else
            echo "Unsupported Linux distro. Please install .NET manually: https://dotnet.microsoft.com/download"
            exit 1
        fi

    else
        echo "Unsupported OS: $OSTYPE. Please install .NET manually: https://dotnet.microsoft.com/download"
        exit 1
    fi

    echo "    .NET SDK installed."
}

install_rabbitmq() {
    echo "install rabbitmq"
}

install_mariadb() {
    sudo apt install mariadb-server
}

init_submodules() {
    git submodule add -b dev https://github.com/auxilium-software/auxilium-api.git auxilium-services-api 2>/dev/null || true
    git submodule add -b dev https://github.com/auxilium-software/auxilium-microservices.git auxilium-services-background-task-runner 2>/dev/null || true
    git submodule add -b project-raven https://github.com/auxilium-software/auxilium-portal.git auxilium-portal 2>/dev/null || true
    git submodule add -b main https://github.com/auxilium-software/auxilium-admin-tools.git auxilium-admin-tools 2>/dev/null || true
    git submodule add -b main https://github.com/auxilium-software/auxilium-manual.git auxilium-manual 2>/dev/null || true
}

fix_permissions() {
    sudo chown -R "$(whoami)" "$SCRIPT_DIR/auxilium-services-background-task-runner"
    sudo chown -R "$(whoami)" "$SCRIPT_DIR/auxilium-services-api"
}

build_c_sharp_stuff() {
    echo "==> Building auxilium-services-background-task-runner (CLI)..."
    cd "$SCRIPT_DIR/auxilium-services-background-task-runner"
    dotnet build --configuration Release
    echo "    CLI build complete."

    echo "==> Building auxilium-services-api (ASP.NET Core)..."
    cd "$SCRIPT_DIR/auxilium-services-api"
    dotnet build --configuration Release
    echo "    API build complete."
}





####################################################################################################
# RUN STUFF
####################################################################################################
if ! command -v dotnet &>/dev/null; then
    install_dotnet
else
    echo "==> .NET SDK found: $(dotnet --version)"
fi
install_rabbitmq
install_mariadb


export PATH="$HOME/.dotnet:$PATH"
export DOTNET_ROOT=/home/cal66-admin/.dotnet

init_submodules
git submodule update --init --remote
fix_permissions
build_c_sharp_stuff

exit 0
