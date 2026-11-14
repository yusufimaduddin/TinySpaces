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
        expandedGroups: {},
        mergeModalOpen: false,
        mergeGroupName: '',
        mergeTargetIds: [],
        groupBusy: false,

        // Shared mobile action dropdown (file / group)
        actionMenuOpen: false,
        actionMenuType: null,
        actionMenuFile: null,
        actionMenuGroup: null,
        actionMenuStyle: '',
        isDragging: false,
        newTagInput: '',
        shareEmail: '',
        reviewMode: false,

        // Upload progress state
        uploading: false,
        uploadItems: [],
        overallProgress: 0,
        currentFileName: '',

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

        get visibleFiles() {
            // README.md has its own preview section, so keep it out of the file list.
            return this.files.filter((file) => file.original_name !== 'README.md');
        },

        get readmeFile() {
            return this.files.find((file) => file.original_name === 'README.md') || null;
        },

        get groups() {
            const map = new Map();
            const sortByName = (a, b) => String(a.original_name || '').localeCompare(
                String(b.original_name || ''),
                undefined,
                { numeric: true, sensitivity: 'base' }
            );

            this.visibleFiles.forEach((file) => {
                if (!file.group_name) return;

                const key = String(file.group_name).toLowerCase();
                if (!map.has(key)) {
                    map.set(key, {
                        key: key,
                        name: file.group_name,
                        files: [],
                        totalSize: 0
                    });
                }

                const group = map.get(key);
                group.files.push(file);
                group.totalSize += Number(file.file_size) || 0;
            });

            return Array.from(map.values())
                .map((group) => {
                    group.files.sort(sortByName);
                    return group;
                })
                .sort((a, b) => String(a.name).localeCompare(
                    String(b.name),
                    undefined,
                    { numeric: true, sensitivity: 'base' }
                ));
        },

        get ungroupedFiles() {
            return this.visibleFiles
                .filter((file) => !file.group_name)
                .sort((a, b) => String(a.original_name || '').localeCompare(
                    String(b.original_name || ''),
                    undefined,
                    { numeric: true, sensitivity: 'base' }
                ));
        },

        get fileEntries() {
            const entries = [];
            const groups = this.groups;

            groups.forEach((group) => {
                entries.push({
                    type: 'group',
                    key: 'group:' + group.key,
                    group: group
                });

                if (this.isGroupExpanded(group.key)) {
                    group.files.forEach((file) => {
                        entries.push({
                            type: 'file',
                            key: 'file:' + file.id,
                            file: file,
                            grouped: true
                        });
                    });
                }
            });

            const ungrouped = this.ungroupedFiles;

            if (groups.length > 0 && ungrouped.length > 0) {
                entries.push({
                    type: 'divider',
                    key: 'divider:ungrouped'
                });
            }

            ungrouped.forEach((file) => {
                entries.push({
                    type: 'file',
                    key: 'file:' + file.id,
                    file: file,
                    grouped: false
                });
            });

            return entries;
        },

        get canManageFiles() {
            if (!this.space) return false;
            return Number(this.space.is_owner) === 1 || Number(this.space.has_shared_access) === 1;
        },

        get mergeSourceFile() {
            if (this.mergeTargetIds.length !== 1) return null;
            return this.files.find((file) => file.id === this.mergeTargetIds[0]) || null;
        },

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
                const data = await apiFetch(`/api/spaces/${this.spaceId}`);

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
        openUploadModal() {
            if (!this.uploading && this.selectedFiles.length === 0) {
                this.uploadItems = [];
                this.overallProgress = 0;
                this.currentFileName = '';
            }
            this.uploadModalOpen = true;
        },

        triggerFileInput() {
            if (this.uploading) return;
            document.getElementById('fileUploadInput').click();
        },

        handleFileSelect(event) {
            const files = Array.from(event.target.files);
            this.addSelectedFiles(files);
            event.target.value = '';
        },

        handleDrop(event) {
            if (this.uploading) return;
            if (event.dataTransfer.files && event.dataTransfer.files.length > 0) {
                this.addSelectedFiles(Array.from(event.dataTransfer.files));
            }
        },

        addSelectedFiles(files) {
            if (!files.length) return;

            // Reset previous upload results when picking a new batch
            if (this.uploadItems.length > 0) {
                this.uploadItems = [];
                this.overallProgress = 0;
            }

            this.selectedFiles = [...this.selectedFiles, ...files];
        },

        removeSelectedFile(index) {
            if (this.uploading) return;
            this.selectedFiles.splice(index, 1);
        },

        clearSelectedFiles() {
            if (this.uploading) return;
            this.selectedFiles = [];
            const fileInput = document.getElementById('fileUploadInput');
            if (fileInput) fileInput.value = '';
        },

        async uploadFiles() {
            if (this.uploading || this.selectedFiles.length === 0) return;

            const files = [...this.selectedFiles];
            const totalBytes = files.reduce((sum, file) => sum + file.size, 0) || 1;
            const maxBytes = Number(window.UPLOAD_MAX_BYTES) || 0;

            this.uploading = true;
            this.overallProgress = 0;
            this.currentFileName = '';
            this.uploadItems = files.map((file) => ({
                name: file.name,
                size: file.size,
                progress: 0,
                status: 'pending',
                error: ''
            }));

            let completedBytes = 0;
            let successCount = 0;
            let failureCount = 0;

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const item = this.uploadItems[i];

                if (maxBytes > 0 && file.size > maxBytes) {
                    item.status = 'error';
                    item.error = `Ukuran file melebihi batas server (${this.formatFileSize(maxBytes)})`;
                    failureCount++;
                    completedBytes += file.size;
                    this.overallProgress = Math.round((completedBytes / totalBytes) * 100);
                    continue;
                }

                item.status = 'uploading';
                this.currentFileName = file.name;

                try {
                    const result = await this.uploadSingleFile(file, (loaded) => {
                        item.progress = file.size
                            ? Math.min(100, Math.round((loaded / file.size) * 100))
                            : 0;
                        this.overallProgress = Math.min(
                            99,
                            Math.round(((completedBytes + loaded) / totalBytes) * 100)
                        );
                    });

                    if (result && result.success) {
                        item.status = 'success';
                        item.progress = 100;
                        successCount++;
                    } else {
                        item.status = 'error';
                        item.error = (result && result.message) || 'Upload gagal';
                        failureCount++;
                    }
                } catch (error) {
                    item.status = 'error';
                    item.error = error && error.message ? error.message : 'Upload gagal';
                    failureCount++;

                    // Stop the queue if the session is gone; the page will redirect
                    if (error && error.name === 'AuthError') {
                        completedBytes += file.size;
                        break;
                    }
                }

                completedBytes += file.size;
                this.overallProgress = Math.round((completedBytes / totalBytes) * 100);
            }

            this.uploading = false;
            this.currentFileName = '';
            this.overallProgress = 100;

            if (failureCount === 0) {
                showAlpineToast(`${successCount} file berhasil diupload`, 'success');
            } else if (successCount > 0) {
                showAlpineToast(`${successCount} berhasil, ${failureCount} gagal`, 'warning');
            } else {
                showAlpineToast('Upload gagal. Periksa pesan pada daftar file.', 'error');
            }

            this.selectedFiles = [];
            const fileInput = document.getElementById('fileUploadInput');
            if (fileInput) fileInput.value = '';

            await this.loadSpaceData();
        },

        uploadSingleFile(file, onProgress) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', `/api/spaces/${this.spaceId}/upload`, true);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.upload.onprogress = (event) => {
                    if (event.lengthComputable) {
                        onProgress(event.loaded);
                    }
                };

                xhr.onload = () => {
                    const contentType = (xhr.getResponseHeader('Content-Type') || '').toLowerCase();
                    let data = null;

                    if (contentType.includes('application/json')) {
                        try {
                            data = JSON.parse(xhr.responseText);
                        } catch (error) {
                            data = null;
                        }
                    }

                    if (xhr.status === 401 || (data && data.code === 'SESSION_EXPIRED')) {
                        const authError = new Error('Sesi Anda telah berakhir. Silakan login kembali.');
                        authError.name = 'AuthError';

                        if (!window.__sessionExpiredHandled) {
                            window.__sessionExpiredHandled = true;
                            showAlpineToast(authError.message, 'error');
                            setTimeout(() => {
                                window.location.href = '/login';
                            }, 1200);
                        }

                        reject(authError);
                        return;
                    }

                    if (!data) {
                        reject(new Error(
                            xhr.status === 413
                                ? 'File terlalu besar untuk server.'
                                : 'Respons server tidak valid. Silakan coba lagi.'
                        ));
                        return;
                    }

                    if (!xhr.status || xhr.status >= 400) {
                        reject(new Error(data.message || 'Upload gagal'));
                        return;
                    }

                    resolve(data);
                };

                xhr.onerror = () => reject(new Error('Tidak dapat terhubung ke server. Periksa koneksi Anda.'));
                xhr.ontimeout = () => reject(new Error('Upload timeout. Silakan coba lagi.'));

                const formData = new FormData();
                formData.append('file', file);
                xhr.send(formData);
            });
        },

        // --- Helper: Format File Size ---
        formatFileSize(bytes) {
            const size = Number(bytes) || 0;
            if (size <= 0) return '0 Bytes';

            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.min(
                Math.floor(Math.log(size) / Math.log(k)),
                sizes.length - 1
            );

            return parseFloat((size / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        },

        // --- Helper: Format Date (Relative Time) ---
        formatDate(dateString) {
            if (!dateString) return 'Recently';
            return dayjs.utc(dateString).fromNow();
        },

        // --- Helper: File type detection ---
        getFileExtension(filename) {
            const name = String(filename || '');
            const dot = name.lastIndexOf('.');
            return dot > -1 ? name.slice(dot + 1).toLowerCase() : '';
        },

        isTextFile(file) {
            if (!file) return false;

            const filename = String(file.original_name || '');
            const basename = filename.split(/[\\/]/).pop().toLowerCase();
            const extension = this.getFileExtension(filename);
            const mimeType = String(file.mime_type || '').toLowerCase();
            const textExtensions = [
                'md', 'markdown', 'mdown', 'mkd', 'txt', 'text', 'log', 'csv', 'tsv',
                'html', 'htm', 'xhtml', 'css', 'scss', 'sass', 'less',
                'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'json', 'jsonc', 'xml', 'svg',
                'yml', 'yaml', 'toml', 'ini', 'conf', 'env', 'gitignore', 'htaccess',
                'php', 'phtml', 'py', 'rb', 'java', 'c', 'h', 'cpp', 'hpp', 'cc', 'cs',
                'go', 'rs', 'swift', 'kt', 'kts', 'dart', 'sh', 'bash', 'zsh', 'fish',
                'ps1', 'bat', 'cmd', 'sql', 'graphql', 'gql', 'vue', 'svelte', 'astro',
                'twig', 'blade', 'lock', 'map', 'properties', 'pl', 'lua', 'r', 'scala',
                'clj', 'cljs', 'edn', 'ex', 'exs', 'erl', 'hrl', 'fs', 'fsx', 'vb',
                'asm', 's', 'sol', 'proto', 'tf', 'tfvars', 'hcl', 'nginx', 'cmake',
                'gradle', 'editorconfig'
            ];
            const textFilenames = [
                'dockerfile', 'makefile', 'license', 'licence', 'readme', 'changelog',
                'procfile', 'gemfile', 'rakefile', 'vagrantfile', 'jenkinsfile',
                '.env', '.gitignore', '.htaccess', '.editorconfig'
            ];

            if (textExtensions.includes(extension)) return true;
            if (textFilenames.includes(basename)) return true;
            if (mimeType.startsWith('text/')) return true;

            return [
                'application/json',
                'application/xml',
                'application/javascript',
                'application/x-javascript',
                'application/ecmascript',
                'application/x-httpd-php',
                'application/x-sh',
                'application/sql',
                'application/graphql',
                'application/x-yaml',
                'application/yaml',
                'application/toml'
            ].includes(mimeType);
        },

        isPreviewableFile(file) {
            if (!file || this.isTextFile(file)) return false;

            const extension = this.getFileExtension(file.original_name);
            const mimeType = String(file.mime_type || '').toLowerCase();

            if (
                mimeType.startsWith('image/') ||
                mimeType.startsWith('audio/') ||
                mimeType.startsWith('video/') ||
                mimeType === 'application/pdf'
            ) {
                return true;
            }

            return [
                'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'avif', 'ico',
                'mp3', 'wav', 'ogg', 'oga', 'm4a', 'flac',
                'mp4', 'webm', 'ogv', 'mov', 'm4v'
            ].includes(extension);
        },

        fileIcon(file) {
            const extension = this.getFileExtension(file && file.original_name);

            if (['md', 'markdown', 'mdown', 'mkd'].includes(extension)) return 'bx bx-book';
            if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'avif', 'ico', 'svg'].includes(extension)) return 'bx bx-image';
            if (['mp3', 'wav', 'ogg', 'oga', 'm4a', 'flac'].includes(extension)) return 'bx bx-music';
            if (['mp4', 'webm', 'ogv', 'mov', 'm4v'].includes(extension)) return 'bx bx-video';
            if (['zip', 'rar', '7z', 'tar', 'gz', 'bz2'].includes(extension)) return 'bx bx-archive';
            if (this.isTextFile(file)) return 'bx bx-code-alt';

            return 'bx bx-file';
        },

        // --- Group Logic ---
        isSelectableFile(file) {
            return !!file && file.original_name !== 'README.md';
        },

        isGroupExpanded(key) {
            return !!this.expandedGroups[key];
        },

        toggleGroup(key) {
            this.expandedGroups[key] = !this.expandedGroups[key];
        },

        openMergeModalForFile(file) {
            if (!this.canManageFiles || !this.isSelectableFile(file)) return;

            this.mergeTargetIds = [file.id];
            this.mergeGroupName = file.group_name || '';
            this.mergeModalOpen = true;
        },

        closeMergeModal() {
            if (this.groupBusy) return;
            this.mergeModalOpen = false;
            this.mergeGroupName = '';
            this.mergeTargetIds = [];
        },

        // --- Mobile Action Dropdown ---
        openActionMenu(type, payload, event) {
            const rect = event.currentTarget.getBoundingClientRect();
            const menuWidth = 176;
            const menuHeight = 152;
            const gap = 6;
            const margin = 8;

            let left = rect.right - menuWidth;
            left = Math.max(margin, Math.min(left, window.innerWidth - menuWidth - margin));

            let top = rect.bottom + gap;
            if (top + menuHeight > window.innerHeight - margin) {
                top = Math.max(margin, rect.top - menuHeight - gap);
            }

            this.actionMenuType = type;
            this.actionMenuFile = type === 'file' ? payload : null;
            this.actionMenuGroup = type === 'group' ? payload : null;
            this.actionMenuStyle = `top: ${Math.round(top)}px; left: ${Math.round(left)}px;`;
            this.actionMenuOpen = true;
        },

        closeActionMenu() {
            this.actionMenuOpen = false;
        },

        async submitMerge() {
            const groupName = this.mergeGroupName.trim();

            if (!groupName) {
                showAlpineToast('Please enter a group name', 'error');
                return;
            }

            if (this.mergeTargetIds.length === 0 || this.groupBusy) return;

            this.groupBusy = true;

            try {
                const result = await apiFetch(`/api/spaces/${this.spaceId}/files/merge`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        file_ids: this.mergeTargetIds,
                        group_name: groupName
                    })
                });

                if (result.success) {
                    showAlpineToast(
                        `${result.count} file dimasukkan ke group "${result.group_name}"`,
                        'success'
                    );
                    this.mergeModalOpen = false;
                    this.mergeGroupName = '';
                    this.mergeTargetIds = [];
                    await this.loadSpaceData();
                } else {
                    showAlpineToast(result.message || 'Failed to merge files', 'error');
                }
            } catch (error) {
                showAlpineToast('Error merging files: ' + error.message, 'error');
            } finally {
                this.groupBusy = false;
            }
        },

        async removeFilesFromGroup() {
            if (this.mergeTargetIds.length === 0 || this.groupBusy) return;
            if (!confirm('Remove the selected file(s) from their group? The files will remain in the space.')) return;

            this.groupBusy = true;

            try {
                const result = await apiFetch(`/api/spaces/${this.spaceId}/files/merge`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        file_ids: this.mergeTargetIds,
                        group_name: null
                    })
                });

                if (result.success) {
                    showAlpineToast('Files removed from group', 'success');
                    this.mergeModalOpen = false;
                    this.mergeGroupName = '';
                    this.mergeTargetIds = [];
                    await this.loadSpaceData();
                } else {
                    showAlpineToast(result.message || 'Failed to remove files from group', 'error');
                }
            } catch (error) {
                showAlpineToast('Error removing files from group: ' + error.message, 'error');
            } finally {
                this.groupBusy = false;
            }
        },

        async downloadGroup(groupName) {
            try {
                const response = await fetch(
                    `/api/spaces/${this.spaceId}/groups/download?name=${encodeURIComponent(groupName)}`,
                    { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
                );

                const contentType = (response.headers.get('Content-Type') || '').toLowerCase();
                if (response.status === 401 || contentType.includes('text/html')) {
                    if (!window.__sessionExpiredHandled) {
                        window.__sessionExpiredHandled = true;
                        showAlpineToast('Sesi Anda telah berakhir. Silakan login kembali.', 'error');
                        setTimeout(() => { window.location.href = '/login'; }, 1200);
                    }
                    return;
                }

                if (!response.ok) {
                    let message = 'Failed to download group';

                    if (contentType.includes('application/json')) {
                        const data = await response.json();
                        message = data.message || message;
                    }

                    showAlpineToast(message, 'error');
                    return;
                }

                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${groupName}.zip`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                setTimeout(() => window.URL.revokeObjectURL(url), 1000);
                showAlpineToast(`Downloaded "${groupName}"`, 'success');
            } catch (error) {
                showAlpineToast('Error downloading group: ' + error.message, 'error');
            }
        },

        async releaseGroup(groupName) {
            if (this.groupBusy) return;
            if (!confirm(`Release all files from "${groupName}"? The files will remain in the space.`)) return;

            this.groupBusy = true;

            try {
                const result = await apiFetch(`/api/spaces/${this.spaceId}/groups/release`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ group_name: groupName })
                });

                if (result.success) {
                    showAlpineToast(`${result.count} file dikeluarkan dari group "${result.group_name}"`, 'success');
                    await this.loadSpaceData();
                } else {
                    showAlpineToast(result.message || 'Failed to release group', 'error');
                }
            } catch (error) {
                showAlpineToast('Error releasing group: ' + error.message, 'error');
            } finally {
                this.groupBusy = false;
            }
        },

        async deleteGroup(groupName) {
            if (this.groupBusy) return;
            if (!confirm(`Delete ALL files in "${groupName}"? This action cannot be undone.`)) return;

            this.groupBusy = true;

            try {
                const result = await apiFetch(`/api/spaces/${this.spaceId}/groups`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ group_name: groupName })
                });

                if (result.success) {
                    showAlpineToast(`${result.count} file di group "${result.group_name}" dihapus`, 'success');
                    await this.loadSpaceData();
                } else {
                    showAlpineToast(result.message || 'Failed to delete group', 'error');
                }
            } catch (error) {
                showAlpineToast('Error deleting group: ' + error.message, 'error');
            } finally {
                this.groupBusy = false;
            }
        },

        // --- File Download/View Logic ---
        async viewFile(fileOrId) {
            let file = fileOrId;

            if (!file || typeof file === 'string') {
                file = this.files.find((item) => item.id === fileOrId) || {
                    id: fileOrId,
                    original_name: '',
                    mime_type: ''
                };
            }

            const fileId = file.id;
            if (!fileId) return;

            // Text/code files are opened in the editor, where the Markdown
            // preview stays hidden for non-Markdown files.
            if (this.isTextFile(file)) {
                const editorUrl = `/user/editor/${this.spaceId}/${fileId}`;
                const editorWindow = window.open(editorUrl, '_blank');

                if (!editorWindow) {
                    window.location.href = editorUrl;
                }
                return;
            }

            // Images, PDFs, audio and video are safe to preview inline.
            if (this.isPreviewableFile(file)) {
                const viewUrl = `/api/spaces/${this.spaceId}/files/${fileId}/view`;
                const viewWindow = window.open(viewUrl, '_blank');

                if (!viewWindow) {
                    await this.downloadFile(fileId, file.original_name || 'file');
                }
                return;
            }

            // Anything the browser cannot display is downloaded directly,
            // keeping the original filename from the file record.
            await this.downloadFile(fileId, file.original_name || 'file');
        },

        async downloadFile(fileId, filename) {
            try {
                const response = await fetch(`/api/spaces/${this.spaceId}/files/${fileId}/download`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const contentType = (response.headers.get('Content-Type') || '').toLowerCase();
                if (response.status === 401 || contentType.includes('text/html')) {
                    if (!window.__sessionExpiredHandled) {
                        window.__sessionExpiredHandled = true;
                        showAlpineToast('Sesi Anda telah berakhir. Silakan login kembali.', 'error');
                        setTimeout(() => { window.location.href = '/login'; }, 1200);
                    }
                    return;
                }

                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    const downloadName = filename || 'download';
                    a.href = url;
                    a.download = downloadName;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    setTimeout(() => window.URL.revokeObjectURL(url), 1000);
                    showAlpineToast(`Downloaded ${downloadName}`, 'success');
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
                const result = await apiFetch(`/api/spaces/${this.spaceId}/files/${fileId}`, {
                    method: 'DELETE'
                });

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
                const result = await apiFetch(`/api/spaces/${this.spaceId}/tags`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'add',
                        tags: [this.newTagInput]
                    })
                });

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
                const result = await apiFetch(`/api/spaces/${this.spaceId}/tags`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'remove',
                        tag: tag
                    })
                });

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
                const result = await apiFetch(`/api/spaces/${this.spaceId}/share`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        email: this.shareEmail
                    })
                });

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
                const result = await apiFetch(`/api/spaces/${this.spaceId}/share`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: userId
                    })
                });

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
                const result = await apiFetch(`/api/spaces/${this.spaceId}/review-mode`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        enabled: this.reviewMode
                    })
                });

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
                const result = await apiFetch(`/api/spaces/${this.spaceId}`, {
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
                const result = await apiFetch(`/api/spaces/${this.spaceId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

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
        openQuickEditReadme() {
            this.closeActionMenu();
            this.editReadmeContent = this.readme && this.readme.content ? this.readme.content : '';
            this.editReadmeModalOpen = true;
        },

        async updateReadme() {
            try {
                showAlpineToast('Updating README...', 'info');
                const result = await apiFetch(`/api/spaces/${this.spaceId}/readme`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        content: this.editReadmeContent
                    })
                });

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
