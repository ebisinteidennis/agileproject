// LiveSupport Widget Embed Script (embed.js)
(function() {
    'use strict';
    
    // Get widget ID from the global variable
    var WIDGET_ID = window.WIDGET_ID;
    
    // Check if widget ID is provided
    if (!WIDGET_ID) {
        console.error('LiveSupport Widget: WIDGET_ID not defined. Please set WIDGET_ID before loading embed.js');
        return;
    }
    
    // Configuration
    var config = {
        widgetId: WIDGET_ID,
        baseUrl: 'https://agileproject.site', // Default URL
        visitorId: getOrCreateVisitorId(),
        messages: [],
        theme: 'light',
        position: 'bottom-right',
        primaryColor: '#4a6cf7'
    };
    
    // For real-time messaging
    var lastMessageCheck = 0;
    var messageCheckInterval = null;
    var messageIds = {}; // Track message IDs to avoid duplicates
    
    // Generate or retrieve visitor ID
    function getOrCreateVisitorId() {
        var visitorId = localStorage.getItem('livesupport_visitor_id_' + WIDGET_ID);
        if (!visitorId) {
            visitorId = 'visitor_' + Math.random().toString(36).substring(2, 15);
            localStorage.setItem('livesupport_visitor_id_' + WIDGET_ID, visitorId);
        }
        return visitorId;
    }
    
    // Format API URL
    function formatApiUrl(baseUrl, endpoint) {
        if (baseUrl.endsWith('/')) {
            baseUrl = baseUrl.slice(0, -1);
        }
        
        if (!endpoint.startsWith('/')) {
            endpoint = '/' + endpoint;
        }
        
        return baseUrl + endpoint;
    }
    
    // Load widget styles
    function loadStyles() {
        var style = document.createElement('style');
        style.textContent = `
            .livesupport-widget {
                position: fixed;
                z-index: 999999;
                font-family: Arial, sans-serif;
                font-size: 14px;
                line-height: 1.4;
                box-sizing: border-box;
            }
            
            .livesupport-widget * {
                box-sizing: inherit;
            }
            
            .livesupport-widget.bottom-right {
                bottom: 20px;
                right: 20px;
            }
            
            .livesupport-widget.bottom-left {
                bottom: 20px;
                left: 20px;
            }
            
            .livesupport-button {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background-color: ${config.primaryColor};
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                color: white;
                transition: all 0.3s ease;
            }
            
            .livesupport-button:hover {
                transform: scale(1.1);
            }
            
            .livesupport-chat-window {
                position: absolute;
                bottom: 80px;
                right: 0;
                width: 320px;
                height: 400px;
                background-color: white;
                border-radius: 10px;
                box-shadow: 0 10px 50px rgba(0, 0, 0, 0.2);
                display: none;
                flex-direction: column;
                overflow: hidden;
            }
            
            .livesupport-header {
                background-color: ${config.primaryColor};
                color: white;
                padding: 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .livesupport-header-title {
                font-weight: 600;
            }
            
            .livesupport-header-close {
                cursor: pointer;
                font-size: 20px;
            }
            
            .livesupport-messages {
                flex: 1;
                padding: 15px;
                overflow-y: auto;
                background-color: #f8f9fa;
            }
            
            .livesupport-message {
                margin-bottom: 10px;
                max-width: 85%;
            }
            
            .livesupport-message-bubble {
                padding: 10px;
                border-radius: 15px;
                word-wrap: break-word;
            }
            
            .livesupport-user {
                margin-left: auto;
            }
            
            .livesupport-user .livesupport-message-bubble {
                background-color: ${config.primaryColor};
                color: white;
                border-bottom-right-radius: 5px;
            }
            
            .livesupport-agent {
                margin-right: auto;
            }
            
            .livesupport-agent .livesupport-message-bubble {
                background-color: #e9ecef;
                color: #333;
                border-bottom-left-radius: 5px;
            }
            
            .livesupport-message-time {
                font-size: 10px;
                margin-top: 5px;
                opacity: 0.7;
                text-align: right;
            }
            
            .livesupport-input-container {
                padding: 10px;
                border-top: 1px solid #dee2e6;
                display: flex;
            }
            
            .livesupport-input {
                flex: 1;
                border: 1px solid #ced4da;
                border-radius: 20px;
                padding: 8px 12px;
                margin-right: 10px;
                outline: none;
            }
            
            .livesupport-send {
                background-color: ${config.primaryColor};
                color: white;
                border: none;
                border-radius: 50%;
                width: 36px;
                height: 36px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .livesupport-notification {
                position: absolute;
                top: -5px;
                right: -5px;
                background-color: red;
                color: white;
                border-radius: 50%;
                min-width: 18px;
                height: 18px;
                font-size: 11px;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 2px;
            }
            
            @media (max-width: 480px) {
                .livesupport-chat-window {
                    width: 280px;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Create widget container
    function createWidget() {
        // Create container
        var container = document.createElement('div');
        container.className = 'livesupport-widget ' + config.position;
        document.body.appendChild(container);
        
        // Create button
        var button = document.createElement('div');
        button.className = 'livesupport-button';
        button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><div class="livesupport-notification">0</div>';
        container.appendChild(button);
        
        // Create chat window
        var chatWindow = document.createElement('div');
        chatWindow.className = 'livesupport-chat-window';
        container.appendChild(chatWindow);
        
        // Create header
        var header = document.createElement('div');
        header.className = 'livesupport-header';
        header.innerHTML = '<div class="livesupport-header-title">Live Support</div><div class="livesupport-header-close">&times;</div>';
        chatWindow.appendChild(header);
        
        // Create messages container
        var messagesContainer = document.createElement('div');
        messagesContainer.className = 'livesupport-messages';
        chatWindow.appendChild(messagesContainer);
        
        // Create input container
        var inputContainer = document.createElement('div');
        inputContainer.className = 'livesupport-input-container';
        
        // Create input field
        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'livesupport-input';
        input.placeholder = 'Type a message...';
        
        // Create send button
        var sendButton = document.createElement('button');
        sendButton.className = 'livesupport-send';
        sendButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
        
        // Add elements to input container
        inputContainer.appendChild(input);
        inputContainer.appendChild(sendButton);
        
        // Add input container to chat window
        chatWindow.appendChild(inputContainer);
        
        // Add event listeners
        button.addEventListener('click', function() {
            if (chatWindow.style.display === 'flex') {
                chatWindow.style.display = 'none';
            } else {
                chatWindow.style.display = 'flex';
                // Load messages
                loadMessages();
                // Focus input
                input.focus();
                // Clear notification
                var notification = button.querySelector('.livesupport-notification');
                notification.style.display = 'none';
                notification.textContent = '0';
            }
        });
        
        header.querySelector('.livesupport-header-close').addEventListener('click', function() {
            chatWindow.style.display = 'none';
        });
        
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
        
        sendButton.addEventListener('click', sendMessage);
        
        // Store references
        config.elements = {
            container: container,
            button: button,
            chatWindow: chatWindow,
            messagesContainer: messagesContainer,
            input: input,
            sendButton: sendButton,
            notification: button.querySelector('.livesupport-notification')
        };
        
        // Start polling for messages
        startMessagePolling();
        
        return container;
    }
    
    // Send message
    function sendMessage() {
        var input = config.elements.input;
        var message = input.value.trim();
        
        if (!message) return;
        
        // Clear input
        input.value = '';
        
        // Add message to UI
        addMessage('user', message);
        
        // Store message locally
        storeMessage('user', message);
        
        // Send message to server
        sendMessageToServer(message);
    }
    
    // Add message to UI
    function addMessage(type, message, time, messageId) {
        // Check if message already exists in UI (by ID if provided)
        if (messageId && messageIds[messageId]) {
            return; // Skip if we've already displayed this message
        }
        
        // Mark message as displayed if ID is provided
        if (messageId) {
            messageIds[messageId] = true;
        }
        
        var messagesContainer = config.elements.messagesContainer;
        var messageEl = document.createElement('div');
        messageEl.className = 'livesupport-message livesupport-' + type;
        
        var bubbleEl = document.createElement('div');
        bubbleEl.className = 'livesupport-message-bubble';
        bubbleEl.textContent = message;
        messageEl.appendChild(bubbleEl);
        
        var timeEl = document.createElement('div');
        timeEl.className = 'livesupport-message-time';
        timeEl.textContent = time || formatTime(new Date());
        messageEl.appendChild(timeEl);
        
        messagesContainer.appendChild(messageEl);
        
        // Scroll to bottom
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // Format time
    function formatTime(date) {
        var hours = date.getHours();
        var minutes = date.getMinutes();
        var ampm = hours >= 12 ? 'PM' : 'AM';
        
        hours = hours % 12;
        hours = hours ? hours : 12;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        
        return hours + ':' + minutes + ' ' + ampm;
    }
    
    // Store message locally
    function storeMessage(type, message, time, id) {
        config.messages.push({
            type: type,
            message: message,
            time: time || new Date().toISOString(),
            id: id
        });
        
        // Save to localStorage for persistence
        try {
            localStorage.setItem('livesupport_messages_' + config.widgetId, 
                JSON.stringify(config.messages));
        } catch (e) {
            console.error('Error saving messages to localStorage:', e);
        }
    }
    
    // Load messages from localStorage
    function loadMessages() {
        try {
            var messages = localStorage.getItem('livesupport_messages_' + config.widgetId);
            if (messages) {
                config.messages = JSON.parse(messages);
                
                // Display messages
                var messagesContainer = config.elements.messagesContainer;
                messagesContainer.innerHTML = '';
                messageIds = {}; // Reset message IDs
                
                // Add messages to UI
                config.messages.forEach(function(msg) {
                    addMessage(msg.type, msg.message, formatTime(new Date(msg.time)), msg.id);
                });
                
                // Set last message check time
                if (config.messages.length > 0) {
                    var lastMsg = config.messages[config.messages.length - 1];
                    lastMessageCheck = new Date(lastMsg.time).getTime();
                } else {
                    lastMessageCheck = Date.now() - (60 * 60 * 1000); // Check for messages from the last hour
                }
            } else {
                lastMessageCheck = Date.now() - (60 * 60 * 1000); // Check for messages from the last hour
            }
            
            // Check for new messages from server
            checkForNewMessages();
            
        } catch (e) {
            console.error('Error loading messages from localStorage:', e);
            lastMessageCheck = Date.now() - (60 * 60 * 1000); // Check for messages from the last hour
        }
    }
    
    // Start polling for new messages
    function startMessagePolling() {
        // Stop any existing interval
        if (messageCheckInterval) {
            clearInterval(messageCheckInterval);
        }
        
        // Check for new messages right away
        if (config.visitorId) {
            checkForNewMessages();
        }
        
        // Check for new messages every 3 seconds (more frequent for real-time updates)
        messageCheckInterval = setInterval(function() {
            if (config.visitorId) {
                checkForNewMessages();
            }
        }, 3000); // 3 seconds instead of 5 for more responsive updates
    }
    
    // Check for new messages from the server
    function checkForNewMessages() {
        var apiUrl = formatApiUrl(config.baseUrl, '/widget/api.php') + 
            '?action=get_messages&widget_id=' + encodeURIComponent(config.widgetId) + 
            '&visitor_id=' + encodeURIComponent(config.visitorId);
        
        // Don't use the since parameter to ensure we get all messages
        // This makes sure we don't miss anything, even if timestamps are slightly off
        
        fetch(apiUrl)
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success && data.messages && data.messages.length > 0) {
                    console.log('Received messages from server:', data.messages);
                    
                    var hasNewMessages = false;
                    
                    // Process all messages
                    data.messages.forEach(function(msg) {
                        // Check if we already have this message (by ID)
                        var alreadyDisplayed = false;
                        
                        if (msg.id) {
                            alreadyDisplayed = messageIds[msg.id] === true;
                        } else {
                            // If no ID, check by content and time (less reliable)
                            alreadyDisplayed = config.messages.some(function(existingMsg) {
                                return existingMsg.message === msg.message && 
                                       existingMsg.type === (msg.sender_type === 'agent' ? 'agent' : 'user');
                            });
                        }
                        
                        if (!alreadyDisplayed) {
                            // Determine message type
                            var messageType = msg.sender_type === 'agent' ? 'agent' : 'user';
                            
                            // Skip visitor's own messages (we already display those when sent)
                            if (messageType === 'agent') {
                                // Add message to UI
                                addMessage(messageType, msg.message, formatTime(new Date(msg.created_at)), msg.id);
                                
                                // Store message locally
                                storeMessage(messageType, msg.message, msg.created_at, msg.id);
                                
                                hasNewMessages = true;
                            }
                        }
                    });
                    
                    // Update notification if there are new messages and chat window is closed
                    if (hasNewMessages && config.elements.chatWindow.style.display !== 'flex') {
                        var notification = config.elements.notification;
                        var currentCount = parseInt(notification.textContent) || 0;
                        notification.textContent = currentCount + 1;
                        notification.style.display = 'flex';
                        
                        // Optionally, play a notification sound
                        try {
                            var audio = new Audio('data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU...'); // Base64 encoded short sound
                            audio.volume = 0.5;
                            audio.play();
                        } catch(e) {
                            // Ignore audio errors
                        }
                    }
                }
            })
            .catch(function(error) {
                console.error('Error checking for messages:', error);
            });
    }
    
    // Send message to server
    function sendMessageToServer(message) {
        // Disable send button during sending
        if (config.elements.sendButton) {
            config.elements.sendButton.disabled = true;
        }
        
        // Create form data for compatibility
        var formData = new FormData();
        formData.append('widget_id', config.widgetId);
        formData.append('message', message);
        formData.append('visitor_id', config.visitorId);
        formData.append('url', window.location.href);
        formData.append('user_agent', navigator.userAgent);
        
        // API URL
        var apiUrl = formatApiUrl(config.baseUrl, '/widget/api.php?action=send_message');
        
        // Send request
        fetch(apiUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            console.log('Message send response:', data);
            
            if (data.success) {
                console.log('Message sent successfully');
                
                // Update visitor ID if provided
                if (data.visitor_id && data.visitor_id !== config.visitorId) {
                    config.visitorId = data.visitor_id;
                    localStorage.setItem('livesupport_visitor_id_' + config.widgetId, config.visitorId);
                }
            } else {
                console.error('Error sending message:', data.error);
                addMessage('system', 'Error: ' + (data.error || 'Failed to send message'));
            }
        })
        .catch(function(error) {
            console.error('Error sending message:', error);
            addMessage('system', 'Error: Could not connect to server');
        })
        .finally(function() {
            // Re-enable send button
            if (config.elements.sendButton) {
                config.elements.sendButton.disabled = false;
            }
        });
    }
    
    // Register visitor with the server
    function registerVisitor() {
        var data = {
            widget_id: config.widgetId,
            visitor_id: config.visitorId,
            url: window.location.href,
            user_agent: navigator.userAgent
        };
        
        var apiUrl = formatApiUrl(config.baseUrl, '/widget/api.php?action=register_visitor');
        
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            console.log('Visitor registration response:', data);
            
            if (data.success) {
                // Update visitor ID if provided
                if (data.visitor_id && data.visitor_id !== config.visitorId) {
                    config.visitorId = data.visitor_id;
                    localStorage.setItem('livesupport_visitor_id_' + config.widgetId, config.visitorId);
                }
            }
        })
        .catch(function(error) {
            console.error('Error registering visitor:', error);
        });
    }
    
    // Initialize widget
    function initializeWidget() {
        try {
            // Load configuration
            var configUrl = formatApiUrl('https://agileproject.site', '/widget/api.php?action=get_config&widget_id=' + WIDGET_ID);
            
            fetch(configUrl)
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.success && data.config) {
                        // Update config with server values
                        if (data.config.baseUrl || data.config.siteUrl) {
                            config.baseUrl = data.config.baseUrl || data.config.siteUrl;
                        }
                        
                        if (data.config.primaryColor) {
                            config.primaryColor = data.config.primaryColor;
                        }
                        
                        if (data.config.position) {
                            config.position = data.config.position;
                        }
                        
                        // Initialize widget
                        loadStyles();
                        createWidget();
                        registerVisitor();
                    } else {
                        // Initialize with defaults
                        loadStyles();
                        createWidget();
                        registerVisitor();
                    }
                })
                .catch(function(error) {
                    console.error('Error loading configuration:', error);
                    // Initialize with defaults
                    loadStyles();
                    createWidget();
                    registerVisitor();
                });
        } catch (err) {
            console.error('Widget initialization error:', err);
            // Initialize with defaults
            loadStyles();
            createWidget();
            registerVisitor();
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeWidget);
    } else {
        setTimeout(initializeWidget, 100);
    }
})();