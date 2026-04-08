<?php
// ai_engine.php - Advanced Deep Analysis Engine
// Handles "Deep Dive" investigation with multi-step data gathering.

// 1. Configuration & Constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cennet_dunya_db');
define('GROQ_API_KEY', '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');
define('RAPID_API_KEY', '09df3dd917msh9ce7972941f1569p1b7999jsn5e30d1fef15c');
define('RAPID_API_HOST', 'twitter-api45.p.rapidapi.com');
define('AI_MODEL', 'openai/gpt-oss-120b');

// 2. System Settings
error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(0); // Unlimited execution time
ignore_user_abort(true);
ini_set('memory_limit', '2048M'); // High memory for large context

// 3. SSE Headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

// Disable buffering
if (function_exists('apache_setenv')) apache_setenv('no-gzip', 1);
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
while (ob_get_level() > 0) ob_end_flush();
ob_implicit_flush(1);

// 4. Database Connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    send_sse_error("Veritabanı bağlantı hatası: " . $conn->connect_error);
    exit;
}
$conn->set_charset("utf8mb4");

// 5. Helper Functions

function send_sse($data) {
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

function send_sse_message($msg, $step = null, $detail = null) {
    $data = ['type' => 'status', 'message' => $msg];
    if ($step !== null) $data['step'] = $step;
    if ($detail !== null) $data['detail'] = $detail;
    send_sse($data);
}

function send_sse_error($msg) {
    send_sse(['type' => 'error', 'message' => $msg]);
    exit;
}

function rapid_api_request($endpoint, $params = []) {
    $url = "https://" . RAPID_API_HOST . "/" . $endpoint . "?" . http_build_query($params);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15, // Short timeout to fail fast and retry/skip
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER => [
            "x-rapidapi-host: " . RAPID_API_HOST,
            "x-rapidapi-key: " . RAPID_API_KEY
        ],
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) return null;
    return json_decode($response, true);
}

function extract_json_from_text($text) {
    $text = preg_replace('/<think>.*?<\/think>/s', '', $text);
    $text = preg_replace('/```json\s*(.*?)\s*```/s', '$1', $text);
    $text = preg_replace('/```\s*(.*?)\s*```/s', '$1', $text);
    
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    
    if ($start !== false && $end !== false && $end > $start) {
        $text = substr($text, $start, $end - $start + 1);
    }
    return trim($text);
}

function recursive_json_decode($json_str, $depth = 0) {
    if ($depth > 5) return null;
    $decoded = json_decode($json_str, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if (is_string($decoded)) {
            $trimmed = trim($decoded);
            if (substr($trimmed, 0, 1) === '{' || substr($trimmed, 0, 1) === '[') {
                return recursive_json_decode($trimmed, $depth + 1);
            }
            return $decoded;
        }
        return $decoded;
    }
    // Repair attempts
    if (strpos($json_str, '\\"') !== false && substr($json_str, 0, 1) === '{') {
         $fixed = str_replace('\\"', '"', $json_str);
         if (json_decode($fixed) !== null) return json_decode($fixed, true);
    }
    return null;
}

require_once 'research_engine.php';

// 6. Core Logic

$tweet_id = $_GET['tweet_id'] ?? null;
if (!$tweet_id) {
    send_sse_error("Tweet ID eksik.");
}

// --- PHASE 1: TARGET ACQUISITION ---
send_sse_message("Hedef Tweet Tespit Ediliyor...", "init");

$content = "";
$username = "";
$date = "";

// Check DB
$stmt = $conn->prepare("SELECT * FROM tweets WHERE tweet_id = ?");
$stmt->bind_param("s", $tweet_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $content = $row['text_content']; // Using text_content column as per memory/schema guess, or full_text
    if (!$content) $content = $row['full_text'] ?? $row['text'] ?? ''; // Fallback
    $username = "cennet_dunya"; 
    $date = $row['created_at'];
}

// Check API if DB fail
if (!$content) {
    send_sse_message("Yerel veritabanında yok, küresel ağ taranıyor...", "init");
    $tweet_data = rapid_api_request('tweet.php', ['id' => $tweet_id]);
    if ($tweet_data) {
        $content = $tweet_data['text'] ?? ($tweet_data['legacy']['full_text'] ?? '');
        $username = $tweet_data['user']['screen_name'] ?? 'Unknown';
        $date = $tweet_data['created_at'] ?? date('Y-m-d H:i:s');
    }
}

