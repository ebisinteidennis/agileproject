/**
 * Chat Widget JavaScript
 * This script handles the frontend functionality of the agent/admin chat interface
 */
document.addEventListener('DOMContentLoaded', function() {
    // Chat elements
    const chatContainer = document.querySelector('.chat-container');
    const chatMessages = document.querySelector('.chat-messages');
    const messageInput = document.querySelector('.chat-input');
    const sendButton = document.querySelector('.send-button');
    const visitorInfo = document.querySelector('.visitor-info');
    
    // Variables
    let currentVisitorId = null;
    let lastMessageId = 0;
    let polling = null;
    
    // Initialize chat
    function initChat(visitorId) {
        currentVisitorId = visitorId;
        lastMessageId = 0;
        
        // Clear messages
        if (chatMessages) {
            chatMessages.innerHTML = '';
        }
        
        // Load conversation
        loadConversation(visitorId);
        
        // Start polling for new messages
        if (polling) {
            clearInterval(polling);
        }
        
        polling = setInterval(() => {
            fetchNewMessages(visitorId);
        }, 3000);
        
        // Update visitor status
        updateVisitorStatus(visitorId);
    }
    
    // Load conversation
    function loadConversation(visitorId) {
        fetch(`${SITE_URL}/account/get-messages.php?visitor_id=${visitorId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Display messages
                data.messages.forEach(message => {
                    addMessageToChat(message);
                });
                
                // Scroll to bottom
                scrollToBottom();
                
                // Update visitor info
                if (data.visitor && visitorInfo) {
                    visitorInfo.innerHTML = `
                        <div class="visitor-name">${data.visitor.name || 'Anonymous Visitor'}</div>
                        ${data.visitor.email ? `<div class="visitor-email">${data.visitor.email}</div>` : ''}
                        ${data.visitor.url ? `<div class="visitor-page">Current page: <a href="${data.visitor.url}" target="_blank">${new URL(data.visitor.url).pathname}</a></div>` : ''}
                    `;
                }
                
                // Update last message ID
                if (data.messages.length > 0) {
                    lastMessageId = Math.max(...data.messages.map(m => m.id));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    
    // Fetch new messages
    function fetchNewMessages(visitorId) {
        fetch(`${SITE_URL}/account/get-messages.php?visitor_id=${visitorId}&last_id=${lastMessageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages.length > 0) {
                // Display new messages
                data.messages.forEach(message => {
                    addMessageToChat(message);
                });
                
                // Scroll to bottom
                scrollToBottom();
                
                // Update last message ID
                lastMessageId = Math.max(...data.messages.map(m => m.id));
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    
    // Add message to chat
    function addMessageToChat(message) {
        if (!chatMessages) return;
        
        const messageElement = document.createElement('div');
        messageElement.className = `chat-message ${message.sender_type}`;
        
        const messageContent = document.createElement('div');
        messageContent.className = 'message-content';
        messageContent.textContent = message.message;
        
        const messageTime = document.createElement('div');
        messageTime.className = 'message-time';
        
        // Format timestamp
        const date = new Date(message.created_at);
        messageTime.textContent = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        messageElement.appendChild(messageContent);
        messageElement.appendChild(messageTime);
        chatMessages.appendChild(messageElement);
    }
    
    // Send message
    function sendMessage() {
        if (!messageInput || !currentVisitorId) return;
        
        const message = messageInput.value.trim();
        if (!message) return;
        
        // Create form data
        const formData = new FormData();
        formData.append('visitor_id', currentVisitorId);
        formData.append('message', message);
        
        // Send to server
        fetch(`${SITE_URL}/account/send-message.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add message to chat
                addMessageToChat({
                    message: message,
                    sender_type: 'agent',
                    created_at: new Date().toISOString()
                });
                
                // Clear input
                messageInput.value = '';
                
                // Scroll to bottom
                scrollToBottom();
                
                // Update last message ID
                lastMessageId = data.message_id;
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    
    // Update visitor status
    function updateVisitorStatus(visitorId) {
        fetch(`${SITE_URL}/account/update-visitor-status.php?visitor_id=${visitorId}`)
        .then(response => response.json())
        .then(data => {
            // Update visitor status in UI if needed
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    
    // Scroll to bottom
    function scrollToBottom() {
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }
    
    // Event listeners
    if (sendButton) {
        sendButton.addEventListener('click', sendMessage);
    }
    
    if (messageInput) {
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }
    
    // Initialize chat if visitor ID is provided
    const visitorLinks = document.querySelectorAll('.visitor-chat-link');
    if (visitorLinks.length > 0) {
        visitorLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const visitorId = this.getAttribute('data-visitor-id');
                if (visitorId) {
                    initChat(visitorId);
                    
                    // Show chat container
                    if (chatContainer) {
                        chatContainer.classList.add('open');
                    }
                }
            });
        });
    }
    
    // Close chat
    const closeChat = document.querySelector('.chat-close');
    if (closeChat) {
        closeChat.addEventListener('click', function() {
            if (chatContainer) {
                chatContainer.classList.remove('open');
            }
            
            // Stop polling
            if (polling) {
                clearInterval(polling);
                polling = null;
            }
        });
    }
    
    // Auto-initialize chat if URL has visitor parameter
    const urlParams = new URLSearchParams(window.location.search);
    const visitorParam = urlParams.get('visitor');
    if (visitorParam) {
        initChat(visitorParam);
        
        // Show chat container
        if (chatContainer) {
            chatContainer.classList.add('open');
        }
    }
});