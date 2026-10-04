#!/usr/bin/env bash
set -euo pipefail
if [[ $# -ne 2 ]]; then
  echo 'Usage: bash deploy/aws/run-ec2.sh <region> <secrets-manager-secret-id>' >&2
  exit 1
fi
cd "$(dirname "${BASH_SOURCE[0]}")/../.."
umask 077
# Secret contents flow directly to the restricted environment file, never stdout.
aws secretsmanager get-secret-value --region "$1" --secret-id "$2" --query SecretString --output text |
python3 -c '
import json, os, sys, tempfile
data=json.load(sys.stdin)
keys=["SDK_API_PRE","SDK_APP_ID","SDK_APP_SECRET"]
if any(not isinstance(data.get(k),str) or not data[k] for k in keys):
    sys.exit("The secret must contain SDK_API_PRE, SDK_APP_ID, and SDK_APP_SECRET.")
values={"APP_ORIGIN":"http://localhost:8080", **{k:data[k] for k in keys}}
if any("\n" in v or "\r" in v or "\x00" in v for v in values.values()):
    sys.exit("Environment values must be single-line strings.")
descriptor,path=tempfile.mkstemp(prefix=".env-aws-",dir=".")
try:
    with os.fdopen(descriptor,"w") as f:
        for k,v in values.items():
            f.write(k+"="+v+"\n")
    os.replace(path,".env.aws")
finally:
    if os.path.exists(path): os.unlink(path)
'
docker compose up -d --build
echo 'Explorer is running on the instance loopback port 8080. Connect with Session Manager port forwarding.'
