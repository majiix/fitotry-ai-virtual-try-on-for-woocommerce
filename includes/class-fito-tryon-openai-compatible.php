<?php
/**
 * Unified OpenAI-compatible AI provider client (regional gateways, cloud inference, and self-hosted Ollama).
 */

defined('ABSPATH') || exit;

if (!class_exists('Fito_Tryon_OpenAI_Compatible')) {
class Fito_Tryon_OpenAI_Compatible {
    public const OPTION_KEY = 'fito_tryon_provider_configs';

    private string $td = 'fitotry-ai-virtual-try-on-for-woocommerce';

    /**
     * Retrieve saved configuration for a provider slug.
     *
     * @param string $provider Provider slug.
     * @return array<string, string>
     */
    public function get_config(string $provider): array {
        $all = (array) get_option(self::OPTION_KEY, []);
        $cfg = isset($all[$provider]) && is_array($all[$provider]) ? $all[$provider] : [];
        $meta = Fito_Tryon_Providers::get_provider($provider) ?? [];

        return [
            'api_key'        => trim((string) ($cfg['api_key'] ?? '')),
            'primary_model'  => trim((string) ($cfg['primary_model'] ?? ($meta['default_primary_model'] ?? ''))),
            'fallback_model' => trim((string) ($cfg['fallback_model'] ?? '')),
            'base_url'       => trim((string) ($cfg['base_url'] ?? '')),
            'account_id'     => trim((string) ($cfg['account_id'] ?? '')),
        ];
    }

    public function get_primary_model(string $provider): string {
        $cfg = $this->get_config($provider);
        return $cfg['primary_model'];
    }

    public function get_timeout(): int {
        return (int) get_option('fito_tryon_timeout_seconds', 60);
    }

    /**
     * Resolve effective API Base URL for a provider, substituting Account ID or validating custom URL.
     *
     * @param string               $provider Provider slug.
     * @param array<string, mixed> $extra    Optional overrides (`base_url`, `account_id`).
     * @return string|WP_Error
     */
    public function resolve_base_url(string $provider, array $extra = []) {
        $meta = Fito_Tryon_Providers::get_provider($provider);
        if (!$meta) {
            return new WP_Error('fito_unknown_provider', __('Unknown AI provider.', $this->td));
        }

        $cfg        = $this->get_config($provider);
        $base_url   = trim((string) ($extra['base_url'] ?? $cfg['base_url'] ?? ''));
        $account_id = trim((string) ($extra['account_id'] ?? $cfg['account_id'] ?? ''));

        if (!empty($meta['requires_account_id'])) {
            if (empty($account_id)) {
                return new WP_Error('fito_missing_account_id', __('Account ID is required for this provider.', $this->td));
            }
            $resolved = str_replace('{account_id}', rawurlencode($account_id), (string) $meta['base_url']);
            return Fito_Tryon_Providers::validate_base_url($resolved);
        }

        if (!empty($base_url) && !empty($meta['allows_custom_base_url'])) {
            return Fito_Tryon_Providers::validate_base_url($base_url);
        }

        return Fito_Tryon_Providers::validate_base_url((string) $meta['base_url']);
    }

    /**
     * Build request headers for OpenAI-compatible endpoints.
     *
     * @param string $api_key Optional API key.
     * @return array<string, string>
     */
    private function build_headers(string $api_key): array {
        $headers = ['Content-Type' => 'application/json'];
        if (!empty($api_key)) {
            $headers['Authorization'] = 'Bearer ' . $api_key;
        }
        return $headers;
    }

    /**
     * Test connectivity by querying GET {base_url}/models.
     *
     * @param string               $provider Provider slug.
     * @param string|null          $api_key  Optional API key override.
     * @param array<string, mixed> $extra    Optional overrides (`base_url`, `account_id`).
     * @return array<string, mixed>
     */
    public function test_connection(string $provider, ?string $api_key = null, array $extra = []): array {
        $meta = Fito_Tryon_Providers::get_provider($provider);
        if (!$meta) {
            return ['success' => false, 'message' => __('Unsupported AI provider.', $this->td)];
        }

        $cfg = $this->get_config($provider);
        $key = $api_key !== null ? trim($api_key) : $cfg['api_key'];

        if (!empty($meta['requires_api_key']) && empty($key)) {
            return [
                'success' => false,
                // translators: %s: Provider name
                'message' => sprintf(__('%s API key is empty.', $this->td), $meta['short_label']),
            ];
        }

        $base_url = $this->resolve_base_url($provider, $extra);
        if (is_wp_error($base_url)) {
            return ['success' => false, 'message' => $base_url->get_error_message()];
        }

        $response = wp_remote_get($base_url . '/models', [
            'timeout' => 15,
            'headers' => $this->build_headers($key),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                // translators: %s: Error message
                'message' => sprintf(__('Connection failed: %s', $this->td), $response->get_error_message()),
            ];
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);

        if ($status >= 200 && $status < 300 && is_array($body)) {
            $list  = $body['data'] ?? ($body['models'] ?? []);
            $count = is_array($list) ? count($list) : 0;
            return [
                'success' => true,
                // translators: 1: Provider name, 2: Number of models
                'message' => sprintf(__('Connection verified! %1$s connected (%2$d models available).', $this->td), $meta['short_label'], $count),
            ];
        }

        $err_msg = $body['error']['message'] ?? ($body['message'] ?? sprintf(__('Failed to connect to %s (HTTP %d).', $this->td), $meta['short_label'], $status));
        return [
            'success' => false,
            // translators: %s: Error message
            'message' => sprintf(__('API Error: %s', $this->td), $err_msg),
        ];
    }

    /**
     * Fetch live models from {base_url}/models and merge with curated provider defaults.
     *
     * @param string               $provider Provider slug.
     * @param string|null          $api_key  Optional API key override.
     * @param bool                 $force    Force cache refresh.
     * @param array<string, mixed> $extra    Optional overrides (`base_url`, `account_id`).
     * @return array<int, array<string, mixed>>
     */
    public function fetch_live_models(string $provider, ?string $api_key = null, bool $force = false, array $extra = []): array {
        $meta = Fito_Tryon_Providers::get_provider($provider);
        if (!$meta) {
            return [];
        }

        $cache_key = 'fito_tryon_models_' . sanitize_key($provider);
        if (!$force) {
            $cached = get_transient($cache_key);
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }

        $cfg      = $this->get_config($provider);
        $key      = $api_key !== null ? trim($api_key) : $cfg['api_key'];
        $models   = [];
        $seen     = [];
        $curated  = (array) ($meta['curated_models'] ?? []);

        foreach ($curated as $c) {
            if (!empty($c['id'])) {
                $seen[$c['id']] = true;
                $models[] = [
                    'id'         => $c['id'],
                    'name'       => $c['name'] ?? $c['id'],
                    'type'       => 'image_gen',
                    'type_label' => __('Image Generation', $this->td),
                ];
            }
        }

        $base_url = $this->resolve_base_url($provider, $extra);
        if (!is_wp_error($base_url) && (empty($meta['requires_api_key']) || !empty($key))) {
            $response = wp_remote_get($base_url . '/models', [
                'timeout' => 15,
                'headers' => $this->build_headers($key),
            ]);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $raw_list = $body['data'] ?? ($body['models'] ?? []);
                if (is_array($raw_list)) {
                    foreach ($raw_list as $m) {
                        $id = $m['id'] ?? ($m['name'] ?? '');
                        if (empty($id) || isset($seen[$id])) {
                            continue;
                        }
                        $lower = strtolower($id);
                        if (str_contains($lower, 'embed') || str_contains($lower, 'whisper') || str_contains($lower, 'rerank') || str_contains($lower, 'moderation') || str_contains($lower, 'tts')) {
                            continue;
                        }
                        $seen[$id] = true;
                        $models[] = [
                            'id'         => $id,
                            'name'       => $m['name'] ?? $id,
                            'type'       => 'image_gen',
                            'type_label' => __('Image Generation', $this->td),
                        ];
                    }
                }
            }
        }

        usort($models, function ($a, $b) {
            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        if (!empty($models)) {
            set_transient($cache_key, $models, DAY_IN_SECONDS);
        }

        return $models;
    }

    /**
     * Execute virtual try-on generation against an OpenAI-compatible provider.
     *
     * @param string               $provider Provider slug.
     * @param array<string, mixed> $params   Try-on parameters.
     * @return array<string, mixed>
     */
    public function generate_tryon(string $provider, array $params): array {
        $meta = Fito_Tryon_Providers::get_provider($provider);
        if (!$meta) {
            return ['success' => false, 'message' => __('Unsupported AI provider.', $this->td)];
        }

        $cfg = $this->get_config($provider);
        if (!empty($meta['requires_api_key']) && empty($cfg['api_key'])) {
            return [
                'success' => false,
                // translators: %s: Provider name
                'message' => sprintf(__('%s API key is missing. Please configure it in settings.', $this->td), $meta['short_label']),
            ];
        }

        $base_url = $this->resolve_base_url($provider);
        if (is_wp_error($base_url)) {
            return ['success' => false, 'message' => $base_url->get_error_message()];
        }

        $primary_model = $cfg['primary_model'];
        if (empty($primary_model)) {
            return [
                'success' => false,
                // translators: %s: Provider name
                'message' => sprintf(__('No primary model selected for %s.', $this->td), $meta['short_label']),
            ];
        }

        $start_time = microtime(true);
        $result     = $this->call_provider($provider, $base_url, $primary_model, $params, $cfg['api_key'], false);

        $is_pro = defined('FITO_TRYON_IS_PRO') && FITO_TRYON_IS_PRO;
        if ($is_pro && empty($result['success']) && !empty($cfg['fallback_model']) && $cfg['fallback_model'] !== $primary_model) {
            $result = $this->call_provider($provider, $base_url, $cfg['fallback_model'], $params, $cfg['api_key'], true);
        }

        $result['duration'] = round(microtime(true) - $start_time, 2);
        return $result;
    }

    /**
     * Call OpenAI-compatible /chat/completions first, falling back to /images/generations if needed.
     *
     * @param string               $provider    Provider slug.
     * @param string               $base_url    Resolved base URL.
     * @param string               $model       Model identifier.
     * @param array<string, mixed> $params      Try-on parameters.
     * @param string               $api_key     API key (may be empty for local Ollama).
     * @param bool                 $is_fallback Whether this is a fallback attempt.
     * @return array<string, mixed>
     */
    private function call_provider(string $provider, string $base_url, string $model, array $params, string $api_key, bool $is_fallback): array {
        $prompt            = (string) apply_filters('fito_tryon_build_category_prompt', '', $params);
        $user_image_b64    = Fito_Tryon_Storage::file_to_base64($params['user_image_path'] ?? '');
        $product_image_url = $params['product_image_url'] ?? '';
        $product_image_id  = (int) ($params['product_image_id'] ?? 0);
        $product_image_b64 = Fito_Tryon_Storage::resolve_to_base64($product_image_url, $product_image_id);

        if (empty($user_image_b64)) {
            return [
                'success'       => false,
                'model'         => $model,
                'is_fallback'   => $is_fallback,
                'prompt'        => $prompt,
                'response_text' => $this->sanitize_json(['error' => __('Missing or unreadable user photo.', $this->td)]),
                'message'       => __('Missing or unreadable user photo.', $this->td),
            ];
        }

        $system_instruction = (string) get_option('fito_tryon_system_prompt', '');
        if (empty($system_instruction)) {
            $system_instruction = 'You are an expert AI virtual fitting assistant. Generate realistic, high-fidelity try-on images preserving natural contours, identity, and studio lighting.';
        }

        $messages = [
            ['role' => 'system', 'content' => $system_instruction],
            [
                'role'    => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => $user_image_b64]],
                ],
            ],
        ];

        $prod_ref = !empty($product_image_b64) ? $product_image_b64 : $product_image_url;
        if (!empty($prod_ref)) {
            $messages[1]['content'][] = [
                'type'      => 'image_url',
                'image_url' => ['url' => $prod_ref],
            ];
        }

        $payload = [
            'model'      => $model,
            'messages'   => $messages,
            'max_tokens' => 1200,
        ];

        $response = wp_remote_post($base_url . '/chat/completions', [
            'timeout' => $this->get_timeout(),
            'headers' => $this->build_headers($api_key),
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return [
                'success'       => false,
                'model'         => $model,
                'is_fallback'   => $is_fallback,
                'prompt'        => $prompt,
                'response_text' => $this->sanitize_json(['error' => $response->get_error_message()]),
                'message'       => $response->get_error_message(),
            ];
        }

        $code     = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body     = json_decode($raw_body, true);

        if ($code === 200 && isset($body['choices'][0]['message'])) {
            $choice = $body['choices'][0]['message'];
            $img    = $this->extract_image($choice);
            if (!empty($img)) {
                return [
                    'success'           => true,
                    'model'             => $model,
                    'is_fallback'       => $is_fallback,
                    'result_image_data' => $img,
                    'prompt'            => $prompt,
                    'response_text'     => $this->sanitize_json($body),
                    'prompt_tokens'     => (int) ($body['usage']['prompt_tokens'] ?? 0),
                    'completion_tokens' => (int) ($body['usage']['completion_tokens'] ?? 0),
                    'cost'              => 0.0,
                ];
            }
        }

        // Fallback to /images/generations for diffusion-only endpoints (404/400/405/422 or text-only chat response).
        if (in_array($code, [200, 400, 404, 405, 422], true)) {
            $img_res = $this->call_images_generations($base_url, $model, $system_instruction . "\n\nTask: " . $prompt, $api_key, $is_fallback);
            if (!empty($img_res['success'])) {
                return $img_res;
            }
        }

        $err_msg = $body['error']['message'] ?? sprintf(__('AI provider error (HTTP %d)', $this->td), $code);
        return [
            'success'       => false,
            'model'         => $model,
            'is_fallback'   => $is_fallback,
            'prompt'        => $prompt,
            'response_text' => $this->sanitize_json(!empty($body) ? $body : ['error' => $raw_body, 'http_code' => $code]),
            'message'       => $err_msg,
        ];
    }

    /**
     * Call OpenAI-compatible /images/generations endpoint.
     */
    private function call_images_generations(string $base_url, string $model, string $full_prompt, string $api_key, bool $is_fallback): array {
        $payload = [
            'model'           => $model,
            'prompt'          => $full_prompt,
            'n'               => 1,
            'size'            => '1024x1024',
            'response_format' => 'b64_json',
        ];

        $response = wp_remote_post($base_url . '/images/generations', [
            'timeout' => $this->get_timeout(),
            'headers' => $this->build_headers($api_key),
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200 || !is_array($body)) {
            return ['success' => false, 'message' => $body['error']['message'] ?? __('Image generation failed.', $this->td)];
        }

        $b64 = $body['data'][0]['b64_json'] ?? '';
        if (!empty($b64)) {
            return [
                'success'           => true,
                'model'             => $model,
                'is_fallback'       => $is_fallback,
                'result_image_data' => 'data:image/png;base64,' . $b64,
                'prompt'            => $full_prompt,
                'response_text'     => $this->sanitize_json($body),
                'prompt_tokens'     => 0,
                'completion_tokens' => 0,
                'cost'              => 0.0,
            ];
        }

        $url = $body['data'][0]['url'] ?? '';
        if (!empty($url)) {
            return [
                'success'           => true,
                'model'             => $model,
                'is_fallback'       => $is_fallback,
                'result_image_data' => $url,
                'prompt'            => $full_prompt,
                'response_text'     => $this->sanitize_json($body),
                'prompt_tokens'     => 0,
                'completion_tokens' => 0,
                'cost'              => 0.0,
            ];
        }

        return ['success' => false, 'message' => __('No image data returned from provider.', $this->td)];
    }

    /**
     * Extract image data URI or URL from an OpenAI-compatible chat choice message.
     *
     * @param array<string, mixed> $choice Message array.
     * @return string
     */
    private function extract_image(array $choice): string {
        if (!empty($choice['images']) && is_array($choice['images'])) {
            foreach ($choice['images'] as $img) {
                if (is_string($img)) {
                    return $img;
                }
                if (!empty($img['image_url']['url'])) {
                    return (string) $img['image_url']['url'];
                }
                if (!empty($img['url'])) {
                    return (string) $img['url'];
                }
                if (!empty($img['b64_json'])) {
                    return 'data:image/png;base64,' . $img['b64_json'];
                }
            }
        }

        $content = $choice['content'] ?? '';
        if (is_array($content)) {
            foreach ($content as $part) {
                if (!empty($part['image_url']['url'])) {
                    return (string) $part['image_url']['url'];
                }
                if (!empty($part['b64_json'])) {
                    return 'data:image/png;base64,' . $part['b64_json'];
                }
            }
        }

        if (is_string($content) && preg_match('/(data:image\/[a-zA-Z0-9+.-]+;base64,[A-Za-z0-9+\/=]+)/', $content, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * Sanitize JSON payload for audit log storage.
     *
     * @param mixed $data Payload data.
     * @return string
     */
    private function sanitize_json($data): string {
        if (class_exists('Fito_Tryon_OpenRouter') && method_exists('Fito_Tryon_OpenRouter', 'sanitize_json_response')) {
            return Fito_Tryon_OpenRouter::sanitize_json_response($data);
        }
        return (string) wp_json_encode($data);
    }
}
}
