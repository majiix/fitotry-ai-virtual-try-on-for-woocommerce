<?php
/**
 * Google Gemini direct API integration.
 */

defined('ABSPATH') || exit;

class Fito_Tryon_Gemini {
    public const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';
    public const CACHE_KEY_MODELS = 'fito_tryon_gemini_models_cache';

    public function get_api_key(): string {
        return trim((string) get_option('fito_tryon_gemini_api_key', ''));
    }

    public function get_primary_model(): string {
        return (string) get_option('fito_tryon_gemini_primary_model', 'gemini-2.5-flash-image');
    }

    public function get_fallback_model(): string {
        return (string) get_option('fito_tryon_gemini_fallback_model', 'gemini-3.1-flash-image');
    }

    public function get_timeout(): int {
        return (int) get_option('fito_tryon_timeout_seconds', 60);
    }

    public function get_curated_models(): array {
        return [
            [
                'id'         => 'gemini-2.5-flash-image',
                'name'       => 'Google: Nano Banana (Gemini 2.5 Flash Image)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ],
            [
                'id'         => 'gemini-3.1-flash-image',
                'name'       => 'Google: Nano Banana 2 (Gemini 3.1 Flash Image)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ],
            [
                'id'         => 'gemini-3.1-flash-image-preview',
                'name'       => 'Google: Nano Banana 2 (Gemini 3.1 Flash Image Preview)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ],
            [
                'id'         => 'gemini-3.1-flash-lite-image',
                'name'       => 'Google: Nano Banana 2 Lite (Gemini 3.1 Flash Lite Image)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ],
            [
                'id'         => 'gemini-3-pro-image',
                'name'       => 'Google: Nano Banana Pro (Gemini 3 Pro Image)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ],
            [
                'id'         => 'gemini-3-pro-image-preview',
                'name'       => 'Google: Nano Banana Pro (Gemini 3 Pro Image Preview)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ],
            [
                'id'         => 'imagen-3.0-generate-002',
                'name'       => 'Google: Imagen 3.0 Generate (Legacy)',
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
                'message' => __('Google Gemini API key is empty.', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ];
        }

        $url = self::API_BASE . '/models';
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type'   => 'application/json',
                'x-goog-api-key' => $key,
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

        if ($status === 200 && isset($body['models'])) {
            $count = count($body['models']);
            return [
                'success' => true,
                // translators: %d: Number of available AI models
                'message' => sprintf(__('Connection verified! Google Gemini API connected (%d models available).', 'fitotry-ai-virtual-try-on-for-woocommerce'), $count),
            ];
        }

        $err_msg = $body['error']['message'] ?? __('Failed to connect to Google Gemini API.', 'fitotry-ai-virtual-try-on-for-woocommerce');
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

        $key = $api_key !== null ? trim($api_key) : $this->get_api_key();
        if (empty($key)) {
            return $this->get_curated_models();
        }

        $url = self::API_BASE . '/models';
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type'   => 'application/json',
                'x-goog-api-key' => $key,
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return $this->get_curated_models();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['models']) || !is_array($body['models'])) {
            return $this->get_curated_models();
        }

        $models = [];
        $curated_ids = array_column($this->get_curated_models(), 'id');

        foreach ($body['models'] as $m) {
            $raw_name = $m['name'] ?? '';
            $id = str_replace('models/', '', $raw_name);

            $is_image = false;
            if (isset($m['supportedGenerationMethods']) && is_array($m['supportedGenerationMethods'])) {
                if (in_array('imageGeneration', $m['supportedGenerationMethods'], true) ||
                    in_array('generateImages', $m['supportedGenerationMethods'], true) ||
                    in_array('predict', $m['supportedGenerationMethods'], true)) {
                    $is_image = true;
                }
            }

            if (str_contains(strtolower($id), 'image') || str_contains(strtolower($id), 'imagen')) {
                $is_image = true;
            }

            if (in_array($id, $curated_ids, true)) {
                $is_image = true;
            }

            if ($is_image) {
                $display_name = $m['displayName'] ?? $id;
                $models[] = [
                    'id'         => $id,
                    'name'       => 'Google: ' . $display_name,
                    'type'       => 'image_gen',
                    'type_label' => __('Image Generation', 'fitotry-ai-virtual-try-on-for-woocommerce'),
                ];
            }
        }

        if (empty($models)) {
            return $this->get_curated_models();
        }

        // Merge curated on top if not in API list
        $found_ids = array_column($models, 'id');
        foreach ($this->get_curated_models() as $cur) {
            if (!in_array($cur['id'], $found_ids, true)) {
                $models[] = $cur;
            }
        }

        set_transient(self::CACHE_KEY_MODELS, $models, DAY_IN_SECONDS);
        return $models;
    }

    public function generate_tryon(array $params): array {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('Google Gemini API key is missing. Please configure it in settings.', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ];
        }

        $model = $this->get_primary_model();
        $res = $this->call_gemini($model, $params, $api_key);

        if (defined('FITO_TRYON_IS_PRO') && FITO_TRYON_IS_PRO && !$res['success'] && !empty($this->get_fallback_model()) && $this->get_fallback_model() !== $model) {
            $fallback_model = $this->get_fallback_model();
            $res = $this->call_gemini($fallback_model, $params, $api_key, true);
        }

        return $res;
    }

    private function call_gemini(string $model, array $params, string $api_key, bool $is_fallback = false): array {
        $start_time = microtime(true);
        $prompt = apply_filters('fito_tryon_build_category_prompt', '', $params);

        $user_b64 = Fito_Tryon_Storage::file_to_base64($params['user_image_path']);
        $prod_b64 = Fito_Tryon_Storage::resolve_to_base64($params['product_image_url'] ?? '', $params['product_image_id'] ?? 0);

        if (empty($user_b64) || empty($prod_b64)) {
            return [
                'success' => false,
                'message' => __('Failed to prepare images for Google Gemini API.', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ];
        }

        $parts = [];
        $parts[] = ['text' => $prompt];

        $user_data = explode(',', $user_b64);
        $user_mime = 'image/jpeg';
        if (preg_match('/^data:([^;]+);base64/', $user_data[0], $m)) {
            $user_mime = $m[1];
        }
        $parts[] = [
            'inline_data' => [
                'mime_type' => $user_mime,
                'data'      => $user_data[1] ?? $user_data[0],
            ],
        ];

        $prod_data = explode(',', $prod_b64);
        $prod_mime = 'image/jpeg';
        if (preg_match('/^data:([^;]+);base64/', $prod_data[0], $m)) {
            $prod_mime = $m[1];
        }
        $parts[] = [
            'inline_data' => [
                'mime_type' => $prod_mime,
                'data'      => $prod_data[1] ?? $prod_data[0],
            ],
        ];

        $url = self::API_BASE . '/models/' . urlencode($model) . ':generateContent';

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'responseModalities' => ['IMAGE', 'TEXT'],
            ],
        ];

        $response = wp_remote_post($url, [
            'timeout' => $this->get_timeout(),
            'headers' => [
                'Content-Type'   => 'application/json',
                'x-goog-api-key' => $api_key,
            ],
            'body'    => wp_json_encode($payload),
        ]);

        $duration = round(microtime(true) - $start_time, 2);

        if (is_wp_error($response)) {
            return [
                'success'       => false,
                'model'         => $model,
                'duration'      => $duration,
                'prompt'        => $prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response(['wp_error' => $response->get_error_message()]),
                // translators: %s: Error message
                'message'       => sprintf(__('Gemini API request failed: %s', 'fitotry-ai-virtual-try-on-for-woocommerce'), $response->get_error_message()),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);

        if ($code !== 200) {
            $err_msg = $body['error']['message'] ?? ('HTTP ' . $code);
            return [
                'success'       => false,
                'model'         => $model,
                'duration'      => $duration,
                'prompt'        => $prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response($body ?: ['raw' => $raw_body]),
                // translators: %s: Error message
                'message'       => sprintf(__('Gemini Error (%s)', 'fitotry-ai-virtual-try-on-for-woocommerce'), $err_msg),
            ];
        }

        $img_data = $this->extract_image_from_response($body);
        if (empty($img_data)) {
            return [
                'success'       => false,
                'model'         => $model,
                'duration'      => $duration,
                'prompt'        => $prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response($body),
                'message'       => __('Google Gemini response did not contain an image.', 'fitotry-ai-virtual-try-on-for-woocommerce'),
            ];
        }

        return [
            'success'           => true,
            'model'             => $model,
            'result_image_data' => $img_data,
            'duration'          => $duration,
            'cost'              => 0,
            'prompt'            => $prompt,
            'response_text'     => Fito_Tryon_OpenRouter::sanitize_json_response($body),
        ];
    }

    private function extract_image_from_response(array $body): ?string {
        if (!isset($body['candidates'][0]['content']['parts'])) {
            return null;
        }

        foreach ($body['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['inlineData']['data'])) {
                return (string) $part['inlineData']['data'];
            }
            if (isset($part['inline_data']['data'])) {
                return (string) $part['inline_data']['data'];
            }
        }

        return null;
    }
}
