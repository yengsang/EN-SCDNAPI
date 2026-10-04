# Deploy the private EdgeNext explorer to EC2

This guide uses Amazon Linux 2023 (x86_64), Docker, Secrets Manager, and Session Manager. The application serves its React interface and calls the EdgeNext PHP SDK from one container. AWS IAM controls access through a private tunnel. It has no separate application login, so only grant tunnel access to people allowed to use your EdgeNext account.

Replace YOUR_REGION, YOUR_BUCKET, YOUR_INSTANCE_ID, and YOUR_SECRET_ARN throughout. Use one AWS Region for the instance, bucket, and secret.

## 1. Prepare the secret and upload bucket

In AWS Secrets Manager, create a secret using **Other type of secret**, then enter this in the plaintext JSON editor:

```json
{
  "SDK_API_PRE": "https://apiv4.lalcsafe.com",
  "SDK_APP_ID": "YOUR_EDGENEXT_APP_ID",
  "SDK_APP_SECRET": "YOUR_EDGENEXT_APP_SECRET"
}
```

Name it `edgenext/api`. Use the default Secrets Manager encryption key for this guide. Save its full ARN, including its generated suffix. These are the credentials expected by the PHP SDK; use the matching API credentials from your EdgeNext account.

In S3, create a private bucket with **Block all public access** enabled. Keep the default SSE-S3 encryption for the upload in this guide. The bucket will hold `edgenext-ec2.tar.gz`, containing source code only.

## 2. Create the EC2 instance role

