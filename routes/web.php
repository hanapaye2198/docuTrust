<?php

use App\Http\Controllers\Admin\SignatureComplianceController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\DocumentCertificateController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\DocumentPrepareController;
use App\Http\Controllers\DocumentSignerPagesController;
use App\Http\Controllers\DocumentStreamController;
use App\Http\Controllers\EmailInfrastructureExampleController;
use App\Http\Controllers\EnotarySignerVideoJoinController;
use App\Http\Controllers\MarketingChatbotController;
use App\Http\Controllers\MarketingFeatureController;
use App\Http\Controllers\NotaryCredentialDocumentController;
use App\Http\Controllers\SignDocumentController;
use App\Http\Controllers\TemplatePrepareController;
use App\Http\Controllers\TemplateUseController;
use App\Http\Controllers\TrustProfileAssetController;
use App\Http\Middleware\AllowMediaPermissions;
use App\Services\NotarialRegisterService;
use App\Support\MarketingFeatures;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/features/{feature}', [MarketingFeatureController::class, 'show'])
    ->whereIn('feature', MarketingFeatures::slugs())
    ->name('features.show');

Route::post('/ai/chat', [AIController::class, 'ask'])
    ->middleware('throttle:marketing-chatbot')
    ->name('ai.chat');

Route::post('/marketing-chatbot/message', MarketingChatbotController::class)
    ->middleware('throttle:marketing-chatbot')
    ->name('marketing-chatbot.message');

Route::get('/verify/notary/{token}', function (string $token) {
    $entry = app(NotarialRegisterService::class)->findByVerificationToken($token);

    if ($entry === null) {
        abort(404);
    }

    return view('notary.verify', ['entry' => $entry]);
})->name('notary.verify');

Route::get('/enotary/video/{token}', [EnotarySignerVideoJoinController::class, 'show'])
    ->middleware('throttle:signing-links')
    ->name('enotary.video.join');

Route::middleware('throttle:signing-links')->group(function () {
    Route::get('/sign/{token}', [SignDocumentController::class, 'show'])->name('sign.show');
    Route::post('/sign/{token}/unlock', [SignDocumentController::class, 'unlock'])->name('sign.unlock');
    Route::get('/sign/{token}/pdf', [SignDocumentController::class, 'streamPdf'])->name('sign.document.pdf');
    Route::get('/sign/{token}/signature-image/{signatureField}', [SignDocumentController::class, 'streamSignatureImage'])->name('sign.signature.image');
    Route::post('/sign/{token}', [SignDocumentController::class, 'sign'])->name('sign.store');
    Route::post('/sign/{token}/signature', [SignDocumentController::class, 'storeSignature'])->name('sign.signature.store');
    Route::post('/sign/{token}/trust/authorize', [SignDocumentController::class, 'startTrustAuthorization'])->name('sign.trust.authorize');
    Route::get('/sign/{token}/trust/authorize/{session}', [SignDocumentController::class, 'pollTrustAuthorization'])->name('sign.trust.authorize.poll');
    Route::post('/sign/{token}/complete', [SignDocumentController::class, 'complete'])->name('sign.complete');
});

Route::middleware(['auth', 'role:super_admin,notary_admin,client'])->group(function () {
    Route::get('/account-sign/{signerId}', [SignDocumentController::class, 'showAuthenticated'])->name('sign.account.show');
    Route::post('/account-sign/{signerId}/unlock', [SignDocumentController::class, 'unlockAuthenticated'])->name('sign.account.unlock');
    Route::get('/account-sign/{signerId}/pdf', [SignDocumentController::class, 'streamAuthenticatedPdf'])->name('sign.account.document.pdf');
    Route::get('/account-sign/{signerId}/signature-image/{signatureField}', [SignDocumentController::class, 'streamAuthenticatedSignatureImage'])->name('sign.account.signature.image');
    Route::post('/account-sign/{signerId}', [SignDocumentController::class, 'signAuthenticated'])->name('sign.account.store');
    Route::post('/account-sign/{signerId}/signature', [SignDocumentController::class, 'storeAuthenticatedSignature'])->name('sign.account.signature.store');
    Route::post('/account-sign/{signerId}/trust/authorize', [SignDocumentController::class, 'startAuthenticatedTrustAuthorization'])->name('sign.account.trust.authorize');
    Route::get('/account-sign/{signerId}/trust/authorize/{session}', [SignDocumentController::class, 'pollAuthenticatedTrustAuthorization'])->name('sign.account.trust.authorize.poll');
});

