<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-2LXRWHC1C4"></script>
<script>
    window.dataLayer = window.dataLayer || [];

    function gtag() {
        dataLayer.push(arguments);
    }
    gtag('js', new Date());

    gtag('config', 'G-2LXRWHC1C4');
</script>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
<script>
    window.setAppAppearance = (appearance) => {
        const mode = appearance === 'dark' ? 'dark' : 'light';

        window.localStorage.setItem('flux.appearance', mode);
        document.documentElement.classList.toggle('dark', mode === 'dark');
        document.documentElement.style.colorScheme = mode;

        if (
            window.Flux &&
            typeof window.Flux === 'object' &&
            'appearance' in window.Flux &&
            window.Flux.appearance !== mode
        ) {
            window.Flux.appearance = mode;
        }

        return mode;
    }

    window.setAppAppearance(window.localStorage.getItem('flux.appearance') || 'dark');
</script>