<?php

return [

    'enabled' => filter_var(env('MARKETING_CHATBOT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'fallback_message' => 'I am currently unable to access advanced AI services right now. Please try again later or contact DocuTrust support.',

    'openai_system_prompt' => 'You are DocuTrust AI assistant helping users about digital signatures, blockchain verification, document workflows, eNotary, compliance, templates, and enterprise trust infrastructure. Be accurate and concise. Only answer questions related to DocuTrust. Do not invent pricing or legal advice.',

    'suggestions' => [
        'Are signatures legally binding?',
        'What is blockchain verification?',
        'How secure is DocuTrust?',
        'What is eNOTARY?',
        'How does verification work?',
    ],

    'faqs' => [

        [
            'keywords' => [
                'legal',
                'binding',
                'signature',
            ],
            'answer' => 'Yes. DocuTrust digital signatures comply with the E-Commerce Act and international electronic signature standards.',
        ],

        [
            'keywords' => [
                'blockchain',
                'verification',
                'tamper',
            ],
            'answer' => 'DocuTrust uses SHA256 hashing and blockchain anchoring to provide tamper-proof document verification.',
        ],

        [
            'keywords' => [
                'enotary',
                'notary',
                'notarization',
            ],
            'answer' => 'DocuTrust eNOTARY provides secure remote online notarization with live video verification, identity validation, audit logs, and blockchain-backed records.',
        ],

        [
            'keywords' => [
                'security',
                'safe',
                'encrypted',
            ],
            'answer' => 'DocuTrust uses encryption, audit trails, digital signatures, blockchain validation, and access controls to secure all documents.',
        ],

        [
            'keywords' => [
                'csc',
                'cloud signature consortium',
            ],
            'answer' => 'DocuTrust follows international digital signature standards through Cloud Signature Consortium aligned infrastructure.',
        ],

        [
            'keywords' => [
                'trial',
                'pricing',
                'subscription',
            ],
            'answer' => 'DocuTrust offers flexible plans for startups, enterprises, and government organizations. Contact sales for enterprise pricing.',
        ],

        [
            'keywords' => [
                'docutrust',
                'what is',
                'platform',
            ],
            'answer' => 'DocuTrust is a secure digital signing platform with Agentic AI document automation, blockchain-verified trust, multi-signer workflows, and CSC-aligned cloud signatures.',
        ],

        [
            'keywords' => [
                'verify',
                'verification',
                'document',
            ],
            'answer' => 'You can verify a signed document on DocuTrust using the public Verify page. Each completed document has a cryptographic hash anchored for tamper detection.',
        ],

        [
            'keywords' => [
                'template',
                'workflow',
            ],
            'answer' => 'DocuTrust supports reusable templates, signature field placement, sequential and parallel signing, and automated reminders for faster document workflows.',
        ],

    ],

];
