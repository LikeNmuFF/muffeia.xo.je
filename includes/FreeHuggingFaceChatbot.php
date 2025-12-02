<?php
// includes/FreeHuggingFaceChatbot.php
require_once 'env_loader.php';

class FreeHuggingFaceChatbot {
    private $api_token;
    private $api_url = 'https://api-inference.huggingface.co/models/';
    
    // Enhanced models for comforting conversation and problem-solving
    private $models = [
        'empathetic' => 'microsoft/DialoGPT-medium',    // Best for emotional support
        'creative' => 'microsoft/DialoGPT-large',       // Better for nuanced responses
        'quick' => 'microsoft/DialoGPT-small',          // Fast responses
        'fallback' => 'gpt2'                            // Basic backup
    ];
    
    // Conversation memory for context
    private $conversation_history = [];
    private $max_history_length = 6; // Keep last 3 exchanges
    
    public function __construct($model_type = 'empathetic') {
        $this->api_token = EnvLoader::get('HUGGINGFACE_TOKEN');
        $this->current_model = $this->models[$model_type] ?? $this->models['empathetic'];
        
        if (!$this->api_token || $this->api_token === 'your_hugging_face_token_here') {
            throw new Exception('Hugging Face token not configured in .env file');
        }
    }
    
    public function sendMessage($message, $context = '') {
        // Input validation and cleaning
        $message = trim($message);
        if (empty($message)) {
            return 'I\'m here to listen and support you. Please share what\'s on your mind. 💭';
        }
        
        // Add to conversation history
        $this->addToHistory('user', $message);
        
        // Analyze message sentiment and type
        $message_analysis = $this->analyzeMessage($message);
        
        // Try API first, then fallback to enhanced responses
        $response = $this->tryAllModels($message, $context, $message_analysis);
        
        // Add bot response to history
        $this->addToHistory('assistant', $response);
        
        return $response;
    }
    
    private function analyzeMessage($message) {
        $message_lower = strtolower($message);
        $analysis = [
            'sentiment' => 'neutral',
            'urgency' => 'low',
            'type' => 'general',
            'needs' => []
        ];
        
        // Sentiment analysis
        $positive_words = ['happy', 'good', 'great', 'better', 'thanks', 'thank', 'love', 'amazing', 'wonderful'];
        $negative_words = ['sad', 'angry', 'mad', 'hate', 'bad', 'terrible', 'awful', 'horrible', 'stressed', 'anxious', 'depressed'];
        $urgent_words = ['help', 'emergency', 'crisis', 'suicide', 'hurt', 'die', 'now', 'immediately'];
        
        $positive_count = 0;
        $negative_count = 0;
        $urgent_count = 0;
        
        foreach ($positive_words as $word) {
            if (strpos($message_lower, $word) !== false) $positive_count++;
        }
        foreach ($negative_words as $word) {
            if (strpos($message_lower, $word) !== false) $negative_count++;
        }
        foreach ($urgent_words as $word) {
            if (strpos($message_lower, $word) !== false) $urgent_count++;
        }
        
        if ($urgent_count > 0) {
            $analysis['sentiment'] = 'urgent';
            $analysis['urgency'] = 'high';
        } elseif ($negative_count > $positive_count) {
            $analysis['sentiment'] = 'negative';
            $analysis['urgency'] = $negative_count > 2 ? 'medium' : 'low';
        } elseif ($positive_count > $negative_count) {
            $analysis['sentiment'] = 'positive';
        }
        
        // Message type detection
        if (preg_match('/\b(hello|hi|hey|greetings|good morning|good afternoon)\b/', $message_lower)) {
            $analysis['type'] = 'greeting';
        } elseif (preg_match('/\b(problem|issue|rant|vent|struggle|frustrated|angry|upset)\b/', $message_lower)) {
            $analysis['type'] = 'problem';
            $analysis['needs'][] = 'comfort';
            $analysis['needs'][] = 'validation';
        } elseif (preg_match('/\b(sad|depressed|lonely|hurt|broken|heartbroken)\b/', $message_lower)) {
            $analysis['type'] = 'emotional';
            $analysis['needs'][] = 'empathy';
            $analysis['needs'][] = 'support';
        } elseif (preg_match('/\b(advice|help|suggest|what should|how can)\b/', $message_lower)) {
            $analysis['type'] = 'advice';
            $analysis['needs'][] = 'guidance';
        } elseif (preg_match('/\b(thank|thanks|appreciate|grateful)\b/', $message_lower)) {
            $analysis['type'] = 'gratitude';
        } elseif (preg_match('/\?$/', $message)) {
            $analysis['type'] = 'question';
        }
        
        return $analysis;
    }
    
