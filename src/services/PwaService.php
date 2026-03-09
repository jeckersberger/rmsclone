<?php
/**
 * PwaService - Progressive Web App Unterstuetzung
 *
 * Generiert manifest.json und Service-Worker-Registrierung
 * fuer Offline-Faehigkeit und mobile Installation.
 */
class PwaService
{
    /**
     * Web App Manifest generieren
     */
    public static function getManifest(string $appName = 'MyRMS', string $baseUrl = '/'): array
    {
        return [
            'name' => $appName,
            'short_name' => 'MyRMS',
            'description' => 'Rental Management System - Mietverwaltung',
            'start_url' => $baseUrl,
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#ffffff',
            'theme_color' => '#007bff',
            'lang' => 'de-DE',
            'icons' => [
                ['src' => '/img/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'categories' => ['business', 'productivity'],
            'shortcuts' => [
                ['name' => 'Dashboard', 'url' => '/dashboard', 'icon' => '/img/icon-192.png'],
                ['name' => 'Projekte', 'url' => '/projects', 'icon' => '/img/icon-192.png'],
                ['name' => 'Inventur', 'url' => '/inventory', 'icon' => '/img/icon-192.png'],
            ],
        ];
    }

    /**
     * Service Worker JavaScript generieren
     */
    public static function getServiceWorker(): string
    {
        return <<<'JS'
const CACHE_NAME = 'adamrms-v1';
const OFFLINE_URL = '/offline.html';

const PRECACHE_URLS = [
    '/',
    '/offline.html',
    '/css/adminlte.min.css',
    '/js/adminlte.min.js',
    '/img/icon-192.png',
];

// Install: precache essential resources
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS))
    );
    self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Fetch: network-first with cache fallback
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    // API calls: network only
    if (event.request.url.includes('/api/')) return;

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                if (response.ok) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                }
                return response;
            })
            .catch(() =>
                caches.match(event.request).then((cached) => cached || caches.match(OFFLINE_URL))
            )
    );
});
JS;
    }
}
