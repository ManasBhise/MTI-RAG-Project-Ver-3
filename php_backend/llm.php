<?php
/**
 * Dynamic Multi-Provider LLM Client in Pure PHP (Groq & Gemini REST APIs)
 */

require_once __DIR__ . '/config.php';

function call_llm(array $messages, float $temperature = 0.1, int $maxTokens = 3500) {
    $primaryProvider = strtolower(PRIMARY_LLM_PROVIDER);

    if ($primaryProvider === 'gemini') {
        $result = call_gemini_api($messages, $temperature, $maxTokens);
        if ($result) return $result;

        error_log("Primary Gemini LLM failed. Falling back to Groq LLM.");
        $result = call_groq_api($messages, $temperature, $maxTokens);
        if ($result) return $result;
    } else {
        // Default to Groq primary
        $result = call_groq_api($messages, $temperature, $maxTokens);
        if ($result) return $result;

        error_log("Primary Groq LLM failed. Falling back to Gemini LLM.");
        $result = call_gemini_api($messages, $temperature, $maxTokens);
        if ($result) return $result;
    }

    throw new Exception("All LLM providers (Groq and Gemini) failed or are unconfigured. Please check API keys.");
}

function call_groq_api(array $messages, float $temperature, int $maxTokens) {
    $apiKey = GROQ_API_KEY;
    if (!$apiKey) {
        error_log("GROQ_API_KEY is not set.");
        return null;
    }

    $url = "https://api.groq.com/openai/v1/chat/completions";

    $payload = [
        "model" => GROQ_MODEL,
        "messages" => $messages,
        "temperature" => $temperature,
        "max_tokens" => $maxTokens,
        "top_p" => 0.95
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("Groq cURL Error: " . $curlError);
        return null;
    }

    if ($httpCode !== 200) {
        error_log("Groq HTTP Error {$httpCode}: " . $response);
        return null;
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? null;
}

function call_gemini_api(array $messages, float $temperature, int $maxTokens) {
    $apiKey = GEMINI_API_KEY;
    if (!$apiKey) {
        error_log("GEMINI_API_KEY is not set.");
        return null;
    }

    $model = GEMINI_MODEL;
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    // Format OpenAI messages into Gemini API contents
    $systemInstruction = null;
    $contents = [];

    foreach ($messages as $msg) {
        $role = $msg['role'];
        $text = $msg['content'];

        if ($role === 'system') {
            $systemInstruction = ["parts" => [["text" => $text]]];
        } else {
            $geminiRole = ($role === 'assistant') ? 'model' : 'user';
            $contents[] = [
                "role" => $geminiRole,
                "parts" => [["text" => $text]]
            ];
        }
    }

    $payload = [
        "contents" => $contents,
        "generationConfig" => [
            "temperature" => $temperature,
            "maxOutputTokens" => $maxTokens
        ]
    ];

    if ($systemInstruction) {
        $payload["systemInstruction"] = $systemInstruction;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("Gemini cURL Error: " . $curlError);
        return null;
    }

    if ($httpCode !== 200) {
        error_log("Gemini HTTP Error {$httpCode}: " . $response);
        return null;
    }

    $data = json_decode($response, true);
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
}
