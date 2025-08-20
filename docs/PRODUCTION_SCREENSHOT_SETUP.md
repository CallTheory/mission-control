# Production Setup for Board Check Screenshot Generation

This document outlines the requirements and setup steps for enabling screenshot generation in production environments for the PeoplePraise API integration.

## Overview

The board check feature exports problematic call items to PeoplePraise, including PNG screenshots of message summaries. This functionality uses Puppeteer and Chrome headless browser to convert HTML to images.

## Requirements

### System Dependencies

The following system packages must be installed on the production server:

```bash
# For Ubuntu/Debian systems
apt-get update && apt-get install -y \
    wget \
    gnupg \
    ca-certificates \
    fonts-liberation \
    libappindicator3-1 \
    libasound2 \
    libatk-bridge2.0-0 \
    libatk1.0-0 \
    libc6 \
    libcairo2 \
    libcups2 \
    libdbus-1-3 \
    libexpat1 \
    libfontconfig1 \
    libgbm1 \
    libgcc1 \
    libglib2.0-0 \
    libgtk-3-0 \
    libnspr4 \
    libnss3 \
    libpango-1.0-0 \
    libpangocairo-1.0-0 \
    libstdc++6 \
    libx11-6 \
    libx11-xcb1 \
    libxcb1 \
    libxcomposite1 \
    libxcursor1 \
    libxdamage1 \
    libxext6 \
    libxfixes3 \
    libxi6 \
    libxrandr2 \
    libxrender1 \
    libxss1 \
    libxtst6 \
    lsb-release \
    xdg-utils

# For RHEL/CentOS/Fedora systems
yum install -y \
    alsa-lib \
    atk \
    cups-libs \
    gtk3 \
    ipa-gothic-fonts \
    libXcomposite \
    libXcursor \
    libXdamage \
    libXext \
    libXi \
    libXrandr \
    libXScrnSaver \
    libXtst \
    pango \
    xorg-x11-fonts-100dpi \
    xorg-x11-fonts-75dpi \
    xorg-x11-fonts-cyrillic \
    xorg-x11-fonts-misc \
    xorg-x11-fonts-Type1 \
    xorg-x11-utils
```

### Node.js and NPM

Ensure Node.js (v18+ recommended) and NPM are installed:

```bash
# Check versions
node --version  # Should be v18.0.0 or higher
npm --version   # Should be v8.0.0 or higher
```

## Installation Steps

### 1. Install NPM Dependencies

```bash
cd /path/to/mission-control
npm install --production
```

### 2. Install Chrome Headless Browser

Run this command as the web server user (e.g., www-data, nginx, apache):

```bash
# As the web server user
sudo -u www-data npx puppeteer browsers install chrome-headless-shell

# Or if using a different user
sudo -u [webserver-user] npx puppeteer browsers install chrome-headless-shell
```

