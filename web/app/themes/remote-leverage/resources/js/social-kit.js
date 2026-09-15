/* ==========================================================================
   RL Social Media Kit - Frontend Application Script
   ========================================================================== */

jQuery(document).ready(function($) {
    // Safety check for localized variables
    if (typeof rl_social_kit_vars === 'undefined') {
        return;
    }

    const vars = rl_social_kit_vars;
    const globalSettings = vars.global_settings;
    const userDefaults = vars.user_defaults;

    // Set initial form states from user defaults if available
    if (vars.is_logged_in) {
        // Init form avatar if default exists
        if (userDefaults.avatar) {
            $('#sig-avatar-url').val(userDefaults.avatar);
            $('#rl-avatar-status').text('Default avatar loaded').addClass('success');
        }
    }

    // Render initial signature. Upstream this sat inside the `is_logged_in` branch above,
    // because the plugin's page was login-gated and an anonymous visitor only ever saw the
    // login card. Here the kit is public, so gating it would leave every preview iframe blank
    // until the visitor's first keystroke. No-ops on the login card (the iframes don't exist).
    updateSignaturePreview();

    /* ==========================================================================
       AUTHENTICATION: AJAX Login Form
       ========================================================================== */
    $('#rl-social-kit-login-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('.rl-btn-submit');
        const $errorMsg = $('#rl-login-error');
        
        // Reset states
        $errorMsg.hide().text('');
        $submitBtn.addClass('loading').prop('disabled', true);
        
        $.ajax({
            url: vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'rl_social_kit_login',
                security: vars.nonce,
                username: $('#rl-username').val(),
                password: $('#rl-password').val()
            },
            success: function(response) {
                if (response.success) {
                    $errorMsg.css({
                        'color': '#38a169',
                        'background': '#f0fff4',
                        'border-left-color': '#38a169'
                    }).text(response.data.message).show();
                    
                    // Reload the page to load the logged-in dashboard
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $errorMsg.css({
                        'color': '#e53e3e',
                        'background': '#fff5f5',
                        'border-left-color': '#e53e3e'
                    }).text(response.data.message).show();
                    $submitBtn.removeClass('loading').prop('disabled', false);
                }
            },
            error: function() {
                $errorMsg.text('An error occurred during authentication. Please try again.').show();
                $submitBtn.removeClass('loading').prop('disabled', false);
            }
        });
    });

    /* ==========================================================================
       TABS NAVIGATION
       ========================================================================== */
    $('.rl-menu-item').on('click', function() {
        const $this = $(this);
        const tabId = $this.data('tab');
        
        // Update menu active state
        $('.rl-menu-item').removeClass('active');
        $this.addClass('active');
        
        // Switch views
        $('.rl-tab-view').removeClass('active');
        $('#view-' + tabId).addClass('active');
    });

    /* ==========================================================================
       AVATAR/MEDIA UPLOADER
       ========================================================================== */
    $('#rl-avatar-select-btn').on('click', function(e) {
        e.preventDefault();
        
        const $status = $('#rl-avatar-status');
        
        // Scenario A: Check if WordPress Media Uploader is available (Admin/Author roles)
        if (typeof wp !== 'undefined' && wp.media) {
            let mediaUploader = wp.media({
                title: 'Choose Profile Picture',
                button: { text: 'Use this Photo' },
                multiple: false,
                library: { type: 'image' }
            });
            
            mediaUploader.on('select', function() {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#sig-avatar-url').val(attachment.url);
                $status.text('Image chosen').addClass('success');
                updateSignaturePreview();
            });
            
            mediaUploader.open();
        } 
        // Scenario B: Fallback AJAX Uploader (Subscribers, etc.)
        else {
            $('#rl-avatar-file-input').click();
        }
    });

    // Handle AJAX File Upload
    $('#rl-avatar-file-input').on('change', function() {
        const fileInput = this;
        const $status = $('#rl-avatar-status');
        
        if (fileInput.files.length === 0) {
            return;
        }
        
        const file = fileInput.files[0];
        const formData = new FormData();
        formData.append('action', 'rl_social_kit_upload_avatar');
        formData.append('security', vars.nonce);
        formData.append('avatar', file);
        
        $status.text('Uploading...').removeClass('success').addClass('uploading');
        
        $.ajax({
            url: vars.ajax_url,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                if (response.success) {
                    $('#sig-avatar-url').val(response.data.url);
                    $status.text('Image uploaded!').removeClass('uploading').addClass('success');
                    updateSignaturePreview();
                } else {
                    $status.text('Upload failed').removeClass('uploading');
                    alert(response.data.message);
                }
            },
            error: function() {
                $status.text('Upload error').removeClass('uploading');
                alert('An error occurred during file upload.');
            }
        });
    });

    /* ==========================================================================
       EMAIL SIGNATURE TEMPLATES DEFINITIONS & PARSING
       ========================================================================== */
    
    // Helper to format phone as link safe
    function makePhoneLink(tel) {
        if (!tel) return '';
        return 'tel:' + tel.replace(/[^+\d]/g, '');
    }

    // Helper to escape HTML characters
    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Helper to get form data values
    function getFormData() {
        return {
            firstName: $('#sig-first-name').val() || userDefaults.first_name || 'John',
            lastName: $('#sig-last-name').val() || userDefaults.last_name || 'Doe',
            title: $('#sig-title').val() || 'Senior Director',
            dept: $('#sig-dept').val() || '',
            email: $('#sig-email').val() || userDefaults.email || 'johndoe@company.com',
            officePhone: $('#sig-office-phone').val() || '',
            avatarUrl: $('#sig-avatar-url').val(),
            social: {
                linkedin: $('#sig-linkedin').val(),
                twitter: $('#sig-twitter').val(),
                facebook: $('#sig-facebook').val(),
                instagram: $('#sig-instagram').val()
            }
        };
    }

    // Compile templates using placeholders replacement
    function compileTemplate(template, theme, data) {
        const tplKey = `sig-${template}-${theme}`;
        let html = vars.templates[tplKey];
        if (!html) {
            console.error(`Template not found: ${tplKey}`);
            return '';
        }

        const isDark = (theme === 'dark');
        const themeTextMuted = isDark ? '#DCE3EB' : '#4A5568';
        
        // Address
        const addressParts = (globalSettings.company_address || '').split(',');
        const addressLine1 = addressParts[0] ? addressParts[0].trim() : '';
        const addressLine2 = addressParts.slice(1).join(',').trim();

        // Phone Row
        let phoneRow = '';
        if (data.officePhone) {
            phoneRow = `<tr><td style="padding-bottom: 2px; font-family: Helvetica, Arial, sans-serif;"><a href="${makePhoneLink(data.officePhone)}" style="color: ${themeTextMuted}; text-decoration: none;">${escapeHtml(data.officePhone)}</a></td></tr>`;
        }
        let mobileRow = '';

        // Logo URL
        let logoUrl = globalSettings.company_logo;
        if (isDark) {
            if (logoUrl.indexOf('rl-logo-6.png') !== -1 || logoUrl === '') {
                logoUrl = logoUrl ? logoUrl.replace('rl-logo-6.png', 'rl-logo-5.png') : (vars.plugin_assets + 'resources/other/rl-logo-5.png');
            }
        } else {
            if (logoUrl === '') {
                logoUrl = vars.plugin_assets + 'resources/other/rl-logo-6.png';
            }
        }

        // Social Links & Cells
        const socialLinks = {
            linkedin: data.social.linkedin || globalSettings.global_linkedin,
            twitter: data.social.twitter || globalSettings.global_twitter,
            facebook: data.social.facebook || globalSettings.global_facebook,
            instagram: data.social.instagram || globalSettings.global_instagram,
            youtube: globalSettings.global_youtube
        };

        let socialIconsHtml = '';
        const socialPlatforms = [
            { key: 'linkedin', icon: 'ln.png' },
            { key: 'facebook', icon: 'fb.png' },
            { key: 'instagram', icon: 'ig.png' },
            { key: 'twitter', icon: 'x.png' },
            { key: 'youtube', icon: 'yt.png' }
        ];

        socialPlatforms.forEach(p => {
            if (socialLinks[p.key]) {
                socialIconsHtml += `
                    <td style="padding: 0 6px 0 0; vertical-align: middle;">
                        <a href="${escapeHtml(socialLinks[p.key])}" target="_blank" style="text-decoration: none; display: block;">
                            <img src="${vars.plugin_assets + p.icon}" width="20" height="20" alt="${p.key}" style="border: 0; display: block;" />
                        </a>
                    </td>
                `;
            }
        });

        const name = escapeHtml(data.firstName + ' ' + data.lastName);
        const title = escapeHtml(data.title);
        const email = escapeHtml(data.email);
        const avatarUrl = data.avatarUrl || (vars.plugin_assets + 'logo-icon-black.svg');
        const logoIconWhite = vars.plugin_assets + 'logo-icon-white.svg';
        const logoIconBlack = vars.plugin_assets + 'logo-icon-black.svg';
        const website = escapeHtml(globalSettings.company_website);
        const websiteDisplay = escapeHtml(globalSettings.company_website.replace(/https?:\/\/(www\.)?/, ''));

        html = html
            .replace(/\{\{NAME\}\}/g, name)
            .replace(/\{\{TITLE\}\}/g, title)
            .replace(/\{\{EMAIL\}\}/g, email)
            .replace(/\{\{AVATAR_URL\}\}/g, escapeHtml(avatarUrl))
            .replace(/\{\{LOGO_URL\}\}/g, escapeHtml(logoUrl))
            .replace(/\{\{LOGO_ICON_WHITE\}\}/g, escapeHtml(logoIconWhite))
            .replace(/\{\{LOGO_ICON_BLACK\}\}/g, escapeHtml(logoIconBlack))
            .replace(/\{\{ADDRESS_LINE1\}\}/g, escapeHtml(addressLine1))
            .replace(/\{\{ADDRESS_LINE2\}\}/g, escapeHtml(addressLine2))
            .replace(/\{\{WEBSITE\}\}/g, website)
            .replace(/\{\{WEBSITE_DISPLAY\}\}/g, websiteDisplay)
            .replace(/\{\{SOCIAL_CELLS\}\}/g, socialIconsHtml)
            .replace(/\{\{PHONE_ROW\}\}/g, phoneRow)
            .replace(/\{\{MOBILE_ROW\}\}/g, mobileRow);

        return html;
    }

    /* ==========================================================================
       DASHBOARD: Real-time Live Preview Rendering
       ========================================================================== */
    function updateSignaturePreview() {
        const formData = getFormData();
        const templates = [
            { template: '1', theme: 'light' },
            { template: '1', theme: 'dark' },
            { template: '2', theme: 'light' },
            { template: '2', theme: 'dark' },
            { template: '3', theme: 'light' },
            { template: '3', theme: 'dark' }
        ];

        templates.forEach(t => {
            const html = compileTemplate(t.template, t.theme, formData);
            const iframeId = `iframe-sig-${t.template}-${t.theme}`;
            const iframe = document.getElementById(iframeId);
            if (iframe) {
                const doc = iframe.contentDocument || iframe.contentWindow.document;
                doc.open();
                doc.write(`
                    <html lang="en">
                    <head>
                        <title>Email Signature Preview</title>
                        <style>
                            body {
                                margin: 0;
                                padding: 0;
                                display: flex;
                                justify-content: center;
                                align-items: center;
                                height: 100vh;
                                background-color: transparent;
                            }
                        </style>
                    </head>
                    <body>
                        ${html}
                    </body>
                    </html>
                `);
                doc.close();
            }
        });
    }

    // Bind real-time input event listeners (includes new split phone fields)
    $('#rl-sig-form input, #rl-sig-form select').on('input change', updateSignaturePreview);

    /* ==========================================================================
       EXPORT ACTIONS: Copy & Download handlers
       ========================================================================== */
    
    // Toast Notification helper
    function showToast(message) {
        const $toast = $('#rl-copy-toast');
        $toast.text(message).addClass('show');
        
        setTimeout(function() {
            $toast.removeClass('show');
        }, 2500);
    }

    // 1. Copy Rich Text Signature (for pasting directly into Gmail settings)
    $(document).on('click', '.rl-copy-rich', function() {
        const template = $(this).data('template');
        const theme = $(this).data('theme');
        const formData = getFormData();
        const html = compileTemplate(template, theme, formData);
        
        try {
            // Modern Clipboard API using ClipboardItem (Blob-based)
            const blobHtml = new Blob([html], { type: 'text/html' });
            
            // Plain text fallback
            const plainText = formData.firstName + ' ' + formData.lastName + ' - ' + formData.title;
            const blobText = new Blob([plainText], { type: 'text/plain' });
            
            const clipboardData = new ClipboardItem({
                'text/html': blobHtml,
                'text/plain': blobText
            });
            
            navigator.clipboard.write([clipboardData]).then(function() {
                showToast('Signature copied! Paste it in Gmail settings.');
            }).catch(function(err) {
                fallbackCopyRichText(template, theme);
            });
        } catch (e) {
            fallbackCopyRichText(template, theme);
        }
    });

    function fallbackCopyRichText(template, theme) {
        const iframeId = `iframe-sig-${template}-${theme}`;
        const iframe = document.getElementById(iframeId);
        if (!iframe) return;
        
        try {
            const iframeWindow = iframe.contentWindow;
            const iframeDoc = iframe.contentDocument || iframeWindow.document;
            
            // Select body of iframe
            const range = iframeDoc.createRange();
            range.selectNode(iframeDoc.body);
            
            const selection = iframeWindow.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            
            // Execute copy
            const successful = iframeDoc.execCommand('copy');
            selection.removeAllRanges();
            
            if (successful) {
                showToast('Signature copied (Fallback)! Paste in Gmail settings.');
            } else {
                alert('Copy failed. Please manually select and copy the signature.');
            }
        } catch (err) {
            alert('Your browser does not support copying rich text. Please select the signature and copy manually.');
        }
    }

    // 2. Copy Raw HTML Code
    $(document).on('click', '.rl-copy-html', function() {
        const template = $(this).data('template');
        const theme = $(this).data('theme');
        const formData = getFormData();
        const html = compileTemplate(template, theme, formData);
        
        navigator.clipboard.writeText(html).then(function() {
            showToast('HTML code copied to clipboard!');
        }).catch(function() {
            alert('Failed to copy. Please manually copy the code.');
        });
    });

    // 3. Download HTML file
    $(document).on('click', '.rl-download', function() {
        const template = $(this).data('template');
        const theme = $(this).data('theme');
        const formData = getFormData();
        const html = compileTemplate(template, theme, formData);
        
        const fName = (formData.firstName || 'user').toLowerCase().replace(/\s+/g, '');
        const lName = (formData.lastName || 'signature').toLowerCase().replace(/\s+/g, '');
        
        const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = url;
        a.download = fName + '_' + lName + '_sig_' + template + '_' + theme + '.html';
        document.body.appendChild(a);
        a.click();
        
        setTimeout(function() {
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }, 0);
    });



    /* ==========================================================================
       SETUP INSTRUCTIONS MODAL HANDLERS
       ========================================================================== */
    $(document).on('click', '.rl-btn-setup', function() {
        $('#rl-setup-modal').addClass('active');
    });

    $(document).on('click', '.rl-modal-close, .rl-modal-overlay', function() {
        $('#rl-setup-modal').removeClass('active');
    });

    $(document).on('click', '.rl-modal-tab-btn', function() {
        const $btn = $(this);
        const tabId = $btn.data('modal-tab');
        
        $('.rl-modal-tab-btn').removeClass('active');
        $btn.addClass('active');
        
        $('.rl-modal-tab-content').removeClass('active');
        $('#modal-tab-' + tabId).addClass('active');
    });
});

