<?php

namespace Database\Seeders;

use App\Models\FeatureRegistry;
use Illuminate\Database\Seeder;

/**
 * Upserts the config/features.php key registry into feature_registry.
 * Safe to re-run — existing rows are updated.
 *
 * Keys retired from config are listed in $deprecations with the key that
 * replaces them, so FeatureService can still resolve old plan snapshots
 * (it follows replaced_by once).
 */
class FeatureRegistrySeeder extends Seeder
{
    /** retired key => key that replaces it (or null to just disable) */
    private array $deprecations = [
        'task_management' => 'task_group',
    ];

    public function run(): void
    {
        foreach (config('features') as $key => $meta) {
            FeatureRegistry::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $meta['name'],
                    'description' => $meta['description'],
                    'is_active' => true,
                    'deprecated_at' => null,
                    'replaced_by' => null,
                    'updated_at' => now(),
                    'created_at' => FeatureRegistry::where('key', $key)->value('created_at') ?? now(),
                ],
            );
        }

        foreach ($this->deprecations as $key => $replacedBy) {
            FeatureRegistry::updateOrCreate(
                ['key' => $key],
                [
                    'name' => FeatureRegistry::where('key', $key)->value('name') ?? $key,
                    'description' => 'Deprecated — replaced by ' . ($replacedBy ?? 'nothing') . '.',
                    'is_active' => false,
                    'deprecated_at' => FeatureRegistry::where('key', $key)->value('deprecated_at') ?? now(),
                    'replaced_by' => $replacedBy,
                    'updated_at' => now(),
                    'created_at' => FeatureRegistry::where('key', $key)->value('created_at') ?? now(),
                ],
            );
        }

        $this->command?->info('feature_registry: ' . FeatureRegistry::count() . ' keys ('
            . FeatureRegistry::where('is_active', true)->count() . ' active).');
    }
}
