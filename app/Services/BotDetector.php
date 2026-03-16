<?php

namespace App\Services;

class BotDetector
{
    public static function isBot(string $ua): bool
    {
        if (empty($ua)) return true;

        // Fake Chrome with impossible build numbers (5+ digits)
        if (preg_match('/Chrome\/[\d]+\.[\d]+\.(\d{5,})/', $ua)) return true;

        // Very old browsers that wouldn't run modern JS (Chrome <70 is from 2018 or older)
        if (preg_match('/Chrome\/[1-6]\d\./', $ua)) return true;

        // Known headless/scraper signatures
        if (preg_match('/HeadlessChrome|PhantomJS|Selenium|Puppeteer/i', $ua)) return true;

        // Known bot user agents
        if (preg_match('/bot|crawler|spider|scraper|wget|curl|python-requests|Go-http-client|Java\/|libwww|httpclient/i', $ua)) return true;

        return false;
    }

    public static function isVerifiedBot(string $ua): bool
    {
        return (bool) preg_match('/Googlebot|Bingbot|Slurp|DuckDuckBot|Baiduspider|YandexBot|YandexRenderResourcesBot|facebookexternalhit|Twitterbot|LinkedInBot|Discordbot|Applebot|AhrefsBot|Barkrowler|UptimeRobot|GPTBot|ClaudeBot|PetalBot|SemrushBot|MJ12bot|DotBot/i', $ua);
    }
}
