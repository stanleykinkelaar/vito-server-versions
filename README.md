# Server Versions plugin for VitoDeploy

Adds an **Installed versions** server feature to [VitoDeploy](https://vitodeploy.com) 4.x with one action,
**Check versions**. It shows the exact patch versions of the software on a server and any pending updates for
them, so you can answer "are we affected by CVE X?" from the Vito UI.

![Screenshot](docs/screenshot.png)
<!-- Screenshot placeholder: add docs/screenshot.png -->

## What it reports

- OS release and kernel
- PHP: `php -v` per installed minor version (`/etc/php/X.Y`), the `phpX.Y-fpm` / `phpX.Y-cli` package versions and FPM status
- nginx / Caddy, Redis, MySQL / PostgreSQL, Node.js and Composer versions
- Pending `apt` updates for php, nginx, redis, mysql, mariadb and openssl/libssl packages
- Whether unattended upgrades is enabled and when `apt update` last succeeded

The full output is stored as a server log named `server-versions-check` (**Server > Logs**). A one-line summary is
shown as a toast, e.g. `PHP 8.4.27, 8.3.14 · nginx 1.24.0 · Redis 7.0.15 · Node v22.11.0 · 2 pending updates (1 security)`.

Distribution packages often backport security fixes without changing the upstream version (e.g. Ubuntu's
`8.3.6-0ubuntu0.24.04.13`). When checking a CVE, compare against the package version shown in the log, not only
the `php -v` version.

## Safety

The check is read-only. It runs as Vito's SSH user without `sudo`, does not run `apt update`, and writes nothing
to the server. Missing software is skipped. The plugin has no migrations, no settings and makes no network calls
from Vito.

## Installation

1. Copy or clone this repository to `app/Vito/Plugins/Stanleykinkelaar/VitoServerVersions` on your Vito instance.
2. As the `vito` user, in the Vito directory:

   ```bash
   composer dump-autoload -o && php artisan optimize:clear && php artisan optimize
   ```

3. Go to **Admin > Plugins > Discover**, install **Server Versions**, then enable it in the **Installed** tab.
4. Open a server, go to **Features**, and choose **Installed versions > Actions > Check versions**.

To remove it, disable and uninstall it in **Admin > Plugins** (this deletes the plugin directory), then run the
same three commands again.

## Documentation

You can find everything about Plugin development [here](https://vitodeploy.com/docs/plugins)

## Discovery

To make your plugin discoverable by the Vito app, add `vitodeploy-plugin` topic to your repository on GitHub.