Route::middleware(['auth', 'role:notary'])->group(function () {
    Route::get('/notary/account-sign/{signerId}', [SignDocumentController::class, 'showAuthenticated'])->name('notary.sign.account.show');
    Route::post('/notary/account-sign/{signerId}/unlock', [SignDocumentController::class, 'unlockAuthenticated'])->name('notary.sign.account.unlock');
    Route::get('/notary/account-sign/{signerId}/pdf', [SignDocumentController::class, 'streamAuthenticatedPdf'])->name('notary.sign.account.document.pdf');
    Route::get('/notary/account-sign/{signerId}/signature-image/{signatureField}', [SignDocumentController::class, 'streamAuthenticatedSignatureImage'])->name('notary.sign.account.signature.image');
    Route::post('/notary/account-sign/{signerId}', [SignDocumentController::class, 'signAuthenticated'])->name('notary.sign.account.store');
    Route::post('/notary/account-sign/{signerId}/signature', [SignDocumentController::class, 'storeAuthenticatedSignature'])->name('notary.sign.account.signature.store');
    Route::post('/notary/account-sign/{signerId}/trust/authorize', [SignDocumentController::class, 'startAuthenticatedTrustAuthorization'])->name('notary.sign.account.trust.authorize');
    Route::get('/notary/account-sign/{signerId}/trust/authorize/{session}', [SignDocumentController::class, 'pollAuthenticatedTrustAuthorization'])->name('notary.sign.account.trust.authorize.poll');
});
Volt::route('verify', 'pages.verify')->name('verify.index');

Route::middleware(['auth', 'role:super_admin,notary_admin'])->group(function () {
    Volt::route('admin/enotary', 'notary-admin.dashboard')->name('admin.enotary.dashboard');
    Volt::route('admin/signing-dashboard', 'pages.dashboard')->name('admin.signing.dashboard');

    Volt::route('admin/attorney-applications', 'admin.attorney-applications-index')->name('admin.attorney-applications.index');
    Volt::route('admin/attorney-applications/{credential}', 'admin.attorney-applications-show')->name('admin.attorney-applications.show');
    Route::get('admin/attorney-applications/{credential}/document/{document}', NotaryCredentialDocumentController::class)
        ->name('admin.attorney-applications.document');
});

Route::middleware(['auth', 'role:super_admin'])->group(function () {
    Volt::route('dashboard', 'admin.platform-dashboard')->name('dashboard');

    Volt::route('admin/users', 'admin.users-index')->name('admin.users.index');
    Volt::route('admin/compliance', 'admin.compliance-dashboard')->name('admin.compliance.dashboard');

    Route::prefix('admin/compliance')->name('admin.compliance.')->group(function () {
        Route::get('report.json', [SignatureComplianceController::class, 'json'])
            ->name('report.json');
        Route::get('report.json/download', [SignatureComplianceController::class, 'downloadJson'])
            ->name('report.json.download');
        Route::get('report.pdf', [SignatureComplianceController::class, 'downloadPdf'])
            ->name('report.pdf');
    });
    Volt::route('notary-admin/einvoices', 'notary-admin.einvoices')->name('notary-admin.einvoices');
    Volt::route('notary-admin/billing-profile', 'notary-admin.billing-profile')->name('notary-admin.billing-profile');
});

Route::middleware(['auth', 'role:notary', 'attorney.practice'])->group(function () {
    Volt::route('notary/dashboard', 'notary.dashboard')->name('notary.dashboard');
    Volt::route('notary/credentials', 'notary.credentials')->name('notary.credentials');
    Volt::route('notary/requests', 'notary-requests.index')->name('notary.requests.index');
    Volt::route('notary/requests/create', 'notary-requests.create')->name('notary.requests.create');
    Volt::route('notary/requests/{notaryRequest}', 'notary-requests.show')->name('notary.requests.show');
    Volt::route('notary/requests/{notaryRequest}/session/{session}', 'notary-requests.session-live')->name('notary.requests.session.live')->middleware(AllowMediaPermissions::class);
    Volt::route('notary/attorney-registries', 'notary.attorney-registries.index')->name('notary.attorney-registries.index');
    Volt::route('notary/requests/{notaryRequest}/attorney-registry', 'notary.attorney-registry')->name('notary.attorney-registry');
    Volt::route('notary/requests/{notaryRequest}/register-entry', 'notary.register-entry')->name('notary.register-entry');

    // Attorney access to document preparation and field placement for eNOTARY documents
    Route::get('notary/documents/{document}/stream', DocumentStreamController::class)->name('notary.documents.stream');
    Route::get('notary/documents/{document}/prepare', [DocumentPrepareController::class, 'show'])->name('notary.documents.prepare');
    Route::post('notary/documents/{document}/signature-fields', [DocumentPrepareController::class, 'store'])->name('notary.documents.signature-fields.store');
    Route::post('notary/documents/{document}/signer-pages', DocumentSignerPagesController::class)->name('notary.documents.signer-pages.store');
    Route::post('notary/documents/{document}/send', [DocumentPrepareController::class, 'send'])->name('notary.documents.send');
});

