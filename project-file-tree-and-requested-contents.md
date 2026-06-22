# Project File Tree and Requested Contents

Excludes: `vendor/`, `node_modules/`, `storage/`, `.git/`.

## File Tree

```text
docuTrust/
|-- .cursor/
|   |-- rules/
|   |   `-- laravel-boost.mdc
|   |-- skills/
|   |   |-- fluxui-development/
|   |   |   `-- SKILL.md
|   |   |-- livewire-development/
|   |   |   |-- reference/
|   |   |   |   `-- javascript-hooks.md
|   |   |   `-- SKILL.md
|   |   |-- tailwindcss-development/
|   |   |   `-- SKILL.md
|   |   `-- volt-development/
|   |       `-- SKILL.md
|   `-- mcp.json
|-- .github/
|   `-- workflows/
|       |-- ci.yml
|       `-- deploy.yml
|-- .kiro/
|   |-- docs/
|   |   |-- bid-response-eal4-compliance.md
|   |   |-- certificate-policy.md
|   |   |-- crypto-boundary.md
|   |   |-- csc-compliance-plan.md
|   |   |-- deployment-guide.md
|   |   |-- implementation-status.md
|   |   |-- infrastructure-requirements.md
|   |   |-- README.md
|   |   `-- security-target.md
|   |-- specs/
|   |   |-- attorney-signing-after-video/
|   |   |   |-- design.md
|   |   |   |-- requirements.md
|   |   |   `-- tasks.md
|   |   |-- enotary-signing-workflow/
|   |   |   |-- .config.kiro
|   |   |   |-- bugfix.md
|   |   |   |-- design.md
|   |   |   `-- tasks.md
|   |   `-- enotary-signing-workflow-fix/
|   |       |-- .config.kiro
|   |       `-- bugfix.md
|   `-- steering/
|       `-- enotary-architecture.md
|-- app/
|   |-- Concerns/
|   |   `-- ResolvesSecureDisk.php
|   |-- Console/
|   |   `-- Commands/
|   |       |-- AnchorDocumentHash.php
|   |       |-- AuditHsmOperations.php
|   |       |-- CheckAll.php
|   |       |-- CheckCertificateExpiry.php
|   |       |-- CheckCertificateRevocation.php
|   |       |-- CheckCertificateValidity.php
|   |       |-- CheckCscCompliance.php
|   |       |-- CheckHsmAll.php
|   |       |-- CheckHsmAudit.php
|   |       |-- CheckHsmCompliance.php
|   |       |-- CheckHsmHealth.php
|   |       |-- CheckHsmNetwork.php
|   |       |-- CheckHsmPerformance.php
|   |       |-- CheckHsmRedundancy.php
|   |       |-- CheckHsmSecurity.php
|   |       |-- CheckKeyHealth.php
|   |       |-- DestroyKey.php
|   |       |-- ExpireSadSessions.php
|   |       |-- ExportAuditReport.php
|   |       |-- GenerateCertificate.php
|   |       |-- GenerateCrl.php
|   |       |-- GenerateDocumentHash.php
|   |       |-- GenerateKeyPair.php
|   |       |-- GetHsmAuditLog.php
|   |       |-- GetHsmSlotInfo.php
|   |       |-- GetHsmStatus.php
|   |       |-- GetPublicKey.php
|   |       |-- InitializeHsm.php
|   |       |-- MoveRootCaKeyToExternalStore.php
|   |       |-- PruneEInvoiceSubmissionPayloads.php
|   |       |-- RecoverStaleEInvoices.php
|   |       |-- RevokeCertificate.php
|   |       |-- RotateKeys.php
|   |       |-- SemaphoreTestCommand.php
|   |       |-- SendPendingSignatureReminders.php
|   |       |-- SignHash.php
|   |       |-- TestNotaryPaymentFlow.php
|   |       |-- UpdateSignerSealProvider.php
|   |       |-- VerifyDocumentHash.php
|   |       |-- VerifyHash.php
|   |       `-- VerifySignature.php
|   |-- Contracts/
|   |   |-- Ekyc/
|   |   |   |-- EkycVerificationProvider.php
|   |   |   `-- IdDocumentTextExtractor.php
|   |   |-- Otp/
|   |   |   `-- OtpServiceInterface.php
|   |   |-- Signature/
|   |   |   `-- SignatureEngineInterface.php
|   |   |-- Sms/
|   |   |   `-- SmsProviderInterface.php
|   |   |-- CertificateAuthorityKeyStore.php
|   |   |-- HsmService.php
|   |   |-- PadesSigningContract.php
|   |   |-- SignerKeyStore.php
|   |   `-- SignerSealProvider.php
|   |-- Data/
|   |   |-- ChatbotReply.php
|   |   |-- FieldSignatureCaptureResult.php
|   |   `-- SignerSealResult.php
|   |-- Enums/
|   |   |-- DocumentSignerStatus.php
|   |   |-- DocumentStatus.php
|   |   |-- EInvoiceStatus.php
|   |   |-- EkycStatus.php
|   |   |-- NotaryCredentialStatus.php
|   |   |-- NotaryGeoVerificationStatus.php
|   |   |-- NotaryIdentityVerificationStatus.php
|   |   |-- NotaryRequestStatus.php
|   |   |-- OnboardingStep.php
|   |   |-- OrganizationRole.php
|   |   |-- PaymentStatus.php
|   |   |-- SignatureFieldType.php
|   |   |-- SigningMethod.php
|   |   |-- TemplateRoleType.php
|   |   |-- TemplateSigningMethod.php
|   |   |-- UserRole.php
|   |   `-- UserWorkspace.php
|   |-- Events/
|   |   |-- DocumentCompleted.php
|   |   |-- DocumentSent.php
|   |   |-- DocumentSignerCompleted.php
|   |   |-- NotaryRequestApproved.php
|   |   |-- NotaryRequestDigitalized.php
|   |   |-- NotaryRequestNotarized.php
|   |   |-- NotaryRequestStatusUpdated.php
|   |   |-- NotaryRequestSubmitted.php
|   |   |-- NotarySessionScheduled.php
|   |   `-- SignerSessionUpdated.php
|   |-- Exceptions/
|   |   |-- CscApiException.php
|   |   |-- EkycOcrUnavailableException.php
|   |   `-- SadNotFoundException.php
|   |-- Http/
|   |   |-- Controllers/
|   |   |   |-- Admin/
|   |   |   |   `-- SignatureComplianceController.php
|   |   |   |-- Api/
|   |   |   |   |-- BookingController.php
|   |   |   |   |-- CmpController.php
|   |   |   |   |-- CrlController.php
|   |   |   |   |-- DestinationController.php
|   |   |   |   |-- EkycTokenController.php
|   |   |   |   |-- GatewayHubWebhookController.php
|   |   |   |   |-- HsmController.php
|   |   |   |   |-- NotaryRequestStatusController.php
|   |   |   |   |-- OcspController.php
|   |   |   |   |-- ScanController.php
|   |   |   |   |-- ScepController.php
|   |   |   |   `-- SumsubWebhookController.php
|   |   |   |-- Auth/
|   |   |   |   |-- OnboardingRedirectController.php
|   |   |   |   |-- RecoveryCodesDownloadController.php
|   |   |   |   |-- ResetSessionController.php
|   |   |   |   |-- TwoFactorChallengeController.php
|   |   |   |   `-- VerifyEmailController.php
|   |   |   |-- Signature/
|   |   |   |   `-- CscOAuthController.php
|   |   |   |-- AIController.php
|   |   |   |-- Controller.php
|   |   |   |-- DocumentCertificateController.php
|   |   |   |-- DocumentDownloadController.php
|   |   |   |-- DocumentPrepareController.php
|   |   |   |-- DocumentSignerPagesController.php
|   |   |   |-- DocumentStreamController.php
|   |   |   |-- EmailInfrastructureExampleController.php
|   |   |   |-- EnotarySignerVideoJoinController.php
|   |   |   |-- MarketingChatbotController.php
|   |   |   |-- MarketingFeatureController.php
|   |   |   |-- MobileVerificationController.php
|   |   |   |-- NotaryCredentialDocumentController.php
|   |   |   |-- NotaryDocumentSignerSignatureImageController.php
|   |   |   |-- NotaryIdentityVerificationImageController.php
|   |   |   |-- NotaryPaymentLinkController.php
|   |   |   |-- NotaryRegisterEvidenceImageController.php
|   |   |   |-- NotaryRegisterEvidencePathImageController.php
|   |   |   |-- NotarySettlementFeeController.php
|   |   |   |-- PublicNotaryPaymentController.php
|   |   |   |-- SignDocumentController.php
|   |   |   |-- TemplatePrepareController.php
|   |   |   |-- TemplateUseController.php
|   |   |   `-- TrustProfileAssetController.php
|   |   |-- Middleware/
|   |   |   |-- AddSecurityHeaders.php
|   |   |   |-- AllowMediaPermissions.php
|   |   |   |-- EnsureAttorneyPracticeEligible.php
|   |   |   |-- EnsureEnotaryPortalAccess.php
|   |   |   |-- EnsureOnboardingProgress.php
|   |   |   |-- EnsurePendingTwoFactorChallenge.php
|   |   |   |-- EnsureTwoFactorIsVerified.php
|   |   |   |-- EnsureTwoFactorOnboardingComplete.php
|   |   |   |-- EnsureUserRole.php
|   |   |   |-- EnsureUserWorkspace.php
|   |   |   `-- VirtualGateway.php
|   |   |-- Requests/
|   |   |   |-- Api/
|   |   |   |   |-- ScanTicketRequest.php
|   |   |   |   `-- StoreBookingRequest.php
|   |   |   |-- ContactRequest.php
|   |   |   |-- MarketingChatbotMessageRequest.php
|   |   |   |-- SendMobileOtpRequest.php
|   |   |   |-- StartTrustAuthorizationRequest.php
|   |   |   |-- StoreDocumentRequest.php
|   |   |   |-- StoreDocumentSignatureRequest.php
|   |   |   |-- StoreNotaryClientCaseRequest.php
|   |   |   |-- StoreSignatureFieldsRequest.php
|   |   |   |-- StoreTemplateFieldsRequest.php
|   |   |   |-- TwoFactorVerifyRequest.php
|   |   |   |-- UseTemplateRequest.php
|   |   |   `-- VerifyMobileOtpRequest.php
|   |   `-- Resources/
|   |       `-- Api/
|   |           |-- BookingResource.php
|   |           |-- DestinationResource.php
|   |           `-- TicketResource.php
|   |-- Jobs/
|   |   |-- GenerateCertificateJob.php
|   |   |-- GenerateDocumentPdfJob.php
|   |   |-- RefreshEInvoiceStatusJob.php
|   |   |-- SendDocumentEmailJob.php
|   |   |-- SendReminderJob.php
|   |   `-- SubmitEInvoiceJob.php
|   |-- Livewire/
|   |   |-- Actions/
|   |   |   `-- Logout.php
|   |   |-- Signature/
|   |   |   |-- CscCredentialSelector.php
|   |   |   `-- TrustAuthorizationStatus.php
|   |   `-- DocumentSignersManager.php
|   |-- Mail/
|   |   |-- AttorneyApplicationApprovedMail.php
|   |   |-- AttorneyApplicationRejectedMail.php
|   |   |-- AttorneyApplicationSubmittedMail.php
|   |   |-- DocumentCompletedMail.php
|   |   |-- DocumentSignedMail.php
|   |   |-- EmailOtpVerificationMail.php
|   |   |-- EnotarySignerInvitationMail.php
|   |   |-- NotaryDocumentSignerSignedMail.php
|   |   |-- NotaryPaymentReadyMail.php
|   |   |-- NotaryRequestApprovedMail.php
|   |   |-- NotaryRequestDigitalizedMail.php
|   |   |-- NotaryRequestDigitalizedPartyMail.php
|   |   |-- NotaryRequestNotarizedMail.php
|   |   |-- NotaryRequestSubmittedMail.php
|   |   |-- NotarySessionScheduledMail.php
|   |   |-- NotarySessionVerificationCompleteMail.php
|   |   |-- NotarySignerVideoInvitationMail.php
|   |   |-- ReminderMail.php
|   |   |-- SendOtpMail.php
|   |   `-- SignerInvitationMail.php
|   |-- Models/
|   |   |-- AppNotification.php
|   |   |-- AttorneyNotarialRegistry.php
|   |   |-- BillingProfile.php
|   |   |-- CertificateAuthority.php
|   |   |-- Contact.php
|   |   |-- Document.php
|   |   |-- DocumentHash.php
|   |   |-- DocumentSigner.php
|   |   |-- EInvoice.php
|   |   |-- EInvoiceSubmission.php
|   |   |-- EkycRecord.php
|   |   |-- EnotaryInvitation.php
|   |   |-- MobileOtp.php
|   |   |-- NotarialRegisterEntry.php
|   |   |-- NotaryCredential.php
|   |   |-- NotaryGeoLog.php
|   |   |-- NotaryIdentityVerification.php
|   |   |-- NotaryJournal.php
|   |   |-- NotaryRequest.php
|   |   |-- NotarySession.php
|   |   |-- NotarySigner.php
|   |   |-- OnboardingAuditLog.php
|   |   |-- Organization.php
|   |   |-- Otp.php
|   |   |-- OtpVerification.php
|   |   |-- Payment.php
|   |   |-- Signature.php
|   |   |-- SignatureAuditEvent.php
|   |   |-- SignatureEvidenceRecord.php
|   |   |-- SignatureField.php
|   |   |-- SignerCertificate.php
|   |   |-- Tag.php
|   |   |-- Template.php
|   |   |-- TemplateField.php
|   |   |-- TemplateSigner.php
|   |   |-- TrustAuthorizationSession.php
|   |   |-- TrustedDevice.php
|   |   `-- User.php
|   |-- Policies/
|   |   |-- ContactPolicy.php
|   |   |-- DocumentPolicy.php
|   |   |-- NotaryCredentialPolicy.php
|   |   |-- NotaryIdentityVerificationPolicy.php
|   |   |-- NotaryRequestPolicy.php
|   |   |-- NotarySignerPolicy.php
|   |   |-- TagPolicy.php
|   |   |-- TemplatePolicy.php
|   |   `-- UserPolicy.php
|   |-- Providers/
|   |   |-- AppServiceProvider.php
|   |   |-- BrevoMailServiceProvider.php
|   |   |-- EkycServiceProvider.php
|   |   |-- HsmServiceProvider.php
|   |   |-- PadesServiceProvider.php
|   |   `-- VoltServiceProvider.php
|   |-- Rules/
|   |   `-- PhilippineMobileNumber.php
|   |-- Services/
|   |   |-- Admin/
|   |   |   |-- AdminUserService.php
|   |   |   |-- PlatformDashboardService.php
|   |   |   `-- UserDeletionImpactService.php
|   |   |-- Attorney/
|   |   |   `-- AttorneyDashboardService.php
|   |   |-- Compliance/
|   |   |   |-- ComplianceReportExporter.php
|   |   |   `-- SignatureComplianceService.php
|   |   |-- Ekyc/
|   |   |   |-- Sumsub/
|   |   |   |   |-- SumsubAccessTokenService.php
|   |   |   |   |-- SumsubApiClient.php
|   |   |   |   |-- SumsubEkycProvider.php
|   |   |   |   `-- SumsubWebhookHandler.php
|   |   |   |-- EkycNameMatcher.php
|   |   |   |-- EkycNameMatchResult.php
|   |   |   |-- EkycNameVerificationService.php
|   |   |   |-- EkycProviderManager.php
|   |   |   |-- EkycProviderResult.php
|   |   |   |-- EkycVerificationRequest.php
|   |   |   |-- TesseractEkycProvider.php
|   |   |   `-- TesseractIdDocumentTextExtractor.php
|   |   |-- Notary/
|   |   |   `-- NotarySealProfileService.php
|   |   |-- Signature/
|   |   |   |-- BasicElectronicSignatureDriver.php
|   |   |   |-- CscApiClient.php
|   |   |   |-- CscSigningOrchestrator.php
|   |   |   |-- FuturePKISignatureDriver.php
|   |   |   |-- LtvEmbedder.php
|   |   |   |-- PadesDigestPreparer.php
|   |   |   |-- PadesSignatureEmbedder.php
|   |   |   |-- PadesSigningService.php
|   |   |   |-- SadLifecycleService.php
|   |   |   `-- TimestampAuthorityClient.php
|   |   |-- TrustProfile/
|   |   |   `-- TrustProfileService.php
|   |   |-- AppManagedSignerSealProvider.php
|   |   |-- AttorneyApplicationService.php
|   |   |-- AttorneyNotarialRegistryService.php
|   |   |-- AwsCloudHsmService.php
|   |   |-- BlockchainProofService.php
|   |   |-- CertificateAuthorityService.php
|   |   |-- CertificateVerificationService.php
|   |   |-- ChatbotFaqMatcher.php
|   |   |-- CmpService.php
|   |   |-- CompletedDocumentArtifactService.php
|   |   |-- CompletedDocumentSealingService.php
|   |   |-- CrlGenerator.php
|   |   |-- DatabaseCertificateAuthorityKeyStore.php
|   |   |-- DatabaseSignerKeyStore.php
|   |   |-- DedicatedVirtualGateway.php
|   |   |-- DocumentArchiveService.php
|   |   |-- DocumentCertificateService.php
|   |   |-- DocumentHashService.php
|   |   |-- DocumentNotificationService.php
|   |   |-- DocumentPdfStampingService.php
|   |   |-- DocumentSigningWorkflowService.php
|   |   |-- DocumentStorageService.php
|   |   |-- EInvoiceService.php
|   |   |-- EInvoiceSubmissionRetentionService.php
|   |   |-- EisAuthService.php
|   |   |-- EisCryptoService.php
|   |   |-- EisInquiryService.php
|   |   |-- EisInvoicePayloadFactory.php
|   |   |-- EisSubmissionService.php
|   |   |-- EnotaryInvitationService.php
|   |   |-- FieldSignatureCaptureService.php
|   |   |-- FileBackedCertificateAuthorityKeyStore.php
|   |   |-- GatewayHubService.php
|   |   |-- GeolocationService.php
|   |   |-- HsmAuditLogger.php
|   |   |-- HsmCertificateAuthorityService.php
|   |   |-- HsmHealthMonitor.php
|   |   |-- HsmKeyManager.php
|   |   |-- HsmPkiSignatureService.php
|   |   |-- HsmSignerSealProvider.php
|   |   |-- HsmVirtualGateway.php
|   |   |-- HybridChatbotService.php
|   |   |-- IdentityVerificationService.php
|   |   |-- LocationVerificationService.php
|   |   |-- MarketingChatbotService.php
|   |   |-- MockHsmService.php
|   |   |-- NotarialCertificateService.php
|   |   |-- NotarialRegisterService.php
|   |   |-- NotaryDigitalizationService.php
|   |   |-- NotaryJitsiRoomService.php
|   |   |-- NotaryNotificationService.php
|   |   |-- NotaryParticipantSyncService.php
|   |   |-- NotaryPaymentService.php
|   |   |-- NotaryPublicPaymentLinkService.php
|   |   |-- NotaryRequestStatusPayloadService.php
|   |   |-- NotaryRequestWorkflowService.php
|   |   |-- NotarySchedulingService.php
|   |   |-- NotarySealService.php
|   |   |-- NotarySignerVideoInvitationService.php
|   |   |-- NotarySigningProgressService.php
|   |   |-- OcspResponder.php
|   |   |-- OnboardingAuditLogger.php
|   |   |-- OtpAuditLogger.php
|   |   |-- OtpService.php
|   |   |-- Pkcs10Request.php
|   |   |-- Pkcs7SignedData.php
|   |   |-- PkiSignatureService.php
|   |   |-- RemoteManagedSignerSealProvider.php
|   |   |-- ScepService.php
|   |   |-- SemaphoreService.php
|   |   |-- SendDocumentForSignatureService.php
|   |   |-- SignatureAuditLogger.php
|   |   |-- SignatureEvidenceRecordService.php
|   |   |-- SignerCertificateRevocationService.php
|   |   |-- SignerCertificateService.php
|   |   |-- SignerSealProviderManager.php
|   |   |-- SignerSessionPayloadService.php
|   |   |-- SigningMethodService.php
|   |   |-- SmimeCertificateService.php
|   |   |-- SmsService.php
|   |   |-- ThalesHsmService.php
|   |   |-- TimestampEvidenceValidator.php
|   |   |-- TrustedDeviceService.php
|   |   |-- TwoFactorAuthenticationService.php
|   |   `-- UtimacoHsmService.php
|   |-- Support/
|   |   |-- AuthSession.php
|   |   |-- MarketingFeatures.php
|   |   |-- MarketingKnowledge.php
|   |   |-- php_compat.php
|   |   |-- PublicPdfStream.php
|   |   |-- RotatableFpdi.php
|   |   |-- SignatureFeatures.php
|   |   `-- TrustLevel.php
|   |-- Trust/
|   |   |-- Authorization/
|   |   |   |-- RemoteAuthorizationClient.php
|   |   |   |-- TrustAuthorizationMaterial.php
|   |   |   |-- TrustAuthorizationRequiredException.php
|   |   |   |-- TrustAuthorizationSessionService.php
|   |   |   `-- TrustAuthorizationWorkflowService.php
|   |   |-- RemoteSigning/
|   |   |   |-- RemoteSignatureMaterial.php
|   |   |   |-- RemoteSignatureResponseMapper.php
|   |   |   `-- RemoteSigningClient.php
|   |   `-- Verification/
|   |       `-- CertificateChainValidator.php
|   `-- View/
|       `-- Breadcrumbs.php
|-- blockchain/
|   |-- artifacts/
|   |   |-- build-info/
|   |   |   `-- 61a7e40591e827e9560492d6ac9137c1.json
|   |   `-- contracts/
|   |       |-- DocumentNotary.sol/
|   |       |   |-- DocumentNotary.dbg.json
|   |       |   `-- DocumentNotary.json
|   |       `-- DocuTrustRegistry.sol/
|   |           |-- DocuTrustRegistry.dbg.json
|   |           `-- DocuTrustRegistry.json
|   |-- cache/
|   |   `-- solidity-files-cache.json
|   |-- contracts/
|   |   |-- DocumentNotary.sol
|   |   `-- DocuTrustRegistry.sol
|   |-- scripts/
|   |   |-- deploy-document-notary.js
|   |   |-- deploy.js
|   |   `-- test-wallet.js
|   |-- .env
|   |-- .env.example
|   |-- hardhat.config.js
|   |-- package-lock.json
|   |-- package.json
|   `-- README.md
|-- blockchain-service/
|   |-- src/
|   |   |-- contractAbi.js
|   |   `-- index.js
|   |-- .env.example
|   |-- package-lock.json
|   `-- package.json
|-- bootstrap/
|   |-- cache/
|   |   |-- .gitignore
|   |   |-- packages.php
|   |   |-- ser54B9.tmp
|   |   |-- ser5E6.tmp
|   |   |-- ser5E7.tmp
|   |   `-- services.php
|   |-- app.php
|   `-- providers.php
|-- config/
|   |-- openssl/
|   |   `-- docutrust-openssl.cnf
|   |-- app.php
|   |-- auth.php
|   |-- broadcasting.php
|   |-- cache.php
|   |-- chatbot.php
|   |-- database.php
|   |-- docutrust.php
|   |-- ekyc.php
|   |-- filesystems.php
|   |-- hsm.php
|   |-- livewire.php
|   |-- logging.php
|   |-- mail.php
|   |-- otp.php
|   |-- queue.php
|   |-- reverb.php
|   |-- services.php
|   |-- session.php
|   `-- signature.php
|-- database/
|   |-- factories/
|   |   |-- AttorneyNotarialRegistryFactory.php
|   |   |-- ContactFactory.php
|   |   |-- DocumentFactory.php
|   |   |-- DocumentSignerFactory.php
|   |   |-- NotarialRegisterEntryFactory.php
|   |   |-- NotaryCredentialFactory.php
|   |   |-- NotaryGeoLogFactory.php
|   |   |-- NotaryIdentityVerificationFactory.php
|   |   |-- NotaryJournalFactory.php
|   |   |-- NotaryRequestFactory.php
|   |   |-- NotarySessionFactory.php
|   |   |-- NotarySignerFactory.php
|   |   |-- OrganizationFactory.php
|   |   |-- SignatureFactory.php
|   |   |-- SignatureFieldFactory.php
|   |   |-- TagFactory.php
|   |   |-- TemplateFactory.php
|   |   |-- TemplateFieldFactory.php
|   |   |-- TemplateSignerFactory.php
|   |   |-- TrustAuthorizationSessionFactory.php
|   |   `-- UserFactory.php
|   |-- migrations/
|   |   |-- 0001_01_01_000000_create_users_table.php
|   |   |-- 0001_01_01_000001_create_cache_table.php
|   |   |-- 0001_01_01_000002_create_jobs_table.php
|   |   |-- 2026_04_02_144125_create_documents_table.php
|   |   |-- 2026_04_02_144126_create_document_signers_table.php
|   |   |-- 2026_04_02_144127_create_signatures_table.php
|   |   |-- 2026_04_02_150041_add_sent_at_to_documents_table.php
|   |   |-- 2026_04_02_154427_create_signature_fields_table.php
|   |   |-- 2026_04_02_154737_add_signature_field_id_to_signatures_table.php
|   |   |-- 2026_04_02_160618_create_templates_table.php
|   |   |-- 2026_04_02_160619_create_template_signers_table.php
|   |   |-- 2026_04_02_160620_create_template_fields_table.php
|   |   |-- 2026_04_02_160627_add_role_name_to_document_signers_table.php
|   |   |-- 2026_04_02_164636_upgrade_templates_for_builder.php
|   |   |-- 2026_04_02_170902_create_tags_table.php
|   |   |-- 2026_04_02_172518_create_contacts_table.php
|   |   |-- 2026_04_02_173132_create_signature_audit_events_table.php
|   |   |-- 2026_04_03_042453_add_two_factor_onboarding_completed_at_to_users_table.php
|   |   |-- 2026_04_03_120000_extend_signature_audit_events_and_documents_files.php
|   |   |-- 2026_04_03_160000_add_role_and_two_factor_to_users_table.php
|   |   |-- 2026_04_29_130003_add_access_token_and_expires_at_to_document_signers_table.php
|   |   |-- 2026_04_29_130659_create_document_hashes_table.php
|   |   |-- 2026_04_29_131210_create_app_notifications_table.php
|   |   |-- 2026_04_29_133130_add_certificate_path_to_documents_table.php
|   |   |-- 2026_04_29_150018_add_page_number_to_signature_fields_table.php
|   |   |-- 2026_04_29_150554_create_document_tag_table.php
|   |   |-- 2026_04_29_151553_add_performance_indexes_to_core_tables.php
|   |   |-- 2026_04_29_161550_add_pki_keys_to_document_signers_table.php
|   |   |-- 2026_04_29_161551_add_crypto_signature_fields_to_signatures_table.php
|   |   |-- 2026_04_29_165057_create_organizations_table.php
|   |   |-- 2026_04_29_165058_add_multi_tenant_columns.php
|   |   |-- 2026_04_29_193656_add_onboarding_step_to_users_table.php
|   |   |-- 2026_04_29_194630_add_ekyc_status_to_users_table.php
|   |   |-- 2026_04_29_194631_create_ekyc_records_table.php
|   |   |-- 2026_04_29_194632_create_onboarding_audit_logs_table.php
|   |   |-- 2026_05_02_120000_add_onboarding_otp_columns_and_integer_onboarding_step.php
|   |   |-- 2026_05_03_160000_create_certificate_authorities_table.php
|   |   |-- 2026_05_03_160100_create_signer_certificates_table.php
|   |   |-- 2026_05_03_160200_add_signer_certificate_to_signatures_table.php
|   |   |-- 2026_05_03_230000_add_generated_pdf_paths_to_documents_table.php
|   |   |-- 2026_05_05_152504_create_mobile_otps_table.php
|   |   |-- 2026_05_05_200000_add_signing_flow_indexes.php
|   |   |-- 2026_05_05_210000_add_submitted_value_to_signatures_table.php
|   |   |-- 2026_05_06_120000_add_signing_provider_fields_to_signatures_table.php
|   |   |-- 2026_05_06_130000_add_remote_certificate_fields_to_signer_certificates_table.php
|   |   |-- 2026_05_06_140000_add_signing_provider_payload_to_signatures_table.php
|   |   |-- 2026_05_06_150000_add_remote_credential_id_to_document_signers_table.php
|   |   |-- 2026_05_06_160000_create_trust_authorization_sessions_table.php
|   |   |-- 2026_05_06_170000_add_access_password_fields_to_documents_table.php
|   |   |-- 2026_05_06_180000_add_signing_workflow_and_archive_fields_to_documents_table.php
|   |   |-- 2026_05_09_021000_create_otps_table.php
|   |   |-- 2026_05_09_024500_add_mfa_recovery_fields_to_users_table.php
|   |   |-- 2026_05_09_031000_create_trusted_devices_table.php
|   |   |-- 2026_05_09_040000_add_signing_method_and_user_to_document_signers_table.php
|   |   |-- 2026_05_09_110000_align_template_signing_methods_with_document_signers.php
|   |   |-- 2026_05_09_120000_add_email_copy_fields_to_documents_table.php
|   |   |-- 2026_05_09_130000_add_audit_fields_to_documents_table.php
|   |   |-- 2026_05_09_140000_add_role_type_to_document_signers_table.php
|   |   |-- 2026_05_11_150000_create_notary_requests_table.php
|   |   |-- 2026_05_11_150100_create_notary_sessions_table.php
|   |   |-- 2026_05_11_150200_create_notary_journals_table.php
|   |   |-- 2026_05_11_150300_add_notary_request_id_to_documents_table.php
|   |   |-- 2026_05_11_173000_rename_legacy_user_roles.php
|   |   |-- 2026_05_12_100000_create_notary_credentials_table.php
|   |   |-- 2026_05_12_100100_create_notarial_register_entries_table.php
|   |   |-- 2026_05_12_100200_add_identity_and_location_fields_to_notary_requests_table.php
|   |   |-- 2026_05_12_100300_add_verification_checklist_to_notary_sessions_table.php
|   |   |-- 2026_05_12_110000_add_page_and_book_number_to_notarial_register_entries_table.php
|   |   |-- 2026_05_12_140000_enotary_workflow_tables.php
|   |   |-- 2026_05_14_000001_add_hsm_key_id_to_document_signers.php
|   |   |-- 2026_05_14_000002_create_hsm_key_audit_log_table.php
|   |   |-- 2026_05_19_093438_add_name_parts_to_users_table.php
|   |   |-- 2026_05_19_093439_add_ekyc_ocr_fields_to_ekyc_records_table.php
|   |   |-- 2026_05_19_100448_add_trust_profile_fields_to_users_table.php
|   |   |-- 2026_05_20_120000_create_payments_table.php
|   |   |-- 2026_05_20_140000_create_billing_profiles_table.php
|   |   |-- 2026_05_20_140100_create_einvoices_table.php
|   |   |-- 2026_05_20_140200_create_einvoice_submissions_table.php
|   |   |-- 2026_05_20_160000_create_signature_compliance_tables.php
|   |   |-- 2026_05_20_170000_add_deactivated_at_to_users_table.php
|   |   |-- 2026_05_21_170831_add_workspace_to_users_table.php
|   |   |-- 2026_05_21_181806_create_enotary_invitations_table.php
|   |   |-- 2026_05_22_100000_add_sumsub_fields_to_ekyc_records_table.php
|   |   |-- 2026_05_22_120000_add_scale_indexes_for_notary_request_dashboard.php
|   |   |-- 2026_05_22_120000_extend_notary_credentials_for_attorney_applications.php
|   |   |-- 2026_05_22_130000_add_scale_indexes_for_einvoice_dashboard.php
|   |   |-- 2026_05_22_140000_add_payload_pruned_at_to_einvoice_submissions_table.php
|   |   |-- 2026_05_25_081956_create_otp_verifications_table.php
|   |   |-- 2026_05_25_100000_add_allowed_pages_to_document_signers_table.php
|   |   |-- 2026_05_25_174453_add_signer_scope_to_notary_sessions_table.php
|   |   |-- 2026_05_27_180953_create_attorney_notarial_registries_table.php
|   |   |-- 2026_05_30_183204_add_registry_fields_completed_at_to_attorney_notarial_registries_table.php
|   |   |-- 2026_06_22_073046_add_pades_csc_fields_to_documents_table.php
|   |   |-- 2026_06_22_073952_add_sad_lifecycle_fields_to_trust_authorization_sessions_table.php
|   |   |-- 2026_06_22_075838_add_pades_fields_to_signatures_table.php
|   |   |-- 2026_06_22_075839_add_csc_fields_to_signer_certificates_table.php
|   |   |-- 2026_06_22_075840_add_csc_remote_fields_to_document_signers_table.php
|   |   `-- 2026_06_22_075840_add_pades_evidence_to_signature_evidence_records_table.php
|   |-- seeders/
|   |   |-- DatabaseSeeder.php
|   |   |-- DocumentSeeder.php
|   |   |-- DocumentSignerAccountSeeder.php
|   |   |-- ENotaryAttorneySigningPhaseSeeder.php
|   |   |-- ENotarySeeder.php
|   |   |-- ENotarySignerAccountSeeder.php
|   |   |-- ProductionTestAccountSeeder.php
|   |   |-- SignerSeeder.php
|   |   |-- SuperAdminSeeder.php
|   |   `-- TemplateSeeder.php
|   `-- .gitignore
|-- deploy/
|   |-- nginx/
|   |   `-- docutrust.conf
|   |-- systemd/
|   |   |-- docutrust-blockchain.service
|   |   |-- docutrust-queue-documents.override.example.conf
|   |   |-- docutrust-queue-einvoices.override.example.conf
|   |   |-- docutrust-queue-notifications.override.example.conf
|   |   |-- docutrust-queue.env.example
|   |   |-- docutrust-queue.service
|   |   |-- docutrust-queue@.service
|   |   `-- docutrust-reverb.service
|   `-- production.env.example
|-- docs/
|   |-- pki-refactor-plan.html
|   `-- pki-refactor-plan.pdf
|-- infrastructure/
|   |-- nginx/
|   |   `-- vgw.conf
|   |-- wireguard/
|   |   `-- wg0.conf.example
|   `-- docker-compose.vgw.yml
|-- invoice-guide/
|   |-- 09.26.2022_EIS_e-invoice_API_development_guide_v2.2.pdf
|   |-- EIS e-invoice JSON File format v2.01_3.25.22_BIR_updated_f.xlsx
|   |-- EIS_Certification_Public_Key.txt
|   `-- LT_EIS_CERT_User_Guide_20220520_V1.2.pdf
|-- load-tests/
|   `-- k6/
|       |-- lib/
|       |   |-- config.js
|       |   `-- http.js
|       |-- authenticated-documents.js
|       |-- public-signing.js
|       |-- README.md
|       `-- webhook-burst.js
|-- public/
|   |-- build/
|   |   |-- assets/
|   |   |   |-- ___vite-browser-external_commonjs-proxy-PNnQjyI8.js
|   |   |   |-- app-F8srIH8W.js
|   |   |   |-- app-UjL54ReR.css
|   |   |   |-- auto-CpL4W96M.js
|   |   |   |-- fabric-fMFCTjh2.js
|   |   |   |-- idle-session-DcgLrHnt.js
|   |   |   |-- notary-status-poll-BvuWGnsN.js
|   |   |   |-- pdf-BhU4HfYe.js
|   |   |   |-- pdf.worker.min-8Tmxngc5.js
|   |   |   |-- pdf.worker.min-DKQKFyKK.js
|   |   |   |-- sign-view-BHmQZIDG.js
|   |   |   `-- template-prepare-cc4rVmVe.js
|   |   `-- manifest.json
|   |-- images/
|   |   |-- about-us.jpg
|   |   |-- CSC logo dark theme.png
|   |   |-- CSC logo light theme.png
|   |   |-- docutrust-logo.png
|   |   |-- DocuTrust_adv.mp4
|   |   `-- surepay.png
|   |-- .htaccess
|   |-- favicon.ico
|   |-- hot
|   |-- index.php
|   `-- robots.txt
|-- resources/
|   |-- css/
|   |   `-- app.css
|   |-- js/
|   |   |-- document-prepare/
|   |   |   |-- assets.js
|   |   |   |-- fabric-fields.js
|   |   |   |-- field-types.js
|   |   |   `-- geometry.js
|   |   |-- app.js
|   |   |-- echo.js
|   |   |-- idle-session.js
|   |   |-- notary-status-poll.js
|   |   |-- sign-assets.js
|   |   |-- sign-view.js
|   |   `-- template-prepare.js
|   `-- views/
|       |-- auth/
|       |   `-- two-factor.blade.php
|       |-- certificates/
|       |   |-- completion.blade.php
|       |   `-- notarial.blade.php
|       |-- compliance/
|       |   `-- report.blade.php
|       |-- components/
|       |   |-- admin/
|       |   |   `-- page.blade.php
|       |   |-- auth/
|       |   |   |-- onboarding-progress.blade.php
|       |   |   |-- onboarding-wizard-shell.blade.php
|       |   |   `-- otp-inputs.blade.php
|       |   |-- idle-session/
|       |   |   `-- modal.blade.php
|       |   |-- layouts/
|       |   |   |-- app/
|       |   |   |   |-- header.blade.php
|       |   |   |   |-- sidebar.blade.php
|       |   |   |   `-- top-bar.blade.php
|       |   |   |-- auth/
|       |   |   |   |-- card.blade.php
|       |   |   |   |-- register.blade.php
|       |   |   |   |-- simple.blade.php
|       |   |   |   `-- split.blade.php
|       |   |   |-- app.blade.php
|       |   |   |-- auth.blade.php
|       |   |   |-- editor.blade.php
|       |   |   `-- guest-simple.blade.php
|       |   |-- settings/
|       |   |   |-- layout.blade.php
|       |   |   |-- tab-nav.blade.php
|       |   |   `-- trust-layout.blade.php
|       |   |-- trust-profile/
|       |   |   `-- verification-card.blade.php
|       |   |-- user/
|       |   |   |-- mobile-verification-status.blade.php
|       |   |   |-- profile-dropdown-header.blade.php
|       |   |   `-- profile-menu.blade.php
|       |   |-- action-message.blade.php
|       |   |-- app-breadcrumbs.blade.php
|       |   |-- app-logo-icon.blade.php
|       |   |-- app-logo.blade.php
|       |   |-- auth-header.blade.php
|       |   |-- auth-session-status.blade.php
|       |   |-- document-status-badge.blade.php
|       |   |-- marketing-chatbot.blade.php
|       |   |-- placeholder-pattern.blade.php
|       |   |-- template-stepper.blade.php
|       |   `-- text-link.blade.php
|       |-- documents/
|       |   `-- prepare.blade.php
|       |-- emails/
|       |   |-- document-completed.blade.php
|       |   |-- document-signed.blade.php
|       |   |-- enotary-signer-invitation.blade.php
|       |   |-- otp.blade.php
|       |   |-- reminder.blade.php
|       |   `-- signer-invitation.blade.php
|       |-- enotary/
|       |   `-- video-link-invalid.blade.php
|       |-- errors/
|       |   `-- generic.blade.php
|       |-- features/
|       |   |-- partials/
|       |   |   `-- icon.blade.php
|       |   `-- show.blade.php
|       |-- flux/
|       |   |-- icon/
|       |   |   |-- book-open-text.blade.php
|       |   |   |-- chevrons-up-down.blade.php
|       |   |   |-- folder-git-2.blade.php
|       |   |   `-- layout-grid.blade.php
|       |   `-- navlist/
|       |       `-- group.blade.php
|       |-- layouts/
|       |   |-- partials/
|       |   |   |-- marketing-color-scheme-script.blade.php
|       |   |   |-- marketing-header-scripts.blade.php
|       |   |   |-- marketing-header-styles.blade.php
|       |   |   |-- marketing-header.blade.php
|       |   |   |-- marketing-styles.blade.php
|       |   |   `-- marketing-theme-variables.blade.php
|       |   `-- marketing.blade.php
|       |-- livewire/
|       |   |-- admin/
|       |   |   |-- attorney-applications-index.blade.php
|       |   |   |-- attorney-applications-show.blade.php
|       |   |   |-- compliance-dashboard.blade.php
|       |   |   |-- platform-dashboard.blade.php
|       |   |   `-- users-index.blade.php
|       |   |-- auth/
|       |   |   |-- confirm-password.blade.php
|       |   |   |-- enotary-invite-accept.blade.php
|       |   |   |-- forgot-password.blade.php
|       |   |   |-- login.blade.php
|       |   |   |-- onboarding-email-verify.blade.php
|       |   |   |-- onboarding-kyc.blade.php
|       |   |   |-- onboarding-mfa.blade.php
|       |   |   |-- onboarding-mobile.blade.php
|       |   |   |-- register-two-factor.blade.php
|       |   |   |-- register.blade.php
|       |   |   |-- reset-password.blade.php
|       |   |   `-- verify-email.blade.php
|       |   |-- contacts/
|       |   |   `-- index.blade.php
|       |   |-- documents/
|       |   |   |-- create.blade.php
|       |   |   |-- index.blade.php
|       |   |   `-- show.blade.php
|       |   |-- notary/
|       |   |   |-- attorney-registries/
|       |   |   |   `-- index.blade.php
|       |   |   |-- partials/
|       |   |   |   `-- notarial-register-entry-table.blade.php
|       |   |   |-- attorney-registry.blade.php
|       |   |   |-- credentials.blade.php
|       |   |   |-- dashboard.blade.php
|       |   |   `-- register-entry.blade.php
|       |   |-- notary-admin/
|       |   |   |-- billing-profile.blade.php
|       |   |   |-- dashboard.blade.php
|       |   |   `-- einvoices.blade.php
|       |   |-- notary-requests/
|       |   |   |-- show/
|       |   |   |   |-- partials/
|       |   |   |   |   |-- case-completion-panel.blade.php
|       |   |   |   |   |-- case-workflow-sidebar.blade.php
|       |   |   |   |   |-- client-portal-timeline.blade.php
|       |   |   |   |   |-- digitalize-prerequisites.blade.php
|       |   |   |   |   |-- do-this-now-card.blade.php
|       |   |   |   |   |-- do-this-now-inline-fee.blade.php
|       |   |   |   |   |-- e-invoice-status-actions.blade.php
|       |   |   |   |   |-- legacy-session-signer-portal.blade.php
|       |   |   |   |   |-- mobile-action-bar.blade.php
|       |   |   |   |   |-- next-step-card.blade.php
|       |   |   |   |   |-- notary-status-poll-config.blade.php
|       |   |   |   |   |-- primary-action-button.blade.php
|       |   |   |   |   |-- section-completed.blade.php
|       |   |   |   |   |-- section-payment.blade.php
|       |   |   |   |   |-- section-settlement-fee.blade.php
|       |   |   |   |   |-- settlement-checklist.blade.php
|       |   |   |   |   |-- settlement-client-portal.blade.php
|       |   |   |   |   |-- settlement-scroll.blade.php
|       |   |   |   |   |-- settlement-sub-nav.blade.php
|       |   |   |   |   |-- signing-progress.blade.php
|       |   |   |   |   |-- status-badge.blade.php
|       |   |   |   |   |-- tab-audit.blade.php
|       |   |   |   |   |-- tab-closing.blade.php
|       |   |   |   |   |-- tab-documents.blade.php
|       |   |   |   |   |-- tab-parties.blade.php
|       |   |   |   |   |-- tab-session-card.blade.php
|       |   |   |   |   |-- tab-session.blade.php
|       |   |   |   |   |-- video-join-link.blade.php
|       |   |   |   |   |-- video-party-actions.blade.php
|       |   |   |   |   |-- video-party-queue-row.blade.php
|       |   |   |   |   |-- video-session-checklist.blade.php
|       |   |   |   |   |-- video-verification-queue.blade.php
|       |   |   |   |   `-- workflow-compact.blade.php
|       |   |   |   |-- legacy-layout.blade.php
|       |   |   |   `-- task-focused.blade.php
|       |   |   |-- create.blade.php
|       |   |   |-- index.blade.php
|       |   |   |-- session-live.blade.php
|       |   |   `-- show.blade.php
|       |   |-- pages/
|       |   |   |-- dashboard.blade.php
|       |   |   `-- verify.blade.php
|       |   |-- settings/
|       |   |   |-- attorney-application.blade.php
|       |   |   |-- delete-user-form.blade.php
|       |   |   |-- profile.blade.php
|       |   |   `-- trust-profile.blade.php
|       |   |-- signature/
|       |   |   |-- csc-credential-selector.blade.php
|       |   |   `-- trust-authorization-status.blade.php
|       |   |-- templates/
|       |   |   |-- index.blade.php
|       |   |   |-- use.blade.php
|       |   |   `-- wizard.blade.php
|       |   `-- document-signers-manager.blade.php
|       |-- mail/
|       |   |-- attorney/
|       |   |   |-- application-approved.blade.php
|       |   |   |-- application-rejected.blade.php
|       |   |   `-- application-submitted.blade.php
|       |   |-- notary/
|       |   |   |-- document-signer-signed.blade.php
|       |   |   |-- payment-ready.blade.php
|       |   |   |-- request-approved.blade.php
|       |   |   |-- request-digitalized-party.blade.php
|       |   |   |-- request-digitalized.blade.php
|       |   |   |-- request-notarized.blade.php
|       |   |   |-- request-submitted.blade.php
|       |   |   |-- session-scheduled.blade.php
|       |   |   |-- session-verification-complete.blade.php
|       |   |   `-- signer-video-invitation.blade.php
|       |   |-- document-sent.blade.php
|       |   `-- email-otp-verification-text.blade.php
|       |-- notary/
|       |   |-- public-payment.blade.php
|       |   `-- verify.blade.php
|       |-- partials/
|       |   |-- head.blade.php
|       |   |-- idle-session.blade.php
|       |   `-- settings-heading.blade.php
|       |-- sign/
|       |   |-- invalid.blade.php
|       |   `-- show.blade.php
|       |-- templates/
|       |   `-- prepare.blade.php
|       `-- welcome.blade.php
|-- routes/
|   |-- api.php
|   |-- auth.php
|   |-- channels.php
|   |-- cmp.php
|   |-- console.php
|   |-- crl.php
|   |-- hsm.php
|   |-- ocsp.php
|   |-- scep.php
|   `-- web.php
|-- scripts/
|   |-- check-queue-depth.ps1
|   |-- check-queue-depth.sh
|   |-- check-queue-workers.ps1
|   |-- check-queue-workers.sh
|   |-- deploy.sh
|   `-- post-deploy.sh
|-- tests/
|   |-- Feature/
|   |   |-- Auth/
|   |   |   |-- AuthenticationTest.php
|   |   |   |-- EmailVerificationTest.php
|   |   |   |-- PasswordConfirmationTest.php
|   |   |   |-- PasswordResetTest.php
|   |   |   |-- RegistrationTest.php
|   |   |   |-- ResetSessionTest.php
|   |   |   `-- TwoFactorChallengeTest.php
|   |   |-- Ekyc/
|   |   |   `-- OnboardingKycNameVerificationTest.php
|   |   |-- Feature/
|   |   |   `-- Notary/
|   |   |-- Notary/
|   |   |   `-- AttorneyDashboardTest.php
|   |   |-- Onboarding/
|   |   |   `-- OnboardingEndToEndTest.php
|   |   |-- Settings/
|   |   |   |-- NotarySealProfileTest.php
|   |   |   |-- PasswordUpdateTest.php
|   |   |   |-- ProfileUpdateTest.php
|   |   |   `-- TrustProfileTest.php
|   |   |-- Signature/
|   |   |   |-- CscCredentialSelectorTest.php
|   |   |   `-- CscOAuthFlowTest.php
|   |   |-- AttorneyApplicationTest.php
|   |   |-- BlockchainRealIntegrationTest.php
|   |   |-- CertificateAuthorityKeyStoreTest.php
|   |   |-- ContactManagementTest.php
|   |   |-- CscComplianceTest.php
|   |   |-- DashboardTest.php
|   |   |-- DemoSeederTest.php
|   |   |-- DocumentFlowTest.php
|   |   |-- DocumentHashPipelineTest.php
|   |   |-- DocumentSignerWorkflowTest.php
|   |   |-- EInvoiceRecoveryCommandTest.php
|   |   |-- EmailResendAndReminderTest.php
|   |   |-- EnotaryBugConditionTest.php
|   |   |-- EnotaryInvitationTest.php
|   |   |-- EnotaryPortalAccessTest.php
|   |   |-- EnotaryPreservationTest.php
|   |   |-- EnsureTwoFactorOnboardingMiddlewareTest.php
|   |   |-- ExampleTest.php
|   |   |-- GatewayHubWebhookTest.php
|   |   |-- MarketingChatbotTest.php
|   |   |-- MarketingFeatureTest.php
|   |   |-- MobileVerificationControllerTest.php
|   |   |-- NotaryAdminBillingProfilePageTest.php
|   |   |-- NotaryAdminEInvoicesPageTest.php
|   |   |-- NotaryCasePageDuplicateRenderTest.php
|   |   |-- NotaryEnotaryFlowTest.php
|   |   |-- NotaryJitsiSchedulingTest.php
|   |   |-- NotaryRequestPagesTest.php
|   |   |-- NotaryRequestWorkflowTest.php
|   |   |-- NotarySealServiceTest.php
|   |   |-- NotarySessionLivePageTest.php
|   |   |-- NotarySigningProgressTest.php
|   |   |-- NotarySingleDocumentAndVideoInvitationTest.php
|   |   |-- NotificationSystemTest.php
|   |   |-- OnboardingFlowTest.php
|   |   |-- OtpAuditAndRateLimitTest.php
|   |   |-- OtpServiceTest.php
|   |   |-- PlatformAdminDashboardTest.php
|   |   |-- PlatformAdminTest.php
|   |   |-- PruneEInvoiceSubmissionPayloadsCommandTest.php
|   |   |-- PublicDocumentVerificationTest.php
|   |   |-- RegisterTwoFactorOnboardingTest.php
|   |   |-- SemaphoreServiceTest.php
|   |   |-- SemaphoreTestCommandTest.php
|   |   |-- SessionIntegrityTest.php
|   |   |-- SignatureCertificateVerificationTest.php
|   |   |-- SignatureComplianceTest.php
|   |   |-- SignatureEngineTest.php
|   |   |-- SignerCertificateRevocationWorkflowTest.php
|   |   |-- TemplateFlowTest.php
|   |   |-- TemplatePrepareTest.php
|   |   |-- TemplateTagTest.php
|   |   |-- TestNotaryPaymentFlowCommandTest.php
|   |   `-- WorkspaceAccountTest.php
|   |-- Unit/
|   |   |-- Ekyc/
|   |   |   `-- EkycNameMatcherTest.php
|   |   |-- Signature/
|   |   |   |-- CscApiClientTest.php
|   |   |   |-- CscSigningOrchestratorTest.php
|   |   |   |-- PadesDigestPreparerTest.php
|   |   |   `-- SadLifecycleServiceTest.php
|   |   |-- TrustProfile/
|   |   |   `-- TrustProfileServiceTest.php
|   |   |-- View/
|   |   |   `-- BreadcrumbsTest.php
|   |   |-- AttorneyNotarialRegistryServiceTest.php
|   |   |-- ChatbotFaqMatcherTest.php
|   |   |-- ExampleTest.php
|   |   |-- PadesCscMetadataTest.php
|   |   `-- QueueLaneConfigurationTest.php
|   |-- Pest.php
|   `-- TestCase.php
|-- tools/
|   |-- mailpit/
|   |   |-- LICENSE
|   |   |-- mailpit.exe
|   |   `-- README.md
|   |-- mailpit-release.json
|   |-- mailpit-windows-amd64.zip
|   `-- mailpit.exe
|-- .editorconfig
|-- .env
|-- .env.example
|-- .gitattributes
|-- .gitignore
|-- .nvmrc
|-- .phpunit.result.cache
|-- artisan
|-- bash.exe.stackdump
|-- boost.json
|-- check_org.php
|-- composer.json
|-- composer.lock
|-- csc-api.pdf
|-- csc-api.txt
|-- jaas_public.pem
|-- KIRO_TASK_HANDOFF.md
|-- package-lock.json
|-- package.json
|-- phpunit.xml
|-- README.md
|-- twalasignPage
|-- twalaSingerpage
`-- vite.config.js
```

