<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Rest;

use flavor\SchemaMarkupGenerator\Schema\SchemaRenderer;
use flavor\SchemaMarkupGenerator\Schema\SchemaValidator;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Schema REST Endpoint
 *
 * REST API endpoints for schema data.
 *
 * @package flavor\SchemaMarkupGenerator\Rest
 * @author  Michele Marri <info@metodo.dev>
 */
class SchemaEndpoint
{
    /**
     * API namespace
     */
    private const NAMESPACE = 'smg/v1';

    private SchemaRenderer $schemaRenderer;

    public function __construct(SchemaRenderer $schemaRenderer)
    {
        $this->schemaRenderer = $schemaRenderer;
    }

    /**
     * Register REST routes
     */
    public function register(): void
    {
        // Get schema for a post
        register_rest_route(self::NAMESPACE, '/schema/(?P<post_id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getPostSchema'],
                'permission_callback' => [$this, 'checkReadPermission'],
                'args' => [
                    'post_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'description' => __('The post ID', 'schema-markup-generator'),
                    ],
                ],
            ],
        ]);

        // Validate schema
        register_rest_route(self::NAMESPACE, '/validate', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'validateSchema'],
                'permission_callback' => [$this, 'checkEditPermission'],
                'args' => [
                    'schema' => [
                        'required' => true,
                        'type' => 'object',
                        'description' => __('The schema data to validate', 'schema-markup-generator'),
                    ],
                ],
            ],
        ]);

        // Get plugin settings (admin only)
        register_rest_route(self::NAMESPACE, '/settings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getSettings'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateSettings'],
                'permission_callback' => [$this, 'checkAdminPermission'],
                'args' => [
                    'settings' => [
                        'required' => true,
                        'type' => 'object',
                    ],
                ],
            ],
        ]);

        // Get post type mappings
        register_rest_route(self::NAMESPACE, '/mappings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getMappings'],
                'permission_callback' => [$this, 'checkEditPermission'],
            ],
        ]);

        // Refresh schema cache
        register_rest_route(self::NAMESPACE, '/cache/clear', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'clearCache'],
                'permission_callback' => [$this, 'checkAdminPermission'],
                'args' => [
                    'post_id' => [
                        'required' => false,
                        'type' => 'integer',
                        'description' => __('Clear cache for specific post', 'schema-markup-generator'),
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get schema for a post
     */
    public function getPostSchema(WP_REST_Request $request): WP_REST_Response
    {
        $postId = (int) $request->get_param('post_id');
        $post = get_post($postId);

        if (!$post) {
            return new WP_REST_Response([
                'error' => __('Post not found', 'schema-markup-generator'),
            ], 404);
        }

        $schemas = $this->schemaRenderer->getPostSchemas($post);

        return new WP_REST_Response([
            'post_id' => $postId,
            'post_type' => $post->post_type,
            'schemas' => $schemas,
            'json_ld' => $this->schemaRenderer->getJsonForPost($postId),
        ]);
    }

    /**
     * Validate schema data
     */
    public function validateSchema(WP_REST_Request $request): WP_REST_Response
    {
        $schemaData = $request->get_param('schema');
        $validator = new SchemaValidator();

        if (isset($schemaData['@graph'])) {
            $result = $validator->validateGraph($schemaData['@graph']);
        } else {
            $result = $validator->validate($schemaData);
        }

        return new WP_REST_Response([
            'valid' => $result['valid'],
            'errors' => $result['errors'],
            'warnings' => $result['warnings'] ?? [],
        ]);
    }

    /**
     * Get plugin settings
     */
    public function getSettings(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'settings' => get_option('smg_settings', []),
            'post_type_mappings' => get_option('smg_post_type_mappings', []),
            'field_mappings' => get_option('smg_field_mappings', []),
        ]);
    }

    /**
     * Update plugin settings
     */
    public function updateSettings(WP_REST_Request $request): WP_REST_Response
    {
        $settings = $request->get_param('settings');

        if (isset($settings['settings'])) {
            update_option('smg_settings', $settings['settings']);
        }

        if (isset($settings['post_type_mappings'])) {
            update_option('smg_post_type_mappings', $settings['post_type_mappings']);
        }

        if (isset($settings['field_mappings'])) {
            update_option('smg_field_mappings', $settings['field_mappings']);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Settings updated', 'schema-markup-generator'),
        ]);
    }

    /**
     * Get post type mappings
     */
    public function getMappings(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'post_type_mappings' => get_option('smg_post_type_mappings', []),
            'field_mappings' => get_option('smg_field_mappings', []),
        ]);
    }

    /**
     * Clear schema cache
     */
    public function clearCache(WP_REST_Request $request): WP_REST_Response
    {
        $postId = $request->get_param('post_id');

        if ($postId) {
            $this->schemaRenderer->clearCache((int) $postId);
            $message = sprintf(__('Cache cleared for post %d', 'schema-markup-generator'), $postId);
        } else {
            // Clear all cache
            global $wpdb;
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $wpdb->esc_like('_transient_smg_') . '%'
                )
            );
            $message = __('All schema cache cleared', 'schema-markup-generator');
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * Check read permission
     */
    public function checkReadPermission(): bool
    {
        return true; // Public access
    }

    /**
     * Check edit permission
     */
    public function checkEditPermission(): bool
    {
        return current_user_can('edit_posts');
    }

    /**
     * Check admin permission
     */
    public function checkAdminPermission(): bool
    {
        return current_user_can('manage_options');
    }
}

