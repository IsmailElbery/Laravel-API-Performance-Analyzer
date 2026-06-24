<?php

namespace ApiPerformanceAnalyzer\Alerts;

use ApiPerformanceAnalyzer\Models\AlertEvent;
use ApiPerformanceAnalyzer\Models\AlertRule;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Dispatches a fired alert to its configured channels (Slack incoming webhook,
 * email). Channels come from the rule, falling back to apa.alerts.channels.
 * Delivery failures are swallowed (an alert system must not throw into a cron).
 */
class AlertNotifier
{
    public function __construct(protected Config $config) {}

    public function send(AlertRule $rule, AlertEvent $event): void
    {
        $channels = $rule->channels ?: (array) $this->config->get('apa.alerts.channels', []);
        $message = $this->message($rule, $event);

        foreach ($channels as $channel) {
            try {
                match ($channel) {
                    'slack' => $this->slack($message),
                    'mail' => $this->mail($rule, $message),
                    default => null,
                };
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    protected function message(AlertRule $rule, AlertEvent $event): string
    {
        $target = $rule->uri ?: 'all endpoints';

        return sprintf(
            "[APA alert] %s — %s on %s is %s (%s %s). Window: %d min.",
            $rule->name,
            $event->metric,
            $target,
            rtrim(rtrim(number_format($event->observed_value, 2), '0'), '.'),
            $rule->operator,
            rtrim(rtrim(number_format($event->threshold, 2), '0'), '.'),
            $rule->window_minutes,
        );
    }

    protected function slack(string $message): void
    {
        $webhook = $this->config->get('apa.alerts.slack_webhook');
        if (! $webhook) {
            return;
        }

        Http::post($webhook, ['text' => $message]);
    }

    protected function mail(AlertRule $rule, string $message): void
    {
        $to = $this->config->get('apa.alerts.mail_to');
        if (! $to) {
            return;
        }

        Mail::raw($message, function ($mail) use ($to, $rule) {
            $mail->to($to)->subject('[APA] '.$rule->name);
        });
    }
}
