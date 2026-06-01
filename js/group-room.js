/**
 * Group Chat JavaScript
 * Handles all frontend interactions for group chat functionality
 * Like Messenger-style private group chat
 */

let currentGroupId = new URLSearchParams(window.location.search).get('group_id');
let currentUserId = document.body.getAttribute('data-user-id');
let pollTimer = null;

document.addEventListener('DOMContentLoaded', function() {
    setupSidebarToggle();
    setupThemeToggle();
    setupCreateGroupModal();
    setupMessageForm();
    setupMembersModal();
    setupAddMemberModal();
    setupDeleteGroup();
    
    scrollToBottom();
    
    if (currentGroupId) {
        pollTimer = setInterval(pollNewMessages, 3000);
    }
});

function setupSidebarToggle() {
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    function openSidebar() {
        sidebar.classList.add('active');
        sidebarOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeSidebar() {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    if (menuToggle) menuToggle.addEventListener('click', openSidebar);
    if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);
}

function setupThemeToggle() {
    const themeToggle = document.getElementById('theme-toggle');
    if (!themeToggle) return;
    
    themeToggle.addEventListener('change', function() {
        if (this.checked) {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
            localStorage.setItem('theme', 'light');
        }
    });
}

function setupCreateGroupModal() {
    const createGroupBtn = document.getElementById('btnCreateGroup');
    const createGroupEmptyBtn = document.getElementById('btnCreateGroupEmpty');
    const createGroupModal = document.getElementById('createGroupModal');
    const cancelBtns = document.querySelectorAll('#btnCancelCreateGroup, #btnCancelCreateGroupFooter');
    const createGroupForm = document.getElementById('createGroupForm');
    const usersList = document.getElementById('usersList');
    const selectedCount = document.getElementById('selectedCount');
    
    function updateSelectedCount() {
        const checked = document.querySelectorAll('#usersList input[name="members"]:checked').length;
        if (selectedCount) selectedCount.textContent = checked + ' selected';
    }
    
    if (usersList) {
        usersList.addEventListener('change', function(e) {
            if (e.target.matches('input[name="members"]')) {
                updateSelectedCount();
            }
        });
        
        usersList.addEventListener('click', function(e) {
            const label = e.target.closest('.user-item');
            if (label) {
                const cb = label.querySelector('input[name="members"]');
                if (cb) {
                    cb.checked = !cb.checked;
                    updateSelectedCount();
                }
            }
        });
    }
    
    function openCreateGroup() {
        if (createGroupModal) {
            createGroupModal.classList.add('active');
            updateSelectedCount();
        }
    }
    
    function closeCreateGroup() {
        createGroupModal.classList.remove('active');
        if (createGroupForm) createGroupForm.reset();
        if (selectedCount) selectedCount.textContent = '0 selected';
    }
    
    if (createGroupBtn) createGroupBtn.addEventListener('click', openCreateGroup);
    if (createGroupEmptyBtn) createGroupEmptyBtn.addEventListener('click', openCreateGroup);
    
    cancelBtns.forEach(btn => {
        if (btn) btn.addEventListener('click', closeCreateGroup);
    });
    
    if (createGroupForm) {
        createGroupForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitCreateGroup();
        });
    }
    
    if (createGroupModal) {
        createGroupModal.addEventListener('click', function(e) {
            if (e.target === this) closeCreateGroup();
        });
    }
}

function submitCreateGroup() {
    const groupName = document.getElementById('groupName').value.trim();
    const checkboxes = document.querySelectorAll('input[name="members"]:checked');
    const memberIds = Array.from(checkboxes).map(cb => cb.value).join(',');
    
    if (!groupName) {
        alert('Please enter a group name');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'create_group');
    formData.append('name', groupName);
    if (memberIds) formData.append('member_ids', memberIds);
    
    fetch('../api/group-room.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('createGroupModal').classList.remove('active');
            document.getElementById('createGroupForm').reset();
            document.getElementById('selectedCount').textContent = '0 selected';
            window.location.href = '?group_id=' + data.group_id;
        } else {
            alert('Error: ' + (data.message || 'Failed to create group'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating group. Please try again.');
    });
}

function setupMessageForm() {
    const messageForm = document.getElementById('messageForm');
    if (!messageForm) return;
    
    const messageInput = document.getElementById('messageInput');
    
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });
    }
    
    messageForm.addEventListener('submit', function(e) {
        e.preventDefault();
        sendMessage();
    });
}

