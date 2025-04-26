<?php
/*
Plugin Name: Testimonials Plugin
Description: Allow users to submit and view testimonials.
Version: 1.0
Author: Mikiyas Shiferaw
*/

defined('ABSPATH') || exit;

// 1. Register Custom Post Type
add_action('init', function() {
    register_post_type('testimonial', [
        'labels' => [
            'name' => 'Testimonials',
            'singular_name' => 'Testimonial'
        ],
        'public' => false,
        'show_ui' => true,
        'supports' => ['title', 'editor'],
        'capability_type' => 'post',
        'has_archive' => false,
        'rewrite' => false,
    ]);
});

// 2. Add Custom Fields (Meta Boxes)
add_action('add_meta_boxes', function() {
    add_meta_box('testimonial_details', 'Testimonial Details', function($post) {
        $name = get_post_meta($post->ID, '_testimonial_name', true);
        $email = get_post_meta($post->ID, '_testimonial_email', true);
        $rating = get_post_meta($post->ID, '_testimonial_rating', true);
        ?>
        <p><label>Name:</label><br>
            <input type="text" name="testimonial_name" value="<?php echo esc_attr($name); ?>" style="width:100%;">
        </p>
        <p><label>Email (hidden):</label><br>
            <input type="email" name="testimonial_email" value="<?php echo esc_attr($email); ?>" style="width:100%;">
        </p>
        <p><label>Rating (1-5):</label><br>
            <input type="number" name="testimonial_rating" min="1" max="5" value="<?php echo esc_attr($rating); ?>" style="width:100%;">
        </p>
        <?php
    }, 'testimonial', 'normal', 'default');
});

// Save Meta Fields
add_action('save_post', function($post_id) {
    if (get_post_type($post_id) !== 'testimonial') return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (isset($_POST['testimonial_name'])) {
        update_post_meta($post_id, '_testimonial_name', sanitize_text_field($_POST['testimonial_name']));
    }
    if (isset($_POST['testimonial_email'])) {
        update_post_meta($post_id, '_testimonial_email', sanitize_email($_POST['testimonial_email']));
    }
    if (isset($_POST['testimonial_rating'])) {
        update_post_meta($post_id, '_testimonial_rating', intval($_POST['testimonial_rating']));
    }
});

// 3. Frontend Submission Form via Shortcode [submit_testimonial_form]
add_shortcode('submit_testimonial_form', function() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to submit a testimonial.</p>';
    }

    ob_start();
    if (isset($_POST['submit_testimonial'])) {
        $name = sanitize_text_field($_POST['testimonial_name']);
        $email = sanitize_email($_POST['testimonial_email']);
        $message = sanitize_textarea_field($_POST['testimonial_message']);
        $rating = intval($_POST['testimonial_rating']);

        $errors = [];

        if (empty($name)) $errors[] = 'Name is required.';
        if (empty($email) || !is_email($email)) $errors[] = 'Valid email is required.';
        if (empty($message)) $errors[] = 'Message is required.';
        if ($rating < 1 || $rating > 5) $errors[] = 'Rating must be between 1 and 5.';

        if (empty($errors)) {
            $post_id = wp_insert_post([
                'post_title' => wp_strip_all_tags($name),
                'post_content' => $message,
                'post_type' => 'testimonial',
                'post_status' => 'pending',
            ]);

            if ($post_id) {
                update_post_meta($post_id, '_testimonial_name', $name);
                update_post_meta($post_id, '_testimonial_email', $email);
                update_post_meta($post_id, '_testimonial_rating', $rating);
                echo '<p>Thank you! Your testimonial is submitted for review.</p>';
            } else {
                echo '<p>Something went wrong. Please try again.</p>';
            }
        } else {
            foreach ($errors as $error) {
                echo '<p style="color:red;">' . esc_html($error) . '</p>';
            }
        }
    }
    ?>

    <form method="post">
        <p><label>Name:</label><br>
            <input type="text" name="testimonial_name" required>
        </p>
        <p><label>Email (hidden):</label><br>
            <input type="email" name="testimonial_email" required>
        </p>
        <p><label>Message:</label><br>
            <textarea name="testimonial_message" rows="5" required></textarea>
        </p>
        <p><label>Rating (1-5):</label><br>
            <input type="number" name="testimonial_rating" min="1" max="5" required>
        </p>
        <p><input type="submit" name="submit_testimonial" value="Submit Testimonial"></p>
    </form>

    <?php
    return ob_get_clean();
});

