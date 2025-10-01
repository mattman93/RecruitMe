<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Force Simple Mode
    |--------------------------------------------------------------------------
    |
    | When set to true, the Playwright automation will skip the complex
    | enhanced navigation and use only the simple form analysis approach.
    | This can help resolve issues in production environments.
    |
    */
    'force_simple_mode' => env('PLAYWRIGHT_FORCE_SIMPLE_MODE', false),
    
    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, provides verbose logging and error details for
    | debugging Playwright automation issues.
    |
    */
    'debug_mode' => env('PLAYWRIGHT_DEBUG_MODE', false),
];