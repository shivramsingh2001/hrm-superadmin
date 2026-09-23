<?php

namespace App\Http\Controllers;

use App\Models\FeatureRegistry;
use App\Services\AuditLogger;
use Database\Seeders\FeatureRegistrySeeder;
use Illuminate\Http\Request;

class FeatureRegistryController extends Controller
{
    public function index()
    {
        $rows = FeatureRegistry::orderBy('key')->get()->keyBy('key');
        $config = config('features');

        // Diff: keys defined in config but missing from the table, and orphan
        // table rows whose key is no longer in config.
        $missing = array_diff(array_keys($config), $rows->keys()->all());
        $orphans = array_diff($rows->keys()->all(), array_keys($config));

        return view('feature-registry.index', compact('rows', 'config', 'missing', 'orphans'));
    }

    public function reseed()
    {
        (new FeatureRegistrySeeder())->run();
        AuditLogger::record('feature_registry.reseeded', 'feature_registry', null, null,
            ['keys' => FeatureRegistry::count()]);

        return back()->with('success', 'Feature registry re-seeded from config.');
    }

    /** Manage a registered key's deprecation state (superadmin). */
    public function update(Request $request, string $key)
    {
        $row = FeatureRegistry::findOrFail($key);
        $data = $request->validate([
            'action' => ['required', 'in:activate,deactivate,deprecate,undeprecate'],
            'replaced_by' => ['nullable', 'string', 'exists:feature_registry,key', 'different:'.$key],
        ]);

        $old = $row->only(['is_active', 'deprecated_at', 'replaced_by']);

        match ($data['action']) {
            'activate' => $row->update(['is_active' => true]),
            'deactivate' => $row->update(['is_active' => false]),
            'deprecate' => $row->update([
                'deprecated_at' => now(),
                'replaced_by' => $data['replaced_by'] ?? null,
                'is_active' => false,
            ]),
            'undeprecate' => $row->update([
                'deprecated_at' => null,
                'replaced_by' => null,
                'is_active' => true,
            ]),
        };

        AuditLogger::record('feature_registry.updated', 'feature_registry', null,
            $old, $row->fresh()->only(['is_active', 'deprecated_at', 'replaced_by']) + ['key' => $key]);

        return back()->with('success', "Feature '{$key}' {$data['action']}d.");
    }
}
