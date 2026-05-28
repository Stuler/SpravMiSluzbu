# Roští Test Deployment

Target test app:

- SSH: `app@ssh.rosti.cz:10434`
- Deploy path: `/srv/app`
- Suggested domain: `test.spravmisluzbu.sk`
- Git branch: `beta`

## DNS And Domain

The test subdomain is chosen by us. `test.spravmisluzbu.sk` is a good fit if it is not already used.

If DNS for `spravmisluzbu.sk` is not hosted by Roští, create these records for `test.spravmisluzbu.sk`:

```text
A     test    185.58.41.93
AAAA  test    2a01:430:144::2
```

Then add `test.spravmisluzbu.sk` to the Roští app domains in the app Parameters tab and enable HTTPS.

## GitHub Secrets

Required:

```text
ROSTI_TEST_SSH_KEY_BASE64=<base64 encoded private deploy key for app@ssh.rosti.cz:10434>
ROSTI_TEST_LOCAL_NEON_BASE64=<base64 encoded config/local.neon for test>
```

The workflow creates `/srv/app/config/local.neon` from `ROSTI_TEST_LOCAL_NEON_BASE64` before rsync upload. Do not keep the real test `local.neon` in Git.

Use `.docs/rosti-test-local.neon.example` as the starting point:

```bash
cp .docs/rosti-test-local.neon.example /tmp/rosti-test-local.neon
nano /tmp/rosti-test-local.neon
base64 -w 0 /tmp/rosti-test-local.neon
```

Optional, already defaulted in the workflow:

```text
ROSTI_TEST_SSH_HOST=ssh.rosti.cz
ROSTI_TEST_SSH_PORT=10434
ROSTI_TEST_DEPLOY_PATH=/srv/app
```

These values are hardcoded from `ssh://app@ssh.rosti.cz:10434` to avoid malformed GitHub secrets breaking `ssh-keyscan`.

Create the deploy key locally:

```bash
ssh-keygen -t ed25519 -C "github-actions-spravmisluzbu-test" -f ~/.ssh/spravmisluzbu_rosti_test
cat ~/.ssh/spravmisluzbu_rosti_test.pub | ssh -p 10434 app@ssh.rosti.cz 'mkdir -p /srv/.ssh && cat >> /srv/.ssh/authorized_keys && chmod 700 /srv/.ssh && chmod 600 /srv/.ssh/authorized_keys'
base64 -w 0 ~/.ssh/spravmisluzbu_rosti_test
```

Generate the config secret:

```bash
base64 -w 0 config/local.neon
```

## Roští Nginx

Set `/srv/conf/nginx.d/app.conf` on Roští to serve the Nette public directory. The prepared file is `.docs/rosti-test-nginx-app.conf`.

Upload, validate, and restart:

```bash
bash .docs/rosti-test-upload-nginx.sh
```

## Nette Proxy

Roští runs behind a reverse proxy. Add this to the test `local.neon` or environment config:

```neon
http:
    proxy: 127.0.0.1
```