Route::middleware(['auth', 'role:super_admin,notary_admin,client'])->group(function () {
    Route::middleware(['workspace:enotary'])->group(function () {
        Volt::route('notary-requests', 'notary-requests.index')
            ->middleware('enotary.portal:manage')
            ->name('notary-requests.index');
        Volt::route('notary-requests/create', 'notary-requests.create')
            ->middleware('enotary.portal:manage')
            ->name('notary-requests.create');
        Volt::route('notary-requests/{notaryRequest}', 'notary-requests.show')
            ->middleware('enotary.portal:view')
            ->name('notary-requests.show');
        Volt::route('notary-requests/{notaryRequest}/session/{session}', 'notary-requests.session-live')
            ->middleware(['enotary.portal:view', AllowMediaPermissions::class])
            ->name('notary-requests.session.live');
    });

    Route::middleware(['workspace:signing'])->group(function () {
        Volt::route('contacts', 'contacts.index')->name('contacts.index');

        Volt::route('documents', 'documents.index')->name('documents.index');
        Volt::route('documents/create', 'documents.create')->name('documents.create');
        Volt::route('documents/{document}', 'documents.show')->name('documents.show');

        Route::get('documents/{document}/stream', DocumentStreamController::class)->name('documents.stream');
        Route::get('documents/{document}/download', DocumentDownloadController::class)->name('documents.download');
        Route::get('documents/{document}/certificate', [DocumentCertificateController::class, 'show'])->name('documents.certificate.show');
        Route::get('documents/{document}/certificate/download', [DocumentCertificateController::class, 'download'])->name('documents.certificate.download');
        Route::get('documents/{document}/prepare', [DocumentPrepareController::class, 'show'])->name('documents.prepare');
        Route::post('documents/{document}/signature-fields', [DocumentPrepareController::class, 'store'])->name('documents.signature-fields.store');
        Route::post('documents/{document}/signer-pages', DocumentSignerPagesController::class)->name('documents.signer-pages.store');
        Route::post('documents/{document}/send', [DocumentPrepareController::class, 'send'])->name('documents.send');

        Volt::route('templates', 'templates.index')->name('templates.index');
        Volt::route('templates/create', 'templates.wizard')->name('templates.create');
        Volt::route('templates/{template}/edit', 'templates.wizard')->name('templates.edit');
        Route::get('templates/{template}/file', [TemplatePrepareController::class, 'file'])->name('templates.file');
        Route::get('templates/{template}/prepare', [TemplatePrepareController::class, 'show'])->name('templates.prepare');
        Route::post('templates/{template}/fields', [TemplatePrepareController::class, 'store'])->name('templates.fields.store');
        Volt::route('templates/{template}/use', 'templates.use')->name('templates.use');
        Route::post('templates/{template}/documents', [TemplateUseController::class, 'store'])->name('templates.documents.store');
    });
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/trust-profile');

    Volt::route('settings/trust-profile', 'settings.trust-profile')->name('settings.trust-profile');
    Volt::route('settings/attorney-application', 'settings.attorney-application')->name('settings.attorney-application');
    Route::get('settings/trust-profile/photo', [TrustProfileAssetController::class, 'photo'])->name('settings.trust-profile.photo');
    Route::get('settings/trust-profile/signature', [TrustProfileAssetController::class, 'signature'])->name('settings.trust-profile.signature');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Route::redirect('settings/password', '/settings/profile?tab=password')->name('settings.password');
    Route::redirect('settings/security', '/settings/profile?tab=security')->name('settings.security');
    Route::redirect('settings/appearance', '/settings/profile?tab=appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';

if (app()->environment(['local', 'testing'])) {
    Route::middleware('auth')->prefix('_examples/email')->group(function () {
        Route::post('/otp', [EmailInfrastructureExampleController::class, 'sendOtp'])->name('examples.email.otp');
        Route::post('/signer-invitation/{signer}', [EmailInfrastructureExampleController::class, 'sendSignerInvitation'])->name('examples.email.signer-invitation');
        Route::post('/reminder/{signer}', [EmailInfrastructureExampleController::class, 'sendReminder'])->name('examples.email.reminder');
        Route::post('/document-completed/{document}', [EmailInfrastructureExampleController::class, 'sendCompleted'])->name('examples.email.document-completed');
    });
}