function sendMessage() {
    const messageInput = document.getElementById('messageInput');
    const messageText = messageInput.value.trim();
    
    if (!messageText || !currentGroupId) return;
    
    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('group_id', currentGroupId);
    formData.append('message', messageText);
    
    fetch('../api/group-room.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageInput.value = '';
            messageInput.style.height = 'auto';
            
            if (data.message_data) {
                addMessageToUI(data.message_data, true);
                scrollToBottom();
            }
        } else {
            alert('Error: ' + (data.message || 'Failed to send message'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error sending message');
    });
}

function pollNewMessages() {
    if (!currentGroupId) return;
    
    const messagesContainer = document.getElementById('messagesContainer');
    if (!messagesContainer) return;
    
    const existingMessageIds = new Set();
    messagesContainer.querySelectorAll('.message-item').forEach(el => {
        const id = el.getAttribute('data-message-id');
        if (id) existingMessageIds.add(id);
    });
    
    fetch(`../api/group-room.php?action=get_messages&group_id=${currentGroupId}&limit=10`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages) {
                const emptyState = messagesContainer.querySelector('.empty-state');
                if (emptyState) emptyState.remove();
                
                let hasNew = false;
                data.messages.forEach(message => {
                    if (!existingMessageIds.has(String(message.id))) {
                        const isOwn = String(message.sender_id) === String(currentUserId);
                        addMessageToUI(message, isOwn);
                        hasNew = true;
                    }
                });
                
                if (hasNew) {
                    const isAtBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight < 100;
                    if (isAtBottom) {
                        scrollToBottom();
                    }
                }
            }
        })
        .catch(error => console.error('Error polling messages:', error));
}

function addMessageToUI(message, isOwn) {
    const messagesContainer = document.getElementById('messagesContainer');
    if (!messagesContainer) return;
    
    const senderInitial = message.username ? message.username.charAt(0).toUpperCase() : '?';
    const profilePicHtml = message.profile_pic
        ? `<img src="../${escapeHtml(message.profile_pic)}" alt="">`
        : senderInitial;
    
    const messageHtml = `
        <div class="message-item ${isOwn ? 'own' : ''}" data-message-id="${message.id}">
            ${!isOwn ? `
                <div class="message-avatar" title="${escapeHtml(message.username)}">
                    ${profilePicHtml}
                </div>
            ` : ''}
            <div class="message-content">
                <div class="message-text">${escapeHtml(message.message_text)}</div>
                <div class="message-meta">
                    <span>${escapeHtml(message.username)}</span>
                    <span>${formatDate(message.sent_at)}</span>
                </div>
            </div>
        </div>
    `;
    
    messagesContainer.insertAdjacentHTML('beforeend', messageHtml);
}

function setupMembersModal() {
    const showMembersBtn = document.getElementById('btnShowMembers');
    const membersModal = document.getElementById('membersModal');
    const closeBtns = document.querySelectorAll('#btnCloseMembersModal, #btnCloseMembersModalFooter');
    
    if (!showMembersBtn) return;
    
    showMembersBtn.addEventListener('click', function() {
        if (membersModal) {
            membersModal.classList.add('active');
            loadMembers();
        }
    });
    
    closeBtns.forEach(btn => {
        if (btn) btn.addEventListener('click', function() {
            if (membersModal) membersModal.classList.remove('active');
        });
    });
    
    if (membersModal) {
        membersModal.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });
    }
}

