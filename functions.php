<?php
/**
 * CyberAtlasLab Child Theme functions and definitions
 *
 * @package CyberAtlasLab_Child
 * @version 1.0.3
 * @author CyberAtlas Lab Team
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * =============================================================================
 * 1. 核心常量与初始化 (Setup)
 * =============================================================================
 */

// 自动从 style.css 读取版本号，解决 CDN 和浏览器缓存刷新痛点
define('CAL_CHILD_VERSION', wp_get_theme()->get('Version'));
// 定义语言包标识符（Text Domain）
define('CAL_TEXT_DOMAIN', 'cyberatlaslab-child');

/**
 * 加载国际化语言包
 * 翻译文件应放置在：/wp-content/themes/cyberatlaslab-child/languages/
 */
function cal_child_theme_setup()
{
    load_child_theme_textdomain(CAL_TEXT_DOMAIN, get_stylesheet_directory() . '/languages');
}
add_action('after_setup_theme', 'cal_child_theme_setup');

/**
 * =============================================================================
 * 2. 资源加载 (Styles & Scripts)
 * =============================================================================
 */

function cal_enqueue_assets()
{
    $parent_handle = 'astra-parent-style';
    $parent_version = wp_get_theme('astra')->get('Version');

    // [Styles] 按优先级层级加载
    wp_enqueue_style($parent_handle, get_template_directory_uri() . '/style.css', array(), $parent_version);
    wp_enqueue_style('cal-base-style', get_stylesheet_directory_uri() . '/style.css', array($parent_handle), CAL_CHILD_VERSION);
    wp_enqueue_style('cal-main-style', get_stylesheet_directory_uri() . '/css/main.css', array('cal-base-style'), CAL_CHILD_VERSION);
    // 高效核心：overrides.css 动态读取自身文件的最后修改时间，只要你点保存，前台立即刷新缓存！
    $overrides_version = file_exists(get_stylesheet_directory() . '/css/overrides.css')
        ? filemtime(get_stylesheet_directory() . '/css/overrides.css')
        : CAL_CHILD_VERSION;
    wp_enqueue_style('cal-overrides-style', get_stylesheet_directory_uri() . '/css/overrides.css', array('cal-main-style'), $overrides_version);

    // [Scripts]
    wp_enqueue_script('cal-nav-toggle', get_stylesheet_directory_uri() . '/js/nav-toggle.js', array('jquery'), CAL_CHILD_VERSION, true);
    wp_enqueue_script('cal-offer-countdown', get_stylesheet_directory_uri() . '/js/offer-countdown.js', array(), CAL_CHILD_VERSION, true);
}
add_action('wp_enqueue_scripts', 'cal_enqueue_assets', 15);

/**
 * 规范化注入后台编辑器样式
 */
add_action('admin_enqueue_scripts', function ($hook) {
    // 仅在编辑页面时注入，优化后台加载速度
    if (!in_array($hook, array('post.php', 'post-new.php'))) {
        return;
    }

    $custom_admin_css = ".elementor-widget-shortcode { border-left: 4px solid #2563eb !important; border-radius: 4px; background: rgba(37, 99, 235, 0.02); }";
    wp_add_inline_style('elementor-editor', $custom_admin_css);
});

/**
 * =============================================================================
 * 3. 业务逻辑层 (Business Logic)
 * =============================================================================
 */

/**
 * 统一获取优惠截止日期 (ACF 驱动)
 */
function cal_get_unified_end_date($fallback)
{
    $end_date = '';
    if (function_exists('get_field')) {
        // 优先获取当前页，其次获取首页全局设置
        $end_date = get_field('offer_end_date');
        if (empty($end_date)) {
            $front_page_id = get_option('page_on_front');
            $end_date = $front_page_id ? get_field('offer_end_date', $front_page_id) : '';
        }
    }
    return !empty($end_date) ? $end_date : $fallback;
}

/**
 * =============================================================================
 * 4. 营销组件短代码 (Shortcodes with i18n)
 * =============================================================================
 */

/**
 * [cal_glass_alert] - 磨砂提示条
 */
function cal_glass_alert_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'style'         => 'light',
        'fallback_date' => '2026-12-31',
    ), $atts, 'cal_glass_alert');

    $end_date    = cal_get_unified_end_date($atts['fallback_date']);
    $style_class = ($atts['style'] === 'dark') ? '' : 'cal-glass-alert--light';

    ob_start(); ?>
    <div class="cal-glass-alert <?php echo esc_attr($style_class); ?>">
        <span class="cal-glass-alert__dot"></span>
        <span class="cal-glass-alert__label">
            <?php echo __('Offer Ends in', CAL_TEXT_DOMAIN); ?>
        </span>
        <span class="cal-js-countdown" data-end-date="<?php echo esc_attr($end_date); ?>">
            <?php echo __('Loading...', CAL_TEXT_DOMAIN); ?>
        </span>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('cal_glass_alert', 'cal_glass_alert_shortcode');

/**
 * [cal_offer_footer] - 转化页脚简码
 *
 * 场景：测评页/优惠页底部的限时倒计时 + 联盟 CTA
 * 链接优先级：id（产品 ID，走 /visit/ 伪装链） > cta_link（手动 URL） > '#'
 */
function cal_offer_footer_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'cta_text'      => __('Get 60% Off', CAL_TEXT_DOMAIN),
        'cta_link'      => '#',
        'id'            => 0, // 优先：产品 ID（传入后走联盟统一伪装链 /visit/）
        'fallback_date' => '2026-12-31',
        'icon'          => 'right_arrow_icon', // 默认使用普通右箭头，可根据需要调整
    ), $atts, 'cal_offer_footer');

    // 1. 获取统一倒计时截止日期
    $end_date = cal_get_unified_end_date($atts['fallback_date']);

    // 2. 联盟链接解析逻辑（优先产品 ID -> 兜底手动链接）
    $cta_href = '#';
    $target_id = absint($atts['id']);
    if ($target_id && function_exists('crs_build_affiliate_link')) {
        $built = crs_build_affiliate_link($target_id);
        $cta_href = (!empty($built) && $built !== '#') ? $built : '#';
    } elseif (!empty($atts['cta_link']) && $atts['cta_link'] !== '#') {
        $cta_href = $atts['cta_link'];
    }

    // 3. 委托通用渲染器生成 CTA 按钮（保证 XSS 安全 + rel 智能处理 + 图标统一）
    $button_html = cal_render_link_button(array(
        'url'    => $cta_href,
        'text'   => $atts['cta_text'],
        'class'  => 'cal-review-hero__cta',
        'newtab' => true,
        'rel'    => 'nofollow sponsored',
        'aria'   => sprintf(__('Claim offer: %s', CAL_TEXT_DOMAIN), $atts['cta_text']),
        'icon'   => $atts['icon'],
    ));

    // 4. HTML 输出构建
    ob_start(); ?>
    <footer class="cal-review-hero__footer">
        <div class="cal-glass-alert cal-glass-alert--light">
            <span class="cal-glass-alert__dot"></span>
            <?php echo __('Offer Ends in', CAL_TEXT_DOMAIN); ?>
            <span class="cal-js-countdown" data-end-date="<?php echo esc_attr($end_date); ?>">
                <?php echo __('Loading...', CAL_TEXT_DOMAIN); ?>
            </span>
        </div>

        <?php echo $button_html; ?>
    </footer>
    <?php
    return ob_get_clean();
}

if (!shortcode_exists('cal_offer_footer')) {
    add_shortcode('cal_offer_footer', 'cal_offer_footer_shortcode');
}

/**
 * =============================================================================
 * 5. 第三方钩子优化 (Hooks)
 * =============================================================================
 */

// 修改搜索按钮无障碍标签，提升 AEO 权重
add_filter('element_pack/search/toggle_attributes', function ($atts) {
    if (isset($atts['title']) && $atts['title'] === 'search-button') {
        $atts['title'] = __('Search CyberAtlas Lab Reviews', CAL_TEXT_DOMAIN);
    }
    return $atts;
}, 10, 1);


/**
 * =============================================================================
 * 6. ACF 简码全家桶（Elementor 模板兼容版）
 * 解决：1) 文章ID漂移  2) 字段类型数组被过滤  3) 多字段类型支持
 *
 * 简码列表：
 * [acf field="xxx"]              - 通用字段（文本/数值/选择等）
 * [acf field="xxx" output="url"] - 图像/链接字段输出 URL
 * [acf_img field="xxx"]          - 图像字段输出 URL
 * [acf_url field="xxx"]          - 链接/URL 字段输出纯 URL
 * [acf_link field="xxx"]         - 链接字段输出完整 <a> 标签
 * [acf_repeater field="xxx"]     - 中继器循环
 * [acf_list field="xxx" sub="x"] - 中继器单行摘要
 * [acf_debug]                    - 调试输出
 * =============================================================================
 */

/**
 * 获取当前真实文章 ID（Elementor Single Template 强制修正）
 */
function cal_get_current_post_id()
{
    // 优先级 1：主查询对象（最可靠，适用于前台任何页面）
    $id = get_queried_object_id();

    // 优先级 2：循环中的当前文章
    if (!$id) {
        $id = get_the_ID();
    }

    // 优先级 3：从 URL 参数兜底（极少用到）
    if (!$id && !empty($_GET['post'])) {
        $id = intval($_GET['post']);
    }

    return $id;
}

/**
 * 通用 ACF 字段简码
 *
 * 用法：
 * [acf field="product_name"]                    - 文本/数值
 * [acf field="score_overall"]                   - 数值
 * [acf field="product_logo" output="url"]       - 图像输出 URL
 * [acf field="affiliate_url" output="url"]      - 链接输出 URL
 * [acf field="is_recommended"]                  - 真/假
 * [acf field="supported_platforms"]             - 复选框
 * [acf field="log_policy"]                      - 选择
 */
add_shortcode('acf', function ($atts) {
    $atts = shortcode_atts([
        'field'   => '',
        'post_id' => false,
        'output'  => '',      // 'url' = 输出 URL（用于图像/链接字段）
        'format'  => 'false', // 是否让 ACF 执行格式化
    ], $atts, 'acf');

    if (empty($atts['field'])) {
        return '';
    }

    $post_id = $atts['post_id'] ? intval($atts['post_id']) : cal_get_current_post_id();
    if (!$post_id) {
        return '';
    }

    // 获取原始值
    $value = get_field($atts['field'], $post_id);

    if ($value === null || $value === false || $value === '') {
        return '';
    }

    // === 处理 output="url" 参数 ===
    if ($atts['output'] === 'url') {
        // 图像字段（返回数组）
        if (is_array($value) && isset($value['url'])) {
            return esc_url($value['url']);
        }
        // 图像字段（返回 ID）
        if (is_numeric($value)) {
            $src = wp_get_attachment_image_src(intval($value), 'full');
            if ($src) {
                return esc_url($src[0]);
            }
        }
        // 链接字段（返回数组）
        if (is_array($value) && isset($value['url'])) {
            return esc_url($value['url']);
        }
        // URL 字段（返回字符串）
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return esc_url($value);
        }
        // 相对路径
        if (is_string($value) && strpos($value, '/') === 0) {
            return esc_url($value);
        }
        return esc_url($value);
    }

    // === 让 ACF 执行格式化 ===
    if ($atts['format'] === 'true' || $atts['format'] === '1') {
        $value = get_field($atts['field'], $post_id, true);
    }

    // === 根据类型智能处理 ===

    // 布尔值
    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    // 数组类型
    if (is_array($value)) {
        // 图像字段：有 url 键
        if (isset($value['url']) && isset($value['alt'])) {
            // 默认返回 URL（保持向后兼容）
            return esc_url($value['url']);
        }

        // 链接字段：有 url 和 title 键
        if (isset($value['url']) && isset($value['title'])) {
            return esc_url($value['url']);
        }

        // 复选框/多选：字符串数组
        if (isset($value[0]) && is_string($value[0])) {
            $field_obj = get_field_object($atts['field'], $post_id);
            if ($field_obj && !empty($field_obj['choices'])) {
                $labels = [];
                foreach ($value as $v) {
                    $labels[] = $field_obj['choices'][$v] ?? $v;
                }
                return esc_html(implode(', ', $labels));
            }
            return esc_html(implode(', ', $value));
        }

        // 其他数组：返回空（如中继器需用专用简码）
        return '';
    }

    // 对象类型
    if (is_object($value)) {
        // Post Object
        if ($value instanceof WP_Post) {
            return esc_html($value->post_title);
        }
        // User Object
        if ($value instanceof WP_User) {
            return esc_html($value->display_name);
        }
        // Term Object
        if ($value instanceof WP_Term) {
            return esc_html($value->name);
        }
        return '';
    }

    // 数值：保留合理小数位
    if (is_numeric($value)) {
        // 整数直接返回
        if (floor($value) == $value) {
            return intval($value);
        }
        // 小数：去除末尾 0
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    // 字符串：直接输出
    return esc_html($value);
});

/**
 * 图像字段专用
 *
 * 用法：
 * [acf_img field="product_logo"]              - 默认 full 尺寸
 * [acf_img field="product_logo" size="medium"] - 指定尺寸
 */
add_shortcode('acf_img', function ($atts) {
    $atts = shortcode_atts([
        'field'   => '',
        'post_id' => false,
        'size'    => 'full',
    ], $atts, 'acf_img');

    if (empty($atts['field'])) {
        return '';
    }

    $post_id = $atts['post_id'] ? intval($atts['post_id']) : cal_get_current_post_id();
    if (!$post_id) {
        return '';
    }

    $image = get_field($atts['field'], $post_id);

    // ACF 返回数组（Image 对象）
    if (is_array($image)) {
        // 优先返回指定尺寸
        if (!empty($image['sizes'][$atts['size']])) {
            return esc_url($image['sizes'][$atts['size']]);
        }
        // 回退到 full URL
        if (!empty($image['url'])) {
            return esc_url($image['url']);
        }
    }

    // ACF 返回 ID
    if (is_numeric($image)) {
        $src = wp_get_attachment_image_src(intval($image), $atts['size']);
        if ($src) {
            return esc_url($src[0]);
        }
    }

    // ACF 返回 URL 字符串
    if (is_string($image) && filter_var($image, FILTER_VALIDATE_URL)) {
        return esc_url($image);
    }

    return '';
});

/**
 * URL/链接字段专用（输出纯 URL）
 *
 * 用法：
 * [acf_url field="affiliate_url"]
 * [acf_url field="product_website"]
 */
add_shortcode('acf_url', function ($atts) {
    $atts = shortcode_atts([
        'field'   => '',
        'post_id' => false,
    ], $atts, 'acf_url');

    if (empty($atts['field'])) {
        return '';
    }

    $post_id = $atts['post_id'] ? intval($atts['post_id']) : cal_get_current_post_id();
    if (!$post_id) {
        return '';
    }

    $link = get_field($atts['field'], $post_id);

    // Link 字段（返回数组）
    if (is_array($link) && !empty($link['url'])) {
        return esc_url($link['url']);
    }

    // URL 字段（返回字符串）
    if (is_string($link)) {
        if (filter_var($link, FILTER_VALIDATE_URL)) {
            return esc_url($link);
        }
        // 相对路径
        if (strpos($link, '/') === 0) {
            return esc_url($link);
        }
    }

    return '';
});

/**
 * 链接字段输出完整 <a> 标签
 *
 * 用法：
 * [acf_link field="affiliate_url" class="btn btn-primary"]
 * [acf_link field="product_website" label="Visit Site"]
 */
add_shortcode('acf_link', function ($atts) {
    $atts = shortcode_atts([
        'field'   => '',
        'post_id' => false,
        'class'   => '',
        'label'   => '',
        'target'  => '',
    ], $atts, 'acf_link');

    if (empty($atts['field'])) {
        return '';
    }

    $post_id = $atts['post_id'] ? intval($atts['post_id']) : cal_get_current_post_id();
    if (!$post_id) {
        return '';
    }

    $link = get_field($atts['field'], $post_id);

    // Link 字段（返回数组）
    if (is_array($link) && !empty($link['url'])) {
        $url    = esc_url($link['url']);
        $text   = $atts['label'] ?: ($link['title'] ?: 'Click here');
        $target = $atts['target'] ?: ($link['target'] ?? '');
        $target_attr = $target ? ' target="' . esc_attr($target) . '" rel="noopener noreferrer nofollow sponsored"' : '';
        $class_attr  = $atts['class'] ? ' class="' . esc_attr($atts['class']) . '"' : '';

        return sprintf('<a href="%s"%s%s>%s</a>', $url, $class_attr, $target_attr, esc_html($text));
    }

    // URL 字段（返回字符串）
    if (is_string($link) && filter_var($link, FILTER_VALIDATE_URL)) {
        $url    = esc_url($link);
        $text   = $atts['label'] ?: 'Click here';
        $target = $atts['target'] ? ' target="' . esc_attr($atts['target']) . '" rel="noopener noreferrer nofollow sponsored"' : '';
        $class  = $atts['class'] ? ' class="' . esc_attr($atts['class']) . '"' : '';

        return sprintf('<a href="%s"%s%s>%s</a>', $url, $class, $target, esc_html($text));
    }

    return '';
});

/**
 * 中继器字段循环
 *
 * 用法：
 * [acf_repeater field="pros"]
 *   <li>[acf_sub field="pro_item"]</li>
 * [/acf_repeater]
 */
add_shortcode('acf_repeater', function ($atts, $content = null) {
    $atts = shortcode_atts([
        'field'   => '',
        'post_id' => false,
    ], $atts, 'acf_repeater');

    if (empty($atts['field']) || empty($content)) {
        return '';
    }

    $post_id = $atts['post_id'] ? intval($atts['post_id']) : cal_get_current_post_id();
    if (!$post_id) {
        return '';
    }

    $rows = get_field($atts['field'], $post_id);
    if (!is_array($rows) || empty($rows)) {
        return '';
    }

    $output = '';

    // 存储当前行到全局变量供 [acf_sub] 使用
    global $cal_acf_current_row;

    foreach ($rows as $row) {
        $cal_acf_current_row = $row;
        $output .= do_shortcode($content);
    }

    $cal_acf_current_row = null;

    return $output;
});

/**
 * 中继器子字段
 */
add_shortcode('acf_sub', function ($atts) {
    $atts = shortcode_atts([
        'field' => '',
    ], $atts, 'acf_sub');

    if (empty($atts['field'])) {
        return '';
    }

    global $cal_acf_current_row;

    if (!is_array($cal_acf_current_row) || !isset($cal_acf_current_row[$atts['field']])) {
        return '';
    }

    $value = $cal_acf_current_row[$atts['field']];

    // 图像子字段
    if (is_array($value) && isset($value['url'])) {
        return esc_url($value['url']);
    }

    // 布尔值
    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    // 允许安全 HTML（加粗、斜体等），过滤恶意代码
    return wp_kses_post($value);
});

/**
 * 中继器单行摘要（不需要循环）
 *
 * 用法：
 * [acf_list field="pros" sub="pro_item"]              - 输出所有项，逗号分隔
 * [acf_list field="pros" sub="pro_item" sep=" | "]    - 自定义分隔符
 */
add_shortcode('acf_list', function ($atts) {
    $atts = shortcode_atts([
        'field'   => '',
        'post_id' => false,
        'sub'     => '',
        'sep'     => ', ',
    ], $atts, 'acf_list');

    if (empty($atts['field']) || empty($atts['sub'])) {
        return '';
    }

    $post_id = $atts['post_id'] ? intval($atts['post_id']) : cal_get_current_post_id();
    if (!$post_id) {
        return '';
    }

    $rows = get_field($atts['field'], $post_id);
    if (!is_array($rows) || empty($rows)) {
        return '';
    }

    $items = [];
    foreach ($rows as $row) {
        if (is_array($row) && isset($row[$atts['sub']]) && !empty($row[$atts['sub']])) {
            $items[] = $row[$atts['sub']];
        }
    }

    return esc_html(implode($atts['sep'], $items));
});

/**
 * 条件显示：字段有值时才显示内容
 *
 * 用法：
 * [acf_if field="current_discount"]
 *   <span class="discount">[acf field="current_discount"]</span>
 * [/acf_if]
 */
add_shortcode('acf_if', function ($atts, $content = null) {
    $atts = shortcode_atts([
        'field'   => '',
        'post_id' => false,
        'value'   => '',      // 可选：匹配特定值
        'compare' => '=',     // 可选：比较运算符 =, !=, >, <, >=, <=
    ], $atts, 'acf_if');

    if (empty($atts['field']) || empty($content)) {
        return '';
    }

    $post_id = $atts['post_id'] ? intval($atts['post_id']) : cal_get_current_post_id();
    if (!$post_id) {
        return '';
    }

    $field_value = get_field($atts['field'], $post_id);

    // 基本存在性检查
    if ($atts['value'] === '') {
        // 只检查字段是否有值
        $has_value = !empty($field_value) || $field_value === '0' || $field_value === 0;
        return $has_value ? do_shortcode($content) : '';
    }

    // 值比较
    $compare = $atts['compare'];
    $target  = $atts['value'];
    $result  = false;

    switch ($compare) {
        case '=':
        case '==':
            $result = ($field_value == $target);
            break;
        case '!=':
        case '<>':
            $result = ($field_value != $target);
            break;
        case '>':
            $result = (floatval($field_value) > floatval($target));
            break;
        case '<':
            $result = (floatval($field_value) < floatval($target));
            break;
        case '>=':
            $result = (floatval($field_value) >= floatval($target));
            break;
        case '<=':
            $result = (floatval($field_value) <= floatval($target));
            break;
    }

    return $result ? do_shortcode($content) : '';
});

/**
 * 启用 Elementor HTML Widget 中的简码解析
 */
add_filter('elementor/widget/render_content', function ($content, $widget) {
    if ('html' === $widget->get_name()) {
        $content = do_shortcode($content);
    }
    return $content;
}, 10, 2);

/**
 * 启用 ACF 简码（ACF 6.0+ 默认禁用）
 * 注意：我们已自定义简码，此 filter 主要确保兼容性
 */
add_filter('acf/shortcode/allow_in_block_themes_outside_content', '__return_true');

/**
 * =============================================================================
 * 7. WordPress 原生字段简码
 * =============================================================================
 */

/**
 * 文章标题 [post_title]
 */
add_shortcode('post_title', function ($atts) {
    $post_id = cal_get_current_post_id();
    if (!$post_id) {
        return '';
    }
    return esc_html(get_the_title($post_id));
});

/**
 * 作者名称 [post_author]
 */
add_shortcode('post_author', function ($atts) {
    $post_id = cal_get_current_post_id();
    if (!$post_id) {
        return '';
    }
    $post = get_post($post_id);
    if (!$post) {
        return '';
    }
    return esc_html(get_the_author_meta('display_name', $post->post_author));
});

/**
 * 作者链接 [post_author_link]
 */
add_shortcode('post_author_link', function ($atts) {
    $post_id = cal_get_current_post_id();
    if (!$post_id) {
        return '';
    }
    $post = get_post($post_id);
    if (!$post) {
        return '';
    }

    $name = get_the_author_meta('display_name', $post->post_author);
    $url  = get_author_posts_url($post->post_author);

    return sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($name));
});

/**
 * 发布日期 [post_date format="Y-m-d"]
 */
add_shortcode('post_date', function ($atts) {
    $atts = shortcode_atts([
        'format' => 'M j, Y', // 默认: Jun 5, 2026
    ], $atts);

    $post_id = cal_get_current_post_id();
    if (!$post_id) {
        return '';
    }

    return esc_html(get_the_date($atts['format'], $post_id));
});

/**
 * 修改日期 [post_modified format="M Y"]
 */
add_shortcode('post_modified', function ($atts) {
    $atts = shortcode_atts([
        'format' => 'M Y', // 默认: Jun 2026
    ], $atts);

    $post_id = cal_get_current_post_id();
    if (!$post_id) {
        return '';
    }

    return esc_html(get_the_modified_date($atts['format'], $post_id));
});

/**
 * 当前年份 [year]
 */
add_shortcode('year', function () {
    return date('Y');
});

/**
 * 自动内联 SVG [crs_inline_svg field="product_logo"]
 * 上传 SVG → 输出内联代码，非 SVG 回退 <img>
 */
add_shortcode('crs_inline_svg', function ($atts) {
    $atts = shortcode_atts([
        'field'   => '',
        'post_id' => false,
        'class'   => '', // 可选：给 SVG 添加 class
    ], $atts);

    if (empty($atts['field'])) {
        return '';
    }

    $post_id = $atts['post_id'] ? intval($atts['post_id']) : cal_get_current_post_id();
    if (!$post_id) {
        return '';
    }

    $image = get_field($atts['field'], $post_id);
    if (!$image) {
        return '';
    }

    // 获取附件 ID
    $attachment_id = null;
    if (is_numeric($image)) {
        $attachment_id = intval($image);
    } elseif (is_array($image) && !empty($image['id'])) {
        $attachment_id = intval($image['id']);
    } elseif (is_array($image) && !empty($image['ID'])) {
        $attachment_id = intval($image['ID']);
    }

    // 获取文件路径
    $file_path = $attachment_id ? get_attached_file($attachment_id) : '';

    // 检查是否为 SVG
    if ($file_path && file_exists($file_path) && strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) === 'svg') {
        $svg_content = file_get_contents($file_path);

        // 移除 XML 声明
        $svg_content = preg_replace('/<\?xml[^>]*\?>/is', '', $svg_content);

        // 移除 DOCTYPE
        $svg_content = preg_replace('/<!DOCTYPE[^>]*>/is', '', $svg_content);

        // 移除注释
        $svg_content = preg_replace('/<!--.*?-->/s', '', $svg_content);

        // 添加自定义 class（如果提供）
        if (!empty($atts['class'])) {
            $svg_content = preg_replace(
                '/<svg\s/i',
                '<svg class="' . esc_attr($atts['class']) . '" ',
                $svg_content,
                1
            );
        }

        return trim($svg_content);
    }

    // 非 SVG 回退到 img
    $url = '';
    if (is_array($image) && !empty($image['url'])) {
        $url = $image['url'];
    } elseif (is_string($image)) {
        $url = $image;
    } elseif ($attachment_id) {
        $url = wp_get_attachment_url($attachment_id);
    }

    if ($url) {
        return sprintf('<img src="%s" alt="Logo" loading="lazy">', esc_url($url));
    }

    return '';
});

/**
 * =============================================================================
 * 8. Footer JS折叠引入
 * =============================================================================
 */

// 引入JS文件到/JS下
function cal_enqueue_footer_accordion()
{
    wp_enqueue_script(
        'cal-footer-accordion',
        get_stylesheet_directory_uri() . '/js/footer-accordion.js',
        array(),
        '1.0.0',
        true // 在 footer 加载
    );
}
add_action('wp_enqueue_scripts', 'cal_enqueue_footer_accordion');


/**
 * =============================================================================
 * 9. 图标库映射
 * =============================================================================
 */

