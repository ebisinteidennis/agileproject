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
        baseUrl: 'https://agileproject.site', // Default URL, will be updated from server
        visitorId: getOrCreateVisitorId(),
        messages: [],
        theme: 'light',
        position: 'bottom-right',
        primaryColor: '#4a6cf7'
    };
    
    // Debug mode - set to true to enable detailed logging
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
    
    // Format API URL to ensure proper structure
    function formatApiUrl(baseUrl, endpoint) {
        // Remove trailing slash from baseUrl if present
        if (baseUrl.endsWith('/')) {
            baseUrl = baseUrl.slice(0, -1);
        }
        
        // Add leading slash to endpoint if not present
        if (!endpoint.startsWith('/')) {
            endpoint = '/' + endpoint;
        }
        
        return baseUrl + endpoint;
    }
    
    // Load widget styles
    function loadStyles() {
        var style = document.createElement('style');
        style.textContent = `
            @keyframes livesupport-fade-in {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            @keyframes livesupport-pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            .livesupport-widget {
                position: fixed;
                z-index: 999999;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', sans-serif;
                font-size: 14px;
                line-height: 1.4;
                box-sizing: border-box;
            }
            
            .livesupport-widget *,
            .livesupport-widget *:before,
            .livesupport-widget *:after {
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
            
            .livesupport-widget.top-right {
                top: 20px;
                right: 20px;
            }
            
            .livesupport-widget.top-left {
                top: 20px;
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
                animation: livesupport-pulse 2s infinite ease-in-out;
            }
            
            .livesupport-button:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 25px rgba(0, 0, 0, 0.25);
            }
            
            .livesupport-chat-window {
                position: absolute;
                bottom: 80px;
                right: 0;
                width: 350px;
                height: 480px;
                background-color: white;
                border-radius: 16px;
                box-shadow: 0 10px 50px rgba(0, 0, 0, 0.2);
                display: none;
                flex-direction: column;
                overflow: hidden;
                transition: all 0.3s ease;
                animation: livesupport-fade-in 0.3s ease-out;
                border: 1px solid rgba(0, 0, 0, 0.1);
            }
            
            .livesupport-header {
                background-color: ${config.primaryColor};
                color: white;
                padding: 18px 20px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            }
            
            .livesupport-header-title {
                font-weight: 600;
                font-size: 16px;
            }
            
            .livesupport-header-close {
                cursor: pointer;
                font-size: 24px;
                line-height: 0.8;
                padding: 5px;
                transition: all 0.2s ease;
                border-radius: 50%;
            }
            
            .livesupport-header-close:hover {
                background-color: rgba(255, 255, 255, 0.2);
            }
            
            .livesupport-messages {
                flex: 1;
                padding: 20px;
                overflow-y: auto;
                background-color: #f8f9fb;
                scroll-behavior: smooth;
            }
            
            .livesupport-messages::-webkit-scrollbar {
                width: 8px;
            }
            
            .livesupport-messages::-webkit-scrollbar-track {
                background: rgba(0, 0, 0, 0.05);
                border-radius: 4px;
            }
            
            .livesupport-messages::-webkit-scrollbar-thumb {
                background: rgba(0, 0, 0, 0.2);
                border-radius: 4px;
            }
            
            .livesupport-message {
                margin-bottom: 16px;
                display: flex;
                flex-direction: column;
                animation: livesupport-fade-in 0.3s ease-out;
            }
            
            .livesupport-message-bubble {
                padding: 12px 16px;
                border-radius: 18px;
                max-width: 85%;
                word-wrap: break-word;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
                line-height: 1.5;
            }
            
            .livesupport-user .livesupport-message-bubble {
                background-color: ${config.primaryColor};
                color: white;
                margin-left: auto;
                border-bottom-right-radius: 6px;
            }
            
            .livesupport-agent .livesupport-message-bubble {
                background-color: white;
                color: #333;
                margin-right: auto;
                border-bottom-left-radius: 6px;
                border: 1px solid rgba(0, 0, 0, 0.08);
            }
            
            .livesupport-system {
                text-align: center;
                margin: 14px 0;
                color: #666;
                font-size: 12px;
                background-color: rgba(0, 0, 0, 0.05);
                padding: 8px 12px;
                border-radius: 12px;
                width: fit-content;
                margin: 10px auto;
            }
            
            .livesupport-message-time {
                font-size: 10px;
                margin-top: 4px;
                opacity: 0.7;
                align-self: flex-end;
            }
            
            .livesupport-input-container {
                padding: 15px;
                border-top: 1px solid rgba(0, 0, 0, 0.08);
                background-color: white;
                display: flex;
                align-items: flex-end;
            }
            
            .livesupport-input-wrapper {
                flex: 1;
                position: relative;
                border: 1px solid #ddd;
                border-radius: 24px;
                background-color: #f8f9fb;
                transition: all 0.2s ease;
                overflow: hidden;
            }
            
            .livesupport-input-wrapper:focus-within {
                border-color: ${config.primaryColor};
                box-shadow: 0 0 0 2px rgba(74, 108, 247, 0.2);
            }
            
            .livesupport-input {
                width: 100%;
                border: none;
                outline: none;
                resize: none;
                padding: 12px 16px;
                max-height: 120px;
                background-color: transparent;
                font-family: inherit;
                font-size: 14px;
                line-height: 1.4;
            }
            
            .livesupport-send {
                background-color: ${config.primaryColor};
                color: white;
                border: none;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                margin-left: 12px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
                transition: all 0.2s ease;
            }
            
            .livesupport-send:hover {
                background-color: #6C8EFF;
                transform: scale(1.05);
            }
            
            .livesupport-send:disabled {
                opacity: 0.5;
                cursor: not-allowed;
                transform: scale(1);
            }
            
            .livesupport-notification {
                position: absolute;
                top: -8px;
                right: -8px;
                background-color: #ff4136;
                color: white;
                border-radius: 50%;
                min-width: 20px;
                height: 20px;
                font-size: 12px;
                display: none;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                padding: 2px 6px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
                animation: livesupport-fade-in 0.3s ease-out;
            }
            
            .livesupport-branding {
                text-align: center;
                padding: 8px;
                font-size: 11px;
                color: #999;
                background-color: #f8f9fb;
                border-top: 1px solid rgba(0, 0, 0, 0.05);
            }
            
            .livesupport-branding a {
                color: ${config.primaryColor};
                text-decoration: none;
            }
            
            /* Responsive Adjustments */
            @media (max-width: 480px) {
                .livesupport-chat-window {
                    width: 300px;
                    height: 450px;
                    bottom: 70px;
                }
                
                .livesupport-button {
                    width: 50px;
                    height: 50px;
                }
            }
            
            /* Mobile Full Screen Mode */
            @media (max-width: 380px) {
                .livesupport-chat-window.mobile-fullscreen {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    width: 100%;
                    height: 100%;
                    border-radius: 0;
                    z-index: 1000000;
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
        
        // Create greeting message
        var greetingMessage = document.createElement('div');
        greetingMessage.className = 'livesupport-system';
        greetingMessage.textContent = config.greetingMessage || 'Hi there! How can we help you today?';
        messagesContainer.appendChild(greetingMessage);
        
        // Create input container
        var inputContainer = document.createElement('div');
        inputContainer.className = 'livesupport-input-container';
        chatWindow.appendChild(inputContainer);
        
        // Create input wrapper
        var inputWrapper = document.createElement('div');
        inputWrapper.className = 'livesupport-input-wrapper';
        inputContainer.appendChild(inputWrapper);
        
        // Create input field
        var input = document.createElement('textarea');
        input.className = 'livesupport-input';
        input.placeholder = 'Type a message...';
        input.rows = 1;
        inputWrapper.appendChild(input);
        
        // Create send button
        var sendButton = document.createElement('button');
        sendButton.className = 'livesupport-send';
        sendButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
        inputContainer.appendChild(sendButton);
        
        // Create branding footer
        var branding = document.createElement('div');
        branding.className = 'livesupport-branding';
        branding.innerHTML = 'Powered by <a href="' + config.baseUrl + '" target="_blank">LiveSupport</a>';
        chatWindow.appendChild(branding);
        
        // Add event listeners
        button.addEventListener('click', function() {
            if (chatWindow.style.display === 'flex') {
                chatWindow.style.display = 'none';
            } else {
                chatWindow.style.display = 'flex';
                // Load messages when opened
                loadMessages();
                // Focus input
                setTimeout(function() {
                    input.focus();
                }, 300);
                // Clear notification
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
                input.style.height = Math.min(input.scrollHeight, 120) + 'px';
            }, 0);
        });
        
        input.addEventListener('input', function() {
            // Enable/disable send button based on input
            sendButton.disabled = input.value.trim() === '';
            
            // Auto-resize textarea
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 120) + 'px';
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
        
        // Add mobile fullscreen class on small screens
        if (window.innerWidth <= 380) {
            chatWindow.classList.add('mobile-fullscreen');
        }
        
        return container;
    }
    
    // Send message
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
        
        var apiUrl = formatApiUrl(config.baseUrl, '/widget/api.php') + 
            '?action=get_messages&widget_id=' + encodeURIComponent(config.widgetId) + 
            '&visitor_id=' + encodeURIComponent(config.visitorId) + 
            '&since=' + lastMessageCheck;
        
        log('Checking for new messages at URL:', apiUrl);
        
        fetch(apiUrl)
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
    
    // Send message to server via form submission
    function sendMessageToServer(message) {
        log('Sending message via form submission:', message);
        
        // Disable send button during sending
        if (config.elements.sendButton) {
            config.elements.sendButton.disabled = true;
        }
        
        // Create a simple form element
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = formatApiUrl(config.baseUrl, '/widget/api.php?action=send_message');
        form.style.display = 'none';
        
        // Add message data as hidden fields
        var fields = {
            'widget_id': config.widgetId,
            'message': message,
            'visitor_id': config.visitorId,
            'url': window.location.href,
            'user_agent': navigator.userAgent
        };
        
        // Create hidden fields for each data item
        for (var key in fields) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        }
        
        // Create hidden iframe as target to prevent page navigation
        var iframe = document.createElement('iframe');
        iframe.name = 'send_message_frame_' + new Date().getTime();
        iframe.style.display = 'none';
        document.body.appendChild(iframe);
        
        // Set form target to the iframe
        form.target = iframe.name;
        document.body.appendChild(form);
        
        // Process response when iframe loads
        iframe.onload = function() {
            try {
                // Get response from iframe
                var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                var response = iframeDoc.body.innerHTML;
                log('Raw response:', response);
                
                // Try to parse JSON response
                var responseData = JSON.parse(response);
                log('Parsed response:', responseData);
                
                // Handle successful message
                if (responseData.success) {
                    log('Message sent successfully');
                    
                    // If there's an auto-reply, add it
                    if (responseData.reply) {
                        setTimeout(function() {
                            addMessage('agent', responseData.reply, null, responseData.reply_id);
                            storeMessage('agent', responseData.reply, responseData.created_at, responseData.reply_id);
                        }, 1000);
                    }
                    
                    // Update last message check time
                    lastMessageCheck = Date.now();
                } else {
                    // Handle error from server
                    log('Error from server:', responseData.error);
                    addMessage('system', 'Error: ' + (responseData.error || 'Unknown error'));
                }
            } catch (e) {
                // Handle parsing errors
                log('Error processing response:', e);
                // Check if we got any response at all
                var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                if (iframeDoc && iframeDoc.body && iframeDoc.body.innerHTML) {
                    log('Non-JSON response received:', iframeDoc.body.innerHTML);
                    
                    // If we got any response, consider it a success
                    if (iframeDoc.body.innerHTML.length > 0) {
                        log('Received non-empty response, assuming success');
                        lastMessageCheck = Date.now();
                    } else {
                        addMessage('system', 'Error: Could not process server response');
                    }
                } else {
                    addMessage('system', 'Error: No response from server');
                }
            }
            
            // Re-enable send button
            if (config.elements.sendButton) {
                config.elements.sendButton.disabled = false;
            }
            
            // Clean up
            setTimeout(function() {
                document.body.removeChild(form);
                document.body.removeChild(iframe);
            }, 100);
        };
        
        // Handle iframe errors
        iframe.onerror = function() {
            log('Iframe error');
            addMessage('system', 'Connection error. Please try again later.');
            
            // Re-enable send button
            if (config.elements.sendButton) {
                config.elements.sendButton.disabled = false;
            }
            
            // Clean up
            document.body.removeChild(form);
            document.body.removeChild(iframe);
        };
        
        // Set a timeout in case the iframe never loads
        setTimeout(function() {
            if (document.body.contains(iframe)) {
                log('Request timeout');
                addMessage('system', 'Request timed out. Please try again later.');
                
                // Re-enable send button
                if (config.elements.sendButton) {
                    config.elements.sendButton.disabled = false;
                }
                
                // Clean up
                if (document.body.contains(form)) document.body.removeChild(form);
                if (document.body.contains(iframe)) document.body.removeChild(iframe);
            }
        }, 15000); // 15 second timeout
        
        // Submit the form
        log('Submitting form');
        form.submit();
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
        
        var apiUrl = formatApiUrl(config.baseUrl, '/widget/api.php?action=register_visitor');
        
        log('Registering visitor at URL:', apiUrl);
        
        fetch(apiUrl, {
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
    function initializeWidget() {
        try {
            // Get widget configuration first
            var configUrl = formatApiUrl('https://agileproject.site', '/widget/api.php?action=get_config&widget_id=' + WIDGET_ID);
            
            log('Fetching widget configuration from:', configUrl);
            
            fetch(configUrl)
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Failed to load widget configuration: ' + response.status);
                    }
                    return response.json();
                })
                .then(function(data) {
                    log('Widget config response:', data);
                    
                    if (data.success && data.config) {
                        // Update the site URL if provided
                        if (data.config.siteUrl) {
                            config.baseUrl = data.config.siteUrl;
                            log('Updated baseUrl to:', config.baseUrl);
                        }
                        
                        // Update other config options
                        if (data.config.primaryColor) {
                            config.primaryColor = data.config.primaryColor;
                        }
                        
                        if (data.config.position) {
                            config.position = data.config.position;
                        }
                        
                        if (data.config.greetingMessage) {
                            config.greetingMessage = data.config.greetingMessage;
                        }
                        
                        // Continue with widget initialization
                        loadStyles();
                        createWidget();
                        
                        // Register visitor
                        registerVisitor();
                    } else {
                        log('Invalid configuration response', data);
                        // Initialize with defaults as fallback
                        loadStyles();
                        createWidget();
                        registerVisitor();
                    }
                })
                .catch(function(error) {
                    log('Error loading configuration:', error);
                    // Initialize with defaults
                    loadStyles();
                    createWidget();
                    registerVisitor();
                });
        } catch (err) {
            console.error('LiveSupport Widget: Initialization failed:', err);
            // Fallback to default initialization
            loadStyles();
            createWidget();
            registerVisitor();
        }
    }
    
    // Ensure the widget is initialized when the page is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initializeWidget, 1000);
        });
    } else {
        setTimeout(initializeWidget, 1000);
    }
    
    // Add fallback initialization
    window.addEventListener('load', function() {
        if (!document.querySelector('.livesupport-widget')) {
            initializeWidget();
        }
    });
})();