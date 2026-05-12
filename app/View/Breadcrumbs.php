<?php

namespace App\View;

use App\Models\Document;
use App\Models\NotaryRequest;
use App\Models\Template;
use Illuminate\Support\Str;

final class Breadcrumbs
{
    /**
     * @return list<array{label: string, href?: string|null}>
     */
    public static function items(): array
    {
        $route = request()->route();
        if ($route === null) {
            return [];
        }

        $name = $route->getName();
        if ($name === null) {
            return [];
        }

        return match ($name) {
            'dashboard' => [],
            'verify.index' => [
                ['label' => __('Dashboard'), 'href' => route('dashboard')],
                ['label' => __('Verify')],
            ],
            'contacts.index' => [
                ['label' => __('Dashboard'), 'href' => route('dashboard')],
                ['label' => __('Contacts')],
            ],
            'notary-requests.index', 'notary.requests.index' => [
                ['label' => __('Dashboard'), 'href' => route('dashboard')],
                ['label' => __('Notary requests')],
            ],
            'notary-requests.create' => [
                ['label' => __('Dashboard'), 'href' => route('dashboard')],
                ['label' => __('Notary requests'), 'href' => route('notary-requests.index')],
                ['label' => __('New request')],
            ],
            'notary-requests.show', 'notary.requests.show' => self::notaryRequestShow($route->parameter('notaryRequest')),
            'documents.index' => [
                ['label' => __('Dashboard'), 'href' => route('dashboard')],
                ['label' => __('Documents')],
            ],
            'documents.create' => [
                ['label' => __('Dashboard'), 'href' => route('dashboard')],
                ['label' => __('Documents'), 'href' => route('documents.index')],
                ['label' => __('Upload document')],
            ],
            'documents.show' => self::documentShow($route->parameter('document')),
            'documents.prepare' => self::documentPrepare($route->parameter('document')),
            'templates.index' => [
                ['label' => __('Dashboard'), 'href' => route('dashboard')],
                ['label' => __('Templates')],
            ],
            'templates.create' => [
                ['label' => __('Dashboard'), 'href' => route('dashboard')],
                ['label' => __('Templates'), 'href' => route('templates.index')],
                ['label' => __('New template')],
            ],
            'templates.edit' => self::templateWizardEdit($route->parameter('template')),
            'templates.use' => self::templateUse($route->parameter('template')),
            'templates.prepare' => self::templatePrepare($route->parameter('template')),
            default => [],
        };
    }

    /**
     * @return list<array{label: string, href?: string|null}>
     */
    private static function documentShow(mixed $document): array
    {
        if (! $document instanceof Document) {
            return [];
        }

        return [
            ['label' => __('Dashboard'), 'href' => route('dashboard')],
            ['label' => __('Documents'), 'href' => route('documents.index')],
            ['label' => Str::limit($document->title, 48)],
        ];
    }

    /**
     * @return list<array{label: string, href?: string|null}>
     */
    private static function documentPrepare(mixed $document): array
    {
        if (! $document instanceof Document) {
            return [];
        }

        return [
            ['label' => __('Dashboard'), 'href' => route('dashboard')],
            ['label' => __('Documents'), 'href' => route('documents.index')],
            ['label' => Str::limit($document->title, 40), 'href' => route('documents.show', $document)],
            ['label' => __('Prepare')],
        ];
    }

    /**
     * @return list<array{label: string, href?: string|null}>
     */
    private static function notaryRequestShow(mixed $notaryRequest): array
    {
        if (! $notaryRequest instanceof NotaryRequest) {
            return [];
        }

        $indexRoute = request()->route()?->getName() === 'notary.requests.show'
            ? route('notary.requests.index')
            : route('notary-requests.index');

        return [
            ['label' => __('Dashboard'), 'href' => route('dashboard')],
            ['label' => __('Notary requests'), 'href' => $indexRoute],
            ['label' => Str::limit($notaryRequest->title, 48)],
        ];
    }

    /**
     * @return list<array{label: string, href?: string|null}>
     */
    private static function templateWizardEdit(mixed $template): array
    {
        if (! $template instanceof Template) {
            return [];
        }

        return [
            ['label' => __('Dashboard'), 'href' => route('dashboard')],
            ['label' => __('Templates'), 'href' => route('templates.index')],
            ['label' => __('Edit template')],
        ];
    }

    /**
     * @return list<array{label: string, href?: string|null}>
     */
    private static function templateUse(mixed $template): array
    {
        if (! $template instanceof Template) {
            return [];
        }

        return [
            ['label' => __('Dashboard'), 'href' => route('dashboard')],
            ['label' => __('Templates'), 'href' => route('templates.index')],
            ['label' => __('Use template')],
        ];
    }

    /**
     * @return list<array{label: string, href?: string|null}>
     */
    private static function templatePrepare(mixed $template): array
    {
        if (! $template instanceof Template) {
            return [];
        }

        return [
            ['label' => __('Dashboard'), 'href' => route('dashboard')],
            ['label' => __('Templates'), 'href' => route('templates.index')],
            ['label' => Str::limit($template->name, 40)],
            ['label' => __('Prepare')],
        ];
    }
}
