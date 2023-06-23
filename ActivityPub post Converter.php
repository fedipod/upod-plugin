<?php
/*
 * Plugin Name: ActivityPub post converter
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
    // 检查用户是否已经登录
    if (!is_user_logged_in()) {
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
    // Register a new setting
    register_setting('linkplugin', 'linkplugin_author_replacements_old', array('default' => array()));
    register_setting('linkplugin', 'linkplugin_author_replacements_new', array('default' => array()));

    // 在linkplugin设置页面添加一个新的设置区段
    add_settings_section(
        'linkplugin_section',
        'Author Associations',
        'linkplugin_section_callback',
        'linkplugin'
    );

    // 在linkplugin设置区段添加一个新的设置字段
    add_settings_field(
        'linkplugin_field',
        'Author Associations',
        'linkplugin_field_callback',
        'linkplugin',
        'linkplugin_section'
    );
}

function get_user_id_dropdown($name, $selected_value, $users, $forCurrentWebsite = false) {
    $html = '<select name="' . $name . '">';
    // Add default empty option
    $html .= '<option value=""></option>';
    foreach ($users as $user) {
        $website = $user->user_url;

        $isCurrentWebsite = empty($website) || parse_url(site_url(), PHP_URL_HOST) == parse_url($website, PHP_URL_HOST);

        if($forCurrentWebsite) {
            if (!$isCurrentWebsite) {
                continue;
            }
        } else {
            if ($isCurrentWebsite) {
                continue;
            }
        }

        $selected = $user->ID == $selected_value ? ' selected' : '';
        // Add data-url to the option
        $html .= '<option value="' . $user->ID . '" data-url="' . $user->user_url . '"' . $selected . '>' . $user->user_login . '</option>';
    }
    $html .= '</select>';
    return $html;
}
add_action('admin_init', 'linkplugin_settings_init');

// 输出设置区段的内容
function linkplugin_section_callback() {
    echo 'Author obtained through the Reveal plugin. Enter the author Associations here.';
}

// 输出设置字段的内容
function linkplugin_field_callback() {
    // 获取当前的设置值
    $old_option = get_option('linkplugin_author_replacements_old', array());
    $new_option = get_option('linkplugin_author_replacements_new', array());

    // 获取所有用户
    $users = get_users();

    $count = max(count($old_option), count($new_option));

    // 如果没有设置任何替换规则，就不显示任何下拉菜单
    if ($count == 0) {
        echo '<p id="linkplugin_no_replacements">No replacements set. Click "Add New Associations<?php" to add one.</p>';
    } else {
        for ($i = 0; $i < $count; $i++) {
            $old_id = $old_option[$i] ?? '';
            $new_id = $new_option[$i] ?? '';
            echo '<p>';
            echo get_user_id_dropdown('linkplugin_author_replacements_old[]', $old_id, $users);
            echo ' => ';
            echo get_user_id_dropdown('linkplugin_author_replacements_new[]', $new_id, $users, true);
            echo ' <button type="button" onclick="linkplugin_remove_replacement(this)">Remove</button></p>';
        }
        
    }

    echo '<p><button type="button" onclick="linkplugin_add_replacement()">Add New Associations</button></p>';

    echo '<script>
    function linkplugin_remove_replacement(button) {
        var p = button.parentNode;
        p.parentNode.removeChild(p);
        if (document.querySelectorAll(\'[name="linkplugin_author_replacements_old[]"]\').length == 0) {
            var no_replacements = document.getElementById("linkplugin_no_replacements");
            if (!no_replacements) {
                var new_p = document.createElement("p");
                new_p.id = "linkplugin_no_replacements";
                new_p.innerText = "No replacements set. Click \\"Add replacement\\" to add one.";
                button.parentNode.parentNode.insertBefore(new_p, button.parentNode);
            }
        }
    }
    function linkplugin_add_replacement() {
        var p = document.createElement("p");
        var old_dropdown = \'' . str_replace("'", "\'", get_user_id_dropdown('linkplugin_author_replacements_old[]', '', $users)) . '\';
        var new_dropdown = \'' . str_replace("'", "\'", get_user_id_dropdown('linkplugin_author_replacements_new[]', '', $users, true)) . '\';
        p.innerHTML = old_dropdown + " => " + new_dropdown + \' <button type="button" onclick="linkplugin_remove_replacement(this)">Remove</button>\';
        var add_button = document.querySelector("button[onclick=\'linkplugin_add_replacement()\']");
        add_button.parentNode.insertBefore(p, add_button);
        var no_replacements = document.getElementById("linkplugin_no_replacements");
        if (no_replacements) {
            no_replacements.parentNode.removeChild(no_replacements);
        }
        // Group and sort options in the dropdown menus
        var selectElements = p.querySelectorAll(\'select\');
        selectElements.forEach(function(select) {
            var optionsArray = Array.prototype.slice.call(select.options);
            optionsArray.sort(function(a, b) {
                return a.dataset.url.localeCompare(b.dataset.url) || a.text.localeCompare(b.text);
            });
            optionsArray.forEach(function(option) {
                select.appendChild(option);
            });
        });
    }
    
    </script>';
}

// 在复制文章时使用设置的替换规则
function my_copy_posts_plugin_copy_cached_post( $post_id ) {
    $post = get_post( $post_id );

    // 获取设置的作者ID替换规则
    $old_option = get_option('linkplugin_author_replacements_old', array());
    $new_option = get_option('linkplugin_author_replacements_new', array());
    $author_id_replacements = array_combine($old_option, $new_option);

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