// 4. Display Approved Testimonials via Shortcode [testimonials]
add_shortcode('testimonials', function() {
    $query = new WP_Query([
        'post_type' => 'testimonial',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    ]);

    if (!$query->have_posts()) {
        return '<p>No testimonials available.</p>';
    }

    ob_start();
    echo '<div class="testimonials">';
    while ($query->have_posts()) {
        $query->the_post();
        $name = get_post_meta(get_the_ID(), '_testimonial_name', true);
        $rating = intval(get_post_meta(get_the_ID(), '_testimonial_rating', true));
        $date = get_the_date('F j, Y');
        ?>
        <div class="testimonial-item" style="border:1px solid #ccc; padding:10px; margin-bottom:10px;">
            <p><strong><?php echo esc_html($name); ?></strong> (<?php echo esc_html($date); ?>)</p>
            <p><?php echo esc_html(get_the_content()); ?></p>
            <p>
                <?php
                for ($i = 1; $i <= 5; $i++) {
                    echo $i <= $rating ? '⭐' : '☆';
                }
                ?>
            </p>
        </div>
        <?php
    }
    echo '</div>';
    wp_reset_postdata();
    return ob_get_clean();
});

// 5. Admin Menu for Managing Testimonials
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=testimonial',
        'Manage Testimonials',
        'Manage Testimonials',
        'manage_options',
        'manage_testimonials',
        'render_manage_testimonials_page',
        0
    );
});

// 6. Manage Testimonials Page: Approve/Reject
function render_manage_testimonials_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized user');
    }

    // Handle Approve/Reject actions via POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['testimonial_action']) && isset($_POST['testimonial_id'])) {
        $testimonial_id = intval($_POST['testimonial_id']);
        $action = sanitize_text_field($_POST['testimonial_action']);

        if ($action === 'approve') {
            wp_update_post([
                'ID' => $testimonial_id,
                'post_status' => 'publish'
            ]);
        } elseif ($action === 'reject') {
            wp_trash_post($testimonial_id);
        }

        // Redirect to avoid resubmission
        wp_redirect(admin_url('edit.php?post_type=testimonial&page=manage_testimonials'));
        exit;
    }

    // Get pending testimonials
    $testimonials = get_posts([
        'post_type' => 'testimonial',
        'post_status' => 'pending',
        'numberposts' => -1,
    ]);

    echo '<div class="wrap"><h1>Pending Testimonials</h1>';

    if (empty($testimonials)) {
        echo '<p>No pending testimonials.</p>';
    } else {
        echo '<form method="post" action="">';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Name</th><th>Message</th><th>Rating</th><th>Actions</th></tr></thead><tbody>';

        foreach ($testimonials as $testimonial) {
            $name = get_post_meta($testimonial->ID, '_testimonial_name', true);
            $rating = get_post_meta($testimonial->ID, '_testimonial_rating', true);

            echo '<tr>';
            echo '<td>' . esc_html($name) . '</td>';
            echo '<td>' . esc_html(wp_trim_words($testimonial->post_content, 15)) . '</td>';
            echo '<td>' . intval($rating) . '/5</td>';
            echo '<td>';
            echo '<button type="submit" name="testimonial_action" value="approve" class="button button-primary" style="margin-right:5px;">Approve</button>';
            echo '<button type="submit" name="testimonial_action" value="reject" class="button button-secondary">Reject</button>';
            echo '<input type="hidden" name="testimonial_id" value="' . intval($testimonial->ID) . '">';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</form>';
    }

    echo '</div>';
}
