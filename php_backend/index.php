<?php
/**
 * MTI Knowledge Assistant - PHP API Front Controller
 * REST API Endpoints Router for LAMP Stack Deployment
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, HEAD");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Expose-Headers: *");

// Handle CORS Preflight OPTIONS Request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/llm.php';
require_once __DIR__ . '/rag.php';

// Parse Request Path & Method
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptName = dirname($_SERVER['SCRIPT_NAME']);
$path = '/' . trim(str_replace($scriptName, '', $requestUri), '/');
$method = $_SERVER['REQUEST_METHOD'];

// Helper JSON response
function json_response($data, int $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(0);
}

// Get JSON Body Payload
$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true) ?: [];

// --------------------------------------------------------------------------
// 1. Root & Health Check Endpoints
// --------------------------------------------------------------------------
if ($path === '/' || $path === '/health') {
    json_response(["status" => "ok", "service" => "MTI Knowledge Assistant PHP API"]);
}

// --------------------------------------------------------------------------
// 2. Authentication Endpoints
// --------------------------------------------------------------------------
if ($path === '/auth/anonymous' || $path === '/auth/anonymous/') {
    $user = [
        'id' => 1,
        'name' => 'Meteorologist',
        'email' => 'meteorologist@imd.gov.in',
        'role' => 'Operational Meteorologist',
        'organization' => 'India Meteorological Department (IMD)',
        'response_tone' => 'moderate',
        'custom_instructions' => '',
        'use_emojis' => true
    ];
    $token = generate_jwt(['sub' => 1, 'name' => $user['name'], 'email' => $user['email']]);
    json_response([
        'access_token' => $token,
        'token_type' => 'bearer',
        'user' => $user
    ]);
}

if ($path === '/auth/login' || $path === '/auth/login/') {
    $email = trim($body['email'] ?? 'meteorologist@imd.gov.in');
    $cleanEmail = strtolower($email);
    $name = (strpos($cleanEmail, '@') !== false) 
        ? ucwords(str_replace('.', ' ', explode('@', $cleanEmail)[0])) 
        : 'Meteorologist';

    $user = [
        'id' => 1,
        'name' => $name,
        'email' => $cleanEmail,
        'role' => 'Authorized Meteorologist',
        'organization' => 'Meteorological Training Institute (IMD)',
        'response_tone' => 'moderate',
        'custom_instructions' => '',
        'use_emojis' => true
    ];
    $token = generate_jwt(['sub' => 1, 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']]);
    json_response([
        'access_token' => $token,
        'token_type' => 'bearer',
        'user' => $user
    ]);
}

if ($path === '/logout') {
    json_response(['message' => 'Logged out successfully']);
}

// --------------------------------------------------------------------------
// 3. User Profile Endpoints
// --------------------------------------------------------------------------
if ($path === '/user/profile') {
    $currentUser = get_authenticated_user();

    if ($method === 'GET') {
        json_response($currentUser);
    }

    if ($method === 'PUT') {
        if (isset($body['name'])) $currentUser['name'] = trim($body['name']);
        if (isset($body['role'])) $currentUser['role'] = trim($body['role']);
        if (isset($body['organization'])) $currentUser['organization'] = trim($body['organization']);
        if (isset($body['response_tone'])) $currentUser['response_tone'] = trim($body['response_tone']);
        if (isset($body['custom_instructions'])) $currentUser['custom_instructions'] = trim($body['custom_instructions']);
        if (isset($body['use_emojis'])) $currentUser['use_emojis'] = (bool)$body['use_emojis'];

        json_response($currentUser);
    }
}

// --------------------------------------------------------------------------
// 4. Threads & Chat Endpoints
// --------------------------------------------------------------------------
if ($path === '/threads') {
    json_response([]);
}

if (preg_match('#^/threads/([^/]+)/messages$#', $path, $matches)) {
    json_response([]);
}

if (preg_match('#^/threads/([^/]+)$#', $path, $matches)) {
    $threadId = $matches[1];
    if ($method === 'PUT') {
        $now = date('Y-m-d\TH:i:s\Z');
        json_response([
            'id' => $threadId,
            'title' => trim($body['title'] ?? 'Chat Thread'),
            'created_at' => $now,
            'updated_at' => $now
        ]);
    }
    if ($method === 'DELETE') {
        json_response(['message' => 'Thread cleared']);
    }
}

if ($path === '/history') {
    if ($method === 'GET') json_response([]);
    if ($method === 'DELETE') json_response(['message' => 'All history cleared']);
}

if (preg_match('#^/history/(\d+)$#', $path, $matches)) {
    json_response(['message' => 'History entry cleared']);
}

// --------------------------------------------------------------------------
// 5. Main Chat & Translation Endpoints
// --------------------------------------------------------------------------
if ($path === '/chat' || $path === '/chat/') {
    $question = trim($body['question'] ?? '');
    if (!$question) {
        json_response(['detail' => 'Question cannot be empty'], 400);
    }

    $threadId = $body['thread_id'] ?? ('thread_' . substr(md5(uniqid()), 0, 12));
    $mode = $body['mode'] ?? 'moderate';
    $chatHistory = $body['chat_history'] ?? [];

    $currentUser = get_authenticated_user();

    $result = generate_rag_answer($question, $mode, $chatHistory, $currentUser);

    $msgId = (int)(microtime(true) * 1000);
    $now = date('Y-m-d\TH:i:s\Z');

    json_response([
        'id' => $msgId,
        'thread_id' => $threadId,
        'answer' => $result['answer'],
        'sources' => $result['sources'],
        'images' => $result['images'],
        'timestamp' => $now
    ]);
}

if ($path === '/chat/translate') {
    $text = trim($body['text'] ?? '');
    $targetLang = strtolower(trim($body['target_language'] ?? 'hindi'));

    if (!$text) {
        json_response(['detail' => 'Text to translate cannot be empty'], 400);
    }

    try {
        $langName = ($targetLang === 'hindi' || $targetLang === 'hi') ? 'Hindi (हिंदी - Devanagari script)' : ucfirst($targetLang);
        $systemPrompt = "You are a highly skilled meteorological translator for the Meteorological Training Institute (India Meteorological Department).\n" .
            "Translate the provided meteorological text into clean, professional, and elegant {$langName}.\n\n" .
            "STRICT FORMATTING & TRANSLATION RULES:\n" .
            "1. Preserve ALL markdown layout structures exactly (headers, bold text, bullet points, numbers, formulas).\n" .
            "2. Use accurate, standardized IMD meteorological Hindi terminology.\n" .
            "3. Include key English meteorological terms in parentheses alongside Hindi where helpful (e.g. तापीय संवहन (Thermal Advection), वायुमंडलीय दाब (Atmospheric Pressure)).\n" .
            "4. Do NOT output any intro text, conversational filler, or commentary. Output ONLY the translated markdown text.";

        $messages = [
            ["role" => "system", "content" => $systemPrompt],
            ["role" => "user", "content" => $text]
        ];

        $translated = call_llm($messages, 0.1, 2000);

        json_response([
            'translated_text' => $translated,
            'language' => $targetLang
        ]);
    } catch (Exception $e) {
        json_response(['detail' => 'Translation failed: ' . $e->getMessage()], 500);
    }
}

// --------------------------------------------------------------------------
// 6. Knowledge Documents Endpoints
// --------------------------------------------------------------------------
if ($path === '/documents') {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->query("SELECT id, filename, file_size, page_count, uploaded_at FROM documents ORDER BY id DESC");
        $docs = $stmt->fetchAll();
        json_response(['status' => 'success', 'total' => count($docs), 'documents' => $docs]);
    } catch (Exception $e) {
        json_response(['status' => 'success', 'total' => 0, 'documents' => []]);
    }
}

if ($path === '/documents/upload') {
    if (empty($_FILES['file'])) {
        json_response(['detail' => 'No file uploaded'], 400);
    }

    $file = $_FILES['file'];
    $filename = preg_replace('/[^\w\s.-]/', '', basename($file['name']));
    $filename = str_replace(' ', '_', $filename);

    if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
        json_response(['detail' => 'Only PDF documents are supported'], 400);
    }

    $destPath = DATA_DIR . '/' . $filename;
    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("INSERT INTO documents (filename, file_size) VALUES (?, ?) ON DUPLICATE KEY UPDATE file_size = VALUES(file_size)");
            $stmt->execute([$filename, $file['size']]);

            json_response([
                'status' => 'success',
                'message' => "Successfully uploaded and indexed '{$filename}'.",
                'filename' => $filename
            ]);
        } catch (Exception $e) {
            json_response(['detail' => 'Failed database index: ' . $e->getMessage()], 500);
        }
    } else {
        json_response(['detail' => 'Failed to save uploaded file'], 500);
    }
}

if (preg_match('#^/documents/([^/]+)$#', $path, $matches)) {
    if ($method === 'DELETE') {
        $filename = urldecode($matches[1]);
        $filePath = DATA_DIR . '/' . $filename;

        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("DELETE FROM documents WHERE filename = ?");
            $stmt->execute([$filename]);

            $stmt2 = $pdo->prepare("DELETE FROM document_chunks WHERE filename = ?");
            $stmt2->execute([$filename]);
        } catch (Exception $e) {
            // Ignore DB delete error
        }

        json_response(['status' => 'success', 'message' => "Document '{$filename}' removed."]);
    }
}

// Fallback 404 for unknown endpoints
json_response(['detail' => "Endpoint not found: {$method} {$path}"], 404);
