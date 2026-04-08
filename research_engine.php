<?php
// research_engine.php - Dedicated Deep Data Mining Module

class ResearchEngine {
    private $conn;
    private $ai_model;
    private $groq_key;
    private $rapid_key;
    private $rapid_host;

    public function __construct($conn, $ai_model, $groq_key, $rapid_key, $rapid_host) {
        $this->conn = $conn;
        $this->ai_model = $ai_model;
        $this->groq_key = $groq_key;
        $this->rapid_key = $rapid_key;
        $this->rapid_host = $rapid_host;
    }

    public function rapid_api_request_public($endpoint, $params = []) {
        return $this->rapid_api_request($endpoint, $params);
    }

    private function rapid_api_request($endpoint, $params = []) {
        $url = "https://" . $this->rapid_host . "/" . $endpoint . "?" . http_build_query($params);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                "x-rapidapi-host: " . $this->rapid_host,
                "x-rapidapi-key: " . $this->rapid_key
            ],
            // SSL verification disable for local dev
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($err) return ['error' => $err];
        if ($http_code !== 200) return ['error' => "HTTP $http_code", 'body' => $response];
        
        return json_decode($response, true);
    }

    public function generateVariations($content) {
        $variation_prompt = "Aşağıdaki tweet için Twitter'da konuyla ilgili tartışmaları bulmak amacıyla 15 farklı, kısa ve etkili arama sorgusu (keyword) oluştur. Sadece sorguları virgülle ayırarak yaz. Başka hiçbir şey yazma.\n\nTweet: $content";
        
        $ch_var = curl_init("https://api.groq.com/openai/v1/chat/completions");
        $payload_var = [
            'model' => $this->ai_model,
            'messages' => [
                ['role' => 'system', 'content' => 'Sen bir arama motoru uzmanısın. Çıktı formatı: "sorgu1, sorgu2, sorgu3..."'],
                ['role' => 'user', 'content' => $variation_prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 200
        ];
        curl_setopt($ch_var, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_var, CURLOPT_POST, true);
        curl_setopt($ch_var, CURLOPT_POSTFIELDS, json_encode($payload_var));
        curl_setopt($ch_var, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $this->groq_key, "Content-Type: application/json"]);
        $response_var = curl_exec($ch_var);
        curl_close($ch_var);

        $variations = [];
        if ($response_var) {
            $json_var = json_decode($response_var, true);
            $var_text = $json_var['choices'][0]['message']['content'] ?? '';
            $variations = explode(',', $var_text);
            $variations = array_map('trim', $variations);
            $variations = array_slice($variations, 0, 15);
        }
        
        // Fallback
        if (empty($variations)) {
            $words = explode(' ', $content);
            $variations[] = implode(' ', array_slice($words, 0, 3));
            $variations[] = implode(' ', array_slice($words, 0, 5));
            foreach($words as $w) {
                if (mb_strlen($w) > 4) $variations[] = $w;
                if (count($variations) >= 15) break;
            }
        }
        return $variations;
    }

    public function checkRepliability($tweet_id) {
        // Strategy 1: Try replies.php first (sometimes works)
        $replies_data = $this->rapid_api_request('replies.php', ['id' => $tweet_id]);
        $replies = $replies_data['replies'] ?? ($replies_data['timeline'] ?? []);
        
        if (count($replies) > 0) return count($replies);
        
        // Strategy 2: Search by conversation_id
        // Assuming tweet_id is conversation_id for OP, or we search "to:username" logic if we had username
        $query = "conversation_id:$tweet_id filter:replies";
        $search_data = $this->rapid_api_request('search.php', ['query' => $query, 'limit' => 20]);
        $search_replies = $search_data['timeline'] ?? [];
        
        return count($search_replies);
    }

    public function deepDive($variations, $callback_sse = null) {
        $total_context = "";
        $posts_found = 0;
        $comments_read = 0;
        
        // STRICT RULE: 15 Variations, 5 Posts each, 15 Comments each
        foreach ($variations as $index => $query) {
            if (empty($query)) continue;
            
            // Notify Progress
            if ($callback_sse) {
                $callback_sse("scanning", ['query' => $query, 'index' => $index + 1]);
            }

            // 1. Find 5 Posts per variation
            $search_results = $this->rapid_api_request('search.php', ['query' => $query, 'limit' => 20]); // Request more to filter
            
            if (isset($search_results['error'])) {
                 if ($callback_sse) $callback_sse("log", ['content' => "API Hatası ({$query}): " . $search_results['error']]);
                 continue;
            }

            $found_tweets = $search_results['timeline'] ?? [];
            if (empty($found_tweets) && $callback_sse) {
                $callback_sse("log", ['content' => "Bu başlık için veri bulunamadı."]);
            }

            $found_tweets = array_slice($found_tweets, 0, 5);

            foreach ($found_tweets as $t) {
                $t_text = $t['text'] ?? ($t['content'] ?? '');
                $t_user = $t['user_info']['screen_name'] ?? 'unknown';
                $t_id = $t['tweet_id'] ?? ($t['id_str'] ?? '');
                
                if (!$t_text || !$t_id) continue;
                
                $posts_found++;
                $total_context .= "--- İlgili Gönderi ($t_id) ---\nUser: @$t_user\nText: $t_text\n";
                
                if ($callback_sse) {
                    $callback_sse("log", ['content' => "Gönderi Bulundu: @$t_user"]);
                }

                // 2. Find 15 Popular Comments per post
                // Try replies.php first
                $replies = [];
                $replies_data = $this->rapid_api_request('replies.php', ['id' => $t_id]);
                $replies = $replies_data['replies'] ?? ($replies_data['timeline'] ?? []);

                // If empty, try search conversation_id
                if (empty($replies)) {
                    $c_query = "conversation_id:$t_id filter:replies";
                    $c_search = $this->rapid_api_request('search.php', ['query' => $c_query, 'limit' => 20]);
                    $replies = $c_search['timeline'] ?? [];
                }
                
                // Sort by likes/popularity
                usort($replies, function($a, $b) {
                    return ($b['favorite_count'] ?? 0) - ($a['favorite_count'] ?? 0);
                });
                $replies = array_slice($replies, 0, 15);
                
                if (!empty($replies)) {
                    $total_context .= "Yorumlar:\n";
                    foreach ($replies as $r) {
                        $r_text = $r['text'] ?? '';
                        $r_user = $r['user']['screen_name'] ?? ($r['user_info']['screen_name'] ?? 'anon');
                        $r_likes = $r['favorite_count'] ?? 0;
                        if ($r_text) {
                            $total_context .= "  - @$r_user (Like: $r_likes): $r_text\n";
                            $comments_read++;
                            if ($callback_sse && $comments_read % 3 == 0) { // Log less frequently to save UI buffer
                                $callback_sse("log", ['content' => "Yorum Okundu (@$r_user): " . mb_substr($r_text, 0, 30) . "..."]);
                            }
                        }
                    }
                }
            }
            
            if ($callback_sse) {
                $callback_sse("progress", ['found' => $posts_found, 'comments' => $comments_read]);
            }
        }
        
        return [
            'context' => $total_context,
            'stats' => ['posts' => $posts_found, 'comments' => $comments_read]
        ];
    }
    
    public function getOriginalComments($tweet_id) {
        $context = "";
        
        // Strategy 1: replies.php
        $replies_data = $this->rapid_api_request('replies.php', ['id' => $tweet_id]);
        $replies = $replies_data['replies'] ?? ($replies_data['timeline'] ?? []);

        // Strategy 2: search conversation_id
        if (empty($replies)) {
            $query = "conversation_id:$tweet_id filter:replies";
            $search_data = $this->rapid_api_request('search.php', ['query' => $query, 'limit' => 20]);
            $replies = $search_data['timeline'] ?? [];
        }

        if (!empty($replies)) {
             $context .= "\n--- ORİJİNAL TWEET YORUMLARI ---\n";
             foreach ($replies as $r) {
                 $r_text = $r['text'] ?? '';
                 $r_user = $r['user']['screen_name'] ?? ($r['user_info']['screen_name'] ?? 'anon');
                 if ($r_text) {
                     $context .= "  - @$r_user: $r_text\n";
                 }
             }
        }
        return $context;
    }
}
?>