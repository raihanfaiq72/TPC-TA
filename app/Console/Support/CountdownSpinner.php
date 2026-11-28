<?php

namespace App\Console\Support;

use Illuminate\Support\Carbon;
use Laravel\Prompts\Spinner;

class CountdownSpinner extends Spinner
{
    /**
     * @var array<string>
     */
    private array $frames = ['⠂', '⠒', '⠐', '⠰', '⠠', '⠤', '⠄', '⠆'];

    public function __construct(string $message = '', private readonly ?int $deadline = null)
    {
        parent::__construct($message);
    }

    protected function renderTheme(): string
    {
        $message = $this->message;

        if ($this->deadline !== null) {
            $remaining = max(0, $this->deadline - Carbon::now('Asia/Jakarta')->getTimestamp());
            $message .= ' · sisa '.self::formatRemaining($remaining);
        }

        return ' '.$this->frames[$this->count % count($this->frames)].' '.$message.PHP_EOL;
    }

    private static function formatRemaining(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return str_pad((string) $hours, 2, '0', STR_PAD_LEFT)
            .':'.str_pad((string) $minutes, 2, '0', STR_PAD_LEFT)
            .':'.str_pad((string) $remainingSeconds, 2, '0', STR_PAD_LEFT);
    }
}
