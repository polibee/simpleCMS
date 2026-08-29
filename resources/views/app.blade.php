<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'CMSForum') }}</title>
    <script>
        window.__APP_NAME__ = {{ Js::from(config('app.name', 'CMSForum')) }};
    </script>

    @php
        // §七十六 广告平台所有权验证：原样注入 <head>（管理端可信输入，验证类脚本不受 Cookie 门控）
        $adsVerification = trim((string) \App\Support\SiteSettings::get('ads_verification_code'));
        // §七十七 Cookie 授权：横幅开启时，分析/广告脚本仅在「接受全部」后注入
        $cookieBannerEnabled = \App\Support\SiteSettings::bool('cookie_banner_enabled');
        $analyticsAllowed = ! $cookieBannerEnabled || request()->cookie('cookie_consent') === 'full';
        // 统计代码配置中心：GA / Clarity / 百度 / Plausible / 自定义
        $gaId = trim((string) \App\Support\SiteSettings::get('ga_property_id'));
        $clarityId = trim((string) \App\Support\SiteSettings::get('microsoft_clarity_id'));
        $baiduCode = trim((string) \App\Support\SiteSettings::get('baidu_tongji_code'));
        $plausibleDomain = trim((string) \App\Support\SiteSettings::get('plausible_domain'));
        $customAnalytics = trim((string) \App\Support\SiteSettings::get('custom_analytics_html'));
        $statsOn = $analyticsAllowed && ! app()->runningInConsole();
    @endphp

    {!! $adsVerification !!}

    @if($gaId !== '' && $statsOn)
        {{-- Google Analytics（GA4）--}}
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}" defer></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $gaId }}', { anonymize_ip: true });
        </script>
    @endif

    @if($clarityId !== '' && $statsOn)
        {{-- Microsoft Clarity --}}
        <script>
            (function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
            t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
            y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script","{{ $clarityId }}");
        </script>
    @endif

    @if($baiduCode !== '' && $statsOn)
        {{-- 百度统计 --}}
        <script>
            var _hmt = _hmt || [];
            (function(){var hm=document.createElement("script");hm.src="https://hm.baidu.com/hm.js?{{ $baiduCode }}";
            var s=document.getElementsByTagName("script")[0];s.parentNode.insertBefore(hm,s);})();
        </script>
    @endif

    @if($plausibleDomain !== '' && $statsOn)
        {{-- Plausible --}}
        <script defer data-domain="{{ $plausibleDomain }}" src="https://plausible.io/js/script.js"></script>
    @endif

    @if($customAnalytics !== '' && $statsOn)
        {{-- 其他统计代码（管理端可信输入） --}}
        {!! $customAnalytics !!}
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
