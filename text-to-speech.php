<?php
// text_to_speech.php

// Security check to prevent direct access to the credentials file
define('SECURE_ACCESS', true);

// Load credentials
$credentialsFile = __DIR__ . '/creds/config.php';
if (!file_exists($credentialsFile)) {
    die('Credentials file not found');
}
require_once $credentialsFile;

require_once 'vendor/autoload.php';
use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;

// Log file paths
$requestLogFile = 'tts_requests.log';
$monthlyStatsLogFile = 'monthly_stats.log';

// Character limits
$charLimit = 2000;
$monthlyCharLimit = 300000;

// Function to sanitize input text
function sanitizeText($text) {
    // Convert ampersand to "and"
    $text = preg_replace('/&/', ' and ', $text);
    
    // Remove control characters
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
    
    // Remove potentially dangerous characters
    $text = preg_replace('/[<>\'"\{\}]/u', '', $text);
    
    // Normalize whitespace
    $text = preg_replace('/\s+/u', ' ', $text);
    
    // Trim whitespace from start and end
    return trim($text);
}

// Function to log successful requests
function logRequest($text, $charCount, $voice) {
    global $requestLogFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] | Characters: $charCount | Voice: $voice | Text: " . $text . PHP_EOL;
    $logEntry .= str_repeat('-', 80) . PHP_EOL; // Add a separator line
    file_put_contents($requestLogFile, $logEntry, FILE_APPEND);
}

// Function to get and update monthly character count
function updateMonthlyCharCount($charCount) {
    global $monthlyStatsLogFile, $monthlyCharLimit;
    $month = date('Y-m');
    $currentMonthCount = 0;
    
    if (file_exists($monthlyStatsLogFile)) {
        $currentLog = file_get_contents($monthlyStatsLogFile);
        $lines = explode(PHP_EOL, trim($currentLog));
        $lastLine = end($lines);
        $lastLineData = explode('|', $lastLine);
        
        if ($lastLineData[0] === $month) {
            $currentMonthCount = intval($lastLineData[1]);
            $newCount = $currentMonthCount + $charCount;
            $lines[count($lines) - 1] = "$month|$newCount";
            file_put_contents($monthlyStatsLogFile, implode(PHP_EOL, $lines) . PHP_EOL);
        } else {
            $newCount = $charCount;
            $logEntry = "$month|$newCount" . PHP_EOL;
            file_put_contents($monthlyStatsLogFile, $logEntry, FILE_APPEND);
        }
    } else {
        $newCount = $charCount;
        $logEntry = "$month|$newCount" . PHP_EOL;
        file_put_contents($monthlyStatsLogFile, $logEntry);
    }
    
    return $newCount <= $monthlyCharLimit ? $newCount : false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = $_POST['text'] ?? '';
    $voice = $_POST['voice'] ?? '';
    $turnstileResponse = $_POST['cf-turnstile-response'] ?? '';

    if (empty($text) || empty($voice) || empty($turnstileResponse)) {
        echo json_encode(['error' => 'Please fill in all fields and complete the Turnstile challenge.']);
        exit;
    }

    // Sanitize the input text
    $text = sanitizeText($text);
    $charCount = mb_strlen($text);

    // Check character limit
    if ($charCount > $charLimit) {
        echo json_encode(['error' => "Text exceeds the $charLimit character limit. Please shorten your text."]);
        exit;
    }

    // Check and update monthly character count
    $updatedCount = updateMonthlyCharCount($charCount);
    if ($updatedCount === false) {
        echo json_encode(['error' => "Monthly character limit of $monthlyCharLimit has been reached. Please try again next month."]);
        exit;
    }

    // Verify Turnstile response
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://challenges.cloudflare.com/turnstile/v0/siteverify");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => $turnstileSecretKey,
        'response' => $turnstileResponse
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $turnstileResult = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!$turnstileResult['success']) {
        echo json_encode(['error' => 'Turnstile verification failed. Please try again.']);
        exit;
    }

    try {
        $client = new TextToSpeechClient([
            'credentials' => $googleCloudCredentialsPath
        ]);

        $input = new SynthesisInput();
        $input->setText($text);

        $voiceParams = new VoiceSelectionParams();
        $voiceParams->setName($voice);

        // Set the language code based on the selected voice
        if (strpos($voice, 'fr-CA') === 0) {
            $voiceParams->setLanguageCode('fr-CA');
        } elseif (strpos($voice, 'en-US') === 0) {
            $voiceParams->setLanguageCode('en-US');
        } elseif (strpos($voice, 'es-US') === 0) {
            $voiceParams->setLanguageCode('es-US');
        }

        $audioConfig = new AudioConfig();
        $audioConfig->setAudioEncoding(AudioEncoding::MP3);

        $response = $client->synthesizeSpeech($input, $voiceParams, $audioConfig);
        $audioContent = base64_encode($response->getAudioContent());

        // Log the successful request
        logRequest($text, $charCount, $voice);

        echo json_encode(['success' => true, 'audioContent' => $audioContent]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
