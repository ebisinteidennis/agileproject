// LiveSupport Widget Embed Script (embed.js) - Fixed Version
(function() {
    'use strict';
    
    // Get widget ID from the global variable
    var WIDGET_ID = window.WIDGET_ID;
    
    if (!WIDGET_ID) {
        console.error('LiveSupport Widget: WIDGET_ID not defined');
        return;
    }
    
    // Configuration
    var config = {
        widgetId: WIDGET_ID,
        baseUrl: 'https://agileproject.site',
        visitorId: getOrCreateVisitorId(),
        messages: [],
        primaryColor: '#4a6cf7',
        position: 'bottom-right'
    };
    
    // Polling interval
    var messageCheckInterval = null;
    
    // Generate or retrieve visitor ID
    function getOrCreateVisitorId() {
        var visitorId = localStorage.getItem('ls_visitor_' + WIDGET_ID);
        if (!visitorId) {
            visitorId = 'visitor_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
            localStorage.setItem('ls_visitor_' + WIDGET_ID, visitorId);
        }
        return visitorId;
    }
    
    // Load styles
    function loadStyles() {
        var style = document.createElement('style');
        style.textContent = `
            .livesupport-widget {
                position: fixed;
                z-index: 999999;
                font-family: Arial, sans-serif;
            }
            
            .livesupport-widget.bottom-right {
                bottom: 20px;
                right: 20px;
            }
            
            .livesupport-button {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background-color: ${config.primaryColor};
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                color: white;
                transition: transform 0.3s;
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
                background: white;
                border-radius: 10px;
                box-shadow: 0 10px 50px rgba(0,0,0,0.2);
                display: none;
                flex-direction: column;
                overflow: hidden;
            }
            
            .livesupport-chat-window.active {
                display: flex;
            }
            
            .livesupport-header {
                background: ${config.primaryColor};
                color: white;
                padding: 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .livesupport-close {
                cursor: pointer;
                font-size: 20px;
            }
            
            .livesupport-messages {
                flex: 1;
                padding: 15px;
                overflow-y: auto;
                background: #f8f9fa;
            }
            
            .livesupport-message {
                margin-bottom: 10px;
                display: flex;
                flex-direction: column;
            }
            
            .livesupport-message.user {
                align-items: flex-end;
            }
            
            .livesupport-message.agent {
                align-items: flex-start;
            }
            
            .livesupport-bubble {
                max-width: 70%;
                padding: 10px 15px;
                border-radius: 15px;
                word-wrap: break-word;
            }
            
            .livesupport-message.user .livesupport-bubble {
                background: ${config.primaryColor};
                color: white;
                border-bottom-right-radius: 5px;
            }
            
            .livesupport-message.agent .livesupport-bubble {
                background: #e9ecef;
                color: #333;
                border-bottom-left-radius: 5px;
            }
            
            .livesupport-time {
                font-size: 11px;
                color: #666;
                margin-top: 5px;
            }
            
            .livesupport-input-container {
                padding: 10px;
                border-top: 1px solid #dee2e6;
                display: flex;
                gap: 10px;
            }
            
            .livesupport-input {
                flex: 1;
                border: 1px solid #ced4da;
                border-radius: 20px;
                padding: 8px 15px;
                outline: none;
            }
            
            .livesupport-send {
                background: ${config.primaryColor};
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
            
            .livesupport-send:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            
            .livesupport-notification {
                position: absolute;
                top: -5px;
                right: -5px;
                background: red;
                color: white;
                border-radius: 50%;
                min-width: 20px;
                height: 20px;
                font-size: 12px;
                display: none;
                align-items: center;
                justify-content: center;
            }
            
            .livesupport-status {
                text-align: center;
                padding: 5px;
                font-size: 12px;
                color: #666;
            }
        `;
        document.head.appendChild(style);
    }
    
    // Create widget UI
    function createWidget() {
        var container = document.createElement('div');
        container.className = 'livesupport-widget ' + config.position;
        
        // Create toggle button
        var button = document.createElement('div');
        button.className = 'livesupport-button';
        button.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><div class="livesupport-notification">0</div>';
        
        // Create chat window
        var chatWindow = document.createElement('div');
        chatWindow.className = 'livesupport-chat-window';
        chatWindow.innerHTML = `
            <div class="livesupport-header">
                <div>Live Support</div>
                <div class="livesupport-close">&times;</div>
            </div>
            <div class="livesupport-messages">
                <div class="livesupport-status">Welcome! How can we help you?</div>
            </div>
            <div class="livesupport-input-container">
                <input type="text" class="livesupport-input" placeholder="Type a message...">
                <button class="livesupport-send">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </button>
            </div>
        `;
        
        container.appendChild(button);
        container.appendChild(chatWindow);
        document.body.appendChild(container);
        
        // Store elements
        config.elements = {
            button: button,
            chatWindow: chatWindow,
            messagesContainer: chatWindow.querySelector('.livesupport-messages'),
            input: chatWindow.querySelector('.livesupport-input'),
            sendButton: chatWindow.querySelector('.livesupport-send'),
            notification: button.querySelector('.livesupport-notification')
        };
        
        // Add event listeners
        button.addEventListener('click', toggleChat);
        chatWindow.querySelector('.livesupport-close').addEventListener('click', closeChat);
        config.elements.input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendMessage();
        });
        config.elements.sendButton.addEventListener('click', sendMessage);
    }
    
    // Toggle chat window
    function toggleChat() {
        var isOpen = config._isOpen = !config._isOpen;
        config.elements.chatWindow.classList.toggle('active', isOpen);
        
        if (isOpen) {
            loadChatHistory();
            config.elements.input.focus();
            resetNotification();
            startPolling();
        } else {
            stopPolling();
        }
    }
    
    // Close chat
    function closeChat() {
        config._isOpen = false;
        config.elements.chatWindow.classList.remove('active');
        stopPolling();
    }
    
    // Reset notification
    function resetNotification() {
        config.elements.notification.style.display = 'none';
        config.elements.notification.textContent = '0';
    }
    
    // Load chat history from localStorage
    function loadChatHistory() {
        try {
            var saved = localStorage.getItem('ls_messages_' + config.widgetId);
            if (saved) {
                config.messages = JSON.parse(saved);
                displayMessages();
            }
        } catch (e) {
            console.error('Error loading chat history:', e);
            config.messages = [];
        }
    }
    
    // Save chat history to localStorage
    function saveChatHistory() {
        try {
            localStorage.setItem('ls_messages_' + config.widgetId, JSON.stringify(config.messages));
        } catch (e) {
            console.error('Error saving chat history:', e);
        }
    }
    
    // Display all messages
    function displayMessages() {
        config.elements.messagesContainer.innerHTML = '';
        
        if (config.messages.length === 0) {
            config.elements.messagesContainer.innerHTML = '<div class="livesupport-status">Welcome! How can we help you?</div>';
        } else {
            config.messages.forEach(function(msg) {
                addMessageToUI(msg);
            });
        }
        
        scrollToBottom();
    }
    
    // Add message to UI
    function addMessageToUI(msg) {
        var messageEl = document.createElement('div');
        messageEl.className = 'livesupport-message ' + msg.type;
        
        var bubble = document.createElement('div');
        bubble.className = 'livesupport-bubble';
        bubble.textContent = msg.message;
        
        var time = document.createElement('div');
        time.className = 'livesupport-time';
        time.textContent = formatTime(new Date(msg.timestamp));
        
        messageEl.appendChild(bubble);
        messageEl.appendChild(time);
        
        config.elements.messagesContainer.appendChild(messageEl);
    }
    
    // Format time
    function formatTime(date) {
        var hours = date.getHours();
        var minutes = date.getMinutes();
        var ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        return hours + ':' + minutes + ' ' + ampm;
    }
    
    // Scroll to bottom
    function scrollToBottom() {
        config.elements.messagesContainer.scrollTop = config.elements.messagesContainer.scrollHeight;
    }
    
    // Send message
    function sendMessage() {
        var message = config.elements.input.value.trim();
        if (!message) return;
        
        // Add message to UI immediately
        var msg = {
            id: Date.now(),
            type: 'user',
            message: message,
            timestamp: new Date().toISOString()
        };
        
        config.messages.push(msg);
        addMessageToUI(msg);
        saveChatHistory();
        
        // Clear input
        config.elements.input.value = '';
        scrollToBottom();
        
        // Send to server
        sendToServer(message);
    }
    
    // Send message to server
    function sendToServer(message) {
        config.elements.sendButton.disabled = true;
        
        var formData = new FormData();
        formData.append('widget_id', config.widgetId);
        formData.append('visitor_id', config.visitorId);
        formData.append('message', message);
        formData.append('url', window.location.href);
        formData.append('user_agent', navigator.userAgent);
        
        fetch(config.baseUrl + '/widget/api.php?action=send_message', {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                // Update visitor ID if provided
                if (data.visitor_id) {
                    config.visitorId = data.visitor_id;
                    localStorage.setItem('ls_visitor_' + config.widgetId, config.visitorId);
                }
                
                // Check for immediate response
                checkForNewMessages();
            } else {
                console.error('Error:', data.error);
            }
        })
        .catch(function(error) {
            console.error('Connection error:', error);
            addSystemMessage('Connection error. Please check your internet connection.');
        })
        .finally(function() {
            config.elements.sendButton.disabled = false;
        });
    }
    
    // Add system message
    function addSystemMessage(text) {
        var msg = {
            id: Date.now(),
            type: 'agent',
            message: text,
            timestamp: new Date().toISOString()
        };
        
        config.messages.push(msg);
        addMessageToUI(msg);
        saveChatHistory();
        scrollToBottom();
    }
    
    // Check for new messages
    function checkForNewMessages() {
        var url = config.baseUrl + '/widget/api.php?action=get_messages';
        url += '&widget_id=' + encodeURIComponent(config.widgetId);
        url += '&visitor_id=' + encodeURIComponent(config.visitorId);
        
        fetch(url)
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.messages) {
                processNewMessages(data.messages);
            }
        })
        .catch(function(error) {
            console.error('Error checking messages:', error);
        });
    }
    
    // Process new messages
    function processNewMessages(messages) {
        var hasNewMessages = false;
        
        messages.forEach(function(msg) {
            // Check if we already have this message
            var exists = config.messages.some(function(m) {
                return m.id === msg.id || (m.message === msg.message && m.timestamp === msg.created_at);
            });
            
            if (!exists && msg.sender_type === 'agent') {
                var newMsg = {
                    id: msg.id,
                    type: 'agent',
                    message: msg.message,
                    timestamp: msg.created_at
                };
                
                config.messages.push(newMsg);
                
                if (config._isOpen) {
                    addMessageToUI(newMsg);
                    scrollToBottom();
                } else {
                    hasNewMessages = true;
                }
            }
        });
        
        if (hasNewMessages) {
            // Update notification
            var count = parseInt(config.elements.notification.textContent) || 0;
            config.elements.notification.textContent = count + 1;
            config.elements.notification.style.display = 'flex';
        }
        
        saveChatHistory();
    }
    
    // Start polling
    function startPolling() {
        stopPolling();
        checkForNewMessages();
        messageCheckInterval = setInterval(checkForNewMessages, 3000);
    }
    
    // Stop polling
    function stopPolling() {
        if (messageCheckInterval) {
            clearInterval(messageCheckInterval);
            messageCheckInterval = null;
        }
    }
    
    // Initialize widget
    function initialize() {
        loadStyles();
        createWidget();
        
        // Register visitor
        fetch(config.baseUrl + '/widget/api.php?action=register_visitor', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                widget_id: config.widgetId,
                visitor_id: config.visitorId,
                url: window.location.href,
                user_agent: navigator.userAgent
            })
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.visitor_id) {
                config.visitorId = data.visitor_id;
                localStorage.setItem('ls_visitor_' + config.widgetId, config.visitorId);
            }
        })
        .catch(function(error) {
            console.error('Registration error:', error);
        });
    }
    
    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();