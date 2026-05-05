<?php

namespace App\Log;

use App\Mail\ErrorLogNotification;
use App\Models\Group;
use App\Models\LogViewer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Monolog\Handler\AbstractProcessingHandler;
use Throwable;

class CustomLogHandler extends AbstractProcessingHandler
{
    protected function write(array $record): void
    {
        $trace_stack = null;
        $app_running_in_console = app()->runningInConsole();

        foreach (data_get($record, 'context', []) as $key => $value) {
            if ($value instanceof Throwable) {
                $record['context'][$key] = [
                    'message' => $value->getMessage(),
                    'class' => get_class($value),
                    'file' => $value->getFile(),
                    'line' => $value->getLine(),
                ];

                $trace_stack[$key] = $value->getTrace();
            }
        }

        $context = data_get($record, 'context');
        $message = data_get($record, 'message');
        $levelName = data_get($record, 'level_name');

        // Add user information to context
        $user = auth()->user();
        if ($user) {
            $current_group_id = session('current_group', $user->default_group);
            $group_title = Group::where('id', $current_group_id)->value('title');

            $context['user_id'] = $user->id;
            $context['user_name'] = $user->user_name;
            $context['user_email'] = $user->email;
            $context['user_group'] = $group_title;
        }

        if (! $app_running_in_console) {
            $context['session'] = session()->all();
        }

        $url = request()?->fullUrl();

        if ($app_running_in_console) {
            $url = 'php ' . implode(' ', $_SERVER['argv']);
        }

        $now = now();

        $data = [
            'level' => data_get($record, 'level'),
            'level_name' => $levelName,
            'message' => $message,
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE),
            'trace_stack' => json_encode($trace_stack, JSON_UNESCAPED_UNICODE),
            'ip_address' => data_get($context, 'ip_address', request()?->ip()),
            'user_agent' => data_get($context, 'user_agent', request()?->userAgent()),
            'http_method' => data_get($context, 'http_method', request()?->method()),
            'url' => $url,
            'extra' => json_encode(data_get($record, 'extra', []), JSON_UNESCAPED_UNICODE),
            'referer_url' => request()?->header('referer'),
            'headers' => json_encode(request()?->headers->all() ?? [], JSON_UNESCAPED_UNICODE),
            'log_hash' => $this->generateLogHash($message, $context),
            'solved' => $levelName !== 'ERROR',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        try {
            $log_id = LogViewer::insertGetId($data);

            // Send email notification for ERROR level logs in production and local only
            if ($levelName === 'ERROR' && app()->environment('production', 'local')) {
                $this->sendErrorNotification($data, $log_id);
            }
        } catch (Throwable $throwable) {
            // Log the error to stack channel to prevent logging loops
            Log::channel('stack')->error('Failed to insert log into database', [
                'error' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'trace' => $throwable->getTraceAsString(),
                'original_log_data' => $data,
            ]);
        }
    }

    /**
     * Generate a unique hash for the error based on message, file, and line
     */
    protected function generateLogHash(string $message, array $context): string
    {
        $file = $context['file'] ?? '';
        $line = $context['line'] ?? '';

        return md5($message . '|' . $file . '|' . $line);
    }

    /**
     * Send error notification email
     */
    protected function sendErrorNotification(array $data, int $logId): void
    {
        try {
            // Decode JSON fields back to arrays for email display
            $context = json_decode($data['context'], true) ?? [];

            $emailData = [
                'log_id' => $logId,
                'message' => data_get($data, 'message'),
                'level_name' => data_get($data, 'level_name'),
                'timestamp' => data_get($data, 'created_at', now())?->format('Y-m-d h:i:s A'),
                'url' => data_get($data, 'url'),
                'referer_url' => data_get($data, 'referer_url'),
                'http_method' => data_get($data, 'http_method'),
                'ip_address' => data_get($data, 'ip_address'),
                'user_name' => data_get($context, 'user_name'),
                'user_email' => data_get($context, 'user_email'),
                'user_group' => data_get($context, 'user_group'),
                'log_hash' => data_get($data, 'log_hash'),
                'context' => $context,
            ];

            Mail::to([config('constants.mails.ticketing_dev_mail')])
                ->queue(new ErrorLogNotification($emailData));
        } catch (Throwable $e) {
            // Log email sending failures to stack channel
            Log::channel('stack')->error('Failed to send error notification email', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'log_id' => $logId,
                'email_recipient' => config('constants.mails.ticketing_dev_mail'),
            ]);
        }
    }
}
