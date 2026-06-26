<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GA4Service
{
    private string $measurementId;
    private string $apiSecret;
    private bool $enabled;
    private bool $debug;

    private const ENDPOINT        = 'https://www.google-analytics.com/mp/collect';
    private const DEBUG_ENDPOINT  = 'https://www.google-analytics.com/debug/mp/collect';

    public function __construct()
    {
        $this->measurementId = config('services.ga4.measurement_id');
        $this->apiSecret     = config('services.ga4.api_secret');
        $this->enabled       = config('services.ga4.enabled', true);
        $this->debug         = config('services.ga4.debug', false);
    }

    /**
     * Tracker tıklamasını GA4'e gönder.
     *
     * @param  array  $clickData   TrackerClick modeli veya ham array
     */
    public function sendTrackerClick(array $clickData): bool
    {
        return $this->send(
            clientId: $clickData['client_id'],
            eventName: 'tracker_click',
            params: $this->buildClickParams($clickData),
        );
    }

    /**
     * Rotator tıklamasını GA4'e gönder.
     */
    public function sendRotatorClick(array $clickData): bool
    {
        return $this->send(
            clientId: $clickData['client_id'],
            eventName: 'rotator_click',
            params: $this->buildClickParams($clickData),
        );
    }

    // -------------------------------------------------------------------------
    // Core
    // -------------------------------------------------------------------------

    private function send(string $clientId, string $eventName, array $params): bool
    {
        if (! $this->enabled || blank($this->measurementId) || blank($this->apiSecret)) {
            return false;
        }

        // session_id ve engagement_time_msec her event'te OLMALI (GA4 zorunluluğu)
        $params['session_id']            ??= $this->deriveSessionId($clientId);
        $params['engagement_time_msec']  ??= 1;

        $payload = [
            'client_id' => $clientId,
            'events'    => [[
                'name'   => $eventName,
                'params' => $params,
            ]],
        ];

        try {
            $response = Http::timeout(5)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->endpoint(), $payload);

            if ($this->debug) {
                Log::debug('GA4 debug response', [
                    'payload'  => $payload,
                    'status'   => $response->status(),
                    'body'     => $response->json(),
                ]);
            }

            return $response->successful();
        } catch (\Throwable $e) {
            // GA4 hatası redirect'i patlatmasın, sadece logla
            Log::warning('GA4 event gönderilemedi', [
                'event'   => $eventName,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Builders
    // -------------------------------------------------------------------------

    private function buildClickParams(array $data): array
    {
        return array_filter([
            'tracker_id'      => (string) ($data['tracker_id']   ?? null),  // int değil string olmalı
            'rotator_id'      => (string) ($data['rotator_id']   ?? null),
            'destination_url' => $data['destination']  ?? null,
            'country'         => $data['country']      ?? null,
            'device_type'     => $data['device_type']  ?? null,
            'browser'         => $data['browser']      ?? null,
            'os'              => $data['os']            ?? null,
            'referrer'        => $data['referrer']      ?? null,
            'utm_source'      => $data['utm_source']    ?? null,
            'utm_medium'      => $data['utm_medium']    ?? null,
            'utm_campaign'    => $data['utm_campaign']  ?? null,
            'is_unique'       => $data['is_unique'] ? '1' : '0',  // bool değil string
            'click_id'        => (string) ($data['click_id']     ?? null),  // int değil string
            'session_id'      => $data['session_id']    ?? null,
            'engagement_time_msec' => 1,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * client_id'den basit bir session_id türet.
     * Gerçek session takibi istiyorsan bunu cookie/session'dan al.
     */
    private function deriveSessionId(string $clientId): string
    {
        return substr(md5($clientId . date('YmdH')), 0, 16);
    }

    private function endpoint(): string
    {
        return $this->debug ? self::DEBUG_ENDPOINT : self::ENDPOINT;
    }
}
