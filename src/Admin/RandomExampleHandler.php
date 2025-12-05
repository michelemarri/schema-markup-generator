<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin;

use Metodo\SchemaMarkupGenerator\Schema\SchemaRenderer;
use Metodo\SchemaMarkupGenerator\Schema\SchemaFactory;

/**
 * Random Example Handler
 *
 * Handles AJAX requests for generating schema examples from random posts.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin
 * @author  Michele Marri <plugins@metodo.dev>
 */
class RandomExampleHandler
{
    private SchemaRenderer $schemaRenderer;
    private SchemaFactory $schemaFactory;

    public function __construct(SchemaRenderer $schemaRenderer, SchemaFactory $schemaFactory)
    {
        $this->schemaRenderer = $schemaRenderer;
        $this->schemaFactory = $schemaFactory;
    }

    /**
     * Handle AJAX request for random schema example
     */
    public function handle(): void
    {
        check_ajax_referer('smg_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'schema-markup-generator')]);
        }

        $postType = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : '';
        $schemaType = isset($_POST['schema_type']) ? sanitize_text_field($_POST['schema_type']) : '';

        if (!$postType) {
            wp_send_json_error(['message' => __('Post type is required', 'schema-markup-generator')]);
        }

        // Get a random published post of this type
        $posts = get_posts([
            'post_type' => $postType,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'rand',
            'fields' => 'ids',
        ]);

        if (empty($posts)) {
            wp_send_json_error([
                'message' => sprintf(
                    __('No published %s found. Create and publish a post to see an example.', 'schema-markup-generator'),
                    $postType
                ),
            ]);
        }

        $postId = $posts[0];
        $post = get_post($postId);

        if (!$post) {
            wp_send_json_error(['message' => __('Post not found', 'schema-markup-generator')]);
        }

        // If a schema type is specified, use that; otherwise use the mapping
        if ($schemaType) {
            // Generate schema with the specified type
            $schema = $this->schemaFactory->create($schemaType);
            
            if (!$schema) {
                wp_send_json_error(['message' => __('Invalid schema type', 'schema-markup-generator')]);
            }

            // Get field mapping for this post type
            $fieldMappings = get_option('smg_field_mappings', []);
            $mapping = $fieldMappings[$postType] ?? [];

            $schemaData = $schema->build($post, $mapping);

            if (empty($schemaData)) {
                wp_send_json_error(['message' => __('Could not generate schema for this post', 'schema-markup-generator')]);
            }

            $json = wp_json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            // Use the standard renderer
            $json = $this->schemaRenderer->getJsonForPost($postId);
        }

        if (!$json || $json === '{}') {
            wp_send_json_error([
                'message' => __('No schema could be generated. Make sure a schema type is assigned to this post type.', 'schema-markup-generator'),
            ]);
        }

        wp_send_json_success([
            'json' => $json,
            'post_title' => $post->post_title,
            'post_id' => $postId,
            'edit_url' => get_edit_post_link($postId, 'raw'),
            'view_url' => get_permalink($postId),
        ]);
    }
}