## `routes/web.php`

```php
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
use App\Http\Controllers\NotaryDocumentSignerSignatureImageController;
use App\Http\Controllers\NotaryIdentityVerificationImageController;
use App\Http\Controllers\NotaryPaymentLinkController;
use App\Http\Controllers\NotaryRegisterEvidenceImageController;
use App\Http\Controllers\NotaryRegisterEvidencePathImageController;
use App\Http\Controllers\NotarySettlementFeeController;
use App\Http\Controllers\PublicNotaryPaymentController;
use App\Http\Controllers\Signature\CscOAuthController;
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
    Route::get('/sign/{token}/download', [SignDocumentController::class, 'downloadSignedDocument'])->name('sign.document.download');
    Route::get('/sign/{token}/signature-image/{signatureField}', [SignDocumentController::class, 'streamSignatureImage'])->name('sign.signature.image');
    Route::post('/sign/{token}', [SignDocumentController::class, 'sign'])->name('sign.store');
    Route::post('/sign/{token}/signature', [SignDocumentController::class, 'storeSignature'])->name('sign.signature.store');
    Route::post('/sign/{token}/csc/authorize', [SignDocumentController::class, 'initiateCscAuthorization'])->name('sign.csc.authorize');
    Route::post('/sign/{token}/trust/authorize', [SignDocumentController::class, 'startTrustAuthorization'])->name('sign.trust.authorize');
    Route::get('/sign/{token}/trust/authorize/{session}', [SignDocumentController::class, 'pollTrustAuthorization'])->name('sign.trust.authorize.poll');
    Route::post('/sign/{token}/complete', [SignDocumentController::class, 'complete'])->name('sign.complete');
});

Route::get('/csc/oauth/redirect',
    [CscOAuthController::class, 'redirect'])
    ->middleware('throttle:signing-links')
    ->name('csc.oauth.redirect');

Route::get('/csc/oauth/callback',
    [CscOAuthController::class, 'callback'])
    ->middleware('throttle:signing-links')
    ->name('csc.oauth.callback');

Route::middleware(['signed', 'throttle:signing-links'])->group(function () {
    Route::get('/notary/payment/{notaryRequest}', [PublicNotaryPaymentController::class, 'show'])->name('public.notary.payment.show');
    Route::get('/notary/payment/{notaryRequest}/status', [PublicNotaryPaymentController::class, 'status'])->name('public.notary.payment.status');
    Route::post('/notary/payment/{notaryRequest}', [PublicNotaryPaymentController::class, 'checkout'])->name('public.notary.payment.checkout');
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
    Route::post('notary/requests/{notaryRequest}/settlement-fee', NotarySettlementFeeController::class)->name('notary.requests.settlement-fee');
    Route::post('notary/requests/{notaryRequest}/payment-link', NotaryPaymentLinkController::class)->name('notary.requests.payment-link');
    Volt::route('notary/requests/{notaryRequest}/session/{session}', 'notary-requests.session-live')->name('notary.requests.session.live')->middleware(AllowMediaPermissions::class);
    Volt::route('notary/attorney-registries', 'notary.attorney-registries.index')->name('notary.attorney-registries.index');
    Volt::route('notary/requests/{notaryRequest}/attorney-registry', 'notary.attorney-registry')->name('notary.attorney-registry');
    Volt::route('notary/requests/{notaryRequest}/register-entry', 'notary.register-entry')->name('notary.register-entry');

    Route::get('notary/requests/{notaryRequest}/identity-verifications/{verification}/image', NotaryIdentityVerificationImageController::class)
        ->name('notary.identity-verifications.image');
    Route::get('notary/requests/{notaryRequest}/register-evidence/{evidenceIndex}/image', NotaryRegisterEvidenceImageController::class)
        ->whereNumber('evidenceIndex')
        ->name('notary.register-evidence.image');
    Route::get('notary/requests/{notaryRequest}/register-evidence-file/{encodedPath}', NotaryRegisterEvidencePathImageController::class)
        ->name('notary.register-evidence.path');
    Route::get('notary/requests/{notaryRequest}/documents/{document}/signers/{documentSigner}/signatures/{signature}/image', NotaryDocumentSignerSignatureImageController::class)
        ->name('notary.document-signers.signature-image');
    Route::get('notary/credentials/{credential}/document/{document}', NotaryCredentialDocumentController::class)
        ->name('notary.credentials.document');

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
    Route::get('settings/trust-profile/seal', [TrustProfileAssetController::class, 'seal'])->name('settings.trust-profile.seal');
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

```

