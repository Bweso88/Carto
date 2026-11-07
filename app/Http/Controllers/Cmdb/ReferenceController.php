<?php

namespace App\Http\Controllers\Cmdb;

use App\Http\Controllers\Controller;
use App\Support\ReferenceTypes;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administration générique des référentiels (sites, équipes, technologies…).
 */
class ReferenceController extends Controller
{
    public function index(string $type = 'sites'): Response
    {
        $def = ReferenceTypes::get($type);
        $model = $def['model'];

        $records = ReferenceTypes::withUsage($type, $model::query())
            ->orderBy($type === 'environments' ? 'sort_order' : 'name')
            ->when($type === 'technologies', fn ($q) => $q->orderBy('version'))
            ->get()
            ->map(function (Model $m) use ($def) {
                $values = [];
                foreach ($def['fields'] as $field) {
                    $v = $m->getAttribute($field['name']);
                    $values[$field['name']] = match (true) {
                        $v instanceof BackedEnum => $v->value,
                        $v instanceof \DateTimeInterface => $v->format('Y-m-d'),
                        default => $v,
                    };
                }
                $usage = collect($def['usage'])->sum(fn ($rel) => (int) $m->getAttribute(Str::snake($rel).'_count'));
                $row = ['id' => $m->getKey(), 'values' => $values, 'usage' => $usage];
                if (method_exists($m, 'isObsolete')) {
                    $row['obsolete'] = $m->isObsolete();
                }

                return $row;
            });

        return Inertia::render('references/Index', [
            'type' => $type,
            'types' => collect(ReferenceTypes::all())->map(fn ($d, $slug) => ['slug' => $slug, 'title' => $d['title']])->values(),
            'definition' => collect($def)->only(['title', 'singular', 'fields', 'columns']),
            'records' => $records,
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        Gate::authorize('edit-cmdb');
        $def = ReferenceTypes::get($type);
        $data = $this->normalize($request->validate(ReferenceTypes::rules($type)));
        $def['model']::create($data);
        Inertia::flash('toast', ['type' => 'success', 'message' => ucfirst($def['singular']).' ajouté(e).']);

        return back();
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        Gate::authorize('edit-cmdb');
        $def = ReferenceTypes::get($type);
        $record = $def['model']::findOrFail($id);
        $record->update($this->normalize($request->validate(ReferenceTypes::rules($type, $record))));
        Inertia::flash('toast', ['type' => 'success', 'message' => ucfirst($def['singular']).' mis(e) à jour.']);

        return back();
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        Gate::authorize('edit-cmdb');
        $def = ReferenceTypes::get($type);
        $def['model']::findOrFail($id)->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => ucfirst($def['singular']).' supprimé(e).']);

        return back();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        if (array_key_exists('version', $data)) {
            $data['version'] = (string) ($data['version'] ?? '');
        }

        return $data;
    }
}
