# Deployment Guide for Boarding House Management System

## Recommended Stack

### Backend
- **Language**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Server**: Apache (with XAMPP for local dev) or Heroku for production

### Frontend
- **Technologies**: HTML5, CSS3, JavaScript
- **Framework**: Bootstrap for responsive design
- **PWA Features**: Manifest.json, Service Worker for offline capabilities

### Mobile App
- **Framework**: Capacitor (Ionic's cross-platform runtime)
- **Type**: WebView-based Android app (wraps the web app)
- **PWA Alternative**: Installable directly from browser on Android

### Deployment Platforms
- **Web**: Heroku (free tier available)
- **Mobile**: Google Play Store

## Implementation Steps Completed

### PWA Enhancements
- Added `www/manifest.json` for app metadata and icons
- Added `www/sw.js` for caching and offline functionality
- Updated `www/index.html` with PWA meta tags and service worker registration

### Android App Build
- Capacitor configured with app ID `com.boardinghouse.app`
- Android project synced and APK built using Gradle

## Deployment Steps

### 1. Web Deployment to Heroku

Follow the tasks in `TODO_deployment.md`:
- Install Heroku CLI
- Login to Heroku account
- Create new Heroku app
- Add Heroku Postgres add-on
- Set environment variables for database credentials
- Deploy application to Heroku
- Update `boarding_house_url.txt` with new URL
- Test deployed app from Android browser

### 2. Android App Deployment to Google Play Store

#### Prerequisites
- Google Play Developer account ($25 one-time fee)
- APK file (generated from Capacitor build)

#### Steps
1. **Prepare APK**:
   - Ensure APK is signed for release (not debug)
   - Test APK on Android device

2. **Create Play Store Listing**:
   - Go to Google Play Console
   - Create new app
   - Upload APK
   - Add app description, screenshots, icons
   - Set pricing (free or paid)
   - Configure content rating

3. **Publish**:
   - Submit for review
   - Wait for approval (usually 1-3 days)

### 3. PWA Installation

Users can install the PWA directly from the browser:
- Open the web app in Chrome on Android
- Tap "Add to Home screen"
- App appears as native app icon

## Testing

### Local Testing
- **Web**: Run XAMPP, access via `http://localhost/BH/login.php`
- **PWA**: Use Chrome DevTools Lighthouse to audit PWA features
- **Android**: Use `npx cap run android` to test on device/emulator

### Production Testing
- Test all features on Heroku URL
- Test PWA installation
- Test Android APK before Play Store submission

## Maintenance

- Update Heroku environment variables as needed
- Monitor Heroku logs for errors
- Update Play Store listing with new versions
- Keep PWA service worker updated for caching strategies

## Security Considerations

- Use HTTPS on Heroku (enabled by default)
- Secure database credentials in environment variables
- Implement proper authentication and authorization
- Regularly update dependencies

## Cost Estimation

- **Heroku**: Free tier sufficient for small usage, ~$7/month for hobby dyno
- **Google Play**: $25 one-time developer fee
- **Domain**: Optional, Heroku provides subdomain

This setup provides a cost-effective, scalable solution accessible via URL, installable as PWA, and deployable to Play Store.
