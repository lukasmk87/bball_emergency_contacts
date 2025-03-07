<!-- Mobile-Optimized Footer -->
        <footer class="bg-gray-100 border-t mt-auto py-4">
            <div class="container mx-auto px-2 sm:px-4">
                <div class="flex flex-col sm:flex-row justify-center items-center text-gray-500 text-xs sm:text-sm space-y-2 sm:space-y-0">
                    <div class="text-center">
                        &copy; <?= date('Y') ?> <?= APP_NAME ?> | <i class="fas fa-lock text-xs"></i> SSL-gesichert
                    </div>
                    <div class="sm:ml-4 flex space-x-3">
                        <a href="privacy_policy.php" class="text-orange-600 hover:text-orange-800 transition">Datenschutzerklärung</a>
                        <a href="impressum.php" class="text-orange-600 hover:text-orange-800 transition">Impressum</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="assets/js/scripts.js"></script>
    
    <!-- Mobile optimization script -->
    <script>
        // Add smooth scrolling when virtual keyboard appears on mobile
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
            }
        });
    </script>
</body>
</html>