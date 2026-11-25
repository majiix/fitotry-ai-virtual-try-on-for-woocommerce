<?php
/**
 * OpenAI direct API integration.
 */

defined('ABSPATH') || exit;

class Fito_Tryon_OpenAI {
    public const API_BASE = 'https://api.openai.com/v1';
    public const CACHE_KEY_MODELS = 'fito_tryon_openai_models_cache';

    public function get_api_key(): string {
        return trim((string) get_option('fito_tryon_openai_api_key', ''));
    }

    public function get_primary_model(): string {
        return (string) get_option('fito_tryon_openai_primary_model', 'gpt-image-2.5-flare');
    }

    public function get_fallback_model(): string {
        return (string) get_option('fito_tryon_openai_fallback_model', 'dall-e-3');
    }

    public function get_timeout(): int {
        return (int) get_option('fito_tryon_timeout_seconds', 60);
    }

    public function get_curated_models(): array {
        return [
            [
                'id'         => 'gpt-image-2.5-flare',
                'name'       => 'OpenAI: GPT Image 2.5 Flare (Fast & High Quality)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ],
            [
                'id'         => 'gpt-image-2.5-sunburst',
                'name'       => 'OpenAI: GPT Image 2.5 Sunburst (Precision Editing)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ],
            [
                'id'         => 'gpt-image-2',
                'name'       => 'OpenAI: GPT Image 2',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ],
            [
                'id'         => 'gpt-image-1',
                'name'       => 'OpenAI: GPT Image 1 (Multi-Reference)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ],
            [
                'id'         => 'dall-e-3',
                'name'       => 'OpenAI: DALL-E 3 (High Detail)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ],
            [
                'id'         => 'dall-e-2',
                'name'       => 'OpenAI: DALL-E 2 (Legacy)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ],
        ];
    }

    public function test_connection(?string $api_key = null): array {
        $key = $api_key !== null ? trim($api_key) : $this->get_api_key();
        if (empty($key)) {
            return [
                'success' => false,
                'message' => __('OpenAI API key is empty.', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ];
        }

        $response = wp_remote_get(self::API_BASE . '/models', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                // translators: %s: Error message
                'message' => sprintf(__('Connection failed: %s', 'fitotry-ai-virtual-try-on-for-woocommerce'), $response->get_error_message()),
            ];
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status === 200 && isset($body['data'])) {
            $count = count($body['data']);
            return [
                'success' => true,
                // translators: %d: Number of available AI models
                'message' => sprintf(__('Connection verified! OpenAI API connected (%d models available).', 'fitotry-ai-virtual-try-on-for-woocommerce'), $count),
            ];
        }

        $err_msg = $body['error']['message'] ?? __('Failed to connect to OpenAI API.', 'fitotry-ai-virtual-try-on-for-woocommerce');
        return [
            'success' => false,
            // translators: %s: Error message
            'message' => sprintf(__('API Error: %s', 'fitotry-ai-virtual-try-on-for-woocommerce'), $err_msg),
        ];
    }

    public function fetch_live_models(?string $api_key = null, bool $force = false): array {
        if (!$force) {
            $cached = get_transient(self::CACHE_KEY_MODELS);
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }

        $models = $this->get_curated_models();
        $key = $api_key !== null ? trim($api_key) : $this->get_api_key();

        if (!empty($key)) {
            $response = wp_remote_get(self::API_BASE . '/models', [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type'  => 'application/json',
                ],
            ]);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $api_models = $body['data'] ?? [];
                if (is_array($api_models)) {
                    $seen = [];
                    foreach ($models as $m) {
                        $seen[$m['id']] = true;
                    }
                    foreach ($api_models as $item) {
                        $clean_id = $item['id'] ?? '';
                        $lower = strtolower($clean_id);

                        $is_image = str_contains($lower, 'image') || str_contains($lower, 'dall-e');
                        if (str_contains($clean_id, 'audio') || str_contains($clean_id, 'whisper') || str_contains($clean_id, 'embedding') || str_contains($clean_id, 'tts') || str_contains($clean_id, 'moderation')) {
                            $is_image = false;
                        }

                        if ($is_image && empty($seen[$clean_id])) {
                            $seen[$clean_id] = true;
                            $models[] = [
                                'id'         => $clean_id,
                                'name'       => 'OpenAI: ' . $clean_id,
                                'type'       => 'image_gen',
                                'type_label' => __('Image Generation', 'fitotry-ai-virtual-try-on-for-woocommerce'),
                            ];
                        }
                    }
                }
            }
        }

        usort($models, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        if (!empty($key)) {
            set_transient(self::CACHE_KEY_MODELS, $models, DAY_IN_SECONDS);
        }
        return $models;
    }

    public function generate_tryon(array $params): array {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('OpenAI API key is missing. Please configure it in settings.', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ];
        }

        $primary_model = $this->get_primary_model();
        $fallback_model = $this->get_fallback_model();

        $start_time = microtime(true);
        $result = $this->call_openai($primary_model, $params, $api_key, false);

        if (defined('FITO_TRYON_IS_PRO') && FITO_TRYON_IS_PRO && !$result['success'] && !empty($fallback_model) && $fallback_model !== $primary_model) {
            $result = $this->call_openai($fallback_model, $params, $api_key, true);
        }

        $result['duration'] = round(microtime(true) - $start_time, 2);
        return $result;
    }

    private function call_openai(string $model, array $params, string $api_key, bool $is_fallback): array {
        $prompt = (string) apply_filters('fito_tryon_build_category_prompt', '', $params);
        $user_image_b64 = Fito_Tryon_Storage::file_to_base64($params['user_image_path'] ?? '');
        $product_image_url = $params['product_image_url'] ?? '';
        $product_image_id = (int) ($params['product_image_id'] ?? 0);
        $product_image_b64 = Fito_Tryon_Storage::resolve_to_base64($product_image_url, $product_image_id);

        if (empty($user_image_b64)) {
            return [
                'success'       => false,
                'prompt'        => $prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response(['error' => __('Missing or unreadable user photo.', 'fitotry-ai-virtual-try-on-for-woocommerce')]),
                'message'       => __('Missing or unreadable user photo.', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ];
        }

        if (str_starts_with($model, 'dall-e')) {
            return $this->call_dalle($model, $prompt, $api_key, $is_fallback);
        }

        return $this->call_gpt_image($model, $prompt, $user_image_b64, $product_image_b64, $api_key, $is_fallback);
    }

    private function call_gpt_image(string $model, string $prompt, string $user_b64, string $prod_b64, string $api_key, bool $is_fallback): array {
        $url = self::API_BASE . '/images/generations';

        $system_instruction = get_option('fito_tryon_system_prompt', '');
        if (empty($system_instruction)) {
            $system_instruction = 'You are an expert AI virtual fitting assistant. Generate realistic, high-fidelity try-on images preserving natural contours, identity, and studio lighting.';
        }

        $full_prompt = $system_instruction . "\n\nTask: " . $prompt;

        $payload = [
            'model'           => $model,
            'prompt'          => $full_prompt,
            'n'               => 1,
            'size'            => '1024x1024',
            'response_format' => 'b64_json',
        ];

        $response = wp_remote_post($url, [
            'timeout' => $this->get_timeout(),
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return [
                'success'       => false,
                'model'         => $model,
                'is_fallback'   => $is_fallback,
                'prompt'        => $full_prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response(['error' => $response->get_error_message()]),
                'message'       => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);

        if ($code !== 200) {
            // translators: %d: HTTP status code
            $http_err = sprintf(__('OpenAI API error (HTTP %d)', 'fitotry-ai-virtual-try-on-for-woocommerce'), $code);
            $err = $body['error']['message'] ?? $http_err;
            return [
                'success'       => false,
                'model'         => $model,
                'is_fallback'   => $is_fallback,
                'prompt'        => $full_prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response(!empty($body) ? $body : ['error' => $raw_body, 'http_code' => $code]),
                'message'       => $err,
            ];
        }

        $b64 = $body['data'][0]['b64_json'] ?? '';
        if (empty($b64)) {
            $url_fallback = $body['data'][0]['url'] ?? '';
            if (!empty($url_fallback)) {
                return [
                    'success'           => true,
                    'model'             => $model,
                    'is_fallback'       => $is_fallback,
                    'result_image_data' => $url_fallback,
                    'prompt'            => $full_prompt,
                    'response_text'     => Fito_Tryon_OpenRouter::sanitize_json_response($body),
                    'prompt_tokens'     => 0,
                    'completion_tokens' => 0,
                    'cost'              => 0.04,
                ];
            }

            return [
                'success'       => false,
                'model'         => $model,
                'is_fallback'   => $is_fallback,
                'prompt'        => $full_prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response($body),
                'message'       => __('No image data returned from OpenAI API.', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ];
        }

        return [
            'success'           => true,
            'model'             => $model,
            'is_fallback'       => $is_fallback,
            'result_image_data' => $b64,
            'prompt'            => $full_prompt,
            'response_text'     => Fito_Tryon_OpenRouter::sanitize_json_response($body),
            'prompt_tokens'     => 0,
            'completion_tokens' => 0,
            'cost'              => 0.04,
        ];
    }

    private function call_dalle(string $model, string $prompt, string $api_key, bool $is_fallback): array {
        $url = self::API_BASE . '/images/generations';
        $payload = [
            'model'           => $model,
            'prompt'          => $prompt,
            'n'               => 1,
            'size'            => '1024x1024',
            'response_format' => 'b64_json',
        ];

        $response = wp_remote_post($url, [
            'timeout' => $this->get_timeout(),
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return [
                'success'       => false,
                'model'         => $model,
                'is_fallback'   => $is_fallback,
                'prompt'        => $prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response(['error' => $response->get_error_message()]),
                'message'       => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);

        if ($code !== 200) {
            $err = $body['error']['message'] ?? ('HTTP ' . $code);
            return [
                'success'       => false,
                'model'         => $model,
                'is_fallback'   => $is_fallback,
                'prompt'        => $prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response(!empty($body) ? $body : ['error' => $raw_body]),
                'message'       => $err,
            ];
        }

        $b64 = $body['data'][0]['b64_json'] ?? '';
        return [
            'success'           => !empty($b64),
            'model'             => $model,
            'is_fallback'       => $is_fallback,
            'result_image_data' => $b64,
            'prompt'            => $prompt,
            'response_text'     => Fito_Tryon_OpenRouter::sanitize_json_response($body),
            'prompt_tokens'     => 0,
            'completion_tokens' => 0,
            'cost'              => $model === 'dall-e-3' ? 0.04 : 0.02,
        ];
    }
}