if (!function_exists('cal_get_icon')) {
    /**
     * 获取预定义 SVG 图标
     */
    function cal_get_icon($key, $class = '')
    {
        $icons = [
        // 亮点总结标题SVG图标
        'award' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-award"><path d="M0 0h24v24H0z" stroke="none"/><path d="M6 9a6 6 0 1 0 12 0A6 6 0 1 0 6 9"/><path d="m12 15 3.4 5.89 1.598-3.233 3.598.232-3.4-5.889M6.802 12l-3.4 5.89L7 17.657l1.598 3.232 3.4-5.889"/></svg>',

        'servers' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-server-2"><path d="M0 0h24v24H0z" stroke="none"/><path d="M3 7a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v2a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3zm0 8a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v2a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3zm4-7v.01M7 16v.01M11 8h6m-6 8h6"/></svg>',

        // 地球网络图标
        'countries' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 21a9 9 0 1 0 0-18m0 18a9 9 0 1 1 0-18m0 18c2.761 0 3.941-5.163 3.941-9S14.761 3 12 3m0 18c-2.761 0-3.941-5.163-3.941-9S9.239 3 12 3M3.5 9h17m-17 6h17"/></svg>',

        // 速度闪电图标
        'speed' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-bolt"><path d="M0 0h24v24H0z" stroke="none"/><path d="M13 3v7h6l-8 11v-7H5z"/></svg>',

        'dedicated_ip' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"><path d="M2 12h3m14 0h3M12 22v-3m0-14V2"/><path stroke-linejoin="round" d="M10 12h4m-2 2v-4"/><path d="M7 3.338A9.95 9.95 0 0 1 12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12c0-1.821.487-3.53 1.338-5"/></g></svg>',

        // 安全盾牌图标
        'shield' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 10.417c0-3.198 0-4.797.378-5.335.377-.537 1.88-1.052 4.887-2.081l.573-.196C10.405 2.268 11.188 2 12 2s1.595.268 3.162.805l.573.196c3.007 1.029 4.51 1.544 4.887 2.081C21 5.62 21 7.22 21 10.417v1.574c0 5.638-4.239 8.375-6.899 9.536C13.38 21.842 13.02 22 12 22s-1.38-.158-2.101-.473C7.239 20.365 3 17.63 3 11.991z"/><path d="m3 11 9-3 9 3m-9-9v19.5"/></g></svg>',

        // 锁/隐私图标
        'encryption' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-lock"><path d="M0 0h24v24H0z" stroke="none"/><path d="M5 13a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2z"/><path d="M11 16a1 1 0 1 0 2 0 1 1 0 0 0-2 0m-3-5V7a4 4 0 1 1 8 0v4"/></svg>',

        'protocol' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-broadcast"><path d="M0 0h24v24H0z" stroke="none"/><path d="M18.364 19.364a9 9 0 1 0-12.728 0"/><path d="M15.536 16.536a5 5 0 1 0-7.072 0"/><path d="M11 13a1 1 0 1 0 2 0 1 1 0 1 0-2 0"/></svg>',

        'nologs' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2m-7-9 4 4m0-4-4 4"/></g></svg>',

        'audit' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-rosette-discount-check"><path d="M0 0h24v24H0z" stroke="none"/><path d="M5 7.2A2.2 2.2 0 0 1 7.2 5h1a2.2 2.2 0 0 0 1.55-.64l.7-.7a2.2 2.2 0 0 1 3.12 0l.7.7c.412.41.97.64 1.55.64h1a2.2 2.2 0 0 1 2.2 2.2v1c0 .58.23 1.138.64 1.55l.7.7a2.2 2.2 0 0 1 0 3.12l-.7.7a2.2 2.2 0 0 0-.64 1.55v1a2.2 2.2 0 0 1-2.2 2.2h-1a2.2 2.2 0 0 0-1.55.64l-.7.7a2.2 2.2 0 0 1-3.12 0l-.7-.7a2.2 2.2 0 0 0-1.55-.64h-1a2.2 2.2 0 0 1-2.2-2.2v-1a2.2 2.2 0 0 0-.64-1.55l-.7-.7a2.2 2.2 0 0 1 0-3.12l.7-.7A2.2 2.2 0 0 0 5 8.2z"/><path d="m9 12 2 2 4-4"/></svg>',

        'e2ee' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 1200 1200"><path fill="currentColor" d="m320.883 1200 400.642-400.664L600.234 678.07l77.836-77.836 121.266 121.289L1200 320.883 879.117 0l-400.64 400.664L599.766 521.93l-77.836 77.836-121.266-121.289L0 879.117zm0-156.619L156.628 879.127l244.031-244.031 42.973 42.974-78.556 78.555 78.31 78.31 78.557-78.556 42.973 42.974zm478.476-478.477-42.974-42.974 78.557-78.555-78.311-78.31-78.556 78.556-42.984-42.984 244.031-244.055 164.273 164.264-244.052 244.033z"/></svg>',

        'opensource' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-code"><path d="M0 0h24v24H0z" stroke="none"/><path d="m7 8-4 4 4 4m10-8 4 4-4 4M14 4l-4 16"/></svg>',

        'killswitch' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 6a7.75 7.75 0 1 0 10 0m-5-2v8"/></svg>',

        'multihop' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-repeat"><path d="M0 0h24v24H0z" stroke="none"/><path d="M4 12V9a3 3 0 0 1 3-3h13m-3-3 3 3-3 3m3 3v3a3 3 0 0 1-3 3H4m3 3-3-3 3-3"/></svg>',

        'split' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-git-fork"><path d="M0 0h24v24H0z" stroke="none"/><path d="M10 18a2 2 0 1 0 4 0 2 2 0 1 0-4 0M5 6a2 2 0 1 0 4 0 2 2 0 1 0-4 0m10 0a2 2 0 1 0 4 0 2 2 0 1 0-4 0"/><path d="M7 8v2a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V8m-5 4v4"/></svg>',

        'stealth' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-ghost"><path d="M0 0h24v24H0z" stroke="none"/><path d="M5 11a7 7 0 0 1 14 0v7a1.78 1.78 0 0 1-3.1 1.4 1.65 1.65 0 0 0-2.6 0 1.65 1.65 0 0 1-2.6 0 1.65 1.65 0 0 0-2.6 0A1.78 1.78 0 0 1 5 18zm5-1h.01M14 10h.01"/><path d="M10 14a3.5 3.5 0 0 0 4 0"/></svg>',

        'streaming' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-movie"><path d="M0 0h24v24H0z" stroke="none"/><path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm4-2v16m8-16v16M4 8h4m-4 8h4m-4-4h16m-4-4h4m-4 8h4"/></svg>',

        'torrenting' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-download"><path d="M0 0h24v24H0z" stroke="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M7 11l5 5 5-5m-5-7v12"/></svg>',

        'adblock' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-forbid"><path d="M0 0h24v24H0z" stroke="none"/><path d="M3 12a9 9 0 1 0 18 0 9 9 0 1 0-18 0m6-3 6 6"/></svg>',

        'firewall' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-wall"><path d="M0 0h24v24H0z" stroke="none"/><path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm0 2h16m0 4H4m0 4h16M9 4v4m5 0v4m-6 0v4m8-4v4m-5 0v4"/></svg>',

        // KEY图标
        'password' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-key"><path d="M0 0h24v24H0z" stroke="none"/><path d="m16.555 3.843 3.602 3.602a2.877 2.877 0 0 1 0 4.069l-2.643 2.643a2.877 2.877 0 0 1-4.069 0l-.301-.301-6.558 6.558a2 2 0 0 1-1.239.578L5.172 21H4a1 1 0 0 1-.993-.883L3 20v-1.172a2 2 0 0 1 .467-1.284l.119-.13L4 17h2v-2h2v-2l2.144-2.144-.301-.301a2.877 2.877 0 0 1 0-4.069l2.643-2.643a2.877 2.877 0 0 1 4.069 0M15 9h.01"/></svg>',

        'twofa' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-fingerprint"><path d="M0 0h24v24H0z" stroke="none"/><path d="M18.9 7a8 8 0 0 1 1.1 5v1a6 6 0 0 0 .8 3M8 11a4 4 0 0 1 8 0v1a10 10 0 0 0 2 6"/><path d="M12 11v2a14 14 0 0 0 2.5 8M8 15a18 18 0 0 0 1.8 6m-4.9-2a22 22 0 0 1-.9-7v-1a8 8 0 0 1 12-6.95"/></svg>',

        'autofill' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit"><path d="M0 0h24v24H0z" stroke="none"/><path d="M7 7H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2v-1"/><path d="M20.385 6.585a2.1 2.1 0 0 0-2.97-2.97L9 12v3h3zM16 5l3 3"/></svg>',

        // 数据泄露/监控/眼睛图标
        'breach' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-scan-eye"><path d="M0 0h24v24H0z" stroke="none"/><path d="M7 12q5-7 10 0M7 12q5 7 10 0m-5 0h-.01M3 7V5a2 2 0 0 1 2-2h2M3 17v2a2 2 0 0 0 2 2h2M17 3h2a2 2 0 0 1 2 2v2m-4 14h2a2 2 0 0 0 2-2v-2"/></svg>',

        'malware' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-virus"><path d="M0 0h24v24H0z" stroke="none"/><path d="M7 12a5 5 0 1 0 10 0 5 5 0 1 0-10 0m5-5V3m-1 0h2m2.536 5.464 2.828-2.828m-.707-.707 1.414 1.414M17 12h4m0-1v2m-5.465 2.536 2.829 2.828m.707-.707-1.414 1.414M12 17v4m1 0h-2m-2.535-5.464-2.829 2.828m.707.707L4.93 17.657M7 12H3m0 1v-2m5.464-2.536L5.636 5.636m-.707.707L6.343 4.93"/></svg>',

        'ransomware' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-alert-triangle"><path d="M0 0h24v24H0z" stroke="none"/><path d="M12 9v4m-1.637-9.409L2.257 17.125a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636-2.87L13.637 3.59a1.914 1.914 0 0 0-3.274 0M12 16h.01"/></svg>',

        'cloud' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-cloud-download"><path d="M0 0h24v24H0z" stroke="none"/><path d="M19 18a3.5 3.5 0 0 0 0-7h-1A5 4.5 0 0 0 7 9a4.6 4.4 0 0 0-2.1 8.4M12 13v9m-3-3 3 3 3-3"/></svg>',

        'devices' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-devices"><path d="M0 0h24v24H0z" stroke="none"/><path d="M13 9a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1h-6a1 1 0 0 1-1-1z"/><path d="M18 8V5a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h9m3-9h2"/></svg>',

        'support' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-headset"><path d="M0 0h24v24H0z" stroke="none"/><path d="M4 14v-3a8 8 0 1 1 16 0v3m-2 5c0 1.657-2.686 3-6 3"/><path d="M4 14a2 2 0 0 1 2-2h1a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm11 0a2 2 0 0 1 2-2h1a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2z"/></svg>',

        'moneyback' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-moneybag"><path d="M0 0h24v24H0z" stroke="none"/><path d="M9.5 3h5A1.5 1.5 0 0 1 16 4.5 3.5 3.5 0 0 1 12.5 8h-1A3.5 3.5 0 0 1 8 4.5 1.5 1.5 0 0 1 9.5 3"/><path d="M4 17v-1a8 8 0 1 1 16 0v1a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4"/></svg>',

        'free_tier' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-gift"><path d="M0 0h24v24H0z" stroke="none"/><path d="M3 9a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1zm9-1v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7m2.5-4a2.5 2.5 0 0 1 0-5A4.8 8 0 0 1 12 8a4.8 8 0 0 1 4.5-5 2.5 2.5 0 0 1 0 5"/></svg>',

        'price' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-tag"><path d="M0 0h24v24H0z" stroke="none"/><path d="M6.5 7.5a1 1 0 1 0 2 0 1 1 0 1 0-2 0"/><path d="M3 6v5.172a2 2 0 0 0 .586 1.414l7.71 7.71a2.41 2.41 0 0 0 3.408 0l5.592-5.592a2.41 2.41 0 0 0 0-3.408l-7.71-7.71A2 2 0 0 0 11.172 3H6a3 3 0 0 0-3 3"/></svg>',

        'check' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M21.25 5.973a.75.75 0 0 0-.516.226L9.014 17.94l-5.739-5.744a.75.75 0 0 0-1.062 1.06l6.271 6.274a.75.75 0 0 0 1.06 0l12.25-12.27a.75.75 0 0 0-.544-1.286"/></svg>',

        // 得分明细标题SVG图标
        'scoring-breakdown' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-antenna-bars-5"><path d="M0 0h24v24H0z" stroke="none"/><path d="M6 18v-3m4 3v-6m4 6V9m4 9V6"/></svg>',

        // FAQ模块标题SVG图标
        'title_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shield-question"><path d="M0 0h24v24H0z" stroke="none"/><path d="M15.065 19.732c-.95.557-1.98.986-3.065 1.268A12 12 0 0 1 3.5 6 12 12 0 0 0 12 3a12 12 0 0 0 8.5 3c.51 1.738.617 3.55.333 5.303M19 22v.01"/><path d="M19 19a2.003 2.003 0 0 0 .914-3.782 1.98 1.98 0 0 0-2.414.483"/></svg>',

        // FAQ模块标题折叠SVG图标
        'arrow_icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" aria-hidden="true" width="24" height="24"><path d="M143 352.3 7 216.3c-9.4-9.4-9.4-24.6 0-33.9l22.6-22.6c9.4-9.4 24.6-9.4 33.9 0l96.4 96.4 96.4-96.4c9.4-9.4 24.6-9.4 33.9 0l22.6 22.6c9.4 9.4 9.4 24.6 0 33.9l-136 136c-9.2 9.4-24.4 9.4-33.8 0"/></svg>',

        // 优缺点模块：优点标题SVG图标
        'pro_title_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-thumb-up"><path d="M0 0h24v24H0z" stroke="none"/><path d="M7 11v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1za4 4 0 0 0 4-4V6a2 2 0 0 1 4 0v5h3a2 2 0 0 1 2 2l-1 5a2 3 0 0 1-2 2h-7a3 3 0 0 1-3-3"/></svg>',

        // 优缺点模块：缺点标题SVG图标
        'con_title_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-thumb-down"><path d="M0 0h24v24H0z" stroke="none"/><path d="M7 13V5a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1za4 4 0 0 1 4 4v1a2 2 0 0 0 4 0v-5h3a2 2 0 0 0 2-2l-1-5a2 3 0 0 0-2-2h-7a3 3 0 0 0-3 3"/></svg>',

        // 优缺点模块：最适合标题SVG图标
        'best_title_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-users"><path d="M0 0h24v24H0z" stroke="none"/><path d="M5 7a4 4 0 1 0 8 0 4 4 0 1 0-8 0M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2m1-17.87a4 4 0 0 1 0 7.75M21 21v-2a4 4 0 0 0-3-3.85"/></svg>',

        // 带圆圈对勾SVG图标
        'pro_list_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check"><path d="M0 0h24v24H0z" stroke="none"/><path d="M3 12a9 9 0 1 0 18 0 9 9 0 1 0-18 0"/><path d="m9 12 2 2 4-4"/></svg>',

        // 优缺点模块：缺点列表SVG图标
        'con_list_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-alert-circle"><path d="M0 0h24v24H0z" stroke="none"/><path d="M3 12a9 9 0 1 0 18 0 9 9 0 0 0-18 0m9-4v4m0 4h.01"/></svg>',

        // 优缺点模块：最适合列表SVG图标
        'best_list_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-current-location"><path d="M0 0h24v24H0z" stroke="none"/><path d="M9 12a3 3 0 1 0 6 0 3 3 0 1 0-6 0"/><path d="M4 12a8 8 0 1 0 16 0 8 8 0 1 0-16 0m8-10v2m0 16v2m8-10h2M2 12h2"/></svg>',

        // 各模块所需的实心星星SVG图标
        'filled_star_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="icon icon-tabler icons-tabler-filled icon-tabler-star"><path d="M0 0h24v24H0z" fill="none"/><path d="m8.243 7.34-6.38.925-.113.023a1 1 0 0 0-.44 1.684l4.622 4.499-1.09 6.355-.013.11a1 1 0 0 0 1.464.944l5.706-3 5.693 3 .1.046a1 1 0 0 0 1.352-1.1l-1.091-6.355 4.624-4.5.078-.085a1 1 0 0 0-.633-1.62l-6.38-.926-2.852-5.78a1 1 0 0 0-1.794 0z"/></svg>',

        // 内页模块meta文章更新SVG图标
        'article_update_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-calendar-month"><path d="M0 0h24v24H0z" stroke="none"/><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm12-4v4M8 3v4m-4 4h16M8 14v4m4-4v4m4-4v4"/></svg>',

        // 内页模块meta文章作者SVG图标
        'article_author_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user"><path d="M0 0h24v24H0z" stroke="none"/><path d="M8 7a4 4 0 1 0 8 0 4 4 0 0 0-8 0M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/></svg>',

        // 内页模块折扣SVG图标
        'gift_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M0 0h24v24H0z" stroke="none"/><path d="M3 9a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1zm9-1v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7m2.5-4a2.5 2.5 0 0 1 0-5A4.8 8 0 0 1 12 8a4.8 8 0 0 1 4.5-5 2.5 2.5 0 0 1 0 5"/></svg>',

        // 内页Toc模块子弹头图标
        'toc_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-menu-2"><path d="M0 0h24v24H0z" stroke="none"/><path d="M4 6h16M4 12h16M4 18h16"/></svg>',

        // 内页Toc模块倒三角图标
        'chevron_down' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-down"><path d="M0 0h24v24H0z" stroke="none"/><path d="m6 9 6 6 6-6"/></svg>',

        // 面包屑导航主页图标
        'breadcrumb_icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-home"><path d="M0 0h24v24H0z" stroke="none"/><path d="M5 12H3l9-9 9 9h-2M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/><path d="M9 21v-6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v6"/></svg>',

        // 测评页功能参数图标
        'technical_snapshot_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-device-analytics"><path d="M0 0h24v24H0z" stroke="none"/><path d="M3 5a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1zm4 15h10m-8-4v4m6-4v4"/><path d="m8 12 3-3 2 2 3-3"/></svg>',

        // 测评页CTA-S灯泡图标
        'cta_s_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-bulb"><path d="M0 0h24v24H0z" stroke="none"/><path d="M3 12h1m8-9v1m8 8h1M5.6 5.6l.7.7m12.1-.7-.7.7M9 16a5 5 0 1 1 6 0 3.5 3.5 0 0 0-1 3 2 2 0 0 1-4 0 3.5 3.5 0 0 0-1-3m.7 1h4.6"/></svg>',

        // 各场景右侧箭头图标
        'right_arrow_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-narrow-right"><path d="M0 0h24v24H0z" stroke="none"/><path d="M5 12h14m-4 4 4-4m-4-4 4 4"/></svg>',

        // 测评页CTA-L图标
        'cta_l_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trophy"><path d="M0 0h24v24H0z" stroke="none"/><path d="M8 21h8m-4-4v4M7 4h10m0 0v8a5 5 0 0 1-10 0V4M3 9a2 2 0 1 0 4 0 2 2 0 1 0-4 0m14 0a2 2 0 1 0 4 0 2 2 0 1 0-4 0"/></svg>',

        // 测评页CTA-L列表或其他场景对勾图标
        'crs_check_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check"><path d="M0 0h24v24H0z" stroke="none"/><path d="m5 12 5 5L20 7"/></svg>',

        // 测评页评分解释图标
        'info_icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-info-circle"><path d="M0 0h24v24H0z" stroke="none"/><path d="M3 12a9 9 0 1 0 18 0 9 9 0 0 0-18 0m9-3h.01"/><path d="M11 12h1v4h1"/></svg>',

        // 首页CTA带圆圈向右箭头图标
        'cta-arrow-icon' => '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 512 512" fill="currentColor"><path d="M256 8c137 0 248 111 248 248S393 504 256 504 8 393 8 256 119 8 256 8m113.9 231L234.4 103.5c-9.4-9.4-24.6-9.4-33.9 0l-17 17c-9.4 9.4-9.4 24.6 0 33.9L285.1 256 183.5 357.6c-9.4 9.4-9.4 24.6 0 33.9l17 17c9.4 9.4 24.6 9.4 33.9 0L369.9 273c9.4-9.4 9.4-24.6 0-34"/></svg>',
        ];

        $svg = $icons[$key] ?? $icons['check'];

        // 统一追加基础类 .cal-icon
        $base_class = 'cal-icon';
        $all_classes = $class ? $base_class . ' ' . esc_attr($class) : $base_class;

        // 智能插入 class
        if (strpos($svg, 'class="') !== false) {
            $svg = str_replace('class="', 'class="' . $all_classes . ' ', $svg);
        } else {
            $svg = str_replace('<svg ', '<svg class="' . $all_classes . '" ', $svg);
        }

        return $svg;
    }
}

if (!shortcode_exists('cal_icon')) {
    add_shortcode('cal_icon', function ($atts) {
        $atts = shortcode_atts([
            'key'   => 'check',
            'class' => '',
        ], $atts, 'cal_icon');

        return cal_get_icon($atts['key'], $atts['class']);
    });
}

if (!shortcode_exists('cal_icon_dynamic')) {
    add_shortcode('cal_icon_dynamic', function ($atts) {
        $atts = shortcode_atts([
            'field' => '',
            'class' => '',
        ], $atts);

        if (empty($atts['field'])) {
            return '';
        }

        global $cal_acf_current_row;

        $key = 'check';
        if (is_array($cal_acf_current_row) && isset($cal_acf_current_row[$atts['field']])) {
            $key = $cal_acf_current_row[$atts['field']];
        }

        return cal_get_icon($key, $atts['class']);
    });
}


/**
 * =============================================================================
 * 10. 产品数据统一获取（单次读取 + 内存缓存）
 * =============================================================================
 * 【设计模式】：享元模式 (Flyweight) / 运行时单例缓存
 * 【核心职责】：统一收拢当前评测文章关联的所有 ACF 商业字段。
 * 【性能优化】：利用 PHP static 内存缓存，确保同一次请求中多模块调用时 O(1) 数据库 I/O。
 */
if (!function_exists('crs_get_product_data')) {
    function crs_get_product_data($post_id = null)
    {
        static $cache = [];

        if ($post_id === null) {
            $post_id = get_the_ID();
        }

        if (!$post_id) {
            return null;
        }

        if (isset($cache[$post_id])) {
            return $cache[$post_id];
        }

        $cache[$post_id] = [
            // --- 核心标识与评级数据 ---
            'logo_id' => get_field('product_logo', $post_id),
            'name' => get_field('product_name', $post_id) ?: get_the_title($post_id),
            'score' => floatval(get_field('score_overall', $post_id) ?: 0),

            // --- 商业动线与价格数据 ---
            'currency' => get_field('currency_symbol', $post_id) ?: 'USD',
            'price' => get_field('price_lowest', $post_id) ?: '',
            'discount' => get_field('current_discount', $post_id) ?: '',
            // 🏹 核心追加：精准对接 ACF 后台的计费周期详细说明（如：Billed every 28 months）
            'billing_details' => get_field('billing_details', $post_id) ?: '',
            // 架构重解耦：此处保持读取后台物理数据，不在此处直接硬编码拼装
            'raw_url' => get_field('affiliate_url', $post_id) ?: '#',

            // --- 运营排版与免责声明 ---
            'is_recommended' => get_field('is_recommended', $post_id) ?: false,
            'summary' => get_field('product_summary', $post_id) ?: '',
            'price_period' => get_field('price_period', $post_id) ?: '/mo',
            'disclaimer_url' => get_field('disclaimer_url', $post_id) ?: '/disclaimer/',

            // --- 评测核心参数指标 (Specs) ---
            'servers' => get_field('server_count', $post_id) ?: '',
            'countries' => get_field('country_count', $post_id) ?: '',
            'devices' => get_field('device_limit', $post_id) ?: '',
            'refund' => get_field('refund_days', $post_id) ?: '',
            'logs' => get_field('log_policy', $post_id) ?: '',
        ];

        return $cache[$post_id];
    }
}


/**
 * ============================================================================================================================
 * 11. Hero模块简码 - [crs_hero_section]
 * ============================================================================================================================
 * 【核心变革】：对接 CAL_CRS_URL_Builder 工厂，实现前端 CTA 商业链接完美通水，向用户隐蔽真实联盟长链接。
 */
if (!function_exists('crs_hero_section_shortcode')) {
    function crs_hero_section_shortcode()
    {
        // 1. 获取当前上下文的 Post ID 并调取产品聚合数据
        $post_id = get_the_ID();
        $data = crs_get_product_data($post_id);

        // 防御性熔断
        if (!$data) {
            return '';
        }

        // 2. 基础元数据提取
        $post_title    = get_the_title($post_id);
        $author_id     = get_post_field('post_author', $post_id);
        $post_author   = get_the_author_meta('display_name', $author_id);
        $post_modified = get_the_modified_date('M Y', $post_id);

        // 3. 评分星级换算逻辑
        $full_stars  = min(5, max(0, (int) round($data['score'] / 2)));
        $empty_stars = 5 - $full_stars;
        $has_icon    = function_exists('cal_get_icon');

        // 核心通水缝合点：调用标准重定向引擎出口工厂，生成完全隔离伪装的内链路由（例如：/visit/expressvpn/）
        $obfuscated_affiliate_url = CAL_CRS_URL_Builder::build($post_id);

        // 4. 货币符号处理：如果后端返回 "USD"，自动转换为 "$"
        $raw_currency = !empty($data['currency']) ? trim($data['currency']) : '$';
        $currency_symbol = (strtoupper($raw_currency) === 'USD') ? '$' : $raw_currency;

        // 5. 免责声明 URL Fallback 处理
        $disclaimer_url = !empty($data['disclaimer_url']) ? $data['disclaimer_url'] : home_url('/affiliate-disclosure/');

        // 6. Logo 渲染引擎
        $logo_html = '';
        if (!empty($data['logo_id'])) {
            $logo_data = $data['logo_id'];
            if (is_array($logo_data)) {
                $logo_file_path = get_attached_file($logo_data['ID']);
                if ($logo_file_path && file_exists($logo_file_path) && pathinfo($logo_file_path, PATHINFO_EXTENSION) === 'svg') {
                    $logo_html = file_get_contents($logo_file_path);
                } else {
                    $logo_url = isset($logo_data['url']) ? $logo_data['url'] : '';
                    $logo_alt = isset($logo_data['alt']) ? $logo_data['alt'] : $post_title;
                    $logo_html = '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($logo_alt) . '" loading="eager" />';
                }
            } else {
                $logo_html = $logo_data;
            }
        }

        ob_start();
        ?>
        <section class="crs-hero">
            <div class="crs-hero__inner">

                <article class="crs-hero__content">

                    <header class="crs-hero__header">
                        <?php if ($logo_html): ?>
                            <figure class="crs-hero__logo"><?php echo $logo_html; ?></figure>
                        <?php endif; ?>

                        <div class="crs-hero__heading">
                            <?php if (!empty($data['is_recommended'])): ?>
                                <span class="crs-hero__badge">
                                    <?php echo $has_icon ? cal_get_icon('filled_star_icon') : '★'; ?>
                                    <?php _e("Editor's Choice", 'cyberatlaslab'); ?>
                                </span>
                            <?php endif; ?>

                            <!-- H1 作为正文唯一核心大纲 -->
                            <h1 class="crs-hero__title"><?php echo esc_html($post_title); ?></h1>

                            <div class="crs-hero__rating">
                                <div class="crs-hero__stars" aria-hidden="true">
                                    <?php for ($i = 0; $i < $full_stars; $i++): ?>
                                        <span class="crs-hero__star crs-hero__star--gold"><?php echo $has_icon ? cal_get_icon('filled_star_icon') : '★'; ?></span>
                                    <?php endfor; ?>
                                    <?php for ($i = 0; $i < $empty_stars; $i++): ?>
                                        <span class="crs-hero__star crs-hero__star--grey"><?php echo $has_icon ? cal_get_icon('filled_star_icon') : '★'; ?></span>
                                    <?php endfor; ?>
                                </div>

                                <data class="crs-hero__score" value="<?php echo esc_attr($data['score']); ?>"
                                    aria-label="<?php printf(esc_attr__('Overall rating: %s out of 10', 'cyberatlaslab'), number_format($data['score'], 1)); ?>">
                                    <?php echo number_format($data['score'], 1); ?> / 10
                                </data>
                            </div>
                        </div>
                    </header>

                    <?php if (!empty($data['summary'])): ?>
                        <p class="crs-hero__summary"><?php echo esc_html($data['summary']); ?></p>
                    <?php endif; ?>

                    <footer class="crs-hero__meta">
                        <span class="crs-hero__meta-item">
                            <?php echo $has_icon ? cal_get_icon('article_update_icon') : ''; ?>
                            <span class="crs-hero__meta-label"><?php _e('Updated:', 'cyberatlaslab'); ?></span>
                            <time datetime="<?php echo get_the_modified_date('c', $post_id); ?>"><?php echo esc_html($post_modified); ?></time>
                        </span>
                        <span class="crs-hero__meta-item">
                            <?php echo $has_icon ? cal_get_icon('article_author_icon') : ''; ?>
                            <a href="<?php echo esc_url(home_url('/author/cyberatlaslab/')); ?>" class="crs-hero__author" rel="author">
                                <?php echo esc_html($post_author); ?>
                            </a>
                        </span>
                    </footer>

                </article>

                <aside class="crs-hero__cta" aria-label="<?php esc_attr_e('Pricing and Offer', 'cyberatlaslab'); ?>">
                    <div class="crs-hero__cta-score"><?php echo number_format($data['score'], 1); ?></div>
                    <div class="crs-hero__cta-label"><?php _e('Overall Score', 'cyberatlaslab'); ?></div>

                    <div class="crs-hero__cta-price">
                        <span class="crs-hero__cta-price-from"><?php _e('From', 'cyberatlaslab'); ?></span>
                        <span class="crs-hero__cta-price-currency"><?php echo esc_html($currency_symbol); ?></span>
                        <span class="crs-hero__cta-price-num"><?php echo esc_html($data['price']); ?></span>
                        <span class="crs-hero__cta-price-period"><?php echo esc_html($data['price_period']); ?></span>
                    </div>

                    <?php if (!empty($data['billing_details'])): ?>
                        <div class="crs-hero__cta-billing">
                            <?php echo esc_html($data['billing_details']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($data['discount'])): ?>
                        <span class="crs-hero__cta-discount">
                            <?php echo $has_icon ? cal_get_icon('gift_icon') : ''; ?>
                            <?php echo esc_html($data['discount']); ?>
                        </span>
                    <?php endif; ?>

                    <a href="<?php echo esc_url($obfuscated_affiliate_url); ?>" class="crs-hero__cta-button" target="_blank"
                        rel="nofollow noopener sponsored"
                        aria-label="<?php printf(esc_attr__('Visit %s Official Website', 'cyberatlaslab'), esc_attr($data['name'])); ?>">
                        <?php printf(__('Visit %s', 'cyberatlaslab'), esc_html($data['name'])); ?>
                    </a>

                    <p class="crs-hero__cta-disclosure">
                        <?php _e('We may earn a commission when you purchase through links on this page.', 'cyberatlaslab'); ?>
                        <a href="<?php echo esc_url($disclaimer_url); ?>"><?php _e('Learn more', 'cyberatlaslab'); ?></a>
                    </p>
                </aside>

            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}

if (!shortcode_exists('crs_hero_section')) {
    add_shortcode('crs_hero_section', 'crs_hero_section_shortcode');
}


/**
 * ============================================================================================================================
 * 11.5 货币符号规范化辅助：自动读取 ACF currency_symbol 或接收字符串，USD/EUR/GBP 转符号，带 $ 兜底
 * ============================================================================================================================
 */
if (!function_exists('crs_format_currency')) {
    function crs_format_currency($raw = '', $post_id = 0)
    {
        // 如果传入了 post_id 且未指定 raw，则主动去取该文章的 ACF 字段
        if ($post_id > 0 && empty($raw) && function_exists('get_field')) {
            $raw = get_field('currency_symbol', $post_id);
        }

        $raw = trim((string) $raw);

        // 兜底：如果为空，默认返回 '$'
        if ($raw === '') {
            return '$';
        }

        // 货币代码 -> 符号 映射表
        $map = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$'
        ];

        $upper = strtoupper($raw);
        return isset($map[$upper]) ? $map[$upper] : $raw;
    }
}


/**
 * ============================================================================================================================
 * 12. 侧边栏高转化卡片简码 - [crs_sidebar_card]（v2 · 伪装链修正版）
 * 核心职责：在桌面端侧边栏生成固定的、高转化的商业推荐卡片。
 * 修复：CTA 链接改走 crs_build_affiliate_link()（/visit/ 伪装链，空值时隐藏按钮）；
 *       货币代码自动转符号；补 noopener；文本域统一 cyberatlaslab。
 * ============================================================================================================================
 */
if (!function_exists('crs_sidebar_card_shortcode')) {
    function crs_sidebar_card_shortcode($atts = [])
    {
        $atts = shortcode_atts(['id' => ''], $atts, 'crs_sidebar_card');
        $post_id = !empty($atts['id']) ? absint($atts['id']) : get_the_ID();
        $data = crs_get_product_data($post_id);

        // 防御性熔断：若当前页面未关联产品 ACF 字段，则直接隐藏卡片
        if (!$data) {
            return '';
        }

        // 🛠️ CTA 链接统一出口：/visit/ 伪装链（crs_build_affiliate_link 内部已兼容 Link 数组格式）
        $aff_link = function_exists('crs_build_affiliate_link')
            ? crs_build_affiliate_link($post_id)
            : (!empty($data['url']) ? $data['url'] : '#');

        // 评分星级换算逻辑（与 Hero 模块保持一致的 10 分制转 5 星）
        $full_stars = min(5, max(0, (int) round($data['score'] / 2)));
        $empty_stars = 5 - $full_stars;
        $has_icon = function_exists('cal_get_icon');
        $currency = function_exists('crs_format_currency') ? crs_format_currency($data['currency']) : $data['currency'];

        // 统一出口渲染 Logo
        $logo_html = function_exists('crs_render_product_logo')
            ? crs_render_product_logo($data['logo_id'], $data['name'])
            : '';

        ob_start();
        ?>
                <div class="crs-sidebar__card">
                    <div class="crs-sidebar__logo"><?php echo $logo_html; ?></div>
                    <p class="crs-sidebar__name"><?php echo esc_html($data['name']); ?></p>

                    <div class="crs-sidebar__score">
                        <span class="crs-sidebar__score-stars">
                            <?php for ($i = 0; $i < $full_stars; $i++): ?>
                                    <span
                                        class="crs-star-item crs-star--gold"><?php echo $has_icon ? cal_get_icon('filled_star_icon') : '★'; ?></span>
                            <?php endfor; ?>
                            <?php for ($i = 0; $i < $empty_stars; $i++): ?>
                                    <span
                                        class="crs-star-item crs-star--grey"><?php echo $has_icon ? cal_get_icon('filled_star_icon') : '★'; ?></span>
                            <?php endfor; ?>
                        </span>
                        <span class="crs-sidebar__score-num"><?php echo number_format($data['score'], 1); ?>/10</span>
                    </div>

                    <p class="crs-sidebar__price-from"><?php _e('From', 'cyberatlaslab'); ?></p>
                    <div class="crs-sidebar__price">
                        <span class="crs-sidebar__price-value"><?php echo esc_html($currency . $data['price']); ?></span>
                        <span class="crs-sidebar__price-period"><?php echo esc_html($data['price_period']); ?></span>
                    </div>

                    <?php if ($data['discount']): ?>
                            <span class="crs-sidebar__discount">
                                <?php echo $has_icon ? cal_get_icon('gift_icon') : ''; ?>
                                <span class="crs-sidebar__discount-text"><?php echo esc_html($data['discount']); ?></span>
                            </span>
                    <?php endif; ?>

                    <?php if (!empty($aff_link) && $aff_link !== '#'): ?>
                            <a href="<?php echo esc_url($aff_link); ?>" class="crs-sidebar__cta" target="_blank"
                                rel="nofollow noopener sponsored">
                                <?php printf(__('Get %s', 'cyberatlaslab'), esc_html($data['name'])); ?>
                            </a>
                    <?php endif; ?>
                </div>
                <?php
                return ob_get_clean();
    }
}

