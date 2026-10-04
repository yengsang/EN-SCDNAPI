# Connect your existing EC2 server to GitHub

Repository: https://github.com/yengsang/EN-SCDNAPI

Use your usual SSH connection to the server. This procedure uses Git and Docker Compose; it does not require Session Manager, Secrets Manager, or ECS. Deploy into a separate directory from Strapi. The server needs Git, Docker Engine, and Docker Compose 2.30.0 or newer.

## GitHub access

If the server already has GitHub SSH authentication with access to this repository, use the clone command below. A deploy key attached only to the Strapi repository cannot read this repository.

For a dedicated read-only key, generate one on EC2, choosing an unused filename:

```bash
mkdir -p ~/.ssh
chmod 700 ~/.ssh
ssh-keygen -t ed25519 -C 'EN-SCDNAPI EC2 deployment' -f ~/.ssh/edgenext_github
cat ~/.ssh/edgenext_github.pub
```

Add the public key in **GitHub → yengsang/EN-SCDNAPI → Settings → Deploy keys → Add deploy key**. Leave **Allow write access** unchecked. Keep the private key on EC2. If you set a passphrase, load the key into your SSH agent before fetching updates. Verify GitHub's host fingerprint against its official documentation on the first connection.

## Clone the project

With existing authorized GitHub SSH authentication:

```bash
mkdir -p ~/apps
cd ~/apps
git clone git@github.com:yengsang/EN-SCDNAPI.git
cd EN-SCDNAPI
```

If you created the dedicated key above, use this instead:

```bash
mkdir -p ~/apps
cd ~/apps
GIT_SSH_COMMAND='ssh -i ~/.ssh/edgenext_github -o IdentitiesOnly=yes' git clone git@github.com:yengsang/EN-SCDNAPI.git
cd EN-SCDNAPI
git config core.sshCommand 'ssh -i ~/.ssh/edgenext_github -o IdentitiesOnly=yes'
```

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

## Update from GitHub

After new code is pushed, SSH into EC2 and run:

```bash
cd ~/apps/EN-SCDNAPI
git pull --ff-only origin main
docker compose up -d --build
```

This is a manual deployment. Pushing a commit alone does not update the running server. Keep source changes in your development checkout; configure server-specific settings in ignored environment files. If you edit `compose.yaml` on EC2, reconcile that local change when upstream changes affect the same file.

After changing `.env.aws`, run `docker compose up -d --force-recreate`. For troubleshooting, run `docker compose logs --tail=100 explorer`.

The frontend build, TypeScript checks, PHP syntax, and catalog/signing tests passed in the development workspace. The Docker build and live EdgeNext authentication need verification on EC2.

Reference: [GitHub deploy keys](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/managing-deploy-keys).
