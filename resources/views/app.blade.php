<!doctype html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="referrer" content="no-referrer">
        <link rel="icon" type="image/svg+xml" href="/assets/brand/prospera-symbol.svg">
        @viteReactRefresh
        @vite('resources/js/main.tsx')
        <title>{{ config('app.name') }}</title>
    </head>
    <body>
        <div id="root"></div>
        <script src="/assets/js/jquery.min.js" data-loaded="true"></script>
        <script src="/assets/plugins/peity/jquery.peity.min.js" data-loaded="true"></script>
        <script src="/assets/plugins/jquery-sparkline/jquery.sparkline.min.js" data-loaded="true"></script>
    </body>
</html>