if (!shortcode_exists('crs_sidebar_card')) {
    add_shortcode('crs_sidebar_card', 'crs_sidebar_card_shortcode');
}


/**
 * ============================================================================================================================
 * 13. 侧边栏 TOC 简码 - [crs_sidebar_toc] (显式隔离版)
 * * 核心职责：在桌面端侧边栏生成具有高执行效率的文章目录导航。
 * 核心逻辑：基于 WordPress Transient API 机制进行 24 小时缓存。仅在缓存失效或发布新文章时触发 crs_extract_toc_items() 解析核心正文中的 HTML 锚点。
 * 隔离设计：通过 data-context="desktop" 为前端脚本提供专用的交互上下文，完全与移动端抽屉目录隔离。
 * * @package CyberAtlasLab\Core
 * @since 1.0.0
 * @param array $atts 简码传入属性，支持自定义标题与最大显示项数限制。
 * @return string 返回侧边栏静态目录的 HTML 字符串。
 * ============================================================================================================================
 */
if (!function_exists('crs_sidebar_toc_shortcode')) {
    function crs_sidebar_toc_shortcode($atts = [])
    {
        // 1. 合并简码默认属性，设定最大兜底边界
        $atts = shortcode_atts([
            'title' => 'Table of Contents',
            'max_items' => 99,
        ], $atts, 'crs_sidebar_toc');

        // 2. 环境防御：确保在合法的文章上下文环境中执行
        $post_id = get_the_ID();
        if (!$post_id) {
            return '';
        }

        // 3. Transient 缓存检索（v5）
        $cache_key = 'crs_toc_v5_' . $post_id;
        $toc_items = get_transient($cache_key);

        // 4. 仅在"真无缓存"（false）时穿透提取；空数组负缓存照常命中
        if ($toc_items === false) {
            $toc_items = crs_extract_toc_items($post_id);
            if (!empty($toc_items)) {
                set_transient($cache_key, $toc_items, DAY_IN_SECONDS);
            } else {
                set_transient($cache_key, [], 300); // 负缓存 5 分钟，防止空查询反复打数据库
            }
        }

        // 5. 空目录边界退场（含负缓存命中）
        if (empty($toc_items)) {
            return '';
        }

        // 6. 前端显示项数裁切控制
        if (!empty($toc_items) && is_array($toc_items)) {
            $toc_items = array_slice($toc_items, 0, intval($atts['max_items']));
        }

        $has_icon = function_exists('cal_get_icon');

        // 7. 开启输出缓冲区，建立标准的 HTML5 导航骨架
        ob_start();
        ?>
        <nav class="crs-sidebar__toc" data-context="desktop" aria-label="<?php echo esc_attr($atts['title']); ?>">
            <div class="crs-sidebar__toc-title">
                <?php echo $has_icon ? cal_get_icon('toc_icon') : ''; ?>
                <span><?php echo esc_html($atts['title']); ?></span>
            </div>

            <ul class="crs-sidebar__toc-list">
                <?php foreach ($toc_items as $item): ?>
                    <li>
                        <a href="#<?php echo esc_attr($item['anchor']); ?>" class="crs-sidebar__toc-link"
                            data-target="<?php echo esc_attr($item['anchor']); ?>"
                            data-context="desktop"><?php echo esc_html($item['label']); ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php
        // 8. 清理并吐出标准流 HTML
        return ob_get_clean();
    }
}

/**
 * 注册侧边栏 TOC 简码至系统核心
 */
if (!shortcode_exists('crs_sidebar_toc')) {
    add_shortcode('crs_sidebar_toc', 'crs_sidebar_toc_shortcode');
}


/**
 * ============================================================================================================================
 * 14. 提取与清理 TOC 数据逻辑
 * 核心职责：深度解析指定文章的正文源码或 Elementor 视图 JSON 数据，精准提取并清洗出不重复的 TOC（目录）锚点与标签集。
 * 支持范围：同时兼容原生 WordPress 文章内容流与 Elementor 的可视化网格嵌套数据流。
 * @package CyberAtlasLab\Core
 * @since 1.0.0
 * @param int $post_id 当前需要解析的文章 ID。
 * @return array 返回经过数据清洗、去重后的结构化目录数组。
 * ============================================================================================================================
 */
if (!function_exists('crs_extract_toc_items')) {
    function crs_extract_toc_items($post_id)
    {
        $toc_items = [];
        $content = '';

        // 1. 优先捕获 Elementor 编辑器的底层全量元数据
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);

        if (!empty($elementor_data)) {
            if (is_string($elementor_data)) {
                // 初次尝试进行标准 JSON 深度解码
                $data = json_decode($elementor_data, true);

                // 防御性设计：若因 WordPress 默认自动转义导致解码失败，先进行斜杠反转义再执行解码
                if (empty($data)) {
                    $data = json_decode(wp_unslash($elementor_data), true);
                }

                // 成功获取 Elementor JSON 树后，调用递归解析函数提取其中的有效 HTML
                if (is_array($data)) {
                    $content = crs_extract_html_from_elementor($data);
                }
            }
        }

        // 2. 后备降级方案：若非 Elementor 页面或数据为空，则直接调用 WordPress 原生正文流
        if (empty($content)) {
            $post = get_post($post_id);
            if ($post && !empty($post->post_content)) {
                $content = $post->post_content;
            }
        }

        // 边界安全退场：若遍历完全部途径依然无正文内容，直接熔断返回空集合
        if (empty($content)) {
            return $toc_items;
        }

        // 汇合后统一注入 TOC 锚点的过滤器钩子，确保在 Elementor 或原生正文中都能被正确捕获到 data-toc 与 id 属性的 HTML 标签
        if (function_exists('cal_inject_toc_anchors')) {
            $content = cal_inject_toc_anchors($content);
        }

        // 3. 高级双向匹配正则表达式
        // 职责：精准捕获带有 data-toc 和 id 属性的 HTML 标签，无论它们的属性书写顺序在前还是在后
        $pattern = '/<[^>]+?(?:id=["\'](?P<id>[^"\']+)["\'][^>]*?data-toc=["\'](?P<toc1>[^"\']*)["\']|data-toc=["\'](?P<toc2>[^"\']*)["\'][^>]*?id=["\'](?P<id_alt>[^"\']+)["\'])[^>]*?>/isu';

        // 4. 执行全局正则匹配
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // 根据正则捕获组的先后顺序，提取最终的有效锚点 ID
                $anchor = !empty($match['id']) ? $match['id'] : $match['id_alt'];

                // 提取最终显示的目录文本标签
                $label = !empty($match['toc1']) ? $match['toc1'] : (isset($match['toc2']) ? $match['toc2'] : '');

                $anchor = trim($anchor);
                if (empty($anchor)) {
                    continue;
                }

                // 防御机制：若 data-toc 属性为空白，则启动二次降维兜底，正则截取该 HTML 标签内部包裹的纯文字
                if (empty(trim($label))) {
                    if (preg_match('/>([^<]+)</u', $match[0], $text_match)) {
                        $label = trim($text_match[1]);
                    }
                }

                // 若经过上述处理后标签文本依旧为空，视为无效锚点，直接跳过
                if (empty(trim($label))) {
                    continue;
                }

                // 5. 严格对输入数据进行 WordPress 安全清洗，阻断潜在的 XSS 漏洞
                $toc_items[] = [
                    'anchor' => sanitize_text_field($anchor), // 改：尊重原始 id，不再 sanitize_title
                    'label' => sanitize_text_field($label),
                ];
            }
        }

        // 6. 内存级去重引擎：对可能产生的重复锚点进行物理过滤
        $seen = [];
        $unique_items = [];
        foreach ($toc_items as $item) {
            if (!isset($seen[$item['anchor']])) {
                $seen[$item['anchor']] = true;
                $unique_items[] = $item;
            }
        }

        return $unique_items;
    }
}

/**
 * ---------------------------------------------------------------------------------------------------------
 * Elementor 嵌套数据流递归解析引擎
 * 核心职责：采用深度优先遍历（DFS）算法，跨越 Elementor 的 Section、Column、Container 多层网格嵌套，
 * 专门定向抽取 HTML 小工具源码与 Heading 标题小工具组件内容。
 * @param array $elements Elementor 节点树数组。
 * @return string 汇聚提取出来的纯 HTML 源码文本。
 * ----------------------------------------------------------------------------------------------------------
 */
if (!function_exists('crs_extract_html_from_elementor')) {
    function crs_extract_html_from_elementor($elements)
    {
        $html = '';
        if (!is_array($elements)) {
            return $html;
        }

        foreach ($elements as $element) {
            // 途径 A：抓取自定义 HTML 简码及原始代码组件
            if (isset($element['widgetType']) && $element['widgetType'] === 'html') {
                if (isset($element['settings']['html'])) {
                    $html .= $element['settings']['html'] . "\n";
                }
            }

            // 途径 B：heading 组件 → 光板 h2（id/data-toc 统一由注入器生成）
            if (isset($element['widgetType']) && $element['widgetType'] === 'heading') {
                if (isset($element['settings']['title'])) {
                    $html .= '<h2 ';
                    if (!empty($element['settings']['_attributes'])) {
                        $html .= $element['settings']['_attributes'] . ' '; // 存量手工属性保留
                    }
                    $html .= '>' . $element['settings']['title'] . "</h2>\n";
                }
            }

            // 途径 C：发现更深层的子节点网格，启动递归引擎继续向下击穿探寻
            if (isset($element['elements']) && is_array($element['elements'])) {
                $html .= crs_extract_html_from_elementor($element['elements']);
            }
        }
        return $html;
    }
}

/**
 * -------------------------------------------------------------------------------------------------------------
 * 自动垃圾回收与缓存失效控制器
 * 核心职责：在文章发生变动时，即刻清理对应的 Transient 对象缓存，确保前端目录展示 100% 同步最新的编辑状态。
 * 挂载钩子：同时监听原生文章保存动作（save_post）与 Elementor 后台无刷新保存动作（elementor/editor/after_save）。
 * @param int $post_id 当前发生保存操作的文章 ID。
 * -------------------------------------------------------------------------------------------------------------
 */
if (!function_exists('crs_clear_toc_cache')) {
    function crs_clear_toc_cache($post_id)
    {
        // 安全边界防御：如果是自动草稿保存、自动瞬时备份或历史版本修订，直接免退出，不进行缓存清理
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        // 精准靶向清理之前方案 B 注册的 Transient 键值
        delete_transient('crs_toc_v5_' . $post_id);
    }
}
// 注册垃圾回收事件钩子到 WordPress 核心管道
add_action('save_post', 'crs_clear_toc_cache');
add_action('elementor/editor/after_save', 'crs_clear_toc_cache');


/**
 * ================================================================================================================
 * 15. 侧边栏快速参数简码 - [crs_sidebar_specs]
 * * 核心职责：在桌面端侧边栏生成直观的核心技术指标参数面板（Specs Box）。
 * 数据设计：定义了一个多维映射数组，统一管理核心字段名、国际化标签、图标别名以及前端单位后缀。
 * 过滤机制：遍历过程中自动对未填写的空数据字段进行物理隐藏，保障前台页面展示紧凑且无数据缺失感。
 * * @package CyberAtlasLab\Core
 * @since 1.0.0
 * @param array $atts 简码传入属性，支持自定义标题文本。
 * @return string 返回核心参数面板的 HTML 字符串。
 * =================================================================================================================
 */
if (!function_exists('crs_sidebar_specs_shortcode')) {
    function crs_sidebar_specs_shortcode($atts = [])
    {
        // 1. 合并简码默认属性
        $atts = shortcode_atts([
            'title' => 'Quick Specs',
            'id'    => '', // 允许传入特定文章 ID，默认取当前文章
        ], $atts, 'crs_sidebar_specs');

        // 2. 获取当前文章 ID 并调取产品聚合数据
        $post_id = !empty($atts['id']) ? absint($atts['id']) : get_the_ID();
        $data = crs_get_product_data($post_id);

        // 防御性熔断：若没有对应产品数据，整个模块放弃渲染，避免输出空外壳影响页面 CLS 评分
        if (!$data) {
            return '';
        }

        $has_icon = function_exists('cal_get_icon');

        // 3. 构建参数映射矩阵（配置字段、国际化翻译标签、专属系统图标和单位后缀）
        $specs = [
            ['key' => 'servers', 'label' => __('Servers', 'cyberatlaslab'), 'icon' => 'servers', 'suffix' => '+'],
            ['key' => 'countries', 'label' => __('Countries', 'cyberatlaslab'), 'icon' => 'countries', 'suffix' => '+'],
            ['key' => 'devices', 'label' => __('Devices', 'cyberatlaslab'), 'icon' => 'devices', 'suffix' => ''],
            ['key' => 'refund', 'label' => __('Refund', 'cyberatlaslab'), 'icon' => 'moneyback', 'suffix' => ' days'],
            ['key' => 'logs', 'label' => __('Logs', 'cyberatlaslab'), 'icon' => 'nologs', 'suffix' => ''],
        ];

        // 4. 开启输出缓冲区，组织参数列表骨架
        ob_start();
        ?>
        <div class="crs-sidebar__specs">
            <p class="crs-sidebar__specs-title"><?php echo esc_html($atts['title']); ?></p>

            <div class="crs-sidebar__specs-list">
                <?php foreach ($specs as $spec):
                    // 4.1 提取缓存中对应的动态业务数据
                    $value = $data[$spec['key']];

                    // 4.2 智能避让机制：若运营在后台未填写此参数，则不渲染这一行，保持排版无缝紧凑
                    if (empty($value)) {
                        continue;
                    }
                    ?>
                    <div class="crs-sidebar__specs-item">
                        <span class="crs-sidebar__specs-label">
                            <?php echo $has_icon ? cal_get_icon($spec['icon']) : ''; ?>
                            <?php echo esc_html($spec['label']); ?>
                        </span>

                        <span class="crs-sidebar__specs-value"><?php echo esc_html($value . $spec['suffix']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        // 5. 捕获并清空输出缓冲区，输出纯净的 HTML 字符串
        return ob_get_clean();
    }
}

/**
 * 注册快速参数简码至 WordPress 核心
 */
if (!shortcode_exists('crs_sidebar_specs')) {
    add_shortcode('crs_sidebar_specs', 'crs_sidebar_specs_shortcode');
}



/**
 * =============================================================================
 * 16. 统一侧边栏卡片简码 - [crs_sidebar_unified]
 * * 核心职责：将商业转化卡片、文章动态目录（TOC）、快速技术参数（Specs）三个独立组件完美融合成一个高凝聚力的统一化侧边栏大盒子。
 * 性能设计：采用纯 PHP 函数链级调用替代 WordPress 标准简码嵌套解析，消除大量的正则表达式重绘开销，提供极致的渲染速度。
 * 智能交互：内置自适应分割线（Divider）排版算法，根据三个组件各自的实际内容数据饱满度，动态决定是否插入视觉分割线，防止样式塌陷。
 * * @package CyberAtlasLab\Core
 * @since 1.0.0
 * @param array $atts 简码传入属性，支持传递子组件所需的标题和最大项数。
 * @return string 返回完全组装洗净后的侧边栏大外壳 HTML 字符串。
 * =============================================================================
 */
if (!function_exists('crs_sidebar_unified_shortcode')) {
    function crs_sidebar_unified_shortcode($atts)
    {
        // 1. 合并统一化大盒子的默认全局简码属性
        $atts = shortcode_atts([
            'toc_title' => 'Table of Contents',
            'specs_title' => 'Quick Specs',
            'max_items' => 99,
        ], $atts, 'crs_sidebar_unified');

        // 2. 高性能执行：跳过慢速短代码内核，直接以最快速度从底层 PHP 调用子组件的渲染函数
        $card_content = crs_sidebar_card_shortcode([]);
        $toc_content = crs_sidebar_toc_shortcode(['title' => $atts['toc_title'], 'max_items' => $atts['max_items']]);
        $specs_content = crs_sidebar_specs_shortcode(['title' => $atts['specs_title']]);

        // 3. 全局防御：如果三个核心子组件的数据全部为空（例如非单篇产品评测页），整个模块直接全面隐退，不输出多余的空标签
        if (empty(trim($card_content)) && empty(trim($toc_content)) && empty(trim($specs_content))) {
            return '';
        }

        // 4. 开启输出缓冲区，开始组织自适应侧边栏容器骨架
        ob_start();
        ?>
        <div class="crs-sidebar__unified">

            <?php if (!empty(trim($card_content))): ?>
                <div class="crs-sidebar__section crs-sidebar__section--card">
                    <?php echo $card_content; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty(trim($toc_content))): ?>
                <?php if (!empty(trim($card_content))): ?>
                    <div class="crs-sidebar__divider"></div>
                <?php endif; ?>
                <div class="crs-sidebar__section crs-sidebar__section--toc">
                    <?php echo $toc_content; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty(trim($specs_content))): ?>
                <?php if (!empty(trim($card_content)) || !empty(trim($toc_content))): ?>
                    <div class="crs-sidebar__divider"></div>
                <?php endif; ?>
                <div class="crs-sidebar__section crs-sidebar__section--specs">
                    <?php echo $specs_content; ?>
                </div>
            <?php endif; ?>

        </div>
        <?php
        // 5. 回收缓冲区并安全返回最终的大盒子代码
        return ob_get_clean();
    }
}

/**
 * 注册统一侧边栏简码至 WordPress 系统底座
 */
if (!shortcode_exists('crs_sidebar_unified')) {
    add_shortcode('crs_sidebar_unified', 'crs_sidebar_unified_shortcode');
}



/**
 * ======================================================================================================
 * 17. 移动端折叠 TOC - [crs_toc_mobile] (显式隔离无障碍版)
 * ======================================================================================================
 */
if (!function_exists('crs_toc_mobile_shortcode')) {
    function crs_toc_mobile_shortcode($atts)
    {
        // 1. 合并简码默认属性
        $atts = shortcode_atts([
            'title' => 'Contents',
        ], $atts, 'crs_toc_mobile');

        // 2. 环境防御
        $post_id = get_the_ID();
        if (!$post_id) {
            return '';
        }

        // 3. 多端共用对象缓存检索
        $cache_key = 'crs_toc_v5_' . $post_id;
        $toc_items = get_transient($cache_key);

        // 4. 缓存穿透防御
        if ($toc_items === false) {
            $toc_items = crs_extract_toc_items($post_id);
            if (!empty($toc_items)) {
                set_transient($cache_key, $toc_items, DAY_IN_SECONDS);
            } else {
                set_transient($cache_key, [], 300);
            }
        }

        // 5. 空数据安全边界退场
        if (empty($toc_items)) {
            return '';
        }

        $total = count($toc_items);

        // 核心优化：动态生成唯一的控制 ID 供 ARIA 规范咬合
        $list_wrapper_id = 'crs-toc-mobile-panel-' . $post_id;
        $button_label_id = 'crs-toc-mobile-label-' . $post_id;

        // 6. 开启输出缓冲区
        ob_start();
        ?>
        <nav class="crs-toc-mobile crs-toc-mobile--fusion" data-context="mobile" aria-labelledby="<?php echo esc_attr($button_label_id); ?>">

            <button
                class="crs-toc-mobile__trigger"
                type="button"
                aria-expanded="false"
                aria-haspopup="true"
                aria-controls="<?php echo esc_attr($list_wrapper_id); ?>"
            >
                <span class="crs-toc-mobile__icon" aria-hidden="true">
                    <?php echo function_exists('cal_get_icon') ? cal_get_icon('toc_icon') : ''; ?>
                </span>

                <span class="crs-toc-mobile__label" id="<?php echo esc_attr($button_label_id); ?>">
                    <?php echo esc_html($atts['title']); ?>
                </span>

                <span class="crs-toc-mobile__count"><?php echo $total; ?> sections</span>

                <span class="crs-toc-mobile__arrow" aria-hidden="true">
                    <?php echo function_exists('cal_get_icon') ? cal_get_icon('chevron_down') : '▼'; ?>
                </span>
            </button>

            <ul class="crs-toc-mobile__list" id="<?php echo esc_attr($list_wrapper_id); ?>" aria-hidden="true">
                <?php foreach ($toc_items as $index => $item) :
                    // 尊重原文，不强制改变大小写
                    $clean_label = $item['label'];
                    ?>
                    <li>
                        <a href="#<?php echo esc_attr($item['anchor']); ?>"
                           class="crs-toc-mobile__link"
                           data-target="<?php echo esc_attr($item['anchor']); ?>"
                           data-context="mobile">
                            <span class="crs-toc-mobile__num"><?php echo $index + 1; ?></span>
                            <?php echo esc_html($clean_label); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php
        return ob_get_clean();
    }
}

if (!shortcode_exists('crs_toc_mobile')) {
    add_shortcode('crs_toc_mobile', 'crs_toc_mobile_shortcode');
}


/**
 * =========================================================================================================
 * 18. 移动端产品卡片（含 Specs - 计费细节与安全通水完美版 v2）
 * =========================================================================================================
 * v2 优化：
 * ① Logo 统一走 crs_render_product_logo() 公共引擎（SVG 自动内联）；
 * ② 货币统一走 crs_format_currency()，USD/EUR 代码自动转符号；
 * ③ CTA 链接统一走 crs_build_affiliate_link()（伪装链优先 + 框架缺席降级 + 空值隐藏按钮）；
 * ④ Specs 取值加 isset/is_array/is_scalar 三层防御，与侧边栏参数面板同标准；
 * ⑤ 模板收紧，消除渲染文本中的缩进空白。
 * =========================================================================================================
 */
