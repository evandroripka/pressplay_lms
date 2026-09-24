<?php
if (!defined('ABSPATH')) exit;

/** Per-course contracts and affirmative checkout acceptance, stored through order CRUD. */
class PRESS_LMS_Terms
{
    const META = '_press_course_terms';
    const ORDER_META = '_press_lms_terms_acceptances';
    const SESSION_KEY = 'press_lms_terms_acceptances';

    public static function init(): void
    {
        add_action('add_meta_boxes_press_course', [__CLASS__, 'add_box']);
        add_action('save_post_press_course', [__CLASS__, 'save'], 10, 2);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('woocommerce_review_order_before_submit', [__CLASS__, 'render_checkout']);
        add_action('woocommerce_after_checkout_validation', [__CLASS__, 'validate_checkout'], 10, 2);
        add_action('woocommerce_checkout_create_order', [__CLASS__, 'save_checkout'], 5, 2);
        add_filter('render_block_woocommerce/checkout', [__CLASS__, 'render_blocks']);
        add_action('wc_ajax_press_lms_accept_terms', [__CLASS__, 'accept_session']);
        add_action('woocommerce_store_api_checkout_order_processed', [__CLASS__, 'save_blocks'], 1);
        add_action('woocommerce_cart_emptied', [__CLASS__, 'clear_session']);
        add_action('woocommerce_pay_order_before_submit', [__CLASS__, 'render_order_pay']);
        add_action('woocommerce_before_pay_action', [__CLASS__, 'save_order_pay'], 1);
        add_action('woocommerce_admin_order_data_after_order_details', [__CLASS__, 'render_admin_record']);
        add_action('woocommerce_order_details_after_order_table', [__CLASS__, 'render_customer_record']);
    }

    public static function add_box(): void
    {
        add_meta_box('press-course-terms', 'Termos de compra e contrato', [__CLASS__, 'render_editor'], 'press_course', 'normal', 'default');
    }

    public static function render_editor($post): void
    {
        wp_nonce_field('press_course_terms_save', 'press_course_terms_nonce');
        echo '<p>Ao preencher, o checkout exigira um aceite separado para este curso antes do pagamento. Deixe vazio se nao houver contrato especifico.</p>';
        wp_editor((string) get_post_meta($post->ID, self::META, true), 'press_course_terms', ['textarea_name'=>'press_course_terms', 'textarea_rows'=>16, 'media_buttons'=>false]);
        echo '<p class="description">Guardamos no pedido uma copia do texto, sua versao, data/hora, cliente, IP e navegador. Isto registra o aceite, nao e assinatura digital certificada. Revise o contrato e a politica de privacidade com assessoria juridica. Autorizacoes de marketing devem ser separadas.</p>';
    }

