<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Resource;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CRUD for every App\Admin\Resource. The resource class comes from the route's
 * "resource" default (see routes/admin.php).
 */
class ResourceController extends Controller
{
    public function index(Request $request): View
    {
        $resource = $this->resource($request);

        return view('admin.resources.index', [
            'resource' => $resource,
            'records' => $resource::indexQuery($request)->paginate(25)->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        $resource = $this->resource($request);
        abort_unless($resource::canCreate(), 404);

        return view('admin.resources.form', ['resource' => $resource, 'record' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $resource = $this->resource($request);
        abort_unless($resource::canCreate(), 404);

        $record = new ($resource::$model);
        $this->save($resource, $record, $request);

        return redirect()->route($resource::routeName('index'))
            ->with('status', "{$resource::$singular} “{$record->getAttribute($resource::$title)}” created.");
    }

    public function edit(Request $request, string $id): View
    {
        $resource = $this->resource($request);

        return view('admin.resources.form', ['resource' => $resource, 'record' => $this->find($resource, $id)]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $resource = $this->resource($request);
        $record = $this->find($resource, $id);
        $this->save($resource, $record, $request);

        return redirect()->route($resource::routeName('index'))
            ->with('status', "{$resource::$singular} “{$record->getAttribute($resource::$title)}” saved.");
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $resource = $this->resource($request);
        $record = $this->find($resource, $id);

        if (! $resource::canDelete($record)) {
            return back()->with('error', "This {$resource::$singular} is in use and can't be deleted.");
        }

        $name = $record->getAttribute($resource::$title);
        $record->delete();

        return redirect()->route($resource::routeName('index'))->with('status', "{$resource::$singular} “{$name}” deleted.");
    }

    /** @param  class-string<Resource>  $resource */
    private function save(string $resource, Model $record, Request $request): void
    {
        $request->validate($resource::rules($record->exists ? $record : null), $resource::messages());

        DB::transaction(function () use ($resource, $record, $request) {
            $resource::fill($record, $request);
            $record->save();
            $resource::afterSave($record, $request);
        });
    }

    /** @return class-string<Resource> */
    private function resource(Request $request): string
    {
        return $request->route('resource');
    }

    /** @param  class-string<Resource>  $resource */
    private function find(string $resource, string $id): Model
    {
        return $resource::scope()->findOrFail($id);
    }
}
