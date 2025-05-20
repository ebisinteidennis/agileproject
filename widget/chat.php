<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Add CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get widget ID from query string
$widgetId = isset($_GET['id']) ? $_GET['id'] : null;
$debug = isset($_GET['debug']) ? true : false;

if (!$widgetId) {
    header('HTTP/1.0 403 Forbidden');
    echo json_encode(['error' => 'Widget ID is required']);
    exit;
}

// Get user by widget ID
$user = getUserByWidgetId($widgetId);

if (!$user) {
    header('HTTP/1.0 403 Forbidden');
    echo json_encode(['error' => 'Invalid widget ID']);
    exit;
}

// Check if subscription is active and message limit not reached
if (!isSubscriptionActive($user)) {
    header('HTTP/1.0 403 Forbidden');
    echo json_encode(['error' => 'Subscription inactive or expired']);
    exit;
}

if (!canSendMessage($user['id'])) {
    header('HTTP/1.0 403 Forbidden');
    echo json_encode(['error' => 'Message limit reached']);
    exit;
}

// Generate unique visitor ID or use existing one from cookie
$visitorId = isset($_COOKIE['lvs_visitor_id']) ? $_COOKIE['lvs_visitor_id'] : bin2hex(random_bytes(16));
setcookie('lvs_visitor_id', $visitorId, time() + 86400 * 30, '/', '', false, true);

// Process incoming message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process POST requests...
    // Existing code for handling messages...
    exit;
}

// Get messages for this visitor
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_messages'])) {
    // Process GET requests for messages...
    // Existing code for fetching messages...
    exit;
}

// Return widget configuration
$settings = getSiteSettings();
$config = [
    'widget_id' => $widgetId,
    'site_name' => $settings['site_name'],
    'primary_color' => '#3498db', // Default color, could be customizable per user
    'visitor_id' => $visitorId,
    'api_endpoint' => SITE_URL . '/widget/chat.php?id=' . $widgetId,
    'debug' => $debug
];

header('Content-Type: application/javascript');
?>

