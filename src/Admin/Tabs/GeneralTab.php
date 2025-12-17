<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin\Tabs;

/**
 * General Tab (Home/Dashboard)
 *
 * Content statistics dashboard.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin\Tabs
 * @author  Michele Marri <plugins@metodo.dev>
 */
class GeneralTab extends AbstractTab
{
    public function getTitle(): string
    {
        return __('Home', 'schema-markup-generator');
    }

    public function getIcon(): string
    {
        return 'dashicons-admin-home';
    }

    public function getSettingsGroup(): string
    {
        // No settings on this tab - it's just a dashboard
        return '';
    }

    /**
     * Get content statistics for schema coverage
     *
     * @return array{
     *     total_with_schema: int,
     *     total_without_schema: int,
     *     total_overrides: int,
     *     total_disabled: int,
     *     by_post_type: array<string, array{
     *         label: string,
     *         schema: string,
     *         total: int,
     *         with_schema: int,
     *         has_config: bool
     *     }>,
     *     by_schema_type: array<string, int>
     * }
     */
    private function getContentStatistics(): array
    {
        global $wpdb;

        $postTypeMappings = get_option('smg_post_type_mappings', []);
        $pageMappings = get_option('smg_page_mappings', []);

        if (!is_array($postTypeMappings)) {
            $postTypeMappings = [];
        }
        if (!is_array($pageMappings)) {
            $pageMappings = [];
        }

        $stats = [
            'total_with_schema' => 0,
            'total_without_schema' => 0,
            'total_overrides' => 0,
            'total_disabled' => 0,
            'by_post_type' => [],
            'by_schema_type' => [],
        ];

        // Get ALL public post types (not just configured ones)
        $allPostTypes = get_post_types(['public' => true], 'objects');
        
        // Exclude attachment
        unset($allPostTypes['attachment']);

        foreach ($allPostTypes as $postType => $postTypeObject) {
            // Check if this post type has a schema configured
            $schemaType = $postTypeMappings[$postType] ?? '';
            $hasConfig = !empty($schemaType);

            // Count published posts of this type
            $totalPosts = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
                $postType
            ));

            // Skip if no published posts
            if ($totalPosts === 0) {
                continue;
            }