    public static function save($id, $post): void
    {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($id)
            || !current_user_can('edit_post', $id) || !is_string($_POST['press_course_terms_nonce'] ?? null)
            || !wp_verify_nonce(wp_unslash($_POST['press_course_terms_nonce']), 'press_course_terms_save')
            || !is_string($_POST['press_course_terms'] ?? null)) return;
        update_post_meta($id, self::META, wp_slash(wp_kses_post(wp_unslash($_POST['press_course_terms']))));
    }

    public static function get_contract(int $course_id): array
    {
        if ($course_id <= 0 || get_post_type($course_id) !== 'press_course') return [];
        $content = trim(wp_kses_post((string) get_post_meta($course_id, self::META, true)));
        if (trim(wp_strip_all_tags($content)) === '') return [];
        return ['course_id'=>$course_id, 'title'=>get_the_title($course_id), 'content'=>$content, 'version'=>hash('sha256', $content)];
    }

    public static function contracts_for_products(array $product_ids): array
    {
        $contracts = [];
        foreach (array_unique(array_map('intval', $product_ids)) as $product_id) {
            $course_id = PRESS_LMS_Woo::get_course_id_from_product_id($product_id);
            $contract = self::get_contract($course_id);
            if ($contract) $contracts[$course_id] = $contract;
        }
        return $contracts;
    }

    private static function cart_contracts(): array
    {
        return function_exists('WC') && WC()->cart
            ? self::contracts_for_products(array_column(WC()->cart->get_cart(), 'product_id')) : [];
    }

    private static function order_contracts($order): array
    {
        $ids = [];
        foreach ($order->get_items() as $item) $ids[] = $item->get_product_id();
        return self::contracts_for_products($ids);
    }

    public static function render_contract(array $contract): void
    {
        echo '<details class="presslms-contract"><summary>Ler termos de compra: ' . esc_html($contract['title']) . '</summary>';
        echo '<div class="presslms-contract__text">' . wp_kses_post(wpautop($contract['content'])) . '</div></details>';
    }

    public static function render_course(int $course_id): void
    {
        $contract = self::get_contract($course_id);
        if ($contract) self::render_contract($contract);
    }

    private static function render_fields(array $contracts, bool $blocks = false): void
    {
        if (!$contracts) return;
        $session = $blocks && WC()->session ? (array) WC()->session->get(self::SESSION_KEY, []) : [];
        echo '<section class="presslms-contracts" aria-label="Termos de compra"' . ($blocks ? ' data-terms-session="1" data-endpoint="' . esc_url(WC_AJAX::get_endpoint('press_lms_accept_terms')) . '" data-nonce="' . esc_attr(wp_create_nonce(self::session_nonce_action())) . '"' : '') . '>';
        echo '<h3>Termos de compra</h3><p>Leia o contrato de cada curso e confirme sua concordancia para concluir a compra.</p>';
        foreach ($contracts as $id => $contract) {
            self::render_contract($contract);
            $accepted = $blocks && self::valid_session_entry($session[$id] ?? [], $contract);
            echo '<p><label><input type="checkbox" name="press_lms_terms[' . (int) $id . ']" value="' . esc_attr($contract['version']) . '" data-course-id="' . (int) $id . '" required ' . checked($accepted, true, false) . '> Li e aceito os termos de compra de <strong>' . esc_html($contract['title']) . '</strong>.</label></p>';
        }
        echo '<p class="presslms-contracts__status" role="status" aria-live="polite"></p></section>';
    }

    public static function render_checkout(): void { self::render_fields(self::cart_contracts()); }

    public static function render_blocks(string $html): string
    {
        if (is_admin()) return $html;
        ob_start();
        self::render_fields(self::cart_contracts(), true);
        return ob_get_clean() . $html;
    }

    private static function submitted(): array
    {
        return isset($_POST['press_lms_terms']) && is_array($_POST['press_lms_terms']) ? wp_unslash($_POST['press_lms_terms']) : [];
    }

    public static function missing_acceptances(array $contracts, array $accepted): array
    {
        return array_filter($contracts, static function ($contract) use ($accepted) {
            $value = $accepted[$contract['course_id']] ?? null;
            return !is_string($value) || !hash_equals($contract['version'], $value);
        });
    }

    public static function validate_checkout($data, $errors): void
    {
        foreach (self::missing_acceptances(self::cart_contracts(), self::submitted()) as $contract) {
            $errors->add('press_lms_terms_' . $contract['course_id'], 'Leia e aceite os termos de compra de ' . $contract['title'] . '. Se o texto mudou, atualize a pagina e aceite a nova versao.');
        }
    }

    public static function save_checkout($order, $data = []): void
    {
        $contracts = self::order_contracts($order);
        if (self::missing_acceptances($contracts, self::submitted())) throw new Exception('Confirme os termos de compra de todos os cursos antes de pagar.');
        self::snapshot($order, $contracts, 'classic-checkout');
    }

    /** Append snapshots; changing course terms never rewrites a previous acceptance. */
    public static function snapshot($order, array $contracts, string $channel, array $times = []): void
    {
        $history = (array) $order->get_meta(self::ORDER_META, true);
        foreach ($contracts as $contract) {
            $key = $contract['course_id'] . ':' . $contract['version'];
            if (isset($history[$key])) continue;
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            $history[$key] = $contract + [
                'accepted_at_utc'=>gmdate('Y-m-d H:i:s', $times[$contract['course_id']] ?? time()),
                'recorded_at_utc'=>gmdate('Y-m-d H:i:s'),
                'customer_id'=>(int) $order->get_customer_id(),
                'email'=>(string) $order->get_billing_email(),
                'ip'=>filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '',
                'user_agent'=>substr(sanitize_text_field(wp_unslash((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))), 0, 512),
                'channel'=>$channel,
            ];
        }
        if ($contracts) $order->update_meta_data(self::ORDER_META, $history);
    }

    private static function session_nonce_action(): string
    {
        return 'press_lms_terms_' . hash('sha256', (string) WC()->session->get_customer_id());
    }

    private static function valid_session_entry(array $entry, array $contract): bool
    {
        return is_string($entry['version'] ?? null) && hash_equals($contract['version'], $entry['version'])
            && (int) ($entry['time'] ?? 0) > time() - DAY_IN_SECONDS;
    }

    public static function accept_session(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !WC()->session) wp_send_json_error(['message'=>'Requisicao invalida.'], 400);
        if (!is_string($_POST['nonce'] ?? null) || !wp_verify_nonce(wp_unslash($_POST['nonce']), self::session_nonce_action())) {
            wp_send_json_error(['message'=>'Sua sessao expirou. Atualize a pagina.'], 403);
        }
        $contracts = self::cart_contracts();
        $id = absint($_POST['course_id'] ?? 0);
        $version = is_string($_POST['version'] ?? null) ? wp_unslash($_POST['version']) : '';
        if (!isset($contracts[$id]) || !hash_equals($contracts[$id]['version'], $version)) {
            wp_send_json_error(['message'=>'O contrato mudou. Atualize a pagina antes de aceitar.'], 409);
        }
        $accepted = (array) WC()->session->get(self::SESSION_KEY, []);
        if (($_POST['accepted'] ?? '') === '1') $accepted[$id] = ['version'=>$version, 'time'=>time()];
        else unset($accepted[$id]);
        WC()->session->set(self::SESSION_KEY, $accepted);
        wp_send_json_success();
    }

    public static function save_blocks($order): void
    {
        $contracts = self::order_contracts($order);
        $accepted = WC()->session ? (array) WC()->session->get(self::SESSION_KEY, []) : [];
        $times = [];
        foreach ($contracts as $id => $contract) {
            if (!self::valid_session_entry((array) ($accepted[$id] ?? []), $contract)) {
                throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException('press_lms_terms_required', 'Leia e aceite os termos de compra dos cursos antes de pagar. Se necessario, atualize a pagina.', 400);
            }
            $times[$id] = (int) $accepted[$id]['time'];
        }
        self::snapshot($order, $contracts, 'checkout-blocks', $times);
        if ($contracts) $order->save_meta_data();
    }

    public static function clear_session(): void
    {
        if (function_exists('WC') && WC()->session) WC()->session->set(self::SESSION_KEY, []);
    }

    private static function pending_order_contracts($order): array
    {
        $contracts = self::order_contracts($order);
        // A payment retry keeps the contract already attached to that order.
        foreach ((array) $order->get_meta(self::ORDER_META, true) as $record) {
            if (is_array($record) && !empty($record['version'])) unset($contracts[(int) ($record['course_id'] ?? 0)]);
        }
        return $contracts;
    }

    public static function render_order_pay(): void
    {
        $order = wc_get_order(absint(get_query_var('order-pay')));
        if ($order) self::render_fields(self::pending_order_contracts($order));
    }

    public static function save_order_pay($order): void
    {
        $contracts = self::pending_order_contracts($order);
        if (self::missing_acceptances($contracts, self::submitted())) {
            // This hook runs outside WooCommerce's exception handler; notices stop payment.
            wc_add_notice('Leia e aceite os termos de compra antes de pagar.', 'error');
            return;
        }
        self::snapshot($order, $contracts, 'order-pay');
        if ($contracts) $order->save_meta_data();
    }

    private static function render_record($order, bool $admin): void
    {
        foreach ((array) $order->get_meta(self::ORDER_META, true) as $record) {
            if (!is_array($record) || empty($record['content']) || empty($record['version'])) continue;
            $date = new DateTimeImmutable($record['accepted_at_utc'], new DateTimeZone('UTC'));
            echo '<section class="presslms-contracts"><h3>Aceite registrado</h3><p>' . esc_html($date->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i:s')) . ' (Brasilia)</p>';
            self::render_contract($record);
            echo '<p style="overflow-wrap:anywhere">Versao SHA-256: <code>' . esc_html($record['version']) . '</code></p>';
            if ($admin) echo '<p>Cliente #' . (int) ($record['customer_id'] ?? 0) . ' | ' . esc_html($record['email'] ?? '') . ' | IP: ' . esc_html($record['ip'] ?? '') . '</p>';
            echo '</section>';
        }
    }

    public static function render_admin_record($order): void
    {
        if (current_user_can('manage_woocommerce')) self::render_record($order, true);
    }

    public static function render_customer_record($order): void
    {
        if (get_current_user_id() > 0 && (int) $order->get_customer_id() === get_current_user_id()) self::render_record($order, false);
    }

    public static function assets(): void
    {
        if ((!function_exists('is_checkout') || !is_checkout()) && (!function_exists('is_account_page') || !is_account_page()) && !PRESS_LMS_Frontend::is_theme_compat_request()) return;
        wp_enqueue_style('presslms-terms', PRESS_LMS_URL . 'assets/css/terms.css', [], PRESS_LMS_VERSION);
        wp_enqueue_script('presslms-terms', PRESS_LMS_URL . 'assets/js/terms.js', [], PRESS_LMS_VERSION, true);
    }
}
