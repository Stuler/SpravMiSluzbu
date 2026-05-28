#!/usr/bin/env bash
set -euo pipefail

scp -P 10434 .docs/rosti-test-nginx-app.conf app@ssh.rosti.cz:/srv/conf/nginx.d/app.conf
ssh -p 10434 app@ssh.rosti.cz 'nginx -t && supervisorctl restart nginx'
