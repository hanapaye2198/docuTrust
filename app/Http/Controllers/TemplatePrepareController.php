<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTemplateFieldsRequest;
use App\Models\Template;
use App\Models\TemplateField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemplatePrepareController extends Controller
{
    public function show(Template $template): View
    {
        $this->authorize('update', $template);

        $template->load(['templateSigners']);

        $signerRoles = $template->signerRoles();
        $firstSignerRoleName = $signerRoles->first()?->role_name;

        $initialFields = $template->templateFields()
            ->orderBy('id')
            ->get()
            ->map(fn (TemplateField $f) => [
                'id' => $f->id,
                'role_name' => $f->role_name,
                'type' => $f->type->value,
                'position_data' => $f->position_data,
            ])
            ->values();

        $signerRoleNames = $signerRoles->pluck('role_name')->values()->all();

        return view('templates.prepare', [
            'template' => $template,
            'firstSignerRoleName' => $firstSignerRoleName,
            'signerRoleNames' => $signerRoleNames,
            'initialFields' => $initialFields,
            'templatePrepareConfig' => [
                'pdfUrl' => route('templates.file', $template, false),
                'firstSignerRoleName' => $firstSignerRoleName,
                'signerRoleNames' => $signerRoleNames,
                'initialFields' => $initialFields,
                'messages' => [
                    'missingDom' => __('The document viewer could not start because required elements are missing from the page.'),
                    'libs' => __('PDF.js or Fabric.js failed to load. Check your network or try a full page refresh.'),
                    'loadPdf' => __('Could not load the PDF.'),
                ],
            ],
        ]);
    }

    public function file(Template $template): StreamedResponse
    {
        $this->authorize('update', $template);

        $path = $template->primaryPdfPath();
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path, basename($path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }

    public function store(StoreTemplateFieldsRequest $request, Template $template): RedirectResponse
    {
        $fields = $request->validatedFieldsForTemplate($template);

        DB::transaction(function () use ($template, $fields): void {
            $template->templateFields()->delete();

            foreach ($fields as $field) {
                TemplateField::query()->create([
                    'template_id' => $template->id,
                    'role_name' => $field['role_name'],
                    'type' => $field['type'],
                    'position_data' => $field['position_data'],
                ]);
            }
        });

        return redirect()
            ->route('templates.prepare', $template)
            ->with('status', __('Template fields saved.'));
    }
}
