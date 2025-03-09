document.addEventListener('DOMContentLoaded', function() {
    // User menu toggle with keyboard support
    const userMenuButton = document.getElementById('user-menu-button');
    const userMenu = document.getElementById('user-menu');
    
    if (userMenuButton && userMenu) {
        userMenuButton.addEventListener('click', function() {
            const expanded = userMenu.classList.contains('hidden') ? false : true;
            userMenu.classList.toggle('hidden');
            userMenuButton.setAttribute('aria-expanded', !expanded);
        });
        
        // Close menu on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !userMenu.classList.contains('hidden')) {
                userMenu.classList.add('hidden');
                userMenuButton.setAttribute('aria-expanded', 'false');
                userMenuButton.focus();
            }
        });
        
        // Close menu on outside click
        document.addEventListener('click', function(event) {
            if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.add('hidden');
                userMenuButton.setAttribute('aria-expanded', 'false');
            }
        });
        
        // Add keyboard navigation inside menu
        userMenu.addEventListener('keydown', function(event) {
            const menuItems = userMenu.querySelectorAll('[role="menuitem"]');
            const currentIndex = Array.from(menuItems).indexOf(document.activeElement);
            
            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    if (currentIndex < menuItems.length - 1) {
                        menuItems[currentIndex + 1].focus();
                    }
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    if (currentIndex > 0) {
                        menuItems[currentIndex - 1].focus();
                    }
                    break;
                case 'Home':
                    event.preventDefault();
                    menuItems[0].focus();
                    break;
                case 'End':
                    event.preventDefault();
                    menuItems[menuItems.length - 1].focus();
                    break;
            }
        });
    }
    
    // Modal functionality with keyboard support
    const modalOpenButtons = document.querySelectorAll('[data-modal-target]');
    const modalCloseButtons = document.querySelectorAll('[data-modal-close]');
    const modalOverlay = document.getElementById('modal-overlay');
    
    if (modalOpenButtons && modalOverlay) {
        modalOpenButtons.forEach(button => {
            button.addEventListener('click', () => {
                const modalId = button.getAttribute('data-modal-target');
                const modal = document.getElementById(modalId);
                
                if (modal) {
                    modalOverlay.classList.remove('hidden');
                    modal.classList.remove('hidden');
                    
                    // Focus the first focusable element
                    const focusableElements = modal.querySelectorAll(
                        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                    );
                    
                    if (focusableElements.length > 0) {
                        focusableElements[0].focus();
                    }
                }
            });
        });
    }
    
    if (modalCloseButtons) {
        modalCloseButtons.forEach(button => {
            button.addEventListener('click', () => {
                const modalId = button.closest('.modal').id;
                const modal = document.getElementById(modalId);
                
                if (modal && modalOverlay) {
                    modalOverlay.classList.add('hidden');
                    modal.classList.add('hidden');
                }
            });
        });
    }
    
    if (modalOverlay) {
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) {
                const openModals = document.querySelectorAll('.modal:not(.hidden)');
                openModals.forEach(modal => {
                    modal.classList.add('hidden');
                });
                modalOverlay.classList.add('hidden');
            }
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !modalOverlay.classList.contains('hidden')) {
                const openModals = document.querySelectorAll('.modal:not(.hidden)');
                openModals.forEach(modal => {
                    modal.classList.add('hidden');
                });
                modalOverlay.classList.add('hidden');
            }
        });
    }
    
    // Enhanced touch handling for mobile
    const isMobile = window.matchMedia('(max-width: 640px)').matches;
    
    if (isMobile) {
        // Add touch-specific enhancements
        document.querySelectorAll('button, a.btn, input[type="submit"]').forEach(element => {
            element.addEventListener('touchstart', function() {
                this.classList.add('touch-active');
            });
            
            element.addEventListener('touchend', function() {
                this.classList.remove('touch-active');
            });
        });
    }
    
    // Notifications with automatic dismissal
    const notifications = document.querySelectorAll('.notification');
    
    if (notifications.length > 0) {
        notifications.forEach(notification => {
            // Add close button
            const closeButton = document.createElement('button');
            closeButton.innerHTML = '<i class="fas fa-times"></i>';
            closeButton.className = 'absolute top-2 right-2 text-gray-500 hover:text-gray-700';
            closeButton.setAttribute('aria-label', 'Nachricht schließen');
            
            closeButton.addEventListener('click', () => {
                notification.classList.add('opacity-0');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            });
            
            notification.append(closeButton);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notification.classList.add('opacity-0');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 5000);
        });
    }
    
    // Improved delete confirmation
    const deleteButtons = document.querySelectorAll('.delete-confirm');
    
    if (deleteButtons.length > 0) {
        deleteButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                
                const itemType = button.getAttribute('data-item') || 'Eintrag';
                
                if (confirm(`Sind Sie sicher, dass Sie diesen ${itemType} löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden.`)) {
                    window.location.href = button.getAttribute('href');
                }
            });
        });
    }
    
    // Team selection with enhanced functionality
    const teamSelect = document.getElementById('team-select');
    
    if (teamSelect) {
        teamSelect.addEventListener('change', function() {
            // Get current URL parameters
            const url = new URL(window.location.href);
            const params = new URLSearchParams(url.search);
            
            // Update or add team_id parameter
            params.set('team_id', this.value);
            
            // Keep the active tab if it exists
            if (!params.has('tab') && url.searchParams.has('tab')) {
                params.set('tab', url.searchParams.get('tab'));
            }
            
            // Navigate to the new URL
            window.location.href = `${window.location.pathname}?${params.toString()}`;
        });
    }
    
    // Print button functionality
    const printButton = document.getElementById('print-button');
    
    if (printButton) {
        printButton.addEventListener('click', function() {
            window.print();
        });
    }
    
    // Form validation for passwords
    const passwordForm = document.getElementById('password-form');
    
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                
                // Show validation error
                const errorElement = document.getElementById('password-match-error');
                if (errorElement) {
                    errorElement.classList.remove('hidden');
                } else {
                    const errorDiv = document.createElement('div');
                    errorDiv.id = 'password-match-error';
                    errorDiv.className = 'bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4';
                    errorDiv.textContent = 'Die Passwörter stimmen nicht überein.';
                    
                    passwordForm.prepend(errorDiv);
                }
                
                // Focus on confirm password field
                document.getElementById('confirm_password').focus();
            }
        });
    }
    
    // Autocomplete for player position with accessibility
    const positionInput = document.getElementById('position');
    
    if (positionInput && positionInput.tagName === 'INPUT') {
        const positions = [
            'Guard', 
            'Forward', 
            'Center', 
            'Point Guard', 
            'Shooting Guard', 
            'Small Forward', 
            'Power Forward'
        ];
        
        let currentFocus;
        
        // Create autocomplete container with accessible attributes
        const autocompleteContainer = document.createElement('div');
        autocompleteContainer.id = 'position-autocomplete-container';
        autocompleteContainer.className = 'relative';
        autocompleteContainer.setAttribute('role', 'combobox');
        autocompleteContainer.setAttribute('aria-expanded', 'false');
        autocompleteContainer.setAttribute('aria-owns', 'position-autocomplete-list');
        autocompleteContainer.setAttribute('aria-haspopup', 'listbox');
        
        // Replace input with the container and move the input inside
        positionInput.parentNode.insertBefore(autocompleteContainer, positionInput);
        autocompleteContainer.appendChild(positionInput);
        
        positionInput.addEventListener('input', function() {
            closeAllLists();
            
            if (!this.value) {
                autocompleteContainer.setAttribute('aria-expanded', 'false');
                return false;
            }
            
            currentFocus = -1;
            
            const autocompleteList = document.createElement('div');
            autocompleteList.setAttribute('id', 'position-autocomplete-list');
            autocompleteList.setAttribute('class', 'autocomplete-items absolute z-10 w-full bg-white border border-gray-300 mt-1 max-h-60 overflow-auto');
            autocompleteList.setAttribute('role', 'listbox');
            
            autocompleteContainer.appendChild(autocompleteList);
            autocompleteContainer.setAttribute('aria-expanded', 'true');
            
            let matchFound = false;
            
            for (let i = 0; i < positions.length; i++) {
                if (positions[i].toLowerCase().indexOf(this.value.toLowerCase()) > -1) {
                    matchFound = true;
                    
                    const item = document.createElement('div');
                    item.setAttribute('role', 'option');
                    item.setAttribute('aria-selected', 'false');
                    
                    // Highlight the matching part
                    const matchIndex = positions[i].toLowerCase().indexOf(this.value.toLowerCase());
                    const beforeMatch = positions[i].substr(0, matchIndex);
                    const match = positions[i].substr(matchIndex, this.value.length);
                    const afterMatch = positions[i].substr(matchIndex + this.value.length);
                    
                    item.innerHTML = beforeMatch + "<strong>" + match + "</strong>" + afterMatch;
                    item.innerHTML += "<input type='hidden' value='" + positions[i] + "'>";
                    
                    item.addEventListener('click', function() {
                        positionInput.value = this.getElementsByTagName('input')[0].value;
                        positionInput.focus();
                        closeAllLists();
                    });
                    
                    autocompleteList.appendChild(item);
                }
            }
            
            if (!matchFound) {
                autocompleteContainer.setAttribute('aria-expanded', 'false');
            }
        });
        
        positionInput.addEventListener('keydown', function(e) {
            let x = document.getElementById('position-autocomplete-list');
            if (!x) return;
            
            const items = x.getElementsByTagName('div');
            if (!items.length) return;
            
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    currentFocus++;
                    addActive(items);
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    currentFocus--;
                    addActive(items);
                    break;
                case 'Enter':
                    e.preventDefault();
                    if (currentFocus > -1) {
                        if (items[currentFocus]) {
                            items[currentFocus].click();
                        }
                    }
                    break;
                case 'Escape':
                    closeAllLists();
                    break;
            }
        });
        
        function addActive(items) {
            if (!items) return false;
            
            removeActive(items);
            
            if (currentFocus >= items.length) currentFocus = 0;
            if (currentFocus < 0) currentFocus = (items.length - 1);
            
            items[currentFocus].classList.add('bg-gray-200');
            items[currentFocus].setAttribute('aria-selected', 'true');
            items[currentFocus].scrollIntoView({ block: 'nearest' });
        }
        
        function removeActive(items) {
            for (let i = 0; i < items.length; i++) {
                items[i].classList.remove('bg-gray-200');
                items[i].setAttribute('aria-selected', 'false');
            }
        }
        
        function closeAllLists(elmnt) {
            const x = document.getElementById('position-autocomplete-list');
            if (x && elmnt !== x && elmnt !== positionInput) {
                x.parentNode.removeChild(x);
                autocompleteContainer.setAttribute('aria-expanded', 'false');
            }
        }
        
        document.addEventListener('click', function(e) {
            closeAllLists(e.target);
        });
    }
    
    // Mobile keyboard adjustments
    adjustForMobileKeyboard();
});

// Helper function for mobile keyboard adjustments
function adjustForMobileKeyboard() {
    const viewportHeight = window.innerHeight;
    
    window.addEventListener('resize', () => {
        // If the window height changes significantly (virtual keyboard appears)
        if (window.innerHeight < viewportHeight * 0.8) {
            // Scroll focused element into view
            if (document.activeElement && 
                (document.activeElement.tagName === 'INPUT' || 
                 document.activeElement.tagName === 'SELECT' || 
                 document.activeElement.tagName === 'TEXTAREA')) {
                setTimeout(() => {
                    document.activeElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }, 100);
            }
        }
    });
    
    // Make sure dropdowns close properly on mobile
    document.addEventListener('touchstart', (e) => {
        const userMenu = document.getElementById('user-menu');
        const userMenuButton = document.getElementById('user-menu-button');
        
        if (userMenu && !userMenu.classList.contains('hidden') && 
            !userMenu.contains(e.target) && 
            !userMenuButton.contains(e.target)) {
            userMenu.classList.add('hidden');
            if (userMenuButton) {
                userMenuButton.setAttribute('aria-expanded', 'false');
            }
        }
    });
}