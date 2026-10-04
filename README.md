# EdgeNext API Explorer

This project is prepared for private self-hosting on your AWS account. It serves the React interface and an EdgeNext PHP SDK backend in one container. The Compose listener is bound to loopback on port 8080. An existing server can expose it through an authenticated reverse proxy or private network; a Session Manager tunnel is also supported.

All 313 documented operations and the requested SCDN, DNS, and Operation Logs hierarchy are included.

See [deployment from GitHub to your existing EC2 server](deploy/aws/EXISTING-EC2.md) for HTTPS GitHub access, installation at `/var/www/EN-SCDNAPI`, configuration, and updates. [Other AWS hosting options](deploy/aws/README.md) cover Session Manager, Secrets Manager, local development, and validation.

Use Node 22.13+, PHP 8.2+ with cURL, and Docker Compose 2.30+ for container deployment. `npm run dev` starts the local PHP API and frontend. `npm run build` generates the standalone frontend.

On your server, copy `deploy/aws/.env.example` to `.env.aws`, configure the EdgeNext credentials and `APP_ORIGIN` to match your browser's origin, then run `docker compose up -d --build`. Credentials belong on the server and are excluded from Git and Docker build context. After changing credentials, run `docker compose up -d --force-recreate`. The application has no built-in login; restrict access at the reverse proxy or network.

Official catalog source: https://home.console.edgenext.com/apidoc/v2/en/openapi.json, retrieved 2026-10-04. The pinned SDK source and MIT license are in `selfhost/sdk`. Upstream source files are unmodified; the application uses its supported transport interface.
