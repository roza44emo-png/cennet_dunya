<?php
// Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cennet_dunya_db');
define('GROQ_API_KEY', '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');
define('RAPID_API_KEY', '09df3dd917msh9ce7972941f1569p1b7999jsn5e30d1fef15c');
define('RAPID_API_HOST', 'twitter-api45.p.rapidapi.com');
define('AI_MODEL', 'openai/gpt-oss-120b');

// System Settings
error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(0);
ignore_user_abort(true);
ini_set('memory_limit', '1024M');

// Shutdown Handler
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (isset($_GET['stream']) && $_GET['stream'] == '1') {
            echo "data: " . json_encode(['type' => 'error', 'message' => 'Sistem Hatası: ' . $error['message']]) . "\n\n";
            flush();
        } else {
            echo json_encode(['error' => 'Critical Error: ' . $error['message']]);
        }
    }
});

// SSE Headers if streaming
$is_stream = isset($_GET['stream']) && $_GET['stream'] == '1';
if ($is_stream) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    if (function_exists('apache_setenv')) apache_setenv('no-gzip', 1);
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    while (ob_get_level() > 0) ob_end_flush();
    ob_implicit_flush(1);
} else {
    header('Content-Type: application/json; charset=utf-8');
}
header('Access-Control-Allow-Origin: *');

// Database Connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die(json_encode(['error' => 'DB Connection failed']));
}
$conn->set_charset("utf8mb4");

// Helper Functions
function send_sse($message, $type = 'log') {
    global $is_stream;
    if ($is_stream) {
        echo "data: " . json_encode(['type' => $type, 'message' => $message]) . "\n\n";
        flush();
    }
}

function normalizeTurkish($text) {
    $search = ['Ğ','Ü','Ş','I','İ','Ö','Ç','ğ','ü','ş','ı','ö','ç','â','î','û','Â','Î','Û'];
    $replace = ['g','u','s','i','i','o','c','g','u','s','i','o','c','a','i','u','a','i','u'];
    return str_replace($search, $replace, $text);
}

function extract_json_from_text($text) {
    // 0. Remove <think> tags (DeepSeek/Reasoning models)
    $text = preg_replace('/<think>.*?<\/think>/s', '', $text);

    // 1. Remove Markdown code blocks
    if (preg_match('/```json\s*(.*?)\s*```/s', $text, $matches)) {
        $text = $matches[1];
    } elseif (preg_match('/```\s*(.*?)\s*```/s', $text, $matches)) {
        $text = $matches[1];
    }

    // 2. Find first '{' and last '}'
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    
    if ($start !== false && $end !== false && $end > $start) {
        $text = substr($text, $start, $end - $start + 1);
    } else {
        // If no closing bracket found, maybe it was truncated?
        if ($start !== false) {
             $text = substr($text, $start);
        }
    }

    // 3. Remove comments (single line // and multi line /* */)
    $text = preg_replace('!/\*.*?\*/!s', '', $text);
    $text = preg_replace('/\n\s*\/\/.*$/m', '', $text);
    
    // 4. Remove trailing commas (simple regex approach)
    $text = preg_replace('/,\s*}/', '}', $text);
    $text = preg_replace('/,\s*]/', ']', $text);
    
    // 5. Clean control characters (Keep UTF-8 bytes!)
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);
    
    // 6. Handle Newlines inside strings (JSON strictness)
    $text = trim($text);
    
    // 7. Check if it is a JSON string (double encoded)
    // Sometimes models return "{\"key\": ...}"
    if (substr($text, 0, 1) === '"' && substr($text, -1) === '"') {
        $decoded_str = json_decode($text);
        if (is_string($decoded_str)) {
            // It was a valid JSON string, use the inner content
            $text = $decoded_str;
            $text = trim($text);
        }
    }
    
    // 8. Special cleanup for escaped quotes if they are not part of valid JSON structure
    // If we have {\"key\": ... } which is invalid, we might want to fix it.
    // But be careful not to break valid escaped quotes inside strings.
    // simpler approach: let json_decode fail and use repair_json?
    
    return $text;
}