## `app/Services/NotaryRequestWorkflowService.php`

```php
<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\NotaryRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\TemplateRoleType;
use App\Events\NotaryRequestApproved;
use App\Events\NotaryRequestDigitalized;
use App\Events\NotaryRequestNotarized;
use App\Events\NotaryRequestSubmitted;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryCredential;
use App\Models\NotaryJournal;
use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use App\Services\Notary\NotarySealProfileService;
use RuntimeException;

class NotaryRequestWorkflowService
{
    public function maxDocumentsPerRequest(): int
    {
        return max(1, (int) config('docutrust.notary.max_documents_per_request', 1));
    }

    public function canAttachAnotherDocument(NotaryRequest $request, ?Document $documentBeingAttached = null): bool
    {
        $request->loadCount('documents');

        if (
            $documentBeingAttached !== null
            && (int) $documentBeingAttached->notary_request_id === (int) $request->id
        ) {
            return true;
        }

        return $request->documents_count < $this->maxDocumentsPerRequest();
    }

    public function assertCanAttachDocument(NotaryRequest $request, ?Document $documentBeingAttached = null): void
    {
        if ($this->canAttachAnotherDocument($request, $documentBeingAttached)) {
            return;
        }

        throw new RuntimeException(__('This case allows only one document. Replace the existing PDF while it is still in draft, or continue with the current instrument.'));
    }

    public function documentForRequest(NotaryRequest $request): ?Document
    {
        return $request->documents()->orderBy('id')->first();
    }

    public function canVerifyIdentity(NotaryRequest $request): bool
    {
        return $request->identity_verified_at === null
            && in_array($request->status, [
                NotaryRequestStatus::Submitted,
                NotaryRequestStatus::IdentityReviewRequired,
                NotaryRequestStatus::LocationReviewRequired,
                NotaryRequestStatus::LocationVerified,
                NotaryRequestStatus::SessionScheduled,
                NotaryRequestStatus::SessionInProgress,
                NotaryRequestStatus::SessionCompleted,
                NotaryRequestStatus::AttorneySigning,
            ], true);
    }

    public function canVerifyLocation(NotaryRequest $request): bool
    {
        return $request->location_verified_at === null
            && in_array($request->status, [
                NotaryRequestStatus::Submitted,
                NotaryRequestStatus::IdentityReviewRequired,
                NotaryRequestStatus::IdentityVerified,
                NotaryRequestStatus::LocationReviewRequired,
            ], true);
    }

    public function canScheduleSession(NotaryRequest $request): bool
    {
        return in_array($request->status, [
            NotaryRequestStatus::Submitted,
            NotaryRequestStatus::IdentityReviewRequired,
            NotaryRequestStatus::IdentityVerified,
            NotaryRequestStatus::LocationReviewRequired,
            NotaryRequestStatus::LocationVerified,
        ], true)
            && $this->documentsReadyForSessionState($request);
    }

    public function documentsReadyForSession(NotaryRequest $request): bool
    {
        return $this->documentsReadyForSessionState($request);
    }

    /**
     * @return list<array{label: string, description: string, state: string}>
     */
    public function workflowSteps(NotaryRequest $request): array
    {
        $request->loadMissing(['documents.documentSigners', 'sessions', 'registerEntries', 'payments', 'eInvoices', 'attorneyNotarialRegistry', 'notary']);

        $hasSubmitted = $request->submitted_at !== null || $request->status !== NotaryRequestStatus::Draft;
        $hasDocuments = $request->documents->isNotEmpty();
        $allSignersSigned = $this->documentsReadyForSession($request);
        $hasCompletedSession = $this->hasCompletedSession($request);
        $attorneyHasSigned = $this->hasAttorneySignedAllDocuments($request);
        $hasRegisterEntry = $request->registerEntries->isNotEmpty();
        $hasFeeConfigured = $this->hasSettlementFeeConfigured($request);
        $hasPreparedDraft = $this->hasPreparedRegistryDraft($request);
        $canAccessRegistry = $this->canAccessAttorneyRegistry($request);
        $hasAttorneySeal = $this->hasAttorneySealOnFile($request);
        $paymentRequired = $this->paymentRequired($request);
        $hasSettledPayment = $this->hasSettledPayment($request);
        $isNotarized = $request->status === NotaryRequestStatus::Notarized;
        $isAttorneyApproved = $request->status === NotaryRequestStatus::AttorneyApproved;
        $isDigitalized = $request->status === NotaryRequestStatus::Digitalized;
        $canBeginAttorneySigning = $this->canBeginAttorneySigning($request);
        $canDigitalize = $this->canDigitalize($request);
        $canReview = $this->canApprove($request);
        $isReviewComplete = $request->status === NotaryRequestStatus::AttorneyApproved
            || in_array($request->status, [NotaryRequestStatus::Digitalized, NotaryRequestStatus::Notarized], true);
        $canCreateRegister = $this->canCreateRegisterEntry($request);

        $resolveState = function (bool $complete, bool $current, bool $blocked = false): string {
            if ($complete) {
                return 'complete';
            }

            if ($blocked) {
                return 'blocked';
            }

            return $current ? 'current' : 'upcoming';
        };

        $feeComplete = $hasFeeConfigured || (! $paymentRequired && $attorneyHasSigned);
        $feeCurrent = $paymentRequired && $attorneyHasSigned && ! $hasFeeConfigured;
        $paymentComplete = $paymentRequired
            ? $hasSettledPayment
            : ($attorneyHasSigned || $isNotarized || $isDigitalized);
        $paymentCurrent = $paymentRequired && $hasFeeConfigured && ! $hasSettledPayment && $attorneyHasSigned;
        $registryDraftComplete = $hasPreparedDraft;
        $registryDraftCurrent = $canAccessRegistry && ! $hasPreparedDraft;
        $sealComplete = $hasAttorneySeal;
        $sealCurrent = $attorneyHasSigned && ! $hasAttorneySeal && ($hasSettledPayment || ! $paymentRequired) && $hasPreparedDraft;
        $registerComplete = $hasRegisterEntry;
        $registerCurrent = $canCreateRegister && ! $hasRegisterEntry;
        $reviewComplete = $isReviewComplete;
        $reviewCurrent = $canReview && ! $isReviewComplete;
        $digitalComplete = $isDigitalized || $isNotarized;
        $digitalCurrent = $canDigitalize && ! $isDigitalized && ! $isNotarized;

        return [
            [
                'label' => __('Prepare the document'),
                'description' => __('Upload the PDF, add signers, and send it out for signing.'),
                'state' => match (true) {
                    $allSignersSigned || $hasCompletedSession || $attorneyHasSigned || $isNotarized => 'complete',
                    $hasDocuments => 'current',
                    $request->status === NotaryRequestStatus::IdentityReviewRequired => 'current',
                    $request->status === NotaryRequestStatus::LocationReviewRequired => 'current',
                    default => $hasSubmitted ? 'current' : 'upcoming',
                },
            ],
            [
                'label' => __('Wait for signers'),
                'description' => __('Each signer completes their signature on the document.'),
                'state' => match (true) {
                    $allSignersSigned || $hasCompletedSession || $attorneyHasSigned || $isNotarized => 'complete',
                    $hasDocuments && $request->documents->contains(fn (Document $document) => $document->status->value === 'pending') => 'current',
                    default => 'upcoming',
                },
            ],
            [
                'label' => __('Verify client on video'),
                'description' => __('Meet the signer on a live video call and confirm their identity.'),
                'state' => match (true) {
                    $hasCompletedSession || $attorneyHasSigned || $isNotarized => 'complete',
                    in_array($request->status, [
                        NotaryRequestStatus::SessionScheduled,
                        NotaryRequestStatus::SessionInProgress,
                        NotaryRequestStatus::SessionCompleted,
                    ], true) => 'current',
                    $allSignersSigned => 'current',
                    default => 'upcoming',
                },
            ],
            [
                'label' => __('Sign as attorney'),
                'description' => __('After video verification, add your signature to the document.'),
                'state' => match (true) {
                    $attorneyHasSigned || $isNotarized => 'complete',
                    in_array($request->status, [
                        NotaryRequestStatus::SessionCompleted,
                        NotaryRequestStatus::AttorneySigning,
                    ], true) => 'current',
                    $canBeginAttorneySigning => 'current',
                    default => 'upcoming',
                },
            ],
            [
                'label' => __('Set the fee amount'),
                'description' => __('Enter how much the client should pay before you finish the register.'),
                'state' => $resolveState($feeComplete, $feeCurrent, ! $attorneyHasSigned),
            ],
            [
                'label' => __('Client pays the fee'),
                'description' => __('The client pays using the amount you set.'),
                'state' => $resolveState(
                    $paymentComplete,
                    $paymentCurrent,
                    $paymentRequired && (! $hasFeeConfigured || ! $attorneyHasSigned),
                ),
            ],
            [
                'label' => __('Fill notarial book'),
                'description' => __('Complete the 9-field register row and O.R. number after payment.'),
                'state' => $resolveState($registryDraftComplete, $registryDraftCurrent, ! $canAccessRegistry),
            ],
            [
                'label' => __('Upload your notary seal'),
                'description' => __('Add your personal seal in your trust profile.'),
                'state' => $resolveState($sealComplete, $sealCurrent, ! $attorneyHasSigned),
            ],
            [
                'label' => __('Complete notarial book'),
                'description' => __('Create the final notarial book entry from your saved draft.'),
                'state' => $resolveState($registerComplete, $registerCurrent, ! $attorneyHasSigned),
            ],
            [
                'label' => __('Review and approve'),
                'description' => __('Confirm identity, consent, and jurisdiction before finishing.'),
                'state' => $resolveState($reviewComplete, $reviewCurrent, ! $hasRegisterEntry),
            ],
            [
                'label' => __('Apply seal and certificate'),
                'description' => __('Add your seal, QR code, certificate, and timestamp to finish.'),
                'state' => $resolveState($digitalComplete, $digitalCurrent, ! $hasRegisterEntry),
            ],
        ];
    }

    /**
     * Condensed attorney-facing milestones aligned with the case workspace tabs.
     *
     * @return list<array{label: string, description: string, state: string}>
     */
    public function attorneyMilestoneSteps(NotaryRequest $request): array
    {
        $steps = $this->workflowSteps($request);

        return [
            $this->combineWorkflowSteps(
                $steps,
                [0, 1],
                __('Prepare & collect signatures'),
                __('Upload the document, add signers, and collect signatures.'),
            ),
            $steps[2],
            $steps[3],
            $this->combineWorkflowSteps(
                $steps,
                range(4, 10),
                __('Fees & register'),
                __('Set the fee, collect payment, complete the notarial book, and apply your seal and certificate.'),
            ),
        ];
    }

    /**
     * @param  list<array{label: string, description: string, state: string}>  $steps
     * @param  list<int>  $indices
     * @return array{label: string, description: string, state: string}
     */
    private function combineWorkflowSteps(array $steps, array $indices, string $label, string $description): array
    {
        $subset = collect($indices)
            ->map(fn (int $index): ?array => $steps[$index] ?? null)
            ->filter()
            ->values();

        $states = $subset->pluck('state');

        $state = match (true) {
            $subset->isEmpty() => 'upcoming',
            $states->every(fn (string $stepState): bool => $stepState === 'complete') => 'complete',
            $states->contains('current') => 'current',
            $states->contains('blocked') => 'blocked',
            $states->contains('complete') => 'current',
            default => 'upcoming',
        };

        return [
            'label' => $label,
            'description' => $description,
            'state' => $state,
        ];
    }

    public function settlementPendingCount(NotaryRequest $request, bool $forAttorney): int
    {
        return collect($this->settlementSteps($request))
            ->filter(function (array $step) use ($forAttorney): bool {
                if (($step['state'] ?? '') !== 'current') {
                    return false;
                }

                $actor = $step['actor'] ?? '';

                return $forAttorney
                    ? $actor === 'attorney'
                    : $actor === 'client';
            })
            ->count();
    }

    public function currentSettlementSectionId(NotaryRequest $request): ?string
    {
        $step = collect($this->settlementSteps($request))
            ->first(fn (array $settlementStep): bool => ($settlementStep['state'] ?? '') === 'current');

        $sectionId = $step['section_id'] ?? null;

        return is_string($sectionId) && $sectionId !== '' ? $sectionId : null;
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     state: 'complete'|'current'|'upcoming'|'blocked'
     * }>
     */
    public function clientPortalTimeline(NotaryRequest $request): array
    {
        $request->loadMissing(['sessions', 'payments', 'attorneyNotarialRegistry']);

        $isNotarized = $request->status === NotaryRequestStatus::Notarized;
        $hasCompletedSession = $this->hasCompletedSession($request);
        $paymentRequired = $this->paymentRequired($request);
        $hasSettledPayment = $this->hasSettledPayment($request);
        $hasFeeConfigured = $this->hasSettlementFeeConfigured($request);

        $steps = [];

        if ((bool) config('docutrust.notary.require_video_session', true)) {
            $sessionState = match (true) {
                $isNotarized, $hasCompletedSession => 'complete',
                in_array($request->status, [
                    NotaryRequestStatus::SessionScheduled,
                    NotaryRequestStatus::SessionInProgress,
                ], true) => 'current',
                default => 'upcoming',
            };

            $steps[] = [
                'key' => 'session',
                'label' => __('Video verification'),
                'description' => __('Join the scheduled notary session and complete identity verification.'),
                'state' => $sessionState,
            ];
        }

        if ($paymentRequired) {
            $paymentState = match (true) {
                $isNotarized, $hasSettledPayment => 'complete',
                $hasFeeConfigured => 'current',
                default => 'upcoming',
            };

            $steps[] = [
                'key' => 'payment',
                'label' => __('Pay notarial fee'),
                'description' => __('Complete checkout when your attorney sets the fee amount.'),
                'state' => $paymentState,
            ];
        }

        if ($isNotarized) {
            $steps[] = [
                'key' => 'complete',
                'label' => __('Download your documents'),
                'description' => __('Your notarized PDF and certificate are ready.'),
                'state' => 'complete',
            ];
        } else {
            $attorneyClosingState = match (true) {
                ($hasSettledPayment || ! $paymentRequired) && $hasCompletedSession => 'current',
                default => 'upcoming',
            };

            $steps[] = [
                'key' => 'attorney_closing',
                'label' => __('Attorney finalizes'),
                'description' => __('Your attorney completes the register entry and digital notarization.'),
                'state' => $attorneyClosingState,
            ];
        }

        return $steps;
    }

    public function hasCompletedSession(NotaryRequest $request): bool
    {
        $request->loadMissing(['sessions', 'signers', 'documents.documentSigners']);

        $signerScopedSessions = $request->sessions->filter(
            fn ($session): bool => $session->notary_signer_id !== null
        );

        if ($signerScopedSessions->isNotEmpty()) {
            $signedParties = app(NotarySignerVideoInvitationService::class)->signedPartiesForVideo($request);

            if ($signedParties === []) {
                return false;
            }

            return collect($signedParties)->every(function (NotarySigner $signer) use ($signerScopedSessions): bool {
                return $signerScopedSessions->contains(
                    fn ($session): bool => (int) $session->notary_signer_id === (int) $signer->id
                        && $session->status === 'completed'
                );
            });
        }

        return $request->sessions->contains(fn ($session): bool => $session->status === 'completed');
    }

    public function canBeginAttorneySigning(NotaryRequest $request): bool
    {
        if (! $this->hasCompletedSession($request)) {
            return false;
        }

        return $this->documentsReadyForSessionState($request);
    }

    public function hasAttorneySignedAllDocuments(NotaryRequest $request): bool
    {
        $request->loadMissing('documents.documentSigners');

        if ($request->documents->isEmpty() || $request->notary_user_id === null) {
            return false;
        }

        return $request->documents->every(function (Document $document) use ($request): bool {
            return $document->documentSigners->contains(
                fn (DocumentSigner $signer): bool => (int) $signer->user_id === (int) $request->notary_user_id
                    && $signer->role_type === TemplateRoleType::Signer
                    && $signer->status->isCompleted()
            );
        });
    }

    public function documentHasCoreArtifacts(Document $document): bool
    {
        $document->loadMissing('documentHash');

        $hasFinalPdf = is_string($document->final_pdf_path) && $document->final_pdf_path !== '';
        $hasCertificate = is_string($document->certificate_path) && $document->certificate_path !== '';
        $hasDocumentHash = $document->documentHash !== null
            && is_string($document->documentHash->hash)
            && $document->documentHash->hash !== '';

        return $hasFinalPdf && $hasCertificate && $hasDocumentHash;
    }

    public function requestHasCoreArtifacts(NotaryRequest $request): bool
    {
        $request->loadMissing('documents');

        if ($request->documents->isEmpty()) {
            return false;
        }

        return $request->documents->every(
            fn (Document $document): bool => $this->documentHasCoreArtifacts($document)
        );
    }

    public function canCreateRegisterEntry(NotaryRequest $request): bool
    {
        if (in_array($request->status, [
            NotaryRequestStatus::Digitalized,
            NotaryRequestStatus::Notarized,
        ], true)) {
            return false;
        }

        if (! $this->hasAttorneySignedAllDocuments($request)) {
            return false;
        }

        if (! $this->hasAttorneySealOnFile($request)) {
            return false;
        }

        if ($this->paymentRequired($request) && ! $this->hasSettledPayment($request)) {
            return false;
        }

        $request->loadMissing('registerEntries');

        if ($request->registerEntries->isNotEmpty()) {
            return false;
        }

        if (! $this->hasPreparedRegistryDraft($request)) {
            return false;
        }

        return true;
    }

    public function settlementClosingPrerequisitesMet(NotaryRequest $request): bool
    {
        if (! $this->hasAttorneySignedAllDocuments($request)) {
            return false;
        }

        if (! $this->hasAttorneySealOnFile($request)) {
            return false;
        }

        if ($this->paymentRequired($request) && ! $this->hasSettledPayment($request)) {
            return false;
        }

        $request->loadMissing(['registerEntries', 'attorneyNotarialRegistry']);

        return $request->registerEntries->isNotEmpty()
            || $this->hasPreparedRegistryDraft($request);
    }

    public function hasSettlementFeeConfigured(NotaryRequest $request): bool
    {
        $request->loadMissing('attorneyNotarialRegistry');

        return $request->attorneyNotarialRegistry !== null
            && (float) $request->attorneyNotarialRegistry->fees > 0;
    }

    public function hasPreparedRegistryDraft(NotaryRequest $request): bool
    {
        $request->loadMissing('attorneyNotarialRegistry');

        return $request->attorneyNotarialRegistry?->registry_fields_completed_at !== null;
    }

    public function canAccessAttorneyRegistry(NotaryRequest $request): bool
    {
        if (! $this->hasAttorneySignedAllDocuments($request)) {
            return false;
        }

        if ($this->paymentRequired($request) && ! $this->hasSettledPayment($request)) {
            return false;
        }

        return true;
    }

    public function settlementDueAmount(NotaryRequest $request): float
    {
        $request->loadMissing(['registerEntries', 'attorneyNotarialRegistry']);

        $latestRegisterEntry = $request->registerEntries->sortByDesc('created_at')->first();
        if ($latestRegisterEntry !== null && (float) $latestRegisterEntry->fees > 0) {
            return (float) $latestRegisterEntry->fees;
        }

        return (float) ($request->attorneyNotarialRegistry?->fees ?? 0);
    }

    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   description: string,
     *   state: 'complete'|'current'|'upcoming'|'blocked',
     *   actor: 'attorney'|'client'|'system',
     *   section_id: ?string,
     *   href: ?string,
     *   waiting_on: ?('attorney'|'client')
     * }>
     */
    public function settlementSteps(NotaryRequest $request): array
    {
        $request->loadMissing(['attorneyNotarialRegistry', 'registerEntries', 'payments', 'notary']);

        $attorneyHasSigned = $this->hasAttorneySignedAllDocuments($request);
        $hasFeeConfigured = $this->hasSettlementFeeConfigured($request);
        $hasPreparedDraft = $this->hasPreparedRegistryDraft($request);
        $canAccessRegistry = $this->canAccessAttorneyRegistry($request);
        $hasSeal = $this->hasAttorneySealOnFile($request);
        $paymentRequired = $this->paymentRequired($request);
        $hasSettledPayment = $this->hasSettledPayment($request);
        $hasRegisterEntry = $request->registerEntries->isNotEmpty();
        $canReview = $this->canApprove($request);
        $isReviewComplete = $request->status === NotaryRequestStatus::AttorneyApproved
            || in_array($request->status, [NotaryRequestStatus::Digitalized, NotaryRequestStatus::Notarized], true);
        $canDigitalize = $this->canDigitalize($request);
        $isDigitalized = in_array($request->status, [NotaryRequestStatus::Digitalized, NotaryRequestStatus::Notarized], true);

        $resolveState = function (bool $complete, bool $current, bool $blocked = false): string {
            if ($complete) {
                return 'complete';
            }

            if ($blocked) {
                return 'blocked';
            }

            return $current ? 'current' : 'upcoming';
        };

        $feeComplete = $hasFeeConfigured || (! $paymentRequired && $attorneyHasSigned);
        $feeCurrent = $paymentRequired && $attorneyHasSigned && ! $hasFeeConfigured;
        $paymentComplete = $paymentRequired
            ? $hasSettledPayment
            : ($attorneyHasSigned || $isDigitalized);
        $paymentCurrent = $paymentRequired && $hasFeeConfigured && ! $hasSettledPayment && $attorneyHasSigned;
        $registryDraftComplete = $hasPreparedDraft;
        $registryDraftCurrent = $canAccessRegistry && ! $hasPreparedDraft;
        $sealComplete = $hasSeal;
        $sealCurrent = $attorneyHasSigned && ! $hasSeal && ($hasSettledPayment || ! $paymentRequired) && $hasPreparedDraft;
        $registerComplete = $hasRegisterEntry;
        $registerCurrent = $this->canCreateRegisterEntry($request) && ! $hasRegisterEntry;
        $reviewComplete = $isReviewComplete;
        $reviewCurrent = $canReview && ! $isReviewComplete;
        $digitalComplete = $isDigitalized;
        $digitalCurrent = $canDigitalize && ! $isDigitalized;

        $feeState = $resolveState($feeComplete, $feeCurrent, ! $attorneyHasSigned);
        $paymentState = $resolveState(
            $paymentComplete,
            $paymentCurrent,
            $paymentRequired && (! $hasFeeConfigured || ! $attorneyHasSigned),
        );
        $registryDraftState = $resolveState($registryDraftComplete, $registryDraftCurrent, ! $canAccessRegistry);
        $sealState = $resolveState($sealComplete, $sealCurrent, ! $attorneyHasSigned);
        $registerState = $resolveState($registerComplete, $registerCurrent, ! $attorneyHasSigned);
        $reviewState = $resolveState($reviewComplete, $reviewCurrent, ! $hasRegisterEntry);
        $digitalState = $resolveState($digitalComplete, $digitalCurrent, ! $hasRegisterEntry);

        return [
            [
                'key' => 'settlement_fee',
                'label' => __('Set the fee amount'),
                'description' => __('Enter how much the client should pay before you finish the register.'),
                'state' => $feeState,
                'actor' => 'attorney',
                'section_id' => 'section-settlement-fee',
                'href' => null,
                'waiting_on' => $this->settlementStepWaitingOn($feeState, ! $attorneyHasSigned ? 'attorney' : null),
            ],
            [
                'key' => 'payment',
                'label' => __('Client pays the fee'),
                'description' => __('The client pays using the amount you set.'),
                'state' => $paymentState,
                'actor' => 'client',
                'section_id' => 'section-payment',
                'href' => null,
                'waiting_on' => $this->settlementStepWaitingOn(
                    $paymentState,
                    ! $paymentRequired ? null : (! $hasFeeConfigured || ! $attorneyHasSigned ? 'attorney' : null),
                ),
            ],
            [
                'key' => 'registry_draft',
                'label' => __('Fill notarial book'),
                'description' => __('Complete the 9-field register row and O.R. number after payment.'),
                'state' => $registryDraftState,
                'actor' => 'attorney',
                'section_id' => 'section-attorney-registry',
                'href' => $canAccessRegistry ? route('notary.attorney-registry', $request) : null,
                'waiting_on' => $this->settlementStepWaitingOn(
                    $registryDraftState,
                    ! $attorneyHasSigned ? 'attorney' : ($paymentRequired && ! $hasSettledPayment ? 'client' : null),
                ),
            ],
            [
                'key' => 'seal',
                'label' => __('Upload your notary seal'),
                'description' => __('Add your personal seal in your trust profile.'),
                'state' => $sealState,
                'actor' => 'attorney',
                'section_id' => 'section-attorney-seal',
                'href' => app(NotarySealProfileService::class)->trustProfileSealSectionUrl(),
                'waiting_on' => $this->settlementStepWaitingOn(
                    $sealState,
                    ! $attorneyHasSigned ? 'attorney' : (! $hasPreparedDraft ? 'attorney' : ($paymentRequired && ! $hasSettledPayment ? 'client' : null)),
                ),
            ],
            [
                'key' => 'register_entry',
                'label' => __('Complete notarial book'),
                'description' => __('Create the final notarial book entry from your saved draft.'),
                'state' => $registerState,
                'actor' => 'attorney',
                'section_id' => 'section-register',
                'href' => route('notary.register-entry', $request),
                'waiting_on' => $this->settlementStepWaitingOn($registerState, ! $attorneyHasSigned ? 'attorney' : null),
            ],
            [
                'key' => 'attorney_review',
                'label' => __('Review and approve'),
                'description' => __('Confirm identity, consent, and jurisdiction before finishing.'),
                'state' => $reviewState,
                'actor' => 'attorney',
                'section_id' => 'section-review',
                'href' => null,
                'waiting_on' => $this->settlementStepWaitingOn($reviewState, ! $hasRegisterEntry ? 'attorney' : null),
            ],
            [
                'key' => 'digital_notarization',
                'label' => __('Apply seal and certificate'),
                'description' => __('Add your seal, QR code, certificate, and timestamp to finish.'),
                'state' => $digitalState,
                'actor' => 'attorney',
                'section_id' => 'section-digital-notarization',
                'href' => null,
                'waiting_on' => $this->settlementStepWaitingOn($digitalState, ! $hasRegisterEntry ? 'attorney' : null),
            ],
        ];
    }

    /**
     * @param  'complete'|'current'|'upcoming'|'blocked'  $state
     * @param  'attorney'|'client'|null  $blockedBy
     */
    private function settlementStepWaitingOn(string $state, ?string $blockedBy): ?string
    {
        if (in_array($state, ['complete', 'current'], true)) {
            return null;
        }

        return $blockedBy;
    }

    public function canApprove(NotaryRequest $request): bool
    {
        if (! $this->hasCompletedSession($request)) {
            return false;
        }

        if (! $this->hasAttorneySignedAllDocuments($request)) {
            return false;
        }

        $request->loadMissing('registerEntries');

        if ($request->registerEntries->isEmpty()) {
            return false;
        }

        if ($this->paymentRequired($request) && ! $this->hasSettledPayment($request)) {
            return false;
        }

        return true;
    }

    public function canDigitalize(NotaryRequest $request): bool
    {
        if (in_array($request->status, [NotaryRequestStatus::Digitalized, NotaryRequestStatus::Notarized], true)) {
            return false;
        }

        if (! $this->hasAttorneySignedAllDocuments($request)) {
            return false;
        }

        if (! $this->settlementClosingPrerequisitesMet($request)) {
            return false;
        }

        $request->loadMissing('documents');

        return $request->documents->isNotEmpty()
            && $request->documents->every(fn (Document $document): bool => $document->status === DocumentStatus::Completed);
    }

    /**
     * @return list<array{key: string, label: string, complete: bool}>
     */
    public function digitalizePrerequisites(NotaryRequest $request): array
    {
        $request->loadMissing(['documents', 'registerEntries', 'attorneyNotarialRegistry', 'payments']);
        $paymentRequired = $this->paymentRequired($request);

        $prerequisites = [
            [
                'key' => 'attorney_signed',
                'label' => __('Attorney signed the instrument'),
                'complete' => $this->hasAttorneySignedAllDocuments($request),
            ],
            [
                'key' => 'fee',
                'label' => __('Notarial fee configured'),
                'complete' => $this->hasSettlementFeeConfigured($request),
            ],
        ];

        if ($paymentRequired) {
            $prerequisites[] = [
                'key' => 'payment',
                'label' => __('Client payment received'),
                'complete' => $this->hasSettledPayment($request),
            ];
        }

        $prerequisites[] = [
            'key' => 'registry_draft',
            'label' => __('Notarial register draft complete'),
            'complete' => $this->hasPreparedRegistryDraft($request),
        ];
        $prerequisites[] = [
            'key' => 'seal',
            'label' => __('Attorney seal uploaded'),
            'complete' => $this->hasAttorneySealOnFile($request),
        ];
        $prerequisites[] = [
            'key' => 'register_entry',
            'label' => __('Official register entry created'),
            'complete' => $request->registerEntries->isNotEmpty(),
        ];
        $prerequisites[] = [
            'key' => 'final_document',
            'label' => __('Final document artifacts ready'),
            'complete' => $request->documents->isNotEmpty()
                && $request->documents->every(
                    fn (Document $document): bool => $document->status === DocumentStatus::Completed
                ),
        ];

        return $prerequisites;
    }

    public function paymentRequired(NotaryRequest $request): bool
    {
        $request->loadMissing(['registerEntries', 'attorneyNotarialRegistry']);

        if ($request->registerEntries->contains(
            fn ($entry): bool => (float) $entry->fees > 0
        )) {
            return true;
        }

        return (float) ($request->attorneyNotarialRegistry?->fees ?? 0) > 0;
    }

    public function hasSettledPayment(NotaryRequest $request): bool
    {
        if (! $this->paymentRequired($request)) {
            return true;
        }

        $request->loadMissing('payments');

        return $request->payments->contains(
            fn ($payment): bool => $payment->status === PaymentStatus::Paid
        );
    }

    public function beginAttorneySigning(NotaryRequest $request): NotaryRequest
    {
        if (! $this->canBeginAttorneySigning($request)) {
            throw new RuntimeException(__('Attorney signing can begin only after signer completion and the completed verification session.'));
        }

        if ($request->status !== NotaryRequestStatus::AttorneySigning) {
            $request->markAttorneySigning();
        }

        return $request->fresh();
    }

    public function hasAttorneySealOnFile(NotaryRequest $request): bool
    {
        $request->loadMissing('notary');

        if ($request->notary === null) {
            return false;
        }

        $credential = NotaryCredential::query()
            ->where('user_id', $request->notary->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        return $credential !== null
            && is_string($credential->seal_image_path)
            && $credential->seal_image_path !== '';
    }

    private function documentsReadyForSessionState(NotaryRequest $request): bool
    {
        $request->loadMissing(['documents.documentSigners']);

        if ($request->documents->isEmpty()) {
            return false;
        }

        if ($request->documents->count() > $this->maxDocumentsPerRequest()) {
            return false;
        }

        return $request->documents->every(function (Document $document) use ($request): bool {
            if (! in_array($document->status, [DocumentStatus::Pending, DocumentStatus::Completed], true)) {
                return false;
            }

            return $document->documentSigners
                ->filter(function (DocumentSigner $signer) use ($request): bool {
                    if (! $signer->requiresAction()) {
                        return false;
                    }

                    return (int) $signer->user_id !== (int) $request->notary_user_id;
                })
                ->every(fn (DocumentSigner $signer): bool => $signer->status->isCompleted());
        });
    }

    /**
     * @return array{
     *   ready: bool,
     *   issues: list<string>,
     *   documents: array<int, array{
     *     document_id: int,
     *     title: string,
     *     completed: bool,
     *     has_final_pdf: bool,
     *     has_certificate: bool,
     *     has_document_hash: bool,
     *     has_blockchain_transaction: bool,
     *     issues: list<string>
     *   }>
     * }
     */
    public function finalizationReadiness(NotaryRequest $request): array
    {
        $request->loadMissing(['documents.documentHash', 'registerEntries', 'payments', 'eInvoices']);

        $issues = [];
        $documents = [];

        if ($request->documents->isEmpty()) {
            $issues[] = __('Attach at least one document before finalizing notarization.');
        }

        if ($request->registerEntries->isEmpty()) {
            $issues[] = __('Create at least one notarial register entry before finalizing.');
        }

        if ($this->paymentRequired($request) && ! $this->hasSettledPayment($request)) {
            $issues[] = __('Client payment must be completed before finalizing notarization.');
        }

        foreach ($request->documents as $document) {
            $documentIssues = [];
            $completed = $document->status === DocumentStatus::Completed;
            $hasFinalPdf = is_string($document->final_pdf_path) && $document->final_pdf_path !== '';
            $hasCertificate = is_string($document->certificate_path) && $document->certificate_path !== '';
            $hasDocumentHash = $document->documentHash !== null && is_string($document->documentHash->hash) && $document->documentHash->hash !== '';
            $hasBlockchainTransaction = $document->documentHash !== null
                && is_string($document->documentHash->transaction_id)
                && $document->documentHash->transaction_id !== '';

            if (! $completed) {
                $documentIssues[] = __('Document is not completed.');
            }

            if (! $hasFinalPdf) {
                $documentIssues[] = __('Final signed PDF has not been generated.');
            }

            if (! $hasCertificate) {
                $documentIssues[] = __('Completion certificate has not been generated.');
            }

            if (! $hasDocumentHash) {
                $documentIssues[] = __('Document hash has not been recorded.');
            }

            // Blockchain anchoring is optional — service may be unavailable
            // NotaryAdmin can retry blockchain anchoring later
            if (! $hasBlockchainTransaction) {
                // Not a blocking issue — just a warning
            }

            if ($documentIssues !== []) {
                $issues[] = __('Document ":title" is not ready for notarization finalization.', [
                    'title' => $document->title,
                ]);
            }

            $documents[] = [
                'document_id' => (int) $document->id,
                'title' => (string) $document->title,
                'completed' => $completed,
                'has_final_pdf' => $hasFinalPdf,
                'has_certificate' => $hasCertificate,
                'has_document_hash' => $hasDocumentHash,
                'has_blockchain_transaction' => $hasBlockchainTransaction,
                'issues' => $documentIssues,
            ];
        }

        return [
            'ready' => $issues === [],
            'issues' => $issues,
            'documents' => $documents,
        ];
    }

    public function submit(NotaryRequest $request): NotaryRequest
    {
        if ($request->status !== NotaryRequestStatus::Draft) {
            throw new RuntimeException(__('Only draft notarizations can be submitted.'));
        }

        $request->markSubmitted();

        event(new NotaryRequestSubmitted($request));

        return $request->fresh();
    }

    public function approve(NotaryRequest $request, array $legalAssertions = [], ?string $summary = null): NotaryRequest
    {
        if (! $this->canApprove($request)) {
            throw new RuntimeException(__('This notarization is not ready for attorney review completion. Client payment must be completed after the register entry is created.'));
        }

        $request->markApproved();

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'approval',
            'summary' => $summary ?: __('Attorney completed the final review for this notarization.'),
            'legal_assertions' => $legalAssertions,
            'recorded_at' => now(),
        ]);

        event(new NotaryRequestApproved($request));

        return $request->fresh();
    }

    public function reject(NotaryRequest $request, string $reason, array $legalAssertions = []): NotaryRequest
    {
        if ($reason === '') {
            throw new RuntimeException(__('A rejection reason is required.'));
        }

        if (in_array($request->status, [
            NotaryRequestStatus::Notarized,
            NotaryRequestStatus::Rejected,
            NotaryRequestStatus::Failed,
        ], true)) {
            throw new RuntimeException(__('This notarization cannot be rejected in its current state.'));
        }

        $request->markRejected($reason);

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'rejection',
            'summary' => $reason,
            'legal_assertions' => $legalAssertions,
            'recorded_at' => now(),
        ]);

        return $request->fresh();
    }

    public function finalize(NotaryRequest $request): NotaryRequest
    {
        if ($request->status !== NotaryRequestStatus::Digitalized) {
            throw new RuntimeException(__('Digital notarization is required before notarization can be finalized.'));
        }

        $readiness = $this->finalizationReadiness($request);
        if (! $readiness['ready']) {
            throw new RuntimeException($readiness['issues'][0] ?? __('This notarization is not ready for finalization.'));
        }

        $request->markNotarized();

        event(new NotaryRequestNotarized($request));

        return $request->fresh();
    }

    public function digitalize(NotaryRequest $request): NotaryRequest
    {
        if (! $this->canDigitalize($request)) {
            throw new RuntimeException($this->digitalizeBlockingMessage($request));
        }

        if ($request->status !== NotaryRequestStatus::AttorneyApproved) {
            $request = $this->approve($request->fresh(), [
                'identity_matched' => true,
                'voluntary_consent' => true,
                'jurisdiction_valid' => true,
                'digital_notarization_ready' => true,
            ], __('Attorney completed signing and review, and marked this notarization ready for digital notarization.'));
        }

        app(NotaryDigitalizationService::class)->digitalize($request->fresh());

        $request->fresh()->markDigitalized();

        event(new NotaryRequestDigitalized($request->fresh()));

        return $request->fresh();
    }

    public function attachDocument(NotaryRequest $request, Document $document): NotaryRequest
    {
        $this->assertCanAttachDocument($request, $document);

        if ($request->organization_id === null || $document->organization_id === null || $request->organization_id !== $document->organization_id) {
            throw new RuntimeException(__('The selected document does not belong to this organization.'));
        }

        if ($document->notary_request_id !== null && $document->notary_request_id !== $request->id) {
            throw new RuntimeException(__('The selected document is already linked to another notarization.'));
        }

        if ($request->status === NotaryRequestStatus::Notarized) {
            throw new RuntimeException(__('You cannot attach documents to a finalized notarization.'));
        }

        $document->update([
            'notary_request_id' => $request->id,
        ]);

        app(NotaryParticipantSyncService::class)->syncRequestSignersToDocument($document);

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'document_attached',
            'summary' => __('Linked document ":title" to this notarization.', ['title' => $document->title]),
            'legal_assertions' => [
                'document_id' => $document->id,
            ],
            'recorded_at' => now(),
        ]);

        return $request->fresh(['documents', 'journals']);
    }

    public function detachDocument(NotaryRequest $request, Document $document): NotaryRequest
    {
        if ($document->notary_request_id !== $request->id) {
            throw new RuntimeException(__('The selected document is not linked to this notarization.'));
        }

        if ($request->status === NotaryRequestStatus::Notarized) {
            throw new RuntimeException(__('You cannot detach documents from a finalized notarization.'));
        }

        $document->update([
            'notary_request_id' => null,
        ]);

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'document_detached',
            'summary' => __('Removed document ":title" from this notarization.', ['title' => $document->title]),
            'legal_assertions' => [
                'document_id' => $document->id,
            ],
            'recorded_at' => now(),
        ]);

        return $request->fresh(['documents', 'journals']);
    }

    public function cancel(NotaryRequest $request, string $reason = ''): NotaryRequest
    {
        if (in_array($request->status, [
            NotaryRequestStatus::Notarized,
            NotaryRequestStatus::Digitalized,
            NotaryRequestStatus::Rejected,
            NotaryRequestStatus::Failed,
            NotaryRequestStatus::Cancelled,
        ], true)) {
            throw new RuntimeException(__('This notarization cannot be cancelled in its current state.'));
        }

        $request->markCancelled();

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'request_cancelled',
            'summary' => $reason !== '' ? $reason : __('Notarization was cancelled.'),
            'legal_assertions' => [],
            'recorded_at' => now(),
        ]);

        return $request->fresh();
    }

    public function digitalizeBlockingMessage(NotaryRequest $request): string
    {
        if (! $this->hasAttorneySignedAllDocuments($request)) {
            return __('This notarization is not ready for digital notarization. The attorney must finish signing first.');
        }

        if (! $this->settlementClosingPrerequisitesMet($request)) {
            if ($this->paymentRequired($request) && ! $this->hasSettledPayment($request)) {
                return __('This notarization is not ready for digital notarization. Client payment must be completed first.');
            }

            return __('This notarization is not ready for digital notarization. Complete settlement, register entry, and seal first.');
        }

        $request->loadMissing('documents');

        if ($request->documents->isEmpty()
            || ! $request->documents->every(fn (Document $document): bool => $document->status === DocumentStatus::Completed)) {
            return __('This notarization is not ready for digital notarization. Final document artifacts are still being prepared.');
        }

        return __('This notarization is not ready for digital notarization yet.');
    }
}

```