// LiveSupport Widget
(function() {
    try {
        // Widget configuration
        const config = <?php echo json_encode($config); ?>;
        
        <?php if ($debug): ?>
        console.log('LiveSupport Widget: Configuration loaded', config);
        <?php endif; ?>
        
        // Create widget container
        function createWidget() {
            <?php if ($debug): ?>
            console.log('LiveSupport Widget: Creating widget container');
            <?php endif; ?>
            
            // Check if widget already exists
            if (document.getElementById('livesupport-widget')) {
                <?php if ($debug): ?>
                console.log('LiveSupport Widget: Widget already exists, skipping creation');
                <?php endif; ?>
                return;
            }
            
            const widgetHtml = `
                <div id="livesupport-widget" class="livesupport-widget">
                    <div class="livesupport-button" id="livesupport-button">
                        <div class="livesupport-icon">💬</div>
                    </div>
                    <div class="livesupport-chat-container" id="livesupport-chat-container">
                        <div class="livesupport-header">
                            <div class="livesupport-header-title">${config.site_name} Support</div>
                            <div class="livesupport-close" id="livesupport-close">×</div>
                        </div>
                        <div class="livesupport-messages" id="livesupport-messages"></div>
                        <div class="livesupport-input-container">
                            <div class="livesupport-visitor-info" id="livesupport-visitor-info">
                                <input type="text" id="livesupport-name" placeholder="Your name (optional)">
                                <input type="email" id="livesupport-email" placeholder="Your email (optional)">
                                <button id="livesupport-start-chat">Start Chat</button>
                            </div>
                            <div class="livesupport-message-input" id="livesupport-message-input" style="display: none;">
                                <textarea id="livesupport-textarea" placeholder="Type your message..."></textarea>
                                <button id="livesupport-send">Send</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Create style element with !important rules to prevent CSS conflicts
            const styleEl = document.createElement('style');
            styleEl.textContent = `
                .livesupport-widget * {
                    box-sizing: border-box !important;
                    font-family: Arial, sans-serif !important;
                }
                
                .livesupport-widget {
                    position: fixed !important;
                    bottom: 20px !important;
                    right: 20px !important;
                    z-index: 999999 !important;
                    font-size: 14px !important;
                    line-height: 1.4 !important;
                }
                
                .livesupport-button {
                    width: 60px !important;
                    height: 60px !important;
                    border-radius: 50% !important;
                    background-color: ${config.primary_color} !important;
                    color: white !important;
                    text-align: center !important;
                    cursor: pointer !important;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2) !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                }
                
                .livesupport-icon {
                    font-size: 24px !important;
                }
                
                .livesupport-chat-container {
                    position: absolute !important;
                    bottom: 80px !important;
                    right: 0 !important;
                    width: 320px !important;
                    height: 400px !important;
                    background-color: white !important;
                    border-radius: 10px !important;
                    box-shadow: 0 5px 40px rgba(0, 0, 0, 0.16) !important;
                    display: none !important;
                    flex-direction: column !important;
                    overflow: hidden !important;
                }
                
                .livesupport-header {
                    background-color: ${config.primary_color} !important;
                    color: white !important;
                    padding: 15px !important;
                    display: flex !important;
                    justify-content: space-between !important;
                    align-items: center !important;
                }
                
                .livesupport-header-title {
                    font-weight: bold !important;
                }
                
                .livesupport-close {
                    cursor: pointer !important;
                    font-size: 24px !important;
                }
                
                .livesupport-messages {
                    flex: 1 !important;
                    padding: 15px !important;
                    overflow-y: auto !important;
                }
                
                .livesupport-message {
                    margin-bottom: 10px !important;
                    padding: 8px 12px !important;
                    border-radius: 15px !important;
                    max-width: 80% !important;
                    word-wrap: break-word !important;
                }
                
                .livesupport-message-visitor {
                    background-color: #f1f0f0 !important;
                    color: #333 !important;
                    margin-left: auto !important;
                    border-bottom-right-radius: 5px !important;
                }
                
                .livesupport-message-agent {
                    background-color: ${config.primary_color} !important;
                    color: white !important;
                    margin-right: auto !important;
                    border-bottom-left-radius: 5px !important;
                }
                
                .livesupport-message-time {
                    font-size: 10px !important;
                    margin-top: 5px !important;
                    opacity: 0.7 !important;
                    text-align: right !important;
                }
                
                .livesupport-input-container {
                    padding: 10px !important;
                    border-top: 1px solid #eee !important;
                }
                
                .livesupport-visitor-info input {
                    width: 100% !important;
                    padding: 8px !important;
                    margin-bottom: 10px !important;
                    border: 1px solid #ddd !important;
                    border-radius: 4px !important;
                    box-sizing: border-box !important;
                }
                
                .livesupport-visitor-info button,
                .livesupport-message-input button {
                    background-color: ${config.primary_color} !important;
                    color: white !important;
                    border: none !important;
                    padding: 8px 15px !important;
                    border-radius: 4px !important;
                    cursor: pointer !important;
                    width: 100% !important;
                }
                
                .livesupport-message-input {
                    display: flex !important;
                }
                
                .livesupport-message-input textarea {
                    flex: 1 !important;
                    padding: 8px !important;
                    border: 1px solid #ddd !important;
                    border-radius: 4px !important;
                    resize: none !important;
                    height: 40px !important;
                    margin-right: 10px !important;
                    box-sizing: border-box !important;
                }
                
                .livesupport-message-input button {
                    width: auto !important;
                }
            `;
            
            // Append style to document
            document.head.appendChild(styleEl);
            
            // Create widget container
            const container = document.createElement('div');
            container.innerHTML = widgetHtml;
            document.body.appendChild(container);
            
            <?php if ($debug): ?>
            console.log('LiveSupport Widget: Widget container added to DOM');
            <?php endif; ?>
            
            // Initialize widget functionality
            initWidget();
        }
        
        // Initialize widget functionality
        function initWidget() {
            <?php if ($debug): ?>
            console.log('LiveSupport Widget: Initializing widget functionality');
            <?php endif; ?>
            
            const button = document.getElementById('livesupport-button');
            const chatContainer = document.getElementById('livesupport-chat-container');
            const closeButton = document.getElementById('livesupport-close');
            const startChatButton = document.getElementById('livesupport-start-chat');
            const visitorInfo = document.getElementById('livesupport-visitor-info');
            const messageInput = document.getElementById('livesupport-message-input');
            const textarea = document.getElementById('livesupport-textarea');
            const sendButton = document.getElementById('livesupport-send');
            const messagesContainer = document.getElementById('livesupport-messages');
            
            // Check if elements exist
            if (!button || !chatContainer || !closeButton || !startChatButton ||
                !visitorInfo || !messageInput || !textarea || !sendButton || !messagesContainer) {
                console.error('LiveSupport Widget: Required elements not found in DOM');
                return;
            }
            
            let lastMessageId = 0;
            let visitorName, visitorEmail;
            
            // Toggle chat window
            button.addEventListener('click', function() {
                <?php if ($debug): ?>
                console.log('LiveSupport Widget: Chat button clicked');
                <?php endif; ?>
                
                if (chatContainer.style.display === 'flex') {
                    chatContainer.style.display = 'none';
                } else {
                    chatContainer.style.display = 'flex';
                    fetchMessages();
                }
            });
            
            // Rest of your existing widget code...
        }
        
        // Initialize the widget when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', createWidget);
            <?php if ($debug): ?>
            console.log('LiveSupport Widget: Waiting for DOMContentLoaded');
            <?php endif; ?>
        } else {
            createWidget();
            <?php if ($debug): ?>
            console.log('LiveSupport Widget: DOM already loaded, creating widget now');
            <?php endif; ?>
        }
    } catch(error) {
        console.error('LiveSupport Widget Error:', error);
    }
})();