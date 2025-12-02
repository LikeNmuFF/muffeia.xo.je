<?php
// includes/DatabaseChatbot.php
class DatabaseChatbot {
    private $db;
    private $isConnected = false;
    
    public function __construct($db_connection) {
        if (!$db_connection) {
            throw new Exception("Database connection is required");
        }
        
        $this->db = $db_connection;
        $this->isConnected = true;
        
        try {
            $this->initializeDatabase();
        } catch (Exception $e) {
            error_log("DatabaseChatbot initialization error: " . $e->getMessage());
            throw new Exception("Failed to initialize chatbot database");
        }
    }
    
    private function initializeDatabase() {
        // Create responses table if not exists
        $query = "CREATE TABLE IF NOT EXISTS chatbot_responses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(50),
            pattern TEXT,
            response TEXT,
            usage_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!$this->db->query($query)) {
            throw new Exception("Failed to create table: " . $this->db->error);
        }
        
        // Check if table is empty and seed if needed
        $check = $this->db->query("SELECT COUNT(*) as count FROM chatbot_responses");
        if (!$check) {
            throw new Exception("Failed to check table: " . $this->db->error);
        }
        
        $row = $check->fetch_assoc();
        if ($row['count'] == 0) {
            $this->seedResponses();
        }
    }
    
    private function seedResponses() {
        $responses = [
            // Greetings
            ['greeting', 'hello', "Hello! 👋 How can I help you today?"],
            ['greeting', 'hi', "Hi there! 🤗 What's on your mind?"],
            ['greeting', 'hey', "Hey! 😊 How are you doing?"],
            ['greeting', 'good morning', "Good morning! ☀️ How can I assist you?"],
            ['greeting', 'good afternoon', "Good afternoon! 🌤️ What can I do for you?"],
            ['greeting', 'good evening', "Good evening! 🌙 How may I help?"],
            
            // Feelings
            ['feeling', 'sad', "I'm sorry you're feeling sad. 💔 Would you like to talk about it?"],
            ['feeling', 'happy', "I'm glad you're feeling happy! 😊 That's wonderful!"],
            ['feeling', 'angry', "I understand you're feeling angry. 😤 Take a deep breath."],
            ['feeling', 'anxious', "Anxiety can be tough. 😰 Let's talk through this together."],
            ['feeling', 'stressed', "Stress is common. 😓 I'm here to help you cope."],
            ['feeling', 'depressed', "Depression is serious. 😢 Please consider talking to a professional too."],
            
            // Problems
            ['problem', 'problem', "Let's work through this problem together. 🤔 Tell me more."],
            ['problem', 'issue', "I'm here to help with any issues. 💡 What's going on?"],
            ['problem', 'help', "Of course I'll help! 🙌 What do you need assistance with?"],
            ['problem', 'stuck', "Being stuck happens. 🔄 Let's find a solution."],
            ['problem', 'confused', "Confusion is normal. 🤷 Let me clarify things for you."],
            
            // Support
            ['support', 'thank', "You're welcome! 😊 I'm here anytime you need."],
            ['support', 'thanks', "Happy to help! 🎉 Feel free to reach out anytime."],
            ['support', 'advice', "I'd be happy to give advice. 💭 What's the situation?"],
            ['support', 'suggestion', "I can offer suggestions! 💡 What area are you looking for help with?"],
            
            // Relationships
            ['relationship', 'friend', "Friendships are important. 👥 How can I help with your friend situation?"],
            ['relationship', 'family', "Family matters can be complex. 👨‍👩‍👧‍👦 I'm listening."],
            ['relationship', 'relationship', "Relationships take work. ❤️ What's on your mind?"],
            ['relationship', 'breakup', "Breakups are painful. 💔 Take your time to heal."],
            
            // Mental Health
            ['mental_health', 'therapy', "Therapy can be very helpful. 🧠 Have you considered it?"],
            ['mental_health', 'counseling', "Counseling is a great step. 💚 I support your decision."],
            ['mental_health', 'suicide', "Please reach out for help immediately: National Suicide Prevention Lifeline 1-800-273-8255"],
            ['mental_health', 'self harm', "Please talk to someone who can help: Crisis Text Line - Text HOME to 741741"],
            
            // Academic
            ['academic', 'school', "School can be challenging. 📚 What subject are you working on?"],
            ['academic', 'homework', "Need help with homework? 📝 I can try to guide you."],
            ['academic', 'study', "Study strategies vary by person. 📖 What works best for you?"],
            ['academic', 'exam', "Exam stress is real. ✍️ Let's prepare together."],
            
            // General
            ['general', 'how are you', "I'm here and ready to help! 🤖 How are you?"],
            ['general', 'what can you do', "I can listen, offer support, and help with problems. 💪 Try me!"],
            ['general', 'who are you', "I'm MUFFEIA AI, your friendly assistant. 🤖 Here to help!"],
            ['general', 'bye', "Goodbye! 👋 Take care and come back anytime."],
            ['general', 'goodbye', "See you later! 🌟 Stay strong!"]
        ];
        
        $stmt = $this->db->prepare("INSERT INTO chatbot_responses (category, pattern, response) VALUES (?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $this->db->error);
        }
        
        foreach ($responses as $response) {
            $stmt->bind_param("sss", $response[0], $response[1], $response[2]);
            if (!$stmt->execute()) {
                error_log("Failed to insert response: " . $stmt->error);
            }
        }
        
        $stmt->close();
    }
    
    public function getResponse($message) {
        if (!$this->isConnected) {
            return "Sorry, I'm having trouble connecting right now. 😔";
        }
        
        $message = strtolower(trim($message));
        
        if (empty($message)) {
            return "I didn't catch that. Could you say that again? 🤔";
        }
        
        try {
            // Escape the message for SQL LIKE
            $escaped_message = $this->db->real_escape_string($message);
            
            // Find matching patterns using LIKE for better matching
            $query = "SELECT * FROM chatbot_responses WHERE ? LIKE CONCAT('%', pattern, '%') ORDER BY usage_count DESC LIMIT 5";
            $stmt = $this->db->prepare($query);
            
            if (!$stmt) {
                throw new Exception("Failed to prepare query: " . $this->db->error);
            }
            
            $stmt->bind_param("s", $message);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $matches = [];
            while ($row = $result->fetch_assoc()) {
                $matches[] = $row;
            }
            
            $stmt->close();
            
            if (!empty($matches)) {
                // Pick best match (highest usage or random if tied)
                $bestMatch = $matches[0];
                
                // Update usage count
                $updateStmt = $this->db->prepare("UPDATE chatbot_responses SET usage_count = usage_count + 1 WHERE id = ?");
                if ($updateStmt) {
                    $updateStmt->bind_param("i", $bestMatch['id']);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
                
                return $bestMatch['response'];
            }
            
            // No match found - return learning response
            return $this->learnResponse($message);
            
        } catch (Exception $e) {
            error_log("DatabaseChatbot error: " . $e->getMessage());
            return "I'm having trouble understanding right now. 😅 Could you rephrase that?";
        }
    }
    
    private function learnResponse($message) {
        $defaultResponses = [
            "I hear you. 💭 Could you tell me more about that?",
            "That's interesting! 🤔 Help me understand better.",
            "I'm listening carefully. 👂 What would you like to discuss?",
            "Thank you for sharing. 💕 How does that make you feel?",
            "I want to help. 🤗 Can you elaborate on that?",
            "I'm not sure I understand completely. 🧐 Could you explain more?",
            "That's a new one for me! 📚 Tell me more so I can learn.",
            "Hmm, interesting perspective. 💡 What else is on your mind?"
        ];
        
        return $defaultResponses[array_rand($defaultResponses)];
    }
    
    // Optional: Add method to get statistics
    public function getStats() {
        try {
            $query = "SELECT 
                        COUNT(*) as total_responses,
                        SUM(usage_count) as total_uses,
                        COUNT(DISTINCT category) as categories
                      FROM chatbot_responses";
            $result = $this->db->query($query);
            
            if ($result) {
                return $result->fetch_assoc();
            }
        } catch (Exception $e) {
            error_log("Failed to get stats: " . $e->getMessage());
        }
        
        return ['total_responses' => 0, 'total_uses' => 0, 'categories' => 0];
    }
}
?>