## `app/Services/DocumentSigningWorkflowService.php`

```php
<?php

namespace App\Services;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Events\DocumentCompleted;
use App\Events\DocumentSignerCompleted;
use App\Jobs\GenerateCertificateJob;
use App\Jobs\GenerateDocumentPdfJob;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Trust\Authorization\TrustAuthorizationRequiredException;
use App\Trust\Authorization\TrustAuthorizationSessionService;
use Illuminate\Support\Carbon;

class DocumentSigningWorkflowService
{
    public function __construct(
        private readonly TrustAuthorizationSessionService $trustAuthorizationSessionService,
        private readonly SigningMethodService $signingMethodService,
    ) {}

    public function canSignerModifyFields(Document $document, DocumentSigner $signer): ?string
    {
        if ($signer->status->isCompleted()) {
            return $signer->isApprover()
                ? __('You have already approved this document.')
                : ($signer->isRecipient()
                    ? __('This participant does not take action on the document.')
                    : __('You have already signed this document.'));
        }

        if (in_array($document->status, [DocumentStatus::Declined, DocumentStatus::Cancelled], true)) {
            return __('This document can no longer be processed.');
        }

        if ($document->status !== DocumentStatus::Pending) {
            return __('This document is not available right now.');
        }

        if ($signer->isRecipient()) {
            return __('Recipients receive the completed document and do not take action during signing.');
        }

        return $this->canParticipantAct($document, $signer);
    }

    public function completeLegacySigning(DocumentSigner $signer, string $ipAddress): void
    {
        $document = $signer->document;
        if ($signer->isSigner()) {
            $this->ensureActiveTrustAuthorizationIfRequired($signer);
        }

        $signer->update($this->completedStatusPayload($signer));
        event(new DocumentSignerCompleted($document->fresh(), $signer->fresh()));

        $this->finalizeDocumentIfWorkflowComplete($document->fresh()->load('documentSigners'), $ipAddress);
    }

    public function completeSignerIfAllFieldsSigned(DocumentSigner $signer, Document $document, string $ipAddress): void
    {
        $signerHasFields = $document->signatureFields()
            ->where('signer_id', $signer->id)
            ->exists();

        if (! $signerHasFields) {
            return;
        }

        $hasUnsignedFields = $document->signatureFields()
            ->where('signer_id', $signer->id)
            ->whereDoesntHave('signature', function ($query) use ($signer): void {
                $query->where('signer_id', $signer->id);
            })
            ->exists();

        if ($hasUnsignedFields || $signer->status === DocumentSignerStatus::Signed) {
            return;
        }

        $signer->update($this->completedStatusPayload($signer));
        event(new DocumentSignerCompleted($document->fresh(), $signer->fresh()));

        $this->finalizeDocumentIfWorkflowComplete($document->fresh()->load('documentSigners'), $ipAddress);
    }

    private function finalizeDocumentIfWorkflowComplete(Document $document, string $ipAddress): void
    {
        if (! $document->allApproversHaveApproved() || ! $document->allSignersHaveSigned()) {
            return;
        }

        $document->update(['status' => DocumentStatus::Completed]);
        GenerateDocumentPdfJob::dispatch($document->id, 'final');
        $completedDocument = $document->fresh();
        SignatureAuditLogger::documentCompleted($completedDocument, $ipAddress);
        GenerateCertificateJob::dispatch($completedDocument->id);
        event(new DocumentCompleted($completedDocument));
    }

    private function canParticipantAct(Document $document, DocumentSigner $signer): ?string
    {
        if (! $this->usesSequentialSigning($document) || $signer->signing_order === null) {
            return $this->parallelWorkflowBlockingMessage($document, $signer);
        }

        $blockingSigner = $this->pendingPrerequisiteParticipant($document, $signer);

        if ($blockingSigner === null) {
            return null;
        }

        $blockingName = trim((string) $blockingSigner->name);
        $blockingLabel = $blockingName !== ''
            ? $blockingName
            : __('Participant #:order', ['order' => $blockingSigner->signing_order]);

        $blockingRoleLabel = $blockingSigner->isApprover()
            ? __('approver')
            : __('signer');

        return __($signer->isApprover()
            ? 'You cannot approve yet. Waiting for :role :order (:name) to finish first.'
            : 'You cannot sign yet. Waiting for :role :order (:name) to finish first.', [
                'role' => $blockingRoleLabel,
                'order' => $blockingSigner->signing_order,
                'name' => $blockingLabel,
            ]);
    }

    private function usesSequentialSigning(Document $document): bool
    {
        return $document->usesSequentialSigningWorkflow();
    }

    private function pendingPrerequisiteParticipant(Document $document, DocumentSigner $signer): ?DocumentSigner
    {
        /** @var ?DocumentSigner $blockingSigner */
        $blockingSigner = $document->documentSigners
            ->filter(function (DocumentSigner $otherSigner) use ($signer): bool {
                if ($otherSigner->id === $signer->id || $otherSigner->signing_order === null || ! $otherSigner->requiresAction()) {
                    return false;
                }

                if ($otherSigner->signing_order >= $signer->signing_order) {
                    return false;
                }

                return ! $otherSigner->status->isCompleted();
            })
            ->sortBy('signing_order')
            ->first();

        return $blockingSigner;
    }

    private function parallelWorkflowBlockingMessage(Document $document, DocumentSigner $signer): ?string
    {
        if (! $signer->isSigner()) {
            return null;
        }

        $pendingApprover = $document->documentSigners
            ->first(fn (DocumentSigner $participant): bool => $participant->isApprover() && ! $participant->status->isCompleted());

        if ($pendingApprover === null) {
            return null;
        }

        $blockingName = trim((string) $pendingApprover->name);

        return __('You cannot sign yet. Waiting for approver :name to approve first.', [
            'name' => $blockingName !== '' ? $blockingName : __('Approval participant'),
        ]);
    }

    /**
     * @return array{status: DocumentSignerStatus, signed_at: Carbon}
     */
    private function completedStatusPayload(DocumentSigner $signer): array
    {
        return [
            'status' => $signer->isApprover()
                ? DocumentSignerStatus::Approved
                : DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ];
    }

    private function ensureActiveTrustAuthorizationIfRequired(DocumentSigner $signer): void
    {
        if (! $this->signingMethodService->requiresTrustAuthorization($signer)) {
            return;
        }

        $providerName = trim((string) config('services.remote_signing.provider_name', 'remote_managed'));
        $authorization = $this->trustAuthorizationSessionService->activeForSigner($signer, $providerName);

        if ($authorization !== null) {
            return;
        }

        throw new TrustAuthorizationRequiredException(
            __('Start trust authorization before completing your assigned fields.')
        );
    }
}

```

