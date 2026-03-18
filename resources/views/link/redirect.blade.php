<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Redirect</title>
    <style>
        :root {
            color-scheme: light;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            background: linear-gradient(140deg, #f7f7fb 0%, #eceff5 100%);
            color: #0f172a;
        }

        .card {
            width: min(92vw, 560px);
            background: #ffffff;
            border: 1px solid #d8deea;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 10px 35px rgba(15, 23, 42, 0.08);
        }

        h1 {
            margin: 0 0 10px 0;
            font-size: 24px;
            line-height: 1.2;
        }

        p {
            margin: 0 0 12px 0;
            line-height: 1.45;
            color: #334155;
        }

        .target {
            display: inline-block;
            word-break: break-all;
            color: #0b57d0;
            text-decoration: none;
        }

        .button {
            display: inline-block;
            margin-top: 8px;
            padding: 10px 16px;
            border-radius: 10px;
            background: #0b57d0;
            color: #ffffff;
            font-weight: 600;
            text-decoration: none;
        }
    </style>
</head>
<body>
<main class="card">
    <h1>You are being redirected</h1>
    <p>Destination:</p>
    <p><a class="target" href="{{ $targetUrl }}">{{ $targetUrl }}</a></p>

    @if($transitionMode === \App\Services\Links\TransitionMode::Delayed)
        <p>Automatic redirect in <span id="countdown">{{ $countdownSeconds }}</span> second(s).</p>
        <a class="button" href="{{ $targetUrl }}">Go now</a>
        <script>
            (function () {
                var targetUrl = @json($targetUrl);
                var seconds = Number(@json($countdownSeconds));
                var countdown = document.getElementById('countdown');

                var timer = setInterval(function () {
                    seconds -= 1;

                    if (seconds <= 0) {
                        clearInterval(timer);
                        window.location.replace(targetUrl);
                        return;
                    }

                    countdown.textContent = String(seconds);
                }, 1000);
            })();
        </script>
    @else
        <p>Click the button to continue.</p>
        <a class="button" href="{{ $targetUrl }}">Continue</a>
    @endif
</main>
</body>
</html>
