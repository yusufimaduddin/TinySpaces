document.addEventListener('alpine:init', () => {
    // Toast Notification Store
    if (!Alpine.store('toast')) {
        Alpine.store('toast', {
            visible: false,
            message: '',
            type: 'success',
            show(message, type = 'success') {
                this.message = message;
                this.type = type;
                this.visible = true;
                setTimeout(() => {
                    this.visible = false;
                }, 3000);
            }
        });
    }

    // Make toast globally available
    window.showAlpineToast = (message, type) => Alpine.store('toast').show(message, type);

    // Double-click handler for modal backdrops
    // Closes the modal only if clicked twice within 300ms
    window.handleBackdropDoubleTap = (callback) => {
        const now = new Date().getTime();
        if (window.lastBackdropTap && (now - window.lastBackdropTap < 300)) {
            callback();
            window.lastBackdropTap = 0;
        } else {
            window.lastBackdropTap = now;
        }
    };
});
