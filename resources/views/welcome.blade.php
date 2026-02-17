<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
            margin: 0;
        }

        .page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .content {
            flex: 1;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .footer {
            background: #f2f2f2;
            padding: 32px 0;
        }

        .content {
            padding: 32px 0;
        }
    </style>
</head>
<body>
<div class='page'>
    <main class='content'>
        <div class='container'>
            {!! setting('content.main') !!}
        </div>
    </main>

    <footer class='footer'>
        <div class='container'>
            {!! setting('content.footer') !!}
        </div>
    </footer>
</div>
</body>
</html>
