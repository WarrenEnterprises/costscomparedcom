            </div>
            <!-- End Main Content Area -->
            
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="<?php echo TRACKER_PATH; ?>/assets/app.js"></script>
    
    <script>
        // Mobile menu toggle
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
        
        // Close mobile menu when window is resized to desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) {
                document.getElementById('sidebar').classList.remove('-translate-x-full');
                document.getElementById('sidebar-overlay').classList.add('hidden');
            }
        });
        
        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `px-6 py-4 rounded-lg shadow-lg text-white transform transition-all duration-300 ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 
                type === 'warning' ? 'bg-yellow-500' : 
                'bg-blue-500'
            }`;
            
            toast.innerHTML = `
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        ${type === 'success' ? 
                            '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>' :
                        type === 'error' ?
                            '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>' :
                            '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>'
                        }
                    </svg>
                    <span class="font-medium">${message}</span>
                </div>
            `;
            
            const container = document.getElementById('toast-container');
            container.appendChild(toast);
            
            // Animate in
            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 10);
            
            // Remove after 3 seconds
            setTimeout(() => {
                toast.style.transform = 'translateX(400px)';
                toast.style.opacity = '0';
                setTimeout(() => {
                    container.removeChild(toast);
                }, 300);
            }, 3000);
        }
        
        // Copy to clipboard
        function copyToClipboard(text, button = null) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('Copied to clipboard!', 'success');
                
                if (button) {
                    const originalHTML = button.innerHTML;
                    button.innerHTML = '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
                    
                    setTimeout(() => {
                        button.innerHTML = originalHTML;
                    }, 2000);
                }
            }).catch(() => {
                showToast('Failed to copy', 'error');
            });
        }
        
        // Confirm dialog
        function confirmAction(message) {
            return confirm(message);
        }
    </script>
    
</body>
</html>