if (!function_exists('crs_mobile_card_shortcode')) {
    function crs_mobile_card_shortcode($atts = [])
    {
        $atts = shortcode_atts(['id' => ''], $atts, 'crs_mobile_card');

        // 1. 显式 id（分类页 Top1 适配）优先，缺省回退当前文章
        $post_id = !empty($atts['id']) ? absint($atts['id']) : get_the_ID();
        $data = crs_get_product_data($post_id);

        // 防御性熔断：若未调取到对应产品数据集，则直接终止渲染，防止手机端前台样式塌陷
        if (!$data) {
            return '';
        }

        // 2. 星级评分换算逻辑（10分制换算为标准的5星表现形式）
        $full_stars = min(5, max(0, (int) round($data['score'] / 2)));
        $empty_stars = 5 - $full_stars;
        $has_icon = function_exists('cal_get_icon');
        $currency = function_exists('crs_format_currency') ? crs_format_currency($data['currency']) : $data['currency'];

        // 🏹 CTA 链接统一出口：/visit/ 伪装链优先，框架缺席自动降级，空值返回 '#'
        $aff_link = function_exists('crs_build_affiliate_link')
            ? crs_build_affiliate_link($post_id)
            : '#';

        // 3. 产品 Logo：公共引擎（SVG 内联 / 位图 lazy img / 全格式兼容）
        $logo_html = function_exists('crs_render_product_logo')
            ? crs_render_product_logo($data['logo_id'], $data['name'])
            : '';

        // 4. 移动端下部网格参数字段配置矩阵（对齐统一国际化语言域）
        $specs = [
            ['key' => 'servers', 'label' => __('Servers', 'cyberatlaslab'), 'icon' => 'servers', 'suffix' => '+'],
            ['key' => 'countries', 'label' => __('Countries', 'cyberatlaslab'), 'icon' => 'countries', 'suffix' => '+'],
            ['key' => 'devices', 'label' => __('Devices', 'cyberatlaslab'), 'icon' => 'devices', 'suffix' => ''],
            ['key' => 'refund', 'label' => __('Refund', 'cyberatlaslab'), 'icon' => 'moneyback', 'suffix' => ' days'],
            ['key' => 'logs', 'label' => __('Logs', 'cyberatlaslab'), 'icon' => 'nologs', 'suffix' => ''],
        ];

        // 5. 开启输出缓冲区，构建响应式移动端骨架
        ob_start();
        ?>
        <div class="crs-mobile-card">
            <div class="crs-mobile-card__header">
                <?php if ($logo_html): ?>
                    <div class="crs-mobile-card__logo"><?php echo $logo_html; ?></div>
                <?php endif; ?>

                <h3 class="crs-mobile-card__name"><?php echo esc_html($data['name']); ?></h3>

                <div class="crs-mobile-card__score">
                    <span class="crs-mobile-card__stars">
                        <?php for ($i = 0; $i < $full_stars; $i++): ?>
                            <span
                                class="crs-star-item crs-star--gold"><?php echo $has_icon ? cal_get_icon('filled_star_icon') : '★'; ?></span>
                        <?php endfor; ?>
                        <?php for ($i = 0; $i < $empty_stars; $i++): ?>
                            <span
                                class="crs-star-item crs-star--grey"><?php echo $has_icon ? cal_get_icon('filled_star_icon') : '★'; ?></span>
                        <?php endfor; ?>
                    </span>
                    <span class="crs-mobile-card__score-num"><?php echo number_format($data['score'], 1); ?>/10</span>
                </div>

                <?php if ($data['price']): ?>
                    <p class="crs-mobile-card__price-from"><?php _e('From', 'cyberatlaslab'); ?></p>
                    <div class="crs-mobile-card__price">
                        <span class="crs-mobile-card__price-value"><?php echo esc_html($currency . $data['price']); ?></span>
                        <span class="crs-mobile-card__price-period"><?php echo esc_html($data['price_period']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($data['billing_details'])): ?>
                    <div class="crs-mobile-card__billing"><?php echo esc_html(trim($data['billing_details'])); ?></div>
                <?php endif; ?>

                <?php if ($data['discount']): ?>
                    <span
                        class="crs-mobile-card__discount"><?php echo $has_icon ? cal_get_icon('gift_icon') : ''; ?><span><?php echo esc_html($data['discount']); ?></span></span>
                <?php endif; ?>

                <?php if (!empty($aff_link) && $aff_link !== '#'): ?>
                    <a href="<?php echo esc_url($aff_link); ?>" class="crs-mobile-card__cta" target="_blank"
                        rel="nofollow noopener sponsored"
                        aria-label="<?php printf(esc_attr__('Get %s Exclusive Deal', 'cyberatlaslab'), esc_attr($data['name'])); ?>"><?php printf(__('Get %s', 'cyberatlaslab'), esc_html($data['name'])); ?></a>
                <?php endif; ?>
            </div>

            <div class="crs-mobile-card__specs">
                <?php foreach ($specs as $spec):
                    // 🛡️ 三层防御：未定义键 / 数组返回 / 非标量，与侧边栏参数面板同标准
                    $value = isset($data[$spec['key']]) ? $data[$spec['key']] : '';
                    if (is_array($value)) {
                        $value = implode(', ', array_filter(array_map('strval', $value)));
                    }
                    if ($value === '' || $value === null || !is_scalar($value)) {
                        continue;
                    }
                    ?>
                    <div class="crs-mobile-card__specs-item">
                        <span
                            class="crs-mobile-card__specs-label"><?php echo $has_icon ? cal_get_icon($spec['icon']) : ''; ?><?php echo esc_html($spec['label']); ?></span>
                        <span class="crs-mobile-card__specs-value"><?php echo esc_html($value . $spec['suffix']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * 注册移动端专用卡片简码至 WordPress 系统核心
 */
if (!shortcode_exists('crs_mobile_card')) {
    add_shortcode('crs_mobile_card', 'crs_mobile_card_shortcode');
}


/**
 * =============================================================================
 * 19. TOC 高亮脚本加载
 * =============================================================================
 */
function crs_enqueue_toc_assets()
{
    $load = is_singular('product');
    if (!$load && function_exists('cal_is_category_page')) {
        $load = cal_is_category_page(get_queried_object_id());
    }
    if ($load) {
        wp_enqueue_script('crs-toc-highlight', get_stylesheet_directory_uri() . '/js/toc-highlight.js', [], '1.0.0', true);
    }
}
add_action('wp_enqueue_scripts', 'crs_enqueue_toc_assets');

// 添加 defer
function crs_toc_script_defer($tag, $handle, $src)
{
    if ('crs-toc-highlight' === $handle) {
        return '<script src="' . esc_url($src) . '" defer></script>' . "\n";
    }
    return $tag;
}
add_filter('script_loader_tag', 'crs_toc_script_defer', 10, 3);


/**
 * 分类页锚点导航脚本加载 - [cal_anchor_nav] 专用
 * 与 §19 crs_enqueue_toc_assets 判定条件相同，但服务对象不同（整页导航 vs 单品目录），
 * 因此单独注册 handle，避免两个组件的开关逻辑相互牵连。
 */
function cal_enqueue_anchor_nav_assets()
{
    if (!function_exists('cal_is_category_page') || !cal_is_category_page(get_queried_object_id())) {
        return;
    }
    wp_enqueue_script(
        'cal-anchor-nav',
        get_stylesheet_directory_uri() . '/js/cal-anchor-nav.js',
        array(),
        CAL_CHILD_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'cal_enqueue_anchor_nav_assets');

// defer：与 §19 crs-toc-highlight 同一手法，按 handle 白名单追加
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if ('cal-anchor-nav' === $handle) {
        return '<script src="' . esc_url($src) . '" defer></script>' . "
";
    }
    return $tag;
}, 10, 3);



/**
 * =============================================================================
 * 20. 优缺点与适用人群评测模块简码 [cal_verdict]
 * =============================================================================
 */

/**
 * CRS 标题格式化工具函数
 * 将后台任意极端的录入规范统一转换为标准的 Title Case (每个单词首字母大写)
 * 作用：彻底规避由于后台输入全大写（如 PROS）导致 CSS capitalize 属性失效的问题
 */
function cal_format_verdict_title($title)
{
    $title = trim((string) $title);
    if ($title === '') {
        return '';
    }
    // 先整体转小写，再通过 ucwords 将每个单词的首字母转大写
    return ucwords(strtolower($title));
}

/**
 * 优缺点短代码主渲染函数
 */
function cal_shortcode_verdict_strict_manual($atts)
{
    // 1. 解析短代码属性，允许通过属性传入特定的 post_id
    $atts = shortcode_atts([
        'post_id' => false
    ], $atts, 'cal_verdict');

    $post_id = $atts['post_id'] ? absint($atts['post_id']) : get_the_ID();
    if (!$post_id) {
        return '';
    }

    // 2. 数据清洗管道：读取 ACF 标题字段并即时进行 Title Case 格式化
    $title_pros = cal_format_verdict_title(get_field('pros_title_text', $post_id));
    $title_cons = cal_format_verdict_title(get_field('cons_title_text', $post_id));
    $title_best = cal_format_verdict_title(get_field('best_title_text', $post_id));

    // 3. 核心数据驱动配置字典
    $sections_data = [
        'pros' => [
            'field'      => 'pros',
            'sub'        => 'pro_item',
            'items'      => [],
            'title'      => $title_pros,
            'title_icon' => 'pro_title_icon',
            'list_icon'  => 'pro_list_icon'
        ],
        'cons' => [
            'field'      => 'cons',
            'sub'        => 'con_item',
            'items'      => [],
            'title'      => $title_cons,
            'title_icon' => 'con_title_icon',
            'list_icon'  => 'con_list_icon'
        ],
        'best' => [
            'field'      => 'best',
            'sub'        => 'best_item',
            'items'      => [],
            'title'      => $title_best,
            'title_icon' => 'best_title_icon',
            'list_icon'  => 'best_list_icon'
        ]
    ];

    // 4. 数据预处理循环：加载中继器（Repeater）内容并进行防御性空值校验
    $has_any_render = false;
    foreach ($sections_data as $key => $config) {
        // 如果当前栏目的标题为空，直接跳过不处理
        if ($config['title'] === '') {
            continue;
        }

        $raw_repeater = get_field($config['field'], $post_id);
        if (is_array($raw_repeater) && !empty($raw_repeater)) {
            foreach ($raw_repeater as $row) {
                $clean_text = trim((string) ($row[$config['sub']] ?? ''));
                // 确保只有非空文本才会被塞入待渲染队列
                if ($clean_text !== '') {
                    $sections_data[$key]['items'][] = $clean_text;
                }
            }
        }

        // 防御机制：如果标题不为空，但列表要点被删光了，清空标题以阻止空外壳渲染
        if (empty($sections_data[$key]['items'])) {
            $sections_data[$key]['title'] = '';
        } else {
            $has_any_render = true; // 只要有一个栏目有内容，就允许渲染大容器
        }
    }

    // 如果三大板块全部无内容，熔断返回空，不污染前端 DOM 占位
    if (!$has_any_render) {
        return '';
    }

    // 5. 开启缓冲区，输出经过语义化和无障碍优化的 HTML 结构
    ob_start();
    ?>
    <div class="crs-verdict">
        <div class="crs-verdict__grid">

            <?php
            foreach ($sections_data as $modifier => $section):
                if ($section['title'] === '') {
                    continue;
                }

                /**
                 * 核心优化：动态生成全网唯一的无障碍锚点 ID
                 * 例如生成：crs-aria-verdict-pros / crs-aria-verdict-cons
                 */
                $aria_label_id = 'crs-aria-verdict-' . esc_attr($modifier);
                ?>

                <section
                    class="crs-verdict__section crs-verdict__section--<?php echo esc_attr($modifier); ?>"
                    aria-labelledby="<?php echo esc_attr($aria_label_id); ?>"
                >
                    <h2 class="crs-verdict__title" id="<?php echo esc_attr($aria_label_id); ?>">
                        <?php echo cal_get_icon($section['title_icon'], 'crs-verdict__title-icon'); ?>
                        <span class="crs-verdict__title-text"><?php echo esc_html($section['title']); ?></span>
                    </h2>

                    <ul class="crs-verdict__list">
                        <?php foreach ($section['items'] as $text): ?>
                            <li class="crs-verdict__item">
                                <?php echo cal_get_icon($section['list_icon'], 'crs-verdict__item-icon'); ?>
                                <span class="crs-verdict__item-text"><?php echo esc_html($text); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

            <?php endforeach; ?>

        </div>
    </div>
    <?php
    return ob_get_clean();
}
// 注册 WordPress 短代码
add_shortcode('cal_verdict', 'cal_shortcode_verdict_strict_manual');


/**
 * =============================================================================
 * 21. 亮点总结模块简码 - Key Takeaways (带全新无障碍骨架与动态中继器)
 * 简码：[cal_takeaways]
 * =============================================================================
 * 优化说明：
 * 1. 严格手动输入控制：优先读取后台文本字段 takeaway_title。若不填，前台彻底熔断。
 * 2. 智能防空壳门禁：即使填了标题，但若底下的中继器列表未添加任何有效行，同样强行拦截渲染。
 * 3. 语义化与 A11y 封顶：升级为 section[aria-labelledby] 架构，引入独立的 header 隔离层。
 * 4. 国际化与动态副标题：预留符合 i18n 标准的动态副标题。
 */

if (!shortcode_exists('cal_takeaways')) {
    add_shortcode('cal_takeaways', function ($atts) {
        // 1. 解析参数，统一变更为 cal_takeaways 过滤标识
        $atts = shortcode_atts([
            'post_id' => false,
            'field' => 'takeaways', // 锚定你外层的 takeaways 中继器字段名称
        ], $atts, 'cal_takeaways');

        $post_id = $atts['post_id'] ? absint($atts['post_id']) : get_the_ID();
        if (!$post_id) {
            return '';
        }

        // 2. 严格控制：从外层独立字段中读取大标题（平级调用，不进循环）
        $takeaway_title = trim((string) get_field('takeaway_title', $post_id));

        // 门禁一：如果外层独立大标题没填，前台直接熔断不输出
        if ($takeaway_title === '') {
            return '';
        }

        // 3. 读取外层平级的中继器数据
        $rows = get_field($atts['field'], $post_id);
        if (!is_array($rows) || empty($rows)) {
            return '';
        }

        // 4. 清洗中继器内部的子字段数据
        $clean_items = [];
        foreach ($rows as $row) {
            $text = trim((string) ($row['takeaway_text'] ?? ''));
            if ($text !== '') {
                $clean_items[] = [
                    'icon' => trim((string) ($row['takeaway_icon'] ?? 'check')),
                    'text' => $text
                ];
            }
        }

        // 门禁二：若底下的列表洗完后是空的，同样拦截，防止空壳标题
        if (empty($clean_items)) {
            return '';
        }

        // =====================================================================
        // HTML 视图纯净拼接（完美对齐全新 BEM 与大厂无障碍规范）
        // =====================================================================
        $output = '<section class="crs-takeaways" aria-labelledby="crs-takeaways-title">';
        $output .= '<div class="crs-takeaways__inner">';

        // 1：引入结构化头部隔离层，解耦标题与副标题
        $output .= '<header class="crs-takeaways__header">';

        // 大标题绑定唯一 ID，吐出带 aria-hidden 的图标
        $output .= '<h2 id="crs-takeaways-title" class="crs-takeaways__title">';
        $output .= cal_get_icon('award', 'cal-icon crs-takeaways__title-icon');
        $output .= ' <span>' . esc_html($takeaway_title) . '</span></h2>';
        $output .= '</header>';

        // 2：列表项输出，独立图标容器并彻底切断读屏噪声
        $output .= '<ul class="crs-takeaways__list">';
        foreach ($clean_items as $item) {
            $output .= '<li class="crs-takeaways__item">';

            // 图标外包裹 span，并对读屏器隐藏（aria-hidden），规避视觉混淆
            $output .= '<span class="crs-takeaways__icon">';
            $output .= cal_get_icon($item['icon'], 'cal-icon');
            $output .= '</span>';

            // 文本容器，完美兼容 wp_kses_post 允许的加粗/链接等基本富文本格式
            $output .= '<span class="crs-takeaways__text">' . wp_kses_post($item['text']) . '</span>';

            $output .= '</li>';
        }
        $output .= '</ul>';

        $output .= '</div>';
        $output .= '</section>';

        return $output;
    });
}


/**
 * =============================================================================
 * 22. 评分看板模块简码 - [cal_scores]（v3 · Score Engine 接入版）
 * 新增：维度行 Tooltip（crs_render_score_tooltip），score_key 匹配，
 *       sanitize_title(label) 兜底兼容旧数据
 * =============================================================================
 */
if (!shortcode_exists('cal_scores')) {

    /**
     * 辅助函数：高精度动态评分格式化
     */
    function cal_format_score($value)
    {
        return rtrim(
            rtrim(
                number_format((float) $value, 2, '.', ''),
                '0'
            ),
            '.'
        );
    }

    add_shortcode('cal_scores', function ($atts) {
        $atts = shortcode_atts([
            'post_id' => false,
        ], $atts, 'cal_scores');

        $post_id = $atts['post_id'] ? absint($atts['post_id']) : get_the_ID();
        if (!$post_id) {
            return '';
        }

        // 安全门禁一：大标题留空，模块整体熔断
        $scoring_title = trim((string) get_field('scoring_title', $post_id));
        if ($scoring_title === '') {
            return '';
        }

        // 数据抓取与预清洗：中继器细分项预过滤
        $rows = get_field('scoring_breakdown', $post_id);
        $valid_rows = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $label = trim((string) ($row['score_label'] ?? ''));
                $value = $row['score_value'] ?? null;

                // 脏数据过滤：标签为空或分数未填，一律跳过
                if ($label === '' || $value === '' || $value === null) {
                    continue;
                }

                $score_value = max(0, min(10, (float) $value));

                // 🎯 维度键解析：优先受控 score_key，无 key 时 label 清洗兜底（旧数据零迁移）
                $dim_key = !empty($row['score_key'])
                    ? sanitize_key($row['score_key'])
                    : sanitize_title($label);

                $valid_rows[] = [
                    'key' => $dim_key,
                    'label' => $label,
                    'value' => $score_value,
                    'width' => round(($score_value / 10) * 100, 2),
                ];
            }
        }

        // 安全门禁二：有标题无明细，拦截空外壳
        if (empty($valid_rows)) {
            return '';
        }

        // 综合大圆环得分 (Overall Score)
        $score_overall = get_field('score_overall', $post_id);
        $score_overall = is_numeric($score_overall) ? (float) $score_overall : 0;
        $score_overall = max(0, min(10, $score_overall));

        // 几何换算：SVG 圆环 stroke-dashoffset（输出截短至 2 位小数）
        $radius = 65;
        $circumference = 2 * M_PI * $radius;
        $offset = $circumference * (1 - ($score_overall / 10));

        // 智能多语言自适应：依大标题语种切换圆环副标题
        $overall_label = (preg_match('/[\x{4e00}-\x{9fa5}]/u', $scoring_title)) ? '综合得分' : 'Overall Score';
        $score_text = cal_format_score($score_overall);

        // Overall 维度 Tooltip（Score Library 中配置了 overall 才输出）
        $overall_tip = function_exists('crs_render_score_tooltip') ? crs_render_score_tooltip('overall') : '';

        ob_start();
        ?>
        <div class="crs-scores__inner">
            <div class="crs-scores__box">

                <h2 class="crs-scores__title">
                    <?php echo cal_get_icon('scoring-breakdown', 'crs-scores__title-icon'); ?>
                    <span><?php echo esc_html($scoring_title); ?></span>
                </h2>

                <div class="crs-scores__grid">

                    <div class="crs-scores__overall">
                        <div class="crs-scores__circle">
                            <svg viewBox="0 0 140 140" role="img"
                                aria-label="<?php printf(esc_attr__('Overall score %s out of 10', 'cyberatlaslab'), esc_attr($score_text)); ?>">
                                <circle class="crs-scores__circle-bg" cx="70" cy="70" r="<?php echo esc_attr($radius); ?>" />
                                <circle class="crs-scores__circle-fill" cx="70" cy="70" r="<?php echo esc_attr($radius); ?>"
                                    stroke-dasharray="<?php echo esc_attr(round($circumference, 2)); ?>"
                                    stroke-dashoffset="<?php echo esc_attr(round($offset, 2)); ?>" />
                            </svg>
                            <span class="crs-scores__circle-value"><?php echo esc_html($score_text); ?></span>
                        </div>
                        <p class="crs-scores__overall-label"><?php echo esc_html($overall_label); ?><?php echo $overall_tip; ?>
                        </p>
                    </div>

                    <div class="crs-scores__breakdown">
                        <?php foreach ($valid_rows as $item): ?>
                            <div class="crs-scores__item">
                                <div class="crs-scores__item-header">
                                    <span
                                        class="crs-scores__item-label"><?php echo esc_html($item['label']); ?><?php echo function_exists('crs_render_score_tooltip') ? crs_render_score_tooltip($item['key']) : ''; ?></span>
                                    <span
                                        class="crs-scores__item-value"><?php echo esc_html(cal_format_score($item['value'])); ?></span>
                                </div>
                                <div class="crs-scores__bar" role="progressbar"
                                    aria-valuenow="<?php echo esc_attr($item['value']); ?>" aria-valuemin="0" aria-valuemax="10"
                                    aria-label="<?php echo esc_attr($item['label']); ?>">
                                    <div class="crs-scores__bar-fill" style="width: <?php echo esc_attr($item['width']); ?>%;">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>

            </div>
        </div>
        <?php
        return ob_get_clean();
    });
}


/**
 * =============================================================================
 * 23. FAQ模块简码 - [cal_faq]（v2.2 · 三开关版）
 * =============================================================================
 * 新增：① show_title 控制 h2 标题渲染
 *       ② class 参数注入模块级修饰类（控制阴影/字体）
 *       ③ 答案区自动继承修饰类，无需额外标记
 * =============================================================================
 */
if (!shortcode_exists('cal_faq')) {
    add_shortcode('cal_faq', function ($atts) {
        $atts = shortcode_atts([
            'post_id'     => false,
            'source'      => 'product',   // product | option
            'option_key'  => 'home_faq', // source=option 时的前缀
            'show_title'  => true,       // true | false
            'class'       => '',         // 模块级修饰类：crs-faq--home | crs-faq--compact
        ], $atts, 'cal_faq');

        // 解析布尔值（兼容 "false" 字符串）
        $show_title = filter_var($atts['show_title'], FILTER_VALIDATE_BOOLEAN);
        $extra_class = sanitize_html_class($atts['class']);

        // ---------- 数据源解析 ----------
        $faq_title = '';
        $faq_items = [];

        if ($atts['source'] === 'option') {
            if (!function_exists('get_field')) {
                return '';
            }
            $faq_title = trim((string) get_field($atts['option_key'] . '_title', 'option'));
            $faq_items = get_field($atts['option_key'] . '_items', 'option');
        } else {
            $post_id = $atts['post_id'] ? absint($atts['post_id']) : get_the_ID();
            if (!$post_id) {
                return '';
            }
            $faq_title = trim((string) get_field('faq_title', $post_id));
            $faq_items = get_field('faq_list', $post_id);
        }

        // 安全门禁
        if ($atts['source'] !== 'option' && $faq_title === '') {
            return '';
        }

        // 数据清洗
        $valid_faqs = [];
        if (is_array($faq_items) && !empty($faq_items)) {
            foreach ($faq_items as $item) {
                $q = trim((string) ($item['faq_question'] ?? $item['question'] ?? ''));
                $a = trim((string) ($item['faq_answer'] ?? $item['answer'] ?? ''));
                if ($q !== '' && $a !== '') {
                    $valid_faqs[] = ['question' => $q, 'answer' => $a];
                }
            }
        }

        if (empty($valid_faqs)) {
            return '';
        }

        // 实例锁
        static $faq_instance = 0;
        $faq_instance++;
        $lock_name = 'cal_global_faq_' . get_the_ID() . '_' . $faq_instance;

        // 模块级 class 拼接
        $wrapper_class = 'crs-faq__inner';
        if ($extra_class) {
            $wrapper_class .= ' ' . esc_attr($extra_class);
        }

        ob_start();
        ?>
        <div class="<?php echo $wrapper_class; ?>" itemscope itemtype="https://schema.org/FAQPage">
            <div class="crs-faq__box">

                <?php if ($show_title && $faq_title !== ''): ?>
                    <h2 class="crs-faq__title">
                        <?php echo cal_get_icon('title_icon', 'crs-icon crs-faq__title-icon'); ?>
                        <span><?php echo esc_html($faq_title); ?></span>
                    </h2>
                <?php endif; ?>

                <div class="crs-faq__list">
                    <?php
                    $index = 0;
        foreach ($valid_faqs as $faq):
            $index++;
            $is_first_open = ($index === 1) ? 'open' : '';
            ?>
                        <details class="crs-faq__item"
                                 name="<?php echo esc_attr($lock_name); ?>"
                                 <?php echo $is_first_open; ?>
                                 itemprop="mainEntity"
                                 itemscope
                                 itemtype="https://schema.org/Question">

                            <summary class="crs-faq__question">
                                <span itemprop="name"><?php echo esc_html($faq['question']); ?></span>
                                <?php echo cal_get_icon('arrow_icon', 'crs-faq__arrow'); ?>
                            </summary>

                            <div class="crs-faq__answer"
                                 itemprop="acceptedAnswer"
                                 itemscope
                                 itemtype="https://schema.org/Answer">
                                <div class="crs-faq__answer-content" itemprop="text">
                                    <?php echo wp_kses_post(wpautop($faq['answer'])); ?>
                                </div>
                            </div>

                        </details>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>
        <?php
        return ob_get_clean();
    });
}


/**
 * =============================================================================
 * 24.5 公共产品 Logo 渲染引擎 - crs_render_product_logo()
 * 全站 Logo 唯一出口：SVG 附件优先内联（缩放无损 + CSS 可控），位图走 <img> lazy
 * 支持四种输入：ACF 图片数组 / 附件 ID / SVG 源码字符串 / URL 字符串
 * 侧边栏卡片（第 12 段）后续也可换用此函数，实现单一数据源
 * =============================================================================
 */
if (!function_exists('crs_render_product_logo')) {
    function crs_render_product_logo($logo_data, $product_name = '')
    {
        if (empty($logo_data)) {
            return '';
        }

        $alt = trim((string) $product_name);
        $alt = $alt !== '' ? $alt . ' logo' : 'Product logo';

        // A：ACF 图片字段（数组返回格式）
        if (is_array($logo_data)) {
            $file = !empty($logo_data['ID']) ? get_attached_file($logo_data['ID']) : '';
            if ($file && file_exists($file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'svg') {
                $svg = @file_get_contents($file);
                if ($svg) {
                    return $svg;
                } // 🎯 SVG 内联输出
            }
            $url = !empty($logo_data['url']) ? $logo_data['url'] : '';
            return $url ? '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" loading="lazy" />' : '';
        }

        // B：附件 ID（数值返回格式）
        if (is_numeric($logo_data)) {
            $file = get_attached_file($logo_data);
            if ($file && file_exists($file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'svg') {
                $svg = @file_get_contents($file);
                if ($svg) {
                    return $svg;
                }
            }
            return wp_get_attachment_image($logo_data, 'thumbnail', false, ['alt' => $alt]);
        }

        // C：SVG 源码字符串（字段直存 SVG 代码）
        if (is_string($logo_data) && strpos($logo_data, '<svg') !== false) {
            return $logo_data;
        }

        // D：URL 字符串
        if (is_string($logo_data)) {
            return '<img src="' . esc_url($logo_data) . '" alt="' . esc_attr($alt) . '" loading="lazy" />';
        }

        return '';
    }
}

/**
 * =============================================================================
 * 25. 相关评测自动化联动简码 - [cal_related]（v2 · 内联 SVG + DOM 精修版）
 * 优化：Logo 走公共内联引擎；名称裁剪空白；空摘要不渲染空标签；补 aria
 * =============================================================================
 */
if (!shortcode_exists('cal_related')) {
    add_shortcode('cal_related', function ($atts) {
        $atts = shortcode_atts(['post_id' => false], $atts, 'cal_related');
        $current_id = $atts['post_id'] ? absint($atts['post_id']) : get_the_ID();
        if (!$current_id) {
            return '';
        }

        // 1. 获取当前页面勾选的关联文章数据
        $related_data = get_field('related_reviews', $current_id);

        // 【安全熔断】未勾选关联文章，整个模块（含标题）物理蒸发
        if (empty($related_data) || !is_array($related_data)) {
            return '';
        }

        $star_icon = function_exists('cal_get_icon') ? cal_get_icon('filled_star_icon') : '';

        // 模块大标题：后台自定义优先，默认兜底
        $custom_title = trim((string) get_field('related_title', $current_id));
        $related_title = $custom_title !== '' ? $custom_title : __('Related Reviews', 'cyberatlaslab');

        ob_start();
        ?>
        <div class="crs-related__inner">
            <h2 class="crs-related__title">
                <?php echo esc_html($related_title); ?>
            </h2>

            <div class="crs-related__grid">
                <?php
                foreach ($related_data as $item):
                    // 智能格式解耦：自动洗成标准文章 ID
                    $r_id = is_object($item) ? $item->ID : absint($item);
                    if (!$r_id || get_post_status($r_id) !== 'publish') {
                        continue;
                    }

                    // 跨页面数据精准穿透提取
                    $r_permalink = get_permalink($r_id);

                    // 产品名称：优先干净字段，文章标题兜底；🛠️ 裁剪字段误带的首尾空格
                    $p_name_field = trim((string) get_field('product_name', $r_id));
                    $r_title = $p_name_field !== '' ? $p_name_field : trim(get_the_title($r_id));

                    // 综合得分（边界限幅 + 精度格式化）
                    $r_score = get_field('score_overall', $r_id);
                    $r_score = is_numeric($r_score) ? max(0, min(10, (float) $r_score)) : 0;
                    $r_score_fmt = rtrim(rtrim(number_format($r_score, 1, '.', ''), '0'), '.');

                    // 产品简介：空值不渲染空标签
                    $r_excerpt = trim((string) get_field('editor_verdict', $r_id));

                    // 产品 Logo：公共引擎，SVG 自动内联
                    $r_logo_html = function_exists('crs_render_product_logo')
                        ? crs_render_product_logo(get_field('product_logo', $r_id), $r_title)
                        : '';
                    ?>
                    <a href="<?php echo esc_url($r_permalink); ?>" class="crs-related__card"
                        aria-label="<?php printf(esc_attr__('Read %s review', 'cyberatlaslab'), esc_attr($r_title)); ?>">
                        <div class="crs-related__card-header">

                            <?php if ($r_logo_html): ?>
                                <div class="crs-related__card-logo">
                                    <?php echo $r_logo_html; ?>
                                </div>
                            <?php endif; ?>

                            <div class="crs-related__card-info">
                                <p class="crs-related__card-name">
                                    <?php echo esc_html($r_title); ?>
                                </p>
                                <span class="crs-related__card-score">
                                    <?php echo $star_icon; ?>
                                    <span>
                                        <?php echo esc_html($r_score_fmt); ?>/10
                                    </span>
                                </span>
                            </div>
                        </div>

                        <?php if ($r_excerpt !== ''): ?>
                            <p class="crs-related__card-excerpt">
                                <?php echo esc_html($r_excerpt); ?>
                            </p>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    });
}

/**
 * =============================================================================
 * 26. 移动端模块排序与TOC控制引入
 * =============================================================================
 */
// 在子主题的 functions.php 中精准引入
add_action('wp_enqueue_scripts', 'crs_enqueue_mobile_reorder');
function crs_enqueue_mobile_reorder()
{
    // 仅在评测单页 (is_single) 且是移动端（可以通过 wp_is_mobile 或在 JS 里判断）时加载
    if (is_single()) {
        wp_enqueue_script(
            'crs-mobile-reorder',
            get_stylesheet_directory_uri() . '/js/crs-mobile-reorder.js',
            array(),
            '1.0.0',
            array('in_footer' => true, 'strategy' => 'defer') // 异步延迟，极致性能
        );
    }
}


/**
 * =========================================================================================================
 * 27. CRS Conversion Framework v4.4.0 - 三层数据源 + 第一方伪装链出口版
 * =========================================================================================================
 * 数据优先级（高 → 低）：
 *   第 1 层：手动简码参数   [crs_cta_m name="X" btn="Y"]
 *   第 2 层：文章本地字段   编辑页直接填写（cta_local_* + affiliate_url，仅 Post/Page）
 *   第 3 层：产品关联数据   item="1" → associated_products → Product CPT 物理直读
 *   全部为空 → 前端整块隐藏
 *
 * Changelog 2026-07-17：
 *   ① 链接出口修正：优先调用 CAL_CRS_URL_Builder 生成 /visit/slug/ 伪装内链，
 *     真实联盟地址不再直接暴露给前端与爬虫；
 *   ② 新增第 2 层文章本地字段覆盖，普通文章/页面可独立承载营销功能；
 *   ③ 信任中继器重构为标准多行循环解析，支持本地整体替换。
 * =========================================================================================================
 */

/**
 * 1. 目标产品解析器 (极简路由解析)
 */
if (!function_exists('crs_resolve_product_id')) {
    function crs_resolve_product_id($target_identifier = null)
    {
        global $post;
        $current_id = (is_object($post) && isset($post->ID)) ? $post->ID : get_the_ID();

        if (empty($current_id)) {
            $current_id = get_the_ID();
        }

        // 如果未传参，且当前页面本身就是某个产品实体（或一篇走本地模式的文章），则直接返回当前 ID
        if (empty($target_identifier)) {
            return $current_id;
        }

        // 处理 item="1" 这种关联产品索引路由
        if (is_numeric($target_identifier) && intval($target_identifier) < 20) {
            $index = intval($target_identifier) - 1;
            $associated_products = [];
            if (!empty($current_id) && function_exists('get_field')) {
                $raw_fields = get_field('associated_products', $current_id);
                if (is_array($raw_fields)) {
                    foreach ($raw_fields as $item) {
                        if (is_object($item) && isset($item->ID)) {
                            $associated_products[] = $item->ID;
                        } elseif (is_numeric($item)) {
                            $associated_products[] = intval($item);
                        }
                    }
                }
            }
            if (!empty($associated_products) && isset($associated_products[$index])) {
                return $associated_products[$index];
            }
            return null;
        }

        // 显式传入了具体的产品 ID
        return intval($target_identifier);
    }
}

/**
 * 2. 联盟链接动态拼接引擎（v3 · 伪装链优先版）
 * 出口原则：永远给用户和搜索引擎看 /visit/ 第一方内链，
 * 真实联盟地址只存在于跳转引擎（Redirect Engine）内部。
 */
if (!function_exists('crs_build_affiliate_link')) {
    function crs_build_affiliate_link($target_id)
    {
        if (empty($target_id) || !function_exists('get_field')) {
            return '#';
        }

        // ① 先确认目标地址物理存在（同时决定前端按钮是否置灰）
        $raw_url = get_field('affiliate_url', $target_id);

        // 🛡️ 兼容 ACF Link 字段的数组返回格式（url/title/target），统一还原为纯字符串
        if (is_array($raw_url)) {
            $raw_url = (!empty($raw_url['url']) && is_string($raw_url['url'])) ? $raw_url['url'] : '';
        }

        if (empty($raw_url) || !is_string($raw_url)) {
            return '#';
        }

        // ② 优先走 v5.9 框架统一出口：生成 /visit/slug/ 伪装内链，点击后由跳转引擎 302 承接
        if (class_exists('CAL_CRS_URL_Builder')) {
            return CAL_CRS_URL_Builder::build($target_id);
        }

        // ③ 框架缺席时的降级应急通道（正常不会走到）：维持旧逻辑直出
        if (filter_var($raw_url, FILTER_VALIDATE_URL) !== false || strpos($raw_url, '//') === 0) {
            return esc_url($raw_url);
        }

        $prefix = get_field('affiliate_prefix', $target_id);
        if (!empty($prefix) && is_string($prefix)) {
            return esc_url(home_url('/' . trim($prefix, '/') . '/' . trim($raw_url, '/')));
        }

        return esc_url(home_url('/' . trim($raw_url, '/')));
    }
}

/**
 * 2.5 信任印章中继器解析器（多行标准循环，产品层与本地层共用）
 */
if (!function_exists('crs_parse_trust_items')) {
    function crs_parse_trust_items($raw_trust)
    {
        $items = [];
        if (is_array($raw_trust) && !empty($raw_trust)) {
            $trust_fields = ['trust_team', 'trust_time', 'trust_refund', 'trust_feature'];
            foreach ($raw_trust as $row) {
                foreach ($trust_fields as $f_key) {
                    if (!empty($row[$f_key])) {
                        $items[] = $row[$f_key];
                    }
                }
            }
        }
        return $items;
    }
}

/**
 * 3. 统一数据工厂（三层数据源：Product 关联 → 文章本地 → 手动参数）
 */
if (!function_exists('crs_get_cta_hybrid_data')) {
    function crs_get_cta_hybrid_data($target_id, $atts)
    {
        // 场景 D：手动降级沙盒（直接利用短代码传参渲染，不读数据库）
        if (!empty($atts['name'])) {
            return [
                'name' => $atts['name'],
                'score' => !empty($atts['score']) ? floatval($atts['score']) : '',
                'currency' => !empty($atts['currency']) ? $atts['currency'] : '$',
                'price' => !empty($atts['price']) ? $atts['price'] : '',
                'price_period' => !empty($atts['price_period']) ? $atts['price_period'] : '/mo',
                'discount' => !empty($atts['discount']) ? $atts['discount'] : '',
                'billing_details' => !empty($atts['billing_details']) ? $atts['billing_details'] : '',
                'refund' => !empty($atts['refund']) ? $atts['refund'] : '',
                'aff_link' => !empty($atts['url']) ? esc_url($atts['url']) : '#',
                'is_manual' => true,
                'cta_s_label' => !empty($atts['label']) ? $atts['label'] : '',
                'cta_s_offer' => !empty($atts['offer']) ? $atts['offer'] : '',
                'cta_s_btn' => !empty($atts['btn']) ? $atts['btn'] : '',
                'cta_m_label' => !empty($atts['label']) ? $atts['label'] : '',
                'cta_m_btn' => !empty($atts['btn']) ? $atts['btn'] : '',
                'cta_l_label' => !empty($atts['label']) ? $atts['label'] : '',
                'cta_l_btn' => !empty($atts['btn']) ? $atts['btn'] : '',
                'trust_items' => []
            ];
        }

        if (empty($target_id) || !function_exists('get_field')) {
            return null;
        }

        // 🎯 第 3 层（垫底数据）：Product 关联实体物理直驱
        $data = [
            'name' => get_field('product_name', $target_id),
            'score' => get_field('score_overall', $target_id),
            'currency' => get_field('price_currency', $target_id) ?: '$',
            'price' => get_field('price_lowest', $target_id),
            'price_period' => get_field('price_period', $target_id) ?: '/mo',
            'discount' => get_field('current_discount', $target_id),
            'billing_details' => get_field('billing_details', $target_id),
            'refund' => get_field('refund_policy', $target_id),
            'aff_link' => crs_build_affiliate_link($target_id),
            'is_manual' => false,

            // 按钮与文案：绑定 Target 实体
            'cta_s_label' => get_field('cta_s_label', $target_id),
            'cta_s_offer' => get_field('cta_s_offer', $target_id),
            'cta_s_btn' => get_field('cta_s_btn', $target_id),
            'cta_m_label' => get_field('cta_m_label', $target_id),
            'cta_m_btn' => get_field('cta_m_btn', $target_id),
            'cta_l_label' => get_field('cta_l_label', $target_id),
            'cta_l_btn' => get_field('cta_l_btn', $target_id),
            'trust_items' => []
        ];

        // 🏆 信任印章中继器：多行循环解析
        $data['trust_items'] = crs_parse_trust_items(get_field('cta_trust_items', $target_id));

        // 🌍 第 2 层：文章本地字段覆盖（教程/普通页面场景，优先级高于产品关联数据，低于手动参数）
        // 无产品场景自动生效：不传 item 时 target_id 即当前文章，产品层全空，本地层填上即成完整数据
        global $post;
        $local_post_id = (is_object($post) && isset($post->ID)) ? $post->ID : 0;

        // 🛡️ 覆盖层作用域限定：产品页通过 item 调用"其他产品"时，
        // 跳过本地覆盖，保证每个 CTA 展示目标产品自己的数据
        $is_cross_product_call = (get_post_type($local_post_id) === 'product') && ($local_post_id !== $target_id);

        if ($local_post_id && !$is_cross_product_call) {
            // 本地展示数据：填了才覆盖，空值不动（cta_local_* 字段组仅绑定 Post/Page）
            $local_map = [
                'cta_local_name' => 'name',
                'cta_local_price' => 'price',
                'cta_local_price_period' => 'price_period',
                'cta_local_score' => 'score',
                'cta_local_discount' => 'discount',
                'cta_local_billing' => 'billing_details',
            ];
            foreach ($local_map as $field_name => $data_key) {
                $local_val = get_field($field_name, $local_post_id);
                if (!empty($local_val)) {
                    $data[$data_key] = $local_val;
                }
            }

            // 本地 CTA 文案：CTA营销组已绑定三类型，文章填写即覆盖产品关联数据
            $cta_fields = ['cta_s_label', 'cta_s_offer', 'cta_s_btn', 'cta_m_label', 'cta_m_btn', 'cta_l_label', 'cta_l_btn'];
            foreach ($cta_fields as $f) {
                $local_val = get_field($f, $local_post_id);
                if (!empty($local_val)) {
                    $data[$f] = $local_val;
                }
            }

            // 本地信任矩阵：本篇文章填了则整体替换
            $local_trust = crs_parse_trust_items(get_field('cta_trust_items', $local_post_id));
            if (!empty($local_trust)) {
                $data['trust_items'] = $local_trust;
            }

            // 本地联盟链接：文章自存目标地址时，重算跳转链接（同样走 /visit/ 伪装链）
            $local_url = get_field('affiliate_url', $local_post_id);
            if (!empty($local_url)) {
                $data['aff_link'] = crs_build_affiliate_link($local_post_id);
            }
        }

        // 🥇 第 1 层：短代码内显式传参（Shortcode Attributes）拥有最高优先覆盖权
        if (!empty($atts['name'])) {
            $data['name'] = $atts['name'];
        }
        if (!empty($atts['price'])) {
            $data['price'] = $atts['price'];
        }
        if (!empty($atts['price_period'])) {
            $data['price_period'] = $atts['price_period'];
        }
        if (!empty($atts['currency'])) {
            $data['currency'] = $atts['currency'];
        }
        if (!empty($atts['discount'])) {
            $data['discount'] = $atts['discount'];
        }
        if (!empty($atts['billing_details'])) {
            $data['billing_details'] = $atts['billing_details'];
        }
        if (!empty($atts['score'])) {
            $data['score'] = floatval($atts['score']);
        }
        if (!empty($atts['url'])) {
            $data['aff_link'] = esc_url($atts['url']);
        }

        if (!empty($atts['label'])) {
            $data['cta_s_label'] = $data['cta_m_label'] = $data['cta_l_label'] = $atts['label'];
        }
        if (!empty($atts['offer'])) {
            $data['cta_s_offer'] = $atts['offer'];
        }
        if (!empty($atts['btn'])) {
            $data['cta_s_btn'] = $data['cta_m_btn'] = $data['cta_l_btn'] = $atts['btn'];
        }

        for ($i = 1; $i <= 4; $i++) {
            if (!empty($atts["trust_{$i}"])) {
                $data['trust_items'][$i - 1] = $atts["trust_{$i}"];
            }
        }

        return !empty($data['name']) ? $data : null;
    }
}

/**
 * 4. 主短代码解析与分流分配器
 */
if (!function_exists('crs_cta_shortcode')) {
    function crs_cta_shortcode($atts)
    {
        $atts = shortcode_atts([
            'type' => 'm',
            'item' => '',
            'position' => 'inline',
            'name' => '',
            'url' => '',
            'price' => '',
            'price_period' => '',
            'currency' => '',
            'discount' => '',
            'billing_details' => '',
            'score' => '',
            'refund' => '',
            'label' => '',
            'offer' => '',
            'btn' => '',
            'trust_1' => '',
            'trust_2' => '',
            'trust_3' => '',
            'trust_4' => '',
        ], $atts, 'crs_cta');

        $target_id = crs_resolve_product_id($atts['item']);
        $data = crs_get_cta_hybrid_data($target_id, $atts);

        if (!$data || empty($data['name'])) {
            return '';
        }

        $has_icon = function_exists('cal_get_icon');
        $type = strtolower($atts['type']);

        switch ($type) {
            case 's':
                return crs_render_cta_s($data, $atts, $has_icon);
            case 'l':
                return crs_render_cta_l($data, $atts, $has_icon);
            case 'm':
            default:
                return crs_render_cta_m($data, $atts, $has_icon);
        }
    }
}

if (!shortcode_exists('crs_cta')) {
    add_shortcode('crs_cta', 'crs_cta_shortcode');
}

/**
 * 5. CTA-S 渲染模板
 */
if (!function_exists('crs_render_cta_s')) {
    function crs_render_cta_s($data, $atts, $has_icon)
    {
        $is_disabled = (empty($data['aff_link']) || $data['aff_link'] === '#');
        $tracking = crs_get_tracking_attrs('cta_s', $data['name'], $atts['position'], $data['is_manual']);

        $arrow_icon = $has_icon ? cal_get_icon('right_arrow_icon') : '→';
        $tip_icon = $has_icon ? cal_get_icon('cta_s_icon') : '💡';

        ob_start();
        ?>
        <div class="crs-cta crs-cta--s" <?php echo $tracking; ?>>
            <?php if (!empty($data['cta_s_label'])): ?>
                <div class="crs-cta__label">
                    <span class="crs-cta__label-icon" aria-hidden="true"><?php echo $tip_icon; ?></span>
                    <span class="crs-cta__label-text"><?php echo esc_html($data['cta_s_label']); ?></span>
                </div>
            <?php endif; ?>

            <div class="crs-cta__content">
                <strong class="crs-cta__name"><?php echo esc_html($data['name']); ?></strong>
                <?php if (!empty($data['cta_s_offer'])): ?>
                    <span class="crs-cta__deal"><?php echo esc_html($data['cta_s_offer']); ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($data['cta_s_btn'])): ?>
                <a href="<?php echo esc_url($data['aff_link']); ?>"
                    class="crs-cta__link <?php echo $is_disabled ? 'crs-cta__link--disabled' : ''; ?>" target="_blank"
                    rel="nofollow noopener sponsored"
                    style="<?php echo $is_disabled ? 'pointer-events: none; opacity: 0.6;' : ''; ?>"
                    aria-label="<?php printf(esc_attr__('Check %s latest deal', 'cyberatlaslab'), esc_attr($data['name'])); ?>">
                    <span class="crs-cta__link-text"><?php echo esc_html($data['cta_s_btn']); ?></span>
                    <span class="crs-cta__link-arrow" aria-hidden="true"><?php echo $arrow_icon; ?></span>
                </a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * 6. CTA-M 渲染模板
 */
if (!function_exists('crs_render_cta_m')) {
    function crs_render_cta_m($data, $atts, $has_icon)
    {
        $is_disabled = (empty($data['aff_link']) || $data['aff_link'] === '#');
        $tracking = crs_get_tracking_attrs('cta_m', $data['name'], $atts['position'], $data['is_manual']);

        $arrow_icon = $has_icon ? cal_get_icon('right_arrow_icon') : '→';
        $star_icon = $has_icon ? cal_get_icon('filled_star_icon') : '★';

        ob_start();
        ?>
        <div class="crs-cta crs-cta--m" <?php echo $tracking; ?>>
            <div class="crs-cta__info">
                <?php if (!empty($data['cta_m_label'])): ?>
                    <div class="crs-cta__label">
                        <span class="crs-cta__label-icon" aria-hidden="true"><?php echo $star_icon; ?></span>
                        <span class="crs-cta__label-text"><?php echo esc_html($data['cta_m_label']); ?></span>
                    </div>
                <?php endif; ?>

                <div class="crs-cta__name"><?php echo esc_html($data['name']); ?></div>

                <?php if (!empty($data['discount'])): ?>
                    <div class="crs-cta__deal">
                        <span class="crs-cta__highlight"><?php echo esc_html($data['discount']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($data['price'])): ?>
                    <div class="crs-cta__price-row">
                        <span class="crs-cta__price-val">
                            <?php echo esc_html($data['currency'] . $data['price'] . $data['price_period']); ?>
                        </span>
                        <?php if (!empty($data['billing_details'])): ?>
                            <span class="crs-cta__billing-details"><?php echo esc_html($data['billing_details']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($data['cta_m_btn'])): ?>
                <div class="crs-cta__action">
                    <a href="<?php echo esc_url($data['aff_link']); ?>"
                        class="crs-cta__btn <?php echo $is_disabled ? 'crs-cta__btn--disabled' : ''; ?>" target="_blank"
                        rel="nofollow noopener sponsored"
                        style="<?php echo $is_disabled ? 'pointer-events: none; opacity: 0.6;' : ''; ?>"
                        aria-label="<?php printf(esc_attr__('Claim deal for %s', 'cyberatlaslab'), esc_attr($data['name'])); ?>">
                        <span class="crs-cta__btn-text"><?php echo esc_html($data['cta_m_btn']); ?></span>
                        <span class="crs-cta__btn-arrow" aria-hidden="true"><?php echo $arrow_icon; ?></span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * 7. CTA-L 渲染模板
 */
if (!function_exists('crs_render_cta_l')) {
    function crs_render_cta_l($data, $atts, $has_icon)
    {
        $is_disabled = (empty($data['aff_link']) || $data['aff_link'] === '#');
        $tracking = crs_get_tracking_attrs('cta_l', $data['name'], $atts['position'], $data['is_manual']);

        $arrow_icon = $has_icon ? cal_get_icon('right_arrow_icon') : '→';
        $trophy_icon = $has_icon ? cal_get_icon('cta_l_icon') : '🏆';

        ob_start();
        ?>
        <div class="crs-cta crs-cta--l" <?php echo $tracking; ?>>
            <div class="crs-cta__main">
                <div class="crs-cta__info">
                    <?php if (!empty($data['cta_l_label'])): ?>
                        <div class="crs-cta__label">
                            <span class="crs-cta__label-icon" aria-hidden="true"><?php echo $trophy_icon; ?></span>
                            <span class="crs-cta__label-text"><?php echo esc_html($data['cta_l_label']); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="crs-cta__meta">
                        <span class="crs-cta__name"><?php echo esc_html($data['name']); ?></span>
                        <?php if (!empty($data['score'])): ?>
                            <span class="crs-cta__score"><?php echo number_format(floatval($data['score']), 1); ?> / 10</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($data['discount'])): ?>
                        <div class="crs-cta__deal"><?php echo esc_html($data['discount']); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($data['price'])): ?>
                        <div class="crs-cta__price-row">
                            <span class="crs-cta__price-val">
                                <?php echo esc_html($data['currency'] . $data['price'] . $data['price_period']); ?>
                            </span>
                            <?php if (!empty($data['billing_details'])): ?>
                                <span class="crs-cta__billing-details"><?php echo esc_html($data['billing_details']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($data['cta_l_btn'])): ?>
                    <div class="crs-cta__action">
                        <a href="<?php echo esc_url($data['aff_link']); ?>"
                            class="crs-cta__btn <?php echo $is_disabled ? 'crs-cta__btn--disabled' : ''; ?>" target="_blank"
                            rel="nofollow noopener sponsored"
                            style="<?php echo $is_disabled ? 'pointer-events: none; opacity: 0.6;' : ''; ?>"
                            aria-label="<?php printf(esc_attr__('Get %s and claim exclusive reader offer', 'cyberatlaslab'), esc_attr($data['name'])); ?>">
                            <span class="crs-cta__btn-text"><?php echo esc_html($data['cta_l_btn']); ?></span>
                            <span class="crs-cta__btn-arrow" aria-hidden="true"><?php echo $arrow_icon; ?></span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($data['trust_items']) && is_array($data['trust_items'])): ?>
                <div class="crs-cta__trust">
                    <div class="crs-cta__trust-grid">
                        <?php foreach ($data['trust_items'] as $item): ?>
                            <?php if (!empty($item)): ?>
                                <div class="crs-cta__trust-item">
                                    <span class="crs-cta__trust-icon" aria-hidden="true">
                                        <?php echo $has_icon ? cal_get_icon('crs_check_icon') : '✓'; ?>
                                    </span>
                                    <span class="crs-cta__trust-text"><?php echo esc_html($item); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * 8. 辅助函数：安全转义并拼装 HTML5 自定义追踪参数
 */
if (!function_exists('crs_get_tracking_attrs')) {
    function crs_get_tracking_attrs($cta_type, $product_name, $position, $is_manual = false)
    {
        return sprintf(
            'data-cta-type="%s" data-product="%s" data-position="%s" data-mode="%s"',
            esc_attr($cta_type),
            esc_attr($product_name),
            esc_attr($position),
            esc_attr($is_manual ? 'manual' : 'cpt')
        );
    }
}

/**
 * 9. 追踪引擎：页脚轻量级通用数据流推送脚本
 */
if (!function_exists('crs_cta_tracking_script')) {
    function crs_cta_tracking_script()
    {
        if (!is_singular(['post', 'product', 'page'])) {
            return;
        }

        $page_type = is_singular('product') ? 'single_product' : (is_singular('page') ? 'page' : 'aggregate_post');
        ?>
        <script>
            (function () {
                'use strict';
                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.crs-cta__btn, .crs-cta__link, .crs-sidebar__cta').forEach(function (el) {
                        el.addEventListener('click', function (e) {
                            var cta = this.closest('.crs-cta') || this.closest('.crs-sidebar__card');
                            if (!cta) return;

                            var ctaType = cta.getAttribute('data-cta-type') || 'sidebar';
                            var product = cta.getAttribute('data-product') || cta.parentNode.getAttribute('data-product') || 'unknown';
                            var position = cta.getAttribute('data-position') || 'sidebar';
                            var mode = cta.getAttribute('data-mode') || 'cpt';

                            if (typeof dataLayer !== 'undefined') {
                                dataLayer.push({
                                    'event': 'click_affiliate',
                                    'product_name': product,
                                    'cta_type': ctaType,
                                    'page_type': '<?php echo esc_js($page_type); ?>',
                                    'position': position,
                                    'tracking_mode': mode
                                });
                            }
                        });
                    });
                });
            })();
        </script>
        <?php
    }
}
add_action('wp_footer', 'crs_cta_tracking_script', 99);