function loadMembers() {
    if (!currentGroupId) return;
    
    fetch(`../api/group-room.php?action=get_members&group_id=${currentGroupId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderMembers(data.members);
            }
        })
        .catch(error => console.error('Error loading members:', error));
}

function renderMembers(members) {
    const membersList = document.getElementById('membersList');
    if (!membersList) return;

    const isCreator = members.some(m => String(m.user_id) === String(currentUserId) && String(m.is_creator) === '1');

    let html = '';
    members.forEach(member => {
        const memberIsCreator = String(member.is_creator) === '1';
        const initial = member.username ? member.username.charAt(0).toUpperCase() : '?';
        const profilePicHtml = member.profile_pic
            ? `<img src="../${escapeHtml(member.profile_pic)}" alt="">`
            : initial;

        html += `
            <div class="user-item">
                <div class="message-avatar" style="width: 32px; height: 32px; font-size: 13px;">
                    ${profilePicHtml}
                </div>
                <div class="user-item-name">
                    ${escapeHtml(member.username)}
                    ${memberIsCreator ? ' <span style="color: var(--clr-primary); font-size: 12px;">(Creator)</span>' : ''}
                </div>
                ${isCreator && !memberIsCreator ? `
                    <button class="btn-remove-member" data-user-id="${member.user_id}" style="background: none; border: none; color: #e74c3c; cursor: pointer; padding: 4px 8px; border-radius: 4px; font-size: 13px;" title="Remove member">
                        <i class="fas fa-times"></i>
                    </button>
                ` : ''}
            </div>
        `;
    });

    membersList.innerHTML = html;

    membersList.querySelectorAll('.btn-remove-member').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const userId = this.getAttribute('data-user-id');
            removeMember(userId);
        });
    });
}

function removeMember(userId) {
    if (!currentGroupId || !userId) return;
    if (!confirm('Remove this member from the group?')) return;

    const formData = new FormData();
    formData.append('action', 'remove_member');
    formData.append('group_id', currentGroupId);
    formData.append('user_id', userId);

    fetch('../api/group-room.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadMembers();
        } else {
            alert('Error: ' + (data.message || 'Failed to remove member'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error removing member');
    });
}

function setupAddMemberModal() {
    const addMemberBtn = document.getElementById('btnAddMember');
    const addMemberModal = document.getElementById('addMemberModal');
    const cancelBtns = document.querySelectorAll('#btnCancelAddMember, #btnCancelAddMemberFooter');
    const addMemberForm = document.getElementById('addMemberForm');
    
    if (!addMemberBtn) return;
    
    function closeAddMember() {
        addMemberModal.classList.remove('active');
        if (addMemberForm) addMemberForm.reset();
    }
    
    addMemberBtn.addEventListener('click', function() {
        if (addMemberModal) {
            addMemberModal.classList.add('active');
            loadAvailableUsers();
        }
    });
    
    cancelBtns.forEach(btn => {
        if (btn) btn.addEventListener('click', closeAddMember);
    });
    
    if (addMemberForm) {
        addMemberForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitAddMember();
        });
    }
    
    if (addMemberModal) {
        addMemberModal.addEventListener('click', function(e) {
            if (e.target === this) closeAddMember();
        });
    }
}

function loadAvailableUsers() {
    const userSelect = document.getElementById('userSelect');
    if (!userSelect) return;
    
    userSelect.innerHTML = '<option value="">Loading...</option>';
    
    fetch('../api/group-room.php?action=get_all_users')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.users) {
                let html = '<option value="">Select a user...</option>';
                data.users.forEach(user => {
                    html += `<option value="${user.id}">${escapeHtml(user.username)}</option>`;
                });
                userSelect.innerHTML = html;
            } else {
                userSelect.innerHTML = '<option value="">No users available</option>';
            }
        })
        .catch(error => {
            console.error('Error loading users:', error);
            userSelect.innerHTML = '<option value="">Error loading users</option>';
        });
}

function submitAddMember() {
    const userSelect = document.getElementById('userSelect');
    const userId = userSelect.value;
    
    if (!userId || !currentGroupId) {
        alert('Please select a user');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add_member');
    formData.append('group_id', currentGroupId);
    formData.append('user_id', userId);
    
    fetch('../api/group-room.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('addMemberModal').classList.remove('active');
            document.getElementById('addMemberForm').reset();
            loadMembers();
        } else {
            alert('Error: ' + (data.message || 'Failed to add member'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error adding member');
    });
}

function setupDeleteGroup() {
    const deleteGroupBtn = document.getElementById('btnDeleteGroup');
    if (!deleteGroupBtn) return;
    
    deleteGroupBtn.addEventListener('click', function() {
        if (!confirm('Are you sure you want to delete this group? This action cannot be undone.')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'delete_group');
        formData.append('group_id', currentGroupId);
        
        fetch('../api/group-room.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Group deleted successfully');
                window.location.href = 'group-room.php';
            } else {
                alert('Error: ' + (data.message || 'Failed to delete group'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting group');
        });
    });
}

function scrollToBottom() {
    const messagesContainer = document.getElementById('messagesContainer');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const today = new Date();
    
    if (date.toDateString() === today.toDateString()) {
        return date.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true 
        });
    } else {
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric',
            year: date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined
        });
    }
}
