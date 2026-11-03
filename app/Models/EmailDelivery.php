<?php

namespace App\Models;

use App\Jobs\SendEmailDelivery;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property array<string, mixed> $content
 * @property Carbon|null $sent_at
 * @property Carbon|null $cancelled_at
 */
#[Fillable(['deduplication_key', 'user_id', 'content', 'sent_at', 'cancelled_at'])]
class EmailDelivery extends Model
{
    protected function casts(): array
    {
        return ['content' => 'encrypted:array', 'sent_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @param array<string, mixed> $content */
    public static function enqueue(string $key, User $recipient, array $content): void
    {
        $delivery = static::firstOrCreate(['deduplication_key' => $key], [
            'user_id' => $recipient->id,
            'content' => array_merge($content, ['recipient_name' => $recipient->name, 'recipient_email' => $recipient->email]),
        ]);

        if ($delivery->wasRecentlyCreated) {
            SendEmailDelivery::dispatch($delivery->id)->afterCommit();
        }
    }
}
