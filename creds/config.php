<?php
// ./creds/config.php

// Cloudflare Turnstile secret key
$turnstileSecretKey = 'YOUR_TURNSTILE_SECRET_KEY';

// Google Cloud Text-to-Speech credentials file path
$googleCloudCredentialsPath = '/path/to/your/google-cloud-credentials.json';

// Ensure this file is not accessible via web
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}
