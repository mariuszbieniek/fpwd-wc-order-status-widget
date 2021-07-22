<?php
/*
* Plugin Name: WooCommerce Order Status Check
* Plugin URI: https://frompolandwithdev.com
* Requires PHP: 7.2
* Description: A simple widget that allows to check WooCommerce order status in another WordPress installation.
* Version 1.0
* Author: Mariusz Bieniek
* Author URI: https://mariuszbieniek.dev
*/

class WC_Order_Status_Check_Widget extends WP_Widget {
    public CONST NAMESPACE = 'fpwd-wc-order-status-widget';

    public function __construct()
    {
        parent::__construct(
            'wc_order_status_check_widget',
            'WooCommerce Order Status Check',
            esc_html__(
                'A simple widget that allows to check WooCommerce order status in another Wordpress installation.',
                self::NAMESPACE
            )
        );

        /**
         * Add ajax actions
         */
        add_action(
            sprintf('wp_ajax_%s-ajax', self::NAMESPACE),
            [$this, 'ajax_call']
        );

        add_action(
            sprintf('wp_ajax_nopriv_%s-ajax', self::NAMESPACE),
            [$this, 'ajax_call']
        );

        /**
         * Register widget
         */
        add_action('widgets_init', function() {
            register_widget('WC_Order_Status_Check_Widget');
        });

        /**
         * Register Styles
         */
        wp_register_style(
          self::NAMESPACE,
          plugins_url(sprintf('assets/css/%s.css', self::NAMESPACE), __FILE__)
        );

        /**
         * Register scripts
         */
        wp_register_script(
          self::NAMESPACE,
            plugins_url(sprintf('assets/js/%s.js', self::NAMESPACE), __FILE__),
            ['jquery']
        );

        /**
         * Enqueue styles
         */
        wp_enqueue_style(self::NAMESPACE);

        /**
         * Enqueue scripts
         */
        if (!is_admin()) {
            wp_enqueue_script(self::NAMESPACE);
        }
    }

    /**
     * Echoes the widget content.
     *
     * @param array $args
     * @param array $instance
     */
    public function widget($args, $instance): void
    {
        if (empty($instance['error'])) {
            echo $args['before_widget'];
            /** markup for form */ ?>
            <div class="<?php echo sprintf('%s__form', self::NAMESPACE);?>">
                <form>
                    <label><?php echo esc_html__('Order Number', self::NAMESPACE); ?></label>
                    <input
                        type="text"
                        name="<?php echo sprintf('%s-order-number', self::NAMESPACE); ?>"
                        required
                    />
                    <label>E-mail</label>
                    <input
                        type="email"
                        name="<?php echo sprintf('%s-user-email', self::NAMESPACE); ?>"
                        required
                    />
                    <input
                        type="hidden"
                        name="<?php echo sprintf('%s-widget-id', self::NAMESPACE); ?>"
                        value="<?php echo $this->number; ?>"
                    />
                    <button type="submit">Check status</button>
                    <div class="<?php echo sprintf('%s__result', self::NAMESPACE); ?>"></div>
                </form>
            </div>
            <?php /** end markup for form */
            echo $args['after_widget'];
        }
    }

