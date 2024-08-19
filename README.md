# Auxilium
Auxilium is a hosted case management, referral management and client portal system for 3rd sector and public sector organisations.

> [!WARNING]
> This repository is currently unstable, as it is currently being transferred from an internal version control system. This may manifest in broken software, including security related issues. Proceed with caution until this banner is removed.
>

## Installation
The reccomended installation method is through use of the Docker container. If you're using the software in production, please pull the latest release from Docker Hub. This example assumes you already have valid SSL certificates avaliable.

```
sudo cp /etc/letsencrypt/live/test.auxsoft.co.uk /usr/local/certs/ -Lr
docker run -dit -p 80:80 -p 443:443 -v /usr/local/certs/test.auxsoft.co.uk:/etc/ssl/ext-certs --mount source=auxilium-volume,target=/store -e CONTAINER_FQDN="test.auxsoft.co.uk" -e HTTPS_PORT="443" --name auxilium auxiliumsoftware/auxilium:2.0-RC1
```

If you're not using the standard configuration of [Let's Encrypt](https://letsencrypt.org/) for your certificates, you will need to provide certificates in a similar structure. Auxilium expects certificates to be in PEM format named in the following way:

- `fullchain.pem` - The full trust chain for the certificate.
- `privkey.pem` - The unencrypted private key for the certificate.

No other certificates will be required, but do make sure that the container can access these certificates. The docker container will automatically configure Deegraph to also use these certificates.

> [!IMPORTANT]
> If you get a `PR_END_OF_FILE_ERROR` or `ERR_CONNECTION_CLOSED` upon trying to visit the portal in your browser, you have configured certificates incorrectly. Ensure permissions are set so that docker can read the certificates correctly.
>

### Peering Support

> [!WARNING]
> Peering support is currently experimental, it is adviseable to leave this option disabled in production environments.
>

In order to enable peering support, you must directly expose the deegraph instance running inside the container by adding `-p 8880:8880` to the arguments of the `docker run` command. You can then peer to other instances that have this setting enabled.

## Manual Installation
For development purposes, pull the repository and use `install.sh` to get started. This script has been designed to work with Debian Bookworm, and should work on derivitive distributions.

> [!TIP]
> If you're cloning this repo on Windows, you may have to use the `git config core.protectNTFS false` command as there are files named "`aux.css`".
>

## Acknowledgements
Auxilium was created in [Aberystwyth University](https://aber.ac.uk) with funding from the [UK National Lottery's Community Fund](https://www.tnlcommunityfund.org.uk). This software is used to support the work of [Veterans Legal Link](https://veteranslegallink.org/), a service providing free legal advice for UK veterans.

## Dependencies and Licenses
### Libraries
- Twig: Revised BSD License
- endroid/qr-code: MIT License
- FDPF: BSD Zero Clause License
- ezyang/htmlpurifier: LGPL v2.1
- bigfish/pdf417: MIT License
- zbateson/mail-mime-parser: BSD Two Clause License
- lcobucci/jwt: BSD Three Clause License
- web-token/jwt-core: MIT License
### Supporting Software
- PHP: PHP License v3.01
- msgconvert: GPL v1 or later
- Apache 2: Apache License v2.0
- deegraph: GPL v3

## License
Copyright 2021-2024 Aberystwyth University and contributors

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

[http://www.apache.org/licenses/LICENSE-2.0](http://www.apache.org/licenses/LICENSE-2.0)

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
