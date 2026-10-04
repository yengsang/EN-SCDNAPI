# Connect your existing EC2 server to GitHub

Repository: https://github.com/yengsang/EN-SCDNAPI

Use your usual SSH connection to the server. This procedure uses Git and Docker Compose; it does not require Session Manager, Secrets Manager, or ECS. Deploy into a separate directory from Strapi. The server needs Git, Docker Engine, and Docker Compose 2.30.0 or newer.

## GitHub access

Use HTTPS, the same connection method as `/var/www/contact`. First check whether your existing GitHub authentication can read the new repository:

```bash
git ls-remote https://github.com/yengsang/EN-SCDNAPI.git refs/heads/main
```

Success prints a commit hash and `refs/heads/main`. If Git asks for authentication, enter your GitHub username and a personal access token at the password prompt, not your GitHub account password. For a private repository, the token must have read access to `yengsang/EN-SCDNAPI`; a token restricted to Contact will not work. Your existing credential helper may already handle authentication. Keep tokens out of clone URLs, source files, and shell commands.

## Clone the project

Clone alongside your existing Contact project:

```bash
cd /var/www
git clone https://github.com/yengsang/EN-SCDNAPI.git
cd EN-SCDNAPI
```

If your user cannot create a folder in `/var/www`, create and assign only the new project directory, then clone into it:

```bash
sudo mkdir -p /var/www/EN-SCDNAPI
sudo chown "$(id -un):$(id -gn)" /var/www/EN-SCDNAPI
git clone https://github.com/yengsang/EN-SCDNAPI.git /var/www/EN-SCDNAPI
cd /var/www/EN-SCDNAPI
```

The project lives at `/var/www/EN-SCDNAPI`, alongside `/var/www/contact`.

## Switch an existing checkout from SSH to HTTPS

If you already cloned this project using SSH, update that checkout instead of cloning again:

```bash
cd /var/www/EN-SCDNAPI
git remote set-url origin https://github.com/yengsang/EN-SCDNAPI.git
git config --local --unset core.sshCommand
git ls-remote origin refs/heads/main
```

The unset command may return a nonzero status if no local SSH command was configured; continue with the access check.

After HTTPS access succeeds, you can remove the dedicated EdgeNext key if it was created solely for this repository:

```bash
rm -- ~/.ssh/edgenext_github ~/.ssh/edgenext_github.pub
```

If you added its public key to GitHub, also remove that entry under **EN-SCDNAPI → Settings → Deploy keys**. Keep any SSH credentials used to sign in to EC2.

## Configure and start

```bash
cp deploy/aws/.env.example .env.aws
chmod 600 .env.aws
nano .env.aws
```

Set these values without surrounding quotes:

```dotenv
APP_ORIGIN=https://edgenext.yourdomain.com
SDK_API_PRE=https://apiv4.lalcsafe.com
SDK_APP_ID=your_edgenext_app_id
SDK_APP_SECRET=your_edgenext_app_secret
```

Use the exact browser origin with no trailing slash. Credentials stay on EC2 and are excluded from Git and the Docker build context.

Check that port 8080 is available. If it is occupied, edit the host port in `compose.yaml`, for example `127.0.0.1:8082:80`, and point the reverse proxy to that port.

```bash
docker compose up -d --build
docker compose ps
curl --fail http://127.0.0.1:8080/healthz
```

Configure a separate site/subdomain in your existing reverse proxy, forwarding to `127.0.0.1:8080` (or your chosen port). Use HTTPS and restrict access through your VPN, an IP allowlist, or proxy authentication. The application has no built-in login; `APP_ORIGIN` checks are not authentication. Keep the Compose listener bound to loopback.

For this server's Nginx and `enscdnapi.yengsang.com`, follow [the HTTPS and browser-login setup](NGINX-GUIDE.md).

## Update from GitHub

After new code is pushed, SSH into EC2 and run:

```bash
cd /var/www/EN-SCDNAPI
git pull --ff-only origin main
docker compose up -d --build
```

This is a manual deployment. Pushing a commit alone does not update the running server. Keep source changes in your development checkout; configure server-specific settings in ignored environment files. If you edit `compose.yaml` on EC2, reconcile that local change when upstream changes affect the same file.

After changing `.env.aws`, run `docker compose up -d --force-recreate`. For troubleshooting, run `docker compose logs --tail=100 explorer`.

The frontend build, TypeScript checks, PHP syntax, and catalog/signing tests passed in the development workspace. The Docker build and live EdgeNext authentication need verification on EC2.

Reference: [GitHub HTTPS remote authentication](https://docs.github.com/en/get-started/git-basics/about-remote-repositories#cloning-with-https-urls).