    /**
     * Outputs the settings update form.
     *
     * @param array $instance
     * @return void
     */
    public function form($instance): void
    {
        $wordpress_url = !empty($instance['wordpress_url']) ?
            $instance['wordpress_url'] : esc_html__('', self::NAMESPACE);
        $woocommerce_ck = !empty($instance['woocommerce_ck']) ?
            $instance['woocommerce_ck'] : esc_html__('', self::NAMESPACE);
        $woocommerce_cs = !empty($instance['woocommerce_cs']) ?
            $instance['woocommerce_cs'] : esc_html__('', self::NAMESPACE);
        $error = !empty($instance['error']) ? $instance['error'] : esc_html__('', self::NAMESPACE);

        /** markup for form */ ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('wordpress_url')); ?>">
                <?php echo esc_html__('Wordpress URL', self::NAMESPACE); ?>
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo esc_attr($this->get_field_id('wordpress_url')); ?>"
                name="<?php echo esc_attr($this->get_field_name('wordpress_url')); ?>"
                value="<?php echo esc_attr($wordpress_url); ?>"
            />
            <?php
                echo sprintf(
                    '<span class="%s">%s</span>',
                    sprintf('%s__error', self::NAMESPACE),
                    esc_attr($error)
                );
            ?>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('woocommerce_ck')); ?>">
              <?php echo esc_html__('WooCommerce Consumer API Key', self::NAMESPACE); ?>
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo esc_attr($this->get_field_id('woocommerce_ck')); ?>"
                name="<?php echo esc_attr($this->get_field_name('woocommerce_ck')); ?>"
                value="<?php echo esc_attr($woocommerce_ck); ?>"
            />
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('woocommerce_cs')); ?>">
                <?php echo esc_html__('WooCommerce Consumer API Secret', self::NAMESPACE); ?>
            </label>
            <input
              class="widefat"
              type="password"
              id="<?php echo esc_attr($this->get_field_id('woocommerce_cs')); ?>"
              name="<?php echo esc_attr($this->get_field_name('woocommerce_cs')); ?>"
              value="<?php echo esc_attr($woocommerce_cs); ?>"
            />
        </p>
        <?php /** end markup for form */
    }

    /**
     * Updates a particular instance of a widget.
     *
     * @param array $new_instance
     * @param array $old_instance
     * @return array
     */
    public function update($new_instance, $old_instance): array
    {
        return [
            'wordpress_url' => !empty($new_instance['wordpress_url']) ? strip_tags($new_instance['wordpress_url']) : '',
            'woocommerce_ck' => !empty($new_instance['woocommerce_ck']) ? strip_tags($new_instance['woocommerce_ck']) : '',
            'woocommerce_cs' => !empty($new_instance['woocommerce_cs']) ? strip_tags($new_instance['woocommerce_cs']) : '',
            'error' => $this->validate_url($new_instance)
        ];
    }

    /**
     * Validates the provided details.
     *
     * @param array $instance
     * @return string
     */
    public function validate_url(array $instance): string
    {
        if (!filter_var($instance['wordpress_url'], FILTER_VALIDATE_URL)) {
            return esc_html__('Invalid URL.', self::NAMESPACE);
        }

        if (!$this->validate_woocommerce($instance)) {
          return esc_html__(
            'Could not connect to WooCommerce with given data.',
            self::NAMESPACE
          );
        }

        return esc_html__('', self::NAMESPACE);
    }

    /**
     * Validates connection with another WooCommerce.
     *
     * @param array $instance
     * @return bool
     */
    public function validate_woocommerce(array $instance): bool
    {
        [
            'wordpress_url' => $wordpress_url,
            'woocommerce_ck' => $woocommerce_ck,
            'woocommerce_cs' => $woocommerce_cs
        ] = $instance;

        $response = wp_remote_get("{$wordpress_url}/wp-json/wc/v2/orders", [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(
                    sprintf('%s:%s', $woocommerce_ck, $woocommerce_cs)
                )
            ]
        ]);

        return wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Ajax call for frontend form.
     */
    public function ajax_call(): void
    {
        $order_number = filter_input(INPUT_POST, 'orderNumber');
        $email = filter_input(INPUT_POST, 'email');
        $widget_id = filter_input(INPUT_POST, 'widgetId');
        $options = get_option($this->option_name);

        [
            'wordpress_url' => $wordpress_url,
            'woocommerce_ck' => $woocommerce_ck,
            'woocommerce_cs' => $woocommerce_cs
        ] = $options[$widget_id];

        $response = wp_remote_get("{$wordpress_url}/wp-json/wc/v2/orders/{$order_number}", [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(
                    sprintf('%s:%s', $woocommerce_ck, $woocommerce_cs)
                )
            ]
        ]);

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (
          (isset($response_body['billing']['email']) && $response_body['billing']['email'] !== $email) ||
          wp_remote_retrieve_response_code($response) !== 200
        ) {
          echo esc_html__('Given order could not be found. ', self::NAMESPACE);
          exit;
        }

        echo sprintf(
            '%s: <b>%s</b>',
            esc_html__('Your order status is', self::NAMESPACE),
            mb_strtoupper($response_body['status'])
        );

        exit;
    }
}

$widget = new WC_Order_Status_Check_Widget();