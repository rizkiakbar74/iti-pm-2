# Test Environment Setup

## Local XAMPP

Local URL:

```text
http://localhost/itipm_php_mysql_starter/login.php
```

But cloud-based TestSprite may not access this URL.

## Option A — Staging Hosting

Upload project to staging hosting, import database, then test:

```text
https://your-staging-domain.com/itipm/login.php
```

This is the cleanest option.

## Option B — ngrok Tunnel

If testing local XAMPP through public tunnel:

```bash
ngrok http 80
```

Then URL may look like:

```text
https://xxxx.ngrok-free.app/itipm_php_mysql_starter/login.php
```

Use that as `{{APP_BASE_URL}}`.

Risks:
- tunnel may expire
- slower
- upload paths/session may behave differently

## Option C — Cloudflare Tunnel

Alternative public tunnel:

```bash
cloudflared tunnel --url http://localhost
```

Then use the generated public URL.

## Database Reset Before Test

Before full test, import:

```text
database/schema.sql
```

Then all accounts reset to:

```text
password
```

## Important

After import ulang, old test data disappears. That is expected.
