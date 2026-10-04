# Private hosting on your AWS account

For the existing EC2 server hosting `/var/www/contact`, use [HTTPS GitHub deployment](EXISTING-EC2.md) to install the explorer at `/var/www/EN-SCDNAPI` with server-local credentials.

The application runs as a single PHP/Apache container, serving the built React interface and calling the EdgeNext PHP SDK directly. It keeps all 313 operations and the requested SCDN/DNS/Operation Logs grouping. It does not require ChatGPT sign-in, Sites hosting, Cloudflare Workers, Composer, or a database.

## Default: private EC2 access with Session Manager

Use an existing Linux EC2 instance or create one in a private subnet. The instance needs outbound HTTPS connectivity for EdgeNext, image/dependency downloads, AWS Secrets Manager, and Systems Manager. A NAT gateway can supply that egress; Systems Manager VPC endpoints alone do not provide internet access to EdgeNext.

Keep inbound security-group rules empty. No public IP or inbound SSH/HTTP port is needed. Install Docker Engine, Docker Compose v2.30 or newer, AWS CLI, and Python 3 on the instance. Configure SSM Agent and an instance role with `AmazonSSMManagedInstanceCore`. Grant `secretsmanager:GetSecretValue` for only your application's secret ARN, and `kms:Decrypt` for its key if you use a customer-managed KMS key.

On your computer install AWS CLI and the Session Manager plugin. Sign in with an IAM identity authorized to start port-forwarding sessions to this instance. Session Manager access is the authentication boundary; anyone who can forward this port can use the configured EdgeNext account. Restrict those IAM permissions to intended users.

1. Store this JSON in an AWS Secrets Manager secret in your account, replacing the placeholders:

   ```json
   {
     "SDK_API_PRE": "https://apiv4.lalcsafe.com",
     "SDK_APP_ID": "YOUR_CREDENTIAL_ID",
     "SDK_APP_SECRET": "YOUR_SECRET"
   }
   ```

2. Copy this project onto the instance using your approved private transfer method. The project includes a pinned, unmodified copy of the SDK with its MIT license. You need Docker access and outbound HTTPS for the build.

3. From the project directory on EC2, run:

   ```sh
   bash deploy/aws/run-ec2.sh YOUR_REGION YOUR_SECRET_ID
   ```

   The script retrieves the secret through the instance role and writes a mode-0600 `.env.aws` file. It never prints the values and never bakes them into the container image. It starts the container on **127.0.0.1:8080**. If you rotate the secret, rerun this command to replace the file and recreate the container. Administrators with Docker/root access can inspect container environment variables; restrict that access.

4. From this project directory on your computer, open the private tunnel:

   ```sh
   aws ssm start-session --region YOUR_REGION --target YOUR_INSTANCE_ID --document-name AWS-StartPortForwardingSession --parameters file://deploy/aws/session-parameters.json
   ```

5. Open **http://localhost:8080** in your browser. Keep the tunnel session open while using the site. Use that exact address because the server checks `APP_ORIGIN` on API requests.

No AWS resource creation or deployment has been performed by the coding agent. Region, instance, VPC, IAM access and credentials remain under your control.

## Existing private server

Copy `deploy/aws/.env.example` to `.env.aws`, enter your credentials locally on that server, restrict the file to its owner (`chmod 600 .env.aws`), then run `docker compose up -d --build`. Use the same private tunnel approach. The compose configuration deliberately binds only the instance loopback interface.

For access through an existing private VPN and HTTPS reverse proxy, keep the loopback binding, point the reverse proxy at port 8080, and set `APP_ORIGIN` to the exact HTTPS origin visitors use. Provide authentication at the proxy if your private network includes people who should not be allowed to make these API calls. Do not change the listener to a public binding as a shortcut.

## ECS/Fargate alternative

The Docker image can also run on ECS/Fargate. Push the image to your private ECR repository, use private task subnets without assigned public IPs, and place the service behind an internal load balancer reachable through your VPN. Inject the three SDK values from Secrets Manager through task-definition secrets, set `APP_ORIGIN` to the internal HTTPS site URL, and allow task port 80 only from the load balancer security group. The container health endpoint is `/healthz`. This repository does not provision an ECS cluster, load balancer, VPN, certificate, or VPC.

## Local development and verification

Use Node 22.13+ and PHP 8.2+ with cURL. `npm install`, then `npm run dev` starts the PHP API on 127.0.0.1:8081 and Vite on http://localhost:5173. Set `PHP_BIN` if PHP is not on PATH. Credentials can be supplied through your process environment or a project-local `.env.local` file. The development launcher fixes `APP_ORIGIN` to http://localhost:5173.

`npm run build` builds the portable static frontend into `dist/selfhost`. Production uses the Dockerfile's Apache server; PHP's built-in server is for development only. To run a build locally without Docker, copy `dist/selfhost` contents into `selfhost/public`, set `APP_ORIGIN=http://localhost:8080`, and run `php -S 127.0.0.1:8080 -t selfhost/public selfhost/router.php`.

The API validates method, path, input size, origin, and JSON envelopes; only the configured EdgeNext API host receives credentials. The SDK signs requests. A native cURL transport sends JSON, verifies TLS, disables redirects, and limits responses to 2 MB. Live acceptance by your account remains unverified until you configure credentials.

Docker is unavailable in the development workspace, so the container build itself has not been executed there. The portable frontend build has passed. Live EdgeNext authentication remains unverified.

References: [Session Manager](https://docs.aws.amazon.com/systems-manager/latest/userguide/session-manager.html), [port-forwarding sessions](https://docs.aws.amazon.com/systems-manager/latest/userguide/session-manager-working-with-sessions-start.html), [Fargate networking](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/fargate-task-networking.html).
