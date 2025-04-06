/**
 * Admin JavaScript for Daily Auto Poster
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Toggle password visibility
        $('#dap_toggle_key').on('change', function() {
            $('#dap_api_key').attr('type', this.checked ? 'text' : 'password');
        });
        
        // Generate AI post
        $('#dap-generate-post').on('click', function() {
            dapGenerateAiPost();
        });
        
        // Bulk publish drafts
        $('#dap-bulk-publish').on('click', function() {
            dapBulkPublish();
        });
        
        // Publish single post
        $('.dap-publish-single').on('click', function() {
            const postId = $(this).data('id');
            dapPublishSingle(postId);
        });
        
        // Unpublish single post
        $('.dap-unpublish-single').on('click', function() {
            const postId = $(this).data('id');
            dapUnpublishSingle(postId);
        });
        
        // Affiliate Links Editor
        if ($('#dap-affiliate-manager').length) {
            initAffiliateEditor();
        }
    });
    
    function dapGenerateAiPost() {
        const status = $('#dap_ai_status');
        
        let steps = [
            '🧠 Generating topic...',
            '✍️ Writing article...',
            '🔎 Finding sources...',
            '🏷️ Creating tags...',
            '🛍️ Building product list...',
            '🎨 Generating featured image...',
            '📎 Attaching image...',
            '✅ Finalizing post...'
        ];
        
        let stepIndex = 0;
        const stepInterval = setInterval(() => {
            if (stepIndex < steps.length) {
                status.text(steps[stepIndex]);
                stepIndex++;
            }
        }, 3000); // ~3000ms per step
        
        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'dap_generate_ai_post_now',
                nonce: dapAdmin.nonce
            },
            success: function(response) {
                clearInterval(stepInterval);
                if (response.success) {
                    status.text('✅ Post created!');
                    location.reload();
                } else {
                    status.text('❌ Failed: ' + response.data.message);
                }
            },
            error: function() {
                clearInterval(stepInterval);
                status.text('❌ Server error. Check browser console.');
            }
        });
    }
    
    function dapBulkPublish() {
        const count = $('#dap_post_count').val();
        $('#dap_bulk_status').text('⏳ Publishing...');
        
        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'dap_publish_now',
                count: count,
                nonce: dapAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#dap_bulk_status').text(`✅ Published ${response.data.published} draft(s)`);
                    location.reload();
                } else {
                    $('#dap_bulk_status').text(`❌ Failed: ${response.data.message}`);
                }
            },
            error: function() {
                $('#dap_bulk_status').text('❌ Server error. Check browser console.');
            }
        });
    }
    
    function dapPublishSingle(postId) {
        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'dap_publish_single',
                post_id: postId,
                nonce: dapAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed: ' + response.data.message);
                }
            }
        });
    }
    
    function dapUnpublishSingle(postId) {
        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'dap_unpublish_single',
                post_id: postId,
                nonce: dapAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed: ' + response.data.message);
                }
            }
        });
    }
    
    function initAffiliateEditor() {
        // Add new category
        $('#dap-add-category').on('click', function() {
            const categoryName = $('#dap-new-category-name').val().trim().toLowerCase();
            if (categoryName) {
                // Create new category section
                const html = `
                <div class="dap-category-group" data-category="${categoryName}">
                    <h2>${categoryName.charAt(0).toUpperCase() + categoryName.slice(1)}</h2>
                    <button class="button dap-add-product" data-category="${categoryName}">Add Product</button>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Affiliate Link</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                `;
                $('#dap-affiliate-editor').append(html);
                $('#dap-new-category-name').val('');
                
                // Rebind events
                bindAffiliateEvents();
                $('#dap-affiliate-status').html('<div class="notice notice-success"><p>Category added! Remember to add products.</p></div>');
            }
        });
        
        bindAffiliateEvents();
        
        // Save button in modal
        $('#dap-save-link').on('click', function() {
            const category = $('#dap-edit-category').val();
            const originalProduct = $('#dap-edit-product-original').val();
            const product = $('#dap-edit-product').val();
            const link = $('#dap-edit-link').val();
            
            if (!product || !link) {
                alert('Product name and link are required!');
                return;
            }
            
            // Update UI
            const categorySection = $(`.dap-category-group[data-category="${category}"]`);
            const tbody = categorySection.find('tbody');
            
            if (originalProduct) {
                // Editing existing product
                const row = tbody.find(`tr[data-product="${originalProduct}"]`);
                row.attr('data-product', product);
                row.find('td:first-child').text(product);
                row.find('td:nth-child(2) a').attr('href', link).text(link);
            } else {
                // Adding new product
                const html = `
                <tr data-product="${product}">
                    <td>${product}</td>
                    <td><a href="${link}" target="_blank">${link}</a></td>
                    <td>
                        <button class="button dap-edit-link">Edit</button>
                        <button class="button dap-delete-link">Delete</button>
                    </td>
                </tr>
                `;
                tbody.append(html);
                bindAffiliateEvents();
            }
            
            // Close modal
            $('#dap-link-editor-modal').hide();
            $('#dap-affiliate-status').html('<div class="notice notice-success"><p>Affiliate link saved!</p></div>');
            
            // Save to database
            saveAffiliateLinks();
        });
        
        // Cancel button in modal
        $('#dap-cancel-edit').on('click', function() {
            $('#dap-link-editor-modal').hide();
        });
    }
    
    function bindAffiliateEvents() {
        // Edit link
        $('.dap-edit-link').off('click').on('click', function() {
            const row = $(this).closest('tr');
            const category = $(this).closest('.dap-category-group').data('category');
            const product = row.data('product');
            const link = row.find('td:nth-child(2) a').attr('href');
            
            // Fill modal
            $('#dap-edit-category').val(category);
            $('#dap-edit-product-original').val(product);
            $('#dap-edit-product').val(product);
            $('#dap-edit-link').val(link);
            
            // Show modal
            $('#dap-link-editor-modal').show();
        });
        
        // Delete link
        $('.dap-delete-link').off('click').on('click', function() {
            if (confirm('Are you sure you want to delete this affiliate link?')) {
                $(this).closest('tr').remove();
                saveAffiliateLinks();
                $('#dap-affiliate-status').html('<div class="notice notice-success"><p>Affiliate link deleted!</p></div>');
            }
        });
        
        // Add product
        $('.dap-add-product').off('click').on('click', function() {
            const category = $(this).data('category');
            
            // Fill modal for new product
            $('#dap-edit-category').val(category);
            $('#dap-edit-product-original').val('');
            $('#dap-edit-product').val('');
            $('#dap-edit-link').val('');
            
            // Show modal
            $('#dap-link-editor-modal').show();
        });
    }
    
    function saveAffiliateLinks() {
        // Collect all links from UI
        const allLinks = {};
        
        $('.dap-category-group').each(function() {
            const category = $(this).data('category');
            allLinks[category] = {};
            
            $(this).find('tbody tr').each(function() {
                const product = $(this).data('product');
                const link = $(this).find('td:nth-child(2) a').attr('href');
                allLinks[category][product] = link;
            });
        });
        
        // Save to database via AJAX
        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'dap_save_affiliate_links',
                links: allLinks,
                nonce: dapAdmin.nonce
            },
            success: function(response) {
                if (!response.success) {
                    $('#dap-affiliate-status').html('<div class="notice notice-error"><p>Error saving links: ' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $('#dap-affiliate-status').html('<div class="notice notice-error"><p>Server error. Check browser console.</p></div>');
            }
        });
    }
    
})(jQuery);