## `app/Enums/NotaryRequestStatus.php`

```php
<?php

namespace App\Enums;

enum NotaryRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case IdentityReviewRequired = 'identity_review_required';
    case IdentityVerified = 'identity_verified';
    case LocationReviewRequired = 'location_review_required';
    case LocationVerified = 'location_verified';
    case SessionScheduled = 'session_scheduled';
    case SessionInProgress = 'session_in_progress';
    case SessionCompleted = 'session_completed';
    case AttorneySigning = 'attorney_signing';
    case AttorneyApproved = 'attorney_approved';
    case Digitalized = 'digitalized';
    case Notarized = 'notarized';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Submitted => __('Submitted'),
            self::IdentityReviewRequired => __('Identity review'),
            self::IdentityVerified => __('Identity verified'),
            self::LocationReviewRequired => __('Location review'),
            self::LocationVerified => __('Location verified'),
            self::SessionScheduled => __('Video call scheduled'),
            self::SessionInProgress => __('Video call in progress'),
            self::SessionCompleted => __('Video verification done'),
            self::AttorneySigning => __('Ready for you to sign'),
            self::AttorneyApproved => __('Attorney reviewed'),
            self::Digitalized => __('Digitally notarized'),
            self::Notarized => __('Notarized'),
            self::Rejected => __('Rejected'),
            self::Failed => __('Failed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function fluxColor(): string
    {
        return match ($this) {
            self::Notarized => 'emerald',
            self::Rejected, self::Failed, self::Cancelled => 'red',
            self::Submitted, self::SessionScheduled, self::SessionInProgress => 'sky',
            self::IdentityVerified, self::LocationVerified, self::AttorneyApproved, self::Digitalized => 'teal',
            self::IdentityReviewRequired, self::LocationReviewRequired, self::SessionCompleted, self::AttorneySigning => 'amber',
            default => 'zinc',
        };
    }
}

```

