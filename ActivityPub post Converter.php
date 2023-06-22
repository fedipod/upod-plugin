<?php
/*
 * Plugin Name: ActivityPub post Converter
 * Plugin URI: https://github.com/MOMOZAWA3/ActivityPub-post-Converter
 * Description: Support all ActivityPub protocol concerns passed over carefully for copying and converting to WordPress articles, and changing the author to the specified WordPress user.
 * Author: NI YUNHAO
 * Author: https://21te495.daiichi-koudai.com
 * Version: 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ){
    die;
};

// 创建一个新的插件设置页面
function linkplugin_settings_page() {
    add_options_page('ActivityPub post Converter Settings', 'ActivityPub post Converter', 'manage_options', 'linkplugin', 'linkplugin_settings_page_content');
}
add_action('admin_menu', 'linkplugin_settings_page');

// 输出设置页面的内容
function linkplugin_settings_page_content() {
    // 检查用户是否有权限
    if (!current_user_can('manage_options')) {
        return;
    }

    // 显示设置表单
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            // 输出安全字段，用于安全检查
            settings_fields('linkplugin');
            // 输出设置字段
            do_settings_sections('linkplugin');
            // 输出保存设置的按钮
            submit_button('Save Changes');
            ?>
        </form>
    </div>
    <?php
}


// 注册插件设置
function linkplugin_settings_init() {
    // 注册一个新的设置
    register_setting('linkplugin', 'linkplugin_author_replacements', array('default' => array()));

    // 在linkplugin设置页面添加一个新的设置区段
    add_settings_section(
        'linkplugin_section',
        'Author ID Replacements',
        'linkplugin_section_callback',
        'linkplugin'
    );

    // 在linkplugin设置区段添加一个新的设置字段
    add_settings_field(
        'linkplugin_field',
        'Author ID replacements',
        'linkplugin_field_callback',
        'linkplugin',
        'linkplugin_section'
    );
}
add_action('admin_init', 'linkplugin_settings_init');

// 输出设置区段的内容
function linkplugin_section_callback() {
    echo 'Author IDs obtained through the Reveal IDs plugin. Enter the author ID replacements here. Use the format "old_id:new_id".';
}

// 输出设置字段的内容
function linkplugin_field_callback() {
    // 获取当前的设置值
    $replacements_option = get_option('linkplugin_author_replacements', array());

    foreach ($replacements_option as $line) {
        echo '<p><input type="text" name="linkplugin_author_replacements[]" value="' . esc_attr($line) . '"> <button type="button" onclick="linkplugin_remove_replacement(this)">Remove</button></p>';
    }

    echo '<p><button type="button" onclick="linkplugin_add_replacement()">Add replacement</button></p>';
    echo '<script>
    function linkplugin_remove_replacement(button) {
        var p = button.parentNode;
        p.parentNode.removeChild(p);
    }
    function linkplugin_add_replacement() {
        var p = document.createElement("p");
        p.innerHTML = \'<input type="text" name="linkplugin_author_replacements[]" value=""> <button type="button" onclick="linkplugin_remove_replacement(this)">Remove</button>\';
        var add_button = document.querySelector("button[onclick=\'linkplugin_add_replacement()\']");
        add_button.parentNode.insertBefore(p, add_button);
    }
    </script>';
}

// 在复制文章时使用设置的替换规则
function my_copy_posts_plugin_copy_cached_post( $post_id ) {
    $post = get_post( $post_id );
    
    // 获取设置的作者ID替换规则
    $replacements_option = get_option('linkplugin_author_replacements', array());
    $author_id_replacements = array();
    foreach ($replacements_option as $line) {
        list($old_id, $new_id) = explode(':', trim($line));
        $author_id_replacements[$old_id] = $new_id;
    }

    // 检查文章类型
    if ( 'friend_post_cache' !== $post->post_type ) {
        return;
    }

    // 获取原始作者ID
    $original_author_id = $post->post_author;

    // 检查原始作者ID是否在$author_id_replacements中，如果在，则替换为相应的新作者ID
    if ( array_key_exists( $original_author_id, $author_id_replacements ) ) {
        $new_author_id = $author_id_replacements[ $original_author_id ];
    } else {
        // 如果原始作者ID不在替换规则中，不复制文章
        return;
    }

    // 获取用户对象
    $new_author = get_user_by( 'id', $new_author_id );

    // 如果用户不存在，则返回
    if ( ! $new_author ) {
        return;
    }

    // 复制文章
    $new_post = array(
        'post_title'   => $post->post_title,
        'post_content' => $post->post_content,
        'post_status'  => 'publish',
        'post_type'    => 'post',
        'post_author'  => $new_author->ID, // 设置指定的用户为新作者
    );
    $new_post_id = wp_insert_post( $new_post );

    // 立即将文章设为私有
    $new_post_private = array(
        'ID'           => $new_post_id, // 使用刚创建的文章ID
        'post_status'  => 'private', // 设置文章状态为private
    );
    wp_update_post( $new_post_private );
}

add_action( 'save_post', 'my_copy_posts_plugin_copy_cached_post', 10, 1 );
