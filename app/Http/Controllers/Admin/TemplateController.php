<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TemplateController extends Controller
{
    public function index()
    {
        return view('admin.templates.index', [
            'templates' => Template::ordered()->withCount('photoSessions')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.templates.create', [
            'categories' => config('photobooth.categories'),
            'layoutJson' => '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('frame_asset')) {
            $data['frame_asset'] = $request->file('frame_asset')->store('templates', 'public');
        }

        Template::create($data);

        return redirect()->route('admin.templates.index')->with('success', 'Template berhasil ditambahkan.');
    }

    public function edit(Template $template)
    {
        $layout = $template->layout_config;
        if (is_string($layout)) {
            $layout = json_decode($layout, true);
        }

        return view('admin.templates.edit', [
            'template'   => $template,
            'categories' => config('photobooth.categories'),
            'layoutJson' => $this->prettyLayout(is_array($layout) ? $layout : []),
        ]);
    }

    public function update(Request $request, Template $template): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('frame_asset')) {
            $old = $template->frame_asset;
            $data['frame_asset'] = $request->file('frame_asset')->store('templates', 'public');
            if ($old && str_starts_with($old, 'templates/')) {
                Storage::disk('public')->delete($old);
            }
        }

        $template->update($data);

        return redirect()->route('admin.templates.index')->with('success', 'Template berhasil diperbarui.');
    }

    public function toggle(Template $template): RedirectResponse
    {
        $template->update(['is_active' => ! $template->is_active]);

        return back()->with('success', $template->is_active ? 'Template diaktifkan.' : 'Template dinonaktifkan.');
    }

    public function destroy(Template $template): RedirectResponse
    {
        $used = $template->photoSessions()->count();
        if ($used > 0) {
            return back()->with('error', "Template sudah dipakai {$used} sesi. Nonaktifkan saja agar riwayat foto tetap aman.");
        }

        if ($template->frame_asset && str_starts_with($template->frame_asset, 'templates/')) {
            Storage::disk('public')->delete($template->frame_asset);
        }
        $template->delete();

        return redirect()->route('admin.templates.index')->with('success', 'Template dihapus.');
    }

    /** Validasi bersama untuk simpan & ubah; mengembalikan atribut siap disimpan. */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:80'],
            'description'   => ['nullable', 'string', 'max:300'],
            'category'      => ['required', Rule::in(array_keys(config('photobooth.categories')))],
            'tags'          => ['nullable', 'string', 'max:200'],
            'slot_count'    => ['required', 'integer', 'min:1', 'max:4'],
            'bg_color'      => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order'    => ['nullable', 'integer', 'min:0', 'max:9999'],
            'frame_asset'   => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
            'layout_config' => ['nullable', 'string', 'max:5000'],
        ], [
            'name.required'       => 'Nama template wajib diisi.',
            'category.in'         => 'Kategori tidak dikenal.',
            'slot_count.required' => 'Jumlah foto wajib diisi.',
            'slot_count.min'      => 'Jumlah foto minimal 1.',
            'slot_count.max'      => 'Jumlah foto maksimal 4.',
            'bg_color.regex'      => 'Warna latar harus berformat #RRGGBB, contoh #131947.',
            'frame_asset.image'   => 'Bingkai harus berupa gambar PNG atau JPG.',
            'frame_asset.mimes'   => 'Bingkai harus berformat PNG atau JPG.',
            'frame_asset.max'     => 'Ukuran bingkai maksimal 5 MB.',
        ]);

        $layout = $this->parseLayout($validated['layout_config'] ?? null, (int) $validated['slot_count']);

        $tags = collect(explode(',', (string) ($validated['tags'] ?? '')))
            ->map(fn ($t) => mb_strtolower(trim($t)))
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();

        return [
            'name'          => trim($validated['name']),
            'description'   => $validated['description'] ?? null,
            'category'      => $validated['category'],
            'tags'          => $tags,
            'slot_count'    => (int) $validated['slot_count'],
            'bg_color'      => strtoupper($validated['bg_color']),
            'sort_order'    => (int) ($validated['sort_order'] ?? 0),
            'layout_config' => $layout,
            'is_active'     => $request->boolean('is_active'),
        ];
    }

    /**
     * Periksa JSON tata letak. Mengembalikan array slot bersih, atau null bila kosong
     * (server lalu memakai tata letak tumpuk otomatis).
     */
    private function parseLayout(?string $json, int $slotCount): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded) || $decoded === [] || json_last_error() !== JSON_ERROR_NONE) {
            $this->layoutError('Tata letak harus berupa JSON daftar slot, contoh: [{"x":50,"y":260,"width":1100,"height":620}].');
        }

        $maxW = Template::CANVAS_WIDTH;
        $maxH = Template::CANVAS_HEIGHT;
        $slots = [];
        $seen = [];

        foreach (array_values($decoded) as $i => $s) {
            $n = $i + 1;
            if (! is_array($s)) {
                $this->layoutError("Slot {$n} harus berupa objek dengan x, y, width, height.");
            }
            foreach (['x', 'y', 'width', 'height'] as $key) {
                if (! isset($s[$key]) || ! is_numeric($s[$key])) {
                    $this->layoutError("Slot {$n}: nilai \"{$key}\" wajib berupa angka.");
                }
            }

            $slot = [
                'x'      => (int) round((float) $s['x']),
                'y'      => (int) round((float) $s['y']),
                'width'  => (int) round((float) $s['width']),
                'height' => (int) round((float) $s['height']),
                'photo'  => isset($s['photo']) && is_numeric($s['photo']) ? (int) $s['photo'] : $i,
            ];

            if ($slot['width'] < 1 || $slot['height'] < 1 || $slot['x'] < 0 || $slot['y'] < 0
                || $slot['x'] + $slot['width'] > $maxW || $slot['y'] + $slot['height'] > $maxH) {
                $this->layoutError("Slot {$n} keluar dari kanvas {$maxW}×{$maxH} px.");
            }
            if ($slot['photo'] < 0 || $slot['photo'] >= $slotCount) {
                $this->layoutError("Slot {$n} merujuk foto ke-" . ($slot['photo'] + 1) . ", padahal jumlah foto hanya {$slotCount}.");
            }

            $seen[$slot['photo']] = true;
            $slots[] = $slot;
        }

        for ($p = 0; $p < $slotCount; $p++) {
            if (! isset($seen[$p])) {
                $this->layoutError('Foto ke-' . ($p + 1) . ' belum punya slot di tata letak.');
            }
        }

        return $slots;
    }

    private function layoutError(string $message): never
    {
        throw ValidationException::withMessages(['layout_config' => $message]);
    }

    private function prettyLayout(array $slots): string
    {
        if ($slots === []) {
            return '';
        }

        return "[\n" . implode(",\n", array_map(
            fn ($s) => ' ' . json_encode($s, JSON_UNESCAPED_SLASHES),
            $slots
        )) . "\n]";
    }
}