The Chrome binary will be installed to:
- `~/.cache/puppeteer/chrome-headless-shell/` (in the user's home directory)

### 3. Set Permissions

Ensure the web server user has proper permissions:

```bash
# Create cache directory if it doesn't exist
sudo -u www-data mkdir -p ~/.cache/puppeteer

# Set proper ownership
chown -R www-data:www-data /path/to/mission-control/node_modules
chown -R www-data:www-data ~/.cache/puppeteer
```

### 4. Environment Configuration

Add the following to your `.env` file if needed:

```env
# Optional: Explicitly set the Chrome binary path if auto-detection fails
CHROME_BINARY_PATH=/home/www-data/.cache/puppeteer/chrome-headless-shell/linux-*/chrome-headless-shell-linux64/chrome-headless-shell

# Ensure proper app environment
APP_ENV=production
```

## Docker/Container Deployments

### Using Laravel Sail (Development)

```bash
# Install Chrome in the Sail container
./vendor/bin/sail exec -u sail mission-control.test npx puppeteer browsers install chrome-headless-shell
```

### Custom Docker Image

Add to your Dockerfile:

```dockerfile
# Install Chrome dependencies
RUN apt-get update && apt-get install -y \
    wget gnupg ca-certificates \
    fonts-liberation libappindicator3-1 libasound2 libatk-bridge2.0-0 \
    libatk1.0-0 libc6 libcairo2 libcups2 libdbus-1-3 libexpat1 \
    libfontconfig1 libgbm1 libgcc1 libglib2.0-0 libgtk-3-0 libnspr4 \
    libnss3 libpango-1.0-0 libpangocairo-1.0-0 libstdc++6 libx11-6 \
    libx11-xcb1 libxcb1 libxcomposite1 libxcursor1 libxdamage1 libxext6 \
    libxfixes3 libxi6 libxrandr2 libxrender1 libxss1 libxtst6 \
    lsb-release xdg-utils \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js dependencies
COPY package*.json ./
RUN npm ci --production

# Install Puppeteer Chrome
RUN npx puppeteer browsers install chrome-headless-shell

# Set Chrome binary permissions
RUN chmod -R 755 /root/.cache/puppeteer
```

### Kubernetes/Container Orchestration

For Kubernetes deployments, consider:

1. **Init Container**: Use an init container to install Chrome:

```yaml
initContainers:
  - name: install-chrome
    image: your-app-image
    command: ['npx', 'puppeteer', 'browsers', 'install', 'chrome-headless-shell']
    volumeMounts:
      - name: puppeteer-cache
        mountPath: /home/www-data/.cache/puppeteer
```

2. **Persistent Volume**: Mount a persistent volume for the Puppeteer cache to avoid re-downloading Chrome:

```yaml
volumes:
  - name: puppeteer-cache
    persistentVolumeClaim:
      claimName: puppeteer-cache-pvc
```

## Verification

### 1. Test Chrome Installation

```bash
# As the web server user
sudo -u www-data php artisan tinker
>>> use Spatie\Browsershot\Browsershot;
>>> Browsershot::html('<h1>Test</h1>')->noSandbox()->screenshot();
# Should return binary image data without errors
```

### 2. Test Screenshot Generation

```bash
# Run the test suite
php artisan test --filter=RenderMessageSummaryTest
```

### 3. Test Board Check Export

```bash
# Manually trigger a test export
php artisan tinker
>>> use App\Utilities\RenderMessageSummary;
>>> $result = RenderMessageSummary::htmlToImage('<h1>Production Test</h1>');
>>> strlen($result) > 0 ? 'SUCCESS' : 'FAILED';
# Should output: SUCCESS
```

## Troubleshooting

### Common Issues and Solutions

#### 1. Chrome Binary Not Found

**Error**: `Browser was not found at the configured executablePath`

**Solution**:
```bash
# Verify Chrome is installed
ls -la ~/.cache/puppeteer/chrome-headless-shell/

# Reinstall if missing
npx puppeteer browsers install chrome-headless-shell
```

#### 2. Permission Denied

**Error**: `EACCES: permission denied`

**Solution**:
```bash
# Fix ownership
chown -R www-data:www-data ~/.cache/puppeteer
chmod -R 755 ~/.cache/puppeteer
```

#### 3. Missing System Dependencies

**Error**: `error while loading shared libraries`

**Solution**:
```bash
# Install missing libraries (Ubuntu/Debian)
apt-get update && apt-get install -y libgbm1 libxss1
```

#### 4. Sandbox Errors

**Error**: `Running as root without --no-sandbox is not supported`

**Solution**:
The code automatically adds `--no-sandbox` flag in production. Ensure you're using the latest version of `RenderMessageSummary.php`.

#### 5. Memory Issues

**Error**: `Chrome crashed` or timeout errors

**Solution**:
```bash
# Increase PHP memory limit
echo "memory_limit = 512M" >> /etc/php/8.2/cli/php.ini

# For containers, increase memory allocation
docker run -m 2g your-image
```

### Logging

Screenshot generation errors are logged to Laravel's log file. Check:

```bash
tail -f storage/logs/laravel.log | grep "Screenshot generation"
```

## Performance Considerations

1. **Queue Workers**: Ensure sufficient queue workers for the `people-praise` queue:
   ```bash
   php artisan queue:work people-praise --timeout=60 --tries=3
   ```

2. **Memory**: Screenshot generation requires ~100-200MB RAM per process

3. **CPU**: Chrome rendering is CPU-intensive; monitor server load

4. **Caching**: Chrome binary is cached in `~/.cache/puppeteer` (usually ~150MB)

## Security Notes

1. The `--no-sandbox` flag is used for Chrome in containerized environments. This is safe for server-side rendering where you control the HTML content.

2. Never expose the screenshot generation endpoint directly to users.

3. Sanitize any user input that might be included in the screenshot HTML.

## Monitoring

Set up monitoring for:

1. **Failed Jobs**: Monitor the `failed_jobs` table for PeoplePraise export failures
2. **Queue Length**: Monitor `people-praise` queue length
3. **Disk Space**: Monitor `~/.cache/puppeteer` directory size
4. **Error Logs**: Set up alerts for "Screenshot generation failed" in logs

## Maintenance

### Updating Chrome

Periodically update the Chrome browser:

```bash
# Remove old version
rm -rf ~/.cache/puppeteer

# Install latest
npx puppeteer browsers install chrome-headless-shell
```

### Cleaning Up

Remove old screenshot temp files:

```bash
# Clean temp files older than 7 days
find /tmp -name "*.html" -mtime +7 -delete
```

## Support

For issues related to:
- **Puppeteer**: https://github.com/puppeteer/puppeteer/issues
- **Browsershot**: https://github.com/spatie/browsershot/issues
- **Application**: Check application logs and create an issue in the project repository