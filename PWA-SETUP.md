# PWA Setup - Claara

## Files

- `public/manifest.json` - PWA metadata
- `public/sw.js` - Service Worker with a network-first strategy and minimal cache
- `public/includes/head.php` - Shared PWA meta tags

## Required Icons

Create and place these icons in `public/assets/icons/`:

- `icon-72x72.png`
- `icon-96x96.png`
- `icon-128x128.png`
- `icon-144x144.png`
- `icon-152x152.png`
- `icon-192x192.png`
- `icon-384x384.png`
- `icon-512x512.png`
- `icon-192x192-maskable.png`
- `icon-512x512-maskable.png`

For maskable icons, keep the logo inside the central safe area so Android does not crop it.

## Screenshots

Optional screenshots can be placed in `public/assets/screenshots/`:

- `screenshot-mobile-1.png` around 390x844
- `screenshot-desktop-1.png` around 1280x800

If screenshots are not provided, remove the `screenshots` section from `manifest.json`.

## Current Configuration

- **Name**: Claara
- **Theme color**: `#B7C9F2`
- **Orientation**: Portrait
- **Display**: Standalone
- **Start URL**: `/`
