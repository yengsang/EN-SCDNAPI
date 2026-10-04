# Open https://enscdnapi.yengsang.com on the existing Ubuntu EC2 server

These steps add a separate Nginx site forwarding to the explorer at `127.0.0.1:8080`, with an HTTPS certificate and a browser login. Run them through your usual SSH connection to EC2. The application itself has no login; Nginx enforces access for the entire site and its API. These templates assume the standard Ubuntu Nginx worker user/group `www-data` and sites-available/sites-enabled layout.

## 1. DNS and local application

Create an A record for `enscdnapi.yengsang.com` pointing to this EC2 instance's public IPv4 address (preferably its Elastic IP). If an AAAA record exists, it must also reach this instance correctly. HTTP port 80 must reach Nginx for certificate validation, and HTTPS port 443 must reach it for browser access. Keep the Docker app bound to loopback; do not open port 8080 in the EC2 security group.

Verify the running container first:

```bash
cd /var/www/EN-SCDNAPI
sudo docker compose ps
curl --fail http://127.0.0.1:8080/healthz
```

Expect `{"status":"ok"}`. If the container has not started, run `sudo docker compose up -d --build`. If you selected another host port, use it in health checks and change `proxy_pass` in `deploy/aws/nginx/enscdnapi.conf` accordingly.

## 2. Fetch configuration and prepare the login

```bash
cd /var/www/EN-SCDNAPI
git pull --ff-only origin main
sudo apt update
sudo apt install -y certbot apache2-utils
sudo mkdir -p /var/www/letsencrypt
sudo htpasswd -cB /etc/nginx/enscdnapi.htpasswd yengsang
sudo chown root:www-data /etc/nginx/enscdnapi.htpasswd
sudo chmod 640 /etc/nginx/enscdnapi.htpasswd
```

The htpasswd command prompts for a new browser-login password. This is separate from GitHub, EC2, and EdgeNext credentials. Use `-c` only when creating the file for the first time; to change a password later, run `sudo htpasswd -B /etc/nginx/enscdnapi.htpasswd yengsang`. If Nginx runs with another worker group, use that group instead of `www-data`.

If a site file named `/etc/nginx/sites-available/enscdnapi.yengsang.com` already exists, back it up before replacing it. Avoid duplicate `server_name enscdnapi.yengsang.com` entries in other enabled site files.

## 3. Enable HTTP certificate validation

```bash
sudo cp deploy/aws/nginx/enscdnapi.http.conf /etc/nginx/sites-available/enscdnapi.yengsang.com
sudo ln -sfn /etc/nginx/sites-available/enscdnapi.yengsang.com /etc/nginx/sites-enabled/enscdnapi.yengsang.com
sudo nginx -t
```

Only after the test succeeds, run:

```bash
sudo systemctl reload nginx
sudo certbot certonly --webroot -w /var/www/letsencrypt --cert-name enscdnapi.yengsang.com -d enscdnapi.yengsang.com
```

Follow the certificate prompts. Do not proceed if certificate issuance fails. This bootstrap site serves certificate challenges and returns 404 for other requests until HTTPS is configured.

## 4. Enable HTTPS and set the application origin

```bash
sudo cp deploy/aws/nginx/enscdnapi.conf /etc/nginx/sites-available/enscdnapi.yengsang.com
sudo nginx -t
```

Only after the test succeeds, run:

```bash
sudo systemctl reload nginx
nano .env.aws
```

Set this line, keeping your EdgeNext credentials in the same file:

```dotenv
APP_ORIGIN=https://enscdnapi.yengsang.com
```

Then apply the environment change:

```bash
sudo docker compose up -d --force-recreate
```

## 5. Verify browser access and renewal

Open **https://enscdnapi.yengsang.com**. Sign in with `yengsang` and the password created with htpasswd. Expect the API explorer interface. Test a read-only EdgeNext operation to verify API credentials and connectivity.

An unauthenticated request should return 401:

```bash
curl -I https://enscdnapi.yengsang.com/
```

An authenticated health check prompts for your password and should return `{"status":"ok"}`:

```bash
curl --fail --user yengsang https://enscdnapi.yengsang.com/healthz
```

Set up Nginx reloads after successful certificate renewals and check automatic renewal:

```bash
sudo mkdir -p /etc/letsencrypt/renewal-hooks/deploy
sudo install -m 0755 deploy/aws/nginx/reload-enscdnapi.sh /etc/letsencrypt/renewal-hooks/deploy/reload-enscdnapi.sh
sudo systemctl enable --now certbot.timer
sudo certbot renew --cert-name enscdnapi.yengsang.com --dry-run
```

Nginx is not installed in the development workspace; run the `nginx -t` checks on EC2 before each reload. If you get 502, verify Docker and the upstream port. If the certificate request fails, verify DNS, HTTP reachability, and the challenge location. If API requests fail origin validation, check APP_ORIGIN and recreate the container.

References: [Nginx Basic Authentication](https://nginx.org/en/docs/http/ngx_http_auth_basic_module.html), [Certbot webroot validation](https://eff-certbot.readthedocs.io/en/stable/using.html#webroot).
