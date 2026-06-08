<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportItemCategorySync extends Command
{
    protected $signature = 'items:export-category-sync {--output=sync_item_categories.sql : Output SQL file path}';

    protected $description = 'Export SQL to sync local item category assignments to another environment';

    public function handle(): int
    {
        $output = base_path($this->option('output'));
        $pdo = Item::query()->getConnection()->getPdo();

        $categories = ItemCategory::query()
            ->orderBy('code')
            ->get(['code', 'name', 'description', 'color_code', 'is_active']);

        $items = Item::query()
            ->with('category:id,code')
            ->whereNotNull('category_id')
            ->orderBy('sku')
            ->get(['sku', 'erp_item_code', 'category_id']);

        $lines = [
            '-- Sync item category assignments from local to production',
            '-- Generated at: ' . now()->format('Y-m-d H:i:s'),
            '-- Matching key: items.sku -> item_categories.code',
            '-- Recommended: backup production database before running this file.',
            '',
            'START TRANSACTION;',
            '',
            '-- 1) Ensure category master data exists and matches local codes.',
        ];

        if ($categories->isNotEmpty()) {
            $lines[] = 'INSERT INTO item_categories (code, name, description, color_code, is_active, created_at, updated_at, deleted_at) VALUES';
            $lines[] = $categories
                ->map(function (ItemCategory $category) use ($pdo): string {
                    return sprintf(
                        '  (%s, %s, %s, %s, %d, NOW(), NOW(), NULL)',
                        $pdo->quote($category->code),
                        $pdo->quote($category->name),
                        $category->description === null ? 'NULL' : $pdo->quote($category->description),
                        $category->color_code === null ? 'NULL' : $pdo->quote($category->color_code),
                        $category->is_active ? 1 : 0
                    );
                })
                ->implode(",\n");
            $lines[] = 'ON DUPLICATE KEY UPDATE';
            $lines[] = '  name = VALUES(name),';
            $lines[] = '  description = VALUES(description),';
            $lines[] = '  color_code = VALUES(color_code),';
            $lines[] = '  is_active = VALUES(is_active),';
            $lines[] = '  updated_at = NOW(),';
            $lines[] = '  deleted_at = NULL;';
        }

        $lines[] = '';
        $lines[] = '-- 2) Load local item -> category mapping into a temporary table.';
        $lines[] = 'DROP TEMPORARY TABLE IF EXISTS tmp_item_category_sync;';
        $lines[] = 'CREATE TEMPORARY TABLE tmp_item_category_sync (';
        $lines[] = '  sku VARCHAR(255) NOT NULL PRIMARY KEY,';
        $lines[] = '  erp_item_code VARCHAR(255) NULL,';
        $lines[] = '  category_code VARCHAR(255) NOT NULL';
        $lines[] = ') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
        $lines[] = '';

        foreach ($items->chunk(500) as $chunk) {
            $lines[] = 'INSERT INTO tmp_item_category_sync (sku, erp_item_code, category_code) VALUES';
            $lines[] = $chunk
                ->map(function (Item $item) use ($pdo): string {
                    return sprintf(
                        '  (%s, %s, %s)',
                        $pdo->quote($item->sku),
                        $item->erp_item_code === null ? 'NULL' : $pdo->quote($item->erp_item_code),
                        $pdo->quote($item->category?->code)
                    );
                })
                ->implode(",\n") . ';';
            $lines[] = '';
        }

        $lines = array_merge($lines, [
            '-- 3) Preview records that cannot be applied in production.',
            "SELECT s.category_code, COUNT(*) AS missing_category_rows",
            "FROM tmp_item_category_sync s",
            "LEFT JOIN item_categories c ON c.code = s.category_code AND c.deleted_at IS NULL",
            "WHERE c.id IS NULL",
            "GROUP BY s.category_code;",
            '',
            "SELECT COUNT(*) AS items_not_found_by_sku",
            "FROM tmp_item_category_sync s",
            "LEFT JOIN items i ON i.sku = s.sku AND i.deleted_at IS NULL",
            "WHERE i.id IS NULL;",
            '',
            '-- 4) Apply category assignments by SKU.',
            'UPDATE items i',
            'JOIN tmp_item_category_sync s ON s.sku = i.sku',
            'JOIN item_categories c ON c.code = s.category_code AND c.deleted_at IS NULL',
            'SET i.category_id = c.id,',
            '    i.updated_at = NOW()',
            'WHERE i.deleted_at IS NULL;',
            '',
            '-- 5) Confirm how many production items now match the imported mapping.',
            'SELECT COUNT(*) AS matching_item_categories',
            'FROM items i',
            'JOIN tmp_item_category_sync s ON s.sku = i.sku',
            'JOIN item_categories c ON c.id = i.category_id AND c.code = s.category_code',
            'WHERE i.deleted_at IS NULL;',
            '',
            'COMMIT;',
            '',
        ]);

        File::put($output, implode("\n", $lines));

        $this->info('Exported ' . $items->count() . ' item category mappings.');
        $this->info('Output: ' . $output);

        return self::SUCCESS;
    }
}
