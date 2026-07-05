<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Work;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminWorkController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $data['image_path'] = $this->storeImage($request);
        $data['sort_order'] = (int) Work::max('sort_order') + 1;
        unset($data['image']);

        return response()->json(Work::create($data), 201);
    }

    public function update(Request $request, Work $work)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('image')) {
            $this->deleteImage($work);
            $data['image_path'] = $this->storeImage($request);
        }
        unset($data['image']);
        $work->update($data);

        return response()->json($work->fresh());
    }

    public function destroy(Work $work)
    {
        $this->deleteImage($work);
        $work->delete();

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request)
    {
        $ids = $request->validate(['ids' => ['required', 'array']])['ids'];
        $count = count($ids);
        foreach ($ids as $i => $id) {
            Work::where('id', $id)->update(['sort_order' => $count - $i]);
        }

        return response()->json(['ok' => true]);
    }

    private function storeImage(Request $request): string
    {
        $path = $request->file('image')->store('works', 'public');
        if ($path === false) {
            // 書き込み失敗時に false のまま保存すると image_path が「0」になり
            // 画像リンクが静かに壊れるため、ここで明示的にエラーにする
            abort(500, '画像の保存に失敗しました。storage ディレクトリの書き込み権限を確認してください。');
        }

        return $path;
    }

    private function deleteImage(Work $work): void
    {
        if (! str_starts_with($work->image_path, 'images/')) {
            Storage::disk('public')->delete($work->image_path);
        }
    }
}
