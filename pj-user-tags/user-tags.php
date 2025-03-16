<?php
/**
 * Plugin Name: PJ User Tags
 * Description: 'User Tags' taxonomy for users.
 * Version: 1.0
 * Author: Piyush Jangid
 * Author URI: https://piyushjangid.in
 * Text Domain: user-tags
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'USER_TAGS_VERSION', '1.0' );
define( 'USER_TAGS_DIR', plugin_dir_path( __FILE__ ) );
define( 'USER_TAGS_URL', plugin_dir_url( __FILE__ ) );

add_action( 'admin_enqueue_scripts', 'enqueue_user_tags_scripts' );
function enqueue_user_tags_scripts( $hook ) {
    if ( 'users.php' !== $hook && 'profile.php' !== $hook && 'user-edit.php' !== $hook && 'user-new.php' !== $hook ) {
        return;
    }
    wp_enqueue_script( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0-rc.0', true );
    wp_enqueue_style( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0' );

    wp_enqueue_script( 'user-tags-admin', USER_TAGS_URL . 'assets/js/script.js', array( 'jquery', 'select2' ), USER_TAGS_VERSION, true );
}

// 'User Tags' taxonomy
add_action( 'init', 'register_user_tags' );
function register_user_tags() {
    $labels = array(
        'name'              => __( 'User Tags', 'user-tags' ),
        'singular_name'     => __( 'User Tag', 'user-tags' ),
        'search_items'      => __( 'Search User Tags', 'user-tags' ),
        'all_items'         => __( 'All User Tags', 'user-tags' ),
        'edit_item'         => __( 'Edit User Tag', 'user-tags' ),
        'update_item'       => __( 'Update User Tag', 'user-tags' ),
        'add_new_item'      => __( 'Add New User Tag', 'user-tags' ),
        'new_item_name'     => __( 'New User Tag Name', 'user-tags' ),
        'menu_name'         => __( 'User Tags', 'user-tags' ),
    );
    $args = array(
        'hierarchical'      => false,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => false,
        'capabilities'      => array( 'manage_terms' => 'manage_options' ),
        'public'            => false,
        'show_in_menu'      => 'users.php',
    );
    register_taxonomy( 'user_tag', 'user', $args ); 
}

function add_user_tags_usermenu() {
    add_users_page(
        __('User Tags'),
        __('User Tags'),
        'manage_options',
        'edit-tags.php?taxonomy=user_tag'
    );
}
add_action('admin_menu', 'add_user_tags_usermenu');

// Add user tags on update
add_action( 'show_user_profile', 'edit_user_profile' );
add_action( 'edit_user_profile', 'edit_user_profile' );
add_action( 'user_new_form', 'edit_user_profile' );
function edit_user_profile( $user ) { 
    $user_tags = get_user_meta( $user->ID, 'user_tags', true );
    ?>
    <h3><?php _e( 'User Tags', 'user-tags' ); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="user_tags"><?php _e( 'User Tags', 'user-tags' ); ?></label></th>
            <td>
                <select name="user_tags[]" id="user_tags" multiple="multiple" class="user-tags-select" style="width: 60%;">
                    <?php
                    $terms = get_terms( array(
                        'taxonomy' => 'user_tag',
                        'hide_empty' => false,
                    ) );
                    foreach ( $terms as $term ) {
                        echo '<option value="' . esc_attr( $term->term_id ) . '"' . ( in_array( $term->term_id, (array) $user_tags ) ? ' selected="selected"' : '' ) . '>' . esc_html( $term->name ) . '</option>';
                    }
                    ?>
                </select>
            </td>
        </tr>
    </table>
    <?php
}

// Save user tags on update.
add_action( 'personal_options_update', 'save_user_profile' );
add_action( 'edit_user_profile_update', 'save_user_profile' );
add_action( 'user_register', 'save_user_profile' );
function save_user_profile( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }
    $user_tags = isset( $_POST['user_tags'] ) ? array_map( 'intval', $_POST['user_tags'] ) : array();
    update_user_meta( $user_id, 'user_tags', $user_tags );
}

// AJAX action for searching user tag
add_action( 'wp_ajax_search_user_tags', 'search_user_tags' );
function search_user_tags() {
    $search = isset( $_GET['q'] ) ? sanitize_text_field( $_GET['q'] ) : '';
    $terms = get_terms( array(
        'taxonomy'   => 'user_tag',
        'hide_empty' => false,
        'search'     => $search,
    ) );
    $results = array();
    foreach ( $terms as $term ) {
        $results[] = array(
            'id'   => $term->term_id,
            'text' => $term->name,
        );
    }
    wp_send_json( $results );
}

// user tags filter to users page
add_action( 'restrict_manage_users', 'user_tags_filter', 10, 0 );
function user_tags_filter() {
    static $filter_added = false;
    if ( $filter_added ) {
        return;
    }
    $filter_added = true;
    $screen = get_current_screen();
    if ( 'users' !== $screen->id ) {
        return;
    }
    $terms = get_terms( array(
        'taxonomy' => 'user_tag',
        'hide_empty' => false,
    ) );
    echo '<div style="display: inline-block; align-items: center; margin-left: 10px;">';
    echo '<select name="user_tag" id="user_tag" class="">';
    echo '<option value="">' . __( 'All User Tags', 'user-tags' ) . '</option>';
    foreach ( $terms as $term ) {
        $selected = ( isset( $_GET['user_tag'] ) && $_GET['user_tag'] == $term->term_id ) ? ' selected="selected"' : '';
        echo '<option value="' . esc_attr( $term->term_id ) . '"' . $selected . '>' . esc_html( $term->name ) . '</option>';
    }
    echo '</select>';
    echo '<input type="submit" class="button" value="' . __( 'Filter', 'user-tags' ) . '" />';
    echo '</div>';
}

// Filter users by user tags
add_action( 'pre_get_users', 'filter_users_by_user_tags' );
function filter_users_by_user_tags( $query ) {
    global $pagenow;
    if ( 'users.php' !== $pagenow || ! isset( $_GET['user_tag'] ) || empty( $_GET['user_tag'] ) ) {
        return;
    }
    $term_id = intval( $_GET['user_tag'] );
    $meta_query = array(
        array(
            'key'     => 'user_tags',
            'value'   => $term_id,
            'compare' => 'LIKE',
        ),
    );
    $query->set( 'meta_query', $meta_query );
}

// Add user tags column in user table
add_filter( 'manage_users_columns', 'add_user_tags_column' );
function add_user_tags_column( $columns ) {
    $user_tag_columns = array();
    foreach ( $columns as $key => $value ) {
        if ( $key == 'posts' ) {
            $user_tag_columns['user_tags'] = __( 'User Tags', 'user-tags' );
        }
        $user_tag_columns[$key] = $value;
    }
    return $user_tag_columns;
}
add_action( 'manage_users_custom_column', 'show_user_tags_column', 10, 3 );
function show_user_tags_column( $value, $column_name, $user_id ) {
    if ( 'user_tags' == $column_name ) {
        $user_tags = get_user_meta( $user_id, 'user_tags', true );
        if ( ! empty( $user_tags ) ) {
            $terms = get_terms( array(
                'taxonomy' => 'user_tag',
                'include'  => $user_tags,
                'hide_empty' => false,
            ) );
            $tags = array();
            foreach ( $terms as $term ) {
                $tags[] = '<a href="' . esc_url( add_query_arg( array( 'user_tag' => $term->term_id ), admin_url( 'users.php' ) ) ) . '">' . esc_html( $term->name ) . '</a>';
            }
            return implode( ', ', $tags );
        }
    }
    return $value;
}

// Update the count column
add_filter( 'manage_edit-user_tag_columns', 'add_user_tag_count_column' );
function add_user_tag_count_column( $columns ) {
    unset( $columns['posts'] );
    $columns['user_count'] = __( 'User Count', 'user-tags' );
    return $columns;
}
add_filter( 'manage_user_tag_custom_column', 'show_user_tag_count_column', 10, 3 );
function show_user_tag_count_column( $content, $column_name, $term_id ) {
    if ( 'user_count' == $column_name ) {
        $term = get_term( $term_id, 'user_tag' );
        $user_count = count( get_users( array(
            'meta_key' => 'user_tags',
            'meta_value' => $term->term_id,
            'meta_compare' => 'LIKE',
        ) ) );
        $content = '<a href="' . esc_url( add_query_arg( array( 'user_tag' => $term->term_id ), admin_url( 'users.php' ) ) ) . '">' . $user_count . '</a>';
    }
    return $content;
}


