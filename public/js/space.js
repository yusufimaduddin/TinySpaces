document.addEventListener('alpine:init', () => {
    // Theme Configuration Component
    Alpine.data('themeConfig', () => ({
        isDark: localStorage.getItem('theme') === 'dark',

        toggleDarkMode() {
            this.isDark = !this.isDark;
            localStorage.setItem('theme', this.isDark ? 'dark' : 'light');
            this.updateTheme();
        },

        updateTheme() {
            if (this.isDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        },

        init() {
            // Check system preference or local storage
            if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                this.isDark = true;
            }
            this.updateTheme();
        }
    }));

    // Space Management Component
    Alpine.data('spaceManager', () => ({
        spaceId: '', // To be injected from view
        space: null,
        files: [],
        tags: [],
        sharedUsers: [],
        readme: '',
        loading: true,
        selectedFiles: [],
        newTagInput: '',
        shareEmail: '',
        reviewMode: false,

        // Settings form state
        settingName: '',
        settingDescription: '',
        settingStatus: 'Published',
        settingIconClass: '',

        editReadmeContent: '',

        // Modal states
        uploadModalOpen: false,
        addTagModalOpen: false,
        shareModalOpen: false,
        settingsModalOpen: false,
        editReadmeModalOpen: false,

        async init() {
            // Get space ID from a global variable defined in the view
            this.spaceId = window.SPACE_ID || '';
            if (this.spaceId) {
                await this.loadSpaceData();
            }
        },

        async loadSpaceData() {
            try {
                this.loading = true;
                const response = await fetch(`/api/spaces/${this.spaceId}`);
                const data = await response.json();

                if (data.success) {
                    this.space = data.space;
                    this.files = data.files || [];
                    this.tags = data.tags || [];
                    this.sharedUsers = data.shared_users || [];
                    this.readme = data.readme || '';
                    this.editReadmeContent = this.readme;
                    this.reviewMode = !!this.space.review_mode;

                    // Initialize settings form fields
                    this.settingName = this.space.name;
                    this.settingDescription = this.space.description;
                    this.settingStatus = this.space.status;
                    this.settingIconClass = this.space.class_icon || this.space.icon_class || this.space.icon || '';
                } else {
                    showAlpineToast(data.message || 'Failed to load space data', 'error');
                }
            } catch (error) {
                showAlpineToast('Error loading space data: ' + error.message, 'error');
            } finally {
                this.loading = false;
            }
        },

        // --- File Upload Logic ---
        triggerFileInput() {
            document.getElementById('fileUploadInput').click();
        },

        handleFileSelect(event) {
            const files = Array.from(event.target.files);
            this.selectedFiles = [...this.selectedFiles, ...files];
        },

        handleDrop(event) {
            if (event.dataTransfer.files && event.dataTransfer.files.length > 0) {
                const files = Array.from(event.dataTransfer.files);
                this.selectedFiles = [...this.selectedFiles, ...files];
            }
        },

        removeSelectedFile(index) {
            this.selectedFiles.splice(index, 1);
        },

        clearSelectedFiles() {
            this.selectedFiles = [];
            const fileInput = document.getElementById('fileUploadInput');
            if (fileInput) fileInput.value = '';
        },

        async uploadFiles() {
            if (this.selectedFiles.length === 0) return;

            showAlpineToast(`Uploading ${this.selectedFiles.length} file(s)...`, 'info');

            let successCount = 0;
            let lastError = '';

            for (let file of this.selectedFiles) {
                const formData = new FormData();
                formData.append('file', file);

                try {
                    const response = await fetch(`/api/spaces/${this.spaceId}/upload`, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (!result.success) {
                        lastError = result.message || `Failed to upload ${file.name}`;
                    } else {
                        successCount++;
                    }
                } catch (error) {
                    lastError = `Error uploading ${file.name}: ${error.message}`;
                }
            }

            if (successCount === this.selectedFiles.length) {
                showAlpineToast('Files uploaded successfully!', 'success');
            } else if (successCount > 0) {
                showAlpineToast(`${successCount} file(s) uploaded successfully. ${lastError}`, 'warning');
            } else {
                showAlpineToast(lastError || 'Failed to upload files', 'error');
            }

            this.clearSelectedFiles();
            await this.loadSpaceData();
        },

        // --- Helper: Format File Size ---
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        },

        // --- Helper: Format Date (Relative Time) ---
        formatDate(dateString) {
            if (!dateString) return 'Recently';
            return dayjs.utc(dateString).fromNow();
        },

        // --- File Download/View Logic ---
        async viewFile(fileId) {
            window.open(`/api/spaces/${this.spaceId}/files/${fileId}/view`, '_blank');
        },

        async downloadFile(fileId, filename) {
            try {
                const response = await fetch(`/api/spaces/${this.spaceId}/files/${fileId}/download`);
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    showAlpineToast(`Downloaded ${filename}`, 'success');
                } else {
                    showAlpineToast('Failed to download file', 'error');
                }
            } catch (error) {
                showAlpineToast('Error downloading file: ' + error.message, 'error');
            }
        },

        // --- File Delete Logic ---
        async deleteFile(fileId, filename) {
            if (!confirm(`Are you sure you want to delete "${filename}"?`)) return;

            try {
                showAlpineToast(`Deleting ${filename}...`, 'info');
                const response = await fetch(`/api/spaces/${this.spaceId}/files/${fileId}`, {
                    method: 'DELETE'
                });

                const result = await response.json();

                if (result.success) {
                    showAlpineToast(`${filename} deleted successfully`, 'success');
                    await this.loadSpaceData();
                } else {
                    showAlpineToast(result.message || 'Failed to delete file', 'error');
                }
            } catch (error) {
                showAlpineToast('Error deleting file: ' + error.message, 'error');
            }
        },

        // --- Tag Management ---
        async addTag() {
            if (!this.newTagInput.trim()) {
                showAlpineToast('Please enter a tag name', 'error');
                return;
            }

            try {
                const response = await fetch(`/api/spaces/${this.spaceId}/tags`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'add',
                        tags: [this.newTagInput]
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlpineToast('Tag added successfully', 'success');
                    this.newTagInput = '';
                    await this.loadSpaceData();
                } else {
                    showAlpineToast(result.message || 'Failed to add tag', 'error');
                }
            } catch (error) {
                showAlpineToast('Error adding tag: ' + error.message, 'error');
            }
        },

        async removeTag(tag) {
            if (!confirm(`Remove tag "${tag}"?`)) return;

            try {
                const response = await fetch(`/api/spaces/${this.spaceId}/tags`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'remove',
                        tag: tag
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlpineToast('Tag removed successfully', 'success');
                    await this.loadSpaceData();
                } else {
                    showAlpineToast(result.message || 'Failed to remove tag', 'error');
                }
            } catch (error) {
                showAlpineToast('Error removing tag: ' + error.message, 'error');
            }
        },

        // --- Share Space Logic ---
        async shareSpace() {
            if (!this.shareEmail.trim()) {
                showAlpineToast('Please enter an email address', 'error');
                return;
            }

            try {
                const response = await fetch(`/api/spaces/${this.spaceId}/share`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        email: this.shareEmail
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlpineToast('Space shared successfully', 'success');
                    this.shareEmail = '';
                    await this.loadSpaceData();
                } else {
                    showAlpineToast(result.message || 'Failed to share space', 'error');
                }
            } catch (error) {
                showAlpineToast('Error sharing space: ' + error.message, 'error');
            }
        },

        async removeSharedUser(userId) {
            if (!confirm('Remove this user from shared space?')) return;

            try {
                const response = await fetch(`/api/spaces/${this.spaceId}/share`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: userId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlpineToast('User removed from share', 'success');
                    await this.loadSpaceData();
                } else {
                    showAlpineToast(result.message || 'Failed to remove user', 'error');
                }
            } catch (error) {
                showAlpineToast('Error removing user: ' + error.message, 'error');
            }
        },

        async toggleReviewMode() {
            try {
                const response = await fetch(`/api/spaces/${this.spaceId}/review-mode`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        enabled: this.reviewMode
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlpineToast(result.message, 'success');
                } else {
                    this.reviewMode = !this.reviewMode; // Revert on failure
                    showAlpineToast(result.message || 'Failed to toggle review mode', 'error');
                }
            } catch (error) {
                this.reviewMode = !this.reviewMode; // Revert
                showAlpineToast('Error: ' + error.message, 'error');
            }
        },

        copySpaceLink() {
            // Generate Review Link
            const link = `${window.location.protocol}//${window.location.host}/user/review/${this.spaceId}`;
            navigator.clipboard.writeText(link).then(() => {
                showAlpineToast('Review link copied to clipboard', 'success');
            }).catch(() => {
                showAlpineToast('Failed to copy link', 'error');
            });
        },

        // --- Space Settings Management ---
        async updateSpace() {
            try {
                showAlpineToast('Updating space...', 'info');
                const response = await fetch(`/api/spaces/${this.spaceId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        name: this.settingName,
                        description: this.settingDescription,
                        status: this.settingStatus,
                        class_icon: this.settingIconClass
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlpineToast('Space updated successfully', 'success');
                    await this.loadSpaceData();
                } else {
                    showAlpineToast(result.message || 'Failed to update space', 'error');
                }
            } catch (error) {
                showAlpineToast('Error updating space: ' + error.message, 'error');
            }
        },

        async deleteSpace() {
            if (!confirm('Are you sure you want to delete this space? This action cannot be undone.')) return;

            try {
                showAlpineToast('Deleting space...', 'info');
                const response = await fetch(`/api/spaces/${this.spaceId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    showAlpineToast('Space deleted successfully', 'success');
                    setTimeout(() => {
                        window.location.href = '/user/dashboard';
                    }, 1500);
                } else {
                    showAlpineToast(result.message || 'Failed to delete space', 'error');
                }
            } catch (error) {
                showAlpineToast('Error deleting space: ' + error.message, 'error');
            }
        },

        // --- Readme Management ---
        async updateReadme() {
            try {
                showAlpineToast('Updating README...', 'info');
                const response = await fetch(`/api/spaces/${this.spaceId}/readme`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        content: this.editReadmeContent
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlpineToast('README updated successfully', 'success');
                    await this.loadSpaceData();
                } else {
                    showAlpineToast(result.message || 'Failed to update README', 'error');
                }
            } catch (error) {
                showAlpineToast('Error updating README: ' + error.message, 'error');
            }
        }
    }));
});
