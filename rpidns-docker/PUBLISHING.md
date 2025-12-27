# Publishing RpiDNS Docker Images to GHCR

## Quick Start

The GitHub Actions workflow automatically builds and publishes images when you push changes to `rpidns-docker/` on the main branch.

## How It Works

Images are published to:
- `ghcr.io/<your-username>/rpidns-bind`
- `ghcr.io/<your-username>/rpidns-web`

## Setup (One-Time)

1. **Enable GitHub Packages** in your repository settings:
   - Go to Settings → Actions → General
   - Under "Workflow permissions", select "Read and write permissions"
   - Check "Allow GitHub Actions to create and approve pull requests"

2. **Make packages public** (after first build):
   - Go to your GitHub profile → Packages
   - Click on each package (rpidns-bind, rpidns-web)
   - Go to Package settings → Change visibility → Public

## Triggering Builds

### Automatic (on push)
Push changes to `rpidns-docker/` directory on main/master branch.

### Manual (with custom tag)
1. Go to Actions → "Build and Publish RpiDNS Docker Images"
2. Click "Run workflow"
3. Enter a tag (e.g., `v1.0.0`) or leave as `latest`
4. Click "Run workflow"

## Image Tags

| Trigger | Tags Generated |
|---------|----------------|
| Push to main | `latest`, `<sha>`, `main` |
| Manual with tag | `<your-tag>`, `<sha>` |
| Pull request | `pr-<number>` (not pushed) |

## Update docker-compose.yml

After publishing, update image references in `www/io2comm_docker.php`:

```php
// Change from:
image: ghcr.io/homas/rpidns-bind:latest

// To your username:
image: ghcr.io/<your-username>/rpidns-bind:latest
```

## Verify Images

```bash
# Pull and test
docker pull ghcr.io/<your-username>/rpidns-bind:latest
docker pull ghcr.io/<your-username>/rpidns-web:latest

# Check image details
docker inspect ghcr.io/<your-username>/rpidns-bind:latest
```

## Multi-Architecture Support

Images are built for both `linux/amd64` and `linux/arm64` platforms.