/**
 * 10. 高效闭环：批量动态注册别名简码
 */
add_action('init', function () {
    $types = ['s', 'm', 'l'];
    foreach ($types as $type) {
        $shortcode_name = "crs_cta_{$type}";
        if (!shortcode_exists($shortcode_name)) {
            add_shortcode($shortcode_name, function ($atts) use ($type) {
                $atts = is_array($atts) ? $atts : [];
                $atts['type'] = $type;
                return crs_cta_shortcode($atts);
            });
        }
    }
});


/**
 * =============================================================================
 * 28. crs_tech_snapshot — 多品类技术规格快照（Raw Value + cal_get_icon 版）
 * =============================================================================
 * 简码：[crs_tech_snapshot]
 * 依赖：Advanced Custom Fields (ACF) + cal_get_icon() 图标库
 * =============================================================================
 */

if (!function_exists('crs_tech_snapshot_shortcode')) {

    /**
     * 标签名称映射表 — 仅负责显示文本规范化
     */
    function crs_get_tag_map()
    {
        return [
            // Protocols
            'wireguard' => 'WireGuard',
            'openvpn' => 'OpenVPN',
            'ikev2' => 'IKEv2',
            'l2tp' => 'L2TP',
            'lightway' => 'Lightway',
            'nordlynx' => 'NordLynx',
            'chacha20' => 'ChaCha20',
            'aes_256' => 'AES-256',
            'aes-256' => 'AES-256',
            'tls_13' => 'TLS 1.3',

            // VPN Security
            'ram_only' => 'RAM-only Servers',
            'pfs' => 'Perfect Forward Secrecy',
            'dns_leak' => 'DNS Leak Protection',
            'ipv6_leak' => 'IPv6 Leak Protection',
            'quantum_safe' => 'Quantum-Safe Ready',
            'onion_over_vpn' => 'Onion Over VPN',
            'audit' => 'Independent Audit',

            // Antivirus
            'realtime_protection' => 'Real-Time Protection',
            'heurisitic_analysis' => 'Heuristic Analysis',
            'ransomware_shield' => 'Ransomware Shield',
            'firewall_integrated' => 'Advanced Firewall',
            'phishing_protection' => 'Anti-Phishing Shield',
            'sandbox_env' => 'Sandbox Environment',
            'zero_day_defense' => 'Zero-Day Exploit Defense',
            'webcam_protection' => 'Webcam & Mic Shield',

            // Password
            'zero_knowledge' => 'Zero-Knowledge Architecture',
            'two_factor_auth' => '2FA / MFA Support',
            'biometric_unlock' => 'Biometric Login',
            'passkey_support' => 'Passkey Integration',
            'breach_watch' => 'Dark Web Monitoring',
            'password_audit' => 'Vault Security Audit',
            'secure_sharing' => 'Encrypted Sharing',
            'emergency_access' => 'Emergency Access',

            // Parental
            'screen_time' => 'Screen Time Limits',
            'app_blocking' => 'App & Game Blocker',
            'web_filtering' => 'Content Filtering',
            'gps_tracking' => 'Live GPS Tracking',
            'geofencing' => 'Geofencing Alerts',
            'social_monitor' => 'Social Monitoring',
            'call_sms_log' => 'Call & SMS Logging',
            'panic_button' => 'SOS Panic Button',

            // Features
            'kill_switch' => 'Kill Switch',
            'split_tunneling' => 'Split Tunneling',
            'double_vpn' => 'Double VPN',
            'ad_blocker' => 'Ad Blocker',
            'obfuscation' => 'Obfuscation',
        ];
    }

    /**
     * 高亮规则配置 — 基于 Raw Value，与显示文本完全解耦
     */
    function crs_get_highlight_rules()
    {
        return [
            'protocols' => [
                'wireguard',
                'lightway',
                'nordlynx',
                'tls_13',
            ],
            'security' => [
                'ram_only',
                'zero_knowledge',
                'audit',
                'zero_day_defense',
                'realtime_protection',
                'pfs',
                'quantum_safe',
            ],
            'features' => [
                'kill_switch',
                'double_vpn',
            ],
        ];
    }

    /**
     * 清洗原始标签值
     */
    function crs_normalize_tag($raw, $map = [])
    {
        if (empty($raw)) {
            return '';
        }

        $key = strtolower(trim($raw));
        $key = str_replace(['-', ' ', '.'], '_', $key);

        if (isset($map[$key])) {
            return $map[$key];
        }

        $clean = preg_replace('/[\x{4e00}-\x{9fa5}]/u', '', $raw);
        $clean = trim(str_replace([':', '：'], '', $clean));
        $clean = ucwords(str_replace('_', ' ', $clean));

        return $clean ?: $raw;
    }

    /**
     * 品类配置映射
     */
    function crs_get_category_config($category)
    {
        $configs = [
            'vpn' => [
                'field' => 'vpn_security_features',
                'desc' => __('Encryption & infrastructure.', 'cyberatlas-core'),
            ],
            'security' => [
                'field' => 'antivirus_security_features',
                'desc' => __('Threat defense layers.', 'cyberatlas-core'),
            ],
            'password' => [
                'field' => 'password_security_features',
                'desc' => __('Vault encryption & auth.', 'cyberatlas-core'),
            ],
            'parental' => [
                'field' => 'parental_security_features',
                'desc' => __('Monitoring & safety controls.', 'cyberatlas-core'),
            ],
        ];

        return $configs[$category] ?? $configs['vpn'];
    }

    /**
     * 主简码核心引擎
     */
    function crs_tech_snapshot_shortcode($atts = [])
    {
        $post_id = get_the_ID();
        if (!$post_id) {
            return '';
        }

        $product_name = get_the_title($post_id);
        if (empty($product_name)) {
            return '';
        }

        if (!function_exists('get_field')) {
            return '';
        }

        $category = get_field('product_category', $post_id) ?: 'vpn';
        $cfg = crs_get_category_config($category);
        $tag_map = crs_get_tag_map();
        $hl_rules = crs_get_highlight_rules();

        $headquarters = get_field('headquarters', $post_id) ?: '';
        $founded_year = get_field('founded_year', $post_id) ?: '';

        /* ── Platform ── */
        $raw_platforms = (array) get_field('supported_platforms', $post_id);
        $primary_order = ['Windows', 'macOS', 'Android', 'iOS', 'Linux'];
        $primary_tags = [];
        $secondary_tags = [];

        foreach ($raw_platforms as $platform) {
            $is_primary = false;
            foreach ($primary_order as $p) {
                if (stripos($platform, $p) !== false) {
                    $primary_tags[] = $p;
                    $is_primary = true;
                    break;
                }
            }
            if (!$is_primary && !empty($platform)) {
                $secondary_tags[] = [
                    'raw' => $platform,
                    'label' => crs_normalize_tag($platform, $tag_map),
                ];
            }
        }
        $primary_tags = array_values(array_unique($primary_tags));
        usort($primary_tags, function ($a, $b) use ($primary_order) {
            $pos_a = array_search($a, $primary_order);
            $pos_b = array_search($b, $primary_order);
            return ($pos_a === false ? 999 : $pos_a) <=> ($pos_b === false ? 999 : $pos_b);
        });

        /* ── Protocols（保留 Raw Value） ── */
        $raw_protocols = (array) get_field('supported_protocols', $post_id);
        $protocols = [];
        foreach ($raw_protocols as $raw) {
            $clean = crs_normalize_tag($raw, $tag_map);
            if ($clean) {
                $protocols[] = [
                    'raw' => strtolower(str_replace(['-', ' ', '.'], '_', $raw)),
                    'label' => $clean,
                ];
            }
        }

        /* ── Security（保留 Raw Value） ── */
        $raw_sec = (array) get_field($cfg['field'], $post_id);
        $security_tags = [];
        foreach ($raw_sec as $raw) {
            $clean = crs_normalize_tag($raw, $tag_map);
            if ($clean) {
                $security_tags[] = [
                    'raw' => strtolower(str_replace(['-', ' ', '.'], '_', $raw)),
                    'label' => $clean,
                ];
            }
        }

        // 品类前置高亮
        if ($category === 'security' && $malware_rate = get_field('malware_detection_rate', $post_id)) {
            array_unshift($security_tags, [
                'raw' => 'malware_protection',
                'label' => 'Malware Protection: ' . trim($malware_rate),
            ]);
        } elseif ($category === 'password' && $enc_arch = get_field('encryption_architecture', $post_id)) {
            array_unshift($security_tags, [
                'raw' => 'encryption_architecture',
                'label' => trim($enc_arch),
            ]);
        }

        /* ── Features（保留 Raw Value） ── */
        $raw_features = (array) get_field('special_features', $post_id);
        $features = [];
        foreach ($raw_features as $raw) {
            $clean = crs_normalize_tag($raw, $tag_map);
            if ($clean) {
                $features[] = [
                    'raw' => strtolower(str_replace(['-', ' ', '.'], '_', $raw)),
                    'label' => $clean,
                ];
            }
        }

        /* ── Log Policy ── */
        $log_policy = get_field('log_policy', $post_id) ?: '';
        $log_policy_clean = crs_normalize_tag($log_policy, $tag_map);
        $is_secure_policy = stripos($log_policy_clean, 'minimal') !== false
            || stripos($log_policy_clean, 'no') !== false
            || stripos($log_policy_clean, 'zero') !== false;

        ob_start();
        ?>
        <section class="crs-snapshot" aria-labelledby="crs-snapshot-title" data-product-id="<?php echo esc_attr($post_id); ?>"
            data-category="<?php echo esc_attr($category); ?>">

            <header class="crs-snapshot__header">
                <h2 id="crs-snapshot-title" class="crs-snapshot__title">
                    <?php if (function_exists('cal_get_icon')): ?>
                        <?php echo cal_get_icon('technical_snapshot_icon', 'crs-icon-snapshot'); ?>
                    <?php endif; ?>
                    <span><?php _e('Technical Snapshot', 'cyberatlas-core'); ?></span>
                </h2>
                <p class="crs-snapshot__subtitle">
                    <?php _e('Key technical specifications.', 'cyberatlas-core'); ?>
                </p>
            </header>

            <?php if (!empty($primary_tags) || !empty($secondary_tags)): ?>
                <div class="crs-snapshot__row">
                    <div class="crs-snapshot__label-zone">
                        <strong class="crs-snapshot__label"><?php _e('Platform', 'cyberatlas-core'); ?></strong>
                        <span class="crs-snapshot__label-desc"><?php _e('OS availability.', 'cyberatlas-core'); ?></span>
                    </div>
                    <div class="crs-snapshot__content">
                        <?php foreach ($primary_tags as $tag): ?>
                            <span class="crs-snapshot__tag"><?php echo esc_html($tag); ?></span>
                        <?php endforeach; ?>
                        <?php foreach ($secondary_tags as $tag): ?>
                            <span class="crs-snapshot__tag crs-snapshot__tag--secondary"><?php echo esc_html($tag['label']); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($protocols)): ?>
                <div class="crs-snapshot__row">
                    <div class="crs-snapshot__label-zone">
                        <strong class="crs-snapshot__label"><?php _e('Protocols', 'cyberatlas-core'); ?></strong>
                        <span class="crs-snapshot__label-desc"><?php _e('Tunneling methods.', 'cyberatlas-core'); ?></span>
                    </div>
                    <div class="crs-snapshot__content">
                        <?php foreach ($protocols as $protocol): ?>
                            <?php
                            $is_hl = in_array($protocol['raw'], $hl_rules['protocols']);
                            $hl_class = $is_hl ? ' crs-snapshot__tag--highlight' : '';
                            ?>
                            <span
                                class="crs-snapshot__tag<?php echo esc_attr($hl_class); ?>"><?php echo esc_html($protocol['label']); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($security_tags)): ?>
                <div class="crs-snapshot__row">
                    <div class="crs-snapshot__label-zone">
                        <strong class="crs-snapshot__label"><?php _e('Security', 'cyberatlas-core'); ?></strong>
                        <span class="crs-snapshot__label-desc"><?php echo esc_html($cfg['desc']); ?></span>
                    </div>
                    <div class="crs-snapshot__content">
                        <?php foreach ($security_tags as $tag): ?>
                            <?php
                            $is_hl = in_array($tag['raw'], $hl_rules['security']);
                            $hl_class = $is_hl ? ' crs-snapshot__tag--highlight' : '';
                            ?>
                            <span
                                class="crs-snapshot__tag<?php echo esc_attr($hl_class); ?>"><?php echo esc_html($tag['label']); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($features)): ?>
                <div class="crs-snapshot__row">
                    <div class="crs-snapshot__label-zone">
                        <strong class="crs-snapshot__label"><?php _e('Features', 'cyberatlas-core'); ?></strong>
                        <span class="crs-snapshot__label-desc"><?php _e('Advanced utilities.', 'cyberatlas-core'); ?></span>
                    </div>
                    <div class="crs-snapshot__content">
                        <?php foreach ($features as $feature): ?>
                            <?php
                            $is_hl = in_array($feature['raw'], $hl_rules['features']);
                            $hl_class = $is_hl ? ' crs-snapshot__tag--highlight' : '';
                            ?>
                            <span
                                class="crs-snapshot__tag<?php echo esc_attr($hl_class); ?>"><?php echo esc_html($feature['label']); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="crs-snapshot__row">
                <div class="crs-snapshot__label-zone">
                    <strong class="crs-snapshot__label"><?php _e('Company & Privacy', 'cyberatlas-core'); ?></strong>
                    <span class="crs-snapshot__label-desc"><?php _e('Vendor & jurisdiction.', 'cyberatlas-core'); ?></span>
                </div>
                <div class="crs-snapshot__content">
                    <dl class="crs-snapshot__kv-group">
                        <?php if ($log_policy_clean): ?>
                            <div class="crs-snapshot__kv-item">
                                <dt class="crs-snapshot__kv-label"><?php _e('Data Policy:', 'cyberatlas-core'); ?></dt>
                                <dd
                                    class="crs-snapshot__kv-value<?php echo $is_secure_policy ? ' crs-snapshot__kv-value--secure' : ''; ?>">
                                    <?php echo esc_html($log_policy_clean); ?>
                                </dd>
                            </div>
                        <?php endif; ?>

                        <?php if ($headquarters): ?>
                            <div class="crs-snapshot__kv-item">
                                <dt class="crs-snapshot__kv-label"><?php _e('Headquarters:', 'cyberatlas-core'); ?></dt>
                                <dd class="crs-snapshot__kv-value"><?php echo esc_html($headquarters); ?></dd>
                            </div>
                        <?php endif; ?>

                        <?php if ($founded_year): ?>
                            <div class="crs-snapshot__kv-item">
                                <dt class="crs-snapshot__kv-label"><?php _e('Founded:', 'cyberatlas-core'); ?></dt>
                                <dd class="crs-snapshot__kv-value"><?php echo esc_html($founded_year); ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

        </section>
        <?php
        return ob_get_clean();
    }

    add_shortcode('crs_tech_snapshot', 'crs_tech_snapshot_shortcode');
}


/**
 * =============================================================================
 * 29. - 正文流原生独立互斥折叠 FAQ 嵌套简码引擎（CRS系统统一标准版）
 * =============================================================================
 * * 架构优化说明：
 * 1. 彻底斩断跨章节 Bug：取消写死的 `name="cal_global_faq"`，改由当前文章 ID 与动态唯一 Section ID
 * 自动拼接，保证每组 FAQ 拥有独立闭环的手风琴互斥域。
 * 2. 极致 SEO 语义降维：章节内 FAQ 全面剥离 `FAQPage` 等微数据标记，精简为标准 HTML5 容器，
 * 彻底杜绝一个页面出现多个 FAQPage 导致的结构化数据混乱判定。
 * 3. 完美兼容老样式：保留全站统一的 `crs-faq__` 空间类名，视觉表现完美向下兼容。
 * =============================================================================
 */

// 初始化全局状态包（由于内层简码需要知道外层简码生成的唯一 name，改用全局关联数组传递状态）
global $crs_post_faq_runtime;
$crs_post_faq_runtime = [
    'index' => 0,
    'name' => 'faq_default'
];

/**
 * -----------------------------------------------------------------------------
 * 核心组件一：[crs_faq] 正文外层总包裹容器简码
 * -----------------------------------------------------------------------------
 */
if (!function_exists('crs_post_faq_outer_shortcode')) {
    function crs_post_faq_outer_shortcode($atts, $content = null)
    {
        if (empty($content)) {
            return '';
        }

        // 解析外层属性，支持在文章里手动传入 section（如 [crs_faq section="speed"]）
        $pairs = shortcode_atts([
            'section' => '',
        ], $atts, 'crs_faq');

        global $crs_post_faq_runtime;

        // 1. 动态生成永不重复的局部闭琴互斥域 name 属性
        $post_id = get_the_ID() ? get_the_ID() : 'global';

        if (!empty($pairs['section'])) {
            // 如果用户手动传入了 section="speed"
            $uniq_name = 'faq_' . $post_id . '_' . sanitize_title($pairs['section']);
        } else {
            // 如果用户偷懒没写，则通过高精随机指纹兜底，保证同页面多次调用绝对安全隔离
            $uniq_name = 'faq_' . $post_id . '_' . wp_generate_password(6, false, false);
        }

        // 2. 将当前运行时的计数器归零，并将动态唯一 name 注入状态机，供内层 [crs_faq_item] 动态读取
        $crs_post_faq_runtime['index'] = 0;
        $crs_post_faq_runtime['name'] = $uniq_name;

        // 防御机制 A：强力清洗古腾堡编辑器闭合边界处产生的所有游离空行
        $clean_content = preg_replace('/\]\s+\[/', '][', trim($content));
        $clean_content = preg_replace('/\]<br \/>\s+\[/', '][', $clean_content);

        // 解析并渲染内部的 [crs_faq_item] 简码
        $nested_items = do_shortcode($clean_content);

        // 防御机制 B：物理抹除残留多余坏死标签（如空段落和游离换行）
        $nested_items = str_replace(['<br>', '<br />', '<p></p>'], '', $nested_items);

        ob_start();
        ?>
        <section class="crs-faq crs-faq--post-fluid">
            <div class="crs-faq__inner">
                <div class="crs-faq__box">
                    <div class="crs-faq__list">
                        <?php echo $nested_items; ?>
                    </div>
                </div>
            </div>
        </section>
                <?php
                return ob_get_clean();
    }
}

