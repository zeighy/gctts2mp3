# gctts2mp3
Download Google Cloud TTS as MP3 with PHP

# Setup
```
composer require google/cloud-text-to-speech
```
Since this also includes a Cloudflare Turnstile implementation for bot protection, get your Turnstile keys here: [https://dash.cloudflare.com/](https://dash.cloudflare.com/?to=/:account/turnstile)
Update config.php and index.html files accordingly.

And then setup your Google Cloud TTS:
Set up a Google Cloud project:
1. Go to the Google Cloud Console (https://console.cloud.google.com/).
2. Click on the project dropdown and select "New Project".
3. Give your project a name and click "Create".
Enable the Text-to-Speech API:
4. In the Google Cloud Console, go to the Navigation menu (hamburger icon) and select "APIs & Services" > "Library".
5. Search for "Cloud Text-to-Speech API" and click on it.
6. Click "Enable" to activate the API for your project.
Create credentials:
7. In the Google Cloud Console, go to "APIs & Services" > "Credentials".
8. Click "Create Credentials" and select "Service Account".
9. Fill in the service account details and click "Create".
10. Grant this service account the "Cloud Text-to-Speech API User" role.
11. Click "Continue" and then "Done".
12. In the Credentials page, find your new service account and click on it.
13. Go to the "Keys" tab and click "Add Key" > "Create new key".
14. Choose JSON as the key type and click "Create". This will download a JSON file.
15. Place the json file in the creds folder and update the config.php file accordingly.