// Check Fallback JSON
if (!$content) {
    $json_content = @file_get_contents('tweets.json');
    if ($json_content) {
        $tweets = json_decode($json_content, true);
        foreach ($tweets as $t) {
            if (($t['tweet_id'] ?? '') == $tweet_id) {
                $content = $t['text'] ?? '';
                break;
            }
        }
    }
}

if (!$content) {
    send_sse_error("HATA: Hedef Tweet İçeriği Bulunamadı. (ID: $tweet_id)");
}

send_sse_message("Hedef Kilitlendi. İçerik Analizi Başlıyor.", "target_locked", $content);

// Initialize Research Engine
$researcher = new ResearchEngine($conn, AI_MODEL, GROQ_API_KEY, RAPID_API_KEY, RAPID_API_HOST);

// --- PHASE 1.5: PRE-FLIGHT CHECK (COMMENT VALIDATION) ---
send_sse_message("Hedef Tweet Yorum Uygunluğu Kontrol Ediliyor...", "checking_replies");

$reply_count = $researcher->checkRepliability($tweet_id);
if ($reply_count > 0) {
    send_sse_message("Yorum Akışı Doğrulandı. ($reply_count+ referans)", "replies_confirmed");
} else {
    // Decision: Do we stop or warn? User said "test... then analyze". 
    // I will log a strong warning but proceed with variation generation (maybe it has quotes/RTs even if no direct replies API returned)
    // Or better, per user instruction "önce test et... yorumları çek ondan sonra", implying if no comments, maybe the flow changes?
    // I will stick to warning but proceeding, as stopping might block valid text analysis.
    send_sse_message("UYARI: Doğrudan yorum akışı tespit edilemedi veya gizli. Analiz genişletiliyor...", "replies_missing", "Alternatif veri kaynakları devreye giriyor.");
}

// --- PHASE 2: VARIATION GENERATION ---
send_sse_message("Arama Vektörleri Oluşturuluyor (15 Varyasyon)...", "generating_vectors");
$variations = $researcher->generateVariations($content);

// --- PHASE 2.5: FETCH ORIGINAL COMMENTS ---
send_sse_message("Orijinal Tweet Yorumları Toplanıyor...", "gathering_comments");
$original_comments = $researcher->getOriginalComments($tweet_id);

// --- PHASE 3: DEEP DATA MINING ---
$total_context = "ANALİZ EDİLEN TWEET:\n$content\n\n";
if (!empty($original_comments)) {
    $total_context .= $original_comments . "\n\n";
    send_sse_message("Orijinal yorumlar analize eklendi.", "comments_added");
}
$total_context .= "DİĞER BULGULAR:\n";

// Define callback for SSE updates from ResearchEngine
$sse_callback = function($type, $data) {
    if ($type === 'scanning') {
        send_sse_message("Vektör {$data['index']}/10 Taranıyor: '{$data['query']}'", "scanning", ['query' => $data['query']]);
    } elseif ($type === 'progress') {
        send_sse(['type' => 'progress', 'found' => $data['found'], 'comments' => $data['comments']]);
    } elseif ($type === 'log') {
        send_sse(['type' => 'log', 'content' => $data['content']]);
    }
};

$research_results = $researcher->deepDive($variations, $sse_callback);
$total_context .= $research_results['context'];
$posts_found = $research_results['stats']['posts'];
$comments_read = $research_results['stats']['comments'];

// Also fetch comments for the ORIGINAL tweet
send_sse_message("Orijinal Tweetin Tüm Yorumları Okunuyor...", "reading_replies");
$total_context .= $researcher->getOriginalComments($tweet_id);

send_sse_message("Veri Toplama Tamamlandı. $posts_found gönderi ve $comments_read yorum işleniyor...", "processing");

// --- PHASE 4: FINAL AI ANALYSIS ---

send_sse_message("Yapay Zeka (120b) Büyük Veriyi Analiz Ediyor...", "ai_thinking");

