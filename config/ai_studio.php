<?php

/*
 * Mkhaya AI Studio. Credits are what a company spends on AI work; each company gets a monthly
 * allowance (overridable per company in ai_studio_credit_wallets) and may buy top-ups that carry over.
 * Costs are per operation, so pricing changes here, not in code.
 */
return [
    /*
     * Whether a queue worker runs. Production runs without one, so by default AI jobs run straight
     * after the response is sent and publishing runs inside the scheduler; a per-minute sweep picks
     * up anything left queued. Set AI_STUDIO_QUEUE_WORKER=true once a worker processes the queue.
     */
    'queue_worker' => (bool) env('AI_STUDIO_QUEUE_WORKER', false),

    'credits' => [
        'monthly_included' => (int) env('AI_STUDIO_MONTHLY_CREDITS', 500),
        'costs' => [
            'brand_analysis' => 5,
            'campaign_strategy' => 5,
            'plan_post' => 1,          // per post in a generated plan
            'content_generate' => 1,
            'content_transform' => 1,
            'image_template' => 1,
            'image_ai' => 4,
            'autopilot_post' => 1,      // per post autopilot writes
            'video_render' => 10,
            'video_translate' => 10,
            'video_reel' => 3,
            // A planned post designed by AI Studio itself: its image, its carousel slides, or a video's
            // scene pictures (the render itself is video_render, and its scenes content_generate).
            'design_image' => 4,
            'design_carousel' => 6,
            'design_video' => 5,
            'assistant_session' => 1,
            'knowledge_index' => 1,
        ],
    ],

    /*
     * Mkhaya Media Engine — our own video rendering, on ordinary servers (no GPU): ffmpeg for video and
     * Piper (open-source text-to-speech, runs on a CPU) for voiceover. Voices are Piper .onnx models in
     * the voices folder; a video renders without voiceover when Piper is not installed.
     */
    'engine' => [
        'ffmpeg' => env('AI_STUDIO_FFMPEG', 'ffmpeg'),
        'piper' => env('AI_STUDIO_PIPER', 'piper'),
        'voices' => env('AI_STUDIO_PIPER_VOICES', storage_path('app/piper-voices')),
        'fps' => 30,
        'max_scenes' => 12,
        'timeout' => (int) env('AI_STUDIO_RENDER_TIMEOUT', 900),
    ],

    'assistant' => [
        'messages_per_session' => (int) env('AI_STUDIO_ASSISTANT_MESSAGES', 30),
        'minutes_per_session' => 20,
        'sessions_per_day' => (int) env('AI_STUDIO_ASSISTANT_SESSIONS', 500),
    ],

    'planning' => [
        'max_days' => (int) env('AI_STUDIO_MAX_PLAN_DAYS', 92),
        'max_posts_per_day' => 8,
    ],

    'website' => [
        'max_pages' => 6,
        'max_bytes' => 400_000,
        'timeout' => 12,
    ],

    /*
     * Cost and margin reporting (phase 5). AI model cost comes from the AI usage log: its USD price
     * where the provider gave one, else tokens at the rate below. Our own compute (rendering on our
     * servers) has no invoice, so it is estimated per operation. Credits used by a company with no
     * plan are valued at unbilled_credit_value_zar.
     */
    'commercial' => [
        'usd_to_zar' => (float) env('AI_STUDIO_USD_TO_ZAR', 18.5),
        'ai_cost_per_1k_tokens_zar' => (float) env('AI_STUDIO_AI_COST_PER_1K', 0.05),
        'unbilled_credit_value_zar' => (float) env('AI_STUDIO_CREDIT_VALUE', 1.0),
        'compute_cost_zar' => [
            'video_render' => 0.60,
            'video_translate' => 0.60,
            'video_reel' => 0.15,
            'image_template' => 0.01,
            'image_ai' => 0.80,
            'design_image' => 0.80,
            'design_carousel' => 0.80,
            'design_video' => 2.00,
        ],
        'bank_details' => env('AI_STUDIO_PAYMENT_INSTRUCTIONS', 'Pay by EFT using the top-up reference. Credits are added as soon as Mkhaya confirms payment.'),
    ],

    'retention' => [
        'failed_job_days' => 90,
        'audit_days' => 730,
    ],

    'media' => [
        'max_upload_mb' => (int) env('AI_STUDIO_MAX_UPLOAD_MB', 100),
    ],

    /*
     * Auto-design. Pictures come from the free image engines (the shared AI image chain) unless a
     * premium engine is switched on: 'google' (Imagen) or 'openai' (GPT Image). Premium engines bill
     * Mkhaya's own account for every picture, so they are off until AI_STUDIO_IMAGE_ENGINE says so;
     * the other premium engine and then the free chain are the fallbacks. A premium engine draws the
     * headline into the picture itself; with the free engines, whose lettering is unreliable, Mkhaya
     * lays the headline over the picture instead. The logo is always placed by Mkhaya.
     */
    'design' => [
        'image_engine' => env('AI_STUDIO_IMAGE_ENGINE', 'free'), // free | google | openai
        'google' => [
            'key' => env('AI_STUDIO_GOOGLE_IMAGE_KEY', env('GEMINI_API_KEY')),
            'model' => env('AI_STUDIO_GOOGLE_IMAGE_MODEL', 'imagen-4.0-generate-001'),
        ],
        'openai' => [
            'key' => env('AI_STUDIO_OPENAI_IMAGE_KEY', env('OPENAI_API_KEY')),
            'model' => env('AI_STUDIO_OPENAI_IMAGE_MODEL', 'gpt-image-1'),
            'quality' => env('AI_STUDIO_OPENAI_IMAGE_QUALITY', 'medium'),
        ],
        'timeout' => (int) env('AI_STUDIO_IMAGE_TIMEOUT', 120),
        'carousel_slides' => 6,
        'video_scenes' => 5,
    ],
];
