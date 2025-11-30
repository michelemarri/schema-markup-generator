<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema;

use WP_Post;

/**
 * Schema Interface
 *
 * Contract for all schema type implementations.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema
 * @author  Michele Marri <info@metodo.dev>
 */
interface SchemaInterface
{
    /**
     * Get the schema.org type identifier
     *
     * @return string The @type value (e.g., "Article", "Product")
     */
    public function getType(): string;

    /**
     * Get human-readable label for the schema type
     *
     * @return string The display label
     */
    public function getLabel(): string;

    /**
     * Get schema description
     *
     * @return string Description of when to use this schema
     */
    public function getDescription(): string;

    /**
     * Build the schema data for a post
     *
     * @param WP_Post $post    The post object
     * @param array   $mapping Field mapping configuration
     * @return array The schema.org structured data
     */
    public function build(WP_Post $post, array $mapping = []): array;

    /**
     * Get required properties for this schema
     *
     * @return array List of required property names
     */
    public function getRequiredProperties(): array;

    /**
     * Get recommended properties for this schema
     *
     * @return array List of recommended property names
     */
    public function getRecommendedProperties(): array;

    /**
     * Get all available properties with their definitions
     *
     * @return array Property definitions with types and descriptions
     */
    public function getPropertyDefinitions(): array;

    /**
     * Validate the schema data
     *
     * @param array $data The schema data to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validate(array $data): array;
}

