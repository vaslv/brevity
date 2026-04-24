<script>
    (() => {
        try {
            const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (!tz) return;
            if (document.cookie.split('; ').some((c) => c.startsWith('tz=' + encodeURIComponent(tz)))) return;
            document.cookie = 'tz=' + encodeURIComponent(tz) + '; path=/; max-age=31536000; SameSite=Lax';
        } catch (_) {}
    })();
</script>