/**
 * -----------------------------------------------------------------------------
 * 核心组件二：[crs_faq_item] 正文内层单条独占折叠问答轨道简码
 * -----------------------------------------------------------------------------
 */
if (!function_exists('crs_post_faq_item_inner_shortcode')) {
    function crs_post_faq_item_inner_shortcode($atts, $content = null)
    {
        global $crs_post_faq_runtime;

        $pairs = shortcode_atts([
            'q' => 'Frequently Asked Question',
        ], $atts, 'crs_faq_item');

        if (empty($content)) {
            return '';
        }

        // 清洗内层富文本，支持加粗、超链接及古腾堡原生列表
        $clean_answer = do_shortcode(wpautop(trim($content)));

        // 智能首展判断
        $is_first_open = ($crs_post_faq_runtime['index'] === 0) ? 'open' : '';
        $crs_post_faq_runtime['index']++;

        // 从全局运行包中取出由外层简码动态指派的专属组名
        $current_group_name = $crs_post_faq_runtime['name'];

        ob_start();
        ?>
        <details class="crs-faq__item" name="<?php echo esc_attr($current_group_name); ?>" <?php echo $is_first_open; ?>>

            <summary class="crs-faq__question">
                <span><?php echo esc_html($pairs['q']); ?></span>
                <?php
                // 完美对接你的 cal_get_icon 核心图标库，保持箭头样式一致
                echo cal_get_icon('arrow_icon', 'crs-faq__arrow');
        ?>
            </summary>

            <div class="crs-faq__answer">
                <div class="crs-faq__answer-content">
                    <?php echo $clean_answer; ?>
                </div>
            </div>

        </details>
        <?php
        return ob_get_clean();
    }
}

// 注册正文简码
if (!shortcode_exists('crs_faq')) {
    add_shortcode('crs_faq', 'crs_post_faq_outer_shortcode');
}
if (!shortcode_exists('crs_faq_item')) {
    add_shortcode('crs_faq_item', 'crs_post_faq_item_inner_shortcode');
}


/* =============================================================================
30. FONT PRELOAD SYSTEM（全站字体资源预加载）
============================================================================= */
add_action('wp_head', function () {
    $font_url = get_stylesheet_directory_uri() . '/fonts/InterVariable.woff2';

    printf(
        "\n\t<link rel=\"preload\" href=\"%s\" as=\"font\" type=\"font/woff2\" crossorigin>\n",
        esc_url($font_url)
    );
}, 1);

/* =============================================================================
   31. Breadcrumb Home Icon（Rank Math 面包屑首页图标 - 终极纯净版）
   =============================================================================
   作用：
   - 基于 HTML 最终切面，将 Rank Math 第一个首页链接文字物理替换为库内 SVG
   - 资产完全信任闭环函数 cal_get_icon('breadcrumb_icon')，彻底绝育 XSS 冗余开销
   - 采用 mb_convert_encoding 实体化编码，源头上 100% 杜绝 注释残留
   - 完美兼顾 Schema 结构化数据、多语言国际化（i18n）与无障碍盲人读屏（A11y）
   ============================================================================= */
function crs_breadcrumb_shortcode()
{
    // 1. 初始化面包屑数据池（采用数据与视图分离的设计模式）
    $items = [];

    // 2. 注入【首页】节点
    $items[] = [
        'url' => home_url('/'),
        'label' => __('Home', 'cyberatlaslab'), // 统一您的国际化文本域
        'icon' => 'breadcrumb_icon',          // 完美对接你的统一图标库 Key
    ];

    // 3. 动态获取当前页面的 CPT 逻辑层级
    if (is_singular() || is_single()) {

        $current_post_type = get_post_type();

        if ($current_post_type) {
            // 动态获取该文章类型在后台注册的“存档页（Archive）永久链接”
            $archive_url = get_post_type_archive_link($current_post_type);

            // 动态获取该文章类型的配置对象，从中提取后台填写的分类名称
            $post_type_obj = get_post_type_object($current_post_type);
            $archive_label = $post_type_obj ? $post_type_obj->labels->name : 'Reviews';

            // 只有在 CPT 确实开启了 has_archive 且成功拿到 URL 的情况下，才注入【中间分类层】
            if ($archive_url) {
                $items[] = [
                    'url' => $archive_url,
                    'label' => $archive_label,
                ];
            }
        }

        // 注入【当前文章页】节点
        $items[] = [
            'url' => '',
            'label' => get_the_title(),
            'current' => true,
        ];
    }
    // 容错扩展：如果是 CPT 自己的存档列表页本身
    elseif (is_post_type_archive()) {
        $current_post_type = get_query_var('post_type');
        $post_type_obj = get_post_type_object($current_post_type);

        $items[] = [
            'url' => '',
            'label' => $post_type_obj ? $post_type_obj->labels->name : 'Reviews',
            'current' => true,
        ];
    }

    // 4. 视图渲染层（通过 ob 缓存严格控制换行，防御 WP 自动加 P 标签的内核机制）
    ob_start();

    echo '<nav class="crs-breadcrumb" aria-label="Breadcrumb">';
    echo '<ol class="crs-breadcrumb__list">';

    foreach ($items as $item) {
        $is_current = !empty($item['current']);
        $li_attr = $is_current ? ' class="crs-breadcrumb__item" aria-current="page"' : ' class="crs-breadcrumb__item"';

        echo '<li' . $li_attr . '>';

        if ($is_current) {
            // 当前页：直接输出纯文本，DOM 中不再嵌套多余的 a 或 span，追求极致的扁平化
            echo esc_html($item['label']);
        } else {
            // 非当前页：渲染标准的超链接
            echo '<a class="crs-breadcrumb__link" href="' . esc_url($item['url']) . '">';

            // 🛠️ 资产信任闭环：如果声明了图标 Key 且映射库函数存在，直接调取原生的只读物理资产
            if (!empty($item['icon']) && function_exists('cal_get_icon')) {
                echo cal_get_icon($item['icon']);
                // 规范注入无障碍与 SEO 盲人文本
                echo '<span class="screen-reader-text">' . esc_html($item['label']) . '</span>';
            } else {
                // 无图标则正常输出安全文本
                echo esc_html($item['label']);
            }

            echo '</a>';
        }

        echo '</li>';
    }

    echo '</ol>';
    echo '</nav>';

    return ob_get_clean();
}
// 注册新简码，彻底停用老旧过滤器
add_shortcode('crs_breadcrumb', 'crs_breadcrumb_shortcode');


/**
 * =============================================================================
 * 32. CyberAtlasLab CRS Affiliate Framework v5.9.1 (企业级组件化终极闭环版 · Hotfix)
 * =============================================================================
 * Hotfix 2026-07-17：
 * ① validate_prefix_value() 增加 is_string 类型闸 —— 修复 trim(array) 致命错误，
 *    该错误曾在 ACF 验证阶段杀死全部保存请求（Gutenberg AJAX 预检与传统 POST 双路径）；
 * ② passive_flush_rewrites() 修复未定义常量 CAL_CRS_Config::POST_TYPE 引用，
 *    并升级为按允许文章类型数组判断，与多类型变现架构对齐。
 * =============================================================================
 * 职责分离组件：
 * 1. Config            - 配置中心：集中管理魔法字符串、I/O 周期、HTTP 状态码。
 * 2. Prefix Manager    - 前缀管理器：依托内存与持久化双层缓存，提供极速白名单查询。
 * 3. Rewrite Manager   - 路由重写管理器：安全注册动态路由，通过 ACF 原生钩子提供异步非阻塞校验。
 * 4. URL Builder       - 统一链接工厂：全站唯一出口，生成高信任、无冗余编码的变现内链。
 * 5. Analytics Manager - 独立分析打点：事件驱动设计，为未来接入第三方数仓留出完美 Hook 接口。
 * 6. Redirect Engine   - 安全跳转内核：通过高性能 WP_Query 承接并发，利用正统 404 杜绝 SEO Soft 404 惩罚。
 * =============================================================================
 */

/**
 * MODULE 1: GLOBAL CONFIGURATION (框架全局配置中心)
 */
class CAL_CRS_Config
{
    // 允许这些选中的文章类型全面接入 CRS 变现网
    public static function get_allowed_post_types()
    {
        return array('product', 'post', 'page');
    }
    public const POST_TYPE = 'product';                     // 🛠️ 补回缺失常量：核心产品 CPT 标识
    public const DEFAULT_PREFIX = 'visit';                  // 黄金默认路由前缀
    public const REDIRECT_CODE = 302;                       // 联盟跳转的标准 HTTP 临时重定向状态码
    public const CACHE_TTL = 86400;                         // 持久化缓存生命周期 (24小时)
    public const LOG_INVALID_LINKS = true;                  // 是否独立开启异常联盟链接日志审计开关

    // ACF 选项页与字段标识
    public const OPTION_PAGE_READ = 'option';                    // get_field() 读取 Options 页时所需的单数标识
    public const OPTION_PAGE_SAVE = 'options';                   // acf/save_post 回调拦截时所需的复数标识
    public const FIELD_RAW_URL = 'affiliate_url';                // 隐藏的原始联盟长链接字段名
    public const FIELD_PREFIX = 'affiliate_prefix';              // 单品自定义前缀字段名
    public const OPTION_PREFIXES = 'affiliate_prefix_whitelist'; // 全局白名单集中维护字段名 (Textarea)
    public const OPTION_GLOBAL = 'global_affiliate_prefix';      // 全局默认前缀字段名 (Text)

    // 缓存与统计配置
    public const CACHE_KEY = 'cal_crs_active_prefixes_v9';  // 独立高版本缓存 Key
    public const CACHE_GROUP = 'cal_crs';                   // 独立的内存对象缓存分组名称
    public const CLICK_META = 'crs_total_clicks';           // 单品点击打点的 Meta Key
}


/**
 * MODULE 2: PREFIX MANAGER (高性能前缀与内存级白名单管理器)
 */
class CAL_CRS_Prefix_Manager
{
    /**
     * 高性能提取全站当前有效的前缀白名单数组。
     * 优先采用 WordPress 内存对象缓存 (Object Cache) 配合 Persistent Transient 锁存。
     * * @return array 干净、去重且重置索引后的有效前缀白名单集合。
     */
    public static function get_active_prefixes()
    {
        // 1. 优先采用单次请求页面加载的内存级对象缓存屏障
        $prefixes = wp_cache_get(CAL_CRS_Config::CACHE_KEY, CAL_CRS_Config::CACHE_GROUP);
        if (false !== $prefixes) {
            return $prefixes;
        }

        // 2. 次之尝试从持久化 Transient (可联动高并发 Redis/Memcached 扩展) 中获取
        $prefixes = get_transient(CAL_CRS_Config::CACHE_KEY);
        if (false !== $prefixes) {
            wp_cache_set(CAL_CRS_Config::CACHE_KEY, $prefixes, CAL_CRS_Config::CACHE_GROUP);
            return $prefixes;
        }

        $prefixes = array();

        // 3. 直接读取全局集中维护的白名单文本域，通过换行或逗号进行切割分离
        $whitelist_text = get_field(CAL_CRS_Config::OPTION_PREFIXES, CAL_CRS_Config::OPTION_PAGE_READ);
        if (!empty($whitelist_text)) {
            $raw_array = preg_split('/[\s,]+/', trim($whitelist_text));
            foreach ($raw_array as $item) {
                $clean = sanitize_title(trim($item));
                if (!empty($clean)) {
                    $prefixes[] = $clean;
                }
            }
        }

        // 4. 捎带捕获全局配置中的默认前缀
        $global_prefix = get_field(CAL_CRS_Config::OPTION_GLOBAL, CAL_CRS_Config::OPTION_PAGE_READ);
        if (!empty($global_prefix)) {
            $prefixes[] = sanitize_title(trim($global_prefix));
        }

        // 5. 黄金默认值强制兜底
        if (empty($prefixes)) {
            $prefixes[] = CAL_CRS_Config::DEFAULT_PREFIX;
        }

        // 6. 进行严密清洗、去重，并重置数组索引（保证 Transient 与内存缓存格式高度绝对统一）
        $prefixes = array_values(array_unique($prefixes));

        // 7. 写入双层缓存，为下一次并发拦截筑起 0 次数据库请求防线
        set_transient(CAL_CRS_Config::CACHE_KEY, $prefixes, CAL_CRS_Config::CACHE_TTL);
        wp_cache_set(CAL_CRS_Config::CACHE_KEY, $prefixes, CAL_CRS_Config::CACHE_GROUP);

        return $prefixes;
    }

    /**
     * 强制一键式清空当前前缀白名单的所有级别缓存屏障。
     * * @return void
     */
    public static function clear_prefix_cache()
    {
        delete_transient(CAL_CRS_Config::CACHE_KEY);
        wp_cache_delete(CAL_CRS_Config::CACHE_KEY, CAL_CRS_Config::CACHE_GROUP);
    }

    /**
     * 精确获取单个评测产品当前生效的联盟路径前缀。
     * * @param int $post_id 产品文章 ID。
     * @return string 该单品合法的路由前缀字符串。
     */
    public static function get_product_prefix($post_id = 0)
    {
        if ($post_id) {
            $custom_prefix = get_field(CAL_CRS_Config::FIELD_PREFIX, $post_id);
            if (!empty($custom_prefix) && is_string($custom_prefix)) {
                return sanitize_title(trim($custom_prefix));
            }
        }
        $global_prefix = get_field(CAL_CRS_Config::OPTION_GLOBAL, CAL_CRS_Config::OPTION_PAGE_READ);
        return (!empty($global_prefix) && is_string($global_prefix))
            ? sanitize_title(trim($global_prefix))
            : CAL_CRS_Config::DEFAULT_PREFIX;
    }
}


/**
 * MODULE 3: REWRITE MANAGER (安全路由重写与源头异步合法性校验管理器)
 */
class CAL_CRS_Rewrite_Manager
{
    /**
     * 初始化重写与后台拦截模块，挂载核心生命周期钩子。
     * * @return void
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'register_rules'));
        add_filter('query_vars', array(__CLASS__, 'register_query_vars'));
        add_action('acf/save_post', array(__CLASS__, 'passive_flush_rewrites'), 20);

        // 挂钩 ACF 官方标准的原生验证器，完美适配 Gutenberg 异步 REST API 交互及产品列表“快速编辑”
        add_filter('acf/validate_value/name=' . CAL_CRS_Config::FIELD_PREFIX, array(__CLASS__, 'validate_prefix_value'), 10, 4);
    }

    /**
     * 动态读取当前有效的前缀白名单集合，批量向 WordPress 重写内核注册高精度路由规则。
     * * @return void
     */
    public static function register_rules()
    {
        $prefixes = CAL_CRS_Prefix_Manager::get_active_prefixes();

        foreach ($prefixes as $prefix) {
            // 利用 preg_quote 斩断一切后台因为手误或历史遗留特殊字符导致的正则表达式溃败
            $safe_regex_prefix = preg_quote($prefix, '/');

            add_rewrite_rule(
                "^{$safe_regex_prefix}/([^/]+)/?$",
                "index.php?crs_aff_prefix={$prefix}&crs_aff_slug=\$matches[1]",
                'top'
            );
        }
    }

    /**
     * 向 WordPress 系统变量池中注入 CRS 重定向业务专用的核心查询白名单。
     * * @param array $vars 现有的变量数组。
     * @return array 扩充后的变量数组。
     */
    public static function register_query_vars($vars)
    {
        $vars[] = 'crs_aff_prefix';
        $vars[] = 'crs_aff_slug';
        return $vars;
    }

    /**
     * 被动式重写规则刷新处理器。
     * 当运营人员保存全局 Options 设置页，或编辑保存任何接入变现网的文章时触发。
     * * @param int|string $post_id 当前发生保存动作的节点 ID 标识。
     * @return void
     */
    public static function passive_flush_rewrites($post_id)
    {
        // 🛠️ Hotfix：原逻辑引用未定义常量 CAL_CRS_Config::POST_TYPE 会在保存时直接 Fatal，
        // 现改为按框架允许的变现文章类型数组判断（product/post/page），与多类型架构对齐
        $post_type = is_numeric($post_id) ? get_post_type($post_id) : false;

        if (
            $post_id === CAL_CRS_Config::OPTION_PAGE_SAVE
            || ($post_type && in_array($post_type, CAL_CRS_Config::get_allowed_post_types(), true))
        ) {
            CAL_CRS_Prefix_Manager::clear_prefix_cache();
            // 在被动冲刷时，flush_rewrite_rules 会在底层自动内部隐式调起全局的 register_rules()，此处无需重复调用
            flush_rewrite_rules(false);
        }
    }

    /**
     * ACF 官方标准源头拦截校对器。
     * 确保产品编辑页面填写的自定义前缀，必须预先包含在全局白名单集中维护域内。
     * * @param bool|string $valid 是否通过校验，通过返回 true，不通过返回自定义错误字符串。
     * @param mixed       $value 当前表单提交上来的前缀字段值。
     * @param array       $field 字段完整的配置上下文参数数组。
     * @param string      $input 当前 HTML 表单的 input 键名。
     * @return bool|string 审判结果。
     */
    public static function validate_prefix_value($valid, $value, $field, $input)
    {
        // 🛡️ Hotfix 安全闸：克隆/中继器场景下 ACF 可能将数组型值透传至本钩子，
        // 非字符串一律放行，杜绝 trim(array) 致命错误杀死整个保存请求
        if (!is_string($value) || empty($value)) {
            return $valid;
        }

        $input_prefix = sanitize_title(trim($value));
        $whitelist = CAL_CRS_Prefix_Manager::get_active_prefixes();

        if (!in_array($input_prefix, $whitelist, true)) {
            return sprintf(
                __('🛑 联盟前缀「 %s 」非法！该词汇未加入全站全局白名单。请先前往【ACF Options/全局设置页】中追加维护，再保存该产品。', 'cyberatlaslab-child'),
                esc_html($input_prefix)
            );
        }

        return $valid;
    }
}
CAL_CRS_Rewrite_Manager::init();


/**
 * MODULE 4: URL BUILDER (统一标准出口链接生成工厂)
 */
class CAL_CRS_URL_Builder
{
    /**
     * 根据指定的单品文章 ID，拼装生成高资产信任流的标准伪装联盟内链。
     * * @param int $post_id 产品文章 ID。
     * @return string 完美清洗、无冗余 rawurlencode 编码的标准超链接地址。
     */
    public static function build($post_id = 0)
    {
        $post_id = $post_id ? $post_id : get_the_ID();
        if (!$post_id) {
            return home_url();
        }

        $slug = get_post_field('post_name', $post_id);
        $prefix = CAL_CRS_Prefix_Manager::get_product_prefix($post_id);

        // 防御性规范说明：由于 $prefix 与 $slug 在落库与清洗时已经过了完备的 sanitize_title 过滤，
        // 此处移除了冗余的路径编码包装，输出最纯净、最利于 SEO 的路径结构。
        return home_url("/{$prefix}/{$slug}/");
    }
}


/**
 * MODULE 5: ANALYTICS MANAGER (事件驱动型商业打点统计中心)
 */
class CAL_CRS_Analytics_Manager
{
    /**
     * 初始化统计子系统，挂载数据分析管道的动作监听器。
     * * @return void
     */
    public static function init()
    {
        add_action('cal_crs_click', array(__CLASS__, 'default_meta_counter'), 10, 1);
        add_action('cal_crs_invalid_link', array(__CLASS__, 'log_invalid_link_error'), 10, 2);
    }

    /**
     * 原生基础落库计数器：通过物理递增累加该单品的点击总基数。
     * * @param int $post_id 产品文章 ID。
     * @return void
     */
    public static function default_meta_counter($post_id)
    {
        $click_count = (int) get_post_meta($post_id, CAL_CRS_Config::CLICK_META, true);
        update_post_meta($post_id, CAL_CRS_Config::CLICK_META, $click_count + 1);
    }

    /**
     * 异常或损坏变现长链接的探测捕获审计日志。
     * * @param int    $post_id 产品文章 ID。
     * @param string $raw_url 后台录入的包含隐患或格式崩坏的原始未知链接。
     * @return void
     */
    public static function log_invalid_link_error($post_id, $raw_url)
    {
        // 双重安全总闸控制，防止在未开启 debug 或配置关闭时引发多余的服务器磁盘 I/O 损耗
        if (!CAL_CRS_Config::LOG_INVALID_LINKS) {
            return;
        }
        if (defined('WP_DEBUG_LOG') && !WP_DEBUG_LOG) {
            return;
        }

        $post_title = get_the_title($post_id);
        error_log(sprintf("[CRS 核心警告] 产品 ID [%d] (%s) 存在不合法的变现原始链接，系统已强行拦截阻止跳转: %s", $post_id, $post_title, $raw_url));
    }
}
CAL_CRS_Analytics_Manager::init();


/**
 * MODULE 6: REDIRECT ENGINE (安全隔离跳转与 SEO 白帽内核引擎 - 全站多类型通水版)
 */
class CAL_CRS_Redirect_Engine
{
    /**
     * 启动中转内核重定向引擎控制器。
     * * @return void
     */
    public static function init()
    {
        add_action('template_redirect', array(__CLASS__, 'handle_redirect'));
    }

    /**
     * 核心高并发中转拦截逻辑分发处理器。
     * 完美兼容 'product'、'post'、'page' 多文章类型，让普通小教程和普通页面均可无缝参与跳转变现。
     * * @return void
     */
    public static function handle_redirect()
    {
        $req_prefix = get_query_var('crs_aff_prefix');
        $req_slug = get_query_var('crs_aff_slug');

        // 极速边界熔断：凡未命中白名单重写规则的，零点几毫秒内闪退，绝不干扰正文渲染
        if (empty($req_prefix) || empty($req_slug)) {
            return;
        }

        // 🛠️ 核心突破点：获取全站允许挂载变现链的全部 Post Types 数组
        // 自动探测并向系统申报多表联合匹配，彻底打破单一文章类型的枷锁
        $allowed_types = method_exists('CAL_CRS_Config', 'get_allowed_post_types')
            ? CAL_CRS_Config::get_allowed_post_types()
            : array('product', 'post', 'page'); // 安全后备垫底方案

        $query_args = array(
            'name' => $req_slug,
            'post_type' => $allowed_types, // 🏹 从单一字符串变为动态集结数组
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true, // 性能极致优化：跳过 SQL 耗时计算总行数行为
            'update_post_meta_cache' => true, // 保持为 true，确保接下来的 get_field() 物理命中内存级缓存
            'update_post_term_cache' => false,
        );

        $product_query = new WP_Query($query_args);

        if ($product_query->have_posts()) {
            $post_id = $product_query->posts[0];

            // 安全交叉防线校验：请求中的伪装前缀是否与该单品当前真实前缀配对吻合
            $allowed_prefix = CAL_CRS_Prefix_Manager::get_product_prefix($post_id);

            if ($req_prefix === $allowed_prefix) {
                // 此时不管该 ID 属于 product、post 还是 page，只要对齐了统一的 ACF 变现字段即可无损直读
                $raw_url = get_field(CAL_CRS_Config::FIELD_RAW_URL, $post_id);

                // 🛡️ 兼容 Link 字段数组返回格式，解包出纯 URL 字符串
                if (is_array($raw_url)) {
                    $raw_url = (!empty($raw_url['url']) && is_string($raw_url['url'])) ? $raw_url['url'] : '';
                }

                if (!empty($raw_url) && is_string($raw_url)) {
                    // 采用 WordPress 官方标准推荐的 esc_url_raw 进行高度安全的链接过滤与清洗
                    $sanitized_url = esc_url_raw(trim($raw_url));

                    // 利用核心函数二次核验链接的合法协议头与可用结构
                    if (empty($sanitized_url) || false === wp_http_validate_url($sanitized_url)) {
                        // 触发解耦后的异常动作事件流进行打点记录，随后拒绝执行重定向，滑入 404 熔断区
                        do_action('cal_crs_invalid_link', $post_id, $raw_url);
                    } else {
                        // 完美放行：触发点击流动作钩子，完成符合 2026 顶级 SEO 白帽合规标准的 302 重定向
                        do_action('cal_crs_click', $post_id);
                        wp_redirect($sanitized_url, CAL_CRS_Config::REDIRECT_CODE);
                        exit;
                    }
                }
            }
        }

        // 终极隔离：凡恶意探测、配置错误或废弃旧链接，统一向搜索引擎和黑客抛出纯正 404，斩断 Google Soft 404 降权惩罚
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();

        if ($template = get_404_template()) {
            include $template;
        }
        exit;
    }
}
CAL_CRS_Redirect_Engine::init();


/**
 * =========================================================================================================
 * 33. CRS Score Engine v1.0 - 全站评分引擎（数据层 + 解释层 + Tooltip 渲染器）
 * =========================================================================================================
 * 架构：
 *   Product Scores（scoring_breakdown · score_key 受控）
 *   Score Library （Options · 全站 Methodology 唯一数据源）
 *   Renderers     ：Breakdown / Hero / Compare（未来）共用 crs_render_score_tooltip()
 * 维度清单单点维护于 crs_score_dimensions()，通过 acf/load_field 统一注入两处 Select，
 * 杜绝字段组选项漂移；Library 读取复用联盟框架双层缓存模式。
 * =========================================================================================================
 */

/**
 * 33.1 评分维度注册表（全站单点真相源）
 * 新增维度只需在此加一行，Product 与 Options 两处 Select 自动同步
 */
if (!function_exists('crs_get_score_dimensions_grouped')) {
    function crs_get_score_dimensions_grouped()
    {
        return [
            '通用与核心 (General & Core)' => [
                'security'  => 'Security (安全防护)',
                'speed'     => 'Speed & Performance (速度性能)',
                'privacy'   => 'Privacy & Logging (隐私策略)',
                'value'     => 'Value & Pricing (性价比)',
                'support'   => 'Customer Support (客户支持)',
                'usability' => 'Ease of Use (易用性)',
                'features'  => 'Core Features (核心功能)',
            ],
            'VPN & 网络代理 (VPN Proxy)' => [
                'servers'   => 'Server Network (服务器网络)',
                'streaming' => 'Streaming Unblock (流媒体解锁)',
                'apps'      => 'Apps & Compatibility (多平台支持)',
            ],
            '杀毒与网络安全 (Antivirus)' => [
                'malware'     => 'Malware Detection (病毒查杀率)',
                'system_load' => 'System Impact (系统资源消耗)',
            ],
            '密码管理器 (Password Manager)' => [
                'autofill' => 'Autofill & Vault (自动填充与密码库)',
            ],
            '家长控制 (Parental Control)' => [
                'screen_time' => 'Screen Time Management (屏幕时间管理)',
                'filtering'   => 'Content Filtering (内容过滤)',
                'location'    => 'Location Tracking (实时位置追踪)',
            ],
        ];
    }
}


/**
 * 33.2 维度注册表扁平化（供 Library 默认 label 查找；Select 注入仍用分组结构）
 * 同时剥离后台注释用的中文括号，保证前端默认 label 为纯英文
 */
if (!function_exists('crs_score_dimensions_flat')) {
    function crs_score_dimensions_flat()
    {
        $flat = [];
        foreach (crs_score_dimensions() as $group => $dims) {
            foreach ((array) $dims as $key => $label) {
                // "Security (安全防护)" → "Security"（中文括号是后台备注，不能上前端）
                $flat[$key] = trim(preg_replace('/\s*[（(].*?[）)]\s*$/u', '', $label));
            }
        }
        return $flat;
    }
}


/**
 * 33.3 向后兼容别名：旧名 crs_score_dimensions() 统一桥接到新注册表
 * （防止任何残留调用触发未定义函数 Fatal）
 */
if (!function_exists('crs_score_dimensions')) {
    function crs_score_dimensions()
    {
        return crs_get_score_dimensions_grouped();
    }
}


/**
 * 33.4 方法论页 URL 生成器（Tooltip 底部 Learn more 唯一出口）
 */
if (!function_exists('crs_methodology_url')) {
    function crs_methodology_url($anchor = '')
    {
        static $base = null;
        if ($base === null) {
            $page = get_page_by_path('methodology');
            $base = $page ? trailingslashit(get_permalink($page)) : home_url('/methodology/');
        }
        return $anchor !== '' ? $base . '#' . sanitize_title($anchor) : $base;
    }
}


/**
 * 33.5 Score Library 读取器（Options · 双层缓存 · 被动冲刷）
 */
if (!function_exists('crs_get_score_library')) {
    function crs_get_score_library()
    {
        $cache_key = 'crs_score_library_v1';
        $cache_group = 'cal_crs';

        // ① 请求级对象缓存
        $library = wp_cache_get($cache_key, $cache_group);
        if (false !== $library) {
            return $library;
        }

        // ② 持久化 Transient
        $library = get_transient($cache_key);
        if (false !== $library) {
            wp_cache_set($cache_key, $library, $cache_group);
            return $library;
        }

        // ③ 回源构建索引数组
        $library = [];
        if (function_exists('get_field')) {
            // ⚠️ 复用联盟框架常量：读取用 'option' 单数标识，勿写成 'options'
            $option_id = defined('CAL_CRS_Config::OPTION_PAGE_READ') ? CAL_CRS_Config::OPTION_PAGE_READ : 'option';
            $rows = get_field('score_library', $option_id);

            if (is_array($rows)) {
                foreach ((array) $rows as $row) {
                    $key = !empty($row['dimension_key']) ? sanitize_key($row['dimension_key']) : '';
                    if ($key === '') {
                        continue;
                    }

                    // 考察点：按行拆分为数组
                    $points = [];
                    if (!empty($row['eval_points'])) {
                        foreach (preg_split('/[\r\n]+/', $row['eval_points']) as $p) {
                            $p = trim($p);
                            if ($p !== '') {
                                $points[] = $p;
                            }
                        }
                    }

                    $defaults = crs_score_dimensions_flat();
                    $library[$key] = [
                        'label' => !empty($row['dimension_label']) ? trim($row['dimension_label']) : ($defaults[$key] ?? ucfirst($key)),
                        'points' => $points,
                        'anchor' => !empty($row['anchor']) ? sanitize_title($row['anchor']) : $key,
                    ];
                }
            }
        }

        set_transient($cache_key, $library, 12 * HOUR_IN_SECONDS);
        wp_cache_set($cache_key, $library, $cache_group);
        return $library;
    }
}

/**
 * 33.6 Library 缓存被动冲刷（Options 页保存时触发）
 */
add_action('acf/save_post', function ($post_id) {
    $option_save = defined('CAL_CRS_Config::OPTION_PAGE_SAVE') ? CAL_CRS_Config::OPTION_PAGE_SAVE : 'options';
    if ($post_id === $option_save) {
        delete_transient('crs_score_library_v1');
        wp_cache_delete('crs_score_library_v1', 'cal_crs');
    }
}, 15);