function repair_json($json) {
    // Try to decode first
    if (json_decode($json) !== null) return $json;
    
    // Attempt 0: Handle double escaping if present (e.g. {\"key\":...})
    // If the string contains \" but is not wrapped in quotes, it might be bad escaping
    if (strpos($json, '\"') !== false && substr($json, 0, 1) === '{') {
         $fixed = str_replace('\"', '"', $json);
         if (json_decode($fixed) !== null) return $fixed;
    }

    // Attempt 1: Balance Brackets (if truncated)
    $open_braces = substr_count($json, '{');
    $close_braces = substr_count($json, '}');
    $open_brackets = substr_count($json, '[');
    $close_brackets = substr_count($json, ']');
    
    if ($open_braces > $close_braces) {
        $json .= str_repeat('}', $open_braces - $close_braces);
    }
    if ($open_brackets > $close_brackets) {
        $json .= str_repeat(']', $open_brackets - $close_brackets);
    }
    
    return $json;
}

// RapidAPI Helpers
function rapid_api_request($endpoint, $params = []) {
    $url = "https://" . RAPID_API_HOST . "/" . $endpoint . "?" . http_build_query($params);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            "x-rapidapi-host: " . RAPID_API_HOST,
            "x-rapidapi-key: " . RAPID_API_KEY
        ],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function fetch_tweet_details($id) {
    $data = rapid_api_request('tweet.php', ['id' => $id]);
    if (isset($data['tweet_id']) || isset($data['id_str'])) {
        return [
            'text' => $data['text'] ?? ($data['legacy']['full_text'] ?? ''),
            'date' => $data['created_at'] ?? '',
            'likes' => $data['favorite_count'] ?? 0,
            'views' => $data['views'] ?? ($data['view_count'] ?? 0)
        ];
    }
    return null;
}

function fetch_replies($id, $limit = 30) {
    // Try latest first
    $data = rapid_api_request('latest_replies.php', ['id' => $id]);
    $replies = [];
    $source = $data['replies'] ?? ($data['timeline'] ?? []);
    
    if (empty($source)) {
        // Fallback to normal replies
        $data = rapid_api_request('replies.php', ['id' => $id]);
        $source = $data['replies'] ?? ($data['timeline'] ?? []);
    }
    
    if (is_array($source)) {
        foreach ($source as $item) {
            if (count($replies) >= $limit) break;
            $text = $item['text'] ?? ($item['content'] ?? ($item['legacy']['full_text'] ?? ''));
            $user = $item['user_info']['screen_name'] ?? ($item['screen_name'] ?? ($item['core']['user_results']['result']['legacy']['screen_name'] ?? 'Unknown'));
            $date = $item['created_at'] ?? '';
            
            if ($text) {
                $replies[] = [
                    'text' => mb_substr(str_replace("\n", " ", $text), 0, 200),
                    'user' => $user,
                    'date' => $date
                ];
            }
        }
    }
    return $replies;
}

function fetch_retweets($id, $limit = 20) {
    $data = rapid_api_request('retweets.php', ['id' => $id]);
    $retweets = [];
    $source = $data['retweets'] ?? [];
    if (is_array($source)) {
        foreach ($source as $item) {
            if (count($retweets) >= $limit) break;
            $user = $item['user_info']['screen_name'] ?? ($item['screen_name'] ?? 'Unknown');
            $date = $item['created_at'] ?? '';
            $retweets[] = ['user' => $user, 'date' => $date];
        }
    }
    return $retweets;
}

function fetch_search($query, $limit = 10) {
    $data = rapid_api_request('search.php', ['query' => $query, 'search_type' => 'Top']);
    $tweets = [];
    $source = $data['timeline'] ?? [];
    if (is_array($source)) {
        foreach ($source as $item) {
            if (count($tweets) >= $limit) break;
            $text = $item['text'] ?? ($item['content'] ?? ($item['legacy']['full_text'] ?? ''));
            $user = $item['user_info']['screen_name'] ?? ($item['screen_name'] ?? ($item['core']['user_results']['result']['legacy']['screen_name'] ?? 'Unknown'));
            $id = $item['tweet_id'] ?? ($item['id_str'] ?? '');
            $date = $item['created_at'] ?? '';
            
            if ($text && $id) {
                $tweets[] = [
                    'text' => mb_substr(str_replace("\n", " ", $text), 0, 200),
                    'user' => $user,
                    'date' => $date,
                    'url' => "https://twitter.com/$user/status/$id"
                ];
            }
        }
    }
    return $tweets;
}

