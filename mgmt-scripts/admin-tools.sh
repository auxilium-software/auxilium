#!/bin/bash
set -euo pipefail

export DOTNET_CLI_TELEMETRY_OPTOUT=1

VALID_COMMANDS=(
    "create-administrator-user"
    "reset-user-password"
    "set-initial-system-settings"
)

usage() {
    echo "Usage: $0 <command> <config-path>"
    echo ""
    echo "Commands:"
    for cmd in "${VALID_COMMANDS[@]}"; do
        echo "  $cmd"
    done
    exit 1
}

# make sure there's only 2 arguments
if [[ $# -ne 2 ]]; then
    echo "Error: expected 2 arguments, got $#"
    usage
fi

COMMAND="$1"
CONFIG_PATH="$2"

# make sure the command is valid
valid=false
for cmd in "${VALID_COMMANDS[@]}"; do
    if [[ "$COMMAND" == "$cmd" ]]; then
        valid=true
        break
    fi
done

if [[ "$valid" == false ]]; then
    echo "Error: unknown command '$COMMAND'"
    usage
fi

# make sure the config path is valid
if [[ ! -f "$CONFIG_PATH" ]]; then
    echo "Error: config file not found: $CONFIG_PATH"
    exit 1
fi

cd auxilium-services--admin-tools/AuxiliumSoftware.AuxiliumServices.AdministrationTools

dotnet clean
dotnet build
dotnet tool install --global dotnet-ef

dotnet run "$COMMAND" -- --config-path "$CONFIG_PATH"
