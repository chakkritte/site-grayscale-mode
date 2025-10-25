<?php
/*
Plugin Name: Site Grayscale Mode
Plugin URI: https://github.com/chakkritte/site-grayscale-mode
Description: Convert the entire site to grayscale with adjustable intensity, a visitor toggle, shortcode, and optional wp-admin support.
Version: 1.1.0
Author: Chakkrit Termritthikun
License: GPLv2 or later
Text Domain: site-grayscale-mode
*/

if (!defined('ABSPATH')) exit;

if (!class_exists('Site_Grayscale_Mode')) :

class Site_Grayscale_Mode {
    const OPT_KEY = 'sgm_options';

    public function __construct() {
        // Settings/admin
        add_action('admin_init',  [$this, 'register_settings']);
        add_action('admin_menu',  [$this, 'add_settings_page']);

        // Front-end & admin rendering
        add_action('wp_head',     [$this, 'output_styles']);
        add_action('admin_head',  [$this, 'output_styles']); // support grayscale in dashboard when enabled
        add_action('wp_footer',   [$this, 'maybe_output_toggle_button']);

        // Shortcodes
        add_action('init',        [$this, 'register_shortcodes']);

        // Admin bar toggle (admins)
        add_action('admin_bar_menu', [$this, 'maybe_adminbar_toggle'], 100);

        // Helpers
        add_filter('body_class',  [$this, 'body_class']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
    }

    public function load_textdomain() {
        load_plugin_textdomain('site-grayscale-mode', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    // --- Options ---
    public static function default_options() {
        return [
            'enabled'       => 1,    // grayscale enabled on front-end
            'intensity'     => 100,  // 0–100
            'apply_admin'   => 0,    // apply to wp-admin
            'allow_toggle'  => 1,    // show front-end toggle button
            'show_adminbar' => 1,    // admin bar toggle (admins only)
            'button_label'  => 'Toggle grayscale',
        ];
    }

    private function get_options() {
        $opts = get_option(self::OPT_KEY, []);
        $opts = wp_parse_args($opts, self::default_options());
        $opts['intensity']     = max(0, min(100, intval($opts['intensity'])));
        $opts['enabled']       = !empty($opts['enabled']) ? 1 : 0;
        $opts['apply_admin']   = !empty($opts['apply_admin']) ? 1 : 0;
        $opts['allow_toggle']  = !empty($opts['allow_toggle']) ? 1 : 0;
        $opts['show_adminbar'] = !empty($opts['show_adminbar']) ? 1 : 0;
        $opts['button_label']  = sanitize_text_field($opts['button_label']);
        return $opts;
    }

    public function register_settings() {
        register_setting(
            'sgm_settings',
            self::OPT_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
                'default'           => self::default_options(),
            ]
        );

        add_settings_section(
            'sgm_main',
            __('Grayscale Mode', 'site-grayscale-mode'),
            function () {
                echo '<p>' . esc_html__('Render your site in grayscale. Useful for memorial/mourning modes or experiments.', 'site-grayscale-mode') . '</p>';
            },
            'sgm'
        );

        add_settings_field(
            'enabled',
            __('Enable grayscale', 'site-grayscale-mode'),
            function () {
                $o = $this->get_options();
                echo '<label><input type="checkbox" name="' . esc_attr(self::OPT_KEY) . '[enabled]" value="1" ' . checked(1, $o['enabled'], false) . '> ' . esc_html__('Enable on front-end', 'site-grayscale-mode') . '</label>';
            },
            'sgm',
            'sgm_main'
        );

        add_settings_field(
            'intensity',
            __('Intensity (%)', 'site-grayscale-mode'),
            function () {
                $o = $this->get_options();
                echo '<input type="number" min="0" max="100" name="' . esc_attr(self::OPT_KEY) . '[intensity]" value="' . esc_attr($o['intensity']) . '" />';
            },
            'sgm',
            'sgm_main'
        );

        add_settings_field(
            'apply_admin',
            __('Apply in wp-admin', 'site-grayscale-mode'),
            function () {
                $o = $this->get_options();
                echo '<label><input type="checkbox" name="' . esc_attr(self::OPT_KEY) . '[apply_admin]" value="1" ' . checked(1, $o['apply_admin'], false) . '> ' . esc_html__('Also grayscale the dashboard (use with care)', 'site-grayscale-mode') . '</label>';
            },
            'sgm',
            'sgm_main'
        );

        add_settings_field(
            'allow_toggle',
            __('Visitor toggle', 'site-grayscale-mode'),
            function () {
                $o = $this->get_options();
                echo '<label><input type="checkbox" name="' . esc_attr(self::OPT_KEY) . '[allow_toggle]" value="1" ' . checked(1, $o['allow_toggle'], false) . '> ' . esc_html__('Show a front-end toggle button', 'site-grayscale-mode') . '</label>';
            },
            'sgm',
            'sgm_main'
        );

        add_settings_field(
            'show_adminbar',
            __('Admin Bar toggle', 'site-grayscale-mode'),
            function () {
                $o = $this->get_options();
                echo '<label><input type="checkbox" name="' . esc_attr(self::OPT_KEY) . '[show_adminbar]" value="1" ' . checked(1, $o['show_adminbar'], false) . '> ' . esc_html__('Show toggle in Admin Bar (admins only)', 'site-grayscale-mode') . '</label>';
            },
            'sgm',
            'sgm_main'
        );

        add_settings_field(
            'button_label',
            __('Button label', 'site-grayscale-mode'),
            function () {
                $o = $this->get_options();
                echo '<input type="text" name="' . esc_attr(self::OPT_KEY) . '[button_label]" value="' . esc_attr($o['button_label']) . '" class="regular-text" />';
            },
            'sgm',
            'sgm_main'
        );
    }

    public function sanitize($input) {
        $out = self::default_options();
        if (isset($input['enabled']))       $out['enabled'] = 1;
        if (isset($input['apply_admin']))   $out['apply_admin'] = 1;
        if (isset($input['allow_toggle']))  $out['allow_toggle'] = 1;
        if (isset($input['show_adminbar'])) $out['show_adminbar'] = 1;
        if (isset($input['intensity']))     $out['intensity'] = max(0, min(100, intval($input['intensity'])));
        if (isset($input['button_label']))  $out['button_label'] = sanitize_text_field($input['button_label']);
        return $out;
    }

    public function add_settings_page() {
        add_options_page(
            __('Grayscale Mode', 'site-grayscale-mode'),
            __('Grayscale Mode', 'site-grayscale-mode'),
            'manage_options',
            'sgm',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Grayscale Mode', 'site-grayscale-mode'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('sgm_settings');
                do_settings_sections('sgm');
                submit_button();
                ?>
            </form>
            <p><small>
                <?php echo esc_html__('To exclude a specific element from grayscale, add the CSS class', 'site-grayscale-mode'); ?>
                <code>no-grayscale</code>.
            </small></p>
        </div>
        <?php
    }

    // --- Output styles ---
    public function output_styles() {
        $o = $this->get_options();

        // Determine context: front-end or admin
        $in_admin = is_admin();

        // If in admin and not applying to admin, skip
        if ($in_admin && !$o['apply_admin']) return;

        // If on front-end and not enabled, skip
        if (!$in_admin && !$o['enabled']) return;

        $int = intval($o['intensity']);
        ?>
        <style id="sgm-grayscale-style">
            html.sgm-grayscale-root {
                -webkit-filter: grayscale(<?php echo $int; ?>%);
                filter: grayscale(<?php echo $int; ?>%);
            }
            /* Opt-out hook */
            .no-grayscale, .no-grayscale * {
                -webkit-filter: none !important;
                filter: none !important;
            }
            @media print {
                html.sgm-grayscale-root {
                    -webkit-filter: none !important;
                    filter: none !important;
                }
            }
        </style>
        <script>
            (function () {
                try { document.documentElement.classList.add('sgm-grayscale-root'); } catch (e) {}
                // Restore user preference (OFF) even on first paint
                try {
                    if (localStorage.getItem('sgmUserOff') === '1') {
                        document.documentElement.classList.add('sgm-user-off');
                    }
                } catch (e) {}
            })();
        </script>
        <style>
            /* When user turns OFF grayscale, remove the filter */
            html.sgm-user-off { -webkit-filter: none !important; filter: none !important; }
        </style>
        <?php
    }

    // --- Visitor toggle button ---
    public function maybe_output_toggle_button() {
        $o = $this->get_options();
        if (!$o['enabled'] || !$o['allow_toggle']) return;

        $label = esc_html($o['button_label']);
        ?>
        <style>
            .sgm-toggle-btn {
                position: fixed; z-index: 99999; bottom: 1rem; right: 1rem;
                padding: .6rem .8rem; font-size: 14px; line-height: 1; cursor: pointer;
                border: 1px solid rgba(0,0,0,.15); background: #fff; border-radius: .5rem;
                box-shadow: 0 2px 8px rgba(0,0,0,.12);
            }
            .sgm-toggle-btn:focus { outline: 2px solid #2271b1; outline-offset: 2px; }
            @media (prefers-reduced-motion: reduce) { .sgm-toggle-btn { transition: none; } }
        </style>
        <button type="button" class="sgm-toggle-btn" id="sgmToggleBtn" aria-pressed="false" aria-label="<?php echo $label; ?>">
            <?php echo $label; ?>
        </button>
        <script>
            (function(){
                var KEY = 'sgmUserOff';
                var docEl = document.documentElement;
                // Restore previous choice
                try {
                    if (localStorage.getItem(KEY) === '1') {
                        docEl.classList.add('sgm-user-off');
                    }
                } catch(e){}

                var btn = document.getElementById('sgmToggleBtn');
                if (!btn) return;

                function updatePressed(){
                    var off = docEl.classList.contains('sgm-user-off');
                    btn.setAttribute('aria-pressed', off ? 'true' : 'false');
                    btn.title = off ? 'Grayscale is OFF' : 'Grayscale is ON';
                }

                btn.addEventListener('click', function(){
                    var off = docEl.classList.toggle('sgm-user-off');
                    try { localStorage.setItem(KEY, off ? '1' : '0'); } catch(e){}
                    updatePressed();
                });

                updatePressed();
            })();
        </script>
        <?php
    }

    // --- Shortcode ---
    public function register_shortcodes() {
        add_shortcode('grayscale_toggle', function ($atts) {
            $o = $this->get_options();
            if (!$o['enabled'] || !$o['allow_toggle']) return '';
            ob_start();
            ?>
            <button type="button" class="sgm-toggle-btn" id="sgmToggleInline" aria-pressed="false">
                <?php echo esc_html($o['button_label']); ?>
            </button>
            <script>
                (function(){
                    var KEY = 'sgmUserOff';
                    var docEl = document.documentElement;
                    var btn = document.getElementById('sgmToggleInline');
                    if (!btn) return;
                    function sync() {
                        var off = docEl.classList.contains('sgm-user-off');
                        btn.setAttribute('aria-pressed', off ? 'true' : 'false');
                    }
                    btn.addEventListener('click', function(){
                        var off = docEl.classList.toggle('sgm-user-off');
                        try { localStorage.setItem(KEY, off ? '1' : '0'); } catch(e){}
                        sync();
                    });
                    // initialize
                    try { if (localStorage.getItem(KEY) === '1') docEl.classList.add('sgm-user-off'); } catch(e){}
                    sync();
                })();
            </script>
            <?php
            return ob_get_clean();
        });
    }

    // --- Admin bar toggle ---
    public function maybe_adminbar_toggle($wp_admin_bar) {
        if (!current_user_can('manage_options')) return;
        $o = $this->get_options();
        if (!$o['show_adminbar']) return;

        $wp_admin_bar->add_node([
            'id'    => 'sgm-toggle',
            'title' => esc_html__('Grayscale: Toggle', 'site-grayscale-mode'),
            'href'  => '#',
            'meta'  => [
                'onclick' => 'document.documentElement.classList.toggle("sgm-user-off"); event.preventDefault();',
                'title' => esc_attr__('Toggle grayscale for this browser', 'site-grayscale-mode'),
            ]
        ]);
    }

    // --- Body class helper ---
    public function body_class($classes) {
        $o = $this->get_options();
        if ($o['enabled']) {
            $classes[] = 'sgm-grayscale';
        }
        return $classes;
    }
}

new Site_Grayscale_Mode();

endif; // class check
