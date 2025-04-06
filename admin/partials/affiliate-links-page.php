<?php
/**
 * Affiliate links admin page template
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$affiliate_manager = new DAP_Affiliate_Manager();
$all_links = $affiliate_manager->get_all_affiliate_links();
?>
<div class="wrap">
    <h1>Affiliate Links</h1>
    
    <div id="dap-affiliate-manager">
        <div id="dap-affiliate-status" style="margin-bottom:15px;"></div>
        
        <div id="dap-affiliate-editor">
            <?php foreach ($all_links as $category => $links): ?>
                <div class="dap-category-group" data-category="<?php echo esc_attr($category); ?>">
                    <h2><?php echo esc_html(ucfirst($category)); ?></h2>
                    <button class="button dap-add-product" data-category="<?php echo esc_attr($category); ?>">Add Product</button>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Affiliate Link</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($links as $product => $link): ?>
                                <tr data-product="<?php echo esc_attr($product); ?>">
                                    <td><?php echo esc_html($product); ?></td>
                                    <td><a href="<?php echo esc_url($link); ?>" target="_blank"><?php echo esc_html($link); ?></a></td>
                                    <td>
                                        <button class="button dap-edit-link">Edit</button>
                                        <button class="button dap-delete-link">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top:20px;">
            <h2>Add New Category</h2>
            <div class="dap-new-category">
                <input type="text" id="dap-new-category-name" placeholder="Category Name (e.g., makeup, haircare)" style="width:250px;">
                <button class="button button-primary" id="dap-add-category">Add Category</button>
            </div>
        </div>
        
        <!-- Link Editor Modal -->
        <div id="dap-link-editor-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
            <div style="background-color:#fff; margin:10% auto; padding:20px; width:50%; border-radius:4px;">
                <h3>Edit Affiliate Link</h3>
                <input type="hidden" id="dap-edit-category">
                <input type="hidden" id="dap-edit-product-original">
                <p>
                    <label>Product Name:</label><br>
                    <input type="text" id="dap-edit-product" style="width:100%;">
                </p>
                <p>
                    <label>Affiliate Link:</label><br>
                    <input type="text" id="dap-edit-link" style="width:100%;">
                </p>
                <div>
                    <button class="button button-primary" id="dap-save-link">Save</button>
                    <button class="button" id="dap-cancel-edit">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>
