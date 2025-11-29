<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Admin;

use flavor\SchemaMarkupGenerator\Schema\SchemaRenderer;
use flavor\SchemaMarkupGenerator\Schema\SchemaValidator;

/**
 * Preview Handler
 *
 * Handles AJAX requests for schema preview and validation.
 *
 * @package flavor\SchemaMarkupGenerator\Admin
 * @author  Michele Marri <info@metodo.dev>
 */
class PreviewHandler
{
    private SchemaRenderer $schemaRenderer;

    public function __construct(SchemaRenderer $schemaRenderer)
    {
        $this->schemaRenderer = $schemaRenderer;
    }

    /**
     * Handle AJAX preview request
     */
    public function handle(): void
    {
        check_ajax_referer('smg_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'schema-markup-generator')]);
        }

        $postId = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$postId) {
            wp_send_json_error(['message' => __('Invalid post ID', 'schema-markup-generator')]);
        }

        $post = get_post($postId);

        if (!$post) {
            wp_send_json_error(['message' => __('Post not found', 'schema-markup-generator')]);
        }

        // Get schema data
        $schemas = $this->schemaRenderer->getPostSchemas($post);
        $json = $this->schemaRenderer->getJsonForPost($postId);

        // Validate schema
        $validator = new SchemaValidator();
        $validation = ['valid' => true, 'errors' => [], 'warnings' => []];

        foreach ($schemas as $schema) {
            $result = $validator->validate($schema);
            if (!$result['valid']) {
                $validation['valid'] = false;
            }
            $validation['errors'] = array_merge($validation['errors'], $result['errors']);
            $validation['warnings'] = array_merge($validation['warnings'], $result['warnings']);
        }

        wp_send_json_success([
            'json' => $json,
            'validation' => $validation,
            'schemas_count' => count($schemas),
        ]);
    }
}