In IAM, create a role for **AWS service → EC2**. Attach the managed policy `AmazonSSMManagedInstanceCore`. Add an inline policy granting access to your exact secret and uploaded archive:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": "secretsmanager:GetSecretValue",
      "Resource": "YOUR_SECRET_ARN"
    },
    {
      "Effect": "Allow",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::YOUR_BUCKET/edgenext-ec2.tar.gz"
    }
  ]
}
```

If you choose customer-managed KMS encryption instead of the defaults above, add `kms:Decrypt` for the relevant key and permit the role in its key policy. Your own AWS login also needs permission to upload the archive and start Session Manager sessions for this instance; the instance role does not grant permissions to your personal login.

## 3. Launch EC2

Use these settings:

| Setting | Value |
| --- | --- |
| AMI | Standard Amazon Linux 2023, x86_64 |
| Instance | `t3.medium` for comfortable on-instance builds |
| Storage | 30 GiB gp3, encrypted |
| IAM instance profile | The role from step 2 |
| Security group inbound rules | Empty; remove any automatically added SSH rule |
| Security group outbound rules | Allow outbound internet traffic for downloads, AWS APIs, and EdgeNext |
| Metadata | Require IMDSv2 |

For a straightforward setup, use a public subnet with a route to an internet gateway and enable the instance public IPv4 address. The app remains private because there are no inbound rules and its port binds only to loopback. If you already have a private subnet with NAT internet access, use that instead and disable the public IP. Systems Manager VPC endpoints alone do not provide access to the external EdgeNext API.

A key pair is unnecessary for Session Manager access. Standard Amazon Linux 2023 AMIs include SSM Agent and AWS CLI v2. After launch, select **EC2 → Instances → your instance → Connect → Session Manager → Connect**. If the instance is unavailable, check its role, outbound network connectivity, and SSM Agent status.

## 4. Install Docker and Compose on EC2

Run these in the Linux Session Manager shell:

```bash
sudo dnf install -y docker python3 tar
sudo systemctl enable --now docker
sudo mkdir -p /usr/local/lib/docker/cli-plugins
sudo curl -fSL https://github.com/docker/compose/releases/download/v5.5.0/docker-compose-linux-x86_64 -o /usr/local/lib/docker/cli-plugins/docker-compose
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
sudo usermod -aG docker "$(whoami)"
```

Close this shell and start a new Session Manager shell so the Docker group membership takes effect. Verify:

```bash
docker compose version
docker info
aws --version
```

Compose must support `env_file.format: raw` (2.30.0 or newer); the pinned version above supports it. This preserves secret values containing dollar signs. The manual Compose installation does not automatically update itself.

## 5. Upload the project from Windows

In PowerShell on your computer:

```powershell
Set-Location 'C:\Personal\Codex\EN-SCDN API'
tar -czf edgenext-ec2.tar.gz --exclude=node_modules --exclude=.git --exclude=.openai --exclude=.sites-runtime --exclude=.wrangler --exclude=.next --exclude=dist --exclude=.env* --exclude=*.tsbuildinfo edgenext-explorer
```

Upload `edgenext-ec2.tar.gz` through the S3 console to the bucket root. Keep it private. Alternatively, with an authenticated local AWS CLI:

```powershell
aws s3 cp .\edgenext-ec2.tar.gz s3://YOUR_BUCKET/edgenext-ec2.tar.gz --region YOUR_REGION
```

No credentials or node_modules are needed in the archive. Docker installs dependencies and builds the frontend on EC2.

## 6. Start the application on EC2

Back in the Linux Session Manager shell:

```bash
mkdir -p ~/edgenext-deploy
cd ~/edgenext-deploy
aws s3 cp s3://YOUR_BUCKET/edgenext-ec2.tar.gz ./edgenext-ec2.tar.gz --region YOUR_REGION
tar -xzf edgenext-ec2.tar.gz
cd edgenext-explorer
sed -i 's/\r$//' deploy/aws/run-ec2.sh
bash deploy/aws/run-ec2.sh YOUR_REGION YOUR_SECRET_ARN
docker compose ps
curl --fail http://127.0.0.1:8080/healthz
```

The last command should return `{"status":"ok"}`. The first build may take several minutes. The script reads the secret with the instance role, writes a restricted `.env.aws` file, and starts the container on `127.0.0.1:8080`. Credentials stay on the server; root and Docker administrators can inspect them.

## 7. Open the private site from Windows

Install [AWS CLI v2](https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html) and the [Session Manager plugin for Windows](https://docs.aws.amazon.com/systems-manager/latest/userguide/install-plugin-windows.html). Configure the CLI for your AWS account, preferably through your existing IAM Identity Center/SSO profile. Confirm the identity with `aws sts get-caller-identity`. If using a named profile, add `--profile YOUR_PROFILE` to your local AWS commands.

In PowerShell:

```powershell
Set-Location 'C:\Personal\Codex\EN-SCDN API\edgenext-explorer'
aws ssm start-session --region YOUR_REGION --target YOUR_INSTANCE_ID --document-name AWS-StartPortForwardingSession --parameters file://deploy/aws/session-parameters.json
```

Keep this terminal open, then browse to **http://localhost:8080**. Use this exact origin, since API requests are checked against `APP_ORIGIN`. Closing the tunnel removes your browser's access; the EC2 app keeps running. Leave the security group inbound rules empty.

Choose a read-only EdgeNext operation first, enter its parameters, and send a request. Live credential acceptance has not been verified in this workspace. A working health endpoint confirms the web container is running; a successful read-only API call confirms EdgeNext authentication and connectivity.

## Maintenance and troubleshooting

From the project directory on EC2:

```bash
docker compose logs --tail=100 explorer
docker compose restart
```

For source updates, upload a new archive, extract it over the project, then rerun `bash deploy/aws/run-ec2.sh YOUR_REGION YOUR_SECRET_ARN`. After changing credentials in Secrets Manager, rerun the same command so the environment file and container receive the new values. `docker compose down` stops the application without terminating EC2.

If port forwarding fails with `TargetNotConnected`, check SSM Agent, the instance role, and outbound connectivity. If secret retrieval fails, check the exact ARN and IAM/KMS permissions. If Docker reports permission denied, reconnect after the group change. If browser requests fail origin validation, use `http://localhost:8080` rather than an IP address. A 502 from the API may indicate credentials, upstream availability, or outbound connectivity; check server logs without sharing secrets.

The portable frontend build has passed locally. Docker is unavailable in the development workspace, so the container build and live EdgeNext requests still need verification on your EC2 instance. This guide has not created AWS resources or changed the existing ChatGPT-hosted site.

References: [EC2 Session Manager roles](https://docs.aws.amazon.com/systems-manager/latest/userguide/session-manager-getting-started-instance-profile.html), [AWS Docker installation](https://docs.aws.amazon.com/serverless-application-model/latest/developerguide/install-docker.html), [Docker Compose plugin installation](https://docs.docker.com/compose/install/linux/), [Session Manager port forwarding](https://docs.aws.amazon.com/systems-manager/latest/userguide/session-manager-working-with-sessions-start.html).