## `app/Models/Document.php`

```php
<?php

namespace App\Models;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\TemplateRoleType;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    public const SIGNING_WORKFLOW_SEQUENTIAL = 'sequential';

    public const SIGNING_WORKFLOW_PARALLEL = 'parallel';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'notary_request_id',
        'user_id',
        'title',
        'file_path',
        'email_subject',
        'email_message',
        'audit_enabled',
        'audit_settings',
        'access_password_hash',
        'access_password_hint',
        'signing_workflow',
        'prepared_pdf_path',
        'final_pdf_path',
        'files',
        'certificate_path',
        'archive_storage_disk',
        'archive_document_path',
        'archive_certificate_path',
        'archived_at',
        'status',
        'sent_at',
        'csc_signed',
        'pades_byte_range',
        'pades_cms_signature',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'sent_at' => 'datetime',
            'archived_at' => 'datetime',
            'files' => 'array',
            'audit_enabled' => 'boolean',
            'audit_settings' => 'array',
            'csc_signed' => 'boolean',
            'pades_byte_range' => 'array',
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultAuditSettings(): array
    {
        return [
            'show_email' => true,
            'show_document_id' => true,
            'show_author' => true,
            'show_mobile' => false,
            'show_id_details' => false,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<NotaryRequest, $this>
     */
    public function notaryRequest(): BelongsTo
    {
        return $this->belongsTo(NotaryRequest::class);
    }

    /**
     * @return HasMany<DocumentSigner, $this>
     */
    public function documentSigners(): HasMany
    {
        return $this->hasMany(DocumentSigner::class);
    }

    /**
     * Backward-compatible alias used by some controllers/views.
     *
     * @return HasMany<DocumentSigner, $this>
     */
    public function signers(): HasMany
    {
        return $this->documentSigners();
    }

    /**
     * @return HasMany<Signature, $this>
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class);
    }

    /**
     * @return HasMany<SignatureField, $this>
     */
    public function signatureFields(): HasMany
    {
        return $this->hasMany(SignatureField::class);
    }

    /**
     * @return HasMany<SignatureAuditEvent, $this>
     */
    public function signatureAuditEvents(): HasMany
    {
        return $this->hasMany(SignatureAuditEvent::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'document_tag');
    }

    /**
     * @return HasOne<DocumentHash, $this>
     */
    public function documentHash(): HasOne
    {
        return $this->hasOne(DocumentHash::class);
    }

    /**
     * First PDF path: optional `files` JSON array, otherwise `file_path` when it is a PDF.
     */
    public function primaryPdfPath(): ?string
    {
        foreach ($this->files ?? [] as $path) {
            if (is_string($path) && str_ends_with(strtolower($path), '.pdf') && $path !== '') {
                return $path;
            }
        }

        if (is_string($this->file_path) && $this->file_path !== '' && str_ends_with(strtolower($this->file_path), '.pdf')) {
            return $this->file_path;
        }

        return null;
    }

    public function sourcePdfPath(): ?string
    {
        return $this->primaryPdfPath();
    }

    public function activeSigningPdfPath(): ?string
    {
        return $this->prepared_pdf_path ?: $this->sourcePdfPath();
    }

    public function previewPdfPath(): ?string
    {
        if ($this->hasArchivedFinalDocument()) {
            return $this->archive_document_path;
        }

        return $this->final_pdf_path ?: $this->prepared_pdf_path ?: $this->sourcePdfPath();
    }

    public function previewPdfDisk(): string
    {
        if ($this->hasArchivedFinalDocument()) {
            return $this->archiveDisk();
        }

        return (string) config('filesystems.docutrust_disk', 'local');
    }

    public function verifiablePdfPath(): ?string
    {
        if (is_string($this->final_pdf_path) && $this->final_pdf_path !== '' && str_ends_with(strtolower($this->final_pdf_path), '.pdf')) {
            return $this->final_pdf_path;
        }

        return null;
    }

    public function hasAccessPassword(): bool
    {
        return is_string($this->access_password_hash) && $this->access_password_hash !== '';
    }

    public function isAuditTrailEnabled(): bool
    {
        return $this->audit_enabled ?? true;
    }

    /**
     * @return array<string, bool>
     */
    public function resolvedAuditSettings(): array
    {
        return array_merge(
            self::defaultAuditSettings(),
            is_array($this->audit_settings) ? $this->audit_settings : [],
        );
    }

    public function signingWorkflow(): string
    {
        $workflow = (string) ($this->signing_workflow ?? self::SIGNING_WORKFLOW_SEQUENTIAL);

        return in_array($workflow, [self::SIGNING_WORKFLOW_SEQUENTIAL, self::SIGNING_WORKFLOW_PARALLEL], true)
            ? $workflow
            : self::SIGNING_WORKFLOW_SEQUENTIAL;
    }

    public function usesSequentialSigningWorkflow(): bool
    {
        return $this->signingWorkflow() === self::SIGNING_WORKFLOW_SEQUENTIAL;
    }

    public function archiveDisk(): string
    {
        return (string) ($this->archive_storage_disk ?: config('filesystems.docutrust_archive_disk', config('filesystems.docutrust_disk', 'local')));
    }

    public function finalDownloadPath(): ?string
    {
        if ($this->hasArchivedFinalDocument()) {
            return $this->archive_document_path;
        }

        return $this->previewPdfPath();
    }

    public function hasArchivedFinalDocument(): bool
    {
        return is_string($this->archive_document_path) && $this->archive_document_path !== '';
    }

    public function hasDocumentSigners(): bool
    {
        if ($this->relationLoaded('documentSigners')) {
            return $this->documentSigners->isNotEmpty();
        }

        return $this->documentSigners()->exists();
    }

    public function hasSigningParticipants(): bool
    {
        if ($this->relationLoaded('documentSigners')) {
            return $this->documentSigners->contains(fn (DocumentSigner $signer) => $signer->isSigner());
        }

        return $this->documentSigners()->where('role_type', TemplateRoleType::Signer)->exists();
    }

    public function hasSignatureFields(): bool
    {
        if ($this->relationLoaded('signatureFields')) {
            return $this->signatureFields->isNotEmpty();
        }

        return $this->signatureFields()->exists();
    }

    /**
     * @return EloquentCollection<int, DocumentSigner>
     */
    public function signersMissingFields(): EloquentCollection
    {
        if ($this->relationLoaded('documentSigners') && $this->relationLoaded('signatureFields')) {
            $fieldSignerIds = $this->signatureFields
                ->pluck('signer_id')
                ->filter(fn ($id) => $id !== null)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

            return $this->documentSigners
                ->filter(fn (DocumentSigner $signer) => $signer->isSigner() && ! in_array($signer->id, $fieldSignerIds, true))
                ->values();
        }

        return $this->documentSigners()
            ->where('role_type', TemplateRoleType::Signer)
            ->whereDoesntHave('signatureFields')
            ->get();
    }

    public function canPrepareForSigning(): bool
    {
        return $this->status === DocumentStatus::Draft && $this->hasSigningParticipants();
    }

    public function canSendForSigning(): bool
    {
        return $this->status === DocumentStatus::Draft
            && $this->hasSigningParticipants()
            && $this->hasSignatureFields()
            && $this->signersMissingFields()->isEmpty()
            && $this->workflowConfigurationIsValid();
    }

    public function allSignersHaveSigned(): bool
    {
        $signers = $this->relationLoaded('documentSigners')
            ? $this->documentSigners->filter(fn (DocumentSigner $signer) => $signer->isSigner())->values()
            : $this->documentSigners()->where('role_type', TemplateRoleType::Signer)->get();

        if ($signers->isEmpty()) {
            return false;
        }

        return $signers->every(
            fn (DocumentSigner $signer) => $signer->status === DocumentSignerStatus::Signed
        );
    }

    public function allApproversHaveApproved(): bool
    {
        $approvers = $this->relationLoaded('documentSigners')
            ? $this->documentSigners->filter(fn (DocumentSigner $signer) => $signer->isApprover())->values()
            : $this->documentSigners()->where('role_type', TemplateRoleType::Approver)->get();

        if ($approvers->isEmpty()) {
            return true;
        }

        return $approvers->every(
            fn (DocumentSigner $signer) => $signer->status === DocumentSignerStatus::Approved
        );
    }

    public function hasActionableParticipants(): bool
    {
        if ($this->relationLoaded('documentSigners')) {
            return $this->documentSigners->contains(fn (DocumentSigner $signer) => $signer->requiresAction());
        }

        return $this->documentSigners()->whereIn('role_type', [
            TemplateRoleType::Signer->value,
            TemplateRoleType::Approver->value,
        ])->exists();
    }

    public function workflowConfigurationIsValid(): bool
    {
        if (! $this->usesSequentialSigningWorkflow()) {
            return true;
        }

        $signers = $this->relationLoaded('documentSigners')
            ? $this->documentSigners
            : $this->documentSigners()->get();

        if ($signers->isEmpty()) {
            return true;
        }

        $orders = $signers
            ->pluck('signing_order')
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (int) $value)
            ->sort()
            ->values();

        if ($orders->count() !== $signers->count()) {
            return false;
        }

        return $orders->all() === range(1, $signers->count());
    }

    protected static function booted(): void
    {
        static::creating(function (Document $document): void {
            if ($document->organization_id !== null) {
                return;
            }

            if ($document->user_id === null) {
                return;
            }

            $document->organization_id = User::query()->whereKey($document->user_id)->value('organization_id');
        });
    }
}

```