            // Count posts with schema disabled
            $disabledCount = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = %s AND p.post_status = 'publish'
                 AND pm.meta_key = '_smg_disable_schema' AND pm.meta_value = '1'",
                $postType
            ));

            // Count posts with individual override (for this post type)
            $overrideCount = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = %s AND p.post_status = 'publish'
                 AND pm.meta_key = '_smg_schema_type' AND pm.meta_value != ''",
                $postType
            ));

            if ($hasConfig) {
                // Posts with schema = total - disabled
                $withSchema = $totalPosts - $disabledCount;
                $stats['total_with_schema'] += $withSchema;

                // Accumulate by schema type
                if (!isset($stats['by_schema_type'][$schemaType])) {
                    $stats['by_schema_type'][$schemaType] = 0;
                }
                $stats['by_schema_type'][$schemaType] += $withSchema;
            } else {
                // Post type not configured - only posts with override have schema
                $withSchema = $overrideCount;
                $stats['total_without_schema'] += ($totalPosts - $overrideCount);
                $stats['total_with_schema'] += $overrideCount;
            }

            $stats['by_post_type'][$postType] = [
                'label' => $postTypeObject->labels->name,
                'schema' => $schemaType,
                'total' => $totalPosts,
                'with_schema' => $withSchema,
                'disabled' => $disabledCount,
                'overrides' => $overrideCount,
                'has_config' => $hasConfig,
            ];
        }

        // Count posts with individual override globally
        $stats['total_overrides'] = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
             WHERE meta_key = '_smg_schema_type' AND meta_value != ''"
        );

        // Count posts with schema disabled globally
        $stats['total_disabled'] = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
             WHERE meta_key = '_smg_disable_schema' AND meta_value = '1'"
        );

        // Sort schema types by count (descending)
        arsort($stats['by_schema_type']);

        return $stats;
    }

    public function render(): void
    {
        // Get content statistics
        $contentStats = $this->getContentStatistics();

        ?>
        <div class="flex flex-col gap-6" id="tab-general">
            <?php $this->renderSection(
                __('Schema Coverage', 'schema-markup-generator'),
                __('Overview of content with schema markup.', 'schema-markup-generator')
            ); ?>

            <!-- Summary Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="smg-card">
                    <div class="smg-card-body text-center py-4">
                        <div class="text-3xl font-bold text-green-600"><?php echo esc_html(number_format_i18n($contentStats['total_with_schema'])); ?></div>
                        <div class="text-sm text-gray-500"><?php esc_html_e('With Schema', 'schema-markup-generator'); ?></div>
                    </div>
                </div>
                <div class="smg-card">
                    <div class="smg-card-body text-center py-4">
                        <div class="text-3xl font-bold text-amber-600"><?php echo esc_html(number_format_i18n($contentStats['total_without_schema'])); ?></div>
                        <div class="text-sm text-gray-500"><?php esc_html_e('Without Schema', 'schema-markup-generator'); ?></div>
                    </div>
                </div>
                <div class="smg-card">
                    <div class="smg-card-body text-center py-4">
                        <div class="text-3xl font-bold text-blue-600"><?php echo esc_html(number_format_i18n($contentStats['total_overrides'])); ?></div>
                        <div class="text-sm text-gray-500"><?php esc_html_e('Overrides', 'schema-markup-generator'); ?></div>
                    </div>
                </div>
                <div class="smg-card">
                    <div class="smg-card-body text-center py-4">
                        <div class="text-3xl font-bold text-gray-400"><?php echo esc_html(number_format_i18n($contentStats['total_disabled'])); ?></div>
                        <div class="text-sm text-gray-500"><?php esc_html_e('Disabled', 'schema-markup-generator'); ?></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($contentStats['by_post_type'])): ?>
            <div class="smg-card">
                <div class="smg-card-header">
                    <div class="flex items-center gap-2">
                        <span class="dashicons dashicons-list-view"></span>
                        <h3><?php esc_html_e('Coverage by Post Type', 'schema-markup-generator'); ?></h3>
                    </div>
                </div>
                <div class="smg-card-body p-0">
                    <table class="smg-table w-full">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50">
                                <th class="text-left py-3 px-4 font-medium text-gray-600 uppercase text-xs tracking-wider"><?php esc_html_e('Post Type', 'schema-markup-generator'); ?></th>
                                <th class="text-left py-3 px-4 font-medium text-gray-600 uppercase text-xs tracking-wider"><?php esc_html_e('Schema Type', 'schema-markup-generator'); ?></th>
                                <th class="text-right py-3 px-4 font-medium text-gray-600 uppercase text-xs tracking-wider"><?php esc_html_e('Total', 'schema-markup-generator'); ?></th>
                                <th class="text-right py-3 px-4 font-medium text-gray-600 uppercase text-xs tracking-wider"><?php esc_html_e('With Schema', 'schema-markup-generator'); ?></th>
                                <th class="text-center py-3 px-4 font-medium text-gray-600 uppercase text-xs tracking-wider"><?php esc_html_e('Coverage', 'schema-markup-generator'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contentStats['by_post_type'] as $postType => $data): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4">
                                    <span class="font-medium"><?php echo esc_html($data['label']); ?></span>
                                    <span class="text-xs text-gray-400 ml-1">(<?php echo esc_html($postType); ?>)</span>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($data['has_config']): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo esc_html($data['schema']); ?>
                                        </span>
                                    <?php else: ?>
                                        <a href="<?php echo esc_url(admin_url('options-general.php?page=schema-markup-generator&tab=schemas-post-types')); ?>" 
                                           class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 hover:bg-amber-200">
                                            <?php esc_html_e('Not configured', 'schema-markup-generator'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-right font-mono"><?php echo esc_html(number_format_i18n($data['total'])); ?></td>
                                <td class="py-3 px-4 text-right font-mono <?php echo $data['with_schema'] > 0 ? 'text-green-600' : 'text-gray-400'; ?>">
                                    <?php echo esc_html(number_format_i18n($data['with_schema'])); ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php
                                    $coverage = $data['total'] > 0 ? round(($data['with_schema'] / $data['total']) * 100) : 0;
                                    $barColor = $coverage >= 90 ? 'bg-green-500' : ($coverage >= 50 ? 'bg-yellow-500' : 'bg-red-500');
                                    if (!$data['has_config'] && $coverage === 0) {
                                        $barColor = 'bg-gray-300';
                                    }
                                    ?>
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div class="h-full <?php echo esc_attr($barColor); ?>" style="width: <?php echo esc_attr($coverage); ?>%"></div>
                                        </div>
                                        <span class="text-xs font-medium text-gray-600 w-10 text-right"><?php echo esc_html($coverage); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (!empty($contentStats['by_schema_type'])): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php
                $this->renderCard(__('Content by Schema Type', 'schema-markup-generator'), function () use ($contentStats) {
                    ?>
                    <div class="space-y-3">
                        <?php foreach ($contentStats['by_schema_type'] as $schemaType => $count): ?>
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                <?php echo esc_html($schemaType); ?>
                            </span>
                            <span class="font-mono text-sm">
                                <?php echo esc_html(number_format_i18n($count)); ?>
                                <span class="text-gray-400 text-xs"><?php echo esc_html(_n('item', 'items', $count, 'schema-markup-generator')); ?></span>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php
                }, 'dashicons-tag');
                ?>

                <?php
                $this->renderCard(__('Overall Coverage', 'schema-markup-generator'), function () use ($contentStats) {
                    $totalConfigured = array_sum(array_column($contentStats['by_post_type'], 'total'));
                    $totalWithSchema = $contentStats['total_with_schema'];
                    $overallCoverage = $totalConfigured > 0 ? round(($totalWithSchema / $totalConfigured) * 100) : 0;
                    ?>
                    <div class="text-center">
                        <div class="text-5xl font-bold <?php echo $overallCoverage >= 90 ? 'text-green-600' : ($overallCoverage >= 50 ? 'text-yellow-600' : 'text-red-600'); ?>">
                            <?php echo esc_html($overallCoverage); ?>%
                        </div>
                        <div class="text-sm text-gray-500 mt-1"><?php esc_html_e('of all content', 'schema-markup-generator'); ?></div>
                        <div class="text-xs text-gray-400 mt-3">
                            <?php
                            printf(
                                /* translators: %1$s: number with schema, %2$s: total number */
                                esc_html__('%1$s of %2$s posts have schema', 'schema-markup-generator'),
                                '<span class="font-medium">' . esc_html(number_format_i18n($totalWithSchema)) . '</span>',
                                '<span class="font-medium">' . esc_html(number_format_i18n($totalConfigured)) . '</span>'
                            );
                            ?>
                        </div>
                    </div>
                    <?php
                }, 'dashicons-chart-pie');
                ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="smg-card">
                <div class="smg-card-body text-center py-8">
                    <span class="dashicons dashicons-info-outline text-4xl text-gray-300 mb-4"></span>
                    <p class="text-gray-500"><?php esc_html_e('No published content found.', 'schema-markup-generator'); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