    private function tryAllModels($message, $context, $analysis) {
        $models_to_try = ['empathetic', 'creative', 'quick', 'fallback'];
        
        foreach ($models_to_try as $model_type) {
            $model = $this->models[$model_type];
            $response = $this->callHuggingFaceAPI($message, $context, $model, $analysis);
            
            if ($response && !$this->isFallbackResponse($response)) {
                return $this->enhanceResponse($response, $analysis);
            }
            
            usleep(300000); // 0.3 seconds between tries
        }
        
        // If all models failed, use enhanced comforting responses
        return $this->getEnhancedResponse($message, $analysis);
    }
    
    private function callHuggingFaceAPI($message, $context, $model, $analysis) {
        $prompt = $this->preparePrompt($message, $context, $analysis);
        
        $data = [
            'inputs' => $prompt,
            'parameters' => [
                'max_length' => 200,
                'temperature' => $analysis['sentiment'] === 'urgent' ? 0.7 : 0.85,
                'do_sample' => true,
                'return_full_text' => false,
                'top_p' => 0.9,
                'repetition_penalty' => 1.15,
                'num_return_sequences' => 1
            ],
            'options' => [
                'wait_for_model' => true,
                'use_cache' => true
            ]
        ];
        
        $url = $this->api_url . $model;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->api_token,
                'Content-Type: application/json',
            ],
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $response_data = json_decode($response, true);
            return $this->parseAPIResponse($response_data, $prompt);
        }
        
        return null;
    }
    
    private function parseAPIResponse($response_data, $prompt) {
        if (isset($response_data[0]['generated_text'])) {
            $text = $response_data[0]['generated_text'];
            // Remove the prompt if it's included in response
            $text = str_replace($prompt, '', $text);
            $text = trim($text);
            
            if (!empty($text) && strlen($text) > 5) {
                return $text;
            }
        }
        
        return null;
    }
    
    private function isFallbackResponse($response) {
        return empty($response) || 
               strlen($response) < 10 ||
               strpos($response, 'Loading') !== false ||
               strpos($response, 'error') !== false ||
               strpos($response, 'model is currently loading') !== false;
    }
    
    private function preparePrompt($message, $context, $analysis) {
        // Enhanced prompt for MUFFEIA's comforting nature
        $system_prompt = "You are MUFFEIA Comfort Bot, a warm, empathetic AI assistant on the MUFFEIA anonymous support platform. ";
        $system_prompt .= "Your role: provide genuine comfort, emotional validation, and supportive listening for users sharing problems, rants, or feelings. ";
        $system_prompt .= "Respond with: kindness, validation, gentle encouragement, and virtual hugs when appropriate. ";
        $system_prompt .= "Important: Listen actively, acknowledge feelings, offer comfort without unsolicited advice. ";
        $system_prompt .= "Remind users they're not alone and their feelings are valid. ";
        $system_prompt .= "Keep responses conversational, warm, and uplifting. ";
        $system_prompt .= "Crisis response: If user mentions self-harm, gently suggest professional help. ";
        
        // Add context from analysis
        if ($analysis['sentiment'] === 'urgent') {
            $system_prompt .= "URGENT: User seems in distress. Prioritize safety and comfort. ";
        } elseif ($analysis['sentiment'] === 'negative') {
            $system_prompt .= "User is feeling down. Focus on validation and comfort. ";
        }
        
        if (!empty($context)) {
            $system_prompt .= "Context: $context. ";
        }
        
        // Add conversation history for context
        $history_context = $this->getHistoryContext();
        if (!empty($history_context)) {
            $system_prompt .= "Recent conversation: $history_context ";
        }
        
        return $system_prompt . "\n\nUser: " . $message . "\nMUFFEIA Comfort Bot:";
    }
    
    private function enhanceResponse($response, $analysis) {
        // Clean up response
        $response = trim($response);
        $response = preg_replace('/^[^a-zA-Z]*/', '', $response); // Remove leading non-letters
        
        // Add appropriate emojis based on sentiment
        $emojis = $this->getAppropriateEmojis($analysis);
        if (!empty($emojis)) {
            $response .= ' ' . $emojis;
        }
        
        // Ensure response ends with proper punctuation
        if (!preg_match('/[.!?]$/', $response)) {
            $response .= '.';
        }
        
        return $response;
    }
    
    private function getAppropriateEmojis($analysis) {
        $emojis = [
            'urgent' => '🚨',
            'negative' => '💔',
            'positive' => '💕',
            'problem' => '🤗',
            'emotional' => '😔',
            'advice' => '💡',
            'gratitude' => '😊',
            'greeting' => '👋'
        ];
        
        $selected_emojis = [];
        
        if (isset($emojis[$analysis['sentiment']])) {
            $selected_emojis[] = $emojis[$analysis['sentiment']];
        }
        
        if (isset($emojis[$analysis['type']])) {
            $selected_emojis[] = $emojis[$analysis['type']];
        }
        
        // Add comfort emoji for emotional/problem types
        if (in_array($analysis['type'], ['problem', 'emotional']) || $analysis['sentiment'] === 'negative') {
            $selected_emojis[] = '🤗';
        }
        
        return !empty($selected_emojis) ? implode('', array_unique($selected_emojis)) : '💭';
    }
    
    private function getEnhancedResponse($message, $analysis) {
        $message_lower = strtolower(trim($message));
        
        // Enhanced response library with better context handling
        $responses = [
            // Crisis and urgent responses
            'crisis' => [
                'pattern' => '/\b(suicide|kill myself|end it all|want to die|hurt myself|emergency)\b/',
                'responses' => [
                    "I'm really concerned about you and your safety matters most. 🚨 Please reach out to a trusted friend, family member, or call a crisis helpline immediately. You are not alone, and there are people who want to help you through this.",
                    "Your life is precious and you matter. 🚨 Please contact emergency services or a crisis hotline right now. On MUFFEIA, we care about you deeply, and professional help can provide the support you need.",
                    "I'm here with you, but this sounds serious. 🚨 Please call a crisis helpline or tell someone you trust immediately. Your wellbeing is the most important thing right now."
                ]
            ],
            
            // Deep emotional support
            'emotional' => [
                'pattern' => '/\b(sad|depressed|empty|numb|hopeless|worthless|alone|lonely|broken)\b/',
                'responses' => [
                    "I hear the pain in your words, and I want you to know your feelings are completely valid. 💔 It takes courage to share this heaviness. On MUFFEIA, you're not alone in these feelings. Would sharing more help lighten the load?",
                    "That sounds incredibly difficult to carry. 😔 Thank you for trusting me with these feelings. Many on MUFFEIA have felt this way too - you're in a community that understands. What comfort can I offer you right now?",
                    "Your feelings matter, even when they're heavy. 💕 I'm sitting with you in this moment. Sometimes just sharing the weight can make it more bearable. I'm here to listen without judgment."
                ]
            ],
            
            // Problem and rant support
            'problem' => [
                'pattern' => '/\b(problem|issue|rant|vent|frustrated|angry|upset|annoyed|pissed|stress)\b/',
                'responses' => [
                    "I hear your frustration and it's completely understandable. 🤗 Rant away - sometimes getting it all out is the first step toward feeling better. What's really bothering you the most?",
                    "That sounds really frustrating! 😤 Thank you for sharing what's weighing on you. On MUFFEIA, we believe in letting it all out. Getting it off your chest can be so relieving. Want to tell me more?",
                    "I'm listening to every word of your rant. 👂 It's healthy to express these feelings rather than keeping them bottled up. You're in a safe space to vent as much as you need to."
                ]
            ],
            
            // Anxiety and stress
            'anxiety' => [
                'pattern' => '/\b(anxious|worried|nervous|panic|overwhelmed|stressed|can\'t breathe)\b/',
                'responses' => [
                    "I can feel the anxiety in your words. 😥 Let's breathe through this together. Inhale slowly... exhale slowly... You're safe here on MUFFEIA. What's making you feel this way?",
                    "That overwhelming feeling is real and I'm here with you. 🌪️ Sometimes naming what we're anxious about can make it feel more manageable. Want to try putting words to it?",
                    "Your anxiety is valid and you're not alone in feeling this way. 💫 Many on MUFFEIA understand that tight-chest feeling. I'm here to sit with you through it."
                ]
            ],
            
            // Relationship issues
            'relationships' => [
                'pattern' => '/\b(breakup|ex|partner|boyfriend|girlfriend|husband|wife|friend|family)\b/',
                'responses' => [
                    "Relationship struggles can hurt so deeply. 💔 Whether it's with a partner, friend, or family - these feelings are valid. On MUFFEIA, many have navigated similar heartaches. What's weighing heaviest on your heart?",
                    "That sounds really painful to go through. 😔 Relationships can bring both joy and heartache. I'm here to listen and offer comfort as you process these feelings.",
                    "Your feelings about this relationship matter. 💕 Whether it's confusion, hurt, or anger - it's all valid. Sometimes just talking it through can bring clarity."
                ]
            ],
            
            // Muffeia name origin questions
            'name_origin' => [
                'pattern' => '/(why|how).*(name|called).*muffeia/i',
                'responses' => [
                    "Great question! \"Muffeia\" blends \"muff\" (a protected, muffled space) with \"feia\" (feelings + ideas). We wanted a name that captures a safe place where raw emotions can be shared and transformed into supportive ideas together.",
                    "We chose the name to reflect our mission: a digital space that muffles judgment while amplifying empathy. \"Muff\" represents the soundproof comfort zone we build, and \"feia\" symbolizes feelings turned into collective insight.",
                    "Muffeia grew out of a student project about making muffled, judgment-free spaces online. The name reminds us daily that we're here to protect emotions, nurture ideas, and turn rants into resolution."
                ]
            ],
            
            // Platform-specific encouragement
            'platform' => [
                'pattern' => '/\b(muffeia|platform|community|anonymous|post|feed|timeline|profile|message)\b/',
                'responses' => [
                    "I love that you're using MUFFEIA to share in a safe, anonymous space. 🌐 If it helps, you can always post a longer rant on the main feed or DM a supportive member. Want ideas for what to post next?",
                    "Our community is built exactly for moments like this. 🤝 Whether you drop an anonymous post, reply to someone else, or keep chatting with me, you're surrounded by people who get it.",
                    "Staying connected on MUFFEIA can be super grounding. 💫 Try bookmarking a few comforting posts, or hop into your messages after we chat—there's always someone awake here."
                ]
            ],
            
            // Motivation & productivity
            'motivation' => [
                'pattern' => '/\b(motivate|motivation|inspire|focus|lazy|productive|productivity|tired of trying|can\'t keep up)\b/',
                'responses' => [
                    "Losing momentum happens to all of us. ⚡ Maybe start with a tiny win—like sharing a progress update on MUFFEIA or writing a quick gratitude note. Want to brainstorm a 10-minute task together?",
                    "Your effort still counts, even on low-energy days. 🌱 Maybe post a mini goal on the platform so others can cheer you on? I'm happy to help you break things down right now.",
                    "Inspiration is easier to find when we don't hunt for perfection. 💡 Maybe scroll through the community's 'Wins' posts or DM someone who's chasing similar goals. Want me to help draft a check-in message?"
                ]
            ],
            
            // Self-care & burnout
            'selfcare' => [
                'pattern' => '/\b(burnout|burned out|exhausted|drained|self care|self-care|rest|overworked|tired all the time)\b/',
                'responses' => [
                    "Burnout is your body asking for gentleness. 🧸 Could you schedule a mini reset after we finish chatting? Even posting a self-care checklist to MUFFEIA can help you stay accountable.",
                    "You deserve slow moments too. 🌿 Maybe start a 'Rest Log' on your profile or DM a friend about planning a mini break. Want help wording that message?",
                    "Being 'on' all the time is unsustainable. 💆‍♀️ Let's plan one soft activity for you, and maybe share it with the community—they love swapping cozy ideas."
                ]
            ],
            
            // Greetings with emotional check-in
            'greeting' => [
                'pattern' => '/^(hello|hi|hey|greetings|good morning|good afternoon|good evening)/',
                'responses' => [
                    "Hello there! 👋 I'm MUFFEIA Comfort Bot, here to offer a listening ear and virtual support. How are you feeling today?",
                    "Hi! 💕 Welcome to your safe space. I'm here to listen, comfort, and support you. What's on your mind or heart today?",
                    "Hey there! 🤗 I'm so glad you reached out. On MUFFEIA, you can share anything that's weighing on you. How can I support you right now?"
                ]
            ],
            
            // Gratitude responses
            'gratitude' => [
                'pattern' => '/\b(thank|thanks|thank you|appreciate|grateful)\b/',
                'responses' => [
                    "You're so welcome! 😊 It's my honor to be here for you. Remember, MUFFEIA is always available when you need comfort or someone to listen.",
                    "Thank YOU for sharing with me! 💕 Your openness helps create this supportive space. I'm always here when you need to talk.",
                    "I'm really glad I could help! 🌟 You're doing great by reaching out for support. The MUFFEIA community is here for you whenever you need."
                ]
            ],
            
            // Advice seeking
            'advice' => [
                'pattern' => '/\b(what should|how can|what do|advice|suggestion|idea)\b/',
                'responses' => [
                    "That's a thoughtful question. 💭 While I can't give professional advice, I can offer supportive perspectives. On MUFFEIA, many have faced similar situations - sometimes hearing others' experiences helps. What are you considering?",
                    "I appreciate you asking for guidance. 🤗 While I'm here for comfort rather than advice, I can help you explore your options. What feels right to you in this situation?",
                    "That's a important thing to think through. 💡 Let's explore this together from a supportive angle. Sometimes talking it out helps clarity emerge naturally."
                ]
            ]
        ];
        
        // Check for matching response categories
        foreach ($responses as $category => $data) {
            if (preg_match($data['pattern'], $message_lower)) {
                $category_responses = $data['responses'];
                return $category_responses[array_rand($category_responses)];
            }
        }
        
        // Contextual follow-up for unknown messages
        return $this->getContextualFollowUp($message, $analysis);
    }
    
    private function getContextualFollowUp($message, $analysis) {
        $message_trimmed = substr(trim($message), 0, 100);
        
        $follow_ups = [
            "I hear you saying: \"{$message_trimmed}\"... 💭 Want to unpack that a bit more before you post or DM someone about it?",
            "Thank you for sharing that. 🤗 I'm listening carefully to \"{$message_trimmed}\" – what feelings come up for you around this, and should we turn it into a MUFFEIA post together?",
            "I'm reflecting on what you shared about \"{$message_trimmed}\"... 💫 Sometimes writing it out in the community feed helps too. Want help shaping it?",
            "\"{$message_trimmed}\" sounds important. 👂 Would explaining a little more help before you jump back into the main feed?",
            "I appreciate you trusting me with \"{$message_trimmed}\". 💕 On MUFFEIA, every story matters. What would help you feel supported right now—chatting more here, posting, or messaging someone?"
        ];
        
        return $follow_ups[array_rand($follow_ups)];
    }
    
    private function addToHistory($role, $message) {
        $this->conversation_history[] = [
            'role' => $role,
            'message' => $message,
            'timestamp' => time()
        ];
        
        // Keep only the last few exchanges
        if (count($this->conversation_history) > $this->max_history_length) {
            $this->conversation_history = array_slice($this->conversation_history, -$this->max_history_length);
        }
    }
    
    private function getHistoryContext() {
        if (empty($this->conversation_history)) {
            return '';
        }
        
        $context = '';
        foreach ($this->conversation_history as $exchange) {
            $speaker = $exchange['role'] === 'user' ? 'User' : 'Comfort Bot';
            $context .= "{$speaker}: {$exchange['message']} ";
        }
        
        return trim($context);
    }
    
    // Enhanced method for problem-specific comforting
    public function getProblemHelp($user_question, $problem_title = '', $problem_description = '') {
        $context = '';
        if (!empty($problem_title)) {
            $context .= "User is dealing with: $problem_title. ";
        }
        if (!empty($problem_description)) {
            $context .= "Details: " . substr($problem_description, 0, 200) . ". ";
        }
        
        $analysis = $this->analyzeMessage($user_question);
        return $this->sendMessage($user_question, $context);
    }
    
    // Method to clear conversation history
    public function clearHistory() {
        $this->conversation_history = [];
    }
    
    // Method to get conversation summary
    public function getConversationSummary() {
        if (empty($this->conversation_history)) {
            return "No conversation history yet.";
        }
        
        $user_messages = array_filter($this->conversation_history, function($msg) {
            return $msg['role'] === 'user';
        });
        
        $last_user_message = end($user_messages);
        return "Recent conversation about: " . substr($last_user_message['message'], 0, 100) . "...";
    }
}
?>