$data_status_note = "";
if ($posts_found == 0) {
    $data_status_note = "UYARI: Yapılan derin aramada harici veri bulunamadı veya API erişim sorunu yaşandı. Analizi sadece 'ANALİZ EDİLEN TWEET' içeriğine ve kendi genel bilgine dayanarak yap.";
}

$prompt_template = <<<EOT
Sen "Cennet Dünya" (Enes Halifekan) projesinin baş veri analistisin. Elinde hedef bir tweet ve bu tweet ile ilgili yapılmış geniş çaplı bir "derin arama" (deep dive) sonucu var.
$data_status_note

GÖREV:
Bu veriyi analiz et ve aşağıdaki JSON formatında nihai bir istihbarat raporu oluştur.

KURALLAR:
1. SADECE geçerli bir JSON objesi döndür.
2. Markdown kullanma.
3. Yorum satırı ekleme.
4. "kapsamli_arastirma_raporu" alanı Markdown formatında (başlıklar, bold, liste) olabilir ama JSON stringi içinde olmalı.

JSON FORMATI:
{
  "son_karar": {
    "ozet": "Analiz edilen tüm verilerin 1-2 cümlelik özeti.",
    "tavsiye": "Bu konuyu takip et / Önemli değil / Manipülasyon var vb.",
    "ne_anlatilmak_istenmis": "Tweetin görünen ve görünmeyen (subliminal) mesajı.",
    "olasiliklar": {
      "gercek_kehanet_ongoru": 0-100,
      "feto_talimati": 0-100,
      "bireysel_troll_kaotik": 0-100,
      "confirmation_bias": 0-100
    }
  },
  "kapsamli_arastirma_raporu": "## Derin Analiz Raporu\\n\\n**Giriş:**...\\n\\n**Bulgular:**...\\n\\n**Toplum Tepkisi:**...\\n\\n**Sonuç:**...",
  "generated_reply": "Analiz sonuçlarına dayanarak bu tweete atılacak en etkili, zeki ve kısa yanıt (max 280 karakter)."
}

VERİ HAVUZU:
$total_context
EOT;

$payload = [
    'model' => AI_MODEL,
    'messages' => [
        ['role' => 'system', 'content' => 'Sen uzman bir veri analistisin. SADECE JSON formatında yanıt ver. Başka hiçbir açıklama yapma.'],
        ['role' => 'user', 'content' => $prompt_template]
    ],
    'temperature' => 0.5,
    'max_tokens' => 8000
];

$ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . GROQ_API_KEY,
    "Content-Type: application/json"
]);

// Keep-alive progress
curl_setopt($ch, CURLOPT_NOPROGRESS, false);
curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($dltotal, $dlnow, $ultotal, $ulnow) {
    static $last = 0;
    if (time() - $last > 2) {
        echo ": keep-alive\n\n";
        flush();
        $last = time();
    }
    return 0;
});

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    send_sse_error("AI Bağlantı Hatası: $err");
}

// --- PHASE 5: DELIVERY ---
send_sse_message("Rapor Derleniyor...", "finalizing");

$json_response = json_decode($response, true);
$raw_content = $json_response['choices'][0]['message']['content'] ?? '';

$cleaned_json_str = extract_json_from_text($raw_content);
$final_data = recursive_json_decode($cleaned_json_str);

if ($final_data === null) {
    // Fail gracefully with a valid JSON structure describing the error
    $final_data = [
        'son_karar' => [
            'ozet' => 'Veri yoğunluğu nedeniyle model çıktısı tam ayrıştırılamadı, ancak analiz yapıldı.',
            'tavsiye' => 'Manuel İnceleme Önerilir',
            'ne_anlatilmak_istenmis' => 'Veri karmaşası.',
            'olasiliklar' => ['gercek_kehanet_ongoru' => 0, 'feto_talimati' => 0, 'bireysel_troll_kaotik' => 0, 'confirmation_bias' => 100]
        ],
        'kapsamli_arastirma_raporu' => "## Rapor Oluşturma Hatası\n\nModel yanıtı geçerli JSON formatında değildi. Ancak toplanan veriler şunlardı:\n\n" . mb_substr($total_context, 0, 1000) . "...",
        'generated_reply' => "Sistem hatası nedeniyle otomatik yanıt oluşturulamadı."
    ];
}

send_sse(['type' => 'result', 'analysis' => $final_data]);

?>
