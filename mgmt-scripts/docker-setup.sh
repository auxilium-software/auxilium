#!/bin/bash

set -e

CONFIG_OUT="config.docker.yaml"
ENV_OUT=".env"
TITLE="Auxilium 3 (Docker) - Setup Wizard"
HEIGHT=25
WIDTH=75

####################################################################################################
# HELPERS

ask() {
    local title="$1"
    local prompt="$2"
    local default="$3"
    whiptail --title "$TITLE" --inputbox "$prompt" $HEIGHT $WIDTH "$default" \
        3>&1 1>&2 2>&3
}

ask_password() {
    local prompt="$1"
    whiptail --title "$TITLE" --passwordbox "$prompt" $HEIGHT $WIDTH \
        3>&1 1>&2 2>&3
}

ask_yesno() {
    local prompt="$1"
    local default="$2"  # "yes" or "no"
    if [ "$default" = "yes" ]; then
        whiptail --title "$TITLE" --yesno "$prompt" $HEIGHT $WIDTH \
            3>&1 1>&2 2>&3 && echo "true" || echo "false"
    else
        whiptail --title "$TITLE" --defaultno --yesno "$prompt" $HEIGHT $WIDTH \
            3>&1 1>&2 2>&3 && echo "true" || echo "false"
    fi
}

generate_secret() {
    openssl rand -base64 $1 | tr -d '\n'
}

cancel_check() {
    if [ $? -ne 0 ]; then
        whiptail --title "$TITLE" --msgbox "Setup cancelled." 8 $WIDTH
        exit 1
    fi
}

####################################################################################################
# WELCOME

whiptail --title "$TITLE" \
    --msgbox "Welcome!\n\nThis wizard will generate your config.docker.yaml and .env files.\n\nSecrets are never committed to the repository." \
    $HEIGHT $WIDTH


####################################################################################################
# MARIADB

MARIADB_PASSWORD=$(ask_password "MariaDB - enter a password for the 'auxilium' database user:")
cancel_check

MARIADB_PASSWORD_CONFIRM=$(ask_password "MariaDB - confirm password:")
cancel_check

if [ "$MARIADB_PASSWORD" != "$MARIADB_PASSWORD_CONFIRM" ]; then
    whiptail --title "$TITLE" --msgbox "Passwords do not match. Please run setup again." 8 $WIDTH
    exit 1
fi


####################################################################################################
# RABBITMQ

RABBITMQ_PASSWORD=$(ask_password "RabbitMQ - enter a password for the 'auxilium' user:")
cancel_check

RABBITMQ_PASSWORD_CONFIRM=$(ask_password "RabbitMQ - confirm password:")
cancel_check

if [ "$RABBITMQ_PASSWORD" != "$RABBITMQ_PASSWORD_CONFIRM" ]; then
    whiptail --title "$TITLE" --msgbox "Passwords do not match. Please run setup again." 8 $WIDTH
    exit 1
fi


####################################################################################################
# RECAPTCHA

USE_RECAPTCHA=$(ask_yesno "Enable Google ReCAPTCHA v3?" "yes")

if [ "$USE_RECAPTCHA" = "true" ]; then
    RECAPTCHA_SITE_KEY=$(ask "ReCAPTCHA" "Site key:" "")
    cancel_check
    RECAPTCHA_SECRET_KEY=$(ask_password "ReCAPTCHA - secret key:")
    cancel_check
else
    RECAPTCHA_SITE_KEY=""
    RECAPTCHA_SECRET_KEY=""
fi


####################################################################################################
# JWT

JWT_SECRET=$(generate_secret 128)
whiptail --title "$TITLE" \
    --msgbox "JWT secret key has been automatically generated using openssl." \
    8 $WIDTH


####################################################################################################
# API / CORS

API_AVAILABLE_AT=$(ask "API" \
    "Public API base URL (how the portal reaches the API externally):" \
    "http://api:1938")
cancel_check

PORTAL_ORIGIN=$(ask "CORS" \
    "Public portal origin (e.g. https://portal.example.com):" \
    "")
cancel_check

API_ORIGIN=$(ask "CORS" \
    "Public API origin (e.g. https://api.example.com):" \
    "")
cancel_check

PORTAL_HOST=$(echo "$PORTAL_ORIGIN" | sed 's|https\?://||' | sed 's|/.*||')
API_HOST=$(echo "$API_ORIGIN" | sed 's|https\?://||' | sed 's|/.*||')


####################################################################################################
# SMTP

SMTP_HOST=$(ask "SMTP" "SMTP server hostname:" "")
cancel_check

