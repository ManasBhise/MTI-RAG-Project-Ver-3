<?php
/**
 * RAG Pipeline Engine & Document Search in PHP for LAMP Stack
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/llm.php';

function is_meteorology_query(string $question): bool {
    if (strlen(trim($question)) < 2) return false;

    $qLower = mb_strtolower(trim($question));

    // Basic greetings / assistant queries allowed
    $greetings = ['hi', 'hello', 'hey', 'namaste', 'good morning', 'good evening', 'good afternoon', 'help', 'who are you', 'what can you do'];
    $cleanQ = preg_replace('/[^\w\s]/u', '', $qLower);
    if (in_array(trim($cleanQ), $greetings)) {
        return true;
    }

    $nonDomainTriggers = [
        'hitler', 'nazi', 'world war', 'president', 'prime minister', 'governor', 'politician', 'politics', 'election',
        'democracy', 'monarchy', 'capital of', 'who is the prime minister', 'who is the president', 'who won',
        'movie', 'film', 'cinema', 'actor', 'actress', 'celebrity', 'song', 'lyrics', 'singer', 'album',
        'football', 'cricket match', 'ipl', 'fifa', 'nba', 'basketball', 'tennis', 'olympics', 'game',
        'intern at', 'internship at', 'software intern', 'software engineer interview', 'get into google',
        'resume tips', 'placement', 'job interview', 'campus interview', 'software developer', 'hiring process',
        'code for addition', 'add two numbers', 'write a code for', 'write me a code for', 'fibonacci', 'factorial program',
        'recipe', 'how to cook', 'bake a cake', 'diet plan', 'workout', 'bitcoin', 'cryptocurrency', 'stock market'
    ];

    $meteorologyExceptions = [
        'weather', 'climate', 'meteorol', 'atmosphere', 'atmospheric', 'forecast', 'monsoon',
        'cyclone', 'radar', 'satellite', 'wind', 'temperature', 'pressure', 'humidity', 'rain',
        'precipitation', 'cloud', 'nwp', 'wrf', 'mti', 'imd', 'wmo', 'sounding', 'radiosonde',
        'aviation', 'aeronautic', 'flight', 'metar', 'taf', 'sigmet', 'turbulence', 'wind shear', 'icing',
        'troposphere', 'stratosphere', 'boundary layer', 'inversion', 'lapse rate', 'coriolis', 'vorticity'
    ];

    foreach ($nonDomainTriggers as $trigger) {
        if (strpos($qLower, $trigger) !== false) {
            $hasException = false;
            foreach ($meteorologyExceptions as $exc) {
                if (strpos($qLower, $exc) !== false) {
                    $hasException = true;
                    break;
                }
            }
            if (!$hasException) {
                return false;
            }
        }
    }

    return true;
}

function retrieve_relevant_chunks(string $question, int $limit = 5): array {
    try {
        $pdo = get_db_connection();

        // Extract search keywords (length >= 3)
        preg_match_all('/\w{3,}/u', mb_strtolower($question), $matches);
        $words = array_unique($matches[0] ?? []);

        if (empty($words)) {
            return [];
        }

        // 1. Try MySQL FULLTEXT search if possible
        $searchTerm = '+' . implode(' +', $words);
        $stmt = $pdo->prepare("
            SELECT filename, page_number, content,
                   MATCH(content) AGAINST(:search IN BOOLEAN MODE) AS score
            FROM document_chunks
            WHERE MATCH(content) AGAINST(:search IN BOOLEAN MODE)
            ORDER BY score DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        try {
            $stmt->execute();
            $results = $stmt->fetchAll();
            if (!empty($results)) {
                return $results;
            }
        } catch (Exception $e) {
            // Fulltext match may fail if boolean mode syntax or indexing differs, fallback to LIKE
        }

        // 2. Fallback LIKE search across chunks
        $likeConditions = [];
        $params = [];
        foreach ($words as $idx => $word) {
            $likeConditions[] = "content LIKE :w{$idx}";
            $params[":w{$idx}"] = '%' . $word . '%';
        }

        $sql = "SELECT filename, page_number, content FROM document_chunks WHERE " . implode(' OR ', $likeConditions) . " LIMIT {$limit}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    } catch (Exception $e) {
        error_log("Retrieval error: " . $e->getMessage());
        return [];
    }
}

function generate_rag_answer(string $question, string $mode = "moderate", array $chatHistory = [], array $userProfile = []): array {
    // 1. Domain Guardrail Check
    $queryToCheck = $question;
    if (!empty($chatHistory)) {
        $lastTurn = end($chatHistory);
        $queryToCheck = ($lastTurn['question'] ?? '') . ' ' . $question;
    }

    if (!is_meteorology_query($queryToCheck)) {
        return [
            "answer" => "I am specialized in MTI meteorological training literature, atmospheric science, and weather forecasting. I cannot assist with non-meteorological or general topics.",
            "sources" => [],
            "images" => []
        ];
    }

    // 2. Retrieve Context Chunks from MySQL
    $retrievedChunks = retrieve_relevant_chunks($question, 5);

    $contextText = "";
    $sources = [];
    foreach ($retrievedChunks as $chunk) {
        $srcName = $chunk['filename'] . " (page " . $chunk['page_number'] . ")";
        $contextText .= "\n--- Source: {$srcName} ---\n" . $chunk['content'] . "\n";
        if (!in_array($srcName, $sources)) {
            $sources[] = $srcName;
        }
    }

    if (empty($sources)) {
        $sources[] = "MTI Knowledge Repository (Cloud Synthesis)";
    }

    // 3. Build System & User Prompts
    $systemPrompt = "You are the official MTI Knowledge Assistant for the Meteorological Training Institute (India Meteorological Department - IMD).\n" .
        "You are a premier pedagogical and scientific authority in meteorological training literature, atmospheric physics, weather forecasting, aeronautical/aviation meteorology, numerical weather prediction (NWP), radar/satellite remote sensing, oceanography, and atmospheric sciences.\n\n" .
        "CORE INSTRUCTIONS FOR DETAILED EXPLANATIONS:\n" .
        "1. Provide exhaustive, highly structured, and deeply educational responses for all meteorological questions. Do NOT provide brief or superficial summaries.\n" .
        "2. Detail the underlying physical principles, atmospheric thermodynamics, mathematical formulations/equations with variable definitions, synoptic setups, radar/satellite signatures, and practical operational forecasting applications.\n" .
        "3. Organize your answer into distinct markdown sections with bold headers (e.g. `### 1. Comprehensive Overview & Scientific Definition`, `### 2. Physical & Thermodynamic Mechanisms`, `### 3. Mathematical Formulation & Governing Equations`, `### 4. Synoptic, Radar & Satellite Observational Signatures`, `### 5. Operational, Aviation & Forecasting Implications`, `### 6. Key Takeaways & Summary Matrix`).\n" .
        "4. Strict anti-hallucination: Only present verified atmospheric science facts.";

    if ($contextText) {
        $systemPrompt .= "\n\nOFFICIAL MTI CONTEXT DOCUMENTS:\n" . $contextText;
    }

    $messages = [
        ["role" => "system", "content" => $systemPrompt]
    ];

    // Add recent conversational history
    if (!empty($chatHistory)) {
        $recentHistory = array_slice($chatHistory, -4);
        foreach ($recentHistory as $turn) {
            if (!empty($turn['question'])) {
                $messages[] = ["role" => "user", "content" => mb_substr($turn['question'], 0, 1000)];
            }
            if (!empty($turn['answer'])) {
                $messages[] = ["role" => "assistant", "content" => mb_substr($turn['answer'], 0, 2000)];
            }
        }
    }

    $messages[] = ["role" => "user", "content" => trim($question)];

    // 4. Call LLM
    try {
        $answer = call_llm($messages, 0.1, 3500);
        return [
            "answer" => $answer ?: "No response received from LLM.",
            "sources" => $sources,
            "images" => []
        ];
    } catch (Exception $e) {
        error_log("RAG generation failed: " . $e->getMessage());
        return [
            "answer" => "Unable to process meteorological question. Please verify LLM API credentials in config.php.",
            "sources" => ["System Error"],
            "images" => []
        ];
    }
}