/**
 * 33.7 Tooltip 渲染器（全站评分解释唯一出口 · Popover 结构供未来 Compare 复用）
 *
 * @param string $dimension_key 维度键（security / speed / ...）
 * @return string 库中有匹配维度时输出 触发器+浮层，否则空字符串
 */
if (!function_exists('crs_render_score_tooltip')) {
    function crs_render_score_tooltip($dimension_key)
    {
        $dimension_key = sanitize_key($dimension_key);
        if ($dimension_key === '') {
            return '';
        }

        $library = crs_get_score_library();
        if (empty($library[$dimension_key])) {
            return '';
        }

        $entry = $library[$dimension_key];

        static $instance = 0;
        $instance++;
        $uid = 'crs-tip-' . $dimension_key . '-' . $instance;

        // 方法论一句话：Options 全局字段，空值用默认兜底
        $option_id = defined('CAL_CRS_Config::OPTION_PAGE_READ') ? CAL_CRS_Config::OPTION_PAGE_READ : 'option';
        $note = trim((string) get_field('score_tooltip_note', $option_id));
        if ($note === '') {
            $note = __('Scores are based on hands-on testing and research.', 'cyberatlaslab');
        }

        $info_icon = function_exists('cal_get_icon') ? (cal_get_icon('info_icon') ?: 'ⓘ') : 'ⓘ';
        $check_icon = function_exists('cal_get_icon') ? (cal_get_icon('crs_check_icon') ?: '✓') : '✓';

        ob_start();
        ?>
                <span class="crs-score-tip">
                    <button type="button" class="crs-score-tip__trigger" aria-expanded="false"
                        aria-controls="<?php echo esc_attr($uid); ?>"
                        aria-label="<?php printf(esc_attr__('How we score %s', 'cyberatlaslab'), esc_attr($entry['label'])); ?>"><?php echo $info_icon; ?></button>
                    <span class="crs-popover" id="<?php echo esc_attr($uid); ?>" role="dialog" aria-hidden="true">
                        <span class="crs-popover__title"><?php echo esc_html($entry['label']); ?></span>
                        <?php if (!empty($entry['points'])): ?>
                                <span class="crs-popover__subtitle"><?php _e('We Evaluate:', 'cyberatlaslab'); ?></span>
                                <span class="crs-popover__list">
                                    <?php foreach ($entry['points'] as $point): ?>
                                            <span class="crs-popover__item"><?php echo $check_icon; ?><?php echo esc_html($point); ?></span>
                                    <?php endforeach; ?>
                                </span>
                        <?php endif; ?>
                        <span class="crs-popover__note"><?php echo esc_html($note); ?></span>
                        <a class="crs-popover__link" href="<?php echo esc_url(crs_methodology_url($entry['anchor'])); ?>"><?php _e('Learn About Our Methodology', 'cyberatlaslab'); ?><span class="crs-popover__link-arrow" aria-hidden="true"><?php echo cal_get_icon('right_arrow_icon'); ?></span></a>
                    </span>
                </span>
                <?php
                return ob_get_clean();
    }
}

/**
 * 33.8 Tooltip 前端控制器（事件委托 · 点击切换 / ESC / 外部点击关闭）
 */
if (!function_exists('crs_score_tooltip_script')) {
    function crs_score_tooltip_script()
    {
        if (!is_singular()) {
            return;
        }

        $script = <<<'JS'
<script>
(function () {
    'use strict';
    var CRSScoreTooltip = {
        active: null,
        init: function () {
            var self = this;
            document.addEventListener('click', function (e) {
                var trigger = e.target.closest('.crs-score-tip__trigger');
                if (trigger) {
                    e.preventDefault();
                    self.toggle(trigger);
                    return;
                }
                // 点击组件外部：关闭当前浮层
                if (self.active && !e.target.closest('.crs-score-tip')) {
                    self.close();
                }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && self.active) {
                    self.close(true);
                }
            });
        },
        toggle: function (trigger) {
            var isOpen = trigger.getAttribute('aria-expanded') === 'true';
            if (this.active && this.active !== trigger) this.close();
            isOpen ? this.hide(trigger) : this.show(trigger);
        },
        show: function (trigger) {
            var pop = document.getElementById(trigger.getAttribute('aria-controls'));
            if (!pop) return;
            trigger.setAttribute('aria-expanded', 'true');
            pop.setAttribute('aria-hidden', 'false');
            pop.classList.add('is-open');
            this.active = trigger;
        },
        hide: function (trigger) {
            var pop = document.getElementById(trigger.getAttribute('aria-controls'));
            if (pop) {
                pop.setAttribute('aria-hidden', 'true');
                pop.classList.remove('is-open');
            }
            trigger.setAttribute('aria-expanded', 'false');
            if (this.active === trigger) this.active = null;
        },
        close: function (refocus) {
            if (!this.active) return;
            var trigger = this.active;
            this.hide(trigger);
            if (refocus) trigger.focus();
        }
    };
    document.addEventListener('DOMContentLoaded', function () {
        CRSScoreTooltip.init();
    });
})();
</script>
JS;
        echo $script;
    }
}
add_action('wp_footer', 'crs_score_tooltip_script', 98);


/**
 * 35. 通用链接按钮渲染器（纯展示 · 无业务逻辑）
 * 包含：attrs 白名单与二次转义、rel 智能合并、输出净化
 */
if (!function_exists('cal_render_link_button')) {
    function cal_render_link_button($args)
    {
        $args = wp_parse_args($args, array(
            'url'    => '',
            'text'   => '',
            'class'  => 'cal-hero-cta',
            'newtab' => true,
            'rel'    => 'nofollow noopener sponsored',
            'aria'   => '',
            'icon'   => true, // 默认开启图标
            'attrs'  => '',
        ));

        // 无链接或文本不渲染
        if (empty($args['url']) || trim((string) $args['text']) === '') {
            return '';
        }

        // --- 核心优化：根据 class 智能决定默认图标 ---
        $icon_key = '';
        if ($args['icon']) {
            if (is_string($args['icon']) && $args['icon'] !== '') {
                // 如果显式传了具体的图标名字（如 'cta-arrow-icon'），优先用传进来的
                $icon_key = $args['icon'];
            } else {
                // 如果 icon 是 true（默认），通过 class 判断：
                // 如果 class 包含 'cal-showcase-cta' (Top3卡片)，自动使用 'right_arrow_icon'（无圆圈箭头）
                // 否则默认使用 'cta-arrow-icon'（带圆圈箭头）
                if (strpos($args['class'], 'cal-showcase-cta') !== false) {
                    $icon_key = 'right_arrow_icon';
                } else {
                    $icon_key = 'cta-arrow-icon';
                }
            }
        }

        // --- 安全加固 1：attrs 白名单过滤 ---
        $safe_attrs = '';
        if (!empty($args['attrs'])) {
            $pattern = '/(id|aria-[a-z0-9_-]+|data-[a-z0-9_-]+)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i';
            if (preg_match_all($pattern, (string) $args['attrs'], $matches, PREG_SET_ORDER)) {
                $parts = array();
                foreach ($matches as $m) {
                    if (preg_match('/^([a-z0-9_-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|(\S+))$/i', $m[0], $kv)) {
                        $name  = strtolower($kv[1]);
                        $value = '';
                        if (isset($kv[2]) && $kv[2] !== '') {
                            $value = $kv[2];
                        } elseif (isset($kv[3]) && $kv[3] !== '') {
                            $value = $kv[3];
                        } elseif (isset($kv[4])) {
                            $value = $kv[4];
                        }
                        $parts[] = esc_attr($name) . '="' . esc_attr($value) . '"';
                    }
                }
                $safe_attrs = $parts ? ' ' . implode(' ', $parts) : '';
            }
        }

        // --- 安全加固 2：rel 智能处理 ---
        $rel_array = array_filter(array_map('trim', explode(' ', (string) $args['rel'])));
        if ($args['newtab']) {
            if (!in_array('noopener', $rel_array, true)) {
                $rel_array[] = 'noopener';
            }
        } else {
            $rel_array = array_diff($rel_array, array('noopener', 'noreferrer'));
        }
        $rel = implode(' ', array_unique($rel_array));

        // --- HTML 构建 ---
        $html  = '<a href="' . esc_url($args['url']) . '"';
        $html .= ' class="' . esc_attr($args['class']) . '"';
        $html .= $args['newtab'] ? ' target="_blank"' : '';
        $html .= $rel !== '' ? ' rel="' . esc_attr($rel) . '"' : '';
        $html .= $args['aria'] !== '' ? ' aria-label="' . esc_attr($args['aria']) . '"' : '';
        $html .= $safe_attrs;
        $html .= '><span>' . esc_html($args['text']) . '</span>';

        // 渲染匹配到的 SVG 图标
        if ($icon_key !== '' && function_exists('cal_get_icon')) {
            $html .= cal_get_icon($icon_key);
        }
        $html .= '</a>';

        return $html;
    }
}

/**
 * 35.1 产品名称统一读取器（优先缓存 -> ACF -> 标题兜底）
 */
if (!function_exists('crs_get_product_name')) {
    function crs_get_product_name($post_id)
    {
        if (function_exists('crs_get_product_data')) {
            $data = crs_get_product_data($post_id);
            if ($data && !empty($data['name'])) {
                return $data['name'];
            }
        }
        if (function_exists('get_field')) {
            $n = get_field('product_name', $post_id);
            if (!empty($n) && is_string($n)) {
                return $n;
            }
        }
        return get_the_title($post_id);
    }
}

/**
 * 35.2 联盟 CTA 简码 - [cal_cta]
 *
 * 场景1（基础使用）：[cal_cta id="123" text="Get 68% Off"]
 * 场景2（同页跳转）：[cal_cta item="1" text="Get Deal" newtab="no"]
 * 场景3（前端埋点A/B测试）：[cal_cta id="123" text="Visit NordVPN" attrs='data-track="hero" data-pos="1"']
 */
if (!function_exists('cal_cta_shortcode')) {
    function cal_cta_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'id'     => '',
            'item'   => '',
            'text'   => '',
            'class'  => 'cal-hero-cta',
            'newtab' => 'yes',
            'attrs'  => '',
        ), $atts, 'cal_cta');

        // 查找产品 ID
        $target_id = 0;
        if (!empty($atts['id'])) {
            $target_id = absint($atts['id']);
        } elseif (!empty($atts['item']) && function_exists('crs_resolve_product_id')) {
            $target_id = absint(crs_resolve_product_id($atts['item']));
        } else {
            $target_id = get_the_ID();
        }
        if (!$target_id) {
            return '';
        }

        // 构建链接
        $url = function_exists('crs_build_affiliate_link')
            ? crs_build_affiliate_link($target_id)
            : '#';
        if (empty($url) || $url === '#') {
            return '';
        }

        // 获取产品名与文案兜底
        $name = function_exists('crs_get_product_name')
            ? crs_get_product_name($target_id)
            : get_the_title($target_id);

        $text = trim((string) $atts['text']);
        if ($text === '') {
            if ($name === '') {
                return '';
            }
            $text = sprintf(__('Visit %s', 'cyberatlaslab'), $name);
        }

        $aria = $name !== ''
            ? sprintf(__('Visit %s Official Website', 'cyberatlaslab'), $name)
            : $text;

        // 渲染输出
        return cal_render_link_button(array(
            'url'    => $url,
            'text'   => $text,
            'class'  => $atts['class'],
            'newtab' => ($atts['newtab'] !== 'no'),
            'rel'    => 'nofollow sponsored',
            'aria'   => $aria,
            'attrs'  => $atts['attrs'],
        ));
    }
}

if (!shortcode_exists('cal_cta')) {
    add_shortcode('cal_cta', 'cal_cta_shortcode');
}


/**
 * =============================================================================
 * 36. 分类页快速对比表 - [cal_compare_table]
 * =============================================================================
 * 数据源：当前页 cat_top_products relationship（顺序即排名 #1~#5）
 * 定位：Score Engine 第二渲染端——综合评分 + ⓘ tooltip 体系直接复用
 * 结构：桌面真 <table>（语义完整）；移动端 CSS 变换为横向滑动卡片（一套 DOM）
 * 列：Rank │ Product │ Score │ Price │ Best For │ CTA（极简六列）
 * 空值纪律：无关联产品整块不渲染；行内缺数据只缺对应单元格；无有效链接不渲染 CTA
 *
 * 用法：[cal_compare_table]
 * 锚点：<section id="comparison">，供 Sticky Anchor Nav / Quick Filters 跳转
 * =============================================================================
 */
if (!function_exists('cal_compare_table_shortcode')) {

    function cal_compare_table_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'title' => '',
        ), $atts, 'cal_compare_table');

        $post_id = get_the_ID();
        if (!$post_id || !function_exists('get_field')) {
            return '';
        }

        $products = get_field('cat_top_products', $post_id);
        if (empty($products) || !is_array($products)) {
            return '';
        }

        $title = trim((string) $atts['title']);
        if ($title === '') {
            $title = __('Quick Comparison', 'cyberatlaslab');
        }

        // Score Engine：列头 Score 旁挂 Overall 维度 tooltip
        $score_tip = function_exists('crs_render_score_tooltip') ? crs_render_score_tooltip('overall') : '';

        ob_start();
        ?>
        <section class="cal-compare" id="comparison" aria-labelledby="cal-compare-title-<?php echo esc_attr($post_id); ?>">
            <h2 class="cal-compare__title" id="cal-compare-title-<?php echo esc_attr($post_id); ?>">
                <?php echo esc_html($title); ?>
            </h2>
            <div class="cal-compare__scroll">
                <table class="cal-compare__table">
                    <thead>
                        <tr>
                            <th scope="col" class="cal-compare__th-rank"><?php esc_html_e('Rank', 'cyberatlaslab'); ?></th>
                            <th scope="col" class="cal-compare__th-product"><?php esc_html_e('Product', 'cyberatlaslab'); ?></th>
                            <th scope="col" class="cal-compare__th-score">
                                <?php esc_html_e('Score', 'cyberatlaslab'); ?><?php echo $score_tip; ?>
                            </th>
                            <th scope="col" class="cal-compare__th-price"><?php esc_html_e('Price', 'cyberatlaslab'); ?></th>
                            <th scope="col" class="cal-compare__th-bestfor"><?php esc_html_e('Best For', 'cyberatlaslab'); ?></th>
                            <th scope="col" class="cal-compare__th-cta"><span class="screen-reader-text"><?php esc_html_e('Action', 'cyberatlaslab'); ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rank = 0;
        foreach ($products as $product) :
            $pid = is_object($product) ? $product->ID : absint($product);
            if (!$pid) {
                continue;
            }
            $rank++;

            // 名称：统一来源
            $name = function_exists('crs_get_product_name') ? crs_get_product_name($pid) : get_the_title($pid);

            // Logo：SVG 优先
            $logo = function_exists('crs_get_product_logo_html') ? crs_get_product_logo_html($pid) : '';

            // 综合评分
            $score = get_field('score_overall', $pid);
            $score = (is_numeric($score) && $score !== '') ? $score : '';

            // 价格：货币格式化 + 计费单位
            $price_raw = get_field('price_lowest', $pid);
            $symbol = function_exists('crs_format_currency') ? crs_format_currency('', $pid) : '$';
            $price  = ($price_raw !== '' && is_numeric($price_raw)) ? $symbol . $price_raw : '';
            $period = get_field('price_period', $pid);
            $period = is_string($period) ? trim($period) : '';
            $discount = get_field('current_discount', $pid);
            $discount = is_string($discount) ? trim($discount) : '';

            // Best For：取产品 best repeater 首条
            $best_for = '';
            $best_rows = get_field('best', $pid);
            if (!empty($best_rows) && is_array($best_rows) && !empty($best_rows[0]['best_item'])) {
                $best_for = trim((string) $best_rows[0]['best_item']);
            }

            // CTA：联盟伪装链，无链不渲染
            $cta_url = function_exists('crs_build_affiliate_link') ? crs_build_affiliate_link($pid) : '#';
            $has_cta = (!empty($cta_url) && $cta_url !== '#');
            ?>
                            <tr class="cal-compare__row<?php echo $rank === 1 ? ' cal-compare__row--top' : ''; ?>">
                                <td class="cal-compare__cell-rank" data-label="<?php esc_attr_e('Rank', 'cyberatlaslab'); ?>">
                                    <span class="cal-compare__rank-badge"><?php echo esc_html('#' . $rank); ?></span>
                                    <?php if ($rank === 1) : ?>
                                        <span class="cal-compare__top-pick"><?php esc_html_e('Top Pick', 'cyberatlaslab'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="cal-compare__cell-product" data-label="<?php esc_attr_e('Product', 'cyberatlaslab'); ?>">
                                    <span class="cal-compare__logo"><?php echo $logo; ?></span>
                                    <span class="cal-compare__name"><?php echo esc_html($name); ?></span>
                                </td>
                                <td class="cal-compare__cell-score" data-label="<?php esc_attr_e('Score', 'cyberatlaslab'); ?>">
                                    <?php if ($score !== '') : ?>
                                        <span class="cal-compare__score"><?php echo esc_html($score); ?><span class="cal-compare__score-max">/10</span></span>
                                    <?php endif; ?>
                                </td>
                                <td class="cal-compare__cell-price" data-label="<?php esc_attr_e('Price', 'cyberatlaslab'); ?>">
                                    <?php if ($price !== '') : ?>
                                        <span class="cal-compare__price"><?php echo esc_html($price); ?><?php echo $period !== '' ? '<span class="cal-compare__period">' . esc_html($period) . '</span>' : ''; ?></span>
                                    <?php endif; ?>
                                    <?php if ($discount !== '') : ?>
                                        <span class="cal-compare__discount"><?php echo esc_html($discount); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="cal-compare__cell-bestfor" data-label="<?php esc_attr_e('Best For', 'cyberatlaslab'); ?>">
                                    <?php echo $best_for !== '' ? esc_html($best_for) : ''; ?>
                                </td>
                                <td class="cal-compare__cell-cta" data-label="">
                                    <?php
                    if ($has_cta && function_exists('cal_render_link_button')) {
                        echo cal_render_link_button(array(
                            'url'   => $cta_url,
                            'text'  => sprintf(__('Visit %s', 'cyberatlaslab'), $name),
                            'class' => 'cal-compare__cta',
                            'icon'  => true,
                        ));
                    }
            ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}

if (!shortcode_exists('cal_compare_table')) {
    add_shortcode('cal_compare_table', 'cal_compare_table_shortcode');
}


/**
 * =============================================================================
 * 37. 分类页编辑推荐 - [cal_editor_picks]
 * =============================================================================
 * 数据源：cat_top_products（与 [cal_compare_table] 同源，顺序即排名）
 * 三级卡片：#1 Featured 大卡（Editor's Choice）/ #2-3 Medium 中卡 / #4-5 Compact 小卡
 * 移动端：#1-3 依次堆叠，#4-5 收进 <details> 折叠（JS 按断点切换 open 状态）
 * 锚点：<section id="picks"> + 每卡 id="pick-{slug}"（Quick Filters 跳转目标）
 * 转化双出口：Visit 联盟伪装链（sponsored）+ Read Review 内链（传权重）
 * =============================================================================
 */
if (!function_exists('cal_editor_picks_shortcode')) {

    function cal_editor_picks_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'title' => '',
        ), $atts, 'cal_editor_picks');

        $post_id = get_the_ID();
        if (!$post_id || !function_exists('get_field')) {
            return '';
        }

        $products = get_field('cat_top_products', $post_id);
        if (empty($products) || !is_array($products)) {
            return '';
        }

        $title = trim((string) $atts['title']);
        if ($title === '') {
            $title = __("Editor's Picks", 'cyberatlaslab');
        }

        // 单卡渲染器（内部闭包，按层级分发模板）
        $render_pick = function ($pid, $rank) {
            $name = function_exists('crs_get_product_name') ? crs_get_product_name($pid) : get_the_title($pid);
            $logo = function_exists('crs_get_product_logo_html') ? crs_get_product_logo_html($pid) : '';
            $slug = get_post_field('post_name', $pid);

            $score = get_field('score_overall', $pid);
            $score = (is_numeric($score) && $score !== '') ? $score : '';

            $verdict = get_field('editor_verdict', $pid);
            $verdict = is_string($verdict) ? trim($verdict) : '';

            $price_raw = get_field('price_lowest', $pid);
            $symbol = function_exists('crs_format_currency') ? crs_format_currency('', $pid) : '$';
            $price  = ($price_raw !== '' && is_numeric($price_raw)) ? $symbol . $price_raw : '';
            $period = get_field('price_period', $pid);
            $period = is_string($period) ? trim($period) : '';
            $discount = get_field('current_discount', $pid);
            $discount = is_string($discount) ? trim($discount) : '';

            // Pros：仅 #1 大卡展示前 3 条
            $pros_html = '';
            if ($rank === 1) {
                $pros = get_field('pros', $pid);
                if (!empty($pros) && is_array($pros)) {
                    $check = function_exists('cal_get_icon') ? cal_get_icon('check') : '✓';
                    $pros_html .= '<ul class="cal-pick__pros">';
                    foreach (array_slice($pros, 0, 3) as $row) {
                        if (empty($row['pro_item'])) {
                            continue;
                        }
                        $pros_html .= '<li><span class="cal-pick__pro-icon" aria-hidden="true">' . $check . '</span>' . esc_html(trim($row['pro_item'])) . '</li>';
                    }
                    $pros_html .= '</ul>';
                }
            }

            // 双出口
            $cta_url = function_exists('crs_build_affiliate_link') ? crs_build_affiliate_link($pid) : '#';
            $cta_html = '';
            if (!empty($cta_url) && $cta_url !== '#' && function_exists('cal_render_link_button')) {
                $cta_html = cal_render_link_button(array(
                    'url'   => $cta_url,
                    'text'  => sprintf(__('Visit %s', 'cyberatlaslab'), $name),
                    'class' => 'cal-pick-cta',
                ));
            }
            $review_url = get_permalink($pid);

            // 层级修饰类
            $tier = $rank === 1 ? 'cal-pick--featured' : ($rank <= 3 ? 'cal-pick--medium' : 'cal-pick--compact');

            ob_start();
            ?>
            <article class="cal-pick <?php echo esc_attr($tier); ?>" id="pick-<?php echo esc_attr($slug); ?>">
                <div class="cal-pick__head">
                    <span class="cal-pick__rank"><?php echo esc_html('#' . $rank); ?></span>
                    <?php if ($rank === 1) : ?>
                        <span class="cal-pick__choice"><?php esc_html_e("Editor's Choice", 'cyberatlaslab'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="cal-pick__identity">
                    <span class="cal-pick__logo"><?php echo $logo; ?></span>
                    <h3 class="cal-pick__name"><?php echo esc_html($name); ?></h3>
                    <?php if ($score !== '') : ?>
                        <span class="cal-pick__score"><?php echo esc_html($score); ?><span class="cal-pick__score-max">/10</span></span>
                    <?php endif; ?>
                </div>
                <?php if ($verdict !== '') : ?>
                    <p class="cal-pick__verdict"><?php echo esc_html($verdict); ?></p>
                <?php endif; ?>
                <?php echo $pros_html; ?>
                <div class="cal-pick__deal">
                    <?php if ($price !== '') : ?>
                        <span class="cal-pick__price"><?php echo esc_html($price); ?><?php echo $period !== '' ? '<span class="cal-pick__period">' . esc_html($period) . '</span>' : ''; ?></span>
                    <?php endif; ?>
                    <?php if ($discount !== '') : ?>
                        <span class="cal-pick__discount"><?php echo esc_html($discount); ?></span>
                    <?php endif; ?>
                </div>
                <div class="cal-pick__actions">
                    <?php echo $cta_html; ?>
                    <a class="cal-pick__review-link" href="<?php echo esc_url($review_url); ?>">
                        <?php printf(esc_html__('Read %s Review', 'cyberatlaslab'), esc_html($name)); ?>
                    </a>
                </div>
            </article>
            <?php
            return ob_get_clean();
        };

        // 分组渲染：#1 / #2-3 / #4-5
        ob_start();
        ?>
        <section class="cal-picks" id="picks" aria-labelledby="cal-picks-title-<?php echo esc_attr($post_id); ?>">
            <h2 class="cal-picks__title" id="cal-picks-title-<?php echo esc_attr($post_id); ?>">
                <?php echo esc_html($title); ?>
            </h2>
            <div class="cal-picks__grid">
                <?php
                $rank = 0;
        foreach ($products as $product) :
            $pid = is_object($product) ? $product->ID : absint($product);
            if (!$pid) {
                continue;
            }
            $rank++;
            if ($rank === 1) {
                echo $render_pick($pid, $rank);
            }
        endforeach;

        // #2-3 中卡组
        echo '<div class="cal-picks__medium-group">';
        $rank = 0;
        foreach ($products as $product) :
            $pid = is_object($product) ? $product->ID : absint($product);
            if (!$pid) {
                continue;
            }
            $rank++;
            if ($rank === 2 || $rank === 3) {
                echo $render_pick($pid, $rank);
            }
        endforeach;
        echo '</div>';

        // #4-5 小卡组（移动端折叠容器）
        $compact = '';
        $rank = 0;
        foreach ($products as $product) :
            $pid = is_object($product) ? $product->ID : absint($product);
            if (!$pid) {
                continue;
            }
            $rank++;
            if ($rank >= 4) {
                $compact .= $render_pick($pid, $rank);
            }
        endforeach;
        if ($compact !== '') :
            ?>
                    <details class="cal-picks__more" open>
                        <summary class="cal-picks__more-toggle">
                            <?php esc_html_e('Show All Picks', 'cyberatlaslab'); ?>
                        </summary>
                        <div class="cal-picks__compact-group"><?php echo $compact; ?></div>
                    </details>
                    <?php
        endif;
        ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}

if (!shortcode_exists('cal_editor_picks')) {
    add_shortcode('cal_editor_picks', 'cal_editor_picks_shortcode');
}


/**
 *
 *   =============================================================================
 * 38. 分类页 Hero - [cal_cat_hero]（v2 · Fusion D 定稿版）
 * =============================================================================
 * 布局：左（badge/H1/lead/4格stats）+ 右（Trust Panel）→ 分隔线 → 底部（Filters+CTA）→ meta
 * 数据：cat_hero_intro / tested / hours / cycle(新) / points(新·可选) / filters
 *      + cat_category_key（品类标签映射）+ 页面标题（H1）+ modified（Updated）
 * 文案：UI 固定文案全部 PHP 内化（translation-ready），后台只维护数据
 * 图标：pro_list_icon / right_arrow_icon 走 cal_get_icon 映射库
 * 空值纪律：cycle/points 留空则对应 stat 隐藏；H1 不带年份（年份由 badge 承担）
 * =============================================================================
 */
