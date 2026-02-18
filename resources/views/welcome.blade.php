<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
            margin: 0;
        }

        body {
            font-family: "Inter", sans-serif;
            font-optical-sizing: auto;
            font-weight: 400;
            font-style: normal;
        }

        .page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: lab(27.1134% -.956401 -12.3224);
        }

        .content {
            flex: 1;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 .5rem;
        }

        .footer {
            background-color: lab(1.90334 0.278696 -5.48866);
            color: lab(98.1434 -0.369519 -1.05966);
            padding: 2.5rem 0 6rem;
        }

        .content {
            padding: 2.5rem 0;
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
