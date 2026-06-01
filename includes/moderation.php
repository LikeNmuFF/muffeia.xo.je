<?php
// Simple moderation helper
// Usage: include 'includes/moderation.php';
// $res = moderate_text($text); if($res['flagged']) { /* block */ }

if (!function_exists('load_bad_words')) {
    function load_bad_words($path = __DIR__ . '/badwords.txt') {
        $words = [];
        if (!is_readable($path)) return $words;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            $words[] = $line;
        }
        return $words;
    }
}

if (!function_exists('normalize_text')) {
    function normalize_text($text) {
        // strip tags
        $text = strip_tags($text);
        // make lowercase
        $text = mb_strtolower($text, 'UTF-8');
        // transliterate accents if possible
        if (function_exists('iconv')) {
            $trans = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
            if ($trans !== false) $text = $trans;
        }
        // replace non alnum with spaces
        $text = preg_replace('/[^a-z0-9]+/iu', ' ', $text);
        return trim($text);
    }
}

if (!function_exists('moderate_text')) {
    function moderate_text($text) {
        $result = ['flagged' => false, 'matches' => []];

        if (trim($text) === '') return $result;

        $bad = load_bad_words();
        if (empty($bad)) {
            // No blacklist configured; optionally use external moderation if API_KEY defined
            if (defined('OPENAI_API_KEY') && OPENAI_API_KEY) {
                $apiRes = moderate_via_openai($text);
                if ($apiRes['flagged']) return $apiRes;
            }
            return $result;
        }

        $norm = normalize_text($text);
        $tokens = preg_split('/\s+/', $norm, -1, PREG_SPLIT_NO_EMPTY);

        // Use the shared badword map function to avoid duplication
        $bad_map = create_badword_map($bad);

        // Prepare token squishes for faster checks
        $token_squishes = [];
        foreach ($tokens as $tk) {
            $tk = trim($tk);
            if ($tk === '') { $token_squishes[] = ''; continue; }
            $token_squishes[] = preg_replace('/[^a-z0-9]+/i', '', $tk);
        }

        // Check exact token matches or token squish equality
        foreach ($tokens as $idx => $t) {
            $t = trim($t);
            if ($t === '') continue;
            $t_squish = $token_squishes[$idx];
            foreach ($bad_map as $bw_squish => $bw_display) {
                if ($t_squish !== '' && strcasecmp($t_squish, $bw_squish) === 0) {
                    $result['flagged'] = true;
                    $result['matches'][] = $bw_display;
                    break 2;
                }
            }
        }

        // Additionally detect obfuscated single-letter sequences like 'f u c k'
        // Build an array of only alnum token squishes that are single characters
        $single_letter_indices = [];
        foreach ($token_squishes as $i => $s) {
            if ($s !== '' && mb_strlen($s, 'UTF-8') === 1) $single_letter_indices[] = $i;
        }
        if (!empty($single_letter_indices)) {
            // For each badword squish, try sliding windows of length equal to the badword
            foreach ($bad_map as $bw_squish => $bw_display) {
                if ($bw_squish === '') continue;
                $bwlen = mb_strlen($bw_squish, 'UTF-8');
                if ($bwlen <= 1) continue;

                // sliding over token indices, but only where contiguous single-letter tokens exist
                for ($start = 0; $start <= count($tokens) - $bwlen; $start++) {
                    $concat = '';
                    $ok = true;
                    for ($k = 0; $k < $bwlen; $k++) {
                        $s = $token_squishes[$start + $k] ?? '';
                        if ($s === '' || mb_strlen($s, 'UTF-8') !== 1) { $ok = false; break; }
                        $concat .= $s;
                    }
                    if (!$ok) continue;
                    if (strcasecmp($concat, $bw_squish) === 0) {
                        $result['flagged'] = true;
                        $result['matches'][] = $bw_display;
                        break 2;
                    }
                }
            }
        }

        // Deduplicate matches
        $result['matches'] = array_values(array_unique($result['matches']));

        // If still not flagged and OpenAI key is present, optionally call API
        if (!$result['flagged'] && defined('OPENAI_API_KEY') && OPENAI_API_KEY) {
            $apiRes = moderate_via_openai($text);
            if ($apiRes['flagged']) return $apiRes;
        }

        return $result;
    }
}

