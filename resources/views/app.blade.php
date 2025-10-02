<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
         <script type="module">
                import RefreshRuntime from 'http://localhost:5174/@react-refresh'; // Use your Vite port
                RefreshRuntime.injectIntoGlobalHook(window);
                window.$RefreshReg$ = () => {};
                window.$RefreshSig$ = () => (type) => type;
                window.__vite_plugin_react_preamble_installed__ = true;
         </script>
        

        {{-- Inline style to set the HTML background color --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }
        </style>

        <title inertia>{{ config('app.name', 'AppliFlow') }}</title>

        <link rel="icon" href="/favicon-bold-256.png" type="image/png" sizes="256x256">
        <link rel="icon" href="/favicon-bold-64.png" type="image/png" sizes="64x64">
        <link rel="icon" href="/favicon-bold-32.png" type="image/png" sizes="32x32">
        <link rel="shortcut icon" href="/favicon-bold-64.png" type="image/png">
        <link rel="apple-touch-icon" href="/af-fav2.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/js/index.css', 'resources/js/main.tsx'])
        
    </head>
    <body class="font-sans antialiased">
      <div id="root"></div>
    </body>
</html>
