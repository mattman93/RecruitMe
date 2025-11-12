<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="google-site-verification" content="PuVtg7DjUVnaawSVmFhOHt9Gjzat-xKOgnv0qvzAE7A" />

        {{-- Inline style to set the HTML background color --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }
        </style>

        <title inertia>{{ config('app.name', 'AppliFlow') }}</title>

        {{-- Google Analytics --}}
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=YOUR_GA_MEASUREMENT_ID"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());
          gtag('config', 'G-8W4FM5PCPG');
        </script>

        <link rel="icon" href="/favicon-bold-256.png" type="image/png" sizes="256x256">
        <link rel="icon" href="/favicon-bold-64.png" type="image/png" sizes="64x64">
        <link rel="icon" href="/favicon-bold-32.png" type="image/png" sizes="32x32">
        <link rel="shortcut icon" href="/favicon-bold-64.png" type="image/png">
        <link rel="apple-touch-icon" href="/af-fav2.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/js/index.css', 'resources/js/main.tsx'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
      @inertia
    </body>
</html>