if (!function_exists('create_badword_map')) {
    /**
     * Creates a map of squished bad words for faster matching
     * Squishing removes all non-alphanumeric characters
     * Example: "bad word" -> "badword"
     */
    function create_badword_map($bad_words) {
        $map = [];
        foreach ($bad_words as $bw) {
            $bw = trim($bw);
            if ($bw === '') continue;
            $bw_lower = mb_strtolower($bw, 'UTF-8');
            $bw_squish = preg_replace('/[^a-z0-9]+/i', '', $bw_lower);
            if ($bw_squish === '') continue;
            if (!isset($map[$bw_squish])) {
                $map[$bw_squish] = $bw_lower;
            }
        }
        return $map;
    }
}

if (!function_exists('moderate_via_openai')) {
    function moderate_via_openai($text) {
        $result = ['flagged' => false, 'matches' => [], 'source' => 'openai'];
        if (!defined('OPENAI_API_KEY') || !OPENAI_API_KEY) return $result;

        $payload = json_encode([
            'model' => 'omni-moderation-latest',
            'input' => $text
        ]);

        $ch = curl_init('https://api.openai.com/v1/moderations');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            error_log('Moderation API error: ' . $err);
            return $result;
        }

        $json = json_decode($resp, true);
        if (!$json || !isset($json['results'][0])) return $result;

        $r = $json['results'][0];
        // Newer moderation responses include a 'flagged' boolean
        if (isset($r['flagged']) && $r['flagged'] === true) {
            $result['flagged'] = true;
            // include categories if available
            if (isset($r['categories'])) {
                $matches = [];
                foreach ($r['categories'] as $cat => $val) {
                    if ($val) $matches[] = $cat;
                }
                $result['matches'] = $matches;
            }
        }

        return $result;
    }
}

if (!function_exists('mask_text')) {
    function mask_text($text) {
        $bad = load_bad_words();
        if (empty($bad)) return $text;
        
        $result = $text;
        $bad_map = create_badword_map($bad);
        
        if (empty($bad_map)) return $result;
        
        // Sort by length (longest first) to avoid partial matches
        uksort($bad_map, function ($a, $b) {
            return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
        });
        
        // For each bad word, try to find and mask it in the text
        foreach ($bad_map as $bw_squish => $bw_display) {
            // Only process bad words with 3+ characters (avoid 'll', 'ss', etc.)
            if ($bw_squish === '' || mb_strlen($bw_squish, 'UTF-8') < 3) continue;
            
            // Build character array from squished bad word
            $chars = preg_split('//u', $bw_squish, -1, PREG_SPLIT_NO_EMPTY);
            if (empty($chars)) continue;
            
            // Escape special regex chars in each character
            $pattern_parts = [];
            foreach ($chars as $c) {
                $pattern_parts[] = preg_quote($c, '/');
            }
            
            // Build pattern: letters with optional non-alphanumeric between them
            // This catches: fuck, f u c k, f_u_c_k, fucking, fuckyou, etc.
            $pattern = implode('[\\W]*', $pattern_parts);
            
            $result = preg_replace_callback(
                '/' . $pattern . '/iu',
                function ($m) use ($bw_squish) {
                    return mask_word($m[0], $bw_squish);
                },
                $result
            );
        }
        
        return $result;
    }
}

if (!function_exists('mask_word')) {
    function mask_word($word, $reference_squish = null) {
        // If we have a reference squish, use it for the length calculation
        if ($reference_squish) {
            $len = mb_strlen($reference_squish, 'UTF-8');
        } else {
            // Only count alphanumeric characters for length
            $alnum_only = preg_replace('/[^a-z0-9]/iu', '', $word);
            $len = mb_strlen($alnum_only, 'UTF-8');
        }
        
        if ($len <= 1) return '*';
        if ($len === 2) return '*' . '*';
        
        // Pattern: first char + asterisks for middle + last char
        // We need to preserve original characters for first and last
        $alnum_only = preg_replace('/[^a-z0-9]/iu', '', $word);
        if (mb_strlen($alnum_only, 'UTF-8') >= $len) {
            return mb_substr($alnum_only, 0, 1, 'UTF-8') . str_repeat('*', $len - 2) . mb_substr($alnum_only, -1, 1, 'UTF-8');
        }
        
        // Fallback: just asterisks
        return str_repeat('*', $len);
    }
}

?>
