<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_admin()) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$provider = $input['provider'] ?? 'gemini';
$apiKey = trim($input['api_key'] ?? '');
$prompt = trim($input['prompt'] ?? '');
$topic = trim($input['topic'] ?? '');
$imageBase64 = $input['image_base64'] ?? null;

if (!$prompt) {
    echo json_encode(['error' => 'Please enter a prompt or question idea.']);
    exit;
}

if (!$apiKey) {
    echo json_encode(['error' => 'API Key is required. Please enter your Gemini or DeepSeek API key.']);
    exit;
}

$systemPrompt = "You are a professor in Machine Dynamics and Engineering Mechanics. Create a high-quality university practice question based on the user prompt and optional image. Output MUST be a single raw valid JSON object (DO NOT wrap in markdown or ```json codeblocks) matching this EXACT schema:

{
  \"code\": \"T1.X\",
  \"topic\": \"Topic Name\",
  \"title\": \"Short Question Title\",
  \"statement\": \"Complete clear problem statement with numerical values and units.\",
  \"sketch\": \"Brief note on sketching/drawing before solving (or empty).\",
  \"given\": [
    [\"label\", \"value\"]
  ],
  \"hint\": {
    \"approach\": \"General physical approach and method\",
    \"formulas\": [\"formula 1\", \"formula 2\"],
    \"plan\": [\"Step 1 plan\", \"Step 2 plan\"],
    \"tip\": \"Useful tip for avoiding common mistakes\"
  },
  \"parts\": [
    { \"label\": \"(a) Question part 1\", \"value\": 12.5, \"unit\": \"m/s\" }
  ],
  \"steps\": [
    { \"t\": \"Step 1: Title\", \"d\": \"Step 1 detailed working with calculations.\" }
  ],
  \"original\": \"Full worked solution text with all formulas and final numerical answers.\",
  \"seed\": \"Variables seed for built-in calculator\"
}";

if ($topic) {
    $systemPrompt .= "\nTopic context: " . $topic;
}

if ($provider === 'gemini') {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . urlencode($apiKey);

    $parts = [
        ['text' => $systemPrompt . "\n\nUser Request: " . $prompt]
    ];

    if ($imageBase64 && str_contains($imageBase64, 'base64,')) {
        $metaAndData = explode('base64,', $imageBase64);
        $mime = str_replace(['data:', ';'], '', $metaAndData[0]);
        $data = $metaAndData[1];
        $parts[] = [
            'inline_data' => [
                'mime_type' => $mime ?: 'image/png',
                'data' => $data
            ]
        ];
    }

    $payload = [
        'contents' => [
            ['parts' => $parts]
        ],
        'generationConfig' => [
            'response_mime_type' => 'application/json'
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        echo json_encode(['error' => 'Gemini API call failed (HTTP ' . $httpCode . '). Please check your API key.']);
        exit;
    }

    $resData = json_decode($response, true);
    $text = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $cleanJson = trim(preg_replace('/^```(json)?|```$/m', '', trim($text)));
    $parsed = json_decode($cleanJson, true);

    if (!$parsed) {
        echo json_encode(['error' => 'Failed to parse AI response as JSON.', 'raw' => $text]);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $parsed]);
    exit;

} else {
    // DeepSeek API
    $url = 'https://api.deepseek.com/chat/completions';

    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $prompt]
    ];

    $payload = [
        'model' => 'deepseek-chat',
        'messages' => $messages,
        'response_format' => ['type' => 'json_object']
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        echo json_encode(['error' => 'DeepSeek API call failed (HTTP ' . $httpCode . '). Please check your API key.']);
        exit;
    }

    $resData = json_decode($response, true);
    $text = $resData['choices'][0]['message']['content'] ?? '';
    $cleanJson = trim(preg_replace('/^```(json)?|```$/m', '', trim($text)));
    $parsed = json_decode($cleanJson, true);

    if (!$parsed) {
        echo json_encode(['error' => 'Failed to parse DeepSeek response as JSON.', 'raw' => $text]);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $parsed]);
    exit;
}
