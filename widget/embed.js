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
        baseUrl: getBaseUrl(),
        visitorId: getOrCreateVisitorId(),
        messages: [], // Store messages locally
        theme: 'light',
        position: 'bottom-right',
        primaryColor: '#4a6cf7'
    };
    
    // Debug mode
    var DEBUG = true;
    
    // For real-time messaging
    var lastMessageCheck = 0;
    var messageCheckInterval = null;
    
    // Log to console if in debug mode
    function log() {
        if (DEBUG && console && console.log) {
            console.log.apply(console, ['LiveSupport Widget:'].concat(Array.prototype.slice.call(arguments)));
        }
    }
    
    log('Initializing with widget ID:', config.widgetId);
    log('Base URL:', config.baseUrl);
    log('Visitor ID:', config.visitorId);
    
    // Get base URL from script src
    function getBaseUrl() {
        var scripts = document.getElementsByTagName('script');
        var currentScript = scripts[scripts.length - 1];
        var src = currentScript.src || '';
        
        // If we can't determine the base URL, use the current domain
        if (!src || src.indexOf('/widget/embed.js') === -1) {
            var protocol = window.location.protocol;
            var hostname = window.location.hostname;
            return protocol + '//' + hostname;
        }
        
        // Extract base URL from script src
        return src.substring(0, src.indexOf('/widget'));
    }
    
    // Generate or retrieve visitor ID
    function getOrCreateVisitorId() {
        var visitorId = localStorage.getItem('livesupport_visitor_id');
        if (!visitorId) {
            visitorId = generateRandomId();
            localStorage.setItem('livesupport_visitor_id', visitorId);
        }
        return visitorId;
    }
    
    // Generate random ID
    function generateRandomId() {
        return 'visitor_' + Math.random().toString(36).substring(2, 15) + 
               Math.random().toString(36).substring(2, 15);
    }
    
    // Load widget styles
    function loadStyles() {
        var style = document.createElement('style');
        style.textContent = `
            .livesupport-widget {
                position: fixed;
                z-index: 9999;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', sans-serif;
                font-size: 14px;
                line-height: 1.4;
            }
            
            .livesupport-widget.bottom-right {
                bottom: 20px;
                right: 20px;
            }
            
            .livesupport-button {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background-color: #4a6cf7;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                color: white;
            }
            
            .livesupport-chat-window {
                position: absolute;
                bottom: 80px;
                right: 0;
                width: 320px;
                height: 400px;
                background-color: white;
                border-radius: 10px;
                box-shadow: 0 5px 40px rgba(0, 0, 0, 0.16);
                display: none;
                flex-direction: column;
                overflow: hidden;
            }
            
            .livesupport-header {
                background-color: #4a6cf7;
                color: white;
                padding: 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .livesupport-header-title {
                font-weight: bold;
            }
            
            .livesupport-header-close {
                cursor: pointer;
                font-size: 24px;
            }
            
            .livesupport-messages {
                flex: 1;
                padding: 15px;
                overflow-y: auto;
                background-color: #f9f9f9;
            }
            
            .livesupport-message {
                margin-bottom: 10px;
                display: flex;
                flex-direction: column;
            }
            
            .livesupport-message-bubble {
                padding: 10px 12px;
                border-radius: 15px;
                max-width: 80%;
                word-wrap: break-word;
            }
            
            .livesupport-user .livesupport-message-bubble {
                background-color: #4a6cf7;
                color: white;
                margin-left: auto;
                border-bottom-right-radius: 4px;
            }
            
            .livesupport-agent .livesupport-message-bubble {
                background-color: #e5e5ea;
                color: black;
                margin-right: auto;
                border-bottom-left-radius: 4px;
            }
            
            .livesupport-system {
                text-align: center;
                margin: 10px 0;
                color: #666;
                font-size: 12px;
            }
            
            .livesupport-message-time {
                font-size: 10px;
                margin-top: 4px;
                opacity: 0.7;
                align-self: flex-end;
            }
            
            .livesupport-input-container {
                padding: 10px;
                border-top: 1px solid #eee;
                background-color: white;
                display: flex;
            }
            
            .livesupport-input {
                flex: 1;
                border: 1px solid #ddd;
                border-radius: 20px;
                padding: 8px 12px;
                outline: none;
                resize: none;
                height: 40px;
                max-height: 100px;
                overflow-y: auto;
            }
            
            .livesupport-send {
                background-color: #4a6cf7;
                color: white;
                border: none;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                margin-left: 10px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .livesupport-send:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            
            .livesupport-notification {
                position: absolute;
                top: -10px;
                right: -5px;
                background-color: #ff4136;
                color: white;
                border-radius: 50%;
                width: 20px;
                height: 20px;
                font-size: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                display: none;
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
        
        // Create greeting message
        var greetingMessage = document.createElement('div');
        greetingMessage.className = 'livesupport-system';
        greetingMessage.textContent = 'Hi there! How can we help you today?';
        messagesContainer.appendChild(greetingMessage);
        
        // Create input container
        var inputContainer = document.createElement('div');
        inputContainer.className = 'livesupport-input-container';
        chatWindow.appendChild(inputContainer);
        
        // Create input field
        var input = document.createElement('textarea');
        input.className = 'livesupport-input';
        input.placeholder = 'Type a message...';
        input.rows = 1;
        inputContainer.appendChild(input);
        
        // Create send button
        var sendButton = document.createElement('button');
        sendButton.className = 'livesupport-send';
        sendButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
        inputContainer.appendChild(sendButton);
        
        // Add event listeners
        button.addEventListener('click', function() {
            chatWindow.style.display = chatWindow.style.display === 'flex' ? 'none' : 'flex';
            if (chatWindow.style.display === 'flex') {
                // Load messages when chat is opened
                loadMessages();
                input.focus();
                
                // Clear notification when opening chat
                var notification = button.querySelector('.livesupport-notification');
                notification.style.display = 'none';
                notification.textContent = '0';
            }
        });
        
        header.querySelector('.livesupport-header-close').addEventListener('click', function() {
            chatWindow.style.display = 'none';
        });
        
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
            
            // Auto-resize textarea
            setTimeout(function() {
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 100) + 'px';
            }, 0);
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
        
        // Set primary color
        setColor(config.primaryColor);
        
        // Register visitor
        registerVisitor();
        
        // Start polling for messages
        startMessagePolling();
        
        return container;
    }
    
    // Set primary color
    function setColor(color) {
        var elements = config.elements;
        if (!elements) return;
        
        elements.button.style.backgroundColor = color;
        elements.chatWindow.querySelector('.livesupport-header').style.backgroundColor = color;
    }
    
    // Send message to server
    function sendMessage() {
        var input = config.elements.input;
        var message = input.value.trim();
        
        if (!message) return;
        
        // Clear input
        input.value = '';
        input.style.height = 'auto';
        
        // Add message to UI
        addMessage('user', message);
        
        // Store message locally
        storeMessage('user', message);
        
        // Send message to server
        sendMessageToServer(message);
    }
    
    // Add message to UI
    function addMessage(type, message, time, messageId) {
        var messagesContainer = config.elements.messagesContainer;
        var messageEl = document.createElement('div');
        
        if (type === 'system') {
            messageEl.className = 'livesupport-system';
            messageEl.textContent = message;
        } else {
            messageEl.className = 'livesupport-message livesupport-' + type;
            if (messageId) {
                messageEl.setAttribute('data-id', messageId);
            }
            
            var bubbleEl = document.createElement('div');
            bubbleEl.className = 'livesupport-message-bubble';
            bubbleEl.textContent = message;
            messageEl.appendChild(bubbleEl);
            
            var timeEl = document.createElement('div');
            timeEl.className = 'livesupport-message-time';
            timeEl.textContent = time || formatTime(new Date());
            messageEl.appendChild(timeEl);
        }
        
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
            log('Error saving messages to localStorage:', e);
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
                
                // Clear existing messages except system greeting
                while (messagesContainer.childNodes.length > 1) {
                    messagesContainer.removeChild(messagesContainer.lastChild);
                }
                
                // Add messages to UI
                config.messages.forEach(function(msg) {
                    addMessage(msg.type, msg.message, formatTime(new Date(msg.time)), msg.id);
                });
                
                // Set last message check time
                if (config.messages.length > 0) {
                    var lastMsg = config.messages[config.messages.length - 1];
                    lastMessageCheck = new Date(lastMsg.time).getTime();
                } else {
                    lastMessageCheck = Date.now();
                }
            } else {
                lastMessageCheck = Date.now();
            }
            
            // After loading stored messages, check for any new ones from the server
            checkForNewMessages();
            
        } catch (e) {
            log('Error loading messages from localStorage:', e);
            lastMessageCheck = Date.now();
        }
    }
    
    // Start polling for new messages
    function startMessagePolling() {
        // Check for new messages every 5 seconds
        messageCheckInterval = setInterval(function() {
            checkForNewMessages();
        }, 5000);
    }
    
    // Check for new messages from the server
    function checkForNewMessages() {
        // Don't check if we don't have a visitor ID yet
        if (!config.visitorId) return;
        
        fetch(config.baseUrl + '/widget/api.php?action=get_messages&widget_id=' + 
            encodeURIComponent(config.widgetId) + '&visitor_id=' + 
            encodeURIComponent(config.visitorId) + '&since=' + lastMessageCheck)
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.messages && data.messages.length > 0) {
                log('Received new messages:', data.messages);
                
                // Get agent messages
                var agentMessages = data.messages.filter(function(msg) {
                    return msg.sender_type === 'agent';
                });
                
                // Display new agent messages
                if (agentMessages.length > 0) {
                    // Update notification if chat window is closed
                    if (config.elements.chatWindow.style.display !== 'flex') {
                        var notification = config.elements.notification;
                        var currentCount = parseInt(notification.textContent) || 0;
                        notification.textContent = currentCount + agentMessages.length;
                        notification.style.display = 'flex';
                    }
                    
                    // Add messages to UI
                    agentMessages.forEach(function(msg) {
                        // Check if message already exists
                        if (!isMessageDisplayed(msg.id)) {
                            addMessage('agent', msg.message, formatTime(new Date(msg.created_at)), msg.id);
                            storeMessage('agent', msg.message, msg.created_at, msg.id);
                        }
                    });
                }
                
                // Update last check time to the latest message timestamp
                var latestTime = Math.max.apply(null, data.messages.map(function(msg) {
                    return new Date(msg.created_at).getTime();
                }));
                
                if (latestTime > lastMessageCheck) {
                    lastMessageCheck = latestTime;
                }
            }
        })
        .catch(function(error) {
            log('Error checking for messages:', error);
        });
    }
    
    // Check if a message is already displayed
    function isMessageDisplayed(messageId) {
        return config.messages.some(function(msg) {
            return msg.id && msg.id == messageId;
        });
    }
    
    // Send message to server
    function sendMessageToServer(message) {
        log('Sending message to server:', message);
        
        // Debug log widget ID to verify it's being passed correctly
        log('DEBUG: Using widget ID:', config.widgetId);
        
        // Ensure we have a widget ID to send
        if (!config.widgetId) {
            log('ERROR: Widget ID not available for sending message');
            addMessage('system', 'Configuration error. Please refresh the page and try again.');
            
            // Try to recover by using global widget ID directly
            if (window.WIDGET_ID) {
                config.widgetId = window.WIDGET_ID;
                log('RECOVERY: Set widget ID from global variable:', config.widgetId);
            } else {
                log('CRITICAL: Unable to recover widget ID');
                return;
            }
        }
        
        // Disable send button during sending
        if (config.elements.sendButton) {
            config.elements.sendButton.disabled = true;
        }
        
        // Prepare data with explicit widget_id
        var data = {
            widget_id: config.widgetId,
            message: message,
            url: window.location.href,
            referrer: document.referrer,
            user_agent: navigator.userAgent,
            visitor_id: config.visitorId  // Include visitor ID as well
        };
        
        // Log exact data being sent
        log('DEBUG: Sending data:', JSON.stringify(data));
        
        // Construct the full URL
        var apiUrl = config.baseUrl + '/widget/api.php?action=send_message';
        log('DEBUG: Sending to URL:', apiUrl);
        
        // Send request
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(function(response) {
            // Re-enable send button
            if (config.elements.sendButton) {
                config.elements.sendButton.disabled = false;
            }
            
            // Log raw response for debugging
            log('DEBUG: Received response with status:', response.status);
            
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(function(responseData) {
            log('Server response:', responseData);
            
            if (responseData.success) {
                log('Message sent successfully');
                
                // If there's an auto-reply, add it
                if (responseData.reply) {
                    setTimeout(function() {
                        addMessage('agent', responseData.reply);
                        storeMessage('agent', responseData.reply);
                    }, 1000);
                }
                
                // Update last message check time
                lastMessageCheck = Date.now();
            } else {
                log('Error from server:', responseData.error);
                addMessage('system', 'Error sending message: ' + (responseData.error || 'Unknown error'));
            }
        })
        .catch(function(error) {
            // Re-enable send button
            if (config.elements.sendButton) {
                config.elements.sendButton.disabled = false;
            }
            
            log('Error sending message:', error);
            addMessage('system', 'Connection error. Please try again later.');
        });
    }
    
    // Register visitor with the server
    function registerVisitor() {
        log('Registering visitor');
        
        var data = {
            widget_id: config.widgetId,
            url: window.location.href,
            referrer: document.referrer,
            user_agent: navigator.userAgent,
            visitor_id: config.visitorId
        };
        
        fetch(config.baseUrl + '/widget/api.php?action=register_visitor', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            log('Visitor registration response:', data);
            
            if (data.success) {
                log('Visitor registered successfully');
            } else {
                log('Error registering visitor:', data.error);
            }
        })
        .catch(function(error) {
            log('Error registering visitor:', error);
        });
    }
    
    // Initialize widget
    function init() {
        log('Initializing widget');
        
        // Verify WIDGET_ID is available globally
        if (!window.WIDGET_ID) {
            console.error('LiveSupport Widget: WIDGET_ID not defined. Please set WIDGET_ID before loading embed.js');
            return;
        }
        
        // Ensure widget ID is set correctly in config
        config.widgetId = window.WIDGET_ID;
        log('Using widget ID:', config.widgetId);
        
        // Add widget ID to the initialization info in local storage
        try {
            localStorage.setItem('livesupport_widget_id', config.widgetId);
        } catch (e) {
            log('Error saving widget ID to localStorage:', e);
        }
        
        // Load styles
        loadStyles();
        
        // Create widget
        createWidget();
        
        log('Widget initialized successfully');
    }
    
    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();