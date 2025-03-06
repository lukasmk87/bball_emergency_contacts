document.addEventListener('DOMContentLoaded', function() {
    // Benutzermenü Toggle
    const userMenuButton = document.getElementById('user-menu-button');
    const userMenu = document.getElementById('user-menu');
    
    if (userMenuButton && userMenu) {
        userMenuButton.addEventListener('click', function() {
            userMenu.classList.toggle('hidden');
        });
        
        // Klick außerhalb des Menüs schließt das Menü
        document.addEventListener('click', function(event) {
            if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.add('hidden');
            }
        });
    }
    
    // Modal-Funktionalität
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
    }
    
    // Benachrichtigungen automatisch ausblenden
    const notifications = document.querySelectorAll('.notification');
    
    if (notifications) {
        notifications.forEach(notification => {
            setTimeout(() => {
                notification.classList.add('opacity-0');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 5000);
        });
    }
    
    // Bestätigung bei Löschaktionen
    const deleteButtons = document.querySelectorAll('.delete-confirm');
    
    if (deleteButtons) {
        deleteButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                if (!confirm('Sind Sie sicher, dass Sie diesen Eintrag löschen möchten?')) {
                    e.preventDefault();
                }
            });
        });
    }
    
    // Team-Auswahl
    const teamSelect = document.getElementById('team-select');
    
    if (teamSelect) {
        teamSelect.addEventListener('change', function() {
            window.location.href = 'dashboard.php?team_id=' + this.value;
        });
    }
    
    // Druckfunktion
    const printButton = document.getElementById('print-button');
    
    if (printButton) {
        printButton.addEventListener('click', function() {
            window.print();
        });
    }
    
    // Formularvalidierung für Passwörter
    const passwordForm = document.getElementById('password-form');
    
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Die Passwörter stimmen nicht überein.');
            }
        });
    }
    
    // Autovervollständigung für Spieler-Position
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
        
        positionInput.addEventListener('input', function() {
            closeAllLists();
            
            if (!this.value) return false;
            
            currentFocus = -1;
            
            const autocompleteList = document.createElement('div');
            autocompleteList.setAttribute('id', this.id + '-autocomplete-list');
            autocompleteList.setAttribute('class', 'autocomplete-items absolute z-10 w-full bg-white border border-gray-300 mt-1 max-h-60 overflow-auto');
            
            this.parentNode.appendChild(autocompleteList);
            
            for (let i = 0; i < positions.length; i++) {
                if (positions[i].substr(0, this.value.length).toUpperCase() === this.value.toUpperCase()) {
                    const item = document.createElement('div');
                    item.innerHTML = "<strong>" + positions[i].substr(0, this.value.length) + "</strong>";
                    item.innerHTML += positions[i].substr(this.value.length);
                    item.innerHTML += "<input type='hidden' value='" + positions[i] + "'>";
                    
                    item.addEventListener('click', function() {
                        positionInput.value = this.getElementsByTagName('input')[0].value;
                        closeAllLists();
                    });
                    
                    autocompleteList.appendChild(item);
                }
            }
        });
        
        positionInput.addEventListener('keydown', function(e) {
            let x = document.getElementById(this.id + '-autocomplete-list');
            if (x) x = x.getElementsByTagName('div');
            
            if (e.keyCode === 40) { // Down arrow
                currentFocus++;
                addActive(x);
            } else if (e.keyCode === 38) { // Up arrow
                currentFocus--;
                addActive(x);
            } else if (e.keyCode === 13) { // Enter
                e.preventDefault();
                if (currentFocus > -1) {
                    if (x) x[currentFocus].click();
                }
            }
        });
        
        function addActive(x) {
            if (!x) return false;
            removeActive(x);
            if (currentFocus >= x.length) currentFocus = 0;
            if (currentFocus < 0) currentFocus = (x.length - 1);
            x[currentFocus].classList.add('bg-gray-200');
        }
        
        function removeActive(x) {
            for (let i = 0; i < x.length; i++) {
                x[i].classList.remove('bg-gray-200');
            }
        }
        
        function closeAllLists(elmnt) {
            const x = document.getElementsByClassName('autocomplete-items');
            for (let i = 0; i < x.length; i++) {
                if (elmnt !== x[i] && elmnt !== positionInput) {
                    x[i].parentNode.removeChild(x[i]);
                }
            }
        }
        
        document.addEventListener('click', function(e) {
            closeAllLists(e.target);
        });
    }
});