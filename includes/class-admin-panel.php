<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_API_Extended_Admin_Panel {
    
    public static function init() {
        // Add any admin panel specific initialization here
    }
    
    /**
     * Dashboard page
     */
    public static function dashboard_page() {
        $stats = self::get_dashboard_stats();
        ?>
        <div class="wrap">
            <h1><?php _e('API Extended - Dashboard', 'wp-api-extended'); ?> <span class="version-badge">Alpha 1</span></h1>
            
            <div class="api-extended-grid">
                <!-- Stats Cards -->
                <div class="api-extended-card stats-card">
                    <div class="stats-icon">📊</div>
                    <div class="stats-content">
                        <h3><?php _e('Total Posts', 'wp-api-extended'); ?></h3>
                        <div class="stats-number"><?php echo $stats['total_posts']; ?></div>
                    </div>
                </div>
                
                <div class="api-extended-card stats-card">
                    <div class="stats-icon">🖼️</div>
                    <div class="stats-content">
                        <h3><?php _e('Total Media', 'wp-api-extended'); ?></h3>
                        <div class="stats-number"><?php echo $stats['total_media']; ?></div>
                    </div>
                </div>
                
                <div class="api-extended-card stats-card">
                    <div class="stats-icon">👥</div>
                    <div class="stats-content">
                        <h3><?php _e('Total Users', 'wp-api-extended'); ?></h3>
                        <div class="stats-number"><?php echo $stats['total_users']; ?></div>
                    </div>
                </div>
                
                <div class="api-extended-card stats-card">
                    <div class="stats-icon">🔐</div>
                    <div class="stats-content">
                        <h3><?php _e('API Keys', 'wp-api-extended'); ?></h3>
                        <div class="stats-number"><?php echo $stats['api_keys']; ?></div>
                    </div>
                </div>
            </div>
            
            <div class="api-extended-grid">
                <!-- Quick Actions -->
                <div class="api-extended-card">
                    <h2>🚀 <?php _e('Quick Actions', 'wp-api-extended'); ?></h2>
                    <div class="quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=wp-api-extended-content'); ?>" class="button button-primary">
                            <?php _e('Manage Content', 'wp-api-extended'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=wp-api-extended-media'); ?>" class="button button-primary">
                            <?php _e('Upload Media', 'wp-api-extended'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=wp-api-extended-settings'); ?>" class="button button-secondary">
                            <?php _e('API Settings', 'wp-api-extended'); ?>
                        </a>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="api-extended-card">
                    <h2>📝 <?php _e('Recent Posts', 'wp-api-extended'); ?></h2>
                    <div class="recent-posts">
                        <?php if (!empty($stats['recent_posts'])): ?>
                            <ul class="recent-list">
                                <?php foreach ($stats['recent_posts'] as $post): ?>
                                    <li>
                                        <a href="<?php echo get_edit_post_link($post->ID); ?>" target="_blank">
                                            <?php echo esc_html($post->post_title); ?>
                                        </a>
                                        <span class="post-date"><?php echo get_the_date('', $post); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p><?php _e('No recent posts found.', 'wp-api-extended'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- API Usage Guide -->
            <div class="api-extended-card">
                <h2>📚 <?php _e('API Usage Guide', 'wp-api-extended'); ?></h2>
                <div class="usage-guide">
                    <h3><?php _e('Quick Start', 'wp-api-extended'); ?></h3>
                    <ol>
                        <li><?php _e('Generate an API key in the Settings page', 'wp-api-extended'); ?></li>
                        <li><?php _e('Use the API key in your requests with X-API-Key header', 'wp-api-extended'); ?></li>
                        <li><?php _e('Start making requests to the available endpoints', 'wp-api-extended'); ?></li>
                    </ol>
                    
                    <h3><?php _e('Example Request', 'wp-api-extended'); ?></h3>
                    <pre><code>// Get posts list
fetch('<?php echo rest_url('wp-api-extended/v1/posts'); ?>', {
    headers: {
        'X-API-Key': 'your_api_key_here'
    }
})
.then(response => response.json())
.then(data => console.log(data));</code></pre>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Content management page
     */
    public static function content_management_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('API Extended - Content Management', 'wp-api-extended'); ?> <span class="version-badge">Alpha 1</span></h1>
            
            <div class="api-extended-grid">
                <!-- Create Post Form -->
                <div class="api-extended-card">
                    <h2>📝 <?php _e('Create New Post', 'wp-api-extended'); ?></h2>
                    <form id="create-post-form" class="api-form">
                        <div class="form-group">
                            <label for="post-title"><?php _e('Post Title', 'wp-api-extended'); ?> *</label>
                            <input type="text" id="post-title" name="title" required class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="post-content"><?php _e('Content', 'wp-api-extended'); ?> *</label>
                            <textarea id="post-content" name="content" rows="6" required class="form-control"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="post-excerpt"><?php _e('Excerpt', 'wp-api-extended'); ?></label>
                            <textarea id="post-excerpt" name="excerpt" rows="3" class="form-control"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="post-status"><?php _e('Status', 'wp-api-extended'); ?></label>
                            <select id="post-status" name="status" class="form-control">
                                <option value="draft"><?php _e('Draft', 'wp-api-extended'); ?></option>
                                <option value="publish"><?php _e('Publish', 'wp-api-extended'); ?></option>
                                <option value="pending"><?php _e('Pending Review', 'wp-api-extended'); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="featured-media"><?php _e('Featured Image', 'wp-api-extended'); ?></label>
                            <div class="media-upload-wrapper">
                                <input type="hidden" id="featured-media" name="featured_media">
                                <button type="button" id="select-featured-media" class="button"><?php _e('Select Image', 'wp-api-extended'); ?></button>
                                <span id="featured-media-preview" class="media-preview"></span>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="button button-primary"><?php _e('Create Post', 'wp-api-extended'); ?></button>
                            <button type="button" id="test-create-post" class="button button-secondary"><?php _e('Test Create', 'wp-api-extended'); ?></button>
                        </div>
                    </form>
                    <div id="create-post-result" class="result-container"></div>
                </div>
                
                <!-- Quick Tools -->
                <div class="api-extended-card">
                    <h2>🛠️ <?php _e('Quick Tools', 'wp-api-extended'); ?></h2>
                    
                    <div class="tool-buttons">
                        <button id="get-posts-test" class="button"><?php _e('Test Get Posts', 'wp-api-extended'); ?></button>
                        <button id="get-categories-test" class="button"><?php _e('Test Get Categories', 'wp-api-extended'); ?></button>
                        <button id="search-posts-test" class="button"><?php _e('Test Search', 'wp-api-extended'); ?></button>
                    </div>
                    
                    <div class="test-results">
                        <h3><?php _e('Test Results', 'wp-api-extended'); ?></h3>
                        <div id="content-test-result" class="result-container"></div>
                    </div>
                </div>
            </div>
            
            <!-- API Examples -->
            <div class="api-extended-card">
                <h2>💡 <?php _e('API Examples', 'wp-api-extended'); ?></h2>
                
                <div class="code-examples">
                    <h3><?php _e('Create Post', 'wp-api-extended'); ?></h3>
                    <pre><code>fetch('<?php echo rest_url('wp-api-extended/v1/posts'); ?>', {
    method: 'POST',
    headers: {
        'X-API-Key': 'your_api_key',
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        title: 'My New Post',
        content: 'This is the post content...',
        status: 'draft',
        excerpt: 'Post excerpt...'
    })
})</code></pre>

                    <h3><?php _e('Update Post', 'wp-api-extended'); ?></h3>
                    <pre><code>fetch('<?php echo rest_url('wp-api-extended/v1/posts/123'); ?>', {
    method: 'PUT',
    headers: {
        'X-API-Key': 'your_api_key',
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        title: 'Updated Post Title',
        content: 'Updated content...'
    })
})</code></pre>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Media upload page
     */
    public static function media_upload_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('API Extended - Media Upload', 'wp-api-extended'); ?> <span class="version-badge">Alpha 1</span></h1>
            
            <div class="api-extended-grid">
                <!-- Upload Form -->
                <div class="api-extended-card">
                    <h2>📤 <?php _e('Upload Media', 'wp-api-extended'); ?></h2>
                    
                    <form id="upload-media-form" class="api-form" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="media-file"><?php _e('Select File', 'wp-api-extended'); ?> *</label>
                            <input type="file" id="media-file" name="file" required class="form-control">
                            <p class="description">
                                <?php 
                                printf(
                                    __('Maximum upload size: %s', 'wp-api-extended'),
                                    size_format(wp_max_upload_size())
                                ); 
                                ?>
                            </p>
                        </div>
                        
                        <div class="form-group">
                            <label for="media-title"><?php _e('Title', 'wp-api-extended'); ?></label>
                            <input type="text" id="media-title" name="title" class="form-control" placeholder="<?php _e('Optional media title', 'wp-api-extended'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="media-description"><?php _e('Description', 'wp-api-extended'); ?></label>
                            <textarea id="media-description" name="description" rows="3" class="form-control" placeholder="<?php _e('Optional media description', 'wp-api-extended'); ?>"></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="button button-primary"><?php _e('Upload Media', 'wp-api-extended'); ?></button>
                            <button type="button" id="test-upload" class="button button-secondary"><?php _e('Test Upload', 'wp-api-extended'); ?></button>
                        </div>
                    </form>
                    
                    <div id="upload-result" class="result-container"></div>
                </div>
                
                <!-- Media Library -->
                <div class="api-extended-card">
                    <h2>🖼️ <?php _e('Media Library', 'wp-api-extended'); ?></h2>
                    
                    <div class="media-library-actions">
                        <button id="refresh-media" class="button"><?php _e('Refresh Library', 'wp-api-extended'); ?></button>
                        <button id="get-media-test" class="button"><?php _e('Test Get Media', 'wp-api-extended'); ?></button>
                    </div>
                    
                    <div id="media-library" class="media-library">
                        <div class="media-loading"><?php _e('Loading media...', 'wp-api-extended'); ?></div>
                    </div>
                    
                    <div id="media-test-result" class="result-container"></div>
                </div>
            </div>
            
            <!-- API Examples -->
            <div class="api-extended-card">
                <h2>💡 <?php _e('Media API Examples', 'wp-api-extended'); ?></h2>
                
                <div class="code-examples">
                    <h3><?php _e('Upload Media', 'wp-api-extended'); ?></h3>
                    <pre><code>const formData = new FormData();
formData.append('file', fileInput.files[0]);

fetch('<?php echo rest_url('wp-api-extended/v1/media'); ?>', {
    method: 'POST',
    headers: {
        'X-API-Key': 'your_api_key'
    },
    body: formData
})</code></pre>

                    <h3><?php _e('Get Media List', 'wp-api-extended'); ?></h3>
                    <pre><code>fetch('<?php echo rest_url('wp-api-extended/v1/media'); ?>', {
    headers: {
        'X-API-Key': 'your_api_key'
    }
})</code></pre>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get dashboard statistics
     */
    private static function get_dashboard_stats() {
        $post_count = wp_count_posts();
        $media_count = wp_count_posts('attachment');
        $user_count = count_users();
        
        // Get recent posts
        $recent_posts = get_posts(array(
            'numberposts' => 5,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        // Get API keys count
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_api_extended_keys';
        $api_keys_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name WHERE status = 'active'"
        );
        
        return array(
            'total_posts' => $post_count->publish,
            'total_media' => $media_count->inherit,
            'total_users' => $user_count['total_users'],
            'api_keys' => $api_keys_count ?: 0,
            'recent_posts' => $recent_posts
        );
    }
}
