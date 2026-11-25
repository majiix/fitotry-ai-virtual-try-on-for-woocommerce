<?php
/**
 * Registry of supported image-capable AI providers and URL validation helpers.
 */

defined('ABSPATH') || exit;

if (!class_exists('Fito_Tryon_Providers')) {
class Fito_Tryon_Providers {
    /**
     * Return all 21 supported image-capable AI provider definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_registry(): array {
        $td = 'fitotry-ai-virtual-try-on-for-woocommerce';

        $providers = [
            'openrouter' => [
                'slug'                   => 'openrouter',
                'label'                  => __('OpenRouter (Multi-Model Aggregator)', $td),
                'short_label'            => 'OpenRouter',
                'client_type'            => 'openrouter',
                'base_url'               => 'https://openrouter.ai/api/v1',
                'key_url'                => 'https://openrouter.ai/keys',
                'key_hint'               => __('Get your API key at openrouter.ai/keys.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => false,
                'default_primary_model'  => 'google/gemini-2.5-flash-image',
                'curated_models'         => [],
            ],
            'openai' => [
                'slug'                   => 'openai',
                'label'                  => __('OpenAI Direct', $td),
                'short_label'            => 'OpenAI',
                'client_type'            => 'openai',
                'base_url'               => 'https://api.openai.com/v1',
                'key_url'                => 'https://platform.openai.com/api-keys',
                'key_hint'               => __('Get your API key at platform.openai.com/api-keys.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => false,
                'default_primary_model'  => 'gpt-image-2.5-flare',
                'curated_models'         => [],
            ],
            'gemini' => [
                'slug'                   => 'gemini',
                'label'                  => __('Google Gemini Direct', $td),
                'short_label'            => 'Google Gemini',
                'client_type'            => 'gemini',
                'base_url'               => 'https://generativelanguage.googleapis.com/v1beta',
                'key_url'                => 'https://aistudio.google.com/app/apikey',
                'key_hint'               => __('Get your API key at aistudio.google.com/app/apikey.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => false,
                'default_primary_model'  => 'gemini-2.5-flash-image',
                'curated_models'         => [],
            ],
            'avalai' => [
                'slug'                   => 'avalai',
                'label'                  => __('AvalAI', $td),
                'short_label'            => 'AvalAI',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://api.avalai.ir/v1',
                'key_url'                => 'https://avalai.ir/',
                'key_hint'               => __('Get your API key from avalai.ir.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => true,
                'default_primary_model'  => 'gemini-2.5-flash-image',
                'curated_models'         => [
                    ['id' => 'gemini-2.5-flash-image', 'name' => 'Gemini 2.5 Flash Image'],
                    ['id' => 'gpt-image-1', 'name' => 'OpenAI GPT Image 1'],
                    ['id' => 'dall-e-3', 'name' => 'DALL-E 3'],
                ],
            ],
            'gapgpt' => [
                'slug'                   => 'gapgpt',
                'label'                  => __('GapGPT', $td),
                'short_label'            => 'GapGPT',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://api.gapgpt.app/v1',
                'key_url'                => 'https://gapgpt.app/',
                'key_hint'               => __('Get your API key from gapgpt.app.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => true,
                'default_primary_model'  => 'gpt-image-1',
                'curated_models'         => [
                    ['id' => 'gpt-image-1', 'name' => 'GPT Image 1'],
                    ['id' => 'gemini-2.5-flash-image', 'name' => 'Gemini 2.5 Flash Image'],
                ],
            ],
            'nvidia' => [
                'slug'                   => 'nvidia',
                'label'                  => __('NVIDIA NIM', $td),
                'short_label'            => 'NVIDIA NIM',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://integrate.api.nvidia.com/v1',
                'key_url'                => 'https://build.nvidia.com/',
                'key_hint'               => __('Get your API key at build.nvidia.com.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => true,
                'default_primary_model'  => 'black-forest-labs/flux.1-dev',
                'curated_models'         => [
                    ['id' => 'black-forest-labs/flux.1-dev', 'name' => 'FLUX.1 Dev'],
                    ['id' => 'stabilityai/stable-diffusion-3.5-large', 'name' => 'Stable Diffusion 3.5 Large'],
                ],
            ],
            'mistral' => [
                'slug'                   => 'mistral',
                'label'                  => __('Mistral AI', $td),
                'short_label'            => 'Mistral AI',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://api.mistral.ai/v1',
                'key_url'                => 'https://console.mistral.ai/',
                'key_hint'               => __('Get your API key at console.mistral.ai.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => false,
                'default_primary_model'  => 'mistral-medium-latest',
                'curated_models'         => [
                    ['id' => 'mistral-medium-latest', 'name' => 'Mistral Medium Latest'],
                    ['id' => 'pixtral-large-latest', 'name' => 'Pixtral Large Latest'],
                ],
            ],
            'together' => [
                'slug'                   => 'together',
                'label'                  => __('Together AI', $td),
                'short_label'            => 'Together AI',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://api.together.xyz/v1',
                'key_url'                => 'https://api.together.xyz/',
                'key_hint'               => __('Get your API key at together.ai.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => false,
                'default_primary_model'  => 'black-forest-labs/FLUX.1-dev',
                'curated_models'         => [
                    ['id' => 'black-forest-labs/FLUX.1-dev', 'name' => 'FLUX.1 Dev'],
                    ['id' => 'black-forest-labs/FLUX.1-schnell-Free', 'name' => 'FLUX.1 Schnell'],
                    ['id' => 'black-forest-labs/FLUX.1-kontext-pro', 'name' => 'FLUX.1 Kontext Pro'],
                ],
            ],
            'fireworks' => [
                'slug'                   => 'fireworks',
                'label'                  => __('Fireworks AI', $td),
                'short_label'            => 'Fireworks AI',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://api.fireworks.ai/inference/v1',
                'key_url'                => 'https://fireworks.ai/',
                'key_hint'               => __('Get your API key at fireworks.ai.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => false,
                'default_primary_model'  => 'accounts/fireworks/models/flux-1-dev-fp8',
                'curated_models'         => [
                    ['id' => 'accounts/fireworks/models/flux-1-dev-fp8', 'name' => 'FLUX.1 Dev FP8'],
                    ['id' => 'accounts/fireworks/models/flux-1-schnell-fp8', 'name' => 'FLUX.1 Schnell FP8'],
                ],
            ],
            'xai' => [
                'slug'                   => 'xai',
                'label'                  => __('xAI (Grok)', $td),
                'short_label'            => 'xAI (Grok)',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://api.x.ai/v1',
                'key_url'                => 'https://console.x.ai/',
                'key_hint'               => __('Get your API key at console.x.ai.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => false,
                'default_primary_model'  => 'grok-2-image-1212',
                'curated_models'         => [
                    ['id' => 'grok-2-image-1212', 'name' => 'Grok 2 Image'],
                ],
            ],
            'dashscope' => [
                'slug'                   => 'dashscope',
                'label'                  => __('Alibaba Cloud Model Studio (Qwen DashScope)', $td),
                'short_label'            => 'Qwen DashScope',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
                'key_url'                => 'https://www.alibabacloud.com/',
                'key_hint'               => __('Get your DashScope API key from Alibaba Cloud Model Studio.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => true,
                'default_primary_model'  => 'qwen-image-plus',
                'curated_models'         => [
                    ['id' => 'qwen-image-plus', 'name' => 'Qwen Image Plus'],
                    ['id' => 'wanx2.1-t2i-turbo', 'name' => 'Wanx 2.1 Turbo'],
                ],
            ],
            'cloudflare' => [
                'slug'                   => 'cloudflare',
                'label'                  => __('Cloudflare Workers AI', $td),
                'short_label'            => 'Cloudflare Workers AI',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://api.cloudflare.com/client/v4/accounts/{account_id}/ai/v1',
                'key_url'                => 'https://dash.cloudflare.com/',
                'key_hint'               => __('Enter your Cloudflare Account ID and Workers AI API Token.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => true,
                'allows_custom_base_url' => false,
                'default_primary_model'  => '@cf/black-forest-labs/flux-1-schnell',
                'curated_models'         => [
                    ['id' => '@cf/black-forest-labs/flux-1-schnell', 'name' => 'FLUX.1 Schnell (Workers AI)'],
                    ['id' => '@cf/stabilityai/stable-diffusion-xl-base-1.0', 'name' => 'Stable Diffusion XL 1.0'],
                ],
            ],
            'arvancloud' => [
                'slug'                   => 'arvancloud',
                'label'                  => __('ArvanCloud AI', $td),
                'short_label'            => 'ArvanCloud AI',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://api.arvancloudai.ir/v1',
                'key_url'                => 'https://www.arvancloud.ir/',
                'key_hint'               => __('Get your API key from arvancloud.ir.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => true,
                'default_primary_model'  => 'gpt-image-1',
                'curated_models'         => [],
            ],
            'metisai' => [
                'slug'                   => 'metisai',
                'label'                  => __('MetisAI', $td),
                'short_label'            => 'MetisAI',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://api.metisai.ir/api/v1',
                'key_url'                => 'https://metisai.ir/',
                'key_hint'               => __('Get your API key from metisai.ir.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => true,
                'default_primary_model'  => 'gpt-image-1',
                'curated_models'         => [],
            ],
            'liara' => [
                'slug'                   => 'liara',
                'label'                  => __('Liara AI', $td),
                'short_label'            => 'Liara AI',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://ai.liara.ir/api/v1',
                'key_url'                => 'https://liara.ir/',
                'key_hint'               => __('Get your API key from liara.ir.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => true,
                'default_primary_model'  => 'google/gemini-2.5-flash-image',
                'curated_models'         => [],
            ],
            'heroai' => [
                'slug'                   => 'heroai',
                'label'                  => __('HeroAI', $td),
                'short_label'            => 'HeroAI',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://api.heroai.ir/v1',
                'key_url'                => 'https://heroai.ir/',
                'key_hint'               => __('Get your API key from heroai.ir.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => true,
                'default_primary_model'  => 'gpt-image-1',
                'curated_models'         => [],
            ],
            'talkbot' => [
                'slug'                   => 'talkbot',
                'label'                  => __('TalkBot', $td),
                'short_label'            => 'TalkBot',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://api.talkbot.ir/v1',
                'key_url'                => 'https://talkbot.ir/',
                'key_hint'               => __('Get your API key from talkbot.ir.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => true,
                'default_primary_model'  => 'gpt-image-1',
                'curated_models'         => [],
            ],
            'cupai' => [
                'slug'                   => 'cupai',
                'label'                  => __('CupAI', $td),
                'short_label'            => 'CupAI',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://api.cupai.ir/v1',
                'key_url'                => 'https://cupai.ir/',
                'key_hint'               => __('Get your API key from cupai.ir.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => true,
                'default_primary_model'  => 'gpt-image-1',
                'curated_models'         => [],
            ],
            'hooshang' => [
                'slug'                   => 'hooshang',
                'label'                  => __('Hooshang', $td),
                'short_label'            => 'Hooshang',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://hushang.cloud/v1',
                'key_url'                => 'https://hushang.cloud/',
                'key_hint'               => __('Get your API key from hushang.cloud.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => true,
                'default_primary_model'  => 'gpt-image-1',
                'curated_models'         => [],
            ],
            'hormouz' => [
                'slug'                   => 'hormouz',
                'label'                  => __('Hormouz', $td),
                'short_label'            => 'Hormouz',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'https://api.hormouz.net/v1',
                'key_url'                => 'https://api.hormouz.net/',
                'key_hint'               => __('Get your API key from api.hormouz.net.', $td),
                'requires_api_key'       => true,
                'requires_account_id'    => false,
                'allows_custom_base_url' => true,
                'default_primary_model'  => 'gpt-image-1',
                'curated_models'         => [],
            ],
            'custom' => [
                'slug'                   => 'custom',
                'label'                  => __('Custom OpenAI-Compatible Endpoint / Self-Hosted Ollama', $td),
                'short_label'            => 'Custom / Ollama',
                'client_type'            => 'openai_compatible',
                'base_url'               => 'http://localhost:11434/v1',
                'key_url'                => '',
                'key_hint'               => __('Enter an HTTPS OpenAI-compatible Base URL (or http://localhost:11434/v1 for local Ollama). API key is optional for local Ollama.', $td),
                'requires_api_key'       => false,
                'requires_account_id'    => false,
                'allows_custom_base_url' => true,
                'default_primary_model'  => '',
                'curated_models'         => [],
            ],
        ];

        return (array) apply_filters('fito_tryon_provider_registry', $providers);
    }

    /**
     * Get all registered provider slugs.
     *
     * @return array<int, string>
     */
    public static function get_slugs(): array {
        return array_keys(self::get_registry());
    }

    /**
     * Get map of provider slug => display label.
     *
     * @return array<string, string>
     */
    public static function get_labels(): array {
        $labels = [];
        foreach (self::get_registry() as $slug => $meta) {
            $labels[$slug] = (string) ($meta['label'] ?? ucfirst($slug));
        }
        return $labels;
    }

    /**
     * Retrieve a single provider definition by slug.
     *
     * @param string $slug Provider slug.
     * @return array<string, mixed>|null
     */
    public static function get_provider(string $slug): ?array {
        $registry = self::get_registry();
        return $registry[$slug] ?? null;
    }

    /**
     * Validate a custom or overridden API Base URL.
     * Enforces HTTPS for remote endpoints and permits HTTP only for localhost/127.0.0.1/::1 (Ollama).
     *
     * @param string $url Raw URL input.
     * @return string|WP_Error Normalized URL without trailing slash, or WP_Error on invalid URL.
     */
    public static function validate_base_url(string $url) {
        $td  = 'fitotry-ai-virtual-try-on-for-woocommerce';
        $url = trim($url);
        if (empty($url)) {
            return new WP_Error('fito_invalid_base_url', __('Base URL cannot be empty.', $td));
        }

        $parsed = wp_parse_url($url);
        if (!is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
            return new WP_Error('fito_invalid_base_url', __('Please enter a valid Base URL including http:// or https://.', $td));
        }

        $scheme = strtolower($parsed['scheme']);
        $host   = strtolower(trim($parsed['host'], '[]'));

        if ($host === '169.254.169.254' || $host === 'metadata.google.internal') {
            return new WP_Error('fito_invalid_base_url', __('Cloud metadata endpoints are not allowed.', $td));
        }

        $is_local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($scheme === 'http' && !$is_local) {
            return new WP_Error('fito_invalid_base_url', __('Remote endpoints must use HTTPS. HTTP is only permitted for localhost Ollama.', $td));
        }
        if (!in_array($scheme, ['http', 'https'], true)) {
            return new WP_Error('fito_invalid_base_url', __('Unsupported URL scheme. Use https:// (or http://localhost for Ollama).', $td));
        }

        return untrailingslashit(esc_url_raw($url));
    }
}
}