if (!function_exists('cal_cat_hero_shortcode')) {

    function cal_cat_hero_shortcode($atts)
    {
        $post_id = get_the_ID();
        if (!$post_id || !function_exists('get_field')) {
            return '';
        }

        $title   = get_the_title($post_id);
        $intro   = trim((string) get_field('cat_hero_intro', $post_id));
        $tested  = get_field('cat_hero_tested', $post_id);
        $tested  = (is_numeric($tested) && $tested !== '') ? absint($tested) : 0;
        $hours   = trim((string) get_field('cat_hero_hours', $post_id));   // 只填数值部分，如 "1,200+"
        $cycle   = trim((string) get_field('cat_hero_cycle', $post_id));   // 如 "2026"
        $points  = trim((string) get_field('cat_hero_points', $post_id));  // 如 "47"，可选
        $filters = get_field('cat_hero_filters', $post_id);
        $cat_key = trim((string) get_field('cat_category_key', $post_id));
        $updated = get_the_modified_date('F Y', $post_id);

        // 品类标签映射（stats 与 badge 共用）
        $cat_map = array(
            'vpn'      => array('name' => 'VPN', 'tested_label' => __('VPNs tested', 'cyberatlaslab')),
            'security' => array('name' => 'Antivirus', 'tested_label' => __('Antivirus suites tested', 'cyberatlaslab')),
            'parental' => array('name' => 'Parental Control', 'tested_label' => __('Parental control apps tested', 'cyberatlaslab')),
            'password' => array('name' => 'Password Manager', 'tested_label' => __('Password managers tested', 'cyberatlaslab')),
        );
        $cat_name     = isset($cat_map[$cat_key]) ? $cat_map[$cat_key]['name'] : '';
        $tested_label = isset($cat_map[$cat_key]) ? $cat_map[$cat_key]['tested_label'] : __('Products tested', 'cyberatlaslab');

        // stats 装配（空值自动跳过该格）
        $stats = array();
        if ($tested > 0) {
            $stats[] = array('value' => $tested . '+', 'label' => $tested_label);
        }
        if ($hours !== '') {
            $stats[] = array('value' => $hours, 'label' => __('Hours of testing', 'cyberatlaslab'));
        }
        if ($points !== '') {
            $stats[] = array('value' => $points, 'label' => __('Evaluation points', 'cyberatlaslab'));
        }
        if ($cycle !== '') {
            $stats[] = array('value' => $cycle, 'label' => __('Latest test cycle', 'cyberatlaslab'));
        }

        // 图标
        $check_icon = function_exists('cal_get_icon') ? cal_get_icon('pro_list_icon') : '✓';
        $arrow_icon = function_exists('cal_get_icon') ? cal_get_icon('right_arrow_icon') : '→';
        $star_icon = function_exists('cal_get_icon') ? cal_get_icon('filled_star_icon') : '★';

        // Trust Panel 固定文案
        $trust_items = array(
            sprintf(__('Every %s is evaluated using the same framework.', 'cyberatlaslab'), $cat_name !== '' ? strtolower($cat_name) : __('product', 'cyberatlaslab')),
            __('Recommendations are based on hands-on testing.', 'cyberatlaslab'),
            __('Results are translated into practical buying advice.', 'cyberatlaslab'),
        );

        ob_start();
        ?>
        <section class="cal-cat-hero">
            <div class="cal-cat-hero__inner">
                <div class="cal-cat-hero__main">
                    <div class="cal-cat-hero__content">
                        <?php if ($cat_name !== '' && $cycle !== '') : ?>
                            <span class="cal-cat-hero__badge">
                                <span class="cal-cat-hero__badge-icon" aria-hidden="true"><?php echo $star_icon; ?></span>
                                <?php printf(esc_html__('%1$s Reviews · %2$s Edition', 'cyberatlaslab'), esc_html($cat_name), esc_html($cycle)); ?>
                            </span>
                        <?php endif; ?>

                        <h1 class="cal-cat-hero__title"><?php echo esc_html($title); ?></h1>

                        <?php if ($intro !== '') : ?>
                            <p class="cal-cat-hero__lead"><?php echo esc_html($intro); ?></p>
                        <?php endif; ?>

                        <?php if (!empty($stats)) : ?>
                            <div class="cal-cat-hero__stats">
                                <?php foreach ($stats as $stat) : ?>
                                    <div class="cal-cat-hero__stat">
                                        <strong><?php echo esc_html($stat['value']); ?></strong>
                                        <span><?php echo esc_html($stat['label']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <aside class="cal-cat-hero__trust">
                        <p class="cal-cat-hero__trust-kicker"><?php esc_html_e('Why Trust This List?', 'cyberatlaslab'); ?></p>
                        <h2 class="cal-cat-hero__trust-title"><?php esc_html_e('Reviewed & verified', 'cyberatlaslab'); ?></h2>
                        <ul>
                            <?php foreach ($trust_items as $item) : ?>
                                <li>
                                    <span class="cal-cat-hero__trust-check" aria-hidden="true"><?php echo $check_icon; ?></span>
                                    <span><?php echo esc_html($item); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a class="cal-cat-hero__trust-link" href="#methodology">
                            <?php esc_html_e('See our testing process', 'cyberatlaslab'); ?>
                            <span aria-hidden="true"><?php echo $arrow_icon; ?></span>
                        </a>
                    </aside>
                </div>

                <div class="cal-cat-hero__divider"></div>

                <div class="cal-cat-hero__bottom">
                    <?php if (!empty($filters) && is_array($filters)) : ?>
                        <nav class="cal-cat-hero__filters" aria-label="<?php esc_attr_e('Quick Filters:', 'cyberatlaslab'); ?>">
                            <span class="cal-cat-hero__filter-label"><?php esc_html_e('Quick Filters:', 'cyberatlaslab'); ?></span>
                            <?php foreach ($filters as $row) :
                                $label  = !empty($row['filter_label']) ? trim($row['filter_label']) : '';
                                $anchor = !empty($row['filter_anchor']) ? trim($row['filter_anchor']) : '';
                                if ($label === '' || $anchor === '') {
                                    continue;
                                }
                                if ($anchor[0] !== '#') {
                                    $anchor = '#' . $anchor;
                                }
                                ?>
                                <a class="cal-cat-hero__filter" href="<?php echo esc_attr($anchor); ?>"><?php echo esc_html($label); ?></a>
                            <?php endforeach; ?>
                        </nav>
                    <?php endif; ?>

                    <div class="cal-cat-hero__ctas">
                        <a class="cal-cat-hero__btn cal-cat-hero__btn--primary" href="#picks">
                            <?php esc_html_e('See Top Picks', 'cyberatlaslab'); ?>
                            <span aria-hidden="true"><?php echo $arrow_icon; ?></span>
                        </a>
                        <a class="cal-cat-hero__btn cal-cat-hero__btn--ghost" href="#comparison">
                            <?php esc_html_e('Compare All', 'cyberatlaslab'); ?>
                        </a>
                    </div>
                </div>

                <p class="cal-cat-hero__meta">
                    <strong><?php printf(esc_html__('Updated %s', 'cyberatlaslab'), esc_html($updated)); ?></strong>
                    <?php esc_html_e('· By the CyberAtlasLab Research Team', 'cyberatlaslab'); ?>
                </p>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}

if (!shortcode_exists('cal_cat_hero')) {
    add_shortcode('cal_cat_hero', 'cal_cat_hero_shortcode');
}


/**
 * =============================================================================
 * 39. 分类页粘性锚点导航 - [cal_anchor_nav]
 * =============================================================================
 * 结构：
 *   - 桌面态（≥1025px）：贴合式 Tab Bar，序号 01–06 + 浅蓝 Pill 高亮跟随滚动位置切换，始终整行展开
 *   - 移动态（≤1024px）：折叠手风琴，标题固定为 Contents，交互与 [crs_toc_mobile]（测评页 Contents）完全对齐
 * 无 JS 兼容：折叠面板与全部链接在 HTML 层始终存在（渐进增强），JS 仅负责状态展示，
 *   禁用 JS 时链接依然可点击、可被抓取
 * 顺序：锚点顺序与页面真实阅读顺序一致 —— Comparison → How We Test → Top Picks →
 *   Buying Guide → What We Compare → FAQ（共 6 项），保证导航语义与内容顺序对齐（SEO / AEO 目录一致性）
 * 用法：[cal_anchor_nav]
 * =============================================================================
 */
if (!function_exists('cal_anchor_nav_shortcode')) {

    function cal_anchor_nav_shortcode($atts)
    {
        // 顺序即页面真实阅读顺序：先看差异 → 再懂测试方法 → 再看编辑推荐 → 再读指南 → 再看比较维度 → 最后看 FAQ
        // 2026-08 更新：并入 What We Compare（#factors），修复此前导航缺口导致的滚动断层
        $items = array(
            'comparison'  => __('Comparison', 'cyberatlaslab'),
            'methodology' => __('How We Test', 'cyberatlaslab'),
            'picks'       => __('Top Picks', 'cyberatlaslab'),
            'guide'       => __('Buying Guide', 'cyberatlaslab'),
            'factors'     => __('What We Compare', 'cyberatlaslab'),
            'faq'         => __('FAQ', 'cyberatlaslab'),
        );

        $total       = count($items);
        $post_id     = get_the_ID();
        $unique_seed = $post_id ? $post_id : wp_unique_id();
        $panel_id    = 'cal-anchor-panel-' . $unique_seed;
        $label_id    = 'cal-anchor-label-' . $unique_seed;
        $has_icon    = function_exists('cal_get_icon');

        ob_start();
        // 哨兵：sticky 吸顶态检测锚点，无视觉、不参与布局
        echo '<span class="cal-anchor-sentinel" aria-hidden="true"></span>';
        ?>
        <nav class="cal-anchor-nav" data-cal-anchor-nav aria-label="<?php esc_attr_e('Category sections', 'cyberatlaslab'); ?>">

            <!-- 桌面态：贴合式 Tab Bar，序号 01–06 + 浅蓝 Pill 高亮 -->
            <div class="cal-anchor-nav__desktop" data-context="desktop">
                <ul class="cal-anchor-nav__tabs">
                    <?php $d_index = 0;
        foreach ($items as $anchor => $label) : $d_index++; ?>
                        <li class="cal-anchor-nav__tab-item">
                            <a
                                class="cal-anchor-nav__tab"
                                href="#<?php echo esc_attr($anchor); ?>"
                                data-anchor="<?php echo esc_attr($anchor); ?>"
                                data-anchor-target="#<?php echo esc_attr($anchor); ?>"
                                data-context="desktop"
                                aria-current="false"
                            >
                                <span class="cal-anchor-nav__tab-index" aria-hidden="true"><?php echo esc_html(sprintf('%02d', $d_index)); ?></span>
                                <span class="cal-anchor-nav__tab-label"><?php echo esc_html($label); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- 移动态：折叠手风琴，标题固定为 Contents（交互对齐 [crs_toc_mobile]） -->
            <div class="cal-anchor-nav__mobile" data-context="mobile">
                <button
                    class="cal-anchor-nav__toggle"
                    type="button"
                    aria-expanded="false"
                    aria-haspopup="true"
                    aria-controls="<?php echo esc_attr($panel_id); ?>"
                >
                    <span class="cal-anchor-nav__toggle-icon" aria-hidden="true">
                        <?php echo $has_icon ? cal_get_icon('toc_icon') : ''; ?>
                    </span>

                    <span
                        class="cal-anchor-nav__toggle-label"
                        id="<?php echo esc_attr($label_id); ?>"
                    >
                        <?php esc_html_e('Contents', 'cyberatlaslab'); ?>
                    </span>

                    <span class="cal-anchor-nav__toggle-count">
                        <?php echo esc_html($total); ?> <?php esc_html_e('sections', 'cyberatlaslab'); ?>
                    </span>

                    <span class="cal-anchor-nav__toggle-chevron" aria-hidden="true">
                        <?php echo $has_icon ? cal_get_icon('chevron_down') : '▼'; ?>
                    </span>
                </button>

                <ul
                    class="cal-anchor-nav__list"
                    id="<?php echo esc_attr($panel_id); ?>"
                    aria-hidden="true"
                    aria-labelledby="<?php echo esc_attr($label_id); ?>"
                >
                    <?php $index = 0;
        foreach ($items as $anchor => $label) : $index++; ?>
                        <li>
                            <a
                                class="cal-anchor-nav__item"
                                href="#<?php echo esc_attr($anchor); ?>"
                                data-anchor="<?php echo esc_attr($anchor); ?>"
                                data-anchor-target="#<?php echo esc_attr($anchor); ?>"
                                data-context="mobile"
                                aria-current="false"
                            >
                                <span class="cal-anchor-nav__badge" aria-hidden="true"><?php echo esc_html($index); ?></span>
                                <span class="cal-anchor-nav__item-label"><?php echo esc_html($label); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

        </nav>
        <?php
        return ob_get_clean();
    }
}

if (!shortcode_exists('cal_anchor_nav')) {
    add_shortcode('cal_anchor_nav', 'cal_anchor_nav_shortcode');
}



/**
 * =============================================================================
 * 41. 分类页测试与排名流程 - [cal_how_test]（v2 · 1×3 三阶段版）
 * =============================================================================
 * 职责：讲"怎么得出结论"（流程叙事），不讲"比什么"（那是 WWC 的事）
 * 数据：cat_how_test_title + cat_test_stage_1/2/3_content（阶段标题代码固化）
 * 形态：<details open>，桌面默认展开 / 移动端默认折叠（matchMedia 摘 open）
 * 纪律：阶段标题不加 ⓘ；底部统一 Read Full Methodology 出口
 * 锚点：id="methodology"
 * =============================================================================
 */
if (!function_exists('cal_how_test_shortcode')) {

    function cal_how_test_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'summary' => '',
        ), $atts, 'cal_how_test');

        $post_id = get_the_ID();
        if (!$post_id || !function_exists('get_field')) {
            return '';
        }

        $title = get_field('cat_how_test_title', $post_id);
        $title = is_string($title) && trim($title) !== '' ? trim($title) : __('How We Test & Rank', 'cyberatlaslab');

        // 三阶段：标题全站固化（代码），描述按分类页填写（ACF）
        $stages_def = array(
            1 => __('Hands-on Testing', 'cyberatlaslab'),
            2 => __('Research & Verification', 'cyberatlaslab'),
            3 => __('Scoring & Editorial Review', 'cyberatlaslab'),
        );
        $stages = array();
        foreach ($stages_def as $num => $stage_title) {
            $desc = get_field('cat_test_stage_' . $num . '_content', $post_id);
            $desc = is_string($desc) ? trim($desc) : '';
            if ($desc !== '') {
                $stages[] = array('num' => $num, 'title' => $stage_title, 'desc' => $desc);
            }
        }
        if (empty($stages)) {
            return ''; // 空值纪律：无阶段内容整块不渲染
        }

        $summary = trim((string) $atts['summary']);
        if ($summary === '') {
            $summary = __('We test, verify, and review every product against consistent criteria.', 'cyberatlaslab');
        }

        $methodology_url = function_exists('crs_methodology_url') ? crs_methodology_url() : '';

        ob_start();
        ?>
        <section class="cal-hwt" id="methodology" aria-labelledby="cal-hwt-title-<?php echo esc_attr($post_id); ?>">
            <details class="cal-hwt__box" open>
                <summary class="cal-hwt__toggle">
                    <span class="cal-hwt__heading">
                        <span class="cal-hwt__title" id="cal-hwt-title-<?php echo esc_attr($post_id); ?>" role="heading" aria-level="2">
                            <?php echo esc_html($title); ?>
                        </span>
                        <span class="cal-hwt__summary-text"><?php echo esc_html($summary); ?></span>
                    </span>
                    <span class="cal-hwt__toggle-btn">
                        <span class="cal-hwt__show-text"><?php esc_html_e('See How We Test & Rank', 'cyberatlaslab'); ?></span>
                        <span class="cal-hwt__hide-text"><?php esc_html_e('Hide Testing Details', 'cyberatlaslab'); ?></span>
                    </span>
                </summary>
                <div class="cal-hwt__body">
                    <ol class="cal-hwt__stages">
                        <?php foreach ($stages as $stage) : ?>
                            <li class="cal-hwt__stage">
                                <span class="cal-hwt__stage-num" aria-hidden="true"><?php echo esc_html(sprintf('%02d', $stage['num'])); ?></span>
                                <h3 class="cal-hwt__stage-title"><?php echo esc_html($stage['title']); ?></h3>
                                <p class="cal-hwt__stage-desc"><?php echo esc_html($stage['desc']); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <div class="cal-hwt__footer">
                        <p class="cal-hwt__note">
                            <?php esc_html_e('Scores are based on structured testing, category-specific evaluation criteria, and documented editorial review.', 'cyberatlaslab'); ?>
                        </p>
                        <?php if ($methodology_url !== '') : ?>
                            <a class="cal-hwt__more" href="<?php echo esc_url($methodology_url); ?>">
                                <?php esc_html_e('Read Full Methodology', 'cyberatlaslab'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </details>
        </section>
        <?php
        return ob_get_clean();
    }
}

if (!shortcode_exists('cal_how_test')) {
    add_shortcode('cal_how_test', 'cal_how_test_shortcode');
}


/**
 * 41. 产品 Logo 渲染（上下文无关版）：SVG 内联优先，位图走 attachment image
 * 与 crs_render_product_logo 的区别：字段读取显式带 $post_id，不依赖全局 $post
 */
if (!function_exists('crs_get_product_logo_html')) {
    function crs_get_product_logo_html($post_id, $size = 'thumbnail')
    {
        if (!function_exists('get_field')) {
            return '';
        }

        $logo = get_field('product_logo', $post_id);
        if (empty($logo)) {
            return '';
        }

        $att_id = is_array($logo) ? (!empty($logo['id']) ? absint($logo['id']) : 0)
                : (is_numeric($logo) ? absint($logo) : 0);
        if (!$att_id) {
            return '';
        }

        // SVG：内联输出（可继承 CSS 色彩）
        if (get_post_mime_type($att_id) === 'image/svg+xml') {
            $path = get_attached_file($att_id);
            if ($path && file_exists($path)) {
                $svg = file_get_contents($path);
                $svg = preg_replace('/<\?xml[^>]*\?>/', '', $svg);
                return '<span class="cal-logo-svg" aria-hidden="true">' . $svg . '</span>';
            }
        }

        // 位图回退
        return wp_get_attachment_image($att_id, $size, false, array(
            'alt' => get_the_title($post_id) . ' logo',
        ));
    }
}


/**
 * =============================================================================
 * 42. 分类页相关分类简码 - [cal_related_cats]
 * =============================================================================
 * 数据源：当前页「分类页数据」组 cat_related_cats repeater（手动维护 4~6 个）
 * 骨架：参数化复用 Related Reviews 卡片体系（网格/hover/图标圆盘）
 * 输出：纯内链导航卡（图标 + 名称 + 简介 + 箭头），无评分无 CTA
 * SEO：内链不带 nofollow/noreferrer，权重通畅；ul/li 语义列表
 * 空值纪律：无数据整块不渲染；单行缺 name 或 url 跳过该行
 *
 * 用法：
 *   [cal_related_cats]                        ← 默认标题 Related Categories
 *   [cal_related_cats title="Explore More"]   ← 自定义大标题
 * =============================================================================
 */
if (!function_exists('cal_related_cats_shortcode')) {

    function cal_related_cats_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'title' => '',
        ), $atts, 'cal_related_cats');

        $post_id = get_the_ID();
        if (!$post_id || !function_exists('get_field')) {
            return '';
        }

        $items = get_field('cat_related_cats', $post_id);
        if (empty($items) || !is_array($items)) {
            return '';
        }

        $title = trim((string) $atts['title']);
        if ($title === '') {
            $title = __('Related Categories', 'cyberatlaslab');
        }

        ob_start();
        ?>
        <section class="cal-related-cats" aria-labelledby="cal-related-cats-title-<?php echo esc_attr($post_id); ?>">
            <h2 class="cal-related-cats__title" id="cal-related-cats-title-<?php echo esc_attr($post_id); ?>">
                <?php echo esc_html($title); ?>
            </h2>
            <ul class="cal-related-cats__grid">
                <?php foreach ($items as $row) :
                    $name = isset($row['rel_name']) ? trim((string) $row['rel_name']) : '';
                    $url  = isset($row['rel_url']) ? trim((string) $row['rel_url']) : '';
                    if ($name === '' || $url === '') {
                        continue; // 空值纪律：缺核心字段的行直接跳过
                    }
                    $desc = isset($row['rel_desc']) ? trim((string) $row['rel_desc']) : '';
                    $icon_key = !empty($row['rel_icon']) ? $row['rel_icon'] : 'shield';
                    $icon_svg = function_exists('cal_get_icon') ? cal_get_icon($icon_key) : '';
                    $arrow_svg = function_exists('cal_get_icon') ? cal_get_icon('cta-arrow-icon') : '';
                    ?>
                    <li class="cal-related-cat">
                        <a class="cal-related-cat__link" href="<?php echo esc_url($url); ?>">
                            <span class="cal-related-cat__icon" aria-hidden="true"><?php echo $icon_svg; ?></span>
                            <span class="cal-related-cat__body">
                                <span class="cal-related-cat__name"><?php echo esc_html($name); ?></span>
                                <?php if ($desc !== '') : ?>
                                    <span class="cal-related-cat__desc"><?php echo esc_html($desc); ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="cal-related-cat__arrow" aria-hidden="true"><?php echo $arrow_svg; ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php
        return ob_get_clean();
    }
}

if (!shortcode_exists('cal_related_cats')) {
    add_shortcode('cal_related_cats', 'cal_related_cats_shortcode');
}

/**
 * =============================================================================
 * 44. TOC 锚点自动注入器 - cal_inject_toc_anchors()（v2 · 全站免手动版）
 * =============================================================================
 * 三态处理：
 *   ① 无 id 无 data-toc        → 两个都注入（古腾堡/普通写作产物）
 *   ② 有 data-toc 无 id        → 只补 id（存量 Elementor _attributes 工作流）
 *   ③ 两个都有                 → 原样放行（存量测评页，id 收编进防重表）
 * 双通道同源：the_content（前台渲染）+ crs_extract_toc_items（目录构建）
 * =============================================================================
 */
if (!function_exists('cal_inject_toc_anchors')) {
    function cal_inject_toc_anchors($content)
    {
        if (empty($content) || stripos($content, '<h2') === false) {
            return $content;
        }

        // 预扫描：收集存量手工 id，防止生成的锚点与存量冲突
        $used_ids = array();
        if (preg_match_all('/<h2[^>]*\sid=["\']([^"\']+)["\'][^>]*>/isu', $content, $existing)) {
            $used_ids = array_map('strval', $existing[1]);
        }

        return preg_replace_callback('/<h2([^>]*)>(.*?)<\/h2>/isu', function ($m) use (&$used_ids) {
            $attrs = $m[1];
            $inner = $m[2];

            $has_toc = (stripos($attrs, 'data-toc') !== false);
            $has_id  = (bool) preg_match('/\sid=["\'][^"\']+["\']/isu', $attrs);

            // 态③：双属性齐全 → 放行
            if ($has_toc && $has_id) {
                return $m[0];
            }

            // 确定锚点文本：优先 data-toc 值，其次标题纯文本
            $text = '';
            if ($has_toc && preg_match('/data-toc=["\']([^"\']*)["\']/isu', $attrs, $tm)) {
                $text = trim($tm[1]);
            }
            if ($text === '') {
                $text = trim(wp_strip_all_tags($inner));
            }
            if ($text === '') {
                return $m[0];
            }

            // 生成唯一 id（非拉丁字符兜底哈希）
            if (!$has_id) {
                $base = sanitize_title($text);
                if ($base === '') {
                    $base = 'section-' . substr(md5($text), 0, 8);
                }
                $id = $base;
                $i = 2;
                while (in_array($id, $used_ids, true)) {
                    $id = $base . '-' . $i;
                    $i++;
                }
                $used_ids[] = $id;
                $attrs .= ' id="' . esc_attr($id) . '"';
            }

            // 态①：补 data-toc（态②已有）
            if (!$has_toc) {
                $attrs .= ' data-toc="' . esc_attr($text) . '"';
            }

            return '<h2' . $attrs . '>' . $inner . '</h2>';
        }, $content);
    }
}

// 前台通道：产品页 + 分类页；the_content 与 Elementor 渲染双钩子（幂等，安全重复）
if (!function_exists('cal_toc_inject_on_content')) {
    function cal_toc_inject_on_content($content)
    {
        if (is_admin()) {
            return $content;
        }
        $pid = get_the_ID();
        if (!$pid) {
            return $content;
        }
        $pt = get_post_type($pid);
        if ($pt === 'product') {
            return cal_inject_toc_anchors($content);
        }
        if (function_exists('cal_is_category_page') && cal_is_category_page($pid)) {
            return cal_inject_toc_anchors($content);
        }
        return $content;
    }
}
add_filter('the_content', 'cal_toc_inject_on_content', 12);
add_filter('elementor/frontend/the_content', 'cal_toc_inject_on_content', 12);


/**
 * 45. 分类页移动端 Top1 卡 - [cal_mobile_pick_card]
 * 解析 cat_top_products 第 1 名 → 委托 §18 移动卡渲染（样式/显隐沿用其 CSS）
 * 放置：Guide 左栏 Post Content 之后（文尾转化位）；桌面端由其自身 CSS 隐藏
 */
if (!function_exists('cal_mobile_pick_card_shortcode')) {
    function cal_mobile_pick_card_shortcode($atts)
    {
        $post_id = get_the_ID();
        if (!$post_id || !function_exists('get_field') || !function_exists('crs_mobile_card_shortcode')) {
            return '';
        }
        $products = get_field('cat_top_products', $post_id);
        if (empty($products) || !is_array($products)) {
            return '';
        }
        $first = reset($products);
        $top1  = is_object($first) ? $first->ID : absint($first);
        if (!$top1) {
            return '';
        }
        return crs_mobile_card_shortcode(['id' => $top1]);
    }
}
if (!shortcode_exists('cal_mobile_pick_card')) {
    add_shortcode('cal_mobile_pick_card', 'cal_mobile_pick_card_shortcode');
}

/**
 * 46. 分类页判定器：任一 cat_* 核心字段有值即视为分类页
 * （不再单一依赖 cat_top_products，字段组扩充时往数组里加即可）
 */
if (!function_exists('cal_is_category_page')) {
    function cal_is_category_page($post_id)
    {
        if (get_post_type($post_id) !== 'page') {
            return false;
        }
        $markers = array('cat_top_products', 'cat_hero_intro', 'cat_how_test_content');
        foreach ($markers as $key) {
            if (get_post_meta($post_id, $key, true)) {
                return true;
            }
        }
        return false;
    }
}


/**
 * =============================================================================
 * 47. 分类页统一侧边栏 - [cal_sidebar_unified]
 * =============================================================================
 * 装配：Top1 产品卡（§12，id 参数化）+ 本页 TOC（§13，读当前 Page）
 * 镜像 §16 的自适应分割线算法：子件为空自动隐退，分割线不塌陷
 * 样式：全量复用 .crs-sidebar__unified / __section / __divider 现有 CSS
 * 用法：[cal_sidebar_unified]
 * =============================================================================
 */
if (!function_exists('cal_sidebar_unified_shortcode')) {
    function cal_sidebar_unified_shortcode($atts)
    {
        $atts = shortcode_atts([
            'toc_title' => 'Table of Contents',
        ], $atts, 'cal_sidebar_unified');

        $post_id = get_the_ID();
        if (!$post_id || !function_exists('get_field')) {
            return '';
        }

        // 解析 Top1
        $top1 = 0;
        $products = get_field('cat_top_products', $post_id);
        if (!empty($products) && is_array($products)) {
            $first = reset($products);
            $top1  = is_object($first) ? $first->ID : absint($first);
        }

        // 子件装配：卡 = Top1；TOC = 当前分类页正文
        $card = ($top1 && function_exists('crs_sidebar_card_shortcode'))
            ? crs_sidebar_card_shortcode(['id' => $top1])
            : '';
        $toc  = function_exists('crs_sidebar_toc_shortcode')
            ? crs_sidebar_toc_shortcode(['title' => $atts['toc_title']])
            : '';

        // 全局熔断：两子件全空则整体隐退
        if (empty(trim($card)) && empty(trim($toc))) {
            return '';
        }

        ob_start();
        ?>
        <div class="crs-sidebar__unified">
            <?php if (!empty(trim($card))) : ?>
                <div class="crs-sidebar__section crs-sidebar__section--card"><?php echo $card; ?></div>
            <?php endif; ?>

            <?php if (!empty(trim($toc))) : ?>
                <?php if (!empty(trim($card))) : ?>
                    <div class="crs-sidebar__divider"></div>
                <?php endif; ?>
                <div class="crs-sidebar__section crs-sidebar__section--toc"><?php echo $toc; ?></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
if (!shortcode_exists('cal_sidebar_unified')) {
    add_shortcode('cal_sidebar_unified', 'cal_sidebar_unified_shortcode');
}


/**
 * =============================================================================
 * 48. 分类页评分维度框架 - [cal_what_compare]
 * =============================================================================
 * 数据源：Score Library（Options，唯一数据源）按 cat_category_key 过滤
 * 三态渲染：numeric（权重+进度条）/ editorial（降格标签）/ inactive（不渲染）
 * 硬校验：当前分类 numeric 权重合计 ≠100% → 全列隐藏权重与进度条（卡片照出）
 *         + 管理员可见警告条；缺数据 ≠ 显示假数据
 * 布局：6项 2×3 / 5项 3+2 / 4项 2×2 / 3项 3×1（数量修饰类自适应）
 * 用法：[cal_what_compare]
 * =============================================================================
 */
if (!function_exists('cal_what_compare_shortcode')) {

    function cal_what_compare_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'title'    => '',
            'subtitle' => '',
        ), $atts, 'cal_what_compare');

        $post_id = get_the_ID();
        if (!$post_id || !function_exists('get_field')) {
            return '';
        }

        $cat_key = get_field('cat_category_key', $post_id);
        if (empty($cat_key) || !is_string($cat_key)) {
            return '';
        }

        // 直读原始 repeater：crs_get_score_library() 是 tooltip 专用加工器，
        // 会丢弃 weight/scoring_mode/applicable_categories/short_description
        $library = get_field('score_library', 'option');
        if (empty($library) || !is_array($library)) {
            return '';
        }

        // 过滤：适用品类命中 + 非 inactive
        $dims = array();
        foreach ($library as $row) {
            $mode = !empty($row['scoring_mode']) ? $row['scoring_mode'] : 'numeric';
            if ($mode === 'inactive') {
                continue;
            }
            $cats = isset($row['applicable_categories']) ? $row['applicable_categories'] : array();
            $cats = is_array($cats) ? $cats : array($cats);
            if (!in_array($cat_key, $cats, true)) {
                continue;
            }
            $dims[] = array(
                'key'   => isset($row['dimension_key']) ? $row['dimension_key'] : '',
                'label' => !empty($row['dimension_label']) ? $row['dimension_label'] : (isset($row['dimension_key']) ? $row['dimension_key'] : ''),
                'desc'  => !empty($row['short_description']) ? $row['short_description'] : '',
                'mode'  => $mode,
                'weight' => (isset($row['weight']) && $row['weight'] !== '' && is_numeric($row['weight'])) ? (float) $row['weight'] : null,
            );
        }
        if (empty($dims)) {
            return '';
        }

        // 硬校验：numeric 权重合计必须恰为 100
        $weight_sum = 0;
        $numeric_count = 0;
        foreach ($dims as $d) {
            if ($d['mode'] === 'numeric' && $d['weight'] !== null) {
                $weight_sum += $d['weight'];
                $numeric_count++;
            }
        }
        $weights_valid = ($numeric_count > 0 && abs($weight_sum - 100) < 0.01);

        // 布局修饰类：4 项走 2×2，其余走 3 列自适应
        $count = count($dims);
        $grid_class = $count === 4 ? 'cal-wwc__grid--cols2' : '';

        $title = trim((string) $atts['title']);
        if ($title === '') {
            $title = __('What We Compare', 'cyberatlaslab');
        }
        $subtitle = trim((string) $atts['subtitle']);
        if ($subtitle === '') {
            $subtitle = __('The key factors in our evaluation framework', 'cyberatlaslab');
        }

        ob_start();
        ?>
        <section class="cal-wwc" id="factors" aria-labelledby="cal-wwc-title-<?php echo esc_attr($post_id); ?>">
            <header class="cal-wwc__header">
                <h2 class="cal-wwc__title" id="cal-wwc-title-<?php echo esc_attr($post_id); ?>"><?php echo esc_html($title); ?></h2>
                <p class="cal-wwc__subtitle"><?php echo esc_html($subtitle); ?></p>
                <?php if ($weights_valid) : ?>
                    <p class="cal-wwc__weight-note">
                        <?php esc_html_e('Percentages show how much each factor contributes to the category score. They are not product ratings or recommendation probabilities.', 'cyberatlaslab'); ?>
                    </p>
                <?php endif; ?>
            </header>

            <?php
            // 管理员 QA 警告：校验失败时仅管理员可见
            if (!$weights_valid && $numeric_count > 0 && current_user_can('manage_options')) :
                ?>
                <div class="cal-wwc__admin-warning" role="alert">
                    ⚠️ <?php printf(
                        esc_html__('Scoring Consistency Check: numeric weights for this category sum to %s%% (must be 100%%). Percentages and bars are hidden from visitors until fixed in Score Library.', 'cyberatlaslab'),
                        esc_html($weight_sum)
                    ); ?>
                </div>
            <?php endif; ?>

            <ul class="cal-wwc__grid <?php echo esc_attr($grid_class); ?>">
                <?php foreach ($dims as $d) :
                    $is_editorial = ($d['mode'] !== 'numeric' || $d['weight'] === null);
                    $icon_svg = function_exists('cal_get_icon') && $d['key'] !== '' ? cal_get_icon($d['key']) : '';
                    $tip = ($d['key'] !== '' && function_exists('crs_render_score_tooltip'))
                        ? crs_render_score_tooltip($d['key'])
                        : '';
                    ?>
                    <li class="cal-wwc__card<?php echo $is_editorial ? ' cal-wwc__card--editorial' : ''; ?>">
                        <span class="cal-wwc__icon" aria-hidden="true"><?php echo $icon_svg; ?></span>
                        <h3 class="cal-wwc__name">
                            <?php echo esc_html($d['label']); ?><?php echo $tip; ?>
                        </h3>
                        <?php if ($d['desc'] !== '') : ?>
                            <p class="cal-wwc__desc"><?php echo esc_html($d['desc']); ?></p>
                        <?php endif; ?>
                        <div class="cal-wwc__footer">
                            <?php if (!$is_editorial && $weights_valid) : ?>
                                <div class="cal-wwc__weight-row">
                                    <span class="cal-wwc__weight-label"><?php esc_html_e('Score contribution', 'cyberatlaslab'); ?></span>
                                    <span class="cal-wwc__weight-value"><?php echo esc_html(rtrim(rtrim(number_format($d['weight'], 1), '0'), '.')); ?>%</span>
                                </div>
                                <div class="cal-wwc__bar"><span style="width: <?php echo esc_attr($d['weight']); ?>%"></span></div>
                            <?php elseif ($is_editorial) : ?>
                                <span class="cal-wwc__editorial-tag"><?php esc_html_e('Editorial consideration', 'cyberatlaslab'); ?></span>
                                <p class="cal-wwc__editorial-note"><?php esc_html_e('Used to inform final recommendations.', 'cyberatlaslab'); ?></p>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php
        return ob_get_clean();
    }
}

if (!shortcode_exists('cal_what_compare')) {
    add_shortcode('cal_what_compare', 'cal_what_compare_shortcode');
}














/**

 * =============================================================================

 * 预留扩展区域


 *

 * 可在此添加：

 * - 自定义短代码

 * - AJAX 处理函数

 * - 自定义文章类型

 * - REST API 端点

 * - 自定义 Widgets

 * =============================================================================

 */