SMTP_PORT=$(ask "SMTP" "SMTP port:" "587")
cancel_check

SMTP_USE_TLS=$(ask_yesno "Use TLS for SMTP?" "no")

SMTP_USE_AUTH=$(ask_yesno "Use SMTP authentication?" "yes")

if [ "$SMTP_USE_AUTH" = "true" ]; then
    SMTP_USERNAME=$(ask "SMTP" "SMTP username:" "")
    cancel_check
    SMTP_PASSWORD=$(ask_password "SMTP - password:")
    cancel_check
else
    SMTP_USERNAME=""
    SMTP_PASSWORD=""
fi

SMTP_FROM_ADDRESS=$(ask "SMTP" "From address:" "")
cancel_check

SMTP_FROM_NAME=$(ask "SMTP" "From display name:" "Auxilium 3")
cancel_check


####################################################################################################
# WRITE config.docker.yaml

cat > "$CONFIG_OUT" << EOF
####################################################################################################
# Database Configuration
Databases:
  MariaDB:
    Host: mariadb
    Port: 3306
    Username: auxilium
    Password: ${MARIADB_PASSWORD}
    Database: auxilium
  RabbitMQ:
    Host: rabbitmq
    Port: 5672
    Username: auxilium
    Password: ${RABBITMQ_PASSWORD}
    VirtualHost: Auxilium
    HeartbeatIntervalInSeconds: 600
    BlockedConnectionTimeoutInSeconds: 300
    ExchangeName: Auxilium API
    Queues:
      Notifications: auxilium3_notifications


####################################################################################################
# ReCAPTCHA Configuration
ReCAPTCHA:
  UseReCAPTCHA: ${USE_RECAPTCHA}
  SiteKey: ${RECAPTCHA_SITE_KEY}
  SecretKey: ${RECAPTCHA_SECRET_KEY}
  ScoreThreshold: 0.5


####################################################################################################
# JWT Configuration
JWT:
  SecretKey: ${JWT_SECRET}
  Algorithm: HS256
  MfaTokenExpirationInSeconds: 300
  AccessTokenExpirationInMinutes: 15
  RefreshTokenExpirationInDays: 7
  ValidIssuer: Auxilium API
  ValidAudiencePrefix: Auxilium 3


####################################################################################################
# API Configuration
API:
  UseHttpsRedirection: false
  AvailableFrom:
    - http://0.0.0.0:1938
  AvailableAt: ${API_AVAILABLE_AT}
  CORS:
    AllowedOrigins:
      - http://api:1938
      - http://portal:80
      - ${PORTAL_ORIGIN}
      - ${API_ORIGIN}
    AllowedHosts:
      - api
      - portal
      - ${PORTAL_HOST}
      - ${API_HOST}


####################################################################################################
# SMTP Configuration
SMTP:
  SendEmailsFromPortal: true
  Connection:
    Host: ${SMTP_HOST}
    Port: ${SMTP_PORT}
    UseTls: ${SMTP_USE_TLS}
  Authentication:
    UseAuthentication: ${SMTP_USE_AUTH}
    Username: ${SMTP_USERNAME}
    Password: ${SMTP_PASSWORD}
  From:
    Address: ${SMTP_FROM_ADDRESS}
    Name: ${SMTP_FROM_NAME}


####################################################################################################
# File System Configuration
FileSystem:
  RootStorageDirectories:
    FormData: /var/auxilium/formdata
    AuxLFS:   /var/auxilium/auxlfs


####################################################################################################
# Argon2 Configuration
Argon2:
  MemoryCost: 65536
  TimeCost: 3
  Parallelism: 1
  HashLength: 32
  SaltLength: 16


####################################################################################################
# Development Configuration
Development:
  PHPAcceptSelfSignedCertificatesForAPI: false
EOF


####################################################################################################
# WRITE .env

cat > "$ENV_OUT" << EOF
MARIADB_ROOT_PASSWORD=$(generate_secret 32)
MARIADB_DATABASE=auxilium
MARIADB_USER=auxilium
MARIADB_PASSWORD=${MARIADB_PASSWORD}

RABBITMQ_USER=auxilium
RABBITMQ_PASSWORD=${RABBITMQ_PASSWORD}
EOF


####################################################################################################
# DONE

whiptail --title "$TITLE" \
    --msgbox "Setup complete!\n\nconfig.docker.yaml and .env have been written.\nRun 'sudo docker compose up -d' to start Auxilium.\n\nDo not commit either file to git." \
    $HEIGHT $WIDTH