function get_keywords($text) {
    $keywords = [];
    $text_lower = mb_strtolower($text);
    // Basic extraction logic similar to previous
    preg_match_all('/\p{L}+/u', $text_lower, $words);
    if (isset($words[0])) {
        foreach ($words[0] as $w) {
            if (mb_strlen($w) > 5 && !in_array($w, ["google", "twitter", "https", "com"])) {
                $keywords[] = $w;
            }
        }
    }
    return array_slice(array_unique($keywords), 0, 5);
}

// Router
$action = $_GET['action'] ?? ($_POST['action'] ?? 'fetch_tweets');

switch ($action) {
    case 'post_comment':
        $tweet_id = $_POST['tweet_id'] ?? '';
        $username = $_POST['username'] ?? 'Anonim';
        $comment_text = $_POST['comment_text'] ?? '';
        
        if (!$tweet_id || !$comment_text) die(json_encode(['error' => 'Missing fields']));
        
        $stmt = $conn->prepare("INSERT INTO comments (tweet_id, username, comment_text) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $tweet_id, $username, $comment_text);
        if ($stmt->execute()) {
            $conn->query("UPDATE tweets SET reply_count = reply_count + 1 WHERE tweet_id = '$tweet_id'");
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $conn->error]);
        }
        break;

    case 'get_comments':
        $tweet_id = $_GET['tweet_id'] ?? '';
        if (!$tweet_id) die(json_encode(['error' => 'Missing tweet_id']));
        
        $stmt = $conn->prepare("SELECT * FROM comments WHERE tweet_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("s", $tweet_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $comments = [];
        while ($row = $result->fetch_assoc()) $comments[] = $row;
        echo json_encode(['comments' => $comments]);
        break;

    case 'get_media':
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $stmt = $conn->prepare("SELECT * FROM tweets WHERE has_media = 1 ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $media = [];
        while ($row = $result->fetch_assoc()) {
            $media[] = [
                'id' => $row['tweet_id'],
                'image' => $row['media_url'] ?: "https://picsum.photos/600/300?random=" . $row['id'],
                'text' => $row['full_text'],
                'stats' => ['likes' => (int)$row['favorite_count'], 'retweets' => (int)$row['retweet_count']]
            ];
        }
        echo json_encode(['media' => $media]);
        break;

    case 'get_tweet':
        $tweet_id = $_GET['tweet_id'] ?? '';
        if (!$tweet_id) die(json_encode(['error' => 'Missing tweet_id']));
        
        $stmt = $conn->prepare("SELECT * FROM tweets WHERE tweet_id = ?");
        $stmt->bind_param("s", $tweet_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['tweet' => [
                'id' => $row['tweet_id'],
                'text' => $row['full_text'],
                'time' => $row['created_at'],
                'stats' => [
                    'comments' => (int)$row['reply_count'],
                    'retweets' => (int)$row['retweet_count'],
                    'likes' => (int)$row['favorite_count'],
                    'views' => (int)$row['view_count']
                ],
                'name' => $row['owner_name'] ?: 'Enes Halifekan',
                'handle' => $row['owner_handle'] ?: 'cennet_dunya',
                'avatar' => 'profile.png',
                'image' => ($row['has_media'] == 1) ? ($row['media_url'] ?: "https://picsum.photos/600/300?random=" . $row['id']) : null
            ]]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Tweet not found']);
        }
        break;

    case 'analyze_tweet':
        $tweet_id = $_GET['tweet_id'] ?? '';
        if (!$tweet_id) die(json_encode(['error' => 'Missing tweet_id']));
        
        send_sse("Analiz başlatılıyor...", "info");
        
        // Fetch from DB
        $stmt = $conn->prepare("SELECT full_text, created_at FROM tweets WHERE tweet_id = ?");
        $stmt->bind_param("s", $tweet_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        
        if (!$row) {
            // Try JSON fallback
            $json_file = 'tweets.json';
            if (file_exists($json_file)) {
                $tweets_json = json_decode(file_get_contents($json_file), true);
                if (isset($tweets_json['links'])) {
                    foreach ($tweets_json['links'] as $item) {
                        if (isset($item['otherPropertiesMap']['status_id']) && $item['otherPropertiesMap']['status_id'] == $tweet_id) {
                            $row = [
                                'full_text' => $item['otherPropertiesMap']['tweet_text'],
                                'created_at' => $item['otherPropertiesMap']['created_at']
                            ];
                            break;
                        }
                    }
                }
            }
        }
        
        if (!$row) die(json_encode(['error' => 'Tweet not found locally']));
        
        $tweet_text = $row['full_text'];
        $tweet_date = $row['created_at'];
        
        // Build Context
        $context = "TWEET: $tweet_text\nTarih: $tweet_date\n\n";
        
        send_sse("Twitter verileri toplanıyor...", "info");
        $replies = fetch_replies($tweet_id, 50);
        if ($replies) {
            $context .= "YORUMLAR:\n";
            foreach ($replies as $r) {
                $context .= "- @{$r['user']}: {$r['text']}\n";
                send_sse("Yorum: @{$r['user']}", "data");
            }
        }
        
        $keywords = get_keywords($tweet_text);
        if ($keywords) {
            $query = implode(" ", array_slice($keywords, 0, 3));
            send_sse("Benzer tweetler aranıyor: $query", "info");
            $related = fetch_search($query, 10);
            if ($related) {
                $context .= "\nBENZER KONULAR:\n";
                foreach ($related as $t) {
                    $context .= "- @{$t['user']}: {$t['text']}\n";
                    // Stream topic titles/users to the animation
                    $snippet = mb_substr($t['text'], 0, 40) . "...";
                    send_sse("Bulundu: @{$t['user']} - $snippet", "data");
                }
            }
        }
        
        // AI Request
        $prompt_template = file_exists('prompt.txt') ? file_get_contents('prompt.txt') : "";
        
        // Fallback prompt if file missing
        if (!$prompt_template) {
             $prompt_template = <<<EOT
Sen @cennet_dunya (Enes Halifekan) uzmanı bir analistsin.
GÖREV: Tweeti analiz et ve JSON formatında raporla.

ÇIKTI FORMATI (JSON):
{
  "kapsamli_arastirma_raporu": "Markdown formatında detaylı rapor...",
  "son_karar": {
    "ozet": "Kısa özet",
    "tavsiye": "Yatırım/Takip Tavsiyesi",
    "ne_anlatilmak_istenmis": "Tweetin alt metni...",
    "olasiliklar": {
      "gercek_kehanet_ongoru": 0-100,
      "feto_talimati": 0-100,
      "bireysel_troll_kaotik": 0-100,
      "confirmation_bias": 0-100
    }
  }
}

Tweet: "{TWEET_METNI}"
Tarih: {TWEET_TARIHI}
Context: {GDELT_SONUC_METNI}
EOT;
        }

        $prompt = str_replace(
            ['{TWEET_METNI}', '{TWEET_TARIHI}', '{GDELT_SONUC_METNI}', '{GECMIS_TWEETLER}'],
            [$tweet_text, $tweet_date, $context, ''],
            $prompt_template
        );
        
        send_sse("Yapay Zeka Analizi (Model: ".AI_MODEL.")...", "info");
        
        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        $data = [
            'model' => AI_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => 'Sen uzman bir veri analistisin. SADECE JSON formatında yanıt ver. Markdown kullanma. Yorum satırı ekleme. Sadece saf JSON.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.5,
            'max_tokens' => 8000
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . GROQ_API_KEY, 'Content-Type: application/json'],
            CURLOPT_TIMEOUT => 300
        ]);
        
        if ($is_stream) {
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($resource, $download_size, $downloaded, $upload_size, $uploaded) {
                static $last_time = 0;
                if (time() - $last_time >= 2) {
                    echo ": keep-alive\n\n";
                    flush();
                    $last_time = time();
                }
                return 0;
            });
        }
        
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($err) {
            $msg = "AI Bağlantı Hatası: $err";
            send_sse($msg, "error");
            if (!$is_stream) echo json_encode(['error' => $msg]);
        } else {
            $resData = json_decode($response, true);
            $content = $resData['choices'][0]['message']['content'] ?? null;
            
            if ($content) {
                $json = extract_json_from_text($content);
                
                // Validate & Repair
                $decoded = json_decode($json, true);
                
                // Handle recursive JSON strings (Double/Triple encoded)
                // Sometimes the model returns a string that contains JSON, which contains a string...
                $depth = 0;
                while (is_string($decoded) && $depth < 3) {
                    $try_decode = json_decode($decoded, true);
                    if ($try_decode !== null) {
                        $decoded = $try_decode;
                    } else {
                        // If it is a string but not valid JSON, we might have an issue.
                        // But let's check if we can repair it.
                        $repaired = repair_json($decoded);
                        $try_repaired = json_decode($repaired, true);
                        if ($try_repaired !== null) {
                            $decoded = $try_repaired;
                        } else {
                            break;
                        }
                    }
                    $depth++;
                }

                if (!is_array($decoded)) {
                    $json = repair_json($json);
                    $decoded = json_decode($json, true);
                }
                
                // Final Result Variable
                $final_output = $decoded;
                
                // Final Check
                if ($final_output === null || !isset($final_output['son_karar']['ozet'])) {
                    $error_msg = json_last_error_msg();
                    if ($final_output && !isset($final_output['son_karar']['ozet'])) $error_msg = "Eksik JSON Şeması (son_karar.ozet)";
                    
                    // Fallback JSON matching index.html schema
                    $final_output = [
                        'son_karar' => [
                            'ozet' => 'Analiz verisi teknik bir hata nedeniyle tam işlenemedi veya model beklenen formatta yanıt vermedi.',
                            'tavsiye' => 'Veri Hatası',
                            'ne_anlatilmak_istenmis' => 'Veri alınamadı.',
                            'olasiliklar' => [
                                'gercek_kehanet_ongoru' => 0,
                                'feto_talimati' => 0,
                                'bireysel_troll_kaotik' => 0,
                                'confirmation_bias' => 100
                            ]
                        ],
                        'kapsamli_arastirma_raporu' => "## Hata Raporu\n\n**Hata Detayı:** $error_msg\n\n**Ham Veri:**\n```\n" . mb_substr($content, 0, 500) . "...\n```"
                    ];
                }
                
                if ($is_stream) {
                    echo "data: " . json_encode(['type' => 'result', 'analysis' => $final_output]) . "\n\n";
                } else {
                    echo json_encode(['analysis' => $final_output]);
                }
            } else {
                $msg = "AI Yanıtı Boş: " . json_encode($resData);
                send_sse($msg, "error");
                if (!$is_stream) echo json_encode(['error' => $msg]);
            }
        }
        break;

    case 'fetch_tweets':
    default:
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';
        
        $where = "";
        $params = [];
        $types = "";
        
        if ($search) {
            $norm = mb_strtolower(normalizeTurkish($search));
            $term = "%" . addcslashes($norm, "%_") . "%";
            
            // Build normalization chain for SQL
            $col = "full_text";
            $map = ['Ğ'=>'g', 'Ü'=>'u', 'Ş'=>'s', 'I'=>'i', 'İ'=>'i', 'Ö'=>'o', 'Ç'=>'c', 'ğ'=>'g', 'ü'=>'u', 'ş'=>'s', 'ı'=>'i', 'ö'=>'o', 'ç'=>'c', 'â'=>'a', 'î'=>'i', 'û'=>'u'];
            foreach ($map as $k => $v) $col = "REPLACE($col, '$k', '$v')";
            $col = "LOWER($col)";
            
            $where = "WHERE $col LIKE ?";
            $params[] = $term;
            $types .= "s";
        }
        
        // Count
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tweets $where");
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        
        // Fetch
        $stmt = $conn->prepare("SELECT * FROM tweets $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tweets = [];
        $view_ids = [];
        
        while ($row = $result->fetch_assoc()) {
            $avatar = ($row['owner_handle'] === 'cennet_dunya') ? "profile.png" : "https://api.dicebear.com/7.x/avataaars/svg?seed=" . $row['owner_handle'];
            $view_ids[] = $row['id'];
            
            $tweets[] = [
                'id' => (string)$row['tweet_id'],
                'tweet_id' => (string)$row['tweet_id'],
                'id_str' => (string)$row['tweet_id'],
                'name' => $row['owner_name'],
                'handle' => $row['owner_handle'],
                'created_at' => $row['created_at'],
                'time' => $row['created_at'],
                'text' => $row['full_text'],
                'image' => ($row['has_media'] == 1) ? ($row['media_url'] ?: "https://picsum.photos/600/300?random=" . $row['id']) : null,
                'avatar' => $avatar,
                'stats' => [
                    'comments' => (int)$row['reply_count'],
                    'retweets' => (int)$row['retweet_count'],
                    'likes' => (int)$row['favorite_count'],
                    'views' => (int)$row['view_count'] + 1
                ]
            ];
        }
        
        if ($view_ids) {
            $conn->query("UPDATE tweets SET view_count = view_count + 1 WHERE id IN (" . implode(',', $view_ids) . ")");
        }
        
        echo json_encode([
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'tweets' => $tweets
        ]);
        break;
}

$conn->close();
?>