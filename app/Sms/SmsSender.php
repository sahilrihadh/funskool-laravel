<?php

namespace App\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends transactional SMS through the configured driver (services.sms.driver):
 *  - log    : writes to the log (default, for development)
 *  - msg91  : MSG91 Flow API with DLT-approved templates (India)
 *  - twilio : Twilio Programmable Messaging (international)
 */
class SmsSender
{
    public function send(string $phone, SmsMessage $message): void
    {
        $to = $this->normalise($phone);

        if ($to === null) {
            return;
        }

        match (config('services.sms.driver')) {
            'msg91' => $this->viaMsg91($to, $message),
            'twilio' => $this->viaTwilio($to, $message),
            default => Log::info("SMS to {$to}: {$message->text}"),
        };
    }

    public function isConfigured(): bool
    {
        return match (config('services.sms.driver')) {
            'msg91' => filled(config('services.msg91.auth_key')),
            'twilio' => filled(config('services.twilio.sid')) && filled(config('services.twilio.token')) && filled(config('services.twilio.from')),
            default => false,
        };
    }

    protected function viaMsg91(string $to, SmsMessage $message): void
    {
        $templateId = config("services.msg91.templates.{$message->template}");

        if (! $templateId) {
            Log::warning("MSG91 template for \"{$message->template}\" is not configured; SMS skipped.");

            return;
        }

        $response = Http::withHeaders(['authkey' => config('services.msg91.auth_key')])
            ->acceptJson()
            ->timeout(15)
            ->post('https://control.msg91.com/api/v5/flow', [
                'template_id' => $templateId,
                'short_url' => '0',
                'recipients' => [['mobiles' => ltrim($to, '+'), ...$message->variables]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('MSG91 SMS failed: '.$response->body());
        }
    }

    protected function viaTwilio(string $to, SmsMessage $message): void
    {
        $sid = config('services.twilio.sid');

        $response = Http::asForm()
            ->withBasicAuth($sid, (string) config('services.twilio.token'))
            ->timeout(15)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => config('services.twilio.from'),
                'To' => $to,
                'Body' => $message->text,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Twilio SMS failed: '.$response->body());
        }
    }

    /** "98765 43210" → "+919876543210" */
    protected function normalise(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) < 8) {
            return null;
        }

        if (str_starts_with(trim($phone), '+')) {
            return '+'.$digits;
        }

        $digits = ltrim($digits, '0');

        return strlen($digits) <= 10 ? '+'.config('services.sms.default_country_code').$digits : '+'.$digits;
    }
}
