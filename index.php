<?php
/*
Plugin Name: Testimonials Plugin sebez technology assignment
Description: Allow users to submit and view testimonials.
Version: 1.0
Author: Mikiyas Shiferaw
*/

defined('ABSPATH') || exit;

class TestimonialsPlugin {
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_fields']);
        add_shortcode('submit_testimonial_form', [$this, 'render_submission_form']);
        add_shortcode('testimonials', [$this, 'render_testimonials']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function register_post_type() {
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
    }

    public function add_meta_boxes() {
        add_meta_box('testimonial_details', 'Testimonial Details', [$this, 'render_meta_box'], 'testimonial', 'normal', 'default');
    }

    public function render_meta_box($post) {
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
    }

    public function save_meta_fields($post_id) {
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
    }

    public function render_submission_form() {
        if (!is_user_logged_in()) {
            return '<p style="text-align: center; color:rgb(0, 0, 0); font-weight: bold;">You must be logged in to submit a testimonial. <a href="' . wp_login_url(get_permalink()) . '" style="color: #0073aa; text-decoration: underline;">Click here to log in</a>.</p>';
        }

        ob_start();
        if (isset($_POST['submit_testimonial'])) {
            $this->handle_submission();
        }
        ?>
        <form method="post" style="max-width: 500px; margin: 0 auto; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
            <p>
            <label>Name:</label><br>
            <input type="text" name="testimonial_name" required style="width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 3px;">
            </p>
            <p>
           
            <input type="email" name="testimonial_email" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" hidden required >
            </p>
            <p>
            <label>Message:</label><br>
            <textarea name="testimonial_message" rows="4" maxlength="500" required style="width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 3px;"></textarea>
            </p>
            <p>
            <label>Rating (1-5):</label><br>
            <input type="number" name="testimonial_rating" min="1" max="5" required style="width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 3px;">
            </p>
            <p style="text-align: center;">
            <input type="submit" name="submit_testimonial" value="Submit Testimonial" style="background: #0073aa; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">
            </p>
        </form>
            </p>
        </form>
        <?php
        return ob_get_clean();
    }

    private function handle_submission() {
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

    public function render_testimonials() {
        $query = new WP_Query([
            'post_type' => 'testimonial',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);

        if (!$query->have_posts()) {
            return '<p>No testimonials available.</p>';
        }

        ob_start();
        ?>
        <div class="testimonials-wrapper">
        <?php
        while ($query->have_posts()) {
            $query->the_post();
            $name = get_post_meta(get_the_ID(), '_testimonial_name', true);
            $rating = intval(get_post_meta(get_the_ID(), '_testimonial_rating', true));
            $date = get_the_date('F j, Y');
            $content = get_the_content();
            ?>
            <div class="testimonial-item" style=" overflow-y: auto;">
                <p class="testimonial-name"><?php echo esc_html($name); ?> <span class="testimonial-date">(<?php echo esc_html($date); ?>)</span></p>
                <div class="testimonial-content"  style="overflow-y: auto;" id="content-<?php echo get_the_ID(); ?>">
                    <?php echo esc_html($content); ?>
                </div>
                <p class="testimonial-rating">
                    <?php
                    for ($i = 1; $i <= 5; $i++) {
                        echo $i <= $rating ? '⭐' : '☆';
                    }
                    ?>
                </p>
            </div>
            <?php
        }
        ?>
        </div>

        <script>
        function toggleContent(id, btn) {
            var content = document.getElementById(id);
            if (content.style.maxHeight === "none") {
                content.style.maxHeight = "120px";
                btn.textContent = "Read More";
            } else {
                content.style.maxHeight = "none";
                btn.textContent = "Show Less";
            }
        }
        </script>

        <style>
        /* Wrapper to show only 3 cards at a time */
        .testimonials-wrapper {
            display: flex;
            overflow-x: auto;
            overflow-y: hidden;
            gap: 15px;
            padding: 10px;
            max-width: 100%;
            box-sizing: border-box;
            scroll-snap-type: x mandatory;
        }

        .testimonial-item {
            flex: 0 0 calc(33.333% - 15px); /* Each card takes 1/3 of the visible area */
            max-width: calc(33.333% - 15px);
            border: 1px solid #ccc;
            padding: 15px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            scroll-snap-align: start;
        }

        .testimonial-name {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .testimonial-date {
            font-size: 0.9em;
            color: #666;
        }

        .testimonial-content {
            max-height: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
            word-break: break-word;
            transition: max-height 0.3s ease;
        }

        .read-more-btn {
            margin-top: 10px;
            background: #0073aa;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
        }

        .testimonial-rating {
            margin-top: 10px;
        }
        </style>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=testimonial',
            'Manage Testimonials',
            'Manage Testimonials',
            'manage_options',
            'manage_testimonials',
            [$this, 'render_manage_testimonials_page'],
            0
        );
    }

    public function render_manage_testimonials_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        // Process form submission 
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

           
            echo '<script>window.location.href="' . admin_url('edit.php?post_type=testimonial&page=manage_testimonials') . '";</script>';
            exit; 
        }

        // Output starts here
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
}

new TestimonialsPlugin();