## `app/Models/NotaryRequest.php`

```php
<?php

namespace App\Models;

use App\Enums\NotaryRequestStatus;
use Database\Factories\NotaryRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NotaryRequest extends Model
{
    /** @use HasFactory<NotaryRequestFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'notary_user_id',
        'title',
        'status',
        'request_type',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'completed_at',
        'rejection_reason',
        'metadata',
        'document_path',
        'remarks',
        'id_document_type',
        'id_document_number',
        'id_document_path',
        'selfie_path',
        'identity_verified_at',
        'verified_at',
        'location_verified_at',
        'location_ip_address',
        'location_country_code',
        'location_latitude',
        'location_longitude',
        'location_vpn_detected',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => NotaryRequestStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'completed_at' => 'datetime',
            'notarized_at' => 'datetime',
            'identity_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'location_verified_at' => 'datetime',
            'location_vpn_detected' => 'boolean',
            'location_latitude' => 'decimal:7',
            'location_longitude' => 'decimal:7',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Backward-compatible alias for factory relationship helpers.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->requester();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function notary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notary_user_id');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<NotarySession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(NotarySession::class)->latest('scheduled_for');
    }

    /**
     * @return HasMany<NotaryJournal, $this>
     */
    public function journals(): HasMany
    {
        return $this->hasMany(NotaryJournal::class)->latest('recorded_at');
    }

    /**
     * @return HasMany<NotarySigner, $this>
     */
    public function signers(): HasMany
    {
        return $this->hasMany(NotarySigner::class)->orderBy('id');
    }

    /**
     * @return HasMany<NotaryIdentityVerification, $this>
     */
    public function identityVerifications(): HasMany
    {
        return $this->hasMany(NotaryIdentityVerification::class)->latest();
    }

    /**
     * @return HasMany<NotaryGeoLog, $this>
     */
    public function geoLogs(): HasMany
    {
        return $this->hasMany(NotaryGeoLog::class)->latest();
    }

    /**
     * @return HasMany<NotarialRegisterEntry, $this>
     */
    public function registerEntries(): HasMany
    {
        return $this->hasMany(NotarialRegisterEntry::class)->orderByDesc('entry_number');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('created_at');
    }

    /**
     * @return HasMany<EInvoice, $this>
     */
    public function eInvoices(): HasMany
    {
        return $this->hasMany(EInvoice::class)->latest('created_at');
    }

    /**
     * @return HasOne<AttorneyNotarialRegistry, $this>
     */
    public function attorneyNotarialRegistry(): HasOne
    {
        return $this->hasOne(AttorneyNotarialRegistry::class);
    }

    public function markSubmitted(): void
    {
        $this->forceFill([
            'status' => NotaryRequestStatus::Submitted,
            'submitted_at' => now(),
        ])->save();
    }

    public function markApproved(): void
    {
        $this->forceFill([
            'status' => NotaryRequestStatus::AttorneyApproved,
            'approved_at' => now(),
            'rejected_at' => null,
            'rejection_reason' => null,
        ])->save();
    }

    public function markSessionCompleted(): void
    {
        $this->forceFill([
            'status' => NotaryRequestStatus::SessionCompleted,
        ])->save();
    }

    public function markAttorneySigning(): void
    {
        $this->forceFill([
            'status' => NotaryRequestStatus::AttorneySigning,
        ])->save();
    }

    public function markDigitalized(): void
    {
        $this->forceFill([
            'status' => NotaryRequestStatus::Digitalized,
        ])->save();
    }

    public function markRejected(string $reason): void
    {
        $this->forceFill([
            'status' => NotaryRequestStatus::Rejected,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ])->save();
    }

    public function markNotarized(): void
    {
        $now = now();
        $this->forceFill([
            'status' => NotaryRequestStatus::Notarized,
            'completed_at' => $now,
            'notarized_at' => $now,
        ])->save();
    }

    public function markIdentityVerified(): void
    {
        $now = now();
        $this->forceFill([
            'status' => NotaryRequestStatus::IdentityVerified,
            'identity_verified_at' => $now,
            'verified_at' => $now,
        ])->save();
    }

    public function markIdentityReviewRequired(?string $reason = null): void
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        if ($reason !== null && $reason !== '') {
            $metadata['identity_review_reason'] = $reason;
        }

        $this->forceFill([
            'status' => NotaryRequestStatus::IdentityReviewRequired,
            'metadata' => $metadata,
        ])->save();
    }

    public function markLocationReviewRequired(?string $reason = null): void
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        if ($reason !== null && $reason !== '') {
            $metadata['location_review_reason'] = $reason;
        }

        $this->forceFill([
            'status' => NotaryRequestStatus::LocationReviewRequired,
            'metadata' => $metadata,
        ])->save();
    }

    public function markCancelled(): void
    {
        $this->forceFill([
            'status' => NotaryRequestStatus::Cancelled,
        ])->save();
    }

    public function markFailed(string $reason = ''): void
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $metadata['failure_reason'] = $reason;

        $this->forceFill([
            'status' => NotaryRequestStatus::Failed,
            'metadata' => $metadata,
        ])->save();
    }

    protected static function booted(): void
    {
        static::creating(function (NotaryRequest $request): void {
            if ($request->organization_id !== null) {
                return;
            }

            if ($request->user_id === null) {
                return;
            }

            $request->organization_id = User::query()
                ->whereKey($request->user_id)
                ->value('organization_id');
        });
    }
}